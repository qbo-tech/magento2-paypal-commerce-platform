define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        console.log('paypal_spb-method');

        return Component.extend({
            defaults: {
                template: 'PayPal_CommercePlatform/payment/paypal_spb'
            },
        });
    }
);
