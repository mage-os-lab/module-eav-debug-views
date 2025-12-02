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

namespace MageOS\EavDebugViews\Setup;

use Exception;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Psr\Log\LoggerInterface;

class Uninstall implements UninstallInterface
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Uninstall module - drop all views
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function uninstall(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ): void {
        $setup->startSetup();

        $connection = $setup->getConnection();
        $views = [
            'dev_address',
            'dev_category',
            'dev_customer',
            'dev_product',
            'dev_product_attribute',
        ];

        foreach ($views as $view) {
            try {
                $connection->query("DROP VIEW IF EXISTS {$view}");
            } catch (Exception $e) {
                $this->logger->warning(
                    "MageOS_EavDebugViews: Could not drop view {$view}: " . $e->getMessage()
                );
            }
        }

        $this->logger->info('MageOS_EavDebugViews: Uninstall complete');
        $setup->endSetup();
    }
}
