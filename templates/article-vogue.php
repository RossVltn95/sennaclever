<?php
/**
 * Vogue Business Style Article Template
 * SEO-optimized template with schema markup
 * 
 * @package SennaCareers
 * @since 6.0.0
 */

get_header();

global $post;
$article_template = new SFFC_Prep_Article_Templates();
$post_type = get_post_type();
$categories = wp_get_post_terms($post->ID, 'prep_industry');
$tags = wp_get_post_terms($post->ID, 'prep_skill');

// SEO Meta Data
$meta_description = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: wp_trim_words($post->post_content, 30);
$focus_keyword = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
$canonical_url = get_permalink($post->ID);

// Author information
$author_id = $post->post_author;
$author = get_user_by('id', $author_id);
$author_bio = get_the_author_meta('description', $author_id);
$author_url = get_author_posts_url($author_id);

// Reading time
$word_count = str_word_count(strip_tags($post->post_content));
$reading_time = max(1, round($word_count / 200));

// Image metadata
$thumbnail_id = get_post_thumbnail_id($post->ID);
$thumbnail_url = get_the_post_thumbnail_url($post->ID, 'full');
$thumbnail_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?> prefix="og: http://ogp.me/ns# article: http://ogp.me/ns/article#">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title><?php echo esc_html($post->post_title); ?> | Vogue Business Style - <?php bloginfo('name'); ?></title>
    <meta name="description" content="<?php echo esc_attr($meta_description); ?>">
    <meta name="keywords" content="<?php echo esc_attr($focus_keyword); ?>, finance careers, investment banking, luxury finance, <?php echo $categories ? esc_attr($categories[0]->name) : ''; ?>">
    <meta name="author" content="<?php echo esc_attr($author->display_name); ?>">
    <link rel="canonical" href="<?php echo esc_url($canonical_url); ?>">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?php echo esc_attr($post->post_title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($meta_description); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo esc_url($canonical_url); ?>">
    <meta property="og:image" content="<?php echo esc_url($thumbnail_url); ?>">
    <meta property="og:image:alt" content="<?php echo esc_attr($thumbnail_alt); ?>">
    <meta property="og:site_name" content="<?php bloginfo('name'); ?>">
    <meta property="article:author" content="<?php echo esc_url($author_url); ?>">
    <meta property="article:published_time" content="<?php echo get_the_date('c', $post->ID); ?>">
    <meta property="article:modified_time" content="<?php echo get_the_modified_date('c', $post->ID); ?>">
    <?php if ($categories): foreach($categories as $category): ?>
    <meta property="article:section" content="<?php echo esc_attr($category->name); ?>">
    <?php endforeach; endif; ?>
    <?php if ($tags): foreach($tags as $tag): ?>
    <meta property="article:tag" content="<?php echo esc_attr($tag->name); ?>">
    <?php endforeach; endif; ?>
    
    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($post->post_title); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($meta_description); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($thumbnail_url); ?>">
    <meta name="twitter:image:alt" content="<?php echo esc_attr($thumbnail_alt); ?>">
    
    <!-- LinkedIn Tags -->
    <meta property="linkedin:title" content="<?php echo esc_attr($post->post_title); ?>">
    <meta property="linkedin:description" content="<?php echo esc_attr($meta_description); ?>">
    <meta property="linkedin:image" content="<?php echo esc_url($thumbnail_url); ?>">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class('vogue-template'); ?>>

<!-- Schema.org Article Markup -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "<?php echo esc_url($canonical_url); ?>"
    },
    "headline": "<?php echo esc_js($post->post_title); ?>",
    "description": "<?php echo esc_js($meta_description); ?>",
    "image": {
        "@type": "ImageObject",
        "url": "<?php echo esc_url($thumbnail_url); ?>",
        "width": "1200",
        "height": "800"
    },
    "datePublished": "<?php echo get_the_date('c', $post->ID); ?>",
    "dateModified": "<?php echo get_the_modified_date('c', $post->ID); ?>",
    "author": {
        "@type": "Person",
        "name": "<?php echo esc_js($author->display_name); ?>",
        "url": "<?php echo esc_url($author_url); ?>"
    },
    "publisher": {
        "@type": "Organization",
        "name": "<?php bloginfo('name'); ?>",
        "logo": {
            "@type": "ImageObject",
            "url": "<?php echo esc_url(get_site_icon_url()); ?>"
        }
    },
    "articleSection": "<?php echo $categories ? esc_js($categories[0]->name) : 'Finance'; ?>",
    "keywords": "<?php echo esc_js($focus_keyword); ?>",
    "wordCount": <?php echo $word_count; ?>,
    "timeRequired": "PT<?php echo $reading_time; ?>M",
    "articleBody": "<?php echo esc_js(wp_strip_all_tags($post->post_content)); ?>"
}
</script>

<!-- Breadcrumb Schema -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "<?php echo esc_url(home_url()); ?>"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "Prep Materials",
            "item": "<?php echo esc_url(home_url('/prep-materials/')); ?>"
        },
        {
            "@type": "ListItem",
            "position": 3,
            "name": "<?php echo $categories ? esc_js($categories[0]->name) : 'Articles'; ?>",
            "item": "<?php echo $categories ? esc_url(get_term_link($categories[0])) : '#'; ?>"
        },
        {
            "@type": "ListItem",
            "position": 4,
            "name": "<?php echo esc_js($post->post_title); ?>",
            "item": "<?php echo esc_url($canonical_url); ?>"
        }
    ]
}
</script>

<!-- FAQ Schema for Interview Questions -->
<?php if ($post_type === 'prep_interview_q'): 
    $sample_answer = get_post_meta($post->ID, 'sample_answer', true);
    if ($sample_answer):
?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": {
        "@type": "Question",
        "name": "<?php echo esc_js($post->post_title); ?>",
        "acceptedAnswer": {
            "@type": "Answer",
            "text": "<?php echo esc_js($sample_answer); ?>"
        }
    }
}
</script>
<?php endif; endif; ?>

<!-- How-To Schema for Day in Life Articles -->
<?php if ($post_type === 'prep_day_in_life'): 
    $schedule = get_post_meta($post->ID, 'typical_schedule', true);
    if ($schedule):
?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "HowTo",
    "name": "<?php echo esc_js($post->post_title); ?>",
    "description": "<?php echo esc_js($meta_description); ?>",
    "image": "<?php echo esc_url($thumbnail_url); ?>",
    "estimatedCost": {
        "@type": "MonetaryAmount",
        "currency": "<?php echo get_post_meta($post->ID, 'currency', true) ?: 'USD'; ?>",
        "value": "<?php echo get_post_meta($post->ID, 'compensation_range', true) ?: '0'; ?>"
    },
    "step": [
        <?php 
        $schedule_items = explode("\n", $schedule);
        $steps = array();
        foreach ($schedule_items as $index => $item):
            if (trim($item)):
        ?>
        {
            "@type": "HowToStep",
            "name": "Step <?php echo ($index + 1); ?>",
            "text": "<?php echo esc_js(trim($item)); ?>"
        }<?php echo ($index < count($schedule_items) - 1) ? ',' : ''; ?>
        <?php 
            endif;
        endforeach; 
        ?>
    ]
}
</script>
<?php endif; endif; ?>

<article class="vogue-article" itemscope itemtype="https://schema.org/Article">
    <!-- Hero Section -->
    <header class="vogue-hero">
        <?php if (has_post_thumbnail()): ?>
            <div class="hero-image-container">
                <img src="<?php echo esc_url($thumbnail_url); ?>" 
                     alt="<?php echo esc_attr($thumbnail_alt); ?>"
                     class="vogue-hero-image"
                     itemprop="image"
                     loading="eager">
                <?php if ($image_credit = get_post_meta($post->ID, 'image_credit', true)): ?>
                    <div class="image-credit"><?php echo esc_html($image_credit); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="vogue-header-content">
            <!-- Breadcrumbs for SEO -->
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <ol itemscope itemtype="https://schema.org/BreadcrumbList">
                    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                        <a itemprop="item" href="<?php echo esc_url(home_url()); ?>">
                            <span itemprop="name">Home</span>
                        </a>
                        <meta itemprop="position" content="1">
                    </li>
                    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                        <a itemprop="item" href="<?php echo esc_url(home_url('/prep-materials/')); ?>">
                            <span itemprop="name">Prep Materials</span>
                        </a>
                        <meta itemprop="position" content="2">
                    </li>
                    <?php if ($categories): ?>
                    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                        <a itemprop="item" href="<?php echo esc_url(get_term_link($categories[0])); ?>">
                            <span itemprop="name"><?php echo esc_html($categories[0]->name); ?></span>
                        </a>
                        <meta itemprop="position" content="3">
                    </li>
                    <?php endif; ?>
                </ol>
            </nav>
            
            <div class="article-category">
                <?php if ($categories): ?>
                    <span class="category-label" itemprop="articleSection">
                        <?php echo esc_html($categories[0]->name); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <h1 class="vogue-headline" itemprop="headline"><?php echo esc_html($post->post_title); ?></h1>
            
            <div class="vogue-standfirst" itemprop="description">
                <?php echo wp_kses_post(get_post_meta($post->ID, 'standfirst', true) ?: wp_trim_words($post->post_content, 40)); ?>
            </div>
            
            <div class="article-meta">
                <span class="byline">
                    By <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                        <a href="<?php echo esc_url($author_url); ?>" itemprop="url">
                            <span itemprop="name"><?php echo esc_html($author->display_name); ?></span>
                        </a>
                    </span>
                </span>
                <time class="publish-date" datetime="<?php echo get_the_date('c', $post->ID); ?>" itemprop="datePublished">
                    <?php echo get_the_date('F j, Y', $post->ID); ?>
                </time>
                <meta itemprop="dateModified" content="<?php echo get_the_modified_date('c', $post->ID); ?>">
                <span class="read-time"><?php echo $reading_time; ?> min read</span>
            </div>
        </div>
    </header>
    
    <!-- Article Body -->
    <div class="vogue-article-body">
        <div class="article-content" itemprop="articleBody">
            <?php
            // Process content with Vogue styling and SEO optimization
            $content = apply_filters('the_content', $post->post_content);
            
            // Add proper heading hierarchy for SEO
            $content = preg_replace('/<h1/', '<h2', $content);
            $content = preg_replace('/<\/h1>/', '</h2>', $content);
            
            // Add schema markup to lists if present
            $content = preg_replace('/<ul>/', '<ul itemprop="mainEntity" itemscope itemtype="https://schema.org/ItemList">', $content);
            
            echo $content;
            ?>
            
            <!-- Author Bio Section -->
            <?php if ($author_bio): ?>
            <div class="author-bio" itemprop="author" itemscope itemtype="https://schema.org/Person">
                <?php echo get_avatar($author_id, 120); ?>
                <div class="author-bio-content">
                    <h3 itemprop="name"><?php echo esc_html($author->display_name); ?></h3>
                    <p itemprop="description"><?php echo esc_html($author_bio); ?></p>
                    <a href="<?php echo esc_url($author_url); ?>" itemprop="url" class="author-link">
                        View all articles by <?php echo esc_html($author->display_name); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <aside class="vogue-sidebar">
            <!-- Table of Contents for SEO -->
            <div class="table-of-contents">
                <h3>In This Article</h3>
                <nav id="toc"></nav>
            </div>
            
            <!-- Related Content -->
            <div class="vogue-trending">
                <h3>Trending in <?php echo $categories ? esc_html($categories[0]->name) : 'Finance'; ?></h3>
                <?php
                $related_args = array(
                    'post_type' => $post_type,
                    'posts_per_page' => 5,
                    'post__not_in' => array($post->ID),
                    'meta_key' => 'views',
                    'orderby' => 'meta_value_num',
                    'order' => 'DESC'
                );
                
                if ($categories) {
                    $related_args['tax_query'] = array(
                        array(
                            'taxonomy' => 'prep_industry',
                            'field' => 'term_id',
                            'terms' => $categories[0]->term_id
                        )
                    );
                }
                
                $related_posts = new WP_Query($related_args);
                
                if ($related_posts->have_posts()):
                    while ($related_posts->have_posts()): $related_posts->the_post();
                ?>
                    <article class="trending-item">
                        <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                            <?php if (has_post_thumbnail()): ?>
                                <?php the_post_thumbnail('medium', array('loading' => 'lazy')); ?>
                            <?php endif; ?>
                            <h4><?php the_title(); ?></h4>
                        </a>
                    </article>
                <?php
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </div>
            
            <!-- Newsletter CTA -->
            <div class="vogue-newsletter">
                <h3>The Finance Edit</h3>
                <p>Get the latest in finance careers and lifestyle delivered weekly</p>
                <form class="newsletter-form" action="/newsletter-signup/" method="post">
                    <input type="email" name="email" placeholder="Your email" required>
                    <input type="hidden" name="source" value="vogue-article">
                    <input type="hidden" name="article_id" value="<?php echo $post->ID; ?>">
                    <button type="submit">Subscribe</button>
                </form>
            </div>
            
            <!-- Social Sharing -->
            <div class="social-sharing">
                <h3>Share This Article</h3>
                <div class="share-buttons">
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($canonical_url); ?>&text=<?php echo urlencode($post->post_title); ?>" 
                       target="_blank" rel="noopener" aria-label="Share on Twitter">
                        <svg><!-- Twitter Icon --></svg>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($canonical_url); ?>" 
                       target="_blank" rel="noopener" aria-label="Share on LinkedIn">
                        <svg><!-- LinkedIn Icon --></svg>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($canonical_url); ?>" 
                       target="_blank" rel="noopener" aria-label="Share on Facebook">
                        <svg><!-- Facebook Icon --></svg>
                    </a>
                    <a href="mailto:?subject=<?php echo rawurlencode($post->post_title); ?>&body=<?php echo rawurlencode($canonical_url); ?>" 
                       aria-label="Share via Email">
                        <svg><!-- Email Icon --></svg>
                    </a>
                </div>
            </div>
        </aside>
    </div>
    
    <!-- Article Footer -->
    <footer class="vogue-article-footer">
        <!-- Tags -->
        <div class="article-tags">
            <h3>Topics</h3>
            <?php
            if ($tags):
                foreach ($tags as $tag):
            ?>
                <a href="<?php echo esc_url(get_term_link($tag)); ?>" rel="tag">
                    <span><?php echo esc_html($tag->name); ?></span>
                </a>
            <?php
                endforeach;
            endif;
            ?>
        </div>
        
        <!-- Next/Previous Articles -->
        <nav class="article-navigation">
            <?php
            $prev_post = get_previous_post();
            $next_post = get_next_post();
            ?>
            <?php if ($prev_post): ?>
                <div class="nav-previous">
                    <span>Previous Article</span>
                    <a href="<?php echo esc_url(get_permalink($prev_post)); ?>" rel="prev">
                        <?php echo esc_html($prev_post->post_title); ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if ($next_post): ?>
                <div class="nav-next">
                    <span>Next Article</span>
                    <a href="<?php echo esc_url(get_permalink($next_post)); ?>" rel="next">
                        <?php echo esc_html($next_post->post_title); ?>
                    </a>
                </div>
            <?php endif; ?>
        </nav>
    </footer>
</article>

<!-- Table of Contents Generator -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate TOC from h2 and h3 tags
    const content = document.querySelector('.article-content');
    const toc = document.getElementById('toc');
    const headings = content.querySelectorAll('h2, h3');
    
    if (headings.length > 0) {
        const tocList = document.createElement('ol');
        
        headings.forEach(function(heading, index) {
            const id = 'heading-' + index;
            heading.id = id;
            
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + id;
            a.textContent = heading.textContent;
            
            if (heading.tagName === 'H3') {
                li.style.paddingLeft = '20px';
            }
            
            li.appendChild(a);
            tocList.appendChild(li);
        });
        
        toc.appendChild(tocList);
    }
    
    // Smooth scroll for TOC links
    toc.addEventListener('click', function(e) {
        if (e.target.tagName === 'A') {
            e.preventDefault();
            const target = document.querySelector(e.target.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});
</script>

<!-- Performance Optimization -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="//www.googletagmanager.com">

<?php get_footer(); ?>
