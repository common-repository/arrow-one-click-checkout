<?php

class createVirtualCart {

	/**
	 * @var string[]
	 */
	private $fakeCustomer = [
		'first_name' => 'Arrow',
		'last_name'  => 'Customer',
		'password'   => 'merch-user',
		'email'      => 'merch-user@projectarrow.co',

	];

	public function __construct( $data ) {
		$this->line_items = $data['line_items']; //It can be an array or prducts
		$this->customer   = $data['customer']; //This must be single arry cantins customer primary details
		$this->promocode  = $data['promocode']; //This must be a single array value contains the coupon code string
	}

	public function arrow_virtual_wc_validateApplyCoupon() {
		$couponCode = wc_format_coupon_code(wp_unslash($this->promocode));
		$coupon     = new WC_Coupon( $couponCode );
		//Get Coupon Details
		$coupon_post = get_post( $coupon->get_id() );
		$coupon_data = array(
			'code'                         => esc_attr( $coupon_post->post_name ),
		);
		if ( $coupon_data['code'] != $couponCode ) {
			$result = array( 'stauts' => 'fail', 'result' => 'Coupon Code Not Exists' );
		} else {
			//If Valid Create virtual Cart and apply on them.
			wc()->frontend_includes();
			$this->arrow_virtual_wc_session();
			$this->arrow_virtual_wc_cart_empty();
			$this->arrow_virtual_wc_customer();
			$this->arrow_virtual_wc_add_to_cart();
			if ( wc()->cart->apply_coupon( $couponCode ) ) {

				$amount       = round( (float) wc()->cart->get_coupon_discount_amount( $couponCode ), 2 );
				$couponResult = array(
					'success'       => true,
					'coupon_id'     => $coupon->get_id(),
					'code'          => $coupon->get_code(),
					'discount_type' => $coupon->get_discount_type(),
					'amount'        => $amount,
				);
				$cartResult   = WC()->cart;
				$result       = array( 'stauts' => 'sucess', 'result' => $couponResult, 'cart' => $cartResult );
			} else {
				$result = array( 'stauts' => 'fail', 'result' => 'Issue In apply coupon! Try Again.' );
			}
		}

		//End Coupon Details
		return $result;
	}

	private function arrow_virtual_wc_session() {
		if ( null === WC()->session ) {
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session  = new $session_class();
			WC()->session->init();
		}
	}

	private function arrow_virtual_wc_customer() {
		if ( null === WC()->customer ) {
			$user = get_user_by( 'email', $this->fakeCustomer['email'] );
			if ( $user ) {
				$user_id       = $user->ID;
				WC()->customer = new WC_Customer( $user_id, false );
			} else {
				WC()->customer = new WC_Customer( 0, false );
				WC()->customer->set_email( $this->fakeCustomer['email'] );
				WC()->customer->set_first_name( $this->fakeCustomer['first_name'] );
				WC()->customer->set_last_name( $this->fakeCustomer['last_name'] );
				WC()->customer->set_username( $this->fakeCustomer['email'] );
				WC()->customer->set_password( $this->fakeCustomer['password'] );
				WC()->customer->save();
			}
		}
	}

	private function arrow_virtual_wc_cart_empty() {
		WC()->cart = new WC_Cart();
		WC()->cart->get_cart();
		// This cart may contain items from prev session empty before using
		WC()->cart->empty_cart();
	}

	private function arrow_virtual_wc_add_to_cart() {
		$this->arrow_virtual_wc_cart_empty();
		if ( ! empty( $this->line_items ) ) {
			foreach ( $this->line_items as $line_item ) {
				$variation_id = ! empty( $line_item['variation_id'] ) ? $line_item['variation_id'] : 0;

				$variation_attribute_values = array();

				// For now hardcode to grab first object as we shouldnt need more.
				if ( ! empty( $line_item['variation_attribute_values'] ) ) {
					// If there are attributes use it when adding item to cart which are required to get shipping options back.
					$variation_attribute_values = $line_item['variation_attribute_values'];
				}
				try {
					WC()->cart->add_to_cart( $line_item['product_id'], $line_item['quantity'], $variation_id, $variation_attribute_values );
				} catch ( \Exception $e ) {
					$e->getMessage();
				}
			}
		}
	}
}
