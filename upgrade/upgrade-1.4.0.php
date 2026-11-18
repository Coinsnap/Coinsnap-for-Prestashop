<?php
/**
 * Copyright since 2023 Coinsnap
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Coinsnap <dev@coinsnap.io>
 * @copyright Since 2023 Coinsnap
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../classes/CoinsnapInvoiceStore.php';

// 1.4.0: invoice table for the webhook checks; the old public log file and Bitcoin discount vouchers removed.
function upgrade_module_1_4_0($module)
{
    // Versions before 1.4.0 wrote webhook bodies with customer names to this web-reachable file.
    $oldLog = dirname(__FILE__) . '/../logs/coinsnap.log';
    if (file_exists($oldLog)) {
        unlink($oldLog);
    }

    // Versions before 1.4.0 added a public "BitcoinDiscount" voucher to every cart on every save.
    $module->unregisterHook('actionCartSave');
    $ruleIds = Db::getInstance()->executeS('SELECT id_cart_rule FROM `' . _DB_PREFIX_ . "cart_rule` WHERE code = 'BitcoinDiscount'");
    foreach ($ruleIds ?: [] as $row) {
        $rule = new CartRule((int) $row['id_cart_rule']);
        $rule->delete();
    }

    // Orders placed before this upgrade have no invoice record. Their invoices are accepted for one
    // day, by then every invoice opened before the upgrade has expired.
    Configuration::updateGlobalValue('COINSNAP_LEGACY_UNTIL', time() + 86400);

    return CoinsnapInvoiceStore::createTable();
}
