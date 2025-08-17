# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a modular MRP → ERP Manufacturing System built with PHP and MySQL. The project is in early development stages, starting with Material Requirements Planning (MRP) and expanding into full Enterprise Resource Planning (ERP) functionality.

## Current Development Phase

**Phase 1: Core MRP** (In Progress)
- Building database schema for materials, products, BOM, and inventory
- Developing OOP PHP classes for database interaction
- Creating web forms for data entry
- Implementing MRP calculation logic

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

### Key Database Tables (Phase 1)
- `materials` - Raw materials (resins, inserts, packaging)
- `products` - Finished goods with part numbers
- `bom` - Bill of Materials linking products to materials
- `inventory` - Current stock levels with lot tracking
- `production_orders` - Manufacturing orders (Phase 2)
- `purchase_orders` - Supplier orders (Phase 3)

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

### Current (Phase 1): Core MRP
- Focus on accurate BOM explosion
- Reliable inventory tracking
- Clear shortage reporting
- Simple, functional UI

### Upcoming (Phase 2): Scheduling
- Machine capacity planning
- Forward and backward scheduling
- Gantt chart visualization
- Production status tracking

### Future (Phase 3+): Purchasing & ERP
- Automated PO generation
- Supplier management
- Financial integration
- Quality control modules  

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
