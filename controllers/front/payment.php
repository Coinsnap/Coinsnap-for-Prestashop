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

class CoinsnapPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $isLogged = false;
    public $display_column_left = false;
    public $display_column_right = false;
    public $service;
    protected $ajax_refresh = false;
    protected $css_files_assigned = array();
    protected $js_files_assigned = array();

    public function __construct()
    {
        $this->controller_type = 'modulefront';
        $this->module = Module::getInstanceByName(Tools::getValue('module'));
        if (!$this->module->active) {
            Tools::redirect('index');
        }
        $this->page_name = 'module-' . $this->module->name . '-' . Dispatcher::getInstance()->getController();
        parent::__construct();
    }

    public function postProcess()
    {
        // Only a real checkout cart of a known customer reaches the order and invoice creation.
        $cart = $this->context->cart;
        if (!$cart || !$cart->id || !$cart->id_customer || !$cart->id_address_invoice || !$cart->nbProducts()
            || !Validate::isLoadedObject(new Customer((int) $cart->id_customer))) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $Coinsnap = new Coinsnap();
        $pay_currency = empty(Tools::getValue('pay_currency')) ? '' : Tools::getValue('pay_currency');
        $url = $Coinsnap->getUrl($pay_currency);
        Tools::redirect(Tools::safeOutput($url, false));
    }
}
