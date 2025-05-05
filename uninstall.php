<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}news_feeds");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}news_filters");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}news_items");
delete_transient('8realms_news_feed_items');