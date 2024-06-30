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

require_once(dirname(__FILE__) . '/lib/autoload.php');

class Coinsnap extends PaymentModule
{
    public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];


    public function __construct()
    {
        $this->name = 'coinsnap';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.5';
        $this->author = 'Coinsnap';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = '26e3f9b88be0664784deee6be20e4b7b';
        $this->referralCode = 'D14567';



        $this->ps_versions_compliancy = array(
            'min' => '1.7',
            'max' => _PS_VERSION_
        );


        parent::__construct();

        $this->meta_title = $this->l('coinsnap');
        $this->displayName = 'coinsnap';
        $this->description = $this->l('Coinsnap Gateway PrestaShop');


        $this -> api_url = 'https://app.coinsnap.io';
        $this -> store_id = Configuration::get('COINSNAP_STORE_ID');
        $this -> api_key = Configuration::get('COINSNAP_API_KEY');

        $this -> status_new = Configuration::get('COINSNAP_STATUS_NEW');
        $this -> status_expired = Configuration::get('COINSNAP_STATUS_EXP');
        $this -> status_settled = Configuration::get('COINSNAP_STATUS_SET');
        $this -> status_processing = Configuration::get('COINSNAP_STATUS_PRO');
        
        if(!defined('COINSNAP_SERVER_PATH')){
            define('COINSNAP_SERVER_PATH', 'stores');
        }

    }

    public function install(){
        if (!parent::install() || !$this->registerHook('paymentOptions')) { // || !$this->registerHook('adminOrder')
            return false;
        }
        return true;
    }

    public function hookPaymentOptions($params){
        return $this->coinsnapPaymentOptions($params);
    }



    public function returnsuccess(){

        $notify_json = Tools::file_get_contents('php://input');
        $this->add_log('notification', $notify_json) ;
        PrestaShopLogger::addLog("coinsnap payment notification :".$notify_json);
        $notify_ar = json_decode($notify_json, true);
        $invoice_id =  $notify_ar['invoiceId'];
        $status = 'New';
        $order_id = '';

        try {
            $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
            $invoice = $client->getInvoice($this->store_id, $invoice_id);
            $status = $invoice->getData()['status'] ;
            $order_id = $invoice->getData()['orderId'] ;


        } catch (\Throwable $e) {
            echo "Fail";
            exit;
        }
        //$order_id = Order::getOrderByCartId($cart_id);


        if ($status == 'New') {
            $status_id = $this->status_new;
        } elseif ($status == 'Expired') {
            $status_id = $this->status_expired;
        } elseif ($status == 'Processing') {
            $status_id = $this->status_processing;
        } elseif ($status == 'Settled') {
            $status_id = $this->status_settled;
        }
        $this->setOrderStatus($order_id, $status_id, $invoice_id);

        echo "OK";
        exit;
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


    public function getContent(){

        if (Tools::isSubmit('submit' . $this->name)) {

            $coinsnap_name = Tools::getValue('coinsnap_name');
            $saveOpt = false;
            $err_msg = '';
            if (empty(Tools::getValue('coinsnap_store_id'))) {
                $err_msg = 'Store ID must have value';
            }
            if (empty(Tools::getValue('coinsnap_api_key'))) {
                $err_msg = 'API Key must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_new'))) {
                $err_msg = 'Order Status New must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_expired'))) {
                $err_msg = 'Order Status Expired must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_settled'))) {
                $err_msg = 'Order Status Settled must have value';
            }
            if (empty(Tools::getValue('coinsnap_status_processing'))) {
                $err_msg = 'Order Status Processing must have value';
            }

            if (empty($err_msg)) {
                $saveOpt = true;
            }
            if ($saveOpt) {
                $url =  $this->context->link->getModuleLink('coinsnap', 'notify');

                if (! $this->webhookExists(Tools::getValue('coinsnap_store_id'), Tools::getValue('coinsnap_api_key'), $url)) {
                    if (! $this->registerWebhook(Tools::getValue('coinsnap_store_id'), Tools::getValue('coinsnap_api_key'), $url)) {
                        $err_msg = 'Unable to Set Webhook, Check Store ID and API Key';
                        $saveOpt = false;
                    }
                }
            }

            if ($saveOpt) {

                Configuration::updateValue('COINSNAP_STORE_ID', pSQL(Tools::getValue('coinsnap_store_id')));
                Configuration::updateValue('COINSNAP_API_KEY', pSQL(Tools::getValue('coinsnap_api_key')));
                Configuration::updateValue('COINSNAP_STATUS_NEW', pSQL(Tools::getValue('coinsnap_status_new')));
                Configuration::updateValue('COINSNAP_STATUS_EXP', pSQL(Tools::getValue('coinsnap_status_expired')));
                Configuration::updateValue('COINSNAP_STATUS_SET', pSQL(Tools::getValue('coinsnap_status_settled')));
                Configuration::updateValue('COINSNAP_STATUS_PRO', pSQL(Tools::getValue('coinsnap_status_processing')));

                $html = $this->l('Configuration updated successfully');
                
            } else {
                $warning = $this->l($err_msg);
                
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
            'coinsnap_store_id' => Configuration::get('COINSNAP_STORE_ID'),
            'coinsnap_api_key' => Configuration::get('COINSNAP_API_KEY'),
            'coinsnap_status_new' => $coinsnap_status_new,
            'coinsnap_status_expired' => $coinsnap_status_expired,
            'coinsnap_status_settled' => $coinsnap_status_settled,
            'coinsnap_status_processing' => $coinsnap_status_processing,
            //'coinsnap_confirmation' => $html,
            //'coinsnap_warning' => $warning,
            'orderstates' => $OrderStates
        );
        
        if(isset($html)) $data['coinsnap_confirmation'] = $html;
        if(isset($warning)) $data['coinsnap_warning'] = $warning;


        $this->context->smarty->assign($data);
        $output = $this->display(__FILE__, 'views/templates/admin/admin.tpl');

        return $output;
    }



    public function coinsnapPaymentOptions($params){

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

    public function checkCurrency($cart){

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
            'total_to_pay' => Tools::displayPrice($order->total_paid, null, false),
            'shop_name' => $this->context->shop->name,
        ));
        return $this->fetch('module:' . $this->name . '/views/templates/front/order-confirmation.tpl');
    }


    public function getUrl($pay_currency)
    {

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $iaddress = new Address($cart->id_address_invoice);

        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2);
        $cart_id = $cart->id;
        $ps_currency  = new Currency((int)($cart->id_currency));
        $currency_code = $ps_currency->iso_code;

        $redirectUrl =  _PS_BASE_URL_.__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$cart_id.'&id_module='.(int)$this->id.'&id_order='.(int)$cart_id.'&key='.$cart->secure_key;
        $notifyURL  = $this->context->link->getModuleLink('coinsnap', 'notify');

        $buyerName =  $iaddress->firstname.' '.$iaddress->lastname;
        $buyerEmail = $customer->email;

        $checkoutOptions = new \Coinsnap\Client\InvoiceCheckoutOptions();

        $checkoutOptions->setRedirectURL($redirectUrl);
        $client = new \Coinsnap\Client\Invoice($this->api_url, $this->api_key);
        $camount = \Coinsnap\Util\PreciseNumber::parseFloat($amount, 2);
        
        //  Order saving
        $extra_vars['transaction_id'] = '';
        $this->validateOrder((int)$cart_id, (int)$this->status_new, (float)$amount, $this->displayName, null, $extra_vars, null, false, $cart->secure_key);
        
        $order_id = Order::getOrderByCartId($cart_id);
        $order = new Order($order_id);
        $order_number = $order->reference;
        
        $this->add_log('notification', 'Order Number: '.$order->reference.'('.$order_id.')') ;
        
        $metadata = [];
        $metadata['orderNumber'] = $order_number;
        $metadata['customerName'] = $buyerName;

        $invoice = $client->createInvoice(
            $this->store_id,
            $currency_code,
            $camount,
            $order_id,
            $buyerEmail,
            $buyerName,
            $redirectUrl,
            $this->referralCode,
            $metadata,
            $checkoutOptions
        );

        $payurl = $invoice->getData()['checkoutLink'] ;
        


        if (!empty($payurl)) {
            $invoice_id = $invoice->getData()['id'] ;
            //  $extra_vars['transaction_id'] = $invoice_id;
            $this->set_trans_no($order_id, $invoice_id);
            return  $payurl;
        }
        else {
            $errmsg = $this->l("API Error");
            $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
            $url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&coinsnaperror='.$errmsg.'#coinsnap-anchor';
            Tools::redirect($url);
            exit;

        }

    }

    public function webhookExists(string $storeId, string $apiKey, string $webhook): bool
    {
        try {
            $whClient = new \Coinsnap\Client\Webhook($this->api_url, $apiKey);
            $Webhooks = $whClient->getWebhooks($storeId);

            foreach ($Webhooks as $Webhook) {
                //$this->deleteWebhook($storeId,$apiKey, $Webhook->getData()['id']);
                if ($Webhook->getData()['url'] == $webhook) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }
    public function registerWebhook(string $storeId, string $apiKey, string $webhook): bool
    {
        try {
            $whClient = new \Coinsnap\Client\Webhook($this->api_url, $apiKey);

            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $webhook, //$url
                self::WEBHOOK_EVENTS,   //$specificEvents
                null    //$secret
            );
            Configuration::updateValue('COINSNAP_WEBHOOK_SECRET', pSQL($webhook->getData()['secret']));
            Configuration::updateValue('COINSNAP_WEBHOOK_ID', pSQL($webhook->getData()['id']));
            return true;
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public function deleteWebhook(string $storeId, string $apiKey, string $webhookid): bool
    {

        try {
            $whClient = new \Coinsnap\Client\Webhook($this->api_url, $apiKey);

            $webhook = $whClient->deleteWebhook(
                $storeId,   //$storeId
                $webhookid, //$url
            );
            return true;
        } catch (\Throwable $e) {

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
        $message = date("j.n.Y h:i:s a").' - '.$logtype.' - '.$message.PHP_EOL;
        file_put_contents(dirname(__FILE__).'/logs/coinsnap.log', $message, FILE_APPEND);
    }

}
