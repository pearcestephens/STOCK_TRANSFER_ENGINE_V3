"""
Analytics and AI models
"""
from sqlalchemy import Column, Integer, String, Float, DateTime, Text, ForeignKey, Enum, Boolean, JSON
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
import enum
from app.db.database import Base


class PredictionType(str, enum.Enum):
    """Types of AI predictions"""
    DEMAND_FORECAST = "demand_forecast"
    STOCK_OPTIMIZATION = "stock_optimization"
    REORDER_RECOMMENDATION = "reorder_recommendation"
    SHORTAGE_PREDICTION = "shortage_prediction"
    COST_PREDICTION = "cost_prediction"


class PredictionStatus(str, enum.Enum):
    """Status of predictions"""
    GENERATED = "generated"
    VALIDATED = "validated"
    APPLIED = "applied"
    REJECTED = "rejected"
    EXPIRED = "expired"


class AlertType(str, enum.Enum):
    """Types of alerts"""
    LOW_STOCK = "low_stock"
    OUT_OF_STOCK = "out_of_stock"
    OVERSTOCK = "overstock"
    EXPIRING_STOCK = "expiring_stock"
    SLOW_MOVING = "slow_moving"
    COST_VARIANCE = "cost_variance"
    SYSTEM_ERROR = "system_error"


class AlertSeverity(str, enum.Enum):
    """Alert severity levels"""
    INFO = "info"
    WARNING = "warning"
    CRITICAL = "critical"
    URGENT = "urgent"


class StockPrediction(Base):
    """AI-generated stock predictions"""
    __tablename__ = "stock_predictions"
    
    id = Column(Integer, primary_key=True, index=True)
    stock_id = Column(Integer, ForeignKey("stocks.id"), nullable=False)
    
    # Prediction details
    prediction_type = Column(Enum(PredictionType), nullable=False)
    status = Column(Enum(PredictionStatus), default=PredictionStatus.GENERATED, nullable=False)
    
    # Prediction values
    predicted_value = Column(Float, nullable=False)
    confidence_score = Column(Float, nullable=False)  # 0.0 to 1.0
    prediction_date = Column(DateTime(timezone=True), nullable=False)
    valid_until = Column(DateTime(timezone=True), nullable=False)
    
    # Model information
    model_name = Column(String(100), nullable=False)
    model_version = Column(String(20), nullable=False)
    features_used = Column(JSON, nullable=True)  # JSON array of feature names
    
    # Additional data
    historical_data_points = Column(Integer, nullable=False)
    seasonality_factor = Column(Float, nullable=True)
    trend_factor = Column(Float, nullable=True)
    
    # Metadata
    notes = Column(Text, nullable=True)
    created_by_user = Column(Integer, ForeignKey("users.id"), nullable=True)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    stock = relationship("Stock")
    creator = relationship("User")
    
    def __repr__(self):
        return f"<StockPrediction {self.prediction_type}: {self.predicted_value}>"
    
    @property
    def is_high_confidence(self) -> bool:
        """Check if prediction has high confidence"""
        return self.confidence_score >= 0.8
    
    @property
    def is_expired(self) -> bool:
        """Check if prediction is expired"""
        from datetime import datetime
        return datetime.utcnow() > self.valid_until


class AlertRule(Base):
    """Configurable alert rules"""
    __tablename__ = "alert_rules"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(200), nullable=False)
    description = Column(Text, nullable=True)
    
    # Rule configuration
    alert_type = Column(Enum(AlertType), nullable=False)
    severity = Column(Enum(AlertSeverity), default=AlertSeverity.WARNING, nullable=False)
    is_active = Column(Boolean, default=True, nullable=False)
    
    # Conditions (JSON format)
    conditions = Column(JSON, nullable=False)  # Flexible condition storage
    
    # Targets
    applies_to_all_stocks = Column(Boolean, default=True, nullable=False)
    stock_categories = Column(JSON, nullable=True)  # Array of categories
    specific_stocks = Column(JSON, nullable=True)  # Array of stock IDs
    
    # Notification settings
    notify_users = Column(JSON, nullable=True)  # Array of user IDs
    email_notification = Column(Boolean, default=False, nullable=False)
    dashboard_notification = Column(Boolean, default=True, nullable=False)
    
    # Frequency control
    max_alerts_per_hour = Column(Integer, default=10, nullable=False)
    cooldown_minutes = Column(Integer, default=60, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    last_triggered = Column(DateTime(timezone=True), nullable=True)
    
    # Relationships
    alerts = relationship("Alert", back_populates="rule", cascade="all, delete-orphan")
    
    def __repr__(self):
        return f"<AlertRule {self.name}: {self.alert_type}>"


class Alert(Base):
    """Generated alerts"""
    __tablename__ = "alerts"
    
    id = Column(Integer, primary_key=True, index=True)
    rule_id = Column(Integer, ForeignKey("alert_rules.id"), nullable=False)
    stock_id = Column(Integer, ForeignKey("stocks.id"), nullable=True)
    
    # Alert details
    alert_type = Column(Enum(AlertType), nullable=False)
    severity = Column(Enum(AlertSeverity), nullable=False)
    title = Column(String(200), nullable=False)
    message = Column(Text, nullable=False)
    
    # Status
    is_acknowledged = Column(Boolean, default=False, nullable=False)
    is_resolved = Column(Boolean, default=False, nullable=False)
    acknowledged_by = Column(Integer, ForeignKey("users.id"), nullable=True)
    resolved_by = Column(Integer, ForeignKey("users.id"), nullable=True)
    
    # Data snapshot
    data_snapshot = Column(JSON, nullable=True)  # Relevant data at time of alert
    
    # Actions taken
    actions_taken = Column(Text, nullable=True)
    resolution_notes = Column(Text, nullable=True)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    acknowledged_at = Column(DateTime(timezone=True), nullable=True)
    resolved_at = Column(DateTime(timezone=True), nullable=True)
    
    # Relationships
    rule = relationship("AlertRule", back_populates="alerts")
    stock = relationship("Stock")
    acknowledger = relationship("User", foreign_keys=[acknowledged_by])
    resolver = relationship("User", foreign_keys=[resolved_by])
    
    def __repr__(self):
        return f"<Alert {self.title}: {self.severity}>"
    
    @property
    def is_active(self) -> bool:
        """Check if alert is active (not resolved)"""
        return not self.is_resolved
    
    @property
    def age_hours(self) -> float:
        """Calculate alert age in hours"""
        from datetime import datetime
        delta = datetime.utcnow() - self.created_at.replace(tzinfo=None)
        return delta.total_seconds() / 3600