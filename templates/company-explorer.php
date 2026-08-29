<?php
if (!defined('ABSPATH')) {
    exit;
}

$filters = isset($filters) && is_array($filters) ? $filters : array();
$card_items = isset($card_items) ? $card_items : array();
$pagination = isset($pagination) ? $pagination : array('page' => 1, 'total_pages' => 1, 'total_items' => count($card_items), 'per_page' => 12);

$current_sort = isset($sort) ? $sort : 'aum_desc';

$initial_query = isset($pagination['per_page'])
    ? array(
        'page' => (int) $pagination['page'],
        'perPage' => (int) $pagination['per_page'],
        'sort' => $current_sort,
        'search' => '',
        'tags' => array(),
    )
    : array();

$has_more = !empty($pagination['total_pages']) && $pagination['page'] < $pagination['total_pages'];
?>
<div class="sffc-company-explorer" data-initial-query="<?php echo esc_attr(wp_json_encode($initial_query)); ?>">
    <div class="sffc-company-explorer__hero">
        <div>
            <h1><?php esc_html_e('Private Equity Firm Explorer', 'senna-finance'); ?></h1>
            <p><?php esc_html_e('Filter the top platforms by mandate focus, region, and curated shortlists—then drill into any firm’s live profile.', 'senna-finance'); ?></p>
        </div>
    </div>

    <div class="sffc-company-explorer__layout">
        <aside class="sffc-company-explorer__filters" aria-label="<?php esc_attr_e('Filter private equity firms', 'senna-finance'); ?>">
            <?php if (!empty($filters)) : ?>
                <?php foreach ($filters as $group) :
                    $options = isset($group['options']) && is_array($group['options']) ? $group['options'] : array();
                ?>
                    <section class="sffc-company-filter-group" data-filter-group="<?php echo esc_attr($group['slug']); ?>">
                        <header>
                            <h3><?php echo esc_html($group['name']); ?></h3>
                            <button type="button" class="sffc-company-filter-clear" data-filter-clear="<?php echo esc_attr($group['slug']); ?>">
                                <?php esc_html_e('Clear', 'senna-finance'); ?>
                            </button>
                        </header>
                        <div class="sffc-company-filter-options">
                            <?php foreach ($options as $option) : ?>
                                <button
                                    type="button"
                                    class="sffc-company-filter-option"
                                    data-term-id="<?php echo esc_attr($option['id']); ?>"
                                    data-term-slug="<?php echo esc_attr($option['slug']); ?>"
                                    data-term-parent="<?php echo !empty($option['is_parent']) ? '1' : '0'; ?>">
                                    <span><?php echo esc_html($option['name']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="sffc-company-filters-empty"><?php esc_html_e('Create company filter terms in wp-admin to power this explorer.', 'senna-finance'); ?></p>
            <?php endif; ?>
        </aside>

        <section class="sffc-company-explorer__results" data-total-pages="<?php echo esc_attr((int) ($pagination['total_pages'] ?? 1)); ?>">
            <div class="sffc-company-explorer__controls">
                <div class="sffc-company-search">
                    <label for="sffc-company-search-field" class="screen-reader-text"><?php esc_html_e('Search firms', 'senna-finance'); ?></label>
                    <input id="sffc-company-search-field" type="search" placeholder="<?php esc_attr_e('Search firms, focus, or region', 'senna-finance'); ?>" autocomplete="off">
                </div>
                <div class="sffc-company-sort">
                    <label for="sffc-company-sort-select"><?php esc_html_e('Sort by', 'senna-finance'); ?></label>
                    <select id="sffc-company-sort-select">
                        <option value="aum_desc" <?php selected($current_sort, 'aum_desc'); ?>><?php esc_html_e('AUM · High to Low', 'senna-finance'); ?></option>
                        <option value="aum_asc" <?php selected($current_sort, 'aum_asc'); ?>><?php esc_html_e('AUM · Low to High', 'senna-finance'); ?></option>
                        <option value="name_az" <?php selected($current_sort, 'name_az'); ?>><?php esc_html_e('Name · A to Z', 'senna-finance'); ?></option>
                        <option value="name_za" <?php selected($current_sort, 'name_za'); ?>><?php esc_html_e('Name · Z to A', 'senna-finance'); ?></option>
                        <option value="latest" <?php selected($current_sort, 'latest'); ?>><?php esc_html_e('Recently Added', 'senna-finance'); ?></option>
                    </select>
                </div>
            </div>

            <div class="sffc-company-selected-filters" aria-live="polite" aria-label="<?php esc_attr_e('Active filters', 'senna-finance'); ?>">
            </div>

            <div class="sffc-company-results" data-has-more="<?php echo $has_more ? '1' : '0'; ?>">
                <?php include SFFC_PLUGIN_DIR . 'templates/company-cards.php'; ?>
            </div>

            <button type="button" class="sffc-company-results__load-more button" <?php if (!$has_more) : ?>hidden<?php endif; ?>>
                <?php esc_html_e('Load more firms', 'senna-finance'); ?>
            </button>
        </section>
    </div>
</div>