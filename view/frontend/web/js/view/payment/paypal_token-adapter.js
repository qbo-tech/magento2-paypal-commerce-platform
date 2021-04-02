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

            console.log('ON getAccessToken#paypalcp', window.checkoutConfig.payment.paypalcp);

            //authorizationBasic = 'Basic ' + btoa(clientId + ':' + clientSecret);
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

            console.log('accessToken', accessToken);

            return 'Bearer ' + accessToken
        },

        generateClientToken: function (customerId) {
            var self = this;
            console.log('ON generateClientToken#customerId', customerId);

            ;

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

            console.log('generateClientToken#response', response)
            console.log('generateClientToken#response.clientToken', response.client_token)
            return response.client_token;
        },

    };
});
