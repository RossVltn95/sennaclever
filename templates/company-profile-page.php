<?php
if (!defined('ABSPATH')) {
    exit;
}

global $post;

get_header();

$aggregator = SFFC_Company_Profile_Aggregator::get_instance();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        $output = $aggregator->render_profile(get_the_ID());
        if ($output !== '') {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            the_content();
        }
    }
}

get_footer();
