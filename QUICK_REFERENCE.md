# MRP/ERP System - Quick Reference Card

## Essential Navigation
| To Do This | Go Here |
|------------|---------|
| View alerts & metrics | Dashboard (Home) |
| Add raw material | Materials → Add New |
| Add finished product | Products → Add New |
| Define product recipe | BOM → Create BOM |
| Check stock levels | Inventory → Overview |
| Enter customer order | Orders → New Order |
| Calculate requirements | MRP → Run MRP |
| Plan production schedule | MPS → Master Production Schedule |
| Schedule production | Production → Create |
| View production timeline | Production → Gantt Chart |

## Daily Tasks Checklist
### Morning (15 minutes)
- [ ] Check Dashboard alerts
- [ ] Review Production Gantt chart
- [ ] Check low stock warnings
- [ ] Process new customer orders
- [ ] Run MRP if orders added

### Throughout Day
- [ ] Update production operation status
- [ ] Record completed quantities
- [ ] Note any production issues
- [ ] Adjust inventory as needed

### End of Day (10 minutes)
- [ ] Final production status updates
- [ ] Complete inventory adjustments
- [ ] Review tomorrow's schedule
- [ ] Leave notes for next shift

## Keyboard Shortcuts
- **Tab** - Next field
- **Shift+Tab** - Previous field  
- **Enter** - Submit form
- **F1** - Toggle help panel
- **Ctrl+F** - Search page
- **Esc** - Cancel autocomplete

## Color Codes
- 🟢 **Green** - Good/Sufficient/Complete
- 🟡 **Yellow** - Warning/Low/Attention
- 🔴 **Red** - Critical/Shortage/Urgent
- 🔵 **Blue** - Information/In Progress
- ⚫ **Gray** - Inactive/Cancelled

## Status Codes
### Orders
- **pending** - Pending
- **confirmed** - Confirmed
- **in_production** - In Production
- **completed** - Completed
- **shipped** - Shipped
- **cancelled** - Cancelled

### Production
- **planned** - Planned
- **released** - Released
- **in_progress** - In Progress
- **completed** - Completed
- **on_hold** - On Hold
- **cancelled** - Cancelled

## Common Workflows

### Process Customer Order
1. Orders → New Order
2. Enter product & quantity
3. MRP → Run MRP
4. Production → Create from Order
5. Production → Operations (track)

### Handle Material Shortage
1. MRP → Run MRP
2. Review shortage report
3. Either:
   - Adjust inventory, or
   - Create purchase order, or
   - Reschedule production

### Add New Product
1. Products → Add New
2. Enter part number & details
3. Save product
4. BOM → Create BOM
5. Add required materials

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Can't find item | Check spelling, try partial search |
| Autocomplete not working | Type 2+ characters, refresh page |
| Can't create production order | Check: Order exists? BOM defined? Materials available? |
| Data not saving | Check required fields (red *) |
| Page slow | Clear cache, try different browser |

## Field Icons
- ✏️ **Edit** - Click to modify
- ❓ **Help** - Hover for tooltip
- 🔍 **Search** - Filter results
- ➕ **Add** - Create new item
- 📊 **Report** - View details
- ⚠️ **Alert** - Needs attention

## Units of Measure
- **EA** - Each
- **KG** - Kilograms
- **LB** - Pounds
- **M** - Meters
- **L** - Liters
- **BOX** - Box
- **SET** - Set

## Quick Formulas
- **Reorder Point** = (Daily Usage × Lead Time) + Safety Stock
- **Net Requirements** = Gross Requirements - Available Inventory
- **Production Time** = Setup Time + (Quantity × Cycle Time)

## Support Contacts
- System Issues: IT Support
- Training: Your Supervisor
- Bug Reports: Submit ticket
- Documentation: See USER_GUIDE.md

---
*Print this card and keep it handy for quick reference*
*Version 1.0 | Updated: August 2025*