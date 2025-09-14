# NewTransferV3 Enterprise AI System

🧠 **AI-Orchestrated Inventory Optimization Platform** for Ecigdis Ltd (The Vape Shed)

Advanced enterprise inventory management system featuring AI-driven decision making, neural pattern recognition, and autonomous transfer orchestration across 17+ retail locations in New Zealand.

[![PHP Version](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.5+-orange.svg)](https://mariadb.org/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](LICENSE)

## 🚀 Core Features

### AI & Neural Intelligence
- **Neural Brain Integration**: Real AI decision storage with pattern recognition
- **7-Phase AI Orchestration**: Autonomous decision-making pipeline
- **GPT Auto-Categorization**: Intelligent product classification
- **Machine Learning Optimization**: Continuous algorithm improvement

### Advanced Transfer Operations
- **Multi-Mode Operations**: All stores, hub-to-stores, specific transfers, new store seeding
- **Smart Pack Optimization**: Intelligent outer pack rounding with multiple algorithms
- **Fair-Share Distribution**: Advanced allocation algorithms with profitability weighting
- **Dynamic Schema Resolution**: Automatic database schema adaptation

### Enterprise Integration
- **Real-Time Vend POS Sync**: Live inventory level synchronization
- **Advanced Analytics**: Sales velocity, ABC classification, stockout/overstock risk
- **Performance Monitoring**: Comprehensive metrics and benchmarking
- **Audit Trail**: Complete operation logging with session management

### Production-Grade Architecture
- **3GB Memory Handling**: Large-scale operation support
- **90-Minute Execution Windows**: Long-running transfer optimization
- **Enterprise Error Handling**: Robust exception management
- **Security Hardening**: Input validation, SQL injection protection

## 📋 System Requirements

- **PHP**: 8.1+ (strict typing, 3GB memory support)
- **Database**: MariaDB 10.5+ / MySQL 8.0+
- **Web Server**: Apache/Nginx with PHP-FPM
- **Memory**: Minimum 4GB RAM for production operations
- **Storage**: SSD recommended for database operations

## ⚙️ Installation & Setup

### 1. Environment Preparation
```bash
# Clone repository
git clone [repository-url] newtransferv3
cd newtransferv3

# Set permissions
chmod 755 *.php
chmod 644 *.css *.js *.html
```

### 2. Database Configuration
```sql
-- Import database schema (see docs/DATABASE_SCHEMA.md)
-- Configure neural_memory_core table for AI operations
-- Set up transfer-related tables with proper indexes
```

### 3. System Configuration
```bash
# Copy configuration template
cp config.example.php config.php

# Edit with your credentials (NEVER commit config.php)
nano config.php
```

### 4. Neural Brain Setup
The system includes a sophisticated Neural Brain integration:
- Configure API endpoints in `config.php`
- Ensure neural_memory_core table exists
- Test AI integration with: `php -f ENGINE_DEBUG.php`

## 🎮 Operation Modes

### Command Line Interface
```bash
# Full store network optimization
php index.php mode=all_stores simulate=0

# Hub to stores distribution
php index.php mode=hub_to_stores simulate=0 

# New store seeding with AI optimization
php index.php mode=new_store_seed target_outlet=123 simulate=1

# Specific store transfer
php index.php mode=specific_transfer from_outlet=1 to_outlet=2 simulate=1
```

### Web Dashboard Interface
- **Production Dashboard**: `/dashboard.php` - Live operational control
- **Emergency Interface**: `/emergency_transfer_ui.php` - Crisis management
- **Analytics Portal**: `/real_dashboard.php` - Performance monitoring

### API Integration
```bash
# Execute AI-orchestrated transfer
curl "https://your-domain.com/api.php?action=run_transfer&mode=all_stores&simulate=1"

# Check Neural Brain status
curl "https://your-domain.com/api.php?action=neural_status"

# Retrieve performance metrics
curl "https://your-domain.com/api.php?action=get_metrics"
```

## 🧠 AI & Neural Brain Features

### Neural Decision Storage
- **Pattern Recognition**: Learns from historical transfer decisions
- **Confidence Scoring**: 0.0-1.0 confidence levels for AI recommendations
- **Session Management**: Tracks decision context and outcomes
- **Similar Solution Retrieval**: Finds patterns in previous successful operations

### AI Orchestration Pipeline
1. **Initialization**: System validation and configuration
2. **Data Gathering**: Inventory analysis and demand forecasting  
3. **AI Decision**: Neural Brain consultation and recommendation
4. **Optimization**: Transfer calculation with advanced algorithms
5. **Execution**: Database operations and POS synchronization
6. **Monitoring**: Performance tracking and anomaly detection
7. **Learning**: Neural pattern storage and system improvement

## 📊 Advanced Analytics

The system provides comprehensive analytics including:
- **Sales Velocity**: Product movement analysis
- **ABC Classification**: Automatic product categorization (A/B/C/D)
- **Stockout/Overstock Risk**: Predictive inventory risk scoring
- **Profit Impact Analysis**: Transfer profitability optimization
- **ML Priority Scoring**: Machine learning-driven prioritization

## 🔧 Configuration Reference

### Core Settings (config.php)
```php
'transfer' => [
    'cover_days' => 14,              // Demand forecast period
    'buffer_pct' => 20,              // Safety stock percentage  
    'rounding_mode' => 'smart',      // Pack optimization: nearest|up|down|smart
    'margin_factor' => 1.2,          // Profitability weighting
]
```

### Neural Brain Configuration
```php
'neural_brain' => [
    'enabled' => true,               // Enable AI integration
    'confidence_threshold' => 0.7,   // Minimum confidence for auto-execution
    'learning_enabled' => true,      // Enable pattern learning
]
```

## 🛡️ Security & Compliance

- **Input Validation**: All parameters validated and sanitized
- **SQL Injection Protection**: Prepared statements throughout
- **Access Control**: Outlet-based authorization system
- **Audit Logging**: Complete operation trail with timestamps
- **Error Handling**: Secure error messages without information disclosure

## 📈 Performance Monitoring

### Built-in Benchmarking
The system includes comprehensive performance monitoring:
- Memory usage tracking (up to 3GB operations)
- Execution time monitoring (90-minute windows)
- Database query optimization
- Real-time performance metrics

### Health Checks
```bash
# System health verification
php STATUS.php

# Performance benchmarking  
php PerformanceBenchmark.php

# Database integrity check
php db_check.php
```

## 🔄 Operational Procedures

### Simulation Mode
Always test in simulation mode first:
```bash
php index.php mode=all_stores simulate=1 debug=1
```

### Production Operations
```bash
# Morning optimization run
php index.php mode=all_stores simulate=0 cover_days=7

# Emergency rebalancing
php index.php mode=hub_to_stores simulate=0 urgent=1
```

## 📚 Documentation

- `docs/SYSTEM_ARCHITECTURE.md` - Technical architecture overview
- `docs/API_DOCUMENTATION.md` - Complete API reference  
- `docs/DATABASE_SCHEMA.md` - Database structure and relationships
- `docs/DEPLOYMENT_GUIDE.md` - Production deployment procedures
- `docs/TROUBLESHOOTING.md` - Common issues and solutions

## 🚨 Support & Maintenance

For technical support:
- Check system logs in `/logs/` directory
- Review operational dashboard for anomalies
- Consult troubleshooting guide in documentation
- Contact: Ecigdis Ltd Technical Team

## 📄 License

Proprietary software developed for Ecigdis Ltd (The Vape Shed).  
All rights reserved. Unauthorized reproduction or distribution prohibited.

---

**⚡ Enterprise AI-Powered Inventory Optimization Platform**  
*Revolutionizing retail inventory management through artificial intelligence*