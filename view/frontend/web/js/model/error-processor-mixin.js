define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function (wrapper, quote) {
    'use strict';

    return function (target) {
        target.redirectTo = wrapper.wrap(target.redirectTo, function (originalRedirectTo, redirectUrl) {
            var paymentMethod = quote.paymentMethod();
            if (paymentMethod && ['paypalcp', 'paypalspb', 'paypaloxxo'].indexOf(paymentMethod.method) !== -1) {
                console.log('[PayPal Commerce] Redirección de error evitada para el método de pago: ' + paymentMethod.method);
                return;
            }
            return originalRedirectTo(redirectUrl);
        });

        return target;
    };
});
