<?php
defined('ABSPATH') || exit;

// Add action for fetching events now
add_action('admin_post_fetch_news_feed_now', 'news_fetch_all_feeds_now');
function news_fetch_all_feeds_now() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }
    $result = news_fetch_all_feeds(true); // pass true to get stats
    $url = admin_url('admin.php?page=8realms-news') . '&news_fetch_now=1';
    if ($result && is_array($result)) {
        $url .= '&added=' . intval($result['added']) . '&exists=' . intval($result['exists']) . '&total=' . intval($result['total']);
    }
    wp_safe_redirect($url);
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
function news_fetch_all_feeds($return_stats = false) {
    global $wpdb;
    $feeds_table = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';
    $items_table = $wpdb->prefix . 'news_items';
    $feeds = $wpdb->get_results("SELECT * FROM $feeds_table WHERE active = 1");
    $added = 0;
    $exists = 0;
    include_once ABSPATH . WPINC . '/feed.php';
    foreach ($feeds as $feed) {
        $rss = fetch_feed($feed->url);
        if (is_wp_error($rss)) {
            error_log('Feed error: ' . $feed->url . ' - ' . $rss->get_error_message());
            continue; // skip this feed if error
        }
        $max = $rss->get_item_quantity(20);
        $items = $rss->get_items(0, $max);
        // Get filters for this feed
        $filters = $wpdb->get_col($wpdb->prepare("SELECT keyword FROM $filters_table WHERE feed_id = %d", $feed->id));
        $filters = array_filter(array_map('strtolower', array_map('trim', $filters)));
        foreach ($items as $item) {
            $title = $item->get_title();
            $desc = $item->get_description();
            $link = $item->get_permalink();
            $date = $item->get_date('Y-m-d H:i:s');
            // Debug log
            error_log('Processing item: ' . print_r([
                'title' => $title,
                'link' => $link,
                'date' => $date,
                'desc' => $desc,
                'filters' => $filters
            ], true));
            // If filters exist, only include if at least one keyword matches
            $haystack = strtolower($title . ' ' . $desc);
            if (!empty($filters)) {
                $matched = false;
                foreach ($filters as $kw) {
                    if ($kw && strpos($haystack, $kw) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    error_log('Skipped (no filter match): ' . $title);
                    continue;
                }
            }
            if (!$link || !$title) {
                error_log('Skipped (missing link or title): ' . print_r(['title'=>$title,'link'=>$link],true));
                continue;
            }
            if (!$date) {
                $date = current_time('mysql');
            }
            $already = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $items_table WHERE link = %s",
                $link
            ));
            if ($already) {
                $exists++;
                $wpdb->update(
                    $items_table,
                    [
                        'summary'   => $desc
                    ],
                    [ 'link' => $link ]
                );
                error_log('Updated (already exists): ' . $link);
            } else {
                $result = $wpdb->insert($items_table, [
                    'feed_id'   => $feed->id,
                    'title'     => $title,
                    'link'      => $link,
                    'date'      => $date,
                    'summary'   => $desc
                ]);
                if ($result) {
                    $added++;
                    error_log('Inserted: ' . $link);
                } else {
                    error_log('Insert failed: ' . $wpdb->last_error . ' for ' . $link);
                }
            }
        }
    }
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $items_table");
    if ($return_stats) {
        return [ 'added' => $added, 'exists' => $exists, 'total' => $total ];
    }
}
