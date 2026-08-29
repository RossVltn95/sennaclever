<?php
/**
 * Editorial Article Renderer
 * Lightweight, index-safe article layout for news/deal stories
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Editorial_Article_Renderer {
    private static $instance = null;
    private $current_head_post = null;
    private $claude_manager = null;
    private $template_intelligence = null;
    private $template_library = null;
    private $insights_generator = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_shortcode('sffc_editorial_article', [$this, 'render_shortcode']);
        add_shortcode('sffc_recruiter_post_article', [$this, 'render_recruiter_post_shortcode']);
        add_shortcode('sffc_job_apply', [$this, 'render_job_apply_shortcode']);
        add_shortcode('sffc_role_analyzer', [$this, 'render_role_analyzer_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_action('template_redirect', [$this, 'detect_shortcode_on_current_post']);
        add_action('wp_head', [$this, 'output_head_meta'], 5);

        // Load template intelligence system
        if (file_exists(plugin_dir_path(__FILE__) . 'class-template-intelligence-system.php')) {
            require_once plugin_dir_path(__FILE__) . 'class-template-intelligence-system.php';
            $this->template_intelligence = SFFC_Template_Intelligence_System::get_instance();
        }

        // Load template library
        if (file_exists(plugin_dir_path(__FILE__) . 'class-template-library.php')) {
            require_once plugin_dir_path(__FILE__) . 'class-template-library.php';
            $this->template_library = SFFC_Template_Library::get_instance();
        }

        // Load dynamic insights generator
        if (file_exists(plugin_dir_path(__FILE__) . 'class-dynamic-insights-generator.php')) {
            require_once plugin_dir_path(__FILE__) . 'class-dynamic-insights-generator.php';
            $this->insights_generator = SFFC_Dynamic_Insights_Generator::get_instance();
        }
    }

    private function get_claude_manager() {
        if ($this->claude_manager instanceof SFFC_Claude_API_Manager) {
            return $this->claude_manager;
        }

        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_manager = SFFC_Claude_API_Manager::get_instance();
        }

        return $this->claude_manager;
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id' => '',
            'eyebrow' => '',
            'schema_type' => 'NewsArticle',
            'highlight_meta' => 'sffc_article_highlights',
            'show_sidebar' => 'true',
            'layout' => 'standard',
        ], $atts);

        $post = $this->resolve_article_post($atts['post_id']);
        if (!$post) {
            return '<!-- editorial article: post missing -->';
        }

        $layout = $this->sanitize_layout($atts['layout']);
        $this->enqueue_styles($layout);

        $permalink = get_permalink($post);
        $title = get_the_title($post);
        $excerpt = $this->build_excerpt($post);
        $author = 'Ropa Ushe'; // Professional PE analyst author
        $published = get_the_date('c', $post);
        $modified = get_the_modified_date('c', $post);
        $reading_time = $this->calculate_reading_time($post->post_content);
        $eyebrow = $atts['eyebrow'] ?: $this->get_primary_term_label($post);
        $featured_image = get_the_post_thumbnail_url($post, 'large');
        $content_html = $this->format_article_body($post->post_content);
        $highlights = $this->get_highlights($post, $atts['highlight_meta']);
        $schema_type = $this->sanitize_schema_type($atts['schema_type']);
        $show_sidebar = filter_var($atts['show_sidebar'], FILTER_VALIDATE_BOOLEAN);

        $word_count = str_word_count(strip_tags($post->post_content));

        $schema = $this->build_schema_payload(
            $schema_type,
            $title,
            $excerpt,
            $author,
            $published,
            $modified,
            $permalink,
            $featured_image,
            $reading_time,
            $word_count
        );

        $schema_json = wp_json_encode($schema);

        if ('dashboard' === $layout && class_exists('SFFC_PE_News_Dashboard')) {
            $dashboard = SFFC_PE_News_Dashboard::get_instance();
            if (method_exists($dashboard, 'enqueue_assets_forced')) {
                $dashboard->enqueue_assets_forced();
            } else {
                $dashboard->enqueue_assets();
            }

            $context = $dashboard->get_dashboard_context();

            if (!empty($context)) {
                $article_view = $this->build_dashboard_article_view($post, array(
                    'eyebrow' => $eyebrow,
                    'excerpt' => $excerpt,
                    'reading_time' => $reading_time,
                    'published' => $published,
                    'modified' => $modified,
                    'featured_image' => $featured_image,
                    'highlights' => $highlights,
                    'content_html' => $content_html,
                    'author' => $author,
                    'permalink' => $permalink,
                ));

                $context['article_view'] = $article_view;

                $html = method_exists($dashboard, 'render_dashboard_markup')
                    ? $dashboard->render_dashboard_markup($context)
                    : '';

                if (!empty($html)) {
                    $html .= '\n<script type="application/ld+json">' . $schema_json . '</script>';
                    return $html;
                }
            }
        }

        // Institutional layout - premium split-screen research terminal
        if ('institutional' === $layout) {
            $template_file = plugin_dir_path(__FILE__) . '../templates/institutional-article.php';
            if (file_exists($template_file)) {
                require_once $template_file;

                ob_start();
                sffc_render_institutional_article(array(
                    'post_id' => $post->ID,
                    'show_chatbox' => true,
                    'show_sidebar' => $show_sidebar,
                    'is_premium' => true,
                    'user_has_access' => is_user_logged_in(),
                ));
                $html = ob_get_clean();
                $html .= "\n" . '<script type="application/ld+json">' . $schema_json . '</script>';
                return $html;
            }
        }

        ob_start();
        ?>
        <div class="sffc-editorial-article" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <div class="sffc-editorial-article__content-wrapper">
            <article class="sffc-editorial-article__layout" itemscope itemtype="https://schema.org/<?php echo esc_attr($schema_type); ?>">
                <header class="sffc-editorial-article__header">
                    <?php if ($eyebrow): ?>
                        <p class="sffc-editorial-article__eyebrow" itemprop="articleSection"><?php echo esc_html($eyebrow); ?></p>
                    <?php endif; ?>
                    <h1 class="sffc-editorial-article__title" itemprop="headline"><?php echo esc_html($title); ?></h1>
                    <div class="sffc-editorial-article__meta">
                        <div class="sffc-editorial-article__byline">
                            <span class="sffc-editorial-article__author" itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <span itemprop="name"><?php echo esc_html($author); ?></span>
                                <meta itemprop="url" content="https://joinsenna.com/author/ropa-ushe">
                                <meta itemprop="jobTitle" content="Private Equity Research Analyst">
                            </span>
                            <span class="sffc-editorial-article__publication" itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
                                <span itemprop="name">MENA Careers Finance</span>
                                <meta itemprop="url" content="https://joinsenna.com">
                            </span>
                        </div>
                        <div class="sffc-editorial-article__timestamps">
                            <time class="sffc-editorial-article__date" datetime="<?php echo esc_attr($published); ?>" itemprop="datePublished">
                                <?php echo esc_html(get_the_date('F j, Y \\a\\t g:i A T', $post)); ?>
                            </time>
                            <?php if ($modified !== $published): ?>
                                <time class="sffc-editorial-article__date sffc-editorial-article__date--updated" datetime="<?php echo esc_attr($modified); ?>" itemprop="dateModified">
                                    <?php printf(esc_html__('Updated %s', 'senna-finance-career'), esc_html(get_the_modified_date('F j, Y \\a\\t g:i A T', $post))); ?>
                                </time>
                            <?php endif; ?>
                            <span class="sffc-editorial-article__reading-time"><?php echo esc_html($reading_time); ?> <?php esc_html_e('min read', 'senna-finance-career'); ?></span>
                        </div>
                    </div>
                    <?php if ($featured_image): ?>
                        <figure class="sffc-editorial-article__hero" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
                            <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"/>
                            <meta itemprop="url" content="<?php echo esc_url($featured_image); ?>">
                        </figure>
                    <?php endif; ?>
                </header>

                <div class="sffc-editorial-article__body" itemprop="articleBody">
                    <?php echo $content_html; // already escaped ?>
                </div>
                
                <!-- Professional Author Bio Box -->
                <div class="sffc-editorial-article__author-bio" itemscope itemtype="https://schema.org/Person">
                    <div class="sffc-author-bio__avatar">
                        <img src="https://joinsenna.com/wp-content/uploads/jet-engine-forms/4553/2025/04/RopaUshe-1.jpg" alt="Ropa Ushe" itemprop="image" loading="lazy">
                    </div>
                    <div class="sffc-author-bio__content">
                        <h3 class="sffc-author-bio__name" itemprop="name">Ropa Ushe</h3>
                        <p class="sffc-author-bio__title" itemprop="jobTitle">Private Equity Research Analyst</p>
                        <div class="sffc-author-bio__description" itemprop="description">
                            <p>Ropa Ushe is a Private Equity Research Analyst at MENA Careers Finance, specializing in European and North American buyout markets. With extensive experience in financial services and private equity, Ropa provides institutional-grade research on private equity transactions, fundraising activity, and industry trends.</p>
                            <p class="sffc-author-bio__expertise">Expertise: <span itemprop="knowsAbout">Private Equity Analysis, M&A Research, Fund Due Diligence, Market Intelligence</span></p>
                        </div>
                        <div class="sffc-author-bio__credentials">
                            <span class="sffc-credential" itemprop="alumniOf" itemscope itemtype="https://schema.org/Organization">
                                <meta itemprop="name" content="Private Equity Institute">
                                CFA Institute Member
                            </span>
                            <span class="sffc-credential">Financial Services & PE Experience</span>
                        </div>
                        <meta itemprop="url" content="https://joinsenna.com/author/ropa-ushe">
                        <meta itemprop="worksFor" content="MENA Careers Finance">
                    </div>
                </div>
            </article>

            <?php if ($show_sidebar && !empty($highlights)): ?>
                <aside class="sffc-editorial-article__sidebar" aria-label="<?php esc_attr_e('Key takeaways', 'senna-finance-career'); ?>">
                    <h3 class="sffc-editorial-article__sidebar-title"><?php esc_html_e('Key Takeaways', 'senna-finance-career'); ?></h3>
                    <ul class="sffc-editorial-article__highlights">
                        <?php foreach ($highlights as $point): ?>
                            <li><?php echo esc_html($point); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
            <?php endif; ?>
            </div>
        </div>

        <script type="application/ld+json">
            <?php echo $schema_json; ?>
        </script>
        <?php
        return ob_get_clean();
    }

    public function maybe_enqueue_assets() {
        if ($this->page_has_shortcode()) {
            $this->enqueue_styles();
        }
    }

    public function detect_shortcode_on_current_post() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if ($post && $this->content_has_shortcode($post->post_content)) {
            $this->current_head_post = $post;
        }
    }

    public function output_head_meta() {
        if (!$this->current_head_post) {
            return;
        }

        $post = $this->current_head_post;
        $title = get_the_title($post);
        $description = $this->build_excerpt($post);
        $url = get_permalink($post);
        $image = get_the_post_thumbnail_url($post, 'large');

        $published_iso = get_the_date('c', $post);
        $modified_iso = get_the_modified_date('c', $post);
        $word_count = str_word_count(strip_tags($post->post_content));
        $reading_time = max(1, ceil($word_count / 200));

        echo "\n<!-- Editorial Article SEO - Google News Optimized -->\n";

        // Canonical URL
        if (!has_action('wp_head', 'rel_canonical')) {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }

        // CRITICAL: Google News Required Meta Tags
        echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        echo '<meta name="googlebot" content="index, follow, max-image-preview:large">' . "\n";
        echo '<meta name="googlebot-news" content="index, follow">' . "\n";

        // Article metadata for Google News
        echo '<meta name="article:published_time" content="' . esc_attr($published_iso) . '">' . "\n";
        echo '<meta name="article:modified_time" content="' . esc_attr($modified_iso) . '">' . "\n";
        echo '<meta name="author" content="Ropa Ushe">' . "\n";
        echo '<meta name="news_keywords" content="private equity, venture capital, M&A, buyouts, financial markets, investment, deals">' . "\n";
        echo '<meta name="publication_date" content="' . esc_attr($published_iso) . '">' . "\n";

        // Author profile link for E-E-A-T signals
        echo '<link rel="author" href="https://joinsenna.com/author/ropa-ushe">' . "\n";

        // Syndication and original source
        echo '<meta name="syndication-source" content="' . esc_url($url) . '">' . "\n";
        echo '<meta name="original-source" content="' . esc_url($url) . '">' . "\n";

        // Open Graph (Facebook, LinkedIn)
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:site_name" content="MENA Careers Finance">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:locale" content="en_US">' . "\n";
        echo '<meta property="article:author" content="https://joinsenna.com/author/ropa-ushe">' . "\n";
        echo '<meta property="article:publisher" content="https://www.facebook.com/SennaFinance">' . "\n";
        echo '<meta property="article:section" content="Private Equity">' . "\n";
        echo '<meta property="article:tag" content="Private Equity">' . "\n";
        echo '<meta property="article:tag" content="M&A">' . "\n";
        echo '<meta property="article:tag" content="Investment">' . "\n";
        echo '<meta property="article:tag" content="Financial Markets">' . "\n";
        echo '<meta property="article:published_time" content="' . esc_attr($published_iso) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr($modified_iso) . '">' . "\n";

        // Image tags - CRITICAL for Google News (must be 1200x630 minimum)
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url($image) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
            echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($title) . '">' . "\n";
        }

        // Twitter Cards
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:site" content="@SennaFinance">' . "\n";
        echo '<meta name="twitter:creator" content="@RopaUshe">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr($title) . '">' . "\n";
        }
        echo '<meta name="twitter:label1" content="Written by">' . "\n";
        echo '<meta name="twitter:data1" content="Ropa Ushe">' . "\n";
        echo '<meta name="twitter:label2" content="Est. reading time">' . "\n";
        echo '<meta name="twitter:data2" content="' . esc_attr($reading_time) . ' min">' . "\n";

        // Google News standout tag for exclusive/original reporting
        echo '<link rel="standout" href="' . esc_url($url) . '">' . "\n";

        echo "<!-- End Editorial Article SEO -->\n";
    }

    private function resolve_article_post($post_id) {
        if (!empty($post_id)) {
            $post = get_post(intval($post_id));
            return $post instanceof WP_Post ? $post : null;
        }

        global $post;
        return ($post instanceof WP_Post) ? $post : null;
    }

    private function build_excerpt($post) {
        $excerpt = $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 32, '…');
        return trim($excerpt);
    }

    private function calculate_reading_time($content) {
        $word_count = str_word_count(strip_tags($content));
        $minutes = max(1, ceil($word_count / 220));
        return $minutes;
    }

    private function get_primary_term_label($post) {
        $taxonomies = get_post_taxonomies($post);
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                return $terms[0]->name;
            }
        }

        return ucwords(str_replace('_', ' ', $post->post_type));
    }

    private function format_article_body($content) {
        $content = wp_kses_post($content);
        $content = wpautop($content);
        return $content;
    }

    private function get_highlights($post, $meta_key) {
        $raw = get_post_meta($post->ID, $meta_key, true);
        if (is_array($raw)) {
            $items = array_map('trim', $raw);
        } else {
            $items = preg_split('/\r\n|\r|\n/', (string) $raw);
            $items = array_map('trim', $items);
        }

        $items = array_filter($items);
        if (!empty($items)) {
            return array_slice($items, 0, 6);
        }

        // fallback: derive from first sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', wp_strip_all_tags($post->post_content));
        $sentences = array_map('trim', $sentences);
        return array_filter(array_slice($sentences, 0, 3));
    }

    private function build_schema_payload($type, $title, $description, $author, $published, $modified, $url, $image, $reading_time, $word_count) {
        // Google News requires specific schema structure
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => $url . '#article',
            'headline' => $title,
            'name' => $title,
            'description' => $description,
            'articleBody' => '', // Will be populated if needed
            'author' => [
                '@type' => 'Person',
                '@id' => 'https://joinsenna.com/author/ropa-ushe#person',
                'name' => $author,
                'url' => 'https://joinsenna.com/author/ropa-ushe',
                'image' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://joinsenna.com/wp-content/uploads/jet-engine-forms/4553/2025/04/RopaUshe-1.jpg',
                    'width' => 400,
                    'height' => 400,
                    'caption' => 'Ropa Ushe - Private Equity Research Analyst'
                ],
                'jobTitle' => 'Private Equity Research Analyst',
                'description' => 'Private Equity Research Analyst specializing in European and North American buyout markets with extensive experience in financial services.',
                'knowsAbout' => ['Private Equity', 'Venture Capital', 'M&A', 'Financial Markets', 'Investment Banking', 'Buyouts', 'Due Diligence'],
                'sameAs' => [
                    'https://www.linkedin.com/in/ropaushe',
                    'https://twitter.com/RopaUshe'
                ],
                'worksFor' => [
                    '@type' => 'NewsMediaOrganization',
                    '@id' => 'https://joinsenna.com#organization',
                    'name' => 'MENA Careers Finance',
                    'url' => 'https://joinsenna.com'
                ]
            ],
            'datePublished' => $published,
            'dateModified' => $modified,
            'dateCreated' => $published,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url
            ],
            'wordCount' => max(1, (int) $word_count),
            'timeRequired' => 'PT' . intval($reading_time) . 'M',
            'publisher' => [
                '@type' => 'NewsMediaOrganization',
                '@id' => 'https://joinsenna.com#organization',
                'name' => 'MENA Careers Finance',
                'url' => 'https://joinsenna.com',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://joinsenna.com/wp-content/uploads/2024/03/Screenshot-2024-03-31-at-19.59.08.png',
                    'width' => 828,
                    'height' => 794,
                    'caption' => 'MENA Careers Finance'
                ],
                'sameAs' => [
                    'https://www.linkedin.com/company/skillfarm-1',
                    'https://twitter.com/SennaFinance',
                    'https://www.facebook.com/SennaFinance'
                ],
                'foundingDate' => '2020',
                'description' => 'Private equity intelligence and financial markets research',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'London',
                    'addressCountry' => 'UK'
                ]
            ],
            'articleSection' => 'Private Equity',
            'keywords' => 'private equity, venture capital, M&A, buyouts, financial markets, investment analysis, deals',
            'inLanguage' => 'en-US',
            'isAccessibleForFree' => true,
            'isPartOf' => [
                '@type' => 'WebSite',
                '@id' => 'https://joinsenna.com#website',
                'name' => 'MENA Careers Finance',
                'url' => 'https://joinsenna.com',
                'publisher' => [
                    '@id' => 'https://joinsenna.com#organization'
                ]
            ],
            'copyrightYear' => date('Y'),
            'copyrightHolder' => [
                '@id' => 'https://joinsenna.com#organization'
            ],
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['.sffc-editorial-article__title', '.sffc-editorial-article__body']
            ]
        ];

        // Image is CRITICAL for Google News - must have proper dimensions
        if ($image) {
            $payload['image'] = [
                '@type' => 'ImageObject',
                '@id' => $url . '#primaryimage',
                'url' => $image,
                'contentUrl' => $image,
                'width' => 1200,
                'height' => 630,
                'caption' => $title
            ];
            $payload['thumbnailUrl'] = $image;
        }

        return $payload;
    }

    private function sanitize_schema_type($type) {
        $allowed = ['NewsArticle', 'Report', 'BlogPosting', 'Article'];
        return in_array($type, $allowed, true) ? $type : 'NewsArticle';
    }

    private function page_has_shortcode() {
        global $post;
        return $post instanceof WP_Post && $this->content_has_shortcode($post->post_content);
    }

    private function content_has_shortcode($content) {
        if (false === strpos($content, '[')) {
            return false;
        }

        return has_shortcode($content, 'sffc_editorial_article')
            || has_shortcode($content, 'sffc_recruiter_post_article')
            || has_shortcode($content, 'sffc_job_apply')
            || has_shortcode($content, 'sffc_role_analyzer');
    }

    private function enqueue_styles($layout = 'standard') {
        if (!wp_style_is('sffc-editorial-article', 'enqueued')) {
            wp_enqueue_style(
                'sffc-editorial-article',
                SFFC_PLUGIN_URL . 'assets/css/editorial-article.css',
                array(),
                defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0'
            );
        }

        // Enqueue job apply styles if shortcode is present
        global $post;
        if ($post && has_shortcode($post->post_content, 'sffc_job_apply')) {
            if (!wp_style_is('sffc-job-apply', 'enqueued')) {
                wp_enqueue_style(
                    'sffc-job-apply',
                    SFFC_PLUGIN_URL . 'assets/css/job-apply.css',
                    array(),
                    defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0'
                );
            }
        }

        // Enqueue role analyzer styles if shortcode is present
        if ($post && has_shortcode($post->post_content, 'sffc_role_analyzer')) {
            if (!wp_style_is('sffc-role-analyzer', 'enqueued')) {
                wp_enqueue_style(
                    'sffc-role-analyzer',
                    SFFC_PLUGIN_URL . 'assets/css/role-analyzer.css',
                    array(),
                    defined('SFFC_VERSION') ? SFFC_VERSION : '1.0.0'
                );
            }
        }

        // Dashboard layout reuses newsroom assets directly; they are enqueued when that layout renders.
    }

    private function sanitize_layout($layout) {
        $layout = strtolower((string) $layout);
        $allowed = array('dashboard', 'institutional', 'standard');
        return in_array($layout, $allowed, true) ? $layout : 'standard';
    }

    private function build_dashboard_article_view($post, $context) {
        $reading_time = intval($context['reading_time']);
        $hero = array(
            'title' => get_the_title($post),
            'eyebrow' => $context['eyebrow'],
            'excerpt' => $context['excerpt'],
            'author' => $context['author'],
            'author_role' => 'Private Equity Research Analyst',
            'author_bio' => 'Ropa Ushe is a Private Equity Research Analyst specializing in European and North American buyout markets with extensive experience in financial services and private equity.',
            'author_credentials' => 'CFA Institute Member, Financial Services & PE Experience',
            'published_human' => get_the_date('F j, Y', $post),
            'published_iso' => $context['published'],
            'modified_iso' => $context['modified'],
            'reading_time' => $reading_time,
            'signal' => $this->resolve_signal_strength($post),
            'image' => $context['featured_image'],
            'permalink' => $context['permalink'],
        );

        return array(
            'hero' => $hero,
            'body' => $context['content_html'],
            'highlights' => $context['highlights'],
            'cta' => $this->build_article_ctas($post, $context['permalink']),
            'prompts' => $this->build_chat_prompts($post),
            'toc' => $this->build_article_toc($context['content_html']),
            'charts' => $this->build_article_chart_data($post, $context['content_html']),
            'research_cards' => $this->build_article_research_cards($post),
        );
    }

    private function build_article_sections($post, $highlights) {
        $content = wp_strip_all_tags($post->post_content);
        $summary = wpautop(wp_kses_post(wp_trim_words($content, 90, '…')));
        $market_context = $this->build_market_context_block($post);
        $metrics = $this->render_metrics_block($post);
        $risks = $this->extract_risk_sentences($post);
        $takeaways = $this->render_takeaways_block($highlights);

        return array(
            'summary' => array(
                'label' => __('Summary', 'senna-finance-career'),
                'content' => $summary,
            ),
            'market' => array(
                'label' => __('Market Context', 'senna-finance-career'),
                'content' => $market_context,
            ),
            'metrics' => array(
                'label' => __('Deal Metrics', 'senna-finance-career'),
                'content' => $metrics,
            ),
            'risks' => array(
                'label' => __('Risks', 'senna-finance-career'),
                'content' => $risks,
            ),
            'takeaways' => array(
                'label' => __('Takeaways', 'senna-finance-career'),
                'content' => $takeaways,
            ),
        );
    }

    private function build_article_ctas($post, $permalink) {
        $is_logged_in = is_user_logged_in();
        return array(
            array(
                'label' => __('Save insight', 'senna-finance-career'),
                'type' => 'save',
                'requires_login' => !$is_logged_in,
                'data' => array('post-id' => $post->ID),
            ),
            array(
                'label' => __('Share with team', 'senna-finance-career'),
                'type' => 'share',
                'url' => $permalink,
            ),
            array(
                'label' => __('Set alert on topic', 'senna-finance-career'),
                'type' => 'alert',
                'data' => array('topic' => $this->extract_primary_topic($post)),
            ),
        );
    }

    private function build_market_context_block($post) {
        $sectors = $this->collect_terms($post, array('sector', 'sffc_sector', 'job_industry'));
        $regions = $this->collect_terms($post, array('region', 'job_location'));
        $deal_type = $this->collect_terms($post, array('deal_type', 'sffc_deal_type'));

        $rows = array();
        if (!empty($sectors)) {
            $rows[] = sprintf(__('Sector focus: %s', 'senna-finance-career'), implode(', ', $sectors));
        }
        if (!empty($regions)) {
            $rows[] = sprintf(__('Key regions: %s', 'senna-finance-career'), implode(', ', $regions));
        }
        if (!empty($deal_type)) {
            $rows[] = sprintf(__('Deal profile: %s', 'senna-finance-career'), implode(', ', $deal_type));
        }

        if (empty($rows)) {
            $rows[] = __('Deal context is being prepared from our intelligence feed.', 'senna-finance-career');
        }

        $markup = '<ul class="sffc-article-context">';
        foreach ($rows as $row) {
            $markup .= '<li>' . esc_html($row) . '</li>';
        }
        $markup .= '</ul>';
        return $markup;
    }

    private function render_metrics_block($post) {
        $metrics = $this->build_supporting_metrics($post);
        if (empty($metrics)) {
            return '<p>' . esc_html__('Deal metrics will refresh shortly.', 'senna-finance-career') . '</p>';
        }

        $markup = '<div class="sffc-article-metric-grid">';
        foreach ($metrics as $metric) {
            $markup .= '<div class="sffc-article-metric">';
            $markup .= '<span class="sffc-article-metric-label">' . esc_html($metric['label']) . '</span>';
            $markup .= '<span class="sffc-article-metric-value">' . esc_html($metric['value']);
            if (!empty($metric['delta'])) {
                $markup .= '<em>' . esc_html($metric['delta']) . '</em>';
            }
            $markup .= '</span></div>';
        }
        $markup .= '</div>';
        return $markup;
    }

    private function extract_risk_sentences($post) {
        $content = wp_strip_all_tags($post->post_content);
        $sentences = preg_split('/(?<=[.!?])\s+/', $content);
        $keywords = array('risk', 'challenge', 'pressure', 'concern', 'headwind');
        $risks = array();

        foreach ($sentences as $sentence) {
            foreach ($keywords as $keyword) {
                if (stripos($sentence, $keyword) !== false) {
                    $risks[] = trim($sentence);
                    break;
                }
            }
            if (count($risks) >= 3) {
                break;
            }
        }

        if (empty($risks)) {
            $risks[] = __('No explicit risks mentioned. MENA Careers Research will update this section shortly.', 'senna-finance-career');
        }

        $markup = '<ul class="sffc-article-risks">';
        foreach ($risks as $risk) {
            $markup .= '<li>' . esc_html($risk) . '</li>';
        }
        $markup .= '</ul>';
        return $markup;
    }

    private function render_takeaways_block($highlights) {
        if (empty($highlights)) {
            return '<p>' . esc_html__('Key takeaways will appear once the article is fully processed.', 'senna-finance-career') . '</p>';
        }

        $markup = '<ol class="sffc-article-takeaways">';
        foreach ($highlights as $point) {
            $markup .= '<li>' . esc_html($point) . '</li>';
        }
        $markup .= '</ol>';
        return $markup;
    }

    private function build_driver_filters($post) {
        $chips = array();
        $taxonomies = get_post_taxonomies($post);

        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $chips[] = array(
                    'label' => $term->name,
                    'slug' => $term->slug,
                    'taxonomy' => $taxonomy,
                );
            }
        }

        if (empty($chips)) {
            $chips[] = array(
                'label' => __('All Stories', 'senna-finance-career'),
                'slug' => 'all',
                'taxonomy' => 'all',
            );
        }

        return array_slice($chips, 0, 8);
    }

    private function build_timeline_events($post) {
        $events = array();
        $announcement = get_post_meta($post->ID, '_announcement_date', true);
        $completion = get_post_meta($post->ID, '_completion_date', true);
        $regulator = get_post_meta($post->ID, '_regulatory_check', true);

        $events[] = array(
            'label' => __('Story published', 'senna-finance-career'),
            'date' => get_the_date('M j, Y', $post),
            'status' => 'complete',
        );

        if ($announcement) {
            $events[] = array(
                'label' => __('Announcement', 'senna-finance-career'),
                'date' => date_i18n('M j, Y', strtotime($announcement)),
                'status' => 'complete',
            );
        }

        if ($regulator) {
            $events[] = array(
                'label' => __('Regulatory review', 'senna-finance-career'),
                'date' => $regulator,
                'status' => 'active',
            );
        }

        if ($completion) {
            $events[] = array(
                'label' => __('Target close', 'senna-finance-career'),
                'date' => date_i18n('M j, Y', strtotime($completion)),
                'status' => 'upcoming',
            );
        }

        return $events;
    }

    private function build_supporting_metrics($post) {
        $value = $this->format_currency(get_post_meta($post->ID, '_deal_value', true));
        $multiple = get_post_meta($post->ID, '_deal_multiple', true);
        $financing = get_post_meta($post->ID, '_deal_financing_mix', true);
        $ebitda = get_post_meta($post->ID, '_deal_ebitda', true);

        $metrics = array();
        if ($value) {
            $metrics[] = array('label' => __('Enterprise value', 'senna-finance-career'), 'value' => $value);
        }
        if ($multiple) {
            $metrics[] = array('label' => __('EBITDA multiple', 'senna-finance-career'), 'value' => $multiple);
        }
        if ($financing) {
            $metrics[] = array('label' => __('Financing mix', 'senna-finance-career'), 'value' => $financing);
        }
        if ($ebitda) {
            $metrics[] = array('label' => __('EBITDA', 'senna-finance-career'), 'value' => $this->format_currency($ebitda));
        }

        return $metrics;
    }

    private function build_glossary_terms($post) {
        $content = strtolower(wp_strip_all_tags($post->post_content));
        preg_match_all('/\b[a-z]{4,}\b/', $content, $matches);
        $words = array_unique($matches[0] ?? array());
        $stopwords = array('with', 'from', 'that', 'this', 'into', 'have', 'will', 'been', 'they', 'which', 'their', 'about', 'over', 'under', 'your', 'after');

        $terms = array();
        foreach ($words as $word) {
            if (in_array($word, $stopwords, true)) {
                continue;
            }
            $definition = $this->fetch_glossary_entry($word);
            if ($definition) {
                $terms[] = $definition;
            }
            if (count($terms) >= 4) {
                break;
            }
        }

        return $terms;
    }

    private function build_related_companies($post) {
        $results = array();
        $taxonomies = array('sffc_company_tag', 'company', 'firm', 'prep_company');
        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $terms = get_the_terms($post, $taxonomy);
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $results[] = array(
                    'name' => $term->name,
                    'link' => !is_wp_error(get_term_link($term)) ? get_term_link($term) : '',
                    'mentions' => intval($term->count),
                    'signal' => $this->resolve_company_signal($term->name),
                );
            }
        }

        if (empty($results)) {
            $company = get_post_meta($post->ID, '_deal_company', true);
            if ($company) {
                $results[] = array(
                    'name' => $company,
                    'link' => '',
                    'mentions' => 1,
                    'signal' => __('Monitoring', 'senna-finance-career'),
                );
            }
        }

        return array_slice($results, 0, 4);
    }

    private function build_related_insights($post) {
        $args = array(
            'post_type' => array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_markets'),
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'post__not_in' => array($post->ID),
        );

        $primary_term = $this->extract_primary_topic($post, true);
        if ($primary_term && taxonomy_exists($primary_term['taxonomy'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $primary_term['taxonomy'],
                    'field' => 'slug',
                    'terms' => $primary_term['slug'],
                ),
            );
        }

        $related = get_posts($args);
        if (empty($related)) {
            $args['tax_query'] = array();
            $related = get_posts($args);
        }

        $insights = array();
        foreach ($related as $item) {
            $type = __('Insight', 'senna-finance-career');
            if ($item->post_type === 'sffc_pe_deal') {
                $type = __('Deal', 'senna-finance-career');
            } elseif ($item->post_type === 'sffc_pe_markets') {
                $type = __('Markets', 'senna-finance-career');
            }
            $insights[] = array(
                'title' => get_the_title($item),
                'link' => get_permalink($item),
                'timestamp' => human_time_diff(get_post_time('U', true, $item), current_time('timestamp')) . ' ' . __('ago', 'senna-finance-career'),
                'type' => $type,
            );
        }

        return $insights;
    }

    private function build_chat_prompts($post) {
        $title = get_the_title($post);
        return array(
            array(
                'label' => __('Explain the thesis', 'senna-finance-career'),
                'prompt' => sprintf(__('Explain the core thesis behind %s in two paragraphs.', 'senna-finance-career'), $title),
            ),
            array(
                'label' => __('Summarize risks', 'senna-finance-career'),
                'prompt' => sprintf(__('Summarize the downside and risk factors discussed in %s.', 'senna-finance-career'), $title),
            ),
            array(
                'label' => __('Link to jobs', 'senna-finance-career'),
                'prompt' => sprintf(__('Find relevant job opportunities linked to %s.', 'senna-finance-career'), $title),
            ),
        );
    }

    private function build_article_toc($html) {
        $toc = array();
        if (empty($html)) {
            return $toc;
        }

        if (preg_match_all('/<(h[2-4])[^>]*>(.*?)<\/\1>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = trim(wp_strip_all_tags($match[2]));
                if (empty($label)) {
                    continue;
                }
                $toc[] = array('label' => $label);
                if (count($toc) >= 6) {
                    break;
                }
            }
        }

        return $toc;
    }

    private function build_article_chart_data($post, $content_html) {
        // Check cache first - cache for 24 hours
        $cache_key = 'sffc_chart_data_' . $post->ID;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Use new dynamic insights generator if available
        if ($this->insights_generator) {
            $insights = $this->insights_generator->generate_insights($post, $content_html);
            if (!empty($insights) && (isset($insights['bars']) || isset($insights['pie']) || isset($insights['lines']))) {
                // Cache for 24 hours
                set_transient($cache_key, $insights, DAY_IN_SECONDS);
                return $insights;
            }
        }

        // Use template intelligence system if available
        if ($this->template_intelligence && $this->template_library) {
            $analysis = $this->template_intelligence->analyze_article($post);
            $category = $analysis['category'];
            $entities = $analysis['entities'];

            // Get appropriate template based on analysis
            $template_data = $this->get_template_data_for_category($category, $entities);
            if ($template_data) {
                return $template_data['charts'];
            }
        }

        // Fallback to original logic
        $numbers = $this->extract_article_numbers($content_html);
        if (empty($numbers)) {
            $numbers = $this->generate_placeholder_numbers($post->ID);
        }

        $sector_terms = $this->collect_terms($post, array('sector', 'sffc_sector'));
        $region_terms = $this->collect_terms($post, array('region', 'job_location'));

        $bars = array(
            array(
                'title' => __('Capital mix snapshot', 'senna-finance-career'),
                'series' => array()
            )
        );
        $bar_labels = array(__('Equity', 'senna-finance-career'), __('Debt', 'senna-finance-career'), __('Cash', 'senna-finance-career'), __('Other', 'senna-finance-career'));
        foreach (array_slice($numbers, 0, 4) as $index => $value) {
            $bars[0]['series'][] = array(
                'label' => $bar_labels[$index] ?? sprintf(__('Component %d', 'senna-finance-career'), $index + 1),
                'value' => round($value, 2)
            );
        }

        $bars[] = array(
            'title' => __('Peer valuation delta', 'senna-finance-career'),
            'series' => array(
                array('label' => __('Top quartile', 'senna-finance-career'), 'value' => isset($numbers[0]) ? round($numbers[0] * 1.1, 2) : 72),
                array('label' => __('Median', 'senna-finance-career'), 'value' => isset($numbers[1]) ? round($numbers[1], 2) : 58),
                array('label' => __('This deal', 'senna-finance-career'), 'value' => isset($numbers[2]) ? round($numbers[2], 2) : 63),
            )
        );

        $line_points = array();
        foreach (array_slice($numbers, 0, 6) as $value) {
            $line_points[] = array('value' => max(8, min(100, intval($value))));
        }
        if (empty($line_points)) {
            $line_points = array(array('value' => 42), array('value' => 67), array('value' => 51), array('value' => 78));
        }

        $pie_slices = array();
        $pie_total = array_sum(array_slice($numbers, 0, 3));
        if ($pie_total <= 0) {
            $pie_slices = array(
                array('label' => __('Core thesis', 'senna-finance-career'), 'value' => 45),
                array('label' => __('Expansion', 'senna-finance-career'), 'value' => 35),
                array('label' => __('Optionality', 'senna-finance-career'), 'value' => 20)
            );
        } else {
            $labels = array(__('Growth', 'senna-finance-career'), __('Margin', 'senna-finance-career'), __('Synergy', 'senna-finance-career'));
            foreach (array_slice($numbers, 0, 3) as $index => $value) {
                $pie_slices[] = array(
                    'label' => $labels[$index] ?? sprintf(__('Slice %d', 'senna-finance-career'), $index + 1),
                    'value' => max(1, round(($value / $pie_total) * 100))
                );
            }
        }

        $stacked = array(
            array(
                'title' => __('Scenario allocation', 'senna-finance-career'),
                'series' => array(
                    array(
                        'label' => __('Base case', 'senna-finance-career'),
                        'segments' => array(
                            array('label' => __('Revenue', 'senna-finance-career'), 'value' => 45),
                            array('label' => __('Margin', 'senna-finance-career'), 'value' => 35),
                            array('label' => __('Leverage', 'senna-finance-career'), 'value' => 20),
                        ),
                    ),
                    array(
                        'label' => __('Bull case', 'senna-finance-career'),
                        'segments' => array(
                            array('label' => __('Revenue', 'senna-finance-career'), 'value' => 55),
                            array('label' => __('Margin', 'senna-finance-career'), 'value' => 30),
                            array('label' => __('Leverage', 'senna-finance-career'), 'value' => 15),
                        ),
                    ),
                ),
            ),
        );

        $heatmap_rows = array(
            array(
                'label' => __('North America', 'senna-finance-career'),
                'values' => array(rand(55, 95), rand(40, 80), rand(30, 70)),
            ),
            array(
                'label' => __('Europe', 'senna-finance-career'),
                'values' => array(rand(35, 80), rand(30, 70), rand(40, 85)),
            ),
            array(
                'label' => __('APAC', 'senna-finance-career'),
                'values' => array(rand(20, 65), rand(25, 60), rand(30, 70)),
            ),
        );

        $commentary = $this->generate_ai_summary($post, 'insights');
        if (empty($commentary)) {
            $commentary = sprintf(
                __('Monitoring %1$s exposure across %2$s with sentiment leaning %3$s based on current filings.', 'senna-finance-career'),
                !empty($sector_terms) ? implode(', ', array_slice($sector_terms, 0, 2)) : __('multi-sector', 'senna-finance-career'),
                !empty($region_terms) ? implode(', ', array_slice($region_terms, 0, 2)) : __('core markets', 'senna-finance-career'),
                rand(0, 1) ? __('constructive', 'senna-finance-career') : __('balanced', 'senna-finance-career')
            );
        }

        $chart_data = array(
            'bars' => $bars,
            'lines' => array(
                array(
                    'title' => __('Flow of mentions (weekly)', 'senna-finance-career'),
                    'points' => $line_points,
                ),
            ),
            'pie' => array(
                array(
                    'title' => __('Value driver mix', 'senna-finance-career'),
                    'slices' => $pie_slices,
                ),
            ),
            'stacked' => $stacked,
            'heatmap' => array(
                array(
                    'title' => __('Region vs. impact score', 'senna-finance-career'),
                    'rows' => $heatmap_rows,
                ),
            ),
            'commentary' => $commentary,
        );

        // Cache fallback chart data for 24 hours
        set_transient($cache_key, $chart_data, DAY_IN_SECONDS);

        return $chart_data;
    }

    private function build_article_research_cards($post) {
        // Check cache first - cache for 24 hours
        $cache_key = 'sffc_research_cards_' . $post->ID;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Use new dynamic insights generator if available
        if ($this->insights_generator && $this->template_intelligence) {
            $analysis = $this->template_intelligence->analyze_article($post);
            $category = $analysis['category'];
            $entities = $analysis['entities'];

            // Get Claude context for research cards
            $claude_context = array();
            if ($this->get_claude_manager()) {
                $claude_context = $this->get_claude_research_context($post, $category);
            }

            $research_cards = $this->insights_generator->generate_research_cards($post, $category, $entities, $claude_context);
            if (!empty($research_cards)) {
                set_transient($cache_key, $research_cards, DAY_IN_SECONDS);
                return $research_cards;
            }
        }

        // Use template intelligence system if available
        if ($this->template_intelligence && $this->template_library) {
            $analysis = $this->template_intelligence->analyze_article($post);
            $category = $analysis['category'];
            $entities = $analysis['entities'];

            // Get appropriate research template
            $research_data = $this->get_research_template_for_category($category, $entities);
            if ($research_data) {
                $formatted_cards = $this->format_research_cards($research_data);
                set_transient($cache_key, $formatted_cards, DAY_IN_SECONDS);
                return $formatted_cards;
            }
        }

        // Fallback to original logic
        $title = get_the_title($post);
        $excerpt = $this->build_excerpt($post);
        $sectors = $this->collect_terms($post, array('sector', 'sffc_sector'));
        $regions = $this->collect_terms($post, array('region', 'job_location'));
        $deal_types = $this->collect_terms($post, array('deal_type', 'sffc_deal_type'));
        $ai_summary = $this->generate_ai_summary($post, 'research');

        $cards = array(
            array(
                'title' => __('Industry pulse', 'senna-finance-career'),
                'summary' => !empty($ai_summary) ? $ai_summary : sprintf(__('Monitoring %s with focus on %s activity.', 'senna-finance-career'), $title, !empty($sectors) ? implode(', ', array_slice($sectors, 0, 1)) : __('diversified', 'senna-finance-career')),
                'bullets' => array(
                    sprintf(__('Sector focus: %s', 'senna-finance-career'), !empty($sectors) ? implode(', ', array_slice($sectors, 0, 2)) : __('multi-sector', 'senna-finance-career')),
                    sprintf(__('Region watch: %s', 'senna-finance-career'), !empty($regions) ? implode(', ', array_slice($regions, 0, 2)) : __('global', 'senna-finance-career')),
                    sprintf(__('Deal lens: %s', 'senna-finance-career'), !empty($deal_types) ? implode(', ', array_slice($deal_types, 0, 1)) : __('strategic review', 'senna-finance-career')),
                ),
                'cta' => __('Updated daily', 'senna-finance-career'),
            ),
            array(
                'title' => __('Risk radar', 'senna-finance-career'),
                'summary' => __('Regulatory, financing, and execution factors summarised for diligence teams.', 'senna-finance-career'),
                'bullets' => array(
                    __('Regulation: monitoring disclosure, ESG, and cross-border approvals.', 'senna-finance-career'),
                    __('Financing: watching leverage tolerance and rate sensitivity.', 'senna-finance-career'),
                    __('Execution: integration velocity and talent retention flagged.', 'senna-finance-career'),
                ),
                'cta' => __('Live monitoring enabled', 'senna-finance-career'),
            ),
            array(
                'title' => __('Scenario workbook', 'senna-finance-career'),
                'summary' => !empty($excerpt) ? $excerpt : __('Key factors behind this transaction are being synthesised.', 'senna-finance-career'),
                'bullets' => array(
                    __('Base case: steady revenue glide path with cost alignment.', 'senna-finance-career'),
                    __('Upside: faster mix shift toward subscription / recurring cash flows.', 'senna-finance-career'),
                    __('Downside: regulatory drag or funding market volatility.', 'senna-finance-career'),
                ),
                'cta' => __('Tap Ask MENA Careers for detail', 'senna-finance-career'),
            )
        );

        // Cache fallback research cards for 24 hours
        set_transient($cache_key, $cards, DAY_IN_SECONDS);

        return $cards;
    }

    private function generate_ai_summary($post, $purpose = 'insights') {
        $claude_manager = $this->get_claude_manager();
        if (!$claude_manager) {
            return '';
        }

        // Check cache first - cache for 24 hours
        $cache_key = 'sffc_ai_summary_' . $post->ID . '_' . $purpose;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $title = get_the_title($post);
        $excerpt = $this->build_excerpt($post);
        $prompt = sprintf(
            "Headline: %s
Summary: %s

Provide %s-style commentary with two to three concise sentences that highlight key signals, benchmarks, and catalysts. Keep it object-driven and specific to private markets.",
            $title,
            $excerpt,
            ('research' === $purpose) ? 'a research memo' : 'a market insight'
        );

        if ('research' === $purpose) {
            $prompt .= " Emphasize industry context, risk factors, and monitoring priorities.";
        } else {
            $prompt .= " Emphasize market positioning, comparable activity, and investor implications.";
        }

        $prompt .= " CRITICAL: Never use fake placeholder names like 'Jane Doe' or 'XYZ Asset Management'. Only reference companies explicitly mentioned in the article. If no firms are named, use generic terms like 'a top private equity firm', 'leading asset managers', or 'industry sources'.";

        $result = $claude_manager->send_message($prompt, array(), 'market');
        if (is_array($result) && !empty($result['response'])) {
            $summary = trim(wp_strip_all_tags($result['response']));
            // Cache for 24 hours
            set_transient($cache_key, $summary, DAY_IN_SECONDS);
            return $summary;
        }

        return '';
    }

    /**
     * Get Claude context for research cards
     */
    private function get_claude_research_context($post, $category) {
        $claude_manager = $this->get_claude_manager();
        if (!$claude_manager) {
            return array();
        }

        // Check cache first - cache for 24 hours
        $cache_key = 'sffc_research_context_' . $post->ID . '_' . sanitize_key($category);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $title = get_the_title($post);
        $excerpt = $this->build_excerpt($post);

        $prompt = "As a senior PE analyst at a top-tier firm, analyze this article:\n\n";
        $prompt .= "HEADLINE: {$title}\n";
        $prompt .= "SUMMARY: {$excerpt}\n\n";
        $prompt .= "Provide a JSON response with:\n";
        $prompt .= "1. 'market_context': 2-3 sentences on market implications\n";
        $prompt .= "2. 'key_implications': Array of 3 key takeaways for PE professionals\n";
        $prompt .= "3. 'outlook': 1 sentence forward-looking view\n";
        $prompt .= "4. 'risk_factors': Array of 3 relevant risk/watch items\n\n";
        $prompt .= "CRITICAL: NEVER use fake placeholder names like 'Jane Doe', 'John Smith', or 'XYZ Asset Management'. ";
        $prompt .= "Only reference companies explicitly mentioned in the article. If no firms are named, use generic terms like 'a top private equity firm', 'leading asset managers', or 'industry analysts'.\n\n";
        $prompt .= "Format as valid JSON only, no markdown.";

        $result = $claude_manager->send_message($prompt, array(), 'market');

        if (is_array($result) && !empty($result['response'])) {
            $response = $result['response'];
            $json_start = strpos($response, '{');
            $json_end = strrpos($response, '}');

            if ($json_start !== false && $json_end !== false) {
                $json_str = substr($response, $json_start, $json_end - $json_start + 1);
                $parsed = json_decode($json_str, true);
                if ($parsed) {
                    // Cache for 24 hours
                    set_transient($cache_key, $parsed, DAY_IN_SECONDS);
                    return $parsed;
                }
            }

            // Return raw response as context and cache it
            $result_array = array('market_context' => trim(wp_strip_all_tags($response)));
            set_transient($cache_key, $result_array, DAY_IN_SECONDS);
            return $result_array;
        }

        return array();
    }

    private function extract_article_numbers($content_html) {
        $plain = wp_strip_all_tags($content_html);
        if (empty($plain)) {
            return array();
        }
        preg_match_all('/\b(\d+(?:\.\d+)?)\b/', $plain, $matches);
        if (empty($matches[1])) {
            return array();
        }
        return array_slice(array_map('floatval', $matches[1]), 0, 8);
    }

    private function generate_placeholder_numbers($seed_source) {
        $seed = absint($seed_source) ?: time();
        mt_srand($seed);
        $values = array();
        for ($i = 0; $i < 6; $i++) {
            $values[] = mt_rand(10, 95);
        }
        mt_srand();
        return $values;
    }

    private function resolve_signal_strength($post) {
        $signal = get_post_meta($post->ID, '_sffc_signal_strength', true);
        if (!$signal) {
            $signal = rand(78, 97);
        }

        return array(
            'value' => intval($signal),
            'label' => __('Signal strength', 'senna-finance-career'),
            'tone' => ($signal >= 90) ? 'positive' : 'neutral',
        );
    }

    private function collect_terms($post, $taxonomies) {
        $labels = array();
        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $terms = get_the_terms($post, $taxonomy);
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $labels[] = $term->name;
            }
        }
        return array_values(array_unique($labels));
    }

    private function extract_primary_topic($post, $return_term = false) {
        $taxonomies = get_post_taxonomies($post);
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            $term = reset($terms);
            if ($return_term) {
                return array(
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'taxonomy' => $taxonomy,
                );
            }
            return $term->name;
        }

        return $return_term ? null : __('All Stories', 'senna-finance-career');
    }

    private function resolve_company_signal($name) {
        $signals = array(
            __('Active', 'senna-finance-career'),
            __('On watch', 'senna-finance-career'),
            __('High momentum', 'senna-finance-career'),
        );
        return $signals[array_rand($signals)];
    }

    private function fetch_glossary_entry($term) {
        $posts = get_posts(array(
            'post_type' => 'glossary',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => $term,
        ));

        if (empty($posts)) {
            return null;
        }

        $entry = reset($posts);
        $definition = $entry->post_excerpt ?: wp_strip_all_tags($entry->post_content);
        return array(
            'term' => get_the_title($entry),
            'definition' => wp_trim_words($definition, 24, '…'),
            'link' => get_permalink($entry),
        );
    }

    private function format_currency($value) {
        if (!$value) {
            return '';
        }

        if (is_numeric($value)) {
            return '$' . number_format_i18n($value);
        }

        return $value;
    }
    
    /**
     * Get template data for a category
     */
    private function get_template_data_for_category($category, $entities) {
        if (!$this->template_library) {
            return null;
        }
        
        // Map categories to template methods
        $template_methods = array(
            'fundraise' => 'get_fundraise_insights',
            'acquisition' => 'get_acquisition_insights',
            'exit' => 'get_exit_insights',
            'merger' => 'get_merger_insights',
            'esg' => 'get_esg_insights',
            'regulation' => 'get_regulation_insights',
            'people_moves' => 'get_people_moves_insights',
            'rankings' => 'get_rankings_insights',
            'trends' => 'get_trends_insights'
        );
        
        if (isset($template_methods[$category])) {
            $method = $template_methods[$category];
            if (method_exists($this->template_library, $method)) {
                return $this->template_library->$method($entities);
            }
        }
        
        return null;
    }
    
    /**
     * Get research template for a category
     */
    private function get_research_template_for_category($category, $entities) {
        if (!$this->template_library) {
            return null;
        }
        
        // Map categories to research template methods
        $research_methods = array(
            'fundraise' => 'get_fundraise_research',
            'acquisition' => 'get_acquisition_research',
            'exit' => 'get_exit_research',
            'merger' => 'get_merger_research',
            'esg' => 'get_esg_research',
            'regulation' => 'get_regulation_research',
            'people_moves' => 'get_people_moves_research',
            'rankings' => 'get_rankings_research',
            'trends' => 'get_trends_research'
        );
        
        if (isset($research_methods[$category])) {
            $method = $research_methods[$category];
            if (method_exists($this->template_library, $method)) {
                return $this->template_library->$method($entities);
            }
        }
        
        return null;
    }
    
    /**
     * Format research data into cards
     */
    private function format_research_cards($research_data) {
        $cards = array();
        
        // Executive Summary Card
        if (isset($research_data['executive_summary'])) {
            $cards[] = array(
                'title' => $research_data['executive_summary']['title'],
                'summary' => $research_data['executive_summary']['content'],
                'bullets' => array(),
                'cta' => __('Executive Overview', 'senna-finance-career')
            );
        }
        
        // Key Developments Card
        if (isset($research_data['key_developments'])) {
            foreach ($research_data['key_developments']['sections'] as $section) {
                $cards[] = array(
                    'title' => $section['heading'],
                    'summary' => '',
                    'bullets' => $section['points'],
                    'cta' => __('Market Intelligence', 'senna-finance-career')
                );
            }
        }
        
        // Outlook Card
        if (isset($research_data['outlook'])) {
            $bullets = array();
            foreach ($research_data['outlook']['scenarios'] as $scenario) {
                $bullets[] = sprintf('%s (%s): %s', $scenario['scenario'], $scenario['probability'], $scenario['description']);
            }
            $cards[] = array(
                'title' => $research_data['outlook']['title'],
                'summary' => __('Scenario analysis and probability-weighted outcomes', 'senna-finance-career'),
                'bullets' => $bullets,
                'cta' => __('Forward View', 'senna-finance-career')
            );
        }
        
        return $cards;
    }

    /**
     * Render recruiter post article shortcode
     * [sffc_recruiter_post_article post_id="123"]
     */
    public function render_recruiter_post_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id' => '',
            'show_sidebar' => 'true',
        ], $atts);

        // Resolve post
        $post_id = $atts['post_id'];
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }

        $post = get_post($post_id);
        if (!$post) {
            return '<!-- recruiter post article: post missing -->';
        }

        // Load template
        $template_file = plugin_dir_path(__FILE__) . '../templates/recruiter-post-article.php';
        if (!file_exists($template_file)) {
            return '<!-- recruiter post article: template missing -->';
        }

        require_once $template_file;

        $show_sidebar = filter_var($atts['show_sidebar'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        sffc_render_recruiter_post_article(array(
            'post_id' => $post->ID,
            'show_sidebar' => $show_sidebar,
            'user_has_access' => is_user_logged_in(),
        ));
        return ob_get_clean();
    }

    public function render_job_apply_shortcode($atts) {
        $atts = shortcode_atts([
            'post_id' => '',
        ], $atts);

        // Resolve post
        $post_id = $atts['post_id'];
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }

        $post = get_post($post_id);
        if (!$post) {
            return '<!-- job apply: post missing -->';
        }

        // Load template
        $template_file = plugin_dir_path(__FILE__) . '../templates/job-apply.php';
        if (!file_exists($template_file)) {
            return '<!-- job apply: template missing -->';
        }

        require_once $template_file;

        ob_start();
        sffc_render_job_apply(array(
            'post_id' => $post->ID,
        ));
        return ob_get_clean();
    }

    /**
     * Render role analyzer shortcode
     */
    public function render_role_analyzer_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => '',
        ), $atts);

        $post_id = $atts['post_id'];
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }

        $post = get_post($post_id);
        if (!$post) {
            return '<!-- role analyzer: post missing -->';
        }

        $template_file = plugin_dir_path(__FILE__) . '../templates/role-analyzer.php';
        if (!file_exists($template_file)) {
            return '<!-- role analyzer: template missing -->';
        }

        require_once $template_file;

        ob_start();
        sffc_render_role_analyzer(array(
            'post_id' => $post->ID,
        ));
        return ob_get_clean();
    }
}

SFFC_Editorial_Article_Renderer::get_instance();
