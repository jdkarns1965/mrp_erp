# User Operations Manual

## Purpose
This manual helps you (the human operator) understand how to use the MRP/ERP system for daily operations. It focuses on practical workflows rather than technical details.

## Quick Start - Processing a Customer Order

### Complete Order-to-Production Workflow
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

## Common Daily Tasks

### "How do I check current inventory?"
- **Inventory** → **Overview** shows all stock levels
- Yellow warning = below reorder point
- Red alert = below safety stock
- Click material name to see details

### "How do I add a new product?"
1. **Products** → **Add New Product**
2. Fill in part number, name, category
3. Set safety stock levels
4. Save product
5. Go to **BOM** → **Create BOM** to define components

### "How do I see what's in production?"
- **Production** → **Dashboard** - List view with statuses
- **Production** → **Gantt Chart** - Visual timeline
- **Dashboard** (home) - Quick metrics overview

### "How do I handle material shortages?"
1. Run **MRP** → **Calculate**
2. Review shortage report
3. Either:
   - Adjust inventory if materials arrived
   - Create purchase order (Phase 3)
   - Reschedule production if needed

## Navigation Guide

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

## Understanding the Interface

### Color Codes
- **Green** = Good/Complete/Sufficient
- **Yellow** = Warning/Attention Needed
- **Red** = Critical/Shortage/Overdue
- **Blue** = Information/In Progress
- **Gray** = Inactive/Cancelled

### Icons and Symbols
- ✏️ = Click to edit
- 🔍 = Search/Filter
- ➕ = Add new item
- 📊 = View report
- 📅 = Schedule/Calendar
- ⚠️ = Warning/Alert

### Form Fields
- **Red asterisk (*)** = Required field
- **Autocomplete fields** = Start typing to search
- **Date fields** = Click for calendar picker
- **Number fields** = Use +/- for adjustments

## Tips for Efficient Use

### Data Entry
1. Use TAB key to move between fields
2. Autocomplete accepts partial matches
3. Dates can be typed (MM/DD/YYYY) or selected
4. Save frequently when entering multiple items

### Search and Filter
- Most lists have search boxes at top
- Partial text matching works
- Use filters to narrow results
- Sort columns by clicking headers

### Mobile/Tablet Usage
- All pages are touch-friendly
- Swipe tables horizontally to see all columns
- Landscape orientation shows more data
- Pinch to zoom if needed

## Troubleshooting Guide

### "Autocomplete isn't showing results"
- Type at least 2 characters
- Check if item exists in system
- Try refreshing the page (F5)
- Clear browser cache if persistent

### "Can't create production order"
- Verify customer order exists
- Check product has BOM defined
- Ensure work centers are configured
- Verify materials are available

### "Data seems missing"
- Check active filters
- Look for status filters (active/completed)
- Try "Show All" option
- Check date range if applicable

### "Page loads slowly"
- Check internet connection
- Clear browser cache
- Try different browser
- Contact IT if persistent

## Workflow Scenarios

### Morning Routine
1. Check **Dashboard** for alerts
2. Review **Production** → **Gantt Chart**
3. Check **Inventory** for low stock warnings
4. Process new **Customer Orders**
5. Run **MRP** if new orders added

### End of Day
1. Update **Production** → **Operations** statuses
2. Complete any inventory adjustments
3. Review tomorrow's production schedule
4. Check for any critical alerts

### Weekly Planning
1. Run comprehensive **MRP** analysis
2. Review all **Customer Orders** due dates
3. Check **Inventory** reorder points
4. Plan production schedule for week
5. Identify material purchase needs

## Getting Help

### In-Application Help
- Look for (?) icons for contextual help
- Hover over fields for tooltips
- Check page headers for instructions

### Documentation
- This manual in CLAUDE.md
- Detailed USER_GUIDE.md
- Technical documentation in System Development Manual section

### Support Process
1. Check this manual first
2. Try the troubleshooting guide
3. Ask colleagues who use the system
4. Contact system administrator
5. Report bugs/issues for fixes