<?php
/**
 * Premium Article Content Renderer
 * SEO-optimized article content display for sophisticated finance publications
 * 
 * @package SennaCareers
 * @since 10.24.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Premium_Article_Renderer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
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
        // Register shortcode
        add_shortcode('sffc_premium_article', array($this, 'render_premium_article_shortcode'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add schema.org structured data
        add_action('wp_head', array($this, 'add_schema_markup'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (
            !is_a($post, 'WP_Post') ||
            !has_shortcode($post->post_content, 'sffc_premium_article')
        ) {
            return;
        }
        
        // Enqueue premium article styles
        wp_enqueue_style(
            'sffc-premium-article',
            SFFC_PLUGIN_URL . 'assets/css/premium-article.css',
            array(),
            SFFC_VERSION
        );
        
        // Enqueue premium article JavaScript
        wp_enqueue_script(
            'sffc-premium-article',
            SFFC_PLUGIN_URL . 'assets/js/premium-article.js',
            array('jquery'),
            SFFC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('sffc-premium-article', 'sffcPremiumArticle', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_premium_article_nonce'),
            'searchUrl' => home_url('/search/'), // URL where sffc_pe_search_results shortcode is used
            'strings' => array(
                'readingTime' => __('Reading time', 'senna-finance-career'),
                'minutes' => __('minutes', 'senna-finance-career'),
                'shareArticle' => __('Share article', 'senna-finance-career'),
                'bookmarkArticle' => __('Bookmark this article', 'senna-finance-career'),
            )
        ));
    }
    
    /**
     * Render premium article shortcode
     */
    public function render_premium_article_shortcode($atts) {
        global $post;
        
        // Ensure we have a valid post
        if (!$post || (!is_a($post, 'WP_Post') && !is_object($post))) {
            return '';
        }
        
        // Generate unique instance ID to allow multiple renders
        static $instance_counter = 0;
        $instance_counter++;
        $instance_id = 'sffc-premium-article-' . $instance_counter;
        
        $atts = shortcode_atts(array(
            'post_id' => '', // Allow specifying a specific post ID
            'style' => 'financial_times', // financial_times, bloomberg, wsj, economist
            'show_meta' => 'true',
            'show_reading_time' => 'true',
            'show_social_share' => 'true',
            'show_bookmark' => 'true',
            'enable_typography_animations' => 'true',
            'enable_reading_progress' => 'true',
            'schema_type' => 'NewsArticle', // NewsArticle, BlogPosting, FinancialService
        ), $atts);
        
        // If a specific post ID is provided, use that post instead of global $post
        if (!empty($atts['post_id'])) {
            $article_post = get_post(intval($atts['post_id']));
            if (!$article_post) {
                return '<p>Article not found.</p>';
            }
        } else {
            // Only use current post if it's not a job post type
            if (get_post_type($post) === 'sffc_job') {
                return '<!-- Premium article shortcode cannot be used on job post pages without specifying post_id -->';
            }
            $article_post = $post;
        }
        
        // Get post data for SEO optimization
        $post_title = $article_post->post_title;
        $post_excerpt = $article_post->post_excerpt;
        $post_content = $article_post->post_content;
        $post_date = get_the_date('c', $article_post);
        $post_modified = get_the_modified_date('c', $article_post);
        $author_name = get_the_author_meta('display_name', $article_post->post_author);
        $reading_time = $this->calculate_reading_time($post_content);
        $featured_image = get_the_post_thumbnail_url($article_post, 'large');
        
        // Add proper meta tags to head section
        $this->add_meta_tags_to_head($article_post, $atts, $post_title, $post_excerpt, $post_date, $post_modified, $author_name, $featured_image);
        
        // Generate unique IDs for this article
        $article_id = 'sffc-premium-article-' . $article_post->ID;
        $progress_id = 'sffc-reading-progress-' . $article_post->ID;
        
        ob_start();
        ?>
        
        <script>
            // Hide content exactly like pe_post_dashboard
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    const premiumArticleWrapper = document.querySelector('.sffc-premium-article-wrapper[data-post-id="<?php echo $article_post->ID; ?>"]');
                if (premiumArticleWrapper) {
                    // Only hide content if explicitly requested via attribute
                    if (premiumArticleWrapper.dataset.hideOtherContent === 'true') {
                        const parent = premiumArticleWrapper.parentElement;
                        if (parent) {
                            Array.from(parent.children).forEach(function(child) {
                                if (child !== premiumArticleWrapper && 
                                    child.copareDocumentPosition(premiumArticleWrapper) === Node.DOCUMENT_POSITION_FOLLOWING &&
                                    !child.classList.contains('sffc-job-opportunities-advanced')) {
                                    child.style.display = 'none';
                                }
                            });
                        }
                    }

                    // Only hide header elements if in takeover mode
                    if (premiumArticleWrapper.classList.contains('sffc-premium-article-takeover')) {
                        const hideSelectors = ['.entry-header', '.post-header'];
                        hideSelectors.forEach(function(selector) {
                            document.querySelectorAll(selector).forEach(function(el) {
                                // Don't hide elements inside other shortcodes
                                if (!el.closest('.sffc-job-opportunities-advanced') && 
                                    !el.closest('.sffc-premium-article-wrapper')) {
                                    el.style.visibility = 'hidden'; // Use visibility instead of display to preserve layout
                                }
                            });
                        });
                    }
                    
                    // Only hide duplicate content if explicitly in takeover mode with flag
                    if (premiumArticleWrapper.dataset.hideDuplicateContent === 'true') {
                        const duplicateSelectors = [
                            '.elementor-widget-theme-post-content',
                            '.elementor-post-content'
                        ];

                        duplicateSelectors.forEach(function(selector) {
                            const elements = document.querySelectorAll(selector);
                            elements.forEach(function(el) {
                                // Very conservative hiding - only hide obvious duplicates
                                if (!el.closest('.sffc-premium-article-wrapper') &&
                                    !el.closest('.sffc-job-opportunities-advanced') &&
                                    el.textContent.trim().length > 500 && // Higher threshold
                                    el.innerHTML.includes(premiumArticleWrapper.querySelector('.sffc-article-content')?.innerHTML?.substring(0, 100) || '')) {
                                    console.log('🎯 Premium Article: Hiding duplicate content element:', selector);
                                    el.style.display = 'none';
                                }
                            });
                        });
                    }
                }
                } catch (error) {
                    console.warn('Premium Article: Error in DOM manipulation:', error);
                }
            });
        </script>
        
        <?php if ($atts['enable_reading_progress'] === 'true'): ?>
        <div class="sffc-reading-progress-container">
            <div id="<?php echo esc_attr($progress_id); ?>" class="sffc-reading-progress-bar"></div>
        </div>
        <?php endif; ?>
        
        <div class="sffc-premium-article-wrapper" data-post-id="<?php echo esc_attr($article_post->ID); ?>">
            <article id="<?php echo esc_attr($article_id); ?>" class="sffc-premium-article sffc-premium-article-takeover sffc-style-<?php echo esc_attr($atts['style']); ?>" 
                     itemscope itemtype="https://schema.org/<?php echo esc_attr($atts['schema_type']); ?>">
            
            <!-- SEO structured data using microdata (valid in body) -->
            <div style="display:none;">
                <span itemprop="headline"><?php echo esc_html($post_title); ?></span>
                <span itemprop="description"><?php echo esc_html($post_excerpt); ?></span>
                <time itemprop="datePublished" datetime="<?php echo esc_attr($post_date); ?>"><?php echo esc_html(get_the_date('', $post)); ?></time>
                <time itemprop="dateModified" datetime="<?php echo esc_attr($post_modified); ?>"><?php echo esc_html(get_the_modified_date('', $post)); ?></time>
                <span itemprop="author"><?php echo esc_html($author_name); ?></span>
                <?php if ($featured_image): ?>
                <img itemprop="image" src="<?php echo esc_url($featured_image); ?>" alt="" style="display:none;">
                <?php endif; ?>
            </div>
            
            <!-- Compact Search Section (SEO-optimized, minimal space) -->
            <div class="sffc-premium-search-section" role="search" aria-label="<?php esc_attr_e('Search investment and asset management opportunities', 'senna-finance-career'); ?>">
                <?php echo $this->render_premium_compact_search(); ?>
            </div>
            
            <?php if ($atts['show_meta'] === 'true'): ?>
            <header class="sffc-article-header">
                <div class="sffc-article-meta">
                    <div class="sffc-meta-row sffc-meta-primary">
                        <span class="sffc-category-badge" itemprop="articleSection">
                            <?php 
                            $categories = get_the_category($post);
                            echo !empty($categories) ? esc_html($categories[0]->name) : __('Finance', 'senna-finance-career');
                            ?>
                        </span>
                        
                        <?php if ($atts['show_reading_time'] === 'true'): ?>
                        <span class="sffc-reading-time">
                            <svg class="sffc-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                            <?php echo esc_html($reading_time); ?> <?php _e('min read', 'senna-finance-career'); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="sffc-article-title" itemprop="headline">
                        <?php echo wp_kses_post($post_title); ?>
                    </h1>
                    
                    <div class="sffc-meta-row sffc-meta-secondary">
                        <div class="sffc-author-info" itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <span class="sffc-author-name" itemprop="name"><?php echo esc_html($author_name); ?></span>
                            <span class="sffc-meta-separator">•</span>
                            <time class="sffc-publish-date" datetime="<?php echo esc_attr($post_date); ?>" itemprop="datePublished">
                                <?php echo esc_html(get_the_date('F j, Y', $post)); ?>
                            </time>
                        </div>
                        
                        <?php if ($atts['show_social_share'] === 'true' || $atts['show_bookmark'] === 'true'): ?>
                        <div class="sffc-article-actions">
                            <?php if ($atts['show_social_share'] === 'true'): ?>
                            <button class="sffc-action-btn sffc-share-btn" title="<?php esc_attr_e('Share article', 'senna-finance-career'); ?>" aria-label="<?php esc_attr_e('Share this article', 'senna-finance-career'); ?>">
                                <svg class="sffc-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="18" cy="5" r="3"></circle>
                                    <circle cx="6" cy="12" r="3"></circle>
                                    <circle cx="18" cy="19" r="3"></circle>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                </svg>
                                <span class="sffc-action-label"><?php _e('Share', 'senna-finance-career'); ?></span>
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_bookmark'] === 'true'): ?>
                            <button class="sffc-action-btn sffc-bookmark-btn" title="<?php esc_attr_e('Bookmark article', 'senna-finance-career'); ?>" aria-label="<?php esc_attr_e('Bookmark this article', 'senna-finance-career'); ?>">
                                <svg class="sffc-icon sffc-bookmark-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
                                </svg>
                                <svg class="sffc-icon sffc-bookmark-icon-filled" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"></path>
                                </svg>
                                <span class="sffc-action-label"><?php _e('Save', 'senna-finance-career'); ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($featured_image): ?>
                <div class="sffc-article-hero">
                    <img src="<?php echo esc_url($featured_image); ?>" 
                         alt="<?php echo esc_attr($post_title); ?>"
                         class="sffc-hero-image"
                         itemprop="image">
                </div>
                <?php endif; ?>
            </header>
            <?php endif; ?>
            
            <div class="sffc-article-content" itemprop="articleBody">
                <?php
                // Process content safely exactly like pe_post_dashboard
                $content = $post->post_content;

                // Remove any style tags and their content
                $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

                // Remove any script tags and their content
                $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);

                // Apply WordPress content filters safely
                if (function_exists('apply_filters')) {
                    // Temporarily remove problematic filters that might add unwanted content
                    remove_filter('the_content', 'wpautop');
                    $content = apply_filters('the_content', $content);
                    add_filter('the_content', 'wpautop');
                }

                // Process content for typography enhancements
                $processed_content = $this->enhance_typography($content, $atts['enable_typography_animations'] === 'true');
                
                // Final cleaning with wp_kses_post to ensure safe HTML
                echo wp_kses_post($processed_content);
                ?>
            </div>
            
            <footer class="sffc-article-footer">
                <div class="sffc-footer-meta">
                    <div class="sffc-tags">
                        <?php
                        $tags = get_the_tags($post);
                        if ($tags) {
                            foreach ($tags as $tag) {
                                echo '<span class="sffc-tag" itemprop="keywords">' . esc_html($tag->name) . '</span>';
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="sffc-updated-date">
                        <?php if ($post_date !== $post_modified): ?>
                        <span class="sffc-updated-label"><?php _e('Last updated', 'senna-finance-career'); ?>:</span>
                        <time datetime="<?php echo esc_attr($post_modified); ?>" itemprop="dateModified">
                            <?php echo esc_html(get_the_modified_date('F j, Y', $post)); ?>
                        </time>
                        <?php endif; ?>
                    </div>
                </div>
            </footer>
        </article>
        </div> <!-- Close sffc-premium-article-wrapper -->
        
        <?php if ($atts['enable_typography_animations'] === 'true'): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize typography animations for this article
            if (typeof SFFCPremiumArticle !== 'undefined') {
                SFFCPremiumArticle.initTypographyAnimations('<?php echo esc_js($article_id); ?>');
            }
            
            <?php if ($atts['enable_reading_progress'] === 'true'): ?>
            // Initialize reading progress for this article
            if (typeof SFFCPremiumArticle !== 'undefined') {
                SFFCPremiumArticle.initReadingProgress('<?php echo esc_js($article_id); ?>', '<?php echo esc_js($progress_id); ?>');
            }
            <?php endif; ?>
        });
        </script>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Calculate reading time for content
     */
    private function calculate_reading_time($content) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        $reading_time = ceil($word_count / 200); // Average reading speed: 200 words per minute
        return max(1, $reading_time);
    }
    
    /**
     * Enhance typography with premium styling
     */
    private function enhance_typography($content, $enable_animations = true) {
        // Add premium typography classes
        $content = preg_replace('/<h([1-6])([^>]*)>/i', '<h$1$2 class="sffc-enhanced-heading">', $content);
        $content = preg_replace('/<p([^>]*)>/i', '<p$1 class="sffc-enhanced-paragraph">', $content);
        $content = preg_replace('/<blockquote([^>]*)>/i', '<blockquote$1 class="sffc-enhanced-blockquote">', $content);
        
        // Add animation classes if enabled
        if ($enable_animations) {
            $content = str_replace('class="sffc-enhanced-heading"', 'class="sffc-enhanced-heading sffc-animate-in"', $content);
            $content = str_replace('class="sffc-enhanced-paragraph"', 'class="sffc-enhanced-paragraph sffc-animate-in"', $content);
        }
        
        return $content;
    }
    
    /**
     * Render premium compact search for articles
     */
    private function render_premium_compact_search() {
        // Get search modes from PE search interface if available
        if (class_exists('SFFC_PE_Search_Interface')) {
            $search_interface = SFFC_PE_Search_Interface::get_instance();
            $search_modes = $search_interface->get_search_modes();
        } else {
            // Fallback modes for minimal functionality
            $search_modes = array(
                'jobs' => array(
                    'label' => __('Jobs', 'senna-finance-career'),
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
                    'placeholder' => __('Search PE jobs...', 'senna-finance-career')
                ),
                'insights' => array(
                    'label' => __('Insights', 'senna-finance-career'),
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
                    'placeholder' => __('Search insights...', 'senna-finance-career')
                )
            );
        }

        $current_mode = 'jobs'; // Default mode
        $current_mode_config = $search_modes[$current_mode];
        $placeholder = $current_mode_config['placeholder'];

        ob_start();
        ?>
        <div class="sffc-premium-search-wrapper">
            <div class="sffc-compact-search-container" data-active-mode="<?php echo esc_attr($current_mode); ?>">
                <div class="sffc-compact-search-bar">
                    <div class="sffc-search-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </div>

                    <input type="search"
                           class="sffc-search-input"
                           placeholder="<?php echo esc_attr($placeholder); ?>"
                           aria-label="<?php esc_attr_e('Search investment and asset management opportunities', 'senna-finance-career'); ?>"
                           data-mode="<?php echo esc_attr($current_mode); ?>">

                    <div class="sffc-search-actions">
                        <button type="button" class="sffc-search-clear" aria-label="<?php esc_attr_e('Clear search', 'senna-finance-career'); ?>" hidden>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        
                        <button type="button" class="sffc-search-submit" aria-label="<?php esc_attr_e('Search', 'senna-finance-career'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="sffc-compact-modes" role="tablist" aria-label="<?php esc_attr_e('Search categories', 'senna-finance-career'); ?>">
                    <?php foreach ($search_modes as $mode_key => $mode_config): 
                        $is_active = ($mode_key === $current_mode);
                    ?>
                        <button type="button"
                                class="sffc-mode-tab <?php echo $is_active ? 'active' : ''; ?>"
                                data-mode="<?php echo esc_attr($mode_key); ?>"
                                data-placeholder="<?php echo esc_attr($mode_config['placeholder']); ?>"
                                aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                            <span class="sffc-mode-icon"><?php echo $mode_config['icon']; ?></span>
                            <span class="sffc-mode-label"><?php echo esc_html($mode_config['label']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sffc-join-senna-wrapper">
                <?php echo $this->render_join_senna_button(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Join MENA Careers button
     */
    private function render_join_senna_button() {
        // Get the search results page URL (where sffc_pe_search_results shortcode is used)
        $search_page_url = home_url('/search/'); // Adjust this URL as needed
        
        ob_start();
        ?>
        <div class="sffc-join-senna-cta">
            <a href="<?php echo esc_url($search_page_url); ?>" class="sffc-join-senna-btn" role="button">
                <span><?php esc_html_e('Join MENA Careers', 'senna-finance-career'); ?></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12,5 19,12 12,19"></polyline>
                </svg>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add structured data for SEO
     */
    public function add_schema_markup() {
        global $post;
        
        // Only add on pages with our shortcode
        if (
            !is_singular() || 
            !is_a($post, 'WP_Post') ||
            !has_shortcode($post->post_content, 'sffc_premium_article')
        ) {
            return;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => get_the_title($post),
            'description' => get_the_excerpt($post) ?: wp_trim_words(strip_tags($post->post_content), 30),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
                'url' => get_author_posts_url($post->post_author)
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url() ?: home_url() . '/wp-content/themes/default/images/logo.png'
                )
            ),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'url' => get_permalink($post),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post)
            ),
            'wordCount' => str_word_count(strip_tags($post->post_content)),
            'timeRequired' => 'PT' . max(1, ceil(str_word_count(strip_tags($post->post_content)) / 200)) . 'M',
            'inLanguage' => get_locale(),
            'isAccessibleForFree' => true,
        );
        
        // Add featured image if available
        $featured_image = get_the_post_thumbnail_url($post, 'large');
        if ($featured_image) {
            $schema['image'] = $featured_image;
        }
        
        // Add categories as keywords
        $categories = get_the_category($post);
        if (!empty($categories)) {
            $schema['articleSection'] = $categories[0]->name;
            $schema['keywords'] = implode(', ', wp_list_pluck($categories, 'name'));
        }
        
        // Add tags as additional keywords
        $tags = get_the_tags($post);
        if (!empty($tags)) {
            $existing_keywords = isset($schema['keywords']) ? $schema['keywords'] . ', ' : '';
            $schema['keywords'] = $existing_keywords . implode(', ', wp_list_pluck($tags, 'name'));
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
    }
    
    /**
     * Add meta tags to head section properly
     */
    private function add_meta_tags_to_head($post, $atts, $post_title, $post_excerpt, $post_date, $post_modified, $author_name, $featured_image) {
        // Only add meta tags if we haven't already (prevent duplicates)
        static $meta_added = array();
        $post_key = $post->ID . '_' . md5(serialize($atts));
        
        if (isset($meta_added[$post_key])) {
            return;
        }
        $meta_added[$post_key] = true;
        
        // Add meta tags to head via wp_head action
        add_action('wp_head', function() use ($post, $post_title, $post_excerpt, $post_date, $post_modified, $author_name, $featured_image) {
            // Only add if we're on the same post
            if (!is_singular() || get_the_ID() !== $post->ID) {
                return;
            }
            
            echo "\n<!-- Premium Article Meta Tags -->\n";
            echo '<meta property="og:type" content="article">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($post_title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($post_excerpt) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url(get_permalink($post)) . '">' . "\n";
            if ($featured_image) {
                echo '<meta property="og:image" content="' . esc_url($featured_image) . '">' . "\n";
            }
            
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($post_title) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($post_excerpt) . '">' . "\n";
            if ($featured_image) {
                echo '<meta name="twitter:image" content="' . esc_url($featured_image) . '">' . "\n";
            }
            echo "<!-- End Premium Article Meta Tags -->\n\n";
        }, 5); // Priority 5 to run early but after theme
    }
}

// Initialize the class
SFFC_Premium_Article_Renderer::get_instance();
