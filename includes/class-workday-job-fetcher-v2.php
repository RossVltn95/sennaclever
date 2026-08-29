<?php

/**
 * Workday Job Fetcher V2 - Enhanced with new financial institutions
 * 
 * This class provides a robust solution to fetch jobs from Workday instances
 * with proper endpoint detection and error handling
 * 
 * @package MENA Careers
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Workday_Job_Fetcher_V2
{

    /**
     * Updated Workday instances with correct endpoints
     */
    private $workday_instances = [
        // TIER 1 - CONFIRMED WORKING
        'blackstone' => [
            'base_url' => 'https://blackstone.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/blackstone/Blackstone_Campus_Careers/jobs',
            'careers_path' => '/Blackstone_Campus_Careers',
            'status' => 'working'
        ],
        'barings' => [
            'base_url' => 'https://barings.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/barings/Barings/jobs',
            'careers_path' => '/en-GB/Barings',
            'status' => 'new'
        ],
        'moelis' => [
            'base_url' => 'https://moelis.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/moelis/Experienced-Hires/jobs',
            'careers_path' => '/en-US/Experienced-Hires',
            'status' => 'new'
        ],
        'houlihan_lokey' => [
            'base_url' => 'https://hl.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/hl/External/jobs',
            'careers_path' => '/en-US/External',
            'status' => 'new'
        ],
        'rothschild' => [
            'base_url' => 'https://rothschildandco.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/rothschildandco/RothschildAndCo_Lateral/jobs',
            'careers_path' => '/en-US/RothschildAndCo_Lateral',
            'status' => 'new'
        ],
        'lloyds' => [
            'base_url' => 'https://lbg.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/lbg/LBG_Careers/jobs',
            'careers_path' => '/en-US/LBG_Careers',
            'status' => 'new'
        ],
        'santander' => [
            'base_url' => 'https://santander.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/santander/SantanderCareers/jobs',
            'careers_path' => '/es/SantanderCareers',
            'status' => 'new'
        ],
        'cibc' => [
            'base_url' => 'https://cibc.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/cibc/search/jobs',
            'careers_path' => '/en-US/search',
            'filters' => ['Country' => '29247e57dbaf46fb855b224e03170bc7'],
            'status' => 'new'
        ],
        'statestreet' => [
            'base_url' => 'https://statestreet.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/statestreet/Global/jobs',
            'careers_path' => '/en-US/Global',
            'location_ids' => [
                '8c182d98fcf8010b9e9a732f1244e062',
                '8c182d98fcf8010266be892e1244aa61',
                '8c182d98fcf8012d41a5952f12440363',
                '8c182d98fcf801a4e8086f2a1244095f',
                '8c182d98fcf801ec386f36a41144e95d',
                '8c182d98fcf801a8f53f632f1244cc62',
                '8c182d98fcf801031a9f889f11448a58',
                '8c182d98fcf801d8afa08d2f1244f962'
            ],
            'status' => 'working'
        ],
        'fca' => [
            'base_url' => 'https://fca.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/fca/FCA_Careers/jobs',
            'careers_path' => '/en-US/FCA_Careers',
            'status' => 'new'
        ],
        'aviva' => [
            'base_url' => 'https://aviva.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/aviva/External/jobs',
            'careers_path' => '/en-US/External',
            'status' => 'new'
        ],
        'mfs' => [
            'base_url' => 'https://mfs.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/mfs/MFS-Careers/jobs',
            'careers_path' => '/en-US/MFS-Careers',
            'location_ids' => [
                'b949c042e4be444cacae1a753bc166ea',
                '2c191d673ad410363966d1a6941a340c'
            ],
            'status' => 'new'
        ],

        // EXISTING INSTANCES (keep for backwards compatibility)
        'morganstanley' => [
            'base_url' => 'https://ms.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/ms/External/jobs',
            'careers_path' => '/External',
            'status' => 'working'
        ],
        'bankofamerica' => [
            'base_url' => 'https://ghr.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/ghr/lateral-emea/jobs',
            'careers_path' => '/en-US/lateral-emea',
            'status' => 'working'
        ],

        // NEW WORKDAY FEEDS - Tested and working
        'juliusbaer' => [
            'base_url' => 'https://juliusbaer.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/juliusbaer/External/jobs',
            'careers_path' => '/en-US/External',
            'status' => 'working'
        ],
        'juliusbaer_uae' => [
            'base_url' => 'https://juliusbaer.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/juliusbaer/External/jobs',
            'careers_path' => '/en-US/External',
            'filters' => [
                'Location_Country' => ['7b4fa1f369bd4604ba3692682fcbe345'],
            ],
            'status' => 'working',
            'job_count' => 2
        ],
        'capitalgroup' => [
            'base_url' => 'https://capgroup.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/capgroup/capitalgroupcareers/jobs',
            'careers_path' => '/en-US/capitalgroupcareers',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'quilter' => [
            'base_url' => 'https://quilter.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/quilter/External-Career-Site/jobs',
            'careers_path' => '/en-US/External-Career-Site',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'cdpq' => [
            'base_url' => 'https://cdpq.wd10.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/cdpq/CDPQ/jobs',
            'careers_path' => '/en-US/CDPQ',
            'status' => 'working'
        ],
        'cdpq_infra' => [
            'base_url' => 'https://cdpqfiliales.wd10.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/cdpqfiliales/CDPQ-Infra/jobs',
            'careers_path' => '/CDPQ-Infra',
            'status' => 'working'
        ],
        'robeco' => [
            'base_url' => 'https://robeco.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/robeco/robecoexternalcareers/jobs',
            'careers_path' => '/robecoexternalcareers',
            'status' => 'working'
        ],
        'apollo' => [
            'base_url' => 'https://athene.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/athene/Apollo_Careers/jobs',
            'careers_path' => '/en-GB/Apollo_Careers',
            'status' => 'working'
        ],
        'ardian' => [
            'base_url' => 'https://ardian.wd103.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/ardian/ArdianCareers/jobs',
            'careers_path' => '/en-US/ArdianCareers',
            'status' => 'working'
        ],
        'three_i_intern_career' => [
            'base_url' => 'https://3i.wd103.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/3i/Intern_Career/jobs',
            'careers_path' => '/en-US/Intern_Career',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'icg_external_careers' => [
            'base_url' => 'https://icg.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/icg/external_careers/jobs',
            'careers_path' => '/en-US/external_careers',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'harbourvest_hvp' => [
            'base_url' => 'https://harbourvest.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/harbourvest/HVP/jobs',
            'careers_path' => '/en-US/HVP',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'capitaland_development' => [
            'base_url' => 'https://capitaland.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/capitaland/CapitaLandDevelopment/jobs',
            'careers_path' => '/CapitaLandDevelopment',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'keppel_careers' => [
            'base_url' => 'https://keppel.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/keppel/KeppelCareers/jobs',
            'careers_path' => '/nl-NL/KeppelCareers',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'cambridge_associates' => [
            'base_url' => 'https://cambridgeassociates.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/cambridgeassociates/Cambridge_Associates/jobs',
            'careers_path' => '/en-US/Cambridge_Associates',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'ontario_teachers_london' => [
            'base_url' => 'https://otppb.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/otppb/OntarioTeachers_Careers/jobs',
            'careers_path' => '/en-US/OntarioTeachers_Careers',
            'filters' => [
                'locations' => ['b6f626b7ab7701cc0f288e497c39325f'],
            ],
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'bain_capital_public' => [
            'base_url' => 'https://baincapital.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/baincapital/External_Public/jobs',
            'careers_path' => '/External_Public',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'citic_clsa_external' => [
            'base_url' => 'https://citicclsa.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/citicclsa/External/jobs',
            'careers_path' => '/en-US/External',
            'status' => 'working',
            'source_type' => 'job_aggregator'
        ],
        'deutschebank' => [
            'base_url' => 'https://db.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/db/DBWebsite/jobs',
            'careers_path' => '/en-US/DBWebsite',
            'status' => 'working'
        ],

        // VERIFIED WORKING WORKDAY INSTANCES - DECEMBER 2024
        'brookfield' => [
            'base_url' => 'https://brookfield.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/brookfield/brookfield/jobs',
            'careers_path' => '/brookfield',
            'status' => 'working',
            'job_count' => 100
        ],
        'imf' => [
            'base_url' => 'https://imf.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/imf/IMF/jobs',
            'careers_path' => '/IMF',
            'status' => 'working',
            'job_count' => 16
        ],
        'investpsp' => [
            'base_url' => 'https://investpsp.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/investpsp/psp_careers/jobs',
            'careers_path' => '/en-US/psp_careers',
            'status' => 'working',
            'job_count' => 28
        ],
        'pru_pgim' => [
            'base_url' => 'https://pru.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/pru/PGIM_Careers/jobs',
            'careers_path' => '/en-US/PGIM_Careers',
            'filters' => ['locationCountry' => '29247e57dbaf46fb855b224e03170bc7'],
            'status' => 'working',
            'job_count' => 87
        ],
        'hamiltonlane' => [
            'base_url' => 'https://hamiltonlane.wd108.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/hamiltonlane/Search/jobs',
            'careers_path' => '/Search',
            'status' => 'working',
            'job_count' => 42
        ],
        'higcapital' => [
            'base_url' => 'https://higcapital.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/higcapital/LinkedIn/jobs',
            'careers_path' => '/LinkedIn',
            'status' => 'working',
            'job_count' => 8
        ],
        'oaktree' => [
            'base_url' => 'https://oaktree.wd1.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/oaktree/Oaktree/jobs',
            'careers_path' => '/Oaktree',
            'status' => 'working',
            'job_count' => 67
        ],
        'tritonpartners' => [
            'base_url' => 'https://tritonpartners.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/tritonpartners/External/jobs',
            'careers_path' => '/External',
            'status' => 'working',
            'job_count' => 3
        ],
        'brevanhoward' => [
            'base_url' => 'https://wd3.myworkdaysite.com',
            'endpoint' => '/wday/cxs/brevanhoward/BH_ExternalCareers/jobs',
            'careers_path' => '/recruiting/brevanhoward/BH_ExternalCareers',
            'status' => 'working',
            'job_count' => 23
        ],
        'dimensional' => [
            'base_url' => 'https://dimensional.wd5.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/dimensional/DFA_Careers/jobs',
            'careers_path' => '/DFA_Careers',
            'status' => 'working',
            'job_count' => 70
        ],
        'aquilacapital' => [
            'base_url' => 'https://aquilacapital.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/aquilacapital/AquilaCapital/jobs',
            'careers_path' => '/AquilaCapital',
            'status' => 'working',
            'job_count' => 11
        ],
        'apexgroup_middle_east' => [
            'base_url' => 'https://theapexgroup.wd3.myworkdayjobs.com',
            'endpoint' => '/wday/cxs/theapexgroup/apexgroupcareers/jobs',
            'careers_path' => '/en-US/apexgroupcareers',
            'filters' => [
                'locationCountry' => [
                    '50423b5190ad49bb89e94cd58dfaad69',
                    '7b4fa1f369bd4604ba3692682fcbe345',
                ],
            ],
            'status' => 'working',
            'job_count' => 15
        ]
    ];

    /**
     * Cache duration in seconds (1 hour)
     */
    private $cache_duration = 3600;

    /**
     * Clear all Workday job caches
     * Call this before scheduled fetches to ensure fresh data
     *
     * @param string|null $company Optional specific company to clear cache for
     * @return int Number of cache entries cleared
     */
    public function clear_cache($company = null)
    {
        global $wpdb;

        $cleared = 0;

        if ($company !== null) {
            // Clear cache for specific company
            $pattern = 'sffc_workday_v2_' . $company . '_%';
            $transients = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $pattern
            ));

            foreach ($transients as $transient) {
                $key = str_replace('_transient_', '', $transient);
                delete_transient($key);
                $cleared++;
            }
        } else {
            // Clear all Workday caches
            $transients = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_sffc_workday_v2_%'"
            );

            foreach ($transients as $transient) {
                $key = str_replace('_transient_', '', $transient);
                delete_transient($key);
                $cleared++;
            }
        }

        if ($cleared > 0) {
            error_log(sprintf(
                '[SFFC Workday] Cleared %d cache entries%s',
                $cleared,
                $company ? " for {$company}" : ''
            ));
        }

        return $cleared;
    }

    /**
     * Test connection to a Workday instance
     */
    public function test_connection($company)
    {
        if (!isset($this->workday_instances[$company])) {
            return [
                'success' => false,
                'error' => 'Company not found in configuration',
                'company' => $company
            ];
        }

        $instance = $this->workday_instances[$company];

        // Build request body
        $applied_facets = [];

        if (isset($instance['filters']) && is_array($instance['filters'])) {
            $applied_facets = $instance['filters'];
        }

        if (isset($instance['location_ids']) && !empty($instance['location_ids'])) {
            $applied_facets['locations'] = $instance['location_ids'];
        }

        $request_body = [
            'appliedFacets' => empty($applied_facets) ? new stdClass() : $applied_facets,
            'limit' => 1,
            'offset' => 0,
            'searchText' => ''
        ];

        // Make the test request
        $response = $this->post_workday_jobs_request($instance, $request_body, 30);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'company' => $company,
                'endpoint' => $instance['base_url'] . $instance['endpoint']
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP ' . $response_code,
                'company' => $company,
                'endpoint' => $instance['base_url'] . $instance['endpoint'],
                'response' => substr($body, 0, 200)
            ];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'company' => $company
            ];
        }

        return [
            'success' => true,
            'company' => $company,
            'total_jobs' => isset($data['total']) ? $data['total'] : 0,
            'sample_job' => isset($data['jobPostings'][0]) ? $data['jobPostings'][0] : null,
            'endpoint' => $instance['base_url'] . $instance['endpoint']
        ];
    }

    /**
     * Post to a Workday jobs endpoint with conservative fallbacks for tenants that
     * reject large page sizes or stricter request bodies.
     */
    private function post_workday_jobs_request(array $instance, array $request_body, $timeout = 60)
    {
        $request_variants = [$request_body];

        if (($request_body['limit'] ?? 20) > 10) {
            $limit_ten_body = $request_body;
            $limit_ten_body['limit'] = 10;
            $request_variants[] = $limit_ten_body;
        }

        if (($request_body['limit'] ?? 20) > 5) {
            $limit_five_body = $request_body;
            $limit_five_body['limit'] = 5;
            $request_variants[] = $limit_five_body;
        }

        $no_facets_body = $request_body;
        unset($no_facets_body['appliedFacets']);
        $request_variants[] = $no_facets_body;

        $last_response = null;
        foreach ($request_variants as $variant_index => $variant_body) {
            $retry_count = 0;
            $max_retries = 3;

            while ($retry_count < $max_retries) {
                $response = wp_remote_post(
                    $instance['base_url'] . $instance['endpoint'],
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            'Origin' => $instance['base_url'],
                            'Referer' => $instance['base_url'] . ($instance['careers_path'] ?? ''),
                        ],
                        'body' => wp_json_encode($variant_body),
                        'timeout' => $timeout,
                        'sslverify' => true,
                    ]
                );

                $last_response = $response;

                if (is_wp_error($response)) {
                    $retry_count++;
                    if ($retry_count < $max_retries) {
                        sleep(1);
                    }
                    continue;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 200) {
                    return $response;
                }

                if ($response_code === 429) {
                    $retry_count++;
                    if ($retry_count < $max_retries) {
                        sleep($retry_count * 2);
                    }
                    continue;
                }

                if (in_array($response_code, [400, 422], true) && isset($request_variants[$variant_index + 1])) {
                    break;
                }

                return $response;
            }
        }

        return $last_response ?: new WP_Error('workday_request_failed', 'Workday request failed before receiving a response');
    }

    /**
     * Get jobs from a specific Workday instance with error handling
     */
    public function get_jobs($company, $filters = [])
    {
        // Check for custom feeds first
        $custom_feeds = get_option('sffc_custom_workday_feeds', []);
        if (isset($custom_feeds[$company])) {
            $instance = $custom_feeds[$company];
        } elseif (isset($this->workday_instances[$company])) {
            $instance = $this->workday_instances[$company];
        } else {
            // Try auto-detection for unknown companies
            if (class_exists('SFFC_Workday_Auto_Detector')) {
                $detector = new SFFC_Workday_Auto_Detector();
                $detection = $detector->detect_configuration($company);

                if ($detection['detected'] && !empty($detection['configurations'])) {
                    $config = $detection['configurations'][0];
                    $instance = [
                        'base_url' => $config['base_url'],
                        'endpoint' => $config['endpoints'][0]['endpoint'],
                        'careers_path' => $config['endpoints'][0]['careers_path'],
                        'status' => 'auto_detected'
                    ];

                    // Save for future use
                    $custom_feeds[$company] = $instance;
                    update_option('sffc_custom_workday_feeds', $custom_feeds);
                } else {
                    return new WP_Error('invalid_company', 'Company not found and auto-detection failed: ' . $company);
                }
            } else {
                return new WP_Error('invalid_company', 'Company not found: ' . $company);
            }
        }

        // Check if this instance is known to have issues
        if (isset($instance['status']) && $instance['status'] === 'auth_required') {
            return new WP_Error('auth_required', 'This company requires authentication');
        }

        $cache_key = 'sffc_workday_v2_' . $company . '_' . md5(json_encode($filters));

        // Check cache first
        $cached = get_transient($cache_key);
        if ($cached !== false && empty($filters['bypass_cache'])) {
            return $cached;
        }

        // Build request body
        $applied_facets = isset($filters['facets']) ? $filters['facets'] : [];

        // Add company-specific filters
        if (isset($instance['filters'])) {
            $applied_facets = array_merge($applied_facets, $instance['filters']);
        }

        // Add location filter if specified
        if (isset($filters['location'])) {
            $applied_facets['locations'] = [$filters['location']];
        } elseif (isset($instance['location_ids'])) {
            $applied_facets['locations'] = $instance['location_ids'];
        }

        // Add job family filter if specified
        if (isset($filters['job_family'])) {
            $applied_facets['jobFamilyGroup'] = [$filters['job_family']];
        }

        $requested_limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $request_limit = max(1, min(20, $requested_limit));

        $request_body = [
            'appliedFacets' => empty($applied_facets) ? new stdClass() : $applied_facets,
            'limit' => $request_limit,
            'offset' => isset($filters['offset']) ? intval($filters['offset']) : 0,
            'searchText' => isset($filters['search']) ? sanitize_text_field($filters['search']) : ''
        ];

        $response = $this->post_workday_jobs_request($instance, $request_body);

        if (is_wp_error($response)) {
            // Try alternative endpoint if main fails
            if (isset($instance['fallback_endpoint'])) {
                $fallback_instance = $instance;
                $fallback_instance['endpoint'] = $instance['fallback_endpoint'];
                $response = $this->post_workday_jobs_request($fallback_instance, $request_body);
            }

            if (is_wp_error($response)) {
                error_log('Workday API error for ' . $company . ': ' . $response->get_error_message());
                return $response;
            }
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            // Log the error for debugging
            error_log('Workday API error for ' . $company . ': HTTP ' . $response_code);

            // Try to provide more specific error messages
            $error_message = 'Workday API returned status ' . $response_code . ' for ' . $company;

            if ($response_code === 403) {
                $error_message .= ' - Access forbidden. The company may require authentication.';
            } elseif ($response_code === 404) {
                $error_message .= ' - Endpoint not found. The configuration may be outdated.';
            } elseif ($response_code === 429) {
                $error_message .= ' - Rate limit exceeded. Please try again later.';
            } elseif ($response_code >= 500) {
                $error_message .= ' - Server error. The Workday service may be temporarily unavailable.';
            }

            return new WP_Error('api_error', $error_message);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse Workday response for ' . $company);
        }

        if ($requested_limit > $request_limit && !empty($data['jobPostings']) && is_array($data['jobPostings'])) {
            $all_job_postings = $data['jobPostings'];
            $next_offset = ((int) $request_body['offset']) + count($data['jobPostings']);
            $reported_total = isset($data['total']) ? (int) $data['total'] : 0;

            while (count($all_job_postings) < $requested_limit) {
                if ($reported_total > 0 && $next_offset >= $reported_total) {
                    break;
                }

                $page_body = $request_body;
                $page_body['offset'] = $next_offset;
                $page_body['limit'] = min(20, $requested_limit - count($all_job_postings));

                $page_response = $this->post_workday_jobs_request($instance, $page_body);

                if (is_wp_error($page_response) || wp_remote_retrieve_response_code($page_response) !== 200) {
                    break;
                }

                $page_data = json_decode(wp_remote_retrieve_body($page_response), true);
                if (json_last_error() !== JSON_ERROR_NONE || empty($page_data['jobPostings']) || !is_array($page_data['jobPostings'])) {
                    break;
                }

                $all_job_postings = array_merge($all_job_postings, $page_data['jobPostings']);
                $next_offset += count($page_data['jobPostings']);

                if (count($page_data['jobPostings']) < (int) $page_body['limit']) {
                    break;
                }
            }

            $data['jobPostings'] = array_slice($all_job_postings, 0, $requested_limit);
            $data['total'] = max((int) ($data['total'] ?? 0), count($data['jobPostings']));
        }

        // Process and enrich the job data
        $jobs = $this->process_job_data($data, $company, $instance);

        // Filter out old jobs based on posted_date
        $jobs = $this->filter_old_jobs($jobs);

        // Cache the results only if we got valid data
        if (!empty($jobs['jobs'])) {
            set_transient($cache_key, $jobs, $this->cache_duration);
        }

        return $jobs;
    }

    /**
     * Filter out jobs older than the configured max age
     * This prevents old jobs from ever entering the system
     *
     * @param array $jobs The jobs array with 'jobs' key containing job data
     * @return array Filtered jobs array
     */
    private function filter_old_jobs($jobs)
    {
        if (empty($jobs['jobs'])) {
            return $jobs;
        }

        // Get configurable max job age (default 30 days)
        $max_job_age_days = intval(get_option('sffc_max_job_age_days', 30));
        $cutoff_timestamp = strtotime("-{$max_job_age_days} days");

        $filtered_jobs = [];
        $filtered_count = 0;

        foreach ($jobs['jobs'] as $job) {
            $posted_date = $job['posted_date'] ?? '';

            // If no posted_date, include the job (benefit of the doubt)
            if (empty($posted_date)) {
                $filtered_jobs[] = $job;
                continue;
            }

            // Parse the posted date
            $job_timestamp = strtotime($posted_date);

            // If we can't parse the date, include the job
            if ($job_timestamp === false) {
                $filtered_jobs[] = $job;
                continue;
            }

            // Only include jobs newer than the cutoff
            if ($job_timestamp >= $cutoff_timestamp) {
                $filtered_jobs[] = $job;
            } else {
                $filtered_count++;
            }
        }

        // Log if we filtered any jobs
        if ($filtered_count > 0) {
            error_log(sprintf(
                '[SFFC Workday] Filtered out %d jobs older than %d days from %s',
                $filtered_count,
                $max_job_age_days,
                $jobs['company'] ?? 'unknown'
            ));
        }

        $jobs['jobs'] = $filtered_jobs;
        $jobs['total'] = count($filtered_jobs);
        $jobs['filtered_old_jobs'] = $filtered_count;

        return $jobs;
    }

    /**
     * Process and enrich job data
     */
    private function process_job_data($data, $company, $instance)
    {
        $processed_jobs = [];

        if (!isset($data['jobPostings']) || !is_array($data['jobPostings'])) {
            return [
                'total' => 0,
                'jobs' => [],
                'company' => $company,
                'fetched_at' => current_time('mysql')
            ];
        }

        foreach ($data['jobPostings'] as $job) {
            $job_id = $this->extract_job_id($job);
            $application_url = $instance['base_url'] . $instance['careers_path'] . $job['externalPath'];
            $processed_job = [
                'id' => $job_id,
                'external_id' => $job_id,
                'title' => isset($job['title']) ? $job['title'] : '',
                'company' => $this->get_company_name($company),
                'location' => isset($job['locationsText']) ? $job['locationsText'] : '',
                'url' => $application_url,
                'application_url' => $application_url,
                'posted_date' => isset($job['postedOn']) ? $job['postedOn'] : '',
                'job_family' => isset($job['jobFamily']) ? $job['jobFamily'] : '',
                'time_type' => isset($job['timeType']) ? $job['timeType'] : 'Full-time',
                'source' => 'workday',
                'source_platform' => 'Workday',
                'source_name' => $this->get_company_name($company),
                'source_type' => $instance['source_type'] ?? $this->get_workday_source_type($company),
                'company_key' => $company
            ];

            // Extract additional fields from bulletFields
            if (isset($job['bulletFields']) && is_array($job['bulletFields'])) {
                $processed_job['reference_id'] = $job['bulletFields'][0] ?? '';
                $processed_job['department'] = $job['bulletFields'][1] ?? '';
            }

            // Use intelligent salary estimation
            if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php')) {
                require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php';
                $estimator = SFFC_Intelligent_Salary_Estimator::get_instance();

                // Prepare job data for estimator
                $job_data_for_estimator = [
                    'title' => $processed_job['title'],
                    'location' => $processed_job['location'] ?? '',
                    'company' => $company,
                    'description' => $processed_job['description'] ?? '',
                    'qualifications' => $processed_job['qualifications'] ?? '',
                    'responsibilities' => $processed_job['responsibilities'] ?? '',
                    'skills' => $processed_job['skills'] ?? []
                ];

                $processed_job['estimated_salary'] = $estimator->estimate_salary($job_data_for_estimator);
            } else {
                // Fallback to simple estimation
                $processed_job['estimated_salary'] = $this->estimate_salary($processed_job['title'], $company);
            }

            // Add job level
            $processed_job['level'] = $this->determine_job_level($processed_job['title']);
            $processed_job['seniority'] = $processed_job['level'];

            // Add match score placeholder
            $processed_job['match_score'] = 0;

            // FETCH FULL JOB DETAILS
            $job_details = $this->fetch_job_details($job, $instance, $company);
            if ($job_details) {
                // Merge in the detailed information
                $processed_job = array_merge($processed_job, $job_details);
            } else {
                // Fallback: Extract skills from what we have
                error_log('SFFC: Using fallback skill extraction for ' . $processed_job['title']);

                // Build text to analyze from all available fields
                $text_parts = [
                    $processed_job['title'],
                    $processed_job['job_family'] ?? '',
                    $processed_job['department'] ?? '',
                    $processed_job['location'] ?? ''
                ];

                // Add job level indicators to help with skill extraction
                if (strpos(strtolower($processed_job['title']), 'senior') !== false) {
                    $text_parts[] = 'senior level experience leadership';
                }
                if (strpos(strtolower($processed_job['title']), 'analyst') !== false) {
                    $text_parts[] = 'financial analysis excel modeling';
                }
                if (strpos(strtolower($processed_job['title']), 'manager') !== false) {
                    $text_parts[] = 'management team leadership';
                }
                if (
                    strpos(strtolower($processed_job['title']), 'developer') !== false ||
                    strpos(strtolower($processed_job['title']), 'engineer') !== false
                ) {
                    $text_parts[] = 'software development programming';
                }

                $text_to_analyze = implode(' ', array_filter($text_parts));
                $extracted_skills = $this->extract_skills($text_to_analyze);

                // Always ensure we have at least some basic skills based on title
                if (empty($extracted_skills)) {
                    $extracted_skills = $this->extract_basic_skills_from_title($processed_job['title']);
                }

                if (!empty($extracted_skills)) {
                    $processed_job['skills'] = $extracted_skills;
                    error_log('SFFC: Extracted ' . count($extracted_skills) . ' skills from fallback');
                } else {
                    $processed_job['skills'] = [];
                    error_log('SFFC: No skills extracted from fallback');
                }

                // Set empty fields so they still exist
                $processed_job['description'] = '';
                $processed_job['responsibilities'] = '';
                $processed_job['qualifications'] = '';
            }

            $processed_jobs[] = $processed_job;
        }

        return [
            'total' => isset($data['total']) ? $data['total'] : count($processed_jobs),
            'jobs' => $processed_jobs,
            'facets' => isset($data['facets']) ? $data['facets'] : [],
            'company' => $company,
            'fetched_at' => current_time('mysql')
        ];
    }

    /**
     * Extract job ID from job data
     */
    private function extract_job_id($job)
    {
        if (isset($job['bulletFields'][0])) {
            return $job['bulletFields'][0];
        }
        if (isset($job['externalPath'])) {
            preg_match('/_(\d+)$/', $job['externalPath'], $matches);
            return isset($matches[1]) ? $matches[1] : md5($job['externalPath']);
        }
        return uniqid();
    }

    /**
     * Get company display name
     */
    private function get_company_name($company_key)
    {
        $company_names = [
            'blackstone' => 'Blackstone',
            'barings' => 'Barings',
            'moelis' => 'Moelis & Company',
            'houlihan_lokey' => 'Houlihan Lokey',
            'rothschild' => 'Rothschild & Co',
            'lloyds' => 'Lloyds Banking Group',
            'santander' => 'Santander',
            'cibc' => 'CIBC',
            'statestreet' => 'State Street',
            'fca' => 'FCA',
            'aviva' => 'Aviva',
            'mfs' => 'MFS Investment Management',
            'jpmorgan' => 'J.P. Morgan',
            'morganstanley' => 'Morgan Stanley',
            'bankofamerica' => 'Bank of America',
            'juliusbaer' => 'Julius Baer',
            'juliusbaer_uae' => 'Julius Baer UAE',
            'capitalgroup' => 'Capital Group',
            'quilter' => 'Quilter',
            'cdpq' => 'CDPQ',
            'cdpq_infra' => 'CDPQ Infra',
            'robeco' => 'Robeco',
            'three_i_intern_career' => '3i',
            'icg_external_careers' => 'ICG',
            'harbourvest_hvp' => 'HarbourVest Partners',
            'capitaland_development' => 'CapitaLand Development',
            'keppel_careers' => 'Keppel',
            'cambridge_associates' => 'Cambridge Associates',
            'ontario_teachers_london' => 'Ontario Teachers London',
            'bain_capital_public' => 'Bain Capital',
            'kkr' => 'KKR',
            'apollo' => 'Apollo Global Management',
            'carlyle' => 'The Carlyle Group',
            'blackrock' => 'BlackRock',
            'citi' => 'Citi',
            'deutschebank' => 'Deutsche Bank',
            'ubs' => 'UBS',
            'bnpparibas' => 'BNP Paribas',
            'brookfield' => 'Brookfield',
            'investpsp' => 'PSP Investments',
            'pru_pgim' => 'PGIM',
            'hamiltonlane' => 'Hamilton Lane',
            'higcapital' => 'H.I.G. Capital',
            'lgtcp' => 'LGT Capital Partners',
            'oaktree' => 'Oaktree Capital Management',
            'tritonpartners' => 'Triton Partners',
            'brevanhoward' => 'Brevan Howard',
            'dimensional' => 'Dimensional Fund Advisors',
            'aquilacapital' => 'Aquila Capital',
            'apexgroup_middle_east' => 'Apex Group'
        ];

        return isset($company_names[$company_key]) ? $company_names[$company_key] : ucfirst($company_key);
    }

    private function get_workday_source_type($company_key)
    {
        $banking_feeds = [
            'fca',
            'imf',
            'ubs',
            'deutschebank',
            'citi',
            'bankofamerica',
            'morganstanley',
            'jpmorgan',
            'statestreet',
            'cibc',
            'santander',
            'lloyds',
            'rothschild',
            'houlihan_lokey',
            'moelis',
            'pru_pgim',
        ];

        return in_array($company_key, $banking_feeds, true) ? 'banking' : 'job_aggregator';
    }

    /**
     * Estimate salary based on job title and company
     */
    private function estimate_salary($title, $company)
    {
        $title_lower = strtolower($title);

        // Regional adjustments
        $uk_companies = ['barings', 'rothschild', 'lloyds', 'fca', 'aviva'];
        $currency = in_array($company, $uk_companies) ? 'GBP' : 'USD';
        $symbol = $currency === 'GBP' ? '£' : '$';

        // PE/Boutique firms typically pay more
        $premium_firms = ['blackstone', 'kkr', 'apollo', 'carlyle', 'moelis', 'houlihan_lokey', 'rothschild'];
        $multiplier = in_array($company, $premium_firms) ? 1.3 : 1.0;

        // Base salary ranges by level
        if (strpos($title_lower, 'intern') !== false || strpos($title_lower, 'summer') !== false) {
            $base = ['min' => 85000, 'max' => 95000];
        } elseif (strpos($title_lower, 'analyst') !== false && strpos($title_lower, 'senior') === false) {
            $base = ['min' => 95000, 'max' => 125000];
        } elseif (strpos($title_lower, 'associate') !== false && strpos($title_lower, 'senior') === false) {
            $base = ['min' => 150000, 'max' => 225000];
        } elseif (strpos($title_lower, 'senior associate') !== false) {
            $base = ['min' => 200000, 'max' => 275000];
        } elseif (strpos($title_lower, 'vice president') !== false || strpos($title_lower, 'vp') !== false) {
            $base = ['min' => 275000, 'max' => 400000];
        } elseif (strpos($title_lower, 'director') !== false) {
            $base = ['min' => 350000, 'max' => 500000];
        } elseif (strpos($title_lower, 'managing director') !== false || strpos($title_lower, 'md') !== false) {
            $base = ['min' => 500000, 'max' => 1000000];
        } else {
            $base = ['min' => 100000, 'max' => 200000];
        }

        // Convert to GBP if UK company (rough conversion)
        if ($currency === 'GBP') {
            $base['min'] = $base['min'] * 0.8;
            $base['max'] = $base['max'] * 0.8;
        }

        return [
            'min' => $base['min'] * $multiplier,
            'max' => $base['max'] * $multiplier,
            'currency' => $currency,
            'display' => $symbol . number_format($base['min'] * $multiplier / 1000) . 'k - ' .
                $symbol . number_format($base['max'] * $multiplier / 1000) . 'k'
        ];
    }

    /**
     * Determine job level from title
     */
    private function determine_job_level($title)
    {
        $title_lower = strtolower($title);

        if (strpos($title_lower, 'intern') !== false || strpos($title_lower, 'summer') !== false) {
            return 'Internship';
        } elseif (strpos($title_lower, 'graduate') !== false || strpos($title_lower, 'entry') !== false) {
            return 'Graduate';
        } elseif (strpos($title_lower, 'analyst') !== false && strpos($title_lower, 'senior') === false) {
            return 'Analyst';
        } elseif (strpos($title_lower, 'associate') !== false && strpos($title_lower, 'senior') === false) {
            return 'Associate';
        } elseif (strpos($title_lower, 'senior') !== false) {
            return 'Senior';
        } elseif (strpos($title_lower, 'vp') !== false || strpos($title_lower, 'vice president') !== false) {
            return 'VP';
        } elseif (strpos($title_lower, 'director') !== false && strpos($title_lower, 'managing') === false) {
            return 'Director';
        } elseif (strpos($title_lower, 'managing director') !== false || strpos($title_lower, 'md') !== false) {
            return 'MD';
        } elseif (strpos($title_lower, 'partner') !== false) {
            return 'Partner';
        }

        return 'Professional';
    }

    /**
     * Debug method to test job detail fetching
     */
    public function debug_fetch_details($company = 'blackstone', $limit = 1)
    {
        error_log('=== SFFC DEBUG: Testing Workday detail fetching for ' . $company . ' ===');

        // Get basic jobs first
        $result = $this->get_jobs($company, ['limit' => $limit]);

        if (is_wp_error($result)) {
            error_log('SFFC DEBUG: Failed to get jobs: ' . $result->get_error_message());
            return false;
        }

        if (empty($result['jobs'])) {
            error_log('SFFC DEBUG: No jobs returned');
            return false;
        }

        $job = $result['jobs'][0];
        error_log('SFFC DEBUG: Got job: ' . $job['title']);
        error_log('SFFC DEBUG: Job has the following fields:');

        // Log what fields the job has
        foreach ($job as $key => $value) {
            if (is_array($value)) {
                error_log('  - ' . $key . ': [Array with ' . count($value) . ' items]');
            } elseif (is_string($value) && strlen($value) > 100) {
                error_log('  - ' . $key . ': [String with ' . strlen($value) . ' chars]');
            } else {
                error_log('  - ' . $key . ': ' . json_encode($value));
            }
        }

        // Check specific enhanced fields
        error_log('SFFC DEBUG: Enhanced field check:');
        error_log('  - Has description: ' . (!empty($job['description']) ? 'YES (' . strlen($job['description']) . ' chars)' : 'NO'));
        error_log('  - Has responsibilities: ' . (!empty($job['responsibilities']) ? 'YES' : 'NO'));
        error_log('  - Has qualifications: ' . (!empty($job['qualifications']) ? 'YES' : 'NO'));
        error_log('  - Has skills: ' . (!empty($job['skills']) ? 'YES (' . count($job['skills']) . ' skills)' : 'NO'));

        return $job;
    }

    /**
     * Test all configured Workday instances
     */
    public function test_all_instances()
    {
        $results = [
            'working' => [],
            'failed' => [],
            'summary' => []
        ];

        foreach ($this->workday_instances as $company => $instance) {
            $test = $this->test_connection($company);

            if ($test['success']) {
                $results['working'][$company] = [
                    'total_jobs' => $test['total_jobs'],
                    'sample_title' => isset($test['sample_job']['title']) ? $test['sample_job']['title'] : 'N/A'
                ];
            } else {
                $results['failed'][$company] = $test['error'];
            }
        }

        $results['summary'] = [
            'total_tested' => count($this->workday_instances),
            'working' => count($results['working']),
            'failed' => count($results['failed']),
            'success_rate' => round((count($results['working']) / count($this->workday_instances)) * 100, 2) . '%'
        ];

        return $results;
    }

    /**
     * Fetch detailed job information from Workday
     */
    private function fetch_job_details($job, $instance, $company)
    {
        // Log that we're attempting to fetch details
        error_log('SFFC: Fetching job details for ' . $company . ' - ' . ($job['title'] ?? 'Unknown'));

        // Extract job ID from external path
        if (!isset($job['externalPath'])) {
            error_log('SFFC: No externalPath for job');
            return null;
        }

        // Get job ID from path - try multiple patterns
        $job_id = null;
        $external_path = $job['externalPath'];

        // Pattern 1: 32-character hex ID at the end
        if (preg_match('/([a-f0-9]{32})$/', $external_path, $matches)) {
            $job_id = $matches[1];
        }
        // Pattern 2: After last slash
        elseif (preg_match('/\/([^\/]+)$/', $external_path, $matches)) {
            $job_id = $matches[1];
        }
        // Pattern 3: After underscore
        elseif (preg_match('/_([A-Z0-9\-]+)$/', $external_path, $matches)) {
            $job_id = $matches[1];
        }

        if (!$job_id) {
            error_log('SFFC: Could not extract job ID from path: ' . $external_path);
            return null;
        }

        error_log('SFFC: Extracted job ID: ' . $job_id);

        // Build job detail API URL
        $base_parts = parse_url($instance['endpoint']);
        $path_parts = explode('/jobs', $base_parts['path']);
        $detail_api_url = $instance['base_url'] . $path_parts[0] . '/job/' . $job_id;

        // Fetch job details
        $response = wp_remote_get($detail_api_url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => $instance['base_url'] . $instance['careers_path']
            ],
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            error_log('Workday detail fetch error for ' . $company . ' job ' . $job_id . ': ' . $response->get_error_message());
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('SFFC: Detail API returned ' . $response_code . ' for ' . $detail_api_url);

            // Try alternative endpoint format
            $alt_url = $instance['base_url'] . '/wday/cxs/' . $company . '/job/' . $job_id;
            error_log('SFFC: Trying alternative URL: ' . $alt_url);

            $response = wp_remote_get($alt_url, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0'
                ],
                'sslverify' => false
            ]);

            $alt_code = wp_remote_retrieve_response_code($response);
            if (is_wp_error($response) || $alt_code !== 200) {
                error_log('SFFC: Alternative URL also failed with code ' . $alt_code);
                return null;
            }
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['jobPostingInfo'])) {
            // Log what we got for debugging
            error_log('Workday detail response for ' . $company . ' job ' . $job_id . ' - No jobPostingInfo. Keys: ' . implode(', ', array_keys($data ?: [])));
            return null;
        }

        $job_info = $data['jobPostingInfo'];
        $details = [];

        // Extract job description
        if (isset($job_info['jobDescription'])) {
            $description = $this->clean_html($job_info['jobDescription']);
            $details['description'] = $description;

            // Parse sections from description
            $parsed = $this->parse_job_sections($description);

            if (!empty($parsed['responsibilities'])) {
                $details['responsibilities'] = $parsed['responsibilities'];
            }

            if (!empty($parsed['qualifications'])) {
                $details['qualifications'] = $parsed['qualifications'];
            }

            if (!empty($parsed['requirements'])) {
                $details['requirements'] = $parsed['requirements'];
            }

            // Extract skills from text
            $skills = $this->extract_skills($description);
            if (!empty($skills)) {
                $details['skills'] = $skills;
            }
        }

        // Extract additional description
        if (isset($job_info['additionalJobDescription'])) {
            $details['additional_info'] = $this->clean_html($job_info['additionalJobDescription']);
        }

        // Extract other fields if available
        if (isset($job_info['timeType'])) {
            $details['time_type'] = $job_info['timeType'];
        }

        if (isset($job_info['workerSubType'])) {
            $details['worker_sub_type'] = $job_info['workerSubType'];
        }

        return $details;
    }

    /**
     * Clean HTML content
     */
    private function clean_html($html)
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

        // Convert breaks to newlines
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/li>/i', "\n", $html);

        // Remove HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Parse job description into sections
     */
    private function parse_job_sections($text)
    {
        $sections = [
            'overview' => '',
            'responsibilities' => '',
            'qualifications' => '',
            'requirements' => ''
        ];

        // Common section headers
        $patterns = [
            'responsibilities' => '/(?:responsibilities|duties|what you.?ll do|role|key activities|accountabilities)/i',
            'qualifications' => '/(?:qualifications|what we.?re looking for|ideal candidate|we.?re looking for)/i',
            'requirements' => '/(?:requirements|required|must have|essential|minimum|skills and experience)/i'
        ];

        // Split text into lines
        $lines = explode("\n", $text);
        $current_section = 'overview';
        $section_content = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if this line is a section header
            $found_section = false;
            foreach ($patterns as $section => $pattern) {
                if (preg_match($pattern, $line)) {
                    // Save previous section
                    if (!empty($section_content)) {
                        $sections[$current_section] = implode("\n", $section_content);
                    }

                    $current_section = $section;
                    $section_content = [];
                    $found_section = true;
                    break;
                }
            }

            if (!$found_section) {
                $section_content[] = $line;
            }
        }

        // Save last section
        if (!empty($section_content)) {
            $sections[$current_section] = implode("\n", $section_content);
        }

        return $sections;
    }

    /**
     * Extract skills from job description
     */
    private function extract_skills($text)
    {
        $skills = [];

        // Comprehensive finance skills for UK/EU market
        $technical_patterns = [
            // Programming & Data
            'Python',
            'R',
            'SQL',
            'VBA',
            'MATLAB',
            'SAS',
            'Stata',
            'SPSS',
            'C\+\+',
            'C#',
            'Java',
            'JavaScript',
            'Julia',
            'Scala',
            'Go',
            'Rust',
            'TypeScript',
            'PHP',
            'Ruby',
            'Perl',
            'Shell',
            'Bash',
            'PowerShell',
            'NoSQL',
            'MongoDB',
            'PostgreSQL',
            'MySQL',
            'Oracle SQL',
            'T-SQL',
            'PL/SQL',
            'Hadoop',
            'Spark',

            // Microsoft Office & Productivity
            'Excel',
            'PowerPoint',
            'Word',
            'Outlook',
            'Access',
            'Project',
            'Visio',
            'OneNote',
            'Teams',
            'Advanced Excel',
            'VBA Macros',
            'Power Query',
            'Power Pivot',
            'Google Sheets',
            'Google Suite',

            // Financial Data Platforms
            'Bloomberg Terminal',
            'Bloomberg',
            'Reuters',
            'Refinitiv',
            'Eikon',
            'FactSet',
            'Capital IQ',
            'S&P Capital IQ',
            'Morningstar Direct',
            'Pitchbook',
            'Preqin',
            'Dealogic',
            'ThomsonOne',
            'QUICK',
            'Fidessa',
            'Charles River',
            'SimCorp',
            'Murex',
            'Calypso',
            'Summit',
            'Sophis',
            'Numerix',
            'MSCI Barra',
            'RiskMetrics',
            'Axioma',
            'BlackRock Aladdin',
            'Aladdin',

            // Business Intelligence & Analytics
            'Tableau',
            'Power BI',
            'QlikView',
            'Qlik Sense',
            'Looker',
            'Alteryx',
            'Spotfire',
            'SAS',
            'IBM Cognos',
            'MicroStrategy',
            'Domo',
            'Sisense',
            'ThoughtSpot',
            'DataRobot',
            'H2O.ai',

            // ERP & Accounting Systems
            'SAP',
            'Oracle',
            'NetSuite',
            'Workday',
            'Microsoft Dynamics',
            'Salesforce',
            'HubSpot',
            'QuickBooks',
            'Xero',
            'Sage',
            'FreshBooks',
            'Wave',
            'Zoho Books',
            'MYOB',
            'Intuit',
            'PeopleSoft',
            'JD Edwards',
            'Hyperion',
            'Anaplan',
            'Adaptive Insights',
            'OneStream',

            // Financial Concepts & Techniques
            'DCF',
            'Discounted Cash Flow',
            'LBO',
            'Leveraged Buyout',
            'M&A',
            'Mergers and Acquisitions',
            'Valuation',
            'Financial Modeling',
            'Financial Modelling',
            'Three Statement Model',
            'Risk Management',
            'Portfolio Management',
            'Asset Management',
            'Wealth Management',
            'Investment Management',
            'Fund Management',
            'Hedge Fund',
            'Private Equity',
            'Venture Capital',
            'Investment Banking',
            'Corporate Finance',
            'Project Finance',
            'Structured Finance',
            'Trade Finance',
            'Treasury Management',
            'Cash Management',
            'Working Capital Management',
            'Credit Analysis',
            'Credit Risk',
            'Market Risk',
            'Operational Risk',
            'Liquidity Risk',
            'Counterparty Risk',
            'Regulatory Risk',
            'Compliance Risk',
            'Basel III',
            'Basel IV',
            'IFRS',
            'GAAP',
            'UK GAAP',
            'US GAAP',
            'SOX',
            'Sarbanes-Oxley',
            'MiFID II',
            'MiFID',
            'AIFMD',
            'UCITS',
            'Solvency II',
            'CRD IV',
            'CRD V',
            'EMIR',
            'SFTR',
            'CSDR',
            'Due Diligence',
            'KYC',
            'AML',
            'Anti-Money Laundering',
            'Know Your Customer',
            'Underwriting',
            'Origination',
            'Syndication',
            'Securitization',
            'Securitisation',
            'Derivatives',
            'Options',
            'Futures',
            'Swaps',
            'FX',
            'Forex',
            'Foreign Exchange',
            'Fixed Income',
            'Equities',
            'Commodities',
            'Credit',
            'Rates',
            'Bonds',
            'Stocks',
            'ETFs',
            'Mutual Funds',
            'Alternative Investments',
            'Real Estate',
            'Infrastructure',
            'Distressed Debt',
            'High Yield',
            'Investment Grade',
            'Emerging Markets',
            'Frontier Markets',
            'ESG',
            'Sustainable Finance',
            'Green Finance',
            'Impact Investing',
            'SRI',
            'Responsible Investing',
            'Quantitative Analysis',
            'Quantitative Finance',
            'Quant',
            'Algorithmic Trading',
            'Algo Trading',
            'High Frequency Trading',
            'HFT',
            'Market Making',
            'Arbitrage',
            'Statistical Arbitrage',
            'Pairs Trading',
            'Mean Reversion',
            'Momentum Trading',
            'Factor Investing',
            'Smart Beta',
            'Black-Scholes',
            'Monte Carlo',
            'Monte Carlo Simulation',
            'VaR',
            'Value at Risk',
            'CVaR',
            'Expected Shortfall',
            'Stress Testing',
            'Scenario Analysis',
            'Sensitivity Analysis',
            'Greeks',
            'Delta',
            'Gamma',
            'Vega',
            'Theta',
            'Rho',
            'Duration',
            'Convexity',
            'DV01',
            'ALM',
            'Asset Liability Management',
            'Capital Allocation',
            'RAROC',
            'RORAC',
            'EVA',
            'Transfer Pricing',
            'Fund Transfer Pricing',
            'FTP',
            'Collateral Management',
            'Prime Brokerage',
            'Securities Lending',
            'Repo',
            'Reverse Repo',
            'Stock Loan',

            // UK/EU Specific Terms
            'FCA',
            'PRA',
            'Bank of England',
            'ECB',
            'European Central Bank',
            'BaFin',
            'AMF',
            'CSSF',
            'CBI',
            'DNB',
            'FINMA',
            'FSA',
            'LSE',
            'London Stock Exchange',
            'Euronext',
            'Deutsche Börse',
            'SIX Swiss Exchange',
            'Borsa Italiana',
            'BME',
            'OMX',
            'Nasdaq Nordic',
            'FTSE',
            'FTSE 100',
            'FTSE 250',
            'DAX',
            'CAC 40',
            'STOXX',
            'Euro Stoxx',
            'SMI',
            'AIM',
            'Alternative Investment Market',
            'Main Market',
            'Premium Listing',
            'Standard Listing',
            'Prospectus Directive',
            'Market Abuse Regulation',
            'MAR',
            'PRIIPs',
            'GDPR',
            'Brexit',
            'Passporting',
            'Equivalence',
            'Third Country',
            'QIAIF',
            'ICAV',
            'OEIC',
            'Unit Trust',
            'Investment Trust',
            'SICAV',
            'FCP',
            'RAIF',
            'SIF',
            'ELTIF',
            'Gilt',
            'Gilts',
            'Bund',
            'Bunds',
            'OAT',
            'BTP',
            'Bonos',
            'T-Bill',
            'Treasury Bill',

            // Professional Qualifications
            'CFA',
            'Chartered Financial Analyst',
            'FRM',
            'Financial Risk Manager',
            'CAIA',
            'Chartered Alternative Investment Analyst',
            'PRM',
            'Professional Risk Manager',
            'CPA',
            'Certified Public Accountant',
            'ACCA',
            'Association of Chartered Certified Accountants',
            'ACA',
            'Associate Chartered Accountant',
            'ICAEW',
            'CIMA',
            'Chartered Institute of Management Accountants',
            'CIPFA',
            'Chartered Institute of Public Finance and Accountancy',
            'CA',
            'Chartered Accountant',
            'CTA',
            'Chartered Tax Adviser',
            'TEP',
            'Trust and Estate Practitioner',
            'IMC',
            'Investment Management Certificate',
            'IOC',
            'Investment Operations Certificate',
            'IAD',
            'International Advanced Diploma',
            'CISI',
            'Chartered Institute for Securities & Investment',
            'CII',
            'Chartered Insurance Institute',
            'ACII',
            'Associate of the Chartered Insurance Institute',
            'APMI',
            'Associate of the Pensions Management Institute',
            'PMI',
            'Pensions Management Institute',
            'Series 7',
            'Series 63',
            'Series 65',
            'Series 66',
            'Series 79',
            'Series 86',
            'Series 87',
            'SII',
            'Securities Industry Institute',
            'CeMAP',
            'Certificate in Mortgage Advice and Practice',
            'DipFA',
            'Diploma for Financial Advisers',
            'PRINCE2',
            'PMP',
            'Project Management Professional',
            'Agile',
            'Scrum',
            'Six Sigma',
            'Lean',
            'Lean Six Sigma',
            'ITIL',
            'COBIT',

            // Languages (important for EU roles)
            'English',
            'French',
            'German',
            'Spanish',
            'Italian',
            'Dutch',
            'Portuguese',
            'Polish',
            'Russian',
            'Mandarin',
            'Cantonese',
            'Japanese',
            'Arabic',
            'Hindi',
            'Korean',
            'Fluent English',
            'Native English',
            'Business English',
            'Fluent French',
            'Fluent German',
            'Multilingual',
            'Bilingual',
            'Native Speaker'
        ];

        foreach ($technical_patterns as $skill) {
            if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text)) {
                $skills[] = $skill;
            }
        }

        return array_unique($skills);
    }

    /**
     * Extract basic skills from job title when detailed extraction fails
     */
    private function extract_basic_skills_from_title($title)
    {
        $skills = [];
        $title_lower = strtolower($title);

        // Common role-based skills mapping
        $role_skills = [
            'analyst' => ['Financial Analysis', 'Excel', 'Financial Modeling', 'Data Analysis', 'Research'],
            'associate' => ['Financial Modeling', 'Valuation', 'Excel', 'PowerPoint', 'Due Diligence'],
            'manager' => ['Team Management', 'Leadership', 'Strategic Planning', 'Project Management', 'Stakeholder Management'],
            'director' => ['Leadership', 'Strategic Planning', 'Business Development', 'P&L Management', 'Team Leadership'],
            'vp' => ['Leadership', 'Strategic Planning', 'Business Development', 'Relationship Management', 'Executive Communication'],
            'developer' => ['Software Development', 'Programming', 'Problem Solving', 'Debugging', 'Version Control'],
            'engineer' => ['Software Engineering', 'System Design', 'Problem Solving', 'Technical Documentation', 'Testing'],
            'trader' => ['Trading', 'Risk Management', 'Market Analysis', 'Bloomberg Terminal', 'Excel'],
            'quant' => ['Quantitative Analysis', 'Python', 'Mathematics', 'Statistics', 'Risk Management'],
            'compliance' => ['Regulatory Compliance', 'Risk Management', 'AML', 'KYC', 'Regulatory Reporting'],
            'risk' => ['Risk Management', 'Risk Analysis', 'Basel III', 'Credit Risk', 'Market Risk'],
            'portfolio' => ['Portfolio Management', 'Asset Management', 'Investment Analysis', 'Risk Management', 'Bloomberg'],
            'investment' => ['Investment Analysis', 'Financial Modeling', 'Valuation', 'Due Diligence', 'Market Research'],
            'credit' => ['Credit Analysis', 'Risk Assessment', 'Financial Analysis', 'Due Diligence', 'Underwriting'],
            'operations' => ['Operations Management', 'Process Improvement', 'Project Management', 'Data Analysis', 'Problem Solving'],
            'finance' => ['Financial Analysis', 'Financial Reporting', 'Budgeting', 'Forecasting', 'Excel'],
            'accounting' => ['Accounting', 'Financial Reporting', 'GAAP', 'Audit', 'Tax'],
            'audit' => ['Auditing', 'Risk Assessment', 'Internal Controls', 'SOX Compliance', 'Financial Analysis'],
            'tax' => ['Tax Planning', 'Tax Compliance', 'Tax Research', 'International Tax', 'Transfer Pricing']
        ];

        // Check for each role keyword
        foreach ($role_skills as $keyword => $role_specific_skills) {
            if (strpos($title_lower, $keyword) !== false) {
                $skills = array_merge($skills, $role_specific_skills);
            }
        }

        // Add level-specific skills
        if (strpos($title_lower, 'senior') !== false) {
            $skills[] = 'Leadership';
            $skills[] = 'Mentoring';
        }
        if (strpos($title_lower, 'junior') !== false || strpos($title_lower, 'entry') !== false) {
            $skills[] = 'Microsoft Office';
            $skills[] = 'Attention to Detail';
        }
        if (strpos($title_lower, 'head') !== false || strpos($title_lower, 'chief') !== false) {
            $skills[] = 'Executive Leadership';
            $skills[] = 'Strategic Vision';
        }

        // Add location-specific skills
        if (strpos($title_lower, 'global') !== false || strpos($title_lower, 'international') !== false) {
            $skills[] = 'Cross-border Experience';
            $skills[] = 'International Markets';
        }

        return array_unique($skills);
    }

    /**
     * Get working companies only
     */
    public function get_working_companies()
    {
        $working = [];

        foreach ($this->workday_instances as $company => $instance) {
            if ($instance['status'] === 'working' || $instance['status'] === 'new') {
                $working[] = $company;
            }
        }

        return $working;
    }

    /**
     * Get aggregated jobs from working companies only
     */
    public function get_reliable_jobs($filters = [])
    {
        // Use only confirmed working companies
        $reliable_companies = [
            'blackstone',
            'barings',
            'moelis',
            'houlihan_lokey',
            'rothschild',
            'statestreet',
            'aviva',
            'mfs'
        ];

        $all_jobs = [];
        $errors = [];

        foreach ($reliable_companies as $company) {
            $result = $this->get_jobs($company, $filters);

            if (!is_wp_error($result) && !empty($result['jobs'])) {
                $all_jobs = array_merge($all_jobs, $result['jobs']);
            } elseif (is_wp_error($result)) {
                $errors[$company] = $result->get_error_message();
            }
        }

        return [
            'total' => count($all_jobs),
            'jobs' => $all_jobs,
            'sources' => count($reliable_companies) - count($errors),
            'errors' => $errors,
            'fetched_at' => current_time('mysql')
        ];
    }
}

// Initialize
function sffc_workday_fetcher_v2()
{
    return new SFFC_Workday_Job_Fetcher_V2();
}
