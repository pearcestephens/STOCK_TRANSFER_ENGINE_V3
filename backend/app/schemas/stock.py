"""
Stock management schemas
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime
from app.models.stock import StockCategory, StockStatus, MovementType


class StockBase(BaseModel):
    """Base stock schema"""
    sku: str = Field(..., min_length=1, max_length=50)
    name: str = Field(..., min_length=1, max_length=200)
    description: Optional[str] = None
    category: StockCategory = StockCategory.OTHER
    subcategory: Optional[str] = Field(None, max_length=100)
    status: StockStatus = StockStatus.ACTIVE
    unit_of_measure: str = Field("pcs", max_length=20)
    weight: Optional[float] = Field(None, ge=0)
    dimensions: Optional[str] = Field(None, max_length=100)


class StockCreate(StockBase):
    """Schema for creating stock"""
    unit_cost: float = Field(0.0, ge=0)
    unit_price: float = Field(0.0, ge=0)
    currency: str = Field("USD", max_length=3)
    current_stock: int = Field(0, ge=0)
    reserved_stock: int = Field(0, ge=0)
    minimum_stock: int = Field(10, ge=0)
    maximum_stock: int = Field(1000, ge=0)
    reorder_point: int = Field(20, ge=0)
    reorder_quantity: int = Field(100, ge=0)
    supplier_name: Optional[str] = Field(None, max_length=200)
    supplier_sku: Optional[str] = Field(None, max_length=100)
    lead_time_days: int = Field(7, ge=0)
    barcode: Optional[str] = Field(None, max_length=100)
    location_code: Optional[str] = Field(None, max_length=50)
    batch_tracking: bool = False
    expiry_tracking: bool = False


class StockUpdate(BaseModel):
    """Schema for updating stock"""
    name: Optional[str] = Field(None, min_length=1, max_length=200)
    description: Optional[str] = None
    category: Optional[StockCategory] = None
    subcategory: Optional[str] = Field(None, max_length=100)
    status: Optional[StockStatus] = None
    unit_of_measure: Optional[str] = Field(None, max_length=20)
    weight: Optional[float] = Field(None, ge=0)
    dimensions: Optional[str] = Field(None, max_length=100)
    unit_cost: Optional[float] = Field(None, ge=0)
    unit_price: Optional[float] = Field(None, ge=0)
    currency: Optional[str] = Field(None, max_length=3)
    minimum_stock: Optional[int] = Field(None, ge=0)
    maximum_stock: Optional[int] = Field(None, ge=0)
    reorder_point: Optional[int] = Field(None, ge=0)
    reorder_quantity: Optional[int] = Field(None, ge=0)
    supplier_name: Optional[str] = Field(None, max_length=200)
    supplier_sku: Optional[str] = Field(None, max_length=100)
    lead_time_days: Optional[int] = Field(None, ge=0)
    barcode: Optional[str] = Field(None, max_length=100)
    location_code: Optional[str] = Field(None, max_length=50)
    batch_tracking: Optional[bool] = None
    expiry_tracking: Optional[bool] = None


class StockResponse(StockBase):
    """Schema for stock response"""
    id: int
    unit_cost: float
    unit_price: float
    currency: str
    current_stock: int
    reserved_stock: int
    available_stock: int
    minimum_stock: int
    maximum_stock: int
    reorder_point: int
    reorder_quantity: int
    supplier_name: Optional[str]
    supplier_sku: Optional[str]
    lead_time_days: int
    barcode: Optional[str]
    location_code: Optional[str]
    batch_tracking: bool
    expiry_tracking: bool
    created_at: datetime
    updated_at: Optional[datetime]
    last_movement_at: Optional[datetime]
    
    # Computed properties
    is_low_stock: bool
    is_out_of_stock: bool
    stock_value: float
    
    class Config:
        from_attributes = True


class StockListResponse(BaseModel):
    """Schema for paginated stock list"""
    items: List[StockResponse]
    total: int
    skip: int
    limit: int


class StockMovementBase(BaseModel):
    """Base stock movement schema"""
    movement_type: MovementType
    quantity: int
    unit_cost: Optional[float] = Field(None, ge=0)
    from_location: Optional[str] = Field(None, max_length=100)
    to_location: Optional[str] = Field(None, max_length=100)
    reference_number: Optional[str] = Field(None, max_length=100)
    reference_type: Optional[str] = Field(None, max_length=50)
    batch_number: Optional[str] = Field(None, max_length=50)
    expiry_date: Optional[datetime] = None
    reason: Optional[str] = Field(None, max_length=500)
    notes: Optional[str] = None


class StockMovementCreate(StockMovementBase):
    """Schema for creating stock movement"""
    pass


class StockMovementResponse(StockMovementBase):
    """Schema for stock movement response"""
    id: int
    stock_id: int
    user_id: Optional[int]
    movement_date: datetime
    created_at: datetime
    
    class Config:
        from_attributes = True


class StockLocationResponse(BaseModel):
    """Schema for stock location response"""
    id: int
    stock_id: int
    location_code: str
    location_name: str
    warehouse: Optional[str]
    aisle: Optional[str]
    shelf: Optional[str]
    bin: Optional[str]
    quantity: int
    reserved_quantity: int
    created_at: datetime
    updated_at: Optional[datetime]
    
    class Config:
        from_attributes = True