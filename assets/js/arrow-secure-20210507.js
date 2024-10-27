console.log("Arrow Merchant (Server-side) Loaded");

if (typeof arrowHost === 'undefined') {
    arrowHost = "https://shop.witharrow.co";
}

if (typeof arrowAPI === 'undefined') {
    arrowAPI = "https://fly.witharrow.co/api";
}

var arrowUsername = "";
var arrowEncryptionKey = "";
var arrowClientKey = "";
var arrowShipping = [];
var arrowCustomer = {};
var checkoutWindow;
var checkoutTimer;
var timer;
var isTransactionSuccess = false;
var order_hash = "";
var urlBeforePopUp = "";
// console.log(process.env.DEV_MODE);

function detectMob() {
    return ((window.innerWidth <= 800));
}

function defer(method) {
    if (window.jQuery) {
        method();
    } else {
        setTimeout(function() { defer(method) }, 100);
    }
}

function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

function getParameterByName(name, url = window.location.href) {
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

function waitForElementToDisplay(selector, selector2, callback, checkFrequencyInMs, timeoutInMs) {
  var startTimeInMs = Date.now();
  (function loopSearch() {
    var cond = document.querySelector(selector) != null;
    if (selector2 != "") {
        cond = cond || document.querySelector(selector2) != null;
    }
    if (cond) {
      callback();
      return;
    }
    else {
      setTimeout(function () {
        if (timeoutInMs && Date.now() - startTimeInMs > timeoutInMs)
          return;
        loopSearch();
      }, checkFrequencyInMs);
    }
  })();
}

function loadCheckoutWindow(token) {
    const url = arrowHost + "/order/check/" + token;
    /**
     * Commenting the below code which opens the popup for task PROD-1953. 
     * Once verifed will remove the comented code.
    */
    /*
    var left = (screen.width/2)-(400/2);
    var top = (screen.height/2)-(700/2);
    checkoutWindow = window.open(url,'ArrowCheckout','toolbar=0,status=0,menubar=0,resizable=no,copyhistory=no,width=400,height=700,top='+top+',left='+left);

    if (!checkoutWindow || checkoutWindow.closed || typeof checkoutWindow.closed == 'undefined') {
        window.location.href = url;
    }
    else {
        urlBeforePopUp = window.parent.location.href;
        timer = setInterval(checkChild, 100);
    }
    */
    window.location.href = url;
}


function checkChild() {
    if (checkoutWindow && checkoutWindow.closed) {
        if (isTransactionSuccess){
            if (order_hash != ""){
                var url = arrowAPI + "/order/done/" + order_hash;

                jQuery.ajax({
                    type: "GET",
                    url: url,
                    success: function(data) {
                        window.location.href = arrowOrder.redirect.success+"?order_hash="+order_hash;
                    },
                    error: function(data, request, status, error) {
                        console.log(error);
                        alert("Backend Connection Failure : " + error);
                    }
                });
            } else {
                window.location.href = arrowOrder.redirect.success;
            }
        } else {
            window.location.href = arrowOrder.redirect.cancel;
        }
        clearInterval(timer);
    }
  }

function launchArrow(token) {
    var bodyID = document.getElementsByTagName('body')[0];
    var backdrop = document.createElement('div');
    backdrop.className = 'backdrop';
    backdrop.innerHTML = `<div class="message"><div class="logo"></div><div><p style="color: #FFFFFF !important;">No longer see the Arrow window?</p><p><a class="focusTrigger">Click here</a></p></div></div>`;
    bodyID.appendChild(backdrop);

    var trigger = document.getElementsByClassName("focusTrigger")[0];

    trigger.onclick = function() {
        if (checkoutWindow && !checkoutWindow.closed) {
            //if checkout pop up already shown, bring it to front
            checkoutWindow.focus();
        }
    }

    loadCheckoutWindow(token);
 
}

function handleArrow(e) {
    if (e.origin == arrowHost) {
        
        if (e.data.indexOf("checkout_success") > -1) {
            if (arrowOrder.redirect.success == undefined) {
                arrowModal.style.display = "none";
            } else {
                var splittedMessage = e.data.split("/");
                if (splittedMessage.length > 1){
                    if (splittedMessage[1] != "") {
                        window.location.href = arrowOrder.redirect.success+"?order_hash="+splittedMessage[1];
                    } else {
                        window.location.href = arrowOrder.redirect.success;
                    }
                } else {
                    window.location.href = arrowOrder.redirect.success;
                }
                
            }
            if (checkoutWindow) {
                clearInterval(timer);
                checkoutWindow.close();
            }
        } else if (e.data == "checkout_fail") {
            if (arrowOrder.redirect.fail == undefined) {
                arrowModal.style.display = "none";
            } else {
                window.location.href = arrowOrder.redirect.fail;
            }
            if (checkoutWindow) {
                checkoutWindow.close();
            }
        } else if (e.data == "checkout_cancel") {
            if (arrowOrder.redirect.cancel == undefined) {
                arrowModal.style.display = "none";
            } else {
                window.location.href = arrowOrder.redirect.cancel;
            }
            if (checkoutWindow) {
                checkoutWindow.close();
            }
        } else if (e.data.indexOf("transaction_success") > -1) {
            isTransactionSuccess = true;
            var splittedMessage = e.data.split("/");
            if (splittedMessage.length > 1){
                if (splittedMessage[1] != "") {
                    order_hash = splittedMessage[1];
                }
            }
        } else if (e.data.indexOf("transaction_failed") > -1) {
            isTransactionSuccess = false;
            var splittedMessage = e.data.split("/");
            if (splittedMessage.length > 1){
                if (splittedMessage[1] != "") {
                    order_hash = splittedMessage[1];
                }
            }
        }

    }
}
addEventListener("message", handleArrow, true);

var arrowOrder = new Object;
arrowOrder.items = [];
arrowOrder.extraData = [];

waitForElementToDisplay("#arrow-checkout", "#arrow-checkout-short", function(){
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
    .payment-btn-js {
        width: 100%;
        height: 45px !important;
        border-radius: 8px;
        padding: 10px 20px;
        background-color: black !important;
        margin-bottom: 4px;
        align-items: center;
        justify-content: center;
        display: flex;
    }
    .payment-btn-js img {
        margin-bottom: -3px;
        margin-left: 6px;
        display: inline;
    }
    .payment-btn-js a {
        text-decoration: none;
        color: #FFFFFF;
        font-weight: 700;
        font-size: 14px;
        font-family: 'Nunito';
        text-transform: capitalize;
        letter-spacing: 0.05em;
    }
    .backdrop {
        z-index: 999;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
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
        color: #0286ff;
        text-decoration: underline;
        cursor: pointer;
    }`;
    headID.appendChild(style);



    var span = document.getElementById("arrow-checkout");
    if (span != null){
        span.innerHTML = `<div class="payment-btn-js d-flex align-items-center" id="arrow-checkout">
        <a href="#" class="mx-auto" id="arrow">ARROW CHECKOUT <img src="`+arrowHost+`/assets/images/arrow.png" alt="arrow-logo"></a>
        </div>`;
    }

    var span2 = document.getElementById("arrow-checkout-short");
    if (span2 != null) {
        span2.innerHTML = `<div class="payment-btn-js d-flex align-items-center" id="arrow-checkout-short">
        <a href="#" class="mx-auto" id="arrow">CHECKOUT <img src="`+arrowHost+`/assets/images/arrow.png" alt="arrow-logo"></a>
        </div>`;
    }
},1000,60000);