<?php
/**
 * Application Pack Tiers Management
 *
 * Handles membership tier configuration and access control for Application Pack documents.
 * Integrates with MemberPress for membership-based access.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Pack_Tiers {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option keys
     */
    const OPTION_TIERS = 'sffc_toolkit_tiers';
    const OPTION_DOCUMENTS = 'sffc_toolkit_documents';
    const OPTION_UPGRADE_URL = 'sffc_toolkit_upgrade_url';

    /**
     * Default tier configuration
     */
    private $default_tiers = array(
        'basic' => array(
            'name' => 'Basic',
            'description' => 'Free tier with networking messages',
            'color' => '#64748b',
            'bg_color' => '#f1f5f9',
            'documents' => array('networking'),
            'memberpress_products' => array(),
            'order' => 1,
        ),
        'pro' => array(
            'name' => 'Pro',
            'description' => 'CV and Cover Letter generation',
            'color' => '#1e40af',
            'bg_color' => '#dbeafe',
            'documents' => array('networking', 'cv', 'cover_letter'),
            'memberpress_products' => array(),
            'order' => 2,
        ),
        'advanced' => array(
            'name' => 'Advanced',
            'description' => 'Full access to all documents',
            'color' => '#065f46',
            'bg_color' => '#d1fae5',
            'documents' => array('networking', 'cv', 'cover_letter', 'interview_prep', 'company_brief', 'full_pack'),
            'memberpress_products' => array(),
            'order' => 3,
        ),
    );

    /**
     * Default document definitions
     */
    private $default_documents = array(
        'cv' => array(
            'name' => 'Tailored CV',
            'description' => 'ATS-optimized CV highlighting relevant experience for this role',
            'formats' => array('pdf', 'docx'),
            'icon' => 'user',
            'enabled' => true,
            'order' => 1,
        ),
        'cover_letter' => array(
            'name' => 'Cover Letter',
            'description' => 'Professional letter addressing why you\'re ideal for this position',
            'formats' => array('pdf', 'docx'),
            'icon' => 'mail',
            'enabled' => true,
            'order' => 2,
        ),
        'interview_prep' => array(
            'name' => 'Interview Prep Sheet',
            'description' => 'Role-specific questions, answers, and talking points',
            'formats' => array('pdf'),
            'icon' => 'clipboard',
            'enabled' => true,
            'order' => 3,
        ),
        'company_brief' => array(
            'name' => 'Company Intel Brief',
            'description' => 'Research summary, competitors, culture, and interview intel',
            'formats' => array('pdf'),
            'icon' => 'building',
            'enabled' => true,
            'order' => 4,
        ),
        'networking' => array(
            'name' => 'Networking Messages',
            'description' => '5 templates: LinkedIn connect, InMail, email outreach, referral request',
            'formats' => array('text'),
            'icon' => 'message',
            'enabled' => true,
            'order' => 5,
        ),
        'full_pack' => array(
            'name' => 'Full Application Pack',
            'description' => 'Everything above in a single ZIP download',
            'formats' => array('zip'),
            'icon' => 'package',
            'enabled' => true,
            'featured' => true,
            'order' => 6,
        ),
    );

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
        // Initialize default options if not set
        add_action('init', array($this, 'maybe_initialize_defaults'));
    }

    /**
     * Initialize default options if they don't exist
     */
    public function maybe_initialize_defaults() {
        if (false === get_option(self::OPTION_TIERS)) {
            update_option(self::OPTION_TIERS, $this->default_tiers);
        }
        if (false === get_option(self::OPTION_DOCUMENTS)) {
            update_option(self::OPTION_DOCUMENTS, $this->default_documents);
        }
        if (false === get_option(self::OPTION_UPGRADE_URL)) {
            update_option(self::OPTION_UPGRADE_URL, '/membership/');
        }
    }

    /**
     * Get all tiers
     */
    public function get_tiers() {
        $tiers = get_option(self::OPTION_TIERS, $this->default_tiers);

        // Sort by order
        uasort($tiers, function($a, $b) {
            return ($a['order'] ?? 99) - ($b['order'] ?? 99);
        });

        return $tiers;
    }

    /**
     * Get a specific tier
     */
    public function get_tier($tier_id) {
        $tiers = $this->get_tiers();
        return isset($tiers[$tier_id]) ? $tiers[$tier_id] : null;
    }

    /**
     * Get all document definitions
     */
    public function get_documents() {
        $documents = get_option(self::OPTION_DOCUMENTS, $this->default_documents);

        // Sort by order
        uasort($documents, function($a, $b) {
            return ($a['order'] ?? 99) - ($b['order'] ?? 99);
        });

        return $documents;
    }

    /**
     * Get a specific document definition
     */
    public function get_document($doc_id) {
        $documents = $this->get_documents();
        return isset($documents[$doc_id]) ? $documents[$doc_id] : null;
    }

    /**
     * Get enabled documents only
     */
    public function get_enabled_documents() {
        $documents = $this->get_documents();
        return array_filter($documents, function($doc) {
            return !empty($doc['enabled']);
        });
    }

    /**
     * Get upgrade URL
     */
    public function get_upgrade_url() {
        return get_option(self::OPTION_UPGRADE_URL, '/membership/');
    }

    /**
     * Get the user's current tier based on their MemberPress subscriptions
     */
    public function get_user_tier($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // If not logged in, return null (guest)
        if (!$user_id) {
            return null;
        }

        // Check MemberPress subscriptions
        $user_tier = $this->get_tier_from_memberpress($user_id);

        if ($user_tier) {
            return $user_tier;
        }

        // Default to basic tier for logged-in users
        return 'basic';
    }

    /**
     * Get tier from MemberPress subscriptions
     */
    private function get_tier_from_memberpress($user_id) {
        // Check if MemberPress is active
        if (!class_exists('MeprUser')) {
            return null;
        }

        $mepr_user = new \MeprUser($user_id);
        $active_subscriptions = $mepr_user->active_product_subscriptions();

        if (empty($active_subscriptions)) {
            return null;
        }

        // Get all tiers and check which one the user qualifies for
        $tiers = $this->get_tiers();
        $highest_tier = null;
        $highest_order = 0;

        foreach ($tiers as $tier_id => $tier) {
            $tier_products = $tier['memberpress_products'] ?? array();

            if (empty($tier_products)) {
                continue;
            }

            // Check if user has any of the tier's products
            foreach ($tier_products as $product_id) {
                if (in_array($product_id, $active_subscriptions)) {
                    $tier_order = $tier['order'] ?? 0;
                    if ($tier_order > $highest_order) {
                        $highest_order = $tier_order;
                        $highest_tier = $tier_id;
                    }
                    break;
                }
            }
        }

        return $highest_tier;
    }

    /**
     * Check if a user has access to a specific document
     */
    public function user_has_access($doc_id, $user_id = null) {
        $user_tier = $this->get_user_tier($user_id);

        // Guest users have no access
        if (!$user_tier) {
            return false;
        }

        return $this->tier_has_document($user_tier, $doc_id);
    }

    /**
     * Check if a tier includes a specific document
     */
    public function tier_has_document($tier_id, $doc_id) {
        $tier = $this->get_tier($tier_id);

        if (!$tier) {
            return false;
        }

        $tier_documents = $tier['documents'] ?? array();
        return in_array($doc_id, $tier_documents);
    }

    /**
     * Get the minimum tier required for a document
     */
    public function get_required_tier($doc_id) {
        $tiers = $this->get_tiers();

        foreach ($tiers as $tier_id => $tier) {
            $tier_documents = $tier['documents'] ?? array();
            if (in_array($doc_id, $tier_documents)) {
                return $tier_id;
            }
        }

        return 'advanced'; // Default to highest tier if not found
    }

    /**
     * Get documents available for a specific tier
     */
    public function get_tier_documents($tier_id) {
        $tier = $this->get_tier($tier_id);

        if (!$tier) {
            return array();
        }

        $tier_doc_ids = $tier['documents'] ?? array();
        $all_documents = $this->get_documents();

        $tier_documents = array();
        foreach ($tier_doc_ids as $doc_id) {
            if (isset($all_documents[$doc_id])) {
                $tier_documents[$doc_id] = $all_documents[$doc_id];
            }
        }

        return $tier_documents;
    }

    /**
     * Get tier display info for a user
     */
    public function get_user_tier_info($user_id = null) {
        $tier_id = $this->get_user_tier($user_id);

        if (!$tier_id) {
            return array(
                'id' => 'guest',
                'name' => 'Guest',
                'color' => '#94a3b8',
                'bg_color' => '#f8fafc',
                'documents' => array(),
            );
        }

        $tier = $this->get_tier($tier_id);

        return array(
            'id' => $tier_id,
            'name' => $tier['name'] ?? ucfirst($tier_id),
            'color' => $tier['color'] ?? '#64748b',
            'bg_color' => $tier['bg_color'] ?? '#f1f5f9',
            'documents' => $tier['documents'] ?? array(),
        );
    }

    /**
     * Save tier configuration
     */
    public function save_tiers($tiers) {
        return update_option(self::OPTION_TIERS, $tiers);
    }

    /**
     * Save document configuration
     */
    public function save_documents($documents) {
        return update_option(self::OPTION_DOCUMENTS, $documents);
    }

    /**
     * Save upgrade URL
     */
    public function save_upgrade_url($url) {
        return update_option(self::OPTION_UPGRADE_URL, sanitize_text_field($url));
    }

    /**
     * Update tier MemberPress products
     */
    public function update_tier_products($tier_id, $product_ids) {
        $tiers = $this->get_tiers();

        if (!isset($tiers[$tier_id])) {
            return false;
        }

        $tiers[$tier_id]['memberpress_products'] = array_map('intval', $product_ids);

        return $this->save_tiers($tiers);
    }

    /**
     * Update tier documents
     */
    public function update_tier_documents($tier_id, $doc_ids) {
        $tiers = $this->get_tiers();

        if (!isset($tiers[$tier_id])) {
            return false;
        }

        $tiers[$tier_id]['documents'] = array_map('sanitize_key', $doc_ids);

        return $this->save_tiers($tiers);
    }

    /**
     * Get comparison data for upgrade prompts
     */
    public function get_upgrade_comparison($current_tier_id, $target_tier_id = null) {
        $tiers = $this->get_tiers();
        $documents = $this->get_documents();

        $current_tier = $this->get_tier($current_tier_id);
        $current_docs = $current_tier ? ($current_tier['documents'] ?? array()) : array();

        // If no target specified, get the next tier up
        if (!$target_tier_id) {
            $found_current = false;
            foreach ($tiers as $tid => $tier) {
                if ($found_current) {
                    $target_tier_id = $tid;
                    break;
                }
                if ($tid === $current_tier_id) {
                    $found_current = true;
                }
            }
        }

        $target_tier = $this->get_tier($target_tier_id);
        $target_docs = $target_tier ? ($target_tier['documents'] ?? array()) : array();

        // Find additional documents in target tier
        $additional_docs = array_diff($target_docs, $current_docs);

        $additional_doc_info = array();
        foreach ($additional_docs as $doc_id) {
            if (isset($documents[$doc_id])) {
                $additional_doc_info[$doc_id] = $documents[$doc_id];
            }
        }

        return array(
            'current_tier' => $current_tier_id,
            'target_tier' => $target_tier_id,
            'target_tier_name' => $target_tier['name'] ?? ucfirst($target_tier_id),
            'additional_documents' => $additional_doc_info,
            'upgrade_url' => $this->get_upgrade_url(),
        );
    }

    /**
     * Get all MemberPress products for admin UI
     */
    public function get_memberpress_products() {
        if (!class_exists('MeprProduct')) {
            return array();
        }

        $products = \MeprProduct::get_all();
        $result = array();

        foreach ($products as $product) {
            $result[$product->ID] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'price' => $product->price,
            );
        }

        return $result;
    }
}

// Initialize
SFFC_Application_Pack_Tiers::get_instance();
