<?php
/**
 * Tracks visits on PE news and deal posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_PE_Visit_Tracker
{
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'track_visit'));
    }

    public static function track_visit()
    {
        global $wp_query;

        if (empty($wp_query) || !function_exists('is_singular') || !is_singular(array('sffc_pe_news', 'sffc_pe_deal'))) {
            return;
        }

        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $count = (int) get_post_meta($post->ID, 'sffc_visit_count', true);
        $count++;
        update_post_meta($post->ID, 'sffc_visit_count', $count);
    }
}

SFFC_PE_Visit_Tracker::init();
