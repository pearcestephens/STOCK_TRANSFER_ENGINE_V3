"""
Stock transfer models
"""
from sqlalchemy import Column, Integer, String, Float, DateTime, Text, ForeignKey, Enum, Boolean
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
import enum
from app.db.database import Base


class TransferStatus(str, enum.Enum):
    """Transfer status tracking"""
    DRAFT = "draft"
    PENDING = "pending"
    IN_TRANSIT = "in_transit"
    COMPLETED = "completed"
    CANCELLED = "cancelled"
    FAILED = "failed"


class TransferPriority(str, enum.Enum):
    """Transfer priority levels"""
    LOW = "low"
    NORMAL = "normal"
    HIGH = "high"
    URGENT = "urgent"


class Transfer(Base):
    """Stock transfer model"""
    __tablename__ = "transfers"
    
    id = Column(Integer, primary_key=True, index=True)
    transfer_number = Column(String(50), unique=True, index=True, nullable=False)
    
    # Transfer details
    status = Column(Enum(TransferStatus), default=TransferStatus.DRAFT, nullable=False)
    priority = Column(Enum(TransferPriority), default=TransferPriority.NORMAL, nullable=False)
    
    # Locations
    from_location = Column(String(100), nullable=False)
    to_location = Column(String(100), nullable=False)
    
    # Dates
    requested_date = Column(DateTime(timezone=True), nullable=True)
    scheduled_date = Column(DateTime(timezone=True), nullable=True)
    started_date = Column(DateTime(timezone=True), nullable=True)
    completed_date = Column(DateTime(timezone=True), nullable=True)
    
    # Users
    requested_by = Column(Integer, ForeignKey("users.id"), nullable=False)
    approved_by = Column(Integer, ForeignKey("users.id"), nullable=True)
    completed_by = Column(Integer, ForeignKey("users.id"), nullable=True)
    
    # Additional information
    reason = Column(String(500), nullable=True)
    notes = Column(Text, nullable=True)
    tracking_number = Column(String(100), nullable=True)
    carrier = Column(String(100), nullable=True)
    
    # Financial
    estimated_cost = Column(Float, default=0.0, nullable=False)
    actual_cost = Column(Float, default=0.0, nullable=False)
    
    # Flags
    requires_approval = Column(Boolean, default=False, nullable=False)
    is_urgent = Column(Boolean, default=False, nullable=False)
    is_automated = Column(Boolean, default=False, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    items = relationship("TransferItem", back_populates="transfer", cascade="all, delete-orphan")
    requester = relationship("User", foreign_keys=[requested_by])
    approver = relationship("User", foreign_keys=[approved_by])
    completer = relationship("User", foreign_keys=[completed_by])
    
    def __repr__(self):
        return f"<Transfer {self.transfer_number}: {self.status}>"
    
    @property
    def total_items(self) -> int:
        """Get total number of items in transfer"""
        return len(self.items)
    
    @property
    def total_quantity(self) -> int:
        """Get total quantity across all items"""
        return sum(item.quantity for item in self.items)
    
    @property
    def is_pending_approval(self) -> bool:
        """Check if transfer is pending approval"""
        return self.status == TransferStatus.PENDING and self.requires_approval
    
    @property
    def can_be_cancelled(self) -> bool:
        """Check if transfer can be cancelled"""
        return self.status in [TransferStatus.DRAFT, TransferStatus.PENDING]


class TransferItem(Base):
    """Individual items in a stock transfer"""
    __tablename__ = "transfer_items"
    
    id = Column(Integer, primary_key=True, index=True)
    transfer_id = Column(Integer, ForeignKey("transfers.id"), nullable=False)
    stock_id = Column(Integer, ForeignKey("stocks.id"), nullable=False)
    
    # Quantities
    quantity_requested = Column(Integer, nullable=False)
    quantity_shipped = Column(Integer, default=0, nullable=False)
    quantity_received = Column(Integer, default=0, nullable=False)
    quantity_damaged = Column(Integer, default=0, nullable=False)
    
    # Batch and expiry tracking
    batch_number = Column(String(50), nullable=True)
    expiry_date = Column(DateTime, nullable=True)
    serial_numbers = Column(Text, nullable=True)  # JSON array of serial numbers
    
    # Financial
    unit_cost = Column(Float, nullable=True)
    total_cost = Column(Float, nullable=True)
    
    # Status and notes
    notes = Column(Text, nullable=True)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    transfer = relationship("Transfer", back_populates="items")
    stock = relationship("Stock")
    
    def __repr__(self):
        return f"<TransferItem {self.stock.sku}: {self.quantity_requested}>"
    
    @property
    def is_fully_shipped(self) -> bool:
        """Check if item is fully shipped"""
        return self.quantity_shipped >= self.quantity_requested
    
    @property
    def is_fully_received(self) -> bool:
        """Check if item is fully received"""
        return self.quantity_received >= self.quantity_shipped
    
    @property
    def shortage_quantity(self) -> int:
        """Calculate shortage quantity"""
        return max(0, self.quantity_requested - self.quantity_shipped)
    
    @property
    def pending_receipt_quantity(self) -> int:
        """Calculate pending receipt quantity"""
        return max(0, self.quantity_shipped - self.quantity_received)