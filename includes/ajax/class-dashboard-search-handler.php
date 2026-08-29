<?php
/**
 * Dashboard Search AJAX Handler
 * 
 * Handles search functionality for the PE dashboard
 * Searches across sffc_pe_news, sffc_pe_deal, and sffc_job post types
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Dashboard_Search_Handler {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_sffc_dashboard_search', array($this, 'handle_search'));
        add_action('wp_ajax_nopriv_sffc_dashboard_search', array($this, 'handle_search'));
    }
    
    /**
     * Handle search request
     */
    public function handle_search() {
        // Get search query
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        
        // Verify nonce (accept both nonce names for compatibility)
        if (!wp_verify_nonce($nonce, 'sffc_dashboard_nonce') && !wp_verify_nonce($nonce, 'sffc_public_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        if (empty($query)) {
            wp_send_json_error(array('message' => 'Search query is required'));
            return;
        }
        
        // Search parameters
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
        
        // Perform search
        $results = $this->search_posts($query, $per_page, $paged);
        
        // Format results
        $formatted_results = $this->format_results($results);
        
        wp_send_json_success(array(
            'results' => $formatted_results,
            'total' => count($results),
            'query' => $query
        ));
    }
    
    /**
     * Search posts across multiple post types
     */
    private function search_posts($query, $per_page = 20, $paged = 1) {
        $results = array();
        
        // Search PE News
        $news_args = array(
            'post_type' => 'sffc_pe_news',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'relevance date',
            'order' => 'DESC'
        );
        
        $news_query = new WP_Query($news_args);
        
        if ($news_query->have_posts()) {
            while ($news_query->have_posts()) {
                $news_query->the_post();
                $results[] = $this->format_news_item(get_post());
            }
            wp_reset_postdata();
        }
        
        // Search PE Deals
        $deals_args = array(
            'post_type' => 'sffc_pe_deal',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'relevance date',
            'order' => 'DESC'
        );
        
        $deals_query = new WP_Query($deals_args);
        
        if ($deals_query->have_posts()) {
            while ($deals_query->have_posts()) {
                $deals_query->the_post();
                $results[] = $this->format_deal_item(get_post());
            }
            wp_reset_postdata();
        }
        
        // Search Jobs
        $jobs_args = array(
            'post_type' => 'sffc_job',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'relevance date',
            'order' => 'DESC'
        );
        
        $jobs_query = new WP_Query($jobs_args);
        
        if ($jobs_query->have_posts()) {
            while ($jobs_query->have_posts()) {
                $jobs_query->the_post();
                $results[] = $this->format_job_item(get_post());
            }
            wp_reset_postdata();
        }
        
        // Sort results by relevance and date
        usort($results, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $results;
    }
    
    /**
     * Format news item for display
     */
    private function format_news_item($post) {
        $company = get_post_meta($post->ID, 'company', true);
        $sector = get_post_meta($post->ID, 'sector', true);
        $source = get_post_meta($post->ID, 'source', true);
        $source_url = get_post_meta($post->ID, 'source_url', true);
        
        return array(
            'id' => $post->ID,
            'type' => 'news',
            'title' => $post->post_title,
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
            'content' => $post->post_content,
            'company' => $company,
            'sector' => $sector,
            'source' => $source,
            'source_url' => $source_url,
            'date' => $post->post_date,
            'relative_time' => $this->get_relative_time($post->post_date),
            'pill_label' => 'PE News',
            'pill_class' => 'sffc-pill--news',
            'keywords' => $this->extract_keywords($post)
        );
    }
    
    /**
     * Format deal item for display
     */
    private function format_deal_item($post) {
        $deal_value = get_post_meta($post->ID, 'deal_value', true);
        $deal_type = get_post_meta($post->ID, 'deal_type', true);
        $target_company = get_post_meta($post->ID, 'target_company', true);
        $acquirer = get_post_meta($post->ID, 'acquirer', true);
        $sector = get_post_meta($post->ID, 'sector', true);
        
        return array(
            'id' => $post->ID,
            'type' => 'deal',
            'title' => $post->post_title,
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
            'content' => $post->post_content,
            'deal_value' => $deal_value,
            'deal_type' => $deal_type,
            'target_company' => $target_company,
            'acquirer' => $acquirer,
            'sector' => $sector,
            'date' => $post->post_date,
            'relative_time' => $this->get_relative_time($post->post_date),
            'pill_label' => 'Deal',
            'pill_class' => 'sffc-pill--deal',
            'keywords' => $this->extract_keywords($post)
        );
    }
    
    /**
     * Format job item for display
     */
    private function format_job_item($post) {
        $company = get_post_meta($post->ID, 'company_name', true);
        $location = get_post_meta($post->ID, 'location', true);
        $job_level = get_post_meta($post->ID, 'job_level', true);
        $job_function = get_post_meta($post->ID, 'job_function', true);
        $salary_range = get_post_meta($post->ID, 'salary_range', true);
        
        return array(
            'id' => $post->ID,
            'type' => 'job',
            'title' => $post->post_title,
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30),
            'content' => $post->post_content,
            'company' => $company,
            'location' => $location,
            'job_level' => $job_level,
            'job_function' => $job_function,
            'salary_range' => $salary_range,
            'date' => $post->post_date,
            'relative_time' => $this->get_relative_time($post->post_date),
            'pill_label' => 'Job',
            'pill_class' => 'sffc-pill--job',
            'keywords' => $this->extract_keywords($post)
        );
    }
    
    /**
     * Extract keywords from post
     */
    private function extract_keywords($post) {
        $keywords = array();
        
        // Get tags
        $tags = wp_get_post_tags($post->ID);
        foreach ($tags as $tag) {
            $keywords[] = $tag->name;
        }
        
        // Get categories
        $categories = wp_get_post_categories($post->ID);
        foreach ($categories as $cat_id) {
            $cat = get_category($cat_id);
            $keywords[] = $cat->name;
        }
        
        // Add custom field keywords
        $custom_keywords = get_post_meta($post->ID, 'keywords', true);
        if ($custom_keywords) {
            if (is_array($custom_keywords)) {
                $keywords = array_merge($keywords, $custom_keywords);
            } else {
                $keywords[] = $custom_keywords;
            }
        }
        
        return implode(',', array_unique($keywords));
    }
    
    /**
     * Get relative time
     */
    private function get_relative_time($date) {
        $timestamp = strtotime($date);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $diff . ' seconds';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' days';
        } else {
            return floor($diff / 604800) . ' weeks';
        }
    }
    
    /**
     * Format results for frontend display
     */
    private function format_results($results) {
        $formatted = array();
        
        foreach ($results as $result) {
            $formatted[] = array(
                'html' => $this->generate_card_html($result),
                'data' => $result
            );
        }
        
        return $formatted;
    }
    
    /**
     * Generate card HTML
     */
    private function generate_card_html($item) {
        $html = '<article class="sffc-feed-card" data-type="' . esc_attr($item['type']) . '" ';
        $html .= 'data-keywords="' . esc_attr($item['keywords']) . '" ';
        $html .= 'data-post-id="' . esc_attr($item['id']) . '">';
        
        $html .= '<div class="sffc-feed-card__header">';
        $html .= '<div class="sffc-feed-card__left">';
        $html .= '<span class="sffc-feed-pill ' . esc_attr($item['pill_class']) . '">' . esc_html($item['pill_label']) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="sffc-feed-card__right">';
        $html .= '<span class="sffc-meta-label">' . esc_html($item['relative_time']) . ' ago</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="sffc-feed-card__body">';
        $html .= '<h3 class="sffc-feed-title">' . esc_html($item['title']) . '</h3>';
        $html .= '<p class="sffc-feed-excerpt">' . esc_html($item['excerpt']) . '</p>';
        
        // Add meta information based on type
        if ($item['type'] === 'job' && $item['company']) {
            $html .= '<div class="sffc-feed-meta">';
            $html .= '<span class="sffc-meta-item"><strong>Company:</strong> ' . esc_html($item['company']) . '</span>';
            if ($item['location']) {
                $html .= '<span class="sffc-meta-item"><strong>Location:</strong> ' . esc_html($item['location']) . '</span>';
            }
            $html .= '</div>';
        } elseif ($item['type'] === 'deal' && ($item['target_company'] || $item['deal_value'])) {
            $html .= '<div class="sffc-feed-meta">';
            if ($item['target_company']) {
                $html .= '<span class="sffc-meta-item"><strong>Target:</strong> ' . esc_html($item['target_company']) . '</span>';
            }
            if ($item['deal_value']) {
                $html .= '<span class="sffc-meta-item"><strong>Value:</strong> ' . esc_html($item['deal_value']) . '</span>';
            }
            $html .= '</div>';
        } elseif ($item['type'] === 'news' && $item['company']) {
            $html .= '<div class="sffc-feed-meta">';
            $html .= '<span class="sffc-meta-item"><strong>Company:</strong> ' . esc_html($item['company']) . '</span>';
            if ($item['sector']) {
                $html .= '<span class="sffc-meta-item"><strong>Sector:</strong> ' . esc_html($item['sector']) . '</span>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</article>';
        
        return $html;
    }
}

// Initialize the handler
SFFC_Dashboard_Search_Handler::get_instance();