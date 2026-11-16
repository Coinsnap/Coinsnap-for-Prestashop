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

        if (!$this->registerHook('actionCartSave')) {
            return false;
        }

        if (!$this->registerHook('actionAdminControllerSetMedia')) {
            return false;
        }

        return CoinsnapInvoiceStore::createTable();
    }
    
    public function hookActionCartSave($params){
        
        $bitcoin_discount_enabled = (null !== $this->discount_enabled && (int)$this->discount_enabled > 0)? true : false;        
        
        if($bitcoin_discount_enabled){
            
            $cart = $params['cart'];
            $total = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
            $existingRules = $cart->getCartRules();
            $ps_currency  = new Currency((int)($cart->id_currency));
            $currency = $ps_currency->iso_code;
            
            // Avoid applying the same discount twice
            foreach ($existingRules as $rule) {
                if ($rule['code'] == 'BitcoinDiscount') {
                    $cart->removeCartRule($rule['id_cart_rule']);
                }
            }
            
            $discount_type = $this -> discount_type;
            $isDiscount = false;
            
            if($discount_type === 'fixed' && (float)$this -> discount_amount > 0){

                $discount_amount = round((float)$this -> discount_amount,2);
                $discount_amount_limit = (float)$this -> discount_amount_limit;
                
                if($discount_amount_limit >= 0 && $discount_amount_limit < 100){
                    if($discount_amount > ($total * $discount_amount_limit / 100)){
                        $discount_amount = round($total * $discount_amount_limit / 100,2);
                    }
                        
                    if($discount_amount < $total){
                        $isDiscount = true;
                        $discount_title = '';
                    }
                }
            }
            elseif($this -> discount_percentage > 0) {
                    $discount_percentage = $this -> discount_percentage;

                    if($discount_percentage > 0 && $discount_percentage < 100){
                        $isDiscount = true;
                        $discount_title = ' '.$discount_percentage . '%';
                    }
            }
            if($isDiscount){
                
                
                
                $cartRule = new CartRule();
                $cartRule->name = array_fill_keys(Language::getIDs(false), 'Bitcoin discount' . $discount_title);
                $cartRule->code = 'BitcoinDiscount';
                
                if($discount_type === 'fixed'){
                    $cartRule->reduction_amount = $discount_amount;
                    //$cartRule->reduction_currency = $ps_currency;
                }
                else {
                    $cartRule->reduction_percent = $discount_percentage;
                }
                
                $cartRule->date_from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
                $cartRule->active = true;
                $cartRule->add();

                $cart->addCartRule($cartRule->id);                    
            }            
        }
    }    

    public function hookActionAdminControllerSetMedia(array $params)
    {
        $this->context->controller->addJs($this->getPathUri() . 'views/js/coinsnap.js');
    }

    public function hookPaymentOptions($params)
    {
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
        // Orders placed before 1.4.0 have no record; check against what was sent then.
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

        if ($decision === CoinsnapInvoiceDecision::PAID) {
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
        if ($decision === CoinsnapInvoiceDecision::PROCESSING && $state === $new) {
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

        if (Tools::isSubmit('submit' . $this->name)) {

            $coinsnap_name = Tools::getValue('coinsnap_name');
            $saveOpt = false;
            $errorMessage = '';

            $_provider = (Tools::getValue('coinsnap_provider') === 'btcpay') ? 'btcpay' : 'coinsnap';
            $api_url = ($_provider === 'btcpay') ? Tools::getValue('btcpay_server_url') : 'https://app.coinsnap.io';
            $store_id = ($_provider === 'btcpay') ? Tools::getValue('btcpay_store_id') : Tools::getValue('coinsnap_store_id');
            // The key fields are shown empty; leaving one empty keeps the stored key.
            $coinsnap_api_key = (string) Tools::getValue('coinsnap_api_key') !== '' ? (string) Tools::getValue('coinsnap_api_key') : (string) Configuration::get('COINSNAP_API_KEY');
            $btcpay_api_key = (string) Tools::getValue('btcpay_api_key') !== '' ? (string) Tools::getValue('btcpay_api_key') : (string) Configuration::get('BTCPAY_API_KEY');
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
                    $checkInvoice = $client->checkPaymentData(0, $currency, 'coinsnap', 'calculation');
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
        $coinsnap_status_processing = empty(Configuration::get('COINSNAP_STATUS_PRO')) ? 3 : Configuration::get('COINSNAP_STATUS_PRO');

        $data = array(
            'base_url'    => _PS_BASE_URL_ . __PS_BASE_URI__,
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
            'orderstates' => $OrderStates
        );

        if (isset($html)) {
            $data['coinsnap_confirmation'] = $html;
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

        // No thousands separator: "1,234.56" became 1.00 and crashed checkout for carts of 1000 or more.
        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $cart_id = $cart->id;
        $ps_currency  = new Currency((int)($cart->id_currency));
        $currency = $ps_currency->iso_code;

        $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
        $checkInvoice = $this->checkAmount($amount, strtoupper($currency));

        if ($checkInvoice['result'] === true) {
            
            $redirectUrl = (!empty(Configuration::get('COINSNAP_RETURNURL')))? Configuration::get('COINSNAP_RETURNURL') : (
                (Configuration::get('PS_REWRITING_SETTINGS') > 0) ? _PS_BASE_URL_.__PS_BASE_URI__.$lang.'/order-confirmation?id_cart='.(int)$cart_id.'&id_module='.(int)$this->id.'&id_order='.(int)$cart_id.'&key='.$cart->secure_key : _PS_BASE_URL_.__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$cart_id.'&id_module='.(int)$this->id.'&id_order='.(int)$cart_id.'&key='.$cart->secure_key);

            $buyerName =  $iaddress->firstname.' '.$iaddress->lastname;
            $buyerEmail = $customer->email;

            //  Order saving
            $extra_vars['transaction_id'] = '';
            
            PrestaShopLogger::addLog( 'Coinsnap: state ID = ' . (int)$this->status_new, 1);
            
            $this->validateOrder((int)$cart_id, (int)$this->status_new, (float)$amount, $this->displayName, null, $extra_vars, null, false, $cart->secure_key);

            $order_id = Order::getIdByCartId($cart_id).'';
            $order = new Order((int)$order_id);
            $order_number = $order->reference;


            $metadata = [];
            $metadata['orderNumber'] = $order_number;
            $metadata['customerName'] = $buyerName;

            if ($this->provider === 'btcpay') {
                $metadata['orderId'] = $order_id;
            }
            
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
            $errmsg = $this->l($errorMessage);
            $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
            $url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&coinsnaperror='.$errmsg.'#coinsnap-anchor';
            Tools::redirect($url);
            exit;
        }
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
