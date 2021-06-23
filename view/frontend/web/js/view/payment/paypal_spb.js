define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        console.log('paypal_spb');

        rendererList.push(
            {
                type: 'paypalspb',
                component: 'PayPal_CommercePlatform/js/view/payment/method-renderer/paypal_spb-method'
            }
        );
        return Component.extend({});
    }
);
