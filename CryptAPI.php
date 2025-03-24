<?php
/*
Plugin Name: CryptAPI Payment Gateway for WooCommerce
Plugin URI: https://github.com/cryptapi/woocommerce-cryptapi
Description: Accept cryptocurrency payments on your WooCommerce website
Version: 5.1.1
Requires at least: 5.8
Tested up to: 6.7.2
WC requires at least: 5.8
WC tested up to: 9.6.2
Requires PHP: 7.2
Author: cryptapi
Author URI: https://cryptapi.io/
License: MIT
*/
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('CRYPTAPI_PLUGIN_VERSION', '5.1.1');
define('CRYPTAPI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CRYPTAPI_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'CryptAPI\\';
    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>' . sprintf(esc_html__('CryptAPI requires WooCommerce to be installed and active. You can download %s here.', 'cryptapi'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
        });
        return;
    }

    if (!extension_loaded('bcmath')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>' . sprintf(esc_html__('CryptAPI requires PHP\'s BCMath extension. You can know more about it %s.', 'cryptapi'), '<a href="https://www.php.net/manual/en/book.bc.php" target="_blank">here</a>') . '</strong></p></div>';
        });
        return;
    }

    $register = new \CryptAPI\Register();
    $register->register();

    $initialize = new \CryptAPI\Initialize();
    $initialize->initialize();

    $cryptapi = new \CryptAPI\Controllers\WC_CryptAPI_Gateway();
});


add_filter('cron_schedules', function ($cryptapi_interval) {
    $cryptapi_interval['cryptapi_interval'] = array(
        'interval' => 60,
        'display' => esc_html__('CryptAPI Interval'),
    );

    return $cryptapi_interval;
});

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('cryptapi_cronjob')) {
        wp_schedule_event(time(), 'cryptapi_interval', 'cryptapi_cronjob');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('cryptapi_cronjob');
});

use Automattic\WooCommerce\Utilities\FeaturesUtil;
// Declare compatibility with WooCommerce features
add_action('before_woocommerce_init', function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Register minimum endpoint to be used in the blocks

add_action('rest_api_init', function () {
    register_rest_route('cryptapi/v1', '/get-minimum', array(
        'methods' => 'POST',
        'callback' => 'cryptapi_get_minimum',
        'permission_callback' => 'cryptapi_verify_nonce',
    ));
    register_rest_route('cryptapi/v1', '/update-coin', array(
        'methods' => 'POST',
        'callback' => 'cryptapi_update_coin',
        'permission_callback' => 'cryptapi_verify_nonce',
    ));
});

function cryptapi_verify_nonce(WP_REST_Request $request) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== home_url()) {
        return false;
    }

    $nonce = $request->get_header('X-WP-Nonce');
    return wp_verify_nonce($nonce, 'wp_rest');
}

function cryptapi_get_minimum(WP_REST_Request $request) {
    $coin = sanitize_text_field($request->get_param('coin'));
    $fiat = sanitize_text_field($request->get_param('fiat'));
    $value = sanitize_text_field($request->get_param('value'));

    if (!$coin) {
        return new WP_REST_Response(['status' => 'error'], 400);
    }

    try {
        $convert = (float) \CryptAPI\Utils\Api::get_conversion($fiat, $coin, (string) $value, false);
        $minimum = (float) \CryptAPI\Utils\Api::get_info($coin)->minimum_transaction_coin;

        if ($convert > $minimum) {
            return new WP_REST_Response(['status' => 'success'], 200);
        } else {
            return new WP_REST_Response(['status' => 'error'], 200);
        }
    } catch (Exception $e) {
        return new WP_REST_Response(['status' => 'error'], 500);
    }
}

function cryptapi_update_coin(WP_REST_Request $request) {
    $coin = sanitize_text_field($request->get_param('coin'));
    $selected = $request->get_param('selected', false);

    // Ensure WooCommerce session is available
    if (!WC()->session) {
        $session_handler = new \WC_Session_Handler();
        $session_handler->init();
        WC()->session = $session_handler;
    }

    if (!$selected) {
        WC()->session->set('cryptapi_coin', 'none');
        WC()->session->set('chosen_payment_method', '');
        return new WP_REST_Response(['success' => true, 'coin' => $coin], 200);
    }

    if (!$coin) {
        return new WP_REST_Response(['error' => 'Coin not specified'], 400);
    }

    // Set the session value
    WC()->session->set('cryptapi_coin', $coin);
    WC()->session->set('chosen_payment_method', 'cryptapi');

    return new WP_REST_Response(['success' => true, 'coin' => $coin], 200);
}
