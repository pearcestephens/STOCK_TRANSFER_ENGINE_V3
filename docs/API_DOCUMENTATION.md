# Stock Transfer Engine V3 - API Documentation

## Overview

The Stock Transfer Engine V3 is a sophisticated AI-based stock management system built with FastAPI and React. This document provides comprehensive API documentation for integration with external systems.

## Base URL

```
Production: https://your-domain.com/api/v1
Development: http://localhost:8000/api/v1
```

## Authentication

The API uses JWT (JSON Web Token) based authentication. Include the token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

### Get Access Token

```http
POST /auth/token
Content-Type: application/x-www-form-urlencoded

username=your_username&password=your_password
```

**Response:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 1800,
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "full_name": "Administrator",
    "role": "admin"
  }
}
```

## User Roles

- **Admin**: Full system access
- **Manager**: Stock and transfer management
- **Operator**: Stock operations and transfers
- **Viewer**: Read-only access

## Stock Management Endpoints

### List Stocks

```http
GET /stocks?skip=0&limit=100&search=widget&category=finished_goods&status=active&low_stock_only=false
```

**Query Parameters:**
- `skip` (int): Number of records to skip (pagination)
- `limit` (int): Maximum records to return (1-1000)
- `search` (string): Search by SKU, name, or description
- `category` (enum): Filter by stock category
- `status` (enum): Filter by stock status
- `low_stock_only` (bool): Show only low stock items

**Response:**
```json
{
  "items": [
    {
      "id": 1,
      "sku": "WIDGET-001",
      "name": "Standard Widget",
      "description": "Basic widget for general use",
      "category": "finished_goods",
      "status": "active",
      "unit_of_measure": "pcs",
      "current_stock": 100,
      "available_stock": 90,
      "reserved_stock": 10,
      "minimum_stock": 20,
      "unit_cost": 5.50,
      "unit_price": 12.99,
      "stock_value": 550.00,
      "is_low_stock": false,
      "is_out_of_stock": false,
      "created_at": "2024-01-01T10:00:00Z",
      "last_movement_at": "2024-01-15T14:30:00Z"
    }
  ],
  "total": 1,
  "skip": 0,
  "limit": 100
}
```

### Create Stock Item

```http
POST /stocks
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
  "sku": "NEW-WIDGET-001",
  "name": "New Widget",
  "description": "A new type of widget",
  "category": "finished_goods",
  "unit_of_measure": "pcs",
  "unit_cost": 7.50,
  "unit_price": 15.99,
  "current_stock": 50,
  "minimum_stock": 10,
  "maximum_stock": 200,
  "reorder_point": 15,
  "reorder_quantity": 50,
  "supplier_name": "Widget Supplier Inc",
  "lead_time_days": 5,
  "location_code": "A-1-2"
}
```

### Update Stock Item

```http
PUT /stocks/{stock_id}
Content-Type: application/json
Authorization: Bearer <token>
```

### Create Stock Movement

```http
POST /stocks/{stock_id}/movements
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
  "movement_type": "inbound",
  "quantity": 25,
  "unit_cost": 5.50,
  "from_location": "Supplier",
  "to_location": "A-1-1",
  "reference_number": "PO-2024-001",
  "reason": "Purchase order receipt",
  "notes": "Quality checked and approved"
}
```

### Get Low Stock Alerts

```http
GET /stocks/alerts/low-stock
Authorization: Bearer <token>
```

## Transfer Management Endpoints

### List Transfers

```http
GET /transfers?skip=0&limit=100&status=pending&from_location=Warehouse&to_location=Store
```

### Create Transfer

```http
POST /transfers
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
  "from_location": "Main Warehouse",
  "to_location": "Branch Office",
  "priority": "normal",
  "reason": "Regular stock replenishment",
  "scheduled_date": "2024-01-20T09:00:00Z",
  "requires_approval": true,
  "items": [
    {
      "stock_id": 1,
      "quantity": 10,
      "notes": "Handle with care"
    },
    {
      "stock_id": 2,
      "quantity": 25
    }
  ]
}
```

### Approve Transfer

```http
PUT /transfers/{transfer_id}/approve
Authorization: Bearer <token>
```

### Complete Transfer

```http
PUT /transfers/{transfer_id}/complete
Content-Type: application/json
Authorization: Bearer <token>
```

**Request Body:**
```json
{
  "actual_cost": 25.50,
  "items": [
    {
      "stock_id": 1,
      "quantity_shipped": 10,
      "quantity_received": 10,
      "quantity_damaged": 0
    }
  ]
}
```

## Analytics & AI Endpoints

### Stock Demand Forecasting

```http
GET /analytics/stock-forecasting/{stock_id}?days_ahead=30
Authorization: Bearer <token>
```

**Response:**
```json
{
  "stock_id": 1,
  "stock_sku": "WIDGET-001",
  "current_stock": 100,
  "forecast_period_days": 30,
  "predictions": [
    {
      "date": "2024-01-16",
      "predicted_demand": 3.2,
      "confidence": 0.87
    }
  ],
  "summary": {
    "total_predicted_demand": 96.0,
    "average_daily_demand": 3.2,
    "days_until_stockout": 31,
    "recommended_reorder_quantity": 75
  },
  "model_info": {
    "model_type": "Random Forest",
    "training_data_points": 150,
    "average_confidence": 0.85
  }
}
```

### Reorder Recommendations

```http
GET /analytics/reorder-recommendations
Authorization: Bearer <token>
```

### Stock Optimization Analysis

```http
GET /analytics/stock-optimization
Authorization: Bearer <token>
```

## Dashboard Endpoints

### Dashboard Overview

```http
GET /dashboard/overview
Authorization: Bearer <token>
```

**Response:**
```json
{
  "stock_summary": {
    "total_active_stocks": 1250,
    "low_stock_alerts": 15,
    "out_of_stock": 3,
    "total_inventory_value": 125450.75
  },
  "transfer_summary": {
    "pending_transfers": 8,
    "in_transit_transfers": 12,
    "completed_today": 5,
    "pending_approvals": 3
  },
  "recent_activity": {
    "stock_movements": 42,
    "new_transfers": 6,
    "completed_transfers": 5
  },
  "active_alerts": [
    {
      "id": 1,
      "title": "Low Stock Alert",
      "severity": "warning",
      "created_at": "2024-01-15T10:30:00Z",
      "stock_sku": "WIDGET-001"
    }
  ],
  "last_updated": "2024-01-15T15:45:00Z"
}
```

### Real-time Metrics

```http
GET /dashboard/real-time-metrics
Authorization: Bearer <token>
```

### Stock Trends

```http
GET /dashboard/stock-trends?days=30
Authorization: Bearer <token>
```

### Export Data

```http
GET /dashboard/export-data?format=json&data_type=overview&days=30
Authorization: Bearer <token>
```

**Query Parameters:**
- `format`: Export format (json, csv, xlsx)
- `data_type`: Type of data (overview, stocks, transfers, movements, alerts)
- `days`: Number of days of historical data

## Error Responses

The API returns standard HTTP status codes:

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

**Error Response Format:**
```json
{
  "error": "Validation Error",
  "message": "The provided data is invalid",
  "details": [
    {
      "field": "sku",
      "message": "SKU already exists"
    }
  ]
}
```

## Rate Limiting

API requests are limited to 100 requests per minute per user. Rate limit headers are included in responses:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1642248000
```

## Webhooks

The system can send webhooks for important events:

- Stock level alerts
- Transfer status changes
- System errors

Configure webhooks in the admin panel or via the API.

## SDKs and Integration

### Python SDK Example

```python
import requests

class StockEngineAPI:
    def __init__(self, base_url, token):
        self.base_url = base_url
        self.headers = {'Authorization': f'Bearer {token}'}
    
    def get_stocks(self, **params):
        response = requests.get(f'{self.base_url}/stocks', 
                              headers=self.headers, params=params)
        return response.json()
    
    def create_transfer(self, data):
        response = requests.post(f'{self.base_url}/transfers',
                               headers=self.headers, json=data)
        return response.json()

# Usage
api = StockEngineAPI('http://localhost:8000/api/v1', 'your-token')
stocks = api.get_stocks(limit=50, low_stock_only=True)
```

### JavaScript/Node.js Example

```javascript
class StockEngineAPI {
  constructor(baseUrl, token) {
    this.baseUrl = baseUrl;
    this.headers = {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    };
  }

  async getStocks(params = {}) {
    const url = new URL(`${this.baseUrl}/stocks`);
    Object.keys(params).forEach(key => 
      url.searchParams.append(key, params[key]));
    
    const response = await fetch(url, { headers: this.headers });
    return response.json();
  }

  async createStock(data) {
    const response = await fetch(`${this.baseUrl}/stocks`, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify(data)
    });
    return response.json();
  }
}

// Usage
const api = new StockEngineAPI('http://localhost:8000/api/v1', 'your-token');
const stocks = await api.getStocks({ limit: 50 });
```

## Support

For API support and questions:
- Documentation: `/docs` (Swagger UI)
- Email: support@stockengine.com
- GitHub Issues: Create an issue in the repository