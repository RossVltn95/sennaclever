<?php
/**
 * Comeet Job Fetcher
 * 
 * Fetches jobs from Comeet-powered career portals by extracting JavaScript data objects
 * Handles COMPANY_POSITIONS_DATA and similar embedded job data structures
 * 
 * @package SennaFinanceCareer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Comeet_Job_Fetcher {
    
    private static $instance = null;
    
    /**
     * Comeet company configurations
     */
    private $comeet_instances = [
        'cbd' => [
            'company_code' => 'cbd',
            'base_url' => 'https://www.coeet.com/jobs/cbd',
            'name' => 'Commercial Bank of Dubai (CBD)',
            'country' => 'UAE',
            'category' => 'UAE Banking',
            'quality' => 'premium',
            'status' => 'active',
            'job_page_id' => '14.007',
            'data_variables' => ['COMPANY_POSITIONS_DATA', 'COMPANY_DATA']
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
        return $this->comeet_instances;
    }
    
    /**
     * Fetch jobs from all active Comeet instances
     */
    public function fetch_all_jobs($limit = 50) {
        $all_jobs = [];
        
        foreach ($this->comeet_instances as $company_code => $config) {
            if ($config['status'] === 'active') {
                $company_jobs = $this->fetch_company_jobs($company_code, $limit);
                $all_jobs = array_merge($all_jobs, $company_jobs);
            }
        }
        
        return $all_jobs;
    }
    
    /**
     * Fetch jobs from specific Comeet company
     */
    public function fetch_company_jobs($company_code, $limit = 50) {
        if (!isset($this->comeet_instances[$company_code])) {
            return [];
        }
        
        $config = $this->comeet_instances[$company_code];
        $jobs = [];
        
        // Fetch the careers page HTML
        $html = $this->fetch_careers_page_html($config);
        if (!$html) {
            error_log("SFFC: Failed to fetch Comeet careers page for {$company_code}");
            return [];
        }
        
        // Extract job data from JavaScript variables
        $job_data = $this->extract_job_data_from_html($html, $config);
        if (empty($job_data)) {
            error_log("SFFC: No job data found in Comeet page for {$company_code}");
            return [];
        }
        
        // Process each job
        foreach ($job_data as $job) {
            $processed_job = $this->process_comeet_job($job, $config);
            if ($processed_job) {
                $jobs[] = $processed_job;
            }
            
            if (count($jobs) >= $limit) {
                break;
            }
        }
        
        return $jobs;
    }
    
    /**
     * Fetch careers page HTML content
     */
    private function fetch_careers_page_html($config) {
        $url = $this->build_careers_page_url($config);
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log("SFFC: Comeet fetch error: " . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("SFFC: Comeet HTTP {$status_code} response");
            return false;
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Extract job data from JavaScript variables in HTML
     */
    private function extract_job_data_from_html($html, $config) {
        $job_data = [];
        
        // Look for COMPANY_POSITIONS_DATA variable
        if (preg_match('/window\.COMPANY_POSITIONS_DATA\s*=\s*({.*?});/s', $html, $matches)) {
            $positions_json = $matches[1];
            $positions_data = json_decode($positions_json, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($positions_data['positions'])) {
                $job_data = $positions_data['positions'];
                error_log("SFFC: Found " . count($job_data) . " Comeet positions via COMPANY_POSITIONS_DATA");
            }
        }
        
        // Fallback: Look for other common Comeet data variables
        if (empty($job_data)) {
            foreach ($config['data_variables'] as $var_name) {
                $pattern = '/window\.' . preg_quote($var_name) . '\s*=\s*({.*?});/s';
                if (preg_match($pattern, $html, $matches)) {
                    $data = json_decode($matches[1], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Try different data structures
                        if (isset($data['positions'])) {
                            $job_data = $data['positions'];
                        } elseif (isset($data['jobs'])) {
                            $job_data = $data['jobs'];
                        } elseif (is_array($data) && !empty($data)) {
                            $job_data = $data;
                        }
                        
                        if (!empty($job_data)) {
                            error_log("SFFC: Found job data via {$var_name}");
                            break;
                        }
                    }
                }
            }
        }
        
        return $job_data;
    }
    
    /**
     * Process a single Comeet job
     */
    private function process_comeet_job($job, $config) {
        // Ensure we have required fields
        if (empty($job['name']) && empty($job['title'])) {
            return false;
        }
        
        $title = $job['name'] ?? $job['title'] ?? 'Untitled Position';
        $job_id = $job['id'] ?? $job['uid'] ?? uniqid('comeet_');
        
        return [
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'url' => $this->build_job_url($config, $job),
            'job_id' => $job_id,
            'company' => $config['name'],
            'company_code' => $config['company_code'],
            'location' => $this->extract_job_location($job, $config),
            'department' => $job['department'] ?? $job['team'] ?? '',
            'employment_type' => $this->extract_employment_type($job),
            'experience_level' => $job['experience_level'] ?? '',
            'workplace_type' => $job['workplace_type'] ?? 'On-site',
            'posted_date' => $this->extract_posted_date($job),
            'description' => $this->extract_job_description($job),
            'requirements' => $this->extract_job_requirements($job),
            'contact_email' => $job['contact_email'] ?? '',
            'source' => 'comeet',
            'source_type' => 'company',
            'category' => $config['category'],
            'quality' => $config['quality']
        ];
    }
    
    /**
     * Extract job location
     */
    private function extract_job_location($job, $config) {
        // Try multiple location field variations
        $location_fields = ['location', 'office', 'city', 'address'];
        
        foreach ($location_fields as $field) {
            if (isset($job[$field]) && !empty($job[$field])) {
                if (is_array($job[$field])) {
                    return implode(', ', array_filter($job[$field]));
                } else {
                    return $job[$field];
                }
            }
        }
        
        // Fallback to company country
        return $config['country'];
    }
    
    /**
     * Extract employment type
     */
    private function extract_employment_type($job) {
        $type_fields = ['employment_type', 'type', 'job_type', 'position_type'];
        
        foreach ($type_fields as $field) {
            if (isset($job[$field]) && !empty($job[$field])) {
                return $this->normalize_employment_type($job[$field]);
            }
        }
        
        return 'Full-time'; // Default
    }
    
    /**
     * Normalize employment type
     */
    private function normalize_employment_type($type) {
        $type = strtolower($type);
        
        $mappings = [
            'full-time' => 'Full-time',
            'fulltime' => 'Full-time',
            'part-time' => 'Part-time',
            'parttime' => 'Part-time',
            'contract' => 'Contract',
            'contractor' => 'Contract',
            'temporary' => 'Temporary',
            'intern' => 'Internship',
            'internship' => 'Internship'
        ];
        
        return $mappings[$type] ?? ucfirst($type);
    }
    
    /**
     * Extract posted date
     */
    private function extract_posted_date($job) {
        $date_fields = ['posted_date', 'created_at', 'published_at', 'date_posted'];
        
        foreach ($date_fields as $field) {
            if (isset($job[$field]) && !empty($job[$field])) {
                $timestamp = strtotime($job[$field]);
                if ($timestamp) {
                    return date('Y-m-d', $timestamp);
                }
            }
        }
        
        return date('Y-m-d'); // Default to today
    }
    
    /**
     * Extract job description
     */
    private function extract_job_description($job) {
        $desc_fields = ['description', 'details', 'summary', 'about'];
        
        foreach ($desc_fields as $field) {
            if (isset($job[$field]) && !empty($job[$field])) {
                $description = $job[$field];
                if (is_array($description)) {
                    $description = implode(' ', $description);
                }
                return wp_trim_words(strip_tags($description), 50);
            }
        }
        
        return 'Job details available on application page.';
    }
    
    /**
     * Extract job requirements
     */
    private function extract_job_requirements($job) {
        $req_fields = ['requirements', 'qualifications', 'skills', 'experience_required'];
        
        foreach ($req_fields as $field) {
            if (isset($job[$field]) && !empty($job[$field])) {
                $requirements = $job[$field];
                if (is_array($requirements)) {
                    $requirements = implode(', ', $requirements);
                }
                return wp_trim_words(strip_tags($requirements), 30);
            }
        }
        
        return '';
    }
    
    /**
     * Build careers page URL
     */
    private function build_careers_page_url($config) {
        return $config['base_url'] . '/' . $config['job_page_id'];
    }
    
    /**
     * Build job application URL
     */
    private function build_job_url($config, $job) {
        $job_id = $job['id'] ?? $job['uid'] ?? '';
        if (empty($job_id)) {
            return $config['base_url'];
        }
        
        return $config['base_url'] . '/' . $job_id;
    }
    
    /**
     * Test connection to a Comeet instance
     */
    public function test_connection($company_code) {
        if (!isset($this->comeet_instances[$company_code])) {
            return [
                'success' => false,
                'error' => 'Company not configured',
                'company' => $company_code
            ];
        }
        
        $config = $this->comeet_instances[$company_code];
        $jobs = $this->fetch_company_jobs($company_code, 5);
        
        if (!empty($jobs)) {
            $sample_job = $jobs[0];
            return [
                'success' => true,
                'message' => 'Successfully fetched ' . count($jobs) . ' jobs',
                'company' => $config['name'],
                'sample_job' => [
                    'title' => $sample_job['title'],
                    'department' => $sample_job['department'],
                    'location' => $sample_job['location']
                ],
                'total_jobs' => count($jobs)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'No jobs found or failed to parse job data',
                'company' => $config['name']
            ];
        }
    }
    
    /**
     * Add new Comeet company configuration
     */
    public function add_company($company_code, $config) {
        $this->comeet_instances[$company_code] = array_merge([
            'status' => 'active',
            'data_variables' => ['COMPANY_POSITIONS_DATA', 'COMPANY_DATA'],
            'quality' => 'standard'
        ], $config);
        
        // Save to database
        update_option('sffc_custom_comeet_companies', $this->comeet_instances);
        
        return true;
    }
}

// Initialize the fetcher
SFFC_Comeet_Job_Fetcher::get_instance();