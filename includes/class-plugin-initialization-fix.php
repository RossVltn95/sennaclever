<?php

/**
 * Plugin Initialization Fix
 * Fixes WordPress 6.7.0 compatibility issues
 * 
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to fix plugin initialization timing issues
 */
class SFFC_Plugin_Initialization_Fix
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
        $this->fix_initialization_timing();
        $this->fix_conditional_tags();
        $this->fix_null_parameters();
    }

    /**
     * Fix translation loading timing
     * Move textdomain loading to init hook instead of calling it directly
     */
    private function fix_initialization_timing()
    {
        // Remove the early textdomain loading from the main init method
        // It should only be called on 'init' action or later
        remove_action('init', array(Skill_Farm_Finance_Career::get_instance(), 'init'), 10);

        // Add it back with proper timing
        add_action('init', array($this, 'load_textdomain'), 1);
        add_action('init', array(Skill_Farm_Finance_Career::get_instance(), 'init_delayed'), 2);
    }

    /**
     * Load plugin textdomain properly
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('senna-finance', false, dirname(plugin_basename(SFFC_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Fix is_singular() conditional tag usage
     * Wrap conditional query tags to prevent early calls
     */
    private function fix_conditional_tags()
    {
        // Hook into wp action when conditional tags are available
        add_action('wp', array($this, 'setup_conditional_checks'), 1);
    }

    /**
     * Setup conditional checks after query is run
     */
    public function setup_conditional_checks()
    {
        // Now conditional tags are safe to use
        if (is_singular() || is_page()) {
            // Signal that assets can be enqueued
            add_filter('sffc_can_enqueue_assets', '__return_true');
        }
    }

    /**
     * Fix deprecated null parameter warnings
     */
    private function fix_null_parameters()
    {
        // Add filters to sanitize potentially null values
        add_filter('sffc_strpos_haystack', array($this, 'sanitize_string'), 10, 1);
        add_filter('sffc_str_replace_subject', array($this, 'sanitize_string'), 10, 1);
    }

    /**
     * Sanitize string to prevent null warnings
     */
    public function sanitize_string($value)
    {
        return $value === null ? '' : $value;
    }
}

// Initialize the fix class early
add_action('plugins_loaded', array('SFFC_Plugin_Initialization_Fix', 'get_instance'), 5);
