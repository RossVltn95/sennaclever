<?php

/**
 * Company profile front-end template
 */

if (!defined('ABSPATH')) {
    exit;
}

$info     = $profile['basic_info'];
$metrics  = $profile['metrics'];
$news     = $profile['recent_news'];
$jobs     = $profile['jobs'];
$portfolio = $profile['portfolio'];
$team     = $profile['team'];
$activity = $profile['market_activity'];
$active_funds = $profile['active_funds'];

$regions  = !empty($info['regions']) ? $info['regions'] : array();
$sectors  = !empty($info['sectors']) ? $info['sectors'] : array();
$website  = !empty($info['website']) ? esc_url($info['website']) : '';
$linkedin = !empty($info['linkedin']) ? esc_url($info['linkedin']) : '';
$geo_focus = !empty($regions) ? implode(', ', $regions) : 'Global';

$initials = '';
if (!empty($info['name'])) {
    $words = preg_split('/\s+/', trim($info['name']));
    foreach (array_slice($words, 0, 3) as $word) {
        if ($word !== '') {
            $initials .= strtoupper(mb_substr($word, 0, 1));
        }
        if (strlen($initials) >= 3) {
            break;
        }
    }
    $initials = mb_substr($initials, 0, 3);
}

?>
<div class="sffc-company-profile">
    <?php if (current_user_can('edit_post', $info['id']) && isset($_GET['portfolio_status']) && $_GET['portfolio_status'] === 'added') : ?>
        <div class="sffc-company-notice sffc-company-notice--success"><?php esc_html_e('Portfolio company added successfully.', 'senna-finance'); ?></div>
    <?php endif; ?>
    <div class="sffc-company-hero">
        <div class="sffc-company-hero__logo">
            <?php if (!empty($info['logo'])) : ?>
                <img src="<?php echo esc_url($info['logo']); ?>" alt="<?php echo esc_attr($info['name']); ?> logo">
            <?php else : ?>
                <span class="sffc-company-hero__initials"><?php echo esc_html($initials ?: strtoupper(substr($info['name'], 0, 2))); ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h1 class="sffc-company-hero__name"><?php echo esc_html($info['name']); ?></h1>
            <div class="sffc-company-hero__meta">
                <?php if (!empty($info['founded'])) : ?>
                    <span class="sffc-company-pill">Founded <?php echo esc_html($info['founded']); ?></span>
                <?php endif; ?>
                <?php if (!empty($info['headquarters'])) : ?>
                    <span class="sffc-company-pill">HQ <?php echo esc_html($info['headquarters']); ?></span>
                <?php endif; ?>
                <?php if (!empty($metrics['portfolio_companies'])) : ?>
                    <span class="sffc-company-pill">Portfolio <?php echo esc_html(number_format_i18n($metrics['portfolio_companies'])); ?></span>
                <?php endif; ?>
                <?php if (!empty($metrics['news_week'])) : ?>
                    <span class="sffc-company-pill">News <?php echo esc_html($metrics['news_week']); ?> this week</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($info['description'])) : ?>
                <div class="sffc-company-description">
                    <?php echo wp_kses_post(wpautop($info['description'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $deal_count   = isset($metrics['active_deals']) ? absint($metrics['active_deals']) : 0;
    $exit_count   = isset($metrics['total_exits']) ? absint($metrics['total_exits']) : 0;
    $fund_count   = is_array($active_funds) ? count(array_filter($active_funds)) : 0;
    $geo_focus_display = $geo_focus ?: '—';
    ?>
    <div class="sffc-company-metrics">
        <div class="sffc-company-metric">
            <span class="sffc-company-metric__label">Deals</span>
            <span class="sffc-company-metric__value"><?php echo $deal_count ? esc_html(number_format_i18n($deal_count)) : '—'; ?></span>
        </div>
        <div class="sffc-company-metric">
            <span class="sffc-company-metric__label">Exits</span>
            <span class="sffc-company-metric__value"><?php echo $exit_count ? esc_html(number_format_i18n($exit_count)) : '—'; ?></span>
        </div>
        <div class="sffc-company-metric">
            <span class="sffc-company-metric__label">Active Funds</span>
            <span class="sffc-company-metric__value"><?php echo $fund_count ? esc_html(number_format_i18n($fund_count)) : '—'; ?></span>
        </div>
        <div class="sffc-company-metric">
            <span class="sffc-company-metric__label">Geo-Focus</span>
            <span class="sffc-company-metric__value"><?php echo esc_html($geo_focus_display); ?></span>
        </div>
    </div>

    <div class="sffc-company-profile__layout">
        <div>
            <section class="sffc-company-section">
                <div class="sffc-company-section__title">
                    <span>Latest Coverage</span>
                    <span class="sffc-company-section__subtitle">Curated across premium sources</span>
                </div>
                <?php if (!empty($news)) : ?>
                    <?php foreach ($news as $item) : ?>
                        <article class="sffc-company-news-item">
                            <h4><a href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses($item['title'], array('strong' => array('class' => array()))); ?></a></h4>
                            <div class="sffc-company-news-item__meta">
                                <?php if (!empty($item['source'])) : ?>
                                    <span><?php echo esc_html($item['source']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['time_ago'])) : ?>
                                    <span><?php echo esc_html($item['time_ago']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['relevance_score'])) : ?>
                                    <span class="sffc-company-chip">Relevance <?php echo esc_html(number_format_i18n($item['relevance_score'], 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['description'])) : ?>
                                <p><?php echo esc_html($item['description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($item['matched_terms'])) : ?>
                                <p class="sffc-company-match-terms"><strong>Matched:</strong> <?php echo esc_html(implode(', ', $item['matched_terms'])); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="sffc-company-empty">No recent news yet. We will surface coverage as soon as it appears.</div>
                <?php endif; ?>
            </section>

            <section class="sffc-company-section">
                <div class="sffc-company-section__title">
                    <span>Career Opportunities</span>
                    <span class="sffc-company-section__subtitle">Live mandates pulling directly from the registry feeds</span>
                </div>
                <?php if (!empty($jobs)) : ?>
                    <div class="sffc-company-jobs">
                        <?php foreach ($jobs as $job) : ?>
                            <article class="sffc-company-job-item">
                                <h4><a href="<?php echo esc_url($job['url']); ?>"><?php echo esc_html($job['title']); ?></a></h4>
                                <div class="sffc-company-job-item__meta">
                                    <?php if (!empty($job['location'])) : ?>
                                        <span><?php echo esc_html($job['location']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['type'])) : ?>
                                        <span><?php echo esc_html($job['type']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['posted'])) : ?>
                                        <span><?php echo esc_html($job['posted']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sffc-company-job-item__cta">
                                    <a href="<?php echo esc_url($job['url']); ?>" target="_blank" rel="noopener noreferrer">View Mandate</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="sffc-company-empty">No live mandates at the moment — we’ll surface opportunities here the moment they appear.</div>
                <?php endif; ?>
            </section>

            <section class="sffc-company-section">
                <div class="sffc-company-section__title">
                    <span>Active Funds</span>
                    <span class="sffc-company-section__subtitle">Vehicles currently in market or actively deploying capital</span>
                </div>
                <?php if (!empty($active_funds)) : ?>
                    <div class="sffc-company-funds">
                        <?php foreach ($active_funds as $fund) :
                            if (!is_array($fund)) {
                                continue;
                            }
                            $fund_name = $fund['name'] ?? ($fund['title'] ?? 'Fund');
                            $fund_vintage = $fund['vintage'] ?? ($fund['year'] ?? ''); ?>
                            <article class="sffc-company-fund-item">
                                <h4><?php echo esc_html($fund_name); ?></h4>
                                <div class="sffc-company-fund-item__meta">
                                    <?php if (!empty($fund_vintage)) : ?>
                                        <span><?php echo esc_html(sprintf(__('Vintage %s', 'senna-finance'), $fund_vintage)); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($fund['size'])) : ?>
                                        <span><?php echo esc_html($fund['size']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($fund['focus'])) : ?>
                                    <p><?php echo esc_html($fund['focus']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($fund['notes'])) : ?>
                                    <ul>
                                        <li><strong><?php esc_html_e('Notes:', 'senna-finance'); ?></strong> <?php echo esc_html($fund['notes']); ?></li>
                                    </ul>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="sffc-company-empty">Fund information will appear here as soon as we verify current vehicles.</div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="sffc-company-sidebar">
            <section class="sffc-company-section sffc-company-card sffc-company-card--highlight">
                <h3>Pulse</h3>
                <div class="sffc-company-activity">
                    <div class="sffc-company-activity-card">
                        <h4>Activity Score</h4>
                        <strong><?php echo esc_html($activity['score']); ?></strong>
                        <span class="sffc-company-pulse sffc-company-pulse--<?php echo esc_attr($activity['trend']); ?>">
                            Trend · <?php echo esc_html(ucfirst($activity['trend'])); ?>
                        </span>
                    </div>
                    <div class="sffc-company-activity-card">
                        <h4>Level</h4>
                        <strong><?php echo esc_html($activity['level']); ?></strong>
                        <span>News this week: <?php echo esc_html($metrics['news_week']); ?></span>
                    </div>
                </div>
            </section>

            <section class="sffc-company-section sffc-company-card">
                <h3>Quick Facts</h3>
                <div class="sffc-company-quick-facts">
                    <?php if (!empty($website)) : ?>
                        <div>Website<br><a href="<?php echo $website; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(parse_url($website, PHP_URL_HOST)); ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($linkedin)) : ?>
                        <div>LinkedIn<br><a href="<?php echo $linkedin; ?>" target="_blank" rel="noopener noreferrer">Open Profile</a></div>
                    <?php endif; ?>
                    <?php if (!empty($regions)) : ?>
                        <div>Regions<br><?php echo esc_html(implode(', ', $regions)); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($sectors)) : ?>
                        <div>Sectors<br><?php echo esc_html(implode(', ', $sectors)); ?></div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="sffc-company-section sffc-company-card">
                <h3>Investment Focus</h3>
                <?php if (!empty($sectors)) : ?>
                    <div class="sffc-company-tag-group">
                        <?php foreach ($sectors as $sector) : ?>
                            <span class="sffc-company-pill"><?php echo esc_html($sector); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="sffc-company-empty">No sector focus recorded yet.</div>
                <?php endif; ?>
            </section>

            <section class="sffc-company-section sffc-company-card">
                <h3>Leadership</h3>
                <?php if (!empty($team)) : ?>
                    <?php foreach ($team as $member) : ?>
                        <div class="sffc-company-team-item">
                            <h4><?php echo esc_html($member['name'] ?? ''); ?></h4>
                            <?php if (!empty($member['title'])) : ?>
                                <p><?php echo esc_html($member['title']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="sffc-company-empty">Leadership bios coming soon.</div>
                <?php endif; ?>
            </section>
        </aside>
    </div>

    <div class="sffc-company-divider"></div>

    <section class="sffc-company-section">
        <div class="sffc-company-section__title">
            <span>Portfolio Companies</span>
            <span class="sffc-company-section__subtitle">Key assets across target sectors</span>
        </div>
        <?php if (!empty($portfolio)) : ?>
            <div class="sffc-company-list--grid">
                <?php foreach ($portfolio as $company) : ?>
                    <div class="sffc-company-card">
                        <h4><?php echo esc_html($company['name'] ?? ''); ?></h4>
                        <?php if (!empty($company['sector'])) : ?>
                            <p>Sector · <?php echo esc_html($company['sector']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($company['region'])) : ?>
                            <p>Region · <?php echo esc_html($company['region']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($company['status'])) : ?>
                            <span class="sffc-company-chip"><?php echo esc_html(ucfirst($company['status'])); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="sffc-company-empty">Add portfolio holdings to showcase recent platform investments.</div>
        <?php endif; ?>

        <?php if (current_user_can('edit_post', $info['id'])) : ?>
            <div class="sffc-company-links" style="margin-top: 18px;">
                <a class="sffc-company-manage-link" href="<?php echo esc_url(admin_url('post.php?post=' . $info['id'] . '&action=edit')); ?>#portfolio">Manage portfolio entries</a>
            </div>
            <div class="sffc-company-portfolio-form">
                <h3><?php esc_html_e('Add Portfolio Company', 'senna-finance'); ?></h3>
                <form class="sffc-company-portfolio-form__inner" method="post">
                    <input type="hidden" name="sffc_company_id" value="<?php echo esc_attr($info['id']); ?>">
                    <?php wp_nonce_field('sffc_add_portfolio_company', 'sffc_portfolio_nonce'); ?>
                    <div class="sffc-company-portfolio-fields">
                        <div>
                            <label><?php esc_html_e('Company Name', 'senna-finance'); ?></label>
                            <input type="text" name="portfolio_name" required>
                        </div>
                        <div>
                            <label><?php esc_html_e('Sector', 'senna-finance'); ?></label>
                            <input type="text" name="portfolio_sector">
                        </div>
                        <div>
                            <label><?php esc_html_e('Region', 'senna-finance'); ?></label>
                            <input type="text" name="portfolio_region">
                        </div>
                        <div>
                            <label><?php esc_html_e('Status', 'senna-finance'); ?></label>
                            <input type="text" name="portfolio_status" placeholder="Active, Realized, etc.">
                        </div>
                        <div>
                            <label><?php esc_html_e('Website', 'senna-finance'); ?></label>
                            <input type="url" name="portfolio_url" placeholder="https://">
                        </div>
                    </div>
                    <div>
                        <label><?php esc_html_e('Notes', 'senna-finance'); ?></label>
                        <textarea name="portfolio_notes" rows="3"></textarea>
                    </div>
                    <button type="submit" name="sffc_add_portfolio_company" class="sffc-company-cta"><?php esc_html_e('Add Company', 'senna-finance'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>