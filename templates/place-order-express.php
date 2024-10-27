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
<div class="arrow-checkout-block" data-type="none"
     data-onclick="arrow_express_checkout('Mini Cart');" <?php echo esc_attr( $data_param ); ?> data-brand-fpx="false"
     data-brand-cimb="false" data-brand-maybank="false"></div>
<script>
  jQuery(document).on('ready', function () {
    // express
    // console.log('first init');
    arrowInit();

    jQuery(document.body).on('added_to_cart removed_from_cart wc_cart_button_updated updated_cart_totals wc_fragments_loaded wc_fragments_refreshed', function (event) {
      console.log(event.type);
      // console.log('reload init');
      arrowInit();
    });
  });
</script>
