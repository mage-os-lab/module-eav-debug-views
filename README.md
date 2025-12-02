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
composer require --dev mage-os/module-eav-debug-views
bin/magento setup:upgrade
```

## Created Views

### 1. dev_product

Combines `catalog_product_entity` with all EAV attributes aggregated as JSON.

**Columns:**
- All `catalog_product_entity` columns (entity_id, sku, type_id, etc.)
- `eav_attributes` (JSON) - All EAV attribute values from decimal, datetime, int, text, varchar tables

<img width="972" height="230" alt="2025-12-02_134111" src="https://github.com/user-attachments/assets/722bdd36-0d0c-4ee8-b586-baaec3927c70" />

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

**Columns:**
- All `catalog_category_entity` columns (entity_id, path, level, etc.)
- `eav_attributes` (JSON) - All EAV attribute values from decimal, datetime, int, text, varchar tables

<img width="942" height="230" alt="2025-12-02_133915" src="https://github.com/user-attachments/assets/4b18f9bc-5785-416d-8f87-cbba207a6f60" />

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

**Columns:**
- All `customer_entity` columns (entity_id, firstname, lastname, email, etc.)
- `eav_attributes` (JSON) - All EAV attribute values from decimal, datetime, int, text, varchar tables

<img width="1614" height="121" alt="2025-12-02_133737" src="https://github.com/user-attachments/assets/6edaa2ab-71fa-4abf-b85b-aec1cbd27c7e" />

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

**Columns:**
- All `customer_address_entity` columns (entity_id, firstname, lastname, street, city, etc.)
- `eav_attributes` (JSON) - All EAV attribute values from decimal, datetime, int, text, varchar tables

<img width="1350" height="100" alt="2025-12-02_134001" src="https://github.com/user-attachments/assets/8e06aa3b-0705-4d44-b251-99faa8157b47" />

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

### 5. dev_product_attribute

Quick reference for product attribute metadata.

**Columns:**
- All `eav_attribute` columns (attribute_id, attribute_code, etc.)
- All `catalog_eav_attribute` columns (is_searchable, is_filterable, used_in_product_listing, etc.)
- `attribute_sets` (JSON) - All attribute sets and groups the attribute is assigned to, including IDs, names, and sort order.
- _@TODO: Add `eav_options` with all option IDs and values for DB-stored `select` and `multiselect`-type attributes._

<img width="1167" height="163" alt="2025-12-02_134337" src="https://github.com/user-attachments/assets/aaa3c3bc-917a-4fbf-8e7a-f6eefb9bc4e1" />

**Example Query:**
```sql
SELECT
    attribute_id,
    attribute_code,
    backend_type,
    frontend_input,
    is_filterable,
    is_searchable,
    position,
    attribute_sets
FROM dev_product_attribute
WHERE is_filterable=1
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

For technical reasons, we can't sort attributes alphabetically. Scoped values for an attribute may appear anywhere within the JSON. (MySQL does not support sorting values within `JSON_OBJECTAGG(...)` in `ONLY_FULL_GROUP_BY` mode.)

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
