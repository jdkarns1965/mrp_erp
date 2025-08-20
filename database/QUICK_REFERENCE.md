# Database Sync System - Quick Reference

## 🚀 Quick Start Commands

### New Environment Setup
```bash
# 1. Create fresh database
./scripts/migrate.sh fresh

# 2. Add reference data
./scripts/seed.sh reference

# 3. Add test data
./scripts/seed.sh test
```

### Daily Development
```bash
# Check status
./scripts/migrate.sh status

# Apply migrations
./scripts/migrate.sh up --backup

# Create backup
./scripts/backup.sh --auto
```

## 💾 Backup Operations

| Command | Purpose |
|---------|---------|
| `./scripts/backup.sh` | Interactive full backup |
| `./scripts/backup.sh --auto` | Automated backup (cron-friendly) |
| `./scripts/backup.sh --schema-only` | Structure only |
| `./scripts/backup.sh --list` | List all backups |
| `./scripts/backup.sh --cleanup` | Remove old backups |

## 🔄 Restore Operations

| Command | Purpose |
|---------|---------|
| `./scripts/restore.sh` | Interactive restore |
| `./scripts/restore.sh --dry-run file.sql.gz` | Preview restore |
| `./scripts/restore.sh --backup-first file.sql.gz` | Backup then restore |
| `./scripts/restore.sh --list` | List available backups |

## 📈 Migration Management

| Command | Purpose |
|---------|---------|
| `./scripts/migrate.sh status` | Show migration status |
| `./scripts/migrate.sh up --dry-run` | Preview migrations |
| `./scripts/migrate.sh up` | Apply migrations |
| `./scripts/migrate.sh down --steps=2` | Rollback 2 migrations |
| `./scripts/migrate.sh fresh` | 🚨 Rebuild database |

## 🌍 Environment Sync

| Command | Purpose |
|---------|---------|
| `./scripts/sync.sh list-envs` | List environments |
| `./scripts/sync.sh status prod` | Check environment |
| `./scripts/sync.sh pull prod --dry-run` | Preview pull |
| `./scripts/sync.sh clone dev staging` | Copy dev to staging |
| `./scripts/sync.sh compare dev prod` | Compare schemas |

## 🌱 Seed Data Management

| Command | Purpose |
|---------|---------|
| `./scripts/seed.sh list` | List seed files |
| `./scripts/seed.sh reference` | Apply reference data |
| `./scripts/seed.sh test` | Apply test data |
| `./scripts/seed.sh refresh` | Clear and reapply all |
| `./scripts/seed.sh create name` | Create new seed |

## 🚨 Emergency Procedures

### Quick Recovery
```bash
# Rollback last migration
./scripts/migrate.sh down

# Restore from backup
./scripts/restore.sh --list
./scripts/restore.sh backup_file.sql.gz
```

### Production Emergency
```bash
# 1. Create emergency backup
./scripts/backup.sh --force

# 2. Restore from known good backup
./scripts/restore.sh --backup-first good_backup.sql.gz
```

## ⚡ One-Liners

```bash
# Status check
./scripts/migrate.sh status && ./scripts/seed.sh status

# Full backup with verification
./scripts/backup.sh --auto && ./scripts/backup.sh --verify backups/latest.sql.gz

# Safe migration
./scripts/migrate.sh up --dry-run && ./scripts/migrate.sh up --backup

# Environment health check
for env in dev staging prod; do ./scripts/sync.sh status $env; done
```

## 🛡️ Safety Checklist

### Before Production Changes
- [ ] Test on staging first
- [ ] Create production backup
- [ ] Use `--dry-run` to preview
- [ ] Have rollback plan ready
- [ ] Verify backup integrity

### Daily Checks
- [ ] Migration status: `./scripts/migrate.sh status`
- [ ] Recent backups: `./scripts/backup.sh --list`
- [ ] Disk space: `df -h`
- [ ] Error logs: Check `/tmp/` for errors

## 📞 Quick Help

| Need Help With | Command |
|----------------|---------|
| Script options | `./scripts/[script].sh --help` |
| Current status | `./scripts/migrate.sh status` |
| Available backups | `./scripts/backup.sh --list` |
| Environment info | `./scripts/sync.sh list-envs` |

## 🔐 Permission Commands

```bash
# Fix script permissions
chmod +x scripts/*.sh

# Secure backup directory
chmod 700 backups/

# Standard file permissions
chmod 644 seeds/*.sql migrations/*.sql
```

---
**💡 Tip**: Always use `--dry-run` first when unsure about an operation!