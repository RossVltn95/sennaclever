<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Get profile data
$profile_data = isset($profile_data) ? $profile_data : array();
$profile = $profile_data['profile'] ?? array();
$expertise = $profile_data['expertise'] ?? array();

// Get MemberPress subscription data
$has_memberpress = class_exists('MeprUser');
$subscription_data = null;
$subscription_plans = array();

if ($has_memberpress) {
    $mepr_user = new MeprUser($user_id);
    $subscriptions = $mepr_user->subscriptions();
    $subscription_data = !empty($subscriptions) ? $subscriptions[0] : null;
    
    // Get available plans
    $mepr_products = get_posts(array(
        'post_type' => 'memberpressproduct',
        'post_status' => 'publish',
        'numberposts' => -1
    ));
    
    foreach ($mepr_products as $product) {
        $price = get_post_meta($product->ID, '_mepr_product_price', true);
        $subscription_plans[] = array(
            'id' => $product->ID,
            'name' => $product->post_title,
            'price' => $price,
            'description' => $product->post_excerpt,
            'url' => get_permalink($product->ID)
        );
    }
}

// Get career opportunities data
$career_opportunities = array();
if (shortcode_exists('career_opportunities')) {
    global $wpdb;
    $career_opportunities = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_excerpt,
                pm1.meta_value as company,
                pm2.meta_value as location,
                pm3.meta_value as salary_range,
                pm4.meta_value as job_type
         FROM {$wpdb->prefix}posts p
         LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'company'
         LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'location'
         LEFT JOIN {$wpdb->prefix}postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'salary_range'
         LEFT JOIN {$wpdb->prefix}postmeta pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'job_type'
         WHERE p.post_type = 'sffc_job'
         AND p.post_status = 'publish'
         ORDER BY p.post_date DESC
         LIMIT 6"
    );
}

$profile_picture = get_user_meta($user_id, 'senna_profile_picture', true);
?>

<div class="jp-settings-container">
    <!-- Header -->
    <header class="jp-settings-header">
        <div class="jp-container">
            <h1 class="jp-settings-title">Professional Settings</h1>
            <p class="jp-settings-subtitle">Manage your profile, membership, and career preferences</p>
        </div>
    </header>

    <div class="jp-container">
        <div class="jp-settings-content">
            <!-- Sidebar Navigation -->
            <aside class="jp-settings-sidebar">
                <nav class="jp-settings-nav">
                    <a href="#profile" class="jp-settings-nav-item active" data-section="profile">
                        <span class="jp-nav-icon">•</span>
                        Profile Information
                    </a>
                    <a href="#expertise" class="jp-settings-nav-item" data-section="expertise">
                        <span class="jp-nav-icon">•</span>
                        Expertise Areas
                    </a>
                    <a href="#membership" class="jp-settings-nav-item" data-section="membership">
                        <span class="jp-nav-icon">•</span>
                        Membership & Billing
                    </a>
                    <a href="#career" class="jp-settings-nav-item" data-section="career">
                        <span class="jp-nav-icon">•</span>
                        Career Opportunities
                    </a>
                    <a href="#preferences" class="jp-settings-nav-item" data-section="preferences">
                        <span class="jp-nav-icon">•</span>
                        Preferences
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="jp-settings-main">
                
                <!-- Profile Information Section -->
                <section id="profile" class="jp-settings-section active">
                    <div class="jp-section-header">
                        <h2>Profile Information</h2>
                        <p>Update your professional profile and contact information</p>
                    </div>

                    <form class="jp-profile-form" id="jp-profile-form">
                        <!-- Profile Picture Upload -->
                        <div class="jp-form-group jp-profile-picture-section">
                            <label class="jp-form-label">Profile Picture</label>
                            <div class="jp-profile-picture-container">
                                <div class="jp-profile-picture-preview" id="jp-profile-preview">
                                    <?php if ($profile_picture): ?>
                                        <img src="<?php echo esc_url($profile_picture); ?>" alt="Profile Picture" />
                                    <?php else: ?>
                                        <div class="jp-profile-placeholder">
                                            <?php echo esc_html(strtoupper(substr($current_user->display_name, 0, 1))); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="jp-profile-picture-actions">
                                    <input type="file" id="jp-profile-upload" accept="image/*" style="display: none;" />
                                    <button type="button" class="jp-btn jp-btn-outline" onclick="document.getElementById('jp-profile-upload').click();">
                                        Choose Photo
                                    </button>
                                    <?php if ($profile_picture): ?>
                                        <button type="button" class="jp-btn jp-btn-text" id="jp-remove-picture">
                                            Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="jp-form-row">
                            <div class="jp-form-group">
                                <label for="profile_title" class="jp-form-label">Professional Title</label>
                                <input type="text" id="profile_title" name="profile_title" class="jp-form-input" 
                                       value="<?php echo esc_attr($profile['profile_title'] ?? ''); ?>" 
                                       placeholder="e.g., Vice President, Investment Banking" />
                            </div>
                            <div class="jp-form-group">
                                <label for="current_company" class="jp-form-label">Current Company</label>
                                <input type="text" id="current_company" name="current_company" class="jp-form-input" 
                                       value="<?php echo esc_attr($profile['current_company'] ?? ''); ?>" 
                                       placeholder="e.g., Goldman Sachs" />
                            </div>
                        </div>

                        <div class="jp-form-row">
                            <div class="jp-form-group">
                                <label for="current_position" class="jp-form-label">Current Position</label>
                                <input type="text" id="current_position" name="current_position" class="jp-form-input" 
                                       value="<?php echo esc_attr($profile['current_position'] ?? ''); ?>" 
                                       placeholder="e.g., Managing Director" />
                            </div>
                            <div class="jp-form-group">
                                <label for="years_experience" class="jp-form-label">Years of Experience</label>
                                <select id="years_experience" name="years_experience" class="jp-form-select">
                                    <option value="">Select Experience</option>
                                    <?php 
                                    $selected_years = $profile['years_experience'] ?? '';
                                    $experience_options = array(
                                        '0-2' => '0-2 years', '3-5' => '3-5 years', '6-10' => '6-10 years',
                                        '11-15' => '11-15 years', '16-20' => '16-20 years', '20+' => '20+ years'
                                    );
                                    foreach ($experience_options as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_years, $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="jp-form-group">
                            <label for="industry_focus" class="jp-form-label">Industry Focus</label>
                            <input type="text" id="industry_focus" name="industry_focus" class="jp-form-input" 
                                   value="<?php echo esc_attr($profile['industry_focus'] ?? ''); ?>" 
                                   placeholder="e.g., Private Equity, Investment Banking, Asset Management" />
                        </div>

                        <div class="jp-form-group">
                            <label for="professional_summary" class="jp-form-label">Professional Summary</label>
                            <textarea id="professional_summary" name="professional_summary" class="jp-form-textarea" rows="4" 
                                      placeholder="Describe your professional background and career objectives..."><?php echo esc_textarea($profile['professional_summary'] ?? ''); ?></textarea>
                        </div>

                        <div class="jp-form-row">
                            <div class="jp-form-group">
                                <label for="linkedin_url" class="jp-form-label">LinkedIn URL</label>
                                <input type="url" id="linkedin_url" name="linkedin_url" class="jp-form-input" 
                                       value="<?php echo esc_attr($profile['linkedin_url'] ?? ''); ?>" 
                                       placeholder="https://linkedin.com/in/yourprofile" />
                            </div>
                            <div class="jp-form-group">
                                <label for="company_website" class="jp-form-label">Company Website</label>
                                <input type="url" id="company_website" name="company_website" class="jp-form-input" 
                                       value="<?php echo esc_attr($profile['company_website'] ?? ''); ?>" 
                                       placeholder="https://company.com" />
                            </div>
                        </div>

                        <!-- Privacy Settings -->
                        <div class="jp-form-section">
                            <h3>Privacy & Networking</h3>
                            <div class="jp-form-group">
                                <label class="jp-checkbox-label">
                                    <input type="checkbox" name="open_to_introductions" value="1" 
                                           <?php checked(!empty($profile['open_to_introductions'])); ?> />
                                    <span class="jp-checkbox-custom"></span>
                                    Open to professional introductions
                                </label>
                            </div>
                            <div class="jp-form-group">
                                <label class="jp-checkbox-label">
                                    <input type="checkbox" name="mentor_available" value="1" 
                                           <?php checked(!empty($profile['mentor_available'])); ?> />
                                    <span class="jp-checkbox-custom"></span>
                                    Available as a mentor
                                </label>
                            </div>
                            <div class="jp-form-group">
                                <label class="jp-checkbox-label">
                                    <input type="checkbox" name="seeking_mentor" value="1" 
                                           <?php checked(!empty($profile['seeking_mentor'])); ?> />
                                    <span class="jp-checkbox-custom"></span>
                                    Seeking mentorship
                                </label>
                            </div>
                        </div>

                        <div class="jp-form-actions">
                            <button type="submit" class="jp-btn jp-btn-primary">Save Profile Changes</button>
                            <button type="button" class="jp-btn jp-btn-outline" id="jp-cancel-profile">Cancel</button>
                        </div>
                    </form>
                </section>

                <!-- Expertise Areas Section -->
                <section id="expertise" class="jp-settings-section">
                    <div class="jp-section-header">
                        <h2>Expertise Areas</h2>
                        <p>Showcase your professional expertise and specializations</p>
                    </div>

                    <div class="jp-expertise-manager">
                        <div class="jp-current-expertise">
                            <h3>Your Expertise Areas</h3>
                            <div class="jp-expertise-list" id="jp-expertise-list">
                                <?php if (!empty($expertise)): ?>
                                    <?php foreach ($expertise as $expert): ?>
                                        <div class="jp-expertise-item" data-id="<?php echo esc_attr($expert['id'] ?? ''); ?>">
                                            <div class="jp-expertise-content">
                                                <h4><?php echo esc_html($expert['expertise_title']); ?></h4>
                                                <p>Level: <?php echo esc_html($expert['expertise_level']); ?> 
                                                   | Experience: <?php echo esc_html($expert['years_experience']); ?> years</p>
                                            </div>
                                            <button type="button" class="jp-expertise-remove" data-id="<?php echo esc_attr($expert['id'] ?? ''); ?>">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="jp-no-expertise">No expertise areas added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="jp-add-expertise">
                            <h3>Add New Expertise</h3>
                            <form id="jp-expertise-form">
                                <div class="jp-form-row">
                                    <div class="jp-form-group">
                                        <label for="expertise_title" class="jp-form-label">Expertise Area</label>
                                        <input type="text" id="expertise_title" name="expertise_title" class="jp-form-input" 
                                               placeholder="e.g., Financial Modeling, M&A Advisory" />
                                    </div>
                                    <div class="jp-form-group">
                                        <label for="expertise_level" class="jp-form-label">Proficiency Level</label>
                                        <select id="expertise_level" name="expertise_level" class="jp-form-select">
                                            <option value="Expert">Expert</option>
                                            <option value="Advanced">Advanced</option>
                                            <option value="Intermediate">Intermediate</option>
                                            <option value="Beginner">Beginner</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="jp-form-group">
                                    <label for="expertise_years" class="jp-form-label">Years of Experience</label>
                                    <input type="number" id="expertise_years" name="expertise_years" class="jp-form-input" min="0" max="50" />
                                </div>
                                <button type="submit" class="jp-btn jp-btn-primary">Add Expertise</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Membership Section -->
                <section id="membership" class="jp-settings-section">
                    <div class="jp-section-header">
                        <h2>Membership & Billing</h2>
                        <p>Manage your subscription and billing preferences</p>
                    </div>

                    <?php if ($has_memberpress && $subscription_data): ?>
                        <div class="jp-current-subscription">
                            <div class="jp-subscription-card">
                                <div class="jp-subscription-info">
                                    <h3><?php echo esc_html($subscription_data->product()->post_title); ?></h3>
                                    <p class="jp-subscription-status">Status: <span class="jp-status-active">Active</span></p>
                                    <p class="jp-subscription-details">
                                        Next billing: <?php echo esc_html(date('F j, Y', strtotime($subscription_data->expires_at))); ?>
                                    </p>
                                </div>
                                <div class="jp-subscription-actions">
                                    <button type="button" class="jp-btn jp-btn-outline" onclick="window.open('<?php echo esc_url($subscription_data->account_url()); ?>', '_blank')">
                                        Manage Subscription
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="jp-plan-upgrade">
                        <h3>Available Plans</h3>
                        <div class="jp-plans-grid">
                            <?php if (!empty($subscription_plans)): ?>
                                <?php foreach ($subscription_plans as $plan): ?>
                                    <div class="jp-plan-card">
                                        <h4><?php echo esc_html($plan['name']); ?></h4>
                                        <div class="jp-plan-price">$<?php echo esc_html($plan['price']); ?><span>/month</span></div>
                                        <p><?php echo esc_html($plan['description']); ?></p>
                                        <a href="<?php echo esc_url($plan['url']); ?>" class="jp-btn jp-btn-primary">
                                            <?php echo ($has_memberpress && $subscription_data) ? 'Upgrade' : 'Subscribe'; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No subscription plans available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Career Opportunities Section -->
                <section id="career" class="jp-settings-section">
                    <div class="jp-section-header">
                        <h2>Career Opportunities</h2>
                        <p>Personalized job recommendations and saved opportunities</p>
                    </div>

                    <div class="jp-career-preferences">
                        <h3>Job Preferences</h3>
                        <form id="jp-career-preferences-form">
                            <div class="jp-form-row">
                                <div class="jp-form-group">
                                    <label for="preferred_locations" class="jp-form-label">Preferred Locations</label>
                                    <input type="text" id="preferred_locations" name="preferred_locations" class="jp-form-input" 
                                           placeholder="New York, London, Hong Kong" />
                                </div>
                                <div class="jp-form-group">
                                    <label for="salary_expectation" class="jp-form-label">Salary Expectation</label>
                                    <input type="text" id="salary_expectation" name="salary_expectation" class="jp-form-input" 
                                           placeholder="$200,000 - $300,000" />
                                </div>
                            </div>
                            <div class="jp-form-group">
                                <label for="job_alerts" class="jp-form-label">Job Alert Frequency</label>
                                <select id="job_alerts" name="job_alerts" class="jp-form-select">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="none">No alerts</option>
                                </select>
                            </div>
                            <button type="submit" class="jp-btn jp-btn-primary">Save Preferences</button>
                        </form>
                    </div>

                    <div class="jp-career-opportunities">
                        <h3>Recent Opportunities</h3>
                        <div class="jp-opportunities-list">
                            <?php if (!empty($career_opportunities)): ?>
                                <?php foreach ($career_opportunities as $opportunity): ?>
                                    <div class="jp-opportunity-card">
                                        <h4><?php echo esc_html($opportunity->post_title); ?></h4>
                                        <p class="jp-opportunity-company"><?php echo esc_html($opportunity->company ?: 'Investment Firm'); ?></p>
                                        <p class="jp-opportunity-location"><?php echo esc_html($opportunity->location ?: 'Multiple Locations'); ?></p>
                                        <p class="jp-opportunity-excerpt"><?php echo esc_html(wp_trim_words($opportunity->post_excerpt ?: '', 20)); ?></p>
                                        <div class="jp-opportunity-actions">
                                            <a href="<?php echo get_permalink($opportunity->ID); ?>" class="jp-btn jp-btn-outline">View Details</a>
                                            <button type="button" class="jp-btn jp-btn-text jp-save-opportunity" data-id="<?php echo esc_attr($opportunity->ID); ?>">
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="jp-no-opportunities">
                                    <p>No career opportunities available at the moment.</p>
                                    <div style="margin-top: 20px;">
                                        <?php echo do_shortcode('[career_opportunities]'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Preferences Section -->
                <section id="preferences" class="jp-settings-section">
                    <div class="jp-section-header">
                        <h2>Account Preferences</h2>
                        <p>Customize your experience and notification settings</p>
                    </div>

                    <form id="jp-preferences-form">
                        <div class="jp-form-section">
                            <h3>Notifications</h3>
                            <div class="jp-form-group">
                                <label class="jp-checkbox-label">
                                    <input type="checkbox" name="email_notifications" value="1" checked />
                                    <span class="jp-checkbox-custom"></span>
                                    Email notifications for new opportunities
                                </label>
                            </div>
                            <div class="jp-form-group">
                                <label class="jp-checkbox-label">
                                    <input type="checkbox" name="introduction_notifications" value="1" checked />
                                    <span class="jp-checkbox-custom"></span>
                                    Introduction request notifications
                                </label>
                            </div>
                            <div class="jp-form-group">
                                <label class="jp-checkbox-label">
                                    <input type="checkbox" name="market_updates" value="1" checked />
                                    <span class="jp-checkbox-custom"></span>
                                    Market intelligence updates
                                </label>
                            </div>
                        </div>

                        <div class="jp-form-section">
                            <h3>Privacy</h3>
                            <div class="jp-form-group">
                                <label for="profile_visibility" class="jp-form-label">Profile Visibility</label>
                                <select id="profile_visibility" name="profile_visibility" class="jp-form-select">
                                    <option value="public">Public - visible to all members</option>
                                    <option value="members">Members only</option>
                                    <option value="private">Private - hidden from searches</option>
                                </select>
                            </div>
                        </div>

                        <div class="jp-form-actions">
                            <button type="submit" class="jp-btn jp-btn-primary">Save Preferences</button>
                        </div>
                    </form>
                </section>

            </main>
        </div>
    </div>
</div>