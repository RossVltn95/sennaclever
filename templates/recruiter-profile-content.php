<?php
/**
 * Shared recruiter profile layout used by the single template and shortcode.
 *
 * Expects $view array from SFFC_Recruiter_Manager::get_profile_view_model.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($view) || empty($view['post'])) {
    return;
}

$post = $view['post'];
$meta = $view['meta'];
$consultants = $view['consultants'];
$services = $view['services'];
$testimonials = $view['testimonials'];
$logos = $view['logos'];
$case_studies = $view['case_studies'];
$process_steps = $view['process_steps'];
$taxonomies = $view['taxonomies'];
$cta_buttons = $view['cta_buttons'];
$aggregate_rating = $view['aggregate_rating'];
$canonical_name = $view['canonical_name'];
$schema = $view['schema'];
$hero_highlights = $view['hero_highlights'];
$hero_focus_points = $view['hero_focus_points'];
$hero_scoreboard = $view['hero_scoreboard'];
$hero_facts = $view['hero_facts'];
$metrics_summary = $view['metrics_summary'];
$metrics_has_value = $view['metrics_has_value'];
$content = $view['content'];
$thumbnail_id = $view['thumbnail_id'];
$can_edit = !empty($view['can_edit']);
$recruiter_post_type = $view['recruiter_post_type'];

$show_schema = !empty($render_args['show_schema']);
$show_quick_edit = ($meta['frontend_edit'] === '1') && $can_edit;
?>

<article id="post-<?php echo esc_attr($post->ID); ?>" <?php post_class('sffc-recruiter sffc-recruiter-profile', $post); ?>>
    <header class="sffc-recruiter__hero">
        <div class="sffc-recruiter__hero-inner">
            <div class="sffc-recruiter__hero-main">
                <div class="sffc-recruiter__hero-identity">
                    <?php if ($thumbnail_id) : ?>
                        <div class="sffc-recruiter__logo">
                            <?php echo wp_get_attachment_image($thumbnail_id, 'medium', false, ['class' => 'sffc-recruiter__logo-img']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="sffc-recruiter__hero-heading">
                        <span class="sffc-recruiter__hero-badge"><?php esc_html_e('Private Markets Search Partner', 'senna-finance'); ?></span>
                        <h1 class="sffc-recruiter__title"><?php echo esc_html($canonical_name); ?></h1>
                        <?php if (!empty($meta['tagline'])) : ?>
                            <p class="sffc-recruiter__tagline"><?php echo esc_html($meta['tagline']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($hero_highlights)) : ?>
                    <div class="sffc-recruiter__hero-chips">
                        <?php foreach ($hero_highlights as $highlight) : ?>
                            <span class="sffc-recruiter__hero-chip"><?php echo esc_html($highlight); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($hero_focus_points)) : ?>
                    <div class="sffc-recruiter__hero-insight">
                        <p class="sffc-recruiter__hero-insight-title"><?php esc_html_e('Mandate radar', 'senna-finance'); ?></p>
                        <ul class="sffc-recruiter__hero-insight-list">
                            <?php foreach ($hero_focus_points as $focus_point) : ?>
                                <li><?php echo esc_html($focus_point); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($hero_scoreboard)) : ?>
                    <div class="sffc-recruiter__hero-scoreboard">
                        <?php foreach ($hero_scoreboard as $index => $score) :
                            $classes = ['sffc-recruiter__hero-score'];
                            if ($index === 0) {
                                $classes[] = 'sffc-recruiter__hero-score--primary';
                            }
                            ?>
                            <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                                <span class="sffc-recruiter__hero-score-value"><?php echo esc_html($score['value']); ?></span>
                                <span class="sffc-recruiter__hero-score-label"><?php echo esc_html($score['label']); ?></span>
                                <?php if (!empty($score['detail'])) : ?>
                                    <span class="sffc-recruiter__hero-score-detail"><?php echo esc_html($score['detail']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($hero_facts)) : ?>
                    <dl class="sffc-recruiter__hero-facts">
                        <?php foreach ($hero_facts as $fact) : ?>
                            <div class="sffc-recruiter__hero-fact">
                                <dt><?php echo esc_html($fact['label']); ?></dt>
                                <dd><?php echo esc_html($fact['value']); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>

                <?php if (!empty($cta_buttons)) : ?>
                    <div class="sffc-recruiter__hero-actions">
                        <?php foreach ($cta_buttons as $index => $cta) :
                            $target = $cta['url'] ? esc_url($cta['url']) : esc_url($cta['anchor']);
                            $button_class = $index === 0 ? 'sffc-recruiter__button sffc-recruiter__button--primary' : 'sffc-recruiter__button sffc-recruiter__button--outline';
                            ?>
                            <a class="<?php echo esc_attr($button_class); ?>" href="<?php echo $target; ?>"><?php echo esc_html($cta['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="sffc-recruiter__hero-aside">
                <?php if (!empty($meta['video'])) : ?>
                    <div class="sffc-recruiter__hero-aside-card sffc-recruiter__hero-aside-card--media">
                        <p class="sffc-recruiter__hero-aside-title"><?php esc_html_e('Inside the mandate', 'senna-finance'); ?></p>
                        <div class="sffc-recruiter__video">
                            <?php echo wp_oembed_get($meta['video']) ?: wp_kses_post($meta['video']); ?>
                        </div>
                    </div>
                <?php elseif (!empty($hero_scoreboard)) :
                    $primary_score = $hero_scoreboard[0]; ?>
                    <div class="sffc-recruiter__hero-aside-card sffc-recruiter__hero-aside-card--summary">
                        <p class="sffc-recruiter__hero-aside-title"><?php esc_html_e('Performance snapshot', 'senna-finance'); ?></p>
                        <div class="sffc-recruiter__hero-summary-metric">
                            <span class="sffc-recruiter__hero-summary-value"><?php echo esc_html($primary_score['value']); ?></span>
                            <span class="sffc-recruiter__hero-summary-label"><?php echo esc_html($primary_score['label']); ?></span>
                        </div>
                        <?php if (!empty($primary_score['detail'])) : ?>
                            <p class="sffc-recruiter__hero-summary-detail"><?php echo esc_html($primary_score['detail']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($can_edit) : ?>
                    <a class="sffc-recruiter__edit-link" href="<?php echo esc_url(get_edit_post_link($post)); ?>"><?php esc_html_e('Edit this recruiter', 'senna-finance'); ?></a>
                <?php endif; ?>
            </aside>
        </div>
    </header>

    <?php if ($metrics_has_value) : ?>
        <div class="sffc-company-metrics sffc-company-metrics--recruiter">
            <?php foreach ($metrics_summary as $metric_item) : ?>
                <div class="sffc-company-metric">
                    <span class="sffc-company-metric__label"><?php echo esc_html($metric_item['label']); ?></span>
                    <span class="sffc-company-metric__value"><?php echo esc_html($metric_item['value'] ?: '—'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_quick_edit) : ?>
        <div class="sffc-recruiter__edit-bar">
            <?php if (isset($_GET['sffc_recruiter_updated'])) : ?>
                <div class="sffc-recruiter__notice sffc-recruiter__notice--success"><?php esc_html_e('Profile updated successfully.', 'senna-finance'); ?></div>
            <?php endif; ?>
            <details class="sffc-recruiter__edit-panel">
                <summary><?php esc_html_e('Quick edit profile', 'senna-finance'); ?></summary>
                <form class="sffc-recruiter__edit-form" method="post">
                    <?php wp_nonce_field('sffc_recruiter_frontend_update', 'sffc_recruiter_frontend_nonce'); ?>
                    <input type="hidden" name="sffc_recruiter_post_id" value="<?php echo esc_attr($post->ID); ?>">
                    <input type="hidden" name="sffc_recruiter_frontend_update" value="1">
                    <div class="sffc-recruiter__edit-grid">
                        <label>
                            <span><?php esc_html_e('Tagline', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[tagline]" value="<?php echo esc_attr($meta['tagline']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Headquarters', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[location]" value="<?php echo esc_attr($meta['location']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Team size', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[size]" value="<?php echo esc_attr($meta['size']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Locations covered', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[locations_summary]" value="<?php echo esc_attr($meta['locations_summary']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Role focus', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[role_focus]" value="<?php echo esc_attr($meta['role_focus']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Candidate seniority', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[candidate_seniority]" value="<?php echo esc_attr($meta['candidate_seniority']); ?>">
                        </label>
                        <label class="sffc-recruiter__edit-wide">
                            <span><?php esc_html_e('About the recruiter', 'senna-finance'); ?></span>
                            <textarea name="sffc_recruiter_frontend[about]" rows="5"><?php echo esc_textarea(wp_strip_all_tags($post->post_content)); ?></textarea>
                        </label>
                        <label>
                            <span><?php esc_html_e('Contact name', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[contact_person]" value="<?php echo esc_attr($meta['contact_person']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Contact email', 'senna-finance'); ?></span>
                            <input type="email" name="sffc_recruiter_frontend[contact_email]" value="<?php echo esc_attr($meta['contact_email']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Contact phone', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[contact_phone]" value="<?php echo esc_attr($meta['contact_phone']); ?>">
                        </label>
                        <label class="sffc-recruiter__edit-wide">
                            <span><?php esc_html_e('Address / locations', 'senna-finance'); ?></span>
                            <textarea name="sffc_recruiter_frontend[contact_address]" rows="3"><?php echo esc_textarea($meta['contact_address']); ?></textarea>
                        </label>
                        <label>
                            <span><?php esc_html_e('Website URL', 'senna-finance'); ?></span>
                            <input type="url" name="sffc_recruiter_frontend[contact_website]" value="<?php echo esc_attr($meta['contact_website']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Contact CTA label', 'senna-finance'); ?></span>
                            <input type="text" name="sffc_recruiter_frontend[contact_cta_label]" value="<?php echo esc_attr($meta['contact_cta_label']); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Contact CTA URL', 'senna-finance'); ?></span>
                            <input type="url" name="sffc_recruiter_frontend[contact_cta_url]" value="<?php echo esc_attr($meta['contact_cta_url']); ?>">
                        </label>
                    </div>
                    <div class="sffc-recruiter__edit-actions">
                        <button type="submit" class="sffc-recruiter__button sffc-recruiter__button--primary"><?php esc_html_e('Save changes', 'senna-finance'); ?></button>
                    </div>
                </form>
            </details>
        </div>
    <?php endif; ?>

    <nav class="sffc-recruiter__nav">
        <div class="sffc-recruiter__nav-inner">
            <a href="#about"><?php esc_html_e('About', 'senna-finance'); ?></a>
            <a href="#specializations"><?php esc_html_e('Coverage', 'senna-finance'); ?></a>
            <a href="#recruiter-jobs"><?php esc_html_e('Active Jobs', 'senna-finance'); ?></a>
            <a href="#consultants"><?php esc_html_e('Consultants', 'senna-finance'); ?></a>
            <a href="#solutions"><?php esc_html_e('Solutions', 'senna-finance'); ?></a>
            <a href="#case-studies"><?php esc_html_e('Case Studies', 'senna-finance'); ?></a>
            <a href="#process"><?php esc_html_e('Our Process', 'senna-finance'); ?></a>
            <a href="#reviews"><?php esc_html_e('Reviews', 'senna-finance'); ?></a>
            <a href="#contact"><?php esc_html_e('Contact', 'senna-finance'); ?></a>
        </div>
    </nav>

    <div class="sffc-recruiter__content">
        <section id="about" class="sffc-recruiter__section sffc-recruiter__section--about">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('About the Recruiter', 'senna-finance'); ?></h2>
            </header>
            <div class="sffc-recruiter__rich-text">
                <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </section>

        <section id="specializations" class="sffc-recruiter__section sffc-recruiter__section--coverage">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Specializations & Coverage', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Industries, functions, role levels, and regions covered by the team.', 'senna-finance'); ?></p>
            </header>
            <?php if (array_filter($taxonomies)) : ?>
                <div class="sffc-recruiter__coverage-grid">
                    <?php
                    $coverage_titles = [
                        'industries' => __('Industries', 'senna-finance'),
                        'roles' => __('Role Levels', 'senna-finance'),
                        'functions' => __('Functions', 'senna-finance'),
                        'regions' => __('Regions', 'senna-finance'),
                    ];
                    foreach ($taxonomies as $label => $values) :
                        if (empty($values)) {
                            continue;
                        }
                        ?>
                        <div class="sffc-recruiter__coverage-card">
                            <h3 class="sffc-recruiter__coverage-title"><?php echo esc_html($coverage_titles[$label]); ?></h3>
                            <ul class="sffc-recruiter__coverage-list">
                                <?php foreach ($values as $value) : ?>
                                    <li><?php echo esc_html($value); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('Coverage details coming soon.', 'senna-finance'); ?></p>
            <?php endif; ?>
        </section>

        <section id="recruiter-jobs" class="sffc-recruiter__section sffc-recruiter__section--jobs">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Active Mandates', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Live searches managed by this recruiter. Filter by role, location, seniority, or sector.', 'senna-finance'); ?></p>
            </header>
            <?php
            $current_page = max(1, (int) get_query_var('paged', 1));
            $filters = [
                'role' => isset($_GET['role']) ? sanitize_text_field(wp_unslash($_GET['role'])) : '',
                'location' => isset($_GET['location']) ? sanitize_text_field(wp_unslash($_GET['location'])) : '',
                'seniority' => isset($_GET['seniority']) ? sanitize_text_field(wp_unslash($_GET['seniority'])) : '',
                'sector' => isset($_GET['sector']) ? sanitize_text_field(wp_unslash($_GET['sector'])) : '',
            ];

            $job_meta_query = [
                'relation' => 'OR',
                [
                    'key' => '_sffc_recruiter_id',
                    'value' => $post->ID,
                    'compare' => '=',
                ],
                [
                    'key' => 'sffc_recruiter_name',
                    'value' => $canonical_name,
                    'compare' => '=',
                ],
            ];

            $tax_query = ['relation' => 'AND'];
            if ($filters['role']) {
                $tax_query[] = [
                    'taxonomy' => 'job_level',
                    'field' => 'slug',
                    'terms' => $filters['role'],
                ];
            }
            if ($filters['location']) {
                $tax_query[] = [
                    'taxonomy' => 'job_location',
                    'field' => 'slug',
                    'terms' => $filters['location'],
                ];
            }
            if ($filters['seniority']) {
                $tax_query[] = [
                    'taxonomy' => 'job_level',
                    'field' => 'slug',
                    'terms' => $filters['seniority'],
                ];
            }
            if ($filters['sector']) {
                $tax_query[] = [
                    'taxonomy' => 'job_industry',
                    'field' => 'slug',
                    'terms' => $filters['sector'],
                ];
            }

            $job_args = [
                'post_type' => 'sffc_job',
                'posts_per_page' => 10,
                'paged' => $current_page,
                'meta_query' => $job_meta_query,
            ];

            if (count($tax_query) > 1) {
                $job_args['tax_query'] = $tax_query;
            }

            $jobs_query = new WP_Query($job_args);
            ?>
            <form class="sffc-recruiter__filters" method="get">
                <?php foreach ($filters as $key => $value) : ?>
                    <label class="sffc-recruiter__filter">
                        <span class="sffc-recruiter__filter-label">
                            <?php
                            $labels = [
                                'role' => __('Role', 'senna-finance'),
                                'location' => __('Location', 'senna-finance'),
                                'seniority' => __('Seniority', 'senna-finance'),
                                'sector' => __('Sector', 'senna-finance'),
                            ];
                            echo esc_html($labels[$key]);
                            ?>
                        </span>
                        <input class="sffc-recruiter__filter-input" type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php esc_attr_e('Any', 'senna-finance'); ?>">
                    </label>
                <?php endforeach; ?>
                <div class="sffc-recruiter__filter-actions">
                    <button class="sffc-recruiter__button sffc-recruiter__button--primary" type="submit"><?php esc_html_e('Filter', 'senna-finance'); ?></button>
                    <a class="sffc-recruiter__button sffc-recruiter__button--ghost" href="<?php echo esc_url(get_permalink($post)); ?>"><?php esc_html_e('Reset', 'senna-finance'); ?></a>
                </div>
            </form>
            <?php if ($jobs_query->have_posts()) : ?>
                <div class="sffc-recruiter__jobs">
                    <?php while ($jobs_query->have_posts()) : $jobs_query->the_post(); ?>
                        <article class="sffc-recruiter__job-card">
                            <header class="sffc-recruiter__job-header">
                                <h3 class="sffc-recruiter__job-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                                <p class="sffc-recruiter__job-meta"><?php echo esc_html(get_post_meta(get_the_ID(), 'job_location_display', true)); ?></p>
                            </header>
                            <div class="sffc-recruiter__job-tags">
                                <?php
                                $tags = [
                                    get_post_meta(get_the_ID(), 'job_level_display', true),
                                    get_post_meta(get_the_ID(), 'job_type_display', true),
                                    get_post_meta(get_the_ID(), 'job_industry_display', true),
                                ];
                                foreach (array_filter($tags) as $tag) :
                                    ?>
                                    <span class="sffc-recruiter__job-tag"><?php echo esc_html($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="sffc-recruiter__job-actions">
                                <a class="sffc-recruiter__button sffc-recruiter__button--primary" href="<?php the_permalink(); ?>"><?php esc_html_e('View Details', 'senna-finance'); ?></a>
                                <?php
                                // Check all possible application URL meta keys
                                $apply_url = get_post_meta(get_the_ID(), '_sffc_job_application_url', true);
                                if (empty($apply_url)) {
                                    $apply_url = get_post_meta(get_the_ID(), 'sffc_application_url', true);
                                }
                                if (empty($apply_url)) {
                                    $apply_url = get_post_meta(get_the_ID(), 'sffc_apply_url', true);
                                }
                                if (empty($apply_url)) {
                                    $apply_url = get_post_meta(get_the_ID(), 'job_apply_url', true);
                                }
                                ?>
                                <?php if ($apply_url) : ?>
                                    <a class="sffc-recruiter__button sffc-recruiter__button--outline" href="<?php echo esc_url($apply_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apply', 'senna-finance'); ?></a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
                <div class="sffc-recruiter__pagination" role="navigation" aria-label="<?php esc_attr_e('Job pagination', 'senna-finance'); ?>">
                    <div class="sffc-recruiter__pagination-summary">
                        <?php
                        printf(
                            esc_html__('Page %1$s of %2$s', 'senna-finance'),
                            number_format_i18n($current_page),
                            number_format_i18n(max(1, (int) $jobs_query->max_num_pages))
                        );
                        ?>
                    </div>
                    <div class="sffc-recruiter__pagination-links">
                        <?php
                        echo paginate_links([
                            'total' => $jobs_query->max_num_pages,
                            'current' => $current_page,
                            'add_args' => array_filter($filters),
                        ]);
                        ?>
                    </div>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('No live mandates at the moment — we’ll surface opportunities here the moment they appear.', 'senna-finance'); ?></p>
            <?php endif;
            wp_reset_postdata(); ?>
        </section>

        <section id="consultants" class="sffc-recruiter__section sffc-recruiter__section--consultants">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Our Consultants', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Specialist advisors leading retained and exclusive mandates.', 'senna-finance'); ?></p>
            </header>
            <?php if (!empty($consultants)) : ?>
                <div class="sffc-recruiter__consultants">
                    <?php foreach ($consultants as $consultant) :
                        $photo_source = $consultant['photo'] ?? '';
                        ?>
                        <article class="sffc-recruiter__consultant-card">
                            <div class="sffc-recruiter__consultant-header">
                                <div class="sffc-recruiter__consultant-avatar">
                                    <?php
                                    if ($photo_source) {
                                        if (is_numeric($photo_source)) {
                                            $photo_id = (int) $photo_source;
                                            if ($photo_id) {
                                                echo wp_get_attachment_image($photo_id, 'thumbnail', false, ['class' => 'sffc-recruiter__consultant-avatar-img']);
                                            }
                                        } else {
                                            $alt = esc_attr($consultant['name'] ?? '');
                                            printf(
                                                "<img src='%s' class='sffc-recruiter__consultant-avatar-img' alt='%s'>",
                                                esc_url($photo_source),
                                                $alt
                                            );
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="sffc-recruiter__consultant-meta">
                                    <h3 class="sffc-recruiter__consultant-name"><?php echo esc_html($consultant['name'] ?? ''); ?></h3>
                                    <p class="sffc-recruiter__consultant-title"><?php echo esc_html($consultant['title'] ?? ''); ?></p>
                                </div>
                            </div>
                            <?php if (!empty($consultant['specialization'])) : ?>
                                <p class="sffc-recruiter__consultant-summary"><?php echo esc_html($consultant['specialization']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($consultant['linkedin'])) : ?>
                                <a class="sffc-recruiter__consultant-link" href="<?php echo esc_url($consultant['linkedin']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('LinkedIn', 'senna-finance'); ?></a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('Consultant details coming soon.', 'senna-finance'); ?></p>
            <?php endif; ?>
        </section>

        <section id="solutions" class="sffc-recruiter__section sffc-recruiter__section--solutions">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Solutions for Clients', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Mandate structures and partnerships tailored for funds and portfolio companies.', 'senna-finance'); ?></p>
            </header>
            <?php if (!empty($services)) : ?>
                <div class="sffc-recruiter__services">
                    <?php foreach ($services as $service) : ?>
                        <article class="sffc-recruiter__service-card">
                            <h3 class="sffc-recruiter__service-title"><?php echo esc_html($service['title'] ?? ''); ?></h3>
                            <p class="sffc-recruiter__service-summary"><?php echo esc_html($service['description'] ?? ''); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('Service information coming soon.', 'senna-finance'); ?></p>
            <?php endif; ?>

            <?php if (!empty($logos)) : ?>
                <div class="sffc-recruiter__logos">
                    <?php foreach ($logos as $logo_id) :
                        $logo_id = (int) $logo_id;
                        if ($logo_id) {
                            echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'sffc-recruiter__logo-client']);
                        }
                    endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($meta['cta_call'])) : ?>
                <div id="recruiter-cta" class="sffc-recruiter__cta-panel">
                    <h3 class="sffc-recruiter__cta-panel-title"><?php esc_html_e('Ready to brief a search?', 'senna-finance'); ?></h3>
                    <a class="sffc-recruiter__button sffc-recruiter__button--light" href="<?php echo esc_url($meta['cta_call']); ?>"><?php esc_html_e('Request a Call', 'senna-finance'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <section id="case-studies" class="sffc-recruiter__section sffc-recruiter__section--case-studies">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Case Studies', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Recent mandates showcasing search execution and outcomes.', 'senna-finance'); ?></p>
            </header>
            <?php if (!empty($case_studies)) : ?>
                <div class="sffc-recruiter__case-studies">
                    <?php foreach ($case_studies as $case) : ?>
                        <article class="sffc-recruiter__case-card">
                            <h3 class="sffc-recruiter__case-title"><?php echo esc_html($case['title'] ?? ''); ?></h3>
                            <?php if (!empty($case['summary'])) : ?>
                                <p class="sffc-recruiter__case-summary"><?php echo esc_html($case['summary']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($case['impact'])) : ?>
                                <p class="sffc-recruiter__case-impact"><strong><?php esc_html_e('Impact', 'senna-finance'); ?>:</strong> <?php echo esc_html($case['impact']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($case['link'])) : ?>
                                <a class="sffc-recruiter__case-link" href="<?php echo esc_url($case['link']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View engagement', 'senna-finance'); ?></a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('Case studies will be published here soon.', 'senna-finance'); ?></p>
            <?php endif; ?>
        </section>

        <section id="process" class="sffc-recruiter__section sffc-recruiter__section--process">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Our Process', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Every retained search runs through these milestones.', 'senna-finance'); ?></p>
            </header>
            <?php if (!empty($process_steps)) : ?>
                <ol class="sffc-recruiter__process">
                    <?php foreach ($process_steps as $index => $step) : ?>
                        <li class="sffc-recruiter__process-step">
                            <span class="sffc-recruiter__process-index"><?php echo esc_html($index + 1); ?></span>
                            <div>
                                <h3 class="sffc-recruiter__process-title"><?php echo esc_html($step['title'] ?? ''); ?></h3>
                                <?php if (!empty($step['description'])) : ?>
                                    <p class="sffc-recruiter__process-description"><?php echo esc_html($step['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('We will outline our process soon.', 'senna-finance'); ?></p>
            <?php endif; ?>
        </section>

        <section id="reviews" class="sffc-recruiter__section sffc-recruiter__section--reviews">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Client Reviews', 'senna-finance'); ?></h2>
                <?php if ($aggregate_rating) : ?>
                    <div class="sffc-recruiter__reviews-summary">
                        <span class="sffc-recruiter__reviews-score"><?php echo esc_html(number_format_i18n($aggregate_rating, 1)); ?> / 5</span>
                        <span class="sffc-recruiter__reviews-count"><?php printf(esc_html__('%d reviews', 'senna-finance'), count($testimonials)); ?></span>
                    </div>
                <?php endif; ?>
            </header>
            <?php if (!empty($testimonials)) : ?>
                <div class="sffc-recruiter__testimonials">
                    <?php foreach ($testimonials as $testimonial) : ?>
                        <blockquote class="sffc-recruiter__testimonial">
                            <p>“<?php echo esc_html($testimonial['quote'] ?? ''); ?>”</p>
                            <footer>
                                <span class="sffc-recruiter__testimonial-name"><?php echo esc_html($testimonial['name'] ?? ''); ?></span>
                                <?php if (!empty($testimonial['role'])) : ?>
                                    <span class="sffc-recruiter__testimonial-role"><?php echo esc_html($testimonial['role']); ?></span>
                                <?php endif; ?>
                            </footer>
                        </blockquote>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('Be the first to share feedback on this recruiter.', 'senna-finance'); ?></p>
            <?php endif; ?>
        </section>

        <section id="contact" class="sffc-recruiter__section sffc-recruiter__section--contact">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Contact the Team', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Reach out to scope a search or request candidate shortlists.', 'senna-finance'); ?></p>
            </header>
            <div class="sffc-recruiter__contact-grid">
                <div class="sffc-recruiter__contact-card">
                    <?php if (!empty($meta['contact_person'])) : ?>
                        <p><strong><?php esc_html_e('Primary contact', 'senna-finance'); ?>:</strong> <?php echo esc_html($meta['contact_person']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meta['contact_email'])) : ?>
                        <p><strong><?php esc_html_e('Email', 'senna-finance'); ?>:</strong> <a href="mailto:<?php echo esc_attr($meta['contact_email']); ?>"><?php echo esc_html($meta['contact_email']); ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($meta['contact_phone'])) :
                        $dial = preg_replace('/[^0-9+]/', '', $meta['contact_phone']); ?>
                        <p><strong><?php esc_html_e('Phone', 'senna-finance'); ?>:</strong> <a href="tel:<?php echo esc_attr($dial); ?>"><?php echo esc_html($meta['contact_phone']); ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($meta['contact_address'])) : ?>
                        <p><strong><?php esc_html_e('Offices', 'senna-finance'); ?>:</strong><br><?php echo nl2br(esc_html($meta['contact_address'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meta['contact_website'])) :
                        $host = parse_url($meta['contact_website'], PHP_URL_HOST);
                        ?>
                        <p><strong><?php esc_html_e('Website', 'senna-finance'); ?>:</strong> <a href="<?php echo esc_url($meta['contact_website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($host ?: $meta['contact_website']); ?></a></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($meta['contact_cta_label']) && !empty($meta['contact_cta_url'])) : ?>
                    <div class="sffc-recruiter__contact-cta">
                        <h3><?php esc_html_e('Start the conversation', 'senna-finance'); ?></h3>
                        <a class="sffc-recruiter__button sffc-recruiter__button--primary" href="<?php echo esc_url($meta['contact_cta_url']); ?>"><?php echo esc_html($meta['contact_cta_label']); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="metrics" class="sffc-recruiter__section sffc-recruiter__section--metrics">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Performance Metrics', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Operational metrics from the last 12 months.', 'senna-finance'); ?></p>
            </header>
            <div class="sffc-recruiter__metrics-grid">
                <div class="sffc-recruiter__metric">
                    <span class="sffc-recruiter__metric-label"><?php esc_html_e('Placements (12 mo)', 'senna-finance'); ?></span>
                    <span class="sffc-recruiter__metric-value"><?php echo esc_html($meta['metric_placements'] ?: '—'); ?></span>
                </div>
                <div class="sffc-recruiter__metric">
                    <span class="sffc-recruiter__metric-label"><?php esc_html_e('Avg. time to place', 'senna-finance'); ?></span>
                    <span class="sffc-recruiter__metric-value"><?php echo esc_html($meta['metric_time'] ? $meta['metric_time'] . ' ' . __('days', 'senna-finance') : '—'); ?></span>
                </div>
                <div class="sffc-recruiter__metric">
                    <span class="sffc-recruiter__metric-label"><?php esc_html_e('Candidate NPS', 'senna-finance'); ?></span>
                    <span class="sffc-recruiter__metric-value"><?php echo esc_html($meta['metric_nps'] ?: '—'); ?></span>
                </div>
                <div class="sffc-recruiter__metric">
                    <span class="sffc-recruiter__metric-label"><?php esc_html_e('Active mandates', 'senna-finance'); ?></span>
                    <span class="sffc-recruiter__metric-value"><?php echo esc_html($meta['metric_mandates'] ?: '—'); ?></span>
                </div>
            </div>
        </section>

        <section id="related" class="sffc-recruiter__section sffc-recruiter__section--related">
            <header class="sffc-recruiter__section-header">
                <h2 class="sffc-recruiter__section-title"><?php esc_html_e('Related Recruiters', 'senna-finance'); ?></h2>
                <p class="sffc-recruiter__section-lead"><?php esc_html_e('Other agencies covering similar mandates.', 'senna-finance'); ?></p>
            </header>
            <?php
            $related_args = [
                'post_type' => $recruiter_post_type,
                'post__not_in' => [$post->ID],
                'posts_per_page' => 3,
                'orderby' => 'rand',
            ];

            $terms = wp_get_post_terms($post->ID, ['sffc_recruiter_industry', 'sffc_recruiter_region'], ['fields' => 'ids']);
            if (!empty($terms)) {
                $related_args['tax_query'] = [[
                    'taxonomy' => 'sffc_recruiter_industry',
                    'field' => 'term_id',
                    'terms' => $terms,
                ]];
            }

            $related_query = new WP_Query($related_args);
            if ($related_query->have_posts()) : ?>
                <div class="sffc-recruiter__related">
                    <?php while ($related_query->have_posts()) : $related_query->the_post(); ?>
                        <article class="sffc-recruiter__related-card">
                            <h3 class="sffc-recruiter__related-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p class="sffc-recruiter__related-summary"><?php echo esc_html(get_post_meta(get_the_ID(), '_sffc_recruiter_tagline', true)); ?></p>
                            <a class="sffc-recruiter__related-link" href="<?php the_permalink(); ?>"><?php esc_html_e('View profile', 'senna-finance'); ?></a>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p class="sffc-recruiter__empty-text"><?php esc_html_e('Once more recruiters are added, you will see related profiles here.', 'senna-finance'); ?></p>
            <?php endif;
            wp_reset_postdata(); ?>
        </section>
    </div>
</article>

<?php if ($show_schema && !empty($schema)) : ?>
<script type="application/ld+json">
<?php echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</script>
<?php endif; ?>
