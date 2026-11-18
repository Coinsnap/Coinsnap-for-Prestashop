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
// Run from the module folder: php tests/InvoiceDecisionTest.php
// Runs on the oldest supported PHP (7.4) as well as current PHP.
if (PHP_SAPI !== 'cli') {
    exit;
}
define('_PS_VERSION_', 'test');
require __DIR__ . '/../classes/CoinsnapInvoiceDecision.php';
require __DIR__ . '/../classes/CoinsnapWebhookSignature.php';
require __DIR__ . '/../classes/CoinsnapBtcpayKey.php';

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

$D = 'CoinsnapInvoiceDecision';
check('coinsnap settled exact', $D::decide(['status' => 'Settled', 'invoiceAmount' => 100.00, 'currency' => 'EUR', 'amount' => 150000], false, '100.00', 'EUR') === $D::PAID);
check('coinsnap settled string amount', $D::decide(['status' => 'Settled', 'invoiceAmount' => '1234.56', 'currency' => 'eur'], false, '1234.56', 'EUR') === $D::PAID);
check('fiatAmount fallback', $D::decide(['status' => 'Settled', 'fiatAmount' => '100', 'currency' => 'EUR'], false, '100.00', 'EUR') === $D::PAID);
check('underpaid', $D::decide(['status' => 'Settled', 'invoiceAmount' => 99.98, 'currency' => 'EUR'], false, '100.00', 'EUR') === $D::MISMATCH);
check('wrong currency', $D::decide(['status' => 'Settled', 'invoiceAmount' => 100, 'currency' => 'USD'], false, '100.00', 'EUR') === $D::MISMATCH);
check('no amount', $D::decide(['status' => 'Settled', 'currency' => 'EUR'], false, '100.00', 'EUR') === $D::MISMATCH);
check('sats amount not used for coinsnap', $D::decide(['status' => 'Settled', 'amount' => 100, 'currency' => 'EUR'], false, '100.00', 'EUR') === $D::MISMATCH);
check('zero price never paid', $D::decide(['status' => 'Settled', 'invoiceAmount' => 0, 'currency' => 'EUR'], false, '0.00', 'EUR') === $D::MISMATCH);
check('processing', $D::decide(['status' => 'Processing'], false, '100.00', 'EUR') === $D::PROCESSING);
check('expired', $D::decide(['status' => 'Expired', 'additionalStatus' => 'Underpaid'], false, '100.00', 'EUR') === $D::EXPIRED);
check('new', $D::decide(['status' => 'New'], false, '1.00', 'EUR') === $D::NONE);
check('invalid', $D::decide(['status' => 'Invalid'], false, '1.00', 'EUR') === $D::NONE);
check('missing status', $D::decide([], false, '1.00', 'EUR') === $D::NONE);
check('btcpay settled', $D::decide(['status' => 'Settled', 'amount' => '100.00', 'currency' => 'EUR', 'additionalStatus' => 'PaidOver'], true, '100.00', 'EUR') === $D::PAID);
check('btcpay marked refused', $D::decide(['status' => 'Settled', 'amount' => '100.00', 'currency' => 'EUR', 'additionalStatus' => 'Marked'], true, '100.00', 'EUR') === $D::MISMATCH);
check('btcpay btc 8 dp', $D::decide(['status' => 'Settled', 'amount' => '0.00123456', 'currency' => 'BTC'], true, '0.00123456', 'BTC') === $D::PAID);
check('toMinorUnits null', $D::toMinorUnits(null) === null && $D::toMinorUnits('abc') === null);
check('toMinorUnits string', $D::toMinorUnits('1.5') === 150000000);

$body = '{"invoiceId":"abc","type":"Settled"}';
$now = 1800000000;
$sig = 't=' . $now . ',v1=' . hash_hmac('sha256', $now . '.' . $body, 'secret');
check('signature valid', CoinsnapWebhookSignature::coinsnapProblem($sig, $body, 'secret', $now) === null);
check('signature wrong secret', CoinsnapWebhookSignature::coinsnapProblem($sig, $body, 'other', $now) !== null);
check('signature stale', CoinsnapWebhookSignature::coinsnapProblem($sig, $body, 'secret', $now + 600) !== null);
check('btcpay key store-only', CoinsnapBtcpayKey::isAcceptable(['btcpay.store.cancreateinvoice:S1'], 'S1'));
check('btcpay key unrestricted', !CoinsnapBtcpayKey::isAcceptable(['unrestricted'], 'S1'));

echo "PHP " . PHP_VERSION . ": $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
