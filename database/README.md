# MRP/ERP Database Management

This directory contains a comprehensive database synchronization system for the MRP/ERP project with safety-first principles and environment management capabilities.

## 🚀 Quick Start

```bash
# Setup the sync system
./scripts/setup.sh

# Create fresh database with latest schema
./scripts/migrate.sh fresh

# Add reference data
./scripts/seed.sh reference

# Add test data for development
./scripts/seed.sh test
```

## 📁 Directory Structure

```
database/
├── README_SYNC_SYSTEM.md    # Complete system documentation
├── QUICK_REFERENCE.md       # Quick command reference
├── schema/                  # Base database schemas
├── migrations/              # Incremental schema changes
├── seeds/                   # Reference and test data
├── backups/                 # Automated backups (created)
└── scripts/                 # Management tools
    ├── setup.sh             # System initialization
    ├── backup.sh            # Backup management
    ├── restore.sh           # Safe restoration
    ├── migrate.sh           # Migration runner
    ├── sync.sh              # Environment sync
    └── seed.sh              # Data seeding
```

## 🛠️ Core Features

### ✅ Safety First
- **Dry-run mode** for all operations
- **Automatic backups** before changes
- **Rollback capabilities** for migrations
- **Verification checks** for data integrity

### ✅ Environment Management
- **Multi-environment** sync (dev/staging/prod)
- **Schema comparison** between environments
- **Safe production** deployment procedures
- **Connection testing** and validation

### ✅ Migration System
- **Version tracking** with rollback SQL
- **Batch control** with --steps parameter
- **Fresh install** capability
- **Interactive rollback** to specific versions

### ✅ Seed Data Management
- **Reference data** (UOM, categories, etc.)
- **Test datasets** for development
- **Idempotent operations** (safe to re-run)
- **Execution tracking** and history

## 📖 Documentation

| Document | Purpose |
|----------|---------|
| `README_SYNC_SYSTEM.md` | Complete system documentation |
| `QUICK_REFERENCE.md` | Command quick reference |
| This file | Overview and getting started |

## 🎯 Common Operations

### Daily Development
```bash
./scripts/migrate.sh status     # Check migration status
./scripts/migrate.sh up         # Apply pending migrations
./scripts/backup.sh --auto      # Create daily backup
```

### Environment Setup
```bash
./scripts/migrate.sh fresh      # Fresh database install
./scripts/seed.sh reference     # System reference data
./scripts/seed.sh test          # Development test data
```

### Production Deployment
```bash
./scripts/backup.sh --full      # Create production backup
./scripts/migrate.sh up --dry-run --backup  # Safe migration
./scripts/sync.sh status prod   # Verify production
```

### Emergency Recovery
```bash
./scripts/restore.sh --list     # List available backups
./scripts/restore.sh backup.sql.gz  # Restore from backup
./scripts/migrate.sh down       # Rollback migrations
```

## 🔐 Security Features

- **Backup encryption** and compression
- **Restricted file permissions** on sensitive data
- **Connection validation** before operations
- **Confirmation prompts** for destructive operations
- **Production safeguards** with explicit confirmations

## 🚨 Emergency Contacts

### Quick Recovery Commands
```bash
# Create emergency backup
./scripts/backup.sh --force

# Restore from specific backup
./scripts/restore.sh --backup-first backup_file.sql.gz

# Rollback last migration
./scripts/migrate.sh down
```

## 📊 System Status

- **Setup Status**: Run `./scripts/setup.sh --verify`
- **Database Status**: Run `./scripts/migrate.sh status`
- **Backup Status**: Run `./scripts/backup.sh --list`
- **Environment Status**: Run `./scripts/sync.sh list-envs`

## 🔧 Maintenance

### Automated Tasks
```bash
# Add to crontab for daily backups
0 2 * * * cd /var/www/html/mrp_erp/database && ./scripts/backup.sh --auto

# Weekly cleanup of old backups
0 3 * * 0 cd /var/www/html/mrp_erp/database && ./scripts/backup.sh --cleanup
```

### Manual Maintenance
- Review backup sizes and retention
- Monitor migration performance
- Update environment configurations
- Verify cross-environment schema consistency

---

**Need Help?** Run any script with `--help` for detailed usage information.

**Last Updated**: 2025-08-20 - Comprehensive sync system v1.0