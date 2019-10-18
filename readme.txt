=== CryptAPI Payment Gateway for WooCommerce ===
Contributors: cryptapi
Tags: crypto payments, woocommerce, payment gateway, crypto, payment, pay with crypto, payment request, bitcoin, ethereum, monero, iota, litecoin, bitcoin cash,
Requires at least: 4.0
Tested up to: 5.2
Stable tag: 1.0.2
Requires PHP: 5.5
WC requires at least: 2.4
WC tested up to: 3.7
License: MIT

Accept cryptocurrency payments on your WooCommerce website


== Description ==

Accept payments in Bitcoin, Bitcoin Cash, Litecoin, Ethereum, Monero and IOTA directly to your crypto wallet, without any sign-ups or lengthy processes.
All you need is to provide your crypto address.

= Allow users to pay with crypto directly on your store =

The CryptAPI plugin extends WooCommerce, allowing you to get paid in crypto directly on your store, with a simple setup and no sign-ups required.

Currently accepted cryptocurrencies are:

* (BTC) Bitcoin
* (BCH) Bitcoin Cash
* (LTC) Litecoin
* (ETH) Ethereum
* (XMR) Monero
* (IOTA) IOTA

CryptAPI will attempt to automatically convert the value you set on your store to the cryptocurrency your customer chose.
Exchange rates are fetched hourly from CoinMarketCap.

Supported currencies for automatic exchange rates are:

* (USD) United States Dollar
* (EUR) Euro
* (GBP) Great Britain Pound
* (JPY) Japanese Yen
* (CNY) Chinese Yuan
* (INR) Indian Rupee
* (CAD) Canadian Dollar
* (HKD) Hong Kong Dollar
* (BRL) Brazilian Real
* (DKK) Danish Krone
* (MXN) Mexican Peso
* (AED) United Arab Emirates Dirham

If your WooCommerce's currency is none of the above, the exchange rates will default to USD.
If you're using WooCommerce in a different currency not listed here and need support, please [contact us](https://cryptapi.io) via our live chat.

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

== Frequently Asked Questions ==

= Do I need an API key? =

No. You just need to insert your crypto address of the cryptocurrencies you wish to accept. Whenever a customer pays, the money will be automatically and instantly forwarded to your address.

= How long do payments take before they're confirmed? =

This depends on the cryptocurrency you're using. Bitcoin usually takes up to 11 minutes, Ethereum usually takes less than a minute.

= Is there a minimum for a payment? =

Yes, the minimums change according to the chosen cryptocurrency and can be checked [here](https://cryptapi.io/get_started/#fees).
If the WooCommerce order total is below the chosen cryptocurrency's minimum, an error is raised to the user.

= Where can I find more documentation on your service? =

You can find more documentation about our service on our [get started](https://cryptapi.io/get_started) page, our [technical documentation](https://cryptapi.io/docs/) page or our [resources](https://cryptapi.io/resources/) page.
If there's anything else you need that is not covered on those pages, please get in touch with us, we're here to help you!

= Where can I get support? =

The easiest and fastest way is via our live chat on our [website](https://cryptapi.io) or via our [contact form](https://cryptapi.io/contact/).

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Normal checkout with CryptAPI.
3. Standard payment page with QR-Code.
4. Awaiting payment confirmation
5. Payment confirmed

== Changelog ==

= 1.0 =
* Initial release.

== Upgrade Notice ==
* Initial release.