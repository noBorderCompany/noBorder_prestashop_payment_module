<?php

/*
* noborder payment gateway
* @developer Hanif Zekri
* @publisher noborder
* @copyright (C) 2020 noborder
* @version  1.1
* @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
* https://noborder.tech
*/

class noborderValidationModuleFrontController extends ModuleFrontController {
	
	public $errors = [];
	public $warning = [];
	public $success = [];
	public $info = [];
	
	public function notification(){
		$notifications = json_encode(['error' => $this->errors,
									  'warning' => $this->warning,
									  'success' => $this->success,
									  'info' => $this->info]);
		
		if (session_status() == PHP_SESSION_ACTIVE)
			$_SESSION['notifications'] = $notifications;
		elseif (session_status() == PHP_SESSION_NONE) {
			session_start();
            $_SESSION['notifications'] = $notifications;
        } else
			setcookie('notifications', $notifications);
	}

    public function postProcess(){
		
		$cart = $this->context->cart;
        $authorized = false;
        
		$customer = new Customer($cart->id_customer);
        $moduleActive = $this->module->active;

        if (!$moduleActive || empty($cart->id_customer) || empty($cart->id_address_delivery) || empty($cart->id_address_invoice)) {
            Tools::redirect('index.php?controller=order');
        }

        foreach (Module::getPaymentModules() as $module) {
            $authorized = $module['name'] == 'noborder';
            if ($authorized) break;
        }

        if (!$authorized) {
            $this->errors[] = 'noborder payment module is not active.';
            $this->notification();
            Tools::redirect('index.php?controller=order');
        }

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order');
        }

        if (isset($_GET['do'])) {
            $this->callBack($customer);
        }

        $cart_id = $cart->id;
        $api_key = Configuration::get('noborder_api_key');
        $pay_currency = Configuration::get('noborder_pay_currency');
        $amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$currency = new Currency($cart->id_currency);
		$currency = $currency->iso_code;
		
        $callback = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "https://" : "http://")
            . $_SERVER['SERVER_NAME']
            . '/index.php?fc=module&module=noborder&controller=validation&do=callback&cart_id='
            . $cart_id;

        if (empty($amount)) {
            $this->errors[] = 'The shopping cart is empty or the products in it have no price.';
            $this->notification();
            Tools::redirect('index.php?controller=order');
        }
		
		$params = array(
			'api_key' => $api_key,
			'amount_value' => $amount,
			'amount_currency' => $currency,
			'pay_currency' => $pay_currency,
			'order_id' => $cart_id,
			'respond_type' => 'link',
			'callback' => $callback
		);
		
		$url = 'https://noborder.tech/action/ws/request_create';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
				
		$result = json_decode($response);

        if ($result->status != 'success') {
            $this->errors[] = 'The payment gateway encountered an error. <br> Gateway response : ' . $result->respond;
            $this->notification();
            Tools::redirect('index.php?controller=order');

        } else {
            $this->handleRequestID($cart_id, $result->request_id);
            Tools::redirect($result->respond);
            exit;
        }

    }

    public function callBack($customer){
		
		$cart_id = (int)$_GET['cart_id'];
		$order = new Order($cart_id);
		$request_id = $this->handleRequestID($cart_id, 0);
		
		if ($cart_id <= 0 || !empty($order) and strlen($request_id) < 5) {
			$this->errors[] = 'An error has occurred. Please try again or contact support if necessary.';
			$this->notification();
			Tools::redirect('index.php?controller=order');
			
		} else {
			
			if (empty($order->current_state) || $order->current_state == 1 || $order->current_state == 8) {
				
				$cart = $this->context->cart;
				$amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
				$currency = Context::getContext()->currency;
				
				$api_key = Configuration::get('noborder_api_key');
				
				$params = array(
					'api_key' => $api_key,
					'order_id' => $cart_id,
					'request_id' => $request_id
				);				
				
				$url = 'https://noborder.tech/action/ws/request_status';
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($ch);
				curl_close($ch);

				$result = json_decode($response);

				if ($result->status != 'success') {
					$message = 'The payment gateway encountered an error. <br> Gateway response : ' . $result->respond;
					$this->errors[] = $message;
					$this->notification();
					$this->saveOrderState($cart, $customer, 8, $message);
					Tools::redirect('index.php?controller=order-confirmation');

				} else {

					$verify_status = empty($result->status) ? NULL : $result->status;
					$verify_request_id = empty($result->request_id) ? NULL : $result->request_id;
					$verify_order_id = empty($result->order_id) ? NULL : $result->order_id;
					$verify_amount = empty($result->amount_value) ? NULL : $result->amount_value;

					$message = 'Request ID : ' . $verify_request_id . ', Cart ID : ' . $cart_id;

					if (empty($verify_request_id) || empty($verify_amount) || $verify_order_id != $cart_id || number_format($amount, 5) != number_format($verify_amount, 5)) {

						$message = $this->noborder_get_failed_message($verify_order_id, $verify_request_id);
						$this->saveOrderState($cart, $customer, 8, $message);
						$this->errors[] = $message;
						$this->notification();
						Tools::redirect('index.php?controller=order-confirmation');

					} else {

						$message = $this->noborder_get_success_message($verify_order_id, $verify_request_id);
						$this->saveOrderState($cart, $customer, 2, $message);
						$this->success[] = $message;
						$this->notification();
						Tools::redirect('index.php?controller=order-confirmation');
					}
				}

			} else {
				$this->errors[] = 'This order has already been approved.';
				$this->notification();
				Tools::redirect('index.php?controller=order-confirmation');
			}
		}
	}
	
	public function handleRequestID($cart_id, $request_id) {
        $sqlcart = 'SELECT checkout_session_data FROM `' . _DB_PREFIX_ . 'cart` WHERE id_cart  = "' . $cart_id . '"';
        $cart = Db::getInstance()->getRow($sqlcart)['checkout_session_data'];
		$cart = json_decode($cart, true);
		if ($request_id == 0) {
			return $cart['noborderRequestId'];
		} else {
			$cart['noborderRequestId'] = $request_id;
			$cart = json_encode($cart);
			$sql = 'UPDATE `' . _DB_PREFIX_ . 'cart` SET `checkout_session_data` = ' . "'" . $cart . "'" . ' WHERE `id_cart` = ' . $cart_id;
			return Db::getInstance()->Execute($sql);
		}
    }

    public function saveOrderState($cart, $customer, $state, $message){
        return $this->module->validateOrder(
			(int)$cart->id,
            $state,
            (float)$cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName,
            $message,
            null,
            (int)$this->context->currency->id,
            false,
            $customer->secure_key
        );
    }

    function noborder_get_success_message($cart_id, $request_id) {
        return str_replace(["{request_id}", "{cart_id}"], [$request_id, $cart_id], Configuration::get('noborder_success_massage'));
    }

    function noborder_get_failed_message($cart_id, $request_id) {
		return str_replace(["{request_id}", "{cart_id}"], [$request_id, $cart_id], Configuration::get('noborder_failed_massage'));

    }
}
