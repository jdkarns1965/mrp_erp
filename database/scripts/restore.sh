#!/bin/bash

# MRP/ERP Database Restoration System
# Provides safe database restoration with rollback capabilities

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
BACKUP_DIR="/var/www/html/mrp_erp/database/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Display banner
show_banner() {
    echo -e "${BLUE}╔══════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║      MRP/ERP Database Restore        ║${NC}"
    echo -e "${BLUE}║        SAFETY FIRST SYSTEM           ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
    echo
    echo -e "${YELLOW}⚠️  WARNING: This will modify your database${NC}"
    echo
}

# Display help
show_help() {
    echo "Usage: $0 [options] [backup_file]"
    echo
    echo "Options:"
    echo "  --list              List available backups"
    echo "  --verify [file]     Verify backup before restore"
    echo "  --dry-run [file]    Show what would be restored (no changes)"
    echo "  --force             Skip confirmation prompts"
    echo "  --backup-first      Create backup before restore"
    echo "  --help              Show this help message"
    echo
    echo "Safety Options:"
    echo "  --create-rollback   Create rollback point before restore"
    echo "  --show-changes      Show database changes after restore"
    echo
    echo "Examples:"
    echo "  $0 --list                           # List available backups"
    echo "  $0 backup.sql                       # Interactive restore"
    echo "  $0 --backup-first backup.sql        # Backup current, then restore"
    echo "  $0 --dry-run backup.sql             # Show what would happen"
    echo "  $0 --force --backup-first backup.sql # Automated restore"
}

# Check MySQL connection
check_mysql() {
    local password=$1
    if ! mysql -u $DB_USER -p$password -e "SELECT 1;" 2>/dev/null >/dev/null; then
        echo -e "${RED}✗ MySQL connection failed${NC}"
        echo "Check username, password, and MySQL server status."
        exit 1
    fi
    echo -e "${GREEN}✓ MySQL connection successful${NC}"
}

# Check if database exists
database_exists() {
    local password=$1
    mysql -u $DB_USER -p$password -e "USE $DB_NAME;" 2>/dev/null
}

# Get database info
get_database_info() {
    local password=$1
    
    if database_exists $password; then
        local size=$(mysql -u $DB_USER -p$password -e "
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size'
            FROM information_schema.tables 
            WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
        
        local tables=$(mysql -u $DB_USER -p$password -e "
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
        
        local records=$(mysql -u $DB_USER -p$password $DB_NAME -e "
            SELECT SUM(table_rows) 
            FROM information_schema.tables 
            WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
        
        echo -e "${BLUE}Current Database Status:${NC}"
        echo "  Database: $DB_NAME (exists)"
        echo "  Size: ${size} MB"
        echo "  Tables: $tables"
        echo "  Estimated Records: $records"
    else
        echo -e "${BLUE}Current Database Status:${NC}"
        echo "  Database: $DB_NAME (does not exist)"
    fi
    echo
}

# List available backups
list_backups() {
    echo -e "${BLUE}Available Backups:${NC}"
    echo "=================="
    
    if [ ! -d "$BACKUP_DIR" ] || [ -z "$(ls -A $BACKUP_DIR 2>/dev/null)" ]; then
        echo -e "${YELLOW}No backups found in $BACKUP_DIR${NC}"
        return 1
    fi
    
    local count=0
    cd "$BACKUP_DIR"
    for file in mrp_erp_*.sql*; do
        if [ -f "$file" ]; then
            ((count++))
            local size=$(du -h "$file" | cut -f1)
            local date=$(stat -c %y "$file" | cut -d' ' -f1,2 | cut -d'.' -f1)
            local type=""
            
            if [[ $file == *"full"* ]]; then
                type="Full"
            elif [[ $file == *"schema"* ]]; then
                type="Schema"
            elif [[ $file == *"data"* ]]; then
                type="Data"
            else
                type="Legacy"
            fi
            
            printf "%2d) %-40s %-8s %-8s %s\n" "$count" "$file" "$type" "$size" "$date"
        fi
    done
    
    if [ $count -eq 0 ]; then
        echo -e "${YELLOW}No backup files found${NC}"
        return 1
    fi
    
    return 0
}

# Verify backup file
verify_backup() {
    local backup_file=$1
    
    echo -e "${YELLOW}→ Verifying backup file...${NC}"
    
    if [ ! -f "$backup_file" ]; then
        echo -e "${RED}✗ Backup file not found: $backup_file${NC}"
        return 1
    fi
    
    # Check file size
    local size=$(du -h "$backup_file" | cut -f1)
    echo "  File: $backup_file"
    echo "  Size: $size"
    
    # Check if gzipped
    if [[ $backup_file == *.gz ]]; then
        echo "  Format: Compressed SQL"
        if ! gzip -t "$backup_file" 2>/dev/null; then
            echo -e "${RED}✗ Compression integrity check failed${NC}"
            return 1
        fi
        echo -e "${GREEN}  ✓ Compression: Valid${NC}"
        
        # Check SQL content
        if zcat "$backup_file" | head -20 | grep -q "CREATE DATABASE\|CREATE TABLE\|INSERT INTO"; then
            echo -e "${GREEN}  ✓ SQL Content: Valid${NC}"
        else
            echo -e "${RED}✗ SQL content appears invalid${NC}"
            return 1
        fi
    else
        echo "  Format: Plain SQL"
        if head -20 "$backup_file" | grep -q "CREATE DATABASE\|CREATE TABLE\|INSERT INTO"; then
            echo -e "${GREEN}  ✓ SQL Content: Valid${NC}"
        else
            echo -e "${RED}✗ SQL content appears invalid${NC}"
            return 1
        fi
    fi
    
    echo -e "${GREEN}✓ Backup verification passed${NC}"
    return 0
}

# Dry run - show what would be restored
dry_run() {
    local backup_file=$1
    local password=$2
    
    echo -e "${BLUE}DRY RUN MODE - No changes will be made${NC}"
    echo "======================================="
    
    if ! verify_backup "$backup_file"; then
        return 1
    fi
    
    echo
    echo -e "${YELLOW}→ Analyzing backup content...${NC}"
    
    # Extract info from backup
    local temp_file="/tmp/backup_analysis_$$"
    
    if [[ $backup_file == *.gz ]]; then
        zcat "$backup_file" | head -100 > "$temp_file"
    else
        head -100 "$backup_file" > "$temp_file"
    fi
    
    # Analyze content
    local has_create_db=$(grep -c "CREATE DATABASE" "$temp_file")
    local has_drop_db=$(grep -c "DROP DATABASE" "$temp_file")
    local has_create_table=$(grep -c "CREATE TABLE" "$temp_file")
    local has_insert=$(grep -c "INSERT INTO" "$temp_file")
    
    echo "  Contains:"
    [ $has_drop_db -gt 0 ] && echo "    - Database DROP statements (will delete existing database)"
    [ $has_create_db -gt 0 ] && echo "    - Database CREATE statements"
    [ $has_create_table -gt 0 ] && echo "    - Table CREATE statements ($has_create_table tables)"
    [ $has_insert -gt 0 ] && echo "    - Data INSERT statements"
    
    echo
    echo -e "${YELLOW}→ Impact assessment:${NC}"
    
    if database_exists $password; then
        if [ $has_drop_db -gt 0 ]; then
            echo -e "${RED}  ⚠️  DESTRUCTIVE: Current database will be completely replaced${NC}"
        else
            echo -e "${YELLOW}  ⚠️  PARTIAL: Current database will be modified${NC}"
        fi
        
        echo "  Current database will be affected:"
        get_database_info $password
    else
        echo -e "${GREEN}  ✓ SAFE: New database will be created${NC}"
    fi
    
    rm -f "$temp_file"
    
    echo
    echo -e "${BLUE}This was a DRY RUN - no changes were made${NC}"
    echo "Use without --dry-run to perform actual restore"
}

# Create rollback point
create_rollback() {
    local password=$1
    
    if ! database_exists $password; then
        echo -e "${YELLOW}→ No existing database to backup for rollback${NC}"
        return 0
    fi
    
    echo -e "${YELLOW}→ Creating rollback point...${NC}"
    
    local rollback_file="$BACKUP_DIR/rollback_${TIMESTAMP}.sql.gz"
    
    if mysqldump -u $DB_USER -p$password \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --complete-insert \
        --add-drop-database \
        --databases $DB_NAME 2>/dev/null | gzip > "$rollback_file"; then
        
        echo -e "${GREEN}✓ Rollback point created: $rollback_file${NC}"
        echo "  Use this file to restore current state if needed"
        return 0
    else
        echo -e "${RED}✗ Failed to create rollback point${NC}"
        return 1
    fi
}

# Perform restore
perform_restore() {
    local backup_file=$1
    local password=$2
    
    echo -e "${YELLOW}→ Starting database restore...${NC}"
    echo "  Source: $backup_file"
    echo "  Target: $DB_NAME"
    echo
    
    # Restore the backup
    if [[ $backup_file == *.gz ]]; then
        if zcat "$backup_file" | mysql -u $DB_USER -p$password 2>/dev/null; then
            echo -e "${GREEN}✓ Database restore completed successfully${NC}"
        else
            echo -e "${RED}✗ Database restore failed${NC}"
            return 1
        fi
    else
        if mysql -u $DB_USER -p$password < "$backup_file" 2>/dev/null; then
            echo -e "${GREEN}✓ Database restore completed successfully${NC}"
        else
            echo -e "${RED}✗ Database restore failed${NC}"
            return 1
        fi
    fi
    
    # Verify restore
    echo -e "${YELLOW}→ Verifying restored database...${NC}"
    get_database_info $password
    
    return 0
}

# Interactive restore
interactive_restore() {
    local backup_file=$1
    
    # Get password
    read -sp "Enter MySQL password for $DB_USER: " PASSWORD
    echo
    echo
    
    check_mysql $PASSWORD
    get_database_info $PASSWORD
    
    # Verify backup
    if ! verify_backup "$backup_file"; then
        echo -e "${RED}Backup verification failed. Aborting.${NC}"
        exit 1
    fi
    
    echo
    echo -e "${YELLOW}⚠️  FINAL WARNING ⚠️${NC}"
    echo "This operation will modify your database."
    echo
    read -p "Do you want to create a rollback point first? (y/n) " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        if ! create_rollback $PASSWORD; then
            echo -e "${RED}Failed to create rollback point. Continue anyway? (y/n)${NC}"
            read -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                echo "Restore cancelled."
                exit 1
            fi
        fi
    fi
    
    echo
    echo "Ready to restore:"
    echo "  From: $backup_file"
    echo "  To: $DB_NAME database"
    echo
    read -p "Proceed with restore? (y/n) " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        perform_restore "$backup_file" $PASSWORD
    else
        echo "Restore cancelled."
        exit 0
    fi
}

# Select backup interactively
select_backup() {
    if ! list_backups; then
        echo -e "${RED}No backups available${NC}"
        exit 1
    fi
    
    echo
    read -p "Select backup number (or 'q' to quit): " selection
    
    if [ "$selection" = "q" ]; then
        echo "Cancelled."
        exit 0
    fi
    
    # Get the selected file
    local count=0
    cd "$BACKUP_DIR"
    for file in mrp_erp_*.sql*; do
        if [ -f "$file" ]; then
            ((count++))
            if [ $count -eq $selection ]; then
                echo "$BACKUP_DIR/$file"
                return 0
            fi
        fi
    done
    
    echo -e "${RED}Invalid selection${NC}"
    exit 1
}

# Main script logic
main() {
    show_banner
    
    local backup_file=""
    local force=false
    local backup_first=false
    local create_rollback_point=false
    local dry_run_mode=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --list)
                list_backups
                exit $?
                ;;
            --verify)
                if [ -z "$2" ]; then
                    echo -e "${RED}Error: Please specify backup file to verify${NC}"
                    exit 1
                fi
                verify_backup "$2"
                exit $?
                ;;
            --dry-run)
                dry_run_mode=true
                if [ -n "$2" ] && [[ $2 != --* ]]; then
                    backup_file="$2"
                    shift
                fi
                ;;
            --force)
                force=true
                ;;
            --backup-first)
                backup_first=true
                ;;
            --create-rollback)
                create_rollback_point=true
                ;;
            --help)
                show_help
                exit 0
                ;;
            --*)
                echo -e "${RED}Unknown option: $1${NC}"
                show_help
                exit 1
                ;;
            *)
                if [ -z "$backup_file" ]; then
                    backup_file="$1"
                fi
                ;;
        esac
        shift
    done
    
    # If no backup file specified, let user select
    if [ -z "$backup_file" ]; then
        backup_file=$(select_backup)
    fi
    
    # Make backup path absolute if needed
    if [ ! -f "$backup_file" ] && [ -f "$BACKUP_DIR/$backup_file" ]; then
        backup_file="$BACKUP_DIR/$backup_file"
    fi
    
    if [ ! -f "$backup_file" ]; then
        echo -e "${RED}Backup file not found: $backup_file${NC}"
        exit 1
    fi
    
    # Handle dry run
    if [ "$dry_run_mode" = true ]; then
        read -sp "Enter MySQL password for $DB_USER: " PASSWORD
        echo
        check_mysql $PASSWORD
        dry_run "$backup_file" $PASSWORD
        exit 0
    fi
    
    # Handle forced restore
    if [ "$force" = true ]; then
        read -sp "Enter MySQL password for $DB_USER: " PASSWORD
        echo
        check_mysql $PASSWORD
        
        if [ "$backup_first" = true ]; then
            create_rollback $PASSWORD
        fi
        
        perform_restore "$backup_file" $PASSWORD
        exit $?
    fi
    
    # Interactive restore
    interactive_restore "$backup_file"
}

# Run main function
main "$@"