<?php
/**
 * Provides an API for polling coupon options.
 *
 * @package Fast
 */

/**
 * Given promo code, check whether the coupon exists and is valid for this order.
 *
 * @param WP_REST_Request $request JSON request for shipping endpoint.
 * @return array|WP_Error|WP_REST_Response
 * @throws Exception If failed to add items to cart or no shipping options available for address.
 */
function arrowoneclick_verify_coupon( WP_REST_Request $request ) {
	$params = $request->get_params();
        
	$return = false;

	// This is needed for session to work.
	wc()->frontend_includes();

	// remove all validation to ensure fake registration succeeds
	remove_all_filters('woocommerce_registration_errors');

	arrowoneclick_shipping_init_wc_session();
	arrowoneclick_shipping_init_wc_customer( $params );
	arrowoneclick_shipping_init_wc_cart();
	$return = arrowoneclick_shipping_add_line_items_to_cart( $params );

	if ( false === $return ) {
		$return = arrowoneclick_coupon_set_coupon( $params );
	}
	
	// Cleanup cart.
	WC()->cart->empty_cart();

	return $return;
}

/**
 * Set coupon.
 *
 * @param array $params The request params.
 *
 * @return mixed
 */
function arrowoneclick_coupon_set_coupon( $params ) {
	$code = $params['promocode'];

	$coupon_id = wc_get_coupon_id_by_code( $code );

	$coupon = new WC_Coupon($coupon_id);

	if(wc()->cart->apply_coupon($code)) {
		$amount = wc()->cart->get_discount_total();
		return [[
			'success' => true,
			'coupon_id' => $coupon->get_id(),
			'code' => $coupon->get_code(),
			'discount_type' => $coupon->get_discount_type(),
			'amount' => $amount,
		]];
	}

	$errors = wc_get_notices('error');

	$errorsString = '';

	foreach ($errors as $key => $value) {
		# code...
		$errorsString .= $value['notice'] . '\n';
	}

	return [['errors' => $errorsString]];
}

function arrow_coupon_fetch(WP_REST_Request $data){
	//Begin Adding Coupon Details
	$params = $data->get_body();
	$virtualCart = new createVirtualCart(json_decode($params, true));
	$coupon_data = $virtualCart->arrow_virtual_wc_validateApplyCoupon();
	return $coupon_data;
}