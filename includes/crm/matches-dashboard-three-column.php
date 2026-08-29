                                    <!-- Matches Dashboard: Three-Column Grid Layout -->
                                    <div class="matches-dashboard">
                                        <div class="matches-dashboard__top">
                                            <div class="toolbar-main">
                                                <strong><?php esc_html_e('Your Matched Opportunities', 'senna-finance'); ?></strong>
                                            </div>
                                            <div class="toolbar-actions">
                                                <span class="toolbar-criteria"><?php esc_html_e('Active Filters', 'senna-finance'); ?></span>
                                                <?php
                                                // Show active filter chips
                                                $sectors = $job_preferences['target_sectors'] ?? [];
                                                $seniority = $job_preferences['target_seniority'] ?? [];
                                                $locations = $job_preferences['target_locations'] ?? [];

                                                if (!empty($sectors)):
                                                    $sector_options = sffc_crm_get_sector_options();
                                                    $sector_label = $sector_options[$sectors[0]] ?? $sectors[0];
                                                ?>
                                                    <span class="filter-chip"><?php echo esc_html($sector_label); ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($locations)): ?>
                                                    <span class="filter-chip"><?php echo esc_html($locations[0]); ?></span>
                                                <?php endif; ?>

                                                <?php if (!empty($seniority)):
                                                    $seniority_options = sffc_crm_get_seniority_options();
                                                    $seniority_label = $seniority_options[$seniority[0]] ?? $seniority[0];
                                                ?>
                                                    <span class="filter-chip"><?php echo esc_html($seniority_label); ?></span>
                                                <?php endif; ?>

                                                <button type="button" class="matches-dashboard__cta" data-dashboard-apply-all>
                                                    <span><?php esc_html_e('Apply to Selected', 'senna-finance'); ?></span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="sffc-crm-dashboard-grid">
                                            <?php
                                            // Calculate match scores for all posts
                                            $scored_posts = [];
                                            foreach ($matched_posts_with_groups as $post) {
                                                $post['match_score'] = $this->calculate_match_score($post, $job_preferences);
                                                $scored_posts[] = $post;
                                            }

                                            // Sort by score descending
                                            usort($scored_posts, function ($a, $b) {
                                                return $b['match_score'] - $a['match_score'];
                                            });

                                            // Card 1: Best Matches (score >= 85)
                                            $best_matches = array_filter($scored_posts, function ($post) {
                                                return $post['match_score'] >= 85;
                                            });
                                            $best_matches = array_slice($best_matches, 0, 6);
                                            ?>

                                            <!-- Card 1: Best Matches -->
                                            <section class="dashboard-group">
                                                <div class="group-head">
                                                    <div>
                                                        <strong><?php esc_html_e('Best Matches', 'senna-finance'); ?></strong>
                                                        <span><?php printf(__('%d high-confidence roles', 'senna-finance'), count($best_matches)); ?></span>
                                                    </div>
                                                </div>
                                                <div class="group-list">
                                                    <?php foreach ($best_matches as $match_role):
                                                        $likelihood = $this->get_match_likelihood($match_role['match_score']);
                                                    ?>
                                                        <article class="match-card" data-match-row data-match-id="<?php echo esc_attr($match_role['id']); ?>" data-match-selected="0">
                                                            <div class="match-top">
                                                                <span class="match-check">✓</span>
                                                                <span class="match-logo">
                                                                    <?php if (!empty($match_role['company_logo'])): ?>
                                                                        <img src="<?php echo esc_url($match_role['company_logo']); ?>" alt="<?php echo esc_attr($match_role['company']); ?>">
                                                                    <?php else:
                                                                        $company_initial = strtoupper(substr($match_role['company'] ?: 'S', 0, 1));
                                                                    ?>
                                                                        <span><?php echo esc_html($company_initial); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <div class="match-copy">
                                                                    <strong><?php echo esc_html($match_role['role_title'] ?: __('Opportunity', 'senna-finance')); ?></strong>
                                                                    <span>
                                                                        <?php echo esc_html($match_role['company']); ?>
                                                                        • <?php echo esc_html($match_role['location'] ?: 'Location TBD'); ?>
                                                                        <?php if (!empty($match_role['posted_at'])):
                                                                            $posted_ts = strtotime($match_role['posted_at']);
                                                                            if ($posted_ts):
                                                                                $posted_diff = human_time_diff($posted_ts, current_time('timestamp'));
                                                                        ?>
                                                                            • <?php printf(__('Posted %s ago', 'senna-finance'), $posted_diff); ?>
                                                                        <?php endif; endif; ?>
                                                                    </span>
                                                                </div>
                                                                <div class="match-score">
                                                                    <span class="match-score-badge <?php echo esc_attr($likelihood['class']); ?>">
                                                                        <?php echo esc_html($likelihood['label']); ?>
                                                                    </span>
                                                                    <span class="match-score-percentage"><?php echo esc_html($match_role['match_score']); ?>% match</span>
                                                                </div>
                                                            </div>
                                                            <div class="match-actions">
                                                                <span class="status-select ready"><?php esc_html_e('Ready to apply', 'senna-finance'); ?></span>
                                                                <?php if (!empty($match_role['application_url']) || !empty($match_role['wp_post_id'])):
                                                                    $view_url = !empty($match_role['application_url']) ? $match_role['application_url'] : get_permalink($match_role['wp_post_id']);
                                                                ?>
                                                                    <a class="view-link" href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">
                                                                        <?php esc_html_e('View role', 'senna-finance'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <button type="button" class="remove-btn" data-match-remove aria-label="<?php esc_attr_e('Remove', 'senna-finance'); ?>">×</button>
                                                            </div>
                                                        </article>
                                                    <?php endforeach; ?>

                                                    <?php if (empty($best_matches)): ?>
                                                        <div class="group-empty">
                                                            <p><?php esc_html_e('No high-scoring matches yet. Keep building your profile!', 'senna-finance'); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>

                                            <?php
                                            // Card 2: Skills Matches (sector-specific matches)
                                            $user_first_name = $is_logged_in && !empty($current_user->user_firstname) ? $current_user->user_firstname : __('Your', 'senna-finance');
                                            $skills_matches = [];

                                            if (!empty($sectors)) {
                                                $skills_matches = array_filter($scored_posts, function ($post) use ($sectors) {
                                                    return !empty($post['sector']) && in_array($post['sector'], $sectors);
                                                });
                                                $skills_matches = array_slice($skills_matches, 0, 6);
                                            }
                                            ?>

                                            <!-- Card 2: Skills Matches -->
                                            <section class="dashboard-group">
                                                <div class="group-head">
                                                    <div>
                                                        <strong><?php printf(__('%s Skills Matches', 'senna-finance'), esc_html($user_first_name)); ?></strong>
                                                        <span><?php printf(__('%d roles matching your expertise', 'senna-finance'), count($skills_matches)); ?></span>
                                                    </div>
                                                </div>
                                                <div class="group-list">
                                                    <?php foreach ($skills_matches as $match_role):
                                                        $likelihood = $this->get_match_likelihood($match_role['match_score']);
                                                    ?>
                                                        <article class="match-card" data-match-row data-match-id="<?php echo esc_attr($match_role['id']); ?>" data-match-selected="0">
                                                            <div class="match-top">
                                                                <span class="match-check">✓</span>
                                                                <span class="match-logo">
                                                                    <?php if (!empty($match_role['company_logo'])): ?>
                                                                        <img src="<?php echo esc_url($match_role['company_logo']); ?>" alt="<?php echo esc_attr($match_role['company']); ?>">
                                                                    <?php else:
                                                                        $company_initial = strtoupper(substr($match_role['company'] ?: 'S', 0, 1));
                                                                    ?>
                                                                        <span><?php echo esc_html($company_initial); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <div class="match-copy">
                                                                    <strong><?php echo esc_html($match_role['role_title'] ?: __('Opportunity', 'senna-finance')); ?></strong>
                                                                    <span>
                                                                        <?php echo esc_html($match_role['company']); ?>
                                                                        • <?php echo esc_html($match_role['location'] ?: 'Location TBD'); ?>
                                                                        <?php if (!empty($match_role['posted_at'])):
                                                                            $posted_ts = strtotime($match_role['posted_at']);
                                                                            if ($posted_ts):
                                                                                $posted_diff = human_time_diff($posted_ts, current_time('timestamp'));
                                                                        ?>
                                                                            • <?php printf(__('Posted %s ago', 'senna-finance'), $posted_diff); ?>
                                                                        <?php endif; endif; ?>
                                                                    </span>
                                                                </div>
                                                                <div class="match-score">
                                                                    <span class="match-score-badge <?php echo esc_attr($likelihood['class']); ?>">
                                                                        <?php echo esc_html($likelihood['label']); ?>
                                                                    </span>
                                                                    <span class="match-score-percentage"><?php echo esc_html($match_role['match_score']); ?>% match</span>
                                                                </div>
                                                            </div>
                                                            <div class="match-actions">
                                                                <span class="status-select ready"><?php esc_html_e('CV tailored', 'senna-finance'); ?></span>
                                                                <?php if (!empty($match_role['application_url']) || !empty($match_role['wp_post_id'])):
                                                                    $view_url = !empty($match_role['application_url']) ? $match_role['application_url'] : get_permalink($match_role['wp_post_id']);
                                                                ?>
                                                                    <a class="view-link" href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">
                                                                        <?php esc_html_e('View role', 'senna-finance'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <button type="button" class="remove-btn" data-match-remove aria-label="<?php esc_attr_e('Remove', 'senna-finance'); ?>">×</button>
                                                            </div>
                                                        </article>
                                                    <?php endforeach; ?>

                                                    <?php if (empty($skills_matches)): ?>
                                                        <div class="group-empty">
                                                            <p><?php esc_html_e('Set your sector preferences in Profile to see skill-matched roles.', 'senna-finance'); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>

                                            <?php
                                            // Card 3: Application Queue (recently added/selected roles)
                                            // For now, show recently posted roles. In future, this will be user-selected queue
                                            $queue_matches = array_filter($scored_posts, function ($post) {
                                                if (empty($post['posted_at'])) return false;
                                                $posted_ts = strtotime($post['posted_at']);
                                                if (!$posted_ts) return false;
                                                $days_old = (time() - $posted_ts) / (24 * 60 * 60);
                                                return $days_old <= 7;
                                            });
                                            $queue_matches = array_slice($queue_matches, 0, 6);
                                            ?>

                                            <!-- Card 3: Application Queue -->
                                            <section class="dashboard-group">
                                                <div class="group-head">
                                                    <div>
                                                        <strong><?php esc_html_e('Application Queue', 'senna-finance'); ?></strong>
                                                        <span><?php printf(__('%d roles queued for review', 'senna-finance'), count($queue_matches)); ?></span>
                                                    </div>
                                                </div>
                                                <div class="group-list">
                                                    <?php foreach ($queue_matches as $match_role):
                                                        $likelihood = $this->get_match_likelihood($match_role['match_score']);
                                                    ?>
                                                        <article class="match-card" data-match-row data-match-id="<?php echo esc_attr($match_role['id']); ?>" data-match-selected="1">
                                                            <div class="match-top">
                                                                <span class="match-check">✓</span>
                                                                <span class="match-logo">
                                                                    <?php if (!empty($match_role['company_logo'])): ?>
                                                                        <img src="<?php echo esc_url($match_role['company_logo']); ?>" alt="<?php echo esc_attr($match_role['company']); ?>">
                                                                    <?php else:
                                                                        $company_initial = strtoupper(substr($match_role['company'] ?: 'S', 0, 1));
                                                                    ?>
                                                                        <span><?php echo esc_html($company_initial); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <div class="match-copy">
                                                                    <strong><?php echo esc_html($match_role['role_title'] ?: __('Opportunity', 'senna-finance')); ?></strong>
                                                                    <span>
                                                                        <?php echo esc_html($match_role['company']); ?>
                                                                        • <?php echo esc_html($match_role['location'] ?: 'Location TBD'); ?>
                                                                        <?php if (!empty($match_role['posted_at'])):
                                                                            $posted_ts = strtotime($match_role['posted_at']);
                                                                            if ($posted_ts):
                                                                                $posted_diff = human_time_diff($posted_ts, current_time('timestamp'));
                                                                        ?>
                                                                            • <?php printf(__('Posted %s ago', 'senna-finance'), $posted_diff); ?>
                                                                        <?php endif; endif; ?>
                                                                    </span>
                                                                </div>
                                                                <div class="match-score">
                                                                    <span class="match-score-badge <?php echo esc_attr($likelihood['class']); ?>">
                                                                        <?php echo esc_html($likelihood['label']); ?>
                                                                    </span>
                                                                    <span class="match-score-percentage"><?php echo esc_html($match_role['match_score']); ?>% match</span>
                                                                </div>
                                                            </div>
                                                            <div class="match-actions">
                                                                <span class="status-select ready"><?php esc_html_e('Materials ready', 'senna-finance'); ?></span>
                                                                <?php if (!empty($match_role['application_url']) || !empty($match_role['wp_post_id'])):
                                                                    $view_url = !empty($match_role['application_url']) ? $match_role['application_url'] : get_permalink($match_role['wp_post_id']);
                                                                ?>
                                                                    <a class="view-link" href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">
                                                                        <?php esc_html_e('View role', 'senna-finance'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <button type="button" class="remove-btn" data-match-remove aria-label="<?php esc_attr_e('Remove', 'senna-finance'); ?>">×</button>
                                                            </div>
                                                        </article>
                                                    <?php endforeach; ?>

                                                    <?php if (empty($queue_matches)): ?>
                                                        <div class="group-empty">
                                                            <p><?php esc_html_e('No roles in queue. Select matches above to add them here.', 'senna-finance'); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>

                                        </div>
                                    </div>
