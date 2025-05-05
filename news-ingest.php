<?php
defined('ABSPATH') || exit;

// Create table on activation
register_activation_hook(__FILE__, 'news_create_db');
function news_create_db() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $feeds_table = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';

    $feeds_sql = "
    CREATE TABLE $feeds_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name tinytext NOT NULL,
      url text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset;
    ";

    $filters_sql = "
    CREATE TABLE $filters_table (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      feed_id mediumint(9) NOT NULL,
      keyword varchar(100) NOT NULL,
      PRIMARY KEY  (id)
    ) $charset;
    ";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($feeds_sql);
    dbDelta($filters_sql);
}

// Admin menu registration
add_action('admin_menu', 'news_admin_menu');
function news_admin_menu() {
    add_menu_page('8realms News', '8realms News', 'manage_options', '8realms-news', 'news_feeds_list_page', 'dashicons-rss', 60);
    add_submenu_page('8realms-news', 'Add / Edit Feed', 'Add / Edit Feed', 'manage_options', '8realms-news-edit', 'news_feed_edit_page');
}

// Load WP_List_Table
if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Feeds list table class
class News_Feeds_List_Table extends WP_List_Table {
    private $table_name;
    public function __construct() {
        parent::__construct(array('singular'=>'feed','plural'=>'feeds','ajax'=>false));
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'news_feeds';
    }
    public function get_columns() {
        return array(
            'cb'      => '<input type=\'checkbox\' />',
            'name'    => 'Feed Name',
            'url'     => 'URL',
            'actions' => 'Actions',
        );
    }
    protected function column_cb($item) {
        return sprintf('<input type=\'checkbox\' name=\'feed[]\' value=\'%d\' />', $item->id);
    }
    public function prepare_items() {
        global $wpdb;
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $total_items  = $wpdb->get_var("SELECT COUNT(id) FROM {$this->table_name}");
        $this->set_pagination_args(array('total_items'=>$total_items,'per_page'=>$per_page));
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page,
                ($current_page - 1) * $per_page
            )
        );
        $this->_column_headers = array($this->get_columns(), array(), array());
    }
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
                return esc_html($item->name);
            case 'url':
                return esc_url($item->url);
            case 'actions':
                $edit_url = add_query_arg(array('page'=>'8realms-news-edit','feed_id'=>$item->id), admin_url('admin.php'));
                return sprintf('<a href=\'%s\'>Edit</a>', esc_url($edit_url));
            default:
                return '';
        }
    }
}

// Feeds list page callback
function news_feeds_list_page() {
    echo '<div class=\'wrap\'><h1 class=\'wp-heading-inline\'>RSS Feeds</h1>';
    echo '<a href=\''.esc_url(admin_url('admin.php?page=8realms-news-edit')).'\' class=\'page-title-action\'>Add New</a><hr class=\'wp-header-end\'>';
    $table = new News_Feeds_List_Table();
    $table->prepare_items();
    $table->display();
    echo '</div>';
}

// Edit page callback
function news_feed_edit_page() {
    global $wpdb;
    $feeds_table   = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';
    $id            = isset($_GET['feed_id']) ? intval($_GET['feed_id']) : 0;
    $feed          = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $feeds_table WHERE id = %d", $id)) : null;
    $name          = $feed ? esc_attr($feed->name) : '';
    $url           = $feed ? esc_url($feed->url) : '';
    $existing_filters = $id ? $wpdb->get_col($wpdb->prepare("SELECT keyword FROM $filters_table WHERE feed_id = %d", $id)) : array();
    $filters       = $existing_filters ? implode(',', $existing_filters) : '';
    $preview_items = array();
    $error         = '';
    if (isset($_GET['preview_url'])) {
        news_validate_feed_url(urldecode($_GET['preview_url']), $preview_items, $error);
    }
    echo '<div class=\'wrap\'><h1>'.($id ? 'Edit' : 'Add New').' Feed</h1>';
    echo '<form method=\'post\' action=\''.esc_url(admin_url('admin-post.php')).'\'>';
    wp_nonce_field('save_news_feed');
    echo '<input type=\'hidden\' name=\'action\' value=\'save_news_feed\'>';
    if ($id) echo '<input type=\'hidden\' name=\'feed_id\' value=\''.$id.'\'>';
    echo '<table class=\'form-table\'>';
    echo '<tr><th><label for=\'feed_name\'>Feed Name</label></th><td><input name=\'feed_name\' type=\'text\' id=\'feed_name\' value=\''.$name.'\' class=\'regular-text\'></td></tr>';
    echo '<tr><th><label for=\'feed_url\'>Feed URL</label></th><td><input name=\'feed_url\' type=\'url\' id=\'feed_url\' value=\''.$url.'\' class=\'regular-text\'></td></tr>';
    echo '<tr><th><label for=\'feed_filters\'>Keyword Filters (comma separated)</label></th><td><textarea name=\'feed_filters\' id=\'feed_filters\' class=\'large-text\' rows=\'3\'>'.$filters.'</textarea></td></tr>';
    echo '</table>';
    submit_button('Save Feed');
    submit_button('Preview', 'secondary', 'preview', false);
    echo '</form>';
    if (!empty($preview_items)) {
        echo '<h2>Feed Preview</h2><ul>';
        foreach ($preview_items as $item) {
            printf('<li><a href=\'%s\' target=\'_blank\'>%s</a> — %s</li>',
                esc_url($item->get_permalink()),
                esc_html($item->get_title()),
                esc_html($item->get_date('F j, Y'))
            );
        }
        echo '</ul>';
    } elseif ($error) {
        echo '<div class=\'notice notice-error\'><p>'.esc_html($error).'</p></div>';
    }
    echo '</div>';
}

// Save feed handler
add_action('admin_post_save_news_feed', 'news_save_feed');
function news_save_feed() {
    if (!current_user_can('manage_options') || !check_admin_referer('save_news_feed')) {
        wp_die('Permission denied.');
    }
    global $wpdb;
    $feeds_table   = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';
    $id            = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
    $name          = sanitize_text_field($_POST['feed_name']);
    $url           = esc_url_raw($_POST['feed_url']);
    $filters_input = sanitize_text_field($_POST['feed_filters']);
    $items         = array();
    $error         = '';
    if (!news_validate_feed_url($url, $items, $error)) {
        $redirect = add_query_arg(array('page'=>'8realms-news-edit','preview_url'=>urlencode($url),'error'=>urlencode($error)), admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
    if ($id) {
        $wpdb->update($feeds_table, array('name'=>$name,'url'=>$url), array('id'=>$id), array('%s','%s'), array('%d'));
        $wpdb->delete($filters_table, array('feed_id'=>$id), array('%d'));
    } else {
        $wpdb->insert($feeds_table, array('name'=>$name,'url'=>$url), array('%s','%s'));
        $id = $wpdb->insert_id;
    }
    if ($filters_input) {
        $keywords = array_filter(array_map('trim', explode(',', $filters_input)));
        foreach ($keywords as $kw) {
            $wpdb->insert($filters_table, array('feed_id'=>$id,'keyword'=>$kw), array('%d','%s'));
        }
    }
    wp_safe_redirect(admin_url('admin.php?page=8realms-news'));
    exit;
}

// Validate RSS URL and fetch items
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

// Schedule cron for fetching feeds
add_action('wp', 'news_schedule_cron');
function news_schedule_cron() {
    if (!wp_next_scheduled('news_fetch_event')) {
        wp_schedule_event(time(), 'hourly', 'news_fetch_event');
    }
}

// Fetch and cache feed items
add_action('news_fetch_event', 'news_fetch_all_feeds');
function news_fetch_all_feeds() {
    global $wpdb;
    $feeds_table   = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';
    $feeds         = $wpdb->get_results("SELECT * FROM $feeds_table");
    $all_items     = array();
    include_once ABSPATH . WPINC . '/feed.php';
    foreach ($feeds as $feed) {
        $rss = fetch_feed($feed->url);
        if (!is_wp_error($rss)) {
            $max   = $rss->get_item_quantity(20);
            $items = $rss->get_items(0, $max);
            $keywords = $wpdb->get_col($wpdb->prepare("SELECT keyword FROM $filters_table WHERE feed_id = %d", $feed->id));
            foreach ($items as $item) {
                $content = $item->get_title() . ' ' . $item->get_description();
                $match   = empty($keywords) || array_filter($keywords, fn($kw) => stripos($content, $kw) !== false);
                if ($match) {
                    $all_items[] = array(
                        'title'       => $item->get_title(),
                        'link'        => $item->get_permalink(),
                        'date'        => $item->get_date('U'),
                        'summary'     => $item->get_description(),
                        'thumbnail'   => $item->get_enclosure() ? $item->get_enclosure()->get_link() : '',
                        'source_name' => $feed->name,
                    );
                }
            }
        }
    }
    set_transient('news_feed_items', $all_items, HOUR_IN_SECONDS);
}
