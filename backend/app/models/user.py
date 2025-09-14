"""
User model for authentication and authorization
"""
from sqlalchemy import Column, Integer, String, Boolean, DateTime, Enum
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
import enum
from app.db.database import Base


class UserRole(str, enum.Enum):
    """User roles for access control"""
    ADMIN = "admin"
    MANAGER = "manager"
    OPERATOR = "operator"
    VIEWER = "viewer"


class User(Base):
    """User model for authentication and authorization"""
    __tablename__ = "users"
    
    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), unique=True, index=True, nullable=False)
    email = Column(String(100), unique=True, index=True, nullable=False)
    full_name = Column(String(100), nullable=False)
    hashed_password = Column(String(255), nullable=False)
    
    # Role and permissions
    role = Column(Enum(UserRole), default=UserRole.VIEWER, nullable=False)
    is_active = Column(Boolean, default=True, nullable=False)
    is_verified = Column(Boolean, default=False, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    last_login = Column(DateTime(timezone=True), nullable=True)
    
    # Additional fields
    department = Column(String(50), nullable=True)
    phone = Column(String(20), nullable=True)
    notes = Column(String(500), nullable=True)
    
    def __repr__(self):
        return f"<User {self.username} ({self.role})>"
    
    @property
    def can_manage_users(self) -> bool:
        """Check if user can manage other users"""
        return self.role == UserRole.ADMIN
    
    @property
    def can_manage_stocks(self) -> bool:
        """Check if user can manage stock operations"""
        return self.role in [UserRole.ADMIN, UserRole.MANAGER]
    
    @property
    def can_create_transfers(self) -> bool:
        """Check if user can create stock transfers"""
        return self.role in [UserRole.ADMIN, UserRole.MANAGER, UserRole.OPERATOR]
    
    @property
    def can_view_analytics(self) -> bool:
        """Check if user can view analytics"""
        return self.role in [UserRole.ADMIN, UserRole.MANAGER]