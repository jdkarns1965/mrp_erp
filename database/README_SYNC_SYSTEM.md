# MRP/ERP Database Synchronization System

## Overview

This comprehensive database synchronization system provides safe, reliable tools for managing database schemas, migrations, backups, and environment synchronization for the MRP/ERP project. Built with safety-first principles, every operation includes safeguards, confirmations, and rollback capabilities.

## Directory Structure

```
database/
├── schema/                 # Base database schemas
│   └── 01_base_schema.sql     # Core system schema
├── migrations/             # Incremental schema changes
│   ├── 001_inventory_transactions.sql
│   ├── 002_planning_tables.sql
│   └── 003_mrp_enhancements.sql
├── seeds/                  # Reference and test data
│   ├── 00_reference_data.sql      # System reference data
│   ├── 01_test_data.sql           # Basic test data
│   ├── 02_production_data.sql     # Production test data
│   └── 03_sample_orders.sql       # Sample orders
├── backups/               # Database backups (auto-created)
│   ├── mrp_erp_full_*.sql.gz      # Full backups
│   ├── mrp_erp_schema_*.sql.gz    # Schema-only backups
│   └── rollback_*.sql.gz          # Rollback points
└── scripts/               # Management scripts
    ├── backup.sh              # Backup management
    ├── restore.sh             # Restoration system
    ├── migrate.sh             # Migration runner
    ├── sync.sh                # Environment sync
    └── seed.sh                # Seed data management
```

## Core Scripts

### 1. Backup System (`backup.sh`)

**Purpose**: Create safe, versioned backups with automatic compression and retention management.

**Key Features**:
- Multiple backup types (full, schema-only, data-only)
- Automatic compression with gzip
- Backup verification and integrity checking
- Retention policy management (30-day default)
- Progress reporting and error handling

**Basic Usage**:
```bash
# Interactive full backup
./scripts/backup.sh

# Automated backup (for cron jobs)
./scripts/backup.sh --auto

# Schema-only backup
./scripts/backup.sh --schema-only

# List all available backups
./scripts/backup.sh --list

# Verify backup integrity
./scripts/backup.sh --verify backup_file.sql.gz

# Clean old backups
./scripts/backup.sh --cleanup
```

**Automated Backup Setup**:
```bash
# Add to crontab for daily backups at 2 AM
0 2 * * * cd /var/www/html/mrp_erp/database && ./scripts/backup.sh --auto
```

### 2. Restoration System (`restore.sh`)

**Purpose**: Safely restore databases with comprehensive safety checks and rollback capabilities.

**Safety Features**:
- Pre-restore verification and impact assessment
- Automatic rollback point creation
- Dry-run mode to preview changes
- Interactive confirmation prompts
- Database comparison tools

**Basic Usage**:
```bash
# List available backups and choose
./scripts/restore.sh

# Restore specific backup with safety backup
./scripts/restore.sh --backup-first backup_file.sql.gz

# Dry run to see what would happen
./scripts/restore.sh --dry-run backup_file.sql.gz

# Verify backup before restore
./scripts/restore.sh --verify backup_file.sql.gz

# Automated restore (use with caution)
./scripts/restore.sh --force --backup-first backup_file.sql.gz
```

### 3. Migration System (`migrate.sh`)

**Purpose**: Manage database schema changes with version tracking and rollback capabilities.

**Advanced Features**:
- Migration tracking with rollback SQL generation
- Dry-run mode to preview changes
- Batch migration control with --steps parameter
- Fresh install capability
- Interactive rollback to specific versions

**Basic Usage**:
```bash
# Show current migration status
./scripts/migrate.sh status

# Preview pending migrations
./scripts/migrate.sh up --dry-run

# Apply all pending migrations
./scripts/migrate.sh up

# Apply limited number of migrations
./scripts/migrate.sh up --steps=3

# Rollback last migration
./scripts/migrate.sh down

# Rollback multiple migrations
./scripts/migrate.sh down --steps=2

# Create new migration file
./scripts/migrate.sh create add_user_permissions

# Fresh install (DESTRUCTIVE)
./scripts/migrate.sh fresh --force
```

### 4. Environment Synchronization (`sync.sh`)

**Purpose**: Safely synchronize databases between development, staging, and production environments.

**Environment Safety**:
- Production operations require explicit confirmation
- Automatic backup creation for destructive operations
- Schema comparison between environments
- Selective table inclusion/exclusion

**Environment Configuration**:
```bash
# Edit sync.sh to configure your environments
ENVIRONMENTS[local]="localhost:3306"
ENVIRONMENTS[dev]="dev-server:3306"
ENVIRONMENTS[staging]="staging-server:3306"
ENVIRONMENTS[prod]="prod-server:3306"
```

**Basic Usage**:
```bash
# List available environments
./scripts/sync.sh list-envs

# Check environment status
./scripts/sync.sh status prod

# Pull from production (with backup)
./scripts/sync.sh pull prod --backup --schema-only

# Clone development to staging
./scripts/sync.sh clone dev staging --backup

# Compare schemas between environments
./scripts/sync.sh compare dev staging

# Dry run to see what would happen
./scripts/sync.sh pull prod --dry-run
```

### 5. Seed Data Management (`seed.sh`)

**Purpose**: Manage reference data and test data with idempotent operations and execution tracking.

**Seed File Naming Convention**:
- `00_reference_*` - System reference data
- `01_test_*` - Basic test data
- `02_production_*` - Production test scenarios
- `03_sample_*` - Sample orders and data
- `99_cleanup_*` - Cleanup scripts

**Basic Usage**:
```bash
# List available seed files
./scripts/seed.sh list

# Run all seeds
./scripts/seed.sh run

# Run specific seed
./scripts/seed.sh run 01_test_data

# Apply reference data only
./scripts/seed.sh reference

# Clear and reapply all seeds
./scripts/seed.sh refresh

# Create new seed file
./scripts/seed.sh create customer_data

# Show seed execution history
./scripts/seed.sh status --track
```

## Common Workflows

### 1. Setting Up a New Development Environment

```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE mrp_erp;"

# 2. Apply base schema and migrations
cd /var/www/html/mrp_erp/database
./scripts/migrate.sh fresh

# 3. Apply reference data
./scripts/seed.sh reference

# 4. Apply test data
./scripts/seed.sh test

# 5. Verify setup
./scripts/migrate.sh status
./scripts/seed.sh status
```

### 2. Daily Development Workflow

```bash
# Morning: Check for new migrations
./scripts/migrate.sh status

# Apply any pending migrations
./scripts/migrate.sh up --backup

# Refresh test data if needed
./scripts/seed.sh run 01_test_data

# Create backup before major changes
./scripts/backup.sh --full
```

### 3. Deploying to Staging

```bash
# 1. Backup current staging
./scripts/sync.sh pull staging --dry-run
./scripts/backup.sh --auto

# 2. Apply new migrations
./scripts/migrate.sh up --backup

# 3. Sync development to staging
./scripts/sync.sh clone dev staging --backup

# 4. Verify staging environment
./scripts/sync.sh status staging
./scripts/migrate.sh status
```

### 4. Production Deployment (CRITICAL)

```bash
# 1. MANDATORY: Create production backup
./scripts/sync.sh status prod
./scripts/backup.sh --full

# 2. Test migrations on staging first
./scripts/migrate.sh up --dry-run

# 3. Apply migrations to production
./scripts/migrate.sh up --backup

# 4. Verify production status
./scripts/migrate.sh status
./scripts/sync.sh status prod
```

### 5. Emergency Recovery

```bash
# If something goes wrong, you have options:

# Option 1: Rollback recent migrations
./scripts/migrate.sh down --steps=N

# Option 2: Restore from backup
./scripts/restore.sh --list
./scripts/restore.sh rollback_TIMESTAMP.sql.gz

# Option 3: Fresh restore from production backup
./scripts/restore.sh --backup-first mrp_erp_full_TIMESTAMP.sql.gz
```

## Safety Guidelines

### ⚠️ CRITICAL PRODUCTION RULES

1. **NEVER** run operations on production without testing on staging first
2. **ALWAYS** create backups before any production changes
3. **ALWAYS** use `--dry-run` first to preview changes
4. **NEVER** use `--force` on production without explicit approval
5. **ALWAYS** verify backups before depending on them

### General Safety Practices

1. **Test First**: Always test migrations and syncs on development/staging
2. **Backup Before**: Create backups before any destructive operations
3. **Verify Changes**: Use dry-run mode to preview all operations
4. **Track Changes**: Enable migration and seed tracking
5. **Monitor Space**: Ensure adequate disk space for backups

### Error Recovery

1. **Failed Migration**: Use `migrate.sh down` to rollback
2. **Corrupted Data**: Restore from most recent backup
3. **Sync Failure**: Check connection and retry with `--backup` option
4. **Missing Backup**: Create emergency backup before proceeding

## Configuration

### MySQL Configuration

Ensure your MySQL configuration supports the required operations:

```ini
# my.cnf additions for backup/restore operations
[mysqldump]
single-transaction = true
lock-tables = false
routines = true
triggers = true

[mysql]
max_allowed_packet = 512M
```

### Environment Variables

Create a `.env` file for configuration:

```bash
# Database configuration
DB_NAME="mrp_erp"
DB_USER="root"
DB_BACKUP_RETENTION_DAYS=30

# Environment endpoints
DEV_DB_HOST="localhost"
STAGING_DB_HOST="staging-server"
PROD_DB_HOST="prod-server"
```

### File Permissions

Ensure proper permissions for security:

```bash
chmod 755 scripts/*.sh
chmod 644 seeds/*.sql
chmod 644 migrations/*.sql
chmod 700 backups/  # Restrict backup access
```

## Monitoring and Maintenance

### Automated Monitoring

Set up monitoring for:
- Backup success/failure
- Available disk space
- Migration status across environments
- Database size growth

### Maintenance Tasks

**Daily**:
- Verify recent backups
- Check migration status
- Monitor disk space

**Weekly**:
- Clean old backups
- Compare staging/production schemas
- Review seed data consistency

**Monthly**:
- Full backup verification
- Performance review of large migrations
- Update documentation for new procedures

## Troubleshooting

### Common Issues

**"MySQL connection failed"**:
- Check credentials and server status
- Verify network connectivity
- Check MySQL service status

**"Migration failed"**:
- Review error output with `--verbose`
- Check for syntax errors in migration file
- Verify foreign key constraints

**"Backup verification failed"**:
- Check disk space
- Verify backup file permissions
- Re-run backup with `--verbose`

**"Sync operation timed out"**:
- Check network connectivity between servers
- Verify large table transfer capabilities
- Consider schema-only sync first

### Getting Help

1. Check script help: `./scripts/[script].sh --help`
2. Review error logs in `/tmp/` directory
3. Enable verbose mode for detailed output
4. Check system resources (disk space, memory)
5. Verify MySQL error logs

## Version History

- **v1.0** (2025-08-20): Initial comprehensive sync system
  - Created three-tier structure
  - Implemented all core scripts
  - Added safety features and documentation
  - Established naming conventions

## Contributing

When adding new features:
1. Follow the established safety patterns
2. Include `--dry-run` support
3. Add confirmation prompts for destructive operations
4. Update this documentation
5. Test thoroughly on development environment first

---

**Remember**: This system is designed to be safe and predictable. When in doubt, use dry-run mode first, create backups, and test on non-production environments.