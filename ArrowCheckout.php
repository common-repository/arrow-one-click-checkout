<?php

/**
 * Plugin Name:       Arrow One-Click Checkout V3
 * Plugin URI:        https://www.arrowcheckout.com/
 * Description:       #1 Checkout for Online Businesses in Southeast Asia
 * Version:           1.4.29.8
 * Requires at least: 5.3
 * Requires PHP:      7.0
 * Author:            Arrow Checkout
 * Author URI:        https://www.arrowcheckout.com/
 * License:           All rights reserved
 * License URI:       https://www.arrowcheckout.com/
 */

if ( ! class_exists( 'ArrowCheckout' ) ) {
	class ArrowCheckout {
		private $arrowPluginData, $arrowPluginVersion;
		private static $woocomInstalled;
		private $gateways;

		public function __construct() {
			$this->arrowPluginData    = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
			$this->arrowPluginVersion = $this->arrowPluginData['Version'];

			define( 'ARROW_CURRENT_VERSION', $this->arrowPluginVersion );

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			self::$woocomInstalled = $this->checkForWoocom();

			$this->setupActions();

			$this->initializeArrow();

			add_action( 'wp_ajax_express_checkout', [ $this, 'startExpressCheckout' ] );
			add_action( 'wp_ajax_nopriv_express_checkout', [ $this, 'startExpressCheckout' ] );
			add_filter( 'wp_new_user_notification_email', [ $this, 'updateUserNotificationEmail' ] );

			add_action( 'woocommerce_after_cart', [ $this, 'trackAnalyticsDataJs' ] );

			add_action( 'wp_ajax_setanalyticdata_session', [ $this, 'setAnalyticsData' ] );
			add_action( 'wp_ajax_nopriv_setanalyticdata_session', [ $this, 'setAnalyticsData' ] );

			add_action( 'woocommerce_after_cart', [ $this, 'addDeliveryDateJs' ] );

			add_action( 'wp_ajax_setdelivery_session', [ $this, 'setDeliveryDate' ] );
			add_action( 'wp_ajax_nopriv_setdelivery_session', [ $this, 'setDeliveryDate' ] );

			//Add arrow payment gateway
			add_filter( 'woocommerce_payment_gateways', [ $this, 'addArrowPaymentGateway' ] );

		}

		/**
		 * @return void
		 */
		private function initializeArrow() {
			//Initialize Arrow when plugins are loaded
			add_action( 'plugins_loaded', [ $this, 'arrowInit' ] );

//			//Add arrow payment gateway
//			add_filter( 'woocommerce_payment_gateways', [ $this, 'addArrowPaymentGateway' ] );

			//hide Arrow on checkout page
			add_filter( 'woocommerce_available_payment_gateways', [ $this, 'hideArrow' ] );

			//Enqueue client side scripts
			add_action( 'wp_enqueue_scripts', [ $this, 'clientScripts' ] );

			//Replace existing payment gateway with Arrow
			add_action( 'init', [ $this, 'replaceWoocommerceCheckout' ] );



			add_filter( 'script_loader_tag', function ( $tag, $handle ) {

				if ( 'arrowcheckout-button' !== $handle ) {
					return $tag;
				}

				return str_replace( ' src', ' defer="defer" src', $tag );
			}, 10, 2 );

			//Trigger checkout button change
			add_filter( 'woocommerce_get_script_data', [ $this, 'triggerCheckoutButtonChange' ], 10, 2 );

			//Replace Order Button Action before submit
			add_action( 'woocommerce_review_order_before_submit', [ $this, 'replaceOrderButtonAction' ], 30 );

			//Add the baseURLs to WP-head
			add_action( 'wp_head', [ $this, 'addBaseUrl' ] );


		}



		/**
		 * @return bool
		 */
		public function checkForWoocom() {
			if ( is_multisite() ) {
				$networkActivePlugins = get_site_option( 'active_sitewide_plugins' );
				if ( ! empty( $networkActivePlugins['woocommerce/woocommerce.php'] ) ) {
					return true;
				}
			} else {
				return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
			}

			return false;
		}

		/**
		 * Registering the setup hooks
		 * @return void
		 */
		public function setupActions() {
			register_activation_hook( __FILE__, [ 'ArrowCheckout', 'activateArrow' ] );
		}

		/**
		 * Checks if the Woocmmerce is installed, then Activate the plugin
		 * @return void
		 */
		public static function activateArrow() {

			if ( ! self::$woocomInstalled ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( __( 'Please install and activate WooCommerce first. Click the Back button in your browser to continue.' ) );
			}
		}

		/**
		 * Include add the dependant classes after Activation
		 * @return void
		 */
		public function arrowInit() {
			if ( ! self::$woocomInstalled ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( __( 'WooCommerce not active, Arrow Checkout plugin deactivated. Click the Back button in your browser to continue.' ) );
			}

			include 'class/class-wc-gateway-arrow.php';
			include 'class/arrowPluginHelper.php';
			require 'restapi/inc/virtualcart.php';
			include 'restapi/shipping.php';
			include 'restapi/coupon.php';
			include 'class/Products.php';
			include 'class/Routes.php';
			include 'class/Orders.php';

			$this->initOrderFunctions();
			$this->initRoutes();

		}

		/**
		 * Function to init the Order class functions
		 * @return void
		 */
		private function initOrderFunctions() {
			$orders = new Orders();

			//register the Archived Status first of all
			add_action( 'init', function () use ( $orders ) {
				$orders->registerStatus( $orders->archivedStatus['status'], $orders->archivedStatus['label'] );
			} );

			add_action( 'update_order_status', function () use ( $orders ) {
				$orders->updateOrders( $orders->pendingStatus['status'], $orders->metaValue );
			} );

			//Check if the Archive job is not already scheduled then schedule the job
			if ( ! wp_next_scheduled( 'update_order_status' ) ) {
				wp_schedule_event( time(), 'daily', 'update_order_status', [], true );
			}

		}

		/**
		 * @return void
		 */
		private function initRoutes() {
			$routes = new Routes();
			add_action( 'rest_api_init', [ $routes, 'registerRestRoutes' ] );
		}

		/**
		 * Add Arrow to Woocommerce Payment Gateways
		 *
		 * @param $methods
		 *
		 * @return mixed
		 */
		public function addArrowPaymentGateway( $methods ) {

			$methods[] = 'WC_Gateway_Arrow';

			return $methods;
		}

		/**
		 * Switch Default checkout button with Arrow Checkout
		 * @return void
		 */
		public function replaceWoocommerceCheckout() {

			$gateways   =   $this->getGateways();

			if ( empty( $gateways['arrow'] ) ) {
				return;
			}

			$otherGateways = $this->checkForOtherGateways( $gateways );


			$arrow    = $gateways['arrow'];
			$testMode = $arrow->arrow_admintestmode;

			if ( ( $testMode === 'yes' && is_user_logged_in() && current_user_can( 'manage_options' ) ) || ( $testMode === 'no' ) ) {

				if ( ! $otherGateways ) {
					remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
					add_action( 'woocommerce_checkout_billing', [ $this, 'arrowBillingButton' ], 1 );
				}
				add_action( 'woocommerce_proceed_to_checkout', [ $this, 'checkoutButtonExpressCart' ], 1 );

				// 1-Click Checkout on SKU Level

				if ( $gateways['arrow']->settings['sku_level_one_click_checkout'] == 'yes' ) {
					add_action( 'woocommerce_after_add_to_cart_button', [
						$this,
						'singleProductExpressCheckoutButton'
					] );
				}

				// Temporarily don't affect mini cart
				// for mini cart widget
				if ( $gateways['arrow']->settings['checkout_on_minicart'] == 'yes' ) {


					if (  $otherGateways ) {
						remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
					}


					$priority = has_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout');


					remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', $priority );


					add_action( 'woocommerce_widget_shopping_cart_buttons', [
						$this,
						'arrowCheckoutExpressButton'
					], 10 );
				}
			}
		}

		/**
		 * @param $gateways
		 *
		 * @return mixed
		 */
		private function checkForOtherGateways( $gateways ) {
			unset( $gateways['arrow'] );

			return $gateways;
		}

		/**
		 * @return void
		 */
		public function checkoutButtonExpressCart() {

			if ( ! isset( $this->gateways['arrow'] ) ) {
				return;
			}

			$other_gateways = $this->checkForOtherGateways( $this->gateways );

			$supported_payment_method       = $this->gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
			$get_payment_enable_method_list = $this->gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];
			$lisAllPaymentArr               = $this->getPaymentImageArray();

			include plugin_dir_path( __FILE__ ) . './templates/place-order-express.php';

			if ( ! empty( $other_gateways ) ) {
				echo "<p style=\"text-align:center;margin:0;padding:15px;\" class='or-div'>OR</p>";
			}
		}

		/**
		 * @return void
		 */
		public function singleProductExpressCheckoutButton() {


			$supported_payment_method = $this->gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
			$lisAllPaymentArr         = $this->getPaymentImageArray();


			include plugin_dir_path( __FILE__ ) . './templates/place-order-express-sku.php';
		}

		/**
		 * @return void
		 */
		private function arrowBillingButton() {
			echo "<div id='arrow - checkout - billing - button'>
                    <p class='line'><span>Express Checkout</span></p>
                    <div class='arrow-container'>
                        " . $this->arrowCheckoutExpressButton() . "
                    </div>
                    <p>OR</p>
                  </div>";

		}

		/**
		 * @return void
		 */
		public function arrowCheckoutExpressButton() {

			$gateways   =   $this->getGateways();
			$supported_payment_method       = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
			$get_payment_enable_method_list = $gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];
			$lisAllPaymentArr               = $this->getPaymentImageArray();


			include plugin_dir_path( __FILE__ ) . './templates/place-order-express.php';
		}


		/**
		 * Load Arrow script on the Frontend
		 * @return void
		 */
		public function clientScripts() {

			$this->gateways = $this->getGateways();

			if ( ! isset( $this->gateways['arrow'] ) ) {
				return;
			}

			wp_enqueue_script( 'arrowcheckout-pbkdf2', plugin_dir_url( __FILE__ ) . 'assets/libs/pbkdf2.js', array( 'jquery' ), '1.0', false );
			wp_enqueue_script( 'arrowcheckout-aes', plugin_dir_url( __FILE__ ) . 'assets/libs/aes.js', array( 'jquery' ), '1.0', false );

			if ( 'sandbox' === $this->gateways['arrow']->settings['environment'] && 'yes' === $this->gateways['arrow']->settings['enabled'] ) {
				wp_enqueue_script( 'arrowcheckout-staging', plugin_dir_url( __FILE__ ) . 'assets/js/arrow-staging-20210507.js', array( 'jquery' ), '1.0', false );
			}

			wp_enqueue_script( 'arrowcheckout-plugin', 'https://shop.witharrow.co/cdn/arrow_plugin.js', array( 'jquery' ), '1.0', false );
			//Localize the script instead of printing the nonce on html dom;
			wp_localize_script( 'arrowcheckout-plugin', 'ajax_var', array(
				'nonce' => wp_create_nonce( 'ajax-nonce' )
			) );
			wp_enqueue_script( 'arrowcheckout', plugin_dir_url( __FILE__ ) . 'assets/js/arrow-secure-20210507.js', array( 'jquery' ), '1.0', false );

			wp_enqueue_script( 'arrowcheckout-button', "https://arrow-cdn.s3.amazonaws.com/media/button/v1.02/arrow-button.js", array( 'jquery' ), '1.02', false );
			
			####### AB Testing ##############
			/*
			//Comented becuse this can not be part of master now, We have used the "mixpanelKey" static into arrow-secure-20210507.js
			wp_enqueue_script( 'abTest', plugin_dir_url( __FILE__ ) . 'assets/js/ab-test.js', array( 'jquery' ), '1.0', false );
			wp_localize_script( 'abTest', 'currentPage', array( 'cartPage'    => is_cart(),
			                                                    'productPage' => is_product()
			) );
			*/
			########## AB Testing ends here ###############
			wp_register_script( 'arrowAddDeliveryDateJs', plugin_dir_url( __FILE__ ) . 'assets/js/ord-lite-date-plugin.js', array( 'jquery' ), '1.1', false );
			wp_register_script( 'arrowTrackAnalyticDataJs', plugin_dir_url( __FILE__ ) . 'assets/js/third-party-analytics-tracking.js', array( 'jquery' ), '1.1', false );
			wp_enqueue_style( 'arrowcheckout-css', plugin_dir_url( __FILE__ ) . 'assets/css/arrow.css', array(), '1.0' );

		}

		/**
		 * @param $params
		 * @param $handle
		 *
		 * @return mixed
		 */
		public function triggerCheckoutButtonChange( $params, $handle ) {
			if ( $handle == 'wc-add-to-cart' ) {
				$this->replaceWoocommerceCheckout();
			}

			return $params;
		}

		/**
		 * @return void
		 */
		public function replaceOrderButtonAction() {
			if ( ! isset( $this->gateways['arrow'] ) ) {
				return;
			}

			// check standard checkout
			if ( $this->gateways['arrow']->settings['checkout_type'] == 'standard-checkout' ) {
				// if there are other payment
				$other_gateways = $this->checkForOtherGateways( $this->gateways );
				if ( $other_gateways ) {
					echo '<style>ul.wc_payment_methods {display: block !important}</style>';
				} else {
					add_filter( 'woocommerce_order_button_html', [ $this, 'replaceOrderButton' ] );
					echo '<script type="text/javascript">
				$("#payment_method_arrow").click();
			</script>';
				}
			} else {
				echo '<style>ul.wc_payment_methods {display: block !important} .payment_method_arrow {
		}</style>';
			}
		}

		/**
		 * @return void
		 */
		public function replaceOrderButton() {

			//Get Arrow option value if it is set test mode enable or disabled.
			$arrow_admintestmode = $this->gateways['arrow']->arrow_admintestmode;
			if ( ( $arrow_admintestmode === 'yes' && is_user_logged_in() && current_user_can( 'manage_options' ) ) || ( $arrow_admintestmode == 'no' ) ) {
				$supported_payment_method = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
				$lisAllPaymentArr         = $this->getPaymentImageArray();

				include plugin_dir_path( __FILE__ ) . './templates/place-order-standard-checkout.php';
			}
		}

		/**
		 * @return void
		 */
		public function addBaseUrl() {
			echo '<script type="text/javascript">
                    window.arrowAjaxBaseUri = "' . admin_url( 'admin-ajax.php' ) . '";
	                window.arrowCheckoutNonce = "' . wp_create_nonce( 'express_checkout' ) . '";
	              </script>';
		}

		/**
		 * @return mixed
		 */
		public function getPaymentImageArray() {
			$gateways   =   $this->getGateways();
			$listAllPayment   = '{"visa": "false","master": "false","paynow": "false","fpx": "false","maybank": "false","cimb": "false","grabpay": "false","atome": "false","bca": "false","bank_bri": "false","gopay": "false","ovo": "false","bni": "false","mandiri": "false","alfamart": "false","indomaret": "false","permata": "false","kredivo": "false","akulaku": "false","shopppay": "false","dana": "false","link_aja": "false","sakuku": "false","sakuku": "false","wechat": "false","maestro": "false","stripe": "false","kiplepay": "false","paypal": "false","mcash": "false","nets": "false","jcb": "false","union": "false","american": "false","touch_n_go": "false","boost": "false","rhb": "false","hongleong": "false","affin_bank": "false" }';
			$lisAllPaymentArr = json_decode( $listAllPayment, true );

			$get_payment_enable_method_list = $gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];


			foreach ( $lisAllPaymentArr as $key => $val ) {
				if ( in_array( $key, $get_payment_enable_method_list ) ) {
					$lisAllPaymentArr["$key"] = "true";
				}
			}

			return $lisAllPaymentArr;
		}

		/**
		 * @return void
		 * @throws Exception
		 */
		public function startExpressCheckout() {
			//Verifying the nonce posted by ajax method from arrow_plugin.js
			if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'] ) ) {
				die();
			}
			$cart = WC()->cart->get_cart_contents();
			if ( empty( $cart ) ) {
				$res['errors'] = "Checkout Failed. Please Try Again";

				wp_send_json( $res, 400 );
				die();
			}

			$order_id = WC()->checkout()->create_order( [
				'billing_email'  => wp_get_current_user() !== 0 ? wp_get_current_user()->user_email : 'customer-checkout@witharrow.co',
				'payment_method' => 'arrow',
			] );

			$res = [];
			if ( is_wp_error( $order_id ) ) {
				$res['errors'] = $order_id->errors;

				wp_send_json( $res );
				die();
			} else {
				$res['order_id'] = $order_id;
				$order           = wc_get_order( $order_id );
			}

			$gateway = new WC_Gateway_Arrow();
			$result  = $gateway->process_payment( $order_id );

			$res['arrow_result'] = $result;

			wp_send_json( $res );
			wp_die();
		}


		/**
		 * @param $wp_new_user_notification_email
		 * @param $user
		 * @param $blogname
		 *
		 * @return mixed
		 */
		public function updateUserNotificationEmail( $wp_new_user_notification_email, $user, $blogname ) {
			$password = explode( '@', $user->user_email );
			//wp_update_user(array('ID' => $userid, 'user_pass' => $password[0]))
			wp_set_password( $password[0], $user->ID );

			$headers = "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

			$message = '<b>Hi ' . $user->first_name . ' ' . $user->last_name . ',</b>';
			$message .= '<p>Thank you for checking out using Arrow! We have just created a customer account for you on ' . $blogname . '’s online store, so that you can easily track your orders and enjoy various merchant-offered benefits from ' . $blogname . '.</p>';
			$message .= '<p>Below are the details of your ' . $blogname . ' customer account login:</p>';
			$message .= '<p><b>Username: ' . $user->user_login . '</b></p>';
			$message .= '<p><b>Password: ' . $password[0] . '</b></p>';
			$message .= '<p>While this is an account specific to the ' . $blogname . ' store, you could also <a href="https://shop.witharrow.co/" target="_blank">login to your Arrow account here</a> using your email address to track your orders across various stores powered by Arrow Checkout.</p>';

			$wp_new_user_notification_email['headers'] = $headers;
			$wp_new_user_notification_email['message'] = $message;

			return $wp_new_user_notification_email;
		}

		/**
		 * @return void
		 */
		public function trackAnalyticsDataJs() {
			wp_enqueue_script( 'arrowTrackAnalyticDataJs' );
			wp_localize_script( 'arrowTrackAnalyticDataJs', 'ajaxVar',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		/**
		 * @return bool
		 */
		public function setAnalyticsData() {
			$arrow_ga_clientId      = esc_attr( $_POST['arrow_ga_clientId'] );
			$arrow_event_source_url = esc_attr( $_POST['arrow_event_source_url'] );
			$arrow_fbp              = esc_attr( $_POST['arrow_fbp'] );
			$arrow_fbc              = esc_attr( $_POST['arrow_fbc'] );
			$arrow_external_id      = esc_attr( $_POST['arrow_external_id'] );


			WC()->session->set( 'arrow_ga_clientId', $arrow_ga_clientId );
			WC()->session->set( 'arrow_event_source_url', $arrow_event_source_url );
			WC()->session->set( 'arrow_fbp', $arrow_fbp );
			WC()->session->set( 'arrow_fbc', $arrow_fbc );
			WC()->session->set( 'arrow_external_id', $arrow_external_id );


			return true;
			wp_die();
		}

		/**
		 * @return void
		 */
		public function addDeliveryDateJs() {
			if ( class_exists( 'Order_Delivery_Date_Lite' ) || ( class_exists( 'order_delivery_date' ) ) ) {
				wp_enqueue_script( 'arrowAddDeliveryDateJs' );
				wp_localize_script( 'arrowAddDeliveryDateJs', 'ajaxVar',
					array(
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
					)
				);
			}
		}

		/**
		 * @return bool
		 */
		public function setDeliveryDate() {
			$date = esc_attr( $_POST['delv_date'] );
			$time = esc_attr( $_POST['delv_time'] );
			WC()->session->set( 'arrow_deliverydate', $date );
			WC()->session->set( 'arrow_deliverytime', $time );

			return true;
			wp_die();
		}

		/**
		 * @param $gateways
		 *
		 * @return mixed
		 */
		public function hideArrow( $gateways ) {
			if ( is_checkout() ) {
				if ( isset( $gateways['arrow'] ) ) {
					unset( $gateways['arrow'] );
				}
			}

			return $gateways;
		}

		/**
		 * @return array
		 */
		private function getGateways() {
			require_once plugin_dir_path(__DIR__).'woocommerce/woocommerce.php';
			return WC()->payment_gateways()->get_available_payment_gateways();


		}
	}

	//instantiate the plugin here
	$arrow = new ArrowCheckout();
}
