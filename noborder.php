<?php

/*
* Plugin Name: noBorder crypto payment gateway for Prestashop
* Description: <a href="https://noborder.company">noBorder</a> crypto payment gateway for Prestashop.
* Version: 1.1
* Author: noBorder.company
* Author URI: https://noBorder.company
* Author Email: info@noBorder.company
* Text Domain: noBorder_Prestashop_payment_module
* Tested version up to: 8.1
* copyright (C) 2020 noBorder.company
* license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 or later
*/

if (!defined('_PS_VERSION_')) exit;

class noborder extends PaymentModule {
	
	private $_html = '';
    private $_postErrors = array();
	public $address;
	
	public function __construct(){
		$this->name = 'noborder';
        $this->tab = 'payments_gateways';
        $this->version = '1.1';
        $this->author = 'noborder';
        $this->controllers = array('payment', 'validation');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->displayName = 'noborder';
        $this->description = 'noborder Crypto payment gateway';
        $this->confirmUninstall = 'Are you sure ?';
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        parent::__construct();
    }
	
	public function install(){
		return parent::install() && $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn');
    }
	
	public function uninstall(){
		return parent::uninstall();
    }

    public function getContent(){
		
		if (Tools::isSubmit('noborder_submit')) {
            Configuration::updateValue('noborder_api_key', noborder::sanitize($_POST['noborder_api_key']));
            Configuration::updateValue('noborder_pay_currency', noborder::sanitize($_POST['noborder_pay_currency']));
            Configuration::updateValue('noborder_success_massage', noborder::sanitize($_POST['noborder_success_massage']));
            Configuration::updateValue('noborder_failed_massage', noborder::sanitize($_POST['noborder_failed_massage']));
            $this->_html .= '<div class="conf confirm">' . $this->l('Settings updated') . '</div>';
        }

        $this->_generateForm();
        return $this->_html;

    }

    public static function sanitize($variable){
        return trim(strip_tags($variable));
    }

    private function _generateForm(){
        $this->_html .= '
		
		<form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="defaultForm form-horizontal">
		<div class="panel">
		<div class="form-wrapper">
		
		<div class="form-group">
		<label class="control-label col-lg-4 required"> API Key : </label>
		<div class="col-lg-8">
		<input type="text" name="noborder_api_key" value="' . Configuration::get('noborder_api_key') . '">
		<div class="help-block"> You can create an API Key by going to <a href="https://noborder.company/cryptosite" target="_blank">https://noborder.company/cryptosite</a></div>
		</div>
		</div>
		
		<div class="form-group">
        <label class="control-label col-lg-4 required"> Pay Currencies : </label>
		<div class="col-lg-8">
		<input type="text" name="noborder_pay_currency" value="' . Configuration::get('noborder_pay_currency') . '">
		<div class="help-block"> By default, customers can pay through all <a href="https://noborder.company/cryptosite" target="_blank">active currencies</a> in the gate, but if you want to limit the customer to pay through one or more specific crypto currencies, you can declare the name of the crypto currencies through this variable. If you want to declare more than one currency, separate them with a dash ( - ). </div>
		</div>
		</div>
		
		<div class="form-group">
        <label class="control-label col-lg-4 required"> Success message : </label>
		<div class="col-lg-8">
		<textarea dir="auto" name="noborder_success_massage" style="height: 100px;">' . (!empty(Configuration::get('noborder_success_massage')) ? Configuration::get('noborder_success_massage') : "پرداختYour payment has been successfully completed. <br><br> Cart id : {cart_id} <br> Track id: {request_id}") . '</textarea>
		<div class="help-block">Enter the message you want to display to the customer after a successful payment. You can also choose these placeholders {request_id}, {cart_id} for showing the cart id and the tracking id respectively.</div>
		</div>
		</div>
		
		<div class="form-group">
        <label class="control-label col-lg-4 required"> Failure message : </label>
		<div class="col-lg-8">
		<textarea dir="auto" name="noborder_failed_massage" style="height: 100px;">' . (!empty(Configuration::get('noborder_failed_massage')) ? Configuration::get('noborder_failed_massage') : "Your payment has failed. Please try again or contact the site administrator in case of a problem. <br><br> Cart id : {cart_id} <br> Track id: {request_id}") . '</textarea>
		<div class="help-block"> Enter the message you want to display to the customer after a failure occurred in payment. You can also choose these placeholders {request_id}, {cart_id} for showing the cart id and the tracking id respectively. </div>
		</div>
		</div>
		</div>
		
		<div class="panel-footer">
		<input type="submit" name="noborder_submit" value="' . $this->l('Save') . '" class="btn btn-default pull-right">
		</div>
		
		</div>
		</form>';
    }

    public function hookPaymentOptions($params){
	
		if (!$this->active) return;
		
		//form data will be sent to validation controller when user finishes order process
		$formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);
		
		//Assign the url form action to the template var $action
		$this->smarty->assign(['action' => $formAction]);
		
		//Load form template to be displayed in the checkout step
		$paymentForm = $this->fetch('module:noborder/views/templates/hook/payment_options.tpl');
		
		//Create a PaymentOption object to display module in checkout
		$displayName = 'noborder Crypto payment gateway';
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)->setCallToActionText($displayName)->setAction($formAction)->setForm($paymentForm);
		$payment_options = array($newOption);
		return $payment_options;
    }
	
	public function hookPaymentReturn($params){
		if (!$this->active) return;
		return $this->fetch('module:noborder/views/templates/hook/payment_return.tpl');
    }
	
	public function hash_key(){
        $en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
        $one = rand(1, 26);
        $two = rand(1, 26);
        $three = rand(1, 26);
        return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$three] . rand(0, 9) . rand(10, 99);
    }
}
