#!/bin/bash

# MRP/ERP Database Seed Management System
# Manages reference data and test data seeding

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
SEEDS_DIR="/var/www/html/mrp_erp/database/seeds"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Display banner
show_banner() {
    echo -e "${CYAN}╔══════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     MRP/ERP Seed Management          ║${NC}"
    echo -e "${CYAN}║   Reference & Test Data System       ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════╝${NC}"
    echo
}

# Display help
show_help() {
    echo "Usage: $0 [command] [options]"
    echo
    echo "Commands:"
    echo "  list                     List available seed files"
    echo "  run [seed_name]          Run specific seed or all seeds"
    echo "  status                   Show which seeds have been applied"
    echo "  create <name>            Create new seed file template"
    echo "  refresh                  Clear and reapply all seeds"
    echo "  reference                Apply reference data only"
    echo "  test                     Apply test data only"
    echo
    echo "Seed Types:"
    echo "  00_reference_*           System reference data (UOM, categories, etc.)"
    echo "  01_test_*               Basic test data"
    echo "  02_production_*         Production/manufacturing test data"
    echo "  03_sample_*             Sample orders and scenarios"
    echo "  99_cleanup_*            Cleanup and maintenance scripts"
    echo
    echo "Options:"
    echo "  --dry-run               Show what would be applied"
    echo "  --force                 Skip confirmation prompts"
    echo "  --clear-first           Clear existing data before seeding"
    echo "  --track                 Track seed execution in database"
    echo "  --verbose               Show detailed output"
    echo
    echo "Examples:"
    echo "  $0 list                              # List all available seeds"
    echo "  $0 run 01_test_data                  # Run specific seed"
    echo "  $0 run --dry-run                     # Show what would be applied"
    echo "  $0 reference                         # Apply reference data only"
    echo "  $0 refresh --force                   # Clear and reapply all seeds"
}

# Check database connection
check_database() {
    local password=$1
    
    if ! mysql -u $DB_USER -p$password -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "${RED}✗ Cannot connect to database '$DB_NAME'${NC}"
        echo "Make sure the database exists and credentials are correct."
        exit 1
    fi
    echo -e "${GREEN}✓ Database connection verified${NC}"
}

# Initialize seed tracking table
init_seed_tracking() {
    local password=$1
    
    mysql -u $DB_USER -p$password $DB_NAME <<EOF 2>/dev/null
CREATE TABLE IF NOT EXISTS seed_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seed_name VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time_ms INT UNSIGNED,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT,
    INDEX idx_seed_name (seed_name),
    INDEX idx_executed_at (executed_at)
) ENGINE=InnoDB;
EOF
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Seed tracking table ready${NC}"
    else
        echo -e "${YELLOW}⚠️  Seed tracking unavailable${NC}"
        return 1
    fi
}

# List available seed files
list_seeds() {
    echo -e "${BLUE}Available Seed Files:${NC}"
    echo "===================="
    
    if [ ! -d "$SEEDS_DIR" ] || [ -z "$(ls -A $SEEDS_DIR 2>/dev/null)" ]; then
        echo -e "${YELLOW}No seed files found in $SEEDS_DIR${NC}"
        return 1
    fi
    
    local count=0
    cd "$SEEDS_DIR"
    for file in *.sql; do
        if [ -f "$file" ]; then
            ((count++))
            local size=$(du -h "$file" | cut -f1)
            local date=$(stat -c %y "$file" | cut -d' ' -f1)
            local type=""
            
            if [[ $file == 00_reference_* ]]; then
                type="${CYAN}Reference${NC}"
            elif [[ $file == 01_test_* ]]; then
                type="${GREEN}Test Data${NC}"
            elif [[ $file == 02_production_* ]]; then
                type="${BLUE}Production${NC}"
            elif [[ $file == 03_sample_* ]]; then
                type="${YELLOW}Samples${NC}"
            elif [[ $file == 99_cleanup_* ]]; then
                type="${RED}Cleanup${NC}"
            else
                type="Other"
            fi
            
            printf "%2d) %-35s %-12s %-6s %s\n" "$count" "$file" "$type" "$size" "$date"
        fi
    done
    
    if [ $count -eq 0 ]; then
        echo -e "${YELLOW}No seed files found${NC}"
        return 1
    fi
    
    echo
    echo "Total: $count seed files"
}

# Show seed execution status
show_seed_status() {
    local password=$1
    
    echo -e "${BLUE}Seed Execution Status:${NC}"
    echo "======================"
    
    # Check if tracking table exists
    local table_exists=$(mysql -u $DB_USER -p$password $DB_NAME -sN -e "
        SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME' AND table_name = 'seed_history';" 2>/dev/null)
    
    if [ "$table_exists" = "0" ]; then
        echo -e "${YELLOW}Seed tracking not enabled${NC}"
        echo "Use --track option to enable seed execution tracking"
        return 0
    fi
    
    # Show recent executions
    echo "Recent seed executions:"
    mysql -u $DB_USER -p$password $DB_NAME -e "
        SELECT 
            seed_name as 'Seed File',
            status as 'Status',
            executed_at as 'Executed At',
            CONCAT(execution_time_ms, 'ms') as 'Duration'
        FROM seed_history 
        ORDER BY executed_at DESC 
        LIMIT 10;" 2>/dev/null | column -t
    
    echo
    
    # Show summary by status
    echo "Summary:"
    mysql -u $DB_USER -p$password $DB_NAME -e "
        SELECT 
            status as 'Status',
            COUNT(*) as 'Count',
            MIN(executed_at) as 'First Run',
            MAX(executed_at) as 'Last Run'
        FROM seed_history 
        GROUP BY status;" 2>/dev/null | column -t
}

# Analyze seed file
analyze_seed() {
    local seed_file=$1
    
    if [ ! -f "$seed_file" ]; then
        echo -e "${RED}Seed file not found: $seed_file${NC}"
        return 1
    fi
    
    echo "Analyzing: $(basename "$seed_file")"
    
    local inserts=$(grep -ci "INSERT INTO" "$seed_file")
    local updates=$(grep -ci "UPDATE " "$seed_file")
    local deletes=$(grep -ci "DELETE FROM" "$seed_file")
    local creates=$(grep -ci "CREATE " "$seed_file")
    local drops=$(grep -ci "DROP " "$seed_file")
    
    echo "  Operations: ${inserts} inserts, ${updates} updates, ${deletes} deletes"
    echo "  Schema changes: ${creates} creates, ${drops} drops"
    
    if [ $deletes -gt 0 ] || [ $drops -gt 0 ]; then
        echo -e "  ${RED}⚠️  DESTRUCTIVE operations detected${NC}"
    fi
    
    # Extract table names from INSERT statements
    local tables=$(grep -i "INSERT INTO" "$seed_file" | sed 's/.*INSERT INTO \([a-zA-Z_]*\).*/\1/' | sort | uniq | tr '\n' ' ')
    if [ -n "$tables" ]; then
        echo "  Affects tables: $tables"
    fi
}

# Execute single seed file
execute_seed() {
    local seed_file=$1
    local password=$2
    local track=${3:-false}
    local dry_run=${4:-false}
    local verbose=${5:-false}
    
    local seed_name=$(basename "$seed_file")
    
    echo -e "${YELLOW}→ Processing: $seed_name${NC}"
    
    if [ "$dry_run" = true ]; then
        echo "  [DRY RUN] Would execute seed"
        analyze_seed "$seed_file"
        return 0
    fi
    
    if [ "$verbose" = true ]; then
        analyze_seed "$seed_file"
    fi
    
    # Execute the seed
    local start_time=$(date +%s%3N)
    local error_output=""
    
    if error_output=$(mysql -u $DB_USER -p$password $DB_NAME < "$seed_file" 2>&1); then
        local end_time=$(date +%s%3N)
        local duration=$((end_time - start_time))
        
        echo -e "${GREEN}✓ Executed: $seed_name (${duration}ms)${NC}"
        
        # Track execution if requested
        if [ "$track" = true ]; then
            mysql -u $DB_USER -p$password $DB_NAME -e "
                INSERT INTO seed_history (seed_name, execution_time_ms, status) 
                VALUES ('$seed_name', $duration, 'success');" 2>/dev/null
        fi
        
        return 0
    else
        echo -e "${RED}✗ Failed: $seed_name${NC}"
        if [ "$verbose" = true ]; then
            echo "Error: $error_output"
        fi
        
        # Track failure if requested
        if [ "$track" = true ]; then
            local escaped_error=$(printf '%s\n' "$error_output" | sed 's/"/\\"/g' | sed ':a;N;$!ba;s/\n/\\n/g')
            mysql -u $DB_USER -p$password $DB_NAME -e "
                INSERT INTO seed_history (seed_name, status, error_message) 
                VALUES ('$seed_name', 'failed', '$escaped_error');" 2>/dev/null
        fi
        
        return 1
    fi
}

# Clear existing data
clear_data() {
    local password=$1
    local force=${2:-false}
    
    echo -e "${YELLOW}→ Clearing existing seed data...${NC}"
    
    if [ "$force" = false ]; then
        echo -e "${RED}⚠️  This will delete existing data!${NC}"
        read -p "Continue? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Clear cancelled."
            return 1
        fi
    fi
    
    # Clear data in dependency order (to avoid foreign key constraints)
    local tables=(
        "customer_order_items"
        "customer_orders"
        "production_order_materials"
        "production_order_operations"
        "production_orders"
        "bom_details"
        "bom_headers"
        "inventory_transactions"
        "inventory_lots"
        "inventory"
        "products"
        "materials"
        "customers"
        "suppliers"
    )
    
    for table in "${tables[@]}"; do
        # Check if table exists
        local exists=$(mysql -u $DB_USER -p$password $DB_NAME -sN -e "
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = '$DB_NAME' AND table_name = '$table';" 2>/dev/null)
        
        if [ "$exists" = "1" ]; then
            mysql -u $DB_USER -p$password $DB_NAME -e "DELETE FROM $table;" 2>/dev/null
            echo "  Cleared: $table"
        fi
    done
    
    echo -e "${GREEN}✓ Data cleared${NC}"
}

# Run seeds by pattern
run_seeds_by_pattern() {
    local pattern=$1
    local password=$2
    local track=${3:-false}
    local dry_run=${4:-false}
    local verbose=${5:-false}
    local clear_first=${6:-false}
    
    if [ "$clear_first" = true ] && [ "$dry_run" = false ]; then
        clear_data $password false
        echo
    fi
    
    local seeds=()
    cd "$SEEDS_DIR"
    for file in $pattern; do
        if [ -f "$file" ]; then
            seeds+=("$file")
        fi
    done
    
    # Sort seeds to ensure proper execution order
    IFS=$'\n' seeds=($(sort <<<"${seeds[*]}"))
    unset IFS
    
    if [ ${#seeds[@]} -eq 0 ]; then
        echo -e "${YELLOW}No seeds found matching pattern: $pattern${NC}"
        return 1
    fi
    
    echo -e "${BLUE}Found ${#seeds[@]} seed(s) matching pattern: $pattern${NC}"
    for seed in "${seeds[@]}"; do
        echo "  - $seed"
    done
    echo
    
    # Execute seeds
    local success_count=0
    for seed in "${seeds[@]}"; do
        if execute_seed "$SEEDS_DIR/$seed" $password $track $dry_run $verbose; then
            ((success_count++))
        else
            echo -e "${RED}Seed execution failed. Continue? (y/n)${NC}"
            read -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                break
            fi
        fi
    done
    
    echo
    echo -e "${GREEN}✓ Executed $success_count of ${#seeds[@]} seeds${NC}"
}

# Create new seed file
create_seed() {
    local name=$1
    
    if [ -z "$name" ]; then
        echo -e "${RED}Seed name required${NC}"
        echo "Usage: $0 create <seed_name>"
        exit 1
    fi
    
    # Determine prefix based on type
    echo "Select seed type:"
    echo "1) Reference data (00_reference_)"
    echo "2) Test data (01_test_)"
    echo "3) Production data (02_production_)"
    echo "4) Sample data (03_sample_)"
    echo "5) Cleanup script (99_cleanup_)"
    echo
    read -p "Select type (1-5): " type_choice
    
    local prefix=""
    case $type_choice in
        1) prefix="00_reference_" ;;
        2) prefix="01_test_" ;;
        3) prefix="02_production_" ;;
        4) prefix="03_sample_" ;;
        5) prefix="99_cleanup_" ;;
        *) echo -e "${RED}Invalid choice${NC}"; exit 1 ;;
    esac
    
    local filename="${prefix}${name}.sql"
    local filepath="$SEEDS_DIR/$filename"
    
    # Create seed template
    cat > "$filepath" <<EOF
-- Seed: $name
-- Type: $(echo $prefix | sed 's/_/ /g' | sed 's/[0-9]//g')
-- Created: $(date)
-- Description: [Add description of what this seed does]

-- IMPORTANT: This seed should be idempotent (safe to run multiple times)
-- Use INSERT IGNORE or INSERT ... ON DUPLICATE KEY UPDATE where appropriate

-- Example reference data:
-- INSERT IGNORE INTO units_of_measure (code, description, type) VALUES
-- ('KG', 'Kilogram', 'weight'),
-- ('LB', 'Pound', 'weight'),
-- ('EA', 'Each', 'count');

-- Example test data:
-- INSERT IGNORE INTO materials (material_code, name, category_id, uom_id) VALUES
-- ('TEST-001', 'Test Material 1', 1, 1),
-- ('TEST-002', 'Test Material 2', 2, 2);

-- Add your seed data here
-- Remember to use IGNORE or ON DUPLICATE KEY UPDATE for idempotency
EOF
    
    echo -e "${GREEN}✓ Seed created: $filename${NC}"
    echo "Edit the file to add your seed data:"
    echo "  $filepath"
}

# Main script logic
main() {
    show_banner
    
    local command="${1:-list}"
    local dry_run=false
    local force=false
    local track=false
    local verbose=false
    local clear_first=false
    local seed_name=""
    
    # Parse options
    for arg in "$@"; do
        case $arg in
            --dry-run)
                dry_run=true
                ;;
            --force)
                force=true
                ;;
            --track)
                track=true
                ;;
            --verbose)
                verbose=true
                ;;
            --clear-first)
                clear_first=true
                ;;
        esac
    done
    
    # Handle commands that don't need database connection
    case $command in
        list)
            list_seeds
            exit 0
            ;;
        create)
            create_seed "$2"
            exit 0
            ;;
        help|--help)
            show_help
            exit 0
            ;;
    esac
    
    # Get database password for other commands
    read -sp "Enter MySQL password for $DB_USER: " PASSWORD
    echo
    echo
    
    check_database $PASSWORD
    
    if [ "$track" = true ]; then
        init_seed_tracking $PASSWORD
    fi
    
    echo
    
    # Execute command
    case $command in
        run)
            if [ -n "$2" ]; then
                # Run specific seed
                seed_name="$2"
                if [ -f "$SEEDS_DIR/$seed_name.sql" ]; then
                    execute_seed "$SEEDS_DIR/$seed_name.sql" $PASSWORD $track $dry_run $verbose
                elif [ -f "$SEEDS_DIR/$seed_name" ]; then
                    execute_seed "$SEEDS_DIR/$seed_name" $PASSWORD $track $dry_run $verbose
                else
                    echo -e "${RED}Seed not found: $seed_name${NC}"
                    exit 1
                fi
            else
                # Run all seeds
                run_seeds_by_pattern "*.sql" $PASSWORD $track $dry_run $verbose $clear_first
            fi
            ;;
        status)
            show_seed_status $PASSWORD
            ;;
        refresh)
            if [ "$force" = false ]; then
                echo -e "${YELLOW}This will clear existing data and reapply all seeds${NC}"
                read -p "Continue? (y/n) " -n 1 -r
                echo
                if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                    echo "Refresh cancelled."
                    exit 0
                fi
            fi
            run_seeds_by_pattern "*.sql" $PASSWORD $track $dry_run $verbose true
            ;;
        reference)
            run_seeds_by_pattern "00_reference_*.sql" $PASSWORD $track $dry_run $verbose $clear_first
            ;;
        test)
            run_seeds_by_pattern "01_test_*.sql" $PASSWORD $track $dry_run $verbose $clear_first
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