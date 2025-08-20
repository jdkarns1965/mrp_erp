#!/bin/bash

# MRP/ERP Database Migration System
# Enhanced migration runner with rollback and dry-run capabilities

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
MIGRATION_DIR="/var/www/html/mrp_erp/database/migrations"
SCHEMA_DIR="/var/www/html/mrp_erp/database/schema"
BACKUP_DIR="/var/www/html/mrp_erp/database/backups"
MIGRATIONS_TABLE="schema_migrations"
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
    echo -e "${BLUE}║     MRP/ERP Database Migrations      ║${NC}"
    echo -e "${BLUE}║       Smart & Safe Migration        ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
    echo
}

# Display help
show_help() {
    echo "Usage: $0 [command] [options]"
    echo
    echo "Commands:"
    echo "  up [--steps=N]       Apply pending migrations (limit with --steps)"
    echo "  down [--steps=N]     Rollback last N migrations"
    echo "  status               Show migration status"
    echo "  fresh                Drop database and run all migrations"
    echo "  reset                Rollback all migrations"
    echo "  create <name>        Create new migration file"
    echo "  rollback             Interactive rollback to specific migration"
    echo
    echo "Options:"
    echo "  --dry-run            Show what would happen without making changes"
    echo "  --force              Skip confirmation prompts"
    echo "  --backup             Create backup before migration"
    echo "  --verbose            Show detailed output"
    echo "  --steps=N            Limit number of migrations to run"
    echo
    echo "Examples:"
    echo "  $0 status                    # Show current status"
    echo "  $0 up --dry-run              # Show pending migrations"
    echo "  $0 up --backup               # Run migrations with backup"
    echo "  $0 down --steps=2            # Rollback last 2 migrations"
    echo "  $0 create add_user_table     # Create new migration"
    echo "  $0 fresh --force             # Reset database and run all"
}

# Check MySQL connection and database
check_database() {
    local password=$1
    
    if ! mysql -u $DB_USER -p$password -e "SELECT 1;" 2>/dev/null >/dev/null; then
        echo -e "${RED}✗ MySQL connection failed${NC}"
        exit 1
    fi
    
    # Check if database exists
    if ! mysql -u $DB_USER -p$password -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "${YELLOW}→ Database '$DB_NAME' does not exist${NC}"
        read -p "Create database? (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            mysql -u $DB_USER -p$password -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
            echo -e "${GREEN}✓ Database created${NC}"
        else
            echo "Cannot proceed without database."
            exit 1
        fi
    fi
    
    echo -e "${GREEN}✓ Database connection verified${NC}"
}

# Initialize migrations table
init_migrations_table() {
    local password=$1
    
    mysql -u $DB_USER -p$password $DB_NAME <<EOF 2>/dev/null
CREATE TABLE IF NOT EXISTS $MIGRATIONS_TABLE (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rollback_sql LONGTEXT,
    INDEX idx_batch (batch),
    INDEX idx_executed (executed_at)
) ENGINE=InnoDB;
EOF
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Migrations table ready${NC}"
    else
        echo -e "${RED}✗ Failed to create migrations table${NC}"
        exit 1
    fi
}

# Get current batch number
get_next_batch() {
    local password=$1
    local batch=$(mysql -u $DB_USER -p$password $DB_NAME -sN -e "SELECT COALESCE(MAX(batch), 0) + 1 FROM $MIGRATIONS_TABLE;" 2>/dev/null)
    echo $batch
}

# Get pending migrations
get_pending_migrations() {
    local password=$1
    local steps=$2
    
    # Get all migration files
    local pending=()
    
    for file in "$MIGRATION_DIR"/*.sql; do
        if [ -f "$file" ]; then
            local filename=$(basename "$file")
            # Check if already applied
            local applied=$(mysql -u $DB_USER -p$password $DB_NAME -sN -e "SELECT COUNT(*) FROM $MIGRATIONS_TABLE WHERE migration='$filename';" 2>/dev/null)
            if [ "$applied" = "0" ]; then
                pending+=("$filename")
            fi
        fi
    done
    
    # Sort pending migrations
    IFS=$'\n' pending=($(sort <<<"${pending[*]}"))
    unset IFS
    
    # Limit if steps specified
    if [ -n "$steps" ] && [ "$steps" -gt 0 ]; then
        pending=("${pending[@]:0:$steps}")
    fi
    
    printf '%s\n' "${pending[@]}"
}

# Get applied migrations for rollback
get_applied_migrations() {
    local password=$1
    local steps=$2
    
    local query="SELECT migration FROM $MIGRATIONS_TABLE ORDER BY batch DESC, executed_at DESC"
    if [ -n "$steps" ] && [ "$steps" -gt 0 ]; then
        query="$query LIMIT $steps"
    fi
    
    mysql -u $DB_USER -p$password $DB_NAME -sN -e "$query;" 2>/dev/null
}

# Analyze migration file
analyze_migration() {
    local file=$1
    local temp_analysis="/tmp/migration_analysis_$$"
    
    head -50 "$file" > "$temp_analysis"
    
    local creates=$(grep -ci "CREATE TABLE\|CREATE VIEW\|CREATE INDEX" "$temp_analysis")
    local drops=$(grep -ci "DROP TABLE\|DROP VIEW\|DROP INDEX" "$temp_analysis")
    local alters=$(grep -ci "ALTER TABLE" "$temp_analysis")
    local inserts=$(grep -ci "INSERT INTO" "$temp_analysis")
    local deletes=$(grep -ci "DELETE FROM" "$temp_analysis")
    
    echo "  Actions: ${creates} creates, ${alters} alters, ${drops} drops, ${inserts} inserts, ${deletes} deletes"
    
    if [ $drops -gt 0 ] || [ $deletes -gt 0 ]; then
        echo -e "  ${RED}⚠️  DESTRUCTIVE operations detected${NC}"
    fi
    
    rm -f "$temp_analysis"
}

# Create backup before migration
create_migration_backup() {
    local password=$1
    local backup_file="$BACKUP_DIR/pre_migration_${TIMESTAMP}.sql.gz"
    
    echo -e "${YELLOW}→ Creating backup before migration...${NC}"
    
    if mysqldump -u $DB_USER -p$password \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --complete-insert \
        --databases $DB_NAME 2>/dev/null | gzip > "$backup_file"; then
        
        echo -e "${GREEN}✓ Backup created: $backup_file${NC}"
        return 0
    else
        echo -e "${RED}✗ Backup failed${NC}"
        return 1
    fi
}

# Generate rollback SQL for common operations
generate_rollback_sql() {
    local migration_file=$1
    local rollback_file="/tmp/rollback_${RANDOM}.sql"
    
    # Simple rollback generation (can be enhanced)
    grep -i "CREATE TABLE" "$migration_file" | sed 's/CREATE TABLE/DROP TABLE IF EXISTS/i' > "$rollback_file"
    grep -i "ADD COLUMN" "$migration_file" | sed 's/ADD COLUMN \([^ ]*\)/DROP COLUMN \1/i' >> "$rollback_file"
    
    if [ -s "$rollback_file" ]; then
        cat "$rollback_file"
    else
        echo "-- No automatic rollback available for this migration"
    fi
    
    rm -f "$rollback_file"
}

# Apply single migration
apply_migration() {
    local migration_file=$1
    local password=$2
    local batch=$3
    local dry_run=${4:-false}
    
    local filename=$(basename "$migration_file")
    
    echo -e "${YELLOW}→ Processing: $filename${NC}"
    
    if [ "$dry_run" = true ]; then
        echo "  [DRY RUN] Would apply migration"
        analyze_migration "$migration_file"
        return 0
    fi
    
    # Generate rollback SQL
    local rollback_sql=$(generate_rollback_sql "$migration_file")
    local escaped_rollback=$(printf '%s\n' "$rollback_sql" | sed 's/"/\\"/g' | sed ':a;N;$!ba;s/\n/\\n/g')
    
    # Apply the migration
    if mysql -u $DB_USER -p$password $DB_NAME < "$migration_file" 2>/dev/null; then
        # Record successful migration
        mysql -u $DB_USER -p$password $DB_NAME -e "
            INSERT INTO $MIGRATIONS_TABLE (migration, batch, rollback_sql) 
            VALUES ('$filename', $batch, '$escaped_rollback');" 2>/dev/null
        
        echo -e "${GREEN}✓ Applied: $filename${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed: $filename${NC}"
        return 1
    fi
}

# Rollback single migration
rollback_migration() {
    local migration_name=$1
    local password=$2
    local dry_run=${3:-false}
    
    echo -e "${YELLOW}→ Rolling back: $migration_name${NC}"
    
    if [ "$dry_run" = true ]; then
        echo "  [DRY RUN] Would rollback migration"
        return 0
    fi
    
    # Get rollback SQL
    local rollback_sql=$(mysql -u $DB_USER -p$password $DB_NAME -sN -e "
        SELECT rollback_sql FROM $MIGRATIONS_TABLE 
        WHERE migration = '$migration_name';" 2>/dev/null)
    
    if [ -n "$rollback_sql" ] && [ "$rollback_sql" != "NULL" ]; then
        # Apply rollback
        if echo "$rollback_sql" | mysql -u $DB_USER -p$password $DB_NAME 2>/dev/null; then
            # Remove from migrations table
            mysql -u $DB_USER -p$password $DB_NAME -e "
                DELETE FROM $MIGRATIONS_TABLE WHERE migration = '$migration_name';" 2>/dev/null
            echo -e "${GREEN}✓ Rolled back: $migration_name${NC}"
            return 0
        else
            echo -e "${RED}✗ Rollback failed: $migration_name${NC}"
            return 1
        fi
    else
        echo -e "${YELLOW}⚠️  No rollback SQL available for: $migration_name${NC}"
        echo "Manual rollback may be required."
        return 1
    fi
}

# Show migration status
show_status() {
    local password=$1
    
    echo -e "${BLUE}Migration Status${NC}"
    echo "================"
    
    # Applied migrations
    local applied_count=$(mysql -u $DB_USER -p$password $DB_NAME -sN -e "SELECT COUNT(*) FROM $MIGRATIONS_TABLE;" 2>/dev/null)
    echo "Applied migrations: $applied_count"
    
    if [ $applied_count -gt 0 ]; then
        echo
        echo "Last 5 applied migrations:"
        mysql -u $DB_USER -p$password $DB_NAME -e "
            SELECT migration, batch, executed_at 
            FROM $MIGRATIONS_TABLE 
            ORDER BY executed_at DESC 
            LIMIT 5;" 2>/dev/null | column -t
    fi
    
    # Pending migrations
    echo
    local pending=($(get_pending_migrations $password))
    echo "Pending migrations: ${#pending[@]}"
    
    if [ ${#pending[@]} -gt 0 ]; then
        echo
        echo "Next migrations to run:"
        for migration in "${pending[@]:0:5}"; do
            echo "  - $migration"
        done
        if [ ${#pending[@]} -gt 5 ]; then
            echo "  ... and $((${#pending[@]} - 5)) more"
        fi
    fi
}

# Run up migrations
run_up() {
    local password=$1
    local steps=$2
    local dry_run=${3:-false}
    local create_backup=${4:-false}
    
    local pending=($(get_pending_migrations $password $steps))
    
    if [ ${#pending[@]} -eq 0 ]; then
        echo -e "${GREEN}✓ Database is up to date${NC}"
        return 0
    fi
    
    echo -e "${BLUE}Pending migrations (${#pending[@]}):${NC}"
    for migration in "${pending[@]}"; do
        echo "  - $migration"
        if [ "$dry_run" = false ]; then
            analyze_migration "$MIGRATION_DIR/$migration"
        fi
    done
    echo
    
    if [ "$dry_run" = true ]; then
        echo -e "${BLUE}DRY RUN - No changes will be made${NC}"
        for migration in "${pending[@]}"; do
            apply_migration "$MIGRATION_DIR/$migration" $password 0 true
        done
        return 0
    fi
    
    # Create backup if requested
    if [ "$create_backup" = true ]; then
        if ! create_migration_backup $password; then
            echo -e "${RED}Backup failed. Continue anyway? (y/n)${NC}"
            read -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                echo "Migration cancelled."
                return 1
            fi
        fi
    fi
    
    # Confirm migration
    echo -e "${YELLOW}Ready to apply ${#pending[@]} migration(s)${NC}"
    read -p "Proceed? (y/n) " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Migration cancelled."
        return 0
    fi
    
    # Apply migrations
    local batch=$(get_next_batch $password)
    local success_count=0
    
    for migration in "${pending[@]}"; do
        if apply_migration "$MIGRATION_DIR/$migration" $password $batch false; then
            ((success_count++))
        else
            echo -e "${RED}Migration failed. Stopping.${NC}"
            break
        fi
    done
    
    echo
    echo -e "${GREEN}✓ Applied $success_count of ${#pending[@]} migrations${NC}"
}

# Run down migrations (rollback)
run_down() {
    local password=$1
    local steps=${2:-1}
    local dry_run=${3:-false}
    
    local applied=($(get_applied_migrations $password $steps))
    
    if [ ${#applied[@]} -eq 0 ]; then
        echo -e "${YELLOW}No migrations to rollback${NC}"
        return 0
    fi
    
    echo -e "${BLUE}Migrations to rollback (${#applied[@]}):${NC}"
    for migration in "${applied[@]}"; do
        echo "  - $migration"
    done
    echo
    
    if [ "$dry_run" = true ]; then
        echo -e "${BLUE}DRY RUN - No changes will be made${NC}"
        for migration in "${applied[@]}"; do
            rollback_migration "$migration" $password true
        done
        return 0
    fi
    
    echo -e "${YELLOW}⚠️  WARNING: Rollback operations can be destructive${NC}"
    read -p "Proceed with rollback? (y/n) " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Rollback cancelled."
        return 0
    fi
    
    # Rollback migrations
    local success_count=0
    for migration in "${applied[@]}"; do
        if rollback_migration "$migration" $password false; then
            ((success_count++))
        else
            echo -e "${RED}Rollback failed. Stopping.${NC}"
            break
        fi
    done
    
    echo
    echo -e "${GREEN}✓ Rolled back $success_count of ${#applied[@]} migrations${NC}"
}

# Create new migration file
create_migration() {
    local name=$1
    
    if [ -z "$name" ]; then
        echo -e "${RED}Migration name required${NC}"
        echo "Usage: $0 create <migration_name>"
        exit 1
    fi
    
    # Generate filename with timestamp
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local filename="${timestamp}_${name}.sql"
    local filepath="$MIGRATION_DIR/$filename"
    
    # Create migration template
    cat > "$filepath" <<EOF
-- Migration: $name
-- Created: $(date)
-- Description: [Add description of what this migration does]

-- UP (Apply changes)
-- Add your migration SQL here
-- Example:
-- CREATE TABLE example (
--     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(100) NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB;

-- Note: Rollback SQL will be generated automatically where possible
-- For complex migrations, consider creating a separate rollback migration
EOF
    
    echo -e "${GREEN}✓ Migration created: $filename${NC}"
    echo "Edit the file to add your migration SQL:"
    echo "  $filepath"
}

# Fresh migration (drop and recreate)
run_fresh() {
    local password=$1
    local force=${2:-false}
    
    echo -e "${RED}⚠️  DESTRUCTIVE OPERATION ⚠️${NC}"
    echo "This will drop the entire database and recreate it."
    echo
    
    if [ "$force" = false ]; then
        read -p "Are you absolutely sure? Type 'yes' to continue: " confirmation
        if [ "$confirmation" != "yes" ]; then
            echo "Operation cancelled."
            return 0
        fi
    fi
    
    # Drop database
    echo -e "${YELLOW}→ Dropping database...${NC}"
    mysql -u $DB_USER -p$password -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null
    
    # Recreate database
    echo -e "${YELLOW}→ Creating fresh database...${NC}"
    mysql -u $DB_USER -p$password -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    
    # Apply base schema
    if [ -f "$SCHEMA_DIR/01_base_schema.sql" ]; then
        echo -e "${YELLOW}→ Applying base schema...${NC}"
        mysql -u $DB_USER -p$password $DB_NAME < "$SCHEMA_DIR/01_base_schema.sql" 2>/dev/null
    fi
    
    # Initialize migrations table
    init_migrations_table $password
    
    # Run all migrations
    echo -e "${YELLOW}→ Running all migrations...${NC}"
    run_up $password "" false false
    
    echo -e "${GREEN}✓ Fresh migration completed${NC}"
}

# Main script logic
main() {
    show_banner
    
    local command="${1:-status}"
    local dry_run=false
    local force=false
    local create_backup=false
    local steps=""
    local verbose=false
    
    # Parse options
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                dry_run=true
                ;;
            --force)
                force=true
                ;;
            --backup)
                create_backup=true
                ;;
            --verbose)
                verbose=true
                ;;
            --steps=*)
                steps="${1#*=}"
                ;;
            *)
                if [ -z "$command" ] || [ "$command" = "status" ]; then
                    command="$1"
                fi
                ;;
        esac
        shift
    done
    
    # Get password (unless help)
    if [ "$command" != "help" ] && [ "$1" != "--help" ]; then
        read -sp "Enter MySQL password for $DB_USER: " PASSWORD
        echo
        echo
        
        check_database $PASSWORD
        init_migrations_table $PASSWORD
        echo
    fi
    
    # Execute command
    case $command in
        up)
            run_up $PASSWORD "$steps" $dry_run $create_backup
            ;;
        down)
            run_down $PASSWORD "$steps" $dry_run
            ;;
        status)
            show_status $PASSWORD
            ;;
        fresh)
            run_fresh $PASSWORD $force
            ;;
        reset)
            echo "Rollback all migrations..."
            local all_applied=($(mysql -u $DB_USER -p$PASSWORD $DB_NAME -sN -e "SELECT migration FROM $MIGRATIONS_TABLE ORDER BY batch DESC, executed_at DESC;" 2>/dev/null))
            run_down $PASSWORD "${#all_applied[@]}" $dry_run
            ;;
        create)
            create_migration "$2"
            ;;
        help|--help)
            show_help
            ;;
        *)
            echo -e "${RED}Unknown command: $command${NC}"
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"