<?php

namespace CryptAPI;

require_once 'controllers/CryptAPI.php';

class Initialize {
    public function initialize()
    {
        add_action('init', [$this, 'schedule_cron_job']);
    }

    public function schedule_cron_job()
    {
        if (!wp_next_scheduled('cryptapi_cronjob')) {
            wp_schedule_event(time(), 'hourly', 'cryptapi_cronjob');
        }
    }
}
