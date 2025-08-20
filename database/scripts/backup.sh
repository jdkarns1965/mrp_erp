#!/bin/bash

# MRP/ERP Database Backup System
# Provides safe, automated backups with versioning and retention

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
BACKUP_DIR="/var/www/html/mrp_erp/database/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=30

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Display banner
show_banner() {
    echo -e "${BLUE}╔══════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║       MRP/ERP Database Backup        ║${NC}"
    echo -e "${BLUE}║          Safety First System         ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
    echo
}

# Display help
show_help() {
    echo "Usage: $0 [options]"
    echo
    echo "Options:"
    echo "  --full           Create full backup (schema + data)"
    echo "  --schema-only    Create schema-only backup"
    echo "  --data-only      Create data-only backup"
    echo "  --auto           Automated backup (full + schema)"
    echo "  --cleanup        Remove backups older than $RETENTION_DAYS days"
    echo "  --list           List all available backups"
    echo "  --verify [file]  Verify backup integrity"
    echo "  --help           Show this help message"
    echo
    echo "Examples:"
    echo "  $0 --full                    # Interactive full backup"
    echo "  $0 --auto                    # Automated daily backup"
    echo "  $0 --cleanup                 # Clean old backups"
    echo "  $0 --verify backup.sql       # Verify backup file"
}

# Check if database exists
check_database() {
    if ! mysql -u $DB_USER -p$1 -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "${RED}✗ Database '$DB_NAME' not found${NC}"
        echo "Create database first or check connection settings."
        exit 1
    fi
    echo -e "${GREEN}✓ Database '$DB_NAME' accessible${NC}"
}

# Get database size
get_db_size() {
    local password=$1
    mysql -u $DB_USER -p$password -e "
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1
}

# Get table count
get_table_count() {
    local password=$1
    mysql -u $DB_USER -p$password -e "
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1
}

# Create full backup
create_full_backup() {
    local password=$1
    local filename="$BACKUP_DIR/mrp_erp_full_${TIMESTAMP}.sql"
    
    echo -e "${YELLOW}→ Creating full backup...${NC}"
    echo "  Target: $filename"
    
    if mysqldump -u $DB_USER -p$password \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --complete-insert \
        --add-drop-database \
        --databases $DB_NAME > "$filename" 2>/dev/null; then
        
        # Compress the backup
        gzip "$filename"
        filename="${filename}.gz"
        
        local size=$(du -h "$filename" | cut -f1)
        echo -e "${GREEN}✓ Full backup created: $filename ($size)${NC}"
        echo "$filename"
    else
        echo -e "${RED}✗ Full backup failed${NC}"
        return 1
    fi
}

# Create schema-only backup
create_schema_backup() {
    local password=$1
    local filename="$BACKUP_DIR/mrp_erp_schema_${TIMESTAMP}.sql"
    
    echo -e "${YELLOW}→ Creating schema-only backup...${NC}"
    echo "  Target: $filename"
    
    if mysqldump -u $DB_USER -p$password \
        --no-data \
        --routines \
        --triggers \
        --events \
        --add-drop-database \
        --databases $DB_NAME > "$filename" 2>/dev/null; then
        
        # Compress the backup
        gzip "$filename"
        filename="${filename}.gz"
        
        local size=$(du -h "$filename" | cut -f1)
        echo -e "${GREEN}✓ Schema backup created: $filename ($size)${NC}"
        echo "$filename"
    else
        echo -e "${RED}✗ Schema backup failed${NC}"
        return 1
    fi
}

# Create data-only backup
create_data_backup() {
    local password=$1
    local filename="$BACKUP_DIR/mrp_erp_data_${TIMESTAMP}.sql"
    
    echo -e "${YELLOW}→ Creating data-only backup...${NC}"
    echo "  Target: $filename"
    
    if mysqldump -u $DB_USER -p$password \
        --no-create-info \
        --complete-insert \
        --single-transaction \
        $DB_NAME > "$filename" 2>/dev/null; then
        
        # Compress the backup
        gzip "$filename"
        filename="${filename}.gz"
        
        local size=$(du -h "$filename" | cut -f1)
        echo -e "${GREEN}✓ Data backup created: $filename ($size)${NC}"
        echo "$filename"
    else
        echo -e "${RED}✗ Data backup failed${NC}"
        return 1
    fi
}

# List backups
list_backups() {
    echo -e "${BLUE}Available Backups:${NC}"
    echo "=================="
    
    if [ ! -d "$BACKUP_DIR" ] || [ -z "$(ls -A $BACKUP_DIR 2>/dev/null)" ]; then
        echo -e "${YELLOW}No backups found${NC}"
        return
    fi
    
    cd "$BACKUP_DIR"
    for file in mrp_erp_*.sql*; do
        if [ -f "$file" ]; then
            local size=$(du -h "$file" | cut -f1)
            local date=$(stat -c %y "$file" | cut -d' ' -f1)
            local type=""
            
            if [[ $file == *"full"* ]]; then
                type="Full"
            elif [[ $file == *"schema"* ]]; then
                type="Schema"
            elif [[ $file == *"data"* ]]; then
                type="Data"
            else
                type="Unknown"
            fi
            
            printf "%-40s %-8s %-8s %s\n" "$file" "$type" "$size" "$date"
        fi
    done
}

# Verify backup integrity
verify_backup() {
    local backup_file=$1
    
    if [ ! -f "$backup_file" ]; then
        echo -e "${RED}✗ Backup file not found: $backup_file${NC}"
        return 1
    fi
    
    echo -e "${YELLOW}→ Verifying backup: $backup_file${NC}"
    
    # Check if file is gzipped
    if [[ $backup_file == *.gz ]]; then
        if gzip -t "$backup_file" 2>/dev/null; then
            echo -e "${GREEN}✓ Compression integrity: OK${NC}"
        else
            echo -e "${RED}✗ Compression integrity: FAILED${NC}"
            return 1
        fi
        
        # Check SQL syntax
        if zcat "$backup_file" | head -50 | grep -q "CREATE DATABASE\|CREATE TABLE"; then
            echo -e "${GREEN}✓ SQL format: OK${NC}"
        else
            echo -e "${RED}✗ SQL format: INVALID${NC}"
            return 1
        fi
    else
        # Check SQL syntax for uncompressed file
        if head -50 "$backup_file" | grep -q "CREATE DATABASE\|CREATE TABLE"; then
            echo -e "${GREEN}✓ SQL format: OK${NC}"
        else
            echo -e "${RED}✗ SQL format: INVALID${NC}"
            return 1
        fi
    fi
    
    echo -e "${GREEN}✓ Backup verification completed${NC}"
}

# Cleanup old backups
cleanup_backups() {
    echo -e "${YELLOW}→ Cleaning up backups older than $RETENTION_DAYS days...${NC}"
    
    if [ ! -d "$BACKUP_DIR" ]; then
        echo -e "${YELLOW}No backup directory found${NC}"
        return
    fi
    
    local count=0
    find "$BACKUP_DIR" -name "mrp_erp_*.sql*" -type f -mtime +$RETENTION_DAYS -print0 | while IFS= read -r -d '' file; do
        echo "  Removing: $(basename "$file")"
        rm "$file"
        ((count++))
    done
    
    if [ $count -eq 0 ]; then
        echo -e "${GREEN}✓ No old backups to clean${NC}"
    else
        echo -e "${GREEN}✓ Cleaned $count old backup(s)${NC}"
    fi
}

# Interactive backup
interactive_backup() {
    read -sp "Enter MySQL password for $DB_USER: " PASSWORD
    echo
    echo
    
    check_database $PASSWORD
    
    local db_size=$(get_db_size $PASSWORD)
    local table_count=$(get_table_count $PASSWORD)
    
    echo -e "${BLUE}Database Information:${NC}"
    echo "  Size: ${db_size} MB"
    echo "  Tables: $table_count"
    echo
    
    echo "Backup Options:"
    echo "1) Full backup (schema + data)"
    echo "2) Schema only"
    echo "3) Data only"
    echo "4) Both full + schema"
    echo
    read -p "Select option (1-4): " choice
    
    case $choice in
        1)
            create_full_backup $PASSWORD
            ;;
        2)
            create_schema_backup $PASSWORD
            ;;
        3)
            create_data_backup $PASSWORD
            ;;
        4)
            create_full_backup $PASSWORD
            create_schema_backup $PASSWORD
            ;;
        *)
            echo -e "${RED}Invalid choice${NC}"
            exit 1
            ;;
    esac
}

# Automated backup (for cron jobs)
automated_backup() {
    # Use .my.cnf or prompt if not available
    if [ -f ~/.my.cnf ]; then
        PASSWORD=""
    else
        read -sp "Enter MySQL password for $DB_USER: " PASSWORD
        echo
    fi
    
    check_database $PASSWORD
    
    echo -e "${BLUE}Starting automated backup...${NC}"
    
    # Create both full and schema backups
    create_full_backup $PASSWORD
    create_schema_backup $PASSWORD
    
    # Cleanup old backups
    cleanup_backups
    
    echo -e "${GREEN}✓ Automated backup completed${NC}"
}

# Main script logic
main() {
    show_banner
    
    # Create backup directory if it doesn't exist
    mkdir -p "$BACKUP_DIR"
    
    case "${1:-}" in
        --full)
            interactive_backup
            ;;
        --schema-only)
            read -sp "Enter MySQL password for $DB_USER: " PASSWORD
            echo
            check_database $PASSWORD
            create_schema_backup $PASSWORD
            ;;
        --data-only)
            read -sp "Enter MySQL password for $DB_USER: " PASSWORD
            echo
            check_database $PASSWORD
            create_data_backup $PASSWORD
            ;;
        --auto)
            automated_backup
            ;;
        --cleanup)
            cleanup_backups
            ;;
        --list)
            list_backups
            ;;
        --verify)
            if [ -z "$2" ]; then
                echo -e "${RED}Error: Please specify backup file to verify${NC}"
                echo "Usage: $0 --verify <backup_file>"
                exit 1
            fi
            verify_backup "$2"
            ;;
        --help)
            show_help
            ;;
        "")
            interactive_backup
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"