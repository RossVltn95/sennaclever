<?php
/**
 * Contacts Database Class
 * Handles hiring manager contacts storage and retrieval
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Contacts_Database {

    /**
     * Table name for contacts
     */
    private static $contacts_table = 'sffc_contacts';

    /**
     * Table name for companies
     */
    private static $companies_table = 'sffc_contact_companies';

    /**
     * Get contacts table name with prefix
     */
    public static function get_contacts_table() {
        global $wpdb;
        return $wpdb->prefix . self::$contacts_table;
    }

    /**
     * Get companies table name with prefix
     */
    public static function get_companies_table() {
        global $wpdb;
        return $wpdb->prefix . self::$companies_table;
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Companies table
        $companies_table = self::get_companies_table();
        $sql_companies = "CREATE TABLE IF NOT EXISTS $companies_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            domain varchar(255),
            description text,
            year_founded varchar(10),
            website varchar(500),
            num_employees varchar(50),
            revenue varchar(100),
            linkedin_url varchar(500),
            city varchar(100),
            country varchar(100),
            old_industry varchar(255),
            main_industry varchar(255),
            sub_industry varchar(255),
            specialities text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name(191)),
            KEY domain (domain(191)),
            KEY country (country),
            KEY main_industry (main_industry(191))
        ) $charset_collate;";

        dbDelta($sql_companies);

        // Contacts table
        $contacts_table = self::get_contacts_table();
        $sql_contacts = "CREATE TABLE IF NOT EXISTS $contacts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100),
            email varchar(255),
            phone_1 varchar(50),
            phone_1_type varchar(50),
            phone_2 varchar(50),
            phone_2_type varchar(50),
            job_title varchar(255),
            seniority varchar(100),
            departments varchar(255),
            linkedin_url varchar(500),
            continent varchar(100),
            country varchar(100),
            state varchar(100),
            city varchar(100),
            country_iso varchar(10),
            tags text,
            company_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email(191)),
            KEY company_id (company_id),
            KEY country (country),
            KEY city (city),
            KEY seniority (seniority),
            KEY job_title (job_title(191)),
            CONSTRAINT fk_contact_company FOREIGN KEY (company_id) REFERENCES $companies_table(id) ON DELETE SET NULL
        ) $charset_collate;";

        dbDelta($sql_contacts);

        // Seed with sample data if tables are empty
        self::maybe_seed_sample_contacts();

        return true;
    }

    /**
     * Seed sample contacts from HORTA.csv if no contacts exist
     */
    public static function maybe_seed_sample_contacts() {
        // Check if contacts already exist
        if (self::get_total_count() > 0) {
            return false;
        }

        // Import from HORTA.csv
        $csv_path = SFFC_PLUGIN_DIR . 'HORTA.csv';
        if (!file_exists($csv_path)) {
            return false;
        }

        return self::import_csv_file($csv_path);
    }

    /**
     * Import contacts from a CSV file
     */
    public static function import_csv_file($csv_path) {
        if (!file_exists($csv_path)) {
            return false;
        }

        $handle = fopen($csv_path, 'r');
        if (!$handle) {
            return false;
        }

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return false;
        }

        // Clean headers
        $headers = array_map('trim', $headers);

        // Create field mapping based on CSV headers
        // Support both Title Case (DUBAI.csv) and lowercase (HORTA.csv) headers
        $field_mapping = [
            'first_name' => ['First Name', 'First name'],
            'last_name' => ['Last Name', 'Last name'],
            'email' => ['Work Email', 'Work email'],
            'phone_1' => ['Phone 1'],
            'phone_1_type' => ['Phone 1 type', 'Phone 1 Type'],
            'phone_2' => ['Phone 2'],
            'phone_2_type' => ['Phone 2 type', 'Phone 2 Type'],
            'job_title' => ['Job Title', 'Job title'],
            'seniority' => ['Seniority'],
            'departments' => ['Departments'],
            'linkedin_url' => ['LinkedIn URL', 'Linkedin URL'],
            'continent' => ['Continent'],
            'country' => ['Country'],
            'state' => ['State'],
            'city' => ['City'],
            'country_iso' => ['Country ISO'],
            'tags' => ['Tags'],
            'company_name' => ['Company Name', 'Company name'],
            'company_domain' => ['Company Domain', 'Company domain'],
            'company_description' => ['Company Description', 'Company description'],
            'company_year_founded' => ['Company Year Founded', 'Company year founded'],
            'company_website' => ['Company Website', 'Company website'],
            'company_num_employees' => ['Company Number of Employees', 'Company number of employees'],
            'company_revenue' => ['Company Revenue', 'Company revenue'],
            'company_linkedin' => ['Company linkedin URL', 'Company Linkedin URL'],
            'company_city' => ['Company City', 'Company city'],
            'company_country' => ['Company Country', 'Company country'],
            'company_old_industry' => ['Company old industry', 'Company Old Industry'],
            'company_main_industry' => ['Company Main Industry', 'Company main industry'],
            'company_sub_industry' => ['Company Sub Industry', 'Company sub industry'],
            'company_specialities' => ['Company Specialties', 'Company specialities'],
        ];

        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Create associative array from row
            $data = [];
            foreach ($headers as $index => $header) {
                $data[$header] = isset($row[$index]) ? $row[$index] : '';
            }

            // Import contact
            if (self::import_contact($data, $field_mapping)) {
                $imported++;
            }
        }

        fclose($handle);

        return $imported;
    }

    /**
     * Get contacts with filters and pagination
     */
    /**
     * private equity countries list for region filtering
     */
    public static function get_private_equity_countries() {
        return [
            'United Arab Emirates',
            'Saudi Arabia',
            'Qatar',
            'Kuwait',
            'Bahrain',
            'Oman',
            'Jordan',
            'Lebanon',
            'Egypt',
            'Iraq',
            'Iran',
            'Israel',
            'Palestine',
            'Palestinian Territories',
            'Syria',
            'Yemen',
            'Turkey' // Often included in private equity region
        ];
    }

    public static function get_contacts($args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'company' => '',
            'country' => '',
            'countries' => [], // Array of countries to filter by
            'region' => '', // 'private_equity' to filter by private equity countries
            'city' => '',
            'seniority' => '',
            'industry' => '',
            'orderby' => 'rand',
            'order' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $where = ['1=1'];
        $values = [];

        if (!empty($args['search'])) {
            $where[] = "(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR co.name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        if (!empty($args['company'])) {
            $where[] = "co.name = %s";
            $values[] = $args['company'];
        }

        // Handle region filter (converts to countries array)
        $region_countries = [];
        if (!empty($args['region']) && $args['region'] === 'private_equity') {
            $region_countries = self::get_private_equity_countries();
        }

        // Handle country filtering
        if (!empty($args['country']) && !empty($region_countries)) {
            // User selected specific country - verify it's in the region
            if (in_array($args['country'], $region_countries)) {
                $where[] = "c.country = %s";
                $values[] = $args['country'];
            } else {
                // Country not in region - return no results
                $where[] = "1=0";
            }
        } elseif (!empty($region_countries)) {
            // No specific country selected - filter by all region countries
            $placeholders = array_fill(0, count($region_countries), '%s');
            $where[] = "c.country IN (" . implode(',', $placeholders) . ")";
            foreach ($region_countries as $c) {
                $values[] = $c;
            }
        } elseif (!empty($args['countries']) && is_array($args['countries'])) {
            // Custom countries array filter
            $placeholders = array_fill(0, count($args['countries']), '%s');
            $where[] = "c.country IN (" . implode(',', $placeholders) . ")";
            foreach ($args['countries'] as $c) {
                $values[] = $c;
            }
        } elseif (!empty($args['country'])) {
            // Single country filter (no region restriction)
            $where[] = "c.country = %s";
            $values[] = $args['country'];
        }

        if (!empty($args['city'])) {
            $where[] = "c.city = %s";
            $values[] = $args['city'];
        }

        if (!empty($args['seniority'])) {
            $where[] = "c.seniority = %s";
            $values[] = $args['seniority'];
        }

        if (!empty($args['industry'])) {
            $where[] = "co.main_industry = %s";
            $values[] = $args['industry'];
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby
        $allowed_orderby = ['first_name', 'last_name', 'email', 'job_title', 'country', 'city', 'created_at', 'rand'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'rand';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Handle random ordering
        $order_clause = ($orderby === 'rand') ? 'RAND()' : "c.$orderby $order";

        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $contacts_table c
                      LEFT JOIN $companies_table co ON c.company_id = co.id
                      WHERE $where_clause";

        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }

        $total = $wpdb->get_var($count_sql);

        // Get contacts
        $sql = "SELECT c.*, co.name as company_name, co.domain as company_domain,
                       co.main_industry, co.sub_industry, co.num_employees, co.revenue,
                       co.website as company_website, co.linkedin_url as company_linkedin
                FROM $contacts_table c
                LEFT JOIN $companies_table co ON c.company_id = co.id
                WHERE $where_clause
                ORDER BY $order_clause
                LIMIT %d OFFSET %d";

        $values[] = $args['per_page'];
        $values[] = $offset;

        $contacts = $wpdb->get_results($wpdb->prepare($sql, $values));

        return [
            'contacts' => $contacts,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
            'page' => $args['page'],
            'per_page' => $args['per_page']
        ];
    }

    /**
     * Get single contact by ID
     */
    public static function get_contact($id) {
        global $wpdb;

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $sql = $wpdb->prepare(
            "SELECT c.*, co.name as company_name, co.domain as company_domain,
                    co.description as company_description, co.year_founded,
                    co.website as company_website, co.num_employees, co.revenue,
                    co.linkedin_url as company_linkedin, co.city as company_city,
                    co.country as company_country, co.main_industry, co.sub_industry,
                    co.specialities
             FROM $contacts_table c
             LEFT JOIN $companies_table co ON c.company_id = co.id
             WHERE c.id = %d",
            $id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Get available filter options
     */
    public static function get_filter_options($region = 'private_equity') {
        global $wpdb;

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        // Get private equity countries for filtering
        $me_countries = self::get_private_equity_countries();
        $placeholders = implode(',', array_fill(0, count($me_countries), '%s'));

        // Get companies that have contacts in private equity
        $companies_sql = $wpdb->prepare(
            "SELECT DISTINCT co.name
             FROM $companies_table co
             INNER JOIN $contacts_table c ON c.company_id = co.id
             WHERE co.name != '' AND c.country IN ($placeholders)
             ORDER BY co.name LIMIT 200",
            ...$me_countries
        );
        $companies = $wpdb->get_col($companies_sql);

        // Get countries (only private equity)
        $countries_sql = $wpdb->prepare(
            "SELECT DISTINCT country FROM $contacts_table WHERE country IN ($placeholders) ORDER BY country",
            ...$me_countries
        );
        $countries = $wpdb->get_col($countries_sql);

        // Get seniorities for private equity contacts
        $seniorities_sql = $wpdb->prepare(
            "SELECT DISTINCT seniority FROM $contacts_table WHERE seniority != '' AND country IN ($placeholders) ORDER BY seniority",
            ...$me_countries
        );
        $seniorities = $wpdb->get_col($seniorities_sql);

        // Get industries for companies with private equity contacts
        $industries_sql = $wpdb->prepare(
            "SELECT DISTINCT co.main_industry
             FROM $companies_table co
             INNER JOIN $contacts_table c ON c.company_id = co.id
             WHERE co.main_industry != '' AND c.country IN ($placeholders)
             ORDER BY co.main_industry",
            ...$me_countries
        );
        $industries = $wpdb->get_col($industries_sql);

        return [
            'companies' => $companies,
            'countries' => $countries,
            'seniorities' => $seniorities,
            'industries' => $industries
        ];
    }

    /**
     * Import contact from array
     */
    public static function import_contact($data, $field_mapping) {
        global $wpdb;

        // First, handle company
        $company_id = null;
        $company_name = self::get_mapped_value($data, $field_mapping, 'company_name');

        if (!empty($company_name)) {
            $companies_table = self::get_companies_table();

            // Check if company exists by domain or name
            $company_domain = self::get_mapped_value($data, $field_mapping, 'company_domain');

            if (!empty($company_domain)) {
                $company_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $companies_table WHERE domain = %s",
                    $company_domain
                ));
            }

            if (!$company_id) {
                $company_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $companies_table WHERE name = %s",
                    $company_name
                ));
            }

            // Insert new company if not found
            if (!$company_id) {
                $company_data = [
                    'name' => $company_name,
                    'domain' => $company_domain,
                    'description' => self::get_mapped_value($data, $field_mapping, 'company_description'),
                    'year_founded' => self::get_mapped_value($data, $field_mapping, 'company_year_founded'),
                    'website' => self::get_mapped_value($data, $field_mapping, 'company_website'),
                    'num_employees' => self::get_mapped_value($data, $field_mapping, 'company_num_employees'),
                    'revenue' => self::get_mapped_value($data, $field_mapping, 'company_revenue'),
                    'linkedin_url' => self::get_mapped_value($data, $field_mapping, 'company_linkedin'),
                    'city' => self::get_mapped_value($data, $field_mapping, 'company_city'),
                    'country' => self::get_mapped_value($data, $field_mapping, 'company_country'),
                    'old_industry' => self::get_mapped_value($data, $field_mapping, 'company_old_industry'),
                    'main_industry' => self::get_mapped_value($data, $field_mapping, 'company_main_industry'),
                    'sub_industry' => self::get_mapped_value($data, $field_mapping, 'company_sub_industry'),
                    'specialities' => self::get_mapped_value($data, $field_mapping, 'company_specialities'),
                ];

                $wpdb->insert($companies_table, $company_data);
                $company_id = $wpdb->insert_id;
            }
        }

        // Insert contact
        $contacts_table = self::get_contacts_table();

        $contact_data = [
            'first_name' => self::get_mapped_value($data, $field_mapping, 'first_name'),
            'last_name' => self::get_mapped_value($data, $field_mapping, 'last_name'),
            'email' => self::get_mapped_value($data, $field_mapping, 'email'),
            'phone_1' => self::get_mapped_value($data, $field_mapping, 'phone_1'),
            'phone_1_type' => self::get_mapped_value($data, $field_mapping, 'phone_1_type'),
            'phone_2' => self::get_mapped_value($data, $field_mapping, 'phone_2'),
            'phone_2_type' => self::get_mapped_value($data, $field_mapping, 'phone_2_type'),
            'job_title' => self::get_mapped_value($data, $field_mapping, 'job_title'),
            'seniority' => self::get_mapped_value($data, $field_mapping, 'seniority'),
            'departments' => self::get_mapped_value($data, $field_mapping, 'departments'),
            'linkedin_url' => self::get_mapped_value($data, $field_mapping, 'linkedin_url'),
            'continent' => self::get_mapped_value($data, $field_mapping, 'continent'),
            'country' => self::get_mapped_value($data, $field_mapping, 'country'),
            'state' => self::get_mapped_value($data, $field_mapping, 'state'),
            'city' => self::get_mapped_value($data, $field_mapping, 'city'),
            'country_iso' => self::get_mapped_value($data, $field_mapping, 'country_iso'),
            'tags' => self::get_mapped_value($data, $field_mapping, 'tags'),
            'company_id' => $company_id,
        ];

        $result = $wpdb->insert($contacts_table, $contact_data);

        return $result !== false;
    }

    /**
     * Helper to get mapped value from CSV data
     */
    private static function get_mapped_value($data, $field_mapping, $field_name) {
        if (!isset($field_mapping[$field_name])) {
            return '';
        }

        $csv_columns = $field_mapping[$field_name];

        // Handle both array of possible column names and single string
        if (!is_array($csv_columns)) {
            $csv_columns = [$csv_columns];
        }

        // Try each possible column name
        foreach ($csv_columns as $csv_column) {
            if (isset($data[$csv_column]) && trim($data[$csv_column]) !== '') {
                return trim($data[$csv_column]);
            }
        }

        return '';
    }

    /**
     * Get total contacts count
     */
    public static function get_total_count() {
        global $wpdb;
        $table = self::get_contacts_table();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get total companies count
     */
    public static function get_companies_count() {
        global $wpdb;
        $table = self::get_companies_table();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get companies with filters and pagination
     *
     * @param array $args Filter arguments
     * @return array Companies with contact counts
     */
    public static function get_companies($args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'search' => '',
            'industry' => '',
            'country' => '',
            'has_contacts' => false,
            'orderby' => 'contact_count',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $companies_table = self::get_companies_table();
        $contacts_table = self::get_contacts_table();

        $where = ['1=1'];
        $values = [];

        if (!empty($args['search'])) {
            $where[] = "(co.name LIKE %s OR co.main_industry LIKE %s OR co.city LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        if (!empty($args['industry'])) {
            $where[] = "co.main_industry = %s";
            $values[] = $args['industry'];
        }

        if (!empty($args['country'])) {
            $where[] = "co.country = %s";
            $values[] = $args['country'];
        }

        if ($args['has_contacts']) {
            $where[] = "contact_count > 0";
        }

        $where_clause = implode(' AND ', $where);

        // Valid order columns
        $valid_orderby = ['name', 'contact_count', 'country', 'main_industry', 'num_employees'];
        $orderby = in_array($args['orderby'], $valid_orderby) ? $args['orderby'] : 'contact_count';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get companies with contact count
        $query = "
            SELECT
                co.*,
                COUNT(c.id) as contact_count
            FROM $companies_table co
            LEFT JOIN $contacts_table c ON c.company_id = co.id
            GROUP BY co.id
            HAVING $where_clause
            ORDER BY $orderby $order
            LIMIT %d OFFSET %d
        ";

        $values[] = $args['per_page'];
        $values[] = $offset;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        $companies = $wpdb->get_results($query);

        // Get total count
        $count_query = "
            SELECT COUNT(*) FROM (
                SELECT co.id, COUNT(c.id) as contact_count
                FROM $companies_table co
                LEFT JOIN $contacts_table c ON c.company_id = co.id
                GROUP BY co.id
                HAVING " . implode(' AND ', array_slice($where, 0, -1)) . "
            ) as filtered
        ";

        // For count, use only the filter values (not pagination)
        $count_values = array_slice($values, 0, -2);
        if (!empty($count_values)) {
            $count_query = $wpdb->prepare($count_query, $count_values);
        }

        $total = (int) $wpdb->get_var($count_query);

        return [
            'companies' => $companies,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'page' => $args['page']
        ];
    }

    /**
     * Get a single company by ID with contact count
     *
     * @param int $company_id Company ID
     * @return object|null Company data
     */
    public static function get_company($company_id) {
        global $wpdb;

        $companies_table = self::get_companies_table();
        $contacts_table = self::get_contacts_table();

        $query = $wpdb->prepare("
            SELECT
                co.*,
                COUNT(c.id) as contact_count
            FROM $companies_table co
            LEFT JOIN $contacts_table c ON c.company_id = co.id
            WHERE co.id = %d
            GROUP BY co.id
        ", $company_id);

        return $wpdb->get_row($query);
    }

    /**
     * Get contacts at a specific company
     *
     * @param int $company_id Company ID
     * @param array $args Filter arguments
     * @return array Contacts at the company
     */
    public static function get_company_contacts($company_id, $args = []) {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'orderby' => 'seniority',
            'order' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $offset = ($args['page'] - 1) * $args['per_page'];

        $query = $wpdb->prepare("
            SELECT
                c.*,
                co.name as company_name,
                co.main_industry as company_industry
            FROM $contacts_table c
            LEFT JOIN $companies_table co ON c.company_id = co.id
            WHERE c.company_id = %d
            ORDER BY c.seniority ASC, c.last_name ASC
            LIMIT %d OFFSET %d
        ", $company_id, $args['per_page'], $offset);

        $contacts = $wpdb->get_results($query);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $contacts_table WHERE company_id = %d",
            $company_id
        ));

        return [
            'contacts' => $contacts,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'page' => $args['page']
        ];
    }

    /**
     * Get filter options for companies
     *
     * @return array Available filter options
     */
    public static function get_company_filter_options() {
        global $wpdb;

        $companies_table = self::get_companies_table();

        // Get unique industries
        $industries = $wpdb->get_col("
            SELECT DISTINCT main_industry
            FROM $companies_table
            WHERE main_industry IS NOT NULL AND main_industry != ''
            ORDER BY main_industry ASC
        ");

        // Get unique countries
        $countries = $wpdb->get_col("
            SELECT DISTINCT country
            FROM $companies_table
            WHERE country IS NOT NULL AND country != ''
            ORDER BY country ASC
        ");

        return [
            'industries' => $industries,
            'countries' => $countries
        ];
    }

    /**
     * Clear all contacts and companies
     */
    public static function clear_all() {
        global $wpdb;

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $wpdb->query("DELETE FROM $contacts_table");
        $wpdb->query("DELETE FROM $companies_table");

        return true;
    }

    /**
     * Remove incomplete contacts (missing job title OR company)
     *
     * @return int Number of deleted contacts
     */
    public static function cleanup_incomplete_contacts() {
        global $wpdb;

        $contacts_table = self::get_contacts_table();

        // Delete contacts that have no job_title AND no company_id
        $deleted = $wpdb->query("
            DELETE FROM $contacts_table
            WHERE (job_title IS NULL OR job_title = '')
            AND (company_id IS NULL OR company_id = 0)
        ");

        return $deleted;
    }

    /**
     * Remove contacts missing essential fields
     * More aggressive cleanup - removes if missing job title OR company
     *
     * @return int Number of deleted contacts
     */
    public static function cleanup_contacts_strict() {
        global $wpdb;

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        // First, delete contacts where company_id points to non-existent company
        $wpdb->query("
            DELETE c FROM $contacts_table c
            LEFT JOIN $companies_table co ON c.company_id = co.id
            WHERE c.company_id IS NOT NULL
            AND c.company_id > 0
            AND co.id IS NULL
        ");

        // Delete contacts that have no job_title OR no company_id
        // Handle NULL, empty string, and whitespace-only values
        $deleted = $wpdb->query("
            DELETE FROM $contacts_table
            WHERE (job_title IS NULL OR TRIM(job_title) = '')
            OR (company_id IS NULL OR company_id = 0)
        ");

        // Also cleanup orphaned companies (no contacts)
        $wpdb->query("
            DELETE co FROM $companies_table co
            LEFT JOIN $contacts_table c ON c.company_id = co.id
            WHERE c.id IS NULL
        ");

        // Delete companies with empty names
        $wpdb->query("
            DELETE FROM $companies_table
            WHERE name IS NULL OR TRIM(name) = ''
        ");

        return $deleted;
    }

    /**
     * Remove duplicate contacts
     * Keeps the oldest entry (lowest ID), removes newer duplicates
     *
     * @return int Number of deleted duplicates
     */
    public static function remove_duplicates() {
        global $wpdb;

        $contacts_table = self::get_contacts_table();
        $deleted = 0;

        // Remove duplicates by email (keep oldest)
        $deleted += (int) $wpdb->query("
            DELETE c1 FROM $contacts_table c1
            INNER JOIN $contacts_table c2
            WHERE c1.id > c2.id
            AND c1.email IS NOT NULL
            AND c1.email != ''
            AND c1.email = c2.email
        ");

        // Remove duplicates by LinkedIn URL (keep oldest)
        $deleted += (int) $wpdb->query("
            DELETE c1 FROM $contacts_table c1
            INNER JOIN $contacts_table c2
            WHERE c1.id > c2.id
            AND c1.linkedin_url IS NOT NULL
            AND c1.linkedin_url != ''
            AND c1.linkedin_url = c2.linkedin_url
        ");

        // Remove duplicates by first_name + last_name + company_id (keep oldest)
        $deleted += (int) $wpdb->query("
            DELETE c1 FROM $contacts_table c1
            INNER JOIN $contacts_table c2
            WHERE c1.id > c2.id
            AND c1.first_name = c2.first_name
            AND c1.last_name = c2.last_name
            AND c1.company_id = c2.company_id
            AND c1.company_id IS NOT NULL
        ");

        return $deleted;
    }

    /**
     * Count duplicate contacts
     *
     * @return array Counts of duplicates by type
     */
    public static function count_duplicates() {
        global $wpdb;

        $contacts_table = self::get_contacts_table();

        // Count email duplicates
        $email_dupes = (int) $wpdb->get_var("
            SELECT COUNT(*) - COUNT(DISTINCT email)
            FROM $contacts_table
            WHERE email IS NOT NULL AND email != ''
        ");

        // Count LinkedIn duplicates
        $linkedin_dupes = (int) $wpdb->get_var("
            SELECT COUNT(*) - COUNT(DISTINCT linkedin_url)
            FROM $contacts_table
            WHERE linkedin_url IS NOT NULL AND linkedin_url != ''
        ");

        // Count name+company duplicates
        $name_dupes = (int) $wpdb->get_var("
            SELECT SUM(cnt - 1) FROM (
                SELECT COUNT(*) as cnt
                FROM $contacts_table
                WHERE company_id IS NOT NULL
                GROUP BY first_name, last_name, company_id
                HAVING COUNT(*) > 1
            ) as dupes
        ");

        return [
            'email_duplicates' => max(0, $email_dupes),
            'linkedin_duplicates' => max(0, $linkedin_dupes),
            'name_company_duplicates' => max(0, (int) $name_dupes),
            'total_duplicates' => max(0, $email_dupes) + max(0, $linkedin_dupes) + max(0, (int) $name_dupes)
        ];
    }

    /**
     * Get count of incomplete contacts (for preview)
     *
     * @return array Counts of incomplete contacts
     */
    public static function count_incomplete_contacts() {
        global $wpdb;

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $no_job = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM $contacts_table
            WHERE job_title IS NULL OR TRIM(job_title) = ''
        ");

        $no_company = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM $contacts_table
            WHERE company_id IS NULL OR company_id = 0
        ");

        $orphaned = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM $contacts_table c
            LEFT JOIN $companies_table co ON c.company_id = co.id
            WHERE c.company_id IS NOT NULL
            AND c.company_id > 0
            AND co.id IS NULL
        ");

        return [
            'no_job_title' => $no_job,
            'no_company' => $no_company,
            'orphaned_company_ref' => $orphaned,
            'total_incomplete' => $no_job + $no_company + $orphaned
        ];
    }

    // ============================================
    // USER NETWORKING FEATURES (saved contacts, target companies, outreach)
    // ============================================

    /**
     * Save a contact for a user
     *
     * @param int $user_id User ID
     * @param int $contact_id Contact ID
     * @return bool Success
     */
    public static function save_contact($user_id, $contact_id) {
        $saved = get_user_meta($user_id, 'sffc_saved_contacts', true) ?: [];
        if (!in_array($contact_id, $saved)) {
            $saved[] = $contact_id;
            update_user_meta($user_id, 'sffc_saved_contacts', $saved);
        }
        return true;
    }

    /**
     * Unsave a contact for a user
     *
     * @param int $user_id User ID
     * @param int $contact_id Contact ID
     * @return bool Success
     */
    public static function unsave_contact($user_id, $contact_id) {
        $saved = get_user_meta($user_id, 'sffc_saved_contacts', true) ?: [];
        $saved = array_filter($saved, fn($id) => $id != $contact_id);
        update_user_meta($user_id, 'sffc_saved_contacts', array_values($saved));
        return true;
    }

    /**
     * Get saved contacts for a user
     *
     * @param int $user_id User ID
     * @return array Saved contacts
     */
    public static function get_saved_contacts($user_id) {
        global $wpdb;

        $saved_ids = get_user_meta($user_id, 'sffc_saved_contacts', true) ?: [];
        if (empty($saved_ids)) {
            return [];
        }

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $ids_string = implode(',', array_map('intval', $saved_ids));

        $contacts = $wpdb->get_results("
            SELECT c.*, co.name as company_name, co.main_industry as company_industry
            FROM $contacts_table c
            LEFT JOIN $companies_table co ON c.company_id = co.id
            WHERE c.id IN ($ids_string)
            ORDER BY c.last_name ASC
        ");

        return $contacts ?: [];
    }

    /**
     * Check if a contact is saved by user
     *
     * @param int $user_id User ID
     * @param int $contact_id Contact ID
     * @return bool
     */
    public static function is_contact_saved($user_id, $contact_id) {
        $saved = get_user_meta($user_id, 'sffc_saved_contacts', true) ?: [];
        return in_array($contact_id, $saved);
    }

    /**
     * Save a target company for a user
     *
     * @param int $user_id User ID
     * @param int $company_id Company ID
     * @return bool Success
     */
    public static function save_target_company($user_id, $company_id) {
        $targets = get_user_meta($user_id, 'sffc_target_companies', true) ?: [];
        if (!in_array($company_id, $targets)) {
            $targets[] = $company_id;
            update_user_meta($user_id, 'sffc_target_companies', $targets);
        }
        return true;
    }

    /**
     * Remove a target company for a user
     *
     * @param int $user_id User ID
     * @param int $company_id Company ID
     * @return bool Success
     */
    public static function remove_target_company($user_id, $company_id) {
        $targets = get_user_meta($user_id, 'sffc_target_companies', true) ?: [];
        $targets = array_filter($targets, fn($id) => $id != $company_id);
        update_user_meta($user_id, 'sffc_target_companies', array_values($targets));
        return true;
    }

    /**
     * Get target companies for a user
     *
     * @param int $user_id User ID
     * @return array Target companies with contact counts
     */
    public static function get_target_companies($user_id) {
        global $wpdb;

        $target_ids = get_user_meta($user_id, 'sffc_target_companies', true) ?: [];
        if (empty($target_ids)) {
            return [];
        }

        $companies_table = self::get_companies_table();
        $contacts_table = self::get_contacts_table();

        $ids_string = implode(',', array_map('intval', $target_ids));

        $companies = $wpdb->get_results("
            SELECT co.*, COUNT(c.id) as contact_count
            FROM $companies_table co
            LEFT JOIN $contacts_table c ON c.company_id = co.id
            WHERE co.id IN ($ids_string)
            GROUP BY co.id
            ORDER BY co.name ASC
        ");

        return $companies ?: [];
    }

    /**
     * Check if a company is a target for user
     *
     * @param int $user_id User ID
     * @param int $company_id Company ID
     * @return bool
     */
    public static function is_target_company($user_id, $company_id) {
        $targets = get_user_meta($user_id, 'sffc_target_companies', true) ?: [];
        return in_array($company_id, $targets);
    }

    /**
     * Log outreach to a contact
     *
     * @param int $user_id User ID
     * @param int $contact_id Contact ID
     * @param string $notes Optional notes
     * @return bool Success
     */
    public static function log_outreach($user_id, $contact_id, $notes = '') {
        $outreach = get_user_meta($user_id, 'sffc_outreach_log', true) ?: [];
        $outreach[$contact_id] = [
            'contacted_at' => current_time('mysql'),
            'notes' => sanitize_textarea_field($notes)
        ];
        update_user_meta($user_id, 'sffc_outreach_log', $outreach);
        return true;
    }

    /**
     * Remove outreach log entry
     *
     * @param int $user_id User ID
     * @param int $contact_id Contact ID
     * @return bool Success
     */
    public static function remove_outreach($user_id, $contact_id) {
        $outreach = get_user_meta($user_id, 'sffc_outreach_log', true) ?: [];
        unset($outreach[$contact_id]);
        update_user_meta($user_id, 'sffc_outreach_log', $outreach);
        return true;
    }

    /**
     * Get outreach log for a user
     *
     * @param int $user_id User ID
     * @return array Outreach log with contact data
     */
    public static function get_outreach_log($user_id) {
        global $wpdb;

        $outreach = get_user_meta($user_id, 'sffc_outreach_log', true) ?: [];
        if (empty($outreach)) {
            return [];
        }

        $contact_ids = array_keys($outreach);
        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        $ids_string = implode(',', array_map('intval', $contact_ids));

        $contacts = $wpdb->get_results("
            SELECT c.*, co.name as company_name
            FROM $contacts_table c
            LEFT JOIN $companies_table co ON c.company_id = co.id
            WHERE c.id IN ($ids_string)
        ");

        // Merge outreach data with contact data
        $result = [];
        foreach ($contacts as $contact) {
            $contact->outreach = $outreach[$contact->id] ?? [];
            $result[] = $contact;
        }

        // Sort by contacted_at descending
        usort($result, function($a, $b) {
            $time_a = strtotime($a->outreach['contacted_at'] ?? '1970-01-01');
            $time_b = strtotime($b->outreach['contacted_at'] ?? '1970-01-01');
            return $time_b - $time_a;
        });

        return $result;
    }

    /**
     * Check if contact has been contacted
     *
     * @param int $user_id User ID
     * @param int $contact_id Contact ID
     * @return array|false Outreach data or false
     */
    public static function get_contact_outreach($user_id, $contact_id) {
        $outreach = get_user_meta($user_id, 'sffc_outreach_log', true) ?: [];
        return $outreach[$contact_id] ?? false;
    }

    /**
     * Get contacts matching a job based on company, location, or department
     *
     * @param int $job_id Job post ID
     * @param int $limit Maximum contacts to return
     * @return array Matching contacts with relevance scores
     */
    public static function get_contacts_for_job($job_id, $limit = 5) {
        global $wpdb;

        $post = get_post($job_id);
        if (!$post || $post->post_type !== 'sffc_job') {
            return [];
        }

        // Get job metadata
        $company = get_post_meta($job_id, 'sffc_company', true);
        $location_city = get_post_meta($job_id, 'sffc_location_city', true);
        $location_country = get_post_meta($job_id, 'sffc_location_country', true);
        $department = get_post_meta($job_id, 'sffc_department', true);
        $job_family = get_post_meta($job_id, 'sffc_job_family', true);

        // Fallback to full location if city not set
        if (empty($location_city)) {
            $location = get_post_meta($job_id, 'sffc_location', true);
            if (!empty($location)) {
                // Try to extract city from location string
                $parts = explode(',', $location);
                if (count($parts) >= 1) {
                    $location_city = trim($parts[0]);
                }
                if (count($parts) >= 2) {
                    $location_country = trim(end($parts));
                }
            }
        }

        $contacts_table = self::get_contacts_table();
        $companies_table = self::get_companies_table();

        // Build query with relevance scoring
        $score_cases = [];
        $where_conditions = [];
        $values = [];

        // Company match (highest priority)
        if (!empty($company)) {
            $score_cases[] = "WHEN co.name LIKE %s THEN 100";
            $values[] = '%' . $wpdb->esc_like($company) . '%';
            $where_conditions[] = "co.name LIKE %s";
            $values[] = '%' . $wpdb->esc_like($company) . '%';
        }

        // City match
        if (!empty($location_city)) {
            $score_cases[] = "WHEN c.city LIKE %s THEN 50";
            $values[] = '%' . $wpdb->esc_like($location_city) . '%';
            $where_conditions[] = "c.city LIKE %s";
            $values[] = '%' . $wpdb->esc_like($location_city) . '%';
        }

        // Country match
        if (!empty($location_country)) {
            $score_cases[] = "WHEN c.country LIKE %s THEN 30";
            $values[] = '%' . $wpdb->esc_like($location_country) . '%';
            $where_conditions[] = "c.country LIKE %s";
            $values[] = '%' . $wpdb->esc_like($location_country) . '%';
        }

        // Department/job family match
        if (!empty($department)) {
            $score_cases[] = "WHEN c.departments LIKE %s THEN 40";
            $values[] = '%' . $wpdb->esc_like($department) . '%';
            $where_conditions[] = "c.departments LIKE %s";
            $values[] = '%' . $wpdb->esc_like($department) . '%';
        }

        if (!empty($job_family)) {
            $score_cases[] = "WHEN c.departments LIKE %s THEN 35";
            $values[] = '%' . $wpdb->esc_like($job_family) . '%';
            $where_conditions[] = "c.departments LIKE %s";
            $values[] = '%' . $wpdb->esc_like($job_family) . '%';
        }

        // If no conditions, return empty
        if (empty($where_conditions)) {
            return [];
        }

        $score_sql = "CASE " . implode(' ', $score_cases) . " ELSE 0 END";
        $where_sql = "(" . implode(' OR ', $where_conditions) . ")";

        $sql = "SELECT c.*, co.name as company_name, co.domain as company_domain,
                       co.main_industry, co.linkedin_url as company_linkedin,
                       co.website as company_website,
                       ($score_sql) as relevance_score
                FROM $contacts_table c
                LEFT JOIN $companies_table co ON c.company_id = co.id
                WHERE $where_sql
                ORDER BY relevance_score DESC, c.seniority DESC
                LIMIT %d";

        $values[] = $limit;

        $contacts = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Add match reason for each contact
        foreach ($contacts as &$contact) {
            $reasons = [];
            if (!empty($company) && stripos($contact->company_name, $company) !== false) {
                $reasons[] = 'Works at ' . $contact->company_name;
            }
            if (!empty($location_city) && stripos($contact->city, $location_city) !== false) {
                $reasons[] = 'Based in ' . $contact->city;
            }
            if (!empty($location_country) && stripos($contact->country, $location_country) !== false) {
                $reasons[] = 'Located in ' . $contact->country;
            }
            if (!empty($department) && stripos($contact->departments, $department) !== false) {
                $reasons[] = 'Works in ' . $department;
            }
            $contact->match_reasons = $reasons;
        }

        return $contacts;
    }

    /**
     * Check if user has access to job contacts feature (MemberPress integration)
     *
     * @param int $user_id User ID
     * @return bool Whether user has access
     */
    public static function user_has_contacts_access($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Check MemberPress integration
        if (class_exists('SFFC_MemberPress_Integration')) {
            $memberpress = SFFC_MemberPress_Integration::get_instance();
            return $memberpress->has_premium_access($user_id);
        }

        // Fallback: check if MemberPress is active and user has any active subscription
        if (class_exists('MeprUser')) {
            $mepr_user = new MeprUser($user_id);
            return $mepr_user->is_active();
        }

        // If no membership system, allow for logged-in users
        return is_user_logged_in();
    }
}
