<?php
defined('ABSPATH') || exit;

// Add action for fetching events now
add_action('admin_post_fetch_news_feed_now', 'news_fetch_all_feeds_now');
function news_fetch_all_feeds_now() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }
    news_fetch_all_feeds();
    wp_safe_redirect(admin_url('admin.php?page=8realms-news'));
    exit;
}

function news_validate_feed_url($url, &$items, &$error) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Invalid URL.';
        return false;
    }
    include_once ABSPATH . WPINC . '/feed.php';
    $feed = fetch_feed($url);
    if (is_wp_error($feed)) {
        $error = $feed->get_error_message();
        return false;
    }
    $max = $feed->get_item_quantity(5);
    if (!$max) {
        $error = 'No items found.';
        return false;
    }
    $items = $feed->get_items(0, $max);
    return true;
}

add_action('news_fetch_event', 'news_fetch_all_feeds');
function news_fetch_all_feeds() {
    global $wpdb;
    $feeds_table = $wpdb->prefix . 'news_feeds';
    $items_table = $wpdb->prefix . 'news_items';
    $feeds = $wpdb->get_results("SELECT * FROM $feeds_table WHERE active = 1");

    include_once ABSPATH . WPINC . '/feed.php';
    foreach ($feeds as $feed) {
        $rss = fetch_feed($feed->url);
        if (!is_wp_error($rss)) {
            $max = $rss->get_item_quantity(20);
            $items = $rss->get_items(0, $max);
            foreach ($items as $item) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $items_table WHERE link = %s",
                    $item->get_permalink()
                ));
                if (!$exists) {
                    $wpdb->insert($items_table, [
                        'feed_id'   => $feed->id,
                        'title'     => $item->get_title(),
                        'link'      => $item->get_permalink(),
                        'date'      => $item->get_date('Y-m-d H:i:s'),
                        'summary'   => $item->get_description(),
                        'thumbnail' => $item->get_enclosure() ? $item->get_enclosure()->get_link() : ''
                    ]);
                }
            }
        }
    }
}
