<?php

/**
 * Gupy Job Fetcher Class
 * 
 * Handles fetching jobs from Gupy-powered career sites
 * Uses Next.js data endpoints to retrieve job information
 * 
 * @since 1.0.0
 */
class SFFC_Gupy_Job_Fetcher {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache duration in seconds (1 hour)
     */
    const CACHE_DURATION = 3600;

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
     * Gupy company configurations
     * Each entry contains the subdomain and metadata for job fetching
     */
    private $gupy_instances = [
        'bancodaycoval' => [
            'subdomain' => 'bancodaycoval',
            'base_url' => 'https://bancodaycoval.gupy.io',
            'company_name' => 'Banco Daycoval',
            'company_id' => 574,
            'career_page_id' => 669,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'venhasersafra' => [
            'subdomain' => 'venhasersafra',
            'base_url' => 'https://venhasersafra.gupy.io',
            'company_name' => 'Safra',
            'company_id' => 59404,
            'career_page_id' => 139501,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'vert-capital' => [
            'subdomain' => 'vert-capital',
            'base_url' => 'https://vert-capital.gupy.io',
            'company_name' => 'VERT Capital',
            'company_id' => 8221,
            'career_page_id' => 18787,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'unicredbr' => [
            'subdomain' => 'unicredbr',
            'base_url' => 'https://unicredbr.gupy.io',
            'company_name' => 'Unicred',
            'company_id' => 6670,
            'career_page_id' => 16609,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'genial' => [
            'subdomain' => 'genial',
            'base_url' => 'https://genial.gupy.io',
            'company_name' => 'Genial Investimentos',
            'company_id' => 39076,
            'career_page_id' => 81195,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'gruposunojobs' => [
            'subdomain' => 'gruposunojobs',
            'base_url' => 'https://gruposunojobs.gupy.io',
            'company_name' => 'Grupo Suno',
            'company_id' => 26339,
            'career_page_id' => 64598,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'nomos' => [
            'subdomain' => 'nomos',
            'base_url' => 'https://nomos.gupy.io',
            'company_name' => 'Nomos',
            'company_id' => 6109,
            'career_page_id' => 133924,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'ceresinvestimentos' => [
            'subdomain' => 'ceresinvestimentos',
            'base_url' => 'https://ceresinvestimentos.gupy.io',
            'company_name' => 'Ceres Investimentos',
            'company_id' => 55378,
            'career_page_id' => 113728,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'abcbrasil' => [
            'subdomain' => 'abcbrasil',
            'base_url' => 'https://abcbrasil.gupy.io',
            'company_name' => 'Banco ABC Brasil',
            'company_id' => null, // Will be extracted from API response
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'bmg' => [
            'subdomain' => 'bmg',
            'base_url' => 'https://bmg.gupy.io',
            'company_name' => 'Banco BMG',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'bancofibra' => [
            'subdomain' => 'bancofibra',
            'base_url' => 'https://bancofibra.gupy.io',
            'company_name' => 'Banco Fibra',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'bib' => [
            'subdomain' => 'bib',
            'base_url' => 'https://bib.gupy.io',
            'company_name' => 'Banco Industrial do Brasil',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'ouribank' => [
            'subdomain' => 'ouribank',
            'base_url' => 'https://ouribank.gupy.io',
            'company_name' => 'Ouribank',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'bancotopazio' => [
            'subdomain' => 'bancotopazio',
            'base_url' => 'https://bancotopazio.gupy.io',
            'company_name' => 'Banco Topázio',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'galapagoscapital' => [
            'subdomain' => 'galapagoscapital',
            'base_url' => 'https://galapagoscapital.gupy.io',
            'company_name' => 'Galapagos Capital',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'vemproitau' => [
            'subdomain' => 'vemproitau',
            'base_url' => 'https://vemproitau.gupy.io',
            'company_name' => 'Itaú Unibanco',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ],
        'estagiomercantil' => [
            'subdomain' => 'estagiomercantil',
            'base_url' => 'https://estagiomercantil.gupy.io',
            'company_name' => 'Banco Mercantil (Estágios)',
            'company_id' => null,
            'career_page_id' => null,
            'build_id' => '_AKXoxyNrnuDoO5T59iSR',
            'status' => 'working',
            'country' => 'Brazil',
            'language' => 'pt'
        ]
    ];

    /**
     * Get all configured Gupy instances
     * 
     * @return array
     */
    public function get_instances() {
        return $this->gupy_instances;
    }

    /**
     * Test connection to a Gupy instance
     * 
     * @param string $company_key
     * @return array
     */
    public function test_connection($company_key) {
        if (!isset($this->gupy_instances[$company_key])) {
            return [
                'success' => false,
                'error' => 'Company not configured',
                'company' => $company_key
            ];
        }

        $instance = $this->gupy_instances[$company_key];
        $endpoint = $this->build_jobs_endpoint($instance);

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'company' => $instance['company_name'],
                'endpoint' => $endpoint
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP ' . $status_code,
                'company' => $instance['company_name'],
                'endpoint' => $endpoint
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'company' => $instance['company_name'],
                'endpoint' => $endpoint
            ];
        }

        $jobs = isset($data['pageProps']['jobs']) ? $data['pageProps']['jobs'] : [];
        $job_count = count($jobs);

        $sample_job = null;
        if ($job_count > 0) {
            $sample_job = [
                'id' => $jobs[0]['id'],
                'title' => $jobs[0]['title'],
                'department' => $jobs[0]['department'] ?? 'Unknown',
                'type' => $this->translate_job_type($jobs[0]['type'] ?? ''),
                'workplace_type' => $this->translate_workplace_type($jobs[0]['workplace']['workplaceType'] ?? '')
            ];
        }

        return [
            'success' => true,
            'company' => $instance['company_name'],
            'endpoint' => $endpoint,
            'total_jobs' => $job_count,
            'sample_job' => $sample_job
        ];
    }

    /**
     * Fetch jobs from a Gupy instance
     * 
     * @param string $company_key
     * @param array $options
     * @return array|WP_Error
     */
    public function get_jobs($company_key, $options = []) {
        if (!isset($this->gupy_instances[$company_key])) {
            return new WP_Error('company_not_found', 'Company not configured: ' . $company_key);
        }

        $instance = $this->gupy_instances[$company_key];
        
        // Check cache first
        $cache_key = 'gupy_jobs_' . $company_key . '_' . md5(serialize($options));
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }

        $endpoint = $this->build_jobs_endpoint($instance);

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('api_error', 'HTTP ' . $status_code . ' response from Gupy API');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from Gupy API');
        }

        if (!isset($data['pageProps']['jobs'])) {
            return new WP_Error('data_error', 'No jobs data in Gupy API response');
        }

        $raw_jobs = $data['pageProps']['jobs'];
        $processed_jobs = [];

        // Apply filters and limits
        $limit = isset($options['limit']) ? intval($options['limit']) : 20;
        $offset = isset($options['offset']) ? intval($options['offset']) : 0;
        
        // Filter jobs if needed
        $filtered_jobs = $this->filter_jobs($raw_jobs, $options);
        
        // Apply pagination
        $paginated_jobs = array_slice($filtered_jobs, $offset, $limit);

        foreach ($paginated_jobs as $job) {
            $processed_job = $this->process_job($job, $instance);
            if ($processed_job) {
                $processed_jobs[] = $processed_job;
            }
        }

        $result = [
            'jobs' => $processed_jobs,
            'total' => count($filtered_jobs),
            'company' => $instance['company_name'],
            'fetched_at' => current_time('Y-m-d H:i:s'),
            'source' => 'gupy',
            'filters' => $this->get_available_filters($raw_jobs),
            'departments' => $this->get_departments($raw_jobs),
            'locations' => $this->get_locations($raw_jobs),
            'job_types' => $this->get_job_types($raw_jobs)
        ];

        // Cache the result
        set_transient($cache_key, $result, self::CACHE_DURATION);

        return $result;
    }

    /**
     * Get detailed job information
     * 
     * @param string $company_key
     * @param int $job_id
     * @return array|WP_Error
     */
    public function get_job_details($company_key, $job_id) {
        if (!isset($this->gupy_instances[$company_key])) {
            return new WP_Error('company_not_found', 'Company not configured: ' . $company_key);
        }

        $instance = $this->gupy_instances[$company_key];
        $endpoint = $this->build_job_detail_endpoint($instance, $job_id);

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('api_error', 'HTTP ' . $status_code . ' response from Gupy API');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from Gupy API');
        }

        if (!isset($data['pageProps']['job'])) {
            return new WP_Error('data_error', 'No job data in Gupy API response');
        }

        return $this->process_detailed_job($data['pageProps']['job'], $instance);
    }

    /**
     * Build the jobs list endpoint URL
     * 
     * @param array $instance
     * @return string
     */
    private function build_jobs_endpoint($instance) {
        $build_id = $this->get_current_build_id($instance);
        return sprintf(
            '%s/_next/data/%s/%s.json',
            $instance['base_url'],
            $build_id,
            $instance['language']
        );
    }
    
    /**
     * Get current build ID from Gupy site
     * 
     * @param array $instance
     * @return string
     */
    private function get_current_build_id($instance) {
        // Check cache first
        $cache_key = 'gupy_build_id_' . $instance['subdomain'];
        $cached_build_id = get_transient($cache_key);
        
        if ($cached_build_id !== false) {
            return $cached_build_id;
        }
        
        // Fetch the main page to extract build ID
        $response = wp_remote_get($instance['base_url'], [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Fallback to default build ID
            return $instance['build_id'];
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Look for Next.js build ID in the HTML
        if (preg_match('/_next\/static\/([^\/]+)\/_buildManifest\.js/', $html, $matches)) {
            $build_id = $matches[1];
        } elseif (preg_match('/"buildId":"([^"]+)"/', $html, $matches)) {
            $build_id = $matches[1];
        } else {
            // Fallback to default
            $build_id = $instance['build_id'];
        }
        
        // Cache for 1 hour (build IDs change infrequently)
        set_transient($cache_key, $build_id, 3600);
        
        return $build_id;
    }

    /**
     * Build the job detail endpoint URL
     * 
     * @param array $instance
     * @param int $job_id
     * @return string
     */
    private function build_job_detail_endpoint($instance, $job_id) {
        $build_id = $this->get_current_build_id($instance);
        return sprintf(
            '%s/_next/data/%s/%s/jobs/%d.json',
            $instance['base_url'],
            $build_id,
            $instance['language'],
            $job_id
        );
    }

    /**
     * Process a job from the API response
     * 
     * @param array $job
     * @param array $instance
     * @return array
     */
    private function process_job($job, $instance) {
        return [
            'id' => $job['id'],
            'title' => $job['title'],
            'company' => $instance['company_name'],
            'department' => $job['department'] ?? 'Unknown',
            'location' => $this->format_location($job['workplace'] ?? []),
            'country' => $job['workplace']['address']['country'] ?? $instance['country'],
            'state' => $job['workplace']['address']['state'] ?? '',
            'city' => $job['workplace']['address']['city'] ?? '',
            'workplace_type' => $this->translate_workplace_type($job['workplace']['workplaceType'] ?? ''),
            'job_type' => $this->translate_job_type($job['type'] ?? ''),
            'url' => $this->build_job_url($instance, $job['id']),
            'source' => 'gupy',
            'raw_data' => $job
        ];
    }

    /**
     * Process detailed job information
     * 
     * @param array $job
     * @param array $instance
     * @return array
     */
    private function process_detailed_job($job, $instance) {
        // For detailed jobs, the structure may be different
        $basic_data = [
            'id' => $job['id'],
            'title' => $job['name'] ?? $job['title'] ?? 'Untitled Position',
            'company' => $instance['company_name'],
            'department' => $job['department'] ?? 'Unknown',
            'location' => $this->format_detailed_location($job),
            'country' => $instance['country'],
            'state' => $job['addressState'] ?? '',
            'city' => $job['addressCity'] ?? '',
            'workplace_type' => $this->translate_workplace_type($job['workplaceType'] ?? ''),
            'job_type' => $this->translate_job_type($job['jobType'] ?? ''),
            'url' => $this->build_job_url($instance, $job['id']),
            'source' => 'gupy',
            'raw_data' => $job
        ];
        
        return array_merge($basic_data, [
            'description' => $this->clean_html($job['description'] ?? ''),
            'responsibilities' => $this->clean_html($job['responsibilities'] ?? ''),
            'prerequisites' => $this->clean_html($job['prerequisites'] ?? ''),
            'benefits' => $this->clean_html($job['relevantExperiences'] ?? ''),
            'published_at' => $job['publishedAt'] ?? '',
            'expires_at' => $job['expiresAt'] ?? '',
            'job_code' => $job['code'] ?? '',
            'status' => $job['status'] ?? '',
            'quick_apply' => $job['quickApply'] ?? false,
            'skills' => $job['skills'] ?? [],
            'job_steps' => $job['jobSteps'] ?? []
        ]);
    }

    /**
     * Filter jobs based on criteria
     * 
     * @param array $jobs
     * @param array $filters
     * @return array
     */
    private function filter_jobs($jobs, $filters) {
        $filtered = $jobs;

        // Filter by department
        if (isset($filters['department']) && !empty($filters['department'])) {
            $filtered = array_filter($filtered, function($job) use ($filters) {
                return stripos($job['department'] ?? '', $filters['department']) !== false;
            });
        }

        // Filter by job type
        if (isset($filters['job_type']) && !empty($filters['job_type'])) {
            $filtered = array_filter($filtered, function($job) use ($filters) {
                return $job['type'] === $filters['job_type'];
            });
        }

        // Filter by workplace type
        if (isset($filters['workplace_type']) && !empty($filters['workplace_type'])) {
            $filtered = array_filter($filtered, function($job) use ($filters) {
                return ($job['workplace']['workplaceType'] ?? '') === $filters['workplace_type'];
            });
        }

        // Filter by location
        if (isset($filters['city']) && !empty($filters['city'])) {
            $filtered = array_filter($filtered, function($job) use ($filters) {
                return stripos($job['workplace']['address']['city'] ?? '', $filters['city']) !== false;
            });
        }

        return array_values($filtered);
    }

    /**
     * Get available departments from jobs
     * 
     * @param array $jobs
     * @return array
     */
    private function get_departments($jobs) {
        $departments = [];
        foreach ($jobs as $job) {
            $dept = $job['department'] ?? 'Unknown';
            if (!in_array($dept, $departments)) {
                $departments[] = $dept;
            }
        }
        sort($departments);
        return $departments;
    }

    /**
     * Get available locations from jobs
     * 
     * @param array $jobs
     * @return array
     */
    private function get_locations($jobs) {
        $locations = [];
        foreach ($jobs as $job) {
            if (isset($job['workplace']['address'])) {
                $address = $job['workplace']['address'];
                $location = [
                    'city' => $address['city'] ?? '',
                    'state' => $address['state'] ?? '',
                    'country' => $address['country'] ?? ''
                ];
                $location_key = implode(', ', array_filter($location));
                if (!in_array($location_key, $locations)) {
                    $locations[] = $location_key;
                }
            }
        }
        sort($locations);
        return $locations;
    }

    /**
     * Get available job types from jobs
     * 
     * @param array $jobs
     * @return array
     */
    private function get_job_types($jobs) {
        $types = [];
        foreach ($jobs as $job) {
            $type = $this->translate_job_type($job['type'] ?? '');
            if (!in_array($type, $types)) {
                $types[] = $type;
            }
        }
        sort($types);
        return $types;
    }

    /**
     * Get available filters summary
     * 
     * @param array $jobs
     * @return array
     */
    private function get_available_filters($jobs) {
        return [
            'departments' => $this->get_departments($jobs),
            'locations' => $this->get_locations($jobs),
            'job_types' => $this->get_job_types($jobs),
            'workplace_types' => array_unique(array_map(function($job) {
                return $this->translate_workplace_type($job['workplace']['workplaceType'] ?? '');
            }, $jobs))
        ];
    }

    /**
     * Format location string
     * 
     * @param array $workplace
     * @return string
     */
    private function format_location($workplace) {
        if (!isset($workplace['address'])) {
            return 'Unknown';
        }

        $address = $workplace['address'];
        $parts = array_filter([
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['country'] ?? ''
        ]);

        return implode(', ', $parts);
    }

    /**
     * Format location string for detailed job data
     * 
     * @param array $job
     * @return string
     */
    private function format_detailed_location($job) {
        $parts = array_filter([
            $job['addressCity'] ?? '',
            $job['addressState'] ?? '',
            $job['addressCountry'] ?? ''
        ]);

        return !empty($parts) ? implode(', ', $parts) : 'Unknown';
    }

    /**
     * Translate Gupy job types to readable format
     * 
     * @param string $type
     * @return string
     */
    private function translate_job_type($type) {
        $translations = [
            'vacancy_type_effective' => 'Full-time',
            'vacancy_type_internship' => 'Internship',
            'vacancy_type_trainee' => 'Trainee',
            'vacancy_type_talent_pool' => 'Talent Pool',
            'vacancy_type_temporary' => 'Temporary',
            'vacancy_type_freelancer' => 'Freelancer',
            'vacancy_type_apprentice' => 'Apprentice'
        ];

        return $translations[$type] ?? ucfirst(str_replace(['vacancy_type_', '_'], ['', ' '], $type));
    }

    /**
     * Translate workplace types to readable format
     * 
     * @param string $type
     * @return string
     */
    private function translate_workplace_type($type) {
        $translations = [
            'on-site' => 'On-site',
            'remote' => 'Remote',
            'hybrid' => 'Hybrid'
        ];

        return $translations[$type] ?? ucfirst($type);
    }

    /**
     * Build job URL
     * 
     * @param array $instance
     * @param int $job_id
     * @return string
     */
    private function build_job_url($instance, $job_id) {
        return $instance['base_url'] . '/jobs/' . $job_id;
    }

    /**
     * Clean HTML content
     * 
     * @param string $html
     * @return string
     */
    private function clean_html($html) {
        if (empty($html)) {
            return '';
        }

        // Convert HTML to plain text but preserve line breaks
        $text = wp_strip_all_tags($html, true);
        
        // Clean up extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }

    /**
     * Get company name by key
     * 
     * @param string $company_key
     * @return string
     */
    public function get_company_name($company_key) {
        if (isset($this->gupy_instances[$company_key])) {
            return $this->gupy_instances[$company_key]['company_name'];
        }
        
        return ucfirst(str_replace('_', ' ', $company_key));
    }

    /**
     * Get all company keys
     * 
     * @return array
     */
    public function get_company_keys() {
        return array_keys($this->gupy_instances);
    }

    /**
     * Check if a company uses Gupy platform
     * 
     * @param string $company_key
     * @return bool
     */
    public function is_gupy_company($company_key) {
        return isset($this->gupy_instances[$company_key]);
    }
}