this.Element && function (ElementPrototype) {
    ElementPrototype.matches = ElementPrototype.matches ||
        ElementPrototype.matchesSelector ||
        ElementPrototype.webkitMatchesSelector ||
        ElementPrototype.msMatchesSelector ||
        function (selector) {
            var node = this, nodes = (node.parentNode || node.document).querySelectorAll(selector), i = -1;
            while (nodes[++i] && nodes[i] != node) ;
            return !!nodes[i];
        }
}(Element.prototype);

// closest polyfill
this.Element && function (ElementPrototype) {
    ElementPrototype.closest = ElementPrototype.closest ||
        function (selector) {
            var el = this;
            while (el.matches && !el.matches(selector)) el = el.parentNode;
            return el.matches ? el : null;
        }
}(Element.prototype);

var PlentyAmazonPay = {
    debug: true,
    payButtonCount: 0,
    init: function () {
        PlentyAmazonPay.registerChangeActions();
        PlentyAmazonPay.registerLoginButtons();
        PlentyAmazonPay.registerCheckoutButtons();
        PlentyAmazonPay.registerAddCartButtons();

    },
    initCheckout: function (createCheckoutSessionConfig) {
        console.log('initCheckout()');
        PlentyAmazonPay.registerHiddenButton(createCheckoutSessionConfig);
    },
    ajaxPost: function (form, callback) {
        var url = form.action,
            xhr = new XMLHttpRequest();

        var params = [];

        var fields = form.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.name && field.value) {
                params.push(encodeURIComponent(field.name) + '=' + encodeURIComponent(field.value));
            }
        }
        params = params.join('&');
        PlentyAmazonPay.ajaxPostData(url, params, callback);
    },

    ajaxPostData: function (url, postData, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", url);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = callback.bind(xhr);
        xhr.send(postData);
    },

    registerChangeActions: function () {
        try {
            amazon.Pay.bindChangeAction('#amazon-pay-change-address', {
                amazonCheckoutSessionId: AmazonPayConfiguration.checkoutSessionId,
                changeAction: 'changeAddress'
            });
        } catch (e) {
            //if (PlentyAmazonPay.debug) console.warn(e);
        }
        try {
            amazon.Pay.bindChangeAction('#amazon-pay-change-payment', {
                amazonCheckoutSessionId: AmazonPayConfiguration.checkoutSessionId,
                changeAction: 'changePayment'
            });
        } catch (e) {
            //if (PlentyAmazonPay.debug) console.warn(e);
        }
    },
    registerCheckoutButtons: function () {
        try {
            var buttons = document.querySelectorAll('.amazon-pay-button');
            for (var i = 0; i < buttons.length; i++) {
                var button = buttons[i];
                if (!button.id) {
                    var id = 'amazon-pay-button-' + PlentyAmazonPay.payButtonCount++;
                    button.id = id;
                    amazon.Pay.renderButton('#' + id, {
                        merchantId: AmazonPayConfiguration.merchantId,
                        createCheckoutSession: {
                            url: AmazonPayConfiguration.createCheckoutSessionUrl
                        },
                        sandbox: AmazonPayConfiguration.isSandbox,
                        ledgerCurrency: AmazonPayConfiguration.ledgerCurrency,
                        estimatedOrderAmount: {amount: parseFloat(window.ceresStore.state.basket.data.basketAmount).toString() || '', currencyCode: window.ceresStore.state.basket.data.currency || ''},
                        checkoutLanguage: AmazonPayConfiguration.language,
                        productType: 'PayAndShip', //TODO
                        placement: 'Cart', //TODO
                        buttonColor: button.getAttribute('data-color') ? button.getAttribute('data-color') : 'Gold'
                    });
                }
            }
        } catch (e) {
            if (PlentyAmazonPay.debug) console.warn(e);
        }
    },

    registerLoginButtons: function () {
        try {
            const isCheckoutProcess = document.body.classList.contains('page-login');
            const buttons = document.querySelectorAll('.amazon-login-button');
            for (let i = 0; i < buttons.length; i++) {
                let button = buttons[i];
                if (isCheckoutProcess) {
                    button.classList.add('amazon-pay-button');
                    continue;
                }
                if (!button.id) {
                    var id = 'amazon-login-button-' + PlentyAmazonPay.payButtonCount++;
                    button.id = id;
                    amazon.Pay.renderButton('#' + id, {
                        merchantId: AmazonPayConfiguration.merchantId,
                        sandbox: AmazonPayConfiguration.isSandbox,
                        ledgerCurrency: AmazonPayConfiguration.ledgerCurrency,
                        checkoutLanguage: AmazonPayConfiguration.language,
                        productType: 'SignIn',
                        placement: 'Cart', //TODO
                        buttonColor: button.getAttribute('data-color') ? button.getAttribute('data-color') : 'Gold',
                        signInConfig: {
                            payloadJSON: AmazonPayConfiguration.loginPayload,
                            signature: AmazonPayConfiguration.loginSignature,
                            publicKeyId: AmazonPayConfiguration.publicKeyId
                        }
                    });
                }
            }
        } catch (e) {
            if (PlentyAmazonPay.debug) console.warn(e);
        }
    },

    registerHiddenButton: function (createCheckoutSessionConfig) {

        try {
            const hiddenButton = amazon.Pay.renderButton('#amazon-pay-button-hidden', {
                merchantId: AmazonPayConfiguration.merchantId,
                sandbox: AmazonPayConfiguration.isSandbox,
                ledgerCurrency: AmazonPayConfiguration.ledgerCurrency,
                estimatedOrderAmount: window.ceresStore?{amount: parseFloat(window.ceresStore.state.basket.data.basketAmount).toString() || '', currencyCode: window.ceresStore.state.basket.data.currency || ''}:null,
                checkoutLanguage: AmazonPayConfiguration.language,
                productType: 'PayAndShip', //TODO
                placement: 'Checkout'
            });

            hiddenButton.initCheckout({
                createCheckoutSessionConfig: createCheckoutSessionConfig
            });

        } catch (e) {
            if (PlentyAmazonPay.debug) console.warn(e);
        }
    },

    registerAddCartButtons: function () {
        var buttons = document.querySelectorAll('.amazon-add-cart-button');
        for (var i = 0; i < buttons.length; i++) {
            var button = buttons[i];
            try {
                if (!button.id) {
                    var id = 'amazon-add-cart-button-' + PlentyAmazonPay.payButtonCount++;
                    button.id = id;
                    var _button = amazon.Pay.renderButton('#' + id, {
                        merchantId: AmazonPayConfiguration.merchantId,
                        sandbox: AmazonPayConfiguration.isSandbox,
                        ledgerCurrency: AmazonPayConfiguration.ledgerCurrency,
                        estimatedOrderAmount: {amount: parseFloat(window.ceresStore.state.basket.data.basketAmount).toString() || '', currencyCode: window.ceresStore.state.basket.data.currency || ''},
                        checkoutLanguage: AmazonPayConfiguration.language,
                        productType: 'PayAndShip',
                        placement: 'Product',
                        buttonColor: button.getAttribute('data-color') ? button.getAttribute('data-color') : 'Gold',
                    });

                    _button.onClick(function () {
                        PlentyAmazonPay.buyProduct(function () {
                            _button.initCheckout({
                                createCheckoutSession: {
                                    url: AmazonPayConfiguration.createCheckoutSessionUrl
                                }
                            });
                        });
                    });
                }
            } catch (e) {
                if (PlentyAmazonPay.debug) console.warn(e);
            }
        }
    },

    buyProduct: function (callback) {

        var id = null;
        var qty = 1;
        if (typeof window.ceresStore.state.item !== 'undefined' && window.ceresStore.state.item.variation.documents[0]) {
            id = window.ceresStore.state.item.variation.documents[0].data.variation.id;
            qty = parseInt(window.ceresStore.state.item.variationOrderQuantity);
        } else if (typeof window.ceresStore.state.items.mainItemId !== 'undefined' && window.ceresStore.state.items[window.ceresStore.state.items.mainItemId]) {
            id = window.ceresStore.state.items[window.ceresStore.state.items.mainItemId].variation.documents[0].data.variation.id;
            qty = parseInt(window.ceresStore.state.items[window.ceresStore.state.items.mainItemId].variationOrderQuantity);
        }
        if (id) {
            var postData = 'variationId=' + id + '&quantity=' + qty;
            PlentyAmazonPay.ajaxPostData(
                '/rest/io/basket/items/',
                postData,
                function () {
                    callback();
                }
            );
        } else {
            callback();
        }
    }
}
setInterval(PlentyAmazonPay.init, 1000);

if (typeof jQuery !== 'undefined') {
    jQuery(function () {
        jQuery("[id^='reinitPaymentMethod-']").click(function () {
            const orderId = $(this).attr('id').split('-')[1];
            window.location = '/payment/amazon-pay-existing-order?orderId=' + orderId;
        });
    });
}

document.addEventListener('historyPaymentMethodChanged', e => {
    for (let property in e.detail.newOrder.order.properties) {
        console.log('payment method changed event', e);
        if (e.detail.newOrder.order.properties[property].typeId === 3) {
            if (e.detail.newOrder.order.properties[property].value == AmazonPayConfiguration.paymentMethodId) {
                document.getElementById("reinitPaymentMethod-" + e.detail.oldOrder.order.id).style.display = "block";
            } else {
                document.getElementById("reinitPaymentMethod-" + e.detail.oldOrder.order.id).style.display = "none";
            }
        }
    }
});


/*
'Home' - Initial or main page
'Product' - Product details page
'Cart' - Cart review page before buyer starts checkout
'Checkout' - Any page after buyer starts checkout
'Other' - Any page that doesn't fit the previous descriptions

 */
