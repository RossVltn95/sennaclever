<?php
/**
 * User Profile Manager
 * Core system for managing user profiles with skills, experience, and preferences
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_User_Profile_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_sffc_save_profile', [$this, 'ajax_save_profile']);
        add_action('wp_ajax_sffc_get_profile', [$this, 'ajax_get_profile']);
        add_action('wp_ajax_sffc_add_skill', [$this, 'ajax_add_skill']);
        add_action('wp_ajax_sffc_remove_skill', [$this, 'ajax_remove_skill']);
        add_action('wp_ajax_sffc_add_experience', [$this, 'ajax_add_experience']);
        
        // Frontend access
        add_action('wp_ajax_nopriv_sffc_get_profile', [$this, 'ajax_get_profile']);
    }
    
    /**
     * Create database tables for profile system
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main profiles table with enhanced fields
        $profiles_table = $wpdb->prefix . 'sffc_user_profiles';
        $profiles_sql = "CREATE TABLE IF NOT EXISTS $profiles_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            profile_completion_percentage int(3) DEFAULT 0,
            profile_picture_url varchar(500) DEFAULT '',
            headline varchar(255) DEFAULT '',
            summary longtext,
            career_stage varchar(50) DEFAULT 'Graduate',
            years_experience int(2) DEFAULT 0,
            current_title varchar(255) DEFAULT '',
            current_company varchar(255) DEFAULT '',
            salary_current int(10) DEFAULT 0,
            salary_target_min int(10) DEFAULT 0,
            salary_target_max int(10) DEFAULT 0,
            currency_preference varchar(10) DEFAULT 'USD',
            notice_period varchar(50) DEFAULT '1 month',
            visa_status varchar(100) DEFAULT 'Citizen',
            availability_date date DEFAULT NULL,
            open_to_relocation tinyint(1) DEFAULT 0,
            willing_to_travel varchar(50) DEFAULT 'No',
            preferred_locations longtext,
            preferred_industries longtext,
            preferred_company_size varchar(100) DEFAULT '',
            preferred_work_environment varchar(100) DEFAULT 'Hybrid',
            preferred_contract_type varchar(100) DEFAULT 'Full-time',
            languages_spoken longtext,
            professional_memberships longtext,
            regional_expertise longtext,
            career_goals longtext,
            target_role_level varchar(100) DEFAULT '',
            career_transition_interest varchar(255) DEFAULT '',
            
            work_style_preference varchar(100) DEFAULT '',
            company_culture_fit varchar(100) DEFAULT '',
            membership_level varchar(50) DEFAULT 'free',
            subscription_status varchar(50) DEFAULT 'inactive',
            subscription_expires datetime DEFAULT NULL,
            premium_features_enabled tinyint(1) DEFAULT 0,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY career_stage (career_stage),
            KEY years_experience (years_experience),
            KEY availability_date (availability_date),
            KEY target_role_level (target_role_level),
            KEY membership_level (membership_level),
            KEY subscription_status (subscription_status)
        ) $charset_collate;";
        
        // Skills table
        $skills_table = $wpdb->prefix . 'sffc_user_skills';
        $skills_sql = "CREATE TABLE IF NOT EXISTS $skills_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            skill_name varchar(255) NOT NULL,
            skill_category varchar(100) DEFAULT 'Technical',
            proficiency_level varchar(50) DEFAULT 'Intermediate',
            years_experience int(2) DEFAULT 1,
            verified tinyint(1) DEFAULT 0,
            source varchar(100) DEFAULT 'User Added',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY skill_category (skill_category),
            KEY proficiency_level (proficiency_level),
            UNIQUE KEY user_skill (user_id, skill_name)
        ) $charset_collate;";
        
        // Experience table
        $experience_table = $wpdb->prefix . 'sffc_user_experience';
        $experience_sql = "CREATE TABLE IF NOT EXISTS $experience_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            company_name varchar(255) NOT NULL,
            job_title varchar(255) NOT NULL,
            industry varchar(100) DEFAULT '',
            department varchar(100) DEFAULT '',
            start_date date NOT NULL,
            end_date date NULL,
            description longtext,
            achievements longtext,
            skills_gained longtext,
            is_current tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY industry (industry),
            KEY is_current (is_current)
        ) $charset_collate;";
        
        // Education table
        $education_table = $wpdb->prefix . 'sffc_user_education';
        $education_sql = "CREATE TABLE IF NOT EXISTS $education_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            institution_name varchar(255) NOT NULL,
            degree_type varchar(100) NOT NULL,
            field_of_study varchar(255) NOT NULL,
            graduation_year int(4) NOT NULL,
            grade varchar(50) DEFAULT '',
            relevant_coursework longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY degree_type (degree_type)
        ) $charset_collate;";
        
        // Certifications table
        $certifications_table = $wpdb->prefix . 'sffc_user_certifications';
        $certifications_sql = "CREATE TABLE IF NOT EXISTS $certifications_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            certification_name varchar(255) NOT NULL,
            issuing_organization varchar(255) NOT NULL,
            issue_date date NOT NULL,
            expiry_date date NULL,
            credential_id varchar(255) DEFAULT '',
            verification_url varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY issuing_organization (issuing_organization)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Suppress output from dbDelta during activation
        @dbDelta($profiles_sql);
        @dbDelta($skills_sql);
        @dbDelta($experience_sql);
        @dbDelta($education_sql);
        @dbDelta($certifications_sql);
    }
    
    /**
     * Get complete user profile
     */
    public function get_user_profile($user_id) {
        global $wpdb;
        
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_user_profiles WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$profile) {
            // Create default profile
            $profile = $this->create_default_profile($user_id);
        }
        
        // Get related data
        $profile['skills'] = $this->get_user_skills($user_id);
        $profile['experience'] = $this->get_user_experience($user_id);
        $profile['education'] = $this->get_user_education($user_id);
        $profile['certifications'] = $this->get_user_certifications($user_id);
        
        // Parse JSON fields for arrays
        $profile['preferred_locations'] = json_decode($profile['preferred_locations'] ?? '[]', true);
        $profile['preferred_industries'] = json_decode($profile['preferred_industries'] ?? '[]', true);
        $profile['career_goals'] = json_decode($profile['career_goals'] ?? '[]', true);
        $profile['languages_spoken'] = json_decode($profile['languages_spoken'] ?? '["English"]', true);
        $profile['professional_memberships'] = json_decode($profile['professional_memberships'] ?? '[]', true);
        $profile['regional_expertise'] = json_decode($profile['regional_expertise'] ?? '[]', true);
        
        // Get subscription data if MemberPress is active
        if (class_exists('SFFC_MemberPress_Integration')) {
            $mp_integration = SFFC_MemberPress_Integration::get_instance();
            $profile['has_premium_access'] = $mp_integration->has_premium_access($user_id);
            $profile['active_subscriptions'] = $mp_integration->get_user_subscriptions($user_id);
        }
        
        return $profile;
    }
    
    /**
     * Create default profile for new user
     */
    private function create_default_profile($user_id) {
        global $wpdb;
        
        $user = get_user_by('id', $user_id);
        
        $default_profile = [
            'user_id' => $user_id,
            'profile_completion_percentage' => 10,
            
            // Basic Information
            'profile_picture_url' => '',
            'headline' => '',
            'summary' => '',
            
            // Current Status
            'career_stage' => 'Graduate',
            'years_experience' => 0,
            'current_title' => '',
            'current_company' => '',
            
            // Compensation
            'salary_current' => 0,
            'salary_target_min' => 50000,
            'salary_target_max' => 100000,
            'currency_preference' => 'USD',
            
            // Availability & Mobility
            'notice_period' => '1 month',
            'visa_status' => 'Citizen',
            'availability_date' => null,
            'open_to_relocation' => 0,
            'willing_to_travel' => 'No',
            
            // Career Preferences
            'preferred_locations' => json_encode(['London', 'New York']),
            'preferred_industries' => json_encode(['Investment Banking', 'Private Equity']),
            'preferred_company_size' => '',
            'preferred_work_environment' => 'Hybrid',
            'preferred_contract_type' => 'Full-time',
            
            // Skills & Expertise
            'languages_spoken' => json_encode(['English']),
            'professional_memberships' => json_encode([]),
            'regional_expertise' => json_encode([]),
            
            // Career Goals
            'career_goals' => json_encode([]),
            'target_role_level' => '',
            'career_transition_interest' => '',
            
            // Work Style
            'work_style_preference' => '',
            'company_culture_fit' => '',
            
            // Subscription Integration
            'membership_level' => 'free',
            'subscription_status' => 'inactive',
            'subscription_expires' => null,
            'premium_features_enabled' => 0
        ];
        
        $wpdb->insert(
            $wpdb->prefix . 'sffc_user_profiles',
            $default_profile
        );
        
        $default_profile['id'] = $wpdb->insert_id;
        return $default_profile;
    }
    
    /**
     * Get user skills
     */
    public function get_user_skills($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_user_skills 
             WHERE user_id = %d 
             ORDER BY proficiency_level DESC, skill_category, skill_name",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get user experience
     */
    public function get_user_experience($user_id) {
        global $wpdb;
        
        $experiences = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_user_experience 
             WHERE user_id = %d 
             ORDER BY is_current DESC, start_date DESC",
            $user_id
        ), ARRAY_A);
        
        // Parse JSON fields
        foreach ($experiences as &$exp) {
            $exp['achievements'] = json_decode($exp['achievements'] ?? '[]', true);
            $exp['skills_gained'] = json_decode($exp['skills_gained'] ?? '[]', true);
        }
        
        return $experiences;
    }
    
    /**
     * Get user education
     */
    public function get_user_education($user_id) {
        global $wpdb;
        
        $education = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_user_education 
             WHERE user_id = %d 
             ORDER BY graduation_year DESC",
            $user_id
        ), ARRAY_A);
        
        foreach ($education as &$edu) {
            $edu['relevant_coursework'] = json_decode($edu['relevant_coursework'] ?? '[]', true);
        }
        
        return $education;
    }
    
    /**
     * Get user certifications
     */
    public function get_user_certifications($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffc_user_certifications 
             WHERE user_id = %d 
             ORDER BY issue_date DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Save user profile
     */
    public function save_profile($user_id, $profile_data) {
        global $wpdb;
        
        // Prepare data for database with all new fields
        $db_data = [
            // Basic Information
            'profile_picture_url' => esc_url_raw($profile_data['profile_picture_url'] ?? ''),
            'headline' => sanitize_text_field($profile_data['headline'] ?? ''),
            'summary' => wp_kses_post($profile_data['summary'] ?? ''),
            
            // Current Status
            'career_stage' => sanitize_text_field($profile_data['career_stage'] ?? 'Graduate'),
            'years_experience' => intval($profile_data['years_experience'] ?? 0),
            'current_title' => sanitize_text_field($profile_data['current_title'] ?? ''),
            'current_company' => sanitize_text_field($profile_data['current_company'] ?? ''),
            
            // Compensation
            'salary_current' => intval($profile_data['salary_current'] ?? 0),
            'salary_target_min' => intval($profile_data['salary_target_min'] ?? 0),
            'salary_target_max' => intval($profile_data['salary_target_max'] ?? 0),
            'currency_preference' => sanitize_text_field($profile_data['currency_preference'] ?? 'USD'),
            
            // Availability & Mobility
            'notice_period' => sanitize_text_field($profile_data['notice_period'] ?? '1 month'),
            'visa_status' => sanitize_text_field($profile_data['visa_status'] ?? 'Citizen'),
            'availability_date' => !empty($profile_data['availability_date']) ? sanitize_text_field($profile_data['availability_date']) : null,
            'open_to_relocation' => intval($profile_data['open_to_relocation'] ?? 0),
            'willing_to_travel' => sanitize_text_field($profile_data['willing_to_travel'] ?? 'No'),
            
            // Career Preferences
            'preferred_locations' => json_encode($profile_data['preferred_locations'] ?? []),
            'preferred_industries' => json_encode($profile_data['preferred_industries'] ?? []),
            'preferred_company_size' => sanitize_text_field($profile_data['preferred_company_size'] ?? ''),
            'preferred_work_environment' => sanitize_text_field($profile_data['preferred_work_environment'] ?? 'Hybrid'),
            'preferred_contract_type' => sanitize_text_field($profile_data['preferred_contract_type'] ?? 'Full-time'),
            
            // Skills & Expertise
            'languages_spoken' => json_encode($profile_data['languages_spoken'] ?? ['English']),
            'professional_memberships' => json_encode($profile_data['professional_memberships'] ?? []),
            'regional_expertise' => json_encode($profile_data['regional_expertise'] ?? []),
            
            // Career Goals
            'career_goals' => json_encode($profile_data['career_goals'] ?? []),
            'target_role_level' => sanitize_text_field($profile_data['target_role_level'] ?? ''),
            'career_transition_interest' => sanitize_text_field($profile_data['career_transition_interest'] ?? ''),
            
            // Work Style
            'work_style_preference' => sanitize_text_field($profile_data['work_style_preference'] ?? ''),
            'company_culture_fit' => sanitize_text_field($profile_data['company_culture_fit'] ?? '')
        ];
        
        // Calculate completion percentage
        $db_data['profile_completion_percentage'] = $this->calculate_completion_percentage($profile_data);
        
        // Update or insert
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sffc_user_profiles WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            $result = $wpdb->update(
                $wpdb->prefix . 'sffc_user_profiles',
                $db_data,
                ['user_id' => $user_id]
            );
        } else {
            $db_data['user_id'] = $user_id;
            $result = $wpdb->insert(
                $wpdb->prefix . 'sffc_user_profiles',
                $db_data
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Calculate profile completion percentage with enhanced fields
     */
    private function calculate_completion_percentage($profile_data) {
        $fields = [
            // Basic Information (20%)
            'profile_picture_url' => 5,
            'headline' => 5,
            'summary' => 10,
            
            // Current Status (15%)
            'career_stage' => 3,
            'years_experience' => 3,
            'current_title' => 5,
            'current_company' => 4,
            
            // Compensation (10%)
            'salary_current' => 3,
            'salary_target_min' => 3,
            'salary_target_max' => 2,
            'currency_preference' => 2,
            
            // Preferences (15%)
            'preferred_locations' => 5,
            'preferred_industries' => 5,
            'preferred_company_size' => 2,
            'preferred_work_environment' => 2,
            'preferred_contract_type' => 1,
            
            // Career Goals (10%)
            'target_role_level' => 5,
            'career_transition_interest' => 3,
            'career_goals' => 2,
            
            // Skills & Languages (10%)
            'languages_spoken' => 5,
            'professional_memberships' => 3,
            'regional_expertise' => 2,
            
            // Work Style (5%)
            'work_style_preference' => 3,
            'company_culture_fit' => 2,
            
            // Dynamic counts (15%)
            'skills_count' => 10,
            'experience_count' => 5
        ];
        
        $completion = 0;
        
        foreach ($fields as $field => $weight) {
            if ($field === 'skills_count') {
                $skills_count = count($profile_data['skills'] ?? []);
                if ($skills_count >= 5) $completion += $weight;
                elseif ($skills_count >= 3) $completion += $weight * 0.7;
                elseif ($skills_count >= 1) $completion += $weight * 0.4;
            } elseif ($field === 'experience_count') {
                $exp_count = count($profile_data['experience'] ?? []);
                if ($exp_count >= 2) $completion += $weight;
                elseif ($exp_count >= 1) $completion += $weight * 0.7;
            } elseif ($field === 'languages_spoken' || $field === 'professional_memberships' || $field === 'regional_expertise') {
                // Handle array fields
                $array_data = is_array($profile_data[$field]) ? $profile_data[$field] : json_decode($profile_data[$field] ?? '[]', true);
                if (!empty($array_data) && count($array_data) > 0) {
                    $completion += $weight;
                }
            } elseif (!empty($profile_data[$field])) {
                $completion += $weight;
            }
        }
        
        return min(100, $completion);
    }
    
    /**
     * Add skill to user profile
     */
    public function add_skill($user_id, $skill_data) {
        global $wpdb;
        
        $skill_record = [
            'user_id' => $user_id,
            'skill_name' => sanitize_text_field($skill_data['skill_name']),
            'skill_category' => sanitize_text_field($skill_data['skill_category'] ?? 'Technical'),
            'proficiency_level' => sanitize_text_field($skill_data['proficiency_level'] ?? 'Intermediate'),
            'years_experience' => intval($skill_data['years_experience'] ?? 1),
            'verified' => 0,
            'source' => sanitize_text_field($skill_data['source'] ?? 'User Added')
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_user_skills',
            $skill_record
        );
        
        if ($result) {
            // Recalculate profile completion
            $this->recalculate_profile_completion($user_id);
        }
        
        return $result;
    }
    
    /**
     * Remove skill from user profile
     */
    public function remove_skill($user_id, $skill_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'sffc_user_skills',
            [
                'id' => $skill_id,
                'user_id' => $user_id
            ],
            ['%d', '%d']
        );
        
        if ($result) {
            $this->recalculate_profile_completion($user_id);
        }
        
        return $result;
    }
    
    /**
     * Add experience to user profile
     */
    public function add_experience($user_id, $experience_data) {
        global $wpdb;
        
        // If this is current role, update any existing current role
        if (!empty($experience_data['is_current'])) {
            $wpdb->update(
                $wpdb->prefix . 'sffc_user_experience',
                ['is_current' => 0],
                ['user_id' => $user_id, 'is_current' => 1]
            );
        }
        
        $experience_record = [
            'user_id' => $user_id,
            'company_name' => sanitize_text_field($experience_data['company_name']),
            'job_title' => sanitize_text_field($experience_data['job_title']),
            'industry' => sanitize_text_field($experience_data['industry'] ?? ''),
            'department' => sanitize_text_field($experience_data['department'] ?? ''),
            'start_date' => sanitize_text_field($experience_data['start_date']),
            'end_date' => !empty($experience_data['end_date']) ? sanitize_text_field($experience_data['end_date']) : null,
            'description' => wp_kses_post($experience_data['description'] ?? ''),
            'achievements' => json_encode($experience_data['achievements'] ?? []),
            'skills_gained' => json_encode($experience_data['skills_gained'] ?? []),
            'is_current' => intval($experience_data['is_current'] ?? 0)
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sffc_user_experience',
            $experience_record
        );
        
        if ($result) {
            $this->recalculate_profile_completion($user_id);
        }
        
        return $result;
    }
    
    /**
     * Recalculate profile completion percentage
     */
    private function recalculate_profile_completion($user_id) {
        $profile = $this->get_user_profile($user_id);
        $completion = $this->calculate_completion_percentage($profile);
        
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sffc_user_profiles',
            ['profile_completion_percentage' => $completion],
            ['user_id' => $user_id]
        );
    }
    
    /**
     * Get finance skills taxonomy
     */
    public function get_skills_taxonomy() {
        return [
            'Technical' => [
                'Excel (Advanced)', 'PowerPoint (Advanced)', 'Financial Modeling',
                'Python', 'R', 'SQL', 'VBA', 'MATLAB',
                'Tableau', 'Power BI', 'Qlik', 'Alteryx',
                'Bloomberg Terminal', 'FactSet', 'Reuters Eikon',
                'Salesforce', 'HubSpot', 'Marketo'
            ],
            'Financial Analysis' => [
                'DCF Modeling', 'LBO Modeling', 'Comps Analysis', 'Precedent Transactions',
                'Valuation', 'Financial Statement Analysis', 'Ratio Analysis',
                'Credit Analysis', 'Risk Management', 'Portfolio Management',
                'Derivatives', 'Fixed Income', 'Equity Research'
            ],
            'Industry Knowledge' => [
                'Investment Banking', 'Private Equity', 'Venture Capital', 'Asset Management',
                'Hedge Funds', 'Commercial Banking', 'Insurance', 'Real Estate',
                'Compliance', 'Audit', 'Tax', 'Regulatory Reporting'
            ],
            'Software' => [
                'SAP', 'Oracle', 'QuickBooks', 'Xero', 'Workday',
                'Concur', 'BlackLine', 'Hyperion', 'Cognos', 'Essbase'
            ],
            'Certifications' => [
                'CFA', 'FRM', 'CPA', 'ACCA', 'CAIA', 'PRM', 'ACA', 'CIA'
            ],
            'Soft Skills' => [
                'Leadership', 'Communication', 'Problem Solving', 'Analytical Thinking',
                'Attention to Detail', 'Time Management', 'Teamwork', 'Client Management',
                'Presentation Skills', 'Negotiation', 'Project Management'
            ]
        ];
    }
    
    /**
     * Get available options for profile fields
     */
    public function get_profile_field_options() {
        return [
            'company_size' => [
                'Startup (1-50)',
                'Small (51-200)',
                'Mid-size (201-1000)',
                'Large Enterprise (1000+)',
                'Investment Bank',
                'Boutique Firm',
                'Big 4'
            ],
            'work_environment' => [
                'Office',
                'Remote',
                'Hybrid',
                'Travel-heavy',
                'Client Site'
            ],
            'contract_type' => [
                'Full-time',
                'Part-time',
                'Contract',
                'Consulting',
                'Interim',
                'Freelance'
            ],
            'willing_to_travel' => [
                'No',
                '25%',
                '50%',
                '75%',
                '100%'
            ],
            'currency' => [
                'USD',
                'GBP',
                'EUR',
                'CHF',
                'AUD',
                'CAD',
                'SGD',
                'HKD',
                'JPY'
            ],
            'target_role_level' => [
                'Graduate/Entry Level',
                'Analyst',
                'Senior Analyst',
                'Associate',
                'Senior Associate',
                'Vice President',
                'Director',
                'Managing Director',
                'Partner',
                'C-Level'
            ],
            'work_style' => [
                'Individual Contributor',
                'Team Player',
                'Team Leader',
                'People Manager',
                'Strategic Leader'
            ],
            'company_culture' => [
                'Conservative',
                'Innovative',
                'Fast-paced',
                'Collaborative',
                'Entrepreneurial',
                'Structured',
                'Flexible'
            ],
            'regional_expertise' => [
                'North America',
                'EMEA',
                'APAC',
                'Latin America',
                'private equity',
                'Africa',
                'Global'
            ],
            'languages' => [
                'English',
                'Mandarin',
                'Spanish',
                'French',
                'German',
                'Arabic',
                'Japanese',
                'Portuguese',
                'Russian',
                'Hindi'
            ],
            'professional_memberships' => [
                'CFA Institute',
                'GARP (Global Association of Risk Professionals)',
                'CAIA Association',
                'AICPA',
                'ACCA',
                'Institute of Chartered Accountants',
                'FPA (Financial Planning Association)',
                'CFA Society (Local Chapter)'
            ]
        ];
    }
    
    /**
     * AJAX: Save profile
     */
    public function ajax_save_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $profile_data = $_POST['profile_data'] ?? [];
        
        $result = $this->save_profile($user_id, $profile_data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Profile saved successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to save profile']);
        }
    }
    
    /**
     * AJAX: Get profile
     */
    public function ajax_get_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $profile = $this->get_user_profile($user_id);
        
        // Add subscription widget HTML if MemberPress is active
        if (class_exists('SFFC_MemberPress_Integration')) {
            $mp_integration = SFFC_MemberPress_Integration::get_instance();
            $profile['subscription_widget'] = $mp_integration->render_subscription_widget($user_id);
        }
        
        wp_send_json_success($profile);
    }
    
    /**
     * AJAX: Add skill
     */
    public function ajax_add_skill() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $skill_data = $_POST['skill_data'] ?? [];
        
        $result = $this->add_skill($user_id, $skill_data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Skill added successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to add skill']);
        }
    }
    
    /**
     * AJAX: Remove skill
     */
    public function ajax_remove_skill() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $skill_id = intval($_POST['skill_id'] ?? 0);
        
        $result = $this->remove_skill($user_id, $skill_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'Skill removed successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to remove skill']);
        }
    }
    
    /**
     * AJAX: Add experience
     */
    public function ajax_add_experience() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated']);
        }
        
        $user_id = get_current_user_id();
        $experience_data = $_POST['experience_data'] ?? [];
        
        $result = $this->add_experience($user_id, $experience_data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Experience added successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to add experience']);
        }
    }
}

// Initialize
SFFC_User_Profile_Manager::get_instance();
