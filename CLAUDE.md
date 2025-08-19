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
   - PHP 7.4+ with mysqli extension

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

# Check PHP syntax
php -l filename.php

# Run PHP built-in linter on all files
find . -name "*.php" -exec php -l {} \;
```

## Testing Approach

- Manual testing through web interface initially
- Database transaction testing for MRP calculations
- Test with realistic manufacturing scenarios:
  - Multi-level BOMs
  - Inventory shortages
  - Production scheduling conflicts

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

### Purpose
This manual documents the current state and workflow of the MRP/ERP system to:
1. **Familiarization** - Provide clear understanding of how the application operates
2. **Continuation** - Identify incomplete areas for systematic development
3. **Consistency** - Maintain structured development tracking

### System Workflow Overview

#### Core Business Flow
```
1. Materials Management → 2. Products → 3. BOMs → 4. Inventory → 5. Customer Orders → 6. MRP Calculation → 7. Production Scheduling → 8. Execution
```

#### Module Interactions
- **Materials** feed into **BOMs** and **Inventory**
- **Products** use **BOMs** to define component requirements
- **Customer Orders** trigger **MRP calculations**
- **MRP** identifies shortages and suggests **Production Orders**
- **Production Scheduling** allocates **Work Centers** and manages capacity
- **Inventory** is consumed by production and replenished by purchasing

### Module-by-Module Documentation

#### 1. Materials Management (`/public/materials/`)
**Status:** ✅ 100% Complete

**Features:**
- Full CRUD operations (Create, Read, Update, Delete)
- Material categorization (Raw Materials, Components, Packaging)
- Units of measure (UOM) tracking
- Supplier linkage
- Cost tracking
- Reorder points and safety stock levels

**Key Files:**
- `index.php` - Material listing with search/filter (uses AutocompleteManager)
- `create.php` - New material entry form
- `edit.php` - Material modification
- `view.php` - Detailed material information

**Database Tables:**
- `materials` - Main material records
- `material_categories` - Category definitions
- `units_of_measure` - UOM definitions

#### 2. Products Management (`/public/products/`)
**Status:** ✅ 100% Complete

**Features:**
- Product creation with part numbers
- Category management
- Safety stock configuration
- Lead time tracking
- Product search with autocomplete

**Key Files:**
- `index.php` - Product listing (migrated to AutocompleteManager)
- `create.php` - New product creation
- `edit.php` - Product editing
- `view.php` - Product details
- `search-api.php` - Autocomplete API endpoint

**Database Tables:**
- `products` - Finished goods records
- `product_categories` - Product categorization

#### 3. Bill of Materials (`/public/bom/`)
**Status:** ✅ 100% Complete

**Features:**
- Multi-level BOM support
- Recursive explosion for nested components
- Version control
- Visual BOM tree display
- Material quantity calculations
- Edit links for quick navigation

**Key Files:**
- `index.php` - BOM listing
- `create.php` - BOM creation (uses AutocompleteManager)
- `edit.php` - BOM modification
- `view.php` - BOM tree visualization with edit links

**Database Tables:**
- `bom_headers` - BOM master records
- `bom_details` - Component relationships and quantities

#### 4. Inventory Management (`/public/inventory/`)
**Status:** ✅ 100% Complete

**Features:**
- Real-time stock tracking
- Lot control with expiry dates
- Multiple warehouse support
- Transaction history
- Stock adjustments
- Low stock alerts

**Key Files:**
- `index.php` - Inventory overview
- `adjust.php` - Stock adjustments
- `transactions.php` - Transaction history
- `lots.php` - Lot tracking

**Database Tables:**
- `inventory` - Current stock levels
- `inventory_transactions` - All stock movements
- `inventory_lots` - Lot/batch tracking
- `warehouses` - Location management

#### 5. Customer Orders (`/public/orders/`)
**Status:** ✅ 100% Complete

**Features:**
- Order entry and management
- Due date tracking
- Order status workflow
- Integration with MRP engine
- Order history

**Key Files:**
- `index.php` - Order listing
- `create.php` - New order entry
- `edit.php` - Order modification
- `view.php` - Order details

**Database Tables:**
- `customer_orders` - Order headers
- `customer_order_items` - Order line items
- `customers` - Customer records

#### 6. MRP Engine (`/public/mrp/`)
**Status:** ✅ 100% Complete

**Features:**
- BOM explosion algorithm
- Gross to net requirements calculation
- Shortage identification
- Purchase order suggestions
- Lead time consideration
- Safety stock management

**Key Files:**
- `index.php` - MRP dashboard
- `calculate.php` - Run MRP calculations
- `results.php` - Shortage reports

**Core Classes:**
- `MRP.php` - Main calculation engine
- `MRPEngine.php` - Advanced MRP algorithms

#### 7. Production Scheduling (`/public/production/`)
**Status:** 🔄 85% Complete

**Features Implemented:**
- Production order creation from customer orders
- Work center capacity management
- Forward/backward scheduling
- Gantt chart visualization
- Operation tracking
- Material reservations
- Status history tracking

**Features Missing:**
- Production reporting dashboard
- Shift management enhancements
- Performance analytics

**Key Files:**
- `index.php` - Production dashboard
- `create.php` - Create production orders
- `gantt.php` - Visual scheduling timeline
- `operations.php` - Operation tracking
- `update-status.php` - Status management API

**Database Tables:**
- `production_orders` - Manufacturing orders
- `production_order_operations` - Operation details
- `production_order_materials` - Material allocations
- `work_centers` - Machine/station definitions
- `work_center_calendar` - Capacity schedules
- `production_routes` - Manufacturing sequences

**Core Classes:**
- `ProductionScheduler.php` - Scheduling algorithms

#### 8. Master Production Schedule (`/public/mps/`)
**Status:** 🔄 60% Complete

**Features:**
- Basic MPS framework
- Integration points defined

**Missing:**
- Full implementation
- Capacity planning integration
- Demand forecasting

### Database Architecture

#### Key Relationships
```sql
materials → bom_details → bom_headers → products
products → customer_order_items → customer_orders
customer_orders → production_orders → production_order_operations
production_orders → production_order_materials → inventory
work_centers → production_order_operations
```

#### Critical Views
- `v_work_center_capacity` - Capacity analysis
- `v_production_schedule` - Schedule overview
- `v_inventory_status` - Stock levels with reorder points

### API Endpoints

#### Autocomplete APIs
- `/api/materials-search.php` - Material search
- `/api/categories-search.php` - Category lookup
- `/api/uom-search.php` - Units of measure
- `/api/suppliers-search.php` - Supplier search
- `/api/locations-search.php` - Warehouse lookup
- `/products/search-api.php` - Product search

#### Action APIs
- `/production/update-status.php` - Production status updates
- `/production/get-order-details.php` - Order information

### UI/UX Patterns

#### Design Principles
1. **Mobile-First**: All interfaces responsive
2. **Touch-Friendly**: 44px minimum touch targets
3. **Progressive Enhancement**: Works without JavaScript
4. **Consistent Navigation**: Standard header/footer
5. **Contextual Help**: Tooltips and inline guidance

#### Common Components
- **AutocompleteManager**: Centralized search functionality
- **Entity Edit Links**: Quick navigation with edit icons
- **Status Badges**: Visual status indicators
- **Data Tables**: Sortable, filterable lists
- **Form Validation**: Client and server-side

### Testing Procedures

#### Phase 1 Testing (Complete)
- ✅ Material CRUD operations
- ✅ BOM explosion accuracy
- ✅ MRP calculations with multi-level BOMs
- ✅ Inventory transaction integrity
- ✅ Order processing workflow

#### Phase 2 Testing (In Progress)
- ✅ Production order creation
- ✅ Scheduling algorithm accuracy
- ✅ Gantt chart visualization
- 🔄 Capacity conflict resolution
- 🔄 Performance under load

### Known Issues & Incomplete Areas

#### Critical Issues
1. **Database Not Initialized**: Run `mysql -u root -p mrp_erp < database/schema.sql`
2. **No Authentication**: System lacks user login/permissions

#### Incomplete Features
1. **Production Reporting**: Analytics dashboard needed
2. **Purchase Orders**: No automated PO generation
3. **Financial Integration**: Cost tracking not implemented
4. **Quality Control**: QC workflows missing
5. **User Management**: No role-based access control

#### Technical Debt
- Test files in production directory
- Some legacy autocomplete implementations
- Limited error logging
- No automated testing framework

### Development Priorities

#### Immediate (This Week)
1. Import database schema
2. Complete production reporting module
3. Clean up test files
4. Document user workflows

#### Short-term (2-4 Weeks)
1. Implement basic authentication
2. Add purchase order automation
3. Enhance shift management
4. Create user training materials

#### Medium-term (1-3 Months)
1. Financial integration
2. Quality control module
3. Advanced reporting
4. API documentation
5. External system integration

### Development Guidelines

#### When Adding New Features
1. Check existing patterns in similar modules
2. Use AutocompleteManager for search fields
3. Maintain mobile responsiveness
4. Include database migrations
5. Update this manual

#### Code Standards
- PHP 7.4+ features allowed
- Prepared statements for all queries
- Follow existing file structure
- Comment complex logic
- Use meaningful variable names

#### Database Changes
1. Add migrations to `/database/migrations/`
2. Update `/database/schema.sql`
3. Document new tables here
4. Maintain referential integrity
5. Index foreign keys

### Version History
- **v1.0** - Phase 1 Core MRP Complete
- **v1.5** - Phase 2 Production Scheduling 85% Complete (Current)
- **v2.0** - Phase 2 Complete (Planned)
- **v3.0** - Phase 3 Purchasing & ERP (Future)

---

## Documentation Maintenance System

### Purpose
Ensures all user documentation remains accurate and up-to-date with system changes.

### Documentation Files to Maintain
1. **USER_GUIDE.md** - Comprehensive user manual
2. **QUICK_REFERENCE.md** - Printable quick reference card
3. **CLAUDE.md User Operations Manual** - Integrated guidance
4. **includes/help-system.php** - In-app contextual help

### Maintenance Triggers

#### **Immediate Update Required**
- ✅ New page/feature added
- ✅ Navigation structure changes
- ✅ Status codes or workflows modified
- ✅ Database schema changes affecting user experience
- ✅ User interface changes

#### **Documentation Review Schedule**
- **After each feature completion** - Update relevant sections
- **Phase completion** - Comprehensive documentation review
- **Monthly** - Accuracy check against codebase
- **When users report documentation issues** - Priority fix

#### **Automated Review Agent**
Run this agent task when needed:
```
Task: Review MRP/ERP system documentation for accuracy
Agent: general-purpose
Trigger: After major changes or monthly
```

### Documentation Update Checklist

#### When Adding New Features:
- [ ] Update file structure in System Development Manual
- [ ] Add feature to appropriate USER_GUIDE.md section
- [ ] Update navigation paths in QUICK_REFERENCE.md
- [ ] Add help content to help-system.php
- [ ] Update status codes if changed
- [ ] Test all documented workflows
- [ ] Update version stamps

#### When Modifying Existing Features:
- [ ] Review affected USER_GUIDE.md sections
- [ ] Update workflows in QUICK_REFERENCE.md
- [ ] Modify help-system.php tooltips/content
- [ ] Check navigation consistency
- [ ] Verify status codes still accurate
- [ ] Update troubleshooting guides

#### When Removing Features:
- [ ] Remove from all documentation files
- [ ] Update navigation references
- [ ] Remove from help system
- [ ] Update workflows that reference removed feature
- [ ] Add to "Known Limitations" if users might expect it

### Quality Assurance Process

#### Documentation Testing
1. **Navigation Test**: Follow each documented path in actual system
2. **Workflow Test**: Execute each step-by-step guide
3. **Status Code Test**: Verify all status codes match system
4. **Help System Test**: Check all tooltips and help panels
5. **Quick Reference Test**: Validate all quick paths work

#### User Feedback Integration
- Monitor for documentation-related support requests
- Track common user confusion points  
- Update based on real usage patterns
- Incorporate feedback into next review cycle

### Documentation Standards

#### Writing Standards
- **User perspective**: Write from user's viewpoint, not technical
- **Action-oriented**: Focus on "how to do" rather than "what is"
- **Consistent terminology**: Use same terms throughout
- **Visual hierarchy**: Use consistent heading structure
- **Mobile-friendly**: Consider tablet/phone users

#### Update Standards
- **Version stamps**: Update last-modified dates
- **Change logs**: Document what changed
- **Cross-references**: Update related sections
- **Completeness**: Don't leave partial updates

### File-Specific Guidelines

#### **USER_GUIDE.md**
- Complete section rewrite when major feature changes
- Update table of contents if new sections added
- Maintain step-by-step format
- Include troubleshooting for new features
- Keep examples current with system

#### **QUICK_REFERENCE.md**
- Update navigation table for any menu changes
- Verify all keyboard shortcuts work
- Update status codes immediately when changed
- Keep workflows concise and accurate
- Test printability after changes

#### **help-system.php**
- Add new contexts for new pages
- Update field tooltips when forms change
- Review help panel content for accuracy
- Test all help interactions
- Update workflow guides for new features

#### **CLAUDE.md User Operations Manual**
- Update daily workflows when process changes
- Modify color codes/symbols if system changes
- Update troubleshooting with new common issues
- Keep getting help section current

### Version Control Integration

#### Git Hooks for Documentation
Consider adding git pre-commit hook reminder:
```bash
echo "📝 Documentation Checklist:"
echo "[ ] Updated relevant user documentation?"
echo "[ ] Tested documented workflows?"
echo "[ ] Updated status codes if changed?"
echo "[ ] Added help content for new features?"
```

#### Commit Message Standards
- Include documentation changes in commit messages
- Tag documentation-only commits clearly
- Reference documentation updates in feature commits

### Maintenance History

#### Recent Updates (August 2025)
- ✅ Fixed status codes in QUICK_REFERENCE.md (abbreviated → full words)
- ✅ Removed references to non-existent pages (results.php, orders/view.php)
- ✅ Updated MRP navigation paths (Calculate → Run MRP)
- ✅ Fixed inventory operations documentation (adjust → receive/issue)
- ✅ Added missing MPS functionality documentation
- ✅ Established maintenance process and standards
- ✅ **CRITICAL FIXES (Agent-Identified Issues):**
  - Added MPS to main navigation menu
  - Created production order view page (production/view.php)
  - Created MRP results page (mrp/results.php)
  - Fixed MPS database setup issue (planning tables required)
  - Enhanced help system with MPS context

#### Ongoing Maintenance Tasks
- [ ] Complete Phase 2 production features in USER_GUIDE.md
- [ ] Add in-app help to all major pages
- [ ] Create video tutorials for complex workflows
- [ ] Develop role-based documentation when authentication added

### Success Metrics
- User support requests about "how to" decrease
- New users can navigate system without assistance
- Documentation accuracy verified through testing
- User feedback indicates documentation helpfulness

This maintenance system ensures documentation evolves with the system and remains a valuable resource for users.

---

## User Operations Manual

### Purpose
This manual helps you (the human operator) understand how to use the MRP/ERP system for daily operations. It focuses on practical workflows rather than technical details.

### Quick Start - Processing a Customer Order

#### Complete Order-to-Production Workflow
1. **Create Customer Order**
   - Navigate to **Orders** → **New Order**
   - Use autocomplete to search for product
   - Enter quantity and due date
   - Click Save

2. **Check Material Requirements**
   - Go to **MRP** → **Calculate**
   - System shows all required materials
   - Red items = shortages that need attention
   - Green items = sufficient inventory

3. **Create Production Order**
   - Go to **Production** → **Create New**
   - Select the customer order from dropdown
   - System auto-schedules based on capacity
   - Review the suggested schedule
   - Click Create Production Order

4. **Monitor Production**
   - **Production** → **Gantt Chart** for visual timeline
   - **Production** → **Operations** for detailed tracking
   - Update operation status as work progresses
   - Mark complete when finished

### Common Daily Tasks

#### "How do I check current inventory?"
- **Inventory** → **Overview** shows all stock levels
- Yellow warning = below reorder point
- Red alert = below safety stock
- Click material name to see details

#### "How do I add a new product?"
1. **Products** → **Add New Product**
2. Fill in part number, name, category
3. Set safety stock levels
4. Save product
5. Go to **BOM** → **Create BOM** to define components

#### "How do I see what's in production?"
- **Production** → **Dashboard** - List view with statuses
- **Production** → **Gantt Chart** - Visual timeline
- **Dashboard** (home) - Quick metrics overview

#### "How do I handle material shortages?"
1. Run **MRP** → **Calculate**
2. Review shortage report
3. Either:
   - Adjust inventory if materials arrived
   - Create purchase order (Phase 3)
   - Reschedule production if needed

### Navigation Guide

```
Home Dashboard
├── Materials Management
│   ├── View All Materials
│   ├── Add New Material
│   └── Categories & Suppliers
├── Products
│   ├── Product List
│   ├── Add Product
│   └── Product Search
├── Bill of Materials (BOM)
│   ├── BOM List
│   ├── Create BOM
│   └── View BOM Tree
├── Inventory
│   ├── Current Stock
│   ├── Adjust Stock
│   ├── Transaction History
│   └── Lot Tracking
├── Customer Orders
│   ├── Order List
│   ├── New Order
│   └── Order Status
├── MRP
│   ├── Run Calculation
│   ├── Shortage Report
│   └── Requirements Planning
└── Production
    ├── Production Orders
    ├── Create from Order
    ├── Gantt Chart
    └── Operations Tracking
```

### Understanding the Interface

#### Color Codes
- **Green** = Good/Complete/Sufficient
- **Yellow** = Warning/Attention Needed
- **Red** = Critical/Shortage/Overdue
- **Blue** = Information/In Progress
- **Gray** = Inactive/Cancelled

#### Icons and Symbols
- ✏️ = Click to edit
- 🔍 = Search/Filter
- ➕ = Add new item
- 📊 = View report
- 📅 = Schedule/Calendar
- ⚠️ = Warning/Alert

#### Form Fields
- **Red asterisk (*)** = Required field
- **Autocomplete fields** = Start typing to search
- **Date fields** = Click for calendar picker
- **Number fields** = Use +/- for adjustments

### Tips for Efficient Use

#### Data Entry
1. Use TAB key to move between fields
2. Autocomplete accepts partial matches
3. Dates can be typed (MM/DD/YYYY) or selected
4. Save frequently when entering multiple items

#### Search and Filter
- Most lists have search boxes at top
- Partial text matching works
- Use filters to narrow results
- Sort columns by clicking headers

#### Mobile/Tablet Usage
- All pages are touch-friendly
- Swipe tables horizontally to see all columns
- Landscape orientation shows more data
- Pinch to zoom if needed

### Troubleshooting Guide

#### "Autocomplete isn't showing results"
- Type at least 2 characters
- Check if item exists in system
- Try refreshing the page (F5)
- Clear browser cache if persistent

#### "Can't create production order"
- Verify customer order exists
- Check product has BOM defined
- Ensure work centers are configured
- Verify materials are available

#### "Data seems missing"
- Check active filters
- Look for status filters (active/completed)
- Try "Show All" option
- Check date range if applicable

#### "Page loads slowly"
- Check internet connection
- Clear browser cache
- Try different browser
- Contact IT if persistent

### Workflow Scenarios

#### Morning Routine
1. Check **Dashboard** for alerts
2. Review **Production** → **Gantt Chart**
3. Check **Inventory** for low stock warnings
4. Process new **Customer Orders**
5. Run **MRP** if new orders added

#### End of Day
1. Update **Production** → **Operations** statuses
2. Complete any inventory adjustments
3. Review tomorrow's production schedule
4. Check for any critical alerts

#### Weekly Planning
1. Run comprehensive **MRP** analysis
2. Review all **Customer Orders** due dates
3. Check **Inventory** reorder points
4. Plan production schedule for week
5. Identify material purchase needs

### Getting Help

#### In-Application Help
- Look for (?) icons for contextual help
- Hover over fields for tooltips
- Check page headers for instructions

#### Documentation
- This manual in CLAUDE.md
- Detailed USER_GUIDE.md
- Technical documentation in System Development Manual section

#### Support Process
1. Check this manual first
2. Try the troubleshooting guide
3. Ask colleagues who use the system
4. Contact system administrator
5. Report bugs/issues for fixes