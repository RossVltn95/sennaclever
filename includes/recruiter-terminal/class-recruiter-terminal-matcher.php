<?php
/**
 * Recruiter Terminal Match Scoring Engine
 *
 * Handles skill detection from brief text and calculates match scores
 * between user preferences and briefs.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruiter_Terminal_Matcher {

    /**
     * Finance-specific skills library
     * Key => array of keyword variations
     */
    private static $skills_library = array(
        // Financial Modeling & Analysis
        'financial_modeling' => array('financial model', 'financial modeling', 'excel model', '3-statement', 'three statement', 'integrated model'),
        'valuation' => array('valuation', 'dcf', 'discounted cash flow', 'comps', 'comparable companies', 'precedent transactions', 'sum of parts'),
        'lbo_modeling' => array('lbo', 'leveraged buyout', 'buyout model', 'lbo model', 'debt schedule'),
        'merger_model' => array('merger model', 'accretion dilution', 'accretion/dilution', 'm&a model'),

        // Deal & Transaction
        'ma_analysis' => array('m&a', 'mergers and acquisitions', 'mergers & acquisitions', 'deal analysis', 'transaction'),
        'due_diligence' => array('due diligence', 'dd process', 'diligence process', 'commercial dd', 'financial dd'),
        'deal_execution' => array('deal execution', 'transaction execution', 'deal closing', 'signing'),

        // Investment & Portfolio
        'investment_analysis' => array('investment analysis', 'investment memo', 'investment thesis', 'deal screening'),
        'portfolio_management' => array('portfolio management', 'portfolio company', 'value creation', 'post-acquisition'),
        'asset_management' => array('asset management', 'aum', 'assets under management'),

        // Fundraising & IR
        'fundraising' => array('fundraising', 'capital raising', 'fund formation', 'lp relations', 'limited partner'),
        'investor_relations' => array('investor relations', 'ir', 'shareholder relations', 'earnings call'),

        // Credit & Debt
        'credit_analysis' => array('credit analysis', 'credit', 'debt', 'lending', 'loan', 'fixed income'),
        'restructuring' => array('restructuring', 'turnaround', 'distressed', 'bankruptcy', 'chapter 11'),

        // Real Estate
        'real_estate' => array('real estate', 'property', 'reit', 'cre', 'commercial real estate'),

        // Sectors
        'technology' => array('technology', 'tech', 'saas', 'software', 'fintech', 'tmt'),
        'healthcare' => array('healthcare', 'life sciences', 'pharma', 'biotech', 'medtech'),
        'infrastructure' => array('infrastructure', 'infra', 'energy', 'utilities', 'renewables'),
        'consumer' => array('consumer', 'retail', 'cpg', 'fmcg', 'consumer goods'),
        'industrials' => array('industrials', 'manufacturing', 'industrial', 'b2b'),
        'financial_services' => array('financial services', 'fsg', 'insurance', 'banking', 'finserv'),

        // Skills & Tools
        'excel' => array('excel', 'advanced excel', 'vba', 'macros'),
        'powerpoint' => array('powerpoint', 'presentation', 'pitchbook', 'pitch book'),
        'bloomberg' => array('bloomberg', 'bloomberg terminal', 'factset', 'capital iq', 'pitchbook'),
        'python' => array('python', 'sql', 'data analysis', 'programming'),

        // Qualifications
        'cfa' => array('cfa', 'chartered financial analyst'),
        'mba' => array('mba', 'master of business'),
        'aca' => array('aca', 'acca', 'cpa', 'qualified accountant'),

        // Soft Skills
        'client_management' => array('client management', 'client facing', 'stakeholder', 'relationship management'),
        'team_leadership' => array('team leadership', 'team lead', 'people management', 'manage team', 'direct reports'),
        'communication' => array('communication', 'presentation skills', 'written communication'),
    );

    /**
     * Industry mappings for matching
     */
    private static $industry_mappings = array(
        'private_equity' => array('private equity', 'pe', 'buyout', 'growth equity'),
        'venture_capital' => array('venture capital', 'vc', 'early stage', 'startup'),
        'investment_banking' => array('investment banking', 'ib', 'ibd', 'advisory'),
        'hedge_fund' => array('hedge fund', 'hf', 'long/short', 'macro'),
        'asset_management' => array('asset management', 'am', 'mutual fund', 'etf'),
        'corporate_finance' => array('corporate finance', 'fp&a', 'treasury', 'corporate development'),
        'consulting' => array('consulting', 'strategy consulting', 'management consulting'),
        'real_estate_pe' => array('real estate private equity', 'repe', 'real estate fund'),
        'infrastructure_pe' => array('infrastructure fund', 'infra fund', 'infrastructure pe'),
        'credit_fund' => array('credit fund', 'direct lending', 'private credit', 'mezzanine'),
    );

    /**
     * Location mappings for matching
     */
    private static $location_mappings = array(
        'london' => array('london', 'uk', 'united kingdom', 'britain'),
        'new_york' => array('new york', 'nyc', 'manhattan'),
        'hong_kong' => array('hong kong', 'hk'),
        'singapore' => array('singapore', 'sg'),
        'dubai' => array('dubai', 'uae', 'abu dhabi', 'private equity'),
        'paris' => array('paris', 'france'),
        'frankfurt' => array('frankfurt', 'germany'),
        'zurich' => array('zurich', 'switzerland', 'geneva'),
        'sydney' => array('sydney', 'australia', 'melbourne'),
        'toronto' => array('toronto', 'canada'),
        'remote' => array('remote', 'work from home', 'wfh', 'hybrid'),
    );

    /**
     * Level/seniority mappings
     */
    private static $level_mappings = array(
        'analyst' => array('analyst', 'junior', 'graduate', 'entry level', '0-2 years'),
        'associate' => array('associate', 'senior analyst', '2-4 years', '3-5 years'),
        'vp' => array('vp', 'vice president', 'senior associate', '4-7 years', '5-8 years'),
        'director' => array('director', 'principal', 'senior vp', '7-10 years', '8-12 years'),
        'md' => array('md', 'managing director', 'partner', 'senior director', '10+ years', '12+ years'),
    );

    /**
     * Detect skills from text
     *
     * @param string $text The brief/job text to analyze
     * @return array Array of detected skill keys
     */
    public static function detect_skills_from_text($text) {
        if (empty($text)) {
            return array();
        }

        $text = strtolower($text);
        $detected = array();

        foreach (self::$skills_library as $skill_key => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, strtolower($keyword)) !== false) {
                    $detected[$skill_key] = true;
                    break;
                }
            }
        }

        return array_keys($detected);
    }

    /**
     * Detect industry from text
     *
     * @param string $text The brief text
     * @return array Array of detected industry keys
     */
    public static function detect_industry_from_text($text) {
        if (empty($text)) {
            return array();
        }

        $text = strtolower($text);
        $detected = array();

        foreach (self::$industry_mappings as $industry_key => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, strtolower($keyword)) !== false) {
                    $detected[$industry_key] = true;
                    break;
                }
            }
        }

        return array_keys($detected);
    }

    /**
     * Detect location from text
     *
     * @param string $text The brief text or location field
     * @return array Array of detected location keys
     */
    public static function detect_location_from_text($text) {
        if (empty($text)) {
            return array();
        }

        $text = strtolower($text);
        $detected = array();

        foreach (self::$location_mappings as $location_key => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, strtolower($keyword)) !== false) {
                    $detected[$location_key] = true;
                    break;
                }
            }
        }

        return array_keys($detected);
    }

    /**
     * Detect level/seniority from text
     *
     * @param string $text The brief text
     * @return array Array of detected level keys
     */
    public static function detect_level_from_text($text) {
        if (empty($text)) {
            return array();
        }

        $text = strtolower($text);
        $detected = array();

        foreach (self::$level_mappings as $level_key => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, strtolower($keyword)) !== false) {
                    $detected[$level_key] = true;
                    break;
                }
            }
        }

        return array_keys($detected);
    }

    /**
     * Get user's matching preferences
     *
     * @param int $user_id
     * @return array
     */
    public static function get_user_preferences($user_id) {
        $prefs = get_user_meta($user_id, 'sffc_matching_preferences', true);

        if (!is_array($prefs)) {
            return array(
                'preferred_industries' => array(),
                'career_stage' => '',
                'target_role_level' => '',
                'preferred_locations' => array(),
                'work_environment' => '',
                'salary_min' => '',
                'salary_max' => '',
                'skills' => array(),
                'years_experience' => '',
                'keywords' => array(),
            );
        }

        return $prefs;
    }

    /**
     * Calculate match score between user and brief
     *
     * Scoring breakdown:
     * - Keywords match: 30 points max
     * - Industry match: 25 points max
     * - Location match: 20 points max
     * - Level/Experience match: 15 points max
     * - Skills overlap: 10 points max
     *
     * @param int $user_id
     * @param object $brief Brief object from database
     * @return array ['score' => int, 'breakdown' => array]
     */
    public static function calculate_match_score($user_id, $brief) {
        $user_prefs = self::get_user_preferences($user_id);

        $score = 0;
        $breakdown = array(
            'keywords' => 0,
            'industry' => 0,
            'location' => 0,
            'level' => 0,
            'skills' => 0,
        );

        // Combine brief text for analysis
        $brief_text = $brief->title . ' ' . $brief->brief . ' ' . $brief->sector;

        // 1. Keywords match (30 points max)
        $breakdown['keywords'] = self::score_keywords($user_prefs, $brief_text);
        $score += $breakdown['keywords'];

        // 2. Industry match (25 points max)
        $breakdown['industry'] = self::score_industry($user_prefs, $brief_text, $brief->sector);
        $score += $breakdown['industry'];

        // 3. Location match (20 points max)
        $breakdown['location'] = self::score_location($user_prefs, $brief->location);
        $score += $breakdown['location'];

        // 4. Level/Experience match (15 points max)
        $breakdown['level'] = self::score_level($user_prefs, $brief_text);
        $score += $breakdown['level'];

        // 5. Skills overlap (10 points max)
        $breakdown['skills'] = self::score_skills($user_prefs, $brief);
        $score += $breakdown['skills'];

        // Apply brief's match weight modifier if set
        if (!empty($brief->match_weight) && $brief->match_weight != 50) {
            $weight_modifier = $brief->match_weight / 50; // 1.0 at 50, 2.0 at 100, 0.5 at 25
            $score = min(100, round($score * $weight_modifier));
        }

        return array(
            'score' => min(100, max(0, $score)),
            'breakdown' => $breakdown,
        );
    }

    /**
     * Score keywords match
     *
     * @param array $user_prefs
     * @param string $brief_text
     * @return int Score 0-30
     */
    private static function score_keywords($user_prefs, $brief_text) {
        $keywords = isset($user_prefs['keywords']) ? $user_prefs['keywords'] : array();

        if (empty($keywords) || !is_array($keywords)) {
            // No keywords set - give partial score based on general relevance
            return 10;
        }

        $brief_text_lower = strtolower($brief_text);
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (!empty($keyword) && strpos($brief_text_lower, strtolower($keyword)) !== false) {
                $matches++;
            }
        }

        if (count($keywords) === 0) {
            return 10;
        }

        $match_ratio = $matches / count($keywords);
        return round($match_ratio * 30);
    }

    /**
     * Score industry match
     *
     * @param array $user_prefs
     * @param string $brief_text
     * @param string $brief_sector
     * @return int Score 0-25
     */
    private static function score_industry($user_prefs, $brief_text, $brief_sector) {
        $preferred_industries = isset($user_prefs['preferred_industries']) ? $user_prefs['preferred_industries'] : array();

        if (empty($preferred_industries) || !is_array($preferred_industries)) {
            // No preference - give partial score
            return 10;
        }

        // Detect industries in brief
        $brief_industries = self::detect_industry_from_text($brief_text . ' ' . $brief_sector);

        if (empty($brief_industries)) {
            return 5;
        }

        // Check for overlap
        $overlap = array_intersect($preferred_industries, $brief_industries);

        if (!empty($overlap)) {
            // Direct match
            return 25;
        }

        // Check for partial/related match
        foreach ($preferred_industries as $pref_industry) {
            $pref_industry_lower = strtolower(str_replace('_', ' ', $pref_industry));
            if (strpos(strtolower($brief_text . ' ' . $brief_sector), $pref_industry_lower) !== false) {
                return 20;
            }
        }

        return 5;
    }

    /**
     * Score location match
     *
     * @param array $user_prefs
     * @param string $brief_location
     * @return int Score 0-20
     */
    private static function score_location($user_prefs, $brief_location) {
        $preferred_locations = isset($user_prefs['preferred_locations']) ? $user_prefs['preferred_locations'] : array();
        $work_environment = isset($user_prefs['work_environment']) ? $user_prefs['work_environment'] : '';

        if (empty($preferred_locations) || !is_array($preferred_locations)) {
            // No preference - give partial score
            return 8;
        }

        // Detect locations in brief
        $brief_locations = self::detect_location_from_text($brief_location);

        // Check for remote work
        if ($work_environment === 'remote' && in_array('remote', $brief_locations)) {
            return 20;
        }

        // Check for location overlap
        $overlap = array_intersect($preferred_locations, $brief_locations);

        if (!empty($overlap)) {
            return 20;
        }

        // Check for partial match in location text
        foreach ($preferred_locations as $pref_loc) {
            $pref_loc_lower = strtolower(str_replace('_', ' ', $pref_loc));
            if (strpos(strtolower($brief_location), $pref_loc_lower) !== false) {
                return 15;
            }
        }

        return 3;
    }

    /**
     * Score level/experience match
     *
     * @param array $user_prefs
     * @param string $brief_text
     * @return int Score 0-15
     */
    private static function score_level($user_prefs, $brief_text) {
        $career_stage = isset($user_prefs['career_stage']) ? $user_prefs['career_stage'] : '';
        $target_level = isset($user_prefs['target_role_level']) ? $user_prefs['target_role_level'] : '';
        $years_exp = isset($user_prefs['years_experience']) ? $user_prefs['years_experience'] : '';

        if (empty($career_stage) && empty($target_level)) {
            // No preference - give partial score
            return 6;
        }

        // Detect levels in brief
        $brief_levels = self::detect_level_from_text($brief_text);

        if (empty($brief_levels)) {
            return 5;
        }

        // Map career stage to levels
        $stage_to_levels = array(
            'early-career' => array('analyst', 'associate'),
            'mid-career' => array('associate', 'vp'),
            'senior' => array('vp', 'director', 'md'),
            'executive' => array('director', 'md'),
        );

        // Check target level match
        if (!empty($target_level) && in_array($target_level, $brief_levels)) {
            return 15;
        }

        // Check career stage match
        if (!empty($career_stage) && isset($stage_to_levels[$career_stage])) {
            $expected_levels = $stage_to_levels[$career_stage];
            $overlap = array_intersect($expected_levels, $brief_levels);

            if (!empty($overlap)) {
                return 12;
            }
        }

        return 4;
    }

    /**
     * Score skills overlap
     *
     * @param array $user_prefs
     * @param object $brief
     * @return int Score 0-10
     */
    private static function score_skills($user_prefs, $brief) {
        $user_skills = isset($user_prefs['skills']) ? $user_prefs['skills'] : array();

        if (empty($user_skills) || !is_array($user_skills)) {
            return 4;
        }

        // Get brief's detected skills
        $brief_skills = array();
        if (!empty($brief->detected_skills)) {
            $brief_skills = json_decode($brief->detected_skills, true);
            if (!is_array($brief_skills)) {
                $brief_skills = array();
            }
        }

        // If no detected skills, detect them now
        if (empty($brief_skills)) {
            $brief_skills = self::detect_skills_from_text($brief->title . ' ' . $brief->brief);
        }

        if (empty($brief_skills)) {
            return 4;
        }

        // Calculate overlap
        $overlap = array_intersect($user_skills, $brief_skills);
        $overlap_ratio = count($overlap) / max(count($user_skills), 1);

        return round($overlap_ratio * 10);
    }

    /**
     * Generate matches for a user against all active briefs
     *
     * @param int $user_id
     * @param int $min_score Minimum score threshold (default 30)
     * @return int Number of matches created
     */
    public static function generate_matches_for_user($user_id, $min_score = 30) {
        global $wpdb;
        $tables = Recruiter_Terminal_DB::get_table_names();

        // Get all active briefs
        $briefs = $wpdb->get_results(
            "SELECT * FROM {$tables['briefs']}
             WHERE status = 'active'
             AND visibility IN ('public', 'matched')
             ORDER BY created_at DESC"
        );

        if (empty($briefs)) {
            return 0;
        }

        $matches_created = 0;

        foreach ($briefs as $brief) {
            $result = self::calculate_match_score($user_id, $brief);

            if ($result['score'] >= $min_score) {
                $upsert_result = Recruiter_Terminal_DB::upsert_user_match(
                    $user_id,
                    $brief->id,
                    $result['score'],
                    $result['breakdown']
                );

                if (!is_wp_error($upsert_result)) {
                    $matches_created++;
                }
            }
        }

        return $matches_created;
    }

    /**
     * Generate matches for a brief against all users with preferences
     *
     * @param int $brief_id
     * @param int $min_score Minimum score threshold (default 30)
     * @return int Number of matches created
     */
    public static function generate_matches_for_brief($brief_id, $min_score = 30) {
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief || $brief->status !== 'active') {
            return 0;
        }

        // Get users with matching preferences
        $users = get_users(array(
            'meta_key' => 'sffc_matching_preferences',
            'meta_compare' => 'EXISTS',
            'fields' => 'ID',
        ));

        if (empty($users)) {
            return 0;
        }

        $matches_created = 0;

        foreach ($users as $user_id) {
            $result = self::calculate_match_score($user_id, $brief);

            if ($result['score'] >= $min_score) {
                $upsert_result = Recruiter_Terminal_DB::upsert_user_match(
                    $user_id,
                    $brief_id,
                    $result['score'],
                    $result['breakdown']
                );

                if (!is_wp_error($upsert_result)) {
                    $matches_created++;
                }
            }
        }

        return $matches_created;
    }

    /**
     * Regenerate all matches (for cron or manual trigger)
     *
     * @param int $min_score Minimum score threshold
     * @return array ['users_processed' => int, 'matches_created' => int]
     */
    public static function regenerate_all_matches($min_score = 30) {
        // Get users with matching preferences
        $users = get_users(array(
            'meta_key' => 'sffc_matching_preferences',
            'meta_compare' => 'EXISTS',
            'fields' => 'ID',
        ));

        $total_matches = 0;

        foreach ($users as $user_id) {
            // Clear existing matches for user
            Recruiter_Terminal_DB::delete_user_matches($user_id);

            // Generate new matches
            $total_matches += self::generate_matches_for_user($user_id, $min_score);
        }

        return array(
            'users_processed' => count($users),
            'matches_created' => $total_matches,
        );
    }

    /**
     * Detect and save skills for a brief
     *
     * @param int $brief_id
     * @return array Detected skills
     */
    public static function detect_and_save_brief_skills($brief_id) {
        $brief = Recruiter_Terminal_DB::get_brief($brief_id);

        if (!$brief) {
            return array();
        }

        $skills = self::detect_skills_from_text($brief->title . ' ' . $brief->brief);

        Recruiter_Terminal_DB::update_brief($brief_id, array(
            'detected_skills' => $skills,
        ));

        return $skills;
    }

    /**
     * Get human-readable skill label
     *
     * @param string $skill_key
     * @return string
     */
    public static function get_skill_label($skill_key) {
        $labels = array(
            'financial_modeling' => 'Financial Modeling',
            'valuation' => 'Valuation',
            'lbo_modeling' => 'LBO Modeling',
            'merger_model' => 'Merger Modeling',
            'ma_analysis' => 'M&A Analysis',
            'due_diligence' => 'Due Diligence',
            'deal_execution' => 'Deal Execution',
            'investment_analysis' => 'Investment Analysis',
            'portfolio_management' => 'Portfolio Management',
            'asset_management' => 'Asset Management',
            'fundraising' => 'Fundraising',
            'investor_relations' => 'Investor Relations',
            'credit_analysis' => 'Credit Analysis',
            'restructuring' => 'Restructuring',
            'real_estate' => 'Real Estate',
            'technology' => 'Technology',
            'healthcare' => 'Healthcare',
            'infrastructure' => 'Infrastructure',
            'consumer' => 'Consumer',
            'industrials' => 'Industrials',
            'financial_services' => 'Financial Services',
            'excel' => 'Advanced Excel',
            'powerpoint' => 'PowerPoint/Presentations',
            'bloomberg' => 'Bloomberg/CapIQ',
            'python' => 'Python/SQL',
            'cfa' => 'CFA',
            'mba' => 'MBA',
            'aca' => 'ACA/CPA',
            'client_management' => 'Client Management',
            'team_leadership' => 'Team Leadership',
            'communication' => 'Communication Skills',
        );

        return isset($labels[$skill_key]) ? $labels[$skill_key] : ucwords(str_replace('_', ' ', $skill_key));
    }

    /**
     * Get all available skills for selection
     *
     * @return array
     */
    public static function get_available_skills() {
        $skills = array();

        foreach (array_keys(self::$skills_library) as $skill_key) {
            $skills[$skill_key] = self::get_skill_label($skill_key);
        }

        return $skills;
    }
}
