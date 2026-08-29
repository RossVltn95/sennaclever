<?php

/**
 * Platform Patterns Manager
 * 
 * Manages detection patterns and field mappings for 20+ job application platforms
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Platform_Patterns_Manager
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sffc_platform_patterns';

        // Initialize patterns on activation
        add_action('init', [$this, 'maybe_insert_all_patterns']);
    }

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
     * Check and insert all platform patterns if needed
     */
    public function maybe_insert_all_patterns()
    {
        // Check if we've already inserted all patterns
        if (get_option('sffc_all_platforms_inserted') === '1.0.0') {
            return;
        }

        $this->insert_all_platform_patterns();
        update_option('sffc_all_platforms_inserted', '1.0.0');
    }

    /**
     * Insert all platform patterns
     */
    public function insert_all_platform_patterns()
    {
        global $wpdb;

        // Clear existing patterns first (except custom ones)
        $wpdb->query("DELETE FROM {$this->table_name} WHERE platform_name != 'custom'");

        $platforms = $this->get_all_platform_definitions();

        foreach ($platforms as $platform) {
            $wpdb->insert($this->table_name, $platform);
        }
    }

    /**
     * Get all platform definitions
     */
    private function get_all_platform_definitions()
    {
        return [
            // 1. Workday
            [
                'platform_name' => 'workday',
                'url_pattern' => '%.myworkdayjobs.co%|%wd1.myworkdaysite.co%|%wd5.myworkday.co%',
                'dom_selectors' => json_encode([
                    'name' => ['#legalName', '[name="legalName"]', '[data-automation-id="legalNameSection"]', '.legal-name-field'],
                    'email' => ['#email', '[type="email"]', '[data-automation-id="email"]', '.email-field'],
                    'phone' => ['#phone', '[type="tel"]', '[data-automation-id="phone"]', '.phone-field'],
                    'address' => ['[data-automation-id="addressSection"]', '.address-field'],
                    'experience' => ['[data-automation-id="workExperience"]', '.work-experience-section'],
                    'education' => ['[data-automation-id="education"]', '.education-section'],
                    'resume' => ['[data-automation-id="resume"]', 'input[type="file"]']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'legalName',
                    'email' => 'email',
                    'phone' => 'phone',
                    'address' => 'address',
                    'work_experience' => 'experience',
                    'education' => 'education'
                ]),
                'priority' => 10,
                'is_active' => 1,
                'success_rate' => 85.5
            ],

            // 2. Greenhouse
            [
                'platform_name' => 'greenhouse',
                'url_pattern' => '%greenhouse.io%|%boards.greenhouse%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#first_name', '[name="job_application[first_name]"]'],
                    'last_name' => ['#last_name', '[name="job_application[last_name]"]'],
                    'email' => ['#email', '[name="job_application[email]"]'],
                    'phone' => ['#phone', '[name="job_application[phone]"]'],
                    'resume' => ['#resume', '[name="job_application[resume]"]'],
                    'cover_letter' => ['#cover_letter', '[name="job_application[cover_letter]"]'],
                    'linkedin' => ['[name="job_application[linkedin_url]"]'],
                    'website' => ['[name="job_application[website_url]"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'first_name',
                    'last_name' => 'last_name',
                    'email' => 'email',
                    'phone' => 'phone',
                    'linkedin_url' => 'linkedin',
                    'website_url' => 'website'
                ]),
                'priority' => 9,
                'is_active' => 1,
                'success_rate' => 88.2
            ],

            // 3. Lever
            [
                'platform_name' => 'lever',
                'url_pattern' => '%lever.co%|%jobs.lever.co%',
                'dom_selectors' => json_encode([
                    'name' => ['[name="name"]', '.application-name', 'input[placeholder*="Full name"]'],
                    'email' => ['[name="email"]', '.application-email', 'input[type="email"]'],
                    'phone' => ['[name="phone"]', '.application-phone', 'input[type="tel"]'],
                    'resume' => ['[name="resume"]', 'input[type="file"]'],
                    'urls' => ['[name="urls[0]"]', '.application-links'],
                    'comments' => ['[name="comments"]', 'textarea.application-comments']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'name',
                    'email' => 'email',
                    'phone' => 'phone',
                    'linkedin' => 'urls[0]',
                    'additional_info' => 'comments'
                ]),
                'priority' => 8,
                'is_active' => 1,
                'success_rate' => 82.7
            ],

            // 4. Taleo (Oracle)
            [
                'platform_name' => 'taleo',
                'url_pattern' => '%taleo.net%|%oracle.com/taleo%|%tbe.taleo.net%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#firstName', '[name*="firstName"]', '.first-name-field'],
                    'last_name' => ['#lastName', '[name*="lastName"]', '.last-name-field'],
                    'email' => ['#email', '[name*="email"]', 'input[type="email"]'],
                    'phone' => ['#primaryPhone', '[name*="phone"]', 'input[type="tel"]'],
                    'address' => ['#address1', '[name*="address"]'],
                    'city' => ['#city', '[name*="city"]'],
                    'state' => ['#state', '[name*="state"]'],
                    'zip' => ['#zip', '[name*="postalCode"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'email',
                    'phone' => 'primaryPhone',
                    'street_address' => 'address1',
                    'city' => 'city',
                    'state' => 'state',
                    'postal_code' => 'zip'
                ]),
                'priority' => 7,
                'is_active' => 1,
                'success_rate' => 75.3
            ],

            // 5. iCIMS
            [
                'platform_name' => 'icims',
                'url_pattern' => '%icims.co%|%careers.icims%|%.icims.co%',
                'dom_selectors' => json_encode([
                    'first_name' => ['[id*="firstName"]', '[name*="FirstName"]', '.iCIMS_FirstName'],
                    'last_name' => ['[id*="lastName"]', '[name*="LastName"]', '.iCIMS_LastName'],
                    'email' => ['[id*="email"]', '[name*="Email"]', '.iCIMS_Email'],
                    'phone' => ['[id*="phone"]', '[name*="Phone"]', '.iCIMS_Phone'],
                    'address' => ['[id*="address"]', '[name*="Address"]'],
                    'resume' => ['[id*="resume"]', 'input[type="file"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'email',
                    'phone' => 'phone',
                    'address' => 'address1'
                ]),
                'priority' => 6,
                'is_active' => 1,
                'success_rate' => 73.8
            ],

            // 6. BrassRing (IBM Kenexa)
            [
                'platform_name' => 'brassring',
                'url_pattern' => '%brassring.co%|%kenexa.co%|%sjobs.brassring%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#firstname', '[name="firstname"]', 'input[placeholder*="First"]'],
                    'last_name' => ['#lastname', '[name="lastname"]', 'input[placeholder*="Last"]'],
                    'email' => ['#email', '[name="email"]'],
                    'phone' => ['#phone', '[name="phone"]'],
                    'resume' => ['#resume', '[name="resume_file"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 5,
                'is_active' => 1,
                'success_rate' => 70.2
            ],

            // 7. SuccessFactors (SAP)
            [
                'platform_name' => 'successfactors',
                'url_pattern' => '%successfactors.co%|%sapsf.co%|%sfcareers%',
                'dom_selectors' => json_encode([
                    'first_name' => ['[data-field-name="firstName"]', '#firstName'],
                    'last_name' => ['[data-field-name="lastName"]', '#lastName'],
                    'email' => ['[data-field-name="email"]', '#email'],
                    'phone' => ['[data-field-name="cellPhone"]', '#cellPhone'],
                    'country' => ['[data-field-name="country"]', '#country'],
                    'resume' => ['[data-field-name="resume"]', '.resume-upload']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'email',
                    'phone' => 'cellPhone',
                    'country' => 'country'
                ]),
                'priority' => 6,
                'is_active' => 1,
                'success_rate' => 78.5
            ],

            // 8. ADP
            [
                'platform_name' => 'adp',
                'url_pattern' => '%adp.co%|%mykelly.adp%|%workforce.adp%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#first-name', '[name="firstName"]'],
                    'last_name' => ['#last-name', '[name="lastName"]'],
                    'email' => ['#email-address', '[name="emailAddress"]'],
                    'phone' => ['#phone-number', '[name="phoneNumber"]'],
                    'resume' => ['#resume-upload', '[name="resumeFile"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'emailAddress',
                    'phone' => 'phoneNumber'
                ]),
                'priority' => 5,
                'is_active' => 1,
                'success_rate' => 72.0
            ],

            // 9. Jobvite
            [
                'platform_name' => 'jobvite',
                'url_pattern' => '%jobvite.co%|%jobs.jobvite%|%hire.jobvite%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#jv-field-firstname', '[name="firstname"]'],
                    'last_name' => ['#jv-field-lastname', '[name="lastname"]'],
                    'email' => ['#jv-field-email', '[name="email"]'],
                    'phone' => ['#jv-field-phone', '[name="phone"]'],
                    'resume' => ['#jv-field-resume', '.jv-resume-upload']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 4,
                'is_active' => 1,
                'success_rate' => 76.3
            ],

            // 10. SmartRecruiters
            [
                'platform_name' => 'smartrecruiters',
                'url_pattern' => '%smartrecruiters.co%|%jobs.smartrecruiters%',
                'dom_selectors' => json_encode([
                    'first_name' => ['[name="firstName"]', '#firstName', '.sr-firstname'],
                    'last_name' => ['[name="lastName"]', '#lastName', '.sr-lastname'],
                    'email' => ['[name="email"]', '#email', '.sr-email'],
                    'phone' => ['[name="phoneNumber"]', '#phoneNumber', '.sr-phone'],
                    'resume' => ['[name="resume"]', '.sr-resume-upload']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'email',
                    'phone' => 'phoneNumber'
                ]),
                'priority' => 5,
                'is_active' => 1,
                'success_rate' => 79.1
            ],

            // 11. JazzHR
            [
                'platform_name' => 'jazzhr',
                'url_pattern' => '%applytojob.co%|%jazz.co%|%jazzhr.co%',
                'dom_selectors' => json_encode([
                    'name' => ['#name', '[name="name"]', 'input[placeholder*="Full Name"]'],
                    'email' => ['#email', '[name="email"]'],
                    'phone' => ['#phone', '[name="phone"]'],
                    'resume' => ['#resume', '[name="resume"]'],
                    'cover_letter' => ['#cover_letter', '[name="cover_letter"]']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'name',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 3,
                'is_active' => 1,
                'success_rate' => 74.5
            ],

            // 12. Ashby
            [
                'platform_name' => 'ashby',
                'url_pattern' => '%ashbyhq.co%|%jobs.ashbyhq%',
                'dom_selectors' => json_encode([
                    'name' => ['[name="fullName"]', 'input[aria-label*="Name"]'],
                    'email' => ['[name="email"]', 'input[aria-label*="Email"]'],
                    'phone' => ['[name="phone"]', 'input[aria-label*="Phone"]'],
                    'resume' => ['input[type="file"]', '[aria-label*="Resume"]'],
                    'linkedin' => ['[name="linkedinUrl"]', 'input[aria-label*="LinkedIn"]']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'fullName',
                    'email' => 'email',
                    'phone' => 'phone',
                    'linkedin' => 'linkedinUrl'
                ]),
                'priority' => 4,
                'is_active' => 1,
                'success_rate' => 81.2
            ],

            // 13. Bamboo HR
            [
                'platform_name' => 'bamboohr',
                'url_pattern' => '%bamboohr.co%|%.bamboohr.com/jobs%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#firstName', '[name="firstName"]'],
                    'last_name' => ['#lastName', '[name="lastName"]'],
                    'email' => ['#email', '[name="email"]'],
                    'phone' => ['#phone', '[name="phone"]'],
                    'resume' => ['#resume', '[name="resume"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 3,
                'is_active' => 1,
                'success_rate' => 77.8
            ],

            // 14. Recruiterbox (Trakstar Hire)
            [
                'platform_name' => 'recruiterbox',
                'url_pattern' => '%recruiterbox.co%|%trakstar.co%|%hire.trakstar%',
                'dom_selectors' => json_encode([
                    'name' => ['#candidate_name', '[name="candidate[name]"]'],
                    'email' => ['#candidate_email', '[name="candidate[email]"]'],
                    'phone' => ['#candidate_phone', '[name="candidate[phone]"]'],
                    'resume' => ['#candidate_resume', '[name="candidate[resume]"]']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'candidate[name]',
                    'email' => 'candidate[email]',
                    'phone' => 'candidate[phone]'
                ]),
                'priority' => 2,
                'is_active' => 1,
                'success_rate' => 71.4
            ],

            // 15. ApplyToJob
            [
                'platform_name' => 'applytojob',
                'url_pattern' => '%applytojob.co%',
                'dom_selectors' => json_encode([
                    'name' => ['#applicant_name', '[name="applicant_name"]'],
                    'email' => ['#applicant_email', '[name="applicant_email"]'],
                    'phone' => ['#applicant_phone', '[name="applicant_phone"]'],
                    'resume' => ['#applicant_resume', '[name="applicant_resume"]']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'applicant_name',
                    'email' => 'applicant_email',
                    'phone' => 'applicant_phone'
                ]),
                'priority' => 2,
                'is_active' => 1,
                'success_rate' => 68.9
            ],

            // 16. Ultipro (UKG)
            [
                'platform_name' => 'ultipro',
                'url_pattern' => '%ultipro.co%|%ukg.co%|%recruiting.ultipro%',
                'dom_selectors' => json_encode([
                    'first_name' => ['[id*="FirstName"]', '[name*="FirstName"]'],
                    'last_name' => ['[id*="LastName"]', '[name*="LastName"]'],
                    'email' => ['[id*="Email"]', '[name*="Email"]'],
                    'phone' => ['[id*="Phone"]', '[name*="Phone"]'],
                    'resume' => ['[id*="Resume"]', 'input[type="file"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'FirstName',
                    'last_name' => 'LastName',
                    'email' => 'Email',
                    'phone' => 'Phone'
                ]),
                'priority' => 4,
                'is_active' => 1,
                'success_rate' => 73.6
            ],

            // 17. Paycom
            [
                'platform_name' => 'paycom',
                'url_pattern' => '%paycom.co%|%paycomonline.co%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#txtFirstName', '[name="firstName"]'],
                    'last_name' => ['#txtLastName', '[name="lastName"]'],
                    'email' => ['#txtEmail', '[name="email"]'],
                    'phone' => ['#txtPhone', '[name="phone"]'],
                    'resume' => ['#fileResume', '[name="resume"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstName',
                    'last_name' => 'lastName',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 3,
                'is_active' => 1,
                'success_rate' => 69.3
            ],

            // 18. Paylocity
            [
                'platform_name' => 'paylocity',
                'url_pattern' => '%paylocity.co%|%recruiting.paylocity%',
                'dom_selectors' => json_encode([
                    'first_name' => ['[name="FirstName"]', '#FirstName'],
                    'last_name' => ['[name="LastName"]', '#LastName'],
                    'email' => ['[name="EmailAddress"]', '#EmailAddress'],
                    'phone' => ['[name="PhoneNumber"]', '#PhoneNumber'],
                    'resume' => ['[name="ResumeFile"]', '#ResumeFile']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'FirstName',
                    'last_name' => 'LastName',
                    'email' => 'EmailAddress',
                    'phone' => 'PhoneNumber'
                ]),
                'priority' => 3,
                'is_active' => 1,
                'success_rate' => 70.7
            ],

            // 19. Cornerstone OnDemand
            [
                'platform_name' => 'cornerstone',
                'url_pattern' => '%csod.co%|%cornerstoneondemand%',
                'dom_selectors' => json_encode([
                    'first_name' => ['[id*="firstname"]', '[name*="firstname"]'],
                    'last_name' => ['[id*="lastname"]', '[name*="lastname"]'],
                    'email' => ['[id*="email"]', '[name*="email"]'],
                    'phone' => ['[id*="phone"]', '[name*="phone"]'],
                    'resume' => ['[id*="resume"]', 'input[type="file"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 3,
                'is_active' => 1,
                'success_rate' => 72.1
            ],

            // 20. Zoho Recruit
            [
                'platform_name' => 'zoho_recruit',
                'url_pattern' => '%zohorecruit.co%|%recruit.zoho%',
                'dom_selectors' => json_encode([
                    'first_name' => ['#First_Name', '[name="First_Name"]'],
                    'last_name' => ['#Last_Name', '[name="Last_Name"]'],
                    'email' => ['#Email', '[name="Email"]'],
                    'phone' => ['#Mobile', '[name="Mobile"]'],
                    'resume' => ['#Resume', '[name="Resume"]']
                ]),
                'field_mappings' => json_encode([
                    'first_name' => 'First_Name',
                    'last_name' => 'Last_Name',
                    'email' => 'Email',
                    'phone' => 'Mobile'
                ]),
                'priority' => 2,
                'is_active' => 1,
                'success_rate' => 74.2
            ],

            // 21. Generic/Custom platform (fallback)
            [
                'platform_name' => 'custom',
                'url_pattern' => '',
                'dom_selectors' => json_encode([
                    'name' => ['[name*="name"]', '[placeholder*="name"]', 'input[aria-label*="name"]'],
                    'email' => ['[type="email"]', '[name*="email"]', '[placeholder*="email"]'],
                    'phone' => ['[type="tel"]', '[name*="phone"]', '[placeholder*="phone"]'],
                    'resume' => ['[type="file"]', '[name*="resume"]', '[name*="cv"]']
                ]),
                'field_mappings' => json_encode([
                    'full_name' => 'name',
                    'email' => 'email',
                    'phone' => 'phone'
                ]),
                'priority' => 0,
                'is_active' => 1,
                'success_rate' => 50.0
            ]
        ];
    }

    /**
     * Detect platform from URL
     */
    public function detect_platform($url)
    {
        global $wpdb;

        // Get all active patterns ordered by priority
        $patterns = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
             WHERE is_active = 1 
             ORDER BY priority DESC",
            ARRAY_A
        );

        foreach ($patterns as $pattern) {
            if (empty($pattern['url_pattern'])) {
                continue;
            }

            // Split multiple patterns
            $url_patterns = explode('|', $pattern['url_pattern']);

            foreach ($url_patterns as $url_pattern) {
                $url_pattern = trim($url_pattern);
                if (empty($url_pattern)) continue;

                // Convert SQL LIKE pattern to regex
                $regex_pattern = str_replace('%', '.*', $url_pattern);
                $regex_pattern = '/^' . $regex_pattern . '$/i';

                if (preg_match($regex_pattern, $url)) {
                    return [
                        'platform' => $pattern['platform_name'],
                        'selectors' => json_decode($pattern['dom_selectors'], true),
                        'mappings' => json_decode($pattern['field_mappings'], true),
                        'success_rate' => $pattern['success_rate']
                    ];
                }
            }
        }

        // Return custom platform as fallback
        $custom = $wpdb->get_row(
            "SELECT * FROM {$this->table_name} WHERE platform_name = 'custom'",
            ARRAY_A
        );

        if ($custom) {
            return [
                'platform' => 'custom',
                'selectors' => json_decode($custom['dom_selectors'], true),
                'mappings' => json_decode($custom['field_mappings'], true),
                'success_rate' => $custom['success_rate']
            ];
        }

        return null;
    }

    /**
     * Update platform success rate
     */
    public function update_success_rate($platform_name, $success)
    {
        global $wpdb;

        // Get current stats
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT success_rate, last_verified FROM {$this->table_name} WHERE platform_name = %s",
            $platform_name
        ), ARRAY_A);

        if (!$current) {
            return;
        }

        // Calculate new success rate (weighted average)
        $old_rate = floatval($current['success_rate']);
        $new_rate = $success ? 100 : 0;

        // Weight recent results more heavily
        $updated_rate = ($old_rate * 0.9) + ($new_rate * 0.1);

        // Update database
        $wpdb->update(
            $this->table_name,
            [
                'success_rate' => round($updated_rate, 2),
                'last_verified' => current_time('mysql')
            ],
            ['platform_name' => $platform_name]
        );
    }

    /**
     * Add custom platform pattern
     */
    public function add_custom_pattern($data)
    {
        global $wpdb;

        return $wpdb->insert(
            $this->table_name,
            [
                'platform_name' => sanitize_text_field($data['platform_name']),
                'url_pattern' => sanitize_text_field($data['url_pattern']),
                'dom_selectors' => json_encode($data['dom_selectors']),
                'field_mappings' => json_encode($data['field_mappings']),
                'priority' => intval($data['priority'] ?? 1),
                'is_active' => 1,
                'success_rate' => 50.0
            ]
        );
    }
}

// Initialize
SFFC_Platform_Patterns_Manager::get_instance();
