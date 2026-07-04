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
        $scheduled = wp_get_schedule('cryptapi_cronjob');

        // Ensure the cron runs on the frequent CryptAPI interval so the order
        // cancellation timeout is honoured promptly. Also migrates installs
        // that ended up on a different schedule (e.g. an 'hourly' build) back
        // to it, and (re)schedules if it was cleared.
        if ($scheduled !== 'cryptapi_interval') {
            wp_clear_scheduled_hook('cryptapi_cronjob');
            wp_schedule_event(time(), 'cryptapi_interval', 'cryptapi_cronjob');
        }
    }
}
