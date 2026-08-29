<?php
/**
 * Content Publisher
 * 
 * Handles automated publishing of SEO articles to WordPress
 * with full SEO optimization and scheduling
 * 
 * @package SennaCareers
 * @subpackage SEO
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Content_Publisher {
    
    /**
     * Publishing strategies
     */
    const PUBLISH_IMMEDIATE = 'immediate';
    const PUBLISH_SCHEDULED = 'scheduled';
    const PUBLISH_DRIP = 'drip';
    const PUBLISH_OPTIMAL = 'optimal';
    
    /**
     * Optimal publishing times (UTC)
     */
    const OPTIMAL_TIMES = array(
        'monday' => array('09:00', '14:00'),
        'tuesday' => array('10:00', '15:00'),
        'wednesday' => array('09:00', '14:00'),
        'thursday' => array('10:00', '15:00'),
        'friday' => array('09:00', '13:00'),
        'saturday' => array('10:00'),
        'sunday' => array('14:00')
    );
    
    /**
     * Singleton instance
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
        // Publishing hooks
        add_action('sffc_publish_article', array($this, 'publish_article'));
        add_action('sffc_publish_queue', array($this, 'process_publishing_queue'));
        
        // Schedule publishing queue processor
        if (!wp_next_scheduled('sffc_publish_queue')) {
            wp_schedule_event(time(), 'hourly', 'sffc_publish_queue');
        }
        
        // AJAX handlers
        add_action('wp_ajax_sffc_publish_article', array($this, 'ajax_publish_article'));
        add_action('wp_ajax_sffc_schedule_article', array($this, 'ajax_schedule_article'));
    }
    
    /**
     * Publish article to WordPress
     */
    public function publish_article($article_id, $options = array()) {
        global $wpdb;
        
        // Get article from database
        $article = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_seo_articles WHERE id = %d",
            $article_id
        ));
        
        if (!$article) {
            return array('success' => false, 'error' => 'Article not found');
        }
        
        // Merge options with defaults
        $defaults = array(
            'status' => 'publish',
            'author_id' => get_current_user_id() ?: 1,
            'category_id' => null,
            'tags' => array(),
            'featured_image' => null,
            'notify' => false,
            'social_share' => false
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Prepare post data
        $post_data = array(
            'post_title' => $article->title,
            'post_content' => $this->prepare_content($article->content),
            'post_excerpt' => $article->excerpt,
            'post_status' => $options['status'],
            'post_author' => $options['author_id'],
            'post_type' => 'post',
            'meta_input' => $this->prepare_meta_data($article)
        );
        
        // Add categories
        if ($options['category_id']) {
            $post_data['post_category'] = array($options['category_id']);
        } else {
            $post_data['post_category'] = $this->auto_select_categories($article);
        }
        
        // Add tags
        $post_data['tags_input'] = $this->prepare_tags($article, $options['tags']);
        
        // Create or update post
        if ($article->post_id) {
            $post_data['ID'] = $article->post_id;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'error' => $post_id->get_error_message()
            );
        }
        
        // Update article record
        $wpdb->update(
            $wpdb->prefix . 'sffc_seo_articles',
            array(
                'post_id' => $post_id,
                'status' => 'published',
                'published_date' => current_time('mysql')
            ),
            array('id' => $article_id)
        );
        
        // Handle featured image
        if ($options['featured_image']) {
            $this->set_featured_image($post_id, $options['featured_image']);
        } else {
            $this->auto_generate_featured_image($post_id, $article);
        }
        
        // Add schema markup
        if ($article->schema_markup) {
            $this->add_schema_markup($post_id, $article->schema_markup);
        }
        
        // Process internal links
        $this->process_internal_links($post_id);
        
        // Trigger post-publish actions
        $this->trigger_post_publish($post_id, $article, $options);
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'article_id' => $article_id
        );
    }
    
    /**
     * Prepare content for publishing
     */
    private function prepare_content($content) {
        // Replace internal link placeholders
        $content = $this->replace_link_placeholders($content);
        
        // Add read more tag for excerpts
        $content = $this->add_read_more_tag($content);
        
        // Optimize images
        $content = $this->optimize_content_images($content);
        
        // Add call-to-action if missing
        if (!strpos($content, 'class="cta"')) {
            $content = $this->add_default_cta($content);
        }
        
        // Clean up any remaining artifacts
        $content = $this->clean_content($content);
        
        return $content;
    }
    
    /**
     * Prepare meta data
     */
    private function prepare_meta_data($article) {
        $meta = array(
            '_yoast_wpseo_title' => $article->meta_title ?: $article->title,
            '_yoast_wpseo_metadesc' => $article->meta_description,
            '_yoast_wpseo_focuskw' => $article->focus_keyword,
            '_sffc_article_id' => $article->id,
            '_sffc_word_count' => $article->content_length,
            '_sffc_reading_time' => $article->reading_time,
            '_sffc_seo_score' => $article->seo_score,
            '_sffc_target_audience' => $article->target_audience,
            '_sffc_target_location' => $article->target_location
        );
        
        // Add secondary keywords for Yoast
        if ($article->secondary_keywords) {
            $keywords = json_decode($article->secondary_keywords, true);
            $meta['_yoast_wpseo_focuskeywords'] = json_encode(array_map(function($kw) {
                return array('keyword' => $kw, 'score' => 0);
            }, $keywords));
        }
        
        // Add schema markup if available
        if ($article->schema_markup) {
            $meta['_yoast_wpseo_schema_article_type'] = 'NewsArticle';
        }
        
        return $meta;
    }
    
    /**
     * Auto-select categories based on content
     */
    private function auto_select_categories($article) {
        $categories = array();
        
        // Map sectors to categories
        $sector_map = array(
            'technology' => 'Technology',
            'finance' => 'Finance',
            'private-equity' => 'Private Equity',
            'mergers-acquisitions' => 'M&A',
            'investment' => 'Investment',
            'markets' => 'Markets'
        );
        
        // Check article content for category keywords
        $content = strtolower($article->title . ' ' . $article->content);
        
        foreach ($sector_map as $keyword => $category_name) {
            if (strpos($content, $keyword) !== false) {
                $category = get_term_by('name', $category_name, 'category');
                if ($category) {
                    $categories[] = $category->term_id;
                }
            }
        }
        
        // Default to uncategorized if no matches
        if (empty($categories)) {
            $categories[] = 1;
        }
        
        return $categories;
    }
    
    /**
     * Prepare tags
     */
    private function prepare_tags($article, $additional_tags = array()) {
        $tags = $additional_tags;
        
        // Add focus keyword as tag
        if ($article->focus_keyword) {
            $tags[] = $article->focus_keyword;
        }
        
        // Add secondary keywords as tags
        if ($article->secondary_keywords) {
            $keywords = json_decode($article->secondary_keywords, true);
            $tags = array_merge($tags, $keywords);
        }
        
        // Extract company names from source articles
        if ($article->source_articles) {
            global $wpdb;
            $source_ids = json_decode($article->source_articles, true);
            
            if (!empty($source_ids)) {
                $placeholders = implode(',', array_fill(0, count($source_ids), '%d'));
                $sources = $wpdb->get_results($wpdb->prepare(
                    "SELECT companies_involved FROM {$wpdb->prefix}sffc_aggregated_news 
                     WHERE id IN ($placeholders)",
                    $source_ids
                ));
                
                foreach ($sources as $source) {
                    if ($source->companies_involved) {
                        $companies = json_decode($source->companies_involved, true);
                        $tags = array_merge($tags, $companies);
                    }
                }
            }
        }
        
        // Clean and deduplicate
        $tags = array_unique(array_filter($tags));
        
        // Limit to 10 tags
        return array_slice($tags, 0, 10);
    }
    
    /**
     * Replace link placeholders
     */
    private function replace_link_placeholders($content) {
        $replacements = array(
            '[INTERNAL_LINK:private-equity]' => '<a href="/category/private-equity/">private equity</a>',
            '[INTERNAL_LINK:mergers-acquisitions]' => '<a href="/category/ma/">mergers and acquisitions</a>',
            '[INTERNAL_LINK:investment-strategies]' => '<a href="/investment-strategies/">investment strategies</a>',
            '[INTERNAL_LINK:market-analysis]' => '<a href="/market-analysis/">market analysis</a>'
        );
        
        foreach ($replacements as $placeholder => $link) {
            $content = str_replace($placeholder, $link, $content);
        }
        
        // Remove any remaining placeholders
        $content = preg_replace('/\[INTERNAL_LINK:[^\]]+\]/', '', $content);
        
        return $content;
    }
    
    /**
     * Add read more tag
     */
    private function add_read_more_tag($content) {
        // Add after first 2 paragraphs if not present
        if (strpos($content, '<!--more-->') === false) {
            $paragraphs = explode('</p>', $content);
            if (count($paragraphs) > 3) {
                array_splice($paragraphs, 2, 0, '<!--more-->');
                $content = implode('</p>', $paragraphs);
            }
        }
        
        return $content;
    }
    
    /**
     * Process publishing queue
     */
    public function process_publishing_queue() {
        global $wpdb;
        
        // Get scheduled articles
        $articles = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sffc_seo_articles 
             WHERE status = 'scheduled' 
             AND scheduled_date <= NOW() 
             LIMIT 5"
        );
        
        foreach ($articles as $article) {
            $result = $this->publish_article($article->id);
            
            if (!$result['success']) {
                // Log error
                error_log('Failed to publish article ' . $article->id . ': ' . $result['error']);
            }
        }
    }
    
    /**
     * Schedule article for optimal time
     */
    public function schedule_article($article_id, $strategy = self::PUBLISH_OPTIMAL) {
        global $wpdb;
        
        $scheduled_date = null;
        
        switch ($strategy) {
            case self::PUBLISH_IMMEDIATE:
                return $this->publish_article($article_id);
                
            case self::PUBLISH_SCHEDULED:
                // Use provided date
                $scheduled_date = $options['scheduled_date'] ?? null;
                break;
                
            case self::PUBLISH_DRIP:
                // Find next available slot
                $scheduled_date = $this->get_next_drip_slot();
                break;
                
            case self::PUBLISH_OPTIMAL:
                // Find optimal time based on audience
                $scheduled_date = $this->get_optimal_publish_time($article_id);
                break;
        }
        
        if ($scheduled_date) {
            $wpdb->update(
                $wpdb->prefix . 'sffc_seo_articles',
                array(
                    'status' => 'scheduled',
                    'scheduled_date' => $scheduled_date
                ),
                array('id' => $article_id)
            );
            
            return array(
                'success' => true,
                'scheduled_date' => $scheduled_date
            );
        }
        
        return array(
            'success' => false,
            'error' => 'Could not determine scheduling date'
        );
    }
    
    /**
     * Get optimal publish time
     */
    private function get_optimal_publish_time($article_id) {
        global $wpdb;
        
        // Get article details
        $article = $wpdb->get_row($wpdb->prepare(
            "SELECT target_audience, target_location FROM {$wpdb->prefix}sffc_seo_articles 
             WHERE id = %d",
            $article_id
        ));
        
        // Determine timezone based on location
        $timezone_map = array(
            'united_kingdom' => 'Europe/London',
            'european_union' => 'Europe/Brussels',
            'united_states' => 'America/New_York',
            'germany' => 'Europe/Berlin',
            'france' => 'Europe/Paris',
            'switzerland' => 'Europe/Zurich'
        );
        
        $timezone = $timezone_map[$article->target_location] ?? 'UTC';
        
        // Get next optimal time slot
        $now = new DateTime('now', new DateTimeZone($timezone));
        $day = strtolower($now->format('l'));
        
        // Get today's optimal times
        $times = self::OPTIMAL_TIMES[$day] ?? array('10:00');
        
        foreach ($times as $time) {
            $publish_time = new DateTime($now->format('Y-m-d') . ' ' . $time, new DateTimeZone($timezone));
            
            if ($publish_time > $now) {
                // Convert to UTC for storage
                $publish_time->setTimezone(new DateTimeZone('UTC'));
                return $publish_time->format('Y-m-d H:i:s');
            }
        }
        
        // No time today, use tomorrow's first slot
        $tomorrow = clone $now;
        $tomorrow->modify('+1 day');
        $day = strtolower($tomorrow->format('l'));
        $times = self::OPTIMAL_TIMES[$day] ?? array('10:00');
        
        $publish_time = new DateTime($tomorrow->format('Y-m-d') . ' ' . $times[0], new DateTimeZone($timezone));
        $publish_time->setTimezone(new DateTimeZone('UTC'));
        
        return $publish_time->format('Y-m-d H:i:s');
    }
    
    /**
     * Get next drip slot
     */
    private function get_next_drip_slot() {
        global $wpdb;
        
        // Get last scheduled article
        $last_scheduled = $wpdb->get_var(
            "SELECT MAX(scheduled_date) FROM {$wpdb->prefix}sffc_seo_articles 
             WHERE status = 'scheduled'"
        );
        
        if ($last_scheduled) {
            // Add 24 hours to last scheduled
            $next_slot = new DateTime($last_scheduled);
            $next_slot->modify('+24 hours');
        } else {
            // Start tomorrow at 10 AM
            $next_slot = new DateTime('tomorrow 10:00');
        }
        
        return $next_slot->format('Y-m-d H:i:s');
    }
    
    /**
     * Auto-generate featured image
     */
    private function auto_generate_featured_image($post_id, $article) {
        // This would integrate with an image generation service
        // For now, select from media library based on keywords
        
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 1,
            's' => $article->focus_keyword
        ));
        
        if (!empty($images)) {
            set_post_thumbnail($post_id, $images[0]->ID);
        }
    }
    
    /**
     * Add schema markup
     */
    private function add_schema_markup($post_id, $schema_json) {
        // Add schema to post meta for output in header
        update_post_meta($post_id, '_sffc_schema_markup', $schema_json);
        
        // Hook to wp_head to output schema
        add_action('wp_head', function() use ($post_id, $schema_json) {
            if (is_single($post_id)) {
                echo '<script type="application/ld+json">' . $schema_json . '</script>';
            }
        });
    }
    
    /**
     * Process internal links
     */
    private function process_internal_links($post_id) {
        $content = get_post_field('post_content', $post_id);
        
        // Find related posts
        $related_posts = get_posts(array(
            'post_type' => 'post',
            'posts_per_page' => 5,
            'post__not_in' => array($post_id),
            'meta_key' => '_sffc_article_id',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        // Add links to related content
        foreach ($related_posts as $related) {
            $anchor = get_post_meta($related->ID, '_sffc_focus_keyword', true);
            if ($anchor && stripos($content, $anchor) !== false) {
                $link = '<a href="' . get_permalink($related->ID) . '">' . $anchor . '</a>';
                $content = preg_replace(
                    '/' . preg_quote($anchor, '/') . '/',
                    $link,
                    $content,
                    1
                );
            }
        }
        
        // Update post content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content
        ));
    }
    
    /**
     * Trigger post-publish actions
     */
    private function trigger_post_publish($post_id, $article, $options) {
        // Send notifications
        if ($options['notify']) {
            $this->send_publish_notification($post_id, $article);
        }
        
        // Schedule social sharing
        if ($options['social_share']) {
            wp_schedule_single_event(time() + 300, 'sffc_share_to_social', array($post_id));
        }
        
        // Update performance tracking
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'sffc_content_performance',
            array(
                'article_id' => $article->id,
                'date' => current_time('Y-m-d'),
                'created_at' => current_time('mysql')
            )
        );
        
        // Trigger custom hook
        do_action('sffc_article_published', $post_id, $article);
    }
    
    /**
     * Helper methods
     */
    
    private function optimize_content_images($content) {
        // Add lazy loading
        $content = str_replace('<img ', '<img loading="lazy" ', $content);
        
        // Add alt text if missing
        $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
            if (strpos($matches[1], 'alt=') === false) {
                return '<img' . $matches[1] . ' alt="Financial market analysis">';
            }
            return $matches[0];
        }, $content);
        
        return $content;
    }
    
    private function add_default_cta($content) {
        $cta = '<div class="cta">';
        $cta .= '<h3>Stay Informed</h3>';
        $cta .= '<p>Get the latest financial market insights and analysis delivered to your inbox.</p>';
        $cta .= '<a href="/subscribe/" class="button">Subscribe Now</a>';
        $cta .= '</div>';
        
        return $content . "\n\n" . $cta;
    }
    
    private function clean_content($content) {
        // Remove empty paragraphs
        $content = preg_replace('/<p[^>]*>[\s|&nbsp;]*<\/p>/', '', $content);
        
        // Fix double spaces
        $content = preg_replace('/\s+/', ' ', $content);
        
        return trim($content);
    }
    
    private function send_publish_notification($post_id, $article) {
        $post_url = get_permalink($post_id);
        $subject = 'New Article Published: ' . $article->title;
        $message = "A new article has been published:\n\n";
        $message .= "Title: {$article->title}\n";
        $message .= "URL: {$post_url}\n";
        $message .= "Target Audience: {$article->target_audience}\n";
        $message .= "SEO Score: {$article->seo_score}/100\n";
        
        wp_mail(get_option('admin_email'), $subject, $message);
    }
    
    /**
     * AJAX handlers
     */
    
    public function ajax_publish_article() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $article_id = intval($_POST['article_id'] ?? 0);
        
        if (!$article_id) {
            wp_send_json_error(array('message' => 'Invalid article ID'));
        }
        
        $result = $this->publish_article($article_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_schedule_article() {
        check_ajax_referer('sffc_ajax_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $article_id = intval($_POST['article_id'] ?? 0);
        $strategy = sanitize_text_field($_POST['strategy'] ?? self::PUBLISH_OPTIMAL);
        
        $result = $this->schedule_article($article_id, $strategy);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}

// Initialize
SFFC_Content_Publisher::get_instance();
?>