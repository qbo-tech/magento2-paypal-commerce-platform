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

    };
});
