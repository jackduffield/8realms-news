<?php
/**
 * Plugin Name: 8Realms News
 * Description: Aggregates Age of Sigmar news via RSS feeds.
 * Version:     0.1.0
 * Author:      Jack Duffield
 * Text Domain: news
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('REALMS_NEWS_PATH', plugin_dir_path(__FILE__));
define('REALMS_NEWS_URL',  plugin_dir_url(__FILE__));

// Create DB tables on activation
register_activation_hook(__FILE__, 'news_create_db');
function news_create_db() {
    global $wpdb;
    $charset       = $wpdb->get_charset_collate();
    $feeds_table   = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';
    $items_table   = $wpdb->prefix . 'news_items';

    $sql = "
    CREATE TABLE $feeds_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name tinytext NOT NULL,
      url text NOT NULL,
      active tinyint(1) NOT NULL DEFAULT 1,
      type varchar(20) NOT NULL DEFAULT 'article',
      icon_url text,
      PRIMARY KEY  (id)
    ) $charset;
    CREATE TABLE $filters_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      feed_id mediumint(9) NOT NULL,
      keyword varchar(100) NOT NULL,
      PRIMARY KEY  (id)
    ) $charset;
    CREATE TABLE $items_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      feed_id mediumint(9) NOT NULL,
      title text NOT NULL,
      link text NOT NULL,
      date datetime NOT NULL,
      summary text,
      PRIMARY KEY  (id)
    ) $charset;
    ";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Include module files
require_once REALMS_NEWS_PATH . 'news-ingest.php';
require_once REALMS_NEWS_PATH . 'news-management.php';
require_once REALMS_NEWS_PATH . 'news-display.php';

// Activation & deactivation hooks
register_activation_hook(__FILE__,   'news_plugin_activate');
register_deactivation_hook(__FILE__, 'news_plugin_deactivate');

function news_plugin_activate() {
    news_create_db();
    if (! wp_next_scheduled('news_cron')) {
        wp_schedule_event(time(), 'hourly', 'news_cron');
    }
}

function news_plugin_deactivate() {
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

// Register block server-side with render callback
add_action('init', function() {
    if (function_exists('register_block_type')) {
        register_block_type('news/newsfeed', [
            'render_callback' => 'news_render_feed',
        ]);
    }
});

// Code to fetch RSS data and filter based on keywords
// Includes periodic updates to check for new posts

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'news-frontend-styles',
        plugins_url('style.css', __FILE__),
        [],
        '1.0.0'
    );
});