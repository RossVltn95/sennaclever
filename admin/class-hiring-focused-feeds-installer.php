<?php
/**
 * Hiring-Focused Feeds Installer
 * 
 * Installs feeds specifically focused on hiring, recruitment, and job market news
 * Filters for employment trends, hiring announcements, and talent market insights
 * 
 * @package SennaCareers
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Hiring_Focused_Feeds_Installer {
    
    /**
     * Install hiring-focused feeds
     */
    public static function install_feeds() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('success' => false, 'message' => 'Feed table does not exist');
        }
        
        // Ensure the hiring flag column exists before inserting rows
        self::ensure_hiring_flag_column($table_name);

        // HIRING & RECRUITMENT FOCUSED FEEDS - Filter keywords: jobs, hiring, recruitment, talent, employment, workforce
        $feeds = array(
            // === TIER 1: RECRUITMENT & TALENT NEWS (Priority 1-15) ===
            array('LinkedIn Talent Blog', 'https://www.linkedin.com/pulse/recent-articles-by-linkedin-talent-solutions/', 'hiring-trends', 1),
            array('Indeed Hiring Lab', 'https://www.hiringlab.org/feed/', 'hiring-trends', 2),
            array('Glassdoor for Employers', 'https://www.glassdoor.com/employers/blog/feed/', 'hiring-trends', 3),
            array('RecruitmentGrapevine', 'https://www.recruitmentgrapevine.com/rss.aspx', 'recruitment-news', 4),
            array('HR Magazine UK Jobs', 'https://www.hrmagazine.co.uk/rss/jobs', 'hr-hiring', 5),
            array('People Management Jobs', 'https://www.peoplemanagement.co.uk/jobs/rss', 'hr-hiring', 6),
            array('Recruitment & Employment Federation', 'https://www.rec.uk.com/news-and-policy/news/rss', 'recruitment-policy', 7),
            array('WorkLife by Atlassian', 'https://www.atlassian.com/blog/rss.xml', 'workplace-trends', 8),
            array('Harvard Business Review - Future of Work', 'https://feeds.hbr.org/harvardbusiness/futureofwork', 'workforce-trends', 9),
            array('MIT Sloan - Future of Work', 'https://mitsloan.mit.edu/expertise/future-work/rss.xml', 'workforce-research', 10),
            
            // === TIER 2: FINANCIAL SERVICES HIRING NEWS (Priority 11-25) ===
            array('Financial News - Careers', 'https://www.fnlondon.com/rss/careers', 'finance-careers', 11),
            array('eFinancialCareers News', 'https://www.efinancialcareers.co.uk/rss/news', 'finance-hiring', 12),
            array('Banking Technology Jobs', 'https://www.bankingtechnology.com/category/careers/feed/', 'fintech-hiring', 13),
            array('Wall Street Journal - Careers', 'https://feeds.wsj.com/wsj/careers', 'finance-careers', 14),
            array('Financial Planning Magazine - Careers', 'https://www.financial-planning.com/feed', 'finance-planning-jobs', 15),
            array('American Banker - Careers', 'https://www.americanbanker.com/feed?tag=188', 'banking-careers', 16),
            array('Investment News - Careers', 'https://www.investmentnews.com/rss/careers', 'investment-careers', 17),
            array('Pensions & Investments - People', 'https://www.pionline.com/rss/people', 'asset-mgmt-hiring', 18),
            
            // === TIER 3: PRIVATE EQUITY & INVESTMENT HIRING (Priority 19-30) ===
            array('PE Hub - People Moves', 'https://www.pehub.com/category/people-moves/feed/', 'pe-hiring', 19),
            array('Buyouts Magazine - People', 'https://www.buyoutsinsider.com/rss/people', 'pe-people-moves', 20),
            array('Private Equity Wire - People', 'https://www.privateequitywire.co.uk/category/people/feed/', 'pe-personnel', 21),
            array('Alt Assets - People Moves', 'https://www.altassets.net/category/people/feed/', 'alternatives-hiring', 22),
            array('Infrastructure Investor - People', 'https://www.infrastructureinvestor.com/category/people/feed/', 'infrastructure-hiring', 23),
            array('Private Debt Investor - People', 'https://www.privatedebtinvestor.com/category/people/feed/', 'debt-hiring', 24),
            array('Secondaries Investor - People', 'https://www.secondariesinvestor.com/category/people/feed/', 'secondaries-hiring', 25),
            
            // === TIER 4: HEDGE FUNDS & ASSET MANAGEMENT HIRING (Priority 26-35) ===
            array('HFM Week - People Moves', 'https://www.hfmweek.com/category/people/feed/', 'hf-hiring', 26),
            array('Hedge Fund Review - People', 'https://www.hedgefundreview.com/category/people/feed/', 'hf-personnel', 27),
            array('CityWire - Fund Manager Moves', 'https://citywire.co.uk/rss/fund-manager-moves', 'fund-mgr-moves', 28),
            array('Asset Management News - People', 'https://www.assetmanagementnews.com/category/people/feed/', 'am-hiring', 29),
            array('Institutional Investor - People Moves', 'https://www.institutionalinvestor.com/category/people/feed/', 'institutional-hiring', 30),
            
            // === TIER 5: INVESTMENT BANKING HIRING (Priority 31-40) ===
            array('Global Banking & Finance Review - Careers', 'https://www.globalbankingandfinance.com/category/careers/feed/', 'ib-careers', 31),
            array('Investment Banking News - People', 'https://www.investmentbanking.com/category/people/feed/', 'ib-hiring', 32),
            array('Euromoney - People Moves', 'https://www.euromoney.com/rss/people-moves', 'ib-people-moves', 33),
            array('The Banker - People', 'https://www.thebanker.com/rss/people', 'banking-personnel', 34),
            
            // === TIER 6: CONSULTING & ADVISORY HIRING (Priority 36-45) ===
            array('Consultancy.uk - Jobs', 'https://www.consultancy.uk/news/jobs/rss.xml', 'consulting-jobs', 36),
            array('Management Consulted - Careers', 'https://managementconsulted.com/category/consulting-careers/feed/', 'consulting-careers', 37),
            array('Strategy& - Careers', 'https://www.strategyand.pwc.com/gx/en/careers/rss.xml', 'strategy-careers', 38),
            
            // === TIER 7: FINTECH & DIGITAL HIRING (Priority 41-50) ===
            array('Fintech News - Jobs', 'https://fintechnews.org/category/jobs/feed/', 'fintech-jobs', 41),
            array('PaymentsSource - Careers', 'https://www.paymentssource.com/feed', 'payments-careers', 42),
            array('Fintech Finance - Careers', 'https://www.fintechf.com/category/careers/feed/', 'fintech-careers', 43),
            array('Banking Technology - Careers', 'https://www.bankingtechnology.com/category/careers/feed/', 'banktech-careers', 44),
            
            // === TIER 8: REGULATORY & COMPLIANCE HIRING (Priority 46-55) ===
            array('Compliance Week - People', 'https://www.coplianceweek.com/rss/people', 'compliance-hiring', 46),
            array('Risk Magazine - People', 'https://www.risk.net/rss/people', 'risk-personnel', 47),
            array('RegTech Analyst - Jobs', 'https://www.regtechanalyst.com/category/jobs/feed/', 'regtech-jobs', 48),
            
            // === TIER 9: ACCOUNTING & AUDIT HIRING (Priority 51-60) ===
            array('AccountingWEB - Jobs', 'https://www.accountingweb.com/rss/jobs', 'accounting-jobs', 51),
            array('ACCA Global - Careers', 'https://www.accaglobal.com/gb/en/careers/rss.html', 'acca-careers', 52),
            array('ICAEW - Careers', 'https://www.icaew.com/careers/rss', 'icaew-careers', 53),
            array('Accounting Today - Careers', 'https://www.accountingtoday.com/feed', 'accounting-careers', 54),
            
            // === TIER 10: INSURANCE & ACTUARIAL HIRING (Priority 56-65) ===
            array('Insurance Business - Careers', 'https://www.insurancebusinessmag.com/uk/rss/careers/', 'insurance-careers', 56),
            array('Actuarial Post - Jobs', 'https://www.theactuarialpost.co.uk/rss/jobs/', 'actuarial-jobs', 57),
            array('Insurance Times - People', 'https://www.insurancetimes.co.uk/rss/people/', 'insurance-people', 58),
            
            // === TIER 11: REAL ESTATE & PROPERTY FINANCE HIRING (Priority 61-70) ===
            array('Property Week - Careers', 'https://www.propertyweek.com/rss/careers', 'property-careers', 61),
            array('Real Estate Finance - People', 'https://www.realestatefinance.com/category/people/feed/', 'ref-hiring', 62),
            array('Property Investor Today - Jobs', 'https://www.propertyinvestortoday.co.uk/breaking-news/rss', 'property-investment-jobs', 63),
            
            // === TIER 12: QUANTITATIVE & DATA SCIENCE HIRING (Priority 66-75) ===
            array('QuantNet - Jobs', 'https://quantnet.com/rss/jobs.rss', 'quant-jobs', 66),
            array('Wilmott - Jobs', 'https://wilmott.com/feed/', 'quant-careers', 67),
            array('Data Science Central - Jobs', 'https://www.datasciencecentral.com/rss/jobs', 'data-science-jobs', 68),
            
            // === TIER 13: ESG & SUSTAINABLE FINANCE HIRING (Priority 71-80) ===
            array('ESG Clarity - People', 'https://esgclarity.com/category/people/feed/', 'esg-hiring', 71),
            array('Responsible Investor - People', 'https://www.responsible-investor.com/rss/people', 'sustainable-finance-hiring', 72),
            array('Impact Alpha - Jobs', 'https://impactalpha.com/category/jobs/feed/', 'impact-jobs', 73),
            
            // === TIER 14: WEALTH MANAGEMENT HIRING (Priority 76-85) ===
            array('Wealth Manager - People', 'https://wealthmanager.com/category/people/feed/', 'wealth-mgmt-hiring', 76),
            array('Private Wealth - Careers', 'https://www.privatewealth.com/category/careers/feed/', 'private-wealth-careers', 77),
            array('Family Wealth Report - People', 'https://www.familywealthreport.com/rss/people', 'family-office-hiring', 78),
            
            // === TIER 15: CORPORATE FINANCE & M&A HIRING (Priority 81-90) ===
            array('Mergers & Acquisitions - People', 'https://www.themiddlemarket.com/rss/people', 'ma-hiring', 81),
            array('Corporate Finance Review - People', 'https://www.corporatefinancereview.com/category/people/feed/', 'corp-finance-hiring', 82),
            array('Deal Magazine - People Moves', 'https://www.thedeal.com/rss/people-moves/', 'deal-personnel', 83),
            
            // === TIER 16: ADDITIONAL CAREER & MARKET NEWS (Priority 84-87) ===
            array('City AM - London Business', 'https://www.cityam.com/feed/', 'london-business', 84),
            array('City AM - RSS Alternative', 'https://www.cityam.com/rss', 'london-business-alt', 85),
            array('Macdonald & Company - Real Estate Recruitment', 'https://www.macdonaldandcompany.com/blog/feed/', 'real-estate-careers', 86),
            
            // === TIER 17: DEAL-FOCUSED NEWS SOURCES (Priority 87-92) ===
            array('PE Hub - Deals', 'https://www.pehub.com/category/deals/feed/', 'pe-deals', 87),
            array('Buyouts Magazine - Deals', 'https://www.buyoutsinsider.com/rss/deals', 'buyout-deals', 88),
            array('Alt Assets - Deals', 'https://www.altassets.net/category/deals/feed/', 'alternative-deals', 89),
            array('Mergers & Acquisitions - Deals', 'https://www.themiddlemarket.com/rss/deals', 'ma-deals', 90),
            array('Private Equity Wire - Deals', 'https://www.privateequitywire.co.uk/category/deals/feed/', 'pe-transactions', 91),
            array('Infrastructure Investor - Deals', 'https://www.infrastructureinvestor.com/category/deals/feed/', 'infrastructure-deals', 92)
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
            
            // Insert new feed with hiring focus flag
            $result = $wpdb->insert(
                $table_name,
                array(
                    'feed_name' => $name,
                    'feed_url' => $url,
                    'feed_category' => $category,
                    'priority' => $priority,
                    'is_active' => 1,
                    'hiring_focused' => 1, // Flag for hiring-specific feeds
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
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
            'focus' => 'hiring-and-recruitment'
        );
    }
    
    /**
     * Get hiring feed keywords for filtering
     */
    public static function get_hiring_keywords() {
        return array(
            // Primary hiring keywords
            'hiring', 'recruitment', 'recruiter', 'recruiting', 'recruit',
            'jobs', 'job market', 'employment', 'unemployed', 'unemployment',
            'talent', 'workforce', 'headcount', 'staffing', 'personnel',
            'career', 'careers', 'position', 'positions', 'role', 'roles',
            
            // People movement keywords
            'people moves', 'appointments', 'promotion', 'promoted', 'joins',
            'joined', 'hire', 'hired', 'hires', 'new hire', 'new appointment',
            'executive search', 'head of', 'chief', 'director', 'manager',
            
            // Market trend keywords
            'salary', 'salaries', 'compensation', 'pay', 'wage', 'wages',
            'hiring trends', 'job market trends', 'talent shortage', 'skills gap',
            'remote work', 'hybrid work', 'work from home', 'flexible working',
            
            // Industry specific
            'analyst', 'associate', 'vice president', 'managing director',
            'portfolio manager', 'investment manager', 'relationship manager',
            'compliance officer', 'risk manager', 'trader', 'quant',
            
            // Company expansion keywords  
            'expansion', 'growth', 'scaling', 'team building', 'headcount growth',
            'office opening', 'new office', 'regional expansion'
        );
    }
    
    /**
     * Filter feed content for hiring relevance
     */
    public static function is_hiring_relevant($title, $description = '') {
        $keywords = self::get_hiring_keywords();
        $content = strtolower($title . ' ' . $description);
        
        $relevance_score = 0;
        
        foreach ($keywords as $keyword) {
            if (strpos($content, strtolower($keyword)) !== false) {
                $relevance_score++;
            }
        }
        
        // Return true if at least 1 hiring keyword found
        return $relevance_score > 0;
    }
    
    /**
     * Update existing feeds to mark hiring relevance
     */
    public static function mark_hiring_relevance() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        // Add hiring_focused column if it doesn't exist
        self::ensure_hiring_flag_column($table_name);
        
        // Get all feeds and mark hiring relevance
        $feeds = $wpdb->get_results("SELECT id, feed_name FROM {$table_name}");
        $marked = 0;
        
        foreach ($feeds as $feed) {
            if (self::is_hiring_relevant($feed->feed_name)) {
                $wpdb->update(
                    $table_name,
                    array('hiring_focused' => 1),
                    array('id' => $feed->id),
                    array('%d'),
                    array('%d')
                );
                $marked++;
            }
        }
        
        return array(
            'success' => true,
            'marked_feeds' => $marked,
            'total_feeds' => count($feeds)
        );
    }
    
    /**
     * Get hiring feed statistics
     */
    public static function get_hiring_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sffc_xml_feeds';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('error' => 'Table does not exist');
        }
        
        $stats = array(
            'total_hiring_feeds' => 0,
            'by_category' => array(),
            'top_categories' => array()
        );
        
        // Get total hiring feeds
        $stats['total_hiring_feeds'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE hiring_focused = 1"
        );
        
        // Get feeds by category
        $categories = $wpdb->get_results(
            "SELECT feed_category, COUNT(*) as count 
             FROM {$table_name} 
             WHERE hiring_focused = 1
             GROUP BY feed_category 
             ORDER BY count DESC"
        );
        
        foreach ($categories as $cat) {
            $stats['by_category'][$cat->feed_category] = $cat->count;
        }
        
        // Get top 10 categories
        $stats['top_categories'] = array_slice($stats['by_category'], 0, 10, true);
        
        return $stats;
    }

    /**
     * Ensure the hiring_focused column exists
     */
    private static function ensure_hiring_flag_column($table_name) {
        global $wpdb;

        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'hiring_focused'",
            DB_NAME,
            $table_name
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN hiring_focused TINYINT(1) DEFAULT 0");
        }
    }
}
