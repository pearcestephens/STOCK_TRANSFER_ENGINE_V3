"""
Configuration settings for Stock Transfer Engine V3
"""
from pydantic_settings import BaseSettings
from typing import List
import os


class Settings(BaseSettings):
    """Application settings"""
    
    # Application
    APP_NAME: str = "Stock Transfer Engine V3"
    APP_VERSION: str = "3.0.0"
    DEBUG: bool = False
    
    # Security
    SECRET_KEY: str = "your-secret-key-change-in-production"
    ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 30
    REFRESH_TOKEN_EXPIRE_DAYS: int = 7
    
    # Database
    DATABASE_URL: str = "postgresql://stockuser:stockpass123@localhost:5432/stock_transfer_engine"
    REDIS_URL: str = "redis://localhost:6379/0"
    
    # CORS
    ALLOWED_HOSTS: List[str] = ["*"]
    
    # AI/ML Settings
    ENABLE_AI_FEATURES: bool = True
    ML_MODEL_PATH: str = "models/"
    PREDICTION_CONFIDENCE_THRESHOLD: float = 0.8
    
    # Stock Management
    LOW_STOCK_THRESHOLD: int = 10
    CRITICAL_STOCK_THRESHOLD: int = 5
    AUTO_REORDER_ENABLED: bool = True
    
    # Notifications
    EMAIL_ENABLED: bool = False
    SMTP_SERVER: str = ""
    SMTP_PORT: int = 587
    SMTP_USERNAME: str = ""
    SMTP_PASSWORD: str = ""
    
    # File Storage
    UPLOAD_DIR: str = "uploads/"
    MAX_UPLOAD_SIZE: int = 10 * 1024 * 1024  # 10MB
    
    # Logging
    LOG_LEVEL: str = "INFO"
    LOG_FILE: str = "stock_engine.log"
    
    # API Rate Limiting
    RATE_LIMIT_REQUESTS: int = 100
    RATE_LIMIT_PERIOD: int = 60  # seconds
    
    # Export Settings
    EXPORT_FORMATS: List[str] = ["csv", "xlsx", "json", "pdf"]
    EXPORT_MAX_RECORDS: int = 10000
    
    class Config:
        env_file = ".env"
        case_sensitive = True


# Global settings instance
settings = Settings()


# Database configuration
def get_database_url() -> str:
    """Get database URL from environment or default"""
    return os.getenv("DATABASE_URL", settings.DATABASE_URL)


def get_redis_url() -> str:
    """Get Redis URL from environment or default"""
    return os.getenv("REDIS_URL", settings.REDIS_URL)