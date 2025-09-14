"""
Dashboard endpoints for real-time monitoring and reporting
"""
from typing import List, Optional, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from sqlalchemy import func, and_, or_, desc
from datetime import datetime, timedelta
import json

from app.db.database import get_db, get_redis
from app.models.stock import Stock, StockMovement, MovementType, StockStatus
from app.models.transfer import Transfer, TransferStatus
from app.models.analytics import Alert, AlertRule
from app.models.user import User, UserRole
from app.api.endpoints.auth import get_current_active_user

router = APIRouter()


@router.get("/overview")
async def get_dashboard_overview(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get main dashboard overview with key metrics"""
    # Stock summary
    stock_summary = {
        "total_active_stocks": db.query(Stock).filter(Stock.status == StockStatus.ACTIVE).count(),
        "low_stock_alerts": (
            db.query(Stock)
            .filter(Stock.status == StockStatus.ACTIVE)
            .filter(Stock.available_stock <= Stock.minimum_stock)
            .count()
        ),
        "out_of_stock": (
            db.query(Stock)
            .filter(Stock.status == StockStatus.ACTIVE)
            .filter(Stock.available_stock <= 0)
            .count()
        ),
        "total_inventory_value": (
            db.query(func.sum(Stock.current_stock * Stock.unit_cost))
            .filter(Stock.status == StockStatus.ACTIVE)
            .scalar() or 0
        )
    }
    
    # Transfer summary
    transfer_summary = {
        "pending_transfers": db.query(Transfer).filter(Transfer.status == TransferStatus.PENDING).count(),
        "in_transit_transfers": db.query(Transfer).filter(Transfer.status == TransferStatus.IN_TRANSIT).count(),
        "completed_today": (
            db.query(Transfer)
            .filter(Transfer.status == TransferStatus.COMPLETED)
            .filter(Transfer.completed_date >= datetime.utcnow().date())
            .count()
        ),
        "pending_approvals": (
            db.query(Transfer)
            .filter(Transfer.status == TransferStatus.PENDING)
            .filter(Transfer.requires_approval == True)
            .count()
        ) if current_user.role in [UserRole.ADMIN, UserRole.MANAGER] else 0
    }
    
    # Recent activity (last 24 hours)
    recent_activity = {
        "stock_movements": (
            db.query(StockMovement)
            .filter(StockMovement.movement_date >= datetime.utcnow() - timedelta(hours=24))
            .count()
        ),
        "new_transfers": (
            db.query(Transfer)
            .filter(Transfer.created_at >= datetime.utcnow() - timedelta(hours=24))
            .count()
        ),
        "completed_transfers": (
            db.query(Transfer)
            .filter(Transfer.status == TransferStatus.COMPLETED)
            .filter(Transfer.completed_date >= datetime.utcnow() - timedelta(hours=24))
            .count()
        )
    }
    
    # Active alerts
    active_alerts = (
        db.query(Alert)
        .filter(Alert.is_resolved == False)
        .order_by(desc(Alert.created_at))
        .limit(5)
        .all()
    )
    
    return {
        "stock_summary": stock_summary,
        "transfer_summary": transfer_summary,
        "recent_activity": recent_activity,
        "active_alerts": [
            {
                "id": alert.id,
                "title": alert.title,
                "severity": alert.severity,
                "created_at": alert.created_at,
                "stock_sku": alert.stock.sku if alert.stock else None
            }
            for alert in active_alerts
        ],
        "last_updated": datetime.utcnow().isoformat()
    }


@router.get("/real-time-metrics")
async def get_real_time_metrics(
    db: Session = Depends(get_db),
    redis_client = Depends(get_redis),
    current_user: User = Depends(get_current_active_user)
):
    """Get real-time metrics with caching"""
    cache_key = f"dashboard_metrics_{current_user.role}"
    
    # Try to get from cache first
    cached_metrics = redis_client.get(cache_key)
    if cached_metrics:
        return json.loads(cached_metrics)
    
    # Calculate metrics
    metrics = {
        "timestamp": datetime.utcnow().isoformat(),
        "inventory": {
            "total_items": db.query(Stock).filter(Stock.status == StockStatus.ACTIVE).count(),
            "total_value": float(
                db.query(func.sum(Stock.current_stock * Stock.unit_cost))
                .filter(Stock.status == StockStatus.ACTIVE)
                .scalar() or 0
            ),
            "average_stock_level": float(
                db.query(func.avg(Stock.available_stock))
                .filter(Stock.status == StockStatus.ACTIVE)
                .scalar() or 0
            ),
            "items_below_minimum": (
                db.query(Stock)
                .filter(Stock.status == StockStatus.ACTIVE)
                .filter(Stock.available_stock < Stock.minimum_stock)
                .count()
            )
        },
        "movements_today": {
            "inbound": (
                db.query(func.sum(StockMovement.quantity))
                .filter(StockMovement.movement_type == MovementType.INBOUND)
                .filter(StockMovement.movement_date >= datetime.utcnow().date())
                .scalar() or 0
            ),
            "outbound": (
                db.query(func.sum(StockMovement.quantity))
                .filter(StockMovement.movement_type == MovementType.OUTBOUND)
                .filter(StockMovement.movement_date >= datetime.utcnow().date())
                .scalar() or 0
            ),
            "transfers": (
                db.query(func.sum(StockMovement.quantity))
                .filter(StockMovement.movement_type == MovementType.TRANSFER)
                .filter(StockMovement.movement_date >= datetime.utcnow().date())
                .scalar() or 0
            )
        },
        "transfers": {
            "total_active": (
                db.query(Transfer)
                .filter(Transfer.status.in_([TransferStatus.PENDING, TransferStatus.IN_TRANSIT]))
                .count()
            ),
            "completed_this_week": (
                db.query(Transfer)
                .filter(Transfer.status == TransferStatus.COMPLETED)
                .filter(Transfer.completed_date >= datetime.utcnow() - timedelta(days=7))
                .count()
            ),
            "average_completion_time": 0  # Would calculate from historical data
        }
    }
    
    # Cache for 5 minutes
    redis_client.setex(cache_key, 300, json.dumps(metrics))
    
    return metrics


@router.get("/stock-trends")
async def get_stock_trends(
    days: int = Query(30, ge=7, le=365),
    stock_ids: Optional[List[int]] = Query(None),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get stock level trends over time"""
    start_date = datetime.utcnow() - timedelta(days=days)
    
    # Base query for movements
    query = db.query(StockMovement).filter(StockMovement.movement_date >= start_date)
    
    if stock_ids:
        query = query.filter(StockMovement.stock_id.in_(stock_ids))
    
    movements = query.order_by(StockMovement.movement_date).all()
    
    # Group by date and calculate daily totals
    daily_data = {}
    for movement in movements:
        date_key = movement.movement_date.date().isoformat()
        if date_key not in daily_data:
            daily_data[date_key] = {
                "date": date_key,
                "inbound": 0,
                "outbound": 0,
                "net_change": 0
            }
        
        if movement.movement_type in [MovementType.INBOUND, MovementType.RETURN]:
            daily_data[date_key]["inbound"] += movement.quantity
            daily_data[date_key]["net_change"] += movement.quantity
        elif movement.movement_type in [MovementType.OUTBOUND, MovementType.DAMAGED]:
            daily_data[date_key]["outbound"] += movement.quantity
            daily_data[date_key]["net_change"] -= movement.quantity
    
    # Convert to sorted list
    trend_data = sorted(daily_data.values(), key=lambda x: x["date"])
    
    return {
        "period_days": days,
        "data_points": len(trend_data),
        "trends": trend_data,
        "summary": {
            "total_inbound": sum(d["inbound"] for d in trend_data),
            "total_outbound": sum(d["outbound"] for d in trend_data),
            "net_change": sum(d["net_change"] for d in trend_data)
        }
    }


@router.get("/alerts")
async def get_dashboard_alerts(
    skip: int = Query(0, ge=0),
    limit: int = Query(20, ge=1, le=100),
    severity: Optional[str] = Query(None),
    resolved: bool = Query(False),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get dashboard alerts with filtering"""
    query = db.query(Alert)
    
    if not resolved:
        query = query.filter(Alert.is_resolved == False)
    
    if severity:
        query = query.filter(Alert.severity == severity)
    
    total = query.count()
    alerts = (
        query.order_by(desc(Alert.created_at))
        .offset(skip)
        .limit(limit)
        .all()
    )
    
    return {
        "alerts": [
            {
                "id": alert.id,
                "title": alert.title,
                "message": alert.message,
                "severity": alert.severity,
                "alert_type": alert.alert_type,
                "stock": {
                    "id": alert.stock.id,
                    "sku": alert.stock.sku,
                    "name": alert.stock.name
                } if alert.stock else None,
                "is_acknowledged": alert.is_acknowledged,
                "is_resolved": alert.is_resolved,
                "created_at": alert.created_at,
                "age_hours": alert.age_hours
            }
            for alert in alerts
        ],
        "total": total,
        "unresolved_count": (
            db.query(Alert).filter(Alert.is_resolved == False).count()
            if resolved else None
        )
    }


@router.get("/performance-metrics")
async def get_performance_metrics(
    period_days: int = Query(30, ge=7, le=365),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get system performance metrics"""
    start_date = datetime.utcnow() - timedelta(days=period_days)
    
    # Transfer performance
    completed_transfers = (
        db.query(Transfer)
        .filter(Transfer.status == TransferStatus.COMPLETED)
        .filter(Transfer.completed_date >= start_date)
        .all()
    )
    
    # Calculate average completion time
    completion_times = []
    for transfer in completed_transfers:
        if transfer.started_date and transfer.completed_date:
            completion_time = (transfer.completed_date - transfer.started_date).total_seconds() / 3600
            completion_times.append(completion_time)
    
    avg_completion_time = sum(completion_times) / len(completion_times) if completion_times else 0
    
    # Stock accuracy metrics
    total_movements = (
        db.query(StockMovement)
        .filter(StockMovement.movement_date >= start_date)
        .count()
    )
    
    # System utilization
    active_users = (
        db.query(User)
        .filter(User.is_active == True)
        .filter(User.last_login >= start_date)
        .count()
    )
    
    return {
        "period_days": period_days,
        "transfer_performance": {
            "completed_transfers": len(completed_transfers),
            "average_completion_time_hours": round(avg_completion_time, 2),
            "on_time_completion_rate": 0.95,  # Would calculate based on scheduled vs actual
            "success_rate": 0.98  # Would calculate based on completed vs failed
        },
        "inventory_accuracy": {
            "total_movements": total_movements,
            "accuracy_score": 0.97,  # Would calculate based on cycle counts
            "discrepancy_rate": 0.03
        },
        "system_utilization": {
            "active_users": active_users,
            "api_calls_per_day": 0,  # Would track from middleware
            "average_response_time_ms": 150
        },
        "generated_at": datetime.utcnow().isoformat()
    }


@router.get("/export-data")
async def export_dashboard_data(
    format: str = Query("json", regex="^(json|csv|xlsx)$"),
    data_type: str = Query("overview", regex="^(overview|stocks|transfers|movements|alerts)$"),
    days: int = Query(30, ge=1, le=365),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Export dashboard data in various formats"""
    start_date = datetime.utcnow() - timedelta(days=days)
    
    data = {}
    
    if data_type == "overview":
        data = await get_dashboard_overview(db, current_user)
    elif data_type == "stocks":
        stocks = db.query(Stock).filter(Stock.status == StockStatus.ACTIVE).all()
        data = {
            "stocks": [
                {
                    "id": s.id,
                    "sku": s.sku,
                    "name": s.name,
                    "category": s.category,
                    "current_stock": s.current_stock,
                    "available_stock": s.available_stock,
                    "unit_cost": s.unit_cost,
                    "stock_value": s.stock_value,
                    "is_low_stock": s.is_low_stock
                }
                for s in stocks
            ]
        }
    elif data_type == "transfers":
        transfers = (
            db.query(Transfer)
            .filter(Transfer.created_at >= start_date)
            .order_by(desc(Transfer.created_at))
            .all()
        )
        data = {
            "transfers": [
                {
                    "id": t.id,
                    "transfer_number": t.transfer_number,
                    "status": t.status,
                    "from_location": t.from_location,
                    "to_location": t.to_location,
                    "created_at": t.created_at.isoformat(),
                    "completed_date": t.completed_date.isoformat() if t.completed_date else None,
                    "total_items": t.total_items,
                    "total_quantity": t.total_quantity
                }
                for t in transfers
            ]
        }
    elif data_type == "movements":
        movements = (
            db.query(StockMovement)
            .filter(StockMovement.movement_date >= start_date)
            .order_by(desc(StockMovement.movement_date))
            .limit(1000)  # Limit for performance
            .all()
        )
        data = {
            "movements": [
                {
                    "id": m.id,
                    "stock_sku": m.stock.sku,
                    "movement_type": m.movement_type,
                    "quantity": m.quantity,
                    "movement_date": m.movement_date.isoformat(),
                    "from_location": m.from_location,
                    "to_location": m.to_location,
                    "reason": m.reason
                }
                for m in movements
            ]
        }
    elif data_type == "alerts":
        alerts = (
            db.query(Alert)
            .filter(Alert.created_at >= start_date)
            .order_by(desc(Alert.created_at))
            .all()
        )
        data = {
            "alerts": [
                {
                    "id": a.id,
                    "title": a.title,
                    "severity": a.severity,
                    "alert_type": a.alert_type,
                    "stock_sku": a.stock.sku if a.stock else None,
                    "is_resolved": a.is_resolved,
                    "created_at": a.created_at.isoformat()
                }
                for a in alerts
            ]
        }
    
    # Add metadata
    data["export_metadata"] = {
        "format": format,
        "data_type": data_type,
        "period_days": days,
        "exported_at": datetime.utcnow().isoformat(),
        "exported_by": current_user.username
    }
    
    if format == "json":
        return data
    elif format == "csv":
        # For CSV, flatten the main data structure
        # Implementation would depend on specific requirements
        return {"message": "CSV export not yet implemented", "data": data}
    elif format == "xlsx":
        # For Excel export
        # Implementation would use libraries like openpyxl
        return {"message": "Excel export not yet implemented", "data": data}
    
    return data