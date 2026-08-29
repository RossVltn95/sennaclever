<?php
/**
 * Template: Single CRM Post View
 *
 * Displays a single CRM opportunity post.
 * This template is loaded when visiting /crm-opportunity/{id}/
 *
 * @package SennaCareers
 * @since 7.1.0
 *
 * Available variables:
 * @var object $crm_post - The CRM post data object
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the CRM post from global
$crm_post = $GLOBALS['sffc_crm_current_post'] ?? null;

if (!$crm_post) {
    wp_die(__('Post not found', 'senna-finance'));
}

get_header();
?>

<div class="sffc-crm-post-single">
    <div class="sffc-crm-post-single-container">
        <?php
        // Render the CRM post article
        echo do_shortcode('[sffc_crm_post_article post_id="' . esc_attr($crm_post->id) . '" show_sidebar="true"]');
        ?>
    </div>
</div>

<style>
.sffc-crm-post-single {
    min-height: 100vh;
    background: #f8fafc;
    padding: 0;
}

.sffc-crm-post-single-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

@media (max-width: 768px) {
    .sffc-crm-post-single-container {
        padding: 12px;
    }
}

/* Hide any duplicate headers/footers from theme */
.sffc-crm-post-single .entry-header,
.sffc-crm-post-single .entry-title,
.sffc-crm-post-single .entry-meta {
    display: none;
}
</style>

<?php
get_footer();
