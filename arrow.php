<?php


$arrow_plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$arrow_plugin_version = $arrow_plugin_data['Version'];
define('ARROW_CURRENT_VERSION', $arrow_plugin_version);

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

function arrowcheckout_activate()
{
	if (!class_exists('woocommerce')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die(__('Please install and activate WooCommerce first. Click the Back button in your browser to continue.'));
	}
}

register_activation_hook(__FILE__, 'arrowcheckout_activate');

function arrowcheckout_gateway_init()
{
	if (!class_exists('woocommerce')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die(__('WooCommerce not active, Arrow Checkout plugin deactivated. Click the Back button in your browser to continue.'));
	}
	include 'class/class-wc-gateway-arrow.php';
	include 'class/arrowPluginHelper.php';
	require 'restapi/inc/virtualcart.php';
	include 'restapi/shipping.php';
	include 'restapi/coupon.php';

}
add_action('plugins_loaded', 'arrowcheckout_gateway_init');

function arrowcheckout_init()
{
	arrowcheckout_replace_woocommerce_checkout_button();
}
add_action('init', 'arrowcheckout_init');

/**
 * Add method to WooCommerce
 */
function arrowcheckout_add_payment_gateway($methods)
{
	$methods[] = 'WC_Gateway_Arrow';
	return $methods;
}
add_filter('woocommerce_payment_gateways', 'arrowcheckout_add_payment_gateway');

/**
 * Load Arrow's script on front end.
 */
function arrowcheckout_client_scripts()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	if (!isset($gateways['arrow'])) {
		return;
	}

	wp_enqueue_script('arrowcheckout-pbkdf2', plugin_dir_url(__FILE__) . 'assets/libs/pbkdf2.js', array('jquery'), '1.0', false);
	wp_enqueue_script('arrowcheckout-aes', plugin_dir_url(__FILE__) . 'assets/libs/aes.js', array('jquery'), '1.0', false);

	if ('sandbox' === $gateways['arrow']->settings['environment'] && 'yes' === $gateways['arrow']->settings['enabled']) {
		wp_enqueue_script('arrowcheckout-staging', plugin_dir_url(__FILE__) . 'assets/js/arrow-staging-20210507.js', array('jquery'), '1.0', false);
	}


	wp_enqueue_script('arrowcheckout-plugin', plugin_dir_url(__FILE__) . 'assets/js/arrow_plugin.js', array('jquery'), '1.0', false);
	//Localize the script insted of printing the nonce on html dom;
	wp_localize_script('arrowcheckout-plugin', 'ajax_var', array(
		'nonce' => wp_create_nonce('ajax-nonce')
	));
	wp_enqueue_script('arrowcheckout', plugin_dir_url(__FILE__) . 'assets/js/arrow-secure-20210507.js', array('jquery'), '1.0', false);

	wp_enqueue_script('abTest', plugin_dir_url(__FILE__) . 'assets/js/ab-test.js', array('jquery'), '1.0', false);
    wp_localize_script('abTest', 'currentPage', array('cartPage' => is_cart(), 'productPage' => is_product()));

	wp_enqueue_script('arrowcheckout-button', "https://arrow-cdn.s3.amazonaws.com/media/button/v1.02/arrow-button.js", array('jquery'), '1.02', false);

	wp_enqueue_style('arrowcheckout-css', plugin_dir_url(__FILE__) . 'assets/css/arrow.css', array(), '1.0');

	wp_register_script('arrowAddDeliveryDateJs',  plugin_dir_url(__FILE__) . 'assets/js/ord-lite-date-plugin.js', array('jquery'), '1.1', false);
	wp_register_script('arrowTrackAnalyticDataJs',  plugin_dir_url(__FILE__) . 'assets/js/third-party-analytics-tracking.js', array('jquery'), '1.1', false);
}
add_action('wp_enqueue_scripts', 'arrowcheckout_client_scripts');
add_filter('script_loader_tag', function ($tag, $handle) {

	if ('arrowcheckout-button' !== $handle)
		return $tag;

	return str_replace(' src', ' defer="defer" src', $tag);
}, 10, 2);

/**
 * Switch checkout button with Arrow
 */
function arrowcheckout_replace_woocommerce_checkout_button()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	if (!isset($gateways['arrow'])) {
		return;
	}

	$other_gateways = arrowcheckout_has_other_gateways($gateways);

	//Get Arrow option value if it is set test mode enable or disabled.
	$arrow_admintestmode = $gateways['arrow']->arrow_admintestmode;

	//Implimentd conditons if user test mode is enabled and user must login and this user type must be admin or test mod is desabled then the arrow checkout buton will be replace the defalt check out button.
	if (strpos($arrow_admintestmode, 'y') !== false) {
			$arrow_admintestmode =='yes';
	}else{
			$arrow_admintestmode =='no';
	}
	if (($arrow_admintestmode == 'yes' && is_user_logged_in() && current_user_can('manage_options')) || ($arrow_admintestmode == 'no')) {
		if (!$other_gateways) {
			remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
			add_action('woocommerce_checkout_billing', 'arrowcheckout_checkout_billing_button', 1);
		}
		add_action('woocommerce_proceed_to_checkout', 'arrowcheckout_checkout_button_express_cart', 1);

		// 1-Click Checkout on SKU Level
		if ($gateways['arrow']->settings['sku_level_one_click_checkout'] == 'yes') {
			add_action('woocommerce_after_add_to_cart_button', 'arrowcheckout_add_single_product_express_checkout_button');
		}

		// Temporarily don't affect minicart
		// for minicart widget
		if ($gateways['arrow']->settings['checkout_on_minicart'] == 'yes') {
			if (!$other_gateways) {
				remove_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10);
			}
			remove_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20);
			add_action('woocommerce_widget_shopping_cart_buttons', 'arrowcheckout_checkout_button_express', 10);
		}
	}
}
// add_action( 'template_redirect', 'arrowcheckout_replace_woocommerce_checkout_button' );
// add_action( 'wc_ajax_update_shipping_method', 'arrowcheckout_replace_woocommerce_checkout_button' );
// add_action( 'wc_ajax_get_refreshed_fragments', 'arrowcheckout_replace_woocommerce_checkout_button' );

function trigger_checkout_button_change($params, $handle)
{

	switch ($handle) {

		case 'wc-add-to-cart':
			arrowcheckout_replace_woocommerce_checkout_button();
			break;
	}
	return $params;
}
add_filter('woocommerce_get_script_data', 'trigger_checkout_button_change', 10, 2);

/**
 * Arrow button on cart.
 */
function arrowcheckout_checkout_button()
{
	$icon = plugin_dir_url(__FILE__) . 'assets/images/arrow-icon.png';
?>
	<button class="arrow_checkout_button" id="arrow-btn">
		<div id="arrow"><span>Checkout</span> <img src="<?php echo esc_attr($icon); ?>"></div>
	</button>
<?php
}

/**
 * Arrow button on cart.
 */
function arrowcheckout_checkout_button_express()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	$supported_payment_method = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
	$get_payment_enable_method_list = $gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];
	$lisAllPaymentArr = arrow_getPaymentImageArray();
	include plugin_dir_path(__FILE__) . './templates/place-order-express.php';
}

function arrowcheckout_checkout_button_express_sku()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	$supported_payment_method = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
	$get_payment_enable_method_list = $gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];
	$lisAllPaymentArr = arrow_getPaymentImageArray();
	include plugin_dir_path(__FILE__) . './templates/place-order-express-sku.php';
}

function arrowcheckout_checkout_button_express_cart()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	if (!isset($gateways['arrow'])) {
		return;
	}

	$other_gateways = arrowcheckout_has_other_gateways($gateways);

	$supported_payment_method = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
	$get_payment_enable_method_list = $gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];
	$lisAllPaymentArr = arrow_getPaymentImageArray();
	include plugin_dir_path(__FILE__) . './templates/place-order-express-cart.php';

	if ($other_gateways) {
		echo "<p style=\"text-align:center;margin:0;padding:15px;\">OR</p>";
	}
}


/**
 * Arrow button on checkout.
 */
function arrowcheckout_checkout_billing_button()
{
?>
	<div id="arrow-checkout-billing-button">
		<p class="line"><span>Express Checkout</span></p>
		<div class="arrow-container">
			<?php arrowcheckout_checkout_button_express(); ?>
		</div>

		<p>OR</p>
	</div>
<?php
}

/**
 * Function to add the Archived Status
 * @return void
 */
function arrow_register_archived_status()
{
    register_post_status('wc-archived', array(
        'label' => 'Archived',
        'public' => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list' => true,
        'exclude_from_search' => false,
    ));
}

add_action('init', 'arrow_register_archived_status');

/**
 * @param $order_statuses
 * @return array
 */
function arrow_add_archived_to_order_statuses($order_statuses)
{
    $new_order_statuses = array();

    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
    }
    if (!key_exists('wc-archived', $new_order_statuses)) {
        $new_order_statuses['wc-archived'] = 'Archived';
    }

    return $new_order_statuses;
}

add_filter('wc_order_statuses', 'arrow_add_archived_to_order_statuses');

/**
 * Function to get the orders with payment pending status and paid via Arrow only
 * @return void
 */
function arrow_get_pending_payment_orders()
{
    global $wpdb;

    $maxDays = get_option('woocommerce_arrow_settings')['max_order_age'];
    if (empty($maxDays)) {
        $maxDays = 2; //default days to archive the orders
    }

    $maxAge = date('Y-m-d H:i:s', strtotime("-$maxDays days"));

    //select the orders
    $sqlSelect = "SELECT p.ID FROM $wpdb->posts p 
    left join $wpdb->postmeta pm on p.ID = pm.post_id 
    where p.post_date < '$maxAge' AND p.post_status = 'wc-pending' AND p.post_type = 'shop_order' AND pm.meta_value='arrow'";

    $orders = $wpdb->get_results($sqlSelect);

    if (!empty($orders)) {
        $orderIDs = [];
        foreach ($orders as $order) {
            $orderIDs[] = $order->ID;
            add_order_note($order->ID, 'Order Status changed from Pending to Archived via Cron');
        }

        $implodeIds = implode(',', $orderIDs);

        $sqlUpdate = "UPDATE $wpdb->posts p SET p.post_status = 'wc-archived' WHERE p.ID IN ($implodeIds)";
        $wpdb->query($sqlUpdate);

    }

}

add_action('update_order_status', 'arrow_get_pending_payment_orders');

//Check if the Archive job is not already scheduled then schedule the job
if (!wp_next_scheduled('update_order_status')) {
    wp_schedule_event(time(), 'daily', 'update_order_status', [], true);
}

//add_action('woocommerce_before_cart', 'arrow_get_pending_payment_orders');


if ( is_admin() ) {
    add_action( 'admin_menu', 'arrow_add_archive_orders_menu_entry' );
}

/**
 * @return void
 */
function arrow_add_archive_orders_menu_entry() {
    add_submenu_page( 'woocommerce', 'Archived Orders', 'Archived Orders', 'manage_options', 'archived-orders', 'arrow_generate_archive_orders_page' );
}

/**
 * Get all the archived orders
 * @return void
 */
function arrow_generate_archive_orders_page() {
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => 'wc-archived',
        'posts_per_page' => -1
    );

    $query = new WP_Query($args);
    if ( $query->have_posts() ):
        $html = '<table class="wp-list-table widefat fixed striped table-view-list posts">
                <thead>
                    <tr>
                        <th class="manage-column column-order_number column-primary">Order</th>
                        <th class="manage-column column-order_number column-primary">Date</th>
                        <th class="manage-column column-order_number column-primary">Status</th>
                        <th class="manage-column column-order_number column-primary">Total</th>
                    </tr>
                </thead>
                <tbody>
                ';
        while ( $query->have_posts() ) : $query->the_post();

        $order = wc_get_order($query->post->ID);
        $html.='<tr>
                    <td class="order_number column-order_number has-row-actions column-primary">'.$order->get_user()->data->display_name.'</td>
                    <td class="order_date column-order_date">'.$order->get_date_created().'</td>
                    <td class="order_status column-order_status">'.$order->get_status().'</td>
                    <td class="order_total column-order_total">'.$order->get_formatted_order_total().'</td>
                </tr>';

        endwhile;
        $html.= '</tbody></table>';
        echo paginate_links($args);
        echo $html;
        wp_reset_postdata();
        endif;

}

/**
 * Function to hide archive orders from the Admin order listing
 * @param $query
 * @return void
 */
function arrow_hide_archived_orders( $query ) {
    global $pagenow;

    $hide_order_status = 'wc-archived';

    $query_vars = &$query->query_vars;

    if ( $pagenow == 'edit.php' && $query_vars['post_type'] == 'shop_order' ) {

        if ( is_array( $query_vars['post_status'] ) ) {
            if ( ( $key = array_search( $hide_order_status, $query_vars['post_status'] ) ) !== false ) {
                unset( $query_vars['post_status'][$key] );
            }
        }
    }

}

add_action( 'parse_query', 'arrow_hide_archived_orders' );

/**
 * @param $order_id
 * @param $note
 * @return false|int
 */
function add_order_note($order_id, $note){
    $commentdata = apply_filters( 'woocommerce_new_order_note_data',
        array(
            'comment_post_ID'      => $order_id,
            'comment_author'       => __( 'WooCommerce', 'woo-archive-orders' ),
            'comment_author_email' => strtolower(__( 'WooCommerce', 'woo-archive-orders' )). '@' .site_url(),
            'comment_author_url'   => '',
            'comment_content'      => $note,
            'comment_agent'        => 'WooCommerceArchiveOrder',
            'comment_type'         => 'order_note',
            'comment_parent'       => 0,
            'comment_approved'     => 1,
        ),
        array(
            'order_id'         => $order_id,
            'is_customer_note' => false,
        )
    );
    return wp_insert_comment( $commentdata );
}

/**
 * Register the route 'arrow/v1/text' with the function 'process_my_request'
 */
function arrowcheckout_register_routes()
{
	register_rest_route(
		'arrow/v1',
		'/test',
		array(
			'methods'             => 'POST',
			'callback'            => array(new WC_Gateway_Arrow(), 'process_my_request'),
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
			'callback'            => array(new WC_Gateway_Arrow(), 'success_callback'),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'validate_callback' => function ($param, $request, $key) {
						return is_numeric($param);
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

	// this is for 1.3.0
	// register_rest_route(
	// 	'arrow/v1',
	// 	'/fetch_coupon',
	// 	array(
	// 		'methods'             => 'POST',
	// 		'callback'            => "arrowoneclick_verify_coupon",
	// 		'permission_callback' => '__return_true',
	// 	)
	// );
	
	// API To fetch Coupon details from Woocomerce
	register_rest_route(
		'arrow/v1',
		'coupon/fetch/',
		array(
			'methods' => 'POST',
			'callback' => "arrow_coupon_fetch",
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'arrow/v1',
		'pluginversion',
		array(
			'methods' => 'GET',
			'callback' => "pluginversions",
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'arrow/v1',
		'get_all_products',
		array(
			'methods' => 'GET',
			'callback' => "get_all_products",
			'permission_callback' => '__return_true',
		)
	);
}
add_action('rest_api_init', 'arrowcheckout_register_routes');

/**
 * Replace default place order button.
 */
function arrowcheckout_replace_place_order_button_action()
{
	// return file_get_contents(plugin_dir_path( __FILE__ ) . './templates/place-order-standard-checkout.php');
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	if (!isset($gateways['arrow'])) {
		return;
	}

	// check standard checkout
	if ($gateways['arrow']->settings['checkout_type'] == 'standard-checkout') {
		// if there are other payment
		$other_gateways = arrowcheckout_has_other_gateways($gateways);
		if ($other_gateways) {
			echo '<style>ul.wc_payment_methods {display: block !important}</style>';
		} else {
			add_filter('woocommerce_order_button_html', 'arrowcheckout_replace_place_order_button');
			echo '<script type="text/javascript">
				$("#payment_method_arrow").click();
			</script>';
		}
	} else {
		echo '<style>ul.wc_payment_methods {display: block !important} .payment_method_arrow {
		}</style>';
		//		echo '<script type="text/javascript">
		//		$(".payment_method_arrow").remove();
		//	</script>';
	}
}

function arrowcheckout_replace_place_order_button()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	//Get Arrow option value if it is set test mode enable or disabled.
	$arrow_admintestmode = $gateways['arrow']->arrow_admintestmode;
	if (($arrow_admintestmode == 'yes' && is_user_logged_in() && current_user_can('manage_options')) || ($arrow_admintestmode == 'no')) {
		$supported_payment_method = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
		$lisAllPaymentArr = arrow_getPaymentImageArray();
		include plugin_dir_path(__FILE__) . './templates/place-order-standard-checkout.php';
	}
}

add_action('woocommerce_review_order_before_submit', 'arrowcheckout_replace_place_order_button_action', 30);

/**
 * Replace checkout buttons
 */
function arrowcheckout_add_single_product_express_checkout_button()
{
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	$supported_payment_method = $gateways['arrow']->settings['supported_payment_method'] ?? 'Cards';
	$lisAllPaymentArr = arrow_getPaymentImageArray();
	include plugin_dir_path(__FILE__) . './templates/place-order-express-sku.php';
}

function arrowcheckout_add_base_url()
{
	// include './templates/header_code.php';
	echo '<script type="text/javascript">
  window.arrowAjaxBaseUri = "' . admin_url('admin-ajax.php') . '";
	window.arrowCheckoutNonce = "' . wp_create_nonce('express_checkout') . '";
	</script>';
}

add_action('wp_head', 'arrowcheckout_add_base_url');

/**
 * Create order for quick checkout
 */
function arrowcheckout_start_express_checkout()
{
	//Verieng the nonce posted by ajax method from arrow_plugin.js
	if (isset($_POST['nonce']) &&  wp_verify_nonce($_POST['nonce'])) {
		die();
	}
	$cart = WC()->cart->get_cart_contents();
	if (empty($cart)) {
		$res['errors'] = "Checkout Failed. Please Try Again";

		wp_send_json($res, 400);
		die();
	}

	$order_id = WC()->checkout()->create_order([
		'billing_email' => wp_get_current_user() !== 0 ? wp_get_current_user()->user_email : 'customer-checkout@witharrow.co',
		'payment_method' => 'arrow',
	]);

	$res = [];
	if (is_wp_error($order_id)) {
		$res['errors'] = $order_id->errors;

		wp_send_json($res);
		die();
	} else {
		$res['order_id'] = $order_id;
		$order = wc_get_order($order_id);
	}

	if(isset($_POST['buttonType']) && (!empty($_POST['buttonType']))){
		$buttonType = $_POST['buttonType'];
	}else{
		$buttonType = '';
	}
	$gateway = new WC_Gateway_Arrow();
	$result = $gateway->process_payment($order_id,$buttonType);

	$res['arrow_result'] = $result;

	wp_send_json($res);
	wp_die();
}

add_action('wp_ajax_express_checkout', 'arrowcheckout_start_express_checkout');
add_action('wp_ajax_nopriv_express_checkout', 'arrowcheckout_start_express_checkout');

function arrowcheckout_disable_checkout_page()
{
	$gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if (!isset($gateways['arrow'])) {
		return;
	}

	$other_gateways = arrowcheckout_has_other_gateways($gateways);

	if ('one-click-checkout' === $gateways['arrow']->settings['checkout_type'] && !$other_gateways) {
		if (!is_checkout() || (is_checkout() && is_wc_endpoint_url())) return;

		if (WC()->cart->is_empty()) {
			// If empty cart redirect to home
			wp_redirect(home_url('shop'), 302);
		} else {
			// Else redirect to check out url
			wp_redirect(wc_get_cart_url(), 302);
		}

		exit;
	}
}
add_action('template_redirect', 'arrowcheckout_disable_checkout_page');

function arrowcheckout_has_other_gateways($other_gateways)
{
	unset($other_gateways['arrow']);
	return count($other_gateways);
}

/**
 * Function to hide the Arrow paymetn gatway on checkout page.
 * We will add the condition if the page is checkout page then will unset our Arrow payment gatway.
 */
add_filter('woocommerce_available_payment_gateways', 'hide_arrow_payment_gateways', 100, 1);
function hide_arrow_payment_gateways($available_gateways)
{
	if (is_checkout()) {
		// Disable Arrow
		if (isset($available_gateways['arrow'])) {
			unset($available_gateways['arrow']);
		}
	}
	return $available_gateways;
}

function pluginversions(){
	if( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin_data = get_plugin_data( __FILE__ );
	$result = array('status'=>'pass','result'=>$plugin_data['Version'],'message'=>1);
	return $result;
}

add_action( 'woocommerce_after_cart', 'arrow_trackAnalyticDataJs');
function arrow_trackAnalyticDataJs(){
	wp_enqueue_script('arrowTrackAnalyticDataJs');
	wp_localize_script( 'arrowTrackAnalyticDataJs', 'ajaxVar',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		)
	);
}

add_action( 'woocommerce_after_cart', 'arrow_addDeliveryDateJs');
function arrow_addDeliveryDateJs(){
	if(class_exists('Order_Delivery_Date_Lite') || (class_exists('order_delivery_date'))){
		wp_enqueue_script('arrowAddDeliveryDateJs');
		wp_localize_script( 'arrowAddDeliveryDateJs', 'ajaxVar',
			array( 
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
}

function arrow_setdeliverydate(){
	$date = esc_attr($_POST['delv_date']);
	$time = esc_attr($_POST['delv_time']);
	WC()->session->set( 'arrow_deliverydate', $date);
	WC()->session->set( 'arrow_deliverytime', $time);
	return true;
	wp_die();
}
add_action( 'wp_ajax_setdelivery_session', 'arrow_setdeliverydate' );
add_action( 'wp_ajax_nopriv_setdelivery_session', 'arrow_setdeliverydate' );

function arrow_setanalyticdata(){
	$arrow_ga_clientId = esc_attr($_POST['arrow_ga_clientId']);
	$arrow_event_source_url = esc_attr($_POST['arrow_event_source_url']);
	$arrow_fbp = esc_attr($_POST['arrow_fbp']);
	$arrow_fbc = esc_attr($_POST['arrow_fbc']);
	$arrow_external_id = esc_attr($_POST['arrow_external_id']);


	WC()->session->set( 'arrow_ga_clientId', $arrow_ga_clientId);
	WC()->session->set( 'arrow_event_source_url', $arrow_event_source_url);
	WC()->session->set( 'arrow_fbp', $arrow_fbp);
	WC()->session->set( 'arrow_fbc', $arrow_fbc);
	WC()->session->set( 'arrow_external_id', $arrow_external_id);


	return true;
	wp_die();
}

add_action( 'wp_ajax_setanalyticdata_session', 'arrow_setanalyticdata' );
add_action( 'wp_ajax_nopriv_setanalyticdata_session', 'arrow_setanalyticdata' );

function arrow_getPaymentImageArray(){
	$listAllPayment = '{"visa": "false","master": "false","paynow": "false","fpx": "false","maybank": "false","cimb": "false","grabpay": "false","atome": "false","bca": "false","bank_bri": "false","gopay": "false","ovo": "false","bni": "false","mandiri": "false","alfamart": "false","indomaret": "false","permata": "false","kredivo": "false","akulaku": "false","shopppay": "false","dana": "false","link_aja": "false","sakuku": "false","sakuku": "false","wechat": "false","maestro": "false","stripe": "false","kiplepay": "false","paypal": "false","mcash": "false","nets": "false","jcb": "false","union": "false","american": "false","touch_n_go": "false","boost": "false","rhb": "false","hongleong": "false","affin_bank": "false" }';
	$lisAllPaymentArr = json_decode($listAllPayment,true);
	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	$get_payment_enable_method_list = $gateways['arrow']->settings['arrow_pay_method_image_on_checkout_button'];
	foreach($lisAllPaymentArr as $key=>$val){
		if(in_array($key,$get_payment_enable_method_list)){
			$lisAllPaymentArr["$key"] = "true";
		}
	}
	return $lisAllPaymentArr;
}

add_filter( 'wp_new_user_notification_email' , 'edit_user_notification_email', 10, 3 );
function edit_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
	$password = explode('@',$user->user_email);
	//wp_update_user(array('ID' => $userid, 'user_pass' => $password[0]))
	wp_set_password($password[0],$user->ID);
	
	$headers = "MIME-Version: 1.0" . "\r\n"; 
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	
	$message = '<b>Hi '.$user->first_name.' '.$user->last_name.',</b>';
	$message .='<p>Thank you for checking out using Arrow! We have just created a customer account for you on '.$blogname.'’s online store, so that you can easily track your orders and enjoy various merchant-offered benefits from '.$blogname.'.</p>'; 
	$message .='<p>Below are the details of your '.$blogname.' customer account login:</p>'; 
	$message .='<p><b>Username: '.$user->user_login.'</b></p>'; 
	$message .='<p><b>Password: '.$password[0].'</b></p>'; 
	$message .='<p>While this is an account specific to the '.$blogname.' store, you could also <a href="https://shop.witharrow.co/" target="_blank">login to your Arrow account here</a> using your email address to track your orders across various stores powered by Arrow Checkout.</p>';
	
	$wp_new_user_notification_email['headers'] = $headers;
    $wp_new_user_notification_email['message'] = $message;
    return $wp_new_user_notification_email;
}

/**
 * @param $product
 *
 * @return array
 */
/*function get_images( $product ) {
	$images        = $attachment_ids = array();
	$product_image = $product->get_image_id();

	// Add featured image.
	if ( ! empty( $product_image ) ) {
		$attachment_ids[] = $product_image;
	}

	// Add gallery images.
	$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

	// Build image data.
	foreach ( $attachment_ids as $position => $attachment_id ) {

		$attachment_post = get_post( $attachment_id );

		if ( is_null( $attachment_post ) ) {
			continue;
		}

		$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );

		if ( ! is_array( $attachment ) ) {
			continue;
		}

		$images[] = array(
			'id'         => (int) $attachment_id,
			'created_at' =>  $attachment_post->post_date_gmt ,
			'updated_at' =>  $attachment_post->post_modified_gmt ,
			'src'        => current( $attachment ),
			'title'      => get_the_title( $attachment_id ),
			'alt'        => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'position'   => (int) $position,
		);
	}

	// Set a placeholder image if the product has no images set.
	if ( empty( $images ) ) {

		$images[] = array(
			'id'         => 0,
			'created_at' =>  time() , // Default to now.
			'updated_at' =>  time(),
			'src'        => wc_placeholder_img_src(),
			'title'      => __( 'Placeholder', 'woocommerce' ),
			'alt'        => __( 'Placeholder', 'woocommerce' ),
			'position'   => 0,
		);
	}

	return $images;
}*/

/**
 * @param $product
 *
 * @return array
 */
/*function get_attributes( $product ) {

	$attributes = array();

	if ( $product->is_type( 'variation' ) ) {

		// variation attributes
		foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {

			// taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`
			$attributes[] = array(
				'name'   => wc_attribute_label( str_replace( 'attribute_', '', $attribute_name ), $product ),
				'slug'   => str_replace( 'attribute_', '', wc_attribute_taxonomy_slug( $attribute_name ) ),
				'option' => $attribute,
			);
		}
	} else {

		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[] = array(
				'name'      => wc_attribute_label( $attribute['name'], $product ),
				'slug'      => wc_attribute_taxonomy_slug( $attribute['name'] ),
				'position'  => (int) $attribute['position'],
				'visible'   => (bool) $attribute['is_visible'],
				'variation' => (bool) $attribute['is_variation'],
				'options'   => get_attribute_options( $product->get_id(), $attribute ),
			);
		}
	}

	return $attributes;
}*/

/**
 * @param $product_id
 * @param $attribute
 *
 * @return array
 */
/*function get_attribute_options( $product_id, $attribute ) {
	if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
		return wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'names' ) );
	} elseif ( isset( $attribute['value'] ) ) {
		return array_map( 'trim', explode( '|', $attribute['value'] ) );
	}

	return array();
}*/

/**
 * @param $product
 *
 * @return array
 */
/*function get_grouped_products_data( $product ) {
	$products = array();

	foreach ( $product->get_children() as $child_id ) {
		$_product = wc_get_product( $child_id );

		if ( ! $_product || ! $_product->exists() ) {
			continue;
		}

		$products[] = $this->get_product_data( $_product );

	}

	return $products;
}*/

/**
 * @param $product
 *
 * @return array
 */
/*function get_variation_data( $product ) {
	$variations = array();

	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );

		if ( ! $variation || ! $variation->exists() ) {
			continue;
		}

		$variations[] = array(
			'id'                 => $variation->get_id(),
			'created_at'         =>  $variation->get_date_created(),
			'updated_at'         =>  $variation->get_date_modified(),
			'downloadable'       => $variation->is_downloadable(),
			'virtual'            => $variation->is_virtual(),
			'permalink'          => $variation->get_permalink(),
			'sku'                => $variation->get_sku(),
			'price'              => $variation->get_price(),
			'regular_price'      => $variation->get_regular_price(),
			'sale_price'         => $variation->get_sale_price() ? $variation->get_sale_price() : null,
			'taxable'            => $variation->is_taxable(),
			'tax_status'         => $variation->get_tax_status(),
			'tax_class'          => $variation->get_tax_class(),
			'managing_stock'     => $variation->managing_stock(),
			'stock_quantity'     => $variation->get_stock_quantity(),
			'in_stock'           => $variation->is_in_stock(),
			'backorders_allowed' => $variation->backorders_allowed(),
			'backordered'        => $variation->is_on_backorder(),
			'purchaseable'       => $variation->is_purchasable(),
			'visible'            => $variation->is_visible(),
			'on_sale'            => $variation->is_on_sale(),
			'weight'             => $variation->get_weight() ? $variation->get_weight() : null,
			'dimensions'         => array(
				'length' => $variation->get_length(),
				'width'  => $variation->get_width(),
				'height' => $variation->get_height(),
				'unit'   => get_option( 'woocommerce_dimension_unit' ),
			),
			'shipping_class'    => $variation->get_shipping_class(),
			'shipping_class_id' => ( 0 !== $variation->get_shipping_class_id() ) ? $variation->get_shipping_class_id() : null,
			'image'             => get_images( $variation ),
			'attributes'        => get_attributes( $variation ),
			'downloads'         => $variation->get_downloads(),
			'download_limit'    => (int) $product->get_download_limit(),
			'download_expiry'   => (int) $product->get_download_expiry(),
		);
	}

	return $variations;
}*/

/**
 * @return WP_REST_Response
 */
/*function get_all_products() {
    $acceptedTypes  =   ['simple', 'grouped', 'variable'];

	$limit     = ( ( isset( $_GET['limit'] ) ) && ( ! empty( $_GET['limit'] ) ) && ( preg_match( '/^\d+$/', $_GET['limit'] ) ) ) ? $_GET['limit'] : 10;
	$page      = ( ( isset( $_GET['page'] ) ) && ( ! empty( $_GET['page'] ) ) && ( preg_match( '/^\d+$/', $_GET['page'] ) ) ) ? $_GET['page'] : 1;
	$sortOrder = ( ! empty( $_GET['sort_order'] ) ) ? $_GET['sort_order'] : 'ASC'; //default sort order is ASC

	$data = array();
	$args = array(
		'paginate' => true,
		'order_by' => 'ID',
		'status'    => 'publish',
		'limit'    => $limit,
		'order'    => $sortOrder,
		'page'     => $page,
	);


	$products = wc_get_products( $args );


	foreach ( $products->products as $product ) {
        $type   =   $product->get_type();
        if ( in_array($type, $acceptedTypes) || $product->is_virtual() ) {
	        $data['data'][] = [
		        'id'                 => $product->get_id(),
		        'name'               => $product->get_name(),
		        'slug'               => $product->get_slug(),
		        'permalink'          => $product->get_permalink(),
		        'date_created'       => $product->get_date_created(),
		        'date_modified'      => $product->get_date_modified(),
		        'type'               => $type,
		        'status'             => $product->get_status(),
		        'featured'           => $product->get_featured(),
		        'catalog_visibility' => $product->get_catalog_visibility(),
		        'description'        => $product->get_description(),
		        'short_description'  => $product->get_short_description(),
		        'sku'                => $product->get_sku(),
		        'price'              => $product->get_price(),
		        'regular_price'      => $product->get_regular_price(),
		        'sale_price'         => $product->get_sale_price(),
		        'date_on_sale_from'  => $product->get_date_on_sale_from(),
		        'date_on_sale_to'    => $product->get_date_on_sale_to(),
		        'price_html'         => $product->get_price_html(),
		        'on_sale'            => $product->is_on_sale(),
		        'purchasable'        => $product->is_purchasable(),
		        'total_sales'        => $product->get_total_sales(),
		        'virtual'            => $product->is_virtual(),
		        'downloadable'       => $product->is_downloadable(),
		        'downloads'          => $product->get_downloads(),
		        'download_limit'     => $product->get_download_limit(),
		        'download_expiry'    => $product->get_download_expiry(),
		        'tax_status'         => $product->get_tax_status(),
		        'tax_class'          => $product->get_tax_class(),
		        'manage_stock'       => $product->get_manage_stock(),
		        'stock_quantity'     => $product->get_stock_quantity(),
		        'stock_status'       => $product->get_stock_status(),
		        'backorders'         => $product->get_backorders(),
		        'backorders_allowed' => $product->backorders_allowed(),
		        'backordered'        => $product->is_on_backorder(),
		        'sold_individually'  => $product->get_sold_individually(),
		        'weight'             => $product->get_weight(),
		        'dimensions'         => $product->get_dimensions(),
		        'shipping_required'  => $product->needs_shipping(),
		        'shipping_taxable'   => $product->is_shipping_taxable(),
		        'shipping_class'     => $product->get_shipping_class(),
		        'shipping_class_id'  => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
		        'reviews_allowed'    => $product->get_reviews_allowed(),
		        'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
		        'rating_count'       => $product->get_rating_count(),
		        'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
		        'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
		        'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
		        'parent_id'          => $product->get_parent_id(),
		        'purchase_note'      => apply_filters( 'the_content', $product->get_purchase_note() ),
		        'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat' ),
		        'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag' ),
		        'images'             => get_images($product),
		        'attributes'    =>  get_attributes($product),
		        'default_attributes'    =>   $product->get_default_attributes(),
		        'variations'    => get_variation_data($product),
		        'grouped_products'    =>  $product->get_children(),
		        'menu_order'    =>  $product->get_menu_order(),
		        'meta_data' =>  $product->get_meta_data()

	        ];
        }



	}

	$data['current_page'] = $page;
	$data['per_page']     = $limit;
	$data['total']        = $products->max_num_pages;

	return new WP_REST_Response( $data, 200 );
}*/