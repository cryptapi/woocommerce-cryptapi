<?php
/*
Plugin Name: CryptAPI Payment Gateway for WooCommerce
Plugin URI: https://github.com/cryptapi/woocommerce-cryptapi
Description: Accept cryptocurrency payments on your WooCommerce website
Version: 4.8.5
Requires at least: 5.8
Tested up to: 6.5.3
WC requires at least: 5.8
WC tested up to: 9.0.1
Requires PHP: 7.2
Author: cryptapi
Author URI: https://cryptapi.io/
License: MIT
*/

require_once 'define.php';

function cryptapi_missing_wc_notice()
{
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('CryptAPI requires WooCommerce to be installed and active. You can download %s here.', 'cryptapi'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

function cryptapi_missing_bcmath()
{
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('CryptAPI requires PHP\'s BCMath extension. You can know more about it %s.', 'cryptapi'), '<a href="https://www.php.net/manual/en/book.bc.php" target="_blank">here</a>') . '</strong></p></div>';
}


function cryptapi_include_gateway($methods)
{
    $methods[] = 'WC_CryptAPI_Gateway';
    return $methods;
}

function cryptapi_loader()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'cryptapi_missing_wc_notice');
        return;
    }

    if (!extension_loaded('bcmath')) {
        add_action('admin_notices', 'cryptapi_missing_bcmath');
        return;
    }

    $dirs = [
        CRYPTAPI_PLUGIN_PATH . 'controllers/',
        CRYPTAPI_PLUGIN_PATH . 'utils/',
        CRYPTAPI_PLUGIN_PATH . 'languages/',
    ];

    cryptapi_include_dirs($dirs);

    $language_dir = CRYPTAPI_PLUGIN_PATH . 'languages/';
    $mo_file_path = $language_dir . 'cryptapi-payment-gateway-for-woocommerce-' . get_locale() . '.mo';

    if (file_exists($mo_file_path)) {
        load_textdomain('cryptapi', $mo_file_path, get_locale());
    } else {
        error_log('Translation file not found: ' . $mo_file_path);
    }

    $cryptapi = new WC_CryptAPI_Gateway();
}

add_action('plugins_loaded', 'cryptapi_loader');
add_filter('woocommerce_payment_gateways', 'cryptapi_include_gateway');

function cryptapi_include_dirs($dirs)
{

    foreach ($dirs as $dir) {
        $files = cryptapi_scan_dir($dir);
        if ($files === false) continue;

        foreach ($files as $f) {
            cryptapi_include_file($dir . $f);
        }
    }
}

function cryptapi_include_file($file)
{
    if (cryptapi_is_includable($file)) {
        require_once $file;
        return true;
    }

    return false;
}

function cryptapi_scan_dir($dir)
{
    if (!is_dir($dir)) return false;
    $file = scandir($dir);
    unset($file[0], $file[1]);

    return $file;
}

function cryptapi_is_includable($file)
{
    if (!is_file($file)) return false;
    if (!file_exists($file)) return false;
    if (strtolower(substr($file, -3, 3)) != 'php') return false;

    return true;
}

add_filter('cron_schedules', function ($cryptapi_interval) {
    $cryptapi_interval['cryptapi_interval'] = array(
        'interval' => 60,
        'display' => esc_html__('CryptAPI Interval'),
    );

    return $cryptapi_interval;
});

register_activation_hook(__FILE__, 'cryptapi_activation');

function cryptapi_activation()
{
    if (!wp_next_scheduled('cryptapi_cronjob')) {
        wp_schedule_event(time(), 'cryptapi_interval', 'cryptapi_cronjob');
    }
}

register_deactivation_hook(__FILE__, 'cryptapi_deactivation');

function cryptapi_deactivation()
{
    wp_clear_scheduled_hook('cryptapi_cronjob');
}

if (!wp_next_scheduled('cryptapi_cronjob')) {
    wp_schedule_event(time(), 'cryptapi_interval', 'cryptapi_cronjob');
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
