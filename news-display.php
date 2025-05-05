<?php
defined('ABSPATH') || exit;

add_shortcode('8realms_news', '8realms_news_render_feed');

function 8realms_news_render_feed($atts) {
    $items = get_transient('8realms_news_feed_items') ?: [];
    ob_start();
    ?>
    <div class="8realms-news-search">
        <input type="text" id="8realms-news-search-input" placeholder="<?php esc_attr_e('Search news...', '8realms-news'); ?>">
    </div>
    <div class="8realms-news-feed">
        <?php foreach ($items as $item): ?>
            <div class="8realms-news-card">
                <?php if ($item['thumbnail']): ?><img src="<?php echo esc_url($item['thumbnail']); ?>" alt=""><?php endif; ?>
                <h3><a href="<?php echo esc_url($item['link']); ?>" target="_blank"><?php echo esc_html($item['title']); ?></a></h3>
                <p><?php echo wp_kses_post(wp_trim_words($item['summary'], 30)); ?></p>
                <small><?php echo esc_html($item['source_name']); ?> — <?php echo date_i18n(get_option('date_format'), $item['date']); ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        (function(){
            const input = document.getElementById('8realms-news-search-input');
            input.addEventListener('input', () => {
                const term = input.value.toLowerCase();
                document.querySelectorAll('.8realms-news-card').forEach(card => {
                    card.style.display = card.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}