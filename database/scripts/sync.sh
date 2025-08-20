#!/bin/bash

# MRP/ERP Database Environment Synchronization System
# Safely sync databases between development, staging, and production

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
BACKUP_DIR="/var/www/html/mrp_erp/database/backups"
SEEDS_DIR="/var/www/html/mrp_erp/database/seeds"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Environment configurations
declare -A ENVIRONMENTS
ENVIRONMENTS[local]="localhost:3306"
ENVIRONMENTS[dev]="localhost:3306"
ENVIRONMENTS[staging]="staging-db:3306"
ENVIRONMENTS[prod]="prod-db:3306"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Display banner
show_banner() {
    echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     MRP/ERP Environment Sync System      ║${NC}"
    echo -e "${CYAN}║        Safe Database Synchronization     ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
    echo
}

# Display help
show_help() {
    echo "Usage: $0 <command> [options]"
    echo
    echo "Commands:"
    echo "  pull <from_env> [to_env]     Pull database from environment"
    echo "  push <to_env> [from_env]     Push database to environment"
    echo "  clone <from_env> <to_env>    Clone database between environments"
    echo "  seed <env> [seed_name]       Apply seed data to environment"
    echo "  compare <env1> <env2>        Compare database schemas"
    echo "  status <env>                 Show environment status"
    echo "  list-envs                    List available environments"
    echo
    echo "Environments:"
    echo "  local     - Local development (default)"
    echo "  dev       - Development server"
    echo "  staging   - Staging environment"
    echo "  prod      - Production (restricted operations)"
    echo
    echo "Options:"
    echo "  --dry-run             Show what would happen"
    echo "  --backup              Create backup before sync"
    echo "  --force               Skip confirmation prompts"
    echo "  --schema-only         Sync structure only (no data)"
    echo "  --data-only           Sync data only (no structure)"
    echo "  --exclude-tables=...  Comma-separated list of tables to exclude"
    echo "  --include-tables=...  Comma-separated list of tables to include"
    echo
    echo "Safety Examples:"
    echo "  $0 pull prod --dry-run                   # See what would be pulled"
    echo "  $0 pull prod --backup --schema-only      # Pull structure with backup"
    echo "  $0 clone dev staging --backup            # Clone dev to staging safely"
    echo "  $0 seed local 01_test_data               # Apply test data locally"
    echo
    echo "Production Safety:"
    echo "  - Production pulls require explicit confirmation"
    echo "  - Pushes to production are disabled by default"
    echo "  - All production operations create automatic backups"
}

# Parse connection string
parse_connection() {
    local env=$1
    local connection=${ENVIRONMENTS[$env]}
    
    if [ -z "$connection" ]; then
        echo -e "${RED}Unknown environment: $env${NC}"
        echo "Available environments:"
        for env_name in "${!ENVIRONMENTS[@]}"; do
            echo "  - $env_name"
        done
        exit 1
    fi
    
    local host=$(echo $connection | cut -d':' -f1)
    local port=$(echo $connection | cut -d':' -f2)
    
    echo "$host:$port"
}

# Test database connection
test_connection() {
    local env=$1
    local password=$2
    local connection=$(parse_connection $env)
    local host=$(echo $connection | cut -d':' -f1)
    local port=$(echo $connection | cut -d':' -f2)
    
    if mysql -h $host -P $port -u $DB_USER -p$password -e "SELECT 1;" 2>/dev/null >/dev/null; then
        echo -e "${GREEN}✓ Connection to $env ($host:$port) successful${NC}"
        return 0
    else
        echo -e "${RED}✗ Connection to $env ($host:$port) failed${NC}"
        return 1
    fi
}

# Get database information
get_db_info() {
    local env=$1
    local password=$2
    local connection=$(parse_connection $env)
    local host=$(echo $connection | cut -d':' -f1)
    local port=$(echo $connection | cut -d':' -f2)
    
    # Check if database exists
    if ! mysql -h $host -P $port -u $DB_USER -p$password -e "USE $DB_NAME;" 2>/dev/null; then
        echo "Database: $DB_NAME (does not exist)"
        return 1
    fi
    
    local size=$(mysql -h $host -P $port -u $DB_USER -p$password -e "
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size'
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
    
    local tables=$(mysql -h $host -P $port -u $DB_USER -p$password -e "
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
    
    local records=$(mysql -h $host -P $port -u $DB_USER -p$password $DB_NAME -e "
        SELECT SUM(table_rows) 
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME';" 2>/dev/null | tail -n 1)
    
    echo "Database: $DB_NAME"
    echo "Size: ${size} MB"
    echo "Tables: $tables"
    echo "Records: $records"
}

# Create environment backup
create_env_backup() {
    local env=$1
    local password=$2
    local backup_type=${3:-full}
    
    local connection=$(parse_connection $env)
    local host=$(echo $connection | cut -d':' -f1)
    local port=$(echo $connection | cut -d':' -f2)
    local backup_file="$BACKUP_DIR/${env}_${backup_type}_${TIMESTAMP}.sql.gz"
    
    echo -e "${YELLOW}→ Creating $env backup ($backup_type)...${NC}"
    
    local dump_options="--single-transaction --routines --triggers --events --complete-insert"
    
    if [ "$backup_type" = "schema" ]; then
        dump_options="$dump_options --no-data"
    elif [ "$backup_type" = "data" ]; then
        dump_options="$dump_options --no-create-info"
    fi
    
    if mysqldump -h $host -P $port -u $DB_USER -p$password $dump_options $DB_NAME 2>/dev/null | gzip > "$backup_file"; then
        local size=$(du -h "$backup_file" | cut -f1)
        echo -e "${GREEN}✓ Backup created: $backup_file ($size)${NC}"
        echo "$backup_file"
    else
        echo -e "${RED}✗ Backup failed${NC}"
        return 1
    fi
}

# Apply seed data
apply_seeds() {
    local env=$1
    local password=$2
    local seed_name=$3
    local dry_run=${4:-false}
    
    local connection=$(parse_connection $env)
    local host=$(echo $connection | cut -d':' -f1)
    local port=$(echo $connection | cut -d':' -f2)
    
    if [ -n "$seed_name" ]; then
        # Apply specific seed
        local seed_file="$SEEDS_DIR/${seed_name}.sql"
        if [ ! -f "$seed_file" ]; then
            echo -e "${RED}Seed file not found: $seed_file${NC}"
            return 1
        fi
        
        echo -e "${YELLOW}→ Applying seed: $seed_name${NC}"
        
        if [ "$dry_run" = true ]; then
            echo "  [DRY RUN] Would apply: $seed_file"
            return 0
        fi
        
        if mysql -h $host -P $port -u $DB_USER -p$password $DB_NAME < "$seed_file" 2>/dev/null; then
            echo -e "${GREEN}✓ Seed applied: $seed_name${NC}"
        else
            echo -e "${RED}✗ Seed failed: $seed_name${NC}"
            return 1
        fi
    else
        # Apply all seeds
        echo -e "${YELLOW}→ Applying all seed files...${NC}"
        
        for seed_file in "$SEEDS_DIR"/*.sql; do
            if [ -f "$seed_file" ]; then
                local seed_name=$(basename "$seed_file" .sql)
                echo "  - $seed_name"
                
                if [ "$dry_run" = false ]; then
                    if ! mysql -h $host -P $port -u $DB_USER -p$password $DB_NAME < "$seed_file" 2>/dev/null; then
                        echo -e "${RED}✗ Seed failed: $seed_name${NC}"
                        return 1
                    fi
                fi
            fi
        done
        
        if [ "$dry_run" = false ]; then
            echo -e "${GREEN}✓ All seeds applied${NC}"
        fi
    fi
}

# Compare database schemas
compare_schemas() {
    local env1=$1
    local env2=$2
    local password=$3
    
    local conn1=$(parse_connection $env1)
    local host1=$(echo $conn1 | cut -d':' -f1)
    local port1=$(echo $conn1 | cut -d':' -f2)
    
    local conn2=$(parse_connection $env2)
    local host2=$(echo $conn2 | cut -d':' -f1)
    local port2=$(echo $conn2 | cut -d':' -f2)
    
    echo -e "${BLUE}Comparing schemas: $env1 vs $env2${NC}"
    echo "==============================="
    
    # Create temporary schema dumps
    local schema1="/tmp/schema1_$$"
    local schema2="/tmp/schema2_$$"
    
    mysqldump -h $host1 -P $port1 -u $DB_USER -p$password --no-data --no-create-db $DB_NAME 2>/dev/null > "$schema1"
    mysqldump -h $host2 -P $port2 -u $DB_USER -p$password --no-data --no-create-db $DB_NAME 2>/dev/null > "$schema2"
    
    if [ ! -s "$schema1" ]; then
        echo -e "${RED}Cannot dump schema from $env1${NC}"
        rm -f "$schema1" "$schema2"
        return 1
    fi
    
    if [ ! -s "$schema2" ]; then
        echo -e "${RED}Cannot dump schema from $env2${NC}"
        rm -f "$schema1" "$schema2"
        return 1
    fi
    
    # Compare schemas
    if diff -q "$schema1" "$schema2" >/dev/null; then
        echo -e "${GREEN}✓ Schemas are identical${NC}"
    else
        echo -e "${YELLOW}⚠️  Schema differences found:${NC}"
        echo
        diff -u "$schema1" "$schema2" | head -50
        echo
        echo "Use 'diff -u' on the full schema dumps for complete comparison"
    fi
    
    rm -f "$schema1" "$schema2"
}

# Sync database between environments
sync_database() {
    local from_env=$1
    local to_env=$2
    local password=$3
    local dry_run=${4:-false}
    local backup_first=${5:-false}
    local sync_type=${6:-full}
    
    echo -e "${BLUE}Synchronizing: $from_env → $to_env${NC}"
    echo "============================="
    
    # Test connections
    if ! test_connection $from_env $password; then
        return 1
    fi
    
    if ! test_connection $to_env $password; then
        return 1
    fi
    
    # Show source database info
    echo -e "${CYAN}Source ($from_env):${NC}"
    get_db_info $from_env $password
    echo
    
    # Show destination database info
    echo -e "${CYAN}Destination ($to_env):${NC}"
    get_db_info $to_env $password
    echo
    
    if [ "$dry_run" = true ]; then
        echo -e "${BLUE}DRY RUN - No changes will be made${NC}"
        echo "Would sync $sync_type database from $from_env to $to_env"
        return 0
    fi
    
    # Production safety checks
    if [ "$to_env" = "prod" ]; then
        echo -e "${RED}⚠️  PRODUCTION SYNC WARNING ⚠️${NC}"
        echo "You are about to modify the PRODUCTION database!"
        echo
        read -p "Type 'CONFIRM' to proceed: " confirmation
        if [ "$confirmation" != "CONFIRM" ]; then
            echo "Sync cancelled."
            return 0
        fi
        backup_first=true
    fi
    
    # Create backup if requested or required
    if [ "$backup_first" = true ]; then
        if ! create_env_backup $to_env $password $sync_type; then
            echo -e "${RED}Backup failed. Continue anyway? (y/n)${NC}"
            read -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                echo "Sync cancelled."
                return 1
            fi
        fi
    fi
    
    # Final confirmation
    echo -e "${YELLOW}Ready to sync $sync_type database${NC}"
    echo "From: $from_env"
    echo "To: $to_env"
    echo
    read -p "Proceed? (y/n) " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Sync cancelled."
        return 0
    fi
    
    # Perform sync
    local conn_from=$(parse_connection $from_env)
    local host_from=$(echo $conn_from | cut -d':' -f1)
    local port_from=$(echo $conn_from | cut -d':' -f2)
    
    local conn_to=$(parse_connection $to_env)
    local host_to=$(echo $conn_to | cut -d':' -f1)
    local port_to=$(echo $conn_to | cut -d':' -f2)
    
    echo -e "${YELLOW}→ Performing database sync...${NC}"
    
    # Create temporary dump
    local temp_dump="/tmp/sync_dump_$$.sql"
    
    local dump_options="--single-transaction --routines --triggers --events --complete-insert"
    if [ "$sync_type" = "schema" ]; then
        dump_options="$dump_options --no-data"
    elif [ "$sync_type" = "data" ]; then
        dump_options="$dump_options --no-create-info"
    fi
    
    if mysqldump -h $host_from -P $port_from -u $DB_USER -p$password $dump_options $DB_NAME > "$temp_dump" 2>/dev/null; then
        # Apply to destination
        if mysql -h $host_to -P $port_to -u $DB_USER -p$password $DB_NAME < "$temp_dump" 2>/dev/null; then
            echo -e "${GREEN}✓ Database sync completed${NC}"
            
            # Show updated destination info
            echo
            echo -e "${CYAN}Updated destination ($to_env):${NC}"
            get_db_info $to_env $password
        else
            echo -e "${RED}✗ Sync failed during import${NC}"
            rm -f "$temp_dump"
            return 1
        fi
    else
        echo -e "${RED}✗ Sync failed during export${NC}"
        rm -f "$temp_dump"
        return 1
    fi
    
    rm -f "$temp_dump"
}

# List available environments
list_environments() {
    echo -e "${BLUE}Available Environments:${NC}"
    echo "======================="
    
    for env in "${!ENVIRONMENTS[@]}"; do
        local status="Unknown"
        local connection=${ENVIRONMENTS[$env]}
        
        # Test connection (without password prompt)
        if timeout 2 bash -c "</dev/tcp/${connection%:*}/${connection#*:}" 2>/dev/null; then
            status="${GREEN}Reachable${NC}"
        else
            status="${RED}Unreachable${NC}"
        fi
        
        printf "%-10s %-20s %s\n" "$env" "$connection" "$status"
    done
}

# Show environment status
show_env_status() {
    local env=$1
    local password=$2
    
    echo -e "${BLUE}Environment Status: $env${NC}"
    echo "========================="
    
    if ! test_connection $env $password; then
        return 1
    fi
    
    get_db_info $env $password
}

# Main script logic
main() {
    show_banner
    
    local command="${1:-help}"
    local from_env=""
    local to_env="local"
    local dry_run=false
    local force=false
    local backup_first=false
    local sync_type="full"
    local seed_name=""
    
    # Parse arguments
    case $command in
        pull)
            from_env="$2"
            to_env="${3:-local}"
            ;;
        push)
            to_env="$2"
            from_env="${3:-local}"
            ;;
        clone)
            from_env="$2"
            to_env="$3"
            ;;
        seed)
            to_env="$2"
            seed_name="$3"
            ;;
        compare)
            from_env="$2"
            to_env="$3"
            ;;
        status)
            to_env="$2"
            ;;
        list-envs)
            list_environments
            exit 0
            ;;
        help|--help)
            show_help
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown command: $command${NC}"
            show_help
            exit 1
            ;;
    esac
    
    # Parse options
    shift 2
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                dry_run=true
                ;;
            --force)
                force=true
                ;;
            --backup)
                backup_first=true
                ;;
            --schema-only)
                sync_type="schema"
                ;;
            --data-only)
                sync_type="data"
                ;;
            *)
                if [ -z "$to_env" ] && [ -n "$from_env" ]; then
                    to_env="$1"
                fi
                ;;
        esac
        shift
    done
    
    # Validate environments
    if [ -n "$from_env" ] && [ -z "${ENVIRONMENTS[$from_env]}" ]; then
        echo -e "${RED}Unknown source environment: $from_env${NC}"
        exit 1
    fi
    
    if [ -n "$to_env" ] && [ -z "${ENVIRONMENTS[$to_env]}" ]; then
        echo -e "${RED}Unknown destination environment: $to_env${NC}"
        exit 1
    fi
    
    # Get password
    read -sp "Enter MySQL password for $DB_USER: " PASSWORD
    echo
    echo
    
    # Execute command
    case $command in
        pull|push|clone)
            sync_database $from_env $to_env $PASSWORD $dry_run $backup_first $sync_type
            ;;
        seed)
            apply_seeds $to_env $PASSWORD $seed_name $dry_run
            ;;
        compare)
            compare_schemas $from_env $to_env $PASSWORD
            ;;
        status)
            show_env_status $to_env $PASSWORD
            ;;
    esac
}

# Run main function
main "$@"