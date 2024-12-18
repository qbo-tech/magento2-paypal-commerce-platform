define([
    'jquery'
], function ($) {
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
            }).responseJSON

            return response.token;
        },

        generateIdToken: function () {
            console.info('generate new Id token');
            var self = this;
            var response = $.ajax({
                url: self.urlAccessToken,
                method: 'POST',
                timeout: 0,
                async: false
            }).responseJSON

            console.log('response id token ===> ', response);
            return response.id_token;
        },

    };
});
