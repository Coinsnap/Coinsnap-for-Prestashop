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
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Coinsnap\Client\Webhook;

require_once(dirname(__FILE__) . '/library/loader.php');
require_once(dirname(__FILE__) . '/classes/CoinsnapWebhookSignature.php');
require_once(dirname(__FILE__) . '/classes/CoinsnapInvoiceDecision.php');
require_once(dirname(__FILE__) . '/classes/CoinsnapInvoiceStore.php');
require_once(dirname(__FILE__) . '/classes/CoinsnapBtcpayKey.php');
require_once(dirname(__FILE__) . '/classes/CoinsnapInvoiceData.php');
if (!defined('COINSNAP_SERVER_PATH')) {
    define('COINSNAP_SERVER_PATH', 'stores');
}

class Coinsnap extends PaymentModule
{
    public string $referralCode;
    public string $meta_title;
    public string $provider;
    public string $api_url;
    public string $store_id;
    public string $api_key;
    public string $status_new;
    public string $status_expired;
    public string $status_settled;
    public string $status_processing;
    public bool $is_eu_compatible;
    public bool $autoredirect;
    public bool $discount_enabled;
    public string $discount_type;
    public float $discount_amount;
    public float $discount_amount_limit;
    public float $discount_percentage;
    public string $returnurl;
    public string $webhook_url;
    public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];
    public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing'];

    public function __construct()
    {
        $this->name = 'coinsnap';
        $this->tab = 'payments_gateways';
        $this->version = '1.4.0';
        $this->author = 'Coinsnap';
        $this->need_instance = 1;

        $this->bootstrap = true;
        $this->module_key = '26e3f9b88be0664784deee6be20e4b7b';
        $this->referralCode = 'D14567';

        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_
        );

        parent::__construct();

        $this->meta_title = $this->trans('Coinsnap', [], 'Modules.coinsnap.Admin');
        $this->displayName = $this->trans('Coinsnap', [], 'Modules.coinsnap.Admin');
        $this->description = $this->trans('Accept Bitcoin and Lightning payments via Coinsnap and BTCPay payment gateways', [], 'Modules.coinsnap.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall Coinsnap payment module?', [], 'Modules.coinsnap.Admin');
        $this->is_eu_compatible = true;

        $this -> provider = (Configuration::get('COINSNAP_PROVIDER') === 'btcpay') ? 'btcpay' : 'coinsnap';

        $this -> api_url = ($this -> provider === 'coinsnap') ? 'https://app.coinsnap.io' : Configuration::get('BTCPAY_SERVER_URL');
        $this -> store_id = ($this -> provider === 'coinsnap') ? Configuration::get('COINSNAP_STORE_ID') : Configuration::get('BTCPAY_STORE_ID');
        $this -> api_key = ($this -> provider === 'coinsnap') ? Configuration::get('COINSNAP_API_KEY') : Configuration::get('BTCPAY_API_KEY');

        $this -> autoredirect = (Configuration::get('COINSNAP_AUTOREDIRECT') > 0)? true : false;
        $this -> returnurl = Configuration::get('COINSNAP_RETURNURL');
        
        $this -> discount_enabled = Configuration::get('COINSNAP_DISCOUNT_ENABLED');
        $this -> discount_type = Configuration::get('COINSNAP_DISCOUNT_TYPE');
        $this -> discount_amount = (float)Configuration::get('COINSNAP_DISCOUNT_AMOUNT');
        $this -> discount_amount_limit = (float)Configuration::get('COINSNAP_DISCOUNT_LIMIT');
        $this -> discount_percentage = (float)Configuration::get('COINSNAP_DISCOUNT_PERCENTAGE');

        $this -> status_new = Configuration::get('COINSNAP_STATUS_NEW');
        $this -> status_expired = Configuration::get('COINSNAP_STATUS_EXP');
        $this -> status_settled = Configuration::get('COINSNAP_STATUS_SET');
        $this -> status_processing = Configuration::get('COINSNAP_STATUS_PRO');

        $this -> webhook_url = $this->context->link->getModuleLink('coinsnap', 'notify');


    }

    public function install()
    {

        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook('paymentOptions')) {
            return false;
        }

        if (!$this->registerHook('actionAdminControllerSetMedia')) {
            return false;
        }

        // A fresh install has no orders from before the invoice records existed.
        Configuration::updateGlobalValue('COINSNAP_LEGACY_UNTIL', 0);

        return CoinsnapInvoiceStore::createTable();
    }
    
    // The Bitcoin discount, as a one-time cart rule for this customer, applied only when paying with Coinsnap.
    private function applyBitcoinDiscount(Cart $cart): void
    {
        $this->removeBitcoinDiscount($cart);
        if (!$this->discount_enabled) {
            return;
        }
        $total = (float) $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
        $reductionAmount = 0.0;
        $reductionPercent = 0.0;
        $title = '';

        if ($this->discount_type === 'fixed' && $this->discount_amount > 0) {
            $reductionAmount = round($this->discount_amount, 2);
            $limit = $this->discount_amount_limit;
            if ($limit < 0 || $limit >= 100) {
                return;
            }
            $reductionAmount = min($reductionAmount, round($total * $limit / 100, 2));
            if ($reductionAmount <= 0 || $reductionAmount >= $total) {
                return;
            }
        }
        elseif ($this->discount_type !== 'fixed' && $this->discount_percentage > 0 && $this->discount_percentage < 100) {
            $reductionPercent = $this->discount_percentage;
            $title = ' ' . $reductionPercent . '%';
        }
        else {
            return;
        }

        $cartRule = new CartRule();
        $cartRule->name = array_fill_keys(Language::getIDs(false), 'Bitcoin discount' . $title);
        // Unguessable, single use, this customer only, short-lived: it cannot be entered as a voucher elsewhere.
        $cartRule->code = 'CSBTC-' . Tools::strtoupper(Tools::passwdGen(16));
        $cartRule->id_customer = (int) $cart->id_customer;
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->highlight = false;
        $cartRule->partial_use = false;
        $cartRule->date_from = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $cartRule->active = true;
        if ($reductionAmount > 0) {
            $cartRule->reduction_amount = $reductionAmount;
            $cartRule->reduction_currency = (int) $cart->id_currency;
        }
        else {
            $cartRule->reduction_percent = $reductionPercent;
        }
        if ($cartRule->add()) {
            $cart->addCartRule((int) $cartRule->id);
        }
    }

    // Drops discounts from earlier attempts, so a cart never keeps one it did not pay for with Coinsnap.
    private function removeBitcoinDiscount(Cart $cart): void
    {
        foreach ($cart->getCartRules() as $rule) {
            if (strpos((string) ($rule['code'] ?? ''), 'CSBTC-') !== 0) {
                continue;
            }
            $cart->removeCartRule((int) $rule['id_cart_rule']);
            (new CartRule((int) $rule['id_cart_rule']))->delete();
        }
    }

    public function hookActionAdminControllerSetMedia(array $params)
    {
        $this->context->controller->addJs($this->getPathUri() . 'views/js/coinsnap.js');
    }

    public function hookPaymentOptions($params)
    {
        // The Bitcoin discount is added only while a Coinsnap payment starts. One still on the cart
        // here is left over from an attempt that failed, and would apply to any payment method.
        if (isset($params['cart']) && $params['cart'] instanceof Cart && $params['cart']->id) {
            $this->removeBitcoinDiscount($params['cart']);
        }
        return $this->coinsnapPaymentOptions($params);
    }

    // Webhook endpoint. Only the invoice id is taken from the request; every decision comes from the API.
    public function returnsuccess()
    {
        $rawPostData = (string) Tools::file_get_contents('php://input');
        $webhook = json_decode((string) Configuration::get('COINSNAP_WEBHOOK'), true);
        $secret = is_array($webhook) ? (string) ($webhook['secret'] ?? '') : '';

        $problem = ($this->provider === 'btcpay')
            ? CoinsnapWebhookSignature::btcpayProblem($this->requestHeader('BTCPay-Sig'), $rawPostData, $secret)
            : CoinsnapWebhookSignature::coinsnapProblem($this->requestHeader('X-Coinsnap-Signature'), $rawPostData, $secret, time());
        if ($problem !== null) {
            $this->logWebhookSecurity($problem);
            $this->webhookReply(401, 'Unauthorized');
        }

        $body = json_decode($rawPostData, true);
        $invoiceId = (is_array($body) && is_string($body['invoiceId'] ?? null)) ? $body['invoiceId'] : '';
        if ($invoiceId === '') {
            $this->webhookReply(200, 'OK');
        }
        $claimsPaid = in_array($body['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        try {
            $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
            $invoice = $client->getInvoice($this->store_id, $invoiceId)->getData();
        } catch (\Coinsnap\Exception\RequestException $e) {
            if (in_array($e->getCode(), [400, 404], true)) {
                // The dashboard's "Send test webhook" button lands here.
                $this->logWebhookInfo('unknown invoice', $invoiceId);
                $this->webhookReply(200, 'OK');
            }
            $this->logWebhookInfo('retry: API unavailable', $invoiceId);
            $this->webhookReply(503, 'Retry');
        } catch (\Throwable $e) {
            $this->logWebhookInfo('retry: API unavailable', $invoiceId);
            $this->webhookReply(503, 'Retry');
        }

        $order = $this->findWebhookOrder($invoice);
        if ($order === null) {
            $this->logWebhookInfo('not ours', $invoiceId);
            $this->webhookReply(200, 'OK');
        }
        $expected = $this->expectedPayment($order, $invoiceId);
        if ($expected === null) {
            $this->logWebhookSecurity('invoice not created for this order', $invoiceId);
            $this->webhookReply(200, 'OK');
        }

        $decision = CoinsnapInvoiceDecision::decide($invoice, $this->provider === 'btcpay', $expected['amount'], $expected['currency']);
        if ($decision !== CoinsnapInvoiceDecision::PAID && ($claimsPaid || $decision === CoinsnapInvoiceDecision::MISMATCH)) {
            $this->logWebhookSecurity($claimsPaid ? 'forged status' : 'amount mismatch', $invoiceId);
            $this->webhookReply(200, 'OK');
        }

        $this->applyInvoiceDecision($decision, $order, $invoiceId, $expected);
        $this->webhookReply(200, 'OK');
    }

    private function requestHeader(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
    }

    protected function webhookReply(int $status, string $message)
    {
        http_response_code($status);
        die($message);
    }

    // The order this module created the invoice for, or null when the invoice belongs to something else.
    private function findWebhookOrder(array $invoice)
    {
        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $orderId = ($this->provider === 'btcpay') ? ($metadata['orderId'] ?? '') : ($invoice['orderId'] ?? '');
        if (!is_scalar($orderId) || !ctype_digit((string) $orderId)) {
            return null;
        }
        $order = new Order((int) $orderId);
        if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return null;
        }
        return $order;
    }

    // Amount and currency sent when the invoice was created; null when this order never had that invoice.
    private function expectedPayment(Order $order, string $invoiceId)
    {
        $recorded = CoinsnapInvoiceStore::find($invoiceId, (int) $order->id);
        if ($recorded !== null) {
            return $recorded;
        }
        if (CoinsnapInvoiceStore::orderHasInvoices((int) $order->id)) {
            return null;
        }
        // Orders placed before 1.4.0 have no record. They are accepted only for a day after the
        // upgrade, when every invoice opened before it has expired; after that, any invoice with
        // the same order ID and amount could pay them.
        $legacyUntil = (int) Configuration::getGlobalValue('COINSNAP_LEGACY_UNTIL');
        if (time() > $legacyUntil || strtotime((string) $order->date_add) > $legacyUntil - 86400) {
            return null;
        }
        $currency = new Currency((int) $order->id_currency);
        return ['amount' => number_format((float) $order->total_paid, 2, '.', ''), 'currency' => (string) $currency->iso_code];
    }

    // Moves an order forward only: an order that left the awaiting states never changes from a webhook.
    private function applyInvoiceDecision(string $decision, Order $order, string $invoiceId, array $expected)
    {
        $state = (int) $order->getCurrentState();
        $new = (int) $this->status_new;
        $processing = (int) $this->status_processing;
        $expired = (int) $this->status_expired;
        // From the module's own records: processing and paid may be the same status ID.
        $alreadyPaid = CoinsnapInvoiceStore::orderPaid((int) $order->id);

        if ($decision === CoinsnapInvoiceDecision::PAID) {
            if ($alreadyPaid) {
                // A second invoice paid for an order another invoice already paid: record it, change nothing.
                $first = CoinsnapInvoiceStore::claimPaid($invoiceId, (int) $order->id, $expected['amount'], $expected['currency']);
                $this->logWebhookInfo($first ? 'order already paid by another invoice' : 'already processed', $invoiceId);
                return;
            }
            // A late payment can still settle an order that expired.
            if (!in_array($state, [$new, $processing, $expired], true)) {
                $this->logWebhookInfo('no change from state ' . $state, $invoiceId);
                return;
            }
            if (!CoinsnapInvoiceStore::claimPaid($invoiceId, (int) $order->id, $expected['amount'], $expected['currency'])) {
                $this->logWebhookInfo('already processed', $invoiceId);
                return;
            }
            $this->setOrderStatus($order->id, $this->status_settled, $invoiceId);
            $this->logWebhookInfo('paid', $invoiceId);
            return;
        }
        if ($alreadyPaid) {
            $this->logWebhookInfo('no change: order paid', $invoiceId);
            return;
        }
        if ($decision === CoinsnapInvoiceDecision::PROCESSING && $state === $new && $processing !== $new) {
            // Processing is seen but not confirmed; it must not use a status that counts as paid or shipped.
            if (!self::isAwaitingState($processing)) {
                $this->logWebhookInfo('no change: processing status is a paid status', $invoiceId);
                return;
            }
            $this->setOrderStatus($order->id, $this->status_processing, $invoiceId);
            $this->logWebhookInfo('status processing', $invoiceId);
            return;
        }
        if ($decision === CoinsnapInvoiceDecision::EXPIRED && in_array($state, [$new, $processing], true)) {
            $this->setOrderStatus($order->id, $this->status_expired, $invoiceId);
            $this->logWebhookInfo('status expired', $invoiceId);
            return;
        }
        $this->logWebhookInfo('no change', $invoiceId);
    }

    // An order status that neither counts as paid nor ships goods.
    public static function isAwaitingState(int $idOrderState): bool
    {
        $state = new OrderState($idOrderState);
        return Validate::isLoadedObject($state) && !$state->paid && !$state->shipped && !$state->delivery;
    }

    // Module configuration is changed only by an employee allowed to configure this module, with a valid token.
    private function employeeMayConfigure(): bool
    {
        $employee = $this->context->employee ?? null;
        if (!$employee || !Validate::isLoadedObject($employee)) {
            return false;
        }
        // The form's own token, so the check holds even where the shop turned off tokens in admin URLs.
        $token = isset($_POST['coinsnap_form_token']) ? (string) $_POST['coinsnap_form_token'] : '';
        if ($token === '' || !hash_equals($this->configFormToken(), $token)) {
            return false;
        }
        return (bool) Module::getPermissionStatic($this->id, 'configure', $employee);
    }

    private function configFormToken(): string
    {
        return Tools::getAdminToken('CoinsnapConfig' . (int) $this->context->employee->id);
    }

    private function logWebhookInfo(string $reason, string $invoiceId = '')
    {
        PrestaShopLogger::addLog($this->webhookLogLine('INFO', $reason, $invoiceId), 1);
    }

    // At most 10 security lines a minute, so a flood of fake requests can't fill the log table.
    private function logWebhookSecurity(string $reason, string $invoiceId = '')
    {
        $minute = gmdate('YmdHi');
        $stored = explode(':', (string) Configuration::getGlobalValue('COINSNAP_SECLOG'));
        $count = ($stored[0] === $minute) ? (int) ($stored[1] ?? 0) + 1 : 1;
        Configuration::updateGlobalValue('COINSNAP_SECLOG', $minute . ':' . $count);
        if ($count === 11) {
            PrestaShopLogger::addLog('Coinsnap SECURITY webhook: more rejections this minute, not logged', 2);
        }
        if ($count <= 10) {
            PrestaShopLogger::addLog($this->webhookLogLine('SECURITY', $reason, $invoiceId), 2);
        }
    }

    private function webhookLogLine(string $level, string $reason, string $invoiceId): string
    {
        // The invoice id comes from the request.
        $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceId), 0, 64);
        return sprintf('Coinsnap %s webhook reason=%s invoice=%s', $level, $reason, $id !== '' ? $id : '-');
    }

    /**
     * Uninstall and clean the module settings
     *
     * @return	bool
     */
    public function uninstall()
    {
        parent::uninstall();
        Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'module_country` WHERE `id_module` = '.(int)$this->id);
        CoinsnapInvoiceStore::dropTable();
        return (true);
    }

    public function getContent()
    {
        $this->rememberAdminUrl();

        if (Tools::isSubmit('submit' . $this->name) && !$this->employeeMayConfigure()) {
            $warning = $this->l('You do not have permission to change these settings.');
        } elseif (Tools::isSubmit('submit' . $this->name)) {

            $coinsnap_name = Tools::getValue('coinsnap_name');
            $saveOpt = false;
            $errorMessage = '';

            $_provider = (Tools::getValue('coinsnap_provider') === 'btcpay') ? 'btcpay' : 'coinsnap';
            $api_url = ($_provider === 'btcpay') ? Tools::getValue('btcpay_server_url') : 'https://app.coinsnap.io';
            $store_id = ($_provider === 'btcpay') ? Tools::getValue('btcpay_store_id') : Tools::getValue('coinsnap_store_id');
            // The key fields are shown empty; leaving one empty keeps the stored key.
            $coinsnap_api_key = (string) Tools::getValue('coinsnap_api_key') !== '' ? (string) Tools::getValue('coinsnap_api_key') : (string) Configuration::get('COINSNAP_API_KEY');
            // The stored BTCPay key is never sent to a server URL it wasn't saved for.
            $btcpayUrlUnchanged = (string) Tools::getValue('btcpay_server_url') === (string) Configuration::get('BTCPAY_SERVER_URL');
            $btcpay_api_key = (string) Tools::getValue('btcpay_api_key') !== '' ? (string) Tools::getValue('btcpay_api_key') : ($btcpayUrlUnchanged ? (string) Configuration::get('BTCPAY_API_KEY') : '');
            $api_key = ($_provider === 'btcpay') ? $btcpay_api_key : $coinsnap_api_key;

            if (empty($api_url)) {
                $errorMessage = 'API URL must have value';
            }
            if (empty($store_id)) {
                $errorMessage = 'Store ID must have value';
            }
            if (empty($api_key)) {
                $errorMessage = 'API Key must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_new'))) {
                $errorMessage = 'Order Status New must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_expired'))) {
                $errorMessage = 'Order Status Expired must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_settled'))) {
                $errorMessage = 'Order Status Settled must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_processing'))) {
                $errorMessage = 'Order Status Processing must have value';
            }

            // Orders are placed in the New status before payment, so New, Processing (not confirmed)
            // and Expired must be statuses that neither count as paid nor ship the goods.
            if (empty($errorMessage)) {
                foreach (['coinsnap_status_new' => 'New', 'coinsnap_status_processing' => 'Processing', 'coinsnap_status_expired' => 'Expired'] as $field => $label) {
                    if (!self::isAwaitingState((int) Tools::getValue($field))) {
                        $errorMessage = 'Order Status ' . $label . ' must not be a paid or shipped status';
                    }
                }
            }

            if (empty($errorMessage) && $_provider === 'btcpay') {
                // A pasted BTCPay key must be limited to this store; BTCPay, not the form, says what it can do.
                try {
                    $permissions = CoinsnapBtcpayKey::fetchPermissions((string) $api_url, (string) $api_key);
                    if ($permissions === null || !CoinsnapBtcpayKey::isAcceptable($permissions, (string) $store_id)) {
                        $errorMessage = 'The BTCPay API key must be limited to this store and may only have store permissions. Create a key for this store only.';
                    }
                } catch (\Throwable $e) {
                    $errorMessage = 'btcpay: the server could not be reached. Please check the server URL and try again.';
                }
            }

            if (empty($errorMessage)) {
                $saveOpt = true;
            }
            if ($saveOpt) {
                try {
                    $webhookExists = $this->webhookExists($api_url, $api_key, $store_id);
                } catch (\Throwable $e) {
                    // An API error is not "no webhook": registering now would replace a working secret.
                    $errorMessage = "$_provider: the server at $api_url could not be reached. Please check the Store ID and API key and try again.";
                    $saveOpt = false;
                }
                if ($saveOpt && !$webhookExists && !$this->registerWebhook($api_url, $api_key, $store_id, $_provider)) {
                    $errorMessage = "$_provider: unable to set the webhook on $api_url. Please check the Store ID and API key.";
                    $saveOpt = false;
                }
            }

            if ($saveOpt) {

                Configuration::updateValue('COINSNAP_PROVIDER', pSQL(Tools::getValue('coinsnap_provider')));
                Configuration::updateValue('COINSNAP_API_KEY', pSQL($coinsnap_api_key));
                Configuration::updateValue('COINSNAP_STORE_ID', pSQL(Tools::getValue('coinsnap_store_id')));
                Configuration::updateValue('BTCPAY_SERVER_URL', pSQL(Tools::getValue('btcpay_server_url')));
                Configuration::updateValue('BTCPAY_API_KEY', pSQL($btcpay_api_key));
                Configuration::updateValue('BTCPAY_STORE_ID', pSQL(Tools::getValue('btcpay_store_id')));
                Configuration::updateValue('COINSNAP_AUTOREDIRECT', pSQL(Tools::getValue('coinsnap_autoredirect')));
                Configuration::updateValue('COINSNAP_RETURNURL', pSQL(Tools::getValue('coinsnap_returnurl')));
                
                Configuration::updateValue('COINSNAP_DISCOUNT_ENABLED', pSQL(Tools::getValue('coinsnap_discount_enabled')));
                Configuration::updateValue('COINSNAP_DISCOUNT_TYPE', pSQL(Tools::getValue('coinsnap_discount_type')));
                Configuration::updateValue('COINSNAP_DISCOUNT_AMOUNT', pSQL(Tools::getValue('coinsnap_discount_amount')));
                Configuration::updateValue('COINSNAP_DISCOUNT_LIMIT', pSQL(Tools::getValue('coinsnap_discount_amount_limit')));
                Configuration::updateValue('COINSNAP_DISCOUNT_PERCENTAGE', pSQL(Tools::getValue('coinsnap_discount_percentage')));
                
                
                Configuration::updateValue('COINSNAP_STATUS_NEW', pSQL(Tools::getValue('coinsnap_status_new')));
                Configuration::updateValue('COINSNAP_STATUS_EXP', pSQL(Tools::getValue('coinsnap_status_expired')));
                Configuration::updateValue('COINSNAP_STATUS_SET', pSQL(Tools::getValue('coinsnap_status_settled')));
                Configuration::updateValue('COINSNAP_STATUS_PRO', pSQL(Tools::getValue('coinsnap_status_processing')));

                $client = new \Coinsnap\Client\Invoice($api_url, $api_key);
                $store = new \Coinsnap\Client\Store($api_url, $api_key);
                $currency = 'EUR';

                $connectionData = '';

                if ($_provider === 'btcpay') {

                    try {
                        $storePaymentMethods = $store->getStorePaymentMethods($store_id);

                        if ($storePaymentMethods['code'] === 200) {
                            if ($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                                $checkInvoice = $client->checkPaymentData(0, $currency, 'bitcoin', 'calculation');
                            } elseif ($storePaymentMethods['result']['lightning']) {
                                $checkInvoice = $client->checkPaymentData(0, $currency, 'lightning', 'calculation');
                            }
                        }
                    } catch (\Exception $e) {
                        $errorMessage = 'API connection is not established';
                    }

                } else {
                    try {
                        $checkInvoice = $client->checkPaymentData(0, $currency, 'coinsnap', 'calculation');
                    } catch (\Throwable $e) {
                        $checkInvoice = ['result' => false];
                    }
                }

                if (isset($checkInvoice) && $checkInvoice['result']) {
                    $connectionData = 'Min order amount is' .' '. $checkInvoice['min_value'].' '.$currency;
                } else {
                    $connectionData = 'No payment method is configured';
                }

                $html = $this->l('Configuration updated successfully. '.$connectionData);

            } else {
                $warning = $this->l($errorMessage);
            }
        }
        $states = OrderState::getOrderStates((int) Configuration::get('PS_LANG_DEFAULT'));

        $OrderStates = array();
        foreach ($states as $state) {
            $OrderStates[$state['id_order_state']] = $state['name'];
        }

        $coinsnap_status_new  = empty(Configuration::get('COINSNAP_STATUS_NEW')) ? 1 : Configuration::get('COINSNAP_STATUS_NEW');
        $coinsnap_status_expired  = empty(Configuration::get('COINSNAP_STATUS_EXP')) ? 8 : Configuration::get('COINSNAP_STATUS_EXP');
        $coinsnap_status_settled  = empty(Configuration::get('COINSNAP_STATUS_SET')) ? 2 : Configuration::get('COINSNAP_STATUS_SET');
        // Not 3 ("Processing in progress"): that status counts as paid, and Processing is not confirmed yet.
        $coinsnap_status_processing = empty(Configuration::get('COINSNAP_STATUS_PRO')) ? 1 : Configuration::get('COINSNAP_STATUS_PRO');

        $data = array(
            'module_name' => $this->name,
            'coinsnap_provider' => Configuration::get('COINSNAP_PROVIDER'),
            'coinsnap_store_id' => Configuration::get('COINSNAP_STORE_ID'),
            // Only a hint of the stored keys reaches the page, never the keys.
            'coinsnap_api_key_hint' => $this->secretHint((string) Configuration::get('COINSNAP_API_KEY')),
            'btcpay_server_url' => Configuration::get('BTCPAY_SERVER_URL'),
            'btcpay_store_id' => Configuration::get('BTCPAY_STORE_ID'),
            'btcpay_api_key_hint' => $this->secretHint((string) Configuration::get('BTCPAY_API_KEY')),
            'coinsnap_autoredirect' => Configuration::get('COINSNAP_AUTOREDIRECT'),
            'coinsnap_returnurl' => Configuration::get('COINSNAP_RETURNURL'),
            
            'coinsnap_discount_enabled' => Configuration::get('COINSNAP_DISCOUNT_ENABLED'),
            'coinsnap_discount_type' => Configuration::get('COINSNAP_DISCOUNT_TYPE'),
            'coinsnap_discount_amount' => Configuration::get('COINSNAP_DISCOUNT_AMOUNT'),
            'coinsnap_discount_amount_limit' => Configuration::get('COINSNAP_DISCOUNT_LIMIT'),
            'coinsnap_discount_percentage' => Configuration::get('COINSNAP_DISCOUNT_PERCENTAGE'),
            
            'coinsnap_status_new' => $coinsnap_status_new,
            'coinsnap_status_expired' => $coinsnap_status_expired,
            'coinsnap_status_settled' => $coinsnap_status_settled,
            'coinsnap_status_processing' => $coinsnap_status_processing,
            //'coinsnap_confirmation' => $html,
            //'coinsnap_warning' => $warning,
            'orderstates' => $OrderStates,
            'coinsnap_form_token' => $this->configFormToken(),
        );

        if (isset($html)) {
            $data['coinsnap_confirmation'] = $html;
        }
        if (!isset($warning) && !self::isAwaitingState((int) $coinsnap_status_processing)) {
            // Older versions defaulted to 3 ("Processing in progress"), which counts as paid.
            $warning = $this->l('The Processing order status counts as paid or shipped, but a processing payment is not confirmed yet. Choose an awaiting-payment status and save. Until then, processing payments leave the order unchanged.');
        }
        if (isset($warning)) {
            $data['coinsnap_warning'] = $warning;
        }

        $this->context->smarty->assign($data);
        $output = $this->display(__FILE__, 'views/templates/admin/admin.tpl');

        return $output;
    }

    public function coinsnapPaymentOptions($params)
    {

        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $payment_options = [
            $this->coinsnapExternalPaymentOption(),
        ];
        return $payment_options;
    }

    public function checkCurrency($cart)
    {

        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }


    public function checkAmount($amount, $currency)
    {
        $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
        $store = new \Coinsnap\Client\Store($this->api_url, $this->api_key);
        $checkInvoice = [];

        try {
            $_provider = $this->provider;
            if ($_provider === 'btcpay') {
                try {
                    $storePaymentMethods = $store->getStorePaymentMethods($this->store_id);

                    if ($storePaymentMethods['code'] === 200) {
                        if (!$storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                            $errorMessage = 'No payment method is configured on BTCPay server';
                            $checkInvoice = array('result' => false,'error' => $errorMessage);
                        }
                    } else {
                        $errorMessage = 'Error store loading. Wrong or empty Store ID';
                        $checkInvoice = array('result' => false,'error' => $errorMessage);
                    }

                    if ($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                        $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency), 'bitcoin');
                    } elseif ($storePaymentMethods['result']['lightning']) {
                        $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency), 'lightning');
                    }
                } catch (\Throwable $e) {
                    $errorMessage = 'API connection is not established';
                    $checkInvoice = array('result' => false,'error' => $errorMessage);
                }
            } else {
                $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency));
            }
        } catch (\Throwable $e) {
            $errorMessage = 'API connection is not established';
            $checkInvoice = array('result' => false,'error' => $errorMessage);
        }
        return $checkInvoice;
    }

    public function coinsnapExternalPaymentOption()
    {

        $lang = Tools::strtolower($this->context->language->iso_code);
        $url = $this->context->link->getModuleLink('coinsnap', 'payment');
        $errmsg = null;

        if (Tools::getIsset('coinsnaperror')) {
            $errmsg = Tools::getValue('coinsnaperror');
        }
        $this->context->smarty->assign(array(
            'module_dir' => __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'module_name' => $this->name,
            'action_url' => $url,
            'errmsg' => $errmsg,
        ));

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->l('Pay with Bitcoin + Lightning'))
            ->setForm($this->context->smarty->fetch('module:coinsnap/views/templates/front/payment_infos.tpl'));

        return $newOption;
    }

    public function coinsnapPaymentReturnNew($params)
    {

        if ($this->active == false) {
            return;
        }
        $order = $params['order'];
        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total_to_pay' => $order->total_paid,
            'shop_name' => $this->context->shop->name,
        ));
        return $this->fetch('module:' . $this->name . '/views/templates/front/order-confirmation.tpl');
    }

    public function getUrl($pay_currency)
    {

        $lang = Tools::strtolower($this->context->language->iso_code);
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $iaddress = new Address($cart->id_address_invoice);

        $this->applyBitcoinDiscount($cart);

        // No thousands separator: "1,234.56" became 1.00 and crashed checkout for carts of 1000 or more.
        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $cart_id = $cart->id;
        $ps_currency  = new Currency((int)($cart->id_currency));
        $currency = $ps_currency->iso_code;

        $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
        $checkInvoice = $this->checkAmount($amount, strtoupper($currency));
        // The order is placed before payment, so its first status must not count as paid or ship goods.
        if ($checkInvoice['result'] === true && !self::isAwaitingState((int) $this->status_new)) {
            PrestaShopLogger::addLog('Coinsnap: checkout refused, the New order status counts as paid or shipped', 3);
            $checkInvoice = ['result' => false, 'error' => 'Payment method is not available'];
        }

        if ($checkInvoice['result'] === true) {
            

            $buyerName = trim($iaddress->firstname . ' ' . $iaddress->lastname);
            $buyerEmail = $customer->email;

            //  Order saving
            $extra_vars['transaction_id'] = '';
            
            try {
                $this->validateOrder((int)$cart_id, (int)$this->status_new, (float)$amount, $this->displayName, null, $extra_vars, null, false, $cart->secure_key);
            } catch (\Throwable $e) {
                // No order, so no Coinsnap payment: the discount must not stay on the cart.
                $this->removeBitcoinDiscount($cart);
                throw $e;
            }

            $order_id = Order::getIdByCartId($cart_id).'';
            $order = new Order((int)$order_id);

            // Built from the shop's configured domain, not from the request's Host header.
            $redirectUrl = (!empty(Configuration::get('COINSNAP_RETURNURL'))) ? Configuration::get('COINSNAP_RETURNURL') : $this->context->link->getPageLink(
                'order-confirmation',
                true,
                null,
                ['id_cart' => (int) $cart_id, 'id_module' => (int) $this->id, 'id_order' => (int) $order_id, 'key' => $cart->secure_key]
            );


            // orderId stays the order ID, which the webhook loads the order by (metadata.orderId on BTCPay).
            $metadata = CoinsnapInvoiceData::metadata($this->invoiceOrderData($order, $customer, $iaddress, (int) $cart_id, $lang));
            
            $redirectAutomatically = $this->autoredirect;
            $walletMessage = '';
            
            // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
            if ($currency !== 'BTC' && $this->provider === 'btcpay') {
                $store = new \Coinsnap\Client\Store($this->api_url, $this->api_key);
                $btcpayCurrencies = $store -> getStoreCurrenciesRates($this->store_id,array($currency));
                $isCurrency = true;
                if(!isset($btcpayCurrencies['result']['error']) && count($btcpayCurrencies['result']['currencies'])>0){
                    if(!isset($btcpayCurrencies['result']['currencies']['BTC_'.$currency])){
                        $isCurrency = false;
                    }
                }
                else {
                    $isCurrency = false;
                }
                    
                // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
                if( !$isCurrency ){
                    $currency = 'BTC';
                    $rate = 1/$checkInvoice['rate'];
                    $amountBTC = bcdiv((string)$amount, (string)$rate, 8);
                    $amount = (float)$amountBTC;
                }
            }
        
            $camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat((float) $amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat((float) $amount,2);

            try {
                $invoiceData = $client->createInvoice(
                    $this->store_id,
                    $currency,
                    $camount,
                    $order_id,
                    $buyerEmail,
                    $buyerName,
                    $redirectUrl,
                    $this->referralCode,
                    $metadata,
                    $redirectAutomatically,
                    $walletMessage
                )->getData();
            } catch (\Throwable $e) {
                PrestaShopLogger::addLog('Coinsnap: invoice creation failed for order ' . (int) $order_id . ' (status ' . (int) $e->getCode() . ')', 3);
                $invoiceData = [];
            }

            $payurl = $invoiceData['checkoutLink'] ?? '';

            if (!empty($payurl) && !empty($invoiceData['id'])) {
                $invoice_id = (string) $invoiceData['id'];
                // Only this invoice, for this amount and currency, can pay the order.
                CoinsnapInvoiceStore::remember($invoice_id, (int) $order_id, $camount->__toString(), $currency);
                $this->set_trans_no($order_id, $invoice_id);
                return  $payurl;
            } else {
                $errmsg = $this->l("The payment could not be started. Please try again later.");
                $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
                $url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&coinsnaperror='.$errmsg.'#coinsnap-anchor';
                Tools::redirect($url);
                exit;
            }
        } else {

            if ($checkInvoice['error'] === 'currencyError') {
                $errorMessage = 'Currency '.strtoupper($currency).' is not supported by Coinsnap';
            } elseif ($checkInvoice['error'] === 'amountError') {
                $errorMessage = 'Invoice amount cannot be less than '.$checkInvoice['min_value'].' '.strtoupper($currency);
            } else {
                $errorMessage = $checkInvoice['error'];
            }
            $this->removeBitcoinDiscount($cart);
            $errmsg = $this->l($errorMessage);
            $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
            $url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&coinsnaperror='.$errmsg.'#coinsnap-anchor';
            Tools::redirect($url);
            exit;
        }
    }

    // The front office does not know the (renamed) admin folder, so the settings page records it for orderUrl.
    private function rememberAdminUrl(): void
    {
        if (!defined('_PS_ADMIN_DIR_')) {
            return;
        }
        $url = $this->context->link->getAdminBaseLink() . basename(_PS_ADMIN_DIR_) . '/';
        if ($url !== (string) Configuration::getGlobalValue('COINSNAP_ADMIN_URL')) {
            Configuration::updateGlobalValue('COINSNAP_ADMIN_URL', $url);
        }
    }

    // Admin order page (the admin asks to log in, or to confirm the link without a token); '' before the settings page was opened.
    private function adminOrderUrl(int $orderId): string
    {
        $adminUrl = (string) Configuration::getGlobalValue('COINSNAP_ADMIN_URL');
        return $adminUrl !== '' ? $adminUrl . 'index.php?controller=AdminOrders&id_order=' . $orderId . '&vieworder' : '';
    }

    // Plain order values for CoinsnapInvoiceData; amounts in the order currency, tax included.
    private function invoiceOrderData(Order $order, Customer $customer, Address $address, int $cartId, string $lang): array
    {
        $products = [];
        foreach ($order->getProducts() as $row) {
            $products[] = [
                'name' => $row['product_name'] ?? '',
                'sku' => $row['product_reference'] ?? '',
                'productId' => $row['product_id'] ?? '',
                'quantity' => $row['product_quantity'] ?? 0,
                'price' => $row['unit_price_tax_incl'] ?? 0,
                'total' => $row['total_price_tax_incl'] ?? 0,
            ];
        }

        $codes = [];
        foreach ($order->getCartRules() as $rule) {
            $code = (string) (new CartRule((int) $rule['id_cart_rule']))->code;
            // The one-time Bitcoin discount code means nothing to the merchant; its amount is in discountTotal.
            if ($code !== '' && strpos($code, 'CSBTC-') !== 0) {
                $codes[] = $code;
            }
        }

        $message = Message::getMessageByCartId($cartId);
        $state = $address->id_state ? new State((int) $address->id_state) : null;
        $carrier = new Carrier((int) $order->id_carrier);

        return [
            'id' => (int) $order->id,
            'reference' => $order->reference,
            'orderUrl' => $this->adminOrderUrl((int) $order->id),
            'platformVersion' => _PS_VERSION_,
            'pluginVersion' => $this->version,
            'language' => $lang,
            'customer' => ['firstname' => $customer->firstname, 'lastname' => $customer->lastname, 'email' => $customer->email],
            'address' => [
                'firstname' => $address->firstname,
                'lastname' => $address->lastname,
                'company' => $address->company,
                'phone' => $address->phone,
                'phone_mobile' => $address->phone_mobile,
                'address1' => $address->address1,
                'address2' => $address->address2,
                'city' => $address->city,
                'postcode' => $address->postcode,
                'state' => $state && Validate::isLoadedObject($state) ? $state->iso_code : '',
                'country' => Country::getIsoById((int) $address->id_country),
            ],
            'message' => is_array($message) ? ($message['message'] ?? '') : '',
            'products' => $products,
            'couponCodes' => $codes,
            'discountTotal' => $order->total_discounts_tax_incl,
            'shippingMethod' => Validate::isLoadedObject($carrier) ? $carrier->name : '',
            'shippingTotal' => $order->total_shipping_tax_incl,
            'taxTotal' => (float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl,
        ];
    }

    public function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool
    {
        $whClient = new \Coinsnap\Client\Webhook($apiUrl, $apiKey);
        $storedWebhook = json_decode(Configuration::get('COINSNAP_WEBHOOK'),true);
        $isWebhook = false;

        // Throws when the API can't be asked; the caller must not treat that as "no webhook".
        foreach ($whClient->getWebhooks($storeId) as $webhook) {
            if (strpos($webhook->getData()['url'], $this -> webhook_url) === false) {
                continue;
            }
            if ($storedWebhook && is_array($storedWebhook) && $webhook->getData()['id'] === $storedWebhook['id']){
                $isWebhook = true;
                continue;
            }
            try {
                $whClient->deleteWebhook($storeId, $webhook->getData()['id']);
            } catch (\Throwable $e) {
                PrestaShopLogger::addLog('Coinsnap: stale webhook could not be deleted', 2);
            }
        }

        return $isWebhook;
    }

    public function registerWebhook(string $apiUrl, string $apiKey, string $storeId, string $provider = 'coinsnap')
    {
        try {
            $whClient = new Webhook($apiUrl, $apiKey);
            $webhook_events = ($provider === 'btcpay') ? self::BTCPAY_WEBHOOK_EVENTS : self::COINSNAP_WEBHOOK_EVENTS;
            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $this -> webhook_url, //$url
                $webhook_events,   //$specificEvents
                null    //$secret
            );
            
            
            Configuration::updateValue(
                'COINSNAP_WEBHOOK',
                json_encode([
                    'id' => $webhook->getData()['id'],
                    'secret' => $webhook->getData()['secret'],
                    'url' => $webhook->getData()['url']
                ])
            );

            return $webhook;

        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('Coinsnap: webhook could not be registered (status ' . (int) $e->getCode() . ')', 3);
            return false;
        }
    }

    private function secretHint(string $value): string
    {
        return $value === '' ? '' : '••••••••' . Tools::substr($value, -4);
    }

    public function setOrderStatus($order_id, $status, $invoice_id)
    {
        $order_history = new OrderHistory();
        $order_history->id_order = (int)$order_id;
        $order_history->changeIdOrderState((int)$status, (int)$order_id, true);
        $order_history->addWithemail(true);
        $this->set_trans_no($order_id, $invoice_id);

    }

    public function set_trans_no($order_id, $trans_no)
    {
        $order = new Order((int)$order_id);
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'order_payment` SET `transaction_id` =\'' . pSQL($trans_no) . '\' 
			    WHERE `order_reference` = \'' . pSQL($order->reference) . '\''
        );

    }


}
