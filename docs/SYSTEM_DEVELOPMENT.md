# System Development Manual

## Purpose
This manual documents the current state and workflow of the MRP/ERP system to:
1. **Familiarization** - Provide clear understanding of how the application operates
2. **Continuation** - Identify incomplete areas for systematic development
3. **Consistency** - Maintain structured development tracking

## System Workflow Overview

### Core Business Flow
```
1. Materials Management → 2. Products → 3. BOMs → 4. Inventory → 5. Customer Orders → 6. MRP Calculation → 7. Production Scheduling → 8. Execution
```

### Module Interactions
- **Materials** feed into **BOMs** and **Inventory**
- **Products** use **BOMs** to define component requirements
- **Customer Orders** trigger **MRP calculations**
- **MRP** identifies shortages and suggests **Production Orders**
- **Production Scheduling** allocates **Work Centers** and manages capacity
- **Inventory** is consumed by production and replenished by purchasing

## Module-by-Module Documentation

### 1. Materials Management (`/public/materials/`)
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

### 2. Products Management (`/public/products/`)
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

### 3. Bill of Materials (`/public/bom/`)
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

### 4. Inventory Management (`/public/inventory/`)
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

### 5. Customer Orders (`/public/orders/`)
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

### 6. MRP Engine (`/public/mrp/`)
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

### 7. Production Scheduling (`/public/production/`)
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

### 8. Master Production Schedule (`/public/mps/`)
**Status:** 🔄 60% Complete

**Features:**
- Basic MPS framework
- Integration points defined

**Missing:**
- Full implementation
- Capacity planning integration
- Demand forecasting

## Database Architecture

### Key Relationships
```sql
materials → bom_details → bom_headers → products
products → customer_order_items → customer_orders
customer_orders → production_orders → production_order_operations
production_orders → production_order_materials → inventory
work_centers → production_order_operations
```

### Critical Views
- `v_work_center_capacity` - Capacity analysis
- `v_production_schedule` - Schedule overview
- `v_inventory_status` - Stock levels with reorder points

## API Endpoints

### Autocomplete APIs
- `/api/materials-search.php` - Material search
- `/api/categories-search.php` - Category lookup
- `/api/uom-search.php` - Units of measure
- `/api/suppliers-search.php` - Supplier search
- `/api/locations-search.php` - Warehouse lookup
- `/products/search-api.php` - Product search

### Action APIs
- `/production/update-status.php` - Production status updates
- `/production/get-order-details.php` - Order information

## UI/UX Patterns

### Design Principles
1. **Mobile-First**: All interfaces responsive
2. **Touch-Friendly**: 44px minimum touch targets
3. **Progressive Enhancement**: Works without JavaScript
4. **Consistent Navigation**: Standard header/footer
5. **Contextual Help**: Tooltips and inline guidance

### Common Components
- **AutocompleteManager**: Centralized search functionality
- **Entity Edit Links**: Quick navigation with edit icons
- **Status Badges**: Visual status indicators
- **Data Tables**: Sortable, filterable lists
- **Form Validation**: Client and server-side

## Testing Procedures

### Phase 1 Testing (Complete)
- ✅ Material CRUD operations
- ✅ BOM explosion accuracy
- ✅ MRP calculations with multi-level BOMs
- ✅ Inventory transaction integrity
- ✅ Order processing workflow

### Phase 2 Testing (In Progress)
- ✅ Production order creation
- ✅ Scheduling algorithm accuracy
- ✅ Gantt chart visualization
- 🔄 Capacity conflict resolution
- 🔄 Performance under load

## Known Issues & Incomplete Areas

### Critical Issues
1. **Database Not Initialized**: Run `mysql -u root -p mrp_erp < database/schema.sql`
2. **No Authentication**: System lacks user login/permissions

### Incomplete Features
1. **Production Reporting**: Analytics dashboard needed
2. **Purchase Orders**: No automated PO generation
3. **Financial Integration**: Cost tracking not implemented
4. **Quality Control**: QC workflows missing
5. **User Management**: No role-based access control

### Technical Debt
- Test files in production directory
- Some legacy autocomplete implementations
- Limited error logging
- No automated testing framework

## Development Priorities

### Immediate (This Week)
1. Import database schema
2. Complete production reporting module
3. Clean up test files
4. Document user workflows

### Short-term (2-4 Weeks)
1. Implement basic authentication
2. Add purchase order automation
3. Enhance shift management
4. Create user training materials

### Medium-term (1-3 Months)
1. Financial integration
2. Quality control module
3. Advanced reporting
4. API documentation
5. External system integration

## Development Guidelines

### When Adding New Features
1. Check existing patterns in similar modules
2. Use AutocompleteManager for search fields
3. Maintain mobile responsiveness
4. Include database migrations
5. Update this manual

### Code Standards
- PHP 7.4+ features allowed
- Prepared statements for all queries
- Follow existing file structure
- Comment complex logic
- Use meaningful variable names

### Database Changes
1. Add migrations to `/database/migrations/`
2. Update `/database/schema.sql`
3. Document new tables here
4. Maintain referential integrity
5. Index foreign keys

## Version History
- **v1.0** - Phase 1 Core MRP Complete
- **v1.5** - Phase 2 Production Scheduling 85% Complete (Current)
- **v2.0** - Phase 2 Complete (Planned)
- **v3.0** - Phase 3 Purchasing & ERP (Future)