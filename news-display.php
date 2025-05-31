<?php
defined('ABSPATH') || exit;

/**
 * Front-end rendering of aggregated news feed via [news] shortcode
 */
add_shortcode('news', 'news_render_feed');
function news_render_feed($atts) {
    global $wpdb;
    $items_table = $wpdb->prefix . 'news_items';
    $feeds_table = $wpdb->prefix . 'news_feeds';
    $items = $wpdb->get_results("SELECT i.*, f.name as feed_name, f.icon_url, f.type as feed_type FROM $items_table i JOIN $feeds_table f ON i.feed_id = f.id ORDER BY i.date DESC", ARRAY_A);

    if (empty($items)) {
        return '<p>' . esc_html__('No news items found.', '8realms-news') . '</p>';
    }

    ob_start(); ?>

    <div class="news-feed-filter-bar">
        <span class="news-feed-filter-label">Showing:</span>
        <button class="news-feed-filter-btn active" data-type="article" style="--accent: var(--wp--preset--color--accent-3, #246f38);">Article</button>
        <button class="news-feed-filter-btn active" data-type="podcast" style="--accent: var(--wp--preset--color--accent-3, #246f38);">Podcast</button>
        <button class="news-feed-filter-btn active" data-type="youtube" style="--accent: var(--wp--preset--color--accent-3, #246f38);">YouTube</button>
        <button class="news-feed-filter-btn active" data-type="8realms" style="--accent: var(--wp--preset--color--accent-3, #246f38);">8Realms News</button>
    </div>
    <div class="news-feed">
        <?php foreach ($items as $item): ?>
            <a href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer" class="news-card-link" style="text-decoration:none; color:inherit; display:block;">
                <div class="news-card" data-type="<?php echo esc_attr(strtolower($item['feed_type'])); ?>">
                    <div class="news-card-header">
                        <?php if (!empty($item['icon_url'])): ?>
                            <img src="<?php echo esc_url($item['icon_url']); ?>" alt="<?php echo esc_attr($item['feed_name']); ?>" class="news-card-feed-icon" />
                        <?php endif; ?>
                        <span class="news-card-feed-name">
                            <?php echo esc_html($item['feed_name']); ?>
                        </span>
                    </div>
                    <div class="news-card-content">
                        <h3 class="news-card-title has-accent3-color">
                            <?php echo esc_html($item['title']); ?>
                        </h3>
                        <span class="news-card-type-pill"><?php echo esc_html($item['feed_type']); ?></span>
                        <p class="news-card-summary">
                            <?php echo wp_kses_post(wp_trim_words($item['summary'], 30)); ?>
                        </p>
                        <small class="news-card-date has-accent3-color">
                            <?php echo date_i18n(get_option('date_format'), strtotime($item['date'])); ?>
                        </small>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterBtns = document.querySelectorAll('.news-feed-filter-btn');
        const cards = document.querySelectorAll('.news-card-link .news-card');
        let activeTypes = Array.from(filterBtns).map(btn => btn.getAttribute('data-type'));
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const type = btn.getAttribute('data-type');
                btn.classList.toggle('active');
                if (btn.classList.contains('active')) {
                    activeTypes.push(type);
                } else {
                    activeTypes = activeTypes.filter(t => t !== type);
                }
                // Prevent all filters from being off
                if (activeTypes.length === 0) {
                    btn.classList.add('active');
                    activeTypes.push(type);
                }
                cards.forEach(card => {
                    const cardType = card.getAttribute('data-type');
                    if (activeTypes.includes(cardType)) {
                        card.parentElement.style.display = '';
                    } else {
                        card.parentElement.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>

    <?php return ob_get_clean();
}
