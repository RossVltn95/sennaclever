<?php

/**
 * SEO Permalinks Manager
 * 
 * Creates SEO-optimized URL structures for programmatic content
 * Handles rewrite rules without impacting performance
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_SEO_Permalinks
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Only set up permalinks on init, not on every page load
        add_action('init', [$this, 'add_rewrite_rules'], 10);
        add_filter('post_type_link', [$this, 'custom_job_permalink'], 10, 2);
        add_filter('post_type_link', [$this, 'custom_company_permalink'], 10, 2);
        add_filter('post_type_link', [$this, 'custom_salary_permalink'], 10, 2);
        // DISABLED - Duplicate schema causing Google indexing issues
        // The google-job-schema plugin now handles all JobPosting schema output
        // add_action('wp_head', [$this, 'maybe_output_job_schema'], 20);

        // Flush rules only when needed
        add_action('sffc_flush_rewrite_rules', [$this, 'flush_rules']);
        
        // Add query vars for search parameters
        add_filter('query_vars', [$this, 'add_search_query_vars']);
    }

    /**
     * Add rewrite rules for SEO-friendly URLs
     */
    public function add_rewrite_rules()
    {
        // Careers by location and title
        // /cariera/london/investment-banking-analyst-goldman-sachs-39129/
        add_rewrite_rule(
            'opportunities/([^/]+)/([^/]+)/?$',
            'index.php?sffc_job=$matches[2]',
            'top'
        );

        // Careers by location only
        // /cariera/london/
        add_rewrite_rule(
            'opportunities/([^/]+)/?$',
            'index.php?job_location=$matches[1]',
            'top'
        );

        // Company jobs
        // /firm/goldman-sachs/jobs/
        add_rewrite_rule(
            'firm/([^/]+)/jobs/?$',
            'index.php?sffc_company=$matches[1]&company_section=jobs',
            'top'
        );

        // Company culture
        // /firm/goldman-sachs/culture/
        add_rewrite_rule(
            'firm/([^/]+)/culture/?$',
            'index.php?sffc_company=$matches[1]&company_section=culture',
            'top'
        );

        // Company salaries
        // /firm/goldman-sachs/salaries/
        add_rewrite_rule(
            'firm/([^/]+)/salaries/?$',
            'index.php?sffc_company=$matches[1]&company_section=salaries',
            'top'
        );

        // Search results - SEO-friendly URLs
        // /search/jobs/london-private-equity/
        add_rewrite_rule(
            'search/([^/]+)/([^/]+)/?$',
            'index.php?pagename=search-results&mode=$matches[1]&search_query=$matches[2]&search=1',
            'top'
        );

        // Search results with pagination 
        // /search/jobs/london-private-equity/page/2/
        add_rewrite_rule(
            'search/([^/]+)/([^/]+)/page/([0-9]{1,})/?$',
            'index.php?pagename=search-results&mode=$matches[1]&search_query=$matches[2]&search=1&sffc_page=$matches[3]',
            'top'
        );

        // Search by mode only
        // /search/jobs/
        add_rewrite_rule(
            'search/([^/]+)/?$',
            'index.php?pagename=search-results&mode=$matches[1]&search=1',
            'top'
        );

        // Location + job type
        // /locations/london/investment-banking-jobs/
        add_rewrite_rule(
            'locations/([^/]+)/([^/]+)-jobs/?$',
            'index.php?job_location=$matches[1]&job_industry=$matches[2]',
            'top'
        );

        // Location salary guide
        // /locations/london/salary-guide/
        add_rewrite_rule(
            'locations/([^/]+)/salary-guide/?$',
            'index.php?location_salary=$matches[1]',
            'top'
        );

        // Salary guides by role and location
        // /salary-guide/investment-banking-analyst-london/
        add_rewrite_rule(
            'salary-guide/([^/]+)-([^/]+)/?$',
            'index.php?sffc_salary_guide=$matches[1]-$matches[2]',
            'top'
        );

        // Add query vars
        add_filter('query_vars', [$this, 'add_query_vars']);
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'company_section';
        $vars[] = 'location_salary';
        $vars[] = 'search_query';
        $vars[] = 'mode';
        $vars[] = 'sffc_page';
        return $vars;
    }
    
    /**
     * Add search query vars (separate method for clarity)
     */
    public function add_search_query_vars($vars)
    {
        return $this->add_query_vars($vars);
    }

    /**
     * Custom permalink for job posts
     */
    public function custom_job_permalink($permalink, $post)
    {
        if ($post->post_type !== 'sffc_job') {
            return $permalink;
        }

        // Get location and company
        $location = get_post_meta($post->ID, 'sffc_location_city', true);
        $company = get_post_meta($post->ID, 'sffc_actual_company', true);
        $job_id = get_post_meta($post->ID, 'sffc_external_id', true);

        if (empty($location)) {
            $location = 'global';
        }

        // Use stored slug so the rewrite rule resolves correctly.
        // Appending company/ID segments here would desync with the saved
        // `post_name`, causing the front-end query to miss the post.
        $job_slug = $post->post_name;

        return home_url(sprintf(
            '/opportunities/%s/%s/',
            sanitize_title($location),
            $job_slug
        ));
    }

    /**
     * Custom permalink for company posts
     */
    public function custom_company_permalink($permalink, $post)
    {
        if ($post->post_type !== 'sffc_company') {
            return $permalink;
        }

        return home_url('/firm/' . $post->post_name . '/');
    }

    /**
     * Custom permalink for salary guide posts
     */
    public function custom_salary_permalink($permalink, $post)
    {
        if ($post->post_type !== 'sffc_salary_guide') {
            return $permalink;
        }

        return home_url('/salary-guide/' . $post->post_name . '/');
    }

    /**
     * Generate SEO meta tags for job pages
     */
    public function generate_job_meta_tags($post_id)
    {
        $job = get_post($post_id);
        if (!$job || $job->post_type !== 'sffc_job') {
            return [];
        }

        // Get job data
        $title = $job->post_title;
        $company = get_post_meta($post_id, 'sffc_actual_company', true);
        $location = get_post_meta($post_id, 'sffc_location_city', true);
        $salary = get_post_meta($post_id, 'sffc_salary_display', true);
        $posted_date = get_post_meta($post_id, 'sffc_posted_date', true);

        // Generate meta title
        $meta_title = sprintf(
            '%s at %s | %s | %s | MENA Careers Finance',
            $title,
            $company ?: 'Top Firm',
            $location ?: 'Multiple Locations',
            $salary ?: 'Competitive Salary'
        );

        // Generate meta description
        $meta_description = sprintf(
            'Apply for %s at %s in %s. %s. Posted %s. Join a leading firm in finance and advance your career.',
            $title,
            $company ?: 'a top financial firm',
            $location ?: 'prime locations',
            $salary ?: 'Excellent compensation package',
            $posted_date ? human_time_diff(strtotime($posted_date), current_time('timestamp')) . ' ago' : 'recently'
        );

        return [
            'title' => substr($meta_title, 0, 60),
            'description' => substr($meta_description, 0, 160),
            'og:title' => $meta_title,
            'og:description' => $meta_description,
            'og:type' => 'article',
            'article:published_time' => $posted_date,
            'article:modified_time' => $job->post_modified
        ];
    }

    /**
     * Output JobPosting schema when viewing a single senna job.
     */
    public function maybe_output_job_schema()
    {
        if (!is_singular('sffc_job')) {
            return;
        }

        if (post_password_required()) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $schema = $this->build_job_structured_data($post_id);
        if (empty($schema)) {
            return;
        }

        echo "\n<!-- senna JobPosting Schema -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "\n</script>\n";
    }

    /**
     * Generate structured data for jobs (returns JSON string for programmatic use).
     */
    public function generate_job_structured_data($post_id)
    {
        $schema = $this->build_job_structured_data($post_id);

        return !empty($schema)
            ? wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';
    }

    /**
     * Assemble the JobPosting schema array for a job post.
     */
    private function build_job_structured_data($post_id)
    {
        $job = get_post($post_id);
        if (!$job || $job->post_type !== 'sffc_job' || $job->post_status !== 'publish') {
            return [];
        }

        $description = $this->prepare_job_description($job);
        $employment_type = $this->normalize_employment_types(
            get_post_meta($post_id, 'sffc_time_type', true) ?: get_post_meta($post_id, 'sffc_job_type', true)
        );

        $posted_timestamp = $this->parse_datetime(
            get_post_meta($post_id, 'sffc_posted_date', true) ?: $job->post_date
        );
        $date_posted = $posted_timestamp ? wp_date('c', $posted_timestamp) : '';

        $valid_through = $this->determine_valid_through($post_id, $posted_timestamp);
        $location = $this->build_job_location($post_id);

        $remote_type = strtolower(trim((string) get_post_meta($post_id, 'sffc_remote_type', true)));
        $is_remote = ($remote_type === 'remote');
        $applicant_locations = $this->build_applicant_location_requirements($post_id, $location, $is_remote);

        if (empty($description) || empty($employment_type) || empty($date_posted)) {
            return [];
        }

        if (!$is_remote && empty($location)) {
            return [];
        }

        if ($is_remote && empty($location) && empty($applicant_locations)) {
            return [];
        }

        $direct_apply = $this->determine_direct_apply($post_id);
        $job_url = $this->determine_job_url($post_id);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => get_the_title($job),
            'description' => $description,
            'datePosted' => $date_posted,
            'validThrough' => $valid_through,
            'employmentType' => $employment_type,
            'hiringOrganization' => $this->build_hiring_organization($post_id),
            'jobLocation' => $location,
            'identifier' => $this->build_job_identifier($post_id),
            'baseSalary' => $this->build_base_salary($post_id),
            'directApply' => $direct_apply,
            'url' => $job_url,
            'applicantLocationRequirements' => $applicant_locations,
            'jobLocationType' => $is_remote ? 'TELECOMMUTE' : ($remote_type === 'hybrid' ? 'HYBRID' : null),
            'educationRequirements' => $this->clean_string(
                get_post_meta($post_id, 'sffc_education_requirements', true) ?: get_post_meta($post_id, 'sffc_education_required', true)
            ),
            'experienceRequirements' => $this->clean_string(get_post_meta($post_id, 'sffc_experience_level', true)),
            'skills' => $this->prepare_skills($post_id),
            'jobBenefits' => $this->prepare_job_benefits($post_id),
            'occupationalCategory' => $this->clean_string(get_post_meta($post_id, 'sffc_job_family', true)),
        ];

        if (!$is_remote) {
            unset($schema['applicantLocationRequirements'], $schema['jobLocationType']);
        } else {
            if (empty($schema['applicantLocationRequirements'])) {
                unset($schema['applicantLocationRequirements']);
            }
        }

        if (empty($schema['jobLocation'])) {
            unset($schema['jobLocation']);
        }

        if (empty($schema['baseSalary'])) {
            unset($schema['baseSalary']);
        }

        if (empty($schema['identifier'])) {
            unset($schema['identifier']);
        }

        if ($schema['directApply'] === null) {
            unset($schema['directApply']);
        }

        if (empty($schema['educationRequirements'])) {
            unset($schema['educationRequirements']);
        }

        if (empty($schema['experienceRequirements'])) {
            unset($schema['experienceRequirements']);
        }

        if (empty($schema['skills'])) {
            unset($schema['skills']);
        }

        if (empty($schema['jobBenefits'])) {
            unset($schema['jobBenefits']);
        }

        if (empty($schema['occupationalCategory'])) {
            unset($schema['occupationalCategory']);
        }

        if (empty($schema['validThrough'])) {
            unset($schema['validThrough']);
        }

        if (empty($schema['jobLocationType'])) {
            unset($schema['jobLocationType']);
        }

        if (empty($schema['url'])) {
            unset($schema['url']);
        }

        return $this->filter_empty_recursive($schema);
    }

    /**
     * Prepare description text for schema.
     */
    private function prepare_job_description($post)
    {
        $description = get_post_meta($post->ID, 'sffc_description', true);

        if (!$description) {
            $description = $post->post_content;
        }

        $description = wp_strip_all_tags($description);
        $description = trim(preg_replace('/\s+/', ' ', $description));

        return $description;
    }

    /**
     * Normalize employment type values for schema.
     */
    private function normalize_employment_types($value)
    {
        if (empty($value)) {
            return 'FULL_TIME';
        }

        $tokens = is_array($value)
            ? $value
            : preg_split('/[\\\\\\/|,]+/', (string) $value);

        $normalized = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            $mapped = $this->map_employment_type($token);
            if (!$mapped) {
                $mapped = $this->map_employment_type(strtolower($token));
            }

            if ($mapped) {
                $normalized[] = $mapped;
            }
        }

        $normalized = array_values(array_unique(array_filter($normalized)));

        if (empty($normalized)) {
            return 'FULL_TIME';
        }

        return count($normalized) === 1 ? $normalized[0] : $normalized;
    }

    /**
     * Parse various date formats into a Unix timestamp.
     */
    private function parse_datetime($value)
    {
        if (empty($value)) {
            return 0;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        return $timestamp ? (int) $timestamp : 0;
    }

    /**
     * Determine the validThrough date in ISO 8601.
     */
    private function determine_valid_through($post_id, $posted_timestamp)
    {
        $candidates = [
            get_post_meta($post_id, 'sffc_valid_through', true),
            get_post_meta($post_id, 'sffc_application_deadline', true),
            get_post_meta($post_id, 'sffc_expires_date', true),
            get_post_meta($post_id, 'sffc_end_date', true),
        ];

        foreach ($candidates as $candidate) {
            $timestamp = $this->parse_datetime($candidate);
            if ($timestamp) {
                return wp_date('c', $timestamp);
            }
        }

        if ($posted_timestamp) {
            return wp_date('c', $posted_timestamp + (30 * DAY_IN_SECONDS));
        }

        return '';
    }

    /**
     * Build job location payload.
     */
    private function build_job_location($post_id)
    {
        $city = $this->clean_string(get_post_meta($post_id, 'sffc_location_city', true));
        $region = $this->clean_string(
            get_post_meta($post_id, 'sffc_location_region', true) ?: get_post_meta($post_id, 'sffc_location_state', true)
        );
        $country = $this->normalize_country(get_post_meta($post_id, 'sffc_location_country', true));
        $street = $this->clean_string(get_post_meta($post_id, 'sffc_location_address', true));
        $postal = $this->clean_string(get_post_meta($post_id, 'sffc_location_postal', true));

        if (!$city && !$region && !$country && !$street && !$postal) {
            $raw_location = $this->clean_string(get_post_meta($post_id, 'sffc_location', true));
            if ($raw_location) {
                $parts = array_map('trim', explode(',', $raw_location));
                if (count($parts) === 1) {
                    $city = $parts[0];
                } elseif (count($parts) >= 2) {
                    $city = $parts[0];
                    $country = $this->normalize_country(end($parts));
                    if (count($parts) >= 3) {
                        $region = $parts[count($parts) - 2];
                    }
                }
            }
        }

        $address = ['@type' => 'PostalAddress'];

        if ($street) {
            $address['streetAddress'] = $street;
        }

        if ($city) {
            $address['addressLocality'] = $city;
        }

        if ($region) {
            $address['addressRegion'] = $region;
        }

        if ($postal) {
            $address['postalCode'] = $postal;
        }

        if ($country) {
            $address['addressCountry'] = $country;
        }

        if (count($address) === 1) {
            return [];
        }

        return [
            '@type' => 'Place',
            'address' => $address,
        ];
    }

    /**
     * Build applicant location requirements for remote roles.
     */
    private function build_applicant_location_requirements($post_id, $location, $is_remote)
    {
        if (!$is_remote) {
            return [];
        }

        $regions = [];

        $country = $this->clean_string(get_post_meta($post_id, 'sffc_location_country', true));
        $region = $this->clean_string(get_post_meta($post_id, 'sffc_location_region', true));

        if ($country) {
            $regions[] = [
                '@type' => 'Country',
                'name' => $country,
            ];
        }

        if ($region) {
            $regions[] = [
                '@type' => 'AdministrativeArea',
                'name' => $region,
            ];
        }

        if (empty($regions) && isset($location['address']['addressCountry'])) {
            $regions[] = [
                '@type' => 'Country',
                'name' => $location['address']['addressCountry'],
            ];
        }

        if (empty($regions)) {
            return [];
        }

        // Remove duplicates by serialising entries
        $deduped = [];
        $seen = [];

        foreach ($regions as $region_entry) {
            $hash = wp_json_encode($region_entry);
            if (!isset($seen[$hash])) {
                $deduped[] = $region_entry;
                $seen[$hash] = true;
            }
        }

        return $deduped;
    }

    /**
     * Build hiring organization information.
     */
    private function build_hiring_organization($post_id)
    {
        $name = $this->clean_string(
            get_post_meta($post_id, 'sffc_actual_company', true) ?: get_post_meta($post_id, 'sffc_company', true)
        );

        if (!$name) {
            $name = get_bloginfo('name');
        }

        $organization = [
            '@type' => 'Organization',
            'name' => $name,
        ];

        $logo = $this->sanitize_url(get_post_meta($post_id, 'sffc_company_logo', true));

        if (!$logo) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }

        if (!$logo) {
            $logo = get_site_icon_url();
        }

        if ($logo) {
            $organization['logo'] = esc_url_raw($logo);
        }

        $same_as = $this->sanitize_url(get_post_meta($post_id, 'sffc_company_website', true));

        if (!$same_as) {
            $same_as = $this->sanitize_url(get_post_meta($post_id, 'sffc_source_url', true));
        }

        if (!$same_as) {
            $company_terms = get_the_terms($post_id, 'job_company');
            if (!is_wp_error($company_terms) && !empty($company_terms)) {
                $same_as = esc_url_raw(home_url('/firm/' . $company_terms[0]->slug . '/'));
            }
        }

        if ($same_as) {
            $organization['sameAs'] = $same_as;
        }

        return $organization;
    }

    /**
     * Build job identifier payload.
     */
    private function build_job_identifier($post_id)
    {
        $value = $this->clean_string(
            get_post_meta($post_id, 'sffc_external_id', true) ?: get_post_meta($post_id, 'sffc_job_reference', true)
        );

        if (!$value) {
            return [];
        }

        $identifier = [
            '@type' => 'PropertyValue',
            'value' => $value,
        ];

        $name = $this->clean_string(get_post_meta($post_id, 'sffc_source_name', true));
        if (!$name) {
            $name = $this->clean_string(get_post_meta($post_id, 'sffc_actual_company', true));
        }

        if ($name) {
            $identifier['name'] = $name;
        }

        return $identifier;
    }

    /**
     * Build salary payload.
     */
    private function build_base_salary($post_id)
    {
        $min = $this->to_float(get_post_meta($post_id, 'sffc_salary_min', true));
        $max = $this->to_float(get_post_meta($post_id, 'sffc_salary_max', true));

        if (!$min && !$max) {
            return [];
        }

        $currency = $this->normalize_currency_code(get_post_meta($post_id, 'sffc_salary_currency', true));
        $unit = $this->normalize_salary_unit(get_post_meta($post_id, 'sffc_salary_period', true));

        $value = [
            '@type' => 'QuantitativeValue',
            'unitText' => $unit ?: 'YEAR',
        ];

        if ($min && $max) {
            $value['minValue'] = $min;
            $value['maxValue'] = $max;
        } elseif ($min) {
            $value['value'] = $min;
        } elseif ($max) {
            $value['value'] = $max;
        }

        return [
            '@type' => 'MonetaryAmount',
            'currency' => $currency ?: 'USD',
            'value' => $value,
        ];
    }

    /**
     * Determine if the role is direct apply.
     */
    private function determine_direct_apply($post_id)
    {
        $value = get_post_meta($post_id, 'sffc_external_apply', true);

        if ($value === '' || $value === null) {
            return null;
        }

        $external = $this->to_bool($value);

        return $external === null ? null : !$external;
    }

    /**
     * Determine best URL for the job posting.
     */
    private function determine_job_url($post_id)
    {
        $candidates = [
            get_post_meta($post_id, 'sffc_application_url', true),
            get_post_meta($post_id, 'sffc_source_url', true),
            get_permalink($post_id),
        ];

        foreach ($candidates as $url) {
            $sanitized = $this->sanitize_url($url);
            if ($sanitized) {
                return $sanitized;
            }
        }

        return '';
    }

    /**
     * Prepare skills string.
     */
    private function prepare_skills($post_id)
    {
        $skills = get_post_meta($post_id, 'sffc_skills', true);

        if (is_array($skills) && !empty($skills)) {
            $skills = array_map('trim', $skills);
        } else {
            $skills_list = get_post_meta($post_id, 'sffc_skills_list', true);
            $skills = $skills_list ? array_map('trim', explode(',', $skills_list)) : [];
        }

        $skills = array_values(array_filter($skills));

        if (empty($skills)) {
            return '';
        }

        return implode(', ', array_slice(array_unique($skills), 0, 20));
    }

    /**
     * Prepare job benefits value.
     */
    private function prepare_job_benefits($post_id)
    {
        $benefits = get_post_meta($post_id, 'sffc_benefits', true);

        if (!$benefits) {
            $benefits = get_post_meta($post_id, 'sffc_additional_info', true);
        }

        return $this->clean_string($benefits);
    }

    /**
     * Clean and trim basic strings.
     */
    private function clean_string($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $value = wp_strip_all_tags((string) $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return $value === '' ? '' : $value;
    }

    /**
     * Ensure country values are well-formed.
     */
    private function normalize_country($value)
    {
        $value = $this->clean_string($value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) === 2) {
            return strtoupper($value);
        }

        return $value;
    }

    /**
     * Normalize ISO currency codes.
     */
    private function normalize_currency_code($value)
    {
        $value = strtoupper($this->clean_string($value));

        if (preg_match('/^[A-Z]{3}$/', $value)) {
            return $value;
        }

        return '';
    }

    /**
     * Normalize salary period into schema unitText value.
     */
    private function normalize_salary_unit($value)
    {
        $value = strtoupper($this->clean_string($value));

        $map = [
            'HOUR' => 'HOUR',
            'HOURLY' => 'HOUR',
            'DAILY' => 'DAY',
            'DAY' => 'DAY',
            'WEEK' => 'WEEK',
            'WEEKLY' => 'WEEK',
            'MONTH' => 'MONTH',
            'MONTHLY' => 'MONTH',
            'ANNUAL' => 'YEAR',
            'ANNUALLY' => 'YEAR',
            'YEARLY' => 'YEAR',
            'YEAR' => 'YEAR',
            'PER YEAR' => 'YEAR',
            'PER ANNUM' => 'YEAR',
        ];

        return $map[$value] ?? 'YEAR';
    }

    /**
     * Convert strings to float values.
     */
    private function to_float($value)
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $sanitized = preg_replace('/[^0-9.\-]/', '', $value);
            return is_numeric($sanitized) ? (float) $sanitized : 0.0;
        }

        return 0.0;
    }

    /**
     * Convert various truthy/falsy values to booleans.
     */
    private function to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Recursively remove empty values from schema arrays.
     */
    private function filter_empty_recursive($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $item = $this->filter_empty_recursive($item);

            if ($item === null) {
                unset($value[$key]);
                continue;
            }

            if (is_string($item) && $item === '') {
                unset($value[$key]);
                continue;
            }

            if (is_array($item) && empty($item)) {
                unset($value[$key]);
                continue;
            }

            $value[$key] = $item;
        }

        return $value;
    }

    /**
     * Validate and sanitize URLs.
     */
    private function sanitize_url($url)
    {
        if (empty($url)) {
            return '';
        }

        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);

        return $validated ? esc_url_raw($validated) : '';
    }

    /**
     * Map employment type to schema.org format
     */
    private function map_employment_type($time_type)
    {
        $normalized = strtolower(trim((string) $time_type));

        $mapping = [
            'full-time' => 'FULL_TIME',
            'full time' => 'FULL_TIME',
            'fulltime' => 'FULL_TIME',
            'permanent' => 'FULL_TIME',
            'part-time' => 'PART_TIME',
            'part time' => 'PART_TIME',
            'parttime' => 'PART_TIME',
            'contract' => 'CONTRACTOR',
            'contractor' => 'CONTRACTOR',
            'fixed term' => 'CONTRACTOR',
            'temporary' => 'TEMPORARY',
            'temp' => 'TEMPORARY',
            'intern' => 'INTERN',
            'internship' => 'INTERN',
            'apprenticeship' => 'INTERN',
            'volunteer' => 'VOLUNTEER',
            'per diem' => 'PER_DIEM',
            'freelance' => 'CONTRACTOR',
            'seasonal' => 'TEMPORARY',
            'casual' => 'TEMPORARY',
            'other' => 'OTHER',
        ];

        if (isset($mapping[$normalized])) {
            return $mapping[$normalized];
        }

        $fallback = strtoupper(str_replace([' ', '-'], '_', trim((string) $time_type)));

        return $fallback ?: 'FULL_TIME';
    }

    /**
     * Generate breadcrumbs for SEO
     */
    public function generate_breadcrumbs($post_id = null)
    {
        $breadcrumbs = [];

        // Home
        $breadcrumbs[] = [
            'name' => 'Home',
            'url' => home_url('/')
        ];

        if ($post_id) {
            $post = get_post($post_id);

            if ($post->post_type === 'sffc_job') {
                // Careers breadcrumb
                $breadcrumbs[] = [
                    'name' => 'Opportunities',
                    'url' => home_url('/cariera/')
                ];

                // Location breadcrumb
                $location = get_post_meta($post_id, 'sffc_location_city', true);
                if ($location) {
                    $breadcrumbs[] = [
                        'name' => $location,
                        'url' => home_url('/cariera/' . sanitize_title($location) . '/')
                    ];
                }

                // Current job
                $breadcrumbs[] = [
                    'name' => $post->post_title,
                    'url' => get_permalink($post_id)
                ];
            } elseif ($post->post_type === 'sffc_company') {
                // Companies breadcrumb
                $breadcrumbs[] = [
                    'name' => 'Companies',
                    'url' => home_url('/firm/')
                ];

                // Current company
                $breadcrumbs[] = [
                    'name' => $post->post_title,
                    'url' => get_permalink($post_id)
                ];
            }
        }

        return $this->render_breadcrumbs($breadcrumbs);
    }

    /**
     * Render breadcrumbs HTML
     */
    private function render_breadcrumbs($breadcrumbs)
    {
        if (empty($breadcrumbs)) {
            return '';
        }

        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">';

        foreach ($breadcrumbs as $index => $crumb) {
            $is_last = ($index === count($breadcrumbs) - 1);

            $html .= sprintf(
                '<li class="breadcrumb-item%s" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">',
                $is_last ? ' active' : ''
            );

            if (!$is_last) {
                $html .= sprintf(
                    '<a href="%s" itemprop="item"><span itemprop="name">%s</span></a>',
                    esc_url($crumb['url']),
                    esc_html($crumb['name'])
                );
            } else {
                $html .= sprintf(
                    '<span itemprop="name">%s</span>',
                    esc_html($crumb['name'])
                );
            }

            $html .= sprintf(
                '<meta itemprop="position" content="%d" />',
                $index + 1
            );

            $html .= '</li>';
        }

        $html .= '</ol></nav>';

        return $html;
    }

    /**
     * Generate sitemap entries for jobs
     */
    public function generate_sitemap_entries()
    {
        $entries = [];

        // Get all published jobs
        $jobs = get_posts([
            'post_type' => 'sffc_job',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        foreach ($jobs as $job) {
            $entries[] = [
                'loc' => get_permalink($job->ID),
                'lastmod' => $job->post_modified,
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ];
        }

        // Get all companies
        $companies = get_posts([
            'post_type' => 'sffc_company',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        foreach ($companies as $company) {
            $entries[] = [
                'loc' => get_permalink($company->ID),
                'lastmod' => $company->post_modified,
                'changefreq' => 'weekly',
                'priority' => '0.7'
            ];
        }

        // Get all salary guides
        $guides = get_posts([
            'post_type' => 'sffc_salary_guide',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        foreach ($guides as $guide) {
            $entries[] = [
                'loc' => get_permalink($guide->ID),
                'lastmod' => $guide->post_modified,
                'changefreq' => 'monthly',
                'priority' => '0.6'
            ];
        }

        return $entries;
    }

    /**
     * Flush rewrite rules safely
     */
    public function flush_rules()
    {
        // Only flush if needed (tracked by option)
        if (get_option('sffc_flush_rules_needed')) {
            flush_rewrite_rules();
            delete_option('sffc_flush_rules_needed');
        }
    }

    /**
     * Mark that rules need flushing
     */
    public static function mark_flush_needed()
    {
        update_option('sffc_flush_rules_needed', true);
    }
    
    /**
     * Generate SEO-friendly search URL
     */
    public static function generate_search_url($query, $mode = 'jobs', $page = null)
    {
        // Clean and convert query to URL-friendly format
        $query_slug = self::convert_query_to_slug($query);
        
        // Base search URL
        if (!empty($query_slug)) {
            $url = home_url("/search/{$mode}/{$query_slug}/");
        } else {
            $url = home_url("/search/{$mode}/");
        }
        
        // Add pagination if needed
        if ($page && $page > 1) {
            $url = rtrim($url, '/') . "/page/{$page}/";
        }
        
        return $url;
    }
    
    /**
     * Convert search query to SEO-friendly slug
     */
    private static function convert_query_to_slug($query)
    {
        if (empty($query)) {
            return '';
        }
        
        // Clean the query
        $query = trim($query);
        
        // Convert to lowercase
        $query = strtolower($query);
        
        // Replace spaces and special characters with hyphens
        $query = preg_replace('/[^a-z0-9\s\-]/', '', $query);
        $query = preg_replace('/[\s\-]+/', '-', $query);
        
        // Remove leading/trailing hyphens
        $query = trim($query, '-');
        
        // Limit length for clean URLs
        $query = substr($query, 0, 100);
        
        return $query;
    }
    
    /**
     * Convert SEO slug back to search query
     */
    public static function slug_to_query($slug)
    {
        if (empty($slug)) {
            return '';
        }
        
        // Replace hyphens with spaces
        $query = str_replace('-', ' ', $slug);
        
        // Clean up multiple spaces
        $query = preg_replace('/\s+/', ' ', $query);
        
        return trim($query);
    }
}
