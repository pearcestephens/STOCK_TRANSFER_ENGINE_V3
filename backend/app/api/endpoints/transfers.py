"""
Stock transfer endpoints
"""
from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from sqlalchemy import and_, or_

from app.db.database import get_db
from app.models.transfer import Transfer, TransferItem, TransferStatus, TransferPriority
from app.models.stock import Stock, StockMovement, MovementType
from app.models.user import User, UserRole
from app.api.endpoints.auth import get_current_active_user, require_role

router = APIRouter()


@router.get("/")
async def list_transfers(
    skip: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=1000),
    status: Optional[TransferStatus] = Query(None),
    from_location: Optional[str] = Query(None),
    to_location: Optional[str] = Query(None),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get list of transfers with filtering"""
    query = db.query(Transfer)
    
    # Apply filters
    if status:
        query = query.filter(Transfer.status == status)
    if from_location:
        query = query.filter(Transfer.from_location.ilike(f"%{from_location}%"))
    if to_location:
        query = query.filter(Transfer.to_location.ilike(f"%{to_location}%"))
    
    # Non-admin users see only their transfers
    if current_user.role not in [UserRole.ADMIN, UserRole.MANAGER]:
        query = query.filter(Transfer.requested_by == current_user.id)
    
    total = query.count()
    transfers = query.order_by(Transfer.created_at.desc()).offset(skip).limit(limit).all()
    
    return {
        "items": transfers,
        "total": total,
        "skip": skip,
        "limit": limit
    }


@router.post("/")
async def create_transfer(
    transfer_data: dict,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]))
):
    """Create a new stock transfer"""
    # Generate transfer number
    transfer_count = db.query(Transfer).count()
    transfer_number = f"TRF-{transfer_count + 1:06d}"
    
    # Create transfer
    transfer = Transfer(
        transfer_number=transfer_number,
        from_location=transfer_data["from_location"],
        to_location=transfer_data["to_location"],
        reason=transfer_data.get("reason"),
        notes=transfer_data.get("notes"),
        priority=transfer_data.get("priority", TransferPriority.NORMAL),
        requested_date=transfer_data.get("requested_date"),
        scheduled_date=transfer_data.get("scheduled_date"),
        requested_by=current_user.id,
        requires_approval=transfer_data.get("requires_approval", False)
    )
    
    db.add(transfer)
    db.commit()
    db.refresh(transfer)
    
    # Add transfer items
    for item_data in transfer_data["items"]:
        stock = db.query(Stock).filter(Stock.id == item_data["stock_id"]).first()
        if not stock:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Stock with ID {item_data['stock_id']} not found"
            )
        
        if stock.available_stock < item_data["quantity"]:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Insufficient stock for {stock.sku}. Available: {stock.available_stock}, Requested: {item_data['quantity']}"
            )
        
        # Reserve stock
        stock.reserved_stock += item_data["quantity"]
        stock.available_stock -= item_data["quantity"]
        
        # Create transfer item
        transfer_item = TransferItem(
            transfer_id=transfer.id,
            stock_id=item_data["stock_id"],
            quantity_requested=item_data["quantity"],
            batch_number=item_data.get("batch_number"),
            expiry_date=item_data.get("expiry_date"),
            notes=item_data.get("notes")
        )
        
        db.add(transfer_item)
    
    # Set status
    if transfer.requires_approval:
        transfer.status = TransferStatus.PENDING
    else:
        transfer.status = TransferStatus.IN_TRANSIT
        transfer.started_date = transfer.created_at
    
    db.commit()
    db.refresh(transfer)
    
    return transfer


@router.get("/{transfer_id}")
async def get_transfer(
    transfer_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get a specific transfer"""
    transfer = db.query(Transfer).filter(Transfer.id == transfer_id).first()
    if not transfer:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Transfer not found"
        )
    
    # Check permissions
    if (current_user.role not in [UserRole.ADMIN, UserRole.MANAGER] and 
        transfer.requested_by != current_user.id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Not authorized to view this transfer"
        )
    
    return transfer


@router.put("/{transfer_id}/approve")
async def approve_transfer(
    transfer_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER]))
):
    """Approve a pending transfer"""
    transfer = db.query(Transfer).filter(Transfer.id == transfer_id).first()
    if not transfer:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Transfer not found"
        )
    
    if transfer.status != TransferStatus.PENDING:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Transfer is not pending approval"
        )
    
    transfer.status = TransferStatus.IN_TRANSIT
    transfer.approved_by = current_user.id
    transfer.started_date = transfer.updated_at
    
    db.commit()
    
    return {"message": "Transfer approved and started"}


@router.put("/{transfer_id}/complete")
async def complete_transfer(
    transfer_id: int,
    completion_data: dict,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]))
):
    """Complete a transfer"""
    transfer = db.query(Transfer).filter(Transfer.id == transfer_id).first()
    if not transfer:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Transfer not found"
        )
    
    if transfer.status != TransferStatus.IN_TRANSIT:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Transfer is not in transit"
        )
    
    # Process each item
    for item_data in completion_data.get("items", []):
        transfer_item = db.query(TransferItem).filter(
            and_(
                TransferItem.transfer_id == transfer_id,
                TransferItem.stock_id == item_data["stock_id"]
            )
        ).first()
        
        if not transfer_item:
            continue
        
        # Update quantities
        transfer_item.quantity_shipped = item_data.get("quantity_shipped", transfer_item.quantity_requested)
        transfer_item.quantity_received = item_data.get("quantity_received", transfer_item.quantity_shipped)
        transfer_item.quantity_damaged = item_data.get("quantity_damaged", 0)
        
        # Update stock quantities
        stock = transfer_item.stock
        
        # Unreserve the original quantity
        stock.reserved_stock -= transfer_item.quantity_requested
        
        # Adjust stock based on what was actually shipped/received
        quantity_transferred = transfer_item.quantity_received
        quantity_lost = (transfer_item.quantity_requested - 
                        transfer_item.quantity_received - 
                        transfer_item.quantity_damaged)
        
        # Create stock movements
        # Outbound movement from source
        outbound_movement = StockMovement(
            stock_id=stock.id,
            movement_type=MovementType.OUTBOUND,
            quantity=transfer_item.quantity_shipped,
            from_location=transfer.from_location,
            reference_number=transfer.transfer_number,
            reference_type="Transfer",
            reason=f"Transfer to {transfer.to_location}",
            user_id=current_user.id
        )
        db.add(outbound_movement)
        
        # If there's damage, record it
        if transfer_item.quantity_damaged > 0:
            damage_movement = StockMovement(
                stock_id=stock.id,
                movement_type=MovementType.DAMAGED,
                quantity=transfer_item.quantity_damaged,
                reference_number=transfer.transfer_number,
                reference_type="Transfer",
                reason="Damaged during transfer",
                user_id=current_user.id
            )
            db.add(damage_movement)
        
        # Update available stock
        stock.available_stock = stock.current_stock - stock.reserved_stock
    
    # Update transfer status
    transfer.status = TransferStatus.COMPLETED
    transfer.completed_by = current_user.id
    transfer.completed_date = transfer.updated_at
    transfer.actual_cost = completion_data.get("actual_cost", 0)
    
    db.commit()
    
    return {"message": "Transfer completed successfully"}


@router.put("/{transfer_id}/cancel")
async def cancel_transfer(
    transfer_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]))
):
    """Cancel a transfer"""
    transfer = db.query(Transfer).filter(Transfer.id == transfer_id).first()
    if not transfer:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Transfer not found"
        )
    
    if not transfer.can_be_cancelled:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Transfer cannot be cancelled in current status"
        )
    
    # Check permissions
    if (current_user.role not in [UserRole.ADMIN, UserRole.MANAGER] and 
        transfer.requested_by != current_user.id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Not authorized to cancel this transfer"
        )
    
    # Unreserve all stock
    for item in transfer.items:
        stock = item.stock
        stock.reserved_stock -= item.quantity_requested
        stock.available_stock += item.quantity_requested
    
    transfer.status = TransferStatus.CANCELLED
    db.commit()
    
    return {"message": "Transfer cancelled successfully"}


@router.get("/dashboard/stats")
async def get_transfer_stats(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get transfer dashboard statistics"""
    stats = {}
    
    # Base query
    query = db.query(Transfer)
    if current_user.role not in [UserRole.ADMIN, UserRole.MANAGER]:
        query = query.filter(Transfer.requested_by == current_user.id)
    
    # Status counts
    for status in TransferStatus:
        stats[f"{status.value}_count"] = query.filter(Transfer.status == status).count()
    
    # Pending approvals (for managers/admins)
    if current_user.role in [UserRole.ADMIN, UserRole.MANAGER]:
        stats["pending_approvals"] = (
            db.query(Transfer)
            .filter(Transfer.status == TransferStatus.PENDING)
            .filter(Transfer.requires_approval == True)
            .count()
        )
    
    # Recent transfers
    recent_transfers = (
        query.order_by(Transfer.created_at.desc())
        .limit(5)
        .all()
    )
    
    stats["recent_transfers"] = [
        {
            "id": t.id,
            "transfer_number": t.transfer_number,
            "status": t.status,
            "from_location": t.from_location,
            "to_location": t.to_location,
            "created_at": t.created_at
        }
        for t in recent_transfers
    ]
    
    return stats