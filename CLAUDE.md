# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a modular MRP → ERP Manufacturing System built with PHP and MySQL. The project is in early development stages, starting with Material Requirements Planning (MRP) and expanding into full Enterprise Resource Planning (ERP) functionality.

## Current Development Phase

**Phase 2: Production Scheduling** (In Progress)
- ✅ Phase 1: Core MRP completed and production-ready
- Building production scheduling and capacity planning
- Implementing work center management and calendars
- Creating Gantt chart visualization for production schedules
- Adding production order tracking and operation management

## Development Environment Setup

1. **Prerequisites:**
   - LAMP stack (Linux, Apache, MySQL, PHP)
   - WSL2 environment with VS Code
   - MySQL database server
   - **PHP 7.4+ with mysqli extension** (see PHP Requirements below)

2. **Database Setup:**
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE mrp_erp;"
   
   # Import schema (when available)
   mysql -u root -p mrp_erp < database/schema.sql
   ```

3. **Environment Configuration:**
   - Create `.env` file in project root with database credentials
   - Configure Apache virtual host to point to project directory
   - Ensure proper permissions: `chmod 755` for directories, `chmod 644` for files

## PHP Requirements

**Target PHP Version:** 7.4 - 8.2
- **Minimum:** PHP 7.4 (current production environment)
- **Maximum:** PHP 8.2 (tested compatibility)
- **Recommended:** PHP 8.1 for optimal performance

### Required Extensions
- `mysqli` - Database connectivity
- `json` - JSON handling
- `session` - Session management
- `filter` - Input validation
- `date` - Date/time functions

### Acceptable PHP Features
**✅ Safe to Use:**
- Arrow functions `fn() =>` (PHP 7.4+)
- Typed properties (PHP 7.4+)
- Null coalescing assignment `??=` (PHP 7.4+)
- Union types `string|int` (PHP 8.0+)
- Match expressions (PHP 8.0+)
- Constructor property promotion (PHP 8.0+)
- Named arguments (PHP 8.0+)

**❌ Avoid:**
- Enums (PHP 8.1+) - not critical for this project
- Readonly properties (PHP 8.1+) - use private/protected instead
- Fibers (PHP 8.1+) - not needed for this application
- `never` return type (PHP 8.1+) - use `void` instead

### Code Standards
- Use strict typing: `declare(strict_types=1);` in all new files
- Prepared statements only (no string concatenation in SQL)
- Type hints on all function parameters and return types
- Use `??` and `?:` operators for null safety

## Project Structure (Planned)

```
/var/www/html/mrp_erp/
├── classes/           # OOP PHP classes (Model layer)
│   ├── Database.php   # Database connection singleton
│   ├── Material.php   # Materials management
│   ├── Product.php    # Products management
│   ├── BOM.php        # Bill of Materials
│   ├── Inventory.php  # Inventory tracking
│   └── MRP.php        # MRP calculation engine
├── controllers/       # Controller logic (MVC pattern)
├── views/            # PHP view templates
├── public/           # Web root (CSS, JS, images)
│   ├── css/         # Vanilla CSS (mobile-first)
│   └── js/          # Vanilla JavaScript
├── database/         # SQL schemas and migrations
├── config/           # Configuration files
└── docs/            # Documentation

```

## Code Architecture Guidelines

### PHP Development
- Use OOP with simple MVC-style structure
- Follow PSR-4 autoloading standards when implementing
- Database interactions through prepared statements (mysqli)
- Single responsibility principle for classes
- Mobile-first responsive design

### Database Design Principles
- Normalized tables (3NF minimum)
- Foreign key constraints for referential integrity
- Lot tracking capabilities from the start
- UTC timestamps for all date/time fields
- Soft deletes where appropriate (deleted_at field)

### Key Database Tables
**Phase 1 (Core MRP):**
- `materials` - Raw materials (resins, inserts, packaging)
- `products` - Finished goods with part numbers
- `bom_headers` & `bom_details` - Bill of Materials structure
- `inventory` - Current stock levels with lot tracking
- `customer_orders` - Customer order management

**Phase 2 (Production Scheduling):**
- `work_centers` - Machines, assembly stations, work areas
- `work_center_calendar` - Shift schedules and availability
- `production_routes` - Manufacturing sequences per product
- `production_orders` - Manufacturing orders from customer demand
- `production_order_operations` - Detailed operation scheduling
- `production_order_materials` - Material reservations
- `production_order_status_history` - Status change tracking

**Phase 2 Complete (Document Management):**
- `documents` - File metadata with material/product associations
- Storage path: `storage/documents/[entity_type]/[category]/`
- API endpoints: `/api/documents.php`, `/api/document-download.php`
- Supported formats: PDF, DOC, DOCX, images

**Phase 3+ (Purchasing & ERP):**
- `purchase_orders` - Supplier orders (planned)
- `suppliers` - Supplier management (ready)
- `financial_transactions` - Cost tracking (planned)

## Development Commands

```bash
# Start local development server
php -S localhost:8000 -t public/

# MySQL database access
mysql -u root -p mrp_erp

# Database Migration Management
cd /var/www/html/mrp_erp/database
./scripts/migrate.sh status              # Check migration status
./scripts/migrate.sh up --dry-run        # Preview pending migrations  
./scripts/migrate.sh up --backup         # Apply migrations with backup
./scripts/migrate.sh down --steps=1      # Rollback last migration
./scripts/migrate.sh create <name>       # Create new migration file

# Quick Backup System
./scripts/quick-backup.sh               # Create timestamped backup
./scripts/quick-restore.sh              # Restore latest backup
./scripts/backup.sh                     # Full backup with compression
./scripts/restore.sh <backup_file>      # Restore specific backup

# Health Check & Troubleshooting
curl http://localhost/mrp_erp/public/verify_setup.php  # System health check
php /var/www/html/mrp_erp/debug_mrp.php               # Debug MRP calculations
php /var/www/html/mrp_erp/debug_bom.php               # Debug BOM issues

# API Testing
php test_search_api.php                 # Test search APIs
php test_mrp_validation.php             # Test MRP calculations
php test_form_debug.php                 # Debug form submissions

# Fix Common WSL2 Permission Issues
sudo chmod 755 /var/www/html/mrp_erp/database/scripts/*.sh
sudo chown -R www-data:www-data /var/www/html/mrp_erp/storage/
sudo chmod 755 /var/www/html/mrp_erp/storage/ -R

# PHP Code Quality
php -l filename.php                     # Check PHP syntax
find . -name "*.php" -exec php -l {} \; # Run PHP linter on all files
```

## Testing Approach

### Manual Testing
- Manual testing through web interface initially
- Database transaction testing for MRP calculations
- Test with realistic manufacturing scenarios:
  - Multi-level BOMs
  - Inventory shortages
  - Production scheduling conflicts

### Automated Testing & Health Checks
- **System Health**: `curl http://localhost/mrp_erp/public/verify_setup.php`
- **MRP Validation**: `php test_mrp_validation.php`
- **Search API Testing**: `php test_search_api.php`
- **Form Debugging**: `php test_form_debug.php`
- **Migration Status**: `cd database && ./scripts/migrate.sh status`

### Debug Scripts
- `debug_mrp.php` - Debug MRP calculation issues
- `debug_bom.php` - Debug Bill of Materials problems
- Migration dry-run: `./scripts/migrate.sh up --dry-run`

## Key Implementation Notes

### MRP Calculation Logic
1. Accept customer order input (product + quantity)
2. Explode BOM recursively for all components
3. Calculate gross requirements
4. Compare against current inventory
5. Generate net requirements (shortages)
6. Suggest purchase orders for materials

### Mobile-First UI Requirements
- Responsive grid layouts
- Touch-friendly form controls
- Minimal JavaScript dependencies
- Fast page loads (< 2 seconds)
- Works on phones, tablets, and desktops
- Tooltips on all form fields that need guidance

### Layout System (Updated August 2025)
**Flexbox-based layout eliminates footer white space issues**

**Core CSS Structure:**
```css
html { height: 100%; margin: 0; padding: 0; }
body { 
    min-height: 100vh; 
    display: flex; 
    flex-direction: column; 
    margin: 0; 
    padding: 0; 
}
.container { flex: 1; }  /* Main content grows */
footer { margin-top: auto; }  /* Footer sticks to bottom */
```

**Benefits:**
- ✅ No white space below footer
- ✅ Footer always at bottom of viewport or content
- ✅ Flexible content areas that grow/shrink properly
- ✅ Consistent across all pages

### Modern UI Patterns & Components
**Established in Phase 2** - Use these patterns for consistent, professional interfaces across all pages.

#### **Modern Search Interface Pattern**
**Files:** `/public/materials/index.php`, `/public/css/materials-modern.css`

**Structure:**
```html
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Page Title</h2>
        </div>
        
        <!-- Search Bar with Add Button -->
        <div class="search-bar">
            <div class="search-bar-header">
                <div class="search-form-container">
                    <form method="GET" id="searchForm">
                        <!-- Stacked search elements -->
                        <div class="search-input-section">
                            <input type="text" data-autocomplete-preset="entity-search">
                            <div class="recent-searches" id="recentSearches">
                                <!-- Recent searches as clean links -->
                            </div>
                        </div>
                        <div class="search-controls">
                            <div class="search-buttons">
                                <button class="btn btn-secondary">Search</button>
                                <a href="index.php" class="btn btn-outline">Clear</a>
                            </div>
                            <label class="checkbox-label">
                                <input type="checkbox"> Filter Option
                            </label>
                        </div>
                    </form>
                </div>
                <div class="search-actions">
                    <a href="create.php" class="btn btn-primary">Add Entity</a>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Key Features:**
- **Seamless search unit**: Input → Recent searches → Controls stacked vertically
- **No visual separators**: Borderless flow between search elements
- **Action button in search area**: "Add" button positioned logically with content
- **Clean recent searches**: Underlined text links, not pill-shaped buttons
- **Grouped controls**: Related buttons (Search/Clear) grouped together

#### **Modern List Interface Pattern**
**Replaces card-based layouts for better information density**

**Structure:**
```html
<div class="materials-list-modern">
    <div class="materials-list-header">
        <h2 class="list-title">Entity Inventory</h2>
        <div class="list-meta">X entities found</div>
    </div>
    
    <div class="filter-panel">
        <div class="quick-filters">
            <button class="filter-btn active">All Items</button>
            <button class="filter-btn alert">Low Stock <span class="badge">3</span></button>
            <button class="filter-btn alert">Critical <span class="badge">5</span></button>
        </div>
    </div>
    
    <div class="bulk-actions-bar" id="bulkActionsBar">
        <div class="bulk-info">X items selected</div>
        <div class="bulk-actions">
            <button class="bulk-btn">Export</button>
            <button class="bulk-btn primary">Bulk Action</button>
        </div>
    </div>
    
    <div class="materials-list">
        <div class="list-item" data-attributes="">
            <div class="item-selector">
                <input type="checkbox" class="item-checkbox">
            </div>
            <div class="item-primary">
                <div class="item-header">
                    <span class="entity-code">CODE-001</span>
                    <div class="status-indicators">
                        <span class="stock-status critical"></span>
                        <span class="type-badge">Type</span>
                    </div>
                </div>
                <h3 class="entity-name">Entity Name</h3>
                <div class="item-meta">
                    <span>Category: Value</span>
                    <span>UOM: Each</span>
                </div>
            </div>
            <div class="item-metrics">
                <div class="metric">
                    <label>Metric 1</label>
                    <span class="value">100.00</span>
                </div>
            </div>
            <div class="item-actions">
                <button class="action-quick" title="Quick Action">⚡</button>
                <button class="action-menu-toggle">⋮</button>
            </div>
        </div>
    </div>
</div>
```

**Benefits:**
- **50% more items visible** per screen vs card layout
- **Better scanning** with consistent information hierarchy
- **Smart filtering** with visual badges and counts
- **Bulk operations** with checkbox selection
- **Quick actions** for common tasks

#### **Filter Button Standards**
**Consistent sizing and behavior across all interfaces**

**CSS Requirements:**
```css
.filter-btn {
    height: 32px; /* Consistent height - no min-height */
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    box-sizing: border-box;
}
```

**Alert States:**
- **Normal filters**: White background, gray border
- **Alert filters**: Yellow background for warnings (Low Stock, etc.)
- **Active state**: Blue background for currently selected
- **Badges**: Small red circles with white numbers

#### **Autocomplete Integration**
**Disable conflicting systems to maintain clean recent searches**

**AutocompleteManager Configuration:**
```javascript
// Disable built-in history to use custom recent searches
'entity-search': {
    enableHistory: false,  // Important: prevents pill-shaped chips
    behavior: 'search-submit',
    // ... other config
}
```

**Recent Searches Implementation:**
- Use localStorage with entity-specific keys
- Display as clean underlined links, not pills
- Limit to 5 most recent, store up to 10
- Integrate with form submission, not autocomplete selection

#### **Z-Index Layer Management**
**Consistent layering prevents UI conflicts**

```css
.filter-panel { z-index: 50; }        /* Always visible */
.bulk-actions-bar { z-index: 30; }    /* Below filters */
.autocomplete-dropdown { z-index: 1000; } /* Above everything */
```

#### **Action Menu System (Updated August 2025)**
**Robust event delegation system for ⋮ menu toggles**

**Implementation:**
```javascript
// Uses setupActionMenus() function with event delegation
// No inline onclick handlers - purely event-driven
// Automatically handles both full list and search results
```

**Required CSS for proper positioning:**
```css
.item-actions { position: relative; }           // Parent positioning
.action-menu { z-index: 1000; overflow: visible; }  // Menu layering
.materials-list-modern { overflow: visible; }   // Prevent clipping
.list-item { overflow: visible; }              // Allow menu overflow
```

**Key Features:**
- ✅ Works in all scenarios (full list + search results)
- ✅ Event delegation prevents onclick conflicts
- ✅ Debug logging for troubleshooting
- ✅ Proper z-index layering (1000)
- ✅ Click-outside to close functionality
- ✅ No clipping issues with containers

#### **Implementation Checklist**
**Use this checklist when applying to new pages:**

- [ ] Replace card layout with list-based design
- [ ] Implement stacked search (input → recents → controls)
- [ ] Add filter buttons with consistent heights and badges
- [ ] Configure autocomplete with `enableHistory: false`
- [ ] Add bulk selection and actions bar
- [ ] Implement action menus using setupActionMenus() pattern
- [ ] Add proper z-index layering and overflow:visible
- [ ] Test mobile responsiveness
- [ ] Ensure touch-friendly targets (44px minimum)

#### **CSS Files to Include**
```html
<link rel="stylesheet" href="../css/materials-modern.css">
<link rel="stylesheet" href="../css/autocomplete.css">
```

#### **JavaScript Dependencies**
```html
<script src="../js/autocomplete.js"></script>
<script src="../js/autocomplete-manager.js"></script>
```

**Status:** ✅ **Materials page complete** - Use as reference for other entity pages

### Entity Edit Link Guidelines
**Standard Pattern:** Use `includes/ui-helpers.php` for contextual edit links throughout the system.

**Implementation:**
```php
// Include the helper file
require_once '../../includes/ui-helpers.php';

// Use in templates for entity names with edit links
echo renderEntityName('material', $materialId, $materialName);
echo renderEntityName('product', $productId, $productName);
```

**Design Standards:**
- Small edit icon (✏️) next to entity names
- Subtle styling with hover effects
- Mobile-friendly touch targets (44px minimum)
- Consistent placement and behavior across all pages

**Usage Guidelines:**
- **High-value contexts**: BOM views, inventory lists, production orders
- **Permission-aware**: Ready for future role-based access control
- **Consistent styling**: Use established CSS classes for uniformity

**Implementation Status:**
- ✅ Phase 1: BOM materials table (`bom/view.php`)
- 🔄 Phase 2: Inventory items table (planned)
- 🔄 Phase 3: MRP results and other reference contexts (planned)

### Security Considerations
- Parameterized queries to prevent SQL injection
- Input validation and sanitization
- Role-based access control (Phase 2)
- Session management with PHP sessions
- HTTPS in production

## Phase-Specific Focus Areas

### ✅ Completed (Phase 1): Core MRP
- ✅ Accurate BOM explosion logic
- ✅ Reliable inventory tracking with lot control
- ✅ Clear shortage reporting and MRP calculations
- ✅ Mobile-responsive UI with autocomplete
- ✅ Customer order management
- ✅ Dashboard with real-time metrics

### 🔄 Current (Phase 2): Production Scheduling
- ✅ Work center and capacity management
- ✅ Production order creation from customer orders
- ✅ Forward and backward scheduling algorithms
- ✅ Gantt chart visualization with capacity planning
- ✅ Production operation tracking and status updates
- ✅ Material reservation and allocation
- 🔄 Production reporting and analytics (in development)

### 📋 Future (Phase 3+): Purchasing & ERP
- Automated PO generation from MRP shortages
- Advanced supplier management and EDI
- Financial integration and cost accounting
- Quality control modules and testing
- Advanced analytics and reporting
- Integration with external systems  

## Autocomplete/Autosuggest Implementation Guidelines

### Overview
The MRP/ERP system uses a centralized, configuration-driven autocomplete system based on the `AutocompleteManager` class. This provides consistent behavior, easy maintenance, and minimal code duplication across all search fields and forms.

### Core Files
- `public/js/autocomplete-manager.js` - **NEW** Centralized manager with presets
- `public/js/autocomplete.js` - Base AutoComplete class (still used internally)
- `public/css/autocomplete.css` - Styling for all autocomplete components
- `public/api/` - API endpoints for different entity types

### **NEW: Centralized AutocompleteManager System**

The new system eliminates code duplication and provides preset configurations for common use cases.

### API Endpoints
- `public/api/materials-search.php` - Material search with type, category, UOM info
- `public/api/categories-search.php` - Product/material categories (use ?type=material for materials)
- `public/api/uom-search.php` - Units of measure
- `public/api/suppliers-search.php` - Supplier information
- `public/api/locations-search.php` - Warehouse/location data
- `public/products/search-api.php` - Product search (existing)

### **NEW Implementation Patterns (Recommended)**

#### 1. Simple Data Attribute Initialization (Auto-init)
```html
<!-- Search field that auto-submits form -->
<input type="text" 
       name="search" 
       data-autocomplete-preset="products-search"
       autocomplete="off">

<!-- Form field with hidden value -->
<input type="text" 
       name="material_search" 
       data-autocomplete-preset="materials-form"
       autocomplete="off">
```

#### 2. Programmatic Initialization with Manager
```javascript
// Initialize with preset
AutocompleteManager.init('materials-form', '#materialInput');

// Initialize with custom configuration
AutocompleteManager.init('custom', '#customInput', {
    apiUrl: '../api/custom-search.php',
    displayField: 'name',
    behavior: 'form-field',
    onSelect: function(item, inputEl) {
        console.log('Selected:', item);
    }
});

// Initialize multiple elements
AutocompleteManager.init('materials-form', '.material-input');
```

#### 3. Available Presets
- `products-search` - Product search with form submission
- `materials-search` - Material search with form submission  
- `materials-form` - Material selection for forms (with hidden field)
- `products-form` - Product selection for forms
- `categories-form` - Category selection
- `uom-form` - Unit of measure selection
- `suppliers-form` - Supplier selection
- `locations-form` - Location/warehouse selection

### **Legacy Implementation Patterns (Still Supported)**

#### Old Direct AutoComplete Usage
```javascript
// Still works but not recommended for new code
const autocomplete = new AutoComplete(inputElement, {
    apiUrl: '../api/materials-search.php',
    displayField: 'label',
    valueField: 'id',
    showCategory: true,
    onSelect: function(item, inputEl) {
        // Handle selection
    }
});
```

### Required Includes
**NEW:** For pages using the centralized manager:
```html
<link rel="stylesheet" href="../css/autocomplete.css">
<script src="../js/autocomplete.js"></script>
<script src="../js/autocomplete-manager.js"></script>
```

**LEGACY:** For pages using direct AutoComplete:
```html
<link rel="stylesheet" href="../css/autocomplete.css">
<script src="../js/autocomplete.js"></script>
```

### AutoComplete Class Options
- `apiUrl`: API endpoint URL
- `minChars`: Minimum characters to trigger search (default: 1)
- `debounceMs`: Debounce delay in milliseconds (default: 300)
- `maxResults`: Maximum results to show (default: 10)
- `displayField`: Field to show in input (default: 'label')
- `valueField`: Field to use as value (default: 'value')
- `searchParam`: Query parameter name (default: 'q')
- `showCategory`: Show category badges (default: false)
- `onSelect`: Callback when item is selected
- `onClear`: Callback when input is cleared

### API Response Format
All autocomplete APIs should return JSON arrays with this structure:
```json
[
    {
        "id": 1,
        "value": 1,
        "label": "MAT-001 - Plastic Resin",
        "code": "MAT-001",
        "name": "Plastic Resin",
        "category": "Raw Materials",
        "type": "material",
        "uom": "KG",
        "cost": 2.50
    }
]
```

### **Migration Status**
- ✅ **Products search** (products/index.php) - **MIGRATED** to AutocompleteManager
- ✅ **Materials search** (materials/index.php) - **MIGRATED** to AutocompleteManager  
- ✅ **BOM material selection** (bom/create.php) - **MIGRATED** to AutocompleteManager
- ✅ **Document management** (documents/index.php) - **COMPLETE** with file upload/download
- 🔄 BOM edit page (bom/edit.php) - Needs migration
- 🔄 Inventory forms (item selection) - Needs implementation
- 🔄 Order forms (product selection) - Needs implementation
- 🔄 Category/UOM dropdowns (throughout forms) - Needs implementation

### **Benefits of New System**
- **90% less code** - Products page went from 190+ lines to 1 line
- **Consistent behavior** - All search fields work the same way
- **Easy maintenance** - One place to update all autocomplete functionality
- **Configuration-driven** - Add new types by creating presets
- **Backward compatible** - Old implementations still work

### Best Practices
1. Always use data attributes for simple initialization
2. Use programmatic initialization for dynamic content
3. Include proper error handling in API endpoints
4. Maintain consistent response formats across APIs
5. Use debouncing to avoid excessive API calls
6. Provide meaningful placeholder text
7. Include category information when helpful
8. Auto-populate related fields when possible (e.g., UOM when material selected)

### Mobile Considerations
- Touch-friendly 44px minimum target size
- Larger dropdown items on mobile
- Responsive design breaks to single column
- Reduced motion support for accessibility

### Future Enhancements
- Caching for frequently accessed data
- Offline support with localStorage
- Advanced filtering options
- Multi-select autocomplete for tags/categories

## Phase 2: Production Scheduling Implementation Guidelines

### Core Classes
- `ProductionScheduler.php` - Main scheduling engine with forward/backward scheduling
- Handles production order creation from customer orders
- Manages work center capacity allocation and availability
- Provides Gantt chart data for visualization

### Production Order Workflow
1. **Order Creation**: Convert customer orders to production orders via `production/create.php`
2. **Scheduling**: Automatic or manual scheduling using `ProductionScheduler` class
3. **Execution**: Track operations through `production/operations.php`
4. **Monitoring**: Visual timeline via Gantt chart in `production/gantt.php`

### Key Features Implemented
- **Work Center Management**: Machines, assembly stations with capacity and calendars
- **Production Routes**: Define operation sequences per product
- **Capacity Planning**: Calculate work center utilization and availability
- **Operation Tracking**: Real-time status updates for individual operations
- **Material Reservations**: Automatic allocation from BOM requirements
- **Status History**: Complete audit trail of production order changes
- **Document Management**: File attachments for materials/products with API support

### Database Views for Performance
- `v_work_center_capacity` - Aggregated capacity data for planning
- `v_production_schedule` - Production order progress and completion metrics

### Scheduling Algorithms
- **Forward Scheduling**: Start ASAP, schedule operations sequentially
- **Backward Scheduling**: Work backward from customer due date
- **Capacity Checks**: Automatic conflict detection and resolution
- **Setup/Teardown**: Includes machine setup times in calculations

### UI Components
- **Production Dashboard**: Order status overview with statistics
- **Gantt Chart**: Interactive timeline with capacity visualization  
- **Operations Tracking**: Real-time operation status and progress
- **Mobile-Responsive**: Touch-friendly interface for shop floor use

### Status Management
Production orders flow through: `planned` → `released` → `in_progress` → `completed`
Operations flow through: `planned` → `ready` → `in_progress` → `completed`

### Integration Points
- Connects to Phase 1 MRP data (BOMs, inventory, customer orders)
- Prepares for Phase 3 financial integration (cost tracking ready)
- Material reservations integrate with inventory management

### Performance Considerations
- Indexed scheduling queries for large datasets
- Efficient Gantt chart rendering with date range limits
- Optimized capacity calculation views
- Background scheduling for large production runs

## Next Phase Planning

### Phase 3: Purchasing & Advanced ERP
Ready to implement:
- Purchase order generation from MRP shortages
- Supplier portal and EDI integration
- Advanced cost accounting and financial integration
- Quality control and testing workflows
- Advanced reporting and analytics dashboard

---

## System Development Manual

📖 **Detailed system documentation has been moved to: [docs/SYSTEM_DEVELOPMENT.md](docs/SYSTEM_DEVELOPMENT.md)**

### Quick Reference
- **Current Phase**: Phase 2 Production Scheduling (85% Complete)
- **Module Status**: 7/8 modules complete (MPS pending)
- **Database**: Fully normalized with migration system
- **Testing**: Phase 1 complete, Phase 2 in progress

### Key Development Guidelines
- Use AutocompleteManager for all search fields
- Follow mobile-first responsive design
- Prepared statements for all database queries
- Update migrations in `/database/migrations/`
- Maintain this manual when adding features

For complete module documentation, database architecture, API endpoints, testing procedures, and development guidelines, see [docs/SYSTEM_DEVELOPMENT.md](docs/SYSTEM_DEVELOPMENT.md).

---

## Documentation Maintenance System

📝 **Complete documentation maintenance procedures have been moved to: [docs/DOCUMENTATION_MAINTENANCE.md](docs/DOCUMENTATION_MAINTENANCE.md)**

### Key Documentation Files
- **USER_GUIDE.md** - Comprehensive user manual
- **QUICK_REFERENCE.md** - Printable quick reference card  
- **docs/USER_OPERATIONS.md** - Daily operations guide
- **includes/help-system.php** - In-app contextual help

### Update Triggers
- ✅ New page/feature added - Update immediately
- ✅ Navigation structure changes - Update all references
- ✅ Status codes/workflows modified - Verify accuracy
- **Monthly Review** - Check against actual system

For complete maintenance procedures, checklists, standards, and update workflows, see [docs/DOCUMENTATION_MAINTENANCE.md](docs/DOCUMENTATION_MAINTENANCE.md).

---

## 🔄 ACTIVE DEVELOPMENT CONTEXT

<!-- UPDATE THIS SECTION REGULARLY - IT'S READ BY ALL AGENTS AND CLAUDE CODE -->

### 📍 Current Sprint/Focus
- **Working on:** Work environment sync complete - database and environment ready
- **Priority:** Continue MPS module development and production reporting  
- **Deadline:** Complete Phase 2 production scheduling (85% done)

### 🚧 Work in Progress
```
Task: Database Migration and Work Environment Setup
Status: ✅ COMPLETE - Work dev environment synchronized
Files: database/backups/work_to_home_20250820_211704.sql.gz
Completed Today (Aug 20):
- Verified migration status: 5 migrations applied (all current)
- Created fresh backup: work_to_home_20250820_211704.sql.gz (16K)
- Database synchronized between environments
- Work environment ready for development
Next: Continue MPS module completion and production reporting fixes
```

### ⚠️ Critical Information
```
IMPORTANT - DATA PRESERVATION:
- NEVER run migrate.sh fresh without backup
- Test data preserved and active:
  - 3 materials (Plastic Resin, Metal Insert, Cardboard Box)
  - 2 products (Widget A, Widget B)
  - 2 BOMs configured
  - 3 inventory transactions
- Database current: 5 migrations applied, 0 pending
- All environments synchronized with latest backup system
```

### 🐛 Known Issues
```
Active Bugs:
- MPS module incomplete (estimated 60% done, needs verification)
- Production reporting integration with document management system
- Some legacy autocomplete code needs migration to AutocompleteManager

COMMON SCHEMA MISMATCH ISSUE:
- Problem: Blank pages after database migration/restore
- Cause: PHP code expects columns that don't exist (supplier_moq, supplier_part_number)
- Quick Fix: curl http://localhost/mrp_erp/public/verify_setup.php
- Health Check: Access verify_setup.php for detailed system status
- Permanent Fix: Either update PHP code OR add missing columns via migration

Troubleshooting Steps:
1. Check migration status: cd database && ./scripts/migrate.sh status
2. Run health check: curl http://localhost/mrp_erp/public/verify_setup.php  
3. Check migrations with --dry-run first
4. Use production dashboard instead of MPS for current workflow
5. Fix permissions if needed (see Development Commands)
```

### 💡 Recent Discoveries
```
Database: MySQL root/passgas1989, existing test data preserved
Migration: schema_migrations table tracks applied migrations
Environment: WSL2 Windows, /var/www/html/mrp_erp, git initialized
```

### 🔧 Environment Specifics
```
Home Dev:
- WSL2 Ubuntu on Windows
- MySQL 8.0.x
- PHP 7.4+
- Path: /var/www/html/mrp_erp
- Database: mrp_erp

Work Dev:
- WSL2 Ubuntu on Windows (Current Environment)
- MySQL 8.0.x with root/passgas1989 access
- PHP 7.4+ with Apache configured
- Path: /var/www/html/mrp_erp
- Database: mrp_erp (5 migrations applied, up to date)
- Special configs: Apache virtual host configured
```

### 📝 Handoff Notes
```
✅ Migration system complete with tracking & backups
✅ Test data preserved (3 materials, 2 products, BOMs)
✅ 3 pending migrations ready (002-004)

Next: Apply migrations (--dry-run first), complete MPS module, fix production reporting
```

### 🎯 Next Steps
```
Immediate:
1. ✅ Migration system setup (COMPLETE)
2. Apply pending migrations (with --dry-run first)
3. Test sync between environments
4. Document migration workflow

This Week:
1. Complete MPS module
2. Fix production reporting
3. Migrate remaining autocomplete code
4. Update user documentation
```

### 🚀 Quick Commands
```bash
# Navigation
cd /var/www/html/mrp_erp/database

# Quick Work-Home Sync
./scripts/quick-backup.sh            # Before leaving work/home
./scripts/quick-restore.sh           # After arriving home/work

# Migration Management
./scripts/migrate.sh status          # Check current state
./scripts/migrate.sh up --dry-run    # Preview changes
./scripts/migrate.sh up --backup     # Apply with safety

# Health & Troubleshooting
curl http://localhost/mrp_erp/public/verify_setup.php  # System health check
php debug_mrp.php                    # Debug MRP calculations  
php debug_bom.php                    # Debug BOM issues

# Database Access
mysql -u root -ppassgas1989 mrp_erp

# Local Development
php -S localhost:8000 -t public/     # Start dev server
sudo service apache2 start           # Start Apache (if needed)

# Fix Permissions (Common WSL2 Issues)
sudo chmod 755 database/scripts/*.sh
sudo chown -R www-data:www-data storage/

# Git Sync (End of Day)
cd database && ./scripts/quick-backup.sh
git add -A && git commit -m "Work backup $(date +%Y-%m-%d)" && git push

# Git Sync (Start of Day)  
git pull origin main && cd database && ./scripts/quick-restore.sh
```

### 📊 Test Data Reference
```
Materials: 3 (Plastic Resin 10kg, Metal Insert 50pcs, Cardboard Box 25pcs)
Products: 2 (Widget A, Widget B) with BOMs configured
Inventory: 3 transactions recorded, Customer/Production Orders: TBD
```

### 🔐 Sensitive Information
```
⚠️ DO NOT COMMIT TO GIT:
MySQL: root / passgas1989
Database: mrp_erp
[Other credentials to be added]
```

### 📅 Session History
```
2025-08-24 Late Evening: ✅ Products Management UI/UX Modernization & Search Modularity Complete
- Modernized products management page to match materials page exactly
- Replaced table layout with professional list-based interface for better information density
- Implemented consistent search bar with stacked elements (input → recent searches → controls)
- Added filter buttons with badges for stock levels (Low Stock, Out of Stock, No BOM)
- Created bulk actions bar for multi-select operations (Export, Stock Adjust, Create BOMs)
- Implemented action menus with proper ⋮ toggle system using event delegation
- Fixed search modularity: both Materials and Products pages now use identical search patterns
- Resolved pill-shaped recent searches issue by fixing AutocompleteManager presets
- Updated products-search preset: enableHistory: false to match materials-search exactly
- Synchronized script loading order and CSS includes between both pages
- Added products-list-modern CSS alias to materials-modern.css for consistency
- Recent searches now appear as clean underlined links on both pages (not pills)
- Search functionality is now truly modular and drop-in compatible for future entity pages
- Both pages use identical: HTML structure, CSS classes, JavaScript functions, localStorage patterns

2025-08-24 Evening: ✅ Action Menu System & Layout Fixes Complete
- Fixed action menu toggle not working in materials search results
- Replaced problematic inline onclick handlers with robust event delegation system
- Implemented setupActionMenus() function for centralized menu management
- Fixed menu clipping issues by updating CSS overflow properties (.materials-list-modern, .list-item)
- Increased z-index from 50 to 1000 for proper menu layering
- Added position:relative to .item-actions for proper absolute positioning
- Fixed white space below footer by implementing flexbox layout (body, container flex properties)
- Added comprehensive debug logging for menu toggle troubleshooting
- Materials page action menus now work perfectly in all scenarios (full list + search results)

2025-08-20 Evening: ✅ Work Environment Database Migration Complete
- Synchronized database from home to work dev environment
- Verified 5 migrations applied: all schemas current and up-to-date
- Created fresh backup: work_to_home_20250820_211704.sql.gz (16K)
- Updated CLAUDE.md context for work environment
- Database contains preserved test data: 3 materials, 2 products, BOMs
- Work dev environment ready for continued development

2025-01-20 Afternoon: ✅ Created seamless work-home sync system
- Implemented quick-backup.sh for one-command database backups
- Implemented quick-restore.sh for easy environment restoration
- Created comprehensive WORK_HOME_SYNC.md documentation
- Tested backup/restore cycle successfully
- Ready for end-of-day transition to home environment

2025-01-20 Night: ✅ Fixed Material view page database issues
- Fixed Apache PHP module not loading (sudo a2enmod php7.4)
- Fixed Material::findWithDetails() querying non-existent supplier_moq column
- Updated to use safety_stock_qty from actual database schema
- Materials view page now working correctly
- Decided against health check script - quick fixes via error messages work better

2025-01-20 Evening: ✅ PHP requirements documentation added
- Added PHP version compatibility guide (7.4-8.2 supported)
- Specified safe vs. avoid PHP features for consistent coding
- Defined code standards: strict typing, prepared statements, type hints
- Added required extensions list and null safety requirements

2025-01-20 Earlier: ✅ CLAUDE.md optimization - reduced 43.6k→22.8k chars
- Moved large documentation sections to docs/ directory (3 files)
- ✅ Complete database sync system with migration tracking
Previous: Production scheduling, CLAUDE.md structure updates
```

### 💭 THINKING NOTES
```
Key Decisions: Keep it simple - no over-engineering diagnostic tools for quick fixes
Questions: Auto-migration on pull? Production data anonymization? Staging env?
Ideas: Rollback methods, data anonymization, testing automation, migration UI
Lessons: Schema/code mismatches are inevitable and quick to fix - don't over-engineer
```


---

## User Operations Manual

👤 **Complete user operations guide has been moved to: [docs/USER_OPERATIONS.md](docs/USER_OPERATIONS.md)**

### Quick Daily Operations
- **Check Inventory**: Inventory → Overview (watch for yellow/red alerts)
- **Process Orders**: Orders → New Order → MRP → Calculate → Production → Create
- **Monitor Production**: Production → Gantt Chart (visual timeline)
- **Handle Shortages**: MRP → Calculate (red items need attention)

### Common Workflows
1. **Order to Production**: Order → MRP Check → Production Schedule → Monitor
2. **New Product Setup**: Products → Add → BOM → Create → Test MRP
3. **Daily Routine**: Dashboard → Production Status → Inventory Alerts → Process Orders

### Interface Quick Tips
- **Colors**: Green=Good, Yellow=Warning, Red=Critical
- **Icons**: ✏️=Edit, 🔍=Search, ➕=Add, ⚠️=Alert
- **Forms**: Red asterisk=Required, Autocomplete=Start typing

For complete workflows, troubleshooting, navigation guide, and daily operation procedures, see [docs/USER_OPERATIONS.md](docs/USER_OPERATIONS.md).