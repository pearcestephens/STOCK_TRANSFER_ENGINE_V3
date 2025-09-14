# 🚀 WORKING SIMPLE TRANSFER UI - OPERATIONAL GUIDE

## 📍 ACCESS INFORMATION

**URL:** https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/working_simple_ui.php

**File Location:** `/home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/working_simple_ui.php`

---

## 🎛️ USER MANUAL - HOW TO USE

### 1. TRANSFER EXECUTION

#### **Mode Selection (CRITICAL)**
- **Simulate:** Safe mode - shows what WOULD happen without making changes
- **Live Run:** DANGER MODE - actually creates transfers and modifies inventory

#### **Outlet Selection**
- **From Outlet:** Source store (leave blank for auto-detection)
- **To Outlet:** Destination store (leave blank for auto-detection)
- **Auto-Detection:** Engine determines optimal transfer routes

#### **Running a Transfer**
1. Select your mode (ALWAYS start with Simulate)
2. Choose outlets (optional)
3. Click "🚀 Run Transfer Engine"
4. Watch the output area for results
5. Check "Active Transfers" section for progress

### 2. MONITORING & STATUS

#### **System Status Panel**
- **Today/Week Transfers:** Count of transfers executed
- **Failed (24h):** Error count in last 24 hours
- **Outlets:** Total active stores
- **Engine Status:** IDLE or RUNNING
- **Log Size:** Current operation log size

#### **Active Transfers (Auto-refresh every 10s)**
- **Running Processes:** Shows actual transfer engine processes with PIDs
- **Active Transfers:** Database records in progress status
- **Real-time Updates:** Automatically refreshes every 10 seconds

#### **Recent Transfers**
- **Last 10 Transfers:** Most recent transfer records
- **Full Details:** Transfer ID, outlets, status, product count, dates
- **Status Colors:** 
  - Green = completed
  - Red = failed
  - Yellow = pending

### 3. BUTTONS & FUNCTIONS

| Button | Function | What It Does |
|--------|----------|-------------|
| 🚀 Run Transfer Engine | Execute transfer | Runs actual transfer engine with selected parameters |
| 🔄 Refresh Outlets | Update dropdowns | Reloads outlet list from database |
| 🔄 Refresh Transfers | Update history | Reloads recent transfer list |
| 📊 Refresh Status | Update stats | Reloads system statistics |
| 🗑️ Clear Output | Clear display | Clears the output display area |
| 📜 View Operation Logs | Open logs | Opens operation log file in new tab |

---

## ⚠️ SAFETY & BEST PRACTICES

### BEFORE GOING LIVE
1. **ALWAYS test in Simulate mode first**
2. **Check system status for any failures**
3. **Verify outlet selection is correct**
4. **Monitor active transfers before starting new ones**

### ERROR HANDLING
- All errors are logged to `logs/transfer_operations.log`
- Failed operations show detailed error messages
- Database connection failures are automatically handled
- Process timeouts prevent hanging transfers

### SECURITY FEATURES
- Input validation on all parameters
- SQL injection protection with prepared statements
- Proper error logging without exposing sensitive data
- Process isolation and timeout protection

---

## 🛠️ TROUBLESHOOTING GUIDE

### Problem: "Database connection failed"
**Solution:** 
- Check MySQL service status
- Verify credentials in config.php
- Contact system administrator

### Problem: "Transfer engine not found"
**Solution:**
- Verify index.php exists in NewTransferV3 directory
- Check file permissions
- Ensure PHP has execution rights

### Problem: Transfer runs but no output
**Solution:**
- Check `logs/transfer_operations.log` for details
- Verify PHP error logs
- Check process timeout (300 seconds limit)

### Problem: Buttons not responding
**Solution:**
- Check browser console for JavaScript errors
- Refresh the page
- Verify AJAX endpoints are accessible

### Problem: Active transfers show stale processes
**Solution:**
- Stale PID files are automatically cleaned
- Manually delete files in `logs/transfer_*.pid` if needed
- Restart web server if persistent

---

## 📊 MONITORING & LOGS

### Operation Logs
**Location:** `logs/transfer_operations.log`
**Content:** All transfer operations, errors, and system events
**Rotation:** Manual cleanup required when file gets large

### Log Entry Examples
```
2025-09-14 18:30:15 - Transfer execution: mode=simulate, from=1, to=2
2025-09-14 18:30:25 - Transfer completed: return_code=0, output_length=1234
2025-09-14 18:30:30 - Recent transfers fetched: 10 transfers
```

### System Monitoring
- **Auto-refresh:** Active transfers update every 10 seconds
- **Manual refresh:** Use refresh buttons for immediate updates
- **Real-time status:** Process monitoring with PID tracking

---

## 🚨 PRODUCTION DEPLOYMENT

### Pre-Deployment Checklist
- [ ] Test in simulate mode
- [ ] Verify database connectivity
- [ ] Check all buttons function
- [ ] Confirm logging works
- [ ] Test error conditions
- [ ] Backup existing files

### Deployment Steps
1. **Backup current system**
2. **Upload working_simple_ui.php**
3. **Create logs directory if not exists**
4. **Set proper file permissions**
5. **Test basic functionality**
6. **Monitor initial operations**

### Post-Deployment Validation
- [ ] Access URL loads correctly
- [ ] System status shows valid data
- [ ] Outlet dropdowns populate
- [ ] Simulate mode works
- [ ] Logs are being written
- [ ] Active transfer monitoring works

---

## 📞 SUPPORT & ESCALATION

### For Technical Issues:
1. Check operation logs first
2. Verify system status panel
3. Test with simulate mode
4. Document exact error messages
5. Contact system administrator

### For Production Issues:
1. **STOP all live transfers immediately**
2. Check active transfers panel
3. Review recent transfer history
4. Check for failed transfers
5. Escalate to management

---

## 🔧 TECHNICAL SPECIFICATIONS

### Dependencies
- PHP 7.4+ with mysqli extension
- MySQL/MariaDB database
- Web server with shell_exec() enabled
- jQuery and Bootstrap (loaded via template)

### Performance
- Transfer execution timeout: 300 seconds (5 minutes)
- Auto-refresh interval: 10 seconds
- Database query limits: 100 records max
- Log file monitoring: Real-time

### Security
- Prepared SQL statements
- Input validation and sanitization
- Process isolation
- Error logging without sensitive data exposure
- Safe file operations with proper locking

---

**REMEMBER: This system controls actual inventory transfers. Always test thoroughly before using in production!**
