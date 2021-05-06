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

            loadSdk: function (callbackOnLoaded) {
                var self = this;
                //console.log('#loadSdk#', callbackOnLoaded);

                self.onLoadedCallback = callbackOnLoaded;

                var componentUrl = self.paypalSdk;

                if ((typeof paypal === 'undefined')) {

                    var clientToken = paypalTokenAdapter.generateClientToken(self.customerId);

                    if (clientToken) {
                        var objCallback = {
                            completeCallback: function (resultIndicator, successIndicator) {
                                console.log('completeCallback complete');
                            },
                            errorCallback: function () {
                                console.error('Payment errorCallback');

                            },
                            cancelCallback: function () {
                                console.log('Payment cancelled');
                            },
                            onLoadedCallback: function () {
                                console.log('paypal load paypal', paypal);
                                $(document).ready(function () {
                                    console.log('Payment onLoadedCallback READY', self.onLoadedCallback);
                                    return callbackOnLoaded.call();
                                });
                                console.log('Load paypal Component');
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

                        htmlElement.setAttribute('data-service-stage', 'sandbox.paypal.com');
                        htmlElement.setAttribute('data-stage', 'sandbox.paypal.com');
                        htmlElement.setAttribute('data-api-stage-host', 'api.sandbox.paypal.com');
                        htmlElement.setAttribute('data-error', 'window.ErrorCallback');
                        htmlElement.setAttribute('data-cancel', 'window.ErrorCallback');
                        htmlElement.setAttribute('data-complete', 'window.CompletedCallback');
                        htmlElement.setAttribute('data-client-token', clientToken);
                    }
                }
            }
        };
    }
);
