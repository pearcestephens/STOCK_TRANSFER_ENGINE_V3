"""
Stock Transfer Engine V3 - Main FastAPI Application
"""
from fastapi import FastAPI, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from contextlib import asynccontextmanager
import uvicorn

from app.core.config import settings
from app.db.database import engine, Base
from app.api.endpoints import auth, stocks, transfers, analytics, dashboard


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan handler"""
    # Startup
    print("🚀 Starting Stock Transfer Engine V3...")
    
    # Create database tables
    Base.metadata.create_all(bind=engine)
    print("✅ Database tables created")
    
    yield
    
    # Shutdown
    print("🛑 Shutting down Stock Transfer Engine V3...")


# Create FastAPI app
app = FastAPI(
    title="Stock Transfer Engine V3",
    description="AI-based sophisticated stock management system with real-time monitoring and analytics",
    version="3.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_HOSTS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/")
async def root():
    """Root endpoint with API information"""
    return {
        "message": "Stock Transfer Engine V3 API",
        "version": "3.0.0",
        "status": "operational",
        "docs": "/docs",
        "features": [
            "Real-time stock management",
            "AI-powered analytics", 
            "Advanced reporting",
            "Multi-user support",
            "Audit trail"
        ]
    }


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "service": "Stock Transfer Engine V3"}


# Include API routers
app.include_router(auth.router, prefix="/api/v1/auth", tags=["Authentication"])
app.include_router(stocks.router, prefix="/api/v1/stocks", tags=["Stock Management"])
app.include_router(transfers.router, prefix="/api/v1/transfers", tags=["Stock Transfers"])
app.include_router(analytics.router, prefix="/api/v1/analytics", tags=["Analytics & AI"])
app.include_router(dashboard.router, prefix="/api/v1/dashboard", tags=["Dashboard"])


@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    """Global exception handler"""
    return JSONResponse(
        status_code=500,
        content={
            "error": "Internal server error",
            "message": "An unexpected error occurred",
            "type": type(exc).__name__
        }
    )


if __name__ == "__main__":
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )