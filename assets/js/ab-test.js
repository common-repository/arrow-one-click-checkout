(function (f, b) {
    if (!b.__SV) {
        var e, g, i, h;
        window.mixpanel = b;
        b._i = [];
        b.init = function (e, f, c) {
            function g(a, d) {
                var b = d.split(".");
                2 == b.length && (a = a[b[0]], d = b[1]);
                a[d] = function () {
                    a.push([d].concat(Array.prototype.slice.call(arguments, 0)))
                }
            }

            var a = b;
            "undefined" !== typeof c ? a = b[c] = [] : c = "mixpanel";
            a.people = a.people || [];
            a.toString = function (a) {
                var d = "mixpanel";
                "mixpanel" !== c && (d += "." + c);
                a || (d += " (stub)");
                return d
            };
            a.people.toString = function () {
                return a.toString(1) + ".people (stub)"
            };
            i = "disable time_event track track_pageview track_links track_forms track_with_groups add_group set_group remove_group register register_once alias unregister identify name_tag set_config reset opt_in_tracking opt_out_tracking has_opted_in_tracking has_opted_out_tracking clear_opt_in_out_tracking start_batch_senders people.set people.set_once people.unset people.increment people.append people.union people.track_charge people.clear_charges people.delete_user people.remove".split(" ");
            for (h = 0; h < i.length; h++) g(a, i[h]);
            var j = "set set_once union unset remove delete".split(" ");
            a.get_group = function () {
                function b(c) {
                    d[c] = function () {
                        call2_args = arguments;
                        call2 = [c].concat(Array.prototype.slice.call(call2_args, 0));
                        a.push([e, call2])
                    }
                }

                for (var d = {}, e = ["get_group"].concat(Array.prototype.slice.call(arguments, 0)), c = 0; c < j.length; c++) b(j[c]);
                return d
            };
            b._i.push([e, f, c])
        };
        b.__SV = 1.2;
        e = f.createElement("script");
        e.type = "text/javascript";
        e.async = !0;
        e.src = "undefined" !== typeof MIXPANEL_CUSTOM_LIB_URL ?
            MIXPANEL_CUSTOM_LIB_URL : "file:" === f.location.protocol && "//cdn.mxpnl.com/libs/mixpanel-2-latest.min.js".match(/^\/\//) ? "https://cdn.mxpnl.com/libs/mixpanel-2-latest.min.js" : "//cdn.mxpnl.com/libs/mixpanel-2-latest.min.js";
        g = f.getElementsByTagName("script")[0];
        g.parentNode.insertBefore(e, g)
    }
})(document, window.mixpanel || []);


const merchant_id = 174
const environment_id = 'ca5kc0e15egg61kc4ncg'
const campaign_id = 'ca5kcns0nbsg44k4qhb0'
let pageName = currentPage
const eventName = () => {
    if (pageName.cartPage == 1)
        return 'CartPageReach'
    if (pageName.productPage == 1)
        return 'ProductPageReach'
}

// initiate mixpanel function and store distinct id as cookie
mixpanel.init("a2d5963837cd2c484c39cc1b294ee9a3", {
    loaded: function (mixpanel) {
        distinct_id = mixpanel.get_distinct_id();
        setCookie('mx_distinct_id', distinct_id, 90);
    }
});

function uuid() {
    return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
        (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    )
}

function setCookie(name, value, exp) {
    const d = new Date();
    d.setTime(d.getTime() + (exp * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}


function getCookie(cname) {
    console.log(cname)
    let name = cname + "=";
    let ca = document.cookie.split(';');
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

function mxAfterAjax(data, eventName, location, mx_distinct_id) {

    const interval = setInterval(function () {
        if (mx_distinct_id.length > 0) {
            mixpanel.track(eventName, {
                'MerchantID': merchant_id,
                'Origin': 'E-Store',
                'VariantId': data.data.id,
                'PageUrl': location
            })
            clearInterval(interval)
        }
    }, 300);
}

let ab_user_id = getCookie('ab_user_id')
let mx_distinct_id = getCookie('mx_distinct_id');

if (ab_user_id === null || ab_user_id === '' || ab_user_id === 'undefined') {
    ab_user_id = uuid()
    setCookie('ab_user_id', ab_user_id, 90)
}

function initABTest() {
    jQuery.post({
        type: 'POST',
        url: arrowAPI+'/arrow/ab_testing',
        dataType: 'json',
        data: {
            'user_id': ab_user_id,
            'ab_api_id': 1,
            'environment_id': environment_id,
            'campaign_id': campaign_id,
            'merchant_id': merchant_id
        },
        cache: false,
        success: function (result) {
            if (result.data.modifications.value.show_arrow_button === false) {
                $("#arrow-checkout-block").hide()
                $('.or-div').hide()

            }
            mxAfterAjax(result, eventName(), window.location.href, mx_distinct_id)
        },
        error: function () {
            console.error('Failed to process Ajax')
        }
    })
}

jQuery(document).on('ready added_to_cart removed_from_cart wc_cart_button_updated updated_cart_totals wc_fragments_loaded wc_fragments_refreshed', function (event) {
    console.info('Event Type =>', event)
    initABTest()
})