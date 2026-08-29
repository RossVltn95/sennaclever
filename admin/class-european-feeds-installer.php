<?php
/**
 * European Feeds Installer - Simple and Direct
 * 
 * @package SennaCareers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_European_Feeds_Installer {
    
    /**
     * Install European feeds
     */
    public static function install_feeds() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('success' => false, 'message' => 'Feed table does not exist');
        }
        
        // European feeds array - VERIFIED WORKING FEEDS ONLY
        $feeds = array(
            // Priority European Market Data (1-10)
            array('Yahoo Finance - FTSE 100', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^FTSE&region=US&lang=en-US', 'markets', 1),
            array('Yahoo Finance - DAX', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^GDAXI&region=US&lang=en-US', 'markets', 2),
            array('Yahoo Finance - CAC 40', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^FCHI&region=US&lang=en-US', 'markets', 3),
            array('Yahoo Finance - STOXX 600', 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=^STOXX&region=US&lang=en-US', 'markets', 4),
            array('Financial Times - Markets', 'https://www.ft.com/markets?format=rss', 'markets', 5),
            array('Financial Times - Companies', 'https://www.ft.com/companies?format=rss', 'markets', 6),
            array('Financial Times - World', 'https://www.ft.com/world?format=rss', 'markets', 7),
            array('Bloomberg - Markets', 'https://feeds.bloomberg.com/markets/news.rss', 'markets', 8),
            array('MarketWatch - Europe', 'https://feeds.marketwatch.com/marketwatch/marketpulse/', 'markets', 9),
            array('Investing.co - Europe', 'https://www.investing.com/rss/news_14.rss', 'markets', 10),

            // European & UK Business News (11-20)
            array('BBC Business', 'http://feeds.bbci.co.uk/news/business/rss.xml', 'business', 11),
            array('Guardian Business', 'https://www.theguardian.com/uk/business/rss', 'business', 12),
            array('City AM - London', 'https://www.cityam.com/feed/', 'business', 13),
            array('CNBC Europe', 'https://www.cnbc.com/id/19794221/device/rss/rss.html', 'business', 14),
            array('CNBC World Markets', 'https://www.cnbc.com/id/20910258/device/rss/rss.html', 'markets', 15),
            array('Euronews Business', 'https://www.euronews.com/rss?format=mrss&level=vertical&name=business', 'business', 16),
            array('EU Business', 'https://www.eubusiness.com/rss', 'business', 17),
            array('Politico EU', 'https://www.politico.eu/feed/', 'business', 18),

            // Central Banks & Economics (21-30)
            array('European Central Bank', 'https://www.ecb.europa.eu/rss/press.html', 'central-banks', 21),
            array('Bank of England News', 'https://www.bankofengland.co.uk/rss/news', 'central-banks', 22),
            array('BoE Publications', 'https://www.bankofengland.co.uk/rss/publications', 'central-banks', 23),
            array('BoE Speeches', 'https://www.bankofengland.co.uk/rss/speeches', 'central-banks', 24),
            array('HM Treasury UK', 'https://www.gov.uk/government/organisations/hm-treasury.atom', 'central-banks', 25),
            array('FCA UK Financial News', 'https://www.fca.org.uk/news/rss.xml', 'central-banks', 26),
            array('BIS - Bank for International Settlements', 'https://www.bis.org/doclist/bis_fsi_publs.rss', 'central-banks', 27),
            array('FXStreet News', 'https://www.fxstreet.com/rss/news', 'markets', 28),
            array('This is Money UK', 'https://www.thisismoney.co.uk/home/index.rss', 'business', 29),

            // FinTech & Innovation (31-35)
            array('FinExtra', 'https://www.finextra.com/rss/headlines.aspx', 'fintech', 31),
            array('The Fintech Times', 'https://thefintechtimes.com/feed/', 'fintech', 32),

            // Cryptocurrency (36-40)
            array('CoinDesk', 'https://www.coindesk.com/arc/outboundfeeds/rss/', 'crypto', 36),
            array('CoinTelegraph', 'https://cointelegraph.com/rss', 'crypto', 37),
            array('The Block Crypto', 'https://www.theblock.com/rss.xml', 'crypto', 38),

            // Energy & Commodities (41-45)
            array('Oil Price', 'https://oilprice.com/rss/main', 'commodities', 41),
            array('Energy Voice', 'https://www.energyvoice.com/feed/', 'commodities', 42),
            array('Offshore Energy', 'https://www.offshore-energy.biz/feed/', 'commodities', 43),
        );
        
        $added = 0;
        $skipped = 0;
        
        foreach ($feeds as $feed) {
            list($name, $url, $category, $priority) = $feed;
            
            // Check if feed already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE feed_url = %s",
                $url
            ));
            
            if ($existing) {
                $skipped++;
                continue;
            }
            
            // Insert new feed
            $result = $wpdb->insert(
                $table_name,
                array(
                    'feed_name' => $name,
                    'feed_url' => $url,
                    'feed_category' => $category,
                    'priority' => $priority,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%s')
            );
            
            if ($result) {
                $added++;
            }
        }
        
        return array(
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'total' => count($feeds)
        );
    }
}
?>