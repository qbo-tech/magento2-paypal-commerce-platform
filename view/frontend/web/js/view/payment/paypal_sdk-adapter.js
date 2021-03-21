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

            loadSdk: function (callbackOnLoaded) {
                var self = this;
                console.log('#loadSdk#', callbackOnLoaded);

                self.onLoadedCallback = callbackOnLoaded;

                var componentUrl = self.paypalSdk;

                if ((typeof paypal === 'undefined')) {

                    var clientToken = paypalTokenAdapter.generateClientToken('testVault1');

                    console.log('finish generateToken', clientToken);

                    if (clientToken) {
                        var objCallback = {
                            completeCallback: function (resultIndicator, successIndicator) {
                                console.log('Payment complete');
                                if (window.checkoutConfig.banamexObject) {
                                    window.checkoutConfig.banamexObject.processOncomplete(resultIndicator, successIndicator);
                                } else {
                                    console.log('Error onCompleteCallback paypal');
                                }
                            },
                            errorCallback: function () {
                                if (window.checkoutConfig.banamexObject) {
                                    window.checkoutConfig.banamexObject.errorCallback();
                                } else {
                                    console.log('error on paypal');
                                }
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
            },

            renderHostedFields: function () {
                var self = this;

                if ((typeof paypal === 'undefined')) {
                    self.loadSdk();
                }

                console.log('###hostedfieldsRender#HostedFields.isEligible', paypal.HostedFields.isEligible());

                if (!paypal.HostedFields.isEligible()) {
                    console.log('HostedFields HOSTEDFIELDS_NOT_ELIGIBLE');
                    //return;
                }
                paypal.HostedFields.render({
                    styles: {
                        'input': {
                            'font-size': '16pt',
                            'color': '#3A3A3A'
                        },
                        '.number': {
                            'font-family': 'monospace'
                        },
                        '.valid': {
                            'color': 'green'
                        }
                    },
                    fields: {
                        number: {
                            selector: '#card-number',
                            placeholder: 'card number'
                        },
                        cvv: {
                            selector: '#cvv',
                            placeholder: 'card security number'
                        },
                        expirationDate: {
                            selector: '#expiration-date',
                            placeholder: 'mm / yy'
                        }
                    },
                    createOrder: function () {
                        console.log('### paypal_advanced-method#hostedfieldsRender#createOrder');

                        return fetch('/paypalcheckout/order', {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function (res) {
                            console.log('###paypal_advanced-method#hostedfieldsRender#createOrder# res =', res);
                            return res.json();
                        }).then(function (data) {
                            console.log('###paypal_advanced-method#hostedfieldsRender#createOrder# data.result =', data.result);
                            return data.result.id; // Use the key sent by your server's response, ex. 'id' or 'token'
                        });

                    },
                }).then(function (hf) {

                    $('#co-payment-form').submit(function (event) {
                        event.preventDefault();
                        disableCheckout();
                        const submitOptions = {
                            cardholderName: document.getElementById('card-holder-name').value,
                            vault: $('#vault').is(':checked')
                        };
                        const installment = document.getElementById('installments').value;
                        if (installment && installment !== '') {
                            // installment will be '', if the user hasn't select any installments.
                            var choice = JSON.parse(installment);
                            submitOptions.installments = {
                                term: choice.term,
                                intervalDuration: choice.interval_duration
                            };
                        }
                        hf.submit(submitOptions)
                            .then(function (payload) {
                                updateMessage(payload);
                                var orderResponse = self.placeOrder();
                            })
                            .catch(function (err) {
                                updateMessage(err);
                                enableCheckout();
                            });
                        return false;
                    });
                });
            }
        };
    }
);
