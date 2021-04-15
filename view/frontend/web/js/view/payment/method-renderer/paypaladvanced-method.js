define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/storage',
        'jquery',
        'paypalSdkAdapter',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote',
        'ko',
        'Magento_Checkout/js/model/totals',
        'Magento_Ui/js/model/messageList'
    ],
    function (Component, storage, $, paypalSdkAdapter, selectPaymentMethodAction, checkoutData, quote, ko, totals, globalMessageList) {
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
            customerId: window.checkoutConfig.payment.paypalcp.customerId,
            paypalConfigs: window.checkoutConfig.payment.paypalcp,
            isBcdcEnable: window.checkoutConfig.payment.paypalcp.bcdc.enable,
            isAcdcEnable: window.checkoutConfig.payment.paypalcp.acdc.enable,
            selectedMethod: null,
            installmentOptions: ko.observableArray(),
            selectedInstallments: ko.observable(),


            getCode: function (method) {
                console.log('paypaladvanced-mthod#super', this._super());
                console.log('paypaladvanced-mthod#mthod', method);

                return method;
            },

            isSelected: function () {
                var self = this;

                console.log('isSelected#', quote.paymentMethod())
                console.log('isSelected#', self.paypalMethod)

                if (quote.paymentMethod() && (quote.paymentMethod().method == self.paypalMethod)) {
                    console.log('return#', self.selectedMethod)

                    return self.selectedMethod;
                }

                return false;
            },

            isVisibleCard: function () {
                var self = this;

                if (self.isBcdcEnable || self.isAcdcEnable) {
                    return true;
                }

                return false;
            },

            isInstallmentsEnable: function () {
                return ((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_installments) && (this.installmentOptions().length > 0));
            },

            isVaultingEnable: function () {
                return ((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_vaulting) && (this.paypalConfigs.customerId != null));
            },

            getTitleMethodPaypal: function () {
                if ((this.isBcdcEnable == false) && (this.isAcdcEnable == false)) {
                    return this.paypalConfigs.title;
                } else {
                    return this.paypalConfigs.splitOptions.title_method_paypal
                }
            },

            getTitleMethodCard: function () {
                return this.paypalConfigs.splitOptions.title_method_card;
            },

            selectedPayPalMethod: function (method) {
                var data = this.getData();

                this.selectedMethod = method;
                data.method = this.paypalMethod;

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(this.item.method);
                console.log('selectPaymentMethodSpb#data', data);
            },

            /**
             * Renders the PayPal card fields
             *
             */
            renderHostedFields: function () {
                var self = this;

                //var grandTotal = totals.getSegment('grand_total').value

                console.log('getTotals#', totals.getSegment('grand_total'));

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
                            placeholder: 'Número de tarjeta'
                        },
                        cvv: {
                            selector: '#cvv',
                            placeholder: 'Código de seguridad '
                        },
                        expirationDate: {
                            selector: '#expiration-date',
                            placeholder: 'mm / yy'
                        }
                    },
                    installments: {
                        onInstallmentsRequested: function () {
                            return {
                                amount: String(totals.getSegment('grand_total').value),
                                currencyCode: 'MXN'
                            };
                        },
                        onInstallmentsAvailable: function (installments) {
                            var qualifyingOptions = installments && installments.financing_options && installments.financing_options.filter(function (financialOption) {
                                return financialOption.product === 'CARD_ISSUER_INSTALLMENTS';
                            });

                            var hasCardIssuerInstallment = Boolean(qualifyingOptions && qualifyingOptions.length >= 1 && qualifyingOptions[0].qualifying_financing_options.length > 1);
                            if (!hasCardIssuerInstallment) {
/*                                 appendOption({ type: 'no_installments_option' });
 */                                return;
                            }

                            qualifyingOptions.forEach(function (financialOption) {
                                /*                                 appendOption({ type: 'default_option' });
                                 */
                                var options = [];
                                financialOption.qualifying_financing_options.forEach(function (qualifyingFinancingOption) {

                                    var option = {
                                        value: qualifyingFinancingOption.monthly_payment.value,
                                        currency_code: qualifyingFinancingOption.monthly_payment.currency_code,
                                        interval: qualifyingFinancingOption.credit_financing.interval,
                                        term: qualifyingFinancingOption.credit_financing.term,
                                        interval_duration: qualifyingFinancingOption.credit_financing.interval_duration,
                                        discount_percentage: qualifyingFinancingOption.discount_percentage
                                    };

                                    options.push(option);

                                    console.log('financialOption.qualifying_financing_options#option', option)
                                });

                                self.installmentOptions(options);
                            });
                        },
                        onInstallmentsError: function () {
                            console.log('Error while fetching installments');
                            appendOption({ type: 'error_option' });
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
                        console.log('self.selectedInstallments()', self.selectedInstallments());

                        const installment = self.selectedInstallments();

                        console.log('installment', installment);

                        if (installment && installment !== '') {

                            submitOptions.installments = {
                                term: installment.term,
                                intervalDuration: installment.interval_duration
                            };
                        }

                        console.log('submitOptions', submitOptions);

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
                    'method': this.paypalMethod,
                    'additional_data': {
                        'order_id': this.orderId,
                    }
                };

                return data;
            },
            renderSmartButton: function () {
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
                            console.log('###paypal_advanced-method#renderButton#createOrder# data =', data);
                            if (data.reason) {
                                console.log('###paypal_advanced-method#renderButton#createOrder# data.reason =', JSON.parse(data.reason));

                                globalMessageList.addErrorMessage({
                                    message: JSON.parse(data.reason).message
                                });
                                $(".message.warning").addClass("error").removeClass("warning");
                                $(".message.error").html(data.message);
                                return false;
                            }

                            return data.result.id;
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
            rendersPayments: function () {
                var self = this;

                console.log('#rendersPayments#');

                self.renderHostedFields();
                self.renderSmartButton();

            },
            completeRender: function () {
                var self = this;
                console.log('ON completeRender', paypalSdkAdapter);

                paypalSdkAdapter.loadSdk(function () { self.rendersPayments() });
            },
            enableCheckout: function () {
                $('#submit').prop('disabled', false);
            }
        });
    }
);
