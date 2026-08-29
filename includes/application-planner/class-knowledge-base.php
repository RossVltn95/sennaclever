<?php
/**
 * Knowledge Base
 *
 * Central repository for all knowledge files used by the Application Planner:
 * - Synonyms for keyword matching
 * - Credential recognition patterns
 * - Industry/sector knowledge
 * - Seniority level definitions
 * - Rewrite templates
 * - Dealbreaker rules
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Knowledge_Base {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cached knowledge data
     */
    private $cache = [];

    /**
     * Base path for knowledge files
     */
    private $knowledge_path;

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
     * Constructor
     */
    private function __construct() {
        $this->knowledge_path = dirname(__FILE__) . '/knowledge/';
    }

    /**
     * Load a JSON knowledge file
     *
     * @param string $file Filename without extension
     * @return array Decoded JSON data
     */
    private function load_json($file) {
        if (isset($this->cache[$file])) {
            return $this->cache[$file];
        }

        $path = $this->knowledge_path . $file . '.json';

        if (!file_exists($path)) {
            error_log("Knowledge Base: File not found: {$path}");
            return [];
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Knowledge Base: JSON error in {$file}: " . json_last_error_msg());
            return [];
        }

        $this->cache[$file] = $data;
        return $data;
    }

    /**
     * Get synonyms dictionary
     *
     * @return array Synonym mappings
     */
    public function get_synonyms() {
        return $this->load_json('synonyms');
    }

    /**
     * Get synonyms for a specific term
     *
     * @param string $term Term to look up
     * @return array List of synonyms including the term itself
     */
    public function get_synonyms_for($term) {
        $synonyms = $this->get_synonyms();
        $term_lower = strtolower($term);

        // Check if it's a canonical term
        foreach ($synonyms as $key => $data) {
            if (strtolower($data['canonical']) === $term_lower) {
                return array_merge([$data['canonical']], $data['variations']);
            }

            // Check if it's a variation
            foreach ($data['variations'] as $variation) {
                if (strtolower($variation) === $term_lower) {
                    return array_merge([$data['canonical']], $data['variations']);
                }
            }
        }

        // Not found, return just the term
        return [$term];
    }

    /**
     * Get credentials dictionary
     *
     * @return array Credential patterns and metadata
     */
    public function get_credentials() {
        return $this->load_json('credentials');
    }

    /**
     * Get industries dictionary
     *
     * @return array Industry definitions and relationships
     */
    public function get_industries() {
        return $this->load_json('industries');
    }

    /**
     * Get seniority levels
     *
     * @return array Seniority level definitions
     */
    public function get_seniority_levels() {
        return $this->load_json('seniority-levels');
    }

    /**
     * Get rewrite templates
     *
     * @return array Templates for bullet rewrites
     */
    public function get_rewrite_templates() {
        return $this->load_json('rewrite-templates');
    }

    /**
     * Get dealbreaker rules
     *
     * @return array Rules for identifying dealbreakers
     */
    public function get_dealbreaker_rules() {
        return $this->load_json('dealbreaker-rules');
    }

    /**
     * Get role taxonomy used by the deterministic buy-side matcher.
     *
     * @return array
     */
    public function get_role_taxonomy() {
        return $this->load_json('role-taxonomy');
    }

    /**
     * Find a rewrite template for a specific issue
     *
     * @param string $issue_type Type of issue (e.g., 'weak_verb_supported')
     * @return array|null Template data or null if not found
     */
    public function find_rewrite_template($issue_type) {
        $templates = $this->get_rewrite_templates();

        if (isset($templates[$issue_type])) {
            return $templates[$issue_type];
        }

        // Try partial match
        foreach ($templates as $key => $template) {
            if (strpos($issue_type, $key) !== false || strpos($key, $issue_type) !== false) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Check if a sector is transferable to another
     *
     * @param string $from_sector Source sector
     * @param string $to_sector Target sector
     * @return array|false Transfer info or false if not transferable
     */
    public function check_sector_transfer($from_sector, $to_sector) {
        $industries = $this->get_industries();

        $from_key = $this->find_industry_key($from_sector);
        $to_key = $this->find_industry_key($to_sector);

        if (!$to_key || !isset($industries[$to_key])) {
            return false;
        }

        $target_industry = $industries[$to_key];

        if (isset($target_industry['transferable_from']) && in_array($from_key, $target_industry['transferable_from'])) {
            return [
                'transferable' => true,
                'narrative' => $target_industry['transfer_narrative'] ?? '',
            ];
        }

        return false;
    }

    /**
     * Find industry key by name or sub-sector
     */
    private function find_industry_key($name) {
        $industries = $this->get_industries();
        $name_lower = strtolower($name);

        foreach ($industries as $key => $industry) {
            if (strtolower($industry['canonical']) === $name_lower) {
                return $key;
            }

            foreach ($industry['sub_sectors'] as $sub) {
                if (strtolower($sub) === $name_lower) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Get expected years for a seniority level
     *
     * @param string $level Seniority level
     * @return array [min, max] years
     */
    public function get_expected_years($level) {
        $levels = $this->get_seniority_levels();
        $level_lower = strtolower($level);

        if (isset($levels[$level_lower])) {
            return $levels[$level_lower]['years_range'];
        }

        // Default if not found
        return [0, 99];
    }

    /**
     * Get dealbreaker severity and advice
     *
     * @param string $rule_type Type of dealbreaker rule
     * @return array|null Rule data or null
     */
    public function get_dealbreaker_rule($rule_type) {
        $rules = $this->get_dealbreaker_rules();
        return $rules[$rule_type] ?? null;
    }

    /**
     * Check if a credential is in progress based on text
     *
     * @param string $credential_key Credential key
     * @param string $text Text to check
     * @return bool True if appears to be in progress
     */
    public function is_credential_in_progress($credential_key, $text) {
        $in_progress_patterns = [
            'candidate',
            'level 1', 'level 2', 'level 3',
            'level i', 'level ii', 'level iii',
            'in progress',
            'pursuing',
            'expected',
            'sitting for',
        ];

        $text_lower = strtolower($text);

        foreach ($in_progress_patterns as $pattern) {
            if (strpos($text_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the cache (useful for testing)
     */
    public function clear_cache() {
        $this->cache = [];
    }

    /**
     * Get all keywords as a flat array of canonical terms
     *
     * @return array List of canonical keyword terms
     */
    public function get_all_keywords() {
        $synonyms = $this->get_synonyms();
        return array_column($synonyms, 'canonical');
    }

    /**
     * Normalize a term to its canonical form
     *
     * @param string $term Term to normalize
     * @return string Canonical form or original term
     */
    public function normalize_term($term) {
        $synonyms = $this->get_synonyms();
        $term_lower = strtolower($term);

        foreach ($synonyms as $data) {
            if (strtolower($data['canonical']) === $term_lower) {
                return $data['canonical'];
            }

            foreach ($data['variations'] as $variation) {
                if (strtolower($variation) === $term_lower) {
                    return $data['canonical'];
                }
            }
        }

        return $term;
    }
}
