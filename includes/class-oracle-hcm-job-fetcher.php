<?php
/**
 * Oracle HCM Job Fetcher
 * 
 * Fetches jobs from Oracle Human Capital Management (HCM) Cloud career portals
 * Uses REST API to retrieve structured job data from Oracle Fusion HCM
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Oracle_HCM_Job_Fetcher {
    
    private static $instance = null;
    
    /**
     * Oracle HCM company configurations
     */
    private $oracle_hcm_instances = [
        'adib' => [
            'company_code' => 'adib',
            'api_base_url' => 'https://hciq.fa.em2.oraclecloud.com/hcmRestApi/resources/latest',
            'site_number' => 'CX_1',
            'name' => 'Abu Dhabi Islamic Bank (ADIB)',
            'country' => 'UAE',
            'category' => 'UAE Banking',
            'quality' => 'premium',
            'status' => 'active',
            'api_version' => '11.13.18.05'
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
        return $this->oracle_hcm_instances;
    }
    
    /**
     * Fetch jobs from all active Oracle HCM instances
     */
    public function fetch_all_jobs($limit = 50) {
        $all_jobs = [];
        
        foreach ($this->oracle_hcm_instances as $company_code => $config) {
            if ($config['status'] === 'active') {
                $company_jobs = $this->fetch_company_jobs($company_code, $limit);
                $all_jobs = array_merge($all_jobs, $company_jobs);
            }
        }
        
        return $all_jobs;
    }
    
    /**
     * Fetch jobs from specific Oracle HCM company
     */
    public function fetch_company_jobs($company_code, $limit = 50) {
        if (!isset($this->oracle_hcm_instances[$company_code])) {
            return [];
        }
        
        $config = $this->oracle_hcm_instances[$company_code];
        $jobs = [];
        
        // Fetch job search results from Oracle HCM API
        $search_results = $this->fetch_job_search_api($config, $limit);
        if (empty($search_results)) {
            error_log("SFFC: No jobs found from Oracle HCM API for {$company_code}");
            return [];
        }
        
        // Process each job from the requisition list
        if (isset($search_results['requisitionList']) && is_array($search_results['requisitionList'])) {
            foreach ($search_results['requisitionList'] as $job_data) {
                $processed_job = $this->process_oracle_hcm_job($job_data, $config);
                if ($processed_job) {
                    $jobs[] = $processed_job;
                }
                
                if (count($jobs) >= $limit) {
                    break;
                }
            }
        }
        
        return $jobs;
    }
    
    /**
     * Fetch job search results from Oracle HCM REST API
     */
    private function fetch_job_search_api($config, $limit = 50) {
        $api_url = $config['api_base_url'] . '/recruitingCEJobRequisitions';
        $query_params = [
            'finder' => 'findReqs;siteNumber=' . $config['site_number'],
            'limit' => $limit,
            'expand' => 'requisitionList'
        ];
        
        $url = $api_url . '?' . http_build_query($query_params);
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'SFFC Oracle HCM Fetcher 1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log("SFFC: Oracle HCM API error: " . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("SFFC: Oracle HCM API HTTP {$status_code} response");
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SFFC: Oracle HCM API JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        // Return the first search result item which contains requisitionList
        return isset($data['items']) && !empty($data['items']) ? $data['items'][0] : [];
    }
    
    /**
     * Process a single Oracle HCM job
     */
    private function process_oracle_hcm_job($job, $config) {
        // Ensure we have required fields
        if (empty($job['Title']) || empty($job['Id'])) {
            return false;
        }
        
        $title = $job['Title'];
        $job_id = $job['Id'];
        
        return [
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'url' => $this->build_job_application_url($config, $job_id),
            'job_id' => $job_id,
            'company' => $config['name'],
            'company_code' => $config['company_code'],
            'location' => $this->extract_job_location($job, $config),
            'department' => $job['Department'] ?? '',
            'organization' => $job['Organization'] ?? '',
            'business_unit' => $job['BusinessUnit'] ?? '',
            'posted_date' => $this->extract_posted_date($job),
            'posting_end_date' => $this->extract_posting_end_date($job),
            'workplace_type' => $this->extract_workplace_type($job),
            'job_family' => $job['JobFamily'] ?? '',
            'job_function' => $job['JobFunction'] ?? '',
            'hot_job_flag' => $job['HotJobFlag'] ?? false,
            'trending_flag' => $job['TrendingFlag'] ?? false,
            'description' => $this->extract_job_description($job),
            'qualifications' => $job['ExternalQualificationsStr'] ?? '',
            'responsibilities' => $job['ExternalResponsibilitiesStr'] ?? '',
            'source' => 'oracle_hcm',
            'source_type' => 'company',
            'category' => $config['category'],
            'quality' => $config['quality']
        ];
    }
    
    /**
     * Extract job location
     */
    private function extract_job_location($job, $config) {
        // Try primary location first
        if (!empty($job['PrimaryLocation'])) {
            return $job['PrimaryLocation'];
        }
        
        // Fallback to country
        if (!empty($job['PrimaryLocationCountry'])) {
            $country_codes = ['AE' => 'United Arab Emirates', 'US' => 'United States'];
            return $country_codes[$job['PrimaryLocationCountry']] ?? $job['PrimaryLocationCountry'];
        }
        
        // Final fallback to company country
        return $config['country'];
    }
    
    /**
     * Extract posted date
     */
    private function extract_posted_date($job) {
        if (!empty($job['PostedDate'])) {
            return $job['PostedDate'];
        }
        
        return date('Y-m-d'); // Default to today
    }
    
    /**
     * Extract posting end date
     */
    private function extract_posting_end_date($job) {
        return $job['PostingEndDate'] ?? null;
    }
    
    /**
     * Extract workplace type
     */
    private function extract_workplace_type($job) {
        if (!empty($job['WorkplaceType'])) {
            return $job['WorkplaceType'];
        }
        
        if (!empty($job['WorkplaceTypeCode'])) {
            $workplace_mappings = [
                'ORA_ON_SITE' => 'On-site',
                'ORA_REMOTE' => 'Remote',
                'ORA_HYBRID' => 'Hybrid'
            ];
            return $workplace_mappings[$job['WorkplaceTypeCode']] ?? $job['WorkplaceTypeCode'];
        }
        
        return 'On-site'; // Default
    }
    
    /**
     * Extract job description
     */
    private function extract_job_description($job) {
        if (!empty($job['ShortDescriptionStr'])) {
            return wp_trim_words(strip_tags($job['ShortDescriptionStr']), 50);
        }
        
        return 'Job details available on application page.';
    }
    
    /**
     * Build job application URL
     */
    private function build_job_application_url($config, $job_id) {
        // Oracle HCM uses deeplink URLs for job applications
        $base_url = str_replace('/hcmRestApi/resources/latest', '', $config['api_base_url']);
        
        return $base_url . '/hcmUI/CandidateExperience/en/sites/' . $config['site_number'] . '/jobs/preview/' . $job_id;
    }
    
    /**
     * Get job statistics for a company
     */
    public function get_job_statistics($company_code) {
        if (!isset($this->oracle_hcm_instances[$company_code])) {
            return false;
        }
        
        $config = $this->oracle_hcm_instances[$company_code];
        $search_results = $this->fetch_job_search_api($config, 1);
        
        if (!$search_results) {
            return false;
        }
        
        return [
            'total_jobs' => $search_results['TotalJobsCount'] ?? 0,
            'locations' => $this->extract_facet_data($search_results, 'locationsFacet'),
            'work_locations' => $this->extract_facet_data($search_results, 'workLocationsFacet'),
            'organizations' => $this->extract_facet_data($search_results, 'organizationsFacet'),
            'posting_dates' => $this->extract_facet_data($search_results, 'postingDatesFacet'),
            'workplace_types' => $this->extract_facet_data($search_results, 'workplaceTypesFacet')
        ];
    }
    
    /**
     * Extract facet data for filtering/statistics
     */
    private function extract_facet_data($search_results, $facet_name) {
        if (!isset($search_results[$facet_name]) || !is_array($search_results[$facet_name])) {
            return [];
        }
        
        $facet_data = [];
        foreach ($search_results[$facet_name] as $facet) {
            $facet_data[] = [
                'name' => $facet['Name'] ?? '',
                'count' => $facet['TotalCount'] ?? 0
            ];
        }
        
        return $facet_data;
    }
    
    /**
     * Test connection to an Oracle HCM instance
     */
    public function test_connection($company_code) {
        if (!isset($this->oracle_hcm_instances[$company_code])) {
            return [
                'success' => false,
                'error' => 'Company not configured',
                'company' => $company_code
            ];
        }
        
        $config = $this->oracle_hcm_instances[$company_code];
        $jobs = $this->fetch_company_jobs($company_code, 5);
        $stats = $this->get_job_statistics($company_code);
        
        if (!empty($jobs) && $stats) {
            $sample_job = $jobs[0];
            return [
                'success' => true,
                'message' => 'Successfully connected to Oracle HCM API',
                'company' => $config['name'],
                'total_jobs' => $stats['total_jobs'],
                'sample_job' => [
                    'title' => $sample_job['title'],
                    'location' => $sample_job['location'],
                    'posted_date' => $sample_job['posted_date']
                ],
                'api_url' => $config['api_base_url'],
                'site_number' => $config['site_number']
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to fetch jobs from Oracle HCM API',
                'company' => $config['name'],
                'api_url' => $config['api_base_url']
            ];
        }
    }
    
    /**
     * Add new Oracle HCM company configuration
     */
    public function add_company($company_code, $config) {
        $this->oracle_hcm_instances[$company_code] = array_merge([
            'status' => 'active',
            'quality' => 'standard',
            'api_version' => '11.13.18.05'
        ], $config);
        
        // Save to database
        update_option('sffc_custom_oracle_hcm_companies', $this->oracle_hcm_instances);
        
        return true;
    }
}

// Initialize the fetcher
SFFC_Oracle_HCM_Job_Fetcher::get_instance();