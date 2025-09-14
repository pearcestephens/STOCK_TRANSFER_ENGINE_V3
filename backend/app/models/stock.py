"""
Stock management models
"""
from sqlalchemy import Column, Integer, String, Float, Boolean, DateTime, Text, ForeignKey, Enum
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
import enum
from app.db.database import Base


class StockCategory(str, enum.Enum):
    """Stock categories for organization"""
    RAW_MATERIALS = "raw_materials"
    FINISHED_GOODS = "finished_goods"
    WORK_IN_PROGRESS = "work_in_progress"
    SUPPLIES = "supplies"
    EQUIPMENT = "equipment"
    OTHER = "other"


class StockStatus(str, enum.Enum):
    """Stock status for tracking"""
    ACTIVE = "active"
    INACTIVE = "inactive"
    DISCONTINUED = "discontinued"
    QUARANTINE = "quarantine"


class MovementType(str, enum.Enum):
    """Types of stock movements"""
    INBOUND = "inbound"
    OUTBOUND = "outbound"
    TRANSFER = "transfer"
    ADJUSTMENT = "adjustment"
    RETURN = "return"
    DAMAGED = "damaged"
    EXPIRED = "expired"


class Stock(Base):
    """Stock/Product model"""
    __tablename__ = "stocks"
    
    id = Column(Integer, primary_key=True, index=True)
    sku = Column(String(50), unique=True, index=True, nullable=False)
    name = Column(String(200), nullable=False, index=True)
    description = Column(Text, nullable=True)
    
    # Categories and classification
    category = Column(Enum(StockCategory), default=StockCategory.OTHER, nullable=False)
    subcategory = Column(String(100), nullable=True)
    status = Column(Enum(StockStatus), default=StockStatus.ACTIVE, nullable=False)
    
    # Physical properties
    unit_of_measure = Column(String(20), default="pcs", nullable=False)
    weight = Column(Float, nullable=True)  # in kg
    dimensions = Column(String(100), nullable=True)  # L x W x H
    
    # Financial data
    unit_cost = Column(Float, default=0.0, nullable=False)
    unit_price = Column(Float, default=0.0, nullable=False)
    currency = Column(String(3), default="USD", nullable=False)
    
    # Inventory levels
    current_stock = Column(Integer, default=0, nullable=False)
    reserved_stock = Column(Integer, default=0, nullable=False)
    available_stock = Column(Integer, default=0, nullable=False)
    
    # Thresholds
    minimum_stock = Column(Integer, default=10, nullable=False)
    maximum_stock = Column(Integer, default=1000, nullable=False) 
    reorder_point = Column(Integer, default=20, nullable=False)
    reorder_quantity = Column(Integer, default=100, nullable=False)
    
    # Supplier information
    supplier_name = Column(String(200), nullable=True)
    supplier_sku = Column(String(100), nullable=True)
    lead_time_days = Column(Integer, default=7, nullable=False)
    
    # Additional metadata
    barcode = Column(String(100), nullable=True, unique=True)
    location_code = Column(String(50), nullable=True)
    batch_tracking = Column(Boolean, default=False, nullable=False)
    expiry_tracking = Column(Boolean, default=False, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    last_movement_at = Column(DateTime(timezone=True), nullable=True)
    
    # Relationships
    movements = relationship("StockMovement", back_populates="stock", cascade="all, delete-orphan")
    locations = relationship("StockLocation", back_populates="stock", cascade="all, delete-orphan")
    
    def __repr__(self):
        return f"<Stock {self.sku}: {self.name}>"
    
    @property
    def is_low_stock(self) -> bool:
        """Check if stock is below minimum threshold"""
        return self.available_stock <= self.minimum_stock
    
    @property
    def is_out_of_stock(self) -> bool:
        """Check if stock is out of stock"""
        return self.available_stock <= 0
    
    @property
    def stock_value(self) -> float:
        """Calculate total stock value"""
        return self.current_stock * self.unit_cost


class StockLocation(Base):
    """Stock location tracking"""
    __tablename__ = "stock_locations"
    
    id = Column(Integer, primary_key=True, index=True)
    stock_id = Column(Integer, ForeignKey("stocks.id"), nullable=False)
    location_code = Column(String(50), nullable=False, index=True)
    location_name = Column(String(200), nullable=False)
    
    # Location details
    warehouse = Column(String(100), nullable=True)
    aisle = Column(String(20), nullable=True)
    shelf = Column(String(20), nullable=True)
    bin = Column(String(20), nullable=True)
    
    # Stock quantities at this location
    quantity = Column(Integer, default=0, nullable=False)
    reserved_quantity = Column(Integer, default=0, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    stock = relationship("Stock", back_populates="locations")
    
    def __repr__(self):
        return f"<StockLocation {self.location_code}: {self.quantity}>"


class StockMovement(Base):
    """Stock movement/transaction history"""
    __tablename__ = "stock_movements"
    
    id = Column(Integer, primary_key=True, index=True)
    stock_id = Column(Integer, ForeignKey("stocks.id"), nullable=False)
    
    # Movement details
    movement_type = Column(Enum(MovementType), nullable=False)
    quantity = Column(Integer, nullable=False)
    unit_cost = Column(Float, nullable=True)
    
    # Location information
    from_location = Column(String(100), nullable=True)
    to_location = Column(String(100), nullable=True)
    
    # Reference information
    reference_number = Column(String(100), nullable=True, index=True)
    reference_type = Column(String(50), nullable=True)  # PO, SO, Transfer, etc.
    batch_number = Column(String(50), nullable=True)
    expiry_date = Column(DateTime, nullable=True)
    
    # User and reason
    user_id = Column(Integer, ForeignKey("users.id"), nullable=True)
    reason = Column(String(500), nullable=True)
    notes = Column(Text, nullable=True)
    
    # Timestamps
    movement_date = Column(DateTime(timezone=True), server_default=func.now())
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    
    # Relationships
    stock = relationship("Stock", back_populates="movements")
    user = relationship("User")
    
    def __repr__(self):
        return f"<StockMovement {self.movement_type}: {self.quantity}>"