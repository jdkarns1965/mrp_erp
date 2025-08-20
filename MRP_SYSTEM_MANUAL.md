# MRP System Manual
## Complete Feature Guide for Basic and Enhanced MRP

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Basic MRP System](#basic-mrp-system)
3. [Enhanced MRP Engine](#enhanced-mrp-engine)
4. [Feature Comparison](#feature-comparison)
5. [Usage Guide](#usage-guide)
6. [Technical Reference](#technical-reference)
7. [Best Practices](#best-practices)

---

## System Overview

The MRP/ERP system provides two distinct MRP calculation engines designed for different business needs:

- **Basic MRP**: Quick, order-specific material requirements planning
- **Enhanced MRP**: Enterprise-level, time-phased planning with advanced features

Both systems share core functionality but differ in scope, complexity, and planning capabilities.

---

## Basic MRP System

### Purpose
Single-order material requirements calculation with immediate shortage identification.

### Core Features

#### 1. Order-Based Calculation
- **Function**: `runMRP($orderId)`
- Processes individual customer orders
- Real-time material requirement calculation
- Immediate shortage reporting

#### 2. BOM Explosion
- Recursive bill of materials processing
- Multi-level component breakdown
- Consolidated material requirements across products
- Support for complex product structures

#### 3. Inventory Netting
- **Function**: `getAvailableQuantity()`
- Real-time inventory checking
- Available stock consideration
- Reserved quantity tracking
- Net requirement calculation

#### 4. Order Quantity Calculation
- **Function**: `calculateSuggestedOrderQuantity()`
- Minimum/maximum stock level consideration
- Reorder point logic
- Automatic rounding to pack sizes
- Economic quantity suggestions

#### 5. Lead Time Management
- **Function**: `calculateOrderDate()`
- Backward scheduling from required date
- Lead time offset calculation
- Past-due order detection
- Automatic date adjustment

#### 6. Cost Calculation
- Unit cost tracking
- Total purchase cost estimation
- Material cost aggregation
- Order value calculation

#### 7. Purchase Order Suggestions
- **Function**: `generatePurchaseOrderSuggestions()`
- Supplier-grouped recommendations
- Consolidated material requirements
- Priority-based ordering
- Cost optimization

### Database Operations

#### Tables Used
- `customer_orders` - Order information
- `customer_order_details` - Line items
- `products` - Product master data
- `materials` - Material master data
- `bom_headers` - BOM structure
- `bom_details` - BOM components
- `inventory` - Stock levels
- `mrp_requirements` - Calculation results

#### Data Storage
- Persistent MRP calculation history
- Order-linked requirements
- Audit trail maintenance
- Historical analysis support

### Output Structure

```php
[
    'success' => true/false,
    'order_id' => 123,
    'calculation_date' => '2025-01-20 14:30:00',
    'requirements' => [
        [
            'material_id' => 1,
            'material_code' => 'MAT-001',
            'material_name' => 'Plastic Resin',
            'gross_requirement' => 100,
            'available_stock' => 30,
            'net_requirement' => 70,
            'suggested_order_qty' => 100,
            'suggested_order_date' => '2025-01-15',
            'lead_time_days' => 5,
            'supplier_name' => 'ABC Supplier',
            'unit_cost' => 2.50,
            'total_cost' => 250.00
        ]
    ],
    'summary' => [
        'total_materials' => 5,
        'materials_with_shortage' => 3,
        'total_purchase_cost' => 1250.00,
        'urgent_orders' => 2,
        'can_fulfill' => false
    ]
]
```

---

## Enhanced MRP Engine

### Purpose
Enterprise-wide, time-phased material and production planning with multiple demand sources.

### Advanced Features

#### 1. Time-Phased Planning
- **Function**: `runTimePhasedMRP($options)`
- Planning horizon configuration (default 90 days)
- Period-based bucketing
- Rolling forecast support
- Time fence management

#### 2. Multiple Demand Sources
- **Customer Orders**: Active order processing
- **Master Production Schedule (MPS)**: Forecast integration
- **Safety Stock**: Automatic replenishment
- Demand consolidation and prioritization

#### 3. Planning Calendar Integration
- **Function**: `loadPlanningPeriods()`
- Working day calculation
- Holiday consideration
- Shift calendar support
- Capacity-aware scheduling

#### 4. Lot Sizing Rules
- **Function**: `calculateLotSize()`
- **Lot-for-Lot**: Exact requirement matching
- **Fixed Order Quantity**: Predetermined batch sizes
- **Min-Max**: Range-based ordering
- **Economic Order Quantity (EOQ)**: Cost-optimized batches
- Lot multiple consideration

#### 5. MRP Run Management
- **Function**: `initializeMRPRun()`
- Run type selection (regenerative/net-change)
- Parameter tracking
- Execution monitoring
- Performance metrics

#### 6. Advanced BOM Processing
- **Function**: `explodeAndProcessBOM()`
- Parent-child relationship tracking
- Where-used analysis
- Phantom assembly handling
- Co-product/by-product support

#### 7. Time-Phased Requirements
- **Function**: `calculateTimePhasedRequirements()`
- Period-by-period planning
- Projected available balance
- Planned order releases
- Action message generation

#### 8. Order Suggestions
- **Purchase Orders**: Material procurement
- **Production Orders**: Manufacturing scheduling
- Priority classification (urgent/high/normal)
- Due date calculation

#### 9. Economic Calculations
- **Function**: `calculateEOQ()`
- Ordering cost consideration
- Carrying cost analysis
- Optimal batch size determination
- Cost trade-off optimization

#### 10. Advanced Scheduling
- **Function**: `calculateOrderReleaseDate()`
- Working day calculation
- Lead time offsetting
- Backward/forward scheduling
- Capacity consideration

### Configuration Options

```php
$options = [
    'run_type' => 'regenerative',        // regenerative/net-change
    'planning_horizon' => 90,            // days
    'include_orders' => true,            // customer orders
    'include_mps' => true,               // master schedule
    'include_safety_stock' => true,      // safety stock demands
    'user' => 'john.doe'                 // run initiator
]
```

### Database Operations

#### Additional Tables
- `mrp_runs` - Run history and parameters
- `planning_calendar` - Period definitions
- `master_production_schedule` - MPS data
- `purchase_order_suggestions` - PO recommendations
- `production_order_suggestions` - Production recommendations
- `v_mrp_actions` - Action view for urgent items

### Time-Phased Output

```php
'time_phased_plan' => [
    [
        'period_id' => 1,
        'period_name' => 'Week 1',
        'period_start' => '2025-01-20',
        'period_end' => '2025-01-26',
        'gross_requirements' => 100,
        'scheduled_receipts' => 0,
        'on_hand' => 50,
        'projected_available' => -50,
        'net_requirements' => 50,
        'planned_receipts' => 100,
        'planned_orders' => 100,
        'order_release_date' => '2025-01-15'
    ]
]
```

---

## Feature Comparison

| Feature | Basic MRP | Enhanced MRP |
|---------|-----------|--------------|
| **Scope** | Single order | Enterprise-wide |
| **Planning Horizon** | Order due date | Configurable (90+ days) |
| **Demand Sources** | Customer orders only | Orders + MPS + Safety Stock |
| **Time Phasing** | No | Yes |
| **Lot Sizing** | Simple rounding | Multiple rules (EOQ, Min-Max, etc.) |
| **Calendar Integration** | Basic lead time | Full working calendar |
| **Run Types** | Single calculation | Regenerative/Net-change |
| **Performance** | Fast (<1 second) | Comprehensive (5-30 seconds) |
| **Audit Trail** | Basic | Complete with run history |
| **Order Suggestions** | Purchase only | Purchase + Production |
| **Cost Optimization** | Basic | Advanced (EOQ, carrying costs) |
| **Reporting** | Simple shortage list | Time-phased reports |
| **Database Load** | Light | Heavy |
| **Use Case** | Quick checks | Full planning cycles |

---

## Usage Guide

### When to Use Basic MRP

**Ideal for:**
- Quick order feasibility checks
- Single customer order processing
- Immediate shortage identification
- Simple purchase requirements
- Manual order management
- Small-scale operations

**Example Scenarios:**
1. Customer calls asking about order availability
2. Rush order evaluation
3. Quick material check before quoting
4. Daily shortage review
5. Manual purchase order creation

### When to Use Enhanced MRP

**Ideal for:**
- Weekly/monthly planning cycles
- Multi-order consolidation
- Forecast-based planning
- Automated suggestion generation
- Complex manufacturing environments
- Large-scale operations

**Example Scenarios:**
1. Weekly MRP regeneration
2. Monthly purchase planning
3. Production scheduling
4. Capacity planning
5. Strategic inventory management
6. Cost optimization initiatives

### Workflow Integration

#### Basic MRP Workflow
1. Receive customer order
2. Run Basic MRP calculation
3. Review shortages
4. Generate purchase suggestions
5. Create purchase orders manually
6. Update inventory on receipt

#### Enhanced MRP Workflow
1. Update forecasts (MPS)
2. Configure run parameters
3. Execute Enhanced MRP run
4. Review time-phased plan
5. Approve suggested orders
6. Auto-generate purchase/production orders
7. Monitor execution
8. Analyze performance metrics

---

## Technical Reference

### Basic MRP Methods

| Method | Purpose | Parameters | Returns |
|--------|---------|------------|---------|
| `runMRP()` | Execute MRP calculation | `$orderId` | Results array |
| `getOrderDetails()` | Fetch order information | `$orderId` | Order data |
| `clearPreviousCalculations()` | Clean old results | `$orderId` | void |
| `saveMRPRequirement()` | Store calculation | Multiple | void |
| `calculateSuggestedOrderQuantity()` | Determine order size | Requirements | Quantity |
| `calculateOrderDate()` | Schedule orders | Date, lead time | Date |
| `generateMRPSummary()` | Create summary | Requirements | Summary array |
| `getMRPHistory()` | Retrieve history | `$orderId` | History array |
| `generatePurchaseOrderSuggestions()` | Create PO suggestions | `$orderId` | Suggestions |

### Enhanced MRP Methods

| Method | Purpose | Parameters | Returns |
|--------|---------|------------|---------|
| `runTimePhasedMRP()` | Execute time-phased MRP | `$options` | Results array |
| `initializeMRPRun()` | Start MRP session | `$options` | Run ID |
| `loadPlanningPeriods()` | Get calendar periods | None | Periods array |
| `collectDemands()` | Gather all demands | `$options` | Demands array |
| `processMRPItems()` | Process requirements | `$demands` | Results array |
| `calculateTimePhasedRequirements()` | Time-phase calculation | `$item` | Plan array |
| `calculateLotSize()` | Determine lot size | Requirements, rules | Quantity |
| `calculateEOQ()` | Economic order quantity | Item, demand | EOQ |
| `generateOrderSuggestions()` | Create suggestions | Results | void |
| `finalizeMRPRun()` | Complete run | None | void |
| `getMRPRunSummary()` | Get run summary | None | Summary |

### Configuration Parameters

#### Basic MRP
- None required (order ID only)

#### Enhanced MRP
```php
[
    'run_type' => 'regenerative|net-change',
    'planning_horizon' => 30-365,
    'include_orders' => true|false,
    'include_mps' => true|false,
    'include_safety_stock' => true|false,
    'user' => 'username'
]
```

---

## Best Practices

### Performance Optimization

#### Basic MRP
1. **Index key fields**: order_id, material_id, product_id
2. **Limit BOM depth**: Maximum 5-7 levels recommended
3. **Cache frequently used data**: Materials, suppliers
4. **Batch similar orders**: Process together when possible

#### Enhanced MRP
1. **Schedule off-peak runs**: Night/weekend processing
2. **Use net-change for updates**: Faster than regenerative
3. **Limit planning horizon**: 90 days optimal
4. **Archive old runs**: Keep 30-60 days active
5. **Optimize lot sizing rules**: Review quarterly

### Data Quality

1. **Maintain accurate BOMs**: Regular validation
2. **Update lead times**: Monthly review
3. **Verify inventory accuracy**: Cycle counting
4. **Clean master data**: Remove obsolete items
5. **Validate supplier information**: Quarterly updates

### Process Guidelines

#### Planning Frequency
- **Basic MRP**: On-demand, per order
- **Enhanced MRP**: 
  - Daily: Net-change for urgent items
  - Weekly: Full regeneration
  - Monthly: Complete review with optimization

#### Order Management
1. **Review suggestions before approval**
2. **Consolidate orders by supplier**
3. **Consider minimum order quantities**
4. **Factor in volume discounts**
5. **Plan for safety stock**

#### System Maintenance
1. **Regular database optimization**
2. **Archive historical data**
3. **Update planning calendars**
4. **Review lot sizing parameters**
5. **Monitor system performance**

### Troubleshooting

#### Common Issues - Basic MRP
| Issue | Cause | Solution |
|-------|-------|----------|
| No requirements generated | Missing BOM | Create/activate BOM |
| Incorrect quantities | Wrong BOM quantities | Update BOM details |
| Past-due orders | Short lead times | Adjust lead times |
| Zero inventory showing | Reservation issues | Check reserved quantities |

#### Common Issues - Enhanced MRP
| Issue | Cause | Solution |
|-------|-------|----------|
| Long execution time | Large data volume | Reduce planning horizon |
| Missing periods | Calendar not configured | Set up planning calendar |
| No MPS demands | MPS not populated | Enter forecast data |
| Incorrect lot sizes | Wrong rule configuration | Review lot sizing rules |
| Memory errors | Too many items | Use net-change mode |

---

## Appendix A: Database Schema

### Core Tables Structure

```sql
-- MRP Requirements (Basic)
CREATE TABLE mrp_requirements (
    id INT PRIMARY KEY,
    calculation_date DATETIME,
    order_id INT,
    product_id INT,
    material_id INT,
    gross_requirement DECIMAL(15,4),
    available_stock DECIMAL(15,4),
    net_requirement DECIMAL(15,4),
    suggested_order_qty DECIMAL(15,4),
    suggested_order_date DATE
);

-- MRP Runs (Enhanced)
CREATE TABLE mrp_runs (
    id INT PRIMARY KEY,
    run_date DATETIME,
    run_type VARCHAR(20),
    planning_horizon_days INT,
    status VARCHAR(20),
    run_by VARCHAR(100),
    parameters JSON,
    total_products INT,
    total_materials INT,
    total_po_suggestions INT,
    total_prod_suggestions INT,
    execution_time_seconds INT,
    error_log TEXT
);

-- Planning Calendar
CREATE TABLE planning_calendar (
    id INT PRIMARY KEY,
    period_name VARCHAR(50),
    period_start DATE,
    period_end DATE,
    is_working_period BOOLEAN,
    working_days INT,
    period_type VARCHAR(20)
);
```

---

## Appendix B: API Integration

### REST API Endpoints

#### Basic MRP
```
POST /api/mrp/calculate
{
    "order_id": 123
}

GET /api/mrp/history/{order_id}
GET /api/mrp/suggestions/{order_id}
```

#### Enhanced MRP
```
POST /api/mrp/run
{
    "run_type": "regenerative",
    "planning_horizon": 90,
    "include_orders": true,
    "include_mps": true,
    "include_safety_stock": true
}

GET /api/mrp/runs
GET /api/mrp/runs/{run_id}
GET /api/mrp/time-phased/{item_type}/{item_id}
```

---

## Appendix C: Report Examples

### Basic MRP Shortage Report
```
Order: #ORD-2025-001
Customer: ABC Manufacturing
Required Date: 2025-02-01

Material Shortages:
┌──────────────┬──────────────┬──────────┬───────────┬──────────┐
│ Material     │ Required     │ On Hand  │ Shortage  │ Order By │
├──────────────┼──────────────┼──────────┼───────────┼──────────┤
│ MAT-001      │ 100 KG       │ 30 KG    │ 70 KG     │ Jan 27   │
│ MAT-002      │ 50 PCS       │ 0 PCS    │ 50 PCS    │ Jan 25   │
│ MAT-003      │ 25 BOX       │ 10 BOX   │ 15 BOX    │ Jan 28   │
└──────────────┴──────────────┴──────────┴───────────┴──────────┘

Total Purchase Value: $1,250.00
Urgent Orders: 2
```

### Enhanced MRP Time-Phased Report
```
Item: MAT-001 - Plastic Resin
Lead Time: 5 days
Safety Stock: 20 KG

Week    Gross Req  Sched Rec  On Hand  Proj Avail  Net Req  Plan Order
──────────────────────────────────────────────────────────────────────
Jan 20   50         0          100      50          0         0
Jan 27   75         0          50       -25         45        100
Feb 03   60         100        75       15          0         0
Feb 10   80         0          15       -65         85        100
──────────────────────────────────────────────────────────────────────

Planned Orders:
- Jan 22: Order 100 KG (Due Jan 27)
- Feb 05: Order 100 KG (Due Feb 10)
```

---

## Document Information

**Version**: 1.0  
**Created**: January 2025  
**System**: MRP/ERP Manufacturing System  
**Purpose**: Complete MRP Feature Documentation  

---

*End of Manual*