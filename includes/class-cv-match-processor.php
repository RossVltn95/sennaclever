<?php
/**
 * CV Match Processor
 * Processes CV data for accurate job matching with flexible title matching
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CV_Match_Processor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into CV upload completion
        add_action('sffc_cv_uploaded', [$this, 'process_cv_for_matching'], 10, 2);
        add_action('sffc_cv_parsed', [$this, 'trigger_automatic_matching'], 15, 2);
        
        // Hook into job posting for immediate matching
        add_action('transition_post_status', [$this, 'process_new_job_for_matching'], 10, 3);
    }
    
    /**
     * Create required database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Track sent matches to prevent duplicates
        $sent_matches_table = $wpdb->prefix . 'sffc_sent_matches';
        $sent_matches_sql = "CREATE TABLE IF NOT EXISTS $sent_matches_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            job_id bigint(20) NOT NULL,
            match_score decimal(5,2) NOT NULL,
            message_id bigint(20) DEFAULT NULL,
            match_details longtext,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            user_response varchar(50) DEFAULT 'pending',
            PRIMARY KEY (id),
            UNIQUE KEY user_job (user_id, job_id),
            KEY user_id (user_id),
            KEY job_id (job_id),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        // Job title taxonomy for flexible matching
        $title_taxonomy_table = $wpdb->prefix . 'sffc_job_title_taxonomy';
        $title_taxonomy_sql = "CREATE TABLE IF NOT EXISTS $title_taxonomy_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            base_title varchar(255) NOT NULL,
            variant_title varchar(255) NOT NULL,
            similarity_score decimal(3,2) DEFAULT 0.90,
            category varchar(100) DEFAULT 'general',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY base_title (base_title),
            KEY variant_title (variant_title),
            KEY category (category)
        ) $charset_collate;";
        
        // CV extraction cache for performance
        $cv_cache_table = $wpdb->prefix . 'sffc_cv_extraction_cache';
        $cv_cache_sql = "CREATE TABLE IF NOT EXISTS $cv_cache_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            cv_hash varchar(64) NOT NULL,
            extracted_data longtext,
            extraction_confidence decimal(3,2) DEFAULT 0.80,
            extracted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_cv (user_id, cv_hash),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sent_matches_sql);
        dbDelta($title_taxonomy_sql);
        dbDelta($cv_cache_sql);
        
        // Insert default job title taxonomy
        $this->populate_job_title_taxonomy();
    }
    
    /**
     * Populate job title taxonomy with common finance variations
     */
    private function populate_job_title_taxonomy() {
        global $wpdb;
        
        $taxonomy_table = $wpdb->prefix . 'sffc_job_title_taxonomy';
        
        // Check if already populated
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $taxonomy_table");
        if ($existing > 0) {
            return;
        }
        
        $job_title_mappings = [
            // Investment Analyst variations
            ['Investment Analyst', 'Investment Analyst - Mega Fund', 0.95, 'investment'],
            ['Investment Analyst', 'Investment Analyst (Hedge Fund)', 0.90, 'investment'],
            ['Investment Analyst', 'Junior Investment Analyst', 0.85, 'investment'],
            ['Investment Analyst', 'Senior Investment Analyst', 0.95, 'investment'],
            ['Investment Analyst', 'Investment Research Analyst', 0.90, 'investment'],
            ['Investment Analyst', 'Equity Research Analyst', 0.85, 'investment'],
            
            // Portfolio Manager variations
            ['Portfolio Manager', 'Senior Portfolio Manager', 0.95, 'portfolio'],
            ['Portfolio Manager', 'Fund Manager', 0.90, 'portfolio'],
            ['Portfolio Manager', 'Asset Manager', 0.85, 'portfolio'],
            ['Portfolio Manager', 'Investment Manager', 0.90, 'portfolio'],
            
            // Private Equity variations
            ['Private Equity Associate', 'PE Associate', 0.95, 'private_equity'],
            ['Private Equity Associate', 'Private Equity Analyst', 0.85, 'private_equity'],
            ['Private Equity Associate', 'Growth Equity Associate', 0.90, 'private_equity'],
            ['Private Equity Associate', 'Buyout Associate', 0.90, 'private_equity'],
            
            // Investment Banking variations
            ['Investment Banking Analyst', 'IB Analyst', 0.95, 'investment_banking'],
            ['Investment Banking Analyst', 'Corporate Finance Analyst', 0.85, 'investment_banking'],
            ['Investment Banking Analyst', 'M&A Analyst', 0.90, 'investment_banking'],
            ['Investment Banking Associate', 'IB Associate', 0.95, 'investment_banking'],
            ['Investment Banking Associate', 'Corporate Finance Associate', 0.85, 'investment_banking'],
            
            // Hedge Fund variations
            ['Hedge Fund Analyst', 'Fund Analyst', 0.90, 'hedge_fund'],
            ['Hedge Fund Analyst', 'Quantitative Analyst', 0.80, 'hedge_fund'],
            ['Hedge Fund Analyst', 'Research Analyst', 0.80, 'hedge_fund'],
            
            // Risk Management variations
            ['Risk Manager', 'Senior Risk Manager', 0.95, 'risk'],
            ['Risk Manager', 'Risk Analyst', 0.80, 'risk'],
            ['Risk Manager', 'Credit Risk Manager', 0.90, 'risk'],
            ['Risk Manager', 'Market Risk Manager', 0.90, 'risk'],
            
            // Compliance variations
            ['Compliance Officer', 'Senior Compliance Officer', 0.95, 'compliance'],
            ['Compliance Officer', 'Compliance Manager', 0.90, 'compliance'],
            ['Compliance Officer', 'Regulatory Affairs Officer', 0.85, 'compliance'],
            
            // Operations variations
            ['Operations Manager', 'Fund Operations Manager', 0.90, 'operations'],
            ['Operations Manager', 'Investment Operations Manager', 0.90, 'operations'],
            ['Operations Manager', 'Senior Operations Manager', 0.95, 'operations'],
        ];
        
        foreach ($job_title_mappings as $mapping) {
            $wpdb->insert(
                $taxonomy_table,
                [
                    'base_title' => $mapping[0],
                    'variant_title' => $mapping[1],
                    'similarity_score' => $mapping[2],
                    'category' => $mapping[3]
                ],
                ['%s', '%s', '%f', '%s']
            );
        }
    }
    
    /**
     * Process CV data when uploaded
     */
    public function process_cv_for_matching($user_id, $cv_data) {
        try {
            // Extract key information from CV
            $extracted_data = $this->extract_cv_matching_data($cv_data);
            
            // Cache the extraction
            $this->cache_cv_extraction($user_id, $cv_data, $extracted_data);
            
            // Update user profile with CV-derived data
            $this->update_user_profile_from_cv($user_id, $extracted_data);
            
            // Log successful processing
            error_log("CV processed for user $user_id: " . json_encode($extracted_data));
            
        } catch (Exception $e) {
            error_log("CV processing error for user $user_id: " . $e->getMessage());
        }
    }
    
    /**
     * Extract matching-relevant data from CV
     */
    private function extract_cv_matching_data($cv_data) {
        $extracted = [
            'job_titles' => [],
            'skills' => [],
            'experience_years' => 0,
            'industries' => [],
            'locations' => [],
            'current_role' => '',
            'career_level' => 'Analyst',
            'confidence' => 0.8
        ];
        
        if (isset($cv_data['parsed_data'])) {
            $parsed = $cv_data['parsed_data'];
            
            // Extract job titles from experience
            if (!empty($parsed['experience'])) {
                foreach ($parsed['experience'] as $exp) {
                    if (!empty($exp['position'])) {
                        $extracted['job_titles'][] = $this->normalize_job_title($exp['position']);
                    }
                    if (!empty($exp['company'])) {
                        $extracted['industries'][] = $this->infer_industry_from_company($exp['company']);
                    }
                }
                
                // Set current role as most recent
                if (!empty($parsed['experience'][0]['position'])) {
                    $extracted['current_role'] = $this->normalize_job_title($parsed['experience'][0]['position']);
                }
                
                // Calculate experience years
                $extracted['experience_years'] = $this->calculate_experience_years($parsed['experience']);
            }
            
            // Extract skills
            if (!empty($parsed['skills'])) {
                if (isset($parsed['skills']['technical'])) {
                    $extracted['skills'] = array_merge($extracted['skills'], $parsed['skills']['technical']);
                }
                if (isset($parsed['skills']['soft'])) {
                    $extracted['skills'] = array_merge($extracted['skills'], $parsed['skills']['soft']);
                }
            }
            
            // Extract locations from contact or experience
            if (!empty($parsed['contact']['location'])) {
                $extracted['locations'][] = $parsed['contact']['location'];
            }
            
            // Determine career level from titles
            $extracted['career_level'] = $this->determine_career_level($extracted['job_titles']);
        }
        
        // Remove duplicates and clean data
        $extracted['job_titles'] = array_unique($extracted['job_titles']);
        $extracted['skills'] = array_unique($extracted['skills']);
        $extracted['industries'] = array_filter(array_unique($extracted['industries']));
        $extracted['locations'] = array_unique($extracted['locations']);
        
        return $extracted;
    }
    
    /**
     * Normalize job titles for consistent matching
     */
    private function normalize_job_title($title) {
        // Remove common suffixes/prefixes that don't affect matching
        $title = trim($title);
        $title = preg_replace('/\s*\([^)]*\)\s*/', ' ', $title); // Remove parentheses content
        $title = preg_replace('/\s*-\s*[IV]+\s*$/', '', $title); // Remove roman numerals
        $title = preg_replace('/\s*\d+\s*$/', '', $title); // Remove trailing numbers
        $title = preg_replace('/\s+/', ' ', $title); // Normalize spaces
        
        return trim($title);
    }
    
    /**
     * Infer industry from company name
     */
    private function infer_industry_from_company($company) {
        $company_lower = strtolower($company);
        
        $industry_keywords = [
            'Investment Banking' => ['goldman', 'morgan stanley', 'jp morgan', 'bank', 'securities'],
            'Private Equity' => ['blackstone', 'kkr', 'carlyle', 'apollo', 'equity'],
            'Hedge Fund' => ['citadel', 'bridgewater', 'fund', 'capital management'],
            'Asset Management' => ['vanguard', 'fidelity', 'asset', 'management'],
            'Consulting' => ['mckinsey', 'bcg', 'bain', 'consulting'],
            'Technology' => ['google', 'microsoft', 'apple', 'tech', 'software'],
            'Insurance' => ['aig', 'allianz', 'insurance', 'life'],
        ];
        
        foreach ($industry_keywords as $industry => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($company_lower, $keyword) !== false) {
                    return $industry;
                }
            }
        }
        
        return 'Financial Services'; // Default for finance-focused platform
    }
    
    /**
     * Calculate total years of experience
     */
    private function calculate_experience_years($experience) {
        if (empty($experience)) {
            return 0;
        }
        
        $total_months = 0;
        
        foreach ($experience as $exp) {
            if (!empty($exp['date_range'])) {
                $months = $this->parse_date_range_to_months($exp['date_range']);
                $total_months += $months;
            }
        }
        
        return round($total_months / 12, 1);
    }
    
    /**
     * Parse date range to months
     */
    private function parse_date_range_to_months($date_range) {
        if (stripos($date_range, 'present') !== false || stripos($date_range, 'current') !== false) {
            // Extract start date and calculate to present
            preg_match('/(\d{4})/', $date_range, $matches);
            if (!empty($matches[1])) {
                $start_year = intval($matches[1]);
                $current_year = date('Y');
                return max(6, ($current_year - $start_year) * 12); // Minimum 6 months
            }
        }
        
        // Extract years from range like "2020 - 2022"
        preg_match_all('/(\d{4})/', $date_range, $matches);
        if (count($matches[1]) >= 2) {
            $start = intval($matches[1][0]);
            $end = intval($matches[1][1]);
            return max(6, ($end - $start) * 12); // Minimum 6 months
        }
        
        return 12; // Default to 1 year if can't parse
    }
    
    /**
     * Determine career level from job titles
     */
    private function determine_career_level($job_titles) {
        $levels = [
            'Intern' => ['intern', 'trainee'],
            'Analyst' => ['analyst', 'associate (entry)', 'junior'],
            'Associate' => ['associate', 'senior analyst'],
            'Vice President' => ['vice president', 'vp', 'senior associate'],
            'Director' => ['director', 'principal'],
            'Managing Director' => ['managing director', 'md', 'partner'],
            'Partner' => ['partner', 'managing partner']
        ];
        
        foreach ($job_titles as $title) {
            $title_lower = strtolower($title);
            foreach ($levels as $level => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($title_lower, $keyword) !== false) {
                        return $level;
                    }
                }
            }
        }
        
        return 'Analyst'; // Default level
    }
    
    /**
     * Cache CV extraction for performance
     */
    private function cache_cv_extraction($user_id, $cv_data, $extracted_data) {
        global $wpdb;
        
        $cv_hash = md5(json_encode($cv_data));
        $cache_table = $wpdb->prefix . 'sffc_cv_extraction_cache';
        
        $wpdb->replace(
            $cache_table,
            [
                'user_id' => $user_id,
                'cv_hash' => $cv_hash,
                'extracted_data' => json_encode($extracted_data),
                'extraction_confidence' => $extracted_data['confidence'] ?? 0.8
            ],
            ['%d', '%s', '%s', '%f']
        );
    }
    
    /**
     * Update user profile with CV-derived data
     */
    private function update_user_profile_from_cv($user_id, $extracted_data) {
        global $wpdb;
        
        $profile_table = $wpdb->prefix . 'sffc_user_profiles';
        
        // Prepare update data
        $update_data = [];
        
        if (!empty($extracted_data['current_role'])) {
            $update_data['current_title'] = $extracted_data['current_role'];
        }
        
        if (!empty($extracted_data['experience_years'])) {
            $update_data['years_experience'] = $extracted_data['experience_years'];
        }
        
        if (!empty($extracted_data['career_level'])) {
            $update_data['career_stage'] = $extracted_data['career_level'];
        }
        
        if (!empty($extracted_data['industries'])) {
            $update_data['preferred_industries'] = json_encode($extracted_data['industries']);
        }
        
        if (!empty($extracted_data['locations'])) {
            $update_data['preferred_locations'] = json_encode($extracted_data['locations']);
        }
        
        if (!empty($update_data)) {
            $update_data['last_updated'] = current_time('mysql');
            
            $wpdb->update(
                $profile_table,
                $update_data,
                ['user_id' => $user_id],
                array_fill(0, count($update_data), '%s'),
                ['%d']
            );
            
            // Also update skills table
            $this->update_user_skills_from_cv($user_id, $extracted_data['skills'] ?? []);
        }
    }
    
    /**
     * Update user skills from CV
     */
    private function update_user_skills_from_cv($user_id, $skills) {
        if (empty($skills)) {
            return;
        }
        
        global $wpdb;
        $skills_table = $wpdb->prefix . 'sffc_user_skills';
        
        foreach ($skills as $skill) {
            $skill = trim($skill);
            if (empty($skill)) {
                continue;
            }
            
            // Check if skill already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $skills_table WHERE user_id = %d AND skill_name = %s",
                $user_id,
                $skill
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $skills_table,
                    [
                        'user_id' => $user_id,
                        'skill_name' => $skill,
                        'skill_category' => $this->categorize_skill($skill),
                        'proficiency_level' => 'Intermediate', // Default from CV
                        'years_experience' => 2, // Default assumption
                        'source' => 'cv_upload'
                    ],
                    ['%d', '%s', '%s', '%s', '%d', '%s']
                );
            }
        }
    }
    
    /**
     * Categorize skills
     */
    private function categorize_skill($skill) {
        $skill_lower = strtolower($skill);
        
        $categories = [
            'Technical' => ['excel', 'python', 'sql', 'bloomberg', 'vba', 'r', 'matlab', 'tableau'],
            'Financial' => ['valuation', 'modeling', 'dcf', 'lbo', 'merger', 'acquisition', 'derivatives'],
            'Soft Skills' => ['leadership', 'communication', 'teamwork', 'analytical', 'problem solving'],
            'Languages' => ['english', 'spanish', 'french', 'german', 'mandarin', 'cantonese']
        ];
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($skill_lower, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'Technical'; // Default category
    }
    
    /**
     * Trigger automatic matching after CV is parsed
     */
    public function trigger_automatic_matching($user_id, $cv_data) {
        // Trigger matching in background to avoid blocking CV upload
        wp_schedule_single_event(time() + 30, 'sffc_process_user_matches', [$user_id]);
    }
    
    /**
     * Process new job for matching against all users
     */
    public function process_new_job_for_matching($new_status, $old_status, $post) {
        if ($post->post_type !== 'sffc_job' || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Schedule background processing for all users
        wp_schedule_single_event(time() + 60, 'sffc_process_job_matches', [$post->ID]);
    }
    
    /**
     * Get cached CV extraction data
     */
    public function get_cached_cv_data($user_id) {
        global $wpdb;
        
        $cache_table = $wpdb->prefix . 'sffc_cv_extraction_cache';
        
        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $cache_table WHERE user_id = %d ORDER BY extracted_at DESC LIMIT 1",
            $user_id
        ));
        
        if ($cached) {
            return json_decode($cached->extracted_data, true);
        }
        
        return null;
    }
    
    /**
     * Check if match was already sent
     */
    public function is_match_already_sent($user_id, $job_id) {
        global $wpdb;
        
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $sent_table WHERE user_id = %d AND job_id = %d",
            $user_id,
            $job_id
        ));
        
        return !empty($exists);
    }
    
    /**
     * Record sent match
     */
    public function record_sent_match($user_id, $job_id, $match_score, $message_id, $match_details) {
        global $wpdb;
        
        $sent_table = $wpdb->prefix . 'sffc_sent_matches';
        
        return $wpdb->insert(
            $sent_table,
            [
                'user_id' => $user_id,
                'job_id' => $job_id,
                'match_score' => $match_score,
                'message_id' => $message_id,
                'match_details' => json_encode($match_details)
            ],
            ['%d', '%d', '%f', '%d', '%s']
        );
    }
}

// Initialize
SFFC_CV_Match_Processor::get_instance();
