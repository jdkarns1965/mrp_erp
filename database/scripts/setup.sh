#!/bin/bash

# MRP/ERP Database Sync System Setup
# Initializes the database synchronization system

# Configuration
DB_NAME="mrp_erp"
DB_USER="root"
SCRIPT_DIR="/var/www/html/mrp_erp/database/scripts"
DATABASE_DIR="/var/www/html/mrp_erp/database"

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
    echo -e "${CYAN}║     MRP/ERP Database Sync Setup          ║${NC}"
    echo -e "${CYAN}║       Initializing Sync System           ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
    echo
}

# Display help
show_help() {
    echo "Usage: $0 [options]"
    echo
    echo "Options:"
    echo "  --install            Install sync system (default)"
    echo "  --clean              Clean up old files"
    echo "  --permissions        Fix file permissions"
    echo "  --verify             Verify installation"
    echo "  --uninstall          Remove sync system"
    echo "  --help               Show this help"
    echo
    echo "Examples:"
    echo "  $0                   # Install sync system"
    echo "  $0 --clean           # Clean old files"
    echo "  $0 --verify          # Verify setup"
}

# Check if running from correct directory
check_location() {
    if [ ! -f "$DATABASE_DIR/README_SYNC_SYSTEM.md" ]; then
        echo -e "${RED}✗ Must run from MRP/ERP database directory${NC}"
        echo "Current directory: $(pwd)"
        echo "Expected directory: $DATABASE_DIR"
        exit 1
    fi
    echo -e "${GREEN}✓ Running from correct directory${NC}"
}

# Create directory structure
create_directories() {
    echo -e "${YELLOW}→ Creating directory structure...${NC}"
    
    mkdir -p "$DATABASE_DIR"/{schema,migrations,seeds,scripts,backups}
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Directory structure created${NC}"
        echo "  - schema/ (database schemas)"
        echo "  - migrations/ (incremental changes)"
        echo "  - seeds/ (reference and test data)"
        echo "  - scripts/ (management scripts)"
        echo "  - backups/ (automatic backups)"
    else
        echo -e "${RED}✗ Failed to create directories${NC}"
        exit 1
    fi
}

# Set file permissions
fix_permissions() {
    echo -e "${YELLOW}→ Setting file permissions...${NC}"
    
    # Make scripts executable
    chmod +x "$SCRIPT_DIR"/*.sh 2>/dev/null
    
    # Set proper file permissions
    chmod 644 "$DATABASE_DIR"/seeds/*.sql 2>/dev/null
    chmod 644 "$DATABASE_DIR"/migrations/*.sql 2>/dev/null
    chmod 644 "$DATABASE_DIR"/schema/*.sql 2>/dev/null
    
    # Secure backup directory
    chmod 700 "$DATABASE_DIR"/backups/ 2>/dev/null
    
    # Make documentation readable
    chmod 644 "$DATABASE_DIR"/*.md 2>/dev/null
    
    echo -e "${GREEN}✓ Permissions set${NC}"
    echo "  - Scripts: executable (755)"
    echo "  - SQL files: readable (644)"
    echo "  - Backups: secure (700)"
    echo "  - Documentation: readable (644)"
}

# Clean up old files
clean_old_files() {
    echo -e "${YELLOW}→ Cleaning up old files...${NC}"
    
    local cleaned=0
    
    # Move old files to backup location
    if [ -f "$DATABASE_DIR/test_data_simple.sql" ]; then
        mv "$DATABASE_DIR/test_data_simple.sql" "$DATABASE_DIR/seeds/legacy_test_simple.sql"
        echo "  Moved: test_data_simple.sql → seeds/legacy_test_simple.sql"
        ((cleaned++))
    fi
    
    if [ -f "$DATABASE_DIR/create_inventory_transactions.sql" ]; then
        rm "$DATABASE_DIR/create_inventory_transactions.sql"
        echo "  Removed: create_inventory_transactions.sql (replaced by migration)"
        ((cleaned++))
    fi
    
    # Clean up old migration script
    if [ -f "$DATABASE_DIR/migrate.sh" ]; then
        if [ -f "$SCRIPT_DIR/migrate.sh" ]; then
            rm "$DATABASE_DIR/migrate.sh"
            echo "  Removed: old migrate.sh (replaced by scripts/migrate.sh)"
            ((cleaned++))
        fi
    fi
    
    # Remove temporary files
    rm -f "$DATABASE_DIR"/.DS_Store 2>/dev/null
    rm -f "$DATABASE_DIR"/._* 2>/dev/null
    
    if [ $cleaned -eq 0 ]; then
        echo -e "${GREEN}✓ No old files to clean${NC}"
    else
        echo -e "${GREEN}✓ Cleaned $cleaned old files${NC}"
    fi
}

# Verify installation
verify_installation() {
    echo -e "${BLUE}Verifying Installation:${NC}"
    echo "======================"
    
    local errors=0
    
    # Check directories
    for dir in schema migrations seeds scripts backups; do
        if [ -d "$DATABASE_DIR/$dir" ]; then
            echo -e "${GREEN}✓${NC} Directory: $dir/"
        else
            echo -e "${RED}✗${NC} Directory: $dir/ (missing)"
            ((errors++))
        fi
    done
    
    # Check scripts
    for script in backup.sh restore.sh migrate.sh sync.sh seed.sh setup.sh; do
        if [ -x "$SCRIPT_DIR/$script" ]; then
            echo -e "${GREEN}✓${NC} Script: $script"
        else
            echo -e "${RED}✗${NC} Script: $script (missing or not executable)"
            ((errors++))
        fi
    done
    
    # Check documentation
    for doc in README_SYNC_SYSTEM.md QUICK_REFERENCE.md; do
        if [ -f "$DATABASE_DIR/$doc" ]; then
            echo -e "${GREEN}✓${NC} Documentation: $doc"
        else
            echo -e "${RED}✗${NC} Documentation: $doc (missing)"
            ((errors++))
        fi
    done
    
    # Check for seed files
    local seed_count=$(ls -1 "$DATABASE_DIR"/seeds/*.sql 2>/dev/null | wc -l)
    if [ $seed_count -gt 0 ]; then
        echo -e "${GREEN}✓${NC} Seed files: $seed_count available"
    else
        echo -e "${YELLOW}⚠${NC} Seed files: none found"
    fi
    
    # Check for migration files
    local migration_count=$(ls -1 "$DATABASE_DIR"/migrations/*.sql 2>/dev/null | wc -l)
    if [ $migration_count -gt 0 ]; then
        echo -e "${GREEN}✓${NC} Migration files: $migration_count available"
    else
        echo -e "${YELLOW}⚠${NC} Migration files: none found"
    fi
    
    echo
    if [ $errors -eq 0 ]; then
        echo -e "${GREEN}✓ Installation verification passed${NC}"
        return 0
    else
        echo -e "${RED}✗ Installation verification failed ($errors errors)${NC}"
        return 1
    fi
}

# Test database connection
test_database() {
    echo -e "${YELLOW}→ Testing database connection...${NC}"
    
    read -sp "Enter MySQL password for $DB_USER: " PASSWORD
    echo
    
    if mysql -u $DB_USER -p$PASSWORD -e "SELECT 1;" 2>/dev/null >/dev/null; then
        echo -e "${GREEN}✓ Database connection successful${NC}"
        
        # Check if database exists
        if mysql -u $DB_USER -p$PASSWORD -e "USE $DB_NAME;" 2>/dev/null; then
            echo -e "${GREEN}✓ Database '$DB_NAME' exists${NC}"
        else
            echo -e "${YELLOW}⚠ Database '$DB_NAME' does not exist${NC}"
            read -p "Create database? (y/n) " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                mysql -u $DB_USER -p$PASSWORD -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
                echo -e "${GREEN}✓ Database created${NC}"
            fi
        fi
    else
        echo -e "${RED}✗ Database connection failed${NC}"
        echo "Please check MySQL credentials and server status."
        return 1
    fi
}

# Show next steps
show_next_steps() {
    echo
    echo -e "${BLUE}Next Steps:${NC}"
    echo "==========="
    echo
    echo "1. Database Setup:"
    echo "   ./scripts/migrate.sh fresh    # Create fresh database"
    echo "   ./scripts/seed.sh reference   # Add reference data"
    echo
    echo "2. Daily Usage:"
    echo "   ./scripts/backup.sh --auto    # Create backups"
    echo "   ./scripts/migrate.sh status   # Check migration status"
    echo
    echo "3. Documentation:"
    echo "   cat README_SYNC_SYSTEM.md     # Full documentation"
    echo "   cat QUICK_REFERENCE.md        # Quick reference"
    echo
    echo "4. Set up automated backups:"
    echo "   crontab -e"
    echo "   # Add: 0 2 * * * cd $DATABASE_DIR && ./scripts/backup.sh --auto"
    echo
    echo -e "${GREEN}✓ Setup complete! The database sync system is ready to use.${NC}"
}

# Uninstall sync system
uninstall_system() {
    echo -e "${RED}⚠️  UNINSTALL WARNING ⚠️${NC}"
    echo "This will remove the sync system but preserve backups and data."
    echo
    read -p "Type 'CONFIRM' to proceed: " confirmation
    
    if [ "$confirmation" != "CONFIRM" ]; then
        echo "Uninstall cancelled."
        return 0
    fi
    
    echo -e "${YELLOW}→ Removing sync system...${NC}"
    
    # Remove scripts (but keep backups)
    rm -f "$SCRIPT_DIR"/backup.sh
    rm -f "$SCRIPT_DIR"/restore.sh
    rm -f "$SCRIPT_DIR"/migrate.sh
    rm -f "$SCRIPT_DIR"/sync.sh
    rm -f "$SCRIPT_DIR"/seed.sh
    rm -f "$SCRIPT_DIR"/setup.sh
    
    # Remove documentation
    rm -f "$DATABASE_DIR"/README_SYNC_SYSTEM.md
    rm -f "$DATABASE_DIR"/QUICK_REFERENCE.md
    
    # Remove empty script directory
    rmdir "$SCRIPT_DIR" 2>/dev/null
    
    echo -e "${GREEN}✓ Sync system uninstalled${NC}"
    echo "Backups and data preserved in:"
    echo "  - $DATABASE_DIR/backups/"
    echo "  - $DATABASE_DIR/seeds/"
    echo "  - $DATABASE_DIR/migrations/"
}

# Main installation function
install_system() {
    echo -e "${BLUE}Installing Database Sync System...${NC}"
    echo
    
    create_directories
    fix_permissions
    clean_old_files
    
    echo
    verify_installation
    
    if [ $? -eq 0 ]; then
        echo
        test_database
        show_next_steps
    else
        echo -e "${RED}Installation failed. Please check errors above.${NC}"
        exit 1
    fi
}

# Main script logic
main() {
    show_banner
    check_location
    
    case "${1:-install}" in
        --install|install)
            install_system
            ;;
        --clean)
            clean_old_files
            ;;
        --permissions)
            fix_permissions
            ;;
        --verify)
            verify_installation
            ;;
        --uninstall)
            uninstall_system
            ;;
        --help|help)
            show_help
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