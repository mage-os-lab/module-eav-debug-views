<?php

declare(strict_types=1);

/**
 * Mage-OS EavDebugViews Module
 *
 * WARNING: This is a development/debugging tool.
 * Creates database views for easier EAV data inspection.
 * Not intended for use with production code.
 *
 * @category   MageOS
 * @package    MageOS_EavDebugViews
 * @copyright  Copyright (C) 2025 Mage-OS (https://mage-os.org/)
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace MageOS\EavDebugViews\Setup\Patch\Schema;

use Exception;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Psr\Log\LoggerInterface;

class CreateEavDebugViews implements SchemaPatchInterface
{
    /**
     * @param SchemaSetupInterface $schemaSetup
     * @param LoggerInterface $logger
     */
    public function __construct(
        private SchemaSetupInterface $schemaSetup,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Apply patch - create all EAV debug views
     *
     * @return void
     */
    public function apply(): void
    {
        $this->schemaSetup->startSetup();

        try {
            if (!$this->checkMySQLVersion()) {
                $this->logger->warning(
                    'MageOS_EavDebugViews: MySQL 5.7+ or MariaDB 10.2.3+ required for JSON support. Views not created.'
                );
                $this->schemaSetup->endSetup();
                return;
            }

            $connection = $this->schemaSetup->getConnection();

            $this->createAddressView($connection);
            $this->createCategoryView($connection);
            $this->createCustomerView($connection);
            $this->createProductView($connection);
            $this->createProductAttributeView($connection);

            $this->logger->info('MageOS_EavDebugViews: All EAV debug views created successfully');
        } catch (Exception $e) {
            $this->logger->error('MageOS_EavDebugViews: ' . $e->getMessage());
            throw $e;
        }

        $this->schemaSetup->endSetup();
    }

    /**
     * Check if MySQL/MariaDB version supports JSON_OBJECTAGG
     *
     * @return bool
     */
    private function checkMySQLVersion(): bool
    {
        $connection = $this->schemaSetup->getConnection();
        $versionString = $connection->fetchOne('SELECT VERSION()');
        $version = explode('-', $versionString)[0];

        // MariaDB supports JSON_OBJECTAGG from 10.2.3
        if (stripos($versionString, 'mariadb') !== false) {
            return version_compare($version, '10.2.3', '>=');
        }

        // MySQL supports JSON_OBJECTAGG from 5.7
        return version_compare($version, '5.7.0', '>=');
    }

    /**
     * Create dev_product view
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function createProductView($connection): void
    {
        $productEntity = $this->schemaSetup->getTable('catalog_product_entity');
        $productDecimal = $this->schemaSetup->getTable('catalog_product_entity_decimal');
        $productDatetime = $this->schemaSetup->getTable('catalog_product_entity_datetime');
        $productInt = $this->schemaSetup->getTable('catalog_product_entity_int');
        $productText = $this->schemaSetup->getTable('catalog_product_entity_text');
        $productVarchar = $this->schemaSetup->getTable('catalog_product_entity_varchar');
        $eavAttribute = $this->schemaSetup->getTable('eav_attribute');
        $devProduct = $this->schemaSetup->getTable('dev_product');

        if (!$connection->isTableExists($productEntity)) {
            return;
        }

        $sql = <<<SQL
        CREATE OR REPLACE VIEW {$devProduct} AS
        WITH
            eav_decimal AS (
                SELECT
                    eavd.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavd.store_id > 0, CONCAT(ea.attribute_code, ':', eavd.store_id), ea.attribute_code),
                        eavd.value
                    ) AS attributes
                FROM {$productDecimal} eavd
                INNER JOIN {$eavAttribute} ea ON eavd.attribute_id = ea.attribute_id
                GROUP BY eavd.entity_id
            ),
            eav_datetime AS (
                SELECT
                    eavdt.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavdt.store_id > 0, CONCAT(ea.attribute_code, ':', eavdt.store_id), ea.attribute_code),
                        eavdt.value
                    ) AS attributes
                FROM {$productDatetime} eavdt
                INNER JOIN {$eavAttribute} ea ON eavdt.attribute_id = ea.attribute_id
                GROUP BY eavdt.entity_id
            ),
            eav_int AS (
                SELECT
                    eavi.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavi.store_id > 0, CONCAT(ea.attribute_code, ':', eavi.store_id), ea.attribute_code),
                        eavi.value
                    ) AS attributes
                FROM {$productInt} eavi
                INNER JOIN {$eavAttribute} ea ON eavi.attribute_id = ea.attribute_id
                GROUP BY eavi.entity_id
            ),
            eav_text AS (
                SELECT
                    eavt.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavt.store_id > 0, CONCAT(ea.attribute_code, ':', eavt.store_id), ea.attribute_code),
                        eavt.value
                    ) AS attributes
                FROM {$productText} eavt
                INNER JOIN {$eavAttribute} ea ON eavt.attribute_id = ea.attribute_id
                GROUP BY eavt.entity_id
            ),
            eav_varchar AS (
                SELECT
                    eavv.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavv.store_id > 0, CONCAT(ea.attribute_code, ':', eavv.store_id), ea.attribute_code),
                        eavv.value
                    ) AS attributes
                FROM {$productVarchar} eavv
                INNER JOIN {$eavAttribute} ea ON eavv.attribute_id = ea.attribute_id
                GROUP BY eavv.entity_id
            )
        SELECT
            e.*,
            JSON_MERGE_PRESERVE(
                COALESCE(ed.attributes, JSON_OBJECT()),
                COALESCE(edt.attributes, JSON_OBJECT()),
                COALESCE(ei.attributes, JSON_OBJECT()),
                COALESCE(et.attributes, JSON_OBJECT()),
                COALESCE(ev.attributes, JSON_OBJECT())
            ) AS eav_attributes
        FROM {$productEntity} e
        LEFT JOIN eav_decimal ed ON e.entity_id = ed.entity_id
        LEFT JOIN eav_datetime edt ON e.entity_id = edt.entity_id
        LEFT JOIN eav_int ei ON e.entity_id = ei.entity_id
        LEFT JOIN eav_text et ON e.entity_id = et.entity_id
        LEFT JOIN eav_varchar ev ON e.entity_id = ev.entity_id
        SQL;

        $connection->query($sql);
        $this->logger->info('MageOS_EavDebugViews: View dev_product created');
    }

    /**
     * Create dev_category view
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function createCategoryView($connection): void
    {
        $categoryEntity = $this->schemaSetup->getTable('catalog_category_entity');
        $categoryDecimal = $this->schemaSetup->getTable('catalog_category_entity_decimal');
        $categoryDatetime = $this->schemaSetup->getTable('catalog_category_entity_datetime');
        $categoryInt = $this->schemaSetup->getTable('catalog_category_entity_int');
        $categoryText = $this->schemaSetup->getTable('catalog_category_entity_text');
        $categoryVarchar = $this->schemaSetup->getTable('catalog_category_entity_varchar');
        $eavAttribute = $this->schemaSetup->getTable('eav_attribute');
        $devCategory = $this->schemaSetup->getTable('dev_category');

        if (!$connection->isTableExists($categoryEntity)) {
            return;
        }

        $sql = <<<SQL
        CREATE OR REPLACE VIEW {$devCategory} AS
        WITH
            eav_decimal AS (
                SELECT
                    eavd.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavd.store_id > 0, CONCAT(ea.attribute_code, ':', eavd.store_id), ea.attribute_code),
                        eavd.value
                    ) AS attributes
                FROM {$categoryDecimal} eavd
                INNER JOIN {$eavAttribute} ea ON eavd.attribute_id = ea.attribute_id
                GROUP BY eavd.entity_id
            ),
            eav_datetime AS (
                SELECT
                    eavdt.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavdt.store_id > 0, CONCAT(ea.attribute_code, ':', eavdt.store_id), ea.attribute_code),
                        eavdt.value
                    ) AS attributes
                FROM {$categoryDatetime} eavdt
                INNER JOIN {$eavAttribute} ea ON eavdt.attribute_id = ea.attribute_id
                GROUP BY eavdt.entity_id
            ),
            eav_int AS (
                SELECT
                    eavi.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavi.store_id > 0, CONCAT(ea.attribute_code, ':', eavi.store_id), ea.attribute_code),
                        eavi.value
                    ) AS attributes
                FROM {$categoryInt} eavi
                INNER JOIN {$eavAttribute} ea ON eavi.attribute_id = ea.attribute_id
                GROUP BY eavi.entity_id
            ),
            eav_text AS (
                SELECT
                    eavt.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavt.store_id > 0, CONCAT(ea.attribute_code, ':', eavt.store_id), ea.attribute_code),
                        eavt.value
                    ) AS attributes
                FROM {$categoryText} eavt
                INNER JOIN {$eavAttribute} ea ON eavt.attribute_id = ea.attribute_id
                GROUP BY eavt.entity_id
            ),
            eav_varchar AS (
                SELECT
                    eavv.entity_id,
                    JSON_OBJECTAGG(
                        IF(eavv.store_id > 0, CONCAT(ea.attribute_code, ':', eavv.store_id), ea.attribute_code),
                        eavv.value
                    ) AS attributes
                FROM {$categoryVarchar} eavv
                INNER JOIN {$eavAttribute} ea ON eavv.attribute_id = ea.attribute_id
                GROUP BY eavv.entity_id
            )
        SELECT
            e.*,
            JSON_MERGE_PRESERVE(
                COALESCE(ed.attributes, JSON_OBJECT()),
                COALESCE(edt.attributes, JSON_OBJECT()),
                COALESCE(ei.attributes, JSON_OBJECT()),
                COALESCE(et.attributes, JSON_OBJECT()),
                COALESCE(ev.attributes, JSON_OBJECT())
            ) AS eav_attributes
        FROM {$categoryEntity} e
        LEFT JOIN eav_decimal ed ON e.entity_id = ed.entity_id
        LEFT JOIN eav_datetime edt ON e.entity_id = edt.entity_id
        LEFT JOIN eav_int ei ON e.entity_id = ei.entity_id
        LEFT JOIN eav_text et ON e.entity_id = et.entity_id
        LEFT JOIN eav_varchar ev ON e.entity_id = ev.entity_id
        SQL;

        $connection->query($sql);
        $this->logger->info('MageOS_EavDebugViews: View dev_category created');
    }

    /**
     * Create dev_customer view
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function createCustomerView($connection): void
    {
        $customerEntity = $this->schemaSetup->getTable('customer_entity');
        $customerDecimal = $this->schemaSetup->getTable('customer_entity_decimal');
        $customerDatetime = $this->schemaSetup->getTable('customer_entity_datetime');
        $customerInt = $this->schemaSetup->getTable('customer_entity_int');
        $customerText = $this->schemaSetup->getTable('customer_entity_text');
        $customerVarchar = $this->schemaSetup->getTable('customer_entity_varchar');
        $eavAttribute = $this->schemaSetup->getTable('eav_attribute');
        $devCustomer = $this->schemaSetup->getTable('dev_customer');

        if (!$connection->isTableExists($customerEntity)) {
            return;
        }

        $sql = <<<SQL
        CREATE OR REPLACE VIEW {$devCustomer} AS
        WITH
            eav_decimal AS (
                SELECT
                    eavd.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavd.value
                    ) AS attributes
                FROM {$customerDecimal} eavd
                INNER JOIN {$eavAttribute} ea ON eavd.attribute_id = ea.attribute_id
                GROUP BY eavd.entity_id
            ),
            eav_datetime AS (
                SELECT
                    eavdt.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavdt.value
                    ) AS attributes
                FROM {$customerDatetime} eavdt
                INNER JOIN {$eavAttribute} ea ON eavdt.attribute_id = ea.attribute_id
                GROUP BY eavdt.entity_id
            ),
            eav_int AS (
                SELECT
                    eavi.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavi.value
                    ) AS attributes
                FROM {$customerInt} eavi
                INNER JOIN {$eavAttribute} ea ON eavi.attribute_id = ea.attribute_id
                GROUP BY eavi.entity_id
            ),
            eav_text AS (
                SELECT
                    eavt.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavt.value
                    ) AS attributes
                FROM {$customerText} eavt
                INNER JOIN {$eavAttribute} ea ON eavt.attribute_id = ea.attribute_id
                GROUP BY eavt.entity_id
            ),
            eav_varchar AS (
                SELECT
                    eavv.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavv.value
                    ) AS attributes
                FROM {$customerVarchar} eavv
                INNER JOIN {$eavAttribute} ea ON eavv.attribute_id = ea.attribute_id
                GROUP BY eavv.entity_id
            )
        SELECT
            e.*,
            JSON_MERGE_PRESERVE(
                COALESCE(ed.attributes, JSON_OBJECT()),
                COALESCE(edt.attributes, JSON_OBJECT()),
                COALESCE(ei.attributes, JSON_OBJECT()),
                COALESCE(et.attributes, JSON_OBJECT()),
                COALESCE(ev.attributes, JSON_OBJECT())
            ) AS eav_attributes
        FROM {$customerEntity} e
        LEFT JOIN eav_decimal ed ON e.entity_id = ed.entity_id
        LEFT JOIN eav_datetime edt ON e.entity_id = edt.entity_id
        LEFT JOIN eav_int ei ON e.entity_id = ei.entity_id
        LEFT JOIN eav_text et ON e.entity_id = et.entity_id
        LEFT JOIN eav_varchar ev ON e.entity_id = ev.entity_id
        SQL;

        $connection->query($sql);
        $this->logger->info('MageOS_EavDebugViews: View dev_customer created');
    }

    /**
     * Create dev_address view
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function createAddressView($connection): void
    {
        $addressEntity = $this->schemaSetup->getTable('customer_address_entity');
        $addressDecimal = $this->schemaSetup->getTable('customer_address_entity_decimal');
        $addressDatetime = $this->schemaSetup->getTable('customer_address_entity_datetime');
        $addressInt = $this->schemaSetup->getTable('customer_address_entity_int');
        $addressText = $this->schemaSetup->getTable('customer_address_entity_text');
        $addressVarchar = $this->schemaSetup->getTable('customer_address_entity_varchar');
        $eavAttribute = $this->schemaSetup->getTable('eav_attribute');
        $devAddress = $this->schemaSetup->getTable('dev_address');

        if (!$connection->isTableExists($addressEntity)) {
            return;
        }

        $sql = <<<SQL
        CREATE OR REPLACE VIEW {$devAddress} AS
        WITH
            eav_decimal AS (
                SELECT
                    eavd.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavd.value
                    ) AS attributes
                FROM {$addressDecimal} eavd
                INNER JOIN {$eavAttribute} ea ON eavd.attribute_id = ea.attribute_id
                GROUP BY eavd.entity_id
            ),
            eav_datetime AS (
                SELECT
                    eavdt.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavdt.value
                    ) AS attributes
                FROM {$addressDatetime} eavdt
                INNER JOIN {$eavAttribute} ea ON eavdt.attribute_id = ea.attribute_id
                GROUP BY eavdt.entity_id
            ),
            eav_int AS (
                SELECT
                    eavi.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavi.value
                    ) AS attributes
                FROM {$addressInt} eavi
                INNER JOIN {$eavAttribute} ea ON eavi.attribute_id = ea.attribute_id
                GROUP BY eavi.entity_id
            ),
            eav_text AS (
                SELECT
                    eavt.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavt.value
                    ) AS attributes
                FROM {$addressText} eavt
                INNER JOIN {$eavAttribute} ea ON eavt.attribute_id = ea.attribute_id
                GROUP BY eavt.entity_id
            ),
            eav_varchar AS (
                SELECT
                    eavv.entity_id,
                    JSON_OBJECTAGG(
                        ea.attribute_code,
                        eavv.value
                    ) AS attributes
                FROM {$addressVarchar} eavv
                INNER JOIN {$eavAttribute} ea ON eavv.attribute_id = ea.attribute_id
                GROUP BY eavv.entity_id
            )
        SELECT
            e.*,
            JSON_MERGE_PRESERVE(
                COALESCE(ed.attributes, JSON_OBJECT()),
                COALESCE(edt.attributes, JSON_OBJECT()),
                COALESCE(ei.attributes, JSON_OBJECT()),
                COALESCE(et.attributes, JSON_OBJECT()),
                COALESCE(ev.attributes, JSON_OBJECT())
            ) AS eav_attributes
        FROM {$addressEntity} e
        LEFT JOIN eav_decimal ed ON e.entity_id = ed.entity_id
        LEFT JOIN eav_datetime edt ON e.entity_id = edt.entity_id
        LEFT JOIN eav_int ei ON e.entity_id = ei.entity_id
        LEFT JOIN eav_text et ON e.entity_id = et.entity_id
        LEFT JOIN eav_varchar ev ON e.entity_id = ev.entity_id
        SQL;

        $connection->query($sql);
        $this->logger->info('MageOS_EavDebugViews: View dev_address created');
    }

    /**
     * Create dev_product_attribute view for quick attribute metadata lookup
     *
     * @param AdapterInterface $connection
     * @return void
     */
    private function createProductAttributeView($connection): void
    {
        $eavAttribute = $this->schemaSetup->getTable('eav_attribute');
        $catalogEavAttribute = $this->schemaSetup->getTable('catalog_eav_attribute');
        $eavEntityAttribute = $this->schemaSetup->getTable('eav_entity_attribute');
        $eavAttributeSet = $this->schemaSetup->getTable('eav_attribute_set');
        $eavAttributeGroup = $this->schemaSetup->getTable('eav_attribute_group');
        $devProductAttribute = $this->schemaSetup->getTable('dev_product_attribute');

        $sql = <<<SQL
        CREATE OR REPLACE VIEW {$devProductAttribute} AS
        SELECT
            eav.*,
            cav.*,
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'attribute_set_id', aea.attribute_set_id,
                    'attribute_set_name', eas.attribute_set_name,
                    'attribute_group_id', aea.attribute_group_id,
                    'attribute_group_name', eag.attribute_group_name,
                    'sort_order', aea.sort_order
                )
            ) AS attribute_sets
        FROM {$eavAttribute} eav
        INNER JOIN {$catalogEavAttribute} cav ON eav.attribute_id = cav.attribute_id
        LEFT JOIN {$eavEntityAttribute} aea ON eav.attribute_id = aea.attribute_id
        LEFT JOIN {$eavAttributeSet} eas ON aea.attribute_set_id = eas.attribute_set_id
        LEFT JOIN {$eavAttributeGroup} eag ON aea.attribute_group_id = eag.attribute_group_id
        GROUP BY eav.attribute_id
        ORDER BY eav.attribute_code
        SQL;

        $connection->query($sql);
        $this->logger->info('MageOS_EavDebugViews: View dev_product_attribute created');
    }

    /**
     * Get dependencies
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
