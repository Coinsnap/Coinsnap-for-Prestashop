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
    public string $autoredirect;
    public string $webhook_url;
    public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];
    public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing'];

    public function __construct()
    {
        $this->name = 'coinsnap';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.0';
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

        $this -> autoredirect = Configuration::get('COINSNAP_AUTOREDIRECT');

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

        return true;
    }

    public function hookActionAdminControllerSetMedia(array $params)
    {
        $this->context->controller->addJs($this->getPathUri() . 'views/js/coinsnap.js');
    }

    public function hookPaymentOptions($params)
    {
        return $this->coinsnapPaymentOptions($params);
    }

    public function returnsuccess()
    {
        // First check if we have any input
        $rawPostData = Tools::file_get_contents('php://input');

        if (!$rawPostData) {
            http_response_code(400);
            die('No raw post data received');
        } else {
            $this->add_log('notification', $rawPostData) ;
            PrestaShopLogger::addLog("coinsnap payment notification :".$rawPostData);
        }

        // Get headers and check for signature
        $headers = getallheaders();
        $signature = null;
        $payloadKey = null;
        $_provider = ($this -> provider === 'btcpay') ? 'btcpay' : 'coinsnap';

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-coinsnap-sig' || strtolower($key) === 'btcpay-sig') {
                $signature = $value;
                $payloadKey = strtolower($key);
            }
        }

        // Handle missing or invalid signature
        if (!isset($signature)) {
            http_response_code(401);
            die('Authentication required');
        }

        // Validate the signature
        $webhook = json_decode(Configuration::get('COINSNAP_WEBHOOK'), true);
        if (!Webhook::isIncomingWebhookRequestValid($rawPostData, $signature, $webhook['secret'])) {
            http_response_code(401);
            die('Invalid authentication signature for '.$payloadKey);
        }

        try {

            // Parse the JSON payload
            $postData = json_decode($rawPostData, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($postData->invoiceId)) {
                http_response_code(400);
                die('No Coinsnap invoiceId provided');
            }

            if (strpos($postData->invoiceId, 'test_') !== false) {
                http_response_code(200);
                die('Successful webhook test');
            }

            $invoice_id = $postData->invoiceId;
            $status = 'New';

            try {
                $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
                $csinvoice = $client->getInvoice($this->store_id, $invoice_id);
                $status = $csinvoice->getData()['status'] ;
                //$order_id = Order::getOrderByCartId($cart_id);
                $order_id = ($this -> provider === 'btcpay') ? $csinvoice->getData()['metadata']['orderId'] : $csinvoice->getData()['orderId'];

                $status_id = '';

                switch ($status) {
                    case 'New':
                    case 'InvoiceCreated':
                        $status_id = $this->status_new;
                        break;

                    case 'Expired':
                    case 'InvoiceExpired':
                        $status_id = $this->status_expired;
                        break;

                    case 'Processing':
                    case 'InvoiceProcessing':
                        $status_id = $this->status_processing;
                        break;

                    case 'Settled':
                    case 'InvoiceSettled':
                        $status_id = $this->status_settled;
                        break;
                }

                if (isset($order_id)) {
                    $this->setOrderStatus($order_id, $status_id, $invoice_id);
                }
                echo "OK";
                exit;
            } catch (JsonException $e) {
                http_response_code(400);
                die('Invalid JSON payload');
            }

        } catch (\Throwable $e) {
            http_response_code(500);
            die('Internal server error');
        }
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
            $api_key =  ($_provider === 'btcpay') ? Tools::getValue('btcpay_api_key') : Tools::getValue('coinsnap_api_key');

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
                if (! $this->webhookExists($api_url, $api_key, $store_id)) {
                    if (! $this->registerWebhook($api_url, $api_key, $store_id, $_provider)) {
                        $errorMessage = "$_provider: unable to Set Webhook on $api_url, Check Store ID ($store_id) and API Key ($api_key)";
                        $saveOpt = false;
                    }
                }
            }

            if ($saveOpt) {

                Configuration::updateValue('COINSNAP_PROVIDER', pSQL(Tools::getValue('coinsnap_provider')));
                Configuration::updateValue('COINSNAP_API_KEY', pSQL(Tools::getValue('coinsnap_api_key')));
                Configuration::updateValue('COINSNAP_STORE_ID', pSQL(Tools::getValue('coinsnap_store_id')));
                Configuration::updateValue('BTCPAY_SERVER_URL', pSQL(Tools::getValue('btcpay_server_url')));
                Configuration::updateValue('BTCPAY_API_KEY', pSQL(Tools::getValue('btcpay_api_key')));
                Configuration::updateValue('BTCPAY_STORE_ID', pSQL(Tools::getValue('btcpay_store_id')));
                Configuration::updateValue('COINSNAP_AUTOREDIRECT', pSQL(Tools::getValue('coinsnap_autoredirect')));
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
            'coinsnap_api_key' => Configuration::get('COINSNAP_API_KEY'),
            'btcpay_server_url' => Configuration::get('BTCPAY_SERVER_URL'),
            'btcpay_store_id' => Configuration::get('BTCPAY_STORE_ID'),
            'btcpay_api_key' => Configuration::get('BTCPAY_API_KEY'),
            'coinsnap_autoredirect' => Configuration::get('COINSNAP_AUTOREDIRECT'),
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

        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2);
        $cart_id = $cart->id;
        $ps_currency  = new Currency((int)($cart->id_currency));
        $currency = $ps_currency->iso_code;

        $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
        $checkInvoice = $this->checkAmount($amount, strtoupper($currency));

        if ($checkInvoice['result'] === true) {

            $redirectUrl = (Configuration::get('PS_REWRITING_SETTINGS') > 0) ?
            _PS_BASE_URL_.__PS_BASE_URI__.$lang.'/order-confirmation?id_cart='.(int)$cart_id.'&id_module='.(int)$this->id.'&id_order='.(int)$cart_id.'&key='.$cart->secure_key :
            _PS_BASE_URL_.__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$cart_id.'&id_module='.(int)$this->id.'&id_order='.(int)$cart_id.'&key='.$cart->secure_key;

            $buyerName =  $iaddress->firstname.' '.$iaddress->lastname;
            $buyerEmail = $customer->email;

            $camount = \Coinsnap\Util\PreciseNumber::parseFloat((float)$amount, 2);

            //  Order saving
            $extra_vars['transaction_id'] = '';
            $this->validateOrder((int)$cart_id, (int)$this->status_new, (float)$amount, $this->displayName, null, $extra_vars, null, false, $cart->secure_key);

            $order_id = Order::getIdByCartId($cart_id).'';
            $order = new Order((int)$order_id);
            $order_number = $order->reference;

            $this->add_log('notification', 'Order Number: '.$order->reference.'('.$order_id.')') ;

            $metadata = [];
            $metadata['orderNumber'] = $order_number;
            $metadata['customerName'] = $buyerName;

            if ($this->provider === 'btcpay') {
                $metadata['orderId'] = $order_id;
            }

            $redirectAutomatically = (Configuration::get('COINSNAP_AUTOREDIRECT') > 0) ? true : false;
            $walletMessage = '';

            $invoice = $client->createInvoice(
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
            );

            $payurl = $invoice->getData()['checkoutLink'] ;

            if (!empty($payurl)) {
                $invoice_id = $invoice->getData()['id'] ;
                //  $extra_vars['transaction_id'] = $invoice_id;
                $this->set_trans_no($order_id, $invoice_id);
                return  $payurl;
            } else {
                $errmsg = $this->l("API Error");
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
        $webhook = Configuration::get('COINSNAP_WEBHOOK');
                
        if ($storedWebhook = json_decode($webhook, true)) {

            try {
                $existingWebhook = $whClient->getWebhook($storeId, $storedWebhook['id']);

                if ($existingWebhook->getData()['id'] === $storedWebhook['id'] && strpos($existingWebhook->getData()['url'], $storedWebhook['url']) !== false) {
                    return true;
                }
            } catch (\Throwable $e) {
                $errorMessage = 'Error fetching existing Webhook. Message: ' .$e->getMessage();
                return false;
            }
        }
        try {
            $storeWebhooks = $whClient->getWebhooks($storeId);
            foreach ($storeWebhooks as $webhook) {
                if (strpos($webhook->getData()['url'], $this->webhook_url) !== false) {
                    $whClient->deleteWebhook($storeId, $webhook->getData()['id']);
                }
            }
        } catch (\Throwable $e) {
            $errorMessage = 'Error fetching webhooks for store ID '.$storeId.'. Message: ' .$e->getMessage();
            return false;
        }

        return false;
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
            $errorMessage = 'Error creating a new webhook on Coinsnap instance: ' . $e->getMessage();
            echo $errorMessage;
            return false;
        }
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

    public function add_log($logtype, $message)
    {
        $log_message = date("j.n.Y h:i:s a").' - '.$logtype.' - '.$message.PHP_EOL;
        file_put_contents(dirname(__FILE__).'/logs/coinsnap.log', $log_message, FILE_APPEND);
    }

}
