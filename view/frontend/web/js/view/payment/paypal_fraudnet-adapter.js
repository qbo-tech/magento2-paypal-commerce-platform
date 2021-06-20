define([
    'jquery'
], function ($) {
    'use strict';
    return {

        componentName: "paypalFraudNetSDKComponent",

        fraudNetSwi: window.checkoutConfig.payment.paypalcp.fraudNet.sourceWebIdentifier, //Source Website Identifier
        fraudNetSi: window.checkoutConfig.payment.paypalcp.fraudNet.sessionIdentifier,
        fncls: window.checkoutConfig.payment.paypalcp.fraudNet.fncls,
        onLoadedCallback: '',
        customerId: window.checkoutConfig.payment.paypalcp.customer.id,
        isVaultingEnable: window.checkoutConfig.payment.paypalcp.acdc.enable_vaulting,
        isAcdcEnable: window.checkoutConfig.payment.paypalcp.acdc.enable,

        loadFraudNetSdk: function (callbackOnLoaded) {
            var self = this;
            self.logger('#loadFraudNetSdk#', callbackOnLoaded);

            self.onLoadedCallback = callbackOnLoaded;

            var componentUrl = '';

            if (self.isVaultingEnable && (self.fraudNetSwi != '')) {
                var objCallback = {
                    completeCallback: function (resultIndicator, successIndicator) {
                        self.logger('FraudNet completeCallback complete');
                    },
                    errorCallback: function () {
                        self.error('FraudNet errorCallback');
                    },
                    cancelCallback: function () {
                        self.logger('FraudNet cancelled');
                    },
                    onLoadedCallback: function () {
                        self.logger('FraudNet SDK loaded');
                        $(document).ready(function () {
                            return callbackOnLoaded.call();
                        });
                        self.logger('Load FraudNet Component');
                    }
                };

                window.ErrorCallback = $.proxy(objCallback, "errorCallback");
                window.CancelCallback = $.proxy(objCallback, "cancelCallback");
                window.CompletedCallback = $.proxy(objCallback, "completeCallback");

                var reqFraudNet = requirejs.load({
                    contextName: '_',
                    onScriptLoad: $.proxy(objCallback, "onLoadedCallback"),
                }, self.componentName, componentUrl);

                var htmlElement = $('[data-requiremodule="' + self.componentName + '"]')[0];

                htmlElement.setAttribute('data-error', 'window.ErrorCallback');
                htmlElement.setAttribute('data-cancel', 'window.ErrorCallback');
                htmlElement.setAttribute('data-complete', 'window.CompletedCallback');
                htmlElement.setAttribute('type', 'application/json');
                htmlElement.setAttribute('fncls', self.fncls);
                htmlElement.textContent = `{
                        "f": "${self.fraudNetSi}",
                        "s": "${self.fraudNetSwi}"
                    }`
            }
        },

        logger: function (message, obj) {
            if (window.checkoutConfig.payment.paypalcp.debug) {
                console.log(message, obj);
            }
        }
    };
}/* aflorezd@gmail.com */
);
