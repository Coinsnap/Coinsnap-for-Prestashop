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
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

// Maps the invoice as the API reports it to what should happen to the order.
class CoinsnapInvoiceDecision
{
    public const PAID = 'paid';
    public const PROCESSING = 'processing';
    public const EXPIRED = 'expired';
    public const NONE = 'none';
    // Settled, but not for the amount and currency this plugin asked for (or settled by hand in BTCPay).
    public const MISMATCH = 'mismatch';

    // $expectedAmount and $expectedCurrency are what the plugin sent when it created the invoice.
    public static function decide(array $invoice, bool $isBtcpay, string $expectedAmount, string $expectedCurrency): string
    {
        $status = (string) ($invoice['status'] ?? '');
        $additionalStatus = (string) ($invoice['additionalStatus'] ?? '');

        if ($status === 'Settled') {
            $paidAmount = $isBtcpay ? ($invoice['amount'] ?? null) : ($invoice['invoiceAmount'] ?? $invoice['fiatAmount'] ?? null);
            $markedByHand = $isBtcpay && $additionalStatus === 'Marked';

            return !$markedByHand && self::sameAmount($paidAmount, $invoice['currency'] ?? null, $expectedAmount, $expectedCurrency)
                ? self::PAID
                : self::MISMATCH;
        }

        switch ($status) {
            case 'Processing':
                return self::PROCESSING;
            case 'Expired':
                return self::EXPIRED;
            default:
                return self::NONE;
        }
    }

    // $paidAmount and $paidCurrency come straight from the API response (string, number or null).
    // No `mixed` type: the module still runs on PHP 7.4, where `mixed` would be read as a class name.
    private static function sameAmount($paidAmount, $paidCurrency, string $expectedAmount, string $expectedCurrency): bool
    {
        $expected = self::toMinorUnits($expectedAmount);
        $paid = self::toMinorUnits($paidAmount);
        if ($expected === null || $expected <= 0 || $paid === null) {
            return false;
        }
        if (strtoupper((string) $paidCurrency) !== strtoupper($expectedCurrency)) {
            return false;
        }

        // One unit of the 8th decimal covers rounding for BTC amounts and is far below a cent.
        return abs($paid - $expected) <= self::tolerance($expectedCurrency);
    }

    private static function tolerance(string $currency): int
    {
        return strtoupper($currency) === 'BTC' ? 1 : 1_000_000;
    }

    // Amount as an integer of 10^-8 units, parsed from the decimal string so it is never compared as a float.
    public static function toMinorUnits($value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $value = number_format((float) $value, 8, '.', '');
        }
        if (!is_string($value) || !preg_match('/^(\d+)(?:\.(\d+))?$/', trim($value), $match)) {
            return null;
        }
        $fraction = str_pad($match[2] ?? '', 9, '0');
        $roundUp = (int) $fraction[8] >= 5 ? 1 : 0;

        return (int) $match[1] * 100_000_000 + (int) substr($fraction, 0, 8) + $roundUp;
    }
}
