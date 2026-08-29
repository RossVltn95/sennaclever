<?php
if (!defined('ABSPATH')) {
    exit;
}

$card_items = isset($card_items) && is_array($card_items) ? $card_items : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array('page' => 1, 'total_pages' => 1, 'total_items' => 0);
?>
<div class="sffc-company-explorer__grid">
    <?php if (!empty($card_items)) : ?>
        <?php foreach ($card_items as $card) :
            $regions = isset($card['regions']) && is_array($card['regions']) ? $card['regions'] : array();
            $sectors = isset($card['sectors']) && is_array($card['sectors']) ? $card['sectors'] : array();
            $tags    = isset($card['tags']) && is_array($card['tags']) ? $card['tags'] : array();
            $logo    = !empty($card['logo']) ? $card['logo'] : '';
            $initials = '';
            if ($logo === '' && !empty($card['name'])) {
                $words = preg_split('/\s+/', $card['name']);
                foreach ($words as $word) {
                    if ($word === '') {
                        continue;
                    }
                    $initials .= strtoupper(mb_substr($word, 0, 1));
                    if (strlen($initials) >= 2) {
                        break;
                    }
                }
                $initials = mb_substr($initials, 0, 2);
            }
        ?>
            <article class="sffc-company-card-tile" data-company-id="<?php echo esc_attr($card['id']); ?>">
                <a class="sffc-company-card-tile__link" href="<?php echo esc_url($card['permalink']); ?>">
                    <div class="sffc-company-card-tile__thumb">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($card['name']); ?>" loading="lazy">
                        <?php elseif ($initials) : ?>
                            <span class="sffc-company-card-tile__initials"><?php echo esc_html($initials); ?></span>
                        <?php else : ?>
                            <span class="sffc-company-card-tile__initials">PE</span>
                        <?php endif; ?>
                    </div>
                    <div class="sffc-company-card-tile__body">
                        <div class="sffc-company-card-tile__header">
                            <h3><?php echo esc_html($card['name']); ?></h3>
                            <?php if (!empty($card['aum'])) : ?>
                                <span class="sffc-company-card-tile__aum"><?php echo esc_html($card['aum']); ?> AUM</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($card['excerpt'])) : ?>
                            <p class="sffc-company-card-tile__excerpt"><?php echo esc_html($card['excerpt']); ?></p>
                        <?php endif; ?>
                        <div class="sffc-company-card-tile__meta">
                            <?php if (!empty($card['headquarters'])) : ?>
                                <span><?php echo esc_html($card['headquarters']); ?></span>
                            <?php elseif (!empty($regions)) : ?>
                                <span><?php echo esc_html($regions[0]); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($sectors)) : ?>
                                <span><?php echo esc_html(implode(' · ', array_slice($sectors, 0, 2))); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($card['portfolio_count'])) : ?>
                                <span><?php echo esc_html(number_format_i18n($card['portfolio_count'])); ?> portfolio</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tags)) : ?>
                            <div class="sffc-company-card-tile__tags">
                                <?php foreach (array_slice($tags, 0, 3) as $tag) : ?>
                                    <span class="sffc-company-card-tile__tag"><?php echo esc_html($tag['name']); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($tags) > 3) : ?>
                                    <span class="sffc-company-card-tile__tag sffc-company-card-tile__tag--more">+<?php echo esc_html(count($tags) - 3); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="sffc-company-explorer__empty"><?php esc_html_e('No firms match the selected filters yet.', 'senna-finance'); ?></div>
    <?php endif; ?>
</div>
<?php if (!empty($pagination['total_items'])) : ?>
    <div class="sffc-company-explorer__summary">
        <?php
        printf(
            esc_html__('%1$d firms • Page %2$d of %3$d', 'senna-finance'),
            (int) $pagination['total_items'],
            (int) $pagination['page'],
            max(1, (int) $pagination['total_pages'])
        );
        ?>
    </div>
<?php endif; ?>