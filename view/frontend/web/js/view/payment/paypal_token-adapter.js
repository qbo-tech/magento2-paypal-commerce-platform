define([
    'jquery'
], function ($) {
    'use strict';

    console.log('paypal_token-adapter');

    return {
        urlAccessToken: window.checkoutConfig.payment.paypalcp.urlAccessToken,
        urlGenerateClientToken: window.checkoutConfig.payment.paypalcp.urlGenerateClientToken,
        authorizationBasic: window.checkoutConfig.payment.paypalcp.authorizationBasic,

        getAccessToken: function () {
            var self = this;

            var accessToken = $.ajax({
                url: self.urlAccessToken,
                method: 'POST',
                timeout: 0,
                async: false,
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": self.authorizationBasic
                },
                data: {
                    grant_type: 'client_credentials',
                    response_type: 'token'
                }
            }).responseJSON.access_token

            return 'Bearer ' + accessToken
        },

        generateClientToken: function (customerId) {
            var self = this;

            var data = JSON.stringify({customer_id: customerId});

            var response = $.ajax({
                url: self.urlGenerateClientToken,
                method: 'POST',
                timeout: 0,
                async: false,
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": self.getAccessToken()
                },
                data: data
            }).responseJSON

            return response.client_token;
        },

    };
});
