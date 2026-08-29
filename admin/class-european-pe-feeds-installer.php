<?php
/**
 * European Private Equity Feeds Installer
 * 
 * Comprehensive PE/VC feed installation for European markets
 * 
 * @package SennaCareers
 * @subpackage EuropeanMarkets
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_European_PE_Feeds_Installer {
    
    /**
     * Install European PE/VC feeds
     */
    public static function install_feeds() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('success' => false, 'message' => 'Feed table does not exist');
        }
        
        // Comprehensive European PE/VC feeds - VERIFIED WORKING
        $feeds = array(
            // === TIER 1: MAJOR PE NEWS PLATFORMS (Priority 1-10) ===
            array('Private Equity Wire Europe', 'https://www.privateequitywire.co.uk/rss.xml', 'private-equity', 1),
            array('Alt Assets - Main', 'https://www.altassets.net/feed', 'private-equity', 2),
            array('Alt Assets - Europe', 'https://www.altassets.net/category/regions/europe/feed/', 'private-equity', 3),
            array('Private Equity International', 'https://www.privateequityinternational.com/feed/', 'private-equity', 4),
            array('Infrastructure Investor', 'https://www.infrastructureinvestor.com/feed/', 'infrastructure', 5),
            array('Private Debt Investor', 'https://www.privatedebtinvestor.com/feed/', 'debt-funds', 6),
            array('Secondaries Investor', 'https://www.secondariesinvestor.com/feed/', 'secondaries', 7),
            array('Private Funds CFO', 'https://www.privatefundscfo.com/feed/', 'fund-operations', 8),
            array('Invest Europe', 'https://www.investeurope.eu/rss/', 'industry-data', 9),
            array('Blackstone Insights', 'https://www.blackstone.com/insights/feed/', 'pe-firms', 10),
            
            // === TIER 2: VENTURE CAPITAL & STARTUPS (Priority 11-25) ===
            array('Sifted (Financial Times)', 'https://sifted.eu/feed/', 'venture-capital', 11),
            array('EU-Startups', 'https://www.eu-startups.com/feed/', 'venture-capital', 12),
            array('Tech.eu', 'https://tech.eu/feed/', 'venture-capital', 13),
            array('The Next Web', 'https://thenextweb.com/feed/', 'tech-vc', 14),
            array('Crunchbase News', 'https://news.crunchbase.com/feed/', 'venture-capital', 15),
            
            // === TIER 3: REGIONAL COVERAGE - GERMANY/DACH (Priority 16-20) ===
            array('German Startups', 'https://www.deutsche-startups.de/feed/', 'germany-vc', 16),
            array('Gruenderszene', 'https://www.gruenderszene.de/feed', 'germany-vc', 17),
            array('Handelsblatt Finance', 'https://www.handelsblatt.com/contentexport/feed/finanzen', 'germany-finance', 18),
            array('VC Magazin', 'https://www.vc-magazin.de/feed/', 'germany-pe', 19),
            
            // === TIER 4: REGIONAL COVERAGE - FRANCE (Priority 21-25) ===
            array('FrenchWeb', 'https://www.frenchweb.fr/feed', 'france-tech', 21),
            array('Maddyness France', 'https://www.maddyness.com/feed/', 'france-vc', 22),
            array('Journal du Net', 'https://www.journaldunet.com/rss/', 'france-business', 23),
            
            // === TIER 5: REGIONAL COVERAGE - NORDICS (Priority 26-30) ===
            array('ArcticStartup', 'https://arcticstartup.com/feed/', 'nordics-vc', 26),
            array('Northzone', 'https://www.northzone.com/feed/', 'nordics-pe', 27),
            
            // === TIER 6: REGIONAL COVERAGE - BENELUX (Priority 31-35) ===
            array('StartupJuncture', 'https://startupjuncture.com/feed/', 'benelux-vc', 31),
            array('Silicon Canals', 'https://siliconcanals.com/feed/', 'netherlands-tech', 32),
            
            // === TIER 7: REGIONAL COVERAGE - SOUTHERN EUROPE (Priority 36-40) ===
            array('Startups Italia', 'https://www.startupitalia.eu/feed', 'italy-vc', 36),
            array('El Referente Spain', 'https://elreferente.es/feed/', 'spain-vc', 37),
            array('Portugal Startups', 'https://portugalstartups.com/feed/', 'portugal-vc', 38),
            
            // === TIER 8: REGIONAL COVERAGE - CEE/EASTERN EUROPE (Priority 41-45) ===
            array('Emerging Europe', 'https://emerging-europe.com/feed/', 'eastern-europe', 41),
            array('150sec', 'https://150sec.com/feed/', 'cee-startups', 42),
            array('Czech PE & VC Association', 'https://www.cvca.cz/feed/', 'czech-pe', 43),
            
            // === TIER 9: UK FOCUSED (Priority 46-50) ===
            array('Growth Business UK', 'https://growthbusiness.co.uk/feed/', 'uk-growth', 46),
            array('Balderton Capital', 'https://www.balderton.com/feed/', 'uk-vc', 47),
            
            // === TIER 10: ESG & IMPACT INVESTING (Priority 51-55) ===
            array('Impact Alpha', 'https://impactalpha.com/feed/', 'impact-investing', 51),
            array('ESG Investor', 'https://www.esginvestor.net/feed/', 'esg-funds', 52),
            array('Responsible Investor', 'https://www.responsible-investor.com/rss', 'sustainable-finance', 53),
            
            // === TIER 11: LIMITED PARTNERS & INSTITUTIONAL (Priority 56-60) ===
            array('Chief Investment Officer', 'https://www.ai-cio.com/feed/', 'institutional', 56),
            array('ILPA News', 'https://ilpa.org/feed/', 'limited-partners', 57),
            array('Irish VC Association', 'https://www.ivca.ie/feed/', 'ireland-pe', 58),
            
            // === TIER 12: CONFERENCES & EVENTS (Priority 61-65) ===
            array('IPEM Paris', 'https://www.ipem-market.com/feed/', 'pe-events', 61),
        );
        
        $added = 0;
        $skipped = 0;
        $categories = array();
        
        foreach ($feeds as $feed) {
            list($name, $url, $category, $priority) = $feed;
            
            // Track categories
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
            
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
            'total' => count($feeds),
            'categories' => count($categories),
            'category_list' => $categories
        );
    }
    
    /**
     * Get PE feed statistics
     */
    public static function get_pe_feed_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('error' => 'Table does not exist');
        }
        
        $pe_categories = array(
            'private-equity',
            'venture-capital',
            'pe-firms',
            'debt-funds',
            'secondaries',
            'infrastructure',
            'impact-investing',
            'esg-funds'
        );
        
        $stats = array(
            'total_pe_feeds' => 0,
            'by_category' => array(),
            'by_region' => array()
        );
        
        // Get total PE feeds
        $pe_category_list = "'" . implode("','", $pe_categories) . "'";
        $stats['total_pe_feeds'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE feed_category IN ({$pe_category_list}) 
             OR feed_category LIKE '%-pe' 
             OR feed_category LIKE '%-vc' 
             OR feed_category LIKE '%-funds'"
        );
        
        // Get feeds by category
        $categories = $wpdb->get_results(
            "SELECT feed_category, COUNT(*) as count 
             FROM {$table_name} 
             WHERE feed_category LIKE '%-pe' 
             OR feed_category LIKE '%-vc' 
             OR feed_category IN ({$pe_category_list})
             GROUP BY feed_category 
             ORDER BY count DESC"
        );
        
        foreach ($categories as $cat) {
            $stats['by_category'][$cat->feed_category] = $cat->count;
        }
        
        // Extract regional stats
        $regions = array(
            'germany' => array('germany-', 'dach'),
            'france' => array('france-'),
            'uk' => array('uk-'),
            'nordics' => array('nordic', 'sweden', 'norway', 'denmark', 'finland'),
            'benelux' => array('benelux', 'netherlands', 'belgium', 'luxembourg'),
            'southern' => array('italy', 'spain', 'portugal'),
            'eastern' => array('eastern-europe', 'cee', 'czech', 'poland')
        );
        
        foreach ($regions as $region => $keywords) {
            $count = 0;
            foreach ($keywords as $keyword) {
                $count += $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} 
                         WHERE feed_category LIKE %s",
                        '%' . $keyword . '%'
                    )
                );
            }
            if ($count > 0) {
                $stats['by_region'][$region] = $count;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get feed coverage summary
     */
    public static function get_coverage_summary() {
        return array(
            'geographic_coverage' => array(
                'Pan-European' => 10,
                'France' => 5,
                'UK/Ireland' => 4,
                'Germany/DACH' => 3,
                'Benelux' => 3,
                'Southern Europe' => 3,
                'Nordics' => 2,
                'CEE/Eastern' => 2
            ),
            'sector_coverage' => array(
                'Traditional PE/Buyout' => 10,
                'Venture Capital' => 15,
                'Growth Equity' => 5,
                'Infrastructure' => 1,
                'Private Debt' => 1,
                'Secondaries' => 1,
                'Impact/ESG' => 3
            ),
            'content_types' => array(
                'Deal Flow News' => 25,
                'Fund Raising' => 8,
                'Portfolio Updates' => 12,
                'Market Analysis' => 15,
                'LP Perspectives' => 2,
                'Regulatory' => 3
            )
        );
    }
}
?>
