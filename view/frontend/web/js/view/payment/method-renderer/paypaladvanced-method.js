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
        'mage/translate'
    ],
    function (Component, storage, $, paypalSdkAdapter, selectPaymentMethodAction, checkoutData, quote, ko, totals, $t) {
        'use strict';
        console.log('paypal_advance-method');

        return Component.extend({
            defaults: {
                template: 'PayPal_CommercePlatform/payment/paypaladvanced-form'
            },

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
            installmentsAvailable: ko.observable(false),
            canShowInstallments: ko.observable(false),
            selectedInstallments: ko.observable(),
            isFormValid: ko.observable(false),

            getCode: function (method) {
                console.log('paypaladvanced-mthod#super', this._super());
                console.log('paypaladvanced-mthod#mthod', method);

                return method;
            },

            isSelected: function () {
                var self = this;

                if (quote.paymentMethod() && (quote.paymentMethod().method == self.paypalMethod)) {
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
                return ((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_installments));
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
            },

            /**
             * Renders the PayPal card fields
             *
             */
            renderHostedFields: function () {
                var self = this;

                self.installmentsAvailable((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_installments));

                if ((typeof paypal === 'undefined')) {
                    return;
                }

                if (!paypal.HostedFields.isEligible()) {
                    console.log('HostedFields HOSTEDFIELDS_NOT_ELIGIBLE');
                    return;
                }
                paypal.HostedFields.render({
                    styles: {
                        'input': {
                            'font-size': '14px',
                            'color': '#3A3A3A',
                            'font-family': "'Open Sans','Helvetica Neue',Helvetica,Arial,sans-serif"
                        },
                        '.number': {
                            'font-family': "'Open Sans','Helvetica Neue',Helvetica,Arial,sans-serif"
                        },
                        '.valid': {
                            'color': 'black'
                        },
                        '.invalid': {
                            'color': 'red'
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
                            placeholder: 'mm / aa'
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
                                console.log("MSI not available");
                                self.installmentsAvailable(false);
                                self.canShowInstallments(true);

                                var option = {
                                    value: "Tu tarjeta no es elegible para Meses sin Intereses",
                                    currency_code: '',
                                    interval: '',
                                    term: '',
                                    interval_duration: '',
                                    discount_percentage: ''
                                };
                                var options = [];
                                options.push(option);
                                self.installmentOptions(options);

                                return;
                            }

                            qualifyingOptions.forEach(function (financialOption) {

                                var options = [];

                                financialOption.qualifying_financing_options.forEach(function (qualifyingFinancingOption) {

                                    var option = {
                                        value: qualifyingFinancingOption.monthly_payment.value,
                                        currency_code: qualifyingFinancingOption.monthly_payment.currency_code,
                                        interval: $t(qualifyingFinancingOption.credit_financing.interval),
                                        term: qualifyingFinancingOption.credit_financing.term,
                                        interval_duration: qualifyingFinancingOption.credit_financing.interval_duration,
                                        discount_percentage: qualifyingFinancingOption.discount_percentage
                                    };

                                    options.push(option);
                                    self.installmentOptions(options);
                                    self.installmentsAvailable(true);
                                    self.canShowInstallments(true);

                                    console.log('financialOption.qualifying_financing_options#option', option)
                                });
                            });
                        },
                        onInstallmentsError: function () {
                            self.installmentsAvailable(false);
                            console.log('Error while fetching installments');
                        }
                    },
                    createOrder: function () {
                        return fetch('/paypalcheckout/order', {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function (res) {
                            console.log('###paypal_advanced-method#hostedfieldsRender#createOrder# res =', res);
                            if (res.ok) {
                                return res.json();
                            } else {
                                self.messageContainer.addErrorMessage({
                                    message: $t('An error has occurred on the server, please try again later')
                                });

                                return false;
                            }
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
                        self.messageContainer.addErrorMessage({
                            message: $t('Transaction cannot be processed, please verify your card information or try another.')
                        });
                    }

                }).then(function (hf) {
                    $('#card-form button#submit').attr('disabled', true);

                    $('#card-holder-name').change(function () {
                        self.isValidFields(hf);
                    });

                    hf.on('empty', function (event) {
                        self.isValidFields(hf);
                    });

                    hf.on('notEmpty', function (event) {
                        self.isValidFields(hf);
                    });

                    hf.on('validityChange', function (event) {
                        self.isValidFields(hf);
                    });

                    $('#co-payment-form, #card-form').submit(function (event) {
                        event.preventDefault();

                        $('#submit').prop('disabled', true);
                        const submitOptions = {
                            cardholderName: document.getElementById('card-holder-name').value,
                            vault: $('#vault').is(':checked')
                        };

                        const installment = self.selectedInstallments();

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
                                console.log('placeorder', self.placeOrder());

                                self.enableCheckout();
                            })
                            .catch(function (err) {
                                console.log(' catch => ', err);

                                if (err.hasOwnProperty('details')) {
                                    self.messageContainer.addErrorMessage({
                                        message: $t('Transaction cannot be processed, please verify your card information or try another.')
                                    });
                                }

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
            isValidFields: function (hostedFieldsInstance) {
                var self = this;
                var state = hostedFieldsInstance.getState();
                console.log('state', state);
                var formValid = Object.keys(state.fields).every(function (key) {
                    return !state.fields[key].isEmpty;
                });

                if (formValid && (!$('#card-holder-name').val() == '')) {
                    $('#card-form button#submit').attr('disabled', false);
                    self.isFormValid(true);

                    return true;
                } else {
                    $('#card-form button#submit').attr('disabled', true);

                    return false;
                }
            },
            renderSmartButton: function () {
                var self = this;

                console.log('renderSmartButton');

                if ((typeof paypal === 'undefined')) {

                    console.log('Este medio de pago no se encuentra disponible: Lo sentimos, inténtalo más tarde o comunícate al servicio al clientes.');

                    $('#paypal-button-container').html($t('Este medio de pago no se encuentra disponible: Lo sentimos, inténtalo más tarde o comunícate al servicio al clientes.'));
                    return;
                }

                paypal.Buttons({
                    style: {
                        //layout:  'horizontal'
                        layout: 'vertical'
                    },
                    commit: true,
                    enableVaultInstallments: (this.paypalConfigs.acdc.enable_installments) ? true : false,
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

                                self.messageContainer.addErrorMessage({
                                    message: $t(JSON.parse(data.reason).message)
                                });
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
                        self.messageContainer.addErrorMessage({
                            message: $t('Transaction cannot be processed, please verify your card information or try another.')
                        });
                    }
                }).render('#paypal-button-container');

            },
            rendersPayments: function () {
                var self = this;

                console.log('#rendersPayments#');

                $t('MONTHS');

                self.renderHostedFields();
                self.renderSmartButton();
            },
            completeRender: function () {
                var self = this;
                console.log('ON completeRender', paypalSdkAdapter);

                paypalSdkAdapter.loadSdk(function () {
                    self.rendersPayments();

                    $('#card-form button#submit').attr('disabled', true);
                });
            },
            enableCheckout: function () {
                $('#submit').prop('disabled', false);
            }
        });
    }
);
