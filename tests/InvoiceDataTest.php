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
// Run from the module folder: php tests/InvoiceDataTest.php
// Captures the create-invoice request (orderId + metadata) sent through the bundled library.
// Runs on the oldest supported PHP (7.4) as well as current PHP.
if (PHP_SAPI !== 'cli') {
    exit;
}
define('_PS_VERSION_', 'test');
require __DIR__ . '/../library/loader.php';
require __DIR__ . '/../classes/CoinsnapInvoiceData.php';

$pass = 0;
$fail = 0;
function check($name, $cond)
{
    global $pass, $fail;
    if ($cond) {
        ++$pass;
    } else {
        ++$fail;
        echo "FAIL: $name\n";
    }
}

class RecordingHttpClient implements \Coinsnap\Http\ClientInterface
{
    public $requests = [];

    public function request(string $method, string $url, array $headers = [], string $body = ''): \Coinsnap\Http\ResponseInterface
    {
        $this->requests[] = ['url' => $url, 'body' => json_decode($body, true)];
        return new \Coinsnap\Http\Response(200, '{"id":"inv_1","checkoutLink":"https://pay.example/i/inv_1"}', []);
    }
}

// As Coinsnap::invoiceOrderData() returns it for a real order.
function orderData($withBuyer = true)
{
    return [
        'id' => 12,
        'reference' => 'XKBKNABJK',
        'orderUrl' => $withBuyer ? 'https://shop.example/admin123/index.php?controller=AdminOrders&id_order=12&vieworder' : '',
        'platformVersion' => '8.2.1',
        'pluginVersion' => '1.4.0',
        'language' => 'en',
        'customer' => $withBuyer ? ['firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com'] : [],
        'address' => $withBuyer ? [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'company' => '', 'phone' => '', 'phone_mobile' => '+33 6 12 34',
            'address1' => '1 Rue de Rivoli', 'address2' => 'Bat. B', 'city' => 'Paris', 'postcode' => '75001', 'state' => '', 'country' => 'FR',
        ] : [],
        'message' => $withBuyer ? 'Leave at the door' : '',
        'products' => [
            ['name' => 'Mug - Color : White', 'sku' => 'demo_12', 'productId' => '12', 'quantity' => '2', 'price' => '14.28', 'total' => '28.56'],
            ['name' => 'Poster', 'sku' => '', 'productId' => '4', 'quantity' => '1', 'price' => '34.80', 'total' => '34.80'],
        ],
        'couponCodes' => $withBuyer ? ['AUTUMN10', ''] : [],
        'discountTotal' => $withBuyer ? '6.34' : '0.000000',
        'shippingMethod' => 'My carrier',
        'shippingTotal' => '8.400000',
        'taxTotal' => 10.9,
    ];
}

function send(array $metadata, string $apiUrl, $email, $name)
{
    $http = new RecordingHttpClient();
    (new \Coinsnap\Client\Invoice($apiUrl, 'test-key', $http))->createInvoice(
        'store-1', 'EUR', \Coinsnap\Util\PreciseNumber::parseFloat(65.42, 2), '12', $email, $name,
        'https://shop.example/order-confirmation', 'D14567', $metadata, true, ''
    );
    return $http->requests[0]['body'];
}

foreach (['coinsnap' => 'https://app.coinsnap.io', 'btcpay' => 'https://btcpay.example'] as $provider => $apiUrl) {
    $body = send(CoinsnapInvoiceData::metadata(orderData()), $apiUrl, 'ada@example.com', 'Ada Lovelace');
    $m = $body['metadata'];

    // The webhook loads the order by these: top-level orderId (Coinsnap), metadata.orderId (BTCPay).
    check("$provider top-level orderId", $body['orderId'] === '12');
    check("$provider metadata.orderId", $m['orderId'] === '12');
    check("$provider buyerEmail", $body['buyerEmail'] === 'ada@example.com');

    $expected = [
        'orderId' => '12',
        'orderNumber' => 'XKBKNABJK',
        'orderUrl' => 'https://shop.example/admin123/index.php?controller=AdminOrders&id_order=12&vieworder',
        'platform' => 'prestashop',
        'platformVersion' => '8.2.1',
        'pluginVersion' => '1.4.0',
        'customerName' => 'Ada Lovelace',
        'buyerName' => 'Ada Lovelace',
        'buyerEmail' => 'ada@example.com',
        'buyerPhone' => '+33 6 12 34',
        'buyerAddress1' => '1 Rue de Rivoli',
        'buyerAddress2' => 'Bat. B',
        'buyerCity' => 'Paris',
        'buyerZip' => '75001',
        'buyerCountry' => 'FR',
        'buyerMessage' => 'Leave at the door',
        'itemDesc' => '2 x Mug - Color : White, 1 x Poster',
        'itemCode' => 'demo_12',
        'lineItems' => [
            ['name' => 'Mug - Color : White', 'sku' => 'demo_12', 'productId' => '12', 'quantity' => 2, 'price' => 14.28, 'total' => 28.56],
            ['name' => 'Poster', 'productId' => '4', 'quantity' => 1, 'price' => 34.8, 'total' => 34.8],
        ],
        'couponCodes' => 'AUTUMN10',
        'discountTotal' => 6.34,
        'shippingMethod' => 'My carrier',
        'shippingTotal' => 8.4,
        'taxTotal' => 10.9,
        'language' => 'en',
    ];
    foreach ($expected as $key => $value) {
        check("$provider metadata.$key", array_key_exists($key, $m) && $m[$key] == $value);
    }
    check("$provider no other keys", array_diff(array_keys($m), array_keys($expected)) === []);
}

// Empty values are left out, the order is still identified.
$m = CoinsnapInvoiceData::metadata(orderData(false));
$empty = array_filter($m, function ($v) {
    return $v === '' || $v === null || $v === [];
});
check('guest: no empty values', $empty === []);
check('guest: no buyer keys', preg_grep('/^(buyer|customerName)/', array_keys($m)) === []);
check('guest: orderId and number', $m['orderId'] === '12' && $m['orderNumber'] === 'XKBKNABJK');
check('guest: zero discount kept as number', $m['discountTotal'] === 0.0);
check('long item list is cut to 255', mb_strlen(CoinsnapInvoiceData::metadata(['id' => 1, 'products' => array_fill(0, 40, ['name' => 'Long product name', 'quantity' => 1])])['itemDesc']) === 255);

echo "PHP " . PHP_VERSION . ": $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
