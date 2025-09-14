"""
Test suite for Stock Transfer Engine V3 Backend
"""
import pytest
from fastapi.testclient import TestClient
from backend.app.main import app

client = TestClient(app)


def test_root_endpoint():
    """Test the root API endpoint"""
    response = client.get("/")
    assert response.status_code == 200
    data = response.json()
    assert data["message"] == "Stock Transfer Engine V3 API"
    assert data["version"] == "3.0.0"
    assert "features" in data


def test_health_check():
    """Test the health check endpoint"""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "healthy"
    assert data["service"] == "Stock Transfer Engine V3"


def test_docs_endpoint():
    """Test that API docs are accessible"""
    response = client.get("/docs")
    assert response.status_code == 200


def test_redoc_endpoint():
    """Test that ReDoc is accessible"""
    response = client.get("/redoc")
    assert response.status_code == 200


def test_auth_endpoints_exist():
    """Test that authentication endpoints are available"""
    # Test login endpoint (should return 422 for missing data)
    response = client.post("/api/v1/auth/token")
    assert response.status_code == 422  # Validation error for missing credentials
    
    # Test register endpoint (should return 422 for missing data)
    response = client.post("/api/v1/auth/register")
    assert response.status_code == 422  # Validation error for missing data


def test_protected_endpoints_require_auth():
    """Test that protected endpoints require authentication"""
    # Test stocks endpoint without auth
    response = client.get("/api/v1/stocks/")
    assert response.status_code == 401  # Unauthorized
    
    # Test transfers endpoint without auth
    response = client.get("/api/v1/transfers/")
    assert response.status_code == 401  # Unauthorized
    
    # Test dashboard endpoint without auth
    response = client.get("/api/v1/dashboard/overview")
    assert response.status_code == 401  # Unauthorized