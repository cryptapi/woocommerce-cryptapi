<?php

/*
Plugin Name: CryptAPI Payment Gateway for WooCommerce
Plugin URI: https://cryptapi.io/
Description: Accept cryptocurrency payments on your WooCommerce website
Version: 1.0.0
Requires at least: 4.0
Requires PHP: 5.5
Author: cryptapi
Author URI: https://cryptapi.io/
License: MIT
*/

require_once 'define.php';

function woocommerce_stripe_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'CryptAPI requires WooCommerce to be installed and active. You can download %s here.', 'cryptapi' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}


function include_cryptapi_gateway($methods) {
    $methods[] = 'WC_Gateway_CryptAPI';
    return $methods;
}

function cryptapi_loader() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'woocommerce_missing_wc_notice' );
        return;
    }


    $dirs = [
        PLUGIN_CRYPTAPI_PATH . 'controllers/',
        PLUGIN_CRYPTAPI_PATH . 'utils/',
    ];

    include_dirs($dirs);

    $cryptapi = new WC_Gateway_CryptAPI();
}

add_action('plugins_loaded', 'cryptapi_loader');
add_filter('woocommerce_payment_gateways', 'include_cryptapi_gateway');


function include_dirs($dirs) {

    foreach ($dirs as $dir) {
        $files = scan_dir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            include_file($dir . $f);
        }
    }
}

function include_file($file) {
    if (is_includable($file)) {
        require_once $file;
        return true;
    }

    return false;
}

function scan_dir($dir) {
    if(!is_dir($dir)) return false;
    $file=scandir($dir);
    unset($file[0],$file[1]);

    return $file;
}

function is_includable($file) {
    if (!is_file($file)) return false;
    if (!file_exists($file)) return false;
    if (strtolower(substr($file,-3,3)) != 'php') return false;

    return true;
}