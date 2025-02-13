define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/model/messageList'
], function ($, $t, globalMessageList) {
    'use strict';

    return {
        urlAccessToken: '/paypalcheckout/token/index',

        generateClientToken: function () {
            console.info('new generate client token');
            var self = this;
            var response = $.ajax({
                url: self.urlAccessToken,
                method: 'POST',
                timeout: 0,
                async: false
            }).fail(function() {
                console.log("error creating client token");
                globalMessageList.addErrorMessage({
                    message: $t('It is not possible to use paypal, please try another')
                });
                $('.payment-method').hide();
                $('body').loader();
            });
            var responseJson = response.responseJSON
            return responseJson.token;
        },

    };
});
