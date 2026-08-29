<?php
/**
 * MENA Careers Quick Search
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Quick_Search
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('sffc_quick_search', [$this, 'render_shortcode']);
        add_action('wp_ajax_sffc_quick_search_suggestions', [$this, 'ajax_suggestions']);
        add_action('wp_ajax_nopriv_sffc_quick_search_suggestions', [$this, 'ajax_suggestions']);
    }

    public function render_shortcode($atts = [])
    {
        $atts = shortcode_atts([
            'class' => '',
            'brand' => __('MENA CAREERS', 'senna-finance'),
            'title' => __('Private Equity Careers & Prep', 'senna-finance'),
            'subtitle' => __('Search roles, case studies, companies, and expert guidance.', 'senna-finance'),
        ], $atts);

        $this->enqueue_assets();

        $instance_id = 'sffc-quick-search-' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid());
        $routes = [
            'case-studies' => home_url('/terminal/?tab=resource-library'),
            'careers' => home_url('/terminal/?tab=feed'),
            'companies' => home_url('/terminal/?tab=feed'),
            'expert-advice' => home_url('/terminal/?tab=mentorship'),
        ];

        ob_start();
        ?>
        <section
            class="sffc-quick-search <?php echo esc_attr($atts['class']); ?>"
            id="<?php echo esc_attr($instance_id); ?>"
            data-routes="<?php echo esc_attr(wp_json_encode($routes)); ?>">
            <div class="sffc-quick-search__inner">
                <div class="sffc-quick-search__lockup" aria-hidden="true">
                    <span class="sffc-quick-search__lockup-dot"></span>
                    <span class="sffc-quick-search__lockup-wordmark"><?php echo esc_html($atts['brand']); ?></span>
                </div>
                <h1 class="sffc-quick-search__headline"><?php echo esc_html($atts['title']); ?></h1>
                <div class="sffc-quick-search__divider"></div>

                <div class="sffc-quick-search__modes" role="tablist" aria-label="<?php esc_attr_e('Quick search categories', 'senna-finance'); ?>">
                    <button type="button" class="sffc-quick-search__mode is-active" data-mode="case-studies"><?php esc_html_e('Case Studies', 'senna-finance'); ?></button>
                    <button type="button" class="sffc-quick-search__mode" data-mode="careers"><?php esc_html_e('PE Careers', 'senna-finance'); ?></button>
                    <button type="button" class="sffc-quick-search__mode" data-mode="companies"><?php esc_html_e('Companies', 'senna-finance'); ?></button>
                    <button type="button" class="sffc-quick-search__mode" data-mode="expert-advice"><?php esc_html_e('Expert Advice', 'senna-finance'); ?></button>
                </div>

                <p class="sffc-quick-search__subtitle"><?php echo esc_html($atts['subtitle']); ?></p>

                <div class="sffc-quick-search__bar-wrap">
                    <div class="sffc-quick-search__bar" role="search">
                        <span class="sffc-quick-search__search-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-3.5-3.5"></path>
                            </svg>
                        </span>
                        <input
                            type="search"
                            class="sffc-quick-search__input"
                            placeholder="<?php esc_attr_e('Search MENA Careers roles, case studies, companies...', 'senna-finance'); ?>"
                            autocomplete="off"
                            spellcheck="false">
                        <button type="button" class="sffc-quick-search__clear" aria-label="<?php esc_attr_e('Clear search', 'senna-finance'); ?>" hidden>&times;</button>
                        <button type="button" class="sffc-quick-search__submit"><?php esc_html_e('Search', 'senna-finance'); ?></button>
                    </div>

                    <div class="sffc-quick-search__dropdown" hidden>
                        <div class="sffc-quick-search__dropdown-list"></div>
                    </div>
                </div>

            </div>
        </section>
        <?php

        return ob_get_clean();
    }

    public function ajax_suggestions()
    {
        check_ajax_referer('sffc_quick_search_nonce', 'nonce');

        $mode = sanitize_key($_POST['mode'] ?? 'case-studies');
        $query = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
        $limit = min(10, max(1, absint($_POST['limit'] ?? 8)));

        wp_send_json_success([
            'items' => $this->get_suggestions($mode, $query, $limit),
        ]);
    }

    private function enqueue_assets()
    {
        wp_enqueue_style(
            'sffc-quick-search',
            SFFC_PLUGIN_URL . 'assets/css/quick-search.css',
            [],
            defined('SFFC_VERSION') ? SFFC_VERSION : null
        );

        wp_enqueue_script(
            'sffc-quick-search',
            SFFC_PLUGIN_URL . 'assets/js/quick-search.js',
            ['jquery'],
            defined('SFFC_VERSION') ? SFFC_VERSION : null,
            true
        );

        wp_localize_script('sffc-quick-search', 'sffcQuickSearch', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_quick_search_nonce'),
            'learningUrl' => home_url('/senna-ai/'),
            'membershipUrl' => home_url('/memberships/'),
            'isLoggedIn' => is_user_logged_in(),
            'strings' => [
                'empty' => __('No matches yet. Press enter to open the best matching section.', 'senna-finance'),
                'loading' => __('Searching...', 'senna-finance'),
            ],
        ]);
    }

    private function get_suggestions($mode, $query, $limit)
    {
        if ($mode === 'global') {
            return $this->get_global_suggestions($query, $limit);
        }

        switch ($mode) {
            case 'careers':
                return $this->get_career_suggestions($query, $limit);
            case 'companies':
                return $this->get_company_suggestions($query, $limit);
            case 'expert-advice':
                return $this->get_expert_advice_suggestions($query, $limit);
            case 'case-studies':
            default:
                return $this->get_case_study_suggestions($query, $limit);
        }
    }

    private function get_global_suggestions($query, $limit)
    {
        $sources = [
            $this->get_case_study_suggestions($query, max(2, $limit)),
            $this->get_career_suggestions($query, max(2, $limit)),
            $this->get_company_suggestions($query, max(2, $limit)),
            $this->get_expert_advice_suggestions($query, max(2, $limit)),
        ];

        $results = [];
        $seen = [];
        $source_count = count($sources);
        $index = 0;

        while (count($results) < $limit) {
            $added = false;

            for ($source_index = 0; $source_index < $source_count; $source_index++) {
                if (!isset($sources[$source_index][$index])) {
                    continue;
                }

                $item = $sources[$source_index][$index];
                $key = strtolower(trim(($item['title'] ?? '') . '|' . ($item['kind'] ?? '')));
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }

                $results[] = $item;
                $seen[$key] = true;
                $added = true;

                if (count($results) >= $limit) {
                    break 2;
                }
            }

            if (!$added) {
                break;
            }

            $index++;
        }

        return $results;
    }

    private function get_case_study_suggestions($query, $limit)
    {
        if (!class_exists('SFFC_CRM_Resource_Library')) {
            return [];
        }

        $model = new SFFC_CRM_Resource_Library();
        $resources = $model->get_all([
            'is_active' => 1,
            'is_case_study' => 1,
            'limit' => 120,
        ]);

        $matches = $this->rank_rows($resources, $query, static function ($row) {
            return implode(' ', [
                $row['title'] ?? '',
                $row['description'] ?? '',
                $row['category'] ?? '',
                $row['resource_type'] ?? '',
            ]);
        });

        $items = [];
        foreach (array_slice($matches, 0, $limit) as $resource) {
            $type = strtoupper((string) ($resource['resource_type'] ?? 'link'));
            $category = sanitize_text_field($resource['category'] ?? '');
            $subtitle = trim(implode(' · ', array_filter([
                __('Case Study', 'senna-finance'),
                $category,
                $type,
            ])));

            $items[] = [
                'title' => sanitize_text_field($resource['title'] ?? __('Case Study', 'senna-finance')),
                'subtitle' => $subtitle,
                'url' => esc_url_raw($resource['resource_url'] ?: home_url('/terminal/?tab=resource-library')),
                'badge' => __('Case Study', 'senna-finance'),
                'thumb' => esc_url_raw($resource['thumbnail_url'] ?? ''),
                'kind' => 'case-studies',
            ];
        }

        return $items;
    }

    private function get_career_suggestions($query, $limit)
    {
        if (!class_exists('SFFC_CRM_Post')) {
            return [];
        }

        $post_model = new SFFC_CRM_Post();
        $posts = $post_model->get_feed([
            'per_page' => 80,
            'approved_only' => true,
        ]);

        $matches = $this->rank_rows($posts, $query, static function ($row) {
            return implode(' ', [
                $row['role_title'] ?? '',
                $row['company'] ?? '',
                $row['location'] ?? '',
                $row['location_city'] ?? '',
                $row['location_country'] ?? '',
                $row['content_snippet'] ?? '',
                $row['keywords'] ?? '',
            ]);
        });

        $items = [];
        foreach (array_slice($matches, 0, $limit) as $post) {
            $location = $this->resolve_location_label($post);
            $subtitle = trim(implode(' · ', array_filter([
                sanitize_text_field($post['company'] ?? ''),
                $location,
            ])));

            $items[] = [
                'title' => sanitize_text_field($post['role_title'] ?? __('Opportunity', 'senna-finance')),
                'subtitle' => $subtitle,
                'url' => esc_url_raw($this->resolve_post_url($post, home_url('/terminal/?tab=feed&quick_search=' . rawurlencode($query)))),
                'badge' => __('PE Career', 'senna-finance'),
                'thumb' => esc_url_raw($this->normalize_logo($post['company_logo'] ?? '')),
                'kind' => 'careers',
            ];
        }

        return $items;
    }

    private function get_company_suggestions($query, $limit)
    {
        if (!class_exists('SFFC_CRM_Post')) {
            return [];
        }

        $post_model = new SFFC_CRM_Post();
        $posts = $post_model->get_feed([
            'per_page' => 120,
            'approved_only' => true,
        ]);

        $companies = [];
        foreach ($posts as $post) {
            $company_name = trim((string) ($post['company'] ?? ''));
            if ($company_name === '') {
                continue;
            }

            $key = strtolower($company_name);
            if (!isset($companies[$key])) {
                $companies[$key] = [
                    'title' => sanitize_text_field($company_name),
                    'subtitle' => $this->resolve_location_label($post),
                    'url' => home_url('/terminal/?tab=feed&quick_search=' . rawurlencode($company_name)),
                    'badge' => __('Company', 'senna-finance'),
                    'thumb' => esc_url_raw($this->normalize_logo($post['company_logo'] ?? '')),
                    'kind' => 'companies',
                    'score_text' => $company_name . ' ' . ($post['location'] ?? '') . ' ' . ($post['location_country'] ?? ''),
                ];
                continue;
            }

            if ($companies[$key]['thumb'] === '' && !empty($post['company_logo'])) {
                $companies[$key]['thumb'] = esc_url_raw($this->normalize_logo($post['company_logo']));
            }
            if ($companies[$key]['subtitle'] === '') {
                $companies[$key]['subtitle'] = $this->resolve_location_label($post);
            }
        }

        $matches = $this->rank_rows(array_values($companies), $query, static function ($row) {
            return implode(' ', [
                $row['title'] ?? '',
                $row['subtitle'] ?? '',
                $row['score_text'] ?? '',
            ]);
        });

        $items = [];
        foreach (array_slice($matches, 0, $limit) as $company) {
            unset($company['score_text']);
            $items[] = $company;
        }

        return $items;
    }

    private function get_expert_advice_suggestions($query, $limit)
    {
        $items = [
            [
                'title' => __('Book a Mentor Session', 'senna-finance'),
                'subtitle' => __('Career Assessment, interview prep, networking strategy, and offer decisions.', 'senna-finance'),
                'url' => home_url('/terminal/?tab=mentorship&topic=mentor_session'),
                'badge' => __('Expert Advice', 'senna-finance'),
                'thumb' => '',
                'kind' => 'expert-advice',
            ],
            [
                'title' => __('CV / LinkedIn Review', 'senna-finance'),
                'subtitle' => __('Human-reviewed feedback on positioning, bullets, and profile clarity.', 'senna-finance'),
                'url' => home_url('/terminal/?tab=mentorship&topic=cv_linkedin_review'),
                'badge' => __('Expert Advice', 'senna-finance'),
                'thumb' => '',
                'kind' => 'expert-advice',
            ],
            [
                'title' => __('Mock Interviews', 'senna-finance'),
                'subtitle' => __('Technical, behavioural, modelling, and case-study practice.', 'senna-finance'),
                'url' => home_url('/terminal/?tab=mentorship&topic=mock_interview'),
                'badge' => __('Expert Advice', 'senna-finance'),
                'thumb' => '',
                'kind' => 'expert-advice',
            ],
            [
                'title' => __('Career Plan', 'senna-finance'),
                'subtitle' => __('Get a targeted roadmap based on role, timeline, and location.', 'senna-finance'),
                'url' => home_url('/terminal/?tab=mentorship&topic=career_plan'),
                'badge' => __('Expert Advice', 'senna-finance'),
                'thumb' => '',
                'kind' => 'expert-advice',
            ],
        ];

        $matches = $this->rank_rows($items, $query, static function ($row) {
            return implode(' ', [
                $row['title'] ?? '',
                $row['subtitle'] ?? '',
            ]);
        });

        return array_slice($matches, 0, $limit);
    }

    private function rank_rows(array $rows, $query, callable $text_callback)
    {
        $needle = strtolower(trim((string) $query));
        if ($needle === '') {
            return $rows;
        }

        $scored = [];
        foreach ($rows as $row) {
            $haystack = strtolower(trim((string) $text_callback($row)));
            if ($haystack === '') {
                continue;
            }

            $score = $this->score_match($needle, $haystack);
            if ($score <= 0) {
                continue;
            }

            $row['_score'] = $score;
            $scored[] = $row;
        }

        usort($scored, static function ($left, $right) {
            return ($right['_score'] ?? 0) <=> ($left['_score'] ?? 0);
        });

        foreach ($scored as &$row) {
            unset($row['_score']);
        }
        unset($row);

        return $scored;
    }

    private function score_match($needle, $haystack)
    {
        if ($needle === '') {
            return 1;
        }

        $score = 0;
        if ($haystack === $needle) {
            $score += 500;
        }
        if (strpos($haystack, $needle) === 0) {
            $score += 300;
        } elseif (strpos($haystack, $needle) !== false) {
            $score += 180;
        }

        $tokens = preg_split('/\s+/', $needle);
        foreach ((array) $tokens as $token) {
            if ($token === '') {
                continue;
            }
            if (strpos($haystack, $token) !== false) {
                $score += 50;
            }
        }

        return $score;
    }

    private function resolve_post_url(array $post, $fallback)
    {
        $jobs_post_id = (int) ($post['jobs_post_id'] ?? 0);
        if ($jobs_post_id > 0) {
            $jobs_post = get_post($jobs_post_id);
            if ($jobs_post && $jobs_post->post_type === 'jobs') {
                $permalink = get_permalink($jobs_post_id);
                if ($permalink) {
                    return $permalink;
                }
            }
        }

        if (!empty($post['wp_post_id'])) {
            $wp_post_id = (int) $post['wp_post_id'];
            $wp_post = get_post($wp_post_id);
            if ($wp_post && $wp_post->post_type === 'jobs') {
                $permalink = get_permalink($wp_post_id);
                if ($permalink) {
                    return $permalink;
                }
            }

            $permalink = get_permalink($wp_post_id);
            if ($permalink) {
                return $permalink;
            }
        }

        if (!empty($post['application_url'])) {
            return $post['application_url'];
        }

        return $fallback;
    }

    private function resolve_location_label(array $row)
    {
        $parts = array_filter([
            trim((string) ($row['location'] ?? '')),
            trim((string) ($row['location_city'] ?? '')),
            trim((string) ($row['location_country'] ?? '')),
        ]);

        if (empty($parts)) {
            return '';
        }

        return sanitize_text_field(implode(', ', array_unique($parts)));
    }

    private function normalize_logo($logo)
    {
        $logo = trim((string) $logo);
        if ($logo === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $logo)) {
            $logo = preg_replace('#^(https?:)?//#i', '', $logo);
            if (strpos($logo, '/') === false && strpos($logo, '.') !== false) {
                return '';
            }
            $logo = 'https://' . ltrim($logo, '/');
        }

        return $logo;
    }
}
