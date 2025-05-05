<?php
defined('ABSPATH') || exit;

/**
 * Create DB tables for RSS feeds and filters
 */
function news_create_db() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $feeds_table   = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';

    $sql = "
    CREATE TABLE $feeds_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name tinytext NOT NULL,
      url  text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset;
    CREATE TABLE $filters_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      feed_id mediumint(9) NOT NULL,
      keyword varchar(100) NOT NULL,
      PRIMARY KEY  (id)
    ) $charset;
    ";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Fetch and cache items for all registered feeds
 */
function news_fetch_all_feeds() {
    $feeds = get_option('news_feeds', []);
    $all_items = [];
    foreach ($feeds as $feed) {
        $rss = fetch_feed($feed['url']);
        if (! is_wp_error($rss)) {
            $maxitems = $rss->get_item_quantity(20);
            $items = $rss->get_items(0, $maxitems);
            // Filter by keywords
            $filters = get_option("news_filters_{$feed['id']}", []);
            foreach ($items as $item) {
                $content = $item->get_title() . ' ' . $item->get_description();
                $match = empty($filters) || array_filter($filters, fn($kw) => stripos($content, $kw) !== false);
                if ($match) {
                    $all_items[] = [
                        'title'       => $item->get_title(),
                        'link'        => $item->get_link(),
                        'date'        => $item->get_date('U'),
                        'summary'     => $item->get_description(),
                        'thumbnail'   => $item->get_enclosure() ? $item->get_enclosure()->get_link() : '',
                        'source_name' => $feed['name'],
                    ];
                }
            }
        }
    }
    // Cache for front-end
    set_transient('news_feed_items', $all_items, HOUR_IN_SECONDS);
}