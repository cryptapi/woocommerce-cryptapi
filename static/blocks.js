(() => {
    const settings = wc.wcSettings.getSetting('cryptapi_data', {});
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, useState, useEffect } = window.wp.element;
    const { decodeEntities } = window.wp.htmlEntities;
    const { useSelect, useDispatch } = window.wp.data;
    const supports = { features: settings.supports };
    const name = decodeEntities(settings.name || '');
    const label = decodeEntities(settings.label || '');
    const button = decodeEntities(settings.button || '');
    const apiFetch = window.wp.apiFetch;

    const updateCoin = async (coin, selected = false) => {
        try {
            await apiFetch({
                path: '/cryptapi/v1/update-coin',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': cryptapiData.nonce,
                },
                data: {
                    coin: coin,
                    selected: selected
                },
            })
        } catch (e) {
            //
        }
    }

    const Content = ({ eventRegistration, emitResponse }) => {
        const { invalidateResolutionForStore} = useDispatch('wc/store/cart');

        useEffect(() => {
            updateCoin('none', true).then(() => {
                invalidateResolutionForStore('getCart');
            })

            return () => {
                updateCoin('none', false).then(() => {
                    invalidateResolutionForStore('getCart');
                })
            };
        }, [])

        const { onPaymentSetup } = eventRegistration;
        const [selectedCoin, setSelectedCoin] = useState('none');

        useEffect(() => {
            if (selectedCoin) {
                updateCoin(selectedCoin, true).then(() => {
                    invalidateResolutionForStore('getCart');
                }).catch((error) => {
                    console.error('Failed to update session:', error);
                })
            }
        }, [selectedCoin]);

        const cartTotals = useSelect((select) => {
            const store = select('wc/store/cart');
            return store ? store.getCartTotals() : null;
        }, []);

        useEffect(() => {
            const unsubscribeSetup = onPaymentSetup(async () => {
                if (selectedCoin !== 'none') {
                    try {
                        const data = await apiFetch({
                            path: '/cryptapi/v1/get-minimum',
                            method: 'POST',
                            headers: {
                                'X-WP-Nonce': cryptapiData.nonce,
                            },
                            data: {
                                coin: selectedCoin,
                                fiat: wcSettings.currency.code.toLowerCase(),
                                value: cartTotals.total_price / Math.pow(10, cartTotals.currency_minor_unit),
                            },
                        });

                        if (data.status === 'error') {
                            return {
                                type: emitResponse.responseTypes.ERROR,
                                message: settings.translations.cart_must_be_higher,
                            };
                        }

                        return {
                            type: emitResponse.responseTypes.SUCCESS,
                            meta: {
                                paymentMethodData: {
                                    cryptapi_coin: selectedCoin,
                                },
                            },
                        };
                    } catch (error) {
                        return {
                            type: emitResponse.responseTypes.ERROR,
                            message: settings.translations.error_ocurred,
                        };
                    }
                }

                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: settings.translations.please_select_cryptocurrency,
                };
            });

            return () => {
                unsubscribeSetup();
            };
        }, [selectedCoin, emitResponse.responseTypes, cartTotals]);

        const options = settings.coins.map((val) => {
            const img = val.logo;
            const crypto_name = val.name;
            return createElement(
                'option',
                {
                    value: val.ticker,
                    'data-image': img,
                    key: val.ticker,
                },
                crypto_name
            );
        });

        return createElement('div', { className: 'form-row form-row-wide cryptapi-payment-selection' },
            createElement('p', null, settings.description),
            createElement('ul', { style: { listStyle: 'none' } },
                createElement('li', null,
                    createElement('select', {
                            name: 'cryptapi_coin',
                            id: 'cryptapi_coin',
                            className: 'input-control',
                            style: { display: 'block', marginTop: '10px' },
                            onChange: (e) => setSelectedCoin(e.target.value),
                        },
                        createElement('option', { value: 'none' }, settings.translations.please_select_cryptocurrency),
                        ...options
                    )
                )
            )
        );

        return createElement('div', { className: 'form-row form-row-wide' },
            createElement('p', null, settings.description)
        );
    };

    const ReactElement = (type, props = {}, ...childs) => {
        return createElement(type, props, ...childs);
    };

    const Label = ({ components }) => {
        const { PaymentMethodLabel, PaymentMethodIcons } = components;
        const { invalidateResolutionForStore } = useDispatch('wc/store/cart');

        useEffect(() => {
            updateCoin('none', false).then(() => {
                invalidateResolutionForStore('getCart');
            })
        }, [])

        const labelComp = ReactElement(PaymentMethodLabel, {
            text: label,
        });

        const iconsComp = ReactElement(PaymentMethodIcons, {
            icons: settings.icons,
        });

        return React.createElement(
            'div',
            {
                className: name + '-payment-gateway cryptapi-payment-selection',
            },
            iconsComp,
            labelComp
        );
    };

    registerPaymentMethod({
        name,
        supports,
        ariaLabel: label,
        paymentMethodId: name,
        canMakePayment: () => true,
        label: ReactElement(Label),
        edit: createElement(Content),
        content: createElement(Content),
        placeOrderButtonLabel: button,
    });
})();
