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
                type: 'paypaloxxo',
                component: 'PayPal_CommercePlatform/js/view/payment/method-renderer/paypaloxxo-method'
            }
        );
        return Component.extend({});
    }
);
