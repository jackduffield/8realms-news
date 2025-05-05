<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}8realms_news_feeds");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}8realms_news_filters");
delete_transient('8realms_news_feed_items');
delete_option('8realms_news_feeds');