<?php

/**
 * Class ArrowPluginHelper to add the suporting mtheods for WC_Gateway_Arrow
 * This will help to remove the code complexity and code duplicacy
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
class ArrowPluginHelper
{
    /**
     * Method to add Arrow configuration html Form elements under WC setting.
     *
     */
    public function adminFormFields()
    {
        return array(
            'title'                 => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default'     => 'Arrow Checkout',
                'desc_tip'    => true,
            ),
            'description'           => array(
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout', 'woocommerce'),
                'default'     => 'The fastest way to checkout on the Internet',
                'desc_tip'    => true,
            ),
            'max_order_age' => array(
                'description' => __('This controls the archiving of the old orders'),
                'desc_tip'  =>  true,
                'default'   =>  '2',
                'title'   =>  __('Maximum number of days to Archive the orders', 'woocommerce'),
                'type'  =>  'text'
            ),
            'supported_payment_method' => array(
                'title'       => __('Supported Payment method', 'woocommerce'),
                'type'        => 'select',
                'description' => __('Select Supported Payment method', 'woocommerce'),
                'default'    => 'Cards',
                'options'     => array(
                    'PayNow' => __('PayNow', 'woocommerce'),
                    'Cards'  => __('Cards', 'woocommerce'),
                    'Both'  => __('Both', 'woocommerce'),
                ),

            ),
            'checkout_button_learn_more'               => array(
                'title'   => __('Show "Learn more" option on Checkout button', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => 'Yes',
                'default' => 'yes',
            ),
            'sku_level_one_click_checkout'               => array(
                'title'   => __('Show Checkout button on product page', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => 'Yes',
                'default' => 'yes',
            ),
            'checkout_on_minicart'               => array(
                'title'   => __('Show Checkout button on mini/side cart', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => 'Yes',
                'default' => 'yes',
            ),
            'environment'           => array(
                'title'       => __('Environment', 'woocommerce'),
                'type'        => 'select',
                'default'     => 'sandbox',
                'description' => __('Select the environment', 'woocommerce'),
                'options'     => array(
                    'sandbox'    => __('Sandbox', 'woocommerce'),
                    'production' => __('Production', 'woocommerce'),
                ),
                'class'       => 'environment',

            ),
            'client_key_sandbox'    => array(
                'title'       => 'Client Key',
                'type'        => 'text',
                'description' => __('Enter your <b>Sandbox</b> Client Key.', 'woocommerce'),
                'default'     => '',
                'class'       => 'sandbox_settings env',
            ),
            'secret_key_sandbox'    => array(
                'title'       => 'Secret Key',
                'type'        => 'text',
                'description' => __('Enter your <b>Sandbox</b> Secret Key', 'woocommerce'),
                'default'     => '',
                'class'       => 'sandbox_settings env',
            ),
            'username_sandbox'      => array(
                'title'       => 'Username',
                'type'        => 'text',
                'description' => __('Enter your <b>Sandbox</b> Username', 'woocommerce'),
                'default'     => '',
                'class'       => 'sandbox_settings env',
            ),
            'client_key_production' => array(
                'title'       => 'Client Key',
                'type'        => 'text',
                'description' => __('Enter your <b>Production</b> Client Key.', 'woocommerce'),
                'default'     => '',
                'class'       => 'production_settings env',
            ),
            'secret_key_production' => array(
                'title'       => 'Secret Key',
                'type'        => 'text',
                'description' => __('Enter your <b>Production</b> Secret Key', 'woocommerce'),
                'default'     => '',
                'class'       => 'production_settings env',
            ),
            'username_production'   => array(
                'title'       => 'Username',
                'type'        => 'text',
                'description' => __('Enter your <b>Production</b> Username', 'woocommerce'),
                'default'     => '',
                'class'       => 'production_settings env',
            ),
            //Admin Test mode Drop Down
            'arrow_admintestmode' => array(
                'title'       => __('Test Mode', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Enable to display the Arrow checkout buttons only to users logged in as admin', 'woocommerce'),
                'label'   => 'Enable',
                'default' => 'yes',
            ),
            'arrow_pay_method_image_on_checkout_button' => array(
                'title'       => 'Select payment logos to display',
                'type'          => 'multiselect',
                'description' => __('The payment providers selected here will have their logos displayed under Arrow checkout button', 'woocommerce'),
                'options'   => $this->getPaymentMethodLists(),
                'multiple' => true,
                'class' => 'chosen_select',
            ),
        );
    }

  /**
   * @param $order
   *
   * @return array
   */
    public function getOrderItemsArray( $order)
    {
        $requires_shipping = false;
        $items = $order->get_items();
        foreach ($items as  $item) {
            $product_id  = $item->get_id();
            $product = (method_exists($item, 'get_product')) ? $item->get_product() : wc_get_product($product_id);
            $quantity    = $item['quantity'];
            $name        = $item['name'];
            $image_id    = $product->get_image_id();
            $image_url   = wp_get_attachment_image_src($image_id) === false ? '' : wp_get_attachment_image_src($image_id)[0];
            $description = $product->get_description();
            $number_of_decimals = wc_get_price_decimals();
            if (!$product->is_virtual()) {
                $requires_shipping = true;
            }
            $price = round($item['subtotal'] / $item['quantity'], $number_of_decimals);

            if ((function_exists('wc_deposits_woocommerce_is_active')) && (wc_deposits_woocommerce_is_active())) {
                $pid =  $item['product_id'];
                $deposit_amount = wc_deposits_get_product_deposit_amount($pid);
                $amount_type = wc_deposits_get_product_deposit_amount_type($pid);
                $price =  ($deposit_amount > 0) ? $this->getDepositAmount($pid, $product) : round($item['subtotal'] / $item['quantity'], $number_of_decimals);
            } elseif ((isset($item['awcdp_deposit_meta'])) && (!empty($item['awcdp_deposit_meta']))) {
                $awcdpDeposit = $item['awcdp_deposit_meta'];
                $price = ($awcdpDeposit['deposit'] * $item['quantity']);
                $price = round($price / $item['quantity'], $number_of_decimals);
            }
            $returnItems[] = array(
                'name'        => $name,
                'quantity'    => $quantity,
                'image'       => $image_url,
                'price'       => $price,
                'description' => strip_tags($description),
                'extraData'   => array("product_id" => $item['product_id'], "variation_id" => $item["variation_id"], "variation_attribute_values" => $item["variation_attribute_values"], "quantity" => $item["quantity"]),
                'amount_type' => @$amount_type ?? null,
                'deposit_amount' => @$deposit_amount ?? 0,
            );
        }
        return ['items' => $returnItems, 'requires_shipping' => $requires_shipping];
    }

  /**
   * @param int $pid
   * @param $product
   *
   * @return float|int|string
   */
    public function getDepositAmount(int $pid, $product)
    {
        $deposit_amount = wc_deposits_get_product_deposit_amount($pid);
        $amount_type = wc_deposits_get_product_deposit_amount_type($pid);
        $tax_handling = get_option('wc_deposits_taxes_handling', 'split');
        $proPrice = $product->get_price();
        if ($tax_handling === 'deposit') {
            $tax = wc_get_price_including_tax($product, array('price' => $proPrice)) - wc_get_price_excluding_tax($product, array('price' => $proPrice));
        } elseif ($tax_handling === 'split') {
            $tax_total = wc_get_price_including_tax($product, array('price' => $proPrice)) - wc_get_price_excluding_tax($product, array('price' => $proPrice));
            $deposit_percentage = $deposit_amount * 100 / ($proPrice);
            if ($amount_type === 'percent') {
                $deposit_percentage = $deposit_amount;
            }
            $tax = $tax_total * $deposit_percentage / 100;
        }
        switch ($amount_type) {
            case 'fixed':
                //if prices inclusive of tax
                $price = $deposit_amount;
                $price = round($price, wc_get_price_decimals());
                break;
            case 'percent':
                $price = $proPrice * ($deposit_amount / 100.0);
                $price = round($price, wc_get_price_decimals());
                break;
        }
        return  $price - $tax;
    }
    /**
     * Method to get user (shipping,billing) address form wordpress database.
     * @param int $user_id
     * @return $address []
     */
    public function getUserAddress(int $user_id)
    {
        $user_shipping = array(
            'shipping_first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'shipping_last_name' => get_user_meta($user_id, 'shipping_last_name', true),
            'shipping_company' => get_user_meta($user_id, 'shipping_company', true),
            'shipping_address_1' => get_user_meta($user_id, 'shipping_address_1', true),
            'shipping_address_2' => get_user_meta($user_id, 'shipping_address_2', true),
            'shipping_city' => get_user_meta($user_id, 'shipping_city', true),
            'shipping_state' => get_user_meta($user_id, 'shipping_state', true),
            'shipping_postcode' => get_user_meta($user_id, 'shipping_postcode', true),
            'shipping_country' => get_user_meta($user_id, 'shipping_country', true),
            'shipping_email' => get_user_meta($user_id, 'shipping_email', true),
            'shipping_phone' => get_user_meta($user_id, 'shipping_phone', true),
        );
        //Billing Data
        $user_billing = array(
            'billing_first_name' => get_user_meta($user_id, 'billing_first_name', true),
            'billing_last_name' => get_user_meta($user_id, 'billing_last_name', true),
            'billing_company' => get_user_meta($user_id, 'billing_company', true),
            'billing_address_1' => get_user_meta($user_id, 'billing_address_1', true),
            'billing_address_2' => get_user_meta($user_id, 'billing_address_2', true),
            'billing_city' => get_user_meta($user_id, 'billing_city', true),
            'billing_state' => get_user_meta($user_id, 'billing_state', true),
            'billing_postcode' => get_user_meta($user_id, 'billing_postcode', true),
            'billing_country' => get_user_meta($user_id, 'billing_country', true),
            'billing_email' => get_user_meta($user_id, 'billing_email', true),
            'billing_phone' => get_user_meta($user_id, 'billing_phone', true),

        );
        return array(
            'user_shipping' => $user_shipping,
            'user_billing' => $user_billing
        );
    }

  /**
   * @param $arrow_order
   *
   * @return int|WP_Error
   */
    public function orderRegisterUser( $arrow_order)
    {

        $password = explode('@', $arrow_order->customer->email);
        $userDetails = array(
            'user_nicename' => $arrow_order->customer->first_name,
            'display_name' => $arrow_order->customer->first_name,
            'user_login' => $arrow_order->customer->email,
            'first_name' => $arrow_order->customer->first_name,
            'last_name' => $arrow_order->customer->last_name,
            'user_email' => $arrow_order->customer->email,
            'user_pass' => $password[0],
            'role' => 'Customer'
        );
        $user_id = wp_insert_user($userDetails);
        if (!is_wp_error($user_id)) {
            wp_new_user_notification($user_id, '', 'user');
            $this->registeredUserDetailsUpdate($user_id, $arrow_order);
        }
        return $user_id;
    }

  /**
   * @param $order
   * @param $arrow_order
   *
   * @return bool
   */
    public function updateOroderAddress( $order, $arrow_order)
    {
        $address = array(
            'first_name' => $arrow_order->address->firstname,
            'last_name'  => $arrow_order->address->lastname,
            'email'      => $arrow_order->customer->email,
            'phone'      => $arrow_order->address->contact,
            'company'    => '',
            'address_1'  => $arrow_order->address->address,
            'address_2'  => $arrow_order->address->unit,
            'city'       => $arrow_order->address->city,
            'state'      => $arrow_order->address->state,
            'postcode'   => $arrow_order->address->postal_code,
            'country'    => $arrow_order->address->country_code,
        );
        $order->set_address($address);
        /**
         * Lagecy Method to set address of orders need to updated.
         * We have to check if shiping address is set by Arrow Payment Api or not.
         * Default condition will be default address else it will be updated by shipping address.
         */
        if (!empty($arrow_order->shipping_address)) {
            $shippingAddress = array(
                'first_name' => $arrow_order->shipping_address->firstname,
                'last_name'  => $arrow_order->shipping_address->lastname,
                'email'      => $arrow_order->customer->email,
                'phone'      => $arrow_order->shipping_address->contact,
                'company'    => '',
                'address_1'  => $arrow_order->shipping_address->address,
                'address_2'  => $arrow_order->shipping_address->unit,
                'city'       => $arrow_order->shipping_address->city,
                'state'      => $arrow_order->shipping_address->state,
                'postcode'   => $arrow_order->shipping_address->postal_code,
                'country'    => $arrow_order->shipping_address->country_code,
            );
            $order->set_address($shippingAddress, 'shipping');
        } else {
            $order->set_address($address, 'shipping');
        }
        return true;
    }

  /**
   * @param $order
   * @param int $order_id
   * @param $arrow_order
   * @param int $user_id
   *
   * @return bool
   */
    public function updateOrderMeta( $order, int $order_id, $arrow_order, int $user_id)
    {
        update_post_meta($order_id, '_customer_user', $user_id);
        update_post_meta($order_id, '_billing_first_name', $arrow_order->address->firstname);
        update_post_meta($order_id, '_billing_last_name', $arrow_order->address->lastname);
        update_post_meta($order_id, '_billing_address_1', $arrow_order->address->address);
        update_post_meta($order_id, '_billing_address_2', $arrow_order->address->unit);
        update_post_meta($order_id, '_billing_city', $arrow_order->address->city);
        update_post_meta($order_id, '_billing_state', $arrow_order->address->state);
        update_post_meta($order_id, '_billing_postcode', $arrow_order->address->postal_code);
        update_post_meta($order_id, '_billing_country', $arrow_order->address->country_code);
        update_post_meta($order_id, '_billing_phone', $arrow_order->address->contact);
        update_post_meta($order_id, '_billing_email', $arrow_order->customer->email);
        if (!empty($arrow_order->shipping_address)) {
            update_post_meta($order_id, '_shipping_first_name', $arrow_order->shipping_address->firstname);
            update_post_meta($order_id, '_shipping_last_name', $arrow_order->shipping_address->lastname);
            update_post_meta($order_id, '_shipping_address_1', $arrow_order->shipping_address->address);
            update_post_meta($order_id, '_shipping_address_2', $arrow_order->shipping_address->unit);
            update_post_meta($order_id, '_shipping_city', $arrow_order->shipping_address->city);
            update_post_meta($order_id, '_shipping_state', $arrow_order->shipping_address->state);
            update_post_meta($order_id, '_shipping_postcode', $arrow_order->shipping_address->postal_code);
            update_post_meta($order_id, '_shipping_country', $arrow_order->shipping_address->country_code);
        } else {
            update_post_meta($order_id, '_shipping_first_name', $arrow_order->address->firstname);
            update_post_meta($order_id, '_shipping_last_name', $arrow_order->address->lastname);
            update_post_meta($order_id, '_shipping_address_1', $arrow_order->address->address);
            update_post_meta($order_id, '_shipping_address_2', $arrow_order->address->unit);
            update_post_meta($order_id, '_shipping_city', $arrow_order->address->city);
            update_post_meta($order_id, '_shipping_state', $arrow_order->address->state);
            update_post_meta($order_id, '_shipping_postcode', $arrow_order->address->postal_code);
            update_post_meta($order_id, '_shipping_country', $arrow_order->address->country_code);
        }
        /**
         * Update Delivery date data
         */
        if (class_exists('order_delivery_date')) {
            $this->updateOrderDeliveryDataProData($order_id, $arrow_order);
        } elseif (class_exists('Order_Delivery_Date_Lite')) {
            $this->updateOrderDeliveryDataLiteData($order_id, $arrow_order);
        }
        $this->orderSetMetaDataByApiData($order, $arrow_order);
        return true;
    }

  /**
   * @param int $order_id
   * @param $arrow_order
   *
   * @return bool
   */
    public function updateOrderDeliveryDataLiteData(int $order_id,  $arrow_order)
    {
        if ($arrow_order->extra_data->deliveryDate != '' && $arrow_order->extra_data->deliveryTime != '') {
            $deliveryDate = $arrow_order->extra_data->deliveryDate;
            $deliveryTime = $arrow_order->extra_data->deliveryTime;
            $date_slot_label = '' !== get_option('orddd_lite_delivery_date_field_label') ? get_option('orddd_lite_delivery_date_field_label') : 'Delivery Date';
            $date_format   = 'dd-mm-y';
            update_post_meta($order_id, $date_slot_label, sanitize_text_field(wp_unslash($deliveryDate))); //phpcs:ignore
            $timestamp = Orddd_Lite_Common::orddd_lite_get_timestamp($deliveryDate, $date_format);

            update_post_meta($order_id, '_orddd_lite_timestamp', $timestamp);

            $time_format     = get_option('orddd_lite_delivery_time_format');
            $time_slot_label = '' !== get_option('orddd_lite_delivery_timeslot_field_label') ? get_option('orddd_lite_delivery_timeslot_field_label') : 'Time Slot';
            $time_slot_arr   = explode(' - ', $deliveryTime);

            if ('1' === $time_format) {
                $from_time = date('H:i', strtotime($time_slot_arr[0])); //phpcs:ignore
                if (isset($time_slot_arr[1])) {
                    $to_time         = date('H:i', strtotime($time_slot_arr[1])); //phpcs:ignore
                    $order_time_slot = $from_time . ' - ' . $to_time;
                } else {
                    $order_time_slot = $from_time;
                }
            } else {
                $from_time = date('H:i', strtotime($time_slot_arr[0])); //phpcs:ignore
            }
            update_post_meta($order_id, $time_slot_label, esc_attr($deliveryTime));
            update_post_meta($order_id, '_orddd_time_slot', $order_time_slot);
            $delivery_date  = $deliveryDate;
            $delivery_date .= ' ' . $from_time;
            $timestamp      = strtotime($delivery_date);
            update_post_meta($order_id, '_orddd_lite_timeslot_timestamp', $timestamp);
        }
        return true;
    }

  /**
   * @param int $order_id
   * @param $arrow_order
   *
   * @return bool
   */
    private function updateOrderDeliveryDataProData(int $order_id, $arrow_order)
    {

        if ($arrow_order->extra_data->deliveryDate != '') {
            $deliveryDate = $arrow_order->extra_data->deliveryDate;
            $deliveryTime = $arrow_order->extra_data->deliveryTime;
            $date_slot_label = '' !== get_option('orddd_delivery_date_field_label') ? get_option('orddd_delivery_date_field_label') : 'Delivery Date';
            update_post_meta($order_id, $date_slot_label, $deliveryDate); //phpcs:ignore
            $datetimestamp = strtotime($deliveryDate);
            update_post_meta($order_id, '_orddd_timestamp', $datetimestamp);

            $time_format     = get_option('orddd_delivery_time_format');
            //$time_slot_label = '' !== get_option( 'orddd_pro_delivery_timeslot_field_label' ) ? get_option( 'orddd_pro_delivery_timeslot_field_label' ) : 'Time Slot';
            $time_slot_label = orddd_common::orddd_get_delivery_time_field_label($shipping_method, $categories, $shipping_classes, $location);
            $time_slot_arr   = explode(' - ', $deliveryTime);
            if ($time_slot != '' && $time_slot != 'choose' && $time_slot != 'NA' && $time_slot != 'select') {
                if ('1' === $time_format) {
                    $from_time = date('H:i', strtotime($time_slot_arr[0])); //phpcs:ignore
                    if (isset($time_slot_arr[1])) {
                        $to_time         = date('H:i', strtotime($time_slot_arr[1])); //phpcs:ignore
                        $order_time_slot = $from_time . ' - ' . $to_time;
                    } else {
                        $order_time_slot = $from_time;
                    }
                } else {
                    $from_time = date('H:i', strtotime($time_slot_arr[0])); //phpcs:ignore
                }
                update_post_meta($order_id, $time_slot_label, esc_attr($deliveryTime));
                update_post_meta($order_id, '_orddd_time_slot', $order_time_slot);
                $delivery_date  = $deliveryDate;
                $delivery_date .= ' ' . $from_time;
                $timestamp      = strtotime($delivery_date);
                update_post_meta($order_id, '_orddd_timeslot_timestamp', $timestamp);
            }
        }
        return true;
    }
    /**
     * Method to get required session data form active shop portal user. 
     * To initiate the payment process by arrow
     * @return arr []
     */
    public function setOrderObjSessionValue()
    {
        $deliveryDate = '';
        $deliveryTime = '';
        if (null !== WC()->session->get('arrow_deliverydate')) {
            $deliveryDate = WC()->session->get('arrow_deliverydate');
            $deliveryTime = WC()->session->get('arrow_deliverytime');
        }
        $arrow_ga_clientId = 0;
        $arrow_event_source_url = "";
        if (null !== WC()->session->get('arrow_ga_clientId')) {
            $arrow_ga_clientId = WC()->session->get('arrow_ga_clientId');
            $arrow_event_source_url = WC()->session->get('arrow_event_source_url');
        }
        $arrow_fbc = "";
        $arrow_fbp = "";
        $arrow_external_id = "";
        if (null !== WC()->session->get('arrow_fbp')) {
            $arrow_fbp = WC()->session->get('arrow_fbp');
            $arrow_fbc = WC()->session->get('arrow_fbc');
            $arrow_external_id = WC()->session->get('arrow_external_id');
        }
        return ['deliveryDate' => $deliveryDate, 'deliveryTime' => $deliveryTime, 'arrow_ga_clientId' => $arrow_ga_clientId, 'arrow_event_source_url' => $arrow_event_source_url, 'arrow_fbc' => $arrow_fbc, 'arrow_external_id' => $arrow_external_id];
    }
    /**
     * Method to update Items in wordpress existing order, 
     * This method is getting used, if user making payment through Arrow payLink
     * @param int $order_id Wordpress Order id
     * @param array $items Items array form Arrow API order details
     */
    public function paylinkAddItemsWcOrder(int $order_id, array $items)
    {
        foreach ($items as $itemss) {
            $storeData = $itemss->extra_data->store_data;
            $product = wc_get_product($storeData->product_id);
            $variation_id = ($storeData->variation_id > 0) ? $storeData->variation_id : 0;
            //$order_id = $order->id;
            $prod_id = $storeData->product_id;
            $order  = wc_get_order($order_id);
            // Set values
            $item = array();
            $item['product_id']        = $product->get_id();
            $item['variation_id']      = $variation_id;
            $item['variation_data']    = $item['variation_id'] ? $product->get_variation_attributes() : '';
            $item['name']              = $product->get_title();
            $item['tax_class']         = $product->get_tax_class();
            $item['qty']               = $itemss->quantity;
            $item['line_subtotal']     = wc_format_decimal($itemss->price * $itemss->quantity);
            $item['line_subtotal_tax'] = '';
            $item['line_total']        = wc_format_decimal($itemss->price * $itemss->quantity);
            $item['line_tax']          = '';
            $item['type']              = 'line_item';

            // Add line item Ref http://hookr.io/functions/wc_add_order_item/
            $item_id = wc_add_order_item($order_id, array(
                'order_item_name'       => $item['name'],
                'order_item_type'       => 'line_item'
            ));

            // Add line item meta Ref http://hookr.io/functions/wc_add_order_item_meta/
            if ($item_id) {
                wc_add_order_item_meta($item_id, '_qty', $item['qty']);
                wc_add_order_item_meta($item_id, '_tax_class', $item['tax_class']);
                wc_add_order_item_meta($item_id, '_product_id', $item['product_id']);
                wc_add_order_item_meta($item_id, '_variation_id', $item['variation_id']);
                wc_add_order_item_meta($item_id, '_line_subtotal', $item['line_subtotal']);
                wc_add_order_item_meta($item_id, '_line_subtotal_tax', $item['line_subtotal_tax']);
                wc_add_order_item_meta($item_id, '_line_total', $item['line_total']);
                wc_add_order_item_meta($item_id, '_line_tax', $item['line_tax']);
                wc_add_order_item_meta($item_id, '_line_tax_data', array('total' => array(), 'subtotal' => array()));
                // Store variation data in meta
                if ($item['variation_data'] && is_array($item['variation_data'])) {
                    foreach ($item['variation_data'] as $key => $value) {
                        wc_add_order_item_meta($item_id, str_replace('attribute_', '', $key), $value);
                    }
                }
            }
            $item['item_meta']       = $order->get_item_meta($item_id);
            $item['item_meta_array'] = $order->get_item_meta_array($item_id);
            $item                    = $order->expand_item_meta($item);
            $order->calculate_totals();
        }
    }
    /**
     * Supporting Mehtod to return Array of supported paymetnt method type 
     * These value will be bind as select options in Arrow config select box.
     * To show the image under payemtn button 
     */
    private function getPaymentMethodLists()
    {
        return array(
            'visa' => __('Visa', 'woocommerce'),
            'master' => __('Mastercard', 'woocommerce'),
            'paynow' => __('PayNow', 'woocommerce'),
            'fpx' => __('FPX', 'woocommerce'),
            'maybank' => __('Maybank', 'woocommerce'),
            'cimb' => __('CIMB', 'woocommerce'),
            'grabpay' => __('GrabPay', 'woocommerce'),
            'atome' => __('Atome', 'woocommerce'),
            'bca' => __('BCA', 'woocommerce'),
            'bank_bri' => __('BRI', 'woocommerce'),
            'gopay' => __('GoPay', 'woocommerce'),
            'ovo' => __('OVO', 'woocommerce'),
            'bni' => __('BNI', 'woocommerce'),
            'mandiri' => __('Mandiri', 'woocommerce'),
            'alfamart' => __('Alfamart', 'woocommerce'),
            'indomaret' => __('Indomaret', 'woocommerce'),
            'permata' => __('Permata', 'woocommerce'),
            'kredivo' => __('Kredivo', 'woocommerce'),
            'akulaku' => __('Akulaku', 'woocommerce'),
            //Updated IN New version_compare
            'wechat' => __('Wechat', 'woocommerce'),
            'maestro' => __('Maestro', 'woocommerce'),
            'stripe' => __('Stripe', 'woocommerce'),
            'kiplepay' => __('Kiplepay', 'woocommerce'),
            'paypal' => __('PayPal', 'woocommerce'),
            'mcash' => __('MCash', 'woocommerce'),
            'nets' => __('NETS', 'woocommerce'),
            //End
            'shopppay' => __('Shopeepay', 'woocommerce'),
            'dana' => __('Dana', 'woocommerce'),
            'link_aja' => __('Linkaja', 'woocommerce'),
            'sakuku' => __('Sakuku', 'woocommerce'),
            'jcb' => __('JCB', 'woocommerce'),
            'union' => __('UnionPay', 'woocommerce'),
            'american' => __('American Express', 'woocommerce'),
            'touch_n_go' => __('Touch N Go', 'woocommerce'),
            'boost' => __('Boost', 'woocommerce'),
            'rhb' => __('RHB', 'woocommerce'),
            'hongleong' => __('Hong Leong Bank', 'woocommerce'),
            'affin_bank' => __('Affin Bank', 'woocommerce'),
        );
    }

  /**
   * @param int $user_id
   * @param $arrow_order
   *
   * @return bool
   */
    public function registeredUserDetailsUpdate(int $user_id,  $arrow_order)
    {

        update_user_meta($user_id, 'billing_first_name', $arrow_order->address->firstname);
        update_user_meta($user_id, 'billing_last_name', $arrow_order->address->lastname);
        update_user_meta($user_id, 'billing_address_1', $arrow_order->address->address);
        update_user_meta($user_id, 'billing_address_2', $arrow_order->address->unit);
        update_user_meta($user_id, 'billing_city', $arrow_order->address->city);
        update_user_meta($user_id, 'billing_state', $arrow_order->address->state);
        update_user_meta($user_id, 'billing_postcode', $arrow_order->address->postal_code);
        update_user_meta($user_id, 'billing_country', $arrow_order->address->country_code);
        update_user_meta($user_id, 'billing_phone', $arrow_order->address->contact);

        update_user_meta($user_id, 'shipping_first_name', $arrow_order->address->firstname);
        update_user_meta($user_id, 'shipping_last_name', $arrow_order->address->lastname);
        update_user_meta($user_id, 'shipping_address_1', $arrow_order->address->address);
        update_user_meta($user_id, 'shipping_address_2', $arrow_order->address->unit);
        update_user_meta($user_id, 'shipping_city', $arrow_order->address->city);
        update_user_meta($user_id, 'shipping_state', $arrow_order->address->state);
        update_user_meta($user_id, 'shipping_postcode', $arrow_order->address->postal_code);
        update_user_meta($user_id, 'shipping_country', $arrow_order->address->country_code);
        return true;
    }

  /**
   * @param $order
   * @param $arrow_order
   *
   * @return void
   */
    private function orderSetMetaDataByApiData( $order,  $arrow_order)
    {
        if (!empty($arrow_order->payment_method)) {
            $order->add_meta_data('payment_method', $arrow_order->payment_method);
        }
        if (!empty($arrow_order->estimate)) {
            $order->add_meta_data('delivery_estimate', $arrow_order->estimate);
        }
        if (!empty($arrow_order->payment_channel)) {
            $order->add_meta_data('payment_channel', $arrow_order->payment_channel);
        }
        if (!empty($arrow_order->refund_supported)) {
            $order->add_meta_data('refund_supported', $arrow_order->refund_supported);
        }
        if (!empty($arrow_order->order_comments)) {
            $order->add_order_note($arrow_order->order_comments);
        }
        if (isset($arrow_order->requires_shipping)) {
            $order->add_meta_data('requires_shipping', $arrow_order->requires_shipping ? 'yes' : 'no');
        }
        $extra_data = $arrow_order->extra_data;
        if (($extra_data) && (!empty($extra_data->notes))) {
            $order->add_meta_data('notes', $extra_data->notes);
        }
        error_log('class-wc:' . __LINE__ . ' ' . print_r($arrow_order, true));
        if (!empty($arrow_order->promo_code)) {
            $order->apply_coupon($arrow_order->promo_code);
        }
    }
}
