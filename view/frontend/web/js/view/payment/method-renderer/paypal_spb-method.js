define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'paypalSdkAdapter',
        'paypalFraudNetAdapter',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/quote',
        'ko',
        'mage/translate',
        'mage/storage',
        'Magento_Checkout/js/model/totals',
    ],
    function (Component, $, paypalSdkAdapter, paypalFraudNetAdapter, selectPaymentMethodAction, checkoutData, quote, ko, $t, storage, totals) {
        'use strict';

        if (window.checkoutConfig.payment.paypalcp.acdc.enable) {
            window.checkoutConfig.payment.paypalcp.template = 'PayPal_CommercePlatform/payment/paypaladvanced-form';
        } else if (window.checkoutConfig.payment.paypalcp.bcdc.enable) {
            window.checkoutConfig.payment.paypalcp.template = 'PayPal_CommercePlatform/payment/paypal_spb';
        } else {
            window.checkoutConfig.payment.paypalcp.template = 'PayPal_CommercePlatform/payment/paypal-standard';
        }

        return Component.extend({
            defaults: {
                template: window.checkoutConfig.payment.paypalcp.template //'PayPal_CommercePlatform/payment/paypal_spb'
            },

            paypalMethod: 'paypalspb',
            orderId: null,
            paypalConfigs: window.checkoutConfig.payment.paypalcp,
            isBcdcEnable: window.checkoutConfig.payment.paypalcp.bcdc.enable,
            isAcdcEnable: window.checkoutConfig.payment.paypalcp.acdc.enable,
            fraudNetSwi: window.checkoutConfig.payment.paypalcp.fraudNet.sourceWebIdentifier, //Source Website Identifier
            sessionIdentifier: window.checkoutConfig.payment.paypalcp.fraudNet.sessionIdentifier,
            customerCards: ko.observableArray(window.checkoutConfig.payment.paypalcp.customer.payments.cards),
            canShowInstallments: ko.observable(false),
            installmentsAvailable: ko.observable(false),
            installmentOptions: ko.observableArray(),
            selectedInstallments: ko.observable(),
            isFormValid: ko.observable(false),

            isActiveBcdc: function () {
                var self = this;

                return ((self.isBcdcEnable) && (!self.isAcdcEnable));
            },
            isActiveAcdc: function () {
                var self = this;

                if (self.isAcdcEnable) {
                    return true;
                }

                return false;
            },
            isSelected: function () {
                var self = this;

                if (quote.paymentMethod() && (quote.paymentMethod().method == self.paypalMethod)) {
                    return self.selectedMethod;
                }

                return false;
            },
            selectedPayPalMethod: function (method) {
                var self = this;
                var data = this.getData();

                self.selectedMethod = method;
                data.method = self.paypalMethod;

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(this.item.method);
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
            getTitleMethodCard: function () {
                return this.paypalConfigs.splitOptions.title_method_card;
            },
            getCode: function (method) {
                var self = this;

                return method;
            },
            getData: function () {
                var self = this;

                var data = {
                    method: self.paypalMethod,
                    additional_data: {
                        order_id: self.orderId,
                        fraudNetCMI: self.sessionIdentifier,
                        payment_type: (self.isActiveAcdc) ? 'PayPal_Advanced' : 'PayPal_Basic'
                    }
                };

                var submitOptions = self.validateInstallment({});

                if ((submitOptions) && submitOptions.hasOwnProperty('payment_source')) {
                    data.additional_data.payment_source = JSON.stringify(submitOptions.payment_source);
                }

                return data;
            },
            renderButton: function (fundingSource, elementId) {
                var self = this;

                // Initialize the buttons
                var button = paypal.Buttons({
                    fundingSource: fundingSource,
                    // Set up the transaction
                    createOrder: function (data, actions) {

                        var retOrder = self.createOrder(data, actions).then(function (response) {
                            return response.result.id;
                        }).then(function (res) {
                            return res;
                        });

                        return retOrder;
                    },

                    // Finalize the transaction
                    onApprove: function (data, actions) {
                        self.orderId = data.orderID;
                        self.placeOrder();
                    }
                });

                // Check if the button is eligible
                if (button.isEligible()) {

                    // Render the standalone button for that funding source
                    button.render('#' + elementId);
                }

            },
            /**
             * Renders the PayPal card fields
             *
             */
            renderHostedFields: function () {
                var self = this;

                self.installmentsAvailable((this.isAcdcEnable) && (this.paypalConfigs.acdc.enable_installments));

                if ((typeof paypal === 'undefined')) {
                    self.loadSdk();

                    if ((typeof paypal === 'undefined')) return;
                }

                if (!paypal.HostedFields.isEligible()) {
                    self.logger('HostedFields HOSTEDFIELDS_NOT_ELIGIBLE');
                    self.isVisibleCard(false);
                    self.installmentsAvailable(false);
                    return;
                } else {

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
                                    self.logger("MSI not available");
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
                                console.error('Error while fetching installments');
                            }
                        },
                        createOrder: function (data) {
                            let requestBody = {};
                            requestBody.customer_email = quote.guestEmail;
                            return fetch('/paypalcheckout/order', {
                                method: 'post',
                                body: JSON.stringify(requestBody),
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            }).then(function (res) {
                                self.logger('###paypal_advanced-method#hostedfieldsRender#createOrder# res =', res);
                                if (res.ok) {
                                    return res.json();
                                } else {
                                    self.messageContainer.addErrorMessage({
                                        message: $t('An error has occurred on the server, please try again later')
                                    });
                                    self._enableCheckout();

                                    return false;
                                }
                            }).then(function (data) {
                                self.logger('###paypal_advanced-method#hostedfieldsRender#createOrder# data.result =', data.result);
                                return data.result.id;
                            }).catch(function (error) {
                                console.log('Hubo un problema con la petición :' + error);
                                self._enableCheckout();
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

                            self.logger('submitOptions#co-payment-form, #card-form#submitOptions', submitOptions);

                            hf.submit(submitOptions)
                                .then(function (payload) {
                                    self.logger('hf.submit#payload', payload);
                                    self.orderId = payload.orderId;
                                    self.logger('placeorder', self.placeOrder());

                                    self._enableCheckout();
                                })
                                .catch(function (err) {
                                    self.logger('catch => ', err);

                                    if (err.hasOwnProperty('details')) {
                                        self.messageContainer.addErrorMessage({
                                            message: $t('Transaction cannot be processed, please verify your card information or try another.')
                                        });
                                    }

                                    self._enableCheckout();
                                });
                            return false;
                        });
                    });
                }
            },
            createOrder: function (requestBody) {
                var self = this;

                requestBody.fraudNetCMI = self.sessionIdentifier;
                requestBody.customer_email = quote.guestEmail;

                console.log('createOrder#requestBody', requestBody);

                return storage.post('/paypalcheckout/order', JSON.stringify(requestBody)
                ).done(function (response) {
                    console.log('createOrder#response', response);
                    console.log('createOrder#response.result.id', response.result.id);

                    return response;
                }
                ).fail(function (response) {
                    self._enableCheckout();
                });
            },
            loadFraudnet: function () {
                var self = this;

                self.logger('LoadFraudNet');

                if (self.isVaultingEnable && (self.fraudNetSwi != '') && (self.fraudNetSwi != 'null') && (self.fraudNetSwi)) {
                    self.logger('completeRender#call load fraudNet');
                    paypalFraudNetAdapter.loadFraudNetSdk(function () {
                        self.logger('completeRender#loadFraudNetSdk ', this)
                    });
                }
            },
            loadSdk: function () {
                var self = this;
                self.logger('loadSDK')

                if ((typeof paypal === 'undefined')) {
                    var body = $('body').loader();

                    self.logger('SDK Paypal not loaded');

                    body.loader('show');

                    return paypalSdkAdapter.loadSdk(function () {
                        self.renderButtons();

                        body.loader('hide');

                        return this;
                    });
                }
            },
            renderButtons: function () {
                var self = this;

                var FUNDING_SOURCES = {
                    [paypal.FUNDING.PAYPAL]: 'paypal-button-container',
                };

                if (self.isAcdcEnable) {
                    self.renderHostedFields();
                } else if (self.isBcdcEnable) {
                    FUNDING_SOURCES[paypal.FUNDING.CARD] = 'card-button-container';
                } else {
                    FUNDING_SOURCES[paypal.FUNDING.CARD] = 'card-button-container';
                }

                // Loop over each funding source / payment method
                Object.keys(FUNDING_SOURCES).forEach(function (fundingSource) {
                    console.log('completeRender#fundingSource', fundingSource);

                    self.renderButton(fundingSource, FUNDING_SOURCES[fundingSource])
                });

            },
            validateInstallment: function (submitOptions) {
                var self = this;

                const installment = self.selectedInstallments();

                if (installment && installment.term !== '') {
                    submitOptions.installments = {
                        term: installment.term,
                        interval_duration: installment.interval_duration,
                        intervalDuration: installment.interval_duration
                    };
                    self.logger('validateInstallment#submitOPtion', submitOptions);

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
                        self.logger(submitOptions);
                    }
                }

                return submitOptions;
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
            initializeEvents: function () {
                var self = this;
                var body = $('body').loader();

                self.isVisibleCard(true);

                if (self.customerCards().length > 0) {
                    $('#paypalcheckout').hide();
                    self.loadFraudnet();
                } else {
                    self.loadSdk();
                }

                $('#paypalcp_spb').change(function () {
                    if (this.checked) {
                        self.loadSdk();
                    }
                });

                $('#new-card').change(function () {
                    if (this.checked) {
                        $('#customer-card-token').hide();
                        self.loadSdk();
                        $('#paypalcheckout').show();
                    }
                });

                $('.customer-card-list span.card-delete').click(function () {
                    body.loader('show');

                    var objCard = $(this);
                    var tokenId = objCard.data('id');

                    self.logger('On DELETE ', tokenId);

                    return storage.post('/paypalcheckout/vault/remove/', JSON.stringify({ id: tokenId })
                    ).done(function (response) {

                        $('li#card-' + tokenId).remove();
                        self._enableCheckout();

                        return response;
                    }
                    ).fail(function (response) {
                        console.error('FAIL DELETE | response :', response);
                        self._enableCheckout();

                    });
                });

                $('.customer-card-list > ul > li > input[name=card]').change(function () {
                    var body = $('body').loader();
                    body.loader('show');

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

                    self.createOrder({ 'fraudNetCMI': self.sessionIdentifier }).done(function (response) {
                        console.log('token-submit#createOrder#done#response', response);
                        self.orderId = response.result.id//.orderID;
                        self.placeOrder();
                    }).fail(function (response) {
                        console.error('FAILED paid whit token card', response);
                        $('#submit').prop('disabled', false);
                    });

                    $('#submit').prop('disabled', false);
                });
            },
            _enableCheckout: function () {
                $('#submit').prop('disabled', false);

                var body = $('body').loader();
                body.loader('hide');
            },
            completeRender: function () {
                var self = this;

                $('.ppcp.payment-method').removeClass('_active');
                self.initializeEvents();

                self._enableCheckout();

            },
            logger: function (message, obj) {
                if (window.checkoutConfig.payment.paypalcp.debug) {
                    console.log(message, obj);
                }
            }
        });
    }
);
