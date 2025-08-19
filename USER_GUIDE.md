# MRP/ERP System User Guide

## Welcome!

This guide will help you learn how to use the MRP/ERP system for your daily manufacturing operations. It's written for users who need to manage materials, products, orders, and production scheduling.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Basic Navigation](#basic-navigation)
3. [Managing Materials](#managing-materials)
4. [Managing Products](#managing-products)
5. [Bill of Materials (BOM)](#bill-of-materials-bom)
6. [Inventory Management](#inventory-management)
7. [Customer Orders](#customer-orders)
8. [MRP Calculations](#mrp-calculations)
9. [Production Scheduling](#production-scheduling)
10. [Master Production Schedule (MPS)](#master-production-schedule-mps)
11. [Daily Workflows](#daily-workflows)
12. [Tips and Tricks](#tips-and-tricks)
13. [Troubleshooting](#troubleshooting)

---

## Getting Started

### First Time Login
1. Open your web browser (Chrome, Firefox, Safari, or Edge)
2. Navigate to the MRP system URL
3. You'll see the main dashboard

### Understanding the Dashboard
The dashboard shows key metrics at a glance:
- **Total Products**: Number of finished goods in system
- **Total Materials**: Raw materials and components
- **Low Stock Alerts**: Items below reorder point
- **Active Orders**: Customer orders in progress
- **Production Orders**: Manufacturing orders active

**Color meanings:**
- 🟢 Green = Good/Normal
- 🟡 Yellow = Warning/Attention
- 🔴 Red = Critical/Action Required

---

## Basic Navigation

### Main Menu Structure
The system is organized into logical sections:

```
🏠 Dashboard (Home)
📦 Materials (Raw materials, components)
🏭 Products (Finished goods)
📋 BOM (Bill of Materials - recipes)
📊 Inventory (Stock levels)
📝 Orders (Customer orders)
🔧 MRP (Material planning)
⚙️ Production (Shop floor scheduling)
```

### Using the Navigation
- Click any menu item to access that section
- Use browser back button to return
- Dashboard link always returns home
- Each section has sub-pages for different tasks

---

## Managing Materials

### What are Materials?
Materials are the raw materials and components used to make products:
- Raw materials (plastic resin, metal sheets)
- Components (screws, labels, inserts)
- Packaging materials (boxes, tape, labels)

### Adding a New Material

1. **Navigate to Materials**
   - Click "Materials" in main menu
   - Click "Add New Material" button

2. **Fill in Material Information**
   ```
   Material Code*: MAT-001 (unique identifier)
   Name*: Plastic Resin Type A
   Description: High-density polyethylene resin
   Category*: Raw Materials (select from dropdown)
   Unit of Measure*: KG (kilograms)
   Unit Cost: 2.50 (cost per unit)
   Supplier: Select from list (optional)
   ```

3. **Set Inventory Parameters**
   ```
   Reorder Point: 100 (when to reorder)
   Reorder Quantity: 500 (how much to order)
   Safety Stock: 50 (minimum to keep)
   Lead Time Days: 7 (delivery time)
   ```

4. **Save the Material**
   - Click "Save Material" button
   - System confirms with green success message

### Viewing/Editing Materials

1. **Find the Material**
   - Go to Materials list
   - Use search box to find quickly
   - Or browse the list

2. **View Details**
   - Click material name to view
   - See all information and current stock

3. **Edit Material**
   - Click "Edit" button on view page
   - Update any fields
   - Save changes

### Tips for Materials
- Use consistent naming conventions
- Set accurate reorder points to avoid stockouts
- Update costs regularly for accurate calculations
- Link suppliers for easy reordering

---

## Managing Products

### What are Products?
Products are the finished goods you manufacture and sell to customers.

### Creating a New Product

1. **Navigate to Products**
   - Click "Products" in menu
   - Click "Add New Product"

2. **Enter Product Details**
   ```
   Part Number*: PROD-001
   Product Name*: Widget Assembly A
   Description: Standard widget with blue casing
   Category*: Finished Goods
   Unit of Measure*: EA (each)
   Unit Price: 25.00
   ```

3. **Set Planning Parameters**
   ```
   Lead Time: 3 (days to manufacture)
   Safety Stock: 20 (minimum inventory)
   Minimum Order Quantity: 10
   ```

4. **Save Product**
   - Click "Create Product"
   - Next: Create BOM to define materials needed

### Product Search
The product search uses autocomplete:
1. Start typing product name or part number
2. Select from dropdown suggestions
3. Press Enter or click to view

---

## Bill of Materials (BOM)

### Understanding BOMs
A BOM is like a recipe - it lists all materials needed to make one product.

### Creating a BOM

1. **Start BOM Creation**
   - Go to BOM → Create BOM
   - Or from Product page → "Create BOM"

2. **Select Product**
   - Use autocomplete to find product
   - System shows product details

3. **Add Materials**
   For each material needed:
   ```
   Material: (use autocomplete to search)
   Quantity: 2.5 (amount needed per product)
   Unit: KG (automatically filled)
   Scrap %: 5 (waste percentage)
   ```
   - Click "Add Material" after each entry

4. **Review and Save**
   - Check all materials are listed
   - Verify quantities are correct
   - Click "Save BOM"

### Viewing BOM Tree
The BOM tree shows the hierarchical structure:
```
Product: Widget A
├── Plastic Casing (2 EA)
├── Circuit Board (1 EA)
│   ├── PCB Substrate (1 EA)
│   └── Components Kit (1 SET)
└── Packaging Box (1 EA)
```

Click ✏️ icons to edit items directly.

### Multi-Level BOMs
Products can contain sub-assemblies:
1. Create sub-assembly as a product
2. Create BOM for sub-assembly
3. Add sub-assembly to main product BOM
4. System calculates all levels automatically

---

## Inventory Management

### Viewing Current Stock

1. **Go to Inventory Overview**
   - Shows all materials and products
   - Color-coded status indicators

2. **Understanding Stock Levels**
   ```
   Green: Stock > Reorder Point (good)
   Yellow: Stock < Reorder Point (order soon)
   Red: Stock < Safety Stock (critical)
   ```

3. **Stock Details**
   Click any item to see:
   - Current quantity
   - Location/warehouse
   - Lot numbers
   - Transaction history

### Adjusting Inventory

1. **Navigate to Inventory Operations**
   - Inventory → Receive Stock (for incoming materials)
   - Inventory → Issue Stock (for outgoing/consumed materials)

2. **Receiving Stock (receive.php)**
   - Add materials to inventory
   - Record lot numbers
   - Update stock levels

3. **Issuing Stock (issue.php)**
   - Remove materials from inventory
   - Record production consumption
   - Track usage by lot

For both operations, enter:
```
Material: (select using autocomplete)
Quantity: Amount to receive/issue
Lot Number: Batch identifier (optional)
Reason: Purpose of transaction
```

The system creates transaction records and updates stock levels immediately.

### Lot Tracking
For materials requiring traceability:
1. Enter lot number when receiving
2. System tracks lot through production
3. Can trace lot in finished products
4. Useful for recalls or quality issues

---

## Customer Orders

### Creating a Customer Order

1. **Navigate to Orders**
   - Orders → New Order

2. **Enter Order Information**
   ```
   Customer: Select or enter new
   Order Number: CO-2024-001
   Order Date: (defaults to today)
   Due Date: (when customer needs it)
   ```

3. **Add Products**
   ```
   Product: (use autocomplete)
   Quantity: 100
   Unit Price: (auto-fills from product)
   ```
   - Click "Add Item" for multiple products

4. **Save Order**
   - System checks feasibility
   - Shows estimated completion date

### Order Statuses
- **Pending**: New order, not started
- **In Production**: Being manufactured
- **Completed**: Ready to ship
- **Shipped**: Delivered to customer
- **Cancelled**: Order cancelled

### Managing Orders

1. **View All Orders**
   - Orders → Order List
   - Filter by status, date, customer

2. **Update Order Status**
   - Click order to view
   - Click "Update Status"
   - Select new status
   - Add notes if needed

---

## MRP Calculations

### What is MRP?
Material Requirements Planning calculates what materials you need and when.

### Running MRP Analysis

1. **Navigate to MRP**
   - MRP → Run MRP (basic analysis)
   - Or MRP → Enhanced MRP (time-phased planning)

2. **Basic MRP (run.php)**
   - Select calculation method
   - Click "Run MRP Calculation"
   - View results on same page

3. **Enhanced MRP (run-enhanced.php)**
   - Set planning horizon
   - Choose parameters
   - Get detailed time-phased requirements

4. **Review Results**
   The MRP shows:
   ```
   Material | Required | Available | Shortage | Order By
   --------|----------|-----------|----------|----------
   Resin   | 500 KG   | 200 KG    | 300 KG   | Jan 15
   Screws  | 1000 EA  | 1500 EA   | OK       | -
   ```

### Understanding MRP Results

**Shortage Report**
- Red items: Order immediately
- Yellow items: Order soon
- Green items: Sufficient stock

**Time-Phased Planning**
Shows when materials are needed:
```
Week 1: Need 100 KG resin
Week 2: Need 150 KG resin
Week 3: Need 200 KG resin
```

### Taking Action on MRP
1. Review all shortages
2. Create purchase orders (Phase 3)
3. Or adjust production schedule
4. Or expedite existing orders

---

## Production Scheduling

### Creating Production Orders

1. **From Customer Order**
   - Go to Production → Create from Order
   - Select customer order
   - System suggests schedule

2. **Review Schedule**
   ```
   Start Date: Jan 10, 8:00 AM
   End Date: Jan 12, 3:00 PM
   Work Center: Assembly Line 1
   Quantity: 100 units
   ```

3. **Confirm Production Order**
   - Check material availability
   - Verify work center capacity
   - Click "Create Production Order"

### Using the Gantt Chart

The Gantt chart shows visual timeline:
1. **Navigate to Gantt Chart**
   - Production → Gantt Chart

2. **Understanding the View**
   ```
   Horizontal axis: Time (days/hours)
   Vertical axis: Work centers
   Bars: Production orders
   Colors: Order status
   ```

3. **Interacting with Chart**
   - Hover for details
   - Click to view order
   - Drag to reschedule (if enabled)

### Managing Operations

1. **View Operations**
   - Production → Operations
   - Shows all production steps

2. **Update Operation Status**
   ```
   Setup: Mark when starting setup
   In Progress: Mark when producing
   Complete: Mark when finished
   ```

3. **Record Production Data**
   ```
   Quantity Completed: 95
   Quantity Scrapped: 5
   Actual Time: 4.5 hours
   Notes: Machine adjustment needed
   ```

### Production Statuses
- **Planned**: Scheduled but not started
- **Released**: Ready to start
- **In Progress**: Currently producing
- **Completed**: Production finished

---

## Master Production Schedule (MPS)

### What is MPS?
The Master Production Schedule is a plan for producing finished goods over time. It shows what quantities of each product to manufacture in each planning period.

### Using MPS

1. **Navigate to MPS**
   - Go to MPS → Master Production Schedule

2. **Understanding the MPS Grid**
   The MPS shows:
   ```
   Product | Safety Stock | Lead Time | Week 1 | Week 2 | Week 3 | ...
   Widget A | 50 | 3 days | 100 | 150 | 200 |
   Widget B | 25 | 2 days | 75 | 100 | 125 |
   ```

3. **Planning Production Quantities**
   - Enter planned production for each period
   - Consider customer orders and forecasts
   - Balance capacity and demand
   - Account for safety stock requirements

4. **Save and Execute**
   - Click "Save MPS" to store plan
   - Use "Run Enhanced MRP" to calculate material requirements
   - MPS drives the time-phased MRP calculations

### MPS Best Practices

1. **Plan Realistically**
   - Consider work center capacity
   - Account for material availability
   - Include setup and changeover times

2. **Regular Updates**
   - Review weekly or when orders change
   - Adjust for actual production results
   - Update based on demand changes

3. **Integration with MRP**
   - MPS quantities drive material requirements
   - Use Enhanced MRP with MPS data
   - Review shortage reports after MPS changes

### Troubleshooting MPS

**"No planning periods found"** or **"MPS Setup Required"**
- Run the planning tables setup: `mysql -u root -p mrp_erp < database/create_planning_tables.sql`
- This creates planning calendar and MPS tables
- Refresh the page after running the script

**"No products found"**
- Create products first
- Each product needs basic information
- Products drive MPS planning

**"MPS data not saving"**
- Check all required fields
- Verify planning periods exist
- Try refreshing and re-entering

---

## Daily Workflows

### Morning Startup Routine

1. **Check Dashboard (5 minutes)**
   - Review any overnight alerts
   - Check low stock warnings
   - Note any overdue orders

2. **Review Production Schedule (10 minutes)**
   - Open Gantt chart
   - Check today's production orders
   - Verify material availability
   - Confirm work center readiness

3. **Process New Orders (15 minutes)**
   - Check for new customer orders
   - Enter into system
   - Run quick MRP check

4. **Update Yesterday's Production (10 minutes)**
   - Close completed operations
   - Record actual quantities
   - Note any issues

### Hourly Production Updates

Every 2-4 hours:
1. Check production progress
2. Update operation statuses
3. Record completed quantities
4. Note any delays or issues

### End of Day Process

1. **Final Production Updates (10 minutes)**
   - Update all operation statuses
   - Record final quantities
   - Add notes for next shift

2. **Inventory Adjustments (10 minutes)**
   - Record any material usage
   - Adjust for any discrepancies
   - Update lot numbers if needed

3. **Tomorrow's Preparation (10 minutes)**
   - Review tomorrow's schedule
   - Check material availability
   - Flag any potential issues
   - Leave notes for morning shift

### Weekly Planning Session

Every Monday morning:
1. **Run Full MRP (15 minutes)**
   - Include all open orders
   - Review shortage report
   - Plan material orders

2. **Review Order Pipeline (20 minutes)**
   - Check all customer due dates
   - Identify scheduling conflicts
   - Prioritize if needed

3. **Capacity Planning (20 minutes)**
   - Review Gantt for full week
   - Balance work center loads
   - Plan overtime if needed

4. **Inventory Review (15 minutes)**
   - Check slow-moving items
   - Review reorder points
   - Plan cycle counts

---

## Tips and Tricks

### Keyboard Shortcuts
- **Tab**: Move to next field
- **Shift+Tab**: Move to previous field
- **Enter**: Submit form
- **Esc**: Cancel autocomplete
- **Ctrl+F**: Search on page

### Autocomplete Tips
1. Type at least 2 characters
2. Use arrow keys to navigate
3. Press Tab to select
4. Type partial names to filter
5. Include codes for exact match

### Data Entry Best Practices
1. **Be Consistent**
   - Use standard naming conventions
   - Follow established patterns
   - Keep descriptions clear

2. **Be Accurate**
   - Double-check quantities
   - Verify part numbers
   - Confirm dates

3. **Be Complete**
   - Fill all required fields
   - Add notes when helpful
   - Update status promptly

### Search Techniques
1. **Use Wildcards**
   - Search "PROD*" finds all starting with PROD
   - Search "*001" finds all ending with 001

2. **Filter Combinations**
   - Combine status and date filters
   - Use category filters to narrow results

3. **Save Common Searches**
   - Bookmark filtered pages
   - Note common search terms

### Mobile Usage Tips
1. **Orientation**
   - Use landscape for tables
   - Use portrait for forms

2. **Touch Targets**
   - Tap center of buttons
   - Use zoom if needed
   - Pull down to refresh

3. **Data Entry**
   - Use device keyboard settings
   - Enable autocorrect carefully
   - Save frequently

---

## Troubleshooting

### Common Issues and Solutions

#### System Issues

**"Page not loading"**
1. Check internet connection
2. Try refreshing (F5)
3. Clear browser cache
4. Try different browser
5. Contact IT support

**"Can't log in"** (When implemented)
1. Check username spelling
2. Verify caps lock is off
3. Reset password if needed
4. Contact administrator

**"Data not saving"**
1. Check all required fields filled
2. Look for error messages
3. Try saving again
4. Refresh and re-enter

#### Data Issues

**"Can't find item in search"**
1. Check spelling
2. Try partial search
3. Verify item exists
4. Try different search terms
5. Browse full list

**"Wrong calculations"**
1. Check BOM quantities
2. Verify unit of measure
3. Review safety stock settings
4. Recalculate MRP

**"Duplicate entries"**
1. Search before creating new
2. Check for typos in codes
3. Merge if necessary
4. Update references

#### Production Issues

**"Can't create production order"**
- Check: Customer order exists?
- Check: Product has BOM?
- Check: Materials available?
- Check: Work center configured?

**"Schedule conflicts"**
1. Review Gantt chart
2. Check work center capacity
3. Adjust priorities
4. Consider overtime
5. Split orders if needed

**"Material shortages"**
1. Run MRP calculation
2. Check inventory accuracy
3. Expedite purchases
4. Consider substitutes
5. Adjust schedule

### Error Messages

**"Required field missing"**
- Look for red asterisks (*)
- Fill all required fields
- Check date formats

**"Duplicate entry"**
- Item already exists
- Use different code
- Or edit existing item

**"Insufficient inventory"**
- Not enough materials
- Check stock levels
- Adjust quantity
- Or add inventory first

**"Invalid date"**
- Use MM/DD/YYYY format
- Check date is realistic
- Can't be in past for orders

### Getting Additional Help

1. **Check Documentation**
   - This user guide
   - In-app help icons
   - System messages

2. **Ask Colleagues**
   - Other system users
   - Shift supervisors
   - IT support team

3. **Report Issues**
   - Document error message
   - Note steps to reproduce
   - Include screenshots
   - Submit to IT

4. **Request Training**
   - For new features
   - For new employees
   - For advanced functions

---

## Appendix

### Glossary of Terms

**BOM (Bill of Materials)**: List of all materials needed to make a product

**Lead Time**: Time required to obtain materials or produce products

**MRP (Material Requirements Planning)**: System for calculating material needs

**Reorder Point**: Inventory level that triggers a new order

**Safety Stock**: Minimum inventory kept as buffer

**SKU**: Stock Keeping Unit - unique identifier for items

**UOM (Unit of Measure)**: How quantities are measured (EA, KG, M, etc.)

**Work in Progress**: Products currently being manufactured

### Common Units of Measure

- **EA** - Each (individual items)
- **KG** - Kilograms (weight)
- **LB** - Pounds (weight)
- **M** - Meters (length)
- **FT** - Feet (length)
- **L** - Liters (volume)
- **GAL** - Gallons (volume)
- **BOX** - Box (packaging)
- **SET** - Set (group of items)

### Status Codes

**Order Statuses**
- pending - Pending
- confirmed - Confirmed
- in_production - In Production  
- completed - Completed
- shipped - Shipped
- cancelled - Cancelled

**Production Statuses**
- planned - Planned
- released - Released
- in_progress - Work in Progress
- completed - Completed
- on_hold - On Hold
- cancelled - Cancelled

### Quick Reference Card

**Essential Daily Tasks**
- [ ] Check dashboard alerts
- [ ] Review production schedule
- [ ] Update operation statuses
- [ ] Process new orders
- [ ] Run MRP if needed
- [ ] Adjust inventory
- [ ] Plan tomorrow

**Weekly Tasks**
- [ ] Full MRP analysis
- [ ] Review all due dates
- [ ] Check reorder points
- [ ] Balance capacity
- [ ] Update forecasts

**Monthly Tasks**
- [ ] Cycle counts
- [ ] Cost updates
- [ ] Performance review
- [ ] Clean old data
- [ ] System maintenance

---

## Version Information

**Document Version**: 1.0
**System Version**: 1.5 (Phase 2 - Production Scheduling)
**Last Updated**: August 2025
**Next Review**: September 2025

---

*End of User Guide*