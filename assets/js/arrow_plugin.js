console.log("arrow js plugin loaded");
$ = jQuery;

// Arrow Dev
// const arrowHost = "https://hi.projectarrow.co";
// const arrowAPI = "https://yo.projectarrow.co/api";

var arrowOrder = new Object();

function arrowCheckout(token, success_url, fail_url, cancel_url) {
  arrowOrder.redirect = {
    "success": success_url,
    "fail": fail_url,
    "cancel": cancel_url
  };
  launchArrow(token);
}

function get_arrow_token() {
  // TODO: Fetch arrow token from server to pass to 'launchArrow'

  var token = "";

  launchArrow(token);
}

function arrowBuy() {
  makeLoadingScreen()
  show_loading_overlay()
    var checkout_button = jQuery('form.cart button[type=submit]');
    var append_btn_data = `&${checkout_button.attr('name')}=${checkout_button.attr('value')}`;

  if (checkout_button.hasClass('disabled')) {
    alert('Checkout Failed. Please Try Again');
    return;
  }

  jQuery('form.cart').on('submit', function (e) {
    e.preventDefault();
    jQuery.ajax({
      url: jQuery(this).attr('action'),
      type: jQuery(this).attr('method'),
      data: jQuery(this).serialize() + append_btn_data,
      complete: function (xhr, textStatus) {
        if (xhr.status == 200) {
          arrow_express_checkout();
        } else {
          alert('Checkout Failed. Please Try Again');
        }
      }
    });
  });

  checkout_button.click();
  jQuery('form.cart').off();
}

function arrow_express_checkout(buttonType='') {
  makeLoadingScreen()
  show_loading_overlay()
  const params = {         //POST request
        nonce: ajax_var.nonce, //Get the localize variable value of nonce form arrow.php
        buttonType: buttonType,
        action: "express_checkout",            //action
    };


    jQuery.post(arrowAjaxBaseUri, params)
        .done(function (result) {
            const redir = result.arrow_result.redirect;

            eval(redir);
        })
        .fail(function (error) {
            alert(error.responseJSON.errors);
        });
}

$('.first-load-checkout').remove();
$('.second-overly').show();

function delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function showMessages(LOADING_MESSAGE) {
    let messages = LOADING_MESSAGE.INIT_LOAD['en_init_load'];
    for (let message of messages) {
        $('.loading-msg-here').html(message.message);
        if (message.time == 0) {
            $('.stuck-msg').show();
        }
        if (message.time) {
            await delay(message.time);
        }
    }
}

function show_loading_overlay() {
    const LOADING_MESSAGE = {
        DEFAULT: "Please wait...",
        INIT_LOAD: {
            en_init_load: [
                {
                    message: "Processing...",
                    time: 2000,
                },
                {
                    message: "Preparing checkout... ",
                    time: 4000,
                },
                {
                    message: "Checking inventories...",
                    time: 8000,
                },
                {
                    message: "Almost done...",
                    time: 0,
                },
            ]
        },
    };
    jQuery('.first-load-checkout').show();
    showMessages(LOADING_MESSAGE);

    var trigger = document.getElementsByClassName("focusTrigger")[0];
    trigger.onclick = function () {
        window.location.replace();
    }
}

function makeLoadingScreen() {
    var headID = document.getElementsByTagName('head')[0];
    var link = document.createElement('link');
    link.type = 'text/css';
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&display=swap';
    headID.appendChild(link);

    var link2 = document.createElement('link');
    link2.type = 'text/css';
    link2.rel = 'stylesheet';
    link2.href = 'https://fonts.googleapis.com/css2?family=Nunito:wght@700&display=swap';
    headID.appendChild(link2);

    var style = document.createElement('style');
    style.type = 'text/css';
    style.innerHTML = `
   .first-load-checkout{
                font-family: Nunito;
            }
            .backdrop {
                z-index: 9990 !important;
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                right: 0;
                background-color: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                display:none;
            }
            .message {
                font-style: normal;
                font-weight: normal;
                font-size: 18px;
                line-height: 16px;
                text-align: center;
                color: #FFFFFF;
            }
            .focusTrigger {
                color: #0286ff !important;
                text-decoration: underline;
                cursor: pointer;
                
            }
            .stuck-msg, .second-overly{
                display:none;
            }
            .second-overly .message{
                margin-top:25%;
            }
            .loader .sp-circle {
                border: 4px rgba(240, 37, 111, 0.25) solid;
                border-top: 4px #f0256f solid;
                border-radius: 50%;
                -webkit-animation: spCircRot 1.2s infinite linear;
                animation: spCircRot 1.2s infinite linear;
            }
            .loader .sp {
                width: 52px;
                height: 52px;
            }
            @-webkit-keyframes spCircRot {
                from {
                    -webkit-transform: rotate(0);
                }
                
                to {
                    -webkit-transform: rotate(359deg);
                }
                }
                
                @keyframes spCircRot {
                from {
                    -webkit-transform: rotate(0);
                    transform: rotate(0);
                }
                
                to {
                    -webkit-transform: rotate(359deg);
                    transform: rotate(359deg);
                }
                }
                .loader .loading-icon {
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    -webkit-transform: translate(-50%, -50%);
                    -ms-transform: translate(-50%, -50%);
                    transform: translate(-50%, -50%);
                }
                .loading-icon {
                    vertical-align: middle;
                    border-style: none;
                }
                .loader {
                    position: fixed;
                    left: 50%;
                    top: 46%;
                    -webkit-transform: translate(-50%, -50%);
                    -ms-transform: translate(-50%, -50%);
                    transform: translate(-50%, -50%);
                    border-radius: 100%;
                    width: 68px;
                    height: 68px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                .loading-message {
                    position: fixed;
                    left: 50%;
                    top: 56%;
                    -webkit-transform: translate(-50%, -50%);
                    -ms-transform: translate(-50%, -50%);
                    transform: translate(-50%, -50%);
                    font-size: 18px;
                }
  `;
    headID.appendChild(style);
    var bodyID = document.getElementsByTagName('body')[0];
    var backdrop = document.createElement('div');

    //added initial backdrop, after one click button is clicked
    backdrop.className = 'backdrop first-load-checkout';
    backdrop.innerHTML = '<div class="message">\n' +
        '                <div class="logo">\n' +
        '                <div class="loader">\n' +
        '                    <div class="sp sp-circle"></div>\n' +
        '                        <svg class="loading-icon" width="18" height="21" viewBox="0 0 18 21" fill="none" xmlns="http://www.w3.org/2000/svg">\n' +
        '                            <path d="M17.8739 10.6291L12.2232 20.5851C12.1432 20.726 11.9936 20.8131 11.8316 20.8131H0.782398C0.435227 20.8131 0.218666 20.4368 0.39309 20.1366L11.6998 0.678486C11.8745 0.378004 12.3092 0.380213 12.4807 0.682453L17.8739 10.1846C17.9521 10.3224 17.9521 10.4913 17.8739 10.6291Z" fill="#F0256F"/>\n' +
        '                            <path d="M17.8739 10.6291L12.2232 20.5851C12.1432 20.726 11.9936 20.8131 11.8316 20.8131H0.782398C0.435227 20.8131 0.218666 20.4368 0.39309 20.1366L11.6998 0.678486C11.8745 0.378004 12.3092 0.380213 12.4807 0.682453L17.8739 10.1846C17.9521 10.3224 17.9521 10.4913 17.8739 10.6291Z" fill="url(#paint0_linear_19_2644)"/>\n' +
        '                            <path d="M17.8739 10.6291L12.2232 20.5851C12.1432 20.726 11.9936 20.8131 11.8316 20.8131H0.782398C0.435227 20.8131 0.218666 20.4368 0.39309 20.1366L11.6998 0.678486C11.8745 0.378004 12.3092 0.380213 12.4807 0.682453L17.8739 10.1846C17.9521 10.3224 17.9521 10.4913 17.8739 10.6291Z" fill="url(#paint1_linear_19_2644)"/>\n' +
        '                            <path d="M17.8739 10.184L12.2232 0.22801C12.1432 0.0870785 11.9936 0 11.8316 0H0.782398C0.435227 0 0.218666 0.376309 0.39309 0.676481L11.6998 20.1346C11.8745 20.4351 12.3092 20.4329 12.4807 20.1307L17.8739 10.6285C17.9521 10.4907 17.9521 10.3218 17.8739 10.184Z" fill="#F0256F"/>\n' +
        '                            <defs>\n' +
        '                            <linearGradient id="paint0_linear_19_2644" x1="9" y1="20.8131" x2="8.53473" y2="0.313387" gradientUnits="userSpaceOnUse">\n' +
        '                            <stop stop-color="#F0256F"/>\n' +
        '                            <stop offset="1" stop-color="#551A2F"/>\n' +
        '                            </linearGradient>\n' +
        '                            <linearGradient id="paint1_linear_19_2644" x1="9" y1="20.8131" x2="8.53473" y2="0.313387" gradientUnits="userSpaceOnUse">\n' +
        '                            <stop stop-color="#F0256F"/>\n' +
        '                            <stop offset="1" stop-color="#551A2F"/>\n' +
        '                            </linearGradient>\n' +
        '                            </defs>\n' +
        '                        </svg>\n' +
        '                    </div>\n' +
        '                </div>\n' +
        '            \n' +
        '                <div class="loading-message">\n' +
        '                    <transition name="slide-fade" mode="out-in">\n' +
        '                        <p class="text-center w-100 loading-msg-here" >Processing ...</p>\n' +
        '                    </transition>\n' +
        '                    <div>\n' +
        '                    <p style="color: #FFFFFF !important;" class="stuck-msg"><a class="focusTrigger">Click here</a> if you are stuck</p>\n' +
        '                </div>\n' +
        '                </div>\n' +
        '                ';
    bodyID.appendChild(backdrop);

}

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