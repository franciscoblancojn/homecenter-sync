<?php

/**
 * Tareas programadas: vaciar la cola y consultar el estado de los feeds.
 */
class HCS_HOOK_CRON
{
    public static function init()
    {
        add_filter('cron_schedules', [self::class, 'schedules']);
        add_action('init', [self::class, 'schedule']);
        add_action(HCS_CRON_QUEUE, [self::class, 'runQueue']);
        add_action(HCS_CRON_FEEDS, [self::class, 'runFeeds']);
        register_deactivation_hook(HCS_FILE, [self::class, 'clear']);
    }

    public static function schedules($schedules)
    {
        if (!isset($schedules[HCS_CRON_INTERVAL])) {
            $schedules[HCS_CRON_INTERVAL] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display' => 'Homecenter Sync: cada 5 minutos',
            ];
        }

        return $schedules;
    }

    public static function schedule()
    {
        if (!wp_next_scheduled(HCS_CRON_QUEUE)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, HCS_CRON_INTERVAL, HCS_CRON_QUEUE);
        }
        if (!wp_next_scheduled(HCS_CRON_FEEDS)) {
            wp_schedule_event(time() + 2 * MINUTE_IN_SECONDS, 'hourly', HCS_CRON_FEEDS);
        }
    }

    public static function runQueue()
    {
        $result = HCS_QUEUE::process();
        if (!empty($result['ok'])) {
            HCS_LOG_SERVICE::add('cron_queue', [
                'sent' => $result['sent'],
                'count' => $result['count'],
                'feed' => $result['feed'],
            ]);
        }
    }

    public static function runFeeds()
    {
        $result = HCS_FEEDS::poll();
        if ($result['finished']) {
            HCS_LOG_SERVICE::add('cron_feeds', $result);
        }
    }

    public static function clear()
    {
        wp_clear_scheduled_hook(HCS_CRON_QUEUE);
        wp_clear_scheduled_hook(HCS_CRON_FEEDS);
    }
}

HCS_HOOK_CRON::init();
