(function () {
    'use strict';

    var registry = window.wc && window.wc.wcBlocksRegistry;
    var settingsApi = window.wc && window.wc.wcSettings;
    var element = window.wp && window.wp.element;
    var htmlEntities = window.wp && window.wp.htmlEntities;

    if (!registry || !settingsApi || !element || !htmlEntities) {
        return;
    }

    var settings = settingsApi.getSetting('mochipay_data', {});
    var title = htmlEntities.decodeEntities(settings.title || 'Cryptocurrency');
    var description = htmlEntities.decodeEntities(
        settings.description || 'Pay securely with cryptocurrency through MochiPay.'
    );
    var paymentMethods = Array.isArray(settings.paymentMethods) ? settings.paymentMethods : [];

    function methodValue(item) {
        return item && item.value ? String(item.value) : '';
    }

    function methodLabel(item) {
        return htmlEntities.decodeEntities(item && item.label ? String(item.label) : methodValue(item));
    }

    function Label() {
        return element.createElement('span', null, title);
    }

    function Content(props) {
        var firstMethod = paymentMethods.length ? methodValue(paymentMethods[0]) : '';
        var selectedState = element.useState(firstMethod);
        var selected = selectedState[0];
        var setSelected = selectedState[1];
        var eventRegistration = props && props.eventRegistration ? props.eventRegistration : null;
        var emitResponse = props && props.emitResponse ? props.emitResponse : null;
        var registerPayment = eventRegistration
            ? (eventRegistration.onPaymentSetup || eventRegistration.onPaymentProcessing)
            : null;

        element.useEffect(function () {
            if (!registerPayment || !emitResponse) {
                return undefined;
            }

            var unsubscribe = registerPayment(function () {
                if (!selected) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please select a MochiPay payment currency and network.'
                    };
                }

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            mochipay_payment_method: selected
                        }
                    }
                };
            });

            return function () {
                if (typeof unsubscribe === 'function') {
                    unsubscribe();
                }
            };
        }, [registerPayment, emitResponse, selected]);

        var options = paymentMethods.map(function (item) {
            var value = methodValue(item);
            return element.createElement('option', { key: value, value: value }, methodLabel(item));
        });

        return element.createElement(
            'div',
            { className: 'mochipay-blocks-description' },
            element.createElement('p', null, description),
            element.createElement(
                'label',
                {
                    htmlFor: 'mochipay-payment-method',
                    style: { display: 'block', fontWeight: '600', marginBottom: '6px' }
                },
                'Payment currency and network'
            ),
            element.createElement(
                'select',
                {
                    id: 'mochipay-payment-method',
                    value: selected,
                    onChange: function (event) { setSelected(event.target.value); },
                    style: { width: '100%', minHeight: '42px' },
                    required: true
                },
                options
            )
        );
    }

    function Preview() {
        var labels = paymentMethods.map(methodLabel).join(', ');
        return element.createElement(
            'div',
            { className: 'mochipay-blocks-description' },
            element.createElement('p', null, description),
            labels ? element.createElement('small', null, 'Available: ' + labels) : null
        );
    }

    registry.registerPaymentMethod({
        name: 'mochipay',
        label: element.createElement(Label, null),
        content: element.createElement(Content, null),
        edit: element.createElement(Preview, null),
        canMakePayment: function () { return paymentMethods.length > 0; },
        ariaLabel: title,
        placeOrderButtonLabel: settings.checkoutMode === 'onsite' ? 'Place order & pay' : 'Continue to MochiPay',
        supports: {
            features: settings.supports || ['products']
        }
    });
}());
