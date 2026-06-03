var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/model/error-processor': {
                'PayPal_CommercePlatform/js/model/error-processor-mixin': true
            }
        }
    },
    map: {
        '*': {
            paypalFraudNetAdapter: 'PayPal_CommercePlatform/js/view/payment/paypal_fraudnet-adapter',
        }
    },
};

