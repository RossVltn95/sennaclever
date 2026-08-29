<?php
/**
 * Utility to merge duplicate company taxonomies
 * 
 * Run this once to clean up existing duplicates
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Company_Taxonomy_Cleanup {
    
    /**
     * Run the cleanup
     */
    public static function cleanup_duplicate_companies() {
        $merged_count = 0;
        $error_count = 0;
        
        // Get all company terms
        $terms = get_terms([
            'taxonomy' => 'job_company',
            'hide_empty' => false,
            'fields' => 'all'
        ]);
        
        if (is_wp_error($terms)) {
            error_log('SFFC: Failed to get company terms: ' . $terms->get_error_message());
            return false;
        }
        
        // Group similar companies
        $company_groups = self::group_similar_companies($terms);
        
        // Merge each group
        foreach ($company_groups as $canonical_name => $group) {
            if (count($group) > 1) {
                $result = self::merge_company_group($group, $canonical_name);
                if ($result) {
                    $merged_count += count($group) - 1;
                } else {
                    $error_count++;
                }
            }
        }
        
        error_log("SFFC: Company cleanup complete. Merged $merged_count duplicates, $error_count errors.");
        
        return [
            'merged' => $merged_count,
            'errors' => $error_count
        ];
    }
    
    /**
     * Group similar companies together
     */
    private static function group_similar_companies($terms) {
        $groups = [];
        $processed = [];
        
        // Company name mappings (same as in class-custom-post-types.php)
        $canonical_mappings = [
            'j.p. morgan' => 'J.P. Morgan',
            'jp morgan' => 'J.P. Morgan',
            'jpmorgan' => 'J.P. Morgan',
            'goldman sachs' => 'Goldman Sachs',
            'goldman' => 'Goldman Sachs',
            'morgan stanley' => 'Morgan Stanley',
            'morganstanley' => 'Morgan Stanley',
            'bank of america' => 'Bank of America',
            'bofa' => 'Bank of America',
            'bankofamerica' => 'Bank of America',
            'blackstone' => 'Blackstone',
            'black stone' => 'Blackstone',
            'blackrock' => 'BlackRock',
            'black rock' => 'BlackRock',
            'rothschild & co' => 'Rothschild & Co',
            'rothschild' => 'Rothschild & Co',
            'state street' => 'State Street',
            'statestreet' => 'State Street',
            'lloyds' => 'Lloyds Banking Group',
            'lloyds banking' => 'Lloyds Banking Group',
            'lloyds banking group' => 'Lloyds Banking Group',
            'moelis' => 'Moelis & Company',
            'moelis & company' => 'Moelis & Company',
            'moelis and company' => 'Moelis & Company',
            'houlihan lokey' => 'Houlihan Lokey',
            'houlihanlokey' => 'Houlihan Lokey',
            'barings' => 'Barings',
            'aviva' => 'Aviva',
            'cibc' => 'CIBC',
            'santander' => 'Santander',
            'banco santander' => 'Santander',
            'fca' => 'FCA',
            'financial conduct authority' => 'FCA',
            'mfs' => 'MFS Investment Management',
            'mfs investment' => 'MFS Investment Management',
            'mfs investment management' => 'MFS Investment Management',
            'finatal' => 'Finatal',
            'marks sattin' => 'Marks Sattin',
            'marks_sattin' => 'Marks Sattin',
            'markssattin' => 'Marks Sattin',
            'pearse partners' => 'Pearse Partners',
            'pearse_partners' => 'Pearse Partners',
            'pearsepartners' => 'Pearse Partners'
        ];
        
        foreach ($terms as $term) {
            if (in_array($term->term_id, $processed)) {
                continue;
            }
            
            $lower_name = strtolower($term->name);
            $canonical_name = null;
            
            // Check if this matches a known canonical form
            if (isset($canonical_mappings[$lower_name])) {
                $canonical_name = $canonical_mappings[$lower_name];
            } else {
                // Use the term's own name as canonical
                $canonical_name = $term->name;
            }
            
            // Initialize group if not exists
            if (!isset($groups[$canonical_name])) {
                $groups[$canonical_name] = [];
            }
            
            // Add this term to the group
            $groups[$canonical_name][] = $term;
            $processed[] = $term->term_id;
            
            // Find other similar terms
            foreach ($terms as $other_term) {
                if (in_array($other_term->term_id, $processed)) {
                    continue;
                }
                
                $other_lower = strtolower($other_term->name);
                
                // Check if this matches the same canonical form
                if (isset($canonical_mappings[$other_lower]) && $canonical_mappings[$other_lower] === $canonical_name) {
                    $groups[$canonical_name][] = $other_term;
                    $processed[] = $other_term->term_id;
                }
                // Check for slug match
                elseif (sanitize_title($other_term->name) === sanitize_title($canonical_name)) {
                    $groups[$canonical_name][] = $other_term;
                    $processed[] = $other_term->term_id;
                }
                // Check for similarity
                elseif (self::are_companies_similar($canonical_name, $other_term->name)) {
                    $groups[$canonical_name][] = $other_term;
                    $processed[] = $other_term->term_id;
                }
            }
        }
        
        return $groups;
    }
    
    /**
     * Check if two company names are similar enough to be the same
     */
    private static function are_companies_similar($name1, $name2) {
        $lower1 = strtolower($name1);
        $lower2 = strtolower($name2);
        
        // Exact match
        if ($lower1 === $lower2) {
            return true;
        }
        
        // Remove common suffixes
        $pattern = '/\s+(inc|incorporated|corp|corporation|ltd|limited|llc|llp|plc|ag|sa|gmbh|co\.?|company|group|holdings|partners|capital|management|investments?|advisors?|securities|services|solutions|consulting|associates|international|global)\.?$/i';
        $clean1 = preg_replace($pattern, '', $lower1);
        $clean2 = preg_replace($pattern, '', $lower2);
        
        if ($clean1 === $clean2) {
            return true;
        }
        
        // Check if one contains the other
        if ((strlen($lower1) > 3 && strpos($lower2, $lower1) !== false) ||
            (strlen($lower2) > 3 && strpos($lower1, $lower2) !== false)) {
            // Use similarity percentage
            similar_text($lower1, $lower2, $percent);
            if ($percent > 80) {
                return true;
            }
        }
        
        // Check if slugs match
        if (sanitize_title($name1) === sanitize_title($name2)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Merge a group of company terms into one
     */
    private static function merge_company_group($terms, $canonical_name) {
        if (empty($terms)) {
            return false;
        }
        
        // Find the best term to keep (prefer the one with canonical name or most posts)
        $primary_term = null;
        $max_count = -1;
        
        foreach ($terms as $term) {
            if ($term->name === $canonical_name) {
                $primary_term = $term;
                break;
            }
            if ($term->count > $max_count) {
                $max_count = $term->count;
                $primary_term = $term;
            }
        }
        
        if (!$primary_term) {
            $primary_term = $terms[0];
        }
        
        // Update primary term to canonical name if needed
        if ($primary_term->name !== $canonical_name) {
            wp_update_term($primary_term->term_id, 'job_company', [
                'name' => $canonical_name,
                'slug' => sanitize_title($canonical_name)
            ]);
        }
        
        // Merge all other terms into the primary
        foreach ($terms as $term) {
            if ($term->term_id === $primary_term->term_id) {
                continue;
            }
            
            // Get all posts with this term
            $posts = get_posts([
                'post_type' => 'sffc_job',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'job_company',
                        'field' => 'term_id',
                        'terms' => $term->term_id
                    ]
                ]
            ]);
            
            // Reassign posts to primary term
            foreach ($posts as $post) {
                wp_remove_object_terms($post->ID, $term->term_id, 'job_company');
                wp_set_object_terms($post->ID, $primary_term->term_id, 'job_company', true);
            }
            
            // Delete the duplicate term
            wp_delete_term($term->term_id, 'job_company');
            
            error_log("SFFC: Merged '{$term->name}' (ID: {$term->term_id}) into '{$canonical_name}' (ID: {$primary_term->term_id})");
        }
        
        return true;
    }
    
    /**
     * Add admin menu for manual cleanup
     */
    public static function add_admin_menu() {
        // Check if parent menu exists, if not add to Tools menu as fallback
        global $submenu;
        $parent_slug = 'sffc-dashboard';
        
        // Check if parent menu exists
        if (!isset($submenu[$parent_slug]) && !menu_page_url($parent_slug, false)) {
            // Fallback to Tools menu
            $parent_slug = 'tools.php';
        }
        
        add_submenu_page(
            $parent_slug,
            'Company Cleanup',
            'Company Cleanup',
            'manage_options',
            'sffc-company-cleanup',
            [__CLASS__, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public static function render_admin_page() {
        $message = '';
        
        if (isset($_POST['run_cleanup']) && check_admin_referer('sffc_company_cleanup')) {
            $result = self::cleanup_duplicate_companies();
            if ($result) {
                $message = sprintf(
                    '<div class="notice notice-success"><p>Successfully merged %d duplicate companies. %d errors occurred.</p></div>',
                    $result['merged'],
                    $result['errors']
                );
            } else {
                $message = '<div class="notice notice-error"><p>Failed to run company cleanup.</p></div>';
            }
        }
        
        // Get current stats
        $terms = get_terms([
            'taxonomy' => 'job_company',
            'hide_empty' => false,
            'fields' => 'all'
        ]);

        // Handle WP_Error or empty results
        if (is_wp_error($terms)) {
            $terms = [];
        }

        $groups = self::group_similar_companies($terms);
        $duplicate_count = 0;
        foreach ($groups as $group) {
            if (is_array($group) && count($group) > 1) {
                $duplicate_count += count($group) - 1;
            }
        }
        ?>
        <div class="wrap">
            <h1>Company Taxonomy Cleanup</h1>

            <?php echo $message; ?>

            <div class="card">
                <h2>Current Status</h2>
                <p>Total company terms: <strong><?php echo is_array($terms) ? count($terms) : 0; ?></strong></p>
                <p>Potential duplicates found: <strong><?php echo $duplicate_count; ?></strong></p>
                
                <?php if ($duplicate_count > 0): ?>
                    <h3>Duplicate Groups Found:</h3>
                    <ul>
                    <?php foreach ($groups as $canonical => $group): ?>
                        <?php if (is_array($group) && count($group) > 1): ?>
                            <li>
                                <strong><?php echo esc_html($canonical); ?></strong>:
                                <?php 
                                $names = array_map(function($term) { 
                                    return $term->name . ' (' . $term->count . ' jobs)'; 
                                }, $group);
                                echo esc_html(implode(', ', $names));
                                ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Run Cleanup</h2>
                <p>This will merge duplicate company terms into their canonical forms.</p>
                <p><strong>Note:</strong> This action cannot be undone. Please backup your database first.</p>
                
                <form method="post">
                    <?php wp_nonce_field('sffc_company_cleanup'); ?>
                    <p class="submit">
                        <input type="submit" name="run_cleanup" class="button button-primary" 
                               value="Run Company Cleanup" 
                               onclick="return confirm('Are you sure you want to merge duplicate companies? This cannot be undone.');">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}

// Add admin menu with higher priority to ensure parent menu exists
add_action('admin_menu', ['SFFC_Company_Taxonomy_Cleanup', 'add_admin_menu'], 99);

// Optional: Run cleanup automatically on plugin activation
// SFFC_Company_Taxonomy_Cleanup::cleanup_duplicate_companies();