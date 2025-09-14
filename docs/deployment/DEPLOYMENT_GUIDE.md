# NewTransferV3 Deployment Guide

## Prerequisites

### System Requirements
- **OS**: Ubuntu 20.04+ or CentOS 8+
- **PHP**: 8.1+ with extensions: mysqli, pdo, json, curl, mbstring
- **Database**: MariaDB 10.5+ or MySQL 8.0+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 4GB RAM (8GB recommended)
- **Storage**: 50GB+ for logs and backups

### Access Requirements
- SSH access to production server
- Database admin credentials
- Web server configuration access
- Backup/restore capabilities

## Pre-Deployment Checklist

### 1. Environment Verification
```bash
# Check PHP version and extensions
php -v
php -m | grep -E "(mysqli|pdo|json|curl|mbstring)"

# Verify database connection
mysql -u jcepnzzkmj -p jcepnzzkmj -e "SELECT VERSION();"

# Check web server status
systemctl status apache2
# OR
systemctl status nginx
```

### 2. Backup Current System
```bash
# Database backup
mysqldump -u jcepnzzkmj -p jcepnzzkmj > backup_$(date +%Y%m%d_%H%M%S).sql

# File system backup
tar -czf newtransfer_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
  /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/

# Configuration backup
cp -r /home/master/applications/jcepnzzkmj/public_html/assets/functions/ \
  /tmp/functions_backup_$(date +%Y%m%d_%H%M%S)/
```

### 3. Dependencies Installation
```bash
# Navigate to project directory
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/

# Install Composer dependencies
php composer.phar install --no-dev --optimize-autoloader

# Set proper permissions
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 777 logs/
```

## Deployment Steps

### 1. Code Deployment
```bash
# Stop any running transfers
php index.php?action=stop_all

# Deploy new code (assuming Git deployment)
git pull origin main

# Or manual file upload
# Upload files to: /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/

# Update autoloader
php composer.phar dump-autoload --optimize
```

### 2. Database Migrations
```bash
# Check current schema version
php db_check.php

# Run migrations (if any)
php migration_runner.php

# Verify schema integrity
php check_table_structure.php
```

### 3. Configuration Updates
```bash
# Review configuration
cat config.php

# Update environment-specific settings
# Edit config.php for:
# - Database credentials
# - Neural Brain API endpoints
# - Transfer parameters
# - Logging levels
```

### 4. System Validation
```bash
# Test database connection
php test_db_connection.php

# Run system health check
php STATUS.php

# Test transfer engine in simulation mode
php index.php?action=run&simulate=1&max_products=5

# Verify API endpoints
curl "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/index.php?action=get_outlets"
```

## Post-Deployment Verification

### 1. Functional Testing
```bash
# Test primary interface
curl -I "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/working_simple_ui.php"

# Test emergency interface
curl -I "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/emergency_transfer_ui.php"

# Test API endpoints
curl "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/QUICK_STATUS.php"
```

### 2. Performance Verification
```bash
# Check response times
time php index.php?action=run&simulate=1

# Monitor memory usage
php -d memory_limit=1G index.php?action=run&simulate=1&max_products=100

# Verify log generation
tail -f logs/transfer_operations.log
```

### 3. Integration Testing
```bash
# Test Neural Brain connection
php index.php?action=test_neural

# Verify Vend integration
php test_vend_sync.php

# Check GPT services
php neural_brain_integration.php
```

## Rollback Procedure

### Emergency Rollback
```bash
# 1. Stop current operations
php index.php?action=emergency_stop

# 2. Restore previous code version
tar -xzf newtransfer_backup_YYYYMMDD_HHMMSS.tar.gz -C /tmp/
rsync -av /tmp/NewTransferV3/ \
  /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/

# 3. Restore database (if needed)
mysql -u jcepnzzkmj -p jcepnzzkmj < backup_YYYYMMDD_HHMMSS.sql

# 4. Verify system health
php STATUS.php
```

### Gradual Rollback
```bash
# 1. Switch to emergency interface only
# Disable primary interface via web server config

# 2. Monitor system stability
tail -f logs/system/*.log

# 3. Implement fixes and redeploy
# OR complete full rollback
```

## Monitoring Setup

### Log File Monitoring
```bash
# System logs
tail -f /var/log/apache2/error.log
tail -f /var/log/mysql/error.log

# Application logs
tail -f logs/transfer/*.log
tail -f logs/system/*.log
```

### Health Monitoring
```bash
# Add to crontab for automated monitoring
*/5 * * * * curl -s "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/STATUS.php" | grep -q "OK" || echo "ALERT: NewTransferV3 down" | mail -s "System Alert" admin@ecigdis.co.nz
```

### Performance Monitoring
```bash
# Database performance
mysql -u jcepnzzkmj -p -e "SHOW PROCESSLIST; SHOW STATUS LIKE 'Slow_queries';"

# System resources
htop
iostat -x 1
```

## Maintenance Tasks

### Daily Operations
```bash
# Log rotation
logrotate /etc/logrotate.d/newtransferv3

# Performance analysis
php PerformanceBenchmark.php

# Health check
php QUICK_STATUS.php
```

### Weekly Operations
```bash
# Database optimization
mysql -u jcepnzzkmj -p jcepnzzkmj -e "OPTIMIZE TABLE stock_transfers, stock_products_to_transfer, vend_inventory;"

# Archive old transfers
php archive_old_transfers.php

# System cleanup
php CLEANUP_NOW.php
```

### Monthly Operations
```bash
# Full system backup
./full_backup.sh

# Performance review
php generate_performance_report.php

# Security audit
php security_audit.php
```

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
```bash
# Check error logs
tail -50 /var/log/apache2/error.log

# Verify file permissions
ls -la index.php working_simple_ui.php

# Test PHP syntax
php -l index.php
```

2. **Database Connection Issues**
```bash
# Test connection
php test_db_connection.php

# Check MySQL status
systemctl status mysql

# Verify credentials
mysql -u jcepnzzkmj -p jcepnzzkmj -e "SELECT 1;"
```

3. **Transfer Failures**
```bash
# Check transfer logs
tail -100 logs/transfer/transfer_operations.log

# Run diagnostic
php ENGINE_DEBUG.php

# Test in simulation mode
php index.php?action=run&simulate=1&max_products=1
```

### Emergency Contacts
- **System Administrator**: Pearce Stephens (pearce.stephens@ecigdis.co.nz)
- **Database Administrator**: CIS Team
- **Infrastructure Support**: Cloudways Support

---

*Deployment Guide Version: 1.0*  
*Last Updated: September 14, 2025*  
*System: NewTransferV3 Enterprise*
