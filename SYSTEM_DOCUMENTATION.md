# NewTransferV3 System Documentation

## 📋 System Overview

The NewTransferV3 system is a comprehensive stock transfer management engine designed for The Vape Shed (Ecigdis Ltd) retail network. It handles intelligent stock transfers between stores, new store seeding, inventory optimization, and real-time monitoring across 17+ retail locations.

## 🏗️ Architecture

### Core Components

- **Transfer Engine** (`index.php`) - Main 1808-line transfer orchestration engine
- **CLI API** (`cli_api.php`) - Command-line interface with 5 core actions
- **NewStoreSeeder** (`NewStoreSeeder.php`) - Intelligent new store inventory seeding
- **Production Dashboard** (`production_dashboard.html`) - Real-time monitoring interface
- **Logging System** (`TransferLogger.php`) - Multi-level structured logging
- **Error Handling** (`TransferErrorHandler.php`) - Comprehensive error management
- **Test Suite** (`TestSuite.php`) - Automated testing and validation
- **Performance Benchmark** (`PerformanceBenchmark.php`) - Performance analysis tools

### Database Schema

#### Core Tables
- `vend_outlets` - Store location data
- `vend_inventory` - Product inventory levels
- `vend_products` - Product master data
- `stock_transfers` - Transfer headers
- `stock_products_to_transfer` - Transfer line items

#### Key Relationships
```sql
vend_outlets (1) -> (N) vend_inventory
vend_products (1) -> (N) vend_inventory  
stock_transfers (1) -> (N) stock_products_to_transfer
```

## 🚀 Quick Start Guide

### 1. Environment Setup

```bash
# Navigate to transfer system
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3

# Ensure database connection
php cli_api.php?action=test_db

# Check system status
php STATUS.php
```

### 2. Running Tests

```bash
# Run comprehensive test suite
php TestSuite.php

# Run performance benchmarks
php PerformanceBenchmark.php

# Quick API test
php cli_api.php?action=get_outlets
```

### 3. Basic Operations

#### Test Database Connection
```bash
php cli_api.php?action=test_db
```

#### Get Available Outlets
```bash
php cli_api.php?action=get_outlets
```

#### Create New Store Seed (Simulation)
```bash
php cli_api.php?action=simple_seed&target_outlet_id=YOUR_OUTLET_ID&simulate=1
```

#### Validate Transfer
```bash
php cli_api.php?action=validate_transfer&outlet_id=YOUR_OUTLET_ID
```

## 📡 API Reference

### CLI API Actions

#### 1. `test_db`
Tests database connectivity and basic system health.

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Database connection successful",
    "server_info": "10.5.18-MariaDB",
    "session_id": "TEST_20240101_120000_abc123"
}
```

#### 2. `get_outlets`
Retrieves all active outlet locations.

**Parameters:**
- `include_stats` (optional): Include inventory statistics

**Response:**
```json
{
    "success": true,
    "outlets": [
        {
            "id": "outlet-uuid",
            "name": "Store Name",
            "product_count": 150,
            "total_stock": 2500
        }
    ],
    "total_outlets": 17
}
```

#### 3. `simple_seed`
Creates intelligent seed transfer for new store.

**Parameters:**
- `target_outlet_id` (required): Target store UUID
- `simulate` (optional, default=1): Simulation mode (0=execute, 1=simulate)
- `min_source_stock` (optional, default=5): Minimum source stock level
- `candidate_limit` (optional, default=50): Maximum products to consider

**Response:**
```json
{
    "success": true,
    "message": "Seed transfer created successfully",
    "session_id": "SEED_20240101_120000_def456",
    "products_count": 45,
    "estimated_value": 15750.50,
    "simulation": true
}
```

#### 4. `validate_transfer`
Validates transfer feasibility for outlet.

**Parameters:**
- `outlet_id` (required): Target outlet UUID

**Response:**
```json
{
    "success": true,
    "validation_result": {
        "outlet_exists": true,
        "current_stock_count": 25,
        "transfer_eligible": true,
        "estimated_candidates": 120
    }
}
```

#### 5. `neural_test`
Tests AI/ML integration components.

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "neural_status": "Active",
    "components": {
        "decision_engine": true,
        "prediction_model": true,
        "analytics_layer": true
    }
}
```

## 🛠️ Configuration

### Database Configuration
Database settings are loaded via `../../functions/mysql.php`:

```php
// Connection automatically established
connectToSQL();
global $con; // mysqli connection object
```

### Logging Configuration
Logging levels and output controlled in `TransferLogger.php`:

```php
// Available log levels
LOG_DEBUG = 0    // Detailed debugging information
LOG_INFO = 1     // General information  
LOG_WARNING = 2  // Warning conditions
LOG_ERROR = 3    // Error conditions
```

### Performance Tuning

#### Key Parameters
- `candidate_limit`: Controls batch size (default: 50)
- `min_source_stock`: Minimum stock threshold (default: 5)
- `max_contribution_per_store`: Store contribution limit (default: 5)

#### Optimization Settings
```php
// In NewStoreSeeder options
$options = [
    'simulate' => true,              // Always test first
    'candidate_limit' => 100,        // Larger batches for efficiency  
    'min_source_stock' => 3,         // Lower threshold for more candidates
    'max_contribution_per_store' => 2, // Limit per-store contribution
    'enable_pack_outer' => true      // Handle multi-unit products
];
```

## 📊 Monitoring & Observability

### Dashboard Access
Production dashboard available at:
- **File:** `production_dashboard.html`
- **Features:** Real-time monitoring, system status, transfer tracking, performance metrics

### Log Files
- **Location:** `logs/transfer_YYYY-MM-DD.log`
- **Format:** Structured JSON with timestamps
- **Rotation:** Daily automatic rotation

### Key Metrics
- Transfer success rate (target: >95%)
- Average execution time (target: <30s)
- Memory usage (target: <50MB per process)
- Database query performance (target: <500ms)

## 🧪 Testing

### Test Suite Coverage
- ✅ Database connectivity and schema validation
- ✅ Input validation and sanitization  
- ✅ NewStoreSeeder algorithm correctness
- ✅ CLI API endpoint functionality
- ✅ Performance under load
- ✅ Memory usage and resource management
- ✅ Error handling and recovery

### Running Tests
```bash
# Full test suite
php TestSuite.php

# Performance benchmarks  
php PerformanceBenchmark.php

# Specific component test
php -c "require 'TestSuite.php'; $suite = new TransferEngineTestSuite($con); $suite->runDatabaseTests();"
```

### Test Results Interpretation
- **PASS**: Component functioning correctly
- **FAIL**: Issue requires immediate attention
- **Success Rate >90%**: System healthy
- **Success Rate <70%**: System needs significant attention

## 🚨 Troubleshooting

### Common Issues

#### 1. Database Connection Failures
**Symptoms:** "Cannot connect to database" errors

**Solutions:**
```bash
# Check database connectivity
php cli_api.php?action=test_db

# Verify MySQL service
systemctl status mysql

# Check connection parameters
grep -r "mysql" ../../functions/mysql.php
```

#### 2. Empty Query Results  
**Symptoms:** "No products found" despite inventory existing

**Solutions:**
```sql
-- Check for deleted_at clause issues
SELECT COUNT(*) FROM vend_inventory WHERE deleted_at IS NULL;
SELECT COUNT(*) FROM vend_inventory WHERE deleted_at = '0000-00-00 00:00:00';

-- Verify outlet existence
SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00';
```

#### 3. Memory Issues
**Symptoms:** Fatal memory limit errors

**Solutions:**
```php
// Increase memory limit temporarily
ini_set('memory_limit', '256M');

// Check current usage
echo "Memory: " . round(memory_get_usage(true)/1024/1024, 2) . "MB\n";

// Enable memory monitoring
$logger = new TransferLogger('DEBUG', true);
```

#### 4. Performance Problems
**Symptoms:** Slow execution times >30 seconds

**Solutions:**
```bash
# Run performance benchmark
php PerformanceBenchmark.php

# Check slow query log
tail -f /var/log/mysql/mysql-slow.log

# Optimize database
ANALYZE TABLE vend_inventory, vend_outlets, vend_products;
```

### Error Codes

| Code | Description | Action |
|------|-------------|--------|
| DB001 | Database connection failed | Check MySQL service and credentials |
| VAL001 | Input validation failed | Verify parameter format and requirements |
| SEED001 | No suitable products found | Check inventory levels and outlet status |
| MEM001 | Memory limit exceeded | Increase memory limit or optimize batch size |
| PERF001 | Query timeout | Optimize database queries and indexes |

## 📈 Performance Optimization

### Database Optimization
```sql
-- Add indexes for common queries
CREATE INDEX idx_inventory_outlet_level ON vend_inventory(outlet_id, inventory_level);
CREATE INDEX idx_outlets_active ON vend_outlets(deleted_at, id);
CREATE INDEX idx_products_active ON vend_products(deleted_at, id);

-- Optimize queries with EXPLAIN
EXPLAIN SELECT * FROM vend_inventory WHERE outlet_id = ? AND inventory_level > ?;
```

### Application Optimization
- Use connection pooling for high-concurrency scenarios
- Implement query result caching for repeated operations  
- Batch database operations where possible
- Monitor memory usage and implement cleanup routines

### System Optimization
- Enable query caching in MySQL configuration
- Use SSD storage for database files
- Implement proper backup and recovery procedures
- Monitor system resources (CPU, memory, disk I/O)

## 🔐 Security Considerations

### Input Validation
All user inputs are validated through `TransferErrorHandler`:
- UUID format validation for outlet IDs
- Integer range validation for numeric parameters  
- SQL injection prevention via prepared statements
- XSS protection for web interfaces

### Database Security
- Use prepared statements exclusively
- Implement proper error handling to prevent information disclosure
- Regular security audits of database permissions
- Encrypted connections for production environments

### Access Control
- CLI API designed for internal use only
- Production dashboard requires authentication
- Log files contain no sensitive information
- Session IDs for tracking and audit trails

## 📝 Deployment Guide

### Production Deployment

1. **Pre-deployment Checklist**
   ```bash
   # Run full test suite
   php TestSuite.php
   
   # Verify database connectivity
   php cli_api.php?action=test_db
   
   # Check system resources
   php PerformanceBenchmark.php
   ```

2. **Backup Procedures**
   ```bash
   # Create system backup
   tar -czf newtransfer_backup_$(date +%Y%m%d).tar.gz .
   
   # Database backup
   mysqldump vend_database > backup_$(date +%Y%m%d).sql
   ```

3. **Deployment Steps**
   - Upload files to production directory
   - Verify file permissions (755 for directories, 644 for files)
   - Test database connectivity
   - Run smoke tests
   - Monitor logs for errors

4. **Post-deployment Validation**
   ```bash
   # Verify core functionality
   php cli_api.php?action=test_db
   php cli_api.php?action=get_outlets
   
   # Check logs for errors
   tail -f logs/transfer_$(date +%Y-%m-%d).log
   ```

### Rollback Procedures
1. Stop current processes
2. Restore from backup
3. Verify database integrity  
4. Run validation tests
5. Monitor system stability

## 🔄 Maintenance

### Daily Tasks
- Monitor log files for errors
- Check system resource usage
- Verify transfer completion rates
- Review performance metrics

### Weekly Tasks  
- Run comprehensive test suite
- Perform database maintenance (ANALYZE, OPTIMIZE)
- Review and archive old log files
- Update documentation as needed

### Monthly Tasks
- Full system backup and recovery test
- Performance benchmark analysis
- Security audit and updates
- Capacity planning review

## 📞 Support & Contact

### Development Team
- **Lead Developer:** Pearce Stephens
- **Company:** Ecigdis Ltd (The Vape Shed)
- **System:** CIS Transfer Management

### Emergency Contacts
- **System Issues:** Check logs first, then escalate to IT team
- **Database Problems:** Review troubleshooting guide, run diagnostic tools
- **Performance Issues:** Run benchmark suite, analyze results

### Resources
- **Documentation:** This file and inline code comments
- **Test Suite:** `TestSuite.php` for validation
- **Monitoring:** `production_dashboard.html` for real-time status
- **Benchmarks:** `PerformanceBenchmark.php` for performance analysis

---

## 📋 Appendix

### File Structure
```
NewTransferV3/
├── index.php                    # Main transfer engine (1808 lines)
├── cli_api.php                  # CLI interface (259 lines)  
├── NewStoreSeeder.php           # Seeder algorithm (381+ lines)
├── TransferLogger.php           # Logging system
├── TransferErrorHandler.php     # Error management
├── TestSuite.php               # Comprehensive tests
├── PerformanceBenchmark.php    # Performance analysis
├── production_dashboard.html   # Web interface
├── config.php                  # Configuration settings
└── logs/                       # Log file directory
    └── transfer_YYYY-MM-DD.log
```

### Dependencies
- PHP 8.0+ with MySQLi extension
- MySQL/MariaDB 10.5+
- Web server (Apache/Nginx) for dashboard
- Sufficient memory (minimum 128MB recommended)

### Version History
- v3.0.0 - Complete system rewrite with enhanced features
- v3.1.0 - Added comprehensive logging and error handling  
- v3.2.0 - Implemented production dashboard and monitoring
- v3.3.0 - Added test suite and performance benchmarking

---

**Document Version:** 1.0  
**Last Updated:** January 2024  
**System Version:** NewTransferV3 v3.3.0
