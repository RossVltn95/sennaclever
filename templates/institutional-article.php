<?php
/**
 * Institutional Article Template
 * Premium split-screen research terminal layout
 * Optimized for Google News and SEO
 *
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the institutional article layout
 *
 * @param array $args Article data and configuration
 */
function sffc_render_institutional_article($args = array()) {
    $defaults = array(
        'post_id' => get_the_ID(),
        'show_chatbox' => true,
        'show_sidebar' => true,
        'is_premium' => false,
        'user_has_access' => is_user_logged_in(),
    );

    $args = wp_parse_args($args, $defaults);
    $post_id = $args['post_id'];
    $post = get_post($post_id);

    if (!$post) {
        return;
    }

    // Get article data
    $title = get_the_title($post);
    $content = apply_filters('the_content', $post->post_content);
    $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 30);
    $full_excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 55); // For meta description
    $date = get_the_date('M j, Y', $post);
    $reading_time = sffc_get_reading_time($post->post_content);
    $word_count = str_word_count(wp_strip_all_tags($post->post_content));
    $category = sffc_get_primary_category($post_id);

    // SEO-critical dates in ISO 8601 format
    $date_published_iso = get_the_date('c', $post);
    $date_modified_iso = get_the_modified_date('c', $post);
    $date_published_display = get_the_date('F j, Y', $post);
    $date_modified_display = get_the_modified_date('F j, Y', $post);

    // Get author data
    $author_id = $post->post_author;
    $author_name = get_the_author_meta('display_name', $author_id);
    $author_avatar = get_avatar_url($author_id, array('size' => 80));
    $author_url = get_author_posts_url($author_id);
    $author_bio = get_the_author_meta('description', $author_id);
    $author_job_title = get_the_author_meta('job_title', $author_id) ?: 'Financial Analyst';

    // Get featured image for schema
    $featured_image_id = get_post_thumbnail_id($post_id);
    $featured_image_url = '';
    $featured_image_width = 1200;
    $featured_image_height = 630;
    if ($featured_image_id) {
        $image_data = wp_get_attachment_image_src($featured_image_id, 'full');
        if ($image_data) {
            $featured_image_url = $image_data[0];
            $featured_image_width = $image_data[1];
            $featured_image_height = $image_data[2];
        }
    }
    // Fallback image
    if (empty($featured_image_url)) {
        $featured_image_url = SFFC_PLUGIN_URL . 'assets/images/default-article-og.jpg';
    }

    // Get canonical URL
    $canonical_url = get_permalink($post_id);

    // Get enhanced content from Claude if available
    $enhanced_data = sffc_get_enhanced_article_data($post_id, $title, $post->post_content);
    $charts = $enhanced_data['charts'] ?? array();
    $key_metrics = $enhanced_data['key_metrics'] ?? array();
    $takeaways = $enhanced_data['takeaways'] ?? array();
    $commentary = $enhanced_data['commentary'] ?? '';

    // Get dynamic sections from post meta (new Fact + Context model)
    $sections_json = get_post_meta($post_id, '_article_sections', true);
    $article_sections = !empty($sections_json) ? json_decode($sections_json, true) : array();
    $content_type = get_post_meta($post_id, '_content_type', true);
    $content_type_label = get_post_meta($post_id, '_content_type_label', true);
    $generation_method = get_post_meta($post_id, '_article_generation_method', true);

    // Get source attribution if available
    $source_url = get_post_meta($post_id, '_source_url', true);
    $source_name = get_post_meta($post_id, '_source_name', true);

    // Extract keywords from title and content for news_keywords meta
    $news_keywords = sffc_extract_news_keywords($title, $post->post_content, $category);

    // Check if sections actually have content (not just placeholders)
    $has_dynamic_sections = false;
    if (!empty($article_sections) && is_array($article_sections)) {
        foreach ($article_sections as $section) {
            $section_content = wp_strip_all_tags($section['content'] ?? '');
            // Only use dynamic sections if at least one has meaningful content (>50 chars)
            if (strlen($section_content) > 50 && stripos($section_content, 'pending') === false) {
                $has_dynamic_sections = true;
                break;
            }
        }
    }

    // Publisher info
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $publisher_logo_url = SFFC_PLUGIN_URL . 'assets/images/logo-60x60.png';

    // Enqueue assets
    wp_enqueue_style('inst-article', SFFC_PLUGIN_URL . 'assets/css/institutional-article.css', array(), SFFC_VERSION);
    wp_enqueue_script('inst-article', SFFC_PLUGIN_URL . 'assets/js/institutional-article.js', array(), SFFC_VERSION, true);

    // Localize script for AJAX (if not already done by main plugin)
    if (!wp_script_is('sffc-frontend', 'enqueued')) {
        wp_localize_script('inst-article', 'sffc_frontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffc_public_nonce'),
            'postId' => $post_id,
        ));
    }

    // Build JSON-LD structured data for Google News
    $schema_data = sffc_build_article_schema(array(
        'post_id' => $post_id,
        'title' => $title,
        'excerpt' => $full_excerpt,
        'content' => $post->post_content,
        'canonical_url' => $canonical_url,
        'date_published' => $date_published_iso,
        'date_modified' => $date_modified_iso,
        'author_name' => $author_name,
        'author_url' => $author_url,
        'author_job_title' => $author_job_title,
        'featured_image_url' => $featured_image_url,
        'featured_image_width' => $featured_image_width,
        'featured_image_height' => $featured_image_height,
        'category' => $category,
        'word_count' => $word_count,
        'is_premium' => $args['is_premium'],
        'site_name' => $site_name,
        'site_url' => $site_url,
        'publisher_logo_url' => $publisher_logo_url,
        'source_url' => $source_url,
        'takeaways' => $takeaways,
    ));

    // Output meta tags in head (if not already handled by theme/SEO plugin)
    add_action('wp_head', function() use ($title, $full_excerpt, $canonical_url, $featured_image_url, $date_published_iso, $date_modified_iso, $author_name, $category, $news_keywords, $site_name, $schema_data) {
        sffc_output_article_meta_tags(array(
            'title' => $title,
            'description' => $full_excerpt,
            'canonical_url' => $canonical_url,
            'image_url' => $featured_image_url,
            'date_published' => $date_published_iso,
            'date_modified' => $date_modified_iso,
            'author' => $author_name,
            'section' => $category,
            'keywords' => $news_keywords,
            'site_name' => $site_name,
            'schema' => $schema_data,
        ));
    }, 5);

    ?>
    <div class="inst-terminal" data-post-id="<?php echo esc_attr($post_id); ?>">

        <?php if ($args['show_sidebar']) : ?>
        <!-- Icon Sidebar -->
        <aside class="inst-sidebar">
            <svg class="inst-sidebar-logo" viewBox="0 0 28 28" fill="none">
                <!-- Premium S -->
                <path d="M5 9c0-3 2.5-5.5 5.5-5.5h5c2.5 0 4.5 2 4.5 4.5 0 2-1.3 3.6-3.1 4.2-.2.1-.2.4 0 .5 1.8.6 3.1 2.2 3.1 4.2 0 2.5-2 4.5-4.5 4.5h-5C7.5 21.5 5 19 5 16" stroke="#0f5132" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <!-- Dot top-right -->
                <circle cx="24" cy="5" r="3" fill="#0f5132"/>
            </svg>

            <div class="inst-sidebar-actions">
                <button class="inst-sidebar-btn" data-action="save" data-tooltip="Save Article">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                </button>

                <button class="inst-sidebar-btn" data-action="share" data-tooltip="Share">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"/>
                        <circle cx="6" cy="12" r="3"/>
                        <circle cx="18" cy="19" r="3"/>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                    </svg>
                </button>

                <button class="inst-sidebar-btn" data-action="analyze" data-tooltip="Analyze with AI">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </button>
            </div>
        </aside>
        <?php endif; ?>

        <!-- Main Content Area -->
        <div class="inst-main">

            <?php if (!$args['user_has_access']) : ?>
            <!-- Subscribe Banner for Non-Subscribers -->
            <div class="inst-subscribe-bar" id="inst-subscribe-bar">
                <div class="inst-subscribe-bar-content">
                    <svg class="inst-subscribe-bar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <span class="inst-subscribe-bar-text">Get unlimited access to premium research & analysis</span>
                    <form class="inst-subscribe-bar-form" action="<?php echo esc_url(home_url('/memberships/')); ?>" method="get">
                        <input type="email" name="email" class="inst-subscribe-bar-input" placeholder="Enter your email" required>
                        <button type="submit" class="inst-subscribe-bar-btn">Subscribe</button>
                    </form>
                    <button class="inst-subscribe-bar-close" onclick="document.getElementById('inst-subscribe-bar').style.display='none'" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Article Panel (Left) -->
            <div class="inst-article-panel">

                <!-- Sticky Header -->
                <header class="inst-article-header">
                    <span class="inst-article-header-title"><?php echo esc_html($title); ?></span>
                    <div class="inst-article-header-meta">
                        <span><?php echo esc_html($reading_time); ?> read</span>
                    </div>
                </header>

                <!-- Article Content - Semantic HTML5 with Schema.org microdata -->
                <article class="inst-article-content" itemscope itemtype="https://schema.org/NewsArticle">
                    <!-- Hidden schema properties -->
                    <meta itemprop="mainEntityOfPage" content="<?php echo esc_url($canonical_url); ?>">
                    <meta itemprop="datePublished" content="<?php echo esc_attr($date_published_iso); ?>">
                    <meta itemprop="dateModified" content="<?php echo esc_attr($date_modified_iso); ?>">
                    <meta itemprop="wordCount" content="<?php echo esc_attr($word_count); ?>">
                    <?php if (!$args['is_premium']) : ?>
                    <meta itemprop="isAccessibleForFree" content="True">
                    <?php else : ?>
                    <meta itemprop="isAccessibleForFree" content="False">
                    <?php endif; ?>

                    <!-- Publisher microdata -->
                    <div itemprop="publisher" itemscope itemtype="https://schema.org/Organization" style="display:none;">
                        <meta itemprop="name" content="<?php echo esc_attr($site_name); ?>">
                        <meta itemprop="url" content="<?php echo esc_url($site_url); ?>">
                        <div itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
                            <meta itemprop="url" content="<?php echo esc_url($publisher_logo_url); ?>">
                            <meta itemprop="width" content="60">
                            <meta itemprop="height" content="60">
                        </div>
                    </div>

                    <!-- Featured image microdata -->
                    <?php if ($featured_image_url) : ?>
                    <div itemprop="image" itemscope itemtype="https://schema.org/ImageObject" style="display:none;">
                        <meta itemprop="url" content="<?php echo esc_url($featured_image_url); ?>">
                        <meta itemprop="width" content="<?php echo esc_attr($featured_image_width); ?>">
                        <meta itemprop="height" content="<?php echo esc_attr($featured_image_height); ?>">
                    </div>
                    <?php endif; ?>

                    <div class="inst-article-inner">

                        <!-- Breadcrumb Navigation -->
                        <nav class="inst-breadcrumbs" aria-label="Breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
                            <ol class="inst-breadcrumbs-list">
                                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                    <a itemprop="item" href="<?php echo esc_url($site_url); ?>">
                                        <span itemprop="name">Home</span>
                                    </a>
                                    <meta itemprop="position" content="1">
                                </li>
                                <?php if ($category) : ?>
                                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                    <a itemprop="item" href="<?php echo esc_url(get_category_link(get_cat_ID($category))); ?>">
                                        <span itemprop="name"><?php echo esc_html($category); ?></span>
                                    </a>
                                    <meta itemprop="position" content="2">
                                </li>
                                <?php endif; ?>
                                <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                    <span itemprop="item">
                                        <span itemprop="name"><?php echo esc_html(wp_trim_words($title, 6, '...')); ?></span>
                                    </span>
                                    <meta itemprop="position" content="<?php echo $category ? '3' : '2'; ?>">
                                </li>
                            </ol>
                        </nav>

                        <div class="inst-article-eyebrow-row">
                            <?php if ($content_type_label) : ?>
                            <span class="inst-article-type-badge"><?php echo esc_html($content_type_label); ?></span>
                            <?php endif; ?>
                            <?php if ($category) : ?>
                            <span class="inst-article-category" itemprop="articleSection"><?php echo esc_html($category); ?></span>
                            <?php endif; ?>
                        </div>

                        <h1 class="inst-article-title" itemprop="headline"><?php echo esc_html($title); ?></h1>

                        <?php if (!empty($excerpt)) : ?>
                        <p class="inst-article-subtitle" itemprop="description"><?php echo esc_html($excerpt); ?></p>
                        <?php endif; ?>

                        <div class="inst-article-byline" itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <?php if ($author_avatar) : ?>
                            <img class="inst-article-author-avatar"
                                 src="<?php echo esc_url($author_avatar); ?>"
                                 alt="<?php echo esc_attr($author_name); ?>"
                                 itemprop="image">
                            <?php endif; ?>
                            <div class="inst-article-author-info">
                                <a href="<?php echo esc_url($author_url); ?>" class="inst-article-author-name" itemprop="name" rel="author">
                                    <?php echo esc_html($author_name); ?>
                                </a>
                                <meta itemprop="url" content="<?php echo esc_url($author_url); ?>">
                                <meta itemprop="jobTitle" content="<?php echo esc_attr($author_job_title); ?>">
                                <div class="inst-article-meta-row">
                                    <time datetime="<?php echo esc_attr($date_published_iso); ?>" itemprop="datePublished">
                                        <?php echo esc_html($date_published_display); ?>
                                    </time>
                                    <?php if ($date_modified_iso !== $date_published_iso) : ?>
                                    <span class="inst-article-meta-dot"></span>
                                    <span class="inst-article-updated">
                                        Updated <time datetime="<?php echo esc_attr($date_modified_iso); ?>"><?php echo esc_html($date_modified_display); ?></time>
                                    </span>
                                    <?php endif; ?>
                                    <span class="inst-article-meta-dot"></span>
                                    <span><?php echo esc_html($reading_time); ?> read</span>
                                    <span class="inst-article-meta-dot"></span>
                                    <a href="https://www.linkedin.com/company/89210865/" class="inst-article-linkedin" target="_blank" rel="noopener" aria-label="Follow us on LinkedIn">
                                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                        </svg>
                                        <span>Follow</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Generate executive summary data
                        $exec_summary = sffc_generate_executive_summary($post_id, $title, $post->post_content, $key_metrics, $takeaways);
                        ?>

                        <?php if (!empty($exec_summary)) : ?>
                        <!-- Executive Summary - Investment Research Format -->
                        <div class="inst-executive-summary" data-speakable="true">
                            <div class="inst-exec-header">
                                <h2 class="inst-exec-title">Executive Summary</h2>
                                <?php if (!empty($exec_summary['methodology'])) : ?>
                                <span class="inst-exec-methodology"><?php echo esc_html($exec_summary['methodology']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($exec_summary['thesis'])) : ?>
                            <p class="inst-exec-thesis"><?php echo esc_html($exec_summary['thesis']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($key_metrics)) : ?>
                            <div class="inst-exec-metrics">
                                <?php foreach (array_slice($key_metrics, 0, 4) as $metric) : ?>
                                <div class="inst-exec-metric">
                                    <span class="inst-exec-metric-value"><?php echo esc_html($metric['value']); ?></span>
                                    <span class="inst-exec-metric-label"><?php echo esc_html($metric['label']); ?></span>
                                    <?php if (!empty($metric['sub'])) : ?>
                                    <span class="inst-exec-metric-context"><?php echo esc_html($metric['sub']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($takeaways)) : ?>
                        <!-- Key Takeaways - Enhanced with data points -->
                        <div class="inst-takeaways-enhanced" data-speakable="true">
                            <div class="inst-takeaways-header">
                                <h3 class="inst-takeaways-label">Key Takeaways</h3>
                                <span class="inst-takeaways-count"><?php echo count($takeaways); ?> points</span>
                            </div>
                            <ul class="inst-takeaways-list">
                                <?php foreach ($takeaways as $index => $takeaway) : ?>
                                <li class="inst-takeaway-item">
                                    <span class="inst-takeaway-number"><?php echo $index + 1; ?></span>
                                    <span class="inst-takeaway-text"><?php echo esc_html($takeaway); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="inst-article-body" itemprop="articleBody">
                            <?php if ($has_dynamic_sections) : ?>
                                <!-- Dynamic Sections (Fact + Context Model) -->
                                <?php foreach ($article_sections as $section) : ?>
                                <section class="inst-section inst-section-<?php echo esc_attr($section['type'] ?? 'content'); ?>">
                                    <?php if (!empty($section['title'])) : ?>
                                    <h2 class="inst-section-title"><?php echo esc_html($section['title']); ?></h2>
                                    <?php endif; ?>
                                    <div class="inst-section-content">
                                        <?php echo wp_kses_post($section['content'] ?? ''); ?>
                                    </div>
                                </section>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <!-- Legacy Content -->
                                <?php echo $content; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($source_url || $source_name) : ?>
                        <!-- Source Attribution -->
                        <footer class="inst-article-source">
                            <p class="inst-article-source-text">
                                Source:
                                <?php if ($source_url) : ?>
                                <a href="<?php echo esc_url($source_url); ?>" rel="nofollow noopener" target="_blank" itemprop="isBasedOn">
                                    <?php echo esc_html($source_name ?: parse_url($source_url, PHP_URL_HOST)); ?>
                                </a>
                                <?php else : ?>
                                <span><?php echo esc_html($source_name); ?></span>
                                <?php endif; ?>
                            </p>
                        </footer>
                        <?php endif; ?>

                    </div>
                </article>

            </div>

            <!-- Resize Handle -->
            <div class="inst-resize-handle" title="Drag to resize"></div>

            <!-- Charts Panel (Right) -->
            <div class="inst-charts-panel">
                <div class="inst-charts-inner">

                    <!-- View Toggle -->
                    <div class="inst-view-toggle-container">
                        <div class="inst-view-toggle" role="tablist" aria-label="View mode">
                            <button type="button"
                                    class="inst-view-toggle-btn is-active"
                                    data-view="report"
                                    role="tab"
                                    aria-selected="true"
                                    aria-controls="inst-report-view">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                <span>Report</span>
                            </button>
                            <button type="button"
                                    class="inst-view-toggle-btn"
                                    data-view="data"
                                    role="tab"
                                    aria-selected="false"
                                    aria-controls="inst-data-view">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                                <span>Data</span>
                            </button>
                        </div>
                    </div>

                    <!-- REPORT VIEW (Default) -->
                    <div class="inst-panel-view inst-report-view is-active" id="inst-report-view" role="tabpanel">

                    <!-- Charts Header -->
                    <div class="inst-charts-header">
                        <div class="inst-charts-header-row">
                            <div class="inst-charts-header-text">
                                <h2 class="inst-charts-title"><?php echo esc_html(sffc_get_charts_title($title)); ?></h2>
                                <p class="inst-charts-subtitle"><?php echo esc_html($commentary ?: 'Market intelligence and analysis'); ?></p>
                                <p class="inst-charts-meta">Updated <?php echo esc_html($date); ?></p>
                            </div>
                            <button type="button" class="inst-pdf-download-btn" id="inst-pdf-download" title="Download as PDF">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="12" y1="18" x2="12" y2="12"/>
                                    <polyline points="9 15 12 18 15 15"/>
                                </svg>
                                <span>PDF</span>
                            </button>
                        </div>
                    </div>

                    <div class="inst-charts-divider"></div>

                    <?php if (!empty($key_metrics)) : ?>
                    <!-- Key Metrics -->
                    <div class="inst-metrics">
                        <?php foreach (array_slice($key_metrics, 0, 4) as $metric) : ?>
                        <div class="inst-metric">
                            <div class="inst-metric-value"><?php echo esc_html($metric['value']); ?></div>
                            <div class="inst-metric-label"><?php echo esc_html($metric['label']); ?></div>
                            <?php if (!empty($metric['sub'])) : ?>
                            <div class="inst-metric-sub"><?php echo esc_html($metric['sub']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Render charts based on type
                    $chart_index = 0;
                    $charts_to_show = $args['user_has_access'] ? $charts : array_slice($charts, 0, 2);

                    foreach ($charts_to_show as $chart) :
                        $chart_index++;
                        $is_locked = !$args['user_has_access'] && $chart_index > 1;
                    ?>

                    <?php if ($is_locked) : ?>
                    <div class="inst-premium-gate">
                        <div class="inst-premium-gate-blur">
                    <?php endif; ?>

                    <div class="inst-chart-card">
                        <div class="inst-chart-card-header">
                            <h3 class="inst-chart-card-title"><?php echo esc_html($chart['title']); ?></h3>
                            <?php if (!empty($chart['subtitle'])) : ?>
                            <p class="inst-chart-card-subtitle"><?php echo esc_html($chart['subtitle']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="inst-chart-card-body">
                            <?php sffc_render_chart($chart); ?>
                        </div>
                        <?php if (!empty($chart['source']) || !empty($chart['note'])) : ?>
                        <div class="inst-chart-card-footer">
                            <span class="inst-chart-source"><?php echo esc_html($chart['source'] ?? 'Source: MENA Careers Research'); ?></span>
                            <?php if (!empty($chart['note'])) : ?>
                            <span class="inst-chart-note"><?php echo esc_html($chart['note']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php
                        // Generate detailed chart narrative
                        $chart_narrative = sffc_generate_chart_narrative($chart, $chart_index);
                        if (!empty($chart_narrative['points'])) :
                        ?>
                        <div class="inst-chart-narrative">
                            <div class="inst-chart-narrative-header">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                <span>Chart Analysis</span>
                            </div>
                            <ul class="inst-chart-narrative-points">
                                <?php foreach ($chart_narrative['points'] as $point) : ?>
                                <li><?php echo esc_html($point); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_locked) : ?>
                        </div>
                        <div class="inst-premium-gate-overlay">
                            <svg class="inst-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <h4 class="inst-premium-title">Premium Analysis</h4>
                            <p class="inst-premium-text">Subscribe to unlock full market intelligence</p>
                            <button class="inst-premium-btn" onclick="window.location.href='<?php echo esc_url(home_url('/pricing/')); ?>'">
                                Unlock Access
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endforeach; ?>

                    <?php if (!$args['user_has_access'] && count($charts) > 2) : ?>
                    <!-- Show remaining locked charts as teasers -->
                    <div class="inst-premium-gate">
                        <div class="inst-premium-gate-blur">
                            <div class="inst-insights-box">
                                <h3 class="inst-insights-title">Additional Analysis Available</h3>
                                <div class="inst-insights-grid">
                                    <div class="inst-insight-item">
                                        <div class="inst-insight-value">+<?php echo count($charts) - 2; ?></div>
                                        <div class="inst-insight-label">More Charts</div>
                                    </div>
                                    <div class="inst-insight-item">
                                        <div class="inst-insight-value">Deep</div>
                                        <div class="inst-insight-label">Analysis</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="inst-premium-gate-overlay">
                            <svg class="inst-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <h4 class="inst-premium-title">Get Full Access</h4>
                            <p class="inst-premium-text">Unlock all charts, analysis, and research tools</p>
                            <button class="inst-premium-btn" onclick="window.location.href='<?php echo esc_url(home_url('/pricing/')); ?>'">
                                Subscribe Now
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    </div><!-- END REPORT VIEW -->

                    <!-- DATA VIEW (Spreadsheet-style with chart data) -->
                    <div class="inst-panel-view inst-data-view" id="inst-data-view" role="tabpanel" style="display: none;">
                        <div class="inst-spreadsheet">
                            <div class="inst-spreadsheet-header">
                                <div class="inst-spreadsheet-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                        <polyline points="10 9 9 9 8 9"/>
                                    </svg>
                                    <span>Data Extract</span>
                                </div>
                                <button type="button" class="inst-excel-download-btn" id="inst-excel-download" title="Download as Excel">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="12" y1="18" x2="12" y2="12"/>
                                        <polyline points="9 15 12 18 15 15"/>
                                    </svg>
                                    <span>Excel</span>
                                </button>
                            </div>

                            <table class="inst-data-table">
                                <thead>
                                    <tr>
                                        <th class="inst-col-row"></th>
                                        <th class="inst-col-a">A</th>
                                        <th class="inst-col-b">B</th>
                                        <th class="inst-col-c">C</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $row_num = 1;

                                    // ========================================
                                    // SECTION: Key Metrics (if available)
                                    // ========================================
                                    if (!empty($key_metrics)) :
                                    ?>
                                    <tr class="inst-row-header">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell inst-cell-header" colspan="3">Key Metrics</td>
                                    </tr>
                                    <tr class="inst-row-subheader">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell inst-cell-label">Metric</td>
                                        <td class="inst-cell inst-cell-label">Value</td>
                                        <td class="inst-cell inst-cell-label">Note</td>
                                    </tr>
                                    <?php foreach ($key_metrics as $metric) : ?>
                                    <tr>
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell"><?php echo esc_html($metric['label'] ?? ''); ?></td>
                                        <td class="inst-cell inst-cell-input"><?php echo esc_html($metric['value'] ?? ''); ?></td>
                                        <td class="inst-cell inst-cell-type"><?php echo esc_html($metric['sub'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="inst-row-spacer">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell" colspan="3"></td>
                                    </tr>
                                    <?php endif; ?>

                                    <?php
                                    // ========================================
                                    // SECTION: Chart Data (extracted from each chart)
                                    // ========================================
                                    if (!empty($charts)) :
                                        foreach ($charts as $chart_index => $chart) :
                                            $chart_title = $chart['title'] ?? 'Data Set ' . ($chart_index + 1);
                                            $chart_type = $chart['type'] ?? 'bar';
                                            $chart_data = $chart['data'] ?? $chart;
                                            $chart_source = $chart['source'] ?? 'MENA Careers Research';

                                            // Get the data array based on chart type
                                            $data_items = array();
                                            $value_suffix = '';

                                            if ($chart_type === 'bar' && !empty($chart_data['series'])) {
                                                $data_items = $chart_data['series'];
                                                $value_suffix = $chart_data['suffix'] ?? '';
                                            } elseif (($chart_type === 'donut' || $chart_type === 'pie') && !empty($chart_data['slices'])) {
                                                $data_items = $chart_data['slices'];
                                                $value_suffix = '%';
                                            } elseif ($chart_type === 'line' && !empty($chart_data['points'])) {
                                                $data_items = $chart_data['points'];
                                                $value_suffix = $chart_data['suffix'] ?? '';
                                            }

                                            if (empty($data_items)) continue;
                                    ?>
                                    <tr class="inst-row-header">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell inst-cell-header" colspan="3"><?php echo esc_html($chart_title); ?></td>
                                    </tr>
                                    <tr class="inst-row-subheader">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell inst-cell-label">Item</td>
                                        <td class="inst-cell inst-cell-label">Value</td>
                                        <td class="inst-cell inst-cell-label">Type</td>
                                    </tr>
                                    <?php
                                    foreach ($data_items as $item) :
                                        $item_label = $item['label'] ?? $item['name'] ?? '';
                                        $item_value = $item['value'] ?? '';

                                        // Format value with suffix
                                        $formatted_value = $item_value;
                                        if (is_numeric($item_value)) {
                                            if ($value_suffix === '%') {
                                                $formatted_value = $item_value . '%';
                                            } elseif ($value_suffix) {
                                                $formatted_value = $item_value . ' ' . $value_suffix;
                                            }
                                        }

                                        // Determine type label based on chart type
                                        $type_label = 'Data';
                                        if ($chart_type === 'donut' || $chart_type === 'pie') {
                                            $type_label = 'Share';
                                        } elseif (!empty($item['highlighted'])) {
                                            $type_label = 'Highlighted';
                                        }
                                    ?>
                                    <tr>
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell"><?php echo esc_html($item_label); ?></td>
                                        <td class="inst-cell inst-cell-input"><?php echo esc_html($formatted_value); ?></td>
                                        <td class="inst-cell inst-cell-type"><?php echo esc_html($type_label); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="inst-row-source">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell inst-cell-label">Source</td>
                                        <td class="inst-cell inst-cell-external" colspan="2"><?php echo esc_html(str_replace('Source: ', '', $chart_source)); ?></td>
                                    </tr>
                                    <tr class="inst-row-spacer">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell" colspan="3"></td>
                                    </tr>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>

                                    <?php
                                    // ========================================
                                    // SECTION: Article Classification
                                    // ========================================
                                    if ($category || $source_name || $source_url) :
                                    ?>
                                    <tr class="inst-row-header">
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell inst-cell-header" colspan="3">Article Info</td>
                                    </tr>
                                    <?php if ($category) : ?>
                                    <tr>
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell">Category</td>
                                        <td class="inst-cell inst-cell-link"><?php echo esc_html($category); ?></td>
                                        <td class="inst-cell inst-cell-type">Classification</td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($source_name || $source_url) : ?>
                                    <tr>
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell">Original Source</td>
                                        <td class="inst-cell inst-cell-external"><?php echo esc_html($source_name ?: parse_url($source_url, PHP_URL_HOST)); ?></td>
                                        <td class="inst-cell inst-cell-type">External</td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell">Published</td>
                                        <td class="inst-cell"><?php echo esc_html($date); ?></td>
                                        <td class="inst-cell inst-cell-type">Date</td>
                                    </tr>
                                    <tr>
                                        <td class="inst-row-num"><?php echo $row_num++; ?></td>
                                        <td class="inst-cell">Reading Time</td>
                                        <td class="inst-cell"><?php echo esc_html($reading_time); ?></td>
                                        <td class="inst-cell inst-cell-type">Estimate</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <div class="inst-spreadsheet-footer">
                                <div class="inst-sheet-tabs">
                                    <span class="inst-sheet-tab is-active">All Data</span>
                                </div>
                                <span class="inst-spreadsheet-note"><?php echo $row_num - 1; ?> rows extracted</span>
                            </div>
                        </div>
                    </div><!-- END DATA VIEW -->

                </div>
            </div>

        </div>

        <?php if ($args['show_chatbox']) : ?>
        <!-- MENA Careers Chat Bar (fixed, expandable) -->
        <div class="inst-chatbox" id="inst-chatbox">
            <div class="inst-chatbox-header" onclick="document.getElementById('inst-chatbox').classList.toggle('is-expanded')">
                <svg class="inst-chatbox-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span class="inst-chatbox-title">Ask MENA Careers</span>
                <span class="inst-chatbox-hint">Ask about this article...</span>
                <span class="inst-chatbox-badge">AI</span>
                <svg class="inst-chatbox-toggle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="18 15 12 9 6 15"/>
                </svg>
            </div>
            <div class="inst-chatbox-body">
                <div class="inst-chatbox-prompts">
                    <button class="inst-chatbox-prompt" data-prompt="Explain this article simply">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Explain
                    </button>
                    <button class="inst-chatbox-prompt" data-prompt="Key points from this article">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                        Summarize
                    </button>
                    <button class="inst-chatbox-prompt" data-prompt="Market implications of this news">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        Implications
                    </button>
                </div>
                <div class="inst-chatbox-input-wrap">
                    <input type="text" class="inst-chatbox-input" placeholder="Ask anything about this article...">
                    <button class="inst-chatbox-send" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Render a chart based on type
 */
function sffc_render_chart($chart) {
    $type = $chart['type'] ?? 'bar';
    $data = json_encode($chart['data'] ?? $chart);

    switch ($type) {
        case 'line':
            echo '<div data-chart="line" data-chart-data="' . esc_attr($data) . '"></div>';
            break;

        case 'donut':
        case 'pie':
            echo '<div data-chart="donut" data-chart-data="' . esc_attr($data) . '"></div>';
            break;

        case 'bar':
        default:
            echo '<div data-chart="bar" data-chart-data="' . esc_attr($data) . '"></div>';
            break;
    }
}

/**
 * Generate executive summary for research format
 */
function sffc_generate_executive_summary($post_id, $title, $content, $key_metrics, $takeaways) {
    $summary = array();

    // Determine article type for methodology
    $content_type = get_post_meta($post_id, '_content_type', true);
    $category = sffc_get_primary_category($post_id);

    // Generate methodology based on content type
    $methodologies = array(
        'deal' => 'Deal Analysis & Market Intelligence',
        'acquisition' => 'M&A Transaction Analysis',
        'funding' => 'Capital Markets Research',
        'market' => 'Sector & Market Analysis',
        'news' => 'Real-time Market Intelligence',
    );

    $method_key = 'news';
    $title_lower = strtolower($title . ' ' . $content_type . ' ' . $category);
    foreach ($methodologies as $key => $value) {
        if (strpos($title_lower, $key) !== false) {
            $method_key = $key;
            break;
        }
    }
    $summary['methodology'] = $methodologies[$method_key];

    // Generate thesis statement from title and content
    $summary['thesis'] = sffc_generate_thesis_statement($title, $content, $key_metrics);

    return $summary;
}

/**
 * Generate thesis statement for executive summary
 */
function sffc_generate_thesis_statement($title, $content, $key_metrics) {
    $plain_content = wp_strip_all_tags($content);

    // Extract first meaningful sentence as base
    $sentences = preg_split('/(?<=[.!?])\s+/', $plain_content, 3);
    $base = '';
    foreach ($sentences as $sentence) {
        if (strlen(trim($sentence)) > 40) {
            $base = trim($sentence);
            break;
        }
    }

    if (empty($base)) {
        return '';
    }

    // Enhance with key metric if available
    if (!empty($key_metrics) && !empty($key_metrics[0]['value'])) {
        $metric = $key_metrics[0];
        // Check if metric value is already in the base
        if (strpos($base, $metric['value']) === false) {
            $base = preg_replace('/\.$/', '', $base);
            $base .= ', with ' . strtolower($metric['label']) . ' of ' . $metric['value'] . '.';
        }
    }

    return $base;
}

/**
 * Generate detailed chart narrative for right panel
 */
function sffc_generate_chart_narrative($chart, $chart_index) {
    $type = $chart['type'] ?? 'bar';
    $title = $chart['title'] ?? '';
    $data = $chart['data'] ?? $chart;

    // Get data items
    $items = array();
    if ($type === 'bar' && !empty($data['series'])) {
        $items = $data['series'];
    } elseif (($type === 'donut' || $type === 'pie') && !empty($data['slices'])) {
        $items = $data['slices'];
    } elseif ($type === 'line' && !empty($data['points'])) {
        $items = $data['points'];
    }

    if (empty($items) || count($items) < 2) {
        return array();
    }

    $narrative = array(
        'title' => 'Analysis: ' . $title,
        'points' => array(),
    );

    // Calculate statistics
    $values = array_map(function($item) {
        return floatval($item['value'] ?? 0);
    }, $items);

    $max_val = max($values);
    $min_val = min($values);
    $avg_val = array_sum($values) / count($values);
    $total = array_sum($values);

    // Find max and min items
    $max_item = null;
    $min_item = null;
    foreach ($items as $item) {
        $val = floatval($item['value'] ?? 0);
        if ($val == $max_val) $max_item = $item;
        if ($val == $min_val) $min_item = $item;
    }

    $max_label = $max_item['label'] ?? $max_item['name'] ?? '';
    $min_label = $min_item['label'] ?? $min_item['name'] ?? '';
    $suffix = $data['suffix'] ?? '';

    // Generate narrative points based on chart type
    if ($type === 'donut' || $type === 'pie') {
        // Market share / distribution analysis
        $narrative['points'][] = sprintf(
            '%s dominates with %s%% market share, representing the largest segment in this distribution.',
            $max_label,
            number_format($max_val, 1)
        );

        if ($max_val > 50) {
            $narrative['points'][] = sprintf(
                'This concentration indicates %s holds a majority position (>50%%) in the market.',
                $max_label
            );
        }

        // Find runner up
        $sorted_values = $values;
        rsort($sorted_values);
        if (isset($sorted_values[1])) {
            $runner_up_val = $sorted_values[1];
            foreach ($items as $item) {
                if (floatval($item['value'] ?? 0) == $runner_up_val && ($item['label'] ?? '') !== $max_label) {
                    $narrative['points'][] = sprintf(
                        'The second largest segment is %s at %s%%, trailing by %s percentage points.',
                        $item['label'] ?? '',
                        number_format($runner_up_val, 1),
                        number_format($max_val - $runner_up_val, 1)
                    );
                    break;
                }
            }
        }

        // Remaining segments
        $remaining = count($items) - 2;
        if ($remaining > 0) {
            $remaining_share = 100 - $sorted_values[0] - ($sorted_values[1] ?? 0);
            $narrative['points'][] = sprintf(
                'The remaining %d segments collectively represent %s%% of the total.',
                $remaining,
                number_format(max(0, $remaining_share), 1)
            );
        }

    } elseif ($type === 'line') {
        // Trend analysis
        $first = reset($items);
        $last = end($items);
        $first_val = floatval($first['value'] ?? 0);
        $last_val = floatval($last['value'] ?? 0);
        $first_label = $first['label'] ?? $first['name'] ?? 'start';
        $last_label = $last['label'] ?? $last['name'] ?? 'end';

        if ($last_val > $first_val && $first_val > 0) {
            $change = (($last_val - $first_val) / $first_val) * 100;
            $narrative['points'][] = sprintf(
                'Upward trend observed: values increased from %s%s to %s%s (+%s%%).',
                number_format($first_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($last_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($change, 1)
            );
        } elseif ($last_val < $first_val && $first_val > 0) {
            $change = (($first_val - $last_val) / $first_val) * 100;
            $narrative['points'][] = sprintf(
                'Downward trend observed: values decreased from %s%s to %s%s (-%s%%).',
                number_format($first_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($last_val, 1),
                $suffix ? ' ' . $suffix : '',
                number_format($change, 1)
            );
        } else {
            $narrative['points'][] = 'Values remained relatively stable over the period analyzed.';
        }

        // Peak identification
        $narrative['points'][] = sprintf(
            'Peak value of %s%s reached at %s.',
            number_format($max_val, 1),
            $suffix ? ' ' . $suffix : '',
            $max_label
        );

        // Volatility assessment
        $variance = $max_val - $min_val;
        $volatility = ($first_val > 0) ? ($variance / $first_val) * 100 : 0;
        if ($volatility > 30) {
            $narrative['points'][] = sprintf(
                'High volatility observed with a %s%% range between peak and trough values.',
                number_format($volatility, 0)
            );
        }

    } else {
        // Bar chart - comparative analysis
        $narrative['points'][] = sprintf(
            '%s leads with %s%s, the highest value across all %d categories analyzed.',
            $max_label,
            number_format($max_val, is_float($max_val) && $max_val < 100 ? 1 : 0),
            $suffix ? ' ' . $suffix : '',
            count($items)
        );

        // Gap analysis
        if ($max_val > 0 && $min_val > 0) {
            $gap = (($max_val - $min_val) / $max_val) * 100;
            $narrative['points'][] = sprintf(
                '%s trails at the lowest position with %s%s, a %s%% gap from the leader.',
                $min_label,
                number_format($min_val, is_float($min_val) && $min_val < 100 ? 1 : 0),
                $suffix ? ' ' . $suffix : '',
                number_format($gap, 0)
            );
        }

        // Average comparison
        $narrative['points'][] = sprintf(
            'The average across all categories is %s%s.',
            number_format($avg_val, is_float($avg_val) && $avg_val < 100 ? 1 : 0),
            $suffix ? ' ' . $suffix : ''
        );

        // Above average count
        $above_avg = count(array_filter($values, function($v) use ($avg_val) {
            return $v > $avg_val;
        }));
        if ($above_avg > 0 && $above_avg < count($values)) {
            $narrative['points'][] = sprintf(
                '%d out of %d categories perform above average.',
                $above_avg,
                count($items)
            );
        }
    }

    return $narrative;
}

/**
 * Generate contextual commentary for a chart
 */
function sffc_generate_chart_commentary($chart) {
    $type = $chart['type'] ?? 'bar';
    $title = $chart['title'] ?? '';
    $data = $chart['data'] ?? $chart;

    // Get data items
    $items = array();
    if ($type === 'bar' && !empty($data['series'])) {
        $items = $data['series'];
    } elseif (($type === 'donut' || $type === 'pie') && !empty($data['slices'])) {
        $items = $data['slices'];
    } elseif ($type === 'line' && !empty($data['points'])) {
        $items = $data['points'];
    }

    if (empty($items)) {
        return '';
    }

    // Find the highest value item
    $max_item = null;
    $max_value = 0;
    $total = 0;
    foreach ($items as $item) {
        $value = floatval($item['value'] ?? 0);
        $total += $value;
        if ($value > $max_value) {
            $max_value = $value;
            $max_item = $item;
        }
    }

    if (!$max_item) {
        return '';
    }

    $max_label = $max_item['label'] ?? $max_item['name'] ?? '';
    $suffix = $data['suffix'] ?? '';

    // Generate commentary based on chart type
    if ($type === 'donut' || $type === 'pie') {
        $percentage = $max_value;
        return sprintf(
            '%s leads with %s%% of the total share, representing the dominant segment in this distribution.',
            $max_label,
            number_format($percentage, 0)
        );
    } elseif ($type === 'line') {
        // For line charts, compare first and last
        $first = reset($items);
        $last = end($items);
        $first_val = floatval($first['value'] ?? 0);
        $last_val = floatval($last['value'] ?? 0);

        if ($last_val > $first_val) {
            $change = (($last_val - $first_val) / $first_val) * 100;
            return sprintf(
                'The trend shows growth from %s to %s%s, representing a %.1f%% increase over the period.',
                number_format($first_val, 1),
                number_format($last_val, 1),
                $suffix ? ' ' . $suffix : '',
                $change
            );
        } elseif ($last_val < $first_val) {
            $change = (($first_val - $last_val) / $first_val) * 100;
            return sprintf(
                'The trend shows a decline from %s to %s%s, representing a %.1f%% decrease over the period.',
                number_format($first_val, 1),
                number_format($last_val, 1),
                $suffix ? ' ' . $suffix : '',
                $change
            );
        } else {
            return 'Values have remained relatively stable over the period shown.';
        }
    } else {
        // Bar chart
        $formatted_value = number_format($max_value, is_float($max_value) && $max_value < 100 ? 1 : 0);
        return sprintf(
            '%s stands out with %s%s, the highest value in this comparison across %d data points.',
            $max_label,
            $formatted_value,
            $suffix ? ' ' . $suffix : '',
            count($items)
        );
    }
}

/**
 * Get reading time estimate
 */
function sffc_get_reading_time($content) {
    $word_count = str_word_count(wp_strip_all_tags($content));
    $minutes = ceil($word_count / 200);
    return $minutes . ' min';
}

/**
 * Get primary category
 */
function sffc_get_primary_category($post_id) {
    $categories = get_the_category($post_id);
    if (!empty($categories)) {
        return $categories[0]->name;
    }
    return '';
}

/**
 * Generate charts title from article title
 */
function sffc_get_charts_title($title) {
    // Try to extract key topic from title
    $title = preg_replace('/^(Breaking|Exclusive|Report):\s*/i', '', $title);

    // Truncate if too long
    if (strlen($title) > 50) {
        $title = substr($title, 0, 47) . '...';
    }

    return $title;
}

/**
 * Get enhanced article data (charts, metrics, etc.)
 */
function sffc_get_enhanced_article_data($post_id, $title, $content) {
    $meta_key = '_sffc_article_intel_cache_v1';
    $cached_meta = get_post_meta($post_id, $meta_key, true);
    if (is_array($cached_meta) && !empty($cached_meta['generated_at'])) {
        return $cached_meta;
    }

    // Check cache first
    $cache_key = 'inst_article_data_' . $post_id;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    // Try to get from Dynamic Insights Generator
    if (class_exists('SFFC_Dynamic_Insights_Generator')) {
        $generator = SFFC_Dynamic_Insights_Generator::get_instance();
        $post = get_post($post_id);
        $insights = $generator->generate_insights($post);

        if (!empty($insights)) {
            $data = sffc_format_insights_for_template($insights, $title, $content);
            $data['generated_at'] = current_time('mysql');
            $data['cache_version'] = 1;
            $data['source'] = 'article_intel';
            update_post_meta($post_id, $meta_key, $data);
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
            return $data;
        }
    }

    // Fallback to basic data extraction
    $data = sffc_extract_basic_article_data($title, $content);
    $data['generated_at'] = current_time('mysql');
    $data['cache_version'] = 1;
    $data['source'] = 'template_fallback';
    update_post_meta($post_id, $meta_key, $data);
    return $data;
}

/**
 * Format Claude insights for template
 */
function sffc_format_insights_for_template($insights, $title, $content) {
    $data = array(
        'charts' => array(),
        'key_metrics' => array(),
        'takeaways' => array(),
        'commentary' => $insights['commentary'] ?? '',
    );

    // Format bar charts
    if (!empty($insights['bars'])) {
        foreach ($insights['bars'] as $bar) {
            $data['charts'][] = array(
                'type' => 'bar',
                'title' => $bar['title'],
                'subtitle' => '',
                'data' => array(
                    'series' => $bar['series'],
                    'suffix' => $bar['suffix'] ?? '',
                ),
                'source' => 'Source: MENA Careers Research',
            );
        }
    }

    // Format pie/donut charts
    if (!empty($insights['pie'])) {
        foreach ($insights['pie'] as $pie) {
            $data['charts'][] = array(
                'type' => 'donut',
                'title' => $pie['title'],
                'subtitle' => '',
                'data' => array(
                    'slices' => $pie['slices'],
                    'centerValue' => '',
                    'centerLabel' => 'Share',
                ),
                'source' => 'Source: MENA Careers Research',
            );
        }
    }

    // Format line charts
    if (!empty($insights['lines'])) {
        foreach ($insights['lines'] as $line) {
            $data['charts'][] = array(
                'type' => 'line',
                'title' => $line['title'],
                'subtitle' => $line['subtitle'] ?? '',
                'data' => array(
                    'points' => $line['points'],
                    'suffix' => $line['suffix'] ?? '',
                    'annotation' => $line['annotation'] ?? '',
                ),
                'source' => 'Source: MENA Careers Research',
            );
        }
    }

    // Extract key metrics
    if (!empty($insights['key_metrics'])) {
        $data['key_metrics'] = $insights['key_metrics'];
    }

    // Extract takeaways from content
    $data['takeaways'] = sffc_extract_takeaways($content);

    return $data;
}

/**
 * Extract basic article data when Claude is unavailable
 */
function sffc_extract_basic_article_data($title, $content) {
    $data = array(
        'charts' => array(),
        'key_metrics' => array(),
        'takeaways' => sffc_extract_takeaways($content),
        'commentary' => 'Market intelligence and analysis for this article.',
    );

    // Try to extract numbers from content for basic charts
    preg_match_all('/\$?\s*(\d+(?:\.\d+)?)\s*(billion|million|bn|mn|%)/i', $content, $matches, PREG_SET_ORDER);

    if (!empty($matches)) {
        $series = array();
        foreach (array_slice($matches, 0, 4) as $i => $match) {
            $series[] = array(
                'label' => 'Value ' . ($i + 1),
                'value' => floatval($match[1]),
                'highlighted' => $i === 0,
            );
        }

        if (!empty($series)) {
            $data['charts'][] = array(
                'type' => 'bar',
                'title' => 'Key Figures',
                'data' => array(
                    'series' => $series,
                    'suffix' => strtolower($matches[0][2] ?? ''),
                ),
            );
        }
    }

    return $data;
}

/**
 * Extract key takeaways from content
 */
function sffc_extract_takeaways($content) {
    $takeaways = array();

    // Look for bullet points or numbered lists
    if (preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $content, $matches)) {
        $takeaways = array_slice($matches[1], 0, 5);
    }

    // If no lists found, extract first sentences from paragraphs
    if (empty($takeaways)) {
        if (preg_match_all('/<p[^>]*>([^<]+)<\/p>/i', $content, $matches)) {
            foreach (array_slice($matches[1], 0, 3) as $para) {
                $sentences = preg_split('/(?<=[.!?])\s+/', trim($para), 2);
                if (!empty($sentences[0]) && strlen($sentences[0]) > 20) {
                    $takeaways[] = $sentences[0];
                }
            }
        }
    }

    return array_map('trim', $takeaways);
}

/**
 * Build comprehensive JSON-LD schema for Google News
 *
 * @param array $args Article data
 * @return array Schema data array
 */
function sffc_build_article_schema($args) {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'mainEntityOfPage' => array(
            '@type' => 'WebPage',
            '@id' => $args['canonical_url'],
        ),
        'headline' => substr($args['title'], 0, 110), // Google recommends < 110 chars
        'description' => $args['excerpt'],
        'datePublished' => $args['date_published'],
        'dateModified' => $args['date_modified'],
        'wordCount' => $args['word_count'],
        'inLanguage' => get_bloginfo('language') ?: 'en-US',
        'author' => array(
            '@type' => 'Person',
            'name' => $args['author_name'],
            'url' => $args['author_url'],
            'jobTitle' => $args['author_job_title'],
        ),
        'publisher' => array(
            '@type' => 'Organization',
            'name' => $args['site_name'],
            'url' => $args['site_url'],
            'logo' => array(
                '@type' => 'ImageObject',
                'url' => $args['publisher_logo_url'],
                'width' => 60,
                'height' => 60,
            ),
        ),
        'image' => array(
            '@type' => 'ImageObject',
            'url' => $args['featured_image_url'],
            'width' => $args['featured_image_width'],
            'height' => $args['featured_image_height'],
        ),
    );

    // Add article section if category exists
    if (!empty($args['category'])) {
        $schema['articleSection'] = $args['category'];
    }

    // Add accessibility info for paywalled content
    if ($args['is_premium']) {
        $schema['isAccessibleForFree'] = false;
        $schema['hasPart'] = array(
            '@type' => 'WebPageElement',
            'isAccessibleForFree' => false,
            'cssSelector' => '.inst-article-body',
        );
    } else {
        $schema['isAccessibleForFree'] = true;
    }

    // Add source citation if available
    if (!empty($args['source_url'])) {
        $schema['isBasedOn'] = $args['source_url'];
    }

    // Add speakable for Google Assistant (headline + key takeaways)
    $speakable_selectors = array('.inst-article-title', '.inst-article-subtitle');
    if (!empty($args['takeaways'])) {
        $speakable_selectors[] = '.inst-takeaways';
    }
    $schema['speakable'] = array(
        '@type' => 'SpeakableSpecification',
        'cssSelector' => $speakable_selectors,
    );

    return $schema;
}

/**
 * Output article meta tags for SEO and social sharing
 *
 * @param array $args Meta tag data
 */
function sffc_output_article_meta_tags($args) {
    // Check if we should output (avoid conflicts with SEO plugins)
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')) {
        // Only output JSON-LD, let SEO plugin handle meta tags
        echo "\n<!-- MENA Careers Article Schema -->\n";
        echo '<script type="application/ld+json">' . wp_json_encode($args['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        return;
    }

    echo "\n<!-- MENA Careers Article SEO Meta Tags -->\n";

    // Canonical URL
    echo '<link rel="canonical" href="' . esc_url($args['canonical_url']) . '">' . "\n";

    // Basic meta tags
    echo '<meta name="description" content="' . esc_attr($args['description']) . '">' . "\n";
    echo '<meta name="author" content="' . esc_attr($args['author']) . '">' . "\n";

    // News-specific meta tags
    echo '<meta name="news_keywords" content="' . esc_attr($args['keywords']) . '">' . "\n";
    echo '<meta name="article:published_time" content="' . esc_attr($args['date_published']) . '">' . "\n";
    echo '<meta name="article:modified_time" content="' . esc_attr($args['date_modified']) . '">' . "\n";

    // Open Graph meta tags
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($args['title']) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($args['description']) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($args['canonical_url']) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($args['image_url']) . '">' . "\n";
    echo '<meta property="og:image:width" content="1200">' . "\n";
    echo '<meta property="og:image:height" content="630">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($args['site_name']) . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";
    echo '<meta property="article:published_time" content="' . esc_attr($args['date_published']) . '">' . "\n";
    echo '<meta property="article:modified_time" content="' . esc_attr($args['date_modified']) . '">' . "\n";
    echo '<meta property="article:author" content="' . esc_attr($args['author']) . '">' . "\n";
    if (!empty($args['section'])) {
        echo '<meta property="article:section" content="' . esc_attr($args['section']) . '">' . "\n";
    }

    // Twitter Card meta tags
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($args['title']) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($args['description']) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($args['image_url']) . '">' . "\n";

    // JSON-LD Schema
    echo '<script type="application/ld+json">' . wp_json_encode($args['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

    echo "<!-- End MENA Careers Article SEO Meta Tags -->\n";
}

/**
 * Extract news keywords from title, content, and category
 *
 * @param string $title Article title
 * @param string $content Article content
 * @param string $category Primary category
 * @return string Comma-separated keywords (max 10)
 */
function sffc_extract_news_keywords($title, $content, $category) {
    $keywords = array();

    // Add category first
    if (!empty($category)) {
        $keywords[] = $category;
    }

    // Extract capitalized words (likely proper nouns/entities) from title
    preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b/', $title, $title_matches);
    if (!empty($title_matches[1])) {
        foreach (array_slice($title_matches[1], 0, 3) as $match) {
            if (strlen($match) > 2 && !in_array(strtolower($match), array('the', 'and', 'for', 'from', 'with'))) {
                $keywords[] = $match;
            }
        }
    }

    // Extract money amounts as keywords (e.g., "$50 million", "€10 billion")
    preg_match_all('/[\$€£]?\s*\d+(?:\.\d+)?\s*(?:million|billion|mn|bn|m|b)/i', $title . ' ' . wp_strip_all_tags($content), $money_matches);
    if (!empty($money_matches[0])) {
        $keywords[] = trim($money_matches[0][0]);
    }

    // Add financial terms if found
    $financial_terms = array(
        'acquisition', 'merger', 'IPO', 'funding', 'investment', 'startup',
        'venture capital', 'private equity', 'earnings', 'revenue', 'profit',
        'market', 'stock', 'shares', 'dividend', 'quarterly', 'annual'
    );

    $combined_text = strtolower($title . ' ' . wp_strip_all_tags($content));
    foreach ($financial_terms as $term) {
        if (stripos($combined_text, $term) !== false && !in_array($term, array_map('strtolower', $keywords))) {
            $keywords[] = ucfirst($term);
            if (count($keywords) >= 10) break;
        }
    }

    // Remove duplicates and limit to 10
    $keywords = array_unique($keywords);
    $keywords = array_slice($keywords, 0, 10);

    return implode(', ', $keywords);
}

/**
 * Generate news sitemap entry for an article
 *
 * @param int $post_id Post ID
 * @return array Sitemap entry data
 */
function sffc_get_news_sitemap_entry($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return null;
    }

    $publication_date = get_the_date('c', $post);
    $title = get_the_title($post);
    $category = sffc_get_primary_category($post_id);
    $keywords = sffc_extract_news_keywords($title, $post->post_content, $category);

    return array(
        'loc' => get_permalink($post_id),
        'news:news' => array(
            'news:publication' => array(
                'news:name' => get_bloginfo('name'),
                'news:language' => substr(get_bloginfo('language'), 0, 2),
            ),
            'news:publication_date' => $publication_date,
            'news:title' => $title,
            'news:keywords' => $keywords,
        ),
    );
}

/**
 * Register news sitemap with WordPress
 */
function sffc_register_news_sitemap() {
    // Hook into WordPress sitemap if available (WP 5.5+)
    add_filter('wp_sitemaps_posts_query_args', function($args, $post_type) {
        // For news articles, only include last 48 hours
        if (in_array($post_type, array('sffc_pe_news', 'sffc_pe_deal', 'sffc_pe_signal', 'sffc_pe_markets'))) {
            $args['date_query'] = array(
                array(
                    'after' => '48 hours ago',
                ),
            );
            $args['posts_per_page'] = 1000; // Google News limit
        }
        return $args;
    }, 10, 2);
}
add_action('init', 'sffc_register_news_sitemap');
