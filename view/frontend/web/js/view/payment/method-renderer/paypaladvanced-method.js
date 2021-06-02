define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/storage',
        'jquery',
        'paypalSdkAdapter',
        'paypalFraudNetAdapter',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote',
        'ko',
        'Magento_Checkout/js/model/totals',
        'mage/translate'
    ],
    function (Component, storage, $, paypalSdkAdapter, paypalFraudNetAdapter, selectPaymentMethodAction, checkoutData, quote, ko, totals, $t) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PayPal_CommercePlatform/payment/paypaladvanced-form'
            },

            componentName: "paypalSdkComponent",
            paypalMethod: 'paypalcp',
            orderId: null,
            paypalSdk: window.checkoutConfig.payment.paypalcp.urlSdk,
            customerId: window.checkoutConfig.payment.paypalcp.customer.id,
            paypalConfigs: window.checkoutConfig.payment.paypalcp,
            isBcdcEnable: window.checkoutConfig.payment.paypalcp.bcdc.enable,
            isAcdcEnable: window.checkoutConfig.payment.paypalcp.acdc.enable,
            fraudNetSwi: window.checkoutConfig.payment.paypalcp.fraudNet.sourceWebIdentifier, //Source Website Identifier
            sessionIdentifier: window.checkoutConfig.payment.paypalcp.fraudNet.sessionIdentifier,
            selectedMethod: null,
            installmentOptions: ko.observableArray(),
            installmentsAvailable: ko.observable(false),
            canShowInstallments: ko.observable(false),
            selectedInstallments: ko.observable(),
            isFormValid: ko.observable(false),
            customerCards: ko.observableArray(window.checkoutConfig.payment.paypalcp.customer.payments.cards),

            getCode: function (method) {
                this.logger('paypaladvanced-mthod#super', this._super());
                this.logger('paypaladvanced-mthod#mthod', method);

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

                if (self.isAcdcEnable) {
                    return true;
                }

                return false;
            },

            isInstallmentsEnable: function () {
                return ((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_installments));
            },

            isVaultingEnable: function () {
                return ((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_vaulting) && (this.paypalConfigs.customer.id != null));
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
                    self.isVisibleCard(false);
                    self.installmentsAvailable(false);
                    return;
                } else {
                    self.isVisibleCard(true);

                    if (self.customerCards().length > 0) {
                        $('#paypalcheckout').hide();
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

                                    var options = self.fillInstallmentOptions(financialOption);
                                    self.installmentOptions(options);
                                    self.installmentsAvailable(true);
                                    self.canShowInstallments(true);

                                    self.logger('financialOption.qualifying_financing_options#option', option);
                                });
                            },
                            onInstallmentsError: function () {
                                self.installmentsAvailable(false);
                                console.log('Error while fetching installments');
                            }
                        },
                        createOrder: function (data) {
                            return fetch('/paypalcheckout/order', {
                                method: 'post',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                            }).then(function (res) {
                                self.logger('###paypal_advanced-method#hostedfieldsRender#createOrder# res =', res);
                                if (res.ok) {
                                    return res.json();
                                } else {
                                    self.messageContainer.addErrorMessage({
                                        message: $t('An error has occurred on the server, please try again later')
                                    });

                                    return false;
                                }
                            }).then(function (data) {
                                self.logger('###paypal_advanced-method#hostedfieldsRender#createOrder# data.result =', data.result);
                                return data.result.id;
                            });

                        },
                        onApprove: function (data, actions) {
                            self.logger('###paypal_advanced-method#hostedfieldsRender#onApprove#data', data, actions);
                            self.orderId = data.id;

                            self.placeOrder();
                        },
                        onError: function (err) {
                            self.logger('paypal_advanced-method#hostedfieldsRender#onError', err);
                            self.messageContainer.addErrorMessage({
                                message: $t('Transaction cannot be processed, please verify your card information or try another.')
                            });
                        }

                    }).then(function (hf) {
                        $('#card-form button#submit').attr('disabled', true);

                        $('#card-holder-name').bind('input', function () {
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

                        $('.customer-card-list > ul > li > input[name=card]').change(function () {
                            var body = $('body').loader();
                            body.loader('show');

                            console.log('ONchange#267', this);
                            self.installmentOptions(null);
                            self.selectedInstallments(null);
                            self.canShowInstallments(false);

                            if (this.id == 'new-card') {
                                $('#customer-card-token').hide();
                                $('#paypalcheckout').show();
                            } else {
                                $('#token-submit').prop('disabled', false);

                                $('#customer-card-token').show();
                                $('#paypalcheckout').hide();

                                var cardId = this.id;

                                const card = self.customerCards().find(element => element.id == cardId);

                                var options = self.fillInstallmentOptions(card.financing_options[0]);

                                self.installmentOptions(options);
                                self.installmentsAvailable(true);
                                self.canShowInstallments(true);
                            }
                            body.loader('hide');
                        });

                        $('.customer-card-list button#token-submit').click(function (event) {
                            $('#token-submit').prop('disabled', true);
                            event.preventDefault();

                            var submitOptions = {};
                            submitOptions = self.validateInstallment(submitOptions);

                            console.log('self.creeateOrder', self.createOrder().done(function (response) {
                                console.log('repsonse', response);
                                self.orderId = response.result.id//.orderID;
                                self.placeOrder();
                            }).fail(function (response) {
                                console.error('FAILED paid whit tojen card', response);
                                $('#submit').prop('disabled', false);
                            }));

                            $('#submit').prop('disabled', false);
                        });

                        $('#co-payment-form, #card-form').submit(function (event) {
                            event.preventDefault();

                            $('#submit').prop('disabled', true);

                            var body = $('body').loader();
                            body.loader('show');

                            var submitOptions = {
                                cardholderName: document.getElementById('card-holder-name').value,
                                vault: $('#vault').is(':checked')
                            };

                            submitOptions = self.validateInstallment(submitOptions);

                            self.logger('317submitOptions#co-payment-form, #card-form#submitOptions', submitOptions);

                            hf.submit(submitOptions)
                                .then(function (payload) {
                                    self.logger('hf.submit#payload', payload);
                                    self.orderId = payload.orderId;
                                    self.logger('placeorder', self.placeOrder());

                                    self.enableCheckout();
                                })
                                .catch(function (err) {
                                    self.logger('355 catch => ', err);

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
                }
            },
            fillInstallmentOptions: function (financialOption) {
                var self = this;
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
                });

                return options;
            },
            validateInstallment: function (submitOptions) {
                var self = this;

                const installment = self.selectedInstallments();

                console.log('validateInstallment#installment', installment, submitOptions);

                if (installment && installment.term !== '') {
                    submitOptions.installments = {
                        term: installment.term,
                        interval_duration: installment.interval_duration,
                        intervalDuration: installment.interval_duration
                    };
                    console.log('validateInstallment#submitOPtion', submitOptions);

                    console.log('input checked', $('.customer-card-list > ul > li > input[name=card]:checked').val());

                    if ((self.customerCards().length > 0) && $('.customer-card-list > ul > li > input[name=card]:checked').val() != 'new-card') {
                        submitOptions = {
                            payment_source: {
                                token: {
                                    id: $('.customer-card-list > ul > li > input[name=card]:checked').val(),
                                    type: "PAYMENT_METHOD_TOKEN",
                                    attributes: {
                                        installments: submitOptions.installments
                                    }
                                }
                            }
                        }
                        console.log(submitOptions);
                    }
                }

                console.log('validateInstallment#return#submitOPtion', submitOptions);
                return submitOptions;
            },
            getData: function () {
                var self = this;

                var data = {
                    method: self.paypalMethod,
                    additional_data: {
                        order_id: self.orderId,
                        fraudNetCMI: self.sessionIdentifier
                    }
                };

                var submitOptions = self.validateInstallment({});

                if ((submitOptions) && submitOptions.hasOwnProperty('payment_source')) {
                    data.additional_data.payment_source = JSON.stringify(submitOptions.payment_source);
                }

                return data;
            },
            isValidFields: function (hostedFieldsInstance) {
                var self = this;
                var state = hostedFieldsInstance.getState();
                self.logger('state', state);
                var formValid = Object.keys(state.fields).every(function (key) {
                    return !state.fields[key].isEmpty;
                });

                if (formValid && (!$('#card-holder-name').val() == '')) {
                    $('#card-form button#submit').attr('disabled', false);
                    self.isFormValid(true);

                    return true;
                } else {
                    $('#card-form button#submit').attr('disabled', true);
                    self.isFormValid(false);

                    return false;
                }
            },
            renderSmartButton: function () {
                var self = this;

                if ((typeof paypal === 'undefined')) {

                    self.logger('Este medio de pago no se encuentra disponible: Lo sentimos, inténtalo más tarde o comunícate al servicio al clientes.', '');

                    $('#paypal-button-container').html($t('Este medio de pago no se encuentra disponible: Lo sentimos, inténtalo más tarde o comunícate al servicio al clientes.'));
                    return;
                }

                paypal.Buttons({
                    style: {
                        layout: 'horizontal'
                        //layout: 'vertical'
                    },
                    funding: {
                        //allowed: [paypal.FUNDING.CARD],
                        disallowed: [paypal.FUNDING.CARD, paypal.FUNDING.CREDIT]
                    },
                    commit: true,
                    enableVaultInstallments: (this.paypalConfigs.acdc.enable_installments) ? true : false,
                    //enableStandardCardFields: true,
                    createOrder: function () {

                        return fetch('/paypalcheckout/order', {
                            method: 'post',
                            headers: {
                                'content-type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function (res) {
                            self.logger('###paypal_advanced-method#renderButton#createOrder# res =', res);
                            return res.json();
                        }).then(function (data) {
                            self.logger('###paypal_advanced-method#renderButton#createOrder# data =', data);
                            if (data.reason) {
                                self.logger('###paypal_advanced-method#renderButton#createOrder# data.reason =', JSON.parse(data.reason));

                                self.messageContainer.addErrorMessage({
                                    message: $t(JSON.parse(data.reason).message)
                                });
                                return false;
                            }

                            return data.result.id;
                        });

                    },
                    onApprove: function (data, actions) {
                        self.orderId = data.orderID;
                        self.placeOrder();
                    },
                    onError: function (err) {
                        self.logger('paypal_advanced-method#renderButton#onError', err);
                        self.messageContainer.addErrorMessage({
                            message: $t('Transaction cannot be processed, please verify your card information or try another.')
                        });
                    }
                }).render('#paypal-button-container');

            },
            createOrder: function (requestBody) {
                var self = this;

                return storage.post('/paypalcheckout/order'
                ).done(function (response) {
                    return response;
                }
                ).fail(function (response) { });
            },
            rendersPayments: function () {
                var self = this;

                $t('MONTHS');

                self.renderHostedFields();
                self.renderSmartButton();
            },
            completeRender: function () {
                var self = this;

                if (self.isVaultingEnable && (self.fraudNetSwi != '') && (self.fraudNetSwi != 'null') && (self.fraudNetSwi)) {
                    paypalFraudNetAdapter.loadFraudNetSdk(function () { });
                }

                paypalSdkAdapter.loadSdk(function () {
                    self.rendersPayments();

                    $('#card-form button#submit').attr('disabled', true);
                });

                $('.customer-card-list span.card-delete').click(function () {
                    var body = $('body').loader();
                    body.loader('show');

                    var objCard = $(this);
                    var tokenId = objCard.data('id');

                    console.log('Ondelete ', tokenId);

                    return storage.post('/paypalcheckout/vault/remove/', JSON.stringify({ id: tokenId })
                    ).done(function (response) {
                        console.log('vault/remove#respose', response);

                        $('li#card-' + tokenId).remove();
                        body.loader('hide');

                        return response;
                    }
                    ).fail(function (response) {
                        console.error('fail delete#reposne', response);
                        body.loader('hide');

                    });
                    body.loader('hide');

                });
            },
            enableCheckout: function () {
                $('#submit').prop('disabled', false);

                var body = $('body').loader();
                body.loader('hide');
            },
            logger: function (message, obj) {
                if (window.checkoutConfig.payment.paypalcp.debug) {
                    console.log(message, obj);
                }
            }
        });
    }
);
