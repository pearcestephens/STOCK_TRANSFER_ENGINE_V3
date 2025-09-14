# Deployment and Operations Guide

## 🚀 Quick Deployment Checklist

### Pre-Deployment (5 minutes)
```bash
# 1. Navigate to system directory
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3

# 2. Run quick system check
php cli_api.php?action=test_db
echo "✅ Database connectivity: $(if [ $? -eq 0 ]; then echo 'PASS'; else echo 'FAIL'; fi)"

# 3. Verify core files exist
ls -la index.php cli_api.php NewStoreSeeder.php TransferLogger.php
echo "✅ Core files present"

# 4. Check file permissions
chmod 755 *.php
echo "✅ Permissions set"

# 5. Quick API test
php cli_api.php?action=get_outlets | head -5
echo "✅ API responsive"
```

### Production Deployment (2 minutes)
```bash
# 1. Create backup
tar -czf ../backup_newtransfer_$(date +%Y%m%d_%H%M%S).tar.gz .

# 2. Run comprehensive tests
php TestSuite.php | grep "Success Rate"

# 3. Performance check
php PerformanceBenchmark.php | grep "PERFORMANCE SUMMARY" -A 10

# 4. Start monitoring
echo "System deployed. Monitor via production_dashboard.html"
```

## 📊 Operations Dashboard

### System Status Commands
```bash
# Real-time system check
php STATUS.php

# Database health
php cli_api.php?action=test_db | jq '.success'

# Memory usage
php -r "echo 'Memory: ' . round(memory_get_usage(true)/1024/1024, 2) . 'MB' . PHP_EOL;"

# Active processes
ps aux | grep -i transfer | grep -v grep
```

### Log Monitoring
```bash
# Today's logs
tail -f logs/transfer_$(date +%Y-%m-%d).log

# Error summary
grep -i "error\|fail" logs/transfer_$(date +%Y-%m-%d).log | tail -10

# Performance metrics
grep "execution_time" logs/transfer_$(date +%Y-%m-%d).log | tail -5
```

## 🛠️ Daily Operations

### Morning Health Check (30 seconds)
```bash
#!/bin/bash
echo "🌅 NewTransferV3 Morning Health Check - $(date)"
echo "================================================"

# Database connectivity
DB_STATUS=$(php cli_api.php?action=test_db 2>/dev/null | grep -o '"success":[^,]*' | cut -d: -f2)
echo "Database: $(if [ "$DB_STATUS" == "true" ]; then echo '✅ Connected'; else echo '❌ Failed'; fi)"

# Outlet count
OUTLET_COUNT=$(php cli_api.php?action=get_outlets 2>/dev/null | grep -o '"total_outlets":[0-9]*' | cut -d: -f2)
echo "Outlets: ${OUTLET_COUNT:-0} available"

# Log file size
LOG_FILE="logs/transfer_$(date +%Y-%m-%d).log"
if [ -f "$LOG_FILE" ]; then
    LOG_SIZE=$(du -h "$LOG_FILE" | cut -f1)
    echo "Today's logs: $LOG_SIZE"
else
    echo "Today's logs: New day, no logs yet"
fi

# Recent errors
ERROR_COUNT=$(grep -c "ERROR\|FAIL" "$LOG_FILE" 2>/dev/null || echo "0")
echo "Errors today: $ERROR_COUNT"

echo "================================================"
echo "$(if [ "$DB_STATUS" == "true" ] && [ "${OUTLET_COUNT:-0}" -gt "0" ]; then echo '🟢 System Healthy'; else echo '🔴 Requires Attention'; fi)"
```

### Performance Monitoring
```bash
# Quick performance check
echo "📈 Performance Snapshot - $(date)"
echo "=================================="

# Database query performance
php -r "
require '../../functions/mysql.php';
connectToSQL();
global \$con;
\$start = microtime(true);
\$result = \$con->query('SELECT COUNT(*) FROM vend_inventory');
\$time = round((microtime(true) - \$start) * 1000, 2);
echo \"Database query: {\$time}ms\" . PHP_EOL;
"

# Memory usage check
php -r "echo 'PHP Memory: ' . round(memory_get_usage(true)/1024/1024, 2) . 'MB / ' . ini_get('memory_limit') . PHP_EOL;"

# Disk space
df -h . | tail -1 | awk '{print "Disk space: " $4 " available"}'

echo "=================================="
```

## 🔧 Troubleshooting Runbook

### Issue: Database Connection Failed
```bash
# Diagnosis
echo "🔍 Diagnosing database connection..."
php cli_api.php?action=test_db

# Common fixes
echo "🛠️ Attempting fixes..."

# 1. Check MySQL service
systemctl status mysql || echo "MySQL service may be down"

# 2. Test direct connection
php -r "
try {
    \$con = new mysqli('localhost', 'username', 'password', 'database');
    if (\$con->connect_error) {
        echo 'Connection failed: ' . \$con->connect_error . PHP_EOL;
    } else {
        echo 'Direct connection: Success' . PHP_EOL;
    }
} catch (Exception \$e) {
    echo 'Connection error: ' . \$e->getMessage() . PHP_EOL;
}
"

# 3. Check configuration
grep -r "mysql" ../../functions/mysql.php | head -5
```

### Issue: No Transfer Candidates Found
```bash
# Diagnosis
echo "🔍 Diagnosing candidate selection..."

# Check inventory data
php -r "
require '../../functions/mysql.php';
connectToSQL();
global \$con;

echo 'Inventory records: ';
\$result = \$con->query('SELECT COUNT(*) as count FROM vend_inventory WHERE deleted_at IS NULL OR deleted_at = \"0000-00-00 00:00:00\"');
\$row = \$result->fetch_assoc();
echo \$row['count'] . PHP_EOL;

echo 'Records with stock: ';
\$result = \$con->query('SELECT COUNT(*) as count FROM vend_inventory WHERE inventory_level > 0 AND (deleted_at IS NULL OR deleted_at = \"0000-00-00 00:00:00\")');
\$row = \$result->fetch_assoc();
echo \$row['count'] . PHP_EOL;
"

# Check outlet availability
php cli_api.php?action=get_outlets | grep -o '"total_outlets":[0-9]*'
```

### Issue: High Memory Usage
```bash
# Memory analysis
echo "💾 Memory Analysis"
echo "=================="

# Current PHP memory
php -r "
echo 'Current: ' . round(memory_get_usage(true)/1024/1024, 2) . 'MB' . PHP_EOL;
echo 'Peak: ' . round(memory_get_peak_usage(true)/1024/1024, 2) . 'MB' . PHP_EOL;
echo 'Limit: ' . ini_get('memory_limit') . PHP_EOL;
"

# System memory
free -h | grep Mem

# Process memory
ps aux --sort=-%mem | head -5

# Optimization suggestions
echo "💡 Optimization suggestions:"
echo "- Reduce candidate_limit in seeder options"
echo "- Implement batch processing for large datasets"
echo "- Clear variables after processing: unset(\$large_array)"
echo "- Consider increasing PHP memory limit temporarily"
```

### Issue: Slow Performance
```bash
# Performance diagnosis
echo "🐌 Performance Analysis"
echo "======================"

# Run quick benchmark
php PerformanceBenchmark.php | grep -A 20 "Database Query Speed"

# Check slow queries
echo "Recent slow operations:"
grep "execution_time" logs/transfer_$(date +%Y-%m-%d).log | tail -5

# System load
uptime

# Database performance
php -r "
require '../../functions/mysql.php';
connectToSQL();
global \$con;

echo 'Database variables:' . PHP_EOL;
\$result = \$con->query('SHOW VARIABLES LIKE \"slow_query_log\"');
while (\$row = \$result->fetch_assoc()) {
    echo '  ' . \$row['Variable_name'] . ': ' . \$row['Value'] . PHP_EOL;
}
"
```

## 📋 Maintenance Scripts

### Weekly Maintenance
```bash
#!/bin/bash
echo "🔧 Weekly Maintenance - $(date)"
echo "==============================="

# 1. Log rotation
find logs/ -name "*.log" -mtime +7 -exec gzip {} \;
echo "✅ Rotated old logs"

# 2. Database optimization
php -r "
require '../../functions/mysql.php';
connectToSQL();
global \$con;
\$tables = ['vend_inventory', 'vend_outlets', 'vend_products'];
foreach (\$tables as \$table) {
    \$con->query(\"ANALYZE TABLE \$table\");
    echo \"Analyzed \$table\" . PHP_EOL;
}
"

# 3. System cleanup
find . -name "*.tmp" -delete
find . -name ".DS_Store" -delete
echo "✅ Cleaned temporary files"

# 4. Performance check
php TestSuite.php | grep "Success Rate"

echo "==============================="
echo "✅ Weekly maintenance complete"
```

### Emergency Recovery
```bash
#!/bin/bash
echo "🚨 Emergency Recovery Procedure"
echo "==============================="

# 1. Stop any running transfers
pkill -f "NewTransferV3" || echo "No transfer processes found"

# 2. Check system status
php cli_api.php?action=test_db

# 3. Restore from backup if needed
BACKUP_DIR="../"
LATEST_BACKUP=$(ls -t ${BACKUP_DIR}backup_newtransfer_*.tar.gz 2>/dev/null | head -1)

if [ -n "$LATEST_BACKUP" ]; then
    echo "Latest backup: $LATEST_BACKUP"
    read -p "Restore from backup? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # Create current state backup
        tar -czf ../emergency_backup_$(date +%Y%m%d_%H%M%S).tar.gz .
        
        # Restore from backup
        tar -xzf "$LATEST_BACKUP"
        echo "✅ Restored from backup"
    fi
fi

# 4. Verify system
php TestSuite.php | grep -E "(PASS|FAIL|Success Rate)"

echo "==============================="
echo "🏥 Recovery procedure complete"
```

## 📞 Escalation Procedures

### Level 1: Self-Service (0-15 minutes)
1. Check system status: `php STATUS.php`
2. Review recent logs: `tail logs/transfer_$(date +%Y-%m-%d).log`
3. Run health check: `php cli_api.php?action=test_db`
4. Restart if needed: Stop processes, clear temp files, restart

### Level 2: Technical Support (15-30 minutes)
1. Run full diagnostic: `php TestSuite.php`
2. Generate performance report: `php PerformanceBenchmark.php`
3. Check system resources: CPU, memory, disk
4. Review database performance and connectivity

### Level 3: Emergency Escalation (30+ minutes)
1. Document the issue with logs and error messages
2. Create system snapshot for analysis
3. Contact development team with:
   - System status output
   - Recent error logs
   - Performance metrics
   - Steps already attempted

## 📊 Key Performance Indicators (KPIs)

### System Health Metrics
- **Database Response Time**: Target <100ms, Alert >500ms
- **Transfer Success Rate**: Target >95%, Alert <90%
- **Memory Usage**: Target <128MB, Alert >256MB  
- **Daily Error Count**: Target <5, Alert >20

### Monitoring Commands
```bash
# Response time check
php -r "
\$start = microtime(true);
require '../../functions/mysql.php';
connectToSQL();
\$time = round((microtime(true) - \$start) * 1000, 2);
echo \"DB Response: {\$time}ms\" . ((\$time > 500) ? ' ⚠️ SLOW' : ' ✅ OK') . PHP_EOL;
"

# Success rate (last 24 hours)
grep -c "success.*true" logs/transfer_$(date +%Y-%m-%d).log
```

---

**Operations Guide Version:** 1.0  
**Compatible with:** NewTransferV3 v3.3.0  
**Last Updated:** January 2024
