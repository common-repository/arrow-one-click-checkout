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
     data-onclick="arrowBuy();" <?php echo esc_attr( $data_param ); ?> data-brand-fpx="false" data-brand-cimb="false"
     data-brand-maybank="false"></div>
