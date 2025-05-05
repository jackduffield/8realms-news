<?php
/**
 * Plugin Name: 8Realms News
 * Description: Aggregates Age of Sigmar news via RSS feeds.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: news
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
register_activation_hook(__FILE__,   'news_activate');
register_deactivation_hook(__FILE__, 'news_deactivate');

function news_activate() {
    news_create_db();
    if (! wp_next_scheduled('news_cron')) {
        wp_schedule_event(time(), 'hourly', 'news_cron');
    }
}

function news_deactivate() {
    wp_clear_scheduled_hook('news_cron');
}

// Cron hook to fetch feeds
add_action('news_cron', 'news_fetch_all_feeds');

add_action('enqueue_block_editor_assets', function() {
    wp_enqueue_script(
        'news-blocks',
        plugins_url('blocks.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-block-editor'],
        '1.0.0',
        true
    );
    wp_enqueue_style(
        'news-editor-styles',
        plugins_url('editor.css', __FILE__),
        [],
        '1.0.0'
    );
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'news-frontend-styles',
        plugins_url('style.css', __FILE__),
        [],
        '1.0.0'
    );
});