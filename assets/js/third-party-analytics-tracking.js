/*third-party-analytics-tracking*/
console.log("Loaded", "third-party-analytics-tracking");
var arrow_ga_clientId = 0;

function arrow_update_analytic_data() {
    var clientId = 0;
    var event_source_url = "";
    if (arrow_ga_clientId != 0) {
        clientId = arrow_ga_clientId;
        event_source_url = window.location.href;
    }

    var fbp = getCookie('_fbp');
    var fbc = getCookie('_fbc');
    var external_id = getCookie('external_id');

    var ajaxUrl = ajaxVar.ajaxurl;
    var postData = {
        action: 'setanalyticdata_session',
        arrow_ga_clientId: clientId,
        arrow_event_source_url: event_source_url,
        arrow_fbp: fbp,
        arrow_fbc: fbc,
        arrow_external_id: external_id
    }
    console.log("data", ajaxUrl, postData);
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: ajaxUrl,
        data: postData,
        success: function (res) {
            console.log(res);
        }
    });
}

jQuery(document).on('ready', function () {
    jQuery(document.body).on('updated_cart_totals wc_fragments_loaded wc_fragments_refreshed', function (event) {
        arrow_update_analytic_data();
    });

    try {
        ga(function (tracker) {
            arrow_ga_clientId = tracker.get('clientId');
        });
    } catch (e) {
        console.info(e)
    }

});


function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}
