<?php

/**
 * Class WC_Gateway_Arrow
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
/**
 * Arrow
 *
 * @class WC_Gateway_Arrow
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Arrow extends WC_Payment_Gateway
{
	/**
	 * Gateway constructor
	 */

	public function __construct()
	{
		$this->id                 = 'arrow';
		$this->icon               = plugin_dir_url( __FILE__ ) . '../assets/icon-128x128.png';
		$this->has_fields         = false;
		$this->method_title       = 'Arrow Checkout';
		$this->method_description = 'The fastest way to checkout on the Internet';

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled       = $this->get_option( 'enabled' );
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->support_card  = $this->get_option( 'support_card_payment' );
		$this->support_paynow= $this->get_option( 'support_paynow_payment' );
		//Setting static value for checkout_type so that we can desable the dropdown value in woocomere payment settings.
		$this->checkout_type = ($this->get_option( 'checkout_type' )=='one-click-checkout') ? $this->get_option( 'checkout_type' ):'one-click-checkout';
		$this->environment   = $this->get_option( 'environment' );
		if ( 'sandbox' === $this->environment ) {
			$this->client_key = $this->get_option( 'client_key_sandbox' );
			$this->secret_key = $this->get_option( 'secret_key_sandbox' );
			$this->username   = $this->get_option( 'username_sandbox' );
			$this->api        = 'https://qa-yo.projectarrow.co/api';
		} else {
			$this->client_key = $this->get_option( 'client_key_production' );
			$this->secret_key = $this->get_option( 'secret_key_production' );
			$this->username   = $this->get_option( 'username_production' );
			$this->api        = 'https://fly.witharrow.co/api';
		}
		$this->namespace = '/arrow/v1';
                
		$this->supports = array(
				'products',
				'refunds'
			);
		//Saved value for admin test mode drop Down
		$this->arrow_admintestmode = $this->get_option( 'arrow_admintestmode' );
		$this->logegInUserId = get_current_user_id();

		$this->helperMehtod = new ArrowPluginHelper();
		$this->form_fields = $this->helperMehtod->adminFormFields();

		$this->successUrlString = '/wp-json/arrow/v1/success/';
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('admin_print_scripts-woocommerce_page_wc-settings', array($this, 'arrow_admin_scripts'));
	}
	/**
	 * Add scripts to admin.
	 */
	public function arrow_admin_scripts()
	{
		wp_enqueue_script('arrow-admin-js', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), '0.1', true);
	}

	/**
	 * Process the refund and return the boolean
	 *
	 * @param int $order_id Order ID.
	 * @param float $amount Refund amount.
	 * @param string $reason Refund reason.
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{

		$order = wc_get_order($order_id);
		$hash  = $order->get_meta('arrow_hash', true);

		$refund = $this->call_arrow_refund($order, $hash, $amount * 100);

		if (is_wp_error($refund)) {
			return $refund;
		}

		return true;
	}

	private function arrow_get_cart_applied_coupon_details($coupon_code)
	{
		$appliedCoupons = array();
		foreach ($coupon_code as $code) {
			$appliedCoupons[] = $code;
		}
		$coupon_discount_totals = WC()->cart->coupon_discount_totals[$appliedCoupons[0]];
		return ['appliedCoupons' => $appliedCoupons[0], 'coupon_discount_totals' => $coupon_discount_totals];
	}
	private function arrow_get_customer_data_order_address($address)
	{
		return [
			"first_name" => $address['first_name'],
			"last_name" => $address['last_name'],
			"email" => $address['email'],
			"contact" => $address['phone'],
			"unit" => $address['address_2'],
			"street" => $address['address_1'],
			"building" => '',
			"line2" => $address['address_2'],
			"city" => $address['city'],
			"country" => $address['country'],
			"country_code" => $address['country'],
			"postal_code" => $address['postcode'],
			"use_address"	=> "true",
			//'promocode' => $promocode,
		];
	}
	/**
	 * Process the payment and return the result
	 *
	 * @param array $data Order data.
	 */
	public function process_payment($order_id, $button = '')
	{
		global $woocommerce;
		$this->helperMehtods = new ArrowPluginHelper();
		$order = new WC_Order($order_id);
		$standard_checkout = sanitize_text_field(($_GET['wc-ajax']) ?? '') == 'checkout' ? true : false;
		$arrow_order = new \stdClass();
		$itemsFromHelper = $this->helperMehtods->getOrderItemsArray($order);
		$arrow_order->items = $itemsFromHelper['items'];
		$arrow_order->coupon = NULL;
		$coupon_code = WC()->cart->applied_coupons;
		if ((is_array($coupon_code)) && (!empty($coupon_code))) {
			$appliedCoupnData = $this->arrow_get_cart_applied_coupon_details($coupon_code);
			$couponData = array("success" => true, 'code' => $appliedCoupnData['appliedCoupons'], 'amount' => $appliedCoupnData['coupon_discount_totals'], 'discount_type' => 'fixed');
			$promocode = $appliedCoupnData['appliedCoupons'];
			$arrow_order->promocode = $promocode;
			$arrow_order->coupon = $couponData;
		}
		$arrowGetSessionData = $this->helperMehtods->setOrderObjSessionValue();
		//Get user Billing and Shipping Address if user is login in wordpress
		$arrow_order->estimate = $arrowGetSessionData['deliveryDate'];
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
			$userAddress = $this->helperMehtods->getUserAddress($user_id);
			$arrow_order->shipping_address = $userAddress['user_shipping'];
			$arrow_order->billing_address = $userAddress['user_billing'];
		}
		$arrow_order->extraData = array(
			'cart_id'  => "",
			'order_id' => $order->get_id(),
			'store_order_id' => $order->get_id(),
			'wp_plugin_version' => ARROW_CURRENT_VERSION,
			'checkout_type' => $this->checkout_type,
			'standard_checkout' => $standard_checkout,
			'support_card' => $this->support_card,
			'support_paynow' => $this->support_paynow,
			'supported_payment_method' => @$this->setting['supported_payment_method'],
			'requires_shipping' => $itemsFromHelper['requires_shipping'],
			'deliveryDate' => $arrowGetSessionData['deliveryDate'],
			'deliveryTime' => $arrowGetSessionData['deliveryTime'],
			'buttonType' => ($button == '') ? 'Product Page' : $button,
			'clientId' => $arrowGetSessionData['arrow_ga_clientId'],
			'event_source_url' => $arrowGetSessionData['arrow_event_source_url'],
			'fbp' => $arrowGetSessionData['arrow_fbp'],
			'fbc' => $arrowGetSessionData['arrow_fbc'],
			'external_id' => $arrowGetSessionData['arrow_external_id']
		);
		$address = $order->get_address();
		if ($standard_checkout) {
			$arrow_order->customer = $this->arrow_get_customer_data_order_address($address);
		}
		$arrow_order->shipping = array();
		//$selected_shipping = WC()->session->get('chosen_shipping_methods')[0] ?? '';
		//$tax = 0.00; //define tax
		$tax_shipping_incl = $order->get_shipping_tax() != 0.00; //check tax
		//check if shipping is taxed
		if ($tax_shipping_incl) {
			//calculate tax rate with shipping
			$tax = round($order->get_total_tax() / ($order->get_total() - $order->get_total_tax()), 2);
		} else {
			//calculate tax rate w/o shipping
			$tax = round($order->get_total_tax() / ($order->get_total() - $order->get_shipping_total() - $order->get_total_tax()), 2);
		}
		$arrow_order->shipping[] = array(
			'title'         => "Shipping",
			'order_display' => "Shipping",
			'fee'           => $order->shipping_total,
			'tax' => $tax,
			'tax_shipping_incl' => $tax_shipping_incl,
			'description'   => '',
			'selected'      => true,
			'countries'     => $address['country']
		);

		$arrow_order->redirect = array(
			'success' => get_home_url() . $this->successUrlString . $order->get_id(),
			'fail'    => wc_get_cart_url(),
			'cancel'  => wc_get_cart_url()
		);
		$tax = 0;
		if ($order->get_total_tax() != 0 && !WC()->cart->display_prices_including_tax()) {
			$calc_tax = 0;
			foreach ($order->get_tax_totals() as $t) {
				$calc_tax += $t->amount;
			}
			$tax = round($calc_tax, 2);
		}
		$arrow_order->merchant_client_key = $this->client_key;
		$arrow_order->wp_plugin_version = ARROW_CURRENT_VERSION;
		$arrow_order->tax = $tax;
		$currency_info = (object)[
			'base_currency' => $order->get_currency(),
			'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol($order->get_currency())),
		];
		$arrow_order->currency = $currency_info;
		$b64 = base64_encode(json_encode($arrow_order));
		$encrypted_data = $this->str_to_hex($this->aes_encrypt($b64, $this->secret_key));
		$arrow_init = $this->arrow_init($encrypted_data, $this->username);
		if (is_wp_error($arrow_init)) {
			return array(
				'result' => 'fail',
				'error_messages' => $arrow_init->get_error_messages()
			);
		}
		$token = $arrow_init['data']['token'];
		$success_url = get_home_url() . $this->successUrlString . $order->get_id();
		$fail_url = wc_get_cart_url();
		$cancel_url = wc_get_cart_url();
		$redir = 'javascript:arrowCheckout("' . $token . '", "' . $success_url . '", "' . $fail_url . '", "' . $cancel_url . '")';
		return array('result' => 'success', 'redirect' => $redir);
	}


	public function process_my_request(WP_REST_Request $request)
	{
		// Get JSON body of request
		$request_data = $request->get_json_params();

		// TODO: Encrypt and base64 encode the cart data to post to Arrow
		$b64 = base64_encode(json_encode($request_data));
		$encrypted_data = $this->str_to_hex(aesEncrypt($b64, $this->secret_key));
		$arrow_init = $this->arrow_init($encrypted_data, $this->username);
		$response = array(
			'redirect' => $request_data['redirect'],
			'arrow'    => $arrow_init,
		);
		$response = new WP_REST_Response($response, 200);
		$response->set_headers(array('Cache-Control' => 'must-revalidate, no-cache, no-store, private'));
		return $response;
	}

	/**
	 * Arrow initial call.
	 *
	 * @param string $data Encription data.
	 * @param string $username Username from setting.
	 * @return call_arrow
	 */
	public function arrow_init($data, $username)
	{
		$endpoint = '/init';
		$data     = '{
			"data": "' . $data . '",
			"username": "' . $username . '"
		}';
		return $this->call_arrow($endpoint, $data, 'POST');
	}


	/**
	 * Make a call to API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $postdata Order data.
	 * @param string $verb POST/GET.
	 * @param string $auth Client:Secret.
	 * @return array|WP_Error
	 */
	public function call_arrow($endpoint, $postdata = array(), $verb = false, $auth = false)
	{
		$url = $this->api . $endpoint;
		if ($auth) {
			$auth    = $this->client_key . ':' . $this->secret_key;
			$headers = array(
				'Authorization' => 'Basic ' . base64_encode($auth),
				'Content-Type'  => 'application/json',
			);
		} else {
			$headers = array(
				'Content-Type' => 'application/json',
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'method'  => $verb,
				'body'    => $postdata,
				'headers' => $headers,
			)
		);
		if (is_wp_error($response)) {
			return $response;
		} else {
			return json_decode(wp_remote_retrieve_body($response), true);
		}
	}

	/**
	 * Aes Encription.
	 *
	 * @param string $data b64 data.
	 * @param string $key Secret Key.
	 */
	public function aes_encrypt($data, $key)
	{
		$method    = 'AES-256-CBC';
		$length    = openssl_cipher_iv_length($method);
		$iv        = openssl_random_pseudo_bytes($length);
		$encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
		if ($encrypted) {
			return base64_encode($encrypted) . '|' . base64_encode($iv);
		} else {
			return false;
		}
	}

	/**
	 * String to Hex.
	 *
	 * @param string $string String to convert.
	 */
	public function str_to_hex($string)
	{
		$hex = '';
		for ($i = 0; $i < strlen($string); $i++) {
			$ord     = ord($string[$i]);
			$hexCode = dechex($ord);
			$hex    .= substr('0' . $hexCode, -2);
		}
		return strToUpper($hex);
	}


	/**
	 * Get order details from Arrow.
	 *
	 * @param string $order_hash Order hash.
	 * $return object Arrow Order.
	 */
	public function get_arrow_order($order_hash)
	{
		$postdata    = array();
		$arrow_order = $this->call_arrow_order($postdata, $order_hash);
		$arrow_order = json_decode(wp_json_encode($arrow_order['Order']));

		return $arrow_order;
	}

	/**
	 * Make Arrow order.
	 *
	 * @param string $data Data.
	 * @param string $order_hash Order hash.
	 * @return array
	 */
	public function call_arrow_order($data, $order_hash)
	{
		$endpoint = '/order/' . $order_hash;
		$postdata = (!empty($data)) ? json_encode($data) : '{}';
		$auth     = $this->client_key . ':' . $this->secret_key;

		return $this->call_arrow($endpoint, $postdata, 'POST', $auth);
	}

	public function arrow_cancel_or_refund($order_id)
	{
		$order = wc_get_order($order_id);
		$hash  = $order->get_meta('arrow_hash', true);

		$total         = 0;
		$order_refunds = $order->get_refunds();
		$old_total     = intval($order->get_meta('arrow_refunded', true));

		// Get all the refunds made with WooCommerce.
		foreach ($order_refunds as $refund) {
			error_log('WooCommerce Refund Amount -> ' . $refund->get_data()['amount']);

			$cents  = $refund->get_data()['amount'] * 100;
			$total += intval(floor($cents + 0.5));

			$refund_amount = $total - $old_total;
			$order->update_meta_data('_arrow_refunded', $total);
			$order->save();

			error_log("Arrow old total:     $old_total");
			error_log("Arrow refund hash:   $hash");
			error_log("Arrow refund total:  $total");
			error_log("Arrow refund amount: $refund_amount");

			$this->call_arrow_refund($order, $hash, $refund_amount);
		}
	}

	public function call_arrow_refund($order, $hash, $amount)
	{
		$endpoint = "/order/refund/$hash/$amount";

		$data = '{}';
		error_log('arrow refund -> call arrow');
		return $this->call_arrow($endpoint, $data, 'POST', true);
	}

	public function arrow_cancel_order($order_id)
	{
		$order = wc_get_order($order_id);
		$hash  = $order->get_meta('_arrow_hash', true);
		error_log("Arrow cancel hash:   $hash");
		$this->call_arrow_cancel($order, $hash);
	}

	public function call_arrow_cancel($order, $hash)
	{
		$endpoint = "/order/cancel/$hash";
		$data     = '{}';
		error_log('arrow refund -> call arrow');
		return $this->call_arrow($endpoint, $data, 'POST');
	}

  /**
   * @throws WC_Data_Exception
   */
  public function success_callback($data)
	{
		global $woocommerce;
		$this->helperMehtods = new ArrowPluginHelper();
		WC()->frontend_includes();
		WC()->session = new WC_Session_Handler();
		WC()->session->init();
		WC()->customer = new WC_Customer(get_current_user_id(), true);
		WC()->cart     = new WC_Cart();
		$path         = wp_parse_url($_SERVER['REQUEST_URI']);
		$segments     = explode('/', $path['path']);
		$num_segments = count($segments);
		$order_id     = $segments[$num_segments - 1];
		$order_hash   = $data['order_hash'];

		$order       = new WC_Order($order_id);
		$arrow_order = $this->get_arrow_order($order_hash);
		if ($order->is_paid()) {
			wp_safe_redirect($this->get_return_url($order));
			exit;
		}
		$order->calculate_shipping();
		$existing_shipping = $order->get_items('shipping');
		foreach ($existing_shipping as $id => $sh) {
			$order->remove_item($id);
		}

    $hash = $order->get_meta('_arrow_hash');

    if (empty($hash) || $hash !== $order_hash) {
      $order->update_meta_data('_arrow_hash', $order_hash);
      $order->update_meta_data('_arrow_refunded', 0);
    }

		if ($this->logegInUserId) {
			$current_user =  get_user_by('id', $this->logegInUserId);;
			$userEmail = $current_user->user_email;
		} else {
			$userEmail = $arrow_order->customer->email;
		}
		$user = get_user_by('email', $userEmail);
		if ($user) {
			$user_id = $user->ID;
		} else {
			$user_id = $this->helperMehtods->orderRegisterUser($arrow_order);
		}
    $this->helperMehtods->updateOroderAddress($order, $arrow_order);
    $this->helperMehtods->updateOrderMeta($order, $order_id, $arrow_order, $user_id);
    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title($arrow_order->shipping);
    $shipping->set_total($arrow_order->shipping_price);
    $order->add_item($shipping);
    $order->add_meta_data('arrow_hash', $order_hash);

    $order->calculate_totals();
		if ('confirmed' === $arrow_order->status && 'captured' === $arrow_order->payment_status) {

			$order->payment_complete();
		} else {
			// NOTE: In case we don't immediately authorise payments status can be on-hold until capture.
			$order->update_status('on-hold', __('Awaiting payment', 'woocommerce'));
		}
    $woocommerce->cart->empty_cart();
		wp_safe_redirect($this->get_return_url($order));
		exit;
	}
	public function success_paylink($data)
	{
		global $woocommerce;
		$this->helperMehtods = new ArrowPluginHelper();
		$order_hash = $data['arrowOrderId'];
		if (empty($order_hash)) {
			$order_hash = $_GET['order_hash'];
		}
		$order = wc_create_order();
		$order_id = $order->get_id();
		$postdata  = array(
			'store_order_id' => $order_id,
			'success_url' =>  get_home_url() . $this->successUrlString . $order_id
		);

		$arrow_order = $this->call_arrow_order($postdata, $order_hash);
		$arrow_order = json_decode(json_encode($arrow_order['Order']));
		$customerEmail = $arrow_order->customer->email;
		$user = get_user_by('email', $customerEmail);
		if ($user) {
			$user_id = $user->ID;
		} else {
			$user_id = $this->helperMehtods->orderRegisterUser($arrow_order);
			$this->helperMehtods->registeredUserDetailsUpdate($user_id, $arrow_order);
		}
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method($payment_gateways['arrow']);
		$items = $arrow_order->items;
		$this->helperMehtods->paylinkAddItemsWcOrder($order_id, $items);
		if (!is_wp_error($user_id)) {
			$this->helperMehtods->updateOroderAddress($order, $arrow_order);
			$this->helperMehtods->updateOrderMeta($order, $order_id, $arrow_order, $user_id);
			$order->calculate_totals();
			if ('confirmed' === $arrow_order->status && 'captured' === $arrow_order->payment_status) {
				$order->payment_complete();
			} else {
				$order->update_status('on-hold', __('Awaiting payment', 'woocommerce'));
			}

			wp_safe_redirect($this->get_return_url($order));
		}
		exit;
	}
}
