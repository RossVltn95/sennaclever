<?php
/**
 * Single template for recruiter profiles.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;

$manager = class_exists('SFFC_Recruiter_Manager') ? SFFC_Recruiter_Manager::get_instance() : null;
$view = $manager ? $manager->get_profile_view_model($post->ID) : null;

get_header();

if ($view && $manager) {
    echo $manager->render_profile($view, [
        'show_schema' => true,
    ]);
} else {
    echo '<p>' . esc_html__('Recruiter profile unavailable.', 'senna-finance') . '</p>';
}

get_footer();
