define([
    'Magento_Checkout/js/view/payment/default',
    'mage/storage',
    'jquery',
    'paypalTokenAdapter',
    'Magento_Customer/js/customer-data'
], function (Component, storage, $, paypalTokenAdapter, customerData) {
    'use strict';
    return {

        componentName: "paypalSdkComponent",

        paypalSdk: window.checkoutConfig.payment.paypalcp.urlSdk,
        onLoadedCallback: '',
        customerId: window.checkoutConfig.payment.paypalcp.customerId,
        isVaultingEnable: window.checkoutConfig.payment.paypalcp.acdc.enable_vaulting,
        isAcdcEnable: window.checkoutConfig.payment.paypalcp.acdc.enable,

        loadSdk: function (callbackOnLoaded) {
            var self = this;
            self.logger('#loadSdk#', callbackOnLoaded);

            self.onLoadedCallback = callbackOnLoaded;

            var componentUrl = self.paypalSdk;

            if ((typeof paypal === 'undefined')) {

                var clientToken = paypalTokenAdapter.generateClientToken(self.customerId);

                if (clientToken) {
                    var objCallback = {
                        completeCallback: function (resultIndicator, successIndicator) {
                            self.logger('completeCallback complete');
                        },
                        errorCallback: function () {
                            self.error('Payment errorCallback');
                        },
                        cancelCallback: function () {
                            self.logger('Payment cancelled');
                        },
                        onLoadedCallback: function () {
                            self.logger('PayPal SDK loaded', paypal);
                            $(document).ready(function () {
                                return callbackOnLoaded.call();
                            });
                            self.logger('Load paypal Component');
                        }
                    };

                    window.ErrorCallback = $.proxy(objCallback, "errorCallback");
                    window.CancelCallback = $.proxy(objCallback, "cancelCallback");
                    window.CompletedCallback = $.proxy(objCallback, "completeCallback");

                    requirejs.load({
                        contextName: '_',
                        onScriptLoad: $.proxy(objCallback, "onLoadedCallback"),
                        config: {
                            baseUrl: componentUrl
                        }
                    }, self.componentName, componentUrl);

                    var htmlElement = $('[data-requiremodule="' + self.componentName + '"]')[0];

                    htmlElement.setAttribute('data-error', 'window.ErrorCallback');
                    htmlElement.setAttribute('data-cancel', 'window.ErrorCallback');
                    htmlElement.setAttribute('data-complete', 'window.CompletedCallback');
                    htmlElement.setAttribute('data-client-token', clientToken);
                }
            }
        },

        logger: function (message, obj) {
            if (window.checkoutConfig.payment.paypalcp.debug) {
                console.log(message, obj);
            }
        }
    };
}
);
