<?php
defined('ABSPATH') || exit;

add_action('admin_init', 'news_register_settings');

function news_register_settings() {
    register_setting('news_group', 'news_feeds');
}

function news_settings_page() {
    if (! current_user_can('manage_options')) return;
    $feeds = get_option('news_feeds', []);
    ?>
    <div class="wrap">
        <h1><?php _e('8Realms News Feeds', '8realms-news'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('news_group'); ?>
            <table class="form-table">
                <thead>
                    <tr><th><?php _e('Name', '8realms-news'); ?></th><th><?php _e('URL', '8realms-news'); ?></th><th><?php _e('Keywords (comma-separated)', '8realms-news'); ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($feeds as $id => $feed): ?>
                    <tr>
                        <td><input name="news_feeds[<?php echo esc_attr($id); ?>][name]" value="<?php echo esc_attr($feed['name']); ?>"></td>
                        <td><input name="news_feeds[<?php echo esc_attr($id); ?>][url]" value="<?php echo esc_url($feed['url']); ?>"></td>
                        <td><input name="news_feeds[<?php echo esc_attr($id); ?>][keywords]" value="<?php echo esc_attr(implode(',', get_option("news_filters_{$id}", []))); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                    <tr>
                        <td><input name="news_feeds[new][name]" placeholder="Feed Name"></td>
                        <td><input name="news_feeds[new][url]" placeholder="Feed URL"></td>
                        <td><input name="news_feeds[new][keywords]" placeholder="kw1, kw2"></td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}