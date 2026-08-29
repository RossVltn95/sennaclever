<?php

/**
 * Google Job Schema - Frontend Output with Guaranteed Defaults
 *
 * DISABLED: This file has been disabled to prevent duplicate schema output.
 * The google-job-schema plugin handles schema generation with enhanced title optimization.
 * Keeping this as a backup reference only.
 *
 * @see google-job-schema/google-job-schema.php for the active implementation
 */

if (!defined('ABSPATH')) exit;

// DISABLED - Duplicate schema causing Google indexing issues
// The google-job-schema plugin now handles all JobPosting schema output
return;

add_action('wp_head', function () {
    if (is_admin()) return;

    global $post;
    if (!$post || !is_singular()) return;

    $settings = get_option('gjs_settings', []);
    $enabled_types = $settings['post_types'] ?? [];
    if (!in_array(get_post_type($post), $enabled_types)) return;

    $mappings = get_option('gjs_field_mappings', []);

    // Helper: safely get field value
    $get_field = function ($schema_key, $default = '') use ($mappings, $post) {
        if (!empty($mappings[$schema_key])) {
            $value = get_post_meta($post->ID, $mappings[$schema_key], true);
            if (!empty($value)) return $value;
        }
        return $default;
    };

    // Core fields with guaranteed defaults
    $title = get_the_title($post) ?: 'Untitled Job';
    $description = wp_strip_all_tags(apply_filters('the_content', $post->post_content));
    if (empty($description)) $description = 'No description available.';
    $datePosted = get_the_date('c', $post) ?: date('c');
    $employmentType = strtoupper($get_field('employment_type', 'FULL_TIME'));
    $validThrough = $get_field('valid_through') ?: date('c', strtotime('+30 days'));
    $applyUrl = $get_field('apply_url', get_permalink($post));
    $identifier = $get_field('identifier_value', $post->ID);

    // Salary
    $minSalary = (float)$get_field('salary_min', 0);
    $maxSalary = (float)$get_field('salary_max', 0);
    $currency = $get_field('salary_currency', 'USD');
    $unit = strtoupper($get_field('salary_unit', 'YEAR'));
    $baseSalary = [
        '@type' => 'MonetaryAmount',
        'currency' => $currency,
        'value' => [
            '@type' => 'QuantitativeValue',
            'minValue' => $minSalary,
            'maxValue' => $maxSalary,
            'unitText' => $unit
        ]
    ];

    // Location (fallback: Remote)
    $city = $get_field('job_location_city');
    $region = $get_field('job_location_region');
    $country = $get_field('job_location_country');
    $street = $get_field('job_location_street');
    $postal = $get_field('job_location_postal');
    $isRemote = $get_field('remote_work');

    if ($isRemote || (!$city && !$country)) {
        $jobLocationType = 'TELECOMMUTE';
        $jobLocation = [
            '@type' => 'VirtualLocation'
        ];
    } else {
        $jobLocationType = 'ON_SITE';
        $jobLocation = [
            '@type' => 'Place',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $street ?: 'N/A',
                'addressLocality' => $city ?: 'N/A',
                'addressRegion' => $region ?: 'N/A',
                'postalCode' => $postal ?: '00000',
                'addressCountry' => $country ?: 'N/A'
            ]
        ];
    }

    // Organization (guaranteed)
    $organization = [
        '@type' => 'Organization',
        'name' => $settings['organization_name'] ?? get_bloginfo('name'),
        'url' => $settings['organization_url'] ?? home_url(),
        'logo' => $settings['organization_logo'] ?? get_site_icon_url()
    ];

    // Build JSON-LD
    $schema = [
        '@context' => 'https://schema.org/',
        '@type' => 'JobPosting',
        'title' => $title,
        'description' => $description,
        'datePosted' => $datePosted,
        'validThrough' => $validThrough,
        'employmentType' => $employmentType,
        'hiringOrganization' => $organization,
        'jobLocation' => $jobLocation,
        'jobLocationType' => $jobLocationType,
        'identifier' => [
            '@type' => 'PropertyValue',
            'value' => $identifier
        ],
        'baseSalary' => $baseSalary,
        'directApply' => true,
        'url' => $applyUrl,
        'industry' => 'Financial Services',
        'occupationalCategory' => 'Information Technology',
        'workHours' => 'Monday to Friday, 9am–6pm',
        'incentiveCompensation' => $get_field('benefits', 'Competitive benefits package'),
        'qualifications' => $get_field('education', 'Relevant degree or equivalent experience'),
        'experienceRequirements' => $get_field('experience', 'Experience in a related role preferred')
    ];

    // Log missing values to console
    $missing = [];
    foreach ($mappings as $schema_field => $meta_key) {
        $value = get_post_meta($post->ID, $meta_key, true);
        if (empty($value)) $missing[] = "$schema_field (meta: $meta_key)";
    }
    if (!empty($missing)) {
        echo "<script>console.warn('Missing schema fields for post ID {$post->ID}: " . esc_js(implode(', ', $missing)) . "');</script>";
    }

    echo "\n<script type='application/ld+json'>" . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
});
