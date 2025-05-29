<?php
defined('ABSPATH') || exit;

add_action('admin_init', 'news_register_settings');

function news_register_settings() {
    // Removed options-based settings registration
    // register_setting('news_group', 'news_feeds');
}

function news_settings_page() {
    if (! current_user_can('manage_options')) return;
    global $wpdb;
    $feeds_table = $wpdb->prefix . 'news_feeds';
    $feeds = $wpdb->get_results("SELECT * FROM $feeds_table", ARRAY_A);
    ?>
    <div class="wrap">
        <h1><?php _e('8Realms News Feeds', '8realms-news'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('news_group'); ?>
            <table class="form-table">
                <thead>
                    <tr><th><?php _e('Name', '8realms-news'); ?></th><th><?php _e('URL', '8realms-news'); ?></th><th><?php _e('Keywords (comma-separated)', '8realms-news'); ?></th><th><?php _e('Active', '8realms-news'); ?></th><th><?php _e('Type', '8realms-news'); ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($feeds as $feed): ?>
                    <tr>
                        <td><?php echo esc_html($feed['name']); ?></td>
                        <td><?php echo esc_url($feed['url']); ?></td>
                        <td><?php echo esc_html($feed['type']); ?></td>
                        <td><?php echo $feed['active'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
    <?php
}

// Save, validate, and fetch functions
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
    $active        = isset($_POST['feed_active']) ? 1 : 0;
    $type          = sanitize_text_field($_POST['feed_type']);
    $items         = [];
    $error         = '';
    if (!news_validate_feed_url($url, $items, $error)) {
        $redirect = add_query_arg(['page'=>'8realms-news-edit','preview_url'=>urlencode($url),'error'=>urlencode($error)], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
    if ($id) {
        $wpdb->update($feeds_table, ['name'=>$name,'url'=>$url,'active'=>$active,'type'=>$type], ['id'=>$id], ['%s','%s','%d','%s'], ['%d']);
        $wpdb->delete($filters_table, ['feed_id'=>$id], ['%d']);
    } else {
        $wpdb->insert($feeds_table, ['name'=>$name,'url'=>$url,'active'=>$active,'type'=>$type], ['%s','%s','%d','%s']);
        $id = $wpdb->insert_id;
    }
    if ($filters_input) {
        $keywords = array_filter(array_map('trim', explode(',', $filters_input)));
        foreach ($keywords as $kw) {
            $wpdb->insert($filters_table, ['feed_id'=>$id,'keyword'=>$kw], ['%d','%s']);
        }
    }
    wp_safe_redirect(admin_url('admin.php?page=8realms-news'));
    exit;
}


// Admin menu
add_action('admin_menu', 'news_admin_menu');
function news_admin_menu() {
    add_menu_page('Manage Newsfeeds', 'Manage Newsfeeds', 'manage_options', '8realms-news', 'news_feeds_list_page', 'dashicons-rss', 60);
    add_submenu_page('8realms-news', 'Add / Edit Feed Source', 'Add / Edit Feed Source', 'manage_options', '8realms-news-edit', 'news_feed_edit_page');
}

// Enqueue postbox JS and init toggles
add_action('admin_enqueue_scripts', 'news_admin_assets');
function news_admin_assets() {
    if (empty($_GET['page']) || $_GET['page'] !== '8realms-news-edit') {
        return;
    }
    wp_enqueue_script('postbox');
}
add_action('admin_footer', 'news_postbox_toggles');
function news_postbox_toggles() {
    if (empty($_GET['page']) || $_GET['page'] !== '8realms-news-edit') {
        return;
    }
    echo '<script>jQuery(function($){postboxes.add_postbox_toggles("8realms-news-edit");});</script>';
}

// Register meta boxes
add_action('admin_init', 'news_register_meta_boxes');
function news_register_meta_boxes() {
    add_meta_box('feed_source_details', __('Feed Source Details','8realms-news'), 'news_meta_box_details', '8realms-news-edit', 'normal', 'high');
    add_meta_box('feed_preview', __('Feed Preview','8realms-news'), 'news_meta_box_preview', '8realms-news-edit', 'normal', 'low');
}

// Meta box: details
function news_meta_box_details() {
    global $url, $filters;
    ?>
    <table class="form-table">
        <tr>
            <th><label for="feed_url"><?php _e('Feed URL','8realms-news'); ?></label></th>
            <td><input name="feed_url" type="url" id="feed_url" value="<?php echo esc_url($url); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="feed_filters"><?php _e('Keyword Filters (comma separated)','8realms-news'); ?></label></th>
            <td><textarea name="feed_filters" id="feed_filters" class="large-text" rows="3"><?php echo esc_textarea($filters); ?></textarea></td>
        </tr>
    </table>
    <?php
}

// Meta box: preview
function news_meta_box_preview() {
    global $preview_items, $error, $url;
    if (!empty($preview_items)) {
        echo '<ul>';
        foreach ($preview_items as $item) {
            printf(
                '<li><a href="%s" target="_blank">%s</a> &mdash; %s<br><div style="margin-left:1em; color:#555;">%s</div></li>',
                esc_url($item->get_permalink()),
                esc_html($item->get_title()),
                esc_html($item->get_date('F j, Y')),
                wp_kses_post($item->get_description())
            );
        }
        echo '</ul>';
    } elseif (!empty($error)) {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    }
    // Preview button as submit
    echo '<button type="submit" name="preview_feed" value="1" class="button">' . __('Preview Feed','8realms-news') . '</button>';
}

// Feeds list table and list page (unchanged)...
if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
class News_Feeds_List_Table extends WP_List_Table {
    private $table_name;
    public function __construct() {
        parent::__construct([ 'singular'=>'feed', 'plural'=>'feeds', 'ajax'=>false ]);
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'news_feeds';
    }
    public function get_columns() {
        return [
            'cb'=>'<input type=\'checkbox\' />',
            'name'=>'Feed Name',
            'url'=>'URL',
            'active'=>'Active',
            'type'=>'Type',
            'actions'=>'Actions'
        ];
    }
    protected function column_cb($item) {
        return sprintf('<input type=\'checkbox\' name=\'feed[]\' value=\'%d\' />', $item->id);
    }
    public function prepare_items() {
        global $wpdb;
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $total_items  = $wpdb->get_var("SELECT COUNT(id) FROM {$this->table_name}");
        $this->set_pagination_args([ 'total_items'=>$total_items, 'per_page'=>$per_page ]);
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page, ($current_page - 1) * $per_page
            )
        );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name': return esc_html($item->name);
            case 'url': return esc_url($item->url);
            case 'active': return $item->active ? 'Yes' : 'No';
            case 'type': return esc_html($item->type);
            case 'actions':
                $edit_url = add_query_arg([ 'page'=>'8realms-news-edit', 'feed_id'=>$item->id ], admin_url('admin.php'));
                return sprintf('<a href="%s">Edit</a>', esc_url($edit_url));
            default: return '';
        }
    }
}

function news_feeds_list_page() {
    echo '<div class="wrap"><h1 class="wp-heading-inline">RSS Feeds</h1>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=8realms-news-edit')) . '" class="page-title-action">Add New</a><hr class="wp-header-end">';
    $table = new News_Feeds_List_Table();
    $table->prepare_items();
    $table->display();
    echo '</div>';
}

// Edit page callback using postboxes and meta boxes
function news_feed_edit_page() {
    global $wpdb;
    $feeds_table   = $wpdb->prefix . 'news_feeds';
    $filters_table = $wpdb->prefix . 'news_filters';
    $id            = isset($_GET['feed_id']) ? intval($_GET['feed_id']) : 0;
    // Use POSTed values if previewing, else load from DB
    $is_preview    = isset($_POST['preview_feed']) && $_POST['preview_feed'] == '1';
    $name          = $is_preview ? sanitize_text_field($_POST['feed_name']) : ($id ? esc_attr($wpdb->get_var($wpdb->prepare("SELECT name FROM $feeds_table WHERE id = %d", $id))) : '');
    $url           = $is_preview ? esc_url_raw($_POST['feed_url']) : ($id ? esc_url($wpdb->get_var($wpdb->prepare("SELECT url FROM $feeds_table WHERE id = %d", $id))) : '');
    $active        = $is_preview ? (isset($_POST['feed_active']) ? true : false) : ($id ? (bool) $wpdb->get_var($wpdb->prepare("SELECT active FROM $feeds_table WHERE id = %d", $id)) : true);
    $type          = $is_preview ? sanitize_text_field($_POST['feed_type']) : ($id ? esc_attr($wpdb->get_var($wpdb->prepare("SELECT type FROM $feeds_table WHERE id = %d", $id))) : 'standard');
    $filters       = $is_preview ? sanitize_text_field($_POST['feed_filters']) : ($id ? implode(',', $wpdb->get_col($wpdb->prepare("SELECT keyword FROM $filters_table WHERE feed_id = %d", $id))) : '');
    $preview_items = [];
    $error         = '';

    // If previewing, or if URL exists, show preview
    if ($url) {
        news_validate_feed_url($url, $preview_items, $error);
        // Filter preview items by keywords if filters are set
        if (!empty($filters)) {
            $keywords = array_filter(array_map('trim', explode(',', $filters)));
            if (!empty($keywords)) {
                $preview_items = array_filter($preview_items, function($item) use ($keywords) {
                    $haystack = strtolower($item->get_title() . ' ' . $item->get_description());
                    foreach ($keywords as $kw) {
                        if (stripos($haystack, strtolower($kw)) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . ($id ? __('Edit Feed Source','8realms-news') : __('Add New Feed Source','8realms-news')) . '</h1>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('save_news_feed');
    echo '<input type="hidden" name="action" value="save_news_feed">';
    if ($id) {
        echo '<input type="hidden" name="feed_id" value="' . esc_attr($id) . '">';
    }
    // Title field
    echo '<div id="titlediv"><div id="titlewrap">';
    echo '<label class="screen-reader-text" for="feed_name">' . __('Feed Name','8realms-news') . '</label>';
    echo '<input type="text" name="feed_name" id="feed_name" class="widefat" value="' . esc_attr($name) . '" placeholder="' . esc_attr__('Feed Name','8realms-news') . '">';
    echo '</div>';
    if ($id) {
        echo '<p class="description">' . sprintf(__('Shortcode: [news feed_id="%d"]','8realms-news'), $id) . '</p>';
    }
    echo '</div>';
    // Postbox wrapper
    echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2">';
    echo '<div id="post-body-content">';
    // Pass variables for meta boxes
    $GLOBALS['url'] = $url;
    $GLOBALS['filters'] = $filters;
    $GLOBALS['preview_items'] = $preview_items;
    $GLOBALS['error'] = $error;
    do_meta_boxes('8realms-news-edit','normal', null);
    // Add a meta box for Active, Type, and Save button
    echo '<div class="postbox" style="margin-top:20px;">';
    echo '<h2 class="hndle"><span>' . __('Feed Settings & Actions','8realms-news') . '</span></h2>';
    echo '<div class="inside">';
    echo '<table class="form-table">';
    echo '<tr><th><label for="feed_active">' . __('Active','8realms-news') . '</label></th>';
    echo '<td><input type="checkbox" name="feed_active" id="feed_active" value="1" ' . checked($active, true, false) . '></td></tr>';
    echo '<tr><th><label for="feed_type">' . __('Type','8realms-news') . '</label></th>';
    echo '<td><select name="feed_type" id="feed_type">';
    echo '<option value="standard" ' . selected($type, 'standard', false) . '>' . __('Standard','8realms-news') . '</option>';
    echo '<option value="podcast" ' . selected($type, 'podcast', false) . '>' . __('Podcast','8realms-news') . '</option>';
    echo '<option value="youtube" ' . selected($type, 'youtube', false) . '>' . __('YouTube','8realms-news') . '</option>';
    echo '</select></td></tr>';
    echo '</table>';
    echo '<p><button type="submit" class="button button-primary" name="save_feed_source" value="1">' . __('Save Feed Source','8realms-news') . '</button></p>';
    echo '</div></div>';
    echo '</div>';
    echo '<div id="postbox-container-1" class="postbox-container">';
    // Remove the save meta box from the sidebar
    // do_meta_boxes('8realms-news-edit','side', null);
    echo '</div>';
    echo '</div></div>';
    echo '</form></div>';
}
