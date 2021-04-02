define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/storage',
        'jquery',
        'paypalSdkAdapter',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote'
    ],
    function (Component, storage, $, paypalSdkAdapter, selectPaymentMethodAction, checkoutData, quote) {
        'use strict';
        console.log('paypal_advance-method');

        return Component.extend({
            defaults: {
                template: 'PayPal_CommercePlatform/payment/paypaladvanced-form'
            },

/*             initialize: function () {
                var self = this;
                this._super();
            }, */
            componentName: "paypalSdkComponent",
            paypalMethod: 'paypalcp',
            orderId: null,
            paypalSdk: window.checkoutConfig.payment.paypalcp.urlSdk,
            selectedMethod: null,

            getCode: function(method) {
                console.log('paypaladvanced-mthod#super', this._super());
                console.log('paypaladvanced-mthod#mthod', method);

                return method;
            },

            isSelected: function () {
                var self = this;

                console.log('isSelected#', quote.paymentMethod())
                console.log('isSelected#', self.paypalMethod)

                if (quote.paymentMethod() && (quote.paymentMethod().method == self.paypalMethod)){
                    console.log('return#', self.selectedMethod)

                    return self.selectedMethod;
                }

                return quote.paymentMethod() ? quote.paymentMethod().method : null;
            },

            selectPaymentMethodSpb: function () {
                //var data = this.getData();
                //data.method =
                this.selectedMethod = "paypalcp_spb";
                /* selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(this.item.method);
                console.log('selectPaymentMethodSpb#data', data); */
                return true;
            },

            selectPaymentMethodHf: function () {
                /* var data = this.getData();
                data.method = */
                this.selectedMethod = "paypalcp_hf";
                /* selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(this.item.method);
                */
                console.log('selectPaymentMethodHf#data', data);
                return true;
            },

            selectedPayPalMethod: function(method){
                var data = this.getData();

                this.selectedMethod = method;
                data.method = this.paypalMethod;

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(this.item.method);
                console.log('selectPaymentMethodSpb#data', data);
/*
                var self = this;

                console.log('isSelected#', quote.paymentMethod())

                if (quote.paymentMethod() && (quote.paymentMethod().method == self.paypalPaymentMethod)) {
                    console.log('isSelected#', self.selectedMethod)

                    return self.selectedMethod;
                }

                return quote.paymentMethod() ? quote.paymentMethod().method : null; */
            },

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
                    'method': 'paypalcp',//this.selectedMethod,
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
