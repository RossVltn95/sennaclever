<?php
/**
 * Workday Auto-Detector
 * Automatically detects Workday endpoints and configurations for any company
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Workday_Auto_Detector {
    
    /**
     * Common Workday patterns
     */
    private $common_patterns = [
        'careers_paths' => [
            '/careers',
            '/External',
            '/Global',
            '/Careers',
            '/Campus_Careers',
            '/Experienced-Hires',
            '/Professional',
            '/Public',
            '/All_Jobs',
            '/Lateral',
            '/search'
        ],
        
        'endpoint_patterns' => [
            '/wday/cxs/{company}/{path}/jobs',
            '/wday/cxs/{company}/search/jobs',
            '/api/v1/jobs',
            '/external/jobs'
        ],
        
        'workday_domains' => [
            'wd1.myworkdayjobs.com',
            'wd3.myworkdayjobs.com',
            'wd5.myworkdayjobs.com',
            'wd12.myworkdayjobs.com'
        ]
    ];
    
    /**
     * Auto-detect Workday configuration for a company
     */
    public function detect_configuration($company_name, $base_url = null) {
        $results = [
            'company' => $company_name,
            'detected' => false,
            'configurations' => [],
            'errors' => []
        ];
        
        // If base URL not provided, try common patterns
        if (!$base_url) {
            $base_urls = $this->generate_possible_urls($company_name);
        } else {
            $base_urls = [$base_url];
        }
        
        foreach ($base_urls as $url) {
            $config = $this->test_base_url($url, $company_name);
            if ($config['success']) {
                $results['detected'] = true;
                $results['configurations'][] = $config;
            } else {
                $results['errors'][] = $config['error'];
            }
        }
        
        return $results;
    }
    
    /**
     * Generate possible Workday URLs for a company
     */
    private function generate_possible_urls($company_name) {
        $urls = [];
        $clean_name = $this->clean_company_name($company_name);
        
        foreach ($this->common_patterns['workday_domains'] as $domain) {
            $urls[] = "https://{$clean_name}.{$domain}";
        }
        
        return $urls;
    }
    
    /**
     * Clean company name for URL
     */
    private function clean_company_name($name) {
        // Remove common suffixes
        $name = preg_replace('/\s+(inc|corp|corporation|company|group|llc|ltd|plc)\.?$/i', '', $name);
        
        // Remove special characters and spaces
        $name = preg_replace('/[^a-z0-9]/i', '', $name);
        
        return strtolower($name);
    }
    
    /**
     * Test a base URL to find working endpoints
     */
    private function test_base_url($base_url, $company_name) {
        $result = [
            'success' => false,
            'base_url' => $base_url,
            'endpoints' => [],
            'error' => null
        ];
        
        // First, check if the base URL is accessible
        $test_response = wp_remote_head($base_url, [
            'timeout' => 10,
            'redirection' => 5
        ]);
        
        if (is_wp_error($test_response)) {
            $result['error'] = 'Base URL not accessible: ' . $test_response->get_error_message();
            return $result;
        }
        
        // Try to find the careers page
        $careers_paths = $this->find_careers_paths($base_url);
        
        if (empty($careers_paths)) {
            $result['error'] = 'No careers paths found';
            return $result;
        }
        
        // Test each careers path to find API endpoints
        foreach ($careers_paths as $path) {
            $endpoint = $this->find_api_endpoint($base_url, $path, $company_name);
            if ($endpoint) {
                $result['endpoints'][] = $endpoint;
                $result['success'] = true;
            }
        }
        
        return $result;
    }
    
    /**
     * Find careers paths on the site
     */
    private function find_careers_paths($base_url) {
        $paths = [];
        
        // Try common patterns
        foreach ($this->common_patterns['careers_paths'] as $path) {
            $test_url = $base_url . $path;
            $response = wp_remote_head($test_url, [
                'timeout' => 5,
                'redirection' => 5
            ]);
            
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code >= 200 && $code < 400) {
                    $paths[] = $path;
                }
            }
        }
        
        // Try to scrape the main page for links
        if (empty($paths)) {
            $response = wp_remote_get($base_url, [
                'timeout' => 10
            ]);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                
                // Look for careers links
                if (preg_match_all('/href="([^"]*(?:career|job|opportunity|position)[^"]*)"/', $body, $matches)) {
                    foreach ($matches[1] as $match) {
                        $parsed = parse_url($match);
                        if (isset($parsed['path'])) {
                            $paths[] = $parsed['path'];
                        }
                    }
                }
            }
        }
        
        return array_unique($paths);
    }
    
    /**
     * Find the API endpoint for a careers path
     */
    private function find_api_endpoint($base_url, $careers_path, $company_name) {
        $clean_company = $this->extract_company_from_url($base_url);
        
        // Common API patterns
        $patterns = [
            "/wday/cxs/{$clean_company}{$careers_path}/jobs",
            "/wday/cxs/{$clean_company}/search/jobs",
            "/wday/cxs/{$clean_company}/External/jobs",
            "/wday/cxs/{$clean_company}/jobs",
        ];
        
        foreach ($patterns as $pattern) {
            $endpoint_url = $base_url . $pattern;
            
            // Test the endpoint
            $response = wp_remote_post($endpoint_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'body' => json_encode([
                    'appliedFacets' => new stdClass(),
                    'limit' => 1,
                    'offset' => 0,
                    'searchText' => ''
                ]),
                'timeout' => 10
            ]);
            
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    if (isset($data['jobPostings']) || isset($data['jobs'])) {
                        return [
                            'endpoint' => $pattern,
                            'careers_path' => $careers_path,
                            'total_jobs' => $data['total'] ?? 0,
                            'working' => true
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract company identifier from URL
     */
    private function extract_company_from_url($url) {
        $parsed = parse_url($url);
        $host_parts = explode('.', $parsed['host']);
        return $host_parts[0];
    }
    
    /**
     * Verify and enhance existing configuration
     */
    public function verify_configuration($config) {
        $result = [
            'valid' => false,
            'issues' => [],
            'suggestions' => []
        ];
        
        // Test the endpoint
        $test_url = $config['base_url'] . $config['endpoint'];
        $response = wp_remote_post($test_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => json_encode([
                'appliedFacets' => new stdClass(),
                'limit' => 1,
                'offset' => 0
            ]),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            $result['issues'][] = 'Connection failed: ' . $response->get_error_message();
            
            // Suggest alternatives
            $detection = $this->detect_configuration($config['company_name'] ?? '', $config['base_url']);
            if ($detection['detected']) {
                $result['suggestions'] = $detection['configurations'];
            }
        } else {
            $code = wp_remote_retrieve_response_code($response);
            
            if ($code === 200) {
                $result['valid'] = true;
                
                // Check response quality
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (empty($data['jobPostings']) && empty($data['jobs'])) {
                    $result['issues'][] = 'No jobs returned - endpoint may be correct but empty';
                }
                
                if (isset($data['total']) && $data['total'] > 0) {
                    $result['job_count'] = $data['total'];
                }
            } else {
                $result['issues'][] = "HTTP {$code} error";
                
                if ($code === 403 || $code === 401) {
                    $result['issues'][] = 'Authentication may be required';
                } elseif ($code === 404) {
                    $result['issues'][] = 'Endpoint not found - configuration may be outdated';
                    
                    // Try to detect new endpoint
                    $detection = $this->detect_configuration($config['company_name'] ?? '', $config['base_url']);
                    if ($detection['detected']) {
                        $result['suggestions'] = $detection['configurations'];
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get detailed job data with all fields
     */
    public function get_job_details($base_url, $job_path) {
        $job_url = $base_url . $job_path;
        
        $response = wp_remote_get($job_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Extract structured data
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            $json_ld = json_decode($matches[1], true);
            if ($json_ld) {
                return $this->parse_job_structured_data($json_ld);
            }
        }
        
        // Fallback to HTML parsing
        return $this->parse_job_html($html);
    }
    
    /**
     * Parse structured data from job page
     */
    private function parse_job_structured_data($data) {
        $job = [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'date_posted' => $data['datePosted'] ?? '',
            'valid_through' => $data['validThrough'] ?? '',
            'employment_type' => $data['employmentType'] ?? '',
            'requirements' => []
        ];
        
        // Extract organization
        if (isset($data['hiringOrganization'])) {
            $job['company'] = $data['hiringOrganization']['name'] ?? '';
            $job['company_url'] = $data['hiringOrganization']['sameAs'] ?? '';
        }
        
        // Extract location
        if (isset($data['jobLocation'])) {
            $loc = $data['jobLocation'];
            if (isset($loc['address'])) {
                $job['location'] = [
                    'city' => $loc['address']['addressLocality'] ?? '',
                    'state' => $loc['address']['addressRegion'] ?? '',
                    'country' => $loc['address']['addressCountry'] ?? '',
                    'postal_code' => $loc['address']['postalCode'] ?? ''
                ];
            }
        }
        
        // Extract salary
        if (isset($data['baseSalary'])) {
            $salary = $data['baseSalary'];
            if (isset($salary['value'])) {
                $job['salary'] = [
                    'min' => $salary['value']['minValue'] ?? null,
                    'max' => $salary['value']['maxValue'] ?? null,
                    'currency' => $salary['currency'] ?? 'USD',
                    'unit' => $salary['value']['unitText'] ?? 'YEAR'
                ];
            }
        }
        
        // Extract qualifications
        if (isset($data['qualifications'])) {
            $job['qualifications'] = $data['qualifications'];
        }
        
        // Extract responsibilities
        if (isset($data['responsibilities'])) {
            $job['responsibilities'] = $data['responsibilities'];
        }
        
        return $job;
    }
    
    /**
     * Parse job details from HTML
     */
    private function parse_job_html($html) {
        $job = [];
        
        // Extract title
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/', $html, $matches)) {
            $job['title'] = trim(strip_tags($matches[1]));
        }
        
        // Extract description
        if (preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            $job['description'] = trim(strip_tags($matches[1]));
        }
        
        // Extract requirements
        if (preg_match_all('/<li[^>]*>([^<]*(?:require|qualification|experience)[^<]*)<\/li>/i', $html, $matches)) {
            $job['requirements'] = array_map('trim', $matches[1]);
        }
        
        return $job;
    }
}

// Initialize
function sffc_workday_detector() {
    return new SFFC_Workday_Auto_Detector();
}