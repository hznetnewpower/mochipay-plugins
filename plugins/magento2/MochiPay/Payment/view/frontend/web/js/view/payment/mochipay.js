define(['uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'], function (Component, rendererList) {
    'use strict';
    rendererList.push({type: 'mochipay', component: 'MochiPay_Payment/js/view/payment/method-renderer/mochipay-method'});
    return Component.extend({});
});
