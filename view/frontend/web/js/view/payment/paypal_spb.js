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
                type: 'paypal_basic',
                component: 'PayPal_CommercePlatform/js/view/payment/method-renderer/paypal_spb-method'
            }
        );
        return Component.extend({});
    }
);
