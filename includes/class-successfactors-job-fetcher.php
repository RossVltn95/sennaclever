<?php
/**
 * SuccessFactors Job Fetcher
 * 
 * Fetches jobs from SAP SuccessFactors career portals by scanning job ID ranges
 * Flexible range detection with automatic expansion and gap handling
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_SuccessFactors_Job_Fetcher {
    
    private static $instance = null;
    
    /**
     * SuccessFactors company configurations
     */
    private $successfactors_instances = [
        'thecommerc' => [
            'base_url' => 'https://career2.successfactors.eu/career',
            'company_code' => 'thecommerc',
            'name' => 'The Commercial Bank',
            'country' => 'Qatar',
            'category' => 'Qatar Banking',
            'quality' => 'premium',
            'known_job_ids' => [6147, 7100, 7339, 7461, 7465, 7487, 7492, 7497],
            'scan_ranges' => [
                [4000, 8000]    // Flexible range to catch all job postings
            ],
            'status' => 'active'
        ]
    ];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get instances for admin display
     */
    public function get_instances() {
        return $this->successfactors_instances;
    }
    
    /**
     * Fetch jobs from all active SuccessFactors instances
     */
    public function fetch_all_jobs($limit = 50) {
        $all_jobs = [];
        
        foreach ($this->successfactors_instances as $company_code => $config) {
            if ($config['status'] === 'active') {
                $company_jobs = $this->fetch_company_jobs($company_code, $limit);
                $all_jobs = array_merge($all_jobs, $company_jobs);
            }
        }
        
        return $all_jobs;
    }
    
    /**
     * Fetch jobs from specific company with flexible range scanning
     */
    public function fetch_company_jobs($company_code, $limit = 50) {
        if (!isset($this->successfactors_instances[$company_code])) {
            return [];
        }
        
        $config = $this->successfactors_instances[$company_code];
        $jobs = [];
        
        // Start with known working job IDs
        $working_ids = $this->scan_known_job_ids($config);
        
        // Expand ranges based on successful finds
        $expanded_ranges = $this->calculate_flexible_ranges($config, $working_ids);
        
        // Scan the flexible ranges
        foreach ($expanded_ranges as $range) {
            $range_jobs = $this->scan_job_range($config, $range[0], $range[1], $limit);
            $jobs = array_merge($jobs, $range_jobs);
            
            if (count($jobs) >= $limit) {
                break;
            }
        }
        
        // Update configuration with new findings
        $this->update_working_ranges($company_code, $working_ids);
        
        return array_slice($jobs, 0, $limit);
    }
    
    /**
     * Scan known job IDs to verify which are still active
     */
    private function scan_known_job_ids($config) {
        $working_ids = [];
        
        foreach ($config['known_job_ids'] as $job_id) {
            $job_data = $this->fetch_single_job($config, $job_id);
            if ($job_data) {
                $working_ids[] = $job_id;
            }
        }
        
        return $working_ids;
    }
    
    /**
     * Calculate flexible scan ranges based on working job IDs
     */
    private function calculate_flexible_ranges($config, $working_ids) {
        if (empty($working_ids)) {
            // Fallback to configured ranges if no working IDs found
            return $config['scan_ranges'];
        }
        
        sort($working_ids);
        $min_id = min($working_ids);
        $max_id = max($working_ids);
        
        // Create flexible ranges with buffer zones
        $buffer = 100; // Scan 100 IDs beyond known range
        $gap_threshold = 200; // Split into separate ranges if gap > 200
        
        $ranges = [];
        $current_start = max(1, $min_id - $buffer);
        
        for ($i = 0; $i < count($working_ids) - 1; $i++) {
            $current_id = $working_ids[$i];
            $next_id = $working_ids[$i + 1];
            
            // If there's a large gap, end current range and start new one
            if ($next_id - $current_id > $gap_threshold) {
                $ranges[] = [$current_start, $current_id + $buffer];
                $current_start = max(1, $next_id - $buffer);
            }
        }
        
        // Add final range
        $ranges[] = [$current_start, $max_id + $buffer];
        
        return $ranges;
    }
    
    /**
     * Scan a specific range of job IDs
     */
    private function scan_job_range($config, $start_id, $end_id, $max_jobs = 50) {
        $jobs = [];
        $found_count = 0;
        $consecutive_failures = 0;
        $max_consecutive_failures = 100; // Stop if 100 consecutive IDs have no jobs (larger range needs higher threshold)
        
        for ($job_id = $start_id; $job_id <= $end_id && $found_count < $max_jobs; $job_id++) {
            $job_data = $this->fetch_single_job($config, $job_id);
            
            if ($job_data) {
                $jobs[] = $job_data;
                $found_count++;
                $consecutive_failures = 0;
                
                // Log successful find
                error_log("SFFC: Found SuccessFactors job {$job_id}: " . $job_data['title']);
            } else {
                $consecutive_failures++;
                
                // Stop scanning if too many consecutive failures
                if ($consecutive_failures >= $max_consecutive_failures) {
                    error_log("SFFC: Stopping range scan at {$job_id} due to {$consecutive_failures} consecutive failures");
                    break;
                }
            }
            
            // Add small delay to be respectful to the server
            usleep(250000); // 250ms delay
        }
        
        return $jobs;
    }
    
    /**
     * Fetch a single job by ID
     */
    private function fetch_single_job($config, $job_id) {
        $url = $this->build_job_url($config, $job_id);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'SFFC Job Fetcher 1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $html = wp_remote_retrieve_body($response);
        $job_data = $this->parse_job_html($html, $config, $job_id);
        
        return $job_data;
    }
    
    /**
     * Build job URL for SuccessFactors
     */
    private function build_job_url($config, $job_id) {
        return $config['base_url'] . '?' . http_build_query([
            'career_ns' => 'job_listing',
            'company' => $config['company_code'],
            'navBarLevel' => 'JOB_SEARCH',
            'rcm_site_locale' => 'en_GB',
            'career_job_req_id' => $job_id,
            'selected_lang' => 'en_US'
        ]);
    }
    
    /**
     * Parse job HTML to extract job data
     */
    private function parse_job_html($html, $config, $job_id) {
        // Extract job title from page title
        if (preg_match('/<title>Career Opportunities:\s*([^(]+?)\s*\(' . $job_id . '\)[^<]*<\/title>/', $html, $matches)) {
            $title = trim($matches[1]);
            
            // Skip if no actual job title (just "Career Opportunities")
            if (empty($title) || $title === 'Career Opportunities') {
                return false;
            }
            
            // Extract additional job details
            $location = $this->extract_job_location($html);
            $department = $this->extract_job_department($html);
            $posted_date = $this->extract_posted_date($html);
            $description = $this->extract_job_description($html);
            
            return [
                'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                'url' => $this->build_job_url($config, $job_id),
                'job_id' => $job_id,
                'company' => $config['name'],
                'company_code' => $config['company_code'],
                'location' => $location ?: $config['country'],
                'department' => $department,
                'posted_date' => $posted_date ?: date('Y-m-d'),
                'description' => $description,
                'source' => 'successfactors',
                'source_type' => 'company',
                'category' => $config['category'],
                'quality' => $config['quality']
            ];
        }
        
        return false;
    }
    
    /**
     * Extract job location from HTML
     */
    private function extract_job_location($html) {
        // Look for location patterns in SuccessFactors
        if (preg_match('/location["\']?\s*[:=]\s*["\']([^"\']+)["\']/', $html, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/Location[^<]*<[^>]*>([^<]+)/', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        return '';
    }
    
    /**
     * Extract job department from HTML
     */
    private function extract_job_department($html) {
        // Look for department patterns
        if (preg_match('/department["\']?\s*[:=]\s*["\']([^"\']+)["\']/', $html, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/Department[^<]*<[^>]*>([^<]+)/', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        return '';
    }
    
    /**
     * Extract posted date from HTML
     */
    private function extract_posted_date($html) {
        // Look for posted date patterns
        if (preg_match('/posted["\']?\s*[:=]\s*["\']([^"\']+)["\']/', $html, $matches)) {
            $date = trim($matches[1]);
            return date('Y-m-d', strtotime($date));
        }
        
        if (preg_match('/Posted[^<]*<[^>]*>([^<]+)/', $html, $matches)) {
            $date = trim(strip_tags($matches[1]));
            return date('Y-m-d', strtotime($date));
        }
        
        return date('Y-m-d');
    }
    
    /**
     * Extract job description from HTML
     */
    private function extract_job_description($html) {
        // Look for job description content
        if (preg_match('/<div[^>]*job[^>]*description[^>]*>(.*?)<\/div>/is', $html, $matches)) {
            return wp_trim_words(strip_tags($matches[1]), 50);
        }
        
        // Fallback to extracting from general content areas
        if (preg_match('/<div[^>]*content[^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $content = strip_tags($matches[1]);
            if (strlen($content) > 100) {
                return wp_trim_words($content, 50);
            }
        }
        
        return 'Job details available on application page.';
    }
    
    /**
     * Update working ranges based on successful job finds
     */
    private function update_working_ranges($company_code, $working_ids) {
        if (!empty($working_ids)) {
            // Update the configuration with new working job IDs
            $this->successfactors_instances[$company_code]['known_job_ids'] = array_unique(
                array_merge(
                    $this->successfactors_instances[$company_code]['known_job_ids'],
                    $working_ids
                )
            );
            
            // Optionally save to database for persistence
            update_option('sffc_successfactors_working_ids_' . $company_code, $working_ids);
        }
    }
    
    /**
     * Test connection to a SuccessFactors instance
     */
    public function test_connection($company_code) {
        if (!isset($this->successfactors_instances[$company_code])) {
            return [
                'success' => false,
                'error' => 'Company not configured',
                'company' => $company_code
            ];
        }
        
        $config = $this->successfactors_instances[$company_code];
        
        // Test with the first known job ID
        $test_job_id = $config['known_job_ids'][0] ?? 7492;
        $job_data = $this->fetch_single_job($config, $test_job_id);
        
        if ($job_data) {
            return [
                'success' => true,
                'message' => 'Successfully fetched job: ' . $job_data['title'],
                'company' => $config['name'],
                'test_job_id' => $test_job_id,
                'jobs_found' => 1
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to fetch test job ID: ' . $test_job_id,
                'company' => $config['name']
            ];
        }
    }
    
    /**
     * Add new SuccessFactors company configuration
     */
    public function add_company($company_code, $config) {
        $this->successfactors_instances[$company_code] = array_merge([
            'status' => 'active',
            'known_job_ids' => [],
            'scan_ranges' => [[1000, 2000]]
        ], $config);
        
        // Save to database
        update_option('sffc_custom_successfactors_companies', $this->successfactors_instances);
        
        return true;
    }
}

// Initialize the fetcher
SFFC_SuccessFactors_Job_Fetcher::get_instance();