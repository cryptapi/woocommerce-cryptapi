<?php

namespace CryptAPI;

require_once 'controllers/CryptAPI.php';

class Initialize {
    public function initialize()
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'schedule_cron_job']);
    }

    public function load_textdomain()
    {
        $plugin_dir = plugin_dir_path(__FILE__);
        $mo_file_path = $plugin_dir . '../languages/cryptapi-payment-gateway-for-woocommerce-' . get_locale() . '.mo';

        if (file_exists($mo_file_path)) {
            load_textdomain('cryptapi', $mo_file_path);
        }
    }

    public function schedule_cron_job()
    {
        if (!wp_next_scheduled('cryptapi_cronjob')) {
            wp_schedule_event(time(), 'hourly', 'cryptapi_cronjob');
        }
    }
}
