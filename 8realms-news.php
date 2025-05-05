<?php
/**
 * Plugin Name: 8Realms News
 * Description: Aggregates Age of Sigmar news via RSS feeds.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: 8realms-news
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('REALMS_NEWS_PATH', plugin_dir_path(__FILE__));
define('REALMS_NEWS_URL',  plugin_dir_url(__FILE__));

// Include module files
require_once REALMS_NEWS_PATH . 'news-ingest.php';
require_once REALMS_NEWS_PATH . 'news-management.php';
require_once REALMS_NEWS_PATH . 'news-display.php';

// Activation & deactivation hooks
register_activation_hook(__FILE__,   '8realms_news_activate');
register_deactivation_hook(__FILE__, '8realms_news_deactivate');

function 8realms_news_activate() {
    8realms_news_create_db();
    if (! wp_next_scheduled('8realms_news_cron')) {
        wp_schedule_event(time(), 'hourly', '8realms_news_cron');
    }
}

function 8realms_news_deactivate() {
    wp_clear_scheduled_hook('8realms_news_cron');
}

// Cron hook to fetch feeds
add_action('8realms_news_cron', '8realms_news_fetch_all_feeds');