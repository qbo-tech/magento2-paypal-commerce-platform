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
        customerId: window.checkoutConfig.payment.paypalcp.customer.id,
        isVaultingEnable: window.checkoutConfig.payment.paypalcp.acdc.enable_vaulting,
        isAcdcEnable: window.checkoutConfig.payment.paypalcp.acdc.enable,
        isEnableReferenceTransactions: window.checkoutConfig.payment.paypalcp.referenceTransaction.enable,

        loadSdk: function (callbackOnLoaded, withVault = false) {
            var self = this;
            self.logger('#loadSdk 1 #', callbackOnLoaded);
            self.logger('#loadSdk 2 #', withVault);

            self.onLoadedCallback = callbackOnLoaded;

            if(withVault) {
                self.paypalSdk += '&vault=true';
            }

            var componentUrl = self.paypalSdk;
            var clientToken = null;
            console.info('self.paypalSdk ', self.paypalSdk);

            if ((typeof paypal === 'undefined')) {

                if(self.isAcdcEnable) {
                    console.info('Generating ClientToken...');
                    clientToken = paypalTokenAdapter.generateClientToken(self.customerId);
                }

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

                if(clientToken && self.isAcdcEnable) {
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
