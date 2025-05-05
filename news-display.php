<?php
defined('ABSPATH') || exit;

/**
 * Front-end rendering of aggregated news feed via [news] shortcode
 */
add_shortcode('news', 'news_render_feed');
function news_render_feed($atts) {
    global $wpdb;
    $items_table = $wpdb->prefix . 'news_items';
    $items = $wpdb->get_results("SELECT * FROM $items_table ORDER BY date DESC", ARRAY_A);

    if (empty($items)) {
        return '<p>' . esc_html__('No news items found.', '8realms-news') . '</p>';
    }

    ob_start(); ?>

    <div class="news-feed">
        <?php foreach ($items as $item): ?>
            <div class="news-card" style="display:flex; flex-wrap:wrap; margin-bottom:1.5em; border:1px solid #ddd; border-left:4px solid var(--wp--preset--color--accent3); padding:1em;">

                <?php if (!empty($item['thumbnail'])): ?>
                    <div class="news-card-thumbnail" style="flex:0 0 120px; margin-right:1em;">
                        <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['title']); ?>" style="width:100%; height:auto; object-fit:cover;" />
                    </div>
                <?php endif; ?>

                <div class="news-card-content" style="flex:1 1 auto;">
                    <h3 class="has-accent3-color" style="margin-top:0; margin-bottom:.5em;">
                        <a href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer" class="has-accent3-color" style="text-decoration:none;">
                            <?php echo esc_html($item['title']); ?>
                        </a>
                    </h3>
                    <p style="margin:0 0 .75em;">
                        <?php echo wp_kses_post(wp_trim_words($item['summary'], 30)); ?>
                    </p>
                    <small class="has-accent3-color" style="font-size:.85em;">
                        <?php echo date_i18n(get_option('date_format'), strtotime($item['date'])); ?>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php return ob_get_clean();
}
