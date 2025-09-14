# NewTransferV3 API Documentation

## Base URL
```
https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/
```

## Endpoints

### 1. Transfer Execution
**Endpoint**: `index.php`  
**Method**: GET/POST  
**Purpose**: Execute stock transfers

#### Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `action` | string | Yes | - | Must be "run" |
| `simulate` | int | No | 0 | 1=simulate, 0=live execution |
| `outlet_from` | int | No | - | Source outlet ID |
| `outlet_to` | int | No | - | Target outlet ID |
| `cover_days` | int | No | 14 | Demand forecast period |
| `buffer_pct` | int | No | 20 | Safety stock percentage |
| `max_products` | int | No | 0 | Limit products (0=unlimited) |

#### Example Request
```bash
curl "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/index.php?action=run&simulate=1&cover_days=14"
```

#### Response Format
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "products_transferred": 150,
    "total_value": 45678.90,
    "execution_time": 45.2
  },
  "meta": {
    "simulate": true,
    "timestamp": "2025-09-14T10:30:00+12:00"
  }
}
```

### 2. Smart Store Seeding
**Endpoint**: `index.php?action=smart_seed`  
**Method**: POST  
**Purpose**: Seed new store with optimal inventory

#### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `target_outlet_id` | int | Yes | New store outlet ID |
| `respect_pack_outers` | bool | No | Follow pack size rules |
| `balance_categories` | bool | No | Balanced category mix |
| `max_contribution_per_store` | int | No | Max items per source store |
| `min_source_stock` | int | No | Minimum source stock level |
| `simulate` | bool | No | Simulation mode |

#### Example Request
```bash
curl -X POST "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/index.php" \
  -d "action=smart_seed&target_outlet_id=25&simulate=1"
```

### 3. Outlet Directory
**Endpoint**: `index.php?action=get_outlets`  
**Method**: GET  
**Purpose**: Get list of available outlets

#### Response
```json
{
  "success": true,
  "data": [
    {
      "outlet_id": 1,
      "outlet_name": "Auckland CBD",
      "outlet_prefix": "AKL"
    }
  ]
}
```

### 4. Transfer Status
**Endpoint**: `index.php?action=get_transfer_status`  
**Method**: GET  
**Purpose**: Check transfer progress

#### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `transfer_id` | int | Yes | Transfer ID to check |

### 5. Neural Brain Test
**Endpoint**: `index.php?action=test_neural`  
**Method**: GET  
**Purpose**: Validate AI integration

## Web Interfaces

### Primary Interface
**URL**: `working_simple_ui.php`  
**Purpose**: Main production interface for manual transfers

### Emergency Interface  
**URL**: `emergency_transfer_ui.php`  
**Purpose**: Backup interface when primary is unavailable

### Debug Interface
**URL**: `emergency_transfer_ui.php?debug=1`  
**Purpose**: Enhanced debugging and error reporting

## CLI Usage

### Direct Execution
```bash
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/
php index.php
```

### With Parameters
```bash
php index.php?action=run&simulate=1&cover_days=7
```

### Standalone CLI
```bash
php standalone_cli.php --mode=simulate --outlets=1,2,3
```

## Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "INVALID_OUTLET",
    "message": "Outlet ID 999 not found",
    "details": {
      "outlet_id": 999,
      "available_outlets": [1,2,3,4,5]
    }
  },
  "request_id": "req_20250914_103000_abc123"
}
```

## Common Error Codes

| Code | Description | HTTP Status |
|------|-------------|-------------|
| `INVALID_OUTLET` | Outlet ID not found | 400 |
| `INSUFFICIENT_STOCK` | Not enough inventory | 400 |
| `TRANSFER_IN_PROGRESS` | Another transfer running | 409 |
| `DATABASE_ERROR` | Database connection issue | 500 |
| `NEURAL_BRAIN_TIMEOUT` | AI service unavailable | 503 |

## Rate Limits

- **Transfer Execution**: 1 per minute per outlet
- **API Queries**: 60 per minute per IP
- **Neural Brain**: 10 per minute per session

## Authentication

Currently uses session-based authentication from CIS system. API calls must include:
- Valid session cookie
- CSRF token for POST requests

## Monitoring Endpoints

### Health Check
**URL**: `STATUS.php`  
**Response**: System health and dependencies

### Quick Status
**URL**: `QUICK_STATUS.php`  
**Response**: Transfer queue and active operations

---

*Generated: September 14, 2025*  
*Version: NewTransferV3 Enterprise*
