<?php
/**
 * Middle East Finance Feeds Installer
 *
 * Adds tested RSS/XML feeds covering Middle East banking, finance, Gulf
 * business, fintech and capital markets.
 *
 * @package SennaCareers
 * @subpackage NewsFeeds
 * @since 2.1.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Middle_East_Feeds_Installer {

    /**
     * Install verified Middle East finance feeds.
     *
     * @return array
     */
    public static function install_feeds() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sffc_xml_feeds';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('success' => false, 'message' => 'Feed table does not exist');
        }

        // Verified manually on 2026-08-23. Excludes tested HTML/blocked feeds.
        $feeds = array(
            array('MEED - Banking & Finance', 'https://www.meed.com/sector/banking-finance/rss', 'middle-east-banking', 1),
            array('MEED - Technology & IT', 'https://www.meed.com/sector/technology/rss', 'middle-east-technology', 2),
            array('Arab Finance', 'https://arabfinance.com/en/rss/rssbycat/2', 'middle-east-finance', 3),
            array('Arabian Gulf Business Insight', 'https://www.agbi.com/feed/', 'middle-east-business', 4),
            array('MENAbytes - Startups & Fintech', 'https://www.menabytes.com/feed/', 'middle-east-startups', 5),
            array('MEED - Analysis', 'https://www.meed.com/classifications/analysis/feed', 'middle-east-analysis', 6),
            array('MEED - Industrial', 'https://www.meed.com/sector/industrial/rss', 'middle-east-industrial', 7),
            array('MEED - Transport', 'https://www.meed.com/sector/transport/rss', 'middle-east-transport', 8),
            array('MEED - Tourism', 'https://www.meed.com/sector/economy/tourism/rss', 'middle-east-tourism', 9),
            array('Wamda', 'https://www.wamda.com/feed', 'middle-east-startups', 10),
            array('WAYA', 'https://waya.media/feed/', 'middle-east-startups', 11),
            array('Daily News Egypt - Business', 'https://www.dailynewsegypt.com/category/business/feed/', 'middle-east-business', 12),
            array('Saudi Gazette - Business', 'https://www.saudigazette.com.sa/rss/business', 'middle-east-business', 13),
        );

        $added = 0;
        $skipped = 0;
        $categories = array();

        foreach ($feeds as $feed_data) {
            list($name, $url, $category, $priority) = $feed_data;

            if (!in_array($category, $categories, true)) {
                $categories[] = $category;
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE feed_url = %s",
                $url
            ));

            if ($existing) {
                $skipped++;
                continue;
            }

            $result = $wpdb->insert(
                $table_name,
                array(
                    'feed_name' => $name,
                    'feed_url' => $url,
                    'feed_category' => $category,
                    'priority' => $priority,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'error_count' => 0,
                ),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
            );

            if ($result) {
                $added++;
            }
        }

        return array(
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'total' => count($feeds),
            'categories' => count($categories),
            'category_list' => $categories,
            'focus' => 'Verified Middle East banking, finance, Gulf business and fintech RSS feeds',
        );
    }
}
