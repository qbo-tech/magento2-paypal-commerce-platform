define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/storage',
        'jquery',
        'paypalSdkAdapter',
        'Magento_Customer/js/customer-data'
    ],
    function (Component, storage, $, paypalSdkAdapter, customerData) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'PayPal_CommercePlatform/payment/paypaladvanced-form'
            },
            /* initialize: function () {
              this._super();
            }, */
            componentName: "paypalSdkComponent",
            orderId: null,
            paypalSdk: window.checkoutConfig.payment.paypalcp.urlSdk,

            /**
             * Renders the PayPal card fields
             *
             */
            renderHostedFields: function() {
                var self = this;

                if ((typeof paypal === 'undefined')) {
                    return;
                }

                console.log('###hostedfieldsRender#HostedFields.isEligible', paypal.HostedFields.isEligible());

                if (!paypal.HostedFields.isEligible()) {
                    console.log('HostedFields HOSTEDFIELDS_NOT_ELIGIBLE');
                    return;
                }
                paypal.HostedFields.render({
                    styles: {
                        'input': {
                            'font-size': '10pt',
                            'color': '#3A3A3A'
                        },
                        '.number': {
                            'font-family': 'monospace'
                        },
                        '.valid': {
                            'color': 'black'
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
                            return data.result.id;
                        });

                    },
                    onApprove: function (data, actions) {
                        console.log('###paypal_advanced-method#hostedfieldsRender#onApprove#data', data, actions);
                        self.orderId = data.id;

                        self.placeOrder();
                    },
                    onError: function (err) {
                        console.log('paypal_advanced-method#hostedfieldsRender#onError', err);
                    }

                }).then(function (hf) {
                    $('#co-payment-form, #card-form').submit(function (event) {
                        event.preventDefault();
                        $('#submit').prop('disabled', true);
                        const submitOptions = {
                            cardholderName: document.getElementById('card-holder-name').value,
                            vault: $('#vault').is(':checked')
                        };

                        hf.submit(submitOptions)
                            .then(function (payload) {
                                console.log('hf.submit#payload', payload);
                                self.orderId = payload.orderId;
                                self.placeOrder();

                                self.enableCheckout();
                            })
                            .catch(function (err) {
                                console.log(' catch => ', err);
                                self.enableCheckout();
                            });
                        return false;
                    });
                });
            },
            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'order_id': this.orderId,
                    }
                };

                return data;
            },
            renderSmartButton: function(){
                var self = this;

                paypal.Buttons({
                    style: {
                        //layout:  'horizontal'
                        layout: 'vertical'
                    },
                    //commit: true,
                    //enableVaultInstallments: false,
                    //enableStandardCardFields: true,
                    createOrder: function () {
                        console.log('### paypal_advanced-method#renderButton#createOrder');

                        /* var ret = storage.post(
                            '/paypalcheckout/order', {}, false
                        ).fail(
                            function (response) {
                                console.log("Failed saving cards:" + response);
                                //self.errorProcessor.process(response, self.messageContainer);
                                message: $.mage.__('An error ocurred while saving card.');
                            }
                        ) *//* .done(
                            function (result) {
                                console.log("Saved cards:" + JSON.stringify(result));
                                var message = {
                                    message: $.mage.__('Card successfully saved.')
                                };
                                return result;

                                //TODO: Let or not the user know about saved card before placing order ? Let merchant decide with config ?
                                //self.messageContainer.addSuccessMessage(message);
                            }
                        ) *//* .done(
                            function (data) {
                                console.log("### paypal_advanced-method#renderButton#createOrder#data:" + JSON.stringify(data));
                                var message = {
                                    message: $.mage.__('Card successfully saved.')
                                };
                                console.log("### paypal_advanced-method#renderButton#createOrder#data.result.id:" + data.result.id);

                                return data.result.id;

                                //TODO: Let or not the user know about saved card before placing order ? Let merchant decide with config ?
                                //self.messageContainer.addSuccessMessage(message);
                            }
                        );

                        console.log('###ret###', ret);

                        return ret.done(function (data) {
                            console.log('###ret#data', data);
                            return data.result.id;
                        });

                        return ret; */

                        return fetch('/paypalcheckout/order', {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function (res) {
                            console.log('###paypal_advanced-method#renderButton#createOrder# res =', res);
                            return res.json();
                        }).then(function (data) {
                            console.log('###paypal_advanced-method#renderButton#createOrder# data.result =', data.result);
                            return data.result.id; // Use the key sent by your server's response, ex. 'id' or 'token'
                        });

                    },
                    onApprove: function (data, actions) {
                        console.log('###paypal_advanced-method#renderSmartButton#onApprove#data, actions', data, actions);

                        self.orderId = data.orderID;
                        self.placeOrder();
                    },
                    onError: function (err) {
                        console.log('paypal_advanced-method#renderButton#onError', err);
                    }
                }).render('#paypal-button-container');

            },
            rendersPayments: function(){
                var self = this;

                console.log('#rendersPayments#');

                self.renderHostedFields();
                self.renderSmartButton();

            },
            completeRender: function (){
                var self = this;
                console.log('ON completeRender', paypalSdkAdapter);

                paypalSdkAdapter.loadSdk(function () { self.rendersPayments()});
            },
            enableCheckout: function(){
                $('#submit').prop('disabled', false);
            }
        });
    }
);
