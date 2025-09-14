"""
Analytics and AI endpoints
"""
from typing import List, Optional, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from sqlalchemy import func, and_, or_
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor

from app.db.database import get_db
from app.models.stock import Stock, StockMovement, MovementType
from app.models.transfer import Transfer, TransferStatus
from app.models.analytics import StockPrediction, Alert, AlertRule, PredictionType
from app.models.user import User, UserRole
from app.api.endpoints.auth import get_current_active_user, require_role

router = APIRouter()


@router.get("/stock-forecasting/{stock_id}")
async def forecast_stock_demand(
    stock_id: int,
    days_ahead: int = Query(30, ge=1, le=365),
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER]))
):
    """Generate AI-based stock demand forecast"""
    stock = db.query(Stock).filter(Stock.id == stock_id).first()
    if not stock:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Stock not found"
        )
    
    # Get historical movement data
    movements = (
        db.query(StockMovement)
        .filter(StockMovement.stock_id == stock_id)
        .filter(StockMovement.movement_type == MovementType.OUTBOUND)
        .filter(StockMovement.movement_date >= datetime.utcnow() - timedelta(days=365))
        .order_by(StockMovement.movement_date)
        .all()
    )
    
    if len(movements) < 10:
        return {
            "message": "Insufficient historical data for forecasting",
            "recommended_action": "Collect more movement data"
        }
    
    # Prepare data for ML model
    df = pd.DataFrame([
        {
            "date": m.movement_date,
            "quantity": m.quantity,
            "day_of_week": m.movement_date.weekday(),
            "month": m.movement_date.month,
            "quarter": (m.movement_date.month - 1) // 3 + 1
        }
        for m in movements
    ])
    
    # Aggregate daily consumption
    daily_consumption = df.groupby(df['date'].dt.date)['quantity'].sum().reset_index()
    daily_consumption['date'] = pd.to_datetime(daily_consumption['date'])
    daily_consumption = daily_consumption.sort_values('date')
    
    # Create features for ML model
    daily_consumption['day_of_week'] = daily_consumption['date'].dt.dayofweek
    daily_consumption['month'] = daily_consumption['date'].dt.month
    daily_consumption['quarter'] = daily_consumption['date'].dt.quarter
    daily_consumption['days_since_start'] = (
        daily_consumption['date'] - daily_consumption['date'].min()
    ).dt.days
    
    # Prepare training data
    X = daily_consumption[['days_since_start', 'day_of_week', 'month', 'quarter']].values
    y = daily_consumption['quantity'].values
    
    # Train model
    model = RandomForestRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)
    
    # Generate predictions
    last_date = daily_consumption['date'].max()
    predictions = []
    
    for i in range(1, days_ahead + 1):
        pred_date = last_date + timedelta(days=i)
        features = np.array([[
            daily_consumption['days_since_start'].max() + i,
            pred_date.weekday(),
            pred_date.month,
            (pred_date.month - 1) // 3 + 1
        ]])
        
        predicted_demand = max(0, model.predict(features)[0])
        confidence = min(0.95, max(0.5, 1.0 - (i / days_ahead) * 0.4))  # Confidence decreases over time
        
        predictions.append({
            "date": pred_date.strftime("%Y-%m-%d"),
            "predicted_demand": round(predicted_demand, 2),
            "confidence": round(confidence, 3)
        })
    
    # Calculate summary statistics
    total_predicted_demand = sum(p["predicted_demand"] for p in predictions)
    avg_daily_demand = total_predicted_demand / days_ahead
    
    # Stock out prediction
    current_stock = stock.available_stock
    days_until_stockout = None
    cumulative_demand = 0
    
    for i, pred in enumerate(predictions):
        cumulative_demand += pred["predicted_demand"]
        if cumulative_demand >= current_stock:
            days_until_stockout = i + 1
            break
    
    # Save prediction to database
    db_prediction = StockPrediction(
        stock_id=stock_id,
        prediction_type=PredictionType.DEMAND_FORECAST,
        predicted_value=avg_daily_demand,
        confidence_score=np.mean([p["confidence"] for p in predictions]),
        prediction_date=datetime.utcnow(),
        valid_until=datetime.utcnow() + timedelta(days=days_ahead),
        model_name="RandomForestRegressor",
        model_version="1.0",
        features_used=["days_since_start", "day_of_week", "month", "quarter"],
        historical_data_points=len(movements),
        created_by_user=current_user.id
    )
    
    db.add(db_prediction)
    db.commit()
    
    return {
        "stock_id": stock_id,
        "stock_sku": stock.sku,
        "stock_name": stock.name,
        "current_stock": current_stock,
        "forecast_period_days": days_ahead,
        "predictions": predictions,
        "summary": {
            "total_predicted_demand": round(total_predicted_demand, 2),
            "average_daily_demand": round(avg_daily_demand, 2),
            "days_until_stockout": days_until_stockout,
            "recommended_reorder_quantity": max(
                stock.reorder_quantity,
                int(avg_daily_demand * stock.lead_time_days * 1.5)
            )
        },
        "model_info": {
            "model_type": "Random Forest",
            "training_data_points": len(movements),
            "average_confidence": round(np.mean([p["confidence"] for p in predictions]), 3)
        }
    }


@router.get("/reorder-recommendations")
async def get_reorder_recommendations(
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER]))
):
    """Get AI-based reorder recommendations"""
    # Get stocks that are below reorder point or predicted to run out soon
    stocks_needing_reorder = []
    
    stocks = (
        db.query(Stock)
        .filter(Stock.status == "active")
        .filter(
            or_(
                Stock.available_stock <= Stock.reorder_point,
                Stock.available_stock <= Stock.minimum_stock * 1.5
            )
        )
        .all()
    )
    
    for stock in stocks:
        # Get recent consumption rate
        recent_movements = (
            db.query(StockMovement)
            .filter(StockMovement.stock_id == stock.id)
            .filter(StockMovement.movement_type == MovementType.OUTBOUND)
            .filter(StockMovement.movement_date >= datetime.utcnow() - timedelta(days=30))
            .all()
        )
        
        if recent_movements:
            total_consumed = sum(m.quantity for m in recent_movements)
            daily_consumption_rate = total_consumed / 30
            
            # Calculate recommended order quantity
            safety_stock = daily_consumption_rate * stock.lead_time_days * 0.5
            recommended_quantity = max(
                stock.reorder_quantity,
                int((daily_consumption_rate * stock.lead_time_days) + safety_stock)
            )
            
            # Calculate urgency score
            days_until_stockout = (
                stock.available_stock / daily_consumption_rate 
                if daily_consumption_rate > 0 else float('inf')
            )
            
            urgency_score = max(0, min(10, 10 - days_until_stockout))
            
            stocks_needing_reorder.append({
                "stock_id": stock.id,
                "sku": stock.sku,
                "name": stock.name,
                "current_stock": stock.available_stock,
                "minimum_stock": stock.minimum_stock,
                "reorder_point": stock.reorder_point,
                "daily_consumption_rate": round(daily_consumption_rate, 2),
                "days_until_stockout": round(days_until_stockout, 1) if days_until_stockout != float('inf') else None,
                "recommended_quantity": recommended_quantity,
                "urgency_score": round(urgency_score, 1),
                "supplier_name": stock.supplier_name,
                "lead_time_days": stock.lead_time_days,
                "estimated_cost": recommended_quantity * stock.unit_cost
            })
    
    # Sort by urgency score
    stocks_needing_reorder.sort(key=lambda x: x["urgency_score"], reverse=True)
    
    return {
        "recommendations": stocks_needing_reorder,
        "total_items": len(stocks_needing_reorder),
        "total_estimated_cost": sum(item["estimated_cost"] for item in stocks_needing_reorder),
        "generated_at": datetime.utcnow().isoformat()
    }


@router.get("/stock-optimization")
async def get_stock_optimization_analysis(
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role([UserRole.ADMIN, UserRole.MANAGER]))
):
    """Provide AI-driven stock optimization recommendations"""
    analysis = {
        "overstock_items": [],
        "slow_moving_items": [],
        "fast_moving_items": [],
        "optimization_opportunities": []
    }
    
    # Get all active stocks
    stocks = db.query(Stock).filter(Stock.status == "active").all()
    
    for stock in stocks:
        # Get movement data for the last 90 days
        movements = (
            db.query(StockMovement)
            .filter(StockMovement.stock_id == stock.id)
            .filter(StockMovement.movement_date >= datetime.utcnow() - timedelta(days=90))
            .all()
        )
        
        if not movements:
            continue
        
        # Calculate movement metrics
        outbound_movements = [m for m in movements if m.movement_type == MovementType.OUTBOUND]
        total_consumed = sum(m.quantity for m in outbound_movements)
        daily_consumption = total_consumed / 90
        
        # Stock turnover ratio
        if stock.current_stock > 0:
            turnover_ratio = total_consumed / stock.current_stock
        else:
            turnover_ratio = 0
        
        # Days of inventory remaining
        days_remaining = (
            stock.available_stock / daily_consumption 
            if daily_consumption > 0 else float('inf')
        )
        
        # Categorize stocks
        stock_data = {
            "stock_id": stock.id,
            "sku": stock.sku,
            "name": stock.name,
            "current_stock": stock.available_stock,
            "daily_consumption": round(daily_consumption, 2),
            "turnover_ratio": round(turnover_ratio, 2),
            "days_remaining": round(days_remaining, 1) if days_remaining != float('inf') else None,
            "stock_value": stock.current_stock * stock.unit_cost
        }
        
        # Overstock (more than 180 days of inventory)
        if days_remaining > 180:
            excess_quantity = int(stock.available_stock - (daily_consumption * 90))
            analysis["overstock_items"].append({
                **stock_data,
                "excess_quantity": max(0, excess_quantity),
                "excess_value": max(0, excess_quantity * stock.unit_cost),
                "recommendation": "Consider reducing stock levels or finding alternative uses"
            })
        
        # Slow moving (turnover ratio < 0.5)
        elif turnover_ratio < 0.5 and daily_consumption > 0:
            analysis["slow_moving_items"].append({
                **stock_data,
                "recommendation": "Review demand patterns and consider promotional activities"
            })
        
        # Fast moving (turnover ratio > 4)
        elif turnover_ratio > 4:
            analysis["fast_moving_items"].append({
                **stock_data,
                "recommendation": "Consider increasing stock levels or improving supply frequency"
            })
    
    # Sort lists by relevant metrics
    analysis["overstock_items"].sort(key=lambda x: x["excess_value"], reverse=True)
    analysis["slow_moving_items"].sort(key=lambda x: x["turnover_ratio"])
    analysis["fast_moving_items"].sort(key=lambda x: x["turnover_ratio"], reverse=True)
    
    # Generate optimization opportunities
    total_excess_value = sum(item["excess_value"] for item in analysis["overstock_items"])
    if total_excess_value > 10000:
        analysis["optimization_opportunities"].append({
            "type": "inventory_reduction",
            "description": f"Reduce overstock to free up ${total_excess_value:,.2f} in inventory value",
            "impact": "high",
            "effort": "medium"
        })
    
    if len(analysis["fast_moving_items"]) > 5:
        analysis["optimization_opportunities"].append({
            "type": "supply_frequency",
            "description": "Increase supply frequency for fast-moving items to reduce stockout risk",
            "impact": "high",
            "effort": "low"
        })
    
    return analysis


@router.get("/dashboard/metrics")
async def get_analytics_dashboard_metrics(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_active_user)
):
    """Get key metrics for analytics dashboard"""
    # Stock metrics
    total_stocks = db.query(Stock).filter(Stock.status == "active").count()
    low_stock_count = (
        db.query(Stock)
        .filter(Stock.status == "active")
        .filter(Stock.available_stock <= Stock.minimum_stock)
        .count()
    )
    out_of_stock_count = (
        db.query(Stock)
        .filter(Stock.status == "active")
        .filter(Stock.available_stock <= 0)
        .count()
    )
    
    # Calculate total inventory value
    stocks_with_value = (
        db.query(Stock)
        .filter(Stock.status == "active")
        .filter(Stock.current_stock > 0)
        .all()
    )
    total_inventory_value = sum(s.current_stock * s.unit_cost for s in stocks_with_value)
    
    # Transfer metrics
    active_transfers = db.query(Transfer).filter(Transfer.status.in_([
        TransferStatus.PENDING, TransferStatus.IN_TRANSIT
    ])).count()
    
    # Recent movement activity (last 7 days)
    recent_movements = (
        db.query(StockMovement)
        .filter(StockMovement.movement_date >= datetime.utcnow() - timedelta(days=7))
        .count()
    )
    
    # Predictions count
    active_predictions = (
        db.query(StockPrediction)
        .filter(StockPrediction.valid_until >= datetime.utcnow())
        .count()
    )
    
    # Active alerts
    active_alerts = (
        db.query(Alert)
        .filter(Alert.is_resolved == False)
        .count()
    )
    
    return {
        "inventory": {
            "total_stocks": total_stocks,
            "low_stock_items": low_stock_count,
            "out_of_stock_items": out_of_stock_count,
            "total_value": round(total_inventory_value, 2),
            "stock_health_score": round(
                max(0, 100 - (low_stock_count / max(1, total_stocks)) * 100), 1
            )
        },
        "operations": {
            "active_transfers": active_transfers,
            "recent_movements": recent_movements,
            "pending_approvals": (
                db.query(Transfer)
                .filter(Transfer.status == TransferStatus.PENDING)
                .filter(Transfer.requires_approval == True)
                .count()
            )
        },
        "analytics": {
            "active_predictions": active_predictions,
            "active_alerts": active_alerts,
            "ai_recommendations": (
                db.query(Stock)
                .filter(Stock.available_stock <= Stock.reorder_point)
                .count()
            )
        },
        "generated_at": datetime.utcnow().isoformat()
    }