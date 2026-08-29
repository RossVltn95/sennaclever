<?php
/**
 * Newsroom Google News Compatibility
 *
 * Handles all Google News requirements including:
 * - NewsArticle Schema.org structured data
 * - Author information with image and bio
 * - Google News meta tags
 * - News sitemap generation
 *
 * @package SennaFinanceCareer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Newsroom_Google_News {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Post types that should be treated as news articles
     */
    private $news_post_types = array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_markets', 'post');

    /**
     * Publisher information
     */
    private $publisher = array(
        'name' => 'MENA Careers Intelligence',
        'url' => '',
        'logo' => '',
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->publisher['url'] = home_url();
        $this->publisher['logo'] = $this->get_publisher_logo();

        // Add hooks
        add_action('wp_head', array($this, 'output_google_news_meta'), 5);
        add_action('wp_head', array($this, 'output_article_schema'), 10);
        add_filter('sffc_nrt_author_data', array($this, 'enhance_author_data'), 10, 2);

        // News Sitemap
        add_action('init', array($this, 'register_news_sitemap'));
        add_filter('wp_sitemaps_add_provider', array($this, 'maybe_add_news_sitemap'), 10, 2);
    }

    /**
     * Get publisher logo URL
     */
    private function get_publisher_logo() {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            return wp_get_attachment_image_url($custom_logo_id, 'full');
        }
        return plugin_dir_url(dirname(__FILE__)) . 'assets/images/senna-logo.png';
    }

    /**
     * Output Google News meta tags
     */
    public function output_google_news_meta() {
        if (!is_singular($this->news_post_types)) {
            return;
        }

        $post = get_queried_object();
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }

        $author_data = $this->get_enhanced_author_data($post->ID);
        $keywords = $this->get_article_keywords($post->ID);
        $section = $this->get_article_section($post->ID);

        // Google News meta tags
        echo "\n<!-- Google News Meta Tags -->\n";

        // Author name (required for Google News)
        echo '<meta name="author" content="' . esc_attr($author_data['name']) . '">' . "\n";

        // News keywords (helps with discovery)
        if (!empty($keywords)) {
            echo '<meta name="news_keywords" content="' . esc_attr(implode(', ', $keywords)) . '">' . "\n";
        }

        // Article section
        if (!empty($section)) {
            echo '<meta name="article:section" content="' . esc_attr($section) . '">' . "\n";
        }

        // Publication date
        echo '<meta name="article:published_time" content="' . esc_attr(get_the_date('c', $post)) . '">' . "\n";
        echo '<meta name="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post)) . '">' . "\n";

        // Original source (if aggregated content)
        $source_url = get_post_meta($post->ID, '_source_url', true);
        if (!empty($source_url)) {
            echo '<meta name="original-source" content="' . esc_url($source_url) . '">' . "\n";
        }

        // Syndication source
        echo '<meta name="syndication-source" content="' . esc_url(get_permalink($post)) . '">' . "\n";

        // Open Graph tags for social sharing (also used by Google)
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr(get_the_title($post)) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($this->get_article_excerpt($post)) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink($post)) . '">' . "\n";

        $featured_image = get_the_post_thumbnail_url($post, 'large');
        if ($featured_image) {
            echo '<meta property="og:image" content="' . esc_url($featured_image) . '">' . "\n";
        }

        echo '<meta property="article:author" content="' . esc_attr($author_data['name']) . '">' . "\n";
        echo '<meta property="article:publisher" content="' . esc_attr($this->publisher['name']) . '">' . "\n";
        echo "<!-- End Google News Meta Tags -->\n\n";
    }

    /**
     * Output NewsArticle schema.org structured data
     */
    public function output_article_schema() {
        if (!is_singular($this->news_post_types)) {
            return;
        }

        $post = get_queried_object();
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }

        $schema = $this->build_article_schema($post->ID);
        if (empty($schema)) {
            return;
        }

        echo "\n<!-- NewsArticle Schema.org Structured Data -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
        echo "<!-- End NewsArticle Schema -->\n\n";

        // Output FAQ Schema
        $faq_schema = $this->build_faq_schema();
        echo "\n<!-- FAQ Schema.org Structured Data -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
        echo "<!-- End FAQ Schema -->\n\n";
    }

    /**
     * Build FAQ schema explaining MENA Careers and MENA Careers
     */
    private function build_faq_schema() {
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array(
                array(
                    '@type' => 'Question',
                    'name' => 'What is MENA Careers Intelligence?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'MENA Careers Intelligence is a global business intelligence platform that delivers institutional-grade research, news, and analysis on deals, investments, and market trends across international markets. The platform provides real-time intelligence on global financial markets, corporate developments, and business opportunities.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'What markets does MENA Careers cover?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'MENA Careers provides comprehensive coverage of global markets including: Europe (London, Frankfurt, Paris), North America (New York, Toronto), Asia-Pacific (Singapore, Hong Kong, Tokyo), and emerging markets. We track investments, M&A deals, IPOs, market indices, and economic developments across these markets.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'What does MENA Careers offer for business intelligence?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'MENA Careers provides: (1) Global Business News - Breaking news on investments, deals, and market developments; (2) Deal Intelligence - Analysis of M&A transactions, private equity activity, and corporate finance; (3) Market Analysis - In-depth research on sectors, economic trends, and investment opportunities; (4) Professional Network - Connect with business professionals and decision-makers globally.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Who is MENA Careers designed for?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'MENA Careers is designed for business professionals, investors, and executives interested in global markets. This includes investment professionals, corporate development teams, fund analysts, private equity professionals, entrepreneurs, and anyone seeking business intelligence on international markets.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'What sectors does MENA Careers cover?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'MENA Careers covers all major sectors globally including: Technology and Fintech, Real Estate and Infrastructure, Energy and Renewables, Healthcare, Financial Services and Banking, Consumer and Retail, E-commerce, Manufacturing and Industrials. We provide sector-specific analysis tailored to global market dynamics.',
                    ),
                ),
            ),
        );
    }

    /**
     * Build comprehensive NewsArticle schema
     */
    public function build_article_schema($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $author_data = $this->get_enhanced_author_data($post_id);
        $featured_image = get_the_post_thumbnail_url($post, 'full');
        $permalink = get_permalink($post);
        $excerpt = $this->get_article_excerpt($post);
        $word_count = str_word_count(wp_strip_all_tags($post->post_content));
        $section = $this->get_article_section($post_id);
        $keywords = $this->get_article_keywords($post_id);

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            '@id' => $permalink . '#article',
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => $permalink,
            ),
            'headline' => get_the_title($post),
            'name' => get_the_title($post),
            'description' => $excerpt,
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'wordCount' => $word_count,
            'inLanguage' => get_bloginfo('language'),
            'isAccessibleForFree' => is_user_logged_in() ? true : 'False',
            'author' => $this->build_author_schema($author_data),
            'publisher' => $this->build_publisher_schema(),
        );

        // Add image if available
        if ($featured_image) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $featured_image,
                'width' => 1200,
                'height' => 630,
            );
            $schema['thumbnailUrl'] = $featured_image;
        }

        // Add article section
        if (!empty($section)) {
            $schema['articleSection'] = $section;
        }

        // Add keywords
        if (!empty($keywords)) {
            $schema['keywords'] = implode(', ', $keywords);
        }

        // Add article body (truncated for schema)
        $schema['articleBody'] = wp_trim_words(wp_strip_all_tags($post->post_content), 300, '...');

        // Add speakable for Google Assistant
        $schema['speakable'] = array(
            '@type' => 'SpeakableSpecification',
            'cssSelector' => array(
                '.nrt-article-title',
                '.nrt-exec-thesis',
                '.nrt-section-content p:first-of-type',
            ),
        );

        // Source attribution if available
        $source_url = get_post_meta($post_id, '_source_url', true);
        $source_name = get_post_meta($post_id, '_source_name', true);
        if (!empty($source_url)) {
            $schema['isBasedOn'] = array(
                '@type' => 'Article',
                'url' => $source_url,
                'name' => $source_name ?: parse_url($source_url, PHP_URL_HOST),
            );
        }

        return $schema;
    }

    /**
     * Build author schema with full bio and image
     */
    private function build_author_schema($author_data) {
        $author_schema = array(
            '@type' => 'Person',
            '@id' => $author_data['url'] . '#person',
            'name' => $author_data['name'],
            'url' => $author_data['url'],
        );

        // Add author image
        if (!empty($author_data['avatar'])) {
            $author_schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $author_data['avatar'],
                'width' => 96,
                'height' => 96,
            );
        }

        // Add job title
        if (!empty($author_data['title'])) {
            $author_schema['jobTitle'] = $author_data['title'];
        }

        // Add bio/description
        if (!empty($author_data['bio'])) {
            $author_schema['description'] = $author_data['bio'];
        }

        // Add social profiles
        if (!empty($author_data['social_profiles'])) {
            $author_schema['sameAs'] = $author_data['social_profiles'];
        }

        // Add works for
        $author_schema['worksFor'] = array(
            '@type' => 'Organization',
            'name' => $this->publisher['name'],
            'url' => $this->publisher['url'],
        );

        return $author_schema;
    }

    /**
     * Build publisher schema
     */
    private function build_publisher_schema() {
        return array(
            '@type' => 'Organization',
            '@id' => $this->publisher['url'] . '#organization',
            'name' => $this->publisher['name'],
            'url' => $this->publisher['url'],
            'logo' => array(
                '@type' => 'ImageObject',
                'url' => $this->publisher['logo'],
                'width' => 600,
                'height' => 60,
            ),
            'sameAs' => array(
                'https://twitter.com/SennaFinance',
                'https://www.linkedin.com/company/89210865/',
            ),
        );
    }

    /**
     * Get enhanced author data with bio and image
     */
    public function get_enhanced_author_data($post_id) {
        $post = get_post($post_id);

        // Default author data - Ropa Ushe (verified author for Google News)
        $default_author = array(
            'id' => 0,
            'name' => 'Ropa Ushe',
            'title' => 'Global Markets Analyst',
            'bio' => 'Ropa Ushe is a Global Markets Analyst at MENA Careers Intelligence, specializing in international business intelligence and market analysis. With extensive experience in financial services and global markets, Ropa provides institutional-grade research on deals, investments, corporate finance activity, and business trends across international markets.',
            'avatar' => 'https://joinsenna.com/wp-content/uploads/2024/08/RopaUshe-1.jpg',
            'url' => 'https://joinsenna.com/author/ropa-ushe',
            'social_profiles' => array(
                'https://www.linkedin.com/in/ropa-ushe-269188117/',
                'https://twitter.com/RopaUshe',
            ),
        );

        if (!$post) {
            return $default_author;
        }

        $author_id = $post->post_author;
        $author = get_userdata($author_id);

        if (!$author) {
            return $default_author;
        }

        // Get author meta
        $job_title = get_user_meta($author_id, 'job_title', true);
        $bio = get_user_meta($author_id, 'description', true);
        $avatar_url = get_avatar_url($author_id, array('size' => 200));

        // Get social profiles
        $social_profiles = array();
        $linkedin = get_user_meta($author_id, 'linkedin', true);
        $twitter = get_user_meta($author_id, 'twitter', true);

        if (!empty($linkedin)) {
            $social_profiles[] = $linkedin;
        }
        if (!empty($twitter)) {
            $social_profiles[] = 'https://twitter.com/' . ltrim($twitter, '@');
        }

        // If no avatar, use default
        if (empty($avatar_url) || strpos($avatar_url, 'gravatar.com/avatar/?') !== false) {
            $avatar_url = $default_author['avatar'];
        }

        return array(
            'id' => $author_id,
            'name' => $author->display_name ?: $default_author['name'],
            'title' => $job_title ?: $default_author['title'],
            'bio' => $bio ?: $default_author['bio'],
            'avatar' => $avatar_url,
            'url' => get_author_posts_url($author_id),
            'social_profiles' => !empty($social_profiles) ? $social_profiles : $default_author['social_profiles'],
        );
    }

    /**
     * Filter to enhance author data in newsroom terminal
     */
    public function enhance_author_data($author, $post_id) {
        return $this->get_enhanced_author_data($post_id);
    }

    /**
     * Get article excerpt
     */
    private function get_article_excerpt($post) {
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }
        return wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');
    }

    /**
     * Get article section/category
     */
    private function get_article_section($post_id) {
        // Try custom taxonomy first
        $sector = get_post_meta($post_id, '_sector', true);
        if (!empty($sector)) {
            return $sector;
        }

        // Try post categories
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            return $categories[0]->name;
        }

        // Try post type - Global markets focused labels
        $post_type = get_post_type($post_id);
        $type_labels = array(
            'sffc_pe_news' => 'Global Business News',
            'sffc_pe_deal' => 'Deal Intelligence',
            'sffc_pe_markets' => 'Markets Intelligence',
            'post' => 'Market News',
        );

        return $type_labels[$post_type] ?? 'Business & Markets';
    }

    /**
     * Get article keywords
     */
    private function get_article_keywords($post_id) {
        $keywords = array();

        // Get from post meta
        $meta_keywords = get_post_meta($post_id, '_keywords', true);
        if (!empty($meta_keywords)) {
            if (is_string($meta_keywords)) {
                $keywords = array_map('trim', explode(',', $meta_keywords));
            } elseif (is_array($meta_keywords)) {
                $keywords = $meta_keywords;
            }
        }

        // Get from tags
        $tags = get_the_tags($post_id);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
        }

        // Add sector
        $sector = get_post_meta($post_id, '_sector', true);
        if (!empty($sector)) {
            $keywords[] = $sector;
        }

        // Add region
        $region = get_post_meta($post_id, '_region', true);
        if (!empty($region)) {
            $keywords[] = $region;
        }

        // Default keywords if empty - Global markets and business focused
        if (empty($keywords)) {
            $keywords = array('Markets', 'Business', 'Finance', 'Investment', 'Trading', 'Banking', 'Economy', 'Corporate');
        }

        return array_unique(array_filter($keywords));
    }

    /**
     * Maybe add news sitemap provider to WordPress sitemaps
     *
     * @param object $provider The sitemap provider.
     * @param string $name     The provider name.
     * @return object The provider (unchanged).
     */
    public function maybe_add_news_sitemap($provider, $name) {
        // We use our own custom news sitemap via rewrite rules
        // Just return the provider unchanged
        return $provider;
    }

    /**
     * Register news sitemap
     */
    public function register_news_sitemap() {
        // Register rewrite rule for news sitemap
        add_rewrite_rule(
            'sitemap-news\.xml$',
            'index.php?sffc_news_sitemap=1',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'sffc_news_sitemap';
            return $vars;
        });

        add_action('template_redirect', array($this, 'render_news_sitemap'));
    }

    /**
     * Render news sitemap XML
     */
    public function render_news_sitemap() {
        if (!get_query_var('sffc_news_sitemap')) {
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        echo '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        // Get posts from last 48 hours (Google News requirement)
        $args = array(
            'post_type' => $this->news_post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'date_query' => array(
                array(
                    'after' => '48 hours ago',
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $posts = get_posts($args);

        foreach ($posts as $post) {
            $author_data = $this->get_enhanced_author_data($post->ID);
            $keywords = $this->get_article_keywords($post->ID);

            echo "  <url>\n";
            echo "    <loc>" . esc_url(get_permalink($post)) . "</loc>\n";
            echo "    <news:news>\n";
            echo "      <news:publication>\n";
            echo "        <news:name>" . esc_html($this->publisher['name']) . "</news:name>\n";
            echo "        <news:language>" . esc_html(substr(get_bloginfo('language'), 0, 2)) . "</news:language>\n";
            echo "      </news:publication>\n";
            echo "      <news:publication_date>" . esc_html(get_the_date('c', $post)) . "</news:publication_date>\n";
            echo "      <news:title>" . esc_html(get_the_title($post)) . "</news:title>\n";

            if (!empty($keywords)) {
                echo "      <news:keywords>" . esc_html(implode(', ', array_slice($keywords, 0, 10))) . "</news:keywords>\n";
            }

            echo "    </news:news>\n";
            echo "  </url>\n";
        }

        echo "</urlset>\n";
        exit;
    }

    /**
     * Generate schema JSON for AJAX-loaded stories
     * Used by the newsroom terminal when loading stories dynamically
     */
    public function get_story_schema_json($post_id) {
        $schema = $this->build_article_schema($post_id);
        return wp_json_encode($schema, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Render author box for article display
     */
    public function render_author_box($post_id) {
        $author = $this->get_enhanced_author_data($post_id);

        ob_start();
        ?>
        <div class="nrt-author-box" itemscope itemtype="https://schema.org/Person">
            <div class="nrt-author-box__image">
                <?php if (!empty($author['avatar'])) : ?>
                <img src="<?php echo esc_url($author['avatar']); ?>"
                     alt="<?php echo esc_attr($author['name']); ?>"
                     class="nrt-author-box__avatar"
                     itemprop="image"
                     width="80"
                     height="80"
                     loading="lazy">
                <?php else : ?>
                <div class="nrt-author-box__avatar-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <?php endif; ?>
            </div>
            <div class="nrt-author-box__content">
                <div class="nrt-author-box__header">
                    <a href="<?php echo esc_url($author['url']); ?>"
                       class="nrt-author-box__name"
                       itemprop="name">
                        <?php echo esc_html($author['name']); ?>
                    </a>
                    <?php if (!empty($author['title'])) : ?>
                    <span class="nrt-author-box__title" itemprop="jobTitle">
                        <?php echo esc_html($author['title']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($author['bio'])) : ?>
                <p class="nrt-author-box__bio" itemprop="description">
                    <?php echo esc_html(wp_trim_words($author['bio'], 30, '...')); ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($author['social_profiles'])) : ?>
                <div class="nrt-author-box__social">
                    <?php foreach ($author['social_profiles'] as $profile) : ?>
                        <?php
                        $icon = '';
                        if (strpos($profile, 'linkedin') !== false) {
                            $icon = '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
                        } elseif (strpos($profile, 'twitter') !== false || strpos($profile, 'x.com') !== false) {
                            $icon = '<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
                        }
                        ?>
                        <a href="<?php echo esc_url($profile); ?>"
                           class="nrt-author-box__social-link"
                           target="_blank"
                           rel="noopener"
                           itemprop="sameAs">
                            <?php echo $icon; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <meta itemprop="url" content="<?php echo esc_url($author['url']); ?>">
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the class
add_action('plugins_loaded', function() {
    SFFC_Newsroom_Google_News::get_instance();
});

/**
 * Helper function to get enhanced author data
 */
function sffc_get_google_news_author($post_id) {
    $google_news = SFFC_Newsroom_Google_News::get_instance();
    return $google_news->get_enhanced_author_data($post_id);
}

/**
 * Helper function to get article schema
 */
function sffc_get_article_schema($post_id) {
    $google_news = SFFC_Newsroom_Google_News::get_instance();
    return $google_news->build_article_schema($post_id);
}

/**
 * Helper function to render author box
 */
function sffc_render_author_box($post_id) {
    $google_news = SFFC_Newsroom_Google_News::get_instance();
    return $google_news->render_author_box($post_id);
}
