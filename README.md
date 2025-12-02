# Mage-OS EAV Debug Views

Developer utility module for Magento 2.4.x that creates database views aggregating EAV entity data with attribute values in JSON format.

> [!IMPORTANT]
> 
> **This module is designed for development and debugging.**
> 
> While production-installable, consider these factors:
> - Database views may impact performance on large datasets
> - JSON aggregation is resource-intensive for complex queries
> - Intended for temporary debugging, not permanent production use
> - No query optimization beyond entity_id lookups
> 
> **Suggested Use:** Install in development/staging only. Do not write code that would use these views on a live site.

## Requirements

- **Magento:** 2.4.x
- **PHP:** 8.1+
- **Database:** MySQL 5.7+ or MariaDB 10.2.3+
  - Requires MySQL `JSON` function support

## Installation

```bash
composer require --dev mage-os/eav-debug-views
bin/magento setup:upgrade
```

## Created Views

### 1. dev_product

Combines `catalog_product_entity` with all EAV attributes aggregated as JSON.

**Columns:**
- All `catalog_product_entity` columns (entity_id, sku, type_id, etc.)
- `eav_attributes` (JSON) - All EAV attribute values from decimal, datetime, int, text, varchar tables

**Example Query:**
```sql
SELECT
    entity_id,
    sku,
    type_id,
    JSON_PRETTY(eav_attributes) as attributes
FROM dev_product
WHERE sku = 'my-product-sku';
```

**Extract Specific Attributes:**
```sql
SELECT
    entity_id,
    sku,
    JSON_EXTRACT(eav_attributes, '$.name') as name,
    JSON_EXTRACT(eav_attributes, '$.price') as price,
    JSON_EXTRACT(eav_attributes, '$.status') as status
FROM dev_product
WHERE entity_id = 1;
```

### 2. dev_category

Combines `catalog_category_entity` with EAV attributes.

**Example Query:**
```sql
SELECT
    entity_id,
    parent_id,
    path,
    level,
    JSON_EXTRACT(eav_attributes, '$.name') as name,
    JSON_EXTRACT(eav_attributes, '$.is_active') as is_active
FROM dev_category
WHERE level = 2;
```

### 3. dev_customer

Combines `customer_entity` with EAV attributes.

**Example Query:**
```sql
SELECT
    entity_id,
    email,
    firstname,
    lastname,
    JSON_PRETTY(eav_attributes) as custom_attributes
FROM dev_customer
WHERE email LIKE '%@example.com';
```

### 4. dev_address

Combines `customer_address_entity` with EAV attributes.

**Example Query:**
```sql
SELECT
    entity_id,
    parent_id,
    city,
    country_id,
    JSON_PRETTY(eav_attributes) as custom_attributes
FROM dev_address
WHERE parent_id = 1;
```

### 5. dev_eav_attributes

Quick reference for attribute metadata without joins.

**Example Query:**
```sql
SELECT
    attribute_id,
    attribute_code,
    backend_type,
    frontend_input,
    is_filterable,
    is_searchable,
    sort_order,
    attribute_sets
FROM dev_eav_attributes
ORDER BY attribute_code;
```

## Store Scope

All EAV views aggregate **all store_id values** into a single JSON object per entity.

**Store-specific attribute keys** use the format `attribute_code:store_id` (e.g., `name:1`, `name:2`).
**Global attributes** (store_id = 0) use just the `attribute_code` (e.g., `name`, `sku`).

**Example - Querying store-specific values:**

```sql
-- Get product with global and store-specific names
SELECT
    entity_id,
    sku,
    JSON_EXTRACT(eav_attributes, '$.name') as global_name,
    JSON_EXTRACT(eav_attributes, '$.\"name:1\"') as store_1_name,
    JSON_EXTRACT(eav_attributes, '$.\"name:2\"') as store_2_name
FROM dev_product
WHERE sku = 'my-product';

-- See all attribute values including store-specific
SELECT
    entity_id,
    sku,
    JSON_PRETTY(eav_attributes) as all_attributes
FROM dev_product
WHERE entity_id = 1;
```

## Performance Considerations

### Query Optimization

If filtering by attribute values, be careful about the amount of records processed.

**Fast:**
```sql
-- Uses entity table index
SELECT * FROM dev_product WHERE entity_id = 123;
SELECT * FROM dev_product WHERE sku = 'ABC123';
```

**Not fast:**
```sql
-- Full table scan with JSON parsing
SELECT * FROM dev_product
WHERE JSON_EXTRACT(eav_attributes, '$.status') = 1;
```

### View Characteristics

- **NOT materialized** - Data is queried live from base tables
- **NOT indexed** - Uses base table indexes via entity_id
- **CTE overhead** - 5 subqueries per entity type
- **JSON aggregation** - Processing cost on SELECT

**Recommendation:** Use for ad-hoc debugging queries, not high-frequency production queries.

## Uninstallation

```bash
bin/magento module:uninstall MageOS_EavDebugViews --remove-data
```

This command:
1. Drops all module views from the database
2. Removes module from `setup_module` table
3. Removes module code (if installed via composer)

## Use Cases

### Quick Product Debugging
```sql
-- See all attributes for a specific product
SELECT entity_id, sku, JSON_PRETTY(eav_attributes)
FROM dev_product
WHERE sku = 'problematic-sku';
```

### Find Products with Specific Attribute Values
```sql
-- Find disabled products
SELECT entity_id, sku,
       JSON_EXTRACT(eav_attributes, '$.status') as status
FROM dev_product
HAVING status = 2;  -- Disabled
```

### Attribute Discovery
```sql
-- What attributes exist for products?
SELECT attribute_code, frontend_input, is_required
FROM dev_eav_attributes
WHERE entity_type_code = 'catalog_product'
  AND is_user_defined = 1;
```

### Category Hierarchy Analysis
```sql
-- View category tree with names
SELECT
    entity_id,
    level,
    path,
    JSON_EXTRACT(eav_attributes, '$.name') as name,
    JSON_EXTRACT(eav_attributes, '$.is_active') as active
FROM dev_category
WHERE level BETWEEN 1 AND 3
ORDER BY path;
```

## License

Open Software License (OSL-3.0)

## Contributing

Issues and pull requests welcome on GitHub.

## Support

This is a community-maintained developer utility. No support or warranty implied. Use at your own risk.

For bugs or feature requests, please open an issue in the GitHub repository.
