console.log(localStorage);
function arrow_update_session_value(){
	if(localStorage.getItem( 'h_deliverydate_lite_session' )=== null){
		var deliverydate = localStorage.getItem( 'e_deliverydate_session' );
		var deliverytime = localStorage.getItem( "orddd_time_slot" );
	}else{
		var deliverydate = localStorage.getItem( 'h_deliverydate_lite_session' );
    	var deliverytime = localStorage.getItem( "orddd_lite_time_slot" );
	}
    var ajaxUrl = ajaxVar.ajaxurl;
    var postData = {action:'setdelivery_session', delv_date: deliverydate,delv_time :deliverytime }
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url:ajaxUrl,
        data: postData,
        success: function(res){
            console.log(res);
        }
    });
}

jQuery(document).on('ready', function () {
    jQuery(document.body).on('updated_cart_totals wc_fragments_loaded wc_fragments_refreshed', function (event) {
      arrow_update_session_value();
    });
});