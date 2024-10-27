<?php
$data_param = "";
foreach ( $lisAllPaymentArr as $key => $val ) {
  $data_param .= ' data-brand-' . $key . '=' . $val;
}
if ( ( $gateways['arrow']->settings['checkout_button_learn_more'] ?? "" ) != "yes" ) {
  $data_param .= ' data-learn-more=false';
} else if ( ( $gateways['arrow']->settings['checkout_button_learn_more'] ?? "" ) == "yes" ) {
  $data_param .= ' data-learn-more=true';
}
?>
<div class="arrow-checkout-block" id="arrow-checkout-block" data-type="none"
     data-onclick="arrow_express_checkout('Cart Page');" <?php echo esc_attr( $data_param ); ?>></div>
<script>
  jQuery(document.body).on('removed_from_cart updated_cart_totals', function () {
    arrowInit();
  });
</script>
