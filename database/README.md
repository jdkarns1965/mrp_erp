# Database Files

This directory contains database schemas, backups, and migration scripts for the MRP/ERP system.

## Files

### Complete Backups
- `mrp_erp_full_backup.sql` - Complete database backup including all tables, data, views, and stored procedures
- `mrp_erp_schema_only.sql` - Schema-only backup (table structures, views, indexes) without data

### Schema and Migrations
- `schema.sql` - Initial database schema
- `mrp_enhancements.sql` - MRP module enhancements
- `create_inventory_transactions.sql` - Inventory transaction tables
- `create_planning_tables.sql` - Production planning tables (MPS)
- `create_sample_production_orders.sql` - Sample production order data

### Test Data
- `test_data.sql` - Comprehensive test dataset
- `test_data_simple.sql` - Minimal test dataset
- `add_production_data.sql` - Production module test data

## Restoring Database

### Option 1: Clean Install from Full Backup (Recommended)
```bash
# Drop existing database if it exists (optional)
mysql -u root -p -e "DROP DATABASE IF EXISTS mrp_erp;"

# Restore complete database with all data
mysql -u root -p < mrp_erp_full_backup.sql
```

### Option 2: Using phpMyAdmin
1. Open phpMyAdmin in your browser
2. Drop the `mrp_erp` database if it exists
3. Click "Import" tab
4. Choose file: `database/mrp_erp_full_backup.sql`
5. Click "Go" to import

### Option 3: Schema Only (Fresh Install)
```bash
# Drop existing database if it exists
mysql -u root -p -e "DROP DATABASE IF EXISTS mrp_erp;"

# Restore schema only
mysql -u root -p < mrp_erp_schema_only.sql
```

### Option 4: Schema with Test Data
```bash
# Drop existing database if it exists
mysql -u root -p -e "DROP DATABASE IF EXISTS mrp_erp;"

# First restore schema
mysql -u root -p < mrp_erp_schema_only.sql

# Then add test data
mysql -u root -p mrp_erp < test_data.sql
```

## Backup Schedule

Backups should be updated:
- After major feature additions
- Before deploying to production
- When sample data changes significantly

## Notes

- The full backup includes all customer data (if any exists)
- Schema-only backup is useful for new installations
- Test data files are for development/testing only
- Always backup before making schema changes

Last backup: 2025-08-19