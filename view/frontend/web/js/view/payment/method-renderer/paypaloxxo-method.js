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
        'mage/translate',
				'Magento_Ui/js/modal/alert',
    ],
    function (Component, storage, $, paypalSdkAdapter, paypalFraudNetAdapter, selectPaymentMethodAction, checkoutData, quote, ko, totals, $t, alert) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PayPal_CommercePlatform/payment/paypal-oxxo.html'
            },
            componentName: "paypalSdkComponent",
            paypalMethod: 'paypaloxxo',
            orderId: null,
            isOxxoEnable: window.checkoutConfig.payment.paypalcp.oxxo.enable,
            paypalConfigs: window.checkoutConfig.payment.paypalcp,
            fraudNetSwi: window.checkoutConfig.payment.paypalcp.fraudNet.sourceWebIdentifier, //Source Website Identifier
            sessionIdentifier: window.checkoutConfig.payment.paypalcp.fraudNet.sessionIdentifier,
            selectedMethod: null,

            /**
             *
             * @returns {any}
             */
            isOxxoActive: function () {
                var self = this;
                return self.isOxxoEnable && self.grandTotal() < 10000;
            },

          /**
           * Return order total
           * @returns {*}
           */
          grandTotal: function () {
            /** @type {Object} */
            var totals = quote.getTotals()();
            return (totals ? totals : quote)['grand_total'];
          },

            /**
             * Return payment method code
             * @returns {string}
             */
            getCode: function () {
                return 'paypaloxxo';
            },

            /**
             * Validate if payment is selected
             * @returns {null|*|boolean}
             */
            isSelected: function () {
                var self = this;

                if (quote.paymentMethod() && (quote.paymentMethod().method == self.paypalMethod)) {
                    return self.selectedMethod;
                }

                return false;
            },

            /**
             * Return configured title
             * @returns {*}
             */
            getTitleMethodCard: function () {
                return this.paypalConfigs.splitOptions.title_method_oxxo;
            },

            /**
             * Set seleted paymen method
             * @param method
             */
            selectedPayPalMethod: function (method) {
                var self = this;
                var data = this.getData();

                this.selectedMethod = method;
                data.method = this.paypalMethod;

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(this.item.method);
            },

            /**
             * Place paypal oxxo order
             */
            placeOxxoOrder: function () {
                let self = this;
                $('body').trigger('processStart');
                this.createOrder().done(function (response) {
                  try {
                    self.orderId = response.result.id;
                    window.open(response.result.links[1].href,'popup','width=850,height=600');
                    let iframe;
                    iframe = document.createElement('iframe');
                    iframe.src = response.result.links[1].href;
                    iframe.style.display = 'none';
                    document.body.appendChild(iframe);

                    setTimeout(function() {
                      $('body').trigger('processStop');
                      self.placeOrder();
                    }, 3000);
                  } catch (error) {
                    console.log(error);
                    alert({
                      title: $.mage.__('Alert'),
                      modalClass: 'alert',
                      content: $.mage.__('An error occurred during the payment process.'),
                      actions: {
                        always: function () {
                        }
                      }
                    });
                    $('body').trigger('processStop');
                  }

                }).fail(function (response) {
                  console.error('FAILED paid whit token card', response);
                  $('#submit').prop('disabled', false);
                  $('body').trigger('processStop');
                  alert({
                    title: $.mage.__('Alert'),
                    modalClass: 'alert',
                    content: response.responseJSON.reason,
                    actions: {
                      always: function () {
                      }
                    }
                  });
                });
            },

            /**
             * Return order data
             * @returns {{additional_data: {order_id, fraudNetCMI: *}, method: (string|*)}}
             */
            getData: function () {
                let self = this;
                let billing = quote.billingAddress();
                let data = {
                    method: self.paypalMethod,
                    additional_data: {
                        order_id: self.orderId
                    }
                };

                return data;
            },

            /**
             * Create paypal order
             * @param requestBody
             * @returns {*}
             */
            createOrder: function (requestBody) {
                var self = this;
                let billing = quote.billingAddress();
                return storage.post('/paypalcheckout/order',
                    JSON.stringify({
                        'fraudNetCMI': self.sessionIdentifier,
												'customer_email': quote.guestEmail,
                        'payment_method': 'paypaloxxo',
                        'payment_source': {
                            'name': billing.firstname + ' ' + billing.lastname,
                            'email': quote.guestEmail,
                            'country_code': 'MX'
                        }
                    })
                ).done(function (response) {
                        return response;
                }).fail(function (response) { });
            },

            /**
             * Log data in browser
             * @param message
             * @param obj
             */
            logger: function (message, obj) {
                if (window.checkoutConfig.payment.paypalcp.debug) {
                    console.log(message, obj);
                }
            }
        });
    }
);
