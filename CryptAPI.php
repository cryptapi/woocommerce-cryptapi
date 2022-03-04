<?php

/*
Plugin Name: CryptAPI Payment Gateway for WooCommerce
Plugin URI: https://github.com/cryptapi/woocommerce-cryptapi
Description: Accept cryptocurrency payments on your WooCommerce website
Version: 4.1
Requires at least: 4.0
Tested up to: 5.9.1
WC requires at least: 2.4
WC tested up to: 6.2
Requires PHP: 5.5
Author: cryptapi
Author URI: https://cryptapi.io/
License: MIT
*/

require_once 'define.php';

function cryptapi_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'CryptAPI requires WooCommerce to be installed and active. You can download %s here.', 'cryptapi' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}


function cryptapi_include_gateway($methods) {
    $methods[] = 'WC_CryptAPI_Gateway';
    return $methods;
}

function cryptapi_loader() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'cryptapi_missing_wc_notice' );
        return;
    }

    $dirs = [
        CRYPTAPI_PLUGIN_PATH . 'controllers/',
        CRYPTAPI_PLUGIN_PATH . 'utils/',
        CRYPTAPI_PLUGIN_PATH . 'languages/',
    ];

    cryptapi_include_dirs($dirs);

    $mo_file_path = dirname(__FILE__) . '/languages/cryptapi-payment-gateway-for-woocommerce-'. get_locale() . '.mo';
    load_textdomain('cryptapi', $mo_file_path );

    $cryptapi = new WC_CryptAPI_Gateway();
}

add_action('plugins_loaded', 'cryptapi_loader');
add_filter('woocommerce_payment_gateways', 'cryptapi_include_gateway');

function cryptapi_include_dirs($dirs) {

    foreach ($dirs as $dir) {
        $files = cryptapi_scan_dir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            cryptapi_include_file($dir . $f);
        }
    }
}

function cryptapi_include_file($file) {
    if (cryptapi_is_includable($file)) {
        require_once $file;
        return true;
    }

    return false;
}

function cryptapi_scan_dir($dir) {
    if(!is_dir($dir)) return false;
    $file = scandir($dir);
    unset($file[0], $file[1]);

    return $file;
}

function cryptapi_is_includable($file) {
    if (!is_file($file)) return false;
    if (!file_exists($file)) return false;
    if (strtolower(substr($file,-3,3)) != 'php') return false;

    return true;
}

add_filter( 'cron_schedules', function ( $cryptapi_interval ) {
	$cryptapi_interval['cryptapi_interval'] = array(
		'interval' => 60,
		'display'  => esc_html__( 'CryptAPI Interval' ),
	);

	return $cryptapi_interval;
} );

register_activation_hook( __FILE__, 'cryptapi_activation' );

function cryptapi_activation() {
	if (! wp_next_scheduled ( 'cryptapi_cronjob' )) {
		wp_schedule_event( time(), 'cryptapi_interval', 'cryptapi_cronjob' );
	}
}

register_deactivation_hook( __FILE__, 'cryptapi_deactivation' );

function cryptapi_deactivation() {
	wp_clear_scheduled_hook( 'cryptapi_cronjob' );
};