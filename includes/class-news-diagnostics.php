<?php

/**
 * News Diagnostics & Test Utilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_News_Diagnostics
{
    const AJAX_ACTION = 'sffc_create_test_news';
    const NONCE_KEY = 'sffc_news_test';

    public static function init()
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('sffc news:test', [__CLASS__, 'cli_create_test_news']);
        }

        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_create_test_news']);
    }

    public static function ajax_create_test_news()
    {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to run this test.', 'senna-finance'),
            ], 403);
        }

        $company = isset($_POST['company']) ? sanitize_text_field(wp_unslash($_POST['company'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $link_raw = isset($_POST['link']) ? wp_unslash($_POST['link']) : '';
        $link = $link_raw !== '' ? esc_url_raw($link_raw) : '';

        if ($company === '') {
            wp_send_json_error([
                'message' => __('Please enter a company name.', 'senna-finance'),
            ]);
        }

        $result = self::create_test_news($company, [
            'title' => $title,
            'description' => $description,
            'source' => $source,
            'link' => $link,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success($result);
    }

    public static function cli_create_test_news($args, $assoc_args)
    {
        $company_name = isset($assoc_args['company']) ? trim($assoc_args['company']) : 'Blackstone';
        if ($company_name === '') {
            WP_CLI::error('Please supply a valid company name using --company.');
        }

        $result = self::create_test_news($company_name, [
            'title' => isset($assoc_args['title']) ? trim($assoc_args['title']) : '',
            'description' => isset($assoc_args['description']) ? trim($assoc_args['description']) : '',
            'source' => isset($assoc_args['source']) ? trim($assoc_args['source']) : '',
            'link' => isset($assoc_args['link']) ? esc_url_raw($assoc_args['link']) : '',
        ]);

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        if (empty($result['matches'])) {
            WP_CLI::warning('No company matches detected in headline/summary. Check registry aliases.');
        } else {
            foreach ($result['matches'] as $match) {
                WP_CLI::line(sprintf(
                    'Match: %s (score %s, confidence %s) terms: %s',
                    $match['primary_name'] ?? 'n/a',
                    $match['relevance_score'] ?? 0,
                    $match['confidence'] ?? 'n/a',
                    isset($match['matched_terms']) ? implode(', ', (array) $match['matched_terms']) : ''
                ));
            }
        }

        WP_CLI::success(sprintf(
            'News article #%d created/ensured and linked to %s (ID %d). Link rows: %d',
            $result['news_id'],
            $result['company_name'],
            $result['company_id'],
            $result['link_count']
        ));

        WP_CLI::line(sprintf('Title: %s', get_the_title($result['news_id'])));
        WP_CLI::line(sprintf('Permalink: %s', $result['permalink']));
        WP_CLI::line(sprintf('Source URL: %s', get_post_meta($result['news_id'], '_sffc_news_source_url', true)));
    }

    private static function create_test_news($company_name, array $args = [])
    {
        $company_name = trim($company_name);
        $company_post = self::find_company_post($company_name);

        if (!$company_post) {
            return new WP_Error('sffc_news_company_not_found', sprintf(__('No published company profile found matching "%s".', 'senna-finance'), $company_name));
        }

        $canonical_name = class_exists('SFFC_Company_Title_Helper')
            ? SFFC_Company_Title_Helper::get_canonical_name($company_post)
            : $company_post->post_title;

        if ($canonical_name === '') {
            $canonical_name = $company_post->post_title;
        }

        if (class_exists('SFFC_Company_Title_Helper')) {
            SFFC_Company_Title_Helper::ensure_canonical_meta($company_post->ID, $canonical_name);
            SFFC_Company_Title_Helper::ensure_seo_title($company_post->ID, $canonical_name);
        }

        $defaults = [
            'title' => sprintf(__('%s Test Deal Announcement', 'senna-finance'), $canonical_name),
            'description' => sprintf(__('%s completes a landmark acquisition as part of the news linking test harness.', 'senna-finance'), $canonical_name),
            'source' => 'SFFC QA Harness',
            'link' => home_url('/sffc-news-test/' . sanitize_title($canonical_name) . '-' . time() . '/'),
        ];

        $args = wp_parse_args($args, $defaults);

        $news_payload = [
            'title' => $args['title'],
            'description' => $args['description'],
            'content' => $args['description'],
            'link' => $args['link'],
            'source' => $args['source'],
            'pubDate' => current_time('timestamp'),
        ];

        $linker = class_exists('SFFC_News_Company_Linker') ? SFFC_News_Company_Linker::get_instance() : null;
        if (!$linker) {
            return new WP_Error('sffc_news_linker_missing', __('News Company Linker is not available.', 'senna-finance'));
        }

        $diagnostics = $linker->diagnose_news_item($news_payload);
        $matches = isset($diagnostics['matches']) && is_array($diagnostics['matches']) ? $diagnostics['matches'] : array();

        $linker->process_news_item($news_payload, 'test_harness');
        $news_id = $linker->ensure_news_article($news_payload);

        if (!$news_id) {
            return new WP_Error('sffc_news_article_failed', __('Failed to create or locate the news article.', 'senna-finance'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sffc_company_news_links';
        $link_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE company_id = %d AND news_item_id = %d",
            $company_post->ID,
            $news_id
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, company_id, news_item_id, relevance_score, created_at FROM $table WHERE news_item_id = %d ORDER BY id DESC LIMIT 5",
            $news_id
        ), ARRAY_A);

        $last_error = $wpdb->last_error;

        if (class_exists('SFFC_Company_Profile_Aggregator')) {
            SFFC_Company_Profile_Aggregator::clear_profile_cache($company_post->ID);
        }

        $match_summaries = array();
        foreach ($matches as $match) {
            $match_summaries[] = array(
                'company_id' => isset($match['company_id']) ? (int) $match['company_id'] : 0,
                'primary_name' => $match['primary_name'] ?? '',
                'relevance_score' => $match['relevance_score'] ?? 0,
                'confidence' => $match['confidence'] ?? '',
                'matched_terms' => isset($match['matched_terms']) ? array_values((array) $match['matched_terms']) : array(),
            );
        }

        return [
            'news_id' => $news_id,
            'company_id' => $company_post->ID,
            'company_name' => $canonical_name,
            'link_count' => $link_count,
            'permalink' => get_permalink($news_id),
            'company_profile_url' => get_permalink($company_post->ID),
            'edit_link' => get_edit_post_link($news_id, 'raw'),
            'matches' => $match_summaries,
            'link_rows' => $rows,
            'db_error' => $last_error,
        ];
    }

    private static function find_company_post($company_name)
    {
        $query_name = $company_name;
        if (class_exists('SFFC_Company_Title_Helper')) {
            $stripped = SFFC_Company_Title_Helper::strip_seo_suffix($company_name);
            if ($stripped !== '') {
                $query_name = $stripped;
            }
        }

        $meta_query = [];
        if (class_exists('SFFC_Company_Title_Helper')) {
            $meta_query[] = [
                'key' => SFFC_Company_Title_Helper::META_CANONICAL_NAME,
                'value' => $query_name,
                'compare' => '=',
            ];
        }

        $args = [
            'post_type' => 'sffc_company',
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ];

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        } else {
            $args['title'] = $query_name;
        }

        $posts = get_posts($args);
        if (!empty($posts)) {
            return $posts[0];
        }

        $fallback = get_page_by_title($company_name, OBJECT, 'sffc_company');
        if ($fallback instanceof WP_Post) {
            return $fallback;
        }

        $slug = sanitize_title($query_name);
        $by_path = get_page_by_path($slug, OBJECT, 'sffc_company');
        return $by_path instanceof WP_Post ? $by_path : null;
    }
}

add_action('plugins_loaded', ['SFFC_News_Diagnostics', 'init']);
