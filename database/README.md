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

### Full Restore (Schema + Data)
```bash
mysql -u root -p < mrp_erp_full_backup.sql
```

### Schema Only
```bash
mysql -u root -p < mrp_erp_schema_only.sql
```

### With Test Data
```bash
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