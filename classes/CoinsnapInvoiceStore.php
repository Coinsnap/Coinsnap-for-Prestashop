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

// Invoices this module created: which order each pays for, the amount and currency sent, and whether it was applied.
class CoinsnapInvoiceStore
{
    public const TABLE = 'coinsnap_invoice';

    public static function createTable(): bool
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `invoice_id` VARCHAR(64) NOT NULL,
                `id_order` INT UNSIGNED NOT NULL,
                `amount` VARCHAR(32) NOT NULL,
                `currency` VARCHAR(8) NOT NULL,
                `paid_at` DATETIME NULL,
                PRIMARY KEY (`invoice_id`),
                KEY `id_order` (`id_order`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function dropTable(): bool
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    public static function remember(string $invoiceId, int $idOrder, string $amount, string $currency): bool
    {
        return Db::getInstance()->insert(self::TABLE, [
            'invoice_id' => pSQL($invoiceId),
            'id_order' => $idOrder,
            'amount' => pSQL($amount),
            'currency' => pSQL($currency),
        ], false, true, Db::INSERT_IGNORE);
    }

    // The recorded invoice, or null when it was not created for this order.
    public static function find(string $invoiceId, int $idOrder): ?array
    {
        $row = Db::getInstance()->getRow(
            'SELECT `amount`, `currency`, `paid_at` FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `invoice_id` = \'' . pSQL($invoiceId) . '\' AND `id_order` = ' . (int) $idOrder
        );
        return is_array($row) ? $row : null;
    }

    // Orders placed before 1.4.0 have no rows.
    public static function orderHasInvoices(int $idOrder): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_order` = ' . (int) $idOrder
        );
    }

    // True for the first caller only, so two webhooks for one invoice can't both mark the order paid.
    public static function claimPaid(string $invoiceId, int $idOrder, string $amount, string $currency): bool
    {
        $db = Db::getInstance();
        $db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . self::TABLE . '` (`invoice_id`, `id_order`, `amount`, `currency`, `paid_at`)
             VALUES (\'' . pSQL($invoiceId) . '\', ' . (int) $idOrder . ', \'' . pSQL($amount) . '\', \'' . pSQL($currency) . '\', NOW())'
        );
        if ($db->Affected_Rows() === 1) {
            return true;
        }
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . self::TABLE . '` SET `paid_at` = NOW()
             WHERE `invoice_id` = \'' . pSQL($invoiceId) . '\' AND `id_order` = ' . (int) $idOrder . ' AND `paid_at` IS NULL'
        );
        return $db->Affected_Rows() === 1;
    }
}
