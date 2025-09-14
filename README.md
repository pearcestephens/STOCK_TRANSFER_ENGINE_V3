# NewTransferV3 Enterprise AI System

🧠 **AI-Orchestrated Inventory Optimization Platform** for Ecigdis Ltd (The Vape Shed)

Advanced enterprise inventory management system featuring AI-driven decision making, neural pattern recognition, and autonomous transfer orchestration across 17+ retail locations in New Zealand.

[![PHP Version](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.5+-orange.svg)](https://mariadb.org/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](LICENSE)

**Enterprise-Grade Stock Transfer Management System for Ecigdis Ltd (The Vape Shed)**

---

## 🚀 QUICK START

### **Immediate Access:**
- **Main Interface:** https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/working_simple_ui.php
- **Emergency Backup:** https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/emergency_transfer_ui.php
- **System Debug:** Add `?debug=1` to emergency URL

### **Command Line:**
```bash
# Simulate transfer (safe)
php index.php?action=run&simulate=1

# Execute live transfer (danger)  
php index.php?action=run&simulate=0
```

---

## 📁 CLEAN MVC ARCHITECTURE

```
NewTransferV3/
├── 🎛️ CONTROLLERS
│   ├── index.php                 ← Main Transfer Engine (1808 lines)
│   ├── working_simple_ui.php     ← Production Web Interface
│   ├── emergency_transfer_ui.php ← Emergency Backup Interface  
│   └── api.php                   ← REST API Endpoints
│
├── 📊 MODELS & SERVICES  
│   └── src/
│       ├── Models/Transfer.php
│       ├── Services/DatabaseService.php
│       ├── Core/TransferEngine.php
│       └── Controllers/TransferController.php
│
├── 🎨 TEMPLATES
│   ├── CIS_TEMPLATE             ← Main Template (540 lines)
│   └── CIS_TEMPLATE_BOT_FRIENDLY.php ← Bot Template (299 lines)
│
├── ⚙️ CONFIG
│   ├── config.php               ← System Configuration
│   ├── bootstrap.php            ← App Bootstrap
│   └── composer.json            ← Dependencies
│
├── 📚 DOCUMENTATION
│   └── docs/
│       ├── COMPLETE_SYSTEM_DOCUMENTATION.md
│       ├── DATABASE_SCHEMA.md
│       ├── DEPLOYMENT_CHECKLIST.md
│       ├── OPERATIONAL_GUIDE.md
│       └── PRODUCTION_READY_SUMMARY.md
│
├── 📊 LOGS
│   └── logs/transfer_operations.log
│
└── 🗂️ ARCHIVE
    ├── backup_files/    (15+ .bak files)
    ├── demo_files/      (20+ HTML demos)  
    ├── experimental/    (25+ prototypes)
    ├── utilities/       (15+ debug scripts)
    └── old_docs/        (10+ old documentation)
```

---

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

---

## 🛡️ ENTERPRISE GRADE

### **Architecture Standards:**
- **MVC Pattern** - Proper separation of concerns
- **Clean Code** - Professional coding standards  
- **Modular Design** - Reusable components and services
- **Documentation** - Comprehensive guides and procedures
- **Testing Ready** - PHPUnit configuration and test structure

### **Operational Standards:**
- **Production Ready** - Deployed and operational
- **Scalable Design** - Built for growth and expansion
- **Performance Optimized** - Fast response times
- **Security Hardened** - Enterprise security measures
- **Maintainable** - Easy to update and extend

---

## 📖 DOCUMENTATION

### **Quick Reference:**
- **[System Overview](docs/COMPLETE_SYSTEM_DOCUMENTATION.md)** - Complete system architecture
- **[User Guide](docs/OPERATIONAL_GUIDE.md)** - How to use the system
- **[Database Schema](docs/DATABASE_SCHEMA.md)** - Complete database structure  
- **[Deployment](docs/DEPLOYMENT_CHECKLIST.md)** - Production deployment procedures
- **[Production Summary](docs/PRODUCTION_READY_SUMMARY.md)** - Feature overview

### **Technical Specifications:**
- **Language:** PHP 8.1+ with strict typing
- **Database:** MySQL/MariaDB with optimized indexes
- **Framework:** Custom MVC with CIS template integration
- **Frontend:** Bootstrap 4 + jQuery + AJAX
- **Security:** Prepared statements, input validation, CSRF protection
- **Monitoring:** Structured logging, real-time status, health checks

---

## 🧹 CLEANUP COMPLETED

### **Before Cleanup:** ~100+ Files
**Cluttered with demos, experiments, and backup files**

### **After Cleanup:** ~20 Core Files  
**Clean MVC platform with proper organization**

### **Archived:** ~80 Files Safely Stored
- **15+ Backup Files** → `ARCHIVE/backup_files/`
- **20+ Demo HTML/JS** → `ARCHIVE/demo_files/`  
- **25+ Experimental Code** → `ARCHIVE/experimental/`
- **15+ Debug Utilities** → `ARCHIVE/utilities/`
- **10+ Old Documentation** → `ARCHIVE/old_docs/`

---

## 🎯 READY FOR PRODUCTION

**This is now a clean, professional, enterprise-grade transfer management platform with:**

✅ **Working Transfer System** - Fully operational with real data  
✅ **Clean MVC Architecture** - Professional code organization  
✅ **Complete Documentation** - Up-to-date guides and procedures  
✅ **Production Deployment** - Live and accessible system  
✅ **Zero Garbage** - All rubbish archived, only essentials remain  

---

## 🆘 SUPPORT

### **For Issues:**
1. **Check System Status:** Use debug endpoint `?debug=1`
2. **Review Operation Logs:** `logs/transfer_operations.log`  
3. **Use Emergency Interface:** Standalone backup UI available
4. **Consult Documentation:** Complete guides in `docs/` folder

### **Emergency Procedures:**
- **System Down:** Use emergency_transfer_ui.php (no dependencies)
- **Database Issues:** Check debug status and connection logs
- **Transfer Failures:** Review operation logs and error messages
- **Performance Issues:** Monitor system dashboard and active transfers

---

**🏆 ENTERPRISE-READY TRANSFER PLATFORM - DEPLOYED & OPERATIONAL**
