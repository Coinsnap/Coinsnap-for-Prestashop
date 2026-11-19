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

/**
 * The metadata sent with a new Coinsnap or BTCPay invoice, built from plain order values
 * (see Coinsnap::invoiceOrderData()), so the merchant can find the order from the invoice.
 *
 * orderId stays the PrestaShop order ID: the webhook loads the order by it.
 */
class CoinsnapInvoiceData
{
    const MAX_ITEM_DESC = 255;

    public static function metadata(array $order): array
    {
        $orderId = (string) $order['id'];
        $customer = isset($order['customer']) && is_array($order['customer']) ? $order['customer'] : [];
        $address = isset($order['address']) && is_array($order['address']) ? $order['address'] : [];

        $name = self::join([self::text($address, 'firstname'), self::text($address, 'lastname')]);
        if ($name === '') {
            $name = self::join([self::text($customer, 'firstname'), self::text($customer, 'lastname')]);
        }
        $phone = self::text($address, 'phone') !== '' ? self::text($address, 'phone') : self::text($address, 'phone_mobile');

        $lineItems = [];
        foreach (isset($order['products']) && is_array($order['products']) ? $order['products'] : [] as $product) {
            $quantity = (float) ($product['quantity'] ?? 0);
            $lineItems[] = self::withoutEmpty([
                'name' => self::text($product, 'name'),
                'sku' => self::text($product, 'sku'),
                'productId' => self::text($product, 'productId'),
                'quantity' => floor($quantity) === $quantity ? (int) $quantity : $quantity,
                'price' => self::amount($product['price'] ?? 0),
                'total' => self::amount($product['total'] ?? 0),
            ]);
        }
        $names = [];
        $skus = [];
        foreach ($lineItems as $item) {
            $names[] = $item['quantity'] . ' x ' . ($item['name'] ?? '');
            if (isset($item['sku'])) {
                $skus[] = $item['sku'];
            }
        }
        $codes = isset($order['couponCodes']) && is_array($order['couponCodes']) ? $order['couponCodes'] : [];

        return self::withoutEmpty([
            'orderId' => $orderId,
            'orderNumber' => self::text($order, 'reference'),
            'orderUrl' => self::text($order, 'orderUrl'),
            'platform' => 'prestashop',
            'platformVersion' => self::text($order, 'platformVersion'),
            'pluginVersion' => self::text($order, 'pluginVersion'),
            'customerName' => $name,
            'buyerName' => $name,
            'buyerEmail' => self::text($customer, 'email'),
            'buyerCompany' => self::text($address, 'company'),
            'buyerPhone' => $phone,
            'buyerAddress1' => self::text($address, 'address1'),
            'buyerAddress2' => self::text($address, 'address2'),
            'buyerCity' => self::text($address, 'city'),
            'buyerZip' => self::text($address, 'postcode'),
            'buyerState' => self::text($address, 'state'),
            'buyerCountry' => self::text($address, 'country'),
            'buyerMessage' => self::text($order, 'message'),
            'itemDesc' => self::truncate($names !== [] ? implode(', ', $names) : 'Order ' . $orderId),
            'itemCode' => implode(', ', array_values(array_unique($skus))),
            'lineItems' => $lineItems,
            'couponCodes' => implode(', ', array_values(array_unique(array_filter(array_map('strval', $codes), 'strlen')))),
            'discountTotal' => self::amount($order['discountTotal'] ?? 0),
            'shippingMethod' => self::text($order, 'shippingMethod'),
            'shippingTotal' => self::amount($order['shippingTotal'] ?? 0),
            'taxTotal' => self::amount($order['taxTotal'] ?? 0),
            'language' => self::text($order, 'language'),
        ]);
    }

    private static function text(array $values, string $key): string
    {
        return isset($values[$key]) && is_scalar($values[$key]) ? trim((string) $values[$key]) : '';
    }

    private static function join(array $parts): string
    {
        return trim(implode(' ', array_filter($parts, 'strlen')));
    }

    private static function amount($value): float
    {
        return round((float) $value, 2);
    }

    // Empty strings, nulls and empty lists are left out; zero amounts are kept.
    private static function withoutEmpty(array $values): array
    {
        return array_filter($values, function ($value) {
            return $value !== null && $value !== '' && $value !== [];
        });
    }

    private static function truncate(string $text): string
    {
        return mb_strlen($text) > self::MAX_ITEM_DESC ? mb_substr($text, 0, self::MAX_ITEM_DESC - 3) . '...' : $text;
    }
}
