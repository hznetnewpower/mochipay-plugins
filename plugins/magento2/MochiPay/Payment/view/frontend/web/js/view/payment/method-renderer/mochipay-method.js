define(['Magento_Checkout/js/view/payment/default', 'ko'], function (Component, ko) {
    'use strict';
    return Component.extend({
        defaults: {template: 'MochiPay_Payment/payment/mochipay'},
        selectedMethod: ko.observable('USDT_TRC20'),
        redirectAfterPlaceOrder: true,
        getCode: function () { return 'mochipay'; },
        getTitle: function () { return window.checkoutConfig.payment.mochipay.title; },
        getMethods: function () { return window.checkoutConfig.payment.mochipay.methods; },
        getData: function () {
            return {method: this.item.method, additional_data: {mochipay_payment_method: this.selectedMethod()}};
        }
    });
});
