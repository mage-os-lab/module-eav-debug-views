<?php
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

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'MageOS_EavDebugViews',
    __DIR__
);
