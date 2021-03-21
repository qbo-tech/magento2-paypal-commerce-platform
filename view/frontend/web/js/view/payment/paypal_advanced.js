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
        rendererList.push(
            {
                type: 'paypal_advanced',
                component: 'PayPal_CommercePlatform/js/view/payment/method-renderer/paypaladvanced-method'
            }
        );
        return Component.extend({});
    }
);
