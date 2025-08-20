#!/bin/bash

# Quick Restore Script for Home Environment
# Usage: ./quick-restore.sh [backup_file]

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
DB_PASS="passgas1989"
BACKUP_DIR="/var/www/html/mrp_erp/database/backups"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Quick Restore - Home Environment   ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
echo

# Determine backup file
if [ -z "$1" ]; then
    # No argument, use latest work backup
    BACKUP_FILE="${BACKUP_DIR}/latest_work_backup.sql.gz"
    if [ ! -f "$BACKUP_FILE" ]; then
        echo -e "${RED}✗ No latest work backup found${NC}"
        echo "Run 'git pull' first or specify a backup file"
        exit 1
    fi
else
    BACKUP_FILE="$1"
    if [ ! -f "$BACKUP_FILE" ]; then
        # Try in backup directory
        BACKUP_FILE="${BACKUP_DIR}/$1"
        if [ ! -f "$BACKUP_FILE" ]; then
            echo -e "${RED}✗ Backup file not found: $1${NC}"
            exit 1
        fi
    fi
fi

echo -e "${YELLOW}Using backup: $(basename $BACKUP_FILE)${NC}"

# Check if it's compressed
if [[ "$BACKUP_FILE" == *.gz ]]; then
    echo -e "${YELLOW}Decompressing backup...${NC}"
    TEMP_FILE="/tmp/restore_$(date +%s).sql"
    gunzip -c "$BACKUP_FILE" > "$TEMP_FILE"
    RESTORE_FILE="$TEMP_FILE"
else
    RESTORE_FILE="$BACKUP_FILE"
fi

# Verify database exists
mysql -u $DB_USER -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null

# Restore the backup
echo -e "${YELLOW}Restoring database...${NC}"
mysql -u $DB_USER -p$DB_PASS $DB_NAME < "$RESTORE_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database restored successfully${NC}"
    
    # Get stats
    TABLE_COUNT=$(mysql -u $DB_USER -p$DB_PASS -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null)
    DB_SIZE=$(mysql -u $DB_USER -p$DB_PASS -N -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null)
    
    echo -e "  Tables: ${TABLE_COUNT}"
    echo -e "  Size: ${DB_SIZE} MB"
    
    # Check for migration status
    echo
    echo -e "${BLUE}Checking migration status...${NC}"
    MIGRATION_COUNT=$(mysql -u $DB_USER -p$DB_PASS -N -e "SELECT COUNT(*) FROM $DB_NAME.schema_migrations;" 2>/dev/null || echo "0")
    if [ "$MIGRATION_COUNT" != "0" ]; then
        echo -e "${GREEN}✓ $MIGRATION_COUNT migrations applied${NC}"
        echo
        echo -e "${YELLOW}Run './scripts/migrate.sh status' to check for pending migrations${NC}"
    fi
else
    echo -e "${RED}✗ Restore failed${NC}"
    exit 1
fi

# Cleanup temp file if used
if [ ! -z "$TEMP_FILE" ] && [ -f "$TEMP_FILE" ]; then
    rm "$TEMP_FILE"
fi

echo
echo -e "${GREEN}Home environment is ready!${NC}"