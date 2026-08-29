<?php
/**
 * Company Title Helper
 *
 * Normalises canonical company names and builds SEO-friendly titles
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Company_Title_Helper
{
    const META_CANONICAL_NAME = '_sffc_company_canonical_name';
    /**
     * Suffix appended to generated SEO titles.
     *
     * Updated to better reflect the recruiter-focused experience rather than
     * the legacy “Careers, Deals and Insights” label.
     */
    const SEO_SUFFIX = ' Recruiter Profile';

    /**
     * Build the SEO-formatted title for a company.
     */
    public static function build_seo_title($company_name)
    {
        $canonical = self::normalise_name($company_name);

        if ($canonical === '') {
            return 'Recruiter Profile';
        }

        if (self::SEO_SUFFIX === '') {
            return $canonical;
        }

        return $canonical . self::SEO_SUFFIX;
    }

    /**
     * Ensure canonical name meta is stored for the post.
     */
    public static function ensure_canonical_meta($post_id, $canonical_name = null)
    {
        $post_id = intval($post_id);

        if ($post_id <= 0) {
            return;
        }

        $canonical = $canonical_name !== null
            ? self::normalise_name($canonical_name)
            : self::get_canonical_name($post_id);

        if ($canonical === '') {
            return;
        }

        update_post_meta($post_id, self::META_CANONICAL_NAME, $canonical);
    }

    /**
     * Retrieve the canonical (unsuffixed) company name.
     */
    public static function get_canonical_name($post)
    {
        $post = self::resolve_post($post);

        if (!$post) {
            return '';
        }

        $stored = get_post_meta($post->ID, self::META_CANONICAL_NAME, true);

        if (is_string($stored) && trim($stored) !== '') {
            return self::normalise_name($stored);
        }

        return self::strip_seo_suffix($post->post_title);
    }

    /**
     * Ensure the stored post title matches the SEO format.
     */
    public static function ensure_seo_title($post_id, $canonical_name = null)
    {
        $post = self::resolve_post($post_id);
        if (!$post) {
            return;
        }

        $canonical = $canonical_name !== null
            ? self::normalise_name($canonical_name)
            : self::get_canonical_name($post);

        if ($canonical === '') {
            return;
        }

        $expected_title = self::build_seo_title($canonical);

        if ($post->post_title === $expected_title) {
            return;
        }

        // Preserve the existing slug so permalinks stay canonical.
        $current_slug = $post->post_name ?: sanitize_title($canonical);

        wp_update_post(array(
            'ID' => $post->ID,
            'post_title' => $expected_title,
            'post_name' => $current_slug,
        ));

        self::ensure_canonical_meta($post->ID, $canonical);
    }

    /**
     * Strip the SEO suffix (if present) from a title.
     */
    public static function strip_seo_suffix($title)
    {
        $title = self::normalise_name($title);

        if ($title === '') {
            return '';
        }

        if (self::SEO_SUFFIX !== '' && self::ends_with_suffix($title)) {
            $suffix_length = strlen(self::SEO_SUFFIX);
            return trim(substr($title, 0, -$suffix_length));
        }

        return $title;
    }

    /**
     * Determine if the value already carries the suffix (case-insensitive).
     */
    private static function ends_with_suffix($value)
    {
        $value = trim($value);
        $suffix = self::SEO_SUFFIX;

        if ($suffix === '' || $value === '' || strlen($value) < strlen($suffix)) {
            return false;
        }

        return strcasecmp(substr($value, -strlen($suffix)), $suffix) === 0;
    }

    /**
     * Normalise the provided name string.
     */
    private static function normalise_name($value)
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(wp_strip_all_tags($value));
    }

    /**
     * Resolve a post object from ID or object.
     */
    private static function resolve_post($post)
    {
        if ($post instanceof WP_Post) {
            return $post->post_type === 'sffc_company' ? $post : null;
        }

        $post_id = intval($post);
        if ($post_id <= 0) {
            return null;
        }

        $resolved = get_post($post_id);

        if ($resolved && $resolved->post_type === 'sffc_company') {
            return $resolved;
        }

        return null;
    }
}
