=== CryptAPI Payment Gateway for WooCommerce ===
Contributors: cryptapi
Tags: crypto payments, woocommerce, payment gateway, crypto, payment, pay with crypto, payment request, bitcoin, bnb, usdt, ethereum, monero, litecoin, bitcoin cash, shib, doge
Requires at least: 4.4
Tested up to: 5.9
Stable tag: 4.0.2
Requires PHP: 5.5
WC requires at least: 2.4
WC tested up to: 6.2
License: MIT

Accept cryptocurrency payments on your WooCommerce website


== Description ==

Accept payments in Bitcoin, Ethereum, Bitcoin Cash, Litecoin, Monero, BNB, USDT, SHIB, DOGE and many more directly to your crypto wallet, without any sign-ups or lengthy processes.
All you need is to provide your crypto address.

= Allow users to pay with crypto directly on your store =

The CryptAPI plugin extends WooCommerce, allowing you to get paid in crypto directly on your store, with a simple setup and no sign-ups required.

= Accepted cryptocurrencies & tokens include: =

* (BTC) Bitcoin
* (ETH) Ethereum
* (BCH) Bitcoin Cash
* (LTC) Litecoin
* (XMR) Monero
* (TRX) Tron
* (BNB) Binance Coin
* (USDT) USDT
* (SHIB) Shiba Inu
* (DOGE) Dogecoin


among many others, for a full list of the supported cryptocurrencies and tokens, check [this page](https://cryptapi.io/pricing/).

= Auto-value conversion =

CryptAPI will attempt to automatically convert the value you set on your store to the cryptocurrency your customer chose.

Exchange rates are fetched every 5 minutes from CoinGecko.

Supported currencies for automatic exchange rates are:

* (XAF) CFA Franc
* (RON) Romanian Leu
* (BGN) Bulgarian Lev
* (HUF) Hungarian Forint
* (CZK) Czech Koruna
* (PHP) Philippine Peso
* (PLN) Poland Zloti
* (UGX) Uganda Shillings
* (MXN) Mexican Peso
* (INR) Indian Rupee
* (HKD) Hong Kong Dollar
* (CNY) Chinese Yuan
* (BRL) Brazilian Real
* (DKK) Danish Krone
* (AED) UAE Dirham
* (JPY) Japanese Yen
* (CAD) Canadian Dollar
* (GBP) GB Pound
* (EUR) Euro
* (USD) US Dollar

If your WooCommerce's currency is none of the above, the exchange rates will default to USD.
If you're using WooCommerce in a different currency not listed here and need support, please [contact us](https://cryptapi.io) via our live chat.

**Note:** CryptAPI will not exchange your crypto for FIAT or other crypto, just convert the value

= Why choose CryptAPI? =

CryptAPI has no setup fees, no monthly fees, no hidden costs, and you don't even need to sign-up!
Simply set your crypto addresses and you're ready to go. As soon as your customers pay we forward your earnings directly to your own wallet.

CryptAPI has a low 1% fee on the transactions processed. No hidden costs.
For more info on our fees [click here](https://cryptapi.io/get_started/#fees)

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'CryptAPI Payment Gateway for WooCommerce'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `woocommerce-cryptapi.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `woocommerce-cryptapi.zip`
2. Extract the `woocommerce-cryptapi` directory to your computer
3. Upload the `woocommerce-cryptapi` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Configuration ==

1. Go to WooCommerce settings
2. Select the "Payments" tab
3. Activate the payment method (if inactive)
4. Set the name you wish to show your users on Checkout (for example: "Cryptocurrency")
5. Fill the payment method's description (for example: "Pay with cryptocurrency")
6. Select which cryptocurrencies you wish to accept (control + click to select many)
7. Input your addresses to the cryptocurrencies you selected. This is where your funds will be sent to, so make sure the addresses are correct.
8. Click "Save Changes"
9. All done!

== Frequently Asked Questions ==

= Do I need an API key? =

No. You just need to insert your crypto address of the cryptocurrencies you wish to accept. Whenever a customer pays, the money will be automatically and instantly forwarded to your address.

= How long do payments take before they're confirmed? =

This depends on the cryptocurrency you're using. Bitcoin usually takes up to 11 minutes, Ethereum usually takes less than a minute.

= Is there a minimum for a payment? =

Yes, the minimums change according to the chosen cryptocurrency and can be checked [here](https://cryptapi.io/pricing/).
If the WooCommerce order total is below the chosen cryptocurrency's minimum, an error is raised to the user.

= Where can I find more documentation on your service? =

You can find more documentation about our service on our [get started](https://cryptapi.io/get_started) page, our [technical documentation](https://cryptapi.io/docs/) page or our [resources](https://cryptapi.io/resources/) page.
If there's anything else you need that is not covered on those pages, please get in touch with us, we're here to help you!

= Where can I get support? =

The easiest and fastest way is via our live chat on our [website](https://cryptapi.io), via our [contact form](https://cryptapi.io/contact/), via [discord](https://discord.gg/pQaJ32SGrR) or via [telegram](https://t.me/cryptapi_support).

== Screenshots ==

1. The settings panel used to configure the gateway
2. Set your crypto addresses (part 1)
3. Set your crypto addresses (part 2)
4. Example of payment using Litecoins
5. The QR code can be set to provide only the address or the address with the amount: the user can choose with one click
6. Once the payment is received, the system will wait for network confirmations
7. The payment is confirmed!

== Changelog ==

= 1.0 =
* Initial release.

= 2.0 =
* New coins
* Updated codebase
* New API URL

= 3.0 =
* UI Improvements
* Minor Bug Fixes

= 3.0.2 =
* New setting to show QR Code by default
* UI Improvements
* Minor Bug Fixes

= 3.1 =
* Add support for WooCommerce Subscriptions plugin
* Add new feature to refresh values based on store owner preferences
* Add new feature to cancel orders if they take more than selected time to pay

= 3.2 =
* UI Improvements
* Minor Bug Fixes

= 3.2.1 =
* Add translations for multiple languages

= 4.0 =
* New settings and color schemes to fit dark mode
* New settings to add CryptAPI's services fees to the checkout
* New settings to add blockchain fees to the checkout
* Upgrade the settings
* UI Improvements
* Minor fixes

= 4.0.1 =
* Minor fixes

= 4.0.2 =
* Minor fixes

== Upgrade Notice ==
* No breaking changes