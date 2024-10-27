<?php
if ( ! class_exists( 'Routes' ) ) {
	class Routes {
		/**
		 * @return void
		 */
		public function registerRestRoutes() {

			register_rest_route(
				'arrow/v1',
				'/test',
				array(
					'methods'             => 'POST',
					'callback'            => array( new WC_Gateway_Arrow(), 'process_my_request' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'arrow/v1',
				'/cart',
				array(
					'methods'             => 'POST',
					'callback'            => array(
						new WC_Gateway_Arrow(),
						'get_cart_items',
					),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'arrow/v1',
				'/success/(?P<id>\d+)',
				array(
					'methods'             => 'GET',
					'callback'            => array( new WC_Gateway_Arrow(), 'success_callback' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				)
			);

			register_rest_route(
				'arrow/v1',
				'/fetch_shipping',
				array(
					'methods'             => 'POST',
					'callback'            => "arrowoneclick_calculate_shipping",
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'arrow/v1',
				'coupon/fetch/',
				array(
					'methods'             => 'POST',
					'callback'            => "arrow_coupon_fetch",
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'arrow/v1',
				'get_all_products',
				array(
					'methods' => 'GET',
					'callback' => array(new Products(), 'getAllProducts'),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'arrow/v1',
				'pluginversion',
				array(
					'methods'             => 'GET',
					'callback'            => "pluginversions",
					'permission_callback' => '__return_true',
				)
			);
			register_rest_route(
				'arrow/v1',
				'/success/orderhash(?:/(?P<arrowOrderId>[a-zA-Z0-9-]+))?',
				array(
					'methods'             => 'GET',
					'callback'            => array( new WC_Gateway_Arrow(), 'success_paylink' ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

}
