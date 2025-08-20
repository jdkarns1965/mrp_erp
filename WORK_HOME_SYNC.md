# Work-Home Development Sync Guide

## Quick Commands

### At Work (Before Leaving)
```bash
cd /var/www/html/mrp_erp/database
./scripts/quick-backup.sh
git add -A
git commit -m "End of work day backup $(date +%Y-%m-%d)"
git push
```

### At Home (After Arriving)
```bash
cd /var/www/html/mrp_erp
git pull
cd database
./scripts/quick-restore.sh
```

## Detailed Workflow

### 1. End of Work Day (Work Environment)

#### Step 1: Create Backup
```bash
cd /var/www/html/mrp_erp/database
./scripts/quick-backup.sh
```
This creates:
- Timestamped backup: `work_to_home_YYYYMMDD_HHMMSS.sql.gz`
- Symlink: `latest_work_backup.sql.gz` (always points to newest)

#### Step 2: Commit to Git
```bash
git add database/backups/latest_work_backup.sql.gz
git add -A  # Include any code changes
git commit -m "Work backup $(date +%Y-%m-%d) - [brief description]"
git push origin main
```

#### Step 3: Note Any Special Context
Update the "ACTIVE DEVELOPMENT CONTEXT" section in CLAUDE.md if needed:
- Current task status
- Known issues
- Environment specifics

### 2. Start of Home Session

#### Step 1: Pull Latest Changes
```bash
cd /var/www/html/mrp_erp
git pull origin main
```

#### Step 2: Restore Database
```bash
cd database
./scripts/quick-restore.sh
# Or specify a specific backup:
# ./scripts/quick-restore.sh backups/work_to_home_20250820_152245.sql.gz
```

#### Step 3: Check Migration Status
```bash
./scripts/migrate.sh status
# Apply pending migrations if any:
./scripts/migrate.sh up --dry-run  # Preview first
./scripts/migrate.sh up            # Apply
```

#### Step 4: Verify Environment
```bash
# Test the application
php -S localhost:8000 -t public/
# Browse to http://localhost:8000
```

### 3. End of Home Session

Same as work procedure:
```bash
cd database
./scripts/quick-backup.sh
git add -A
git commit -m "Home backup $(date +%Y-%m-%d)"
git push
```

### 4. Back at Work

```bash
git pull
cd database
./scripts/quick-restore.sh
```

## Alternative Methods

### Method 1: Direct SCP Transfer
```bash
# At work
cd database
./scripts/quick-backup.sh
scp backups/latest_work_backup.sql.gz home-pc:/path/to/mrp_erp/database/backups/

# At home
cd database
./scripts/quick-restore.sh
```

### Method 2: USB Drive
```bash
# At work
cd database
./scripts/quick-backup.sh
cp backups/latest_work_backup.sql.gz /media/usb/

# At home
cp /media/usb/latest_work_backup.sql.gz database/backups/
cd database
./scripts/quick-restore.sh
```

### Method 3: Cloud Storage
```bash
# At work
cd database
./scripts/quick-backup.sh
# Upload to Dropbox/Google Drive/OneDrive

# At home
# Download from cloud
cd database
./scripts/quick-restore.sh backups/[downloaded-file]
```

## Backup Management

### List All Backups
```bash
ls -lh database/backups/*.gz
```

### Clean Old Backups (Keep Last 30 Days)
```bash
cd database
./scripts/backup.sh --cleanup
```

### Verify Backup Integrity
```bash
cd database
./scripts/backup.sh --verify backups/latest_work_backup.sql.gz
```

## Troubleshooting

### Database Connection Issues
```bash
# Check MySQL is running
sudo service mysql status

# Test connection
mysql -u root -ppassgas1989 -e "SHOW DATABASES;"
```

### Permission Issues
```bash
# Fix script permissions
chmod +x database/scripts/*.sh

# Fix backup directory permissions
chmod 755 database/backups
```

### Git Conflicts
```bash
# If backup conflicts occur, keep local version
git checkout --ours database/backups/latest_work_backup.sql.gz
git add database/backups/latest_work_backup.sql.gz
git commit -m "Resolved backup conflict - kept local"
```

### Large Backup Files
Git has a 100MB file limit. If backups grow too large:
1. Use Git LFS for backup files
2. Store backups outside Git
3. Use incremental backups

## Environment Differences

### Work Environment
```yaml
Path: /var/www/html/mrp_erp
Database: mrp_erp
MySQL User: root
MySQL Pass: passgas1989
PHP Version: [CHECK]
```

### Home Environment
```yaml
Path: /var/www/html/mrp_erp
Database: mrp_erp
MySQL User: root
MySQL Pass: passgas1989
PHP Version: 7.4+
```

## Best Practices

1. **Always backup before leaving** either environment
2. **Commit frequently** with meaningful messages
3. **Test after restore** to ensure everything works
4. **Document environment-specific issues** in CLAUDE.md
5. **Keep backups for at least 7 days** before cleanup
6. **Use --dry-run** for migrations before applying

## Quick Reference Card

```bash
# LEAVING WORK/HOME
cd database && ./scripts/quick-backup.sh
git add -A && git commit -m "Backup $(date +%Y-%m-%d)" && git push

# ARRIVING HOME/WORK
git pull && cd database && ./scripts/quick-restore.sh

# CHECK STATUS
./scripts/migrate.sh status

# EMERGENCY RESTORE
./scripts/restore.sh --list  # See all backups
./scripts/restore.sh [specific-backup]
```

---
*Last Updated: 2025-01-20*
*Scripts Version: 1.0*