"""
Stock management endpoints
"""
from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from sqlalchemy import or_, and_

from app.db.database import get_db
from app.models.stock import Stock, StockMovement, StockLocation, StockStatus, StockCategory, MovementType
from app.models.user import User, UserRole
from app.api.endpoints.auth import get_current_active_user, require_role
from app.schemas.stock import (
    StockCreate, StockUpdate, StockResponse, StockListResponse,
    StockMovementCreate, StockMovementResponse,
    StockLocationResponse
)

router = APIRouter()


@router.get("/", response_model=StockListResponse)
async def list_stocks(
    skip: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=1000),
    search: Optional[str] = Query(None),
    category: Optional[StockCategory] = Query(None),
    status: Optional[StockStatus] = Query(None),
    low_stock_only: bool = Query(False),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get list of stocks with filtering and pagination"""
    query = db.query(Stock)
    
    # Apply filters
    if search:
        search_filter = or_(
            Stock.sku.ilike(f"%{search}%"),
            Stock.name.ilike(f"%{search}%"),
            Stock.description.ilike(f"%{search}%")
        )
        query = query.filter(search_filter)
    
    if category:
        query = query.filter(Stock.category == category)
    
    if status:
        query = query.filter(Stock.status == status)
    
    if low_stock_only:
        query = query.filter(Stock.available_stock <= Stock.minimum_stock)
    
    # Get total count before pagination
    total = query.count()
    
    # Apply pagination
    stocks = query.offset(skip).limit(limit).all()
    
    return {
        "items": stocks,
        "total": total,
        "skip": skip,
        "limit": limit
    }


@router.post("/", response_model=StockResponse)
async def create_stock(
    stock: StockCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER]))
):
    """Create a new stock item"""
    # Check if SKU already exists
    if db.query(Stock).filter(Stock.sku == stock.sku).first():
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="SKU already exists"
        )
    
    # Create stock
    db_stock = Stock(**stock.dict())
    db_stock.available_stock = db_stock.current_stock - db_stock.reserved_stock
    
    db.add(db_stock)
    db.commit()
    db.refresh(db_stock)
    
    # Create initial stock movement if current_stock > 0
    if db_stock.current_stock > 0:
        movement = StockMovement(
            stock_id=db_stock.id,
            movement_type=MovementType.INBOUND,
            quantity=db_stock.current_stock,
            unit_cost=db_stock.unit_cost,
            to_location=db_stock.location_code,
            reason="Initial stock entry",
            user_id=current_user.id
        )
        db.add(movement)
        db.commit()
    
    return db_stock


@router.get("/{stock_id}", response_model=StockResponse)
async def get_stock(
    stock_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get a specific stock item"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    return stock


@router.put("/{stock_id}", response_model=StockResponse)
async def update_stock(
    stock_id: int,
    stock_update: StockUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER]))
):
    """Update a stock item"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    # Update fields
    for field, value in stock_update.dict(exclude_unset=True).items():
        setattr(stock, field, value)
    
    # Recalculate available stock
    stock.available_stock = stock.current_stock - stock.reserved_stock
    
    db.commit()
    db.refresh(stock)
    return stock


@router.delete("/{stock_id}")
async def delete_stock(
    stock_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN]))
):
    """Delete a stock item"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    db.delete(stock)
    db.commit()
    return {"message": "Stock deleted successfully"}


@router.post("/{stock_id}/movements", response_model=StockMovementResponse)
async def create_stock_movement(
    stock_id: int,
    movement: StockMovementCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]))
):
    """Create a stock movement"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    # Create movement
    db_movement = StockMovement(
        stock_id=stock_id,
        user_id=current_user.id,
        **movement.dict()
    )
    
    # Update stock quantities based on movement type
    if movement.movement_type in [MovementType.INBOUND, MovementType.RETURN]:
        stock.current_stock += movement.quantity
    elif movement.movement_type in [MovementType.OUTBOUND, MovementType.DAMAGED, MovementType.EXPIRED]:
        if stock.current_stock < movement.quantity:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Insufficient stock for this movement"
            )
        stock.current_stock -= movement.quantity
    elif movement.movement_type == MovementType.ADJUSTMENT:
        # For adjustments, quantity can be positive or negative
        new_stock = stock.current_stock + movement.quantity
        if new_stock < 0:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Stock cannot be negative after adjustment"
            )
        stock.current_stock = new_stock
    
    # Update available stock
    stock.available_stock = stock.current_stock - stock.reserved_stock
    stock.last_movement_at = db_movement.movement_date
    
    db.add(db_movement)
    db.commit()
    db.refresh(db_movement)
    
    return db_movement


@router.get("/{stock_id}/movements", response_model=List[StockMovementResponse])
async def get_stock_movements(
    stock_id: int,
    skip: int = Query(0, ge=0),
    limit: int = Query(50, ge=1, le=200),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get stock movement history"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    movements = (
        db.query(StockMovement)
        .filter(StockMovement.stock_id == stock_id)
        .order_by(StockMovement.movement_date.desc())
        .offset(skip)
        .limit(limit)
        .all()
    )
    
    return movements


@router.get("/{stock_id}/locations", response_model=List[StockLocationResponse])
async def get_stock_locations(
    stock_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get stock locations"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    locations = db.query(StockLocation).filter(StockLocation.stock_id == stock_id).all()
    return locations


@router.get("/alerts/low-stock")
async def get_low_stock_alerts(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get low stock alerts"""
    low_stock_items = (
        db.query(Stock)
        .filter(Stock.available_stock <= Stock.minimum_stock)
        .filter(Stock.status == StockStatus.ACTIVE)
        .all()
    )
    
    alerts = []
    for stock in low_stock_items:
        severity = "critical" if stock.available_stock <= 0 else "warning"
        alerts.append({
            "stock_id": stock.id,
            "sku": stock.sku,
            "name": stock.name,
            "current_stock": stock.available_stock,
            "minimum_stock": stock.minimum_stock,
            "severity": severity,
            "message": f"{stock.name} is {'out of stock' if stock.available_stock <= 0 else 'below minimum level'}"
        })
    
    return {"alerts": alerts, "total": len(alerts)}


@router.post("/{stock_id}/reserve")
async def reserve_stock(
    stock_id: int,
    quantity: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]))
):
    """Reserve stock for transfer or order"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    if stock.available_stock < quantity:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Insufficient available stock for reservation"
        )
    
    stock.reserved_stock += quantity
    stock.available_stock -= quantity
    
    db.commit()
    
    return {"message": f"Reserved {quantity} units of {stock.sku}"}


@router.post("/{stock_id}/unreserve")
async def unreserve_stock(
    stock_id: int,
    quantity: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]))
):
    """Unreserve stock"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    if stock.reserved_stock < quantity:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot unreserve more than reserved quantity"
        )
    
    stock.reserved_stock -= quantity
    stock.available_stock += quantity
    
    db.commit()
    
    return {"message": f"Unreserved {quantity} units of {stock.sku}"}