define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/model/messageList'
], function ($, $t, globalMessageList) {
    'use strict';

    return {
        urlAccessToken: '/paypalcheckout/token/index',

        generateIdToken: function () {
            console.info('generate new Id token');
            var self = this;
            var response = $.ajax({
                url: self.urlAccessToken,
                method: 'POST',
                timeout: 0,
                async: false
            }).fail(function() {
                console.log("error creating id token");
                globalMessageList.addErrorMessage({
                    message: $t('It is not possible to use paypal, please try another')
                });
                $('.payment-method').hide();
                $('body').loader();
            });

            var responseJson = response.responseJSON
            console.log('response id token ===> ', responseJson);
            return responseJson.id_token;
        },

    };
});
