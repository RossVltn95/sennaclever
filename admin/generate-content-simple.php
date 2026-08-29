<?php
/**
 * Simple Content Generation Page
 * Direct PHP execution - no AJAX complexity
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Process form submission
$message = '';
$error = '';

if (isset($_POST['generate_content']) && wp_verify_nonce($_POST['_wpnonce'], 'generate_prep_content')) {
    
    // Load required files
    $files = [
        SFFC_PLUGIN_DIR . 'includes/class-prep-interview-questions.php',
        SFFC_PLUGIN_DIR . 'includes/class-prep-terms-glossary.php',
        SFFC_PLUGIN_DIR . 'includes/class-prep-day-in-life-generator.php'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    $content_generated = 0;
    
    // Generate Case Studies (simplified)
    if (isset($_POST['generate_case_studies'])) {
        $case_studies = [
            [
                'title' => 'Microsoft Acquisition of Activision Blizzard',
                'content' => '<div class="prep-content"><h2>Deal Analysis</h2><p>$68.7B gaming acquisition case study...</p></div>'
            ],
            [
                'title' => 'Apollo Take-Private of Tegna',
                'content' => '<div class="prep-content"><h2>LBO Analysis</h2><p>$8.6B media consolidation case study...</p></div>'
            ],
            [
                'title' => 'BlackRock ESG Strategy',
                'content' => '<div class="prep-content"><h2>Asset Management Strategy</h2><p>$10T AUM ESG transformation...</p></div>'
            ],
            [
                'title' => 'KKR Acquisition of April Group',
                'content' => '<div class="prep-content"><h2>European Insurance Roll-up</h2><p>€3.5B insurance consolidation...</p></div>'
            ],
            [
                'title' => 'Goldman Sachs Marcus Digital Banking',
                'content' => '<div class="prep-content"><h2>Digital Transformation</h2><p>Consumer banking venture analysis...</p></div>'
            ]
        ];
        
        foreach ($case_studies as $study) {
            $post_id = wp_insert_post([
                'post_title' => $study['title'],
                'post_content' => $study['content'],
                'post_type' => 'prep_case_study',
                'post_status' => 'publish'
            ]);
            if ($post_id) $content_generated++;
        }
    }
    
    // Generate Interview Questions
    if (isset($_POST['generate_questions']) && class_exists('SFFC_Prep_Interview_Questions')) {
        $questions = SFFC_Prep_Interview_Questions::generate_questions();
        foreach ($questions as $q) {
            $post_id = wp_insert_post([
                'post_title' => $q['title'],
                'post_content' => $q['content'],
                'post_type' => 'prep_interview_q',
                'post_status' => 'publish'
            ]);
            if ($post_id) $content_generated++;
        }
    }
    
    // Generate Financial Terms
    if (isset($_POST['generate_terms']) && class_exists('SFFC_Prep_Terms_Glossary')) {
        $terms = SFFC_Prep_Terms_Glossary::generate_terms();
        foreach ($terms as $term) {
            $post_id = wp_insert_post([
                'post_title' => $term['title'],
                'post_content' => $term['content'],
                'post_type' => 'prep_term',
                'post_status' => 'publish'
            ]);
            if ($post_id) $content_generated++;
        }
    }
    
    // Generate Day in Life Guides
    if (isset($_POST['generate_guides']) && class_exists('SFFC_Prep_Day_In_Life_Generator')) {
        $guides = SFFC_Prep_Day_In_Life_Generator::generate_guides();
        foreach ($guides as $guide) {
            $post_id = wp_insert_post([
                'post_title' => $guide['title'],
                'post_content' => $guide['content'],
                'post_type' => 'prep_day_in_life',
                'post_status' => 'publish'
            ]);
            if ($post_id) $content_generated++;
        }
    }
    
    // Generate Modeling Guides (simplified)
    if (isset($_POST['generate_modeling'])) {
        $modeling_guides = [
            [
                'title' => 'Complete DCF Modeling Guide',
                'content' => '<div class="prep-content"><h2>DCF Model Construction</h2><p>Step-by-step DCF modeling tutorial...</p></div>'
            ],
            [
                'title' => 'LBO Modeling Masterclass',
                'content' => '<div class="prep-content"><h2>Private Equity LBO Model</h2><p>Complete LBO model walkthrough...</p></div>'
            ],
            [
                'title' => 'Merger Model Tutorial',
                'content' => '<div class="prep-content"><h2>M&A Accretion/Dilution Analysis</h2><p>Merger model construction guide...</p></div>'
            ],
            [
                'title' => 'Three Statement Model',
                'content' => '<div class="prep-content"><h2>Integrated Financial Statements</h2><p>Building integrated financial models...</p></div>'
            ],
            [
                'title' => 'Comparable Company Analysis',
                'content' => '<div class="prep-content"><h2>Trading & Transaction Comps</h2><p>Valuation using comparables...</p></div>'
            ]
        ];
        
        foreach ($modeling_guides as $guide) {
            $post_id = wp_insert_post([
                'post_title' => $guide['title'],
                'post_content' => $guide['content'],
                'post_type' => 'prep_model_guide',
                'post_status' => 'publish'
            ]);
            if ($post_id) $content_generated++;
        }
    }
    
    if ($content_generated > 0) {
        $message = "✅ Successfully generated $content_generated content items!";
    } else {
        $error = "No content was generated. Please select at least one content type.";
    }
}

?>

<div class="wrap">
    <h1>Generate Premium Prep Content</h1>
    
    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Simple Content Generator</h2>
        <p>Select the content types you want to generate and click the button. This will create real content in your WordPress database.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('generate_prep_content'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Content Types</th>
                    <td>
                        <label>
                            <input type="checkbox" name="generate_case_studies" value="1" checked>
                            <strong>Case Studies</strong> (5 items)
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="generate_questions" value="1" checked>
                            <strong>Interview Questions</strong> (30 items)
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="generate_terms" value="1" checked>
                            <strong>Financial Terms</strong> (40 items)
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="generate_modeling" value="1" checked>
                            <strong>Modeling Guides</strong> (5 items)
                        </label><br><br>
                        
                        <label>
                            <input type="checkbox" name="generate_guides" value="1" checked>
                            <strong>Day in Life Guides</strong> (18 items)
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="generate_content" class="button button-primary button-hero">
                    Generate Selected Content Now
                </button>
            </p>
        </form>
    </div>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Current Content Status</h2>
        <?php
        $post_types = [
            'prep_case_study' => 'Case Studies',
            'prep_interview_q' => 'Interview Questions',
            'prep_term' => 'Financial Terms',
            'prep_model_guide' => 'Modeling Guides',
            'prep_day_in_life' => 'Day in Life Guides'
        ];
        
        echo '<ul>';
        foreach ($post_types as $type => $label) {
            $count = wp_count_posts($type);
            $published = isset($count->publish) ? $count->publish : 0;
            echo "<li><strong>$label:</strong> $published published</li>";
        }
        echo '</ul>';
        ?>
    </div>
</div>