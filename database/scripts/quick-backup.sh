#!/bin/bash

# Quick Backup Script for Work-to-Home Transition
# Usage: ./quick-backup.sh

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
DB_PASS="passgas1989"
BACKUP_DIR="/var/www/html/mrp_erp/database/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Quick Backup - Work to Home Sync   ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
echo

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Create timestamped backup
BACKUP_FILE="${BACKUP_DIR}/work_to_home_${TIMESTAMP}.sql"
COMPRESSED_FILE="${BACKUP_FILE}.gz"

echo -e "${YELLOW}Creating backup...${NC}"
mysqldump -u $DB_USER -p$DB_PASS --single-transaction --routines --triggers --add-drop-table $DB_NAME > "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    # Compress the backup
    gzip "$BACKUP_FILE"
    
    # Get file size
    SIZE=$(du -h "$COMPRESSED_FILE" | cut -f1)
    
    echo -e "${GREEN}✓ Backup created successfully${NC}"
    echo -e "  File: ${COMPRESSED_FILE}"
    echo -e "  Size: ${SIZE}"
    
    # Create a latest symlink for easy access
    ln -sf "work_to_home_${TIMESTAMP}.sql.gz" "${BACKUP_DIR}/latest_work_backup.sql.gz"
    echo -e "${GREEN}✓ Created 'latest_work_backup.sql.gz' symlink${NC}"
    
    # Show sync instructions
    echo
    echo -e "${BLUE}To sync to home environment:${NC}"
    echo "1. Copy this file to home environment:"
    echo "   scp ${COMPRESSED_FILE} home:/path/to/mrp_erp/database/backups/"
    echo
    echo "2. Or use git to commit and push:"
    echo "   git add database/backups/latest_work_backup.sql.gz"
    echo "   git commit -m 'Work backup $(date +%Y-%m-%d)'"
    echo "   git push"
    echo
    echo "3. At home, restore with:"
    echo "   cd database && ./scripts/restore.sh backups/latest_work_backup.sql.gz"
else
    echo -e "${RED}✗ Backup failed${NC}"
    exit 1
fi