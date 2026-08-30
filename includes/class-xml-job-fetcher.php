<?php

/**
 * XML Job Feed Fetcher
 * Fetches and processes jobs from XML sitemaps and RSS feeds
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_XML_Job_Fetcher
{

    /**
     * Premium XML feed sources
     */
    private $xml_sources = [
        'pearse_partners' => [
            'url' => 'https://pearsepartners.com/job-sitemap.xml',
            'type' => 'sitemap',
            'category' => 'Investment Banking',
            'quality' => 'premium',
            'has_salary' => false,
            'source_type' => 'recruiter'
        ],
        'wuzzuf' => [
            'url' => 'https://wuzzuf.net/sitemap-job-1.xml',
            'type' => 'sitemap',
            'category' => 'private equity',
            'quality' => 'standard',
            'has_salary' => false
        ],
        'adcb_careers' => [
            'url' => 'https://adcbcareers.com/sitemap.xml',
            'type' => 'sitemap',
            'name' => 'Abu Dhabi Commercial Bank (ADCB)',
            'category' => 'UAE Banking',
            'quality' => 'premium',
            'has_salary' => false,
            'source_type' => 'company'
        ],
        'gaap_web' => [
            'url' => 'https://www.gaapweb.com/sitemap1-515.xml',
            'type' => 'sitemap',
            'category' => 'UK Finance',
            'quality' => 'standard',
            'has_salary' => false
        ],
        'consultancy_uk' => [
            'url' => 'https://www.consultancy.uk/sitemap_jobs.xml',
            'type' => 'sitemap',
            'category' => 'Consulting',
            'quality' => 'standard',
            'has_salary' => false
        ],
        'acca_global' => [
            'url' => 'https://jobs.accaglobal.com/sitemap2-1.xml',
            'type' => 'sitemap',
            'category' => 'Accounting',
            'quality' => 'standard',
            'has_salary' => false
        ],
        'barton_partnership' => [
            'url' => 'https://www.thebartonpartnership.com/job/sitemap.xml',
            'type' => 'sitemap',
            'category' => 'Capital Markets',
            'quality' => 'premium',
            'has_salary' => false
        ],
        'dartmouth_partners' => [
            'url' => 'https://www.dartmouthpartners.com/job_listing-sitemap.xml',
            'type' => 'sitemap',
            'category' => 'Investment Banking',
            'quality' => 'premium',
            'has_salary' => false
        ],
        'venture_capital_careers' => [
            'url' => 'https://venturecapitalcareers.com/sitemap-jobs.xml',
            'type' => 'sitemap',
            'category' => 'Venture Capital',
            'quality' => 'premium',
            'has_salary' => false
        ],
        'blackrock_all' => [
            'url' => 'https://careers.blackrock.com/rss/all-jobs',
            'type' => 'rss',
            'name' => 'BlackRock (All Jobs)',
            'category' => 'Asset Management',
            'quality' => 'premium',
            'has_salary' => true,
            'source_type' => 'company'
        ],
        'blackrock_london' => [
            'url' => 'https://careers.blackrock.com/rss/london',
            'type' => 'rss',
            'name' => 'BlackRock (London)',
            'category' => 'Asset Management',
            'quality' => 'premium',
            'has_salary' => true,
            'source_type' => 'company'
        ],
        'gib_careers' => [
            'url' => 'https://careers.gib.com/rss/',
            'type' => 'rss',
            'name' => 'Gulf International Bank (GIB)',
            'category' => 'GCC Banking',
            'quality' => 'premium',
            'has_salary' => false,
            'source_type' => 'company'
        ],
        // Oracle HCM API endpoints - these use dynamic job ID scanning
        'rothschild_oracle' => [
            'url' => 'https://evht.fa.ocs.oraclecloud.eu/hcmRestApi/resources/latest/recruitingCEJobRequisitionDetails/',
            'type' => 'oracle_hcm',
            'name' => 'Edmond de Rothschild',
            'category' => 'Investment Banking',
            'quality' => 'premium',
            'has_salary' => false,
            'scan_ranges' => [
                [900, 1000],    // Lower range with job 946
                [2100, 2200]    // Upper range with jobs 2111-2162
            ]
        ],
        'schroders_oracle' => [
            'url' => 'https://ekbq.fa.em2.oraclecloud.com/hcmRestApi/resources/latest/recruitingCEJobRequisitionDetails/',
            'type' => 'oracle_hcm',
            'name' => 'Schroders',
            'category' => 'Asset Management',
            'quality' => 'premium',
            'has_salary' => false,
            'scan_ranges' => [
                [100, 600]      // Jobs found at 250, 500, 550
            ]
        ],

        // Greenhouse.io API feeds
        'bluecrest_capital' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/bluecrestcapitalmanagement/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'BlueCrest Capital Management',
            'company_name' => 'BlueCrest Capital Management',
            'category' => 'Hedge Fund',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'capital_dynamics_ag' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/capitaldynamicsag/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Capital Dynamics',
            'company_name' => 'Capital Dynamics',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'quadrature_capital' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/quadraturecapital/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Quadrature Capital',
            'company_name' => 'Quadrature Capital',
            'category' => 'Hedge Fund',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'nova_founders_capital' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/novafounders/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Nova Founders Capital',
            'company_name' => 'Nova Founders Capital',
            'category' => 'Venture Capital',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'garda_capital_partners' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/gardacp/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Garda Capital Partners',
            'company_name' => 'Garda Capital Partners',
            'category' => 'Hedge Fund',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'aqr_capital_management' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/aqr/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'AQR Capital Management',
            'company_name' => 'AQR Capital Management',
            'category' => 'Hedge Fund',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'man_group' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/mangroup/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Man Group',
            'company_name' => 'Man Group',
            'category' => 'Asset Management',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'aksia' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/aksia/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Aksia',
            'company_name' => 'Aksia',
            'category' => 'Alternatives',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'iconiq_capital' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/iconiq/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'ICONIQ',
            'company_name' => 'ICONIQ',
            'category' => 'Investment Management',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'flagship_pioneering' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/flagshippioneeringinc/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Flagship Pioneering',
            'company_name' => 'Flagship Pioneering',
            'category' => 'Venture Capital',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'cobblestone_energy' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/cobblestoneenergy/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Cobblestone Energy',
            'company_name' => 'Cobblestone Energy',
            'category' => 'Commodities',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'permira_external_private' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/permiraexternalprivate/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Permira',
            'company_name' => 'Permira',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
        ],
        'pantheon' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/pantheonpublic/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Pantheon',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator'
        ],
        'eqt_partners' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/eqtpartners/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'EQT Partners',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator'
        ],
        'gcm_grosvenor' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/gcmgrosvenor/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'GCM Grosvenor',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator'
        ],
        'private_equity_insights' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/privateequityinsights/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Private Equity Insights',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator'
        ],
        'artisan_partners' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/artisanpartners/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Artisan Partners',
            'category' => 'Asset Management',
            'quality' => 'premium',
            'source_type' => 'job_aggregator'
        ],
        'point72' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/point72/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Point72',
            'category' => 'Hedge Fund',
            'quality' => 'premium',
            'source_type' => 'company'
        ],

        // Pinpoint ATS feeds
        'cinven' => [
            'url' => 'https://cinven.pinpointhq.com/postings',
            'type' => 'pinpoint',
            'name' => 'Cinven',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'company'
        ],

        // Job aggregators
        'workable_riyadh_daily' => [
            'url' => 'https://jobs.workable.com/search?location=Riyadh+Saudi+Arabia&day_range=1',
            'type' => 'workable',
            'name' => 'Workable - Riyadh Latest',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'location' => 'Riyadh, Saudi Arabia',
            'day_range' => 1,
        ],
        'workable_singapore_equity_daily' => [
            'url' => 'https://jobs.workable.com/search?location=Singapore&query=equity&day_range=1',
            'type' => 'workable',
            'name' => 'Workable - Singapore Equity Latest',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'location' => 'Singapore',
            'query' => 'equity',
            'day_range' => 1,
        ],
        'ik_partners_recruitee' => [
            'url' => 'https://ikpartners.recruitee.com/?jobs-c88dea0d%5Btab%5D=all',
            'api_url' => 'https://ikpartners.recruitee.com/api/offers',
            'type' => 'recruitee',
            'name' => 'IK Partners',
            'company_name' => 'IK Partners',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Recruitee',
        ],
        'blueearth_capital_recruitee' => [
            'url' => 'https://blueearthcapitalag.recruitee.com/',
            'api_url' => 'https://blueearthcapitalag.recruitee.com/api/offers',
            'type' => 'recruitee',
            'name' => 'Blue Earth Capital Careers',
            'company_name' => 'Blue Earth Capital',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Recruitee',
        ],
        'creandum_recruitee' => [
            'url' => 'https://creandum.recruitee.com/',
            'api_url' => 'https://creandum.recruitee.com/api/offers',
            'type' => 'recruitee',
            'name' => 'Creandum Careers',
            'company_name' => 'Creandum',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Recruitee',
        ],
        'wusool_capital_careers' => [
            'url' => 'https://www.wusoolcapital.com/careers',
            'type' => 'job_listing_page',
            'name' => 'Wusool Capital Careers',
            'company_name' => 'Wusool Capital',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Website',
            'job_url_pattern' => '~^/careers/[^/?#]+$~i',
            'allowed_locations' => ['Dubai', 'United Arab Emirates', 'UAE'],
        ],
        'foreground_jobs' => [
            'url' => 'https://jobs.foreground.ae/',
            'type' => 'job_listing_page',
            'name' => 'Foreground Jobs',
            'company_name' => 'Foreground',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Foreground',
            'job_url_pattern' => '~^/?jobs/[0-9a-f-]{36}$~i',
        ],
        'delta_exec_private_equity' => [
            'url' => 'https://www.deltaexec.com/executive-search/private-equity',
            'type' => 'job_listing_page',
            'name' => 'Delta Executive Search - Private Equity',
            'company_name' => 'Delta Executive Search',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Delta Executive Search',
            'job_url_pattern' => '~^/[a-z0-9][a-z0-9-]{18,}/?$~i',
            'exclude_url_pattern' => '~^/(executive-search|mandate-search|blog|contact|account|styles|sitemap|icons|privacy|terms)(/|\\?|$)~i',
        ],
        'adia_workable_careers' => [
            'url' => 'https://jobs.adia.ae/',
            'type' => 'workable_board',
            'name' => 'ADIA Careers',
            'company_name' => 'Abu Dhabi Investment Authority',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/adia/jobs.md',
            'company_url' => 'https://www.adia.ae',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi'],
        ],
        'abu_dhabi_investment_council_workable' => [
            'url' => 'https://apply.workable.com/abu-dhabi-investment-council/?lng=en',
            'type' => 'workable_board',
            'name' => 'Abu Dhabi Investment Council Careers',
            'company_name' => 'Abu Dhabi Investment Council',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/abu-dhabi-investment-council/jobs.md',
            'company_url' => 'https://www.adcouncil.ae',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi'],
        ],
        'emirates_investment_authority_workable' => [
            'url' => 'https://apply.workable.com/emirates-investment-authority/?lng=en',
            'type' => 'workable_board',
            'name' => 'Emirates Investment Authority Careers',
            'company_name' => 'Emirates Investment Authority',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/emirates-investment-authority/jobs.md',
            'company_url' => 'https://www.eia.gov.ae',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi'],
        ],
        'alrajhi_bank_careers' => [
            'url' => 'https://careers.alrajhibank.com.sa/en/job-search-results/',
            'type' => 'bayt_careers',
            'name' => 'Al Rajhi Bank Careers',
            'company_name' => 'Al Rajhi Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Bayt Careers',
            'company_logo' => 'https://ksaimg1.b8cdn.com/images/templates/alrajhibank/alrajhibank-logo-en.png?vid=29',
        ],
        'gib_bayt_careers' => [
            'url' => 'https://careers.gib.com/en/job-search-results/',
            'type' => 'bayt_careers',
            'name' => 'Gulf International Bank Careers',
            'company_name' => 'Gulf International Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Bayt Careers',
            'company_logo' => 'https://ksaimg0.b8cdn.com/images/templates/gib/gib-logo-en.png?vid=28',
        ],
        'riyad_bank_careers' => [
            'url' => 'https://careers.riyadbank.com/en/job-search-results/',
            'type' => 'bayt_careers',
            'name' => 'Riyad Bank Careers',
            'company_name' => 'Riyad Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Bayt Careers',
            'company_logo' => 'https://ksaimg4.b8cdn.com/images/templates/rdbank/rdbank-logo-en.png',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
            'force_company_name' => true,
        ],
        'tarmeez_capital_jobs' => [
            'url' => 'https://careers.tarmeez.co/jobs',
            'rss_url' => 'https://careers.tarmeez.co/jobs.rss',
            'type' => 'teamtailor_rss',
            'name' => 'Tarmeez Capital Jobs',
            'company_name' => 'Tarmeez Capital',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Teamtailor',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
        ],
        'coller_capital_jobs' => [
            'url' => 'https://collercapital-1660725598.teamtailor.com/jobs',
            'rss_url' => 'https://collercapital-1660725598.teamtailor.com/jobs.rss',
            'type' => 'teamtailor_rss',
            'name' => 'Coller Capital Jobs',
            'company_name' => 'Coller Capital',
            'category' => 'Job aggregators',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Teamtailor',
        ],
        'katapult_jobs' => [
            'url' => 'https://katapult.teamtailor.com/jobs',
            'rss_url' => 'https://katapult.teamtailor.com/jobs.rss',
            'type' => 'teamtailor_rss',
            'name' => 'Katapult Jobs',
            'company_name' => 'Katapult',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Teamtailor',
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'adnoc_investment_jobs' => [
            'url' => 'https://jobs.adnoc.ae/us/en/search-results?keywords=investment',
            'type' => 'phenom',
            'name' => 'ADNOC Investment Jobs',
            'company_name' => 'ADNOC',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Phenom',
            'job_base_url' => 'https://jobs.adnoc.ae/us/en/job',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi', 'Dubai'],
        ],
        'dubai_holding_investment_jobs' => [
            'url' => 'https://dhcareers.avature.net/en_US/newcareersmarketplace/SearchJobs/investment/feed/?listFilterMode=1&jobRecordsPerPage=25',
            'type' => 'rss',
            'name' => 'Dubai Holding Investment Jobs',
            'company_name' => 'Dubai Holding',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Avature',
            'company_logo' => 'https://dhcareers.avature.net/portal/13/images/logo--hiring-organization.webp',
        ],
        'masdar_smartrecruiters' => [
            'url' => 'https://careers.smartrecruiters.com/masdar',
            'api_url' => 'https://api.smartrecruiters.com/v1/companies/masdar/postings',
            'type' => 'smartrecruiters',
            'name' => 'Masdar Careers',
            'company_name' => 'Masdar',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SmartRecruiters',
            'company_id' => 'masdar',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi', 'Dubai', 'London', 'United Kingdom'],
        ],
        'aldar_lever' => [
            'url' => 'https://jobs.lever.co/aldar',
            'api_url' => 'https://api.lever.co/v0/postings/aldar?mode=json',
            'type' => 'lever',
            'name' => 'Aldar Careers',
            'company_name' => 'Aldar',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Lever',
            'company_id' => 'aldar',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi', 'Dubai'],
        ],
        'raine_lever' => [
            'url' => 'https://jobs.lever.co/raine',
            'api_url' => 'https://api.lever.co/v0/postings/raine?mode=json',
            'type' => 'lever',
            'name' => 'Raine Careers',
            'company_name' => 'Raine',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Lever',
            'company_id' => 'raine',
        ],
        'capital_lever' => [
            'url' => 'https://jobs.lever.co/capital',
            'api_url' => 'https://api.lever.co/v0/postings/capital?mode=json',
            'type' => 'lever',
            'name' => 'Capital Careers',
            'company_name' => 'Capital',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Lever',
            'company_id' => 'capital',
        ],
        'last_energy_lever' => [
            'url' => 'https://jobs.lever.co/last-energy',
            'api_url' => 'https://api.lever.co/v0/postings/last-energy?mode=json',
            'type' => 'lever',
            'name' => 'Last Energy Careers',
            'company_name' => 'Last Energy',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Lever',
            'company_id' => 'last-energy',
        ],
        'bunch_ashby' => [
            'url' => 'https://jobs.ashbyhq.com/bunch',
            'type' => 'ashby',
            'name' => 'Bunch Careers',
            'company_name' => 'Bunch',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Ashby',
            'company_id' => 'bunch',
        ],
        'lndmrk_ashby' => [
            'url' => 'https://jobs.ashbyhq.com/lndmrk',
            'type' => 'ashby',
            'name' => 'LNDMRK Careers',
            'company_name' => 'LNDMRK',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Ashby',
            'company_id' => 'lndmrk',
        ],
        'leantech_ashby' => [
            'url' => 'https://jobs.ashbyhq.com/LeanTech',
            'type' => 'ashby',
            'name' => 'Lean Technologies Careers',
            'company_name' => 'Lean Technologies',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Ashby',
            'company_id' => 'LeanTech',
            'allowed_locations' => ['Dubai', 'United Arab Emirates'],
        ],
        'malaa_pinpoint' => [
            'url' => 'https://malaa.pinpointhq.com/postings',
            'type' => 'pinpoint',
            'name' => 'Malaa Careers',
            'company_name' => 'Malaa',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Pinpoint',
        ],
        'tabby_pinpoint_finance' => [
            'url' => 'https://tabby.pinpointhq.com/postings',
            'type' => 'pinpoint',
            'name' => 'Tabby Finance Careers',
            'company_name' => 'Tabby',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Pinpoint',
            'department_keywords' => ['finance'],
        ],
        'astorg_pinpoint' => [
            'url' => 'https://astorg.pinpointhq.com/postings',
            'type' => 'pinpoint',
            'name' => 'Astorg Careers',
            'company_name' => 'Astorg',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Pinpoint',
        ],
        'gsequity_bamboohr' => [
            'url' => 'https://gsequity.bamboohr.com/careers',
            'api_url' => 'https://gsequity.bamboohr.com/careers/list',
            'type' => 'bamboohr',
            'name' => 'GS Equity Careers',
            'company_name' => 'GS Equity',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'BambooHR',
            'company_id' => 'gsequity',
        ],
        'cygnum_capital_bamboohr' => [
            'url' => 'https://cygcap.bamboohr.com/careers',
            'api_url' => 'https://cygcap.bamboohr.com/careers/list',
            'type' => 'bamboohr',
            'name' => 'Cygnum Capital Careers',
            'company_name' => 'Cygnum Capital',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'BambooHR',
            'company_id' => 'cygcap',
            'allowed_locations' => ['London', 'United Kingdom'],
        ],
        'intaj_alamal_zoho' => [
            'url' => 'https://intajalamal.zohorecruit.com/jobs/Careers/rss',
            'type' => 'rss',
            'name' => 'Intaj Al Amal Careers',
            'company_name' => 'Intaj Al Amal',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Zoho Recruit RSS',
        ],
        'proven_arabia_zoho' => [
            'url' => 'https://proven-sa.zohorecruit.com/jobs/careers',
            'type' => 'zoho_recruit_page',
            'name' => 'Proven Arabia Careers',
            'company_name' => 'Proven Arabia',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Zoho Recruit',
            'allowed_locations' => ['Saudi Arabia', 'Riyadh', 'Jeddah', 'Dammam', 'Khobar'],
        ],
        'outliers_vc_careers' => [
            'url' => 'https://talent.outliers.vc/',
            'type' => 'outliers_vc',
            'name' => 'Outliers VC Careers',
            'company_name' => 'Outliers VC',
            'category' => 'Private Equity',
            'quality' => 'premium',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Outliers VC',
            'allowed_locations' => ['Saudi Arabia', 'Riyadh'],
        ],
        'red_sea_finance_successfactors' => [
            'url' => 'https://careers.theredsea.sa/go/Job-Opportunities/7716923/?q=&q2=&alertId=&title=&department=finance#searchresults',
            'type' => 'successfactors',
            'name' => 'Red Sea Global Finance Jobs',
            'company_name' => 'Red Sea Global',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia', 'Jeddah', 'Tabuk'],
            'force_company_name' => true,
        ],
        'red_sea_residential_development_successfactors' => [
            'url' => 'https://careers.theredsea.sa/go/Job-Opportunities/7716923/?q=&q2=&alertId=&title=&department=Residential+Development#searchresults',
            'type' => 'successfactors',
            'name' => 'Red Sea Global Residential Development Jobs',
            'company_name' => 'Red Sea Global',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia', 'Jeddah', 'Tabuk'],
            'force_company_name' => true,
        ],
        'kafd_bayt_careers' => [
            'url' => 'https://careers.kafd.sa/en/job-search-results/',
            'type' => 'bayt_careers',
            'name' => 'KAFD Careers',
            'company_name' => 'KAFD',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Bayt Careers',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
            'force_company_name' => true,
        ],
        'al_tayer_associate_oracle' => [
            'url' => 'https://hchx.fa.em2.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs?keyword=associate&mode=location',
            'type' => 'oracle_cx',
            'name' => 'Al Tayer Group Associate Jobs',
            'company_name' => 'Al Tayer Group',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle Candidate Experience',
            'site_number' => 'CX_1',
            'api_base_url' => 'https://hchx.fa.em2.oraclecloud.com',
            'keyword' => 'associate',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
        ],
        'fisher_london_jobs' => [
            'url' => 'https://www.fishercareers.com/search-jobs/London/38252/4/2643743-6269131-2635167-2648110/51x50853/-0x12574/50/2',
            'type' => 'talentbrew_search',
            'name' => 'Fisher Investments London Jobs',
            'company_name' => 'Fisher Investments',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'TalentBrew',
            'allowed_locations' => ['London', 'United Kingdom'],
        ],
        'fasanara_workable' => [
            'url' => 'https://apply.workable.com/fasanara/',
            'type' => 'workable_board',
            'name' => 'Fasanara Careers',
            'company_name' => 'Fasanara',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/fasanara/jobs.md',
            'allowed_locations' => ['London', 'United Kingdom', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'parrot_analytics_workable' => [
            'url' => 'https://apply.workable.com/parrot-analytics-4/',
            'type' => 'workable_board',
            'name' => 'Parrot Analytics Careers',
            'company_name' => 'Parrot Analytics',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/parrot-analytics-4/jobs.md',
            'allowed_locations' => ['Qatar', 'Doha', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'London', 'United Kingdom'],
        ],
        'hayfin_capital_management_workable' => [
            'url' => 'https://apply.workable.com/hayfin-capital-management/',
            'type' => 'workable_board',
            'name' => 'Hayfin Capital Management Careers',
            'company_name' => 'Hayfin Capital Management',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/hayfin-capital-management/jobs.md',
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'novo_holdings_workable' => [
            'url' => 'https://jobs.workable.com/company/wbXhUUrak5234NXUu7sSsj/jobs-at-novo-holdings',
            'type' => 'workable_board',
            'name' => 'Novo Holdings Careers',
            'company_name' => 'Novo Holdings',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/novoholdings/jobs.md',
            'allowed_locations' => ['Singapore', 'London', 'United Kingdom', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'insight_investment_workable' => [
            'url' => 'https://apply.workable.com/insight-investment/',
            'type' => 'workable_board',
            'name' => 'Insight Investment Careers',
            'company_name' => 'Insight Investment',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/insight-investment/jobs.md',
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'esr_group_workable' => [
            'url' => 'https://jobs.esr.com/',
            'type' => 'workable_board',
            'name' => 'ESR Group Careers',
            'company_name' => 'ESR Group',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Workable',
            'jobs_md_url' => 'https://apply.workable.com/esr-group/jobs.md?query=investment',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Saudi Arabia', 'Riyadh', 'Qatar', 'Doha', 'Singapore', 'London', 'United Kingdom'],
        ],
        'blackrock_saudi_jobs' => [
            'url' => 'https://careers.blackrock.com/search-jobs/Saudi%20Arabia/45831/2/102358/25/45/0/2',
            'type' => 'talentbrew_search',
            'name' => 'BlackRock Saudi Arabia Jobs',
            'company_name' => 'BlackRock',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'TalentBrew',
            'company_logo' => 'https://tbcdn.talentbrew.com/company/45831/cms/img/general/blackrock-logo-308x76.webp',
            'allowed_locations' => ['Saudi Arabia', 'Riyadh'],
        ],
        'merak_capital_jisr_jobs' => [
            'url' => 'https://www.jisr.net/en/merakcapital/careers-page?host=1&id=debde4c9-e40d-411f-b5cf-57ada988a20b',
            'type' => 'jisr_careers',
            'name' => 'Merak Capital Careers',
            'company_name' => 'Merak Capital',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Jisr',
            'api_url' => 'https://apis.jisr.net/ats/api/v1/career_websites/jobs_details',
            'career_website_uuid' => 'debde4c9-e40d-411f-b5cf-57ada988a20b',
            'company_slug' => 'merakcapital',
            'location' => 'Riyadh, Saudi Arabia',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
        ],
        'agfund_jobs' => [
            'url' => 'https://agfund.org/en/jobs',
            'type' => 'agfund',
            'name' => 'AGFUND Jobs',
            'company_name' => 'AGFUND',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'AGFUND Careers',
            'company_logo' => 'https://agfund.org/images/Agfund-Logo-0122_3_82x62.png',
            'location' => 'Riyadh, Saudi Arabia',
            'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
        ],
        'savills_middle_east_jobs' => [
            'url' => 'https://careers.savills.me/jobs',
            'type' => 'teamtailor_rss',
            'name' => 'Savills Middle East Jobs',
            'company_name' => 'Savills Middle East',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Teamtailor',
            'company_logo' => 'https://images.teamtailor-cdn.com/images/s3/teamtailor-production/logotype-v3/image_uploads/33b57ccb-c6c6-4eb4-93d8-eaa145d29be4/original.png',
            'rss_url' => 'https://careers.savills.me/jobs.rss',
        ],
        'stc_careers' => [
            'url' => 'https://careers.stc.com.sa/search/?createNewAlert=false&q=&optionsFacetsDD_location=&optionsFacetsDD_facility=&optionsFacetsDD_customfield1=',
            'type' => 'successfactors',
            'name' => 'stc Careers',
            'company_name' => 'stc',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'company_logo' => 'https://rmkcdn.successfactors.com/5075ac53/ce1d8d1a-0219-4255-81a8-c.png',
        ],
        'al_futtaim_finance' => [
            'url' => 'https://www.afuturewithus.com/search/?q=finance',
            'type' => 'successfactors',
            'name' => 'Al-Futtaim Finance Jobs',
            'company_name' => 'Al Futtaim Private Company LLC',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'company_logo' => 'https://rmkcdn.successfactors.com/5d589df0/8f422567-53e4-4e54-8da4-0.svg',
        ],
        'elm_careers' => [
            'url' => 'https://career.elm.sa/elm/search/?createNewAlert=false&q=&locationsearch=',
            'type' => 'successfactors',
            'name' => 'Elm Careers',
            'company_name' => 'Elm',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'company_logo' => 'https://rmkcdn.successfactors.com/c966bc59/11976c96-eed7-404f-b051-b.png',
            'force_company_name' => true,
        ],
        'doha_bank_careers' => [
            'url' => 'https://www.dohabank.com.qa/?feed=job_feed',
            'type' => 'rss',
            'name' => 'Doha Bank Careers',
            'company_name' => 'Doha Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'WP Job Manager RSS',
            'company_logo' => 'https://www.dohabank.com.qa/wp-content/uploads/sites/12/cropped-db-icon-512-192x192.png',
            'allowed_locations' => ['Doha', 'Qatar'],
        ],
        'standard_chartered_uae' => [
            'url' => 'https://jobs.standardchartered.com/search/?q=&skillsSearch=false&markerViewed=&carouselIndex=&facetFilters=%7B%22jobLocationCountry%22%3A%5B%22United+Arab+Emirates%22%5D%7D&pageNumber=0',
            'type' => 'successfactors',
            'name' => 'Standard Chartered UAE Careers',
            'company_name' => 'Standard Chartered',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'company_logo' => 'https://rmkcdn.successfactors.com/41234125/6c418eac-00f7-48df-8f71-b.png',
            'force_company_name' => true,
            'use_unified_api' => true,
            'api_url' => 'https://jobs.standardchartered.com/services/recruiting/v1/jobs',
            'locale' => 'en_GB',
            'facet_filters' => [
                'jobLocationCountry' => ['United Arab Emirates'],
            ],
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
        ],
        'gic_real_estate' => [
            'url' => 'https://careers.gic.com.sg/search/?q=&q2=&alertId=&locationsearch=&title=&department=Real+estate&location=',
            'type' => 'successfactors',
            'name' => 'GIC Real Estate Careers',
            'company_name' => 'GIC',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'gic_private_equity' => [
            'url' => 'https://careers.gic.com.sg/search/?q=&q2=&alertId=&locationsearch=&title=&department=private+equity&location=',
            'type' => 'successfactors',
            'name' => 'GIC Private Equity Careers',
            'company_name' => 'GIC',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'temasek_public_market_investment_strategies' => [
            'url' => 'https://jobs.temasek.com.sg/search?q=&q2=&alertId=&locationsearch=&title=&department=Public+Market+Investment+Strategies&location=',
            'type' => 'successfactors',
            'name' => 'Temasek Public Market Investment Strategies',
            'company_name' => 'Temasek',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'temasek_innovation' => [
            'url' => 'https://jobs.temasek.com.sg/search?q=&q2=&alertId=&locationsearch=&title=&department=innovation&location=',
            'type' => 'successfactors',
            'name' => 'Temasek Innovation',
            'company_name' => 'Temasek',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'temasek_sustainability' => [
            'url' => 'https://jobs.temasek.com.sg/search?q=&q2=&alertId=&locationsearch=&title=&department=sustainability&location=',
            'type' => 'successfactors',
            'name' => 'Temasek Sustainability',
            'company_name' => 'Temasek',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'allowed_locations' => ['London', 'United Kingdom', 'Singapore', 'United Arab Emirates', 'Dubai', 'Riyadh', 'Saudi Arabia', 'Doha', 'Qatar'],
        ],
        'hsbc_dubai_careers' => [
            'url' => 'https://portal.careers.hsbc.com/careers/search?query=%2A&location=Dubai%2C%20United%20Arab%20Emirates&pid=563774610998857&domain=hsbc.com&sort_by=relevance&triggerGoButton=false&triggerGoButton=true',
            'type' => 'eightfold',
            'name' => 'HSBC Dubai Careers',
            'company_name' => 'HSBC',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Eightfold',
            'company_logo' => 'https://static.vscdn.net/images/careers/demo/hsbc/1727956206::favicon.png',
            'allowed_locations' => ['Dubai', 'United Arab Emirates'],
        ],
        'jpmorgan_chase_dubai_oracle' => [
            'url' => 'https://jpmc.fa.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1001/jobs?location=Dubai%2C+United+Arab+Emirates&locationId=300000020333038&locationLevel=state&mode=location',
            'type' => 'oracle_cx',
            'name' => 'JPMorgan Chase Dubai Careers',
            'company_name' => 'JPMorgan Chase & Co.',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'company_logo' => 'https://jpmc.fa.oraclecloud.com/hcmRestApi/CandidateExperience/siteFavicon/favicon-144x144.png?siteNumber=CX_1001&size=144x144',
            'api_base_url' => 'https://jpmc.fa.oraclecloud.com',
            'site_number' => 'CX_1001',
            'location' => 'Dubai, United Arab Emirates',
            'location_id' => '300000020333038',
            'location_level' => 'state',
            'allowed_locations' => ['Dubai', 'United Arab Emirates'],
        ],
        'adcb_successfactors_search' => [
            'url' => 'https://adcbcareers.com/search/?createNewAlert=false&q=',
            'type' => 'successfactors',
            'name' => 'ADCB Careers',
            'company_name' => 'Abu Dhabi Commercial Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'SAP SuccessFactors',
            'company_logo' => 'https://rmkcdn.successfactors.com/b2e2b5cf/d5c2f451-2501-4f9f-b4c7-b.png',
            'force_company_name' => true,
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
        ],
        'dubai_investments_careers' => [
            'url' => 'https://fa-evue-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs?mode=location',
            'type' => 'oracle_cx',
            'name' => 'Dubai Investments Group Careers',
            'company_name' => 'Dubai Investments Group',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'company_logo' => 'https://www.dubaiinvestments.com/uploads/di-logo.jpg',
            'api_base_url' => 'https://fa-evue-saasfaprod1.fa.ocs.oraclecloud.com',
            'site_number' => 'CX_1',
            'allowed_locations' => ['United Arab Emirates', 'Dubai'],
        ],
        'goldman_sachs_middle_east' => [
            'url' => 'https://higher.gs.com/results?LOCATION=Riyadh|Dubai&page=1&sort=RELEVANCE',
            'type' => 'goldman_higher',
            'name' => 'Goldman Sachs Middle East Careers',
            'company_name' => 'Goldman Sachs',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Goldman Sachs Higher',
            'api_url' => 'https://api-higher.gs.com/gateway/api/v1/graphql',
            'allowed_locations' => ['Dubai', 'Riyadh'],
        ],
        'deutsche_bank_uae' => [
            'url' => 'https://careers.db.com/professionals/search-roles/#/professional/results/?country=230',
            'type' => 'deutsche_bank_beesite',
            'name' => 'Deutsche Bank UAE Careers',
            'company_name' => 'Deutsche Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Deutsche Bank Beesite',
            'api_url' => 'https://api-deutschebank.beesite.de/search/',
            'country_id' => '230',
            'allowed_locations' => ['United Arab Emirates', 'Dubai'],
        ],
        'rakbank_careers' => [
            'url' => 'https://iacqey.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs',
            'type' => 'oracle_cx',
            'name' => 'RAKBANK Careers',
            'company_name' => 'RAKBANK',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'company_logo' => 'https://campaignme.com/wp-content/uploads/2021/03/rakbank-cover.jpg',
            'api_base_url' => 'https://iacqey.fa.ocs.oraclecloud.com',
            'site_number' => 'CX_1',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Ras Al Khaimah'],
        ],
        'emirates_nbd_careers' => [
            'url' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs',
            'type' => 'oracle_cx',
            'name' => 'Emirates NBD Careers',
            'company_name' => 'Emirates NBD',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'company_logo' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com/cs/groups/public/documents/digitalmedia/c25i/zc0y/~edisp/logo-emiratesnbd-2024.png',
            'api_base_url' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com',
            'site_number' => 'CX_1',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Riyadh'],
        ],
        'iaarey_oracle_dubai_careers' => [
            'url' => 'https://iaarey.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1001/jobs',
            'type' => 'oracle_cx',
            'name' => 'IAAREY Oracle Careers',
            'company_name' => 'IAAREY',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'Oracle HCM',
            'api_base_url' => 'https://iaarey.fa.ocs.oraclecloud.com',
            'site_number' => 'CX_1001',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
        ],
        'shamal_icims_careers' => [
            'url' => 'https://careers-shamal.icims.com/jobs/search?ss=1&hashed=-625882864',
            'type' => 'icims_search',
            'name' => 'Shamal Careers',
            'company_name' => 'Shamal',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'iCIMS',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Sharjah'],
        ],
        'nichehr_global_careers' => [
            'url' => 'https://ats.nichehrglobal.com/careers',
            'api_url' => 'https://sxfmreydtmwwdrdfhbja.supabase.co/rest/v1/jobs?select=*&status=eq.OPEN&order=created_at.desc',
            'type' => 'nichehr_supabase',
            'name' => 'NicheHR Global Careers',
            'company_name' => 'NicheHR Global',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'NicheHR',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh', 'Doha', 'Qatar'],
        ],
        'hdid_oracle_dubai_careers' => [
            'url' => 'https://hdid.fa.us2.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs',
            'type' => 'oracle_cx',
            'name' => 'HDID Oracle Careers',
            'company_name' => 'HDID',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'Oracle HCM',
            'api_base_url' => 'https://hdid.fa.us2.oraclecloud.com',
            'site_number' => 'CX_1',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
        ],
        'fitch_group_dubai_successfactors' => [
            'url' => 'https://careers.fitch.group/search/?createNewAlert=false&q=&locationsearch=dubai&optionsFacetsDD_customfield2=&optionsFacetsDD_department=&optionsFacetsDD_customfield4=',
            'type' => 'successfactors',
            'name' => 'Fitch Group Dubai Careers',
            'company_name' => 'Fitch Group',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'allowed_locations' => ['United Arab Emirates', 'Dubai'],
        ],
        'ezra_greenhouse_careers' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/ezra/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Ezra Careers',
            'company_name' => 'Ezra',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'Greenhouse',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh', 'Doha', 'Qatar'],
        ],
        'the_first_group_icims_careers' => [
            'url' => 'https://careers-thefirstgroup.icims.com/jobs/search?ss=1&searchLocation=--Dubai',
            'type' => 'icims_search',
            'name' => 'The First Group Careers',
            'company_name' => 'The First Group',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'iCIMS',
            'allowed_locations' => ['United Arab Emirates', 'Dubai'],
        ],
        'chalhoub_accounting_saudi_teamtailor' => [
            'url' => 'https://careers.chalhoubgroup.com/jobs?split_view=true&query=&department=ACCOUNTING&country=Saudi+Arabia',
            'rss_url' => 'https://careers.chalhoubgroup.com/jobs.rss?department=ACCOUNTING&country=Saudi+Arabia',
            'type' => 'teamtailor_rss',
            'name' => 'Chalhoub Group Accounting Saudi Arabia',
            'company_name' => 'Chalhoub Group',
            'category' => 'Accounting',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'Teamtailor',
            'allowed_locations' => ['Saudi Arabia', 'Riyadh', 'Jeddah', 'Dammam', 'Khobar'],
        ],
        'chalhoub_finance_uae_teamtailor' => [
            'url' => 'https://careers.chalhoubgroup.com/jobs?split_view=true&query=&department=FINANCE+%26+TREASURY&country=United+Arab+Emirates',
            'rss_url' => 'https://careers.chalhoubgroup.com/jobs.rss?department=FINANCE+%26+TREASURY&country=United+Arab+Emirates',
            'type' => 'teamtailor_rss',
            'name' => 'Chalhoub Group Finance UAE',
            'company_name' => 'Chalhoub Group',
            'category' => 'Finance & Treasury',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'Teamtailor',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
        ],
        'nbf_successfactors_careers' => [
            'url' => 'https://careers.nbf.ae/search/?q=&searchResultView=LIST',
            'type' => 'successfactors',
            'name' => 'National Bank of Fujairah Careers',
            'company_name' => 'National Bank of Fujairah',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'SAP SuccessFactors',
            'force_company_name' => true,
            'use_unified_api' => true,
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Fujairah'],
        ],
        'emirates_nbd_cx2_careers' => [
            'url' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_2/jobs',
            'type' => 'oracle_cx',
            'name' => 'Emirates NBD CX2 Careers',
            'company_name' => 'Emirates NBD',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'feed_group' => 'dubai_feeds',
            'source_platform' => 'Oracle HCM',
            'company_logo' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com/cs/groups/public/documents/digitalmedia/c25i/zc0y/~edisp/logo-emiratesnbd-2024.png',
            'api_base_url' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com',
            'site_number' => 'CX_2',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Riyadh'],
        ],
        'cbd_comeet_careers' => [
            'url' => 'https://www.comeet.com/jobs/cbd/14.007/',
            'type' => 'comeet',
            'name' => 'Commercial Bank of Dubai Careers',
            'company_name' => 'Commercial Bank of Dubai',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Comeet',
            'company_logo' => 'https://www.comeet.co/pub/cbd/14.007/logo?size=medium&last-modified=1767858046',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah'],
        ],
        'fab_careers' => [
            'url' => 'https://ehjd.fa.em2.oraclecloud.com/hcmUI/CandidateExperience/en/sites/fabCareers/jobs',
            'type' => 'oracle_cx',
            'name' => 'FAB Careers',
            'company_name' => 'First Abu Dhabi Bank',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'company_logo' => 'https://ehjd-dev1.fa.em2.oraclecloud.com:443/hcmUI/CandidateExperience/images?imageId=49A4C61C-2357-4AC2-8A93-2F7FC21BA611',
            'api_base_url' => 'https://ehjd.fa.em2.oraclecloud.com',
            'site_number' => 'CX_2001',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Riyadh'],
        ],
        'mashreq_careers' => [
            'url' => 'https://hcld.fa.em2.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs',
            'type' => 'oracle_cx',
            'name' => 'Mashreq Careers',
            'company_name' => 'Mashreq',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'api_base_url' => 'https://hcld.fa.em2.oraclecloud.com',
            'site_number' => 'CX_1',
            'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh'],
        ],
        'adgm_external_careers' => [
            'url' => 'https://fa-eukk-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience/en/sites/CX_1/jobs',
            'type' => 'oracle_cx',
            'name' => 'ADGM External Site Careers',
            'company_name' => 'ADGM',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Oracle HCM',
            'api_base_url' => 'https://fa-eukk-saasfaprod1.fa.ocs.oraclecloud.com',
            'site_number' => 'CX_1',
            'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi', 'Dubai'],
        ],
        'michaelpage_ae_finance_dubai' => [
            'url' => 'https://www.michaelpage.ae/jobs/finance/dubai-dubai?sort_by=most_recent',
            'type' => 'michael_page',
            'name' => 'Michael Page UAE - Finance Dubai',
            'company_name' => 'Michael Page',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Michael Page',
            'location' => 'Dubai, United Arab Emirates',
            'company_logo' => 'https://www.michaelpage.ae/themes/custom/mp_theme/logo.svg',
        ],
        'michaelpage_ae_investment_dubai' => [
            'url' => 'https://www.michaelpage.ae/jobs/investment/dubai-dubai?sort_by=most_recent',
            'type' => 'michael_page',
            'name' => 'Michael Page UAE - Investment Dubai',
            'company_name' => 'Michael Page',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Michael Page',
            'location' => 'Dubai, United Arab Emirates',
            'company_logo' => 'https://www.michaelpage.ae/themes/custom/mp_theme/logo.svg',
        ],
        'michaelpage_ae_saudi_arabia' => [
            'url' => 'https://www.michaelpage.ae/jobs/saudi-arabia?sort_by=most_recent',
            'type' => 'michael_page',
            'name' => 'Michael Page UAE - Saudi Arabia',
            'company_name' => 'Michael Page',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Michael Page',
            'location' => 'Saudi Arabia',
            'company_logo' => 'https://www.michaelpage.ae/themes/custom/mp_theme/logo.svg',
        ],
        'aventus_global_jobs' => [
            'url' => 'https://www.aventusglobal.com/jobs',
            'type' => 'aventus',
            'name' => 'Aventus Global Jobs',
            'company_name' => 'Aventus Global',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Aventus Global',
            'company_logo' => 'https://www.aventusglobal.com/themes/aventus-global-talent/assets/images/aventus-logo-1.png',
        ],
        'venture_search_jobs' => [
            'url' => 'https://www.venturesearch.com/jobs',
            'type' => 'venture_search',
            'name' => 'Venture Search Jobs',
            'company_name' => 'Venture Search',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Venture Search',
            'company_logo' => 'https://www.venturesearch.com/images/logo.svg',
        ],
        'mubadala_professional' => [
            'url' => 'https://www.mubadala.com/en/careers/professional',
            'type' => 'mubadala_takafo',
            'name' => 'Mubadala Professional Careers',
            'company_name' => 'Mubadala Investment Company',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Takafo',
            'company_logo' => 'https://www.mubadala.com/~/media/Images/M/mubadala/corp/logo/mubadala-logo-dark.svg',
            'api_url' => 'https://mic-cand.takafo.ai/v1/jobs/external',
        ],
        'teneo_middle_east' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/teneolinkedin/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Teneo Middle East Careers',
            'company_name' => 'Teneo',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
            'company_logo' => 'https://s3-recruiting.cdn.greenhouse.io/external_greenhouse_job_boards/logos/400/157/100/original/Teneo_Logo_Full_Color_(3).jpg?1584373837',
            'allowed_locations' => ['Dubai', 'Riyadh'],
        ],
        'alpha_fmc_middle_east' => [
            'url' => 'https://boards-api.greenhouse.io/v1/boards/alphafmcroles/jobs?content=true',
            'type' => 'greenhouse',
            'name' => 'Alpha FMC Middle East Careers',
            'company_name' => 'Alpha Financial Markets Consulting',
            'category' => 'Job aggregators',
            'quality' => 'standard',
            'source_type' => 'job_aggregator',
            'source_platform' => 'Greenhouse',
            'company_logo' => 'https://s2-recruiting.cdn.greenhouse.io/external_greenhouse_job_boards/logos/401/165/900/original/Alpha_Group_Colour_RGB.png?1785147933',
            'allowed_locations' => ['Dubai', 'Riyadh', 'Doha'],
        ],
        
    ];

    /**
     * Cache duration (2 hours for XML feeds)
     */
    private $cache_duration = 7200;

    public function __construct()
    {
        $this->bootstrap_custom_sources();
    }

    private function bootstrap_custom_sources()
    {
        $custom_feeds = get_option('sffc_custom_xml_feeds', []);
        if (!empty($custom_feeds) && is_array($custom_feeds)) {
            foreach ($custom_feeds as $key => $feed) {
                if (empty($feed['url'])) {
                    continue;
                }

                $defaults = [
                    'name' => ucwords(str_replace('_', ' ', $key)),
                    'type' => 'sitemap',
                    'category' => 'Recruiter',
                    'quality' => 'standard',
                    'source_type' => $feed['source_type'] ?? 'recruiter',
                ];

                $this->xml_sources[$key] = array_merge($defaults, $feed);
            }
        }

        $disabled_feeds = get_option('sffc_disabled_feeds', []);
        if (!empty($disabled_feeds) && is_array($disabled_feeds)) {
            foreach ($disabled_feeds as $disabled_key) {
                if (strpos($disabled_key, 'xml_') === 0) {
                    $key = substr($disabled_key, 4);
                    unset($this->xml_sources[$key]);
                }
            }
        }
    }

    /**
     * Get XML sources for admin management
     */
    public function get_xml_sources() {
        return $this->xml_sources;
    }

    /**
     * Get trending jobs from all sources
     */
    public function get_trending_jobs($limit = 20)
    {
        $all_jobs = [];

        // Prioritize premium sources
        $premium_sources = ['pearse_partners'];

        foreach ($premium_sources as $source_key) {
            $jobs = $this->fetch_jobs_from_source($source_key, 10);
            if (!empty($jobs)) {
                $all_jobs = array_merge($all_jobs, $jobs);
            }
        }

        foreach ($this->xml_sources as $key => $info) {
            if (in_array($key, $premium_sources, true)) {
                continue;
            }

            if (($info['source_type'] ?? '') !== 'recruiter') {
                continue;
            }

            $jobs = $this->fetch_jobs_from_source($key, 10);
            if (!empty($jobs)) {
                $all_jobs = array_merge($all_jobs, $jobs);
            }
        }

        // Sort by date and return top results
        usort($all_jobs, function ($a, $b) {
            return strcmp($b['posted_date'], $a['posted_date']);
        });

        return array_slice($all_jobs, 0, $limit);
    }

    /**
     * Get premium jobs with salary data
     */
    public function get_premium_jobs($limit = 20, $offset = 0)
    {
        $cache_key = 'sffc_premium_jobs_' . $limit . '_' . $offset;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $premium_jobs = [];

        // Focus on sources with salary data
        $salary_sources = ['pearse_partners'];

        foreach ($salary_sources as $source_key) {
            $jobs = $this->fetch_jobs_from_source($source_key, 50);
            if (!empty($jobs)) {
                foreach ($jobs as &$job) {
                    $job['is_premium'] = true;
                    $job['has_salary'] = true;
                }
                $premium_jobs = array_merge($premium_jobs, $jobs);
            }
        }

        // Apply offset and limit
        $result = array_slice($premium_jobs, $offset, $limit);

        set_transient($cache_key, $result, $this->cache_duration);

        return $result;
    }

    /**
     * Fetch jobs from a specific XML source
     */
    private function fetch_jobs_from_source($source_key, $max_jobs = 50)
    {
        if (!isset($this->xml_sources[$source_key])) {
            return [];
        }

        $source = $this->xml_sources[$source_key];
        $cache_key = 'sffc_xml_' . $source_key;

        // Check cache
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return array_slice($cached, 0, $max_jobs);
        }

        $source_type = $source['type'] ?? 'sitemap';
        if ($source_type === 'oracle_hcm') {
            $jobs = $this->fetch_oracle_hcm_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'greenhouse') {
            $jobs = $this->fetch_greenhouse_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'pinpoint') {
            $jobs = $this->fetch_pinpoint_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'website') {
            $jobs = $this->fetch_website_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'workable') {
            $jobs = $this->fetch_workable_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'workable_board') {
            $jobs = $this->fetch_workable_board_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'bayt_careers') {
            $jobs = $this->fetch_bayt_careers_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'successfactors') {
            $jobs = $this->fetch_successfactors_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'icims_search') {
            $jobs = $this->fetch_icims_search_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'nichehr_supabase') {
            $jobs = $this->fetch_nichehr_supabase_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'phenom') {
            $jobs = $this->fetch_phenom_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'talentbrew_search') {
            $jobs = $this->fetch_talentbrew_search_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'smartrecruiters') {
            $jobs = $this->fetch_smartrecruiters_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'lever') {
            $jobs = $this->fetch_lever_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'ashby') {
            $jobs = $this->fetch_ashby_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'bamboohr') {
            $jobs = $this->fetch_bamboohr_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'zoho_recruit_page') {
            $jobs = $this->fetch_zoho_recruit_page_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'outliers_vc') {
            $jobs = $this->fetch_outliers_vc_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'recruitee') {
            $jobs = $this->fetch_recruitee_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'job_listing_page') {
            $jobs = $this->fetch_curated_listing_page_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'jisr_careers') {
            $jobs = $this->fetch_jisr_careers_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'oracle_cx') {
            $jobs = $this->fetch_oracle_cx_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'goldman_higher') {
            $jobs = $this->fetch_goldman_higher_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'deutsche_bank_beesite') {
            $jobs = $this->fetch_deutsche_bank_beesite_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'comeet') {
            $jobs = $this->fetch_comeet_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'agfund') {
            $jobs = $this->fetch_agfund_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'teamtailor_rss') {
            $jobs = $this->fetch_teamtailor_rss_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'michael_page') {
            $jobs = $this->fetch_michael_page_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'aventus') {
            $jobs = $this->fetch_aventus_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'venture_search') {
            $jobs = $this->fetch_venture_search_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'mubadala_takafo') {
            $jobs = $this->fetch_mubadala_takafo_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'eightfold') {
            $jobs = $this->fetch_eightfold_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'alvarez_marsal') {
            $jobs = $this->fetch_alvarez_marsal_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        if ($source_type === 'consider_board') {
            $jobs = $this->fetch_consider_board_jobs($source_key, $source, $max_jobs);
            if (!empty($jobs)) {
                set_transient($cache_key, $jobs, $this->cache_duration);
            }
            return array_slice($jobs, 0, $max_jobs);
        }

        // Fetch XML
        $response = wp_remote_get($source['url'], [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $xml_content = wp_remote_retrieve_body($response);
        if (empty($xml_content)) {
            return [];
        }

        // Parse XML
        $jobs = $this->parse_xml_feed($xml_content, $source_key, $source);

        if (!empty($jobs)) {
            set_transient($cache_key, $jobs, $this->cache_duration);
        }

        return array_slice($jobs, 0, $max_jobs);
    }

    /**
     * Fetch jobs from a configured source key so typed feeds use the correct parser.
     */
    public function fetch_from_source_key($source_key, $limit = 10)
    {
        return $this->fetch_jobs_from_source($source_key, $limit);
    }

    private function enrich_jobs_with_source_meta(array &$jobs, $source_key, array $source_info)
    {
        $is_recruiter = ($source_info['source_type'] ?? '') === 'recruiter';
        $display_name = $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key));

        foreach ($jobs as &$job) {
            if (!isset($job['source_key'])) {
                $job['source_key'] = $source_key;
            }

            if ($is_recruiter) {
                $job['via_recruiter'] = true;
                if (empty($job['recruiter_name'])) {
                    $job['recruiter_name'] = $display_name;
                }
                if (empty($job['company']) || $job['company'] === 'Confidential') {
                    $job['company'] = $display_name;
                }
            } else {
                if (!isset($job['via_recruiter'])) {
                    $job['via_recruiter'] = false;
                }
            }
        }
        unset($job);
    }

    private function get_allowed_locations_for_source(array $source_info)
    {
        $allowed_locations = array_filter(array_map('strval', (array) ($source_info['allowed_locations'] ?? [])));
        if (($source_info['source_type'] ?? '') === 'job_aggregator') {
            $allowed_locations = array_merge($allowed_locations, $this->get_default_job_aggregator_allowed_locations());
        }

        return array_values(array_unique(array_filter(array_map('trim', $allowed_locations))));
    }

    private function get_default_job_aggregator_allowed_locations()
    {
        return [
            'London',
            'United Kingdom',
            'Dubai',
            'Abu Dhabi',
            'Riyadh',
            'Singapore',
            'Doha',
            'Qatar',
            'United Arab Emirates',
            'UAE',
            'Saudi Arabia',
            'KSA',
            'Jeddah',
            'Dammam',
            'Khobar',
            'Dhahran',
            'Sharjah',
            'Ajman',
            'Ras Al Khaimah',
            'Fujairah',
            'Umm Al Quwain',
        ];
    }

    /**
     * Fetch jobs from Pinpoint ATS API
     */
    private function fetch_pinpoint_jobs($source_key, $source_info, $max_jobs = 50)
    {
        $jobs = [];

        $response = wp_remote_get($source_info['url'], [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Pinpoint returns data in a 'data' key
        $job_list = isset($data['data']) ? $data['data'] : $data;

        if (!is_array($job_list)) {
            return [];
        }

        foreach ($job_list as $job) {
            if (count($jobs) >= $max_jobs) break;

            // Get location name from nested object
            $location = '';
            if (isset($job['location']['name'])) {
                $location = $job['location']['name'];
            } elseif (isset($job['location']['city'])) {
                $location = $job['location']['city'];
            }

            $department = '';
            if (is_array($job['department'] ?? null)) {
                $department = (string) ($job['department']['name'] ?? $job['department']['title'] ?? '');
            } else {
                $department = (string) ($job['department'] ?? '');
            }

            if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $department_keywords = array_filter(array_map('strtolower', (array) ($source_info['department_keywords'] ?? [])));
            if (!empty($department_keywords)) {
                $department_haystack = strtolower(trim($department . ' ' . ($job['team']['name'] ?? '') . ' ' . ($job['job_title'] ?? $job['title'] ?? '')));
                $matches_department = false;
                foreach ($department_keywords as $department_keyword) {
                    if ($department_keyword !== '' && strpos($department_haystack, $department_keyword) !== false) {
                        $matches_department = true;
                        break;
                    }
                }
                if (!$matches_department) {
                    continue;
                }
            }

            $job_record = [
                'id' => 'pinpoint_' . $source_key . '_' . $job['id'],
                'title' => $job['job_title'] ?? $job['title'] ?? '',
                'company' => $source_info['company_name'] ?? ($source_info['name'] ?? ''),
                'location' => $location,
                'description' => strip_tags($job['description'] ?? ''),
                'url' => $job['apply_url'] ?? $job['url'] ?? '',
                'posted_date' => date('Y-m-d', strtotime($job['created_at'] ?? $job['posted_at'] ?? 'now')),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'category' => $source_info['category'] ?? '',
                'department' => $department,
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'source_platform' => $source_info['source_platform'] ?? 'Pinpoint',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'deadline' => $job['deadline_at'] ?? null,
                'via_recruiter' => ($source_info['source_type'] ?? 'recruiter') === 'recruiter',
            ];

            if (!empty($job_record['via_recruiter'])) {
                $job_record['recruiter_name'] = $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key));
            }

            $jobs[] = $job_record;
        }

        if (!empty($jobs)) {
            $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        }

        return $jobs;
    }

    /**
     * Fetch jobs from Greenhouse.io API
     */
    private function fetch_greenhouse_jobs($source_key, $source_info, $max_jobs = 50)
    {
        $jobs = [];

        $response = wp_remote_get($source_info['url'], [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['jobs'])) {
            return [];
        }

        foreach ($data['jobs'] as $job) {
            if (count($jobs) >= $max_jobs) break;
            if (!$this->greenhouse_job_matches_allowed_locations($job, $source_info)) {
                continue;
            }

            $job_record = [
                'id' => 'greenhouse_' . $source_key . '_' . $job['id'],
                'title' => $job['title'] ?? '',
                'company' => $source_info['company_name'] ?? ($source_info['name'] ?? ''),
                'location' => $job['location']['name'] ?? '',
                'description' => strip_tags(html_entity_decode(html_entity_decode($job['content'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8')),
                'url' => $job['absolute_url'] ?? '',
                'posted_date' => date('Y-m-d', strtotime($job['published_at'] ?? ($job['first_published'] ?? ($job['updated_at'] ?? 'now')))),
                'published_at' => $job['published_at'] ?? '',
                'first_published' => $job['first_published'] ?? '',
                'updated_at' => $job['updated_at'] ?? '',
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'category' => $source_info['category'] ?? '',
                'source_type' => $source_info['source_type'] ?? 'company',
                'source_platform' => $source_info['source_platform'] ?? 'Greenhouse',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'departments' => isset($job['departments']) ? array_column($job['departments'], 'name') : [],
                'offices' => isset($job['offices']) ? array_column($job['offices'], 'name') : [],
                'via_recruiter' => ($source_info['source_type'] ?? 'recruiter') === 'recruiter',
            ];

            if (!empty($job_record['via_recruiter'])) {
                $job_record['recruiter_name'] = $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key));
            }

            $jobs[] = $job_record;
        }

        if (!empty($jobs)) {
            $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        }

        return $jobs;
    }

    private function greenhouse_job_matches_allowed_locations(array $job, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        $location_parts = [];
        if (isset($job['location']['name'])) {
            $location_parts[] = (string) $job['location']['name'];
        } elseif (isset($job['location']) && is_string($job['location'])) {
            $location_parts[] = $job['location'];
        }

        if (!empty($job['offices']) && is_array($job['offices'])) {
            foreach ($job['offices'] as $office) {
                if (is_array($office) && !empty($office['name'])) {
                    $location_parts[] = (string) $office['name'];
                }
            }
        }

        $location_text = strtolower(implode(' ', $location_parts));
        foreach ($allowed_locations as $allowed_location) {
            $needle = strtolower(trim((string) $allowed_location));
            if ($needle !== '' && strpos($location_text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch jobs from Recruitee public offers APIs.
     */
    private function fetch_recruitee_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? '');
        if ($api_url === '') {
            $host = parse_url((string) ($source_info['url'] ?? ''), PHP_URL_HOST);
            if ($host) {
                $api_url = 'https://' . $host . '/api/offers';
            }
        }

        if ($api_url === '') {
            return [];
        }

        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $offers = is_array($data['offers'] ?? null) ? $data['offers'] : (is_array($data) ? $data : []);
        if (empty($offers)) {
            return [];
        }

        $jobs = [];
        foreach ($offers as $offer) {
            if (count($jobs) >= $max_jobs || !is_array($offer)) {
                break;
            }

            $translation = is_array($offer['translations']['en'] ?? null) ? $offer['translations']['en'] : [];
            $title = sanitize_text_field((string) (($offer['title'] ?? '') ?: ($translation['title'] ?? '')));
            $location = $this->extract_recruitee_location($offer);
            if ($title === '' || !$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $description = trim(implode(' ', array_filter([
                (string) ($translation['description'] ?? ($offer['description'] ?? '')),
                (string) ($offer['requirements'] ?? ''),
            ])));
            $clean_description = $this->clean_text(wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')));
            $url = esc_url_raw((string) (($offer['careers_url'] ?? '') ?: ($offer['careers_apply_url'] ?? '')));
            $external_id = sanitize_key((string) (($offer['id'] ?? '') ?: ($offer['slug'] ?? '') ?: md5($url . $title)));

            $jobs[] = [
                'id' => 'recruitee_' . $source_key . '_' . $external_id,
                'external_id' => $external_id,
                'title' => $title,
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => $location,
                'description' => $clean_description,
                'url' => $url,
                'posted_date' => $this->normalize_rss_date((string) (($offer['published_at'] ?? '') ?: ($offer['created_at'] ?? '') ?: ($offer['updated_at'] ?? ''))),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Recruitee',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'department' => sanitize_text_field((string) ($offer['department'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => (($source_info['source_type'] ?? '') === 'recruiter'),
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function extract_recruitee_location(array $offer)
    {
        $locations = [];
        foreach ((array) ($offer['locations'] ?? []) as $location) {
            if (!is_array($location)) {
                continue;
            }
            $city = trim((string) ($location['city'] ?? ($location['translations']['en']['city'] ?? '')));
            $country = trim((string) ($location['country'] ?? ($location['translations']['en']['country'] ?? '')));
            $name = trim((string) ($location['name'] ?? ($location['translations']['en']['name'] ?? '')));
            $parts = array_filter(array_unique([$city ?: $name, $country]));
            if (!empty($parts)) {
                $locations[] = implode(', ', $parts);
            }
        }

        if (!empty($locations)) {
            return sanitize_text_field(implode(' / ', array_unique($locations)));
        }

        return sanitize_text_field((string) ($offer['location'] ?? ''));
    }

    /**
     * Fetch curated job listing pages where the listing HTML exposes direct detail links.
     */
    private function fetch_curated_listing_page_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = (string) ($source_info['url'] ?? '');
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if ($html === '' || !preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }

        $include_pattern = (string) ($source_info['job_url_pattern'] ?? '');
        $exclude_pattern = (string) ($source_info['exclude_url_pattern'] ?? '');
        $seen = [];
        $jobs = [];

        foreach ($matches[1] as $href) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $href = html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8');
            if ($href === '' || strpos($href, '#') === 0 || strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0) {
                continue;
            }

            $path = (string) parse_url($href, PHP_URL_PATH);
            if ($path === '') {
                $path = (string) parse_url($this->absolutize_url($href, $url), PHP_URL_PATH);
            }
            if ($include_pattern !== '' && !preg_match($include_pattern, $path)) {
                continue;
            }
            if ($exclude_pattern !== '' && preg_match($exclude_pattern, $path)) {
                continue;
            }

            $job_url = $this->absolutize_url($href, $url);
            $job_url = preg_replace('/\/apply\/?$/i', '', (string) $job_url);
            if ($job_url === '' || isset($seen[$job_url])) {
                continue;
            }
            $seen[$job_url] = true;

            $job = $this->extract_job_from_url_enhanced($job_url, $source_key, $source_info);
            $job['url'] = esc_url_raw($job_url);
            $job['company'] = sanitize_text_field((string) ($source_info['company_name'] ?? ($job['company'] ?? $source_info['name'] ?? '')));
            $job['source'] = $source_key;
            $job['source_key'] = $source_key;
            $job['source_name'] = $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key));
            $job['source_platform'] = $source_info['source_platform'] ?? 'Website';
            $job['source_type'] = $source_info['source_type'] ?? 'job_aggregator';
            $job['category'] = $source_info['category'] ?? 'Job aggregators';
            $job['company_logo'] = esc_url_raw((string) ($source_info['company_logo'] ?? ($job['company_logo'] ?? '')));
            $job['via_recruiter'] = (($source_info['source_type'] ?? '') === 'recruiter');

            $location_text = implode(' ', [
                (string) ($job['location'] ?? ''),
                (string) ($job['title'] ?? ''),
                (string) ($job['description'] ?? ''),
                $job_url,
            ]);
            if (empty($job['title']) || !$this->text_matches_allowed_locations($location_text, $source_info)) {
                continue;
            }

            $jobs[] = $job;
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function text_matches_allowed_locations($text, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        $haystack = strtolower((string) $text);
        foreach ($allowed_locations as $allowed_location) {
            $needle = strtolower(trim((string) $allowed_location));
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch jobs from Greenhouse API by URL (for admin interface)
     */
    private function fetch_greenhouse_jobs_by_url($url, $max_jobs = 50)
    {
        $jobs = [];

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['jobs']) || empty($data['jobs'])) {
            return [];
        }

        // Extract company name from URL (e.g., bluecrestcapitalmanagement -> BlueCrest Capital Management)
        preg_match('/boards\/([^\/]+)\/jobs/', $url, $matches);
        $company_slug = $matches[1] ?? 'Unknown Company';
        $company_name = $this->format_company_name($company_slug);

        foreach ($data['jobs'] as $job) {
            if (count($jobs) >= $max_jobs) break;

            $job_record = [
                'id' => 'greenhouse_' . $company_slug . '_' . $job['id'],
                'title' => $job['title'] ?? '',
                'company' => $company_name,
                'location' => $job['location']['name'] ?? '',
                'description' => strip_tags(html_entity_decode(html_entity_decode($job['content'] ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8')),
                'url' => $job['absolute_url'] ?? '',
                'posted_date' => date('Y-m-d', strtotime($job['updated_at'] ?? 'now')),
                'source' => $company_slug,
                'source_key' => $company_slug,
                'source_name' => $company_name,
                'category' => 'Finance',
                'departments' => isset($job['departments']) ? array_column($job['departments'], 'name') : [],
                'offices' => isset($job['offices']) ? array_column($job['offices'], 'name') : [],
                'via_recruiter' => false, // Greenhouse feeds are typically company feeds
            ];

            $jobs[] = $job_record;
        }

        if (!empty($jobs)) {
            $source_info = [
                'name' => $company_name,
                'source_type' => 'company',
            ];
            $this->enrich_jobs_with_source_meta($jobs, $company_slug, $source_info);
        }

        return $jobs;
    }

    /**
     * Fetch jobs from Jobs by Workable search pages.
     *
     * Workable renders the first result page into window.jobBoard.initialState,
     * which avoids brittle HTML card scraping and keeps company/logo/date fields.
     */
    private function fetch_workable_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $payloads = $this->fetch_workable_api_payloads($url, $max_jobs);

        if (empty($payloads)) {
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                return [];
            }

            $html = (string) wp_remote_retrieve_body($response);
            $jobs_payload = $this->extract_workable_jobs_payload($html);
            if (empty($jobs_payload['jobs']) || !is_array($jobs_payload['jobs'])) {
                return [];
            }

            $payloads[] = $jobs_payload;
        }

        $jobs = [];
        $seen_ids = [];
        foreach ($payloads as $jobs_payload) {
            foreach ($jobs_payload['jobs'] as $job) {
                if (count($jobs) >= $max_jobs || !is_array($job)) {
                    break 2;
                }

                $job_id = (string) ($job['id'] ?? md5((string) ($job['url'] ?? '')));
                if (isset($seen_ids[$job_id])) {
                    continue;
                }
                $seen_ids[$job_id] = true;

                $company = is_array($job['company'] ?? null) ? $job['company'] : [];
                $location = is_array($job['location'] ?? null) ? $job['location'] : [];
                $locations = is_array($job['locations'] ?? null) ? $job['locations'] : [];
                $description = trim(implode("\n\n", array_filter([
                    wp_strip_all_tags((string) ($job['description'] ?? '')),
                    wp_strip_all_tags((string) ($job['requirementsSection'] ?? '')),
                    wp_strip_all_tags((string) ($job['benefitsSection'] ?? '')),
                ])));

                $location_label = !empty($locations[0]) ? (string) $locations[0] : trim(implode(', ', array_filter([
                    $location['city'] ?? '',
                    $location['subregion'] ?? '',
                    $location['countryName'] ?? '',
                ])));

                $jobs[] = [
                    'id' => 'workable_' . $source_key . '_' . sanitize_key($job_id),
                    'title' => sanitize_text_field((string) ($job['title'] ?? '')),
                    'company' => sanitize_text_field((string) ($company['title'] ?? ($source_info['name'] ?? 'Workable'))),
                    'location' => sanitize_text_field($location_label),
                    'description' => $description,
                    'url' => esc_url_raw((string) ($job['url'] ?? '')),
                    'posted_date' => $this->normalize_workable_date($job['created'] ?? ($job['updated'] ?? '')),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? 'Workable',
                    'source_platform' => 'Workable',
                    'source_type' => 'job_aggregator',
                    'category' => $source_info['category'] ?? 'Job aggregators',
                    'department' => sanitize_text_field((string) ($job['department'] ?? '')),
                    'employment_type' => sanitize_text_field((string) ($job['employmentType'] ?? '')),
                    'workplace' => sanitize_text_field((string) ($job['workplace'] ?? '')),
                    'company_logo' => esc_url_raw((string) ($company['image'] ?? ($company['socialSharingImage'] ?? ''))),
                    'company_url' => esc_url_raw((string) ($company['url'] ?? ($company['website'] ?? ''))),
                    'location_city' => sanitize_text_field((string) ($location['city'] ?? '')),
                    'location_country' => sanitize_text_field((string) ($location['countryName'] ?? '')),
                    'via_recruiter' => false,
                ];
            }
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function fetch_workable_api_payloads($search_url, $max_jobs)
    {
        $payloads = [];
        $page_token = '';
        $fetched = 0;
        $max_pages = max(1, min(10, (int) ceil(max(1, $max_jobs) / 20) + 1));

        for ($page = 0; $page < $max_pages && $fetched < $max_jobs; $page++) {
            $api_url = $this->build_workable_api_url($search_url, $page_token);
            if ($api_url === '') {
                break;
            }

            $response = wp_remote_get($api_url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            if (empty($payload['jobs']) || !is_array($payload['jobs'])) {
                break;
            }

            $payloads[] = $payload;
            $fetched += count($payload['jobs']);
            $page_token = (string) ($payload['nextPageToken'] ?? '');
            if ($page_token === '') {
                break;
            }
        }

        return $payloads;
    }

    private function build_workable_api_url($url, $page_token = '')
    {
        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        unset($query['pageToken']);
        if ($page_token !== '') {
            $query['pageToken'] = $page_token;
        }

        return esc_url_raw($scheme . '://' . $parts['host'] . '/api/v1/jobs' . (!empty($query) ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''));
    }

    private function extract_workable_jobs_payload($html)
    {
        $marker = '"api/v1/jobs":{"status":';
        $marker_pos = strpos($html, $marker);
        if ($marker_pos === false) {
            return [];
        }

        $data_marker = '"data":';
        $data_pos = strpos($html, $data_marker, $marker_pos);
        if ($data_pos === false) {
            return [];
        }

        $json_start = strpos($html, '{', $data_pos + strlen($data_marker));
        if ($json_start === false) {
            return [];
        }

        $json = $this->extract_balanced_json_object($html, $json_start);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extract_balanced_json_object($content, $start)
    {
        $length = strlen((string) $content);
        $depth = 0;
        $in_string = false;
        $escaped = false;

        for ($i = (int) $start; $i < $length; $i++) {
            $char = $content[$i];

            if ($in_string) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $in_string = false;
                }
                continue;
            }

            if ($char === '"') {
                $in_string = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, (int) $start, $i - (int) $start + 1);
                }
            }
        }

        return '';
    }

    private function normalize_workable_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function fetch_workable_board_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $jobs_md_url = (string) ($source_info['jobs_md_url'] ?? $this->build_workable_board_jobs_md_url($source_info['url'] ?? ''));
        if ($jobs_md_url === '') {
            return [];
        }

        $response = wp_remote_get($jobs_md_url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/markdown,text/plain,*/*',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $markdown = (string) wp_remote_retrieve_body($response);
        return $this->parse_workable_board_jobs_markdown($markdown, $source_key, $source_info, $max_jobs);
    }

    private function build_workable_board_jobs_md_url($url)
    {
        $parts = wp_parse_url((string) $url);
        if (empty($parts['host'])) {
            return '';
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($parts['host'] === 'jobs.adia.ae') {
            return 'https://apply.workable.com/adia/jobs.md';
        }

        if ($parts['host'] === 'apply.workable.com') {
            $segments = array_values(array_filter(explode('/', $path)));
            if (!empty($segments[0])) {
                return 'https://apply.workable.com/' . rawurlencode($segments[0]) . '/jobs.md';
            }
        }

        return '';
    }

    private function parse_workable_board_jobs_markdown($markdown, $source_key, array $source_info, $max_jobs = 50)
    {
        $jobs = [];
        $seen = [];
        $lines = preg_split('/\r\n|\r|\n/', (string) $markdown);

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line[0] !== '|' || stripos($line, '| Title | Department | Location |') === 0 || strpos($line, '|-------') === 0) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (count($cells) < 7) {
                continue;
            }

            [$title, $department, $location, $type, $salary, $posted, $details] = array_pad($cells, 7, '');
            if ($title === '' || $title === 'Title') {
                continue;
            }

            $detail_url = $this->extract_markdown_link_url($details);
            $external_id = $detail_url !== '' ? basename((string) wp_parse_url($detail_url, PHP_URL_PATH), '.md') : md5($title . $location);
            if (isset($seen[$external_id])) {
                continue;
            }

            if (!$this->workable_board_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $seen[$external_id] = true;
            $detail = $detail_url !== '' ? $this->fetch_workable_board_detail($detail_url) : [];
            $description = $detail['description'] ?? '';
            $apply_url = $detail['apply_url'] ?? $detail_url;

            $jobs[] = [
                'id' => 'workable_board_' . $source_key . '_' . sanitize_key($external_id),
                'external_id' => $external_id,
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? 'Workable')),
                'location' => sanitize_text_field($location),
                'description' => wp_strip_all_tags((string) $description),
                'url' => esc_url_raw($apply_url ?: $detail_url),
                'posted_date' => $this->normalize_workable_date($posted),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Workable',
                'source_platform' => 'Workable',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'department' => sanitize_text_field($department !== '—' ? $department : ''),
                'employment_type' => sanitize_text_field($type !== '—' ? $type : ''),
                'salary' => sanitize_text_field($salary !== '—' ? $salary : ''),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'company_url' => esc_url_raw((string) ($source_info['company_url'] ?? '')),
                'location_city' => sanitize_text_field(strtok($location, ',') ?: ''),
                'location_country' => sanitize_text_field(str_contains($location, 'United Arab Emirates') ? 'United Arab Emirates' : ''),
                'via_recruiter' => false,
            ];

            if (count($jobs) >= $max_jobs) {
                break;
            }
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function extract_markdown_link_url($markdown)
    {
        if (preg_match('/\[[^\]]+\]\(([^)]+)\)/', (string) $markdown, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    private function fetch_workable_board_detail($detail_url)
    {
        $response = wp_remote_get((string) $detail_url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/markdown,text/plain,*/*',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $markdown = (string) wp_remote_retrieve_body($response);
        $apply_url = '';
        if (preg_match('/## Apply\s+\[Apply[^\]]*\]\(([^)]+)\)/is', $markdown, $match)) {
            $apply_url = trim($match[1]);
        } elseif (preg_match('/\[Apply[^\]]*\]\(([^)]+)\)/i', $markdown, $match)) {
            $apply_url = trim($match[1]);
        }

        $description = preg_replace('/^# .*?(\r\n|\r|\n)/', '', $markdown, 1);
        $description = preg_replace('/## Apply.*$/is', '', (string) $description);
        $description = preg_replace('/---\s*Powered by .*$/is', '', (string) $description);
        $description = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', (string) $description);
        $description = str_replace(['**', '###', '##', '>'], '', (string) $description);

        return [
            'description' => $this->clean_text($description),
            'apply_url' => esc_url_raw($apply_url),
        ];
    }

    private function workable_board_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch jobs from Bayt-powered hosted careers pages.
     */
    private function fetch_bayt_careers_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $payloads = $this->fetch_bayt_careers_payloads($source_info['url'] ?? '', $max_jobs);
        if (empty($payloads)) {
            return [];
        }

        $jobs = [];
        $seen_ids = [];
        foreach ($payloads as $payload) {
            foreach (($payload['jobs'] ?? []) as $job) {
                if (count($jobs) >= $max_jobs || !is_array($job)) {
                    break 2;
                }

                $job_id = (string) ($job['id'] ?? md5((string) ($job['url'] ?? '')));
                if ($job_id === '' || isset($seen_ids[$job_id])) {
                    continue;
                }
                $seen_ids[$job_id] = true;

                $relative_url = (string) ($job['url'] ?? '');
                $description = html_entity_decode(wp_strip_all_tags((string) ($job['desc'] ?? '')), ENT_QUOTES, 'UTF-8');
                $description = trim(preg_replace('/\s+/', ' ', str_replace(['middot;', 'nbsp;'], [' ', ' '], $description)));
                $logo = is_array($job['logo'] ?? null) ? $job['logo'] : [];
                $source = is_array($job['source'] ?? null) ? $job['source'] : [];
                $location = sanitize_text_field((string) ($job['loc'] ?? ($source_info['location'] ?? '')));

                if (!$this->bayt_careers_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $jobs[] = [
                    'id' => 'bayt_' . $source_key . '_' . sanitize_key($job_id),
                    'title' => sanitize_text_field((string) ($job['title'] ?? '')),
                    'company' => sanitize_text_field((string) (($job['companyName'] ?? '') ?: ($source_info['company_name'] ?? ($source_info['name'] ?? 'Bayt Careers')))),
                    'location' => $location,
                    'description' => $description,
                    'url' => esc_url_raw($this->absolutize_url($relative_url, $source_info['url'] ?? '')),
                    'posted_date' => $this->normalize_bayt_date($job['crtDate'] ?? ''),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? 'Bayt Careers',
                    'source_platform' => $source_info['source_platform'] ?? 'Bayt Careers',
                    'source_type' => 'job_aggregator',
                    'category' => $source_info['category'] ?? 'Job aggregators',
                    'department' => sanitize_text_field((string) ($job['division'] ?? '')),
                    'employment_type' => sanitize_text_field((string) ($job['empType'] ?? '')),
                    'role' => sanitize_text_field((string) ($job['role'] ?? '')),
                    'seniority' => sanitize_text_field((string) ($job['crrLvl'] ?? '')),
                    'experience' => sanitize_text_field((string) ($job['exp'] ?? '')),
                    'reference_id' => sanitize_text_field((string) ($job['refId'] ?? '')),
                    'company_logo' => esc_url_raw((string) (($logo['value'] ?? '') ?: ($source['value'] ?? '') ?: ($source_info['company_logo'] ?? ''))),
                    'via_recruiter' => false,
                ];
            }
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function bayt_careers_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        $location = strtolower((string) $location);
        if ($location === '') {
            return false;
        }

        foreach ($allowed_locations as $allowed_location) {
            if ($allowed_location !== '' && strpos($location, strtolower((string) $allowed_location)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function fetch_bayt_careers_payloads($search_url, $max_jobs)
    {
        $payloads = [];
        $total_jobs = null;
        $max_pages = max(1, min(20, (int) ceil(max(1, $max_jobs) / 10) + 1));

        for ($page = 1; $page <= $max_pages; $page++) {
            $api_url = $this->build_bayt_careers_api_url($search_url, $page);
            if ($api_url === '') {
                break;
            }

            $response = wp_remote_get($api_url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            if (!is_array($payload)) {
                break;
            }

            $jobs = $payload['jobs'] ?? [];
            if (!is_array($jobs) || empty($jobs)) {
                break;
            }

            $payloads[] = $payload;
            $total_jobs = isset($payload['totalJobs']) ? (int) $payload['totalJobs'] : $total_jobs;
            $fetched = array_sum(array_map(static function ($page_payload) {
                return is_array($page_payload['jobs'] ?? null) ? count($page_payload['jobs']) : 0;
            }, $payloads));

            if (($total_jobs !== null && $fetched >= $total_jobs) || $fetched >= $max_jobs) {
                break;
            }
        }

        return $payloads;
    }

    private function build_bayt_careers_api_url($url, $page = 1)
    {
        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['page'] = max(1, (int) $page);

        return esc_url_raw($scheme . '://' . $parts['host'] . '/app/control/byt_job_search_manager?' . http_build_query([
            'action' => 1,
            'token' => '',
            'query' => http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            'body' => 'job-search-results',
            'lan' => 'en',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    private function normalize_bayt_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function absolutize_url($url, $base_url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $parts = wp_parse_url($base_url);
        if (empty($parts['host'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        return $scheme . '://' . $parts['host'] . '/' . ltrim($url, '/');
    }

    /**
     * Fetch jobs from SAP SuccessFactors search result tables.
     */
    private function fetch_successfactors_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        if (!empty($source_info['use_unified_api']) || !empty($source_info['api_url'])) {
            $jobs = $this->fetch_successfactors_unified_jobs($source_key, $source_info, $max_jobs);
            if (!empty($jobs)) {
                $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
                return $jobs;
            }
        }

        $jobs = [];
        $seen_ids = [];
        $max_pages = max(1, min(10, (int) ceil(max(1, $max_jobs) / 25) + 1));

        for ($page = 0; $page < $max_pages && count($jobs) < $max_jobs; $page++) {
            $page_url = $this->build_successfactors_page_url($url, $page);
            $response = wp_remote_get($page_url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $page_jobs = $this->parse_successfactors_search_html((string) wp_remote_retrieve_body($response), $source_key, $source_info, $url);
            if (empty($page_jobs)) {
                break;
            }

            foreach ($page_jobs as $job) {
                if (count($jobs) >= $max_jobs) {
                    break 2;
                }

                $job_id = $job['external_id'] ?? $job['id'] ?? md5($job['url'] ?? '');
                if (isset($seen_ids[$job_id])) {
                    continue;
                }
                $seen_ids[$job_id] = true;
                $jobs[] = $job;
            }
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function fetch_successfactors_unified_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = esc_url_raw((string) ($source_info['api_url'] ?? ''));
        if ($api_url === '') {
            $parts = wp_parse_url((string) ($source_info['url'] ?? ''));
            if (empty($parts['host'])) {
                return [];
            }
            $api_url = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/services/recruiting/v1/jobs';
        }

        $locale = sanitize_text_field((string) ($source_info['locale'] ?? 'en_GB'));
        $facet_filters = is_array($source_info['facet_filters'] ?? null) ? $source_info['facet_filters'] : [];
        if (empty($facet_filters)) {
            $parts = wp_parse_url((string) ($source_info['url'] ?? ''));
            if (!empty($parts['query'])) {
                $query = [];
                parse_str($parts['query'], $query);
                if (!empty($query['facetFilters'])) {
                    $decoded = json_decode((string) $query['facetFilters'], true);
                    if (is_array($decoded)) {
                        $facet_filters = $decoded;
                    }
                }
            }
        }

        $jobs = [];
        $seen_ids = [];
        $max_pages = max(1, min(10, (int) ceil(max(1, $max_jobs) / 25)));

        for ($page = 0; $page < $max_pages && count($jobs) < $max_jobs; $page++) {
            $payload = [
                'keywords' => '',
                'locale' => $locale,
                'location' => '',
                'pageNumber' => $page,
                'sortBy' => 'recent',
            ];
            if (!empty($facet_filters)) {
                $payload['facetFilters'] = $facet_filters;
            }

            $response = wp_remote_post($api_url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                    'Referer' => (string) ($source_info['url'] ?? ''),
                ],
                'body' => wp_json_encode($payload),
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $data = json_decode((string) wp_remote_retrieve_body($response), true);
            $items = is_array($data['jobSearchResult'] ?? null) ? $data['jobSearchResult'] : [];
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                if (count($jobs) >= $max_jobs) {
                    break 2;
                }

                $response_data = is_array($item['response'] ?? null) ? $item['response'] : [];
                $external_id = sanitize_text_field((string) ($response_data['id'] ?? ''));
                if ($external_id === '' || isset($seen_ids[$external_id])) {
                    continue;
                }
                $seen_ids[$external_id] = true;

                $title = sanitize_text_field((string) ($response_data['unifiedStandardTitle'] ?? ($response_data['title'] ?? '')));
                $url_title = sanitize_text_field((string) ($response_data['unifiedUrlTitle'] ?? ($response_data['urlTitle'] ?? '')));
                $job_url = $this->build_successfactors_unified_job_url($source_info['url'] ?? '', $title, $url_title, $external_id, $locale);
                $details = $this->fetch_successfactors_job_detail($job_url);
                $location = $this->clean_text(implode(', ', array_filter((array) ($response_data['jobLocationShort'] ?? []))));
                if ($location === '') {
                    $location = $this->clean_text(implode(', ', array_filter((array) ($response_data['jobLocationCountry'] ?? []))));
                }

                if (!$this->successfactors_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $category = $this->clean_text(implode(', ', array_filter((array) ($response_data['mfield1'] ?? []))));
                $company = !empty($source_info['force_company_name'])
                    ? ($source_info['company_name'] ?? ($source_info['name'] ?? ''))
                    : ($source_info['company_name'] ?? ($source_info['name'] ?? ''));

                $jobs[] = [
                    'id' => 'successfactors_' . $source_key . '_' . sanitize_key($external_id),
                    'external_id' => $external_id,
                    'title' => $title,
                    'company' => sanitize_text_field((string) $company),
                    'location' => sanitize_text_field($details['location'] ?: $location),
                    'description' => $details['description'] !== '' ? $details['description'] : $title,
                    'url' => esc_url_raw($job_url),
                    'posted_date' => $this->normalize_successfactors_date($details['posted_date'] ?: ($response_data['unifiedStandardStart'] ?? '')),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? 'SAP SuccessFactors',
                    'source_platform' => $source_info['source_platform'] ?? 'SAP SuccessFactors',
                    'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                    'category' => $category ?: ($source_info['category'] ?? 'Job aggregators'),
                    'department' => $category,
                    'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                    'currency' => sanitize_text_field(implode(', ', array_filter((array) ($response_data['currency'] ?? [])))),
                    'deadline' => sanitize_text_field((string) ($response_data['unifiedStandardEnd'] ?? '')),
                    'via_recruiter' => false,
                ];
            }
        }

        return $jobs;
    }

    private function build_successfactors_unified_job_url($source_url, $title, $url_title, $external_id, $locale)
    {
        $parts = wp_parse_url((string) $source_url);
        if (empty($parts['host']) || $external_id === '') {
            return '';
        }

        $slug = $url_title !== '' ? $url_title : rawurlencode(str_replace(' ', '-', (string) $title));
        $slug = trim($slug, '/');
        $scheme = $parts['scheme'] ?? 'https';

        return esc_url_raw($scheme . '://' . $parts['host'] . '/job/' . $slug . '/' . rawurlencode((string) $external_id . '-' . (string) $locale) . '/');
    }

    private function successfactors_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        $haystack = strtolower((string) $location);
        foreach ($allowed_locations as $allowed_location) {
            if (strpos($haystack, strtolower((string) $allowed_location)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function build_successfactors_page_url($url, $page = 0)
    {
        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '/search/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if ($page > 0) {
            $query['startrow'] = (int) $page * 25;
        } else {
            unset($query['startrow']);
        }

        return esc_url_raw($scheme . '://' . $parts['host'] . $path . (!empty($query) ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''));
    }

    private function parse_successfactors_search_html($html, $source_key, array $source_info, $base_url)
    {
        if (trim((string) $html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[@id="searchresults"]//tbody//tr[contains(concat(" ", normalize-space(@class), " "), " data-row ") or .//a[contains(concat(" ", normalize-space(@class), " "), " jobTitle-link ")]]');
        if (!$rows || $rows->length === 0) {
            return [];
        }

        $jobs = [];
        foreach ($rows as $row) {
            $link = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " jobTitle-link ")]', $row)->item(0);
            if (!$link) {
                continue;
            }

            $relative_url = html_entity_decode((string) $link->getAttribute('href'), ENT_QUOTES, 'UTF-8');
            $job_url = $this->absolutize_url($relative_url, $base_url);
            $external_id = '';
            if (preg_match('/\/job\/[^\/]+\/(\d+)\//', $relative_url, $matches)) {
                $external_id = $matches[1];
            }

            $title = $this->clean_text($link->textContent);
            $location = $this->clean_text($this->xpath_text($xpath, './/td[contains(concat(" ", normalize-space(@class), " "), " colLocation ")]//span[contains(concat(" ", normalize-space(@class), " "), " jobLocation ")]', $row));
            $company = $this->clean_text($this->xpath_text($xpath, './/td[contains(concat(" ", normalize-space(@class), " "), " colFacility ")]//span[contains(concat(" ", normalize-space(@class), " "), " jobFacility ")]', $row));
            $company_name = !empty($source_info['force_company_name'])
                ? ($source_info['company_name'] ?? ($source_info['name'] ?? ''))
                : ($company ?: ($source_info['company_name'] ?? ($source_info['name'] ?? '')));
            $posted_date_raw = $this->clean_text($this->xpath_text($xpath, './/td[contains(concat(" ", normalize-space(@class), " "), " colDate ")]//span[contains(concat(" ", normalize-space(@class), " "), " jobDate ")]', $row));
            $details = $this->fetch_successfactors_job_detail($job_url);

            $jobs[] = [
                'id' => 'successfactors_' . $source_key . '_' . sanitize_key($external_id ?: md5($job_url)),
                'external_id' => $external_id,
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field($company_name),
                'location' => sanitize_text_field($details['location'] ?: $location),
                'description' => $details['description'],
                'url' => esc_url_raw($job_url),
                'posted_date' => $this->normalize_successfactors_date($details['posted_date'] ?: $posted_date_raw),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'SAP SuccessFactors',
                'source_platform' => $source_info['source_platform'] ?? 'SAP SuccessFactors',
                'source_type' => 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'department' => sanitize_text_field($company),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function fetch_successfactors_job_detail($url)
    {
        $details = [
            'description' => '',
            'location' => '',
            'posted_date' => '',
        ];

        if ($url === '') {
            return $details;
        }

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return $details;
        }

        $html = (string) wp_remote_retrieve_body($response);
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $description_node = $xpath->query('//*[@itemprop="description"]')->item(0);
        if ($description_node) {
            $details['description'] = $this->clean_text($description_node->textContent);
        }
        if ($details['description'] === '') {
            $details['description'] = $this->clean_text($this->xpath_text($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " jobdescription ")]'));
        }

        $details['location'] = $this->clean_text($this->xpath_text($xpath, '//*[@id="job-location"]//*[contains(concat(" ", normalize-space(@class), " "), " jobGeoLocation ")]'));
        if ($details['location'] === '') {
            $details['location'] = $this->extract_successfactors_detail_label_value($xpath, 'Job Location');
        }

        $date_meta = $xpath->query('//meta[@itemprop="datePosted"]')->item(0);
        $details['posted_date'] = $date_meta ? (string) $date_meta->getAttribute('content') : '';
        if ($details['posted_date'] === '') {
            $details['posted_date'] = $this->extract_successfactors_detail_label_value($xpath, 'Posting Start Date');
        }

        return $details;
    }

    private function extract_successfactors_detail_label_value(DOMXPath $xpath, $label)
    {
        $nodes = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " joblayouttoken-label ") and contains(normalize-space(.), "' . $label . '")]');
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        $container = $nodes->item(0)->parentNode;
        if (!$container) {
            return '';
        }

        foreach ($xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " rtltextaligneligible ")]', $container) as $value_node) {
            $value = $this->clean_text($value_node->textContent);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function fetch_eightfold_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = (string) ($source_info['url'] ?? '');
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $postings = $this->extract_eightfold_job_postings((string) wp_remote_retrieve_body($response));
        if (empty($postings)) {
            return [];
        }

        $jobs = [];
        $seen = [];

        foreach ($postings as $posting) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $title = $this->clean_text($posting['title'] ?? '');
            $job_url = esc_url_raw((string) ($posting['url'] ?? $url));
            $external_id = $this->extract_eightfold_external_id($job_url, $posting);
            if ($title === '' || isset($seen[$external_id])) {
                continue;
            }

            $location = $this->normalize_eightfold_location($posting['jobLocation'] ?? []);
            if (!$this->eightfold_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $company = $this->clean_text($posting['hiringOrganization']['name'] ?? ($source_info['company_name'] ?? $source_info['name'] ?? ''));
            if ($company === '') {
                $company = $this->clean_text($source_info['company_name'] ?? $source_info['name'] ?? 'Eightfold');
            }

            $seen[$external_id] = true;
            $jobs[] = [
                'id' => 'eightfold_' . $source_key . '_' . sanitize_key($external_id),
                'external_id' => sanitize_text_field($external_id),
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field($company),
                'location' => sanitize_text_field($location),
                'description' => $this->clean_text(wp_strip_all_tags((string) ($posting['description'] ?? ''))),
                'url' => $job_url,
                'posted_date' => $this->normalize_eightfold_date($posting['datePosted'] ?? ''),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Eightfold',
                'source_platform' => $source_info['source_platform'] ?? 'Eightfold',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'department' => sanitize_text_field((string) ($posting['department'] ?? '')),
                'business_unit' => sanitize_text_field($this->clean_text($posting['business_unit'] ?? '')),
                'employment_type' => sanitize_text_field((string) ($posting['employmentType'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? ($posting['hiringOrganization']['logo'] ?? ''))),
                'company_url' => esc_url_raw((string) ($posting['hiringOrganization']['sameAs'] ?? '')),
                'location_city' => sanitize_text_field(strtok($location, ',') ?: ''),
                'location_country' => sanitize_text_field(stripos($location, 'United Arab Emirates') !== false || stripos($location, 'Dubai') !== false ? 'United Arab Emirates' : ''),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function extract_eightfold_job_postings($html)
    {
        $postings = [];
        if (trim((string) $html) === '') {
            return $postings;
        }

        if (preg_match('/<code[^>]+id=["\']smartApplyData["\'][^>]*>(.*?)<\/code>/is', (string) $html, $match)) {
            $decoded = json_decode(html_entity_decode(trim($match[1]), ENT_QUOTES, 'UTF-8'), true);
            if (is_array($decoded) && !empty($decoded['positions']) && is_array($decoded['positions'])) {
                foreach ($decoded['positions'] as $position) {
                    if (!is_array($position)) {
                        continue;
                    }

                    $postings[] = [
                        '@type' => 'JobPosting',
                        'title' => $position['posting_name'] ?? ($position['name'] ?? ''),
                        'description' => $position['job_description'] ?? '',
                        'datePosted' => !empty($position['t_create']) ? date('c', (int) $position['t_create']) : '',
                        'employmentType' => $position['jobType'] ?? '',
                        'url' => $position['canonicalPositionUrl'] ?? '',
                        'identifier' => [
                            'value' => $position['display_job_id'] ?? ($position['ats_job_id'] ?? ($position['id'] ?? '')),
                        ],
                        'hiringOrganization' => [
                            '@type' => 'Organization',
                            'name' => $position['custom_data']['brand'] ?? ($decoded['companyName'] ?? ''),
                            'sameAs' => $decoded['domain'] ?? '',
                        ],
                        'jobLocation' => $position['location'] ?? ($position['locations'][0] ?? ''),
                        'department' => $position['department'] ?? '',
                        'business_unit' => $position['business_unit'] ?? '',
                    ];
                }
            }
        }

        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', (string) $html, $matches)) {
            foreach ($matches[1] as $json) {
                $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES, 'UTF-8'), true);
                foreach ($this->flatten_job_posting_schema($decoded) as $posting) {
                    $postings[] = $posting;
                }
            }
        }

        return $postings;
    }

    private function flatten_job_posting_schema($schema)
    {
        if (!is_array($schema)) {
            return [];
        }

        $schema_type = $schema['@type'] ?? '';
        if ($schema_type === 'JobPosting' || (is_array($schema_type) && in_array('JobPosting', $schema_type, true))) {
            return [$schema];
        }

        $postings = [];
        foreach ($schema as $item) {
            if (is_array($item)) {
                $postings = array_merge($postings, $this->flatten_job_posting_schema($item));
            }
        }

        return $postings;
    }

    private function normalize_eightfold_location($location)
    {
        if (is_string($location)) {
            return $this->clean_text($location);
        }

        if (isset($location[0]) && is_array($location[0])) {
            $location = $location[0];
        }

        $address = is_array($location) ? ($location['address'] ?? []) : [];
        $city = $this->clean_text($address['addressLocality'] ?? '');
        $region = $this->clean_text($address['addressRegion'] ?? '');
        $country = $address['addressCountry']['name'] ?? ($address['addressCountry'] ?? '');
        $country = $this->normalize_eightfold_country($this->clean_text($country));

        return $this->clean_text(implode(', ', array_filter([$city, $region, $country])));
    }

    private function normalize_eightfold_country($country)
    {
        $country = trim((string) $country);
        if (strtoupper($country) === 'AE') {
            return 'United Arab Emirates';
        }

        return $country;
    }

    private function eightfold_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extract_eightfold_external_id($url, array $posting)
    {
        $parts = wp_parse_url((string) $url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (!empty($query['pid'])) {
            return (string) $query['pid'];
        }

        if (!empty($parts['path']) && preg_match('#/careers/job/([0-9]+)#', (string) $parts['path'], $match)) {
            return (string) $match[1];
        }

        if (!empty($posting['identifier']['value'])) {
            return (string) $posting['identifier']['value'];
        }

        return md5($url . ($posting['title'] ?? ''));
    }

    private function normalize_eightfold_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function xpath_text(DOMXPath $xpath, $query, ?DOMNode $context = null)
    {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        return $nodes->item(0)->textContent;
    }

    private function clean_text($text)
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8')));
    }

    private function fetch_icims_search_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = (string) ($source_info['url'] ?? '');
        if ($url === '') {
            return [];
        }

        if (strpos($url, 'in_iframe=') === false) {
            $url = add_query_arg('in_iframe', '1', $url);
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $cards = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iCIMS_JobCardItem ")]');
        if (!$cards || $cards->length === 0) {
            return [];
        }

        $jobs = [];
        $seen = [];
        foreach ($cards as $card) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $link_node = $xpath->query('.//a[contains(@href, "/jobs/")]', $card)->item(0);
            if (!$link_node instanceof DOMElement) {
                continue;
            }

            $job_url = $this->make_absolute_url($link_node->getAttribute('href'), $url);
            $title = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " title ")]//*[self::h2 or self::h3 or self::h4]', $card));
            if ($title === '') {
                $title = $this->clean_text($link_node->textContent);
            }
            $title = preg_replace('/^\s*Title\s+/i', '', (string) $title);
            $title = preg_replace('/^\s*\d+\s*-\s*/', '', (string) $title);

            if ($title === '' || $job_url === '') {
                continue;
            }

            $external_id = '';
            if (preg_match('#/jobs/(\d+)/#', $job_url, $matches)) {
                $external_id = $matches[1];
            }
            if ($external_id === '') {
                $external_id = md5($job_url);
            }
            if (isset($seen[$external_id])) {
                continue;
            }

            $location = $this->extract_icims_card_term($xpath, $card, 'Location');
            if ($location === '') {
                $location = (string) ($source_info['location'] ?? '');
            }
            if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $seen[$external_id] = true;
            $posted_date = $this->normalize_icims_date($this->extract_icims_card_posted_date($xpath, $card));
            $description = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " description ")]', $card));
            $category = $this->extract_icims_card_term($xpath, $card, 'Category');

            $jobs[] = [
                'id' => 'icims_' . $source_key . '_' . sanitize_key($external_id),
                'external_id' => sanitize_text_field($external_id),
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => sanitize_text_field($location),
                'description' => $description,
                'url' => esc_url_raw($job_url),
                'posted_date' => $posted_date,
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'iCIMS',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => sanitize_text_field($category ?: ($source_info['category'] ?? 'Job aggregators')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function extract_icims_card_term(DOMXPath $xpath, DOMNode $card, $label)
    {
        $terms = $xpath->query('.//dt', $card);
        if (!$terms) {
            return '';
        }

        foreach ($terms as $term) {
            $term_text = $this->clean_text($term->textContent);
            if (stripos($term_text, (string) $label) === false) {
                continue;
            }

            $value = $xpath->query('following-sibling::dd[1]', $term)->item(0);
            if ($value) {
                return $this->clean_text($value->textContent);
            }
        }

        return '';
    }

    private function extract_icims_card_posted_date(DOMXPath $xpath, DOMNode $card)
    {
        $nodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " posted-date ")]//*[@title] | .//*[contains(concat(" ", normalize-space(@class), " "), " header ")]//*[@title]', $card);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);
        return $node instanceof DOMElement ? $node->getAttribute('title') : $node->textContent;
    }

    private function normalize_icims_date($date)
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function fetch_nichehr_supabase_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? '');
        if ($api_url === '') {
            return [];
        }

        $api_url = add_query_arg('limit', max(1, (int) $max_jobs), $api_url);
        $anon_key = (string) ($source_info['anon_key'] ?? 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InN4Zm1yZXlkdG13d2RyZGZoYmphIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQ3NTgxNDMsImV4cCI6MjA4MDMzNDE0M30.D7g3-KgWPCVoVRpr0f9c8_6ccyzMqmBy5c2lbVXj5Rk');
        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'apikey' => $anon_key,
                'Authorization' => 'Bearer ' . $anon_key,
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $records = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($records)) {
            return [];
        }

        $jobs = [];
        foreach ($records as $record) {
            if (count($jobs) >= $max_jobs || !is_array($record)) {
                break;
            }

            $title = $this->clean_text($record['title'] ?? '');
            $location = $this->clean_text($record['location'] ?? '');
            $external_id = sanitize_key((string) ($record['id'] ?? ''));
            if ($title === '' || $external_id === '') {
                continue;
            }
            if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $description = $this->clean_text(wp_strip_all_tags((string) ($record['description'] ?? '')));
            $salary = $this->format_nichehr_salary($record);
            if ($salary !== '') {
                $description = trim($description . ' Salary: ' . $salary);
            }

            $jobs[] = [
                'id' => 'nichehr_' . $source_key . '_' . $external_id,
                'external_id' => $external_id,
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? 'NicheHR Global')),
                'location' => sanitize_text_field($location),
                'description' => $description,
                'url' => esc_url_raw(rtrim((string) ($source_info['url'] ?? ''), '/') . '/' . rawurlencode((string) ($record['id'] ?? ''))),
                'posted_date' => $this->normalize_iso_date($record['created_at'] ?? ''),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'NicheHR',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => sanitize_text_field((string) ($record['department'] ?? $source_info['category'] ?? 'Job aggregators')),
                'department' => sanitize_text_field((string) ($record['department'] ?? '')),
                'seniority' => sanitize_text_field((string) ($record['seniority'] ?? '')),
                'job_type' => sanitize_text_field((string) ($record['employment_type'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => true,
                'recruiter_name' => sanitize_text_field((string) ($source_info['company_name'] ?? 'NicheHR Global')),
            ];
        }

        return $jobs;
    }

    private function format_nichehr_salary(array $record)
    {
        $currency = trim((string) ($record['salary_currency'] ?? ''));
        $min = $record['salary_min'] ?? '';
        $max = $record['salary_max'] ?? '';
        if ($currency === '' || ($min === '' && $max === '')) {
            return '';
        }

        if ($min !== '' && $max !== '') {
            return $currency . ' ' . number_format((float) $min) . '-' . number_format((float) $max);
        }

        return $currency . ' ' . number_format((float) ($min !== '' ? $min : $max));
    }

    private function make_absolute_url($href, $base_url)
    {
        $href = trim((string) $href);
        if ($href === '' || preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = wp_parse_url($base_url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return $href;
        }

        if (strpos($href, '//') === 0) {
            return $parts['scheme'] . ':' . $href;
        }

        $root = $parts['scheme'] . '://' . $parts['host'];
        if (strpos($href, '/') === 0) {
            return $root . $href;
        }

        $path = isset($parts['path']) ? dirname($parts['path']) : '';
        return rtrim($root . '/' . trim($path, '/'), '/') . '/' . $href;
    }

    private function normalize_successfactors_date($date)
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }

        $timestamp = $this->parse_day_first_numeric_date($date);
        if (!$timestamp) {
            $timestamp = strtotime($date);
        }

        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    private function parse_day_first_numeric_date($date)
    {
        $date = trim((string) $date);
        if (!preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $date, $matches)) {
            return false;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];
        if ($year < 100) {
            $year += 2000;
        }

        $hour = isset($matches[4]) ? (int) $matches[4] : 0;
        $minute = isset($matches[5]) ? (int) $matches[5] : 0;
        $second = isset($matches[6]) ? (int) $matches[6] : 0;

        if (!checkdate($month, $day, $year)) {
            return false;
        }

        return gmmktime($hour, $minute, $second, $month, $day, $year);
    }

    /**
     * Fetch jobs from Phenom career pages with server-rendered phApp.ddo data.
     */
    private function fetch_phenom_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $jobs = [];
        $seen_ids = [];
        $page_size = 10;
        $max_pages = max(1, min(10, (int) ceil(max(1, $max_jobs) / $page_size) + 1));

        for ($page = 0; $page < $max_pages && count($jobs) < $max_jobs; $page++) {
            $page_url = $this->build_phenom_page_url($url, $page * $page_size);
            $response = wp_remote_get($page_url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $payload = $this->extract_phenom_ddo_payload((string) wp_remote_retrieve_body($response));
            $items = $payload['eagerLoadRefineSearch']['data']['jobs'] ?? [];
            if (empty($items) || !is_array($items)) {
                break;
            }

            $added_this_page = 0;
            foreach ($items as $item) {
                if (count($jobs) >= $max_jobs || !is_array($item)) {
                    break 2;
                }

                $external_id = (string) ($item['jobSeqNo'] ?? ($item['reqId'] ?? ($item['jobId'] ?? '')));
                $job_url = $this->build_phenom_job_url($item, $source_info);
                $dedupe_key = $external_id ?: md5($job_url);
                if (isset($seen_ids[$dedupe_key])) {
                    continue;
                }
                $seen_ids[$dedupe_key] = true;

                $description = $item['ml_job_parser']['descriptionTeaser_first200']
                    ?? $item['ml_job_parser']['descriptionTeaser_keyword']
                    ?? $item['descriptionTeaser']
                    ?? '';
                $location = $item['cityStateCountry'] ?? $item['address'] ?? $item['location'] ?? $item['city'] ?? '';
                if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $jobs[] = [
                    'id' => 'phenom_' . $source_key . '_' . sanitize_key($dedupe_key),
                    'external_id' => $external_id,
                    'title' => sanitize_text_field($item['title'] ?? ''),
                    'company' => sanitize_text_field($this->normalize_phenom_company($item['company'] ?? '', $source_info)),
                    'location' => sanitize_text_field($location),
                    'description' => wp_strip_all_tags(html_entity_decode((string) $description, ENT_QUOTES, 'UTF-8')),
                    'url' => esc_url_raw($job_url),
                    'posted_date' => $this->normalize_phenom_date($item['postedDate'] ?? ($item['dateCreated'] ?? '')),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                    'source_platform' => $source_info['source_platform'] ?? 'Phenom',
                    'source_type' => 'job_aggregator',
                    'category' => sanitize_text_field($item['category'] ?? ($source_info['category'] ?? 'Job aggregators')),
                    'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                    'job_type' => sanitize_text_field($item['jobType'] ?? ($item['type'] ?? '')),
                    'seniority' => '',
                    'skills' => !empty($item['ml_skills']) && is_array($item['ml_skills']) ? array_slice(array_map('sanitize_text_field', $item['ml_skills']), 0, 12) : [],
                    'via_recruiter' => false,
                ];
                $added_this_page++;
            }

            if ($added_this_page === 0) {
                break;
            }
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return $jobs;
    }

    private function build_phenom_page_url($url, $offset)
    {
        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '/global/en/search-results';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if ((int) $offset > 0) {
            $query['from'] = (int) $offset;
            $query['s'] = 1;
        } else {
            unset($query['from'], $query['s']);
        }

        return esc_url_raw($scheme . '://' . $parts['host'] . $path . (!empty($query) ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''));
    }

    private function extract_phenom_ddo_payload($html)
    {
        $marker = 'phApp.ddo = ';
        $marker_pos = strpos($html, $marker);
        if ($marker_pos === false) {
            return [];
        }

        $json_start = strpos($html, '{', $marker_pos + strlen($marker));
        if ($json_start === false) {
            return [];
        }

        $json = $this->extract_balanced_json_object($html, $json_start);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function build_phenom_job_url(array $item, array $source_info)
    {
        $base = $source_info['job_base_url'] ?? ($source_info['url'] ?? '');
        $job_seq_no = (string) ($item['jobSeqNo'] ?? '');
        if ($job_seq_no !== '') {
            return rtrim($base, '/') . '/' . rawurlencode($job_seq_no);
        }

        return $source_info['url'] ?? '';
    }

    private function normalize_phenom_company($company, array $source_info)
    {
        $company = trim((string) $company);
        if ($company === '' || preg_match('/^\d+$/', $company)) {
            return $source_info['company_name'] ?? ($source_info['name'] ?? '');
        }

        return $company;
    }

    private function normalize_phenom_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function fetch_talentbrew_search_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = (string) ($source_info['url'] ?? '');
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if ($html === '' || !preg_match_all('/<li\b[^>]*class="[^"]*section3__search-results-li[^"]*"[^>]*>(.*?)<\/li>/is', $html, $rows)) {
            return [];
        }

        $jobs = [];
        foreach ($rows[1] as $row_html) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            if (!preg_match('/<a\b[^>]*href="([^"]+)"[^>]*data-job-id="([^"]+)"[^>]*>(.*?)<\/a>/is', $row_html, $link_match)) {
                continue;
            }

            $job_url = html_entity_decode((string) $link_match[1], ENT_QUOTES, 'UTF-8');
            if (strpos($job_url, 'http') !== 0) {
                $job_url = $this->absolutize_url($job_url, $url);
            }

            $content_html = (string) $link_match[3];
            $title = '';
            if (preg_match('/<h2\b[^>]*class="[^"]*section3__job-title[^"]*"[^>]*>(.*?)<\/h2>/is', $content_html, $title_match)) {
                $title = $this->clean_text(wp_strip_all_tags(html_entity_decode((string) $title_match[1], ENT_QUOTES, 'UTF-8')));
            }

            $location = '';
            if (preg_match_all('/<span\b[^>]*class="[^"]*section3__job-info[^"]*"[^>]*>(.*?)<\/span>/is', $content_html, $location_matches)) {
                foreach ($location_matches[1] as $location_html) {
                    $candidate_location = $this->clean_text(wp_strip_all_tags(html_entity_decode((string) $location_html, ENT_QUOTES, 'UTF-8')));
                    if ($candidate_location !== '' && stripos($candidate_location, 'Location:') === false) {
                        $location = $candidate_location;
                        break;
                    }
                }
            }
            if ($location === '') {
                $location = (string) ($source_info['location'] ?? '');
            }

            if ($title === '' || !$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $external_id = sanitize_key((string) $link_match[2]);
            if ($external_id === '') {
                $external_id = sanitize_key(md5($job_url));
            }

            $jobs[] = [
                'id' => 'talentbrew_' . $source_key . '_' . $external_id,
                'external_id' => $external_id,
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => sanitize_text_field($location),
                'description' => $this->clean_text($title . "\n\n" . $location),
                'url' => esc_url_raw($job_url),
                'posted_date' => date('Y-m-d'),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'TalentBrew',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function fetch_smartrecruiters_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = rtrim((string) ($source_info['api_url'] ?? ''), '?&');
        if ($api_url === '') {
            $company_id = trim((string) ($source_info['company_id'] ?? ''), '/');
            if ($company_id === '') {
                return [];
            }
            $api_url = 'https://api.smartrecruiters.com/v1/companies/' . rawurlencode($company_id) . '/postings';
        }

        $jobs = [];
        $seen_ids = [];
        $limit = min(100, max(1, (int) $max_jobs));
        $max_pages = max(1, min(5, (int) ceil(max(1, $max_jobs) / $limit) + 1));

        for ($page = 0; $page < $max_pages && count($jobs) < $max_jobs; $page++) {
            $request_url = add_query_arg([
                'limit' => $limit,
                'offset' => $page * $limit,
            ], $api_url);

            $response = wp_remote_get($request_url, [
                'timeout' => 30,
                'redirection' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            $items = is_array($payload['content'] ?? null) ? $payload['content'] : [];
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                if (count($jobs) >= $max_jobs || !is_array($item)) {
                    break 2;
                }

                $external_id = (string) ($item['id'] ?? $item['uuid'] ?? '');
                if ($external_id === '' || isset($seen_ids[$external_id])) {
                    continue;
                }

                $location = $this->smartrecruiters_location_label($item['location'] ?? []);
                if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $seen_ids[$external_id] = true;
                $detail = $this->fetch_smartrecruiters_job_detail($api_url, $external_id);
                $detail_source = !empty($detail) ? $detail : $item;
                $description = $this->smartrecruiters_description($detail_source);
                $company = $detail_source['company']['name'] ?? $source_info['company_name'] ?? $source_info['name'] ?? '';
                $function = $detail_source['function']['label'] ?? '';
                $experience_level = $detail_source['experienceLevel']['label'] ?? '';

                $jobs[] = [
                    'id' => 'smartrecruiters_' . $source_key . '_' . sanitize_key($external_id),
                    'external_id' => $external_id,
                    'title' => sanitize_text_field((string) ($detail_source['name'] ?? $item['name'] ?? '')),
                    'company' => sanitize_text_field((string) $company),
                    'location' => sanitize_text_field($location),
                    'description' => $description,
                    'url' => esc_url_raw((string) ($detail_source['postingUrl'] ?? $item['postingUrl'] ?? $source_info['url'] ?? '')),
                    'posted_date' => $this->normalize_iso_date($detail_source['releasedDate'] ?? $item['releasedDate'] ?? ''),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                    'source_platform' => $source_info['source_platform'] ?? 'SmartRecruiters',
                    'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                    'category' => sanitize_text_field((string) ($function ?: ($source_info['category'] ?? 'Job aggregators'))),
                    'department' => sanitize_text_field((string) $function),
                    'seniority' => sanitize_text_field((string) $experience_level),
                    'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                    'via_recruiter' => false,
                ];
            }

            $total = (int) ($payload['totalFound'] ?? 0);
            if ($total > 0 && ($page + 1) * $limit >= $total) {
                break;
            }
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        return $jobs;
    }

    private function fetch_smartrecruiters_job_detail($api_url, $external_id)
    {
        $detail_url = rtrim((string) $api_url, '/') . '/' . rawurlencode((string) $external_id);
        $response = wp_remote_get($detail_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($payload) ? $payload : [];
    }

    private function smartrecruiters_location_label($location)
    {
        if (!is_array($location)) {
            return '';
        }

        if (!empty($location['fullLocation'])) {
            return (string) $location['fullLocation'];
        }

        return trim(implode(', ', array_filter([
            $location['city'] ?? '',
            $location['region'] ?? '',
            $this->normalize_smartrecruiters_country($location['country'] ?? ''),
        ])));
    }

    private function normalize_smartrecruiters_country($country)
    {
        $map = [
            'ae' => 'United Arab Emirates',
            'sa' => 'Saudi Arabia',
            'qa' => 'Qatar',
            'gb' => 'United Kingdom',
        ];

        $country = strtolower((string) $country);
        return $map[$country] ?? strtoupper($country);
    }

    private function smartrecruiters_description(array $job)
    {
        $sections = $job['jobAd']['sections'] ?? [];
        $parts = [];
        if (is_array($sections)) {
            foreach (['companyDescription', 'jobDescription', 'qualifications', 'additionalInformation'] as $section_key) {
                if (!empty($sections[$section_key]['text'])) {
                    $parts[] = wp_strip_all_tags(html_entity_decode((string) $sections[$section_key]['text'], ENT_QUOTES, 'UTF-8'));
                }
            }
        }

        if (empty($parts)) {
            $parts[] = (string) ($job['name'] ?? '');
        }

        return $this->clean_text(implode("\n\n", $parts));
    }

    private function fetch_lever_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? '');
        if ($api_url === '') {
            $company_id = trim((string) ($source_info['company_id'] ?? ''), '/');
            if ($company_id === '') {
                return [];
            }
            $api_url = 'https://api.lever.co/v0/postings/' . rawurlencode($company_id) . '?mode=json';
        }

        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $items = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($items)) {
            return [];
        }

        $jobs = [];
        foreach ($items as $item) {
            if (count($jobs) >= $max_jobs || !is_array($item)) {
                break;
            }

            $categories = is_array($item['categories'] ?? null) ? $item['categories'] : [];
            $location = $this->lever_location_label($categories);
            if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $external_id = (string) ($item['id'] ?? md5((string) ($item['hostedUrl'] ?? $item['text'] ?? '')));
            $description = $this->clean_text(wp_strip_all_tags(html_entity_decode((string) ($item['descriptionPlain'] ?? $item['description'] ?? ''), ENT_QUOTES, 'UTF-8')));

            $jobs[] = [
                'id' => 'lever_' . $source_key . '_' . sanitize_key($external_id),
                'external_id' => $external_id,
                'title' => sanitize_text_field((string) ($item['text'] ?? '')),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => sanitize_text_field($location),
                'description' => $description,
                'url' => esc_url_raw((string) ($item['applyUrl'] ?? $item['hostedUrl'] ?? $source_info['url'] ?? '')),
                'posted_date' => $this->normalize_millisecond_date($item['createdAt'] ?? ''),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Lever',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => sanitize_text_field((string) ($categories['team'] ?? $categories['department'] ?? $source_info['category'] ?? 'Job aggregators')),
                'department' => sanitize_text_field((string) ($categories['department'] ?? $categories['team'] ?? '')),
                'employment_type' => sanitize_text_field((string) ($categories['commitment'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        return $jobs;
    }

    private function lever_location_label(array $categories)
    {
        $locations = $categories['allLocations'] ?? [];
        if (is_array($locations) && !empty($locations)) {
            return implode(', ', array_filter(array_map('strval', $locations)));
        }

        return (string) ($categories['location'] ?? '');
    }

    private function fetch_ashby_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = (string) ($source_info['url'] ?? '');
        if ($url === '') {
            $company_id = trim((string) ($source_info['company_id'] ?? ''), '/');
            if ($company_id === '') {
                return [];
            }
            $url = 'https://jobs.ashbyhq.com/' . rawurlencode($company_id);
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if ($html === '' || !preg_match('/window\.__appData\s*=\s*(\{.*?\});/s', $html, $matches)) {
            return [];
        }

        $data = json_decode($matches[1], true);
        $postings = is_array($data['jobBoard']['jobPostings'] ?? null) ? $data['jobBoard']['jobPostings'] : [];
        if (empty($postings)) {
            return [];
        }

        $teams = [];
        foreach ((array) ($data['jobBoard']['teams'] ?? []) as $team) {
            if (is_array($team) && !empty($team['id'])) {
                $teams[(string) $team['id']] = (string) ($team['externalName'] ?? $team['name'] ?? '');
            }
        }

        $company_slug = trim((string) ($source_info['company_id'] ?? basename(parse_url($url, PHP_URL_PATH) ?: '')), '/');
        $jobs = [];
        foreach ($postings as $posting) {
            if (count($jobs) >= $max_jobs || !is_array($posting)) {
                break;
            }

            $title = sanitize_text_field((string) ($posting['title'] ?? ''));
            $location = $this->ashby_location_label($posting);
            if ($title === '' || !$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $external_id = sanitize_key((string) ($posting['id'] ?? md5($title . $location)));
            $department = sanitize_text_field((string) ($teams[(string) ($posting['teamId'] ?? '')] ?? ''));
            $description = trim(implode(' ', array_filter([
                $department ? 'Team: ' . $department . '.' : '',
                !empty($posting['employmentType']) ? 'Employment type: ' . $posting['employmentType'] . '.' : '',
                !empty($posting['workplaceType']) ? 'Work setup: ' . $posting['workplaceType'] . '.' : '',
            ])));

            $jobs[] = [
                'id' => 'ashby_' . $source_key . '_' . $external_id,
                'external_id' => $external_id,
                'title' => $title,
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => sanitize_text_field($location),
                'description' => $this->clean_text($description),
                'url' => esc_url_raw('https://jobs.ashbyhq.com/' . rawurlencode($company_slug) . '/' . rawurlencode((string) ($posting['id'] ?? ''))),
                'posted_date' => date('Y-m-d'),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Ashby',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'department' => $department,
                'employment_type' => sanitize_text_field((string) ($posting['employmentType'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? ($data['organization']['theme']['logoSquareImageUrl'] ?? ''))),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        return $jobs;
    }

    private function ashby_location_label(array $posting)
    {
        $locations = [];
        if (!empty($posting['locationName'])) {
            $locations[] = (string) $posting['locationName'];
        }

        foreach ((array) ($posting['secondaryLocations'] ?? []) as $secondary_location) {
            if (is_array($secondary_location) && !empty($secondary_location['locationName'])) {
                $locations[] = (string) $secondary_location['locationName'];
            }
        }

        return implode(', ', array_values(array_unique(array_filter(array_map('trim', $locations)))));
    }

    private function fetch_bamboohr_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? '');
        if ($api_url === '') {
            $source_url = rtrim((string) ($source_info['url'] ?? ''), '/');
            if ($source_url !== '') {
                $api_url = $source_url . '/list';
            }
        }

        if ($api_url === '') {
            return [];
        }

        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $items = is_array($data['result'] ?? null) ? $data['result'] : [];
        if (empty($items)) {
            return [];
        }

        $base_url = rtrim((string) ($source_info['url'] ?? preg_replace('#/list(?:\?.*)?$#', '', $api_url)), '/');
        $jobs = [];
        foreach ($items as $item) {
            if (count($jobs) >= $max_jobs || !is_array($item)) {
                break;
            }

            $title = sanitize_text_field((string) ($item['jobOpeningName'] ?? $item['jobTitle'] ?? ''));
            $location = $this->bamboohr_location_label($item);
            if ($title === '' || !$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $external_id = sanitize_key((string) ($item['id'] ?? md5($title . $location)));
            $department = sanitize_text_field((string) ($item['departmentLabel'] ?? ''));
            $description = trim(implode(' ', array_filter([
                $department ? 'Department: ' . $department . '.' : '',
                !empty($item['employmentStatusLabel']) ? 'Employment status: ' . $item['employmentStatusLabel'] . '.' : '',
                !empty($item['employmentType']) ? 'Employment type: ' . $item['employmentType'] . '.' : '',
            ])));

            $jobs[] = [
                'id' => 'bamboohr_' . $source_key . '_' . $external_id,
                'external_id' => $external_id,
                'title' => $title,
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => sanitize_text_field($location),
                'description' => $this->clean_text($description),
                'url' => esc_url_raw($base_url . '/' . rawurlencode((string) ($item['id'] ?? ''))),
                'posted_date' => date('Y-m-d'),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'BambooHR',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'department' => $department,
                'employment_type' => sanitize_text_field((string) ($item['employmentStatusLabel'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        return $jobs;
    }

    private function bamboohr_location_label(array $item)
    {
        $location = is_array($item['location'] ?? null) ? $item['location'] : [];
        $ats_location = is_array($item['atsLocation'] ?? null) ? $item['atsLocation'] : [];
        $parts = array_filter([
            $location['city'] ?? null,
            $location['state'] ?? null,
            $ats_location['city'] ?? null,
            $ats_location['state'] ?? null,
            $ats_location['province'] ?? null,
            $ats_location['country'] ?? null,
        ]);

        return implode(', ', array_values(array_unique(array_filter(array_map('strval', $parts)))));
    }

    private function fetch_zoho_recruit_page_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = (string) ($source_info['url'] ?? '');
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if (!preg_match('/<textarea[^>]+id=["\']jobs["\'][^>]*>(.*?)<\/textarea>/is', $html, $matches)
            && !preg_match('/<input[^>]+id=["\']jobs["\'][^>]+value=["\']([^"\']*)["\']/is', $html, $matches)) {
            return [];
        }

        $json = html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
        $items = json_decode($json, true);
        if (!is_array($items)) {
            return [];
        }

        $jobs = [];
        foreach ($items as $item) {
            if (count($jobs) >= $max_jobs || !is_array($item)) {
                break;
            }

            $title = sanitize_text_field((string) ($item['Posting_Title'] ?? $item['Job_Opening_Name'] ?? ''));
            $city = sanitize_text_field((string) ($item['City'] ?? $item['State'] ?? ''));
            $country = sanitize_text_field((string) ($item['Country'] ?? ''));
            $location = trim(implode(', ', array_filter([$city, $country])));
            if ($title === '' || !$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $external_id = sanitize_key((string) ($item['id'] ?? md5($title . $location)));
            $description = wp_strip_all_tags(html_entity_decode((string) ($item['Job_Description'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $job_url = rtrim($url, '/') . '/' . rawurlencode((string) ($item['id'] ?? ''));

            $jobs[] = [
                'id' => 'zoho_recruit_' . $source_key . '_' . $external_id,
                'external_id' => $external_id,
                'title' => $title,
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                'location' => $location,
                'description' => $this->clean_text($description),
                'url' => esc_url_raw($job_url),
                'posted_date' => $this->normalize_iso_date((string) ($item['Date_Opened'] ?? '')),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Zoho Recruit',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => sanitize_text_field((string) ($item['Industry'] ?? $source_info['category'] ?? 'Job aggregators')),
                'job_type' => sanitize_text_field((string) ($item['Job_Type'] ?? '')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        return $jobs;
    }

    private function fetch_outliers_vc_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $roles = [
            'project_lead' => [
                'title' => 'Project Lead',
                'description' => 'Lead high-impact programs, events, and creative operations at the intersection of innovation and execution.',
            ],
            'platform_lead' => [
                'title' => 'Platform Lead',
                'description' => 'Lead programs, events, and experiences that galvanize the Outliers VC community and brand.',
            ],
            'finance_lead' => [
                'title' => 'Finance Lead',
                'description' => 'Oversee fund management, financial operations, and strategic planning for venture capital initiatives.',
            ],
            'legal_operations_lead' => [
                'title' => 'Legal & Operations Lead',
                'description' => 'Oversee legal, governance, and operations across funds, operating entities, and SPVs.',
            ],
            'senior_operations_lead' => [
                'title' => 'Senior Operations Lead',
                'description' => 'Build and scale operational systems in a fast-paced venture environment.',
            ],
        ];

        $jobs = [];
        foreach ($roles as $slug => $role) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $location = 'Riyadh, Saudi Arabia';
            if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $jobs[] = [
                'id' => 'outliers_vc_' . $source_key . '_' . sanitize_key($slug),
                'external_id' => sanitize_key($slug),
                'title' => sanitize_text_field($role['title']),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? 'Outliers VC')),
                'location' => $location,
                'description' => $this->clean_text((string) $role['description']),
                'url' => esc_url_raw(rtrim((string) ($source_info['url'] ?? 'https://talent.outliers.vc/'), '/') . '/apply'),
                'posted_date' => date('Y-m-d'),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Outliers VC Careers',
                'source_platform' => $source_info['source_platform'] ?? 'Outliers VC',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Private Equity',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);
        return $jobs;
    }

    private function normalize_iso_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function normalize_millisecond_date($date)
    {
        if (is_numeric($date)) {
            $timestamp = (int) floor(((int) $date) / 1000);
            return $timestamp > 0 ? date('Y-m-d', $timestamp) : date('Y-m-d');
        }

        return $this->normalize_iso_date($date);
    }

    private function fetch_jisr_careers_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? '');
        $uuid = (string) ($source_info['career_website_uuid'] ?? '');
        if ($api_url === '' || $uuid === '') {
            return [];
        }

        $jobs = [];
        $page = 1;
        $per_page = min(max($max_jobs, 10), 50);
        $total_pages = 1;

        do {
            $request_url = add_query_arg([
                'uuid' => $uuid,
                'page' => $page,
                'per_page' => $per_page,
            ], $api_url);

            $response = wp_remote_get($request_url, [
                'timeout' => 30,
                'redirection' => 4,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')',
                ],
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
                break;
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            $items = is_array($payload['data']['jobs_details'] ?? null) ? $payload['data']['jobs_details'] : [];
            $pagination = is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [];
            $total_pages = max(1, (int) ($pagination['total_pages'] ?? $total_pages));

            foreach ($items as $item) {
                if (count($jobs) >= $max_jobs) {
                    break 2;
                }
                if (!is_array($item)) {
                    continue;
                }

                $guid = sanitize_key((string) ($item['guid'] ?? ''));
                $title = sanitize_text_field((string) ($item['title_i18n'] ?? ($item['title_en'] ?? '')));
                if ($guid === '' || $title === '') {
                    continue;
                }

                $location = sanitize_text_field((string) ($source_info['location'] ?? 'Riyadh, Saudi Arabia'));
                if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $company_slug = sanitize_key((string) ($source_info['company_slug'] ?? ''));
                $job_url = 'https://www.jisr.net/en/' . $company_slug . '/careers/' . rawurlencode($guid) . '?host=1&id=' . rawurlencode($uuid);

                $jobs[] = [
                    'id' => 'jisr_' . $source_key . '_' . $guid,
                    'external_id' => $guid,
                    'title' => $title,
                    'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? '')),
                    'location' => $location,
                    'description' => $this->clean_text($title . "\n\n" . ($source_info['company_name'] ?? '') . "\n\n" . $location),
                    'url' => esc_url_raw($job_url),
                    'posted_date' => $this->normalize_jisr_date($item['created_at'] ?? ''),
                    'published_at' => sanitize_text_field((string) ($item['created_at'] ?? '')),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                    'source_platform' => $source_info['source_platform'] ?? 'Jisr',
                    'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                    'category' => $source_info['category'] ?? 'Job aggregators',
                    'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                    'department_id' => sanitize_text_field((string) ($item['department_id'] ?? '')),
                    'employment_type_id' => sanitize_text_field((string) ($item['employment_type_id'] ?? '')),
                    'via_recruiter' => false,
                ];
            }

            $page++;
        } while ($page <= $total_pages && count($jobs) < $max_jobs);

        return $jobs;
    }

    private function normalize_jisr_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Fetch jobs from Michael Page listing pages.
     */
    private function fetch_michael_page_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $jobs = $this->parse_michael_page_listing_html((string) wp_remote_retrieve_body($response), $source_key, $source_info, $url, $max_jobs);
        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return array_slice($jobs, 0, $max_jobs);
    }

    private function parse_michael_page_listing_html($html, $source_key, array $source_info, $base_url, $max_jobs = 50)
    {
        if (trim((string) $html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $tiles = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " views-row ")]//*[contains(concat(" ", normalize-space(@class), " "), " search-job-tile ")]');
        if (!$tiles || $tiles->length === 0) {
            return [];
        }

        $jobs = [];
        $seen_urls = [];
        foreach ($tiles as $tile) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $link = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " job-title ")]//a[contains(@href, "/job-detail/")]', $tile)->item(0);
            if (!$link) {
                $link = $xpath->query('.//a[contains(@href, "/job-detail/")]', $tile)->item(0);
            }
            if (!$link) {
                continue;
            }

            $job_url = $this->absolutize_url(html_entity_decode((string) $link->getAttribute('href'), ENT_QUOTES, 'UTF-8'), $base_url);
            if ($job_url === '' || isset($seen_urls[$job_url])) {
                continue;
            }
            $seen_urls[$job_url] = true;

            $title = $this->clean_text($link->textContent);
            $location = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " job-location ")]', $tile));
            $contract = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " job-contract-type ")]', $tile));
            $summary = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " job-summary ")]', $tile));
            $bullets = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " bullet_points ")]', $tile));
            $reference = $this->extract_michael_page_reference($job_url);
            $details = $this->fetch_michael_page_job_detail($job_url);
            $details_match_listing = empty($details['external_id']) || empty($reference) || strtoupper($details['external_id']) === strtoupper($reference);

            $description = ($details_match_listing && $details['description']) ? $details['description'] : trim($summary . "\n\n" . $bullets);
            $posted_date = ($details_match_listing && $details['posted_date']) ? $details['posted_date'] : '';

            $jobs[] = [
                'id' => 'michael_page_' . $source_key . '_' . sanitize_key($reference ?: md5($job_url)),
                'external_id' => $reference,
                'title' => sanitize_text_field(($details_match_listing && $details['title']) ? $details['title'] : $title),
                'company' => sanitize_text_field($source_info['company_name'] ?? 'Michael Page'),
                'location' => sanitize_text_field(($details_match_listing && $details['location']) ? $details['location'] : ($location ?: ($source_info['location'] ?? ''))),
                'description' => $description,
                'url' => esc_url_raw($job_url),
                'posted_date' => $this->normalize_michael_page_date($posted_date),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Michael Page',
                'source_platform' => $source_info['source_platform'] ?? 'Michael Page',
                'source_type' => 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'contract_type' => sanitize_text_field(($details_match_listing && $details['contract_type']) ? $details['contract_type'] : $contract),
                'salary_min' => $details_match_listing ? $details['salary_min'] : null,
                'salary_max' => $details_match_listing ? $details['salary_max'] : null,
                'salary_currency' => $details_match_listing ? $details['salary_currency'] : '',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function fetch_michael_page_job_detail($url)
    {
        $details = [
            'title' => '',
            'description' => '',
            'location' => '',
            'posted_date' => '',
            'contract_type' => '',
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => '',
            'external_id' => '',
        ];

        if ($url === '') {
            return $details;
        }

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return $details;
        }

        $html = (string) wp_remote_retrieve_body($response);
        $metadata = $this->extract_michael_page_metadata($html);
        $details['external_id'] = sanitize_text_field((string) ($metadata['jobreference'] ?? ''));

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $details['title'] = $this->clean_text($metadata['job_title'] ?? '');
        if ($details['title'] === '') {
            $details['title'] = $this->clean_text($this->xpath_text($xpath, '//h1'));
        }

        $description_parts = [];
        foreach ([
            '//*[contains(concat(" ", normalize-space(@class), " "), " job-advert__content ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " job-description ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " job-detail ")]//*[contains(concat(" ", normalize-space(@class), " "), " field--name-body ")]',
            '//meta[@name="description"]/@content',
            '//meta[@property="og:description"]/@content',
        ] as $query) {
            $text = $this->clean_text($this->xpath_text($xpath, $query));
            if ($text !== '') {
                $description_parts[] = $text;
            }
        }
        $details['description'] = implode("\n\n", array_unique($description_parts));

        $city = (string) ($metadata['job_location_suburb_city_name'] ?? '');
        $country = (string) ($metadata['job_location_suburb_country_name'] ?? '');
        $location_value = sanitize_text_field((string) ($metadata['location'] ?? ''));
        $details['location'] = trim(implode(', ', array_filter([$city, $country])));
        if ($details['location'] === '' && $location_value !== '' && !ctype_digit($location_value)) {
            $details['location'] = $location_value;
        }

        $details['contract_type'] = sanitize_text_field((string) ($metadata['contractType'] ?? ($metadata['contract'] ?? '')));
        $details['salary_currency'] = strtoupper(sanitize_text_field((string) ($metadata['currency_code'] ?? ($metadata['salaryCurrency'] ?? ''))));
        $details['salary_min'] = isset($metadata['salarymin']) && is_numeric($metadata['salarymin']) ? (int) $metadata['salarymin'] : null;
        $details['salary_max'] = isset($metadata['salarymax']) && is_numeric($metadata['salarymax']) ? (int) $metadata['salarymax'] : null;
        if (empty($details['salary_min']) && isset($metadata['salary']) && is_numeric($metadata['salary'])) {
            $details['salary_min'] = (int) $metadata['salary'];
            $details['salary_max'] = (int) $metadata['salary'];
        }

        return $details;
    }

    private function extract_michael_page_metadata($html)
    {
        if (!preg_match_all('/(?:var\s+)?(?:thunderheadDataLayer|dataLayer)\s*=\s*(\{.*?\})<\/script>/s', (string) $html, $matches)) {
            return [];
        }

        $metadata = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $metadata = array_merge($metadata, $decoded);
            }
        }

        if (preg_match('/<script[^>]+data-drupal-selector=["\']drupal-settings-json["\'][^>]*>(.*?)<\/script>/s', (string) $html, $settings_match)) {
            $settings = json_decode(html_entity_decode($settings_match[1], ENT_QUOTES, 'UTF-8'), true);
            if (is_array($settings) && isset($settings['dataLayer']) && is_array($settings['dataLayer'])) {
                $metadata = array_merge($metadata, $settings['dataLayer']);
            }
        }

        return $metadata;
    }

    private function extract_michael_page_reference($url)
    {
        if (preg_match('/\/ref\/([^\/\?]+)/', (string) $url, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    private function normalize_michael_page_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function fetch_aventus_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $jobs = $this->parse_aventus_listing_html((string) wp_remote_retrieve_body($response), $source_key, $source_info, $url, $max_jobs);
        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return array_slice($jobs, 0, $max_jobs);
    }

    private function parse_aventus_listing_html($html, $source_key, array $source_info, $base_url, $max_jobs = 50)
    {
        if (trim((string) $html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $cards = $xpath->query('//*[@id="jobListing"]//a[contains(concat(" ", normalize-space(@class), " "), " role__item ")]');
        if (!$cards || $cards->length === 0) {
            return [];
        }

        $jobs = [];
        $seen_urls = [];
        foreach ($cards as $card) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $job_url = $this->absolutize_url(html_entity_decode((string) $card->getAttribute('href'), ENT_QUOTES, 'UTF-8'), $base_url);
            if ($job_url === '' || isset($seen_urls[$job_url])) {
                continue;
            }
            $seen_urls[$job_url] = true;

            $external_id = $this->extract_aventus_external_id($job_url);
            $title = $this->clean_text($this->xpath_text($xpath, './/h4', $card));
            $location = $this->clean_text($this->xpath_text($xpath, './/div[contains(concat(" ", normalize-space(@class), " "), " content ")]//span', $card));
            $posted_text = $this->clean_text($this->xpath_text($xpath, './/span[contains(concat(" ", normalize-space(@class), " "), " days ")]', $card));
            $details = $this->fetch_aventus_job_detail($job_url);
            $details_match_listing = empty($details['external_id']) || empty($external_id) || $details['external_id'] === $external_id;

            $jobs[] = [
                'id' => 'aventus_' . $source_key . '_' . sanitize_key($external_id ?: md5($job_url)),
                'external_id' => sanitize_text_field($external_id),
                'title' => sanitize_text_field(($details_match_listing && $details['title']) ? $details['title'] : $title),
                'company' => sanitize_text_field($source_info['company_name'] ?? 'Aventus Global'),
                'location' => sanitize_text_field(($details_match_listing && $details['location']) ? $details['location'] : $location),
                'description' => ($details_match_listing && $details['description']) ? $details['description'] : $title,
                'url' => esc_url_raw($job_url),
                'posted_date' => $this->normalize_aventus_date(($details_match_listing && $details['posted_date']) ? $details['posted_date'] : $posted_text),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Aventus Global',
                'source_platform' => $source_info['source_platform'] ?? 'Aventus Global',
                'source_type' => 'job_aggregator',
                'category' => sanitize_text_field(($details_match_listing && $details['category']) ? $details['category'] : ($source_info['category'] ?? 'Job aggregators')),
                'employment_type' => sanitize_text_field(($details_match_listing && $details['employment_type']) ? $details['employment_type'] : ''),
                'company_logo' => esc_url_raw((string) (($details_match_listing && $details['company_logo']) ? $details['company_logo'] : ($source_info['company_logo'] ?? ''))),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function fetch_aventus_job_detail($url)
    {
        $details = [
            'title' => '',
            'description' => '',
            'location' => '',
            'posted_date' => '',
            'employment_type' => '',
            'category' => '',
            'company_logo' => '',
            'external_id' => '',
        ];

        if ($url === '') {
            return $details;
        }

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return $details;
        }

        $html = (string) wp_remote_retrieve_body($response);
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $details['category'] = sanitize_text_field($this->clean_text($this->xpath_text($xpath, '//h1[contains(concat(" ", normalize-space(@class), " "), " page-title ")]/following::h4[1]')));

        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        if ($scripts) {
            foreach ($scripts as $script) {
                $payload = json_decode(trim($script->textContent), true);
                if (!is_array($payload) || ($payload['@type'] ?? '') !== 'JobPosting') {
                    continue;
                }

                $details['title'] = sanitize_text_field((string) ($payload['title'] ?? ''));
                $details['description'] = $this->clean_text(wp_strip_all_tags((string) ($payload['description'] ?? '')));
                $details['posted_date'] = sanitize_text_field((string) ($payload['datePosted'] ?? ''));
                $details['employment_type'] = sanitize_text_field((string) ($payload['employmentType'] ?? ''));
                $details['external_id'] = sanitize_text_field((string) ($payload['identifier']['value'] ?? ''));
                $details['company_logo'] = esc_url_raw((string) ($payload['hiringOrganization']['logo'] ?? ''));

                $address = $payload['jobLocation']['address'] ?? [];
                if (is_array($address)) {
                    $city = sanitize_text_field((string) ($address['addressLocality'] ?? ''));
                    $region = sanitize_text_field((string) ($address['addressRegion'] ?? ''));
                    $country = $this->normalize_aventus_country((string) ($address['addressCountry'] ?? ''));
                    $parts = array_values(array_unique(array_filter([$city, $region, $country])));
                    $details['location'] = implode(', ', $parts);
                }

                break;
            }
        }

        if ($details['title'] === '') {
            $details['title'] = sanitize_text_field($this->clean_text($this->xpath_text($xpath, '//h1[contains(concat(" ", normalize-space(@class), " "), " page-title ")]')));
        }

        if ($details['description'] === '') {
            $details['description'] = $this->clean_text($this->xpath_text($xpath, '(//div[contains(concat(" ", normalize-space(@class), " "), " content ")])[last()]'));
        }

        return $details;
    }

    private function extract_aventus_external_id($url)
    {
        if (preg_match('/\/job\/(\d+)/', (string) $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function normalize_aventus_date($date)
    {
        $value = strtolower(trim((string) $date));
        if ($value === '') {
            return date('Y-m-d');
        }

        if (preg_match('/(\d+)\s+day/', $value, $matches)) {
            return date('Y-m-d', strtotime('-' . (int) $matches[1] . ' days'));
        }

        if (preg_match('/(\d+)\s+week/', $value, $matches)) {
            return date('Y-m-d', strtotime('-' . (int) $matches[1] . ' weeks'));
        }

        if (preg_match('/(\d+)\s+month/', $value, $matches)) {
            return date('Y-m-d', strtotime('-' . (int) $matches[1] . ' months'));
        }

        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function normalize_aventus_country($country)
    {
        $map = [
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'QA' => 'Qatar',
            'KW' => 'Kuwait',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
        ];

        $country = strtoupper(trim((string) $country));
        return $map[$country] ?? $country;
    }

    private function fetch_venture_search_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $jobs = $this->parse_venture_search_listing_html((string) wp_remote_retrieve_body($response), $source_key, $source_info, $url, $max_jobs);
        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return array_slice($jobs, 0, $max_jobs);
    }

    private function parse_venture_search_listing_html($html, $source_key, array $source_info, $base_url, $max_jobs = 50)
    {
        if (trim((string) $html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " job-result-item ")]');
        if (!$items || $items->length === 0) {
            return [];
        }

        $jobs = [];
        $seen_urls = [];
        foreach ($items as $item) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $link = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " job-title ")]//a[contains(@href, "/job/")]', $item)->item(0);
            if (!$link) {
                continue;
            }

            $job_url = $this->absolutize_url(html_entity_decode((string) $link->getAttribute('href'), ENT_QUOTES, 'UTF-8'), $base_url);
            if ($job_url === '' || isset($seen_urls[$job_url])) {
                continue;
            }
            $seen_urls[$job_url] = true;

            $external_id = $this->extract_venture_search_external_id($job_url);
            $title = $this->clean_text($link->textContent);
            $location = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " results-job-location ")]', $item));
            $posted_text = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " results-posted-at ")]', $item));
            $summary = $this->clean_text($this->xpath_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " job-description ")]', $item));
            $discipline_slug = sanitize_text_field((string) $item->getAttribute('data-disciplines'));
            $details = $this->fetch_venture_search_job_detail($job_url);
            $details_match_listing = empty($details['external_id']) || empty($external_id) || $details['external_id'] === $external_id;

            $jobs[] = [
                'id' => 'venture_search_' . $source_key . '_' . sanitize_key($external_id ?: md5($job_url)),
                'external_id' => sanitize_text_field($external_id),
                'title' => sanitize_text_field(($details_match_listing && $details['title']) ? $details['title'] : $title),
                'company' => sanitize_text_field($source_info['company_name'] ?? 'Venture Search'),
                'location' => sanitize_text_field(($details_match_listing && $details['location']) ? $details['location'] : $location),
                'description' => ($details_match_listing && $details['description']) ? $details['description'] : $summary,
                'url' => esc_url_raw($job_url),
                'posted_date' => $this->normalize_venture_search_date(($details_match_listing && $details['posted_date']) ? $details['posted_date'] : $posted_text),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Venture Search',
                'source_platform' => $source_info['source_platform'] ?? 'Venture Search',
                'source_type' => 'job_aggregator',
                'category' => sanitize_text_field(($details_match_listing && $details['category']) ? $details['category'] : ($discipline_slug ?: ($source_info['category'] ?? 'Job aggregators'))),
                'contract_type' => sanitize_text_field(($details_match_listing && $details['contract_type']) ? $details['contract_type'] : ''),
                'recruiter_name' => sanitize_text_field(($details_match_listing && $details['contact_name']) ? $details['contact_name'] : ''),
                'recruiter_email' => sanitize_email(($details_match_listing && $details['contact_email']) ? $details['contact_email'] : ''),
                'company_logo' => esc_url_raw((string) (($details_match_listing && $details['company_logo']) ? $details['company_logo'] : ($source_info['company_logo'] ?? ''))),
                'via_recruiter' => true,
            ];
        }

        return $jobs;
    }

    private function fetch_venture_search_job_detail($url)
    {
        $details = [
            'title' => '',
            'description' => '',
            'location' => '',
            'posted_date' => '',
            'category' => '',
            'contract_type' => '',
            'contact_name' => '',
            'contact_email' => '',
            'company_logo' => '',
            'external_id' => '',
        ];

        if ($url === '') {
            return $details;
        }

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return $details;
        }

        $html = (string) wp_remote_retrieve_body($response);
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json" or @type="application/ld+json"]');
        if ($scripts) {
            foreach ($scripts as $script) {
                $payload = json_decode(trim($script->textContent), true);
                if (!is_array($payload) || ($payload['@type'] ?? '') !== 'JobPosting') {
                    continue;
                }

                $details['title'] = sanitize_text_field((string) ($payload['title'] ?? ''));
                $details['description'] = $this->clean_text(wp_strip_all_tags((string) ($payload['description'] ?? '')));
                $details['posted_date'] = sanitize_text_field((string) ($payload['datePosted'] ?? ''));
                $details['external_id'] = sanitize_text_field((string) ($payload['identifier']['value'] ?? ''));
                $details['company_logo'] = esc_url_raw((string) ($payload['hiringOrganization']['logo'] ?? ''));

                $address = $payload['jobLocation']['address'] ?? [];
                if (is_array($address)) {
                    $city = sanitize_text_field((string) ($address['addressLocality'] ?? ''));
                    $region = sanitize_text_field((string) ($address['addressRegion'] ?? ''));
                    $country = $this->normalize_aventus_country((string) ($address['addressCountry'] ?? ''));
                    $parts = array_values(array_unique(array_filter([$city, $region, $country])));
                    $details['location'] = implode(', ', $parts);
                }

                break;
            }
        }

        if ($details['title'] === '') {
            $details['title'] = sanitize_text_field($this->clean_text($this->xpath_text($xpath, '//header//h2 | //h1')));
        }

        if ($details['description'] === '') {
            $details['description'] = $this->clean_text($this->xpath_text($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " desc ")]//article'));
        }

        $details['location'] = $details['location'] ?: $this->extract_venture_search_table_value($xpath, 'Location');
        $details['category'] = $this->extract_venture_search_table_value($xpath, 'Discipline:');
        $details['contract_type'] = $this->extract_venture_search_table_value($xpath, 'Job type:');
        $details['contact_name'] = $this->extract_venture_search_table_value($xpath, 'Contact name:');
        $details['contact_email'] = $this->extract_venture_search_table_value($xpath, 'Contact email:');
        $details['external_id'] = $details['external_id'] ?: $this->extract_venture_search_table_value($xpath, 'Job ref:');
        $details['posted_date'] = $details['posted_date'] ?: $this->extract_venture_search_table_value($xpath, 'Published:');

        return $details;
    }

    private function extract_venture_search_table_value(DOMXPath $xpath, $label)
    {
        $query = '//table//tr[td[1][normalize-space()="' . $label . '"]]/td[2]';
        return $this->clean_text($this->xpath_text($xpath, $query));
    }

    private function extract_venture_search_external_id($url)
    {
        if (preg_match('/-(\d+)(?:\/)?(?:\?.*)?$/', (string) $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function normalize_venture_search_date($date)
    {
        $value = strtolower(trim(preg_replace('/\s+/', ' ', (string) $date)));
        $value = trim(str_replace('posted ', '', $value));
        if ($value === '') {
            return date('Y-m-d');
        }

        if (preg_match('/(\d+)\s+day/', $value, $matches)) {
            return date('Y-m-d', strtotime('-' . (int) $matches[1] . ' days'));
        }

        if (preg_match('/(\d+)\s+week/', $value, $matches)) {
            return date('Y-m-d', strtotime('-' . (int) $matches[1] . ' weeks'));
        }

        if (preg_match('/(\d+)\s+month/', $value, $matches)) {
            return date('Y-m-d', strtotime('-' . (int) $matches[1] . ' months'));
        }

        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Fetch professional jobs from Mubadala's embedded Takafo careers app.
     */
    private function fetch_mubadala_takafo_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = $source_info['api_url'] ?? 'https://mic-cand.takafo.ai/v1/jobs/external';
        $query = [
            'offset' => 1,
            'limit' => max(1, min(100, (int) $max_jobs)),
        ];

        if (!empty($source_info['job_title'])) {
            $query['job_title'] = (string) $source_info['job_title'];
        }

        if (!empty($source_info['location'])) {
            $query['location'] = (string) $source_info['location'];
        }

        $response = wp_remote_get(add_query_arg($query, $api_url), [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                'Referer' => 'https://mic-cand.takafo.ai/jobs/external?mode=eos&company=MIC&types=Full%20Time%20Employee%20%28FTE%29%2CContract%20%28LTC%29',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($payload)) {
            return [];
        }

        $data = $payload['data']['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $jobs = [];
        $seen_ids = [];
        foreach ($data as $job) {
            if (count($jobs) >= $max_jobs || !is_array($job)) {
                break;
            }

            $raw_id = (string) ($job['id'] ?? '');
            $job_id = sanitize_key($raw_id);
            if ($job_id === '' || isset($seen_ids[$job_id])) {
                continue;
            }
            $seen_ids[$job_id] = true;

            $advert = is_array($job['job_advert'] ?? null) ? $job['job_advert'] : [];
            $start_date = (string) ($advert['external_start_date'] ?? ($advert['internal_start_date'] ?? ''));
            $end_date = (string) ($advert['external_end_date'] ?? ($advert['internal_end_date'] ?? ''));
            $contract_type = (string) ($job['contract_type'] ?? '');

            $jobs[] = [
                'id' => 'mubadala_takafo_' . $source_key . '_' . $job_id,
                'external_id' => sanitize_text_field($raw_id),
                'title' => sanitize_text_field((string) ($job['job_title'] ?? '')),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? 'Mubadala Investment Company')),
                'location' => sanitize_text_field((string) ($job['location'] ?? ($source_info['location'] ?? ''))),
                'description' => $this->build_mubadala_takafo_description($advert),
                'url' => esc_url_raw('https://mic-cand.takafo.ai/job/details/' . rawurlencode($raw_id)),
                'posted_date' => $this->normalize_mubadala_takafo_date($start_date),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Mubadala Professional Careers',
                'source_platform' => $source_info['source_platform'] ?? 'Takafo',
                'source_type' => 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'department' => sanitize_text_field((string) ($job['unit'] ?? '')),
                'business_unit' => sanitize_text_field((string) ($job['unit'] ?? '')),
                'platform' => sanitize_text_field((string) ($job['platform'] ?? '')),
                'grade' => sanitize_text_field((string) ($job['grade'] ?? '')),
                'contract_type' => sanitize_text_field($contract_type),
                'employment_type' => sanitize_text_field($contract_type),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'start_date' => $start_date !== '' ? $this->normalize_mubadala_takafo_date($start_date) : '',
                'end_date' => $end_date !== '' ? $this->normalize_mubadala_takafo_date($end_date) : '',
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return array_slice($jobs, 0, $max_jobs);
    }

    private function build_mubadala_takafo_description(array $advert)
    {
        $sections = [
            'About us' => $advert['external_about_us'] ?? ($advert['internal_about_us'] ?? ''),
            'What you will do' => $advert['external_what_will_you_do'] ?? ($advert['internal_what_will_you_do'] ?? ''),
            'What you will bring' => $advert['external_what_you_will_bring'] ?? ($advert['internal_what_you_will_bring'] ?? ''),
            'What we offer' => $advert['external_what_we_offer_you'] ?? ($advert['internal_what_we_offer_you'] ?? ''),
            'Key indicators' => $advert['external_key_indicators'] ?? ($advert['internal_key_indicators'] ?? ''),
        ];

        $description = [];
        foreach ($sections as $heading => $html) {
            $body = $this->clean_html((string) $html);
            if ($body !== '') {
                $description[] = $heading . "\n" . $body;
            }
        }

        return trim(implode("\n\n", $description));
    }

    private function normalize_mubadala_takafo_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Fetch jobs from Consider-powered portfolio boards.
     */
    private function fetch_consider_board_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $board_url = (string) ($source_info['url'] ?? '');
        $api_url = (string) ($source_info['api_url'] ?? '');
        $board_id = (string) ($source_info['board_id'] ?? '');

        if ($board_url === '' || $api_url === '' || $board_id === '') {
            return [];
        }

        $initial_response = wp_remote_get($board_url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')',
            ],
        ]);

        if (is_wp_error($initial_response) || (int) wp_remote_retrieve_response_code($initial_response) >= 400) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($initial_response);
        $csrf_token = '';
        if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $html, $matches)) {
            $csrf_token = (string) $matches[1];
        }

        $cookie_header = $this->build_cookie_header_from_response($initial_response);
        $request_headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')',
            'Referer' => $board_url,
        ];
        if ($csrf_token !== '') {
            $request_headers['X-CSRF-Token'] = $csrf_token;
        }
        if ($cookie_header !== '') {
            $request_headers['Cookie'] = $cookie_header;
        }

        $request_size = max($max_jobs * 5, (int) ($source_info['request_size'] ?? 250));
        $response = wp_remote_post($api_url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => $request_headers,
            'body' => wp_json_encode([
                'meta' => [
                    'size' => $request_size,
                ],
                'board' => [
                    'id' => $board_id,
                    'isParent' => true,
                ],
                'query' => [],
                'grouped' => false,
                'parentSlug' => $board_id,
            ]),
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $items = is_array($payload['jobs'] ?? null) ? $payload['jobs'] : [];
        if (empty($items)) {
            return [];
        }

        $jobs = [];
        $seen_ids = [];
        foreach ($items as $item) {
            if (count($jobs) >= $max_jobs) {
                break;
            }
            if (!is_array($item)) {
                continue;
            }

            if (!$this->consider_board_job_matches_allowed_locations($item, $source_info)) {
                continue;
            }

            $raw_id = (string) ($item['jobId'] ?? md5((string) ($item['url'] ?? ($item['title'] ?? ''))));
            $job_id = sanitize_key($raw_id);
            if ($job_id === '' || isset($seen_ids[$job_id])) {
                continue;
            }
            $seen_ids[$job_id] = true;

            $location = $this->format_consider_board_location($item);
            $description = $this->build_consider_board_description($item);
            $url = (string) ($item['applyUrl'] ?? ($item['url'] ?? ''));

            $jobs[] = [
                'id' => 'consider_' . $source_key . '_' . $job_id,
                'external_id' => sanitize_text_field($raw_id),
                'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                'company' => sanitize_text_field((string) ($item['companyName'] ?? ($source_info['company_name'] ?? ''))),
                'location' => sanitize_text_field($location),
                'description' => $description,
                'url' => esc_url_raw($url),
                'posted_date' => $this->normalize_consider_board_date($item['timeStamp'] ?? ''),
                'published_at' => sanitize_text_field((string) ($item['timeStamp'] ?? '')),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Consider',
                'source_type' => 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'company_logo' => esc_url_raw($this->extract_consider_board_company_logo($item, $source_info)),
                'company_domain' => sanitize_text_field((string) ($item['companyDomain'] ?? '')),
                'remote' => !empty($item['remote']),
                'hybrid' => !empty($item['hybrid']),
                'job_functions' => $this->extract_consider_board_labels($item['jobFunctions'] ?? []),
                'job_types' => $this->extract_consider_board_labels($item['jobTypes'] ?? []),
                'markets' => $this->extract_consider_board_labels($item['markets'] ?? []),
                'skills' => array_slice($this->extract_consider_board_labels($item['skills'] ?? []), 0, 15),
                'seniority' => implode(', ', $this->extract_consider_board_labels($item['jobSeniorities'] ?? [])),
                'via_recruiter' => false,
            ];
        }

        $this->enrich_jobs_with_source_meta($jobs, $source_key, $source_info);

        return array_slice($jobs, 0, $max_jobs);
    }

    private function build_cookie_header_from_response($response)
    {
        $cookies = [];
        foreach (wp_remote_retrieve_cookies($response) as $cookie) {
            if (method_exists($cookie, 'get_name') && method_exists($cookie, 'get_value')) {
                $name = $cookie->get_name();
                $value = $cookie->get_value();
                if ($name !== '' && $value !== '') {
                    $cookies[] = $name . '=' . $value;
                }
            }
        }

        return implode('; ', $cookies);
    }

    private function get_default_consider_board_allowed_locations()
    {
        return [
            'Dubai',
            'Abu Dhabi',
            'Sharjah',
            'United Arab Emirates',
            'Riyadh',
            'Jeddah',
            'Thuwal',
            'Saudi Arabia',
            'Doha',
            'Qatar',
            'Kuwait',
            'Bahrain',
            'Oman',
            'Muscat',
            'Cairo',
            'Egypt',
            'Amman',
            'Jordan',
            'Beirut',
            'Lebanon',
            'London',
        ];
    }

    private function consider_board_job_matches_allowed_locations(array $item, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        $location_text = strtolower($this->format_consider_board_location($item));
        if ($location_text === '') {
            return false;
        }

        foreach ($allowed_locations as $allowed_location) {
            $allowed_location = strtolower((string) $allowed_location);
            if ($allowed_location !== '' && strpos($location_text, $allowed_location) !== false) {
                return true;
            }
        }

        return (bool) preg_match('/(^|,\s|\b)(ae|sa|qa|kw|bh|om|eg|jo|lb)(\b|$)/i', $location_text);
    }

    private function format_consider_board_location(array $item)
    {
        $parts = [];
        foreach ((array) ($item['locations'] ?? []) as $location) {
            $location = trim((string) $location);
            if ($location !== '') {
                $parts[] = $location;
            }
        }

        foreach ((array) ($item['normalizedLocations'] ?? []) as $location) {
            if (!is_array($location)) {
                continue;
            }
            $label = trim((string) ($location['label'] ?? ($location['value'] ?? '')));
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    private function build_consider_board_description(array $item)
    {
        $parts = [];
        $parts[] = (string) ($item['title'] ?? '');
        $parts[] = (string) ($item['companyName'] ?? '');
        $parts[] = $this->format_consider_board_location($item);

        $markets = $this->extract_consider_board_labels($item['markets'] ?? []);
        if (!empty($markets)) {
            $parts[] = 'Markets: ' . implode(', ', $markets);
        }

        $functions = $this->extract_consider_board_labels($item['jobFunctions'] ?? []);
        if (!empty($functions)) {
            $parts[] = 'Functions: ' . implode(', ', $functions);
        }

        $skills = $this->extract_consider_board_labels($item['skills'] ?? []);
        if (!empty($skills)) {
            $parts[] = 'Skills: ' . implode(', ', array_slice($skills, 0, 20));
        }

        return $this->clean_text(implode("\n\n", array_filter($parts)));
    }

    private function extract_consider_board_labels($items)
    {
        $labels = [];
        foreach ((array) $items as $item) {
            if (is_array($item)) {
                $label = trim((string) ($item['label'] ?? ($item['value'] ?? ($item['id'] ?? ''))));
            } else {
                $label = trim((string) $item);
            }

            if ($label !== '') {
                $labels[] = preg_replace('/^resume:/', '', $label);
            }
        }

        return array_values(array_unique($labels));
    }

    private function extract_consider_board_company_logo(array $item, array $source_info)
    {
        $logos = is_array($item['companyLogos'] ?? null) ? $item['companyLogos'] : [];
        foreach (['manual', 'linkedin'] as $key) {
            if (!empty($logos[$key]['src'])) {
                return (string) $logos[$key]['src'];
            }
        }

        return (string) ($source_info['company_logo'] ?? '');
    }

    private function normalize_consider_board_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Format company name from slug
     */
    private function format_company_name($slug)
    {
        $name_map = [
            'bluecrestcapitalmanagement' => 'BlueCrest Capital Management',
            'point72' => 'Point72',
            'pantheonpublic' => 'Pantheon',
            'eqtpartners' => 'EQT Partners'
        ];

        return $name_map[$slug] ?? ucwords(str_replace(['_', '-'], ' ', $slug));
    }

    /**
     * Fetch jobs from Oracle HCM API with smart discovery
     */
    private function fetch_oracle_hcm_jobs($source_key, $source_info, $max_jobs = 20)
    {
        $jobs = [];
        $base_url = $source_info['url'];

        // Get cached job IDs and ranges
        $cache_key = 'oracle_hcm_ranges_' . md5($base_url);
        $known_ranges = get_transient($cache_key);

        if (!$known_ranges) {
            // Use initial scan ranges if no cache
            $known_ranges = isset($source_info['scan_ranges']) ? $source_info['scan_ranges'] : [[100, 1000]];
        }

        // Build scan points from known ranges
        $scan_points = [];
        foreach ($known_ranges as $range) {
            for ($i = $range[0]; $i <= $range[1]; $i += 20) {
                $scan_points[] = $i;
            }
        }

        // Add exploration points to discover new ranges
        // This ensures we find jobs even if they add ID 3425, 4353, etc.
        $exploration_points = [
            // Scan every 500 IDs up to 5000
            500,
            1000,
            1500,
            2000,
            2500,
            3000,
            3500,
            4000,
            4500,
            5000,
            // Scan every 1000 IDs up to 10000
            6000,
            7000,
            8000,
            9000,
            10000
        ];

        foreach ($exploration_points as $point) {
            if (!in_array($point, $scan_points)) {
                $scan_points[] = $point;
            }
        }

        sort($scan_points);
        $active_ids = [];
        $new_ranges = [];

        // Scan for active jobs
        foreach ($scan_points as $id) {
            if (count($jobs) >= $max_jobs) break;

            $job_data = $this->fetch_oracle_job($base_url, $id, $source_info, $source_key);
            if ($job_data) {
                $jobs[] = $job_data;
                $active_ids[] = $id;

                // When we find a job, check nearby IDs
                for ($nearby = $id - 5; $nearby <= $id + 5; $nearby++) {
                    if ($nearby > 0 && !in_array($nearby, $active_ids) && count($jobs) < $max_jobs) {
                        $nearby_job = $this->fetch_oracle_job($base_url, $nearby, $source_info, $source_key);
                        if ($nearby_job) {
                            $jobs[] = $nearby_job;
                            $active_ids[] = $nearby;
                        }
                    }
                }
            }
        }

        // Update cached ranges based on found jobs
        if (!empty($active_ids)) {
            sort($active_ids);
            $new_ranges = $this->calculate_job_ranges($active_ids);
            set_transient($cache_key, $new_ranges, DAY_IN_SECONDS);
        }

        return $jobs;
    }

    /**
     * Fetch single Oracle HCM job
     */
    private function fetch_oracle_job($base_url, $id, array $source_info = [], $source_key = '')
    {
        $url = $base_url . $id;

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['Title'])) {
            return null;
        }

        // Convert Oracle HCM format to our standard format
        return [
            'id' => 'oracle_' . $data['Id'],
            'title' => $data['Title'],
            'company' => $data['Organization'] ?? ($source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key))),
            'location' => $data['PrimaryLocation'] ?? '',
            'description' => $data['ExternalDescriptionStr'] ?? '',
            'url' => $url,
            'posted_date' => isset($data['ExternalPostedStartDate']) ?
                date('Y-m-d', strtotime($data['ExternalPostedStartDate'])) : date('Y-m-d'),
            'category' => $data['Category'] ?? '',
            'job_type' => $data['ContractType'] ?? $data['JobType'] ?? '',
            'source' => 'oracle_hcm',
            'source_key' => $source_key,
            'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
            'via_recruiter' => (($source_info['source_type'] ?? '') === 'recruiter'),
        ];
    }

    /**
     * Fetch jobs from Oracle Candidate Experience search pages.
     */
    private function fetch_oracle_cx_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_base_url = rtrim((string) ($source_info['api_base_url'] ?? $this->extract_oracle_cx_api_base_url($source_info['url'] ?? '')), '/');
        $site_number = (string) ($source_info['site_number'] ?? $this->extract_oracle_cx_site_number($source_info['url'] ?? ''));

        if ($api_base_url === '' || $site_number === '') {
            return [];
        }

        $jobs = [];
        $seen_ids = [];
        $page_size = min(25, max(1, (int) $max_jobs));
        $max_pages = max(1, min(8, (int) ceil(max(1, $max_jobs) / $page_size) + 1));
        $organization_names = [];
        $location_filters = $this->extract_oracle_cx_location_filters($source_info);
        $keyword = (string) ($source_info['keyword'] ?? $this->extract_oracle_cx_keyword_filter($source_info['url'] ?? ''));

        for ($page = 0; $page < $max_pages && count($jobs) < $max_jobs; $page++) {
            $offset = $page * $page_size;
            $request_body = [
                'input' => sanitize_text_field($keyword),
                'expand' => 'requisitionList.workLocation,requisitionList.otherWorkLocations,requisitionList.secondaryLocations,flexFieldsFacet.values,requisitionList.requisitionFlexFields',
                'siteNumber' => $site_number,
                'facets' => 'TITLES;LOCATIONS;LOCATION_LEVEL1;LOCATION_LEVEL2;LOCATION_LEVEL3;CATEGORIES;POSTING_DATES;WORK_LOCATIONS;FLEX_FIELDS;ORGANIZATIONS;WORKPLACE_TYPES',
                'limit' => $page_size,
                'offset' => $offset,
                'sortBy' => 'POSTING_DATES_DESC',
            ];

            if ($location_filters['location'] !== '') {
                $request_body['location'] = $location_filters['location'];
            }

            if ($location_filters['location_id'] !== '') {
                $request_body['locationId'] = $location_filters['location_id'];
            }

            if ($location_filters['location_level'] !== '') {
                $request_body['locationLevel'] = $location_filters['location_level'];
            }

            $response = wp_remote_post($api_base_url . '/hcmRestApi/CandidateExperience/recruitingCEJobSearch/list', [
                'timeout' => 30,
                'redirection' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/vnd.oracle.adf.action+json',
                    'Ora-Irc-Rest-Action' => 'ACE_JOBSEARCH_LIST',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
                'body' => wp_json_encode($request_body),
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            $search = $payload['items'][0] ?? null;
            if (!is_array($search)) {
                break;
            }

            foreach (($search['organizationsFacet'] ?? []) as $organization) {
                $organization_id = (string) ($organization['id'] ?? $organization['Id'] ?? '');
                $organization_name = (string) ($organization['name'] ?? $organization['Name'] ?? '');
                if ($organization_id !== '' && $organization_name !== '') {
                    $organization_names[$organization_id] = $organization_name;
                }
            }

            $items = $search['requisitionList'] ?? [];
            if (empty($items) || !is_array($items)) {
                break;
            }

            $added_this_page = 0;
            foreach ($items as $item) {
                if (count($jobs) >= $max_jobs || !is_array($item)) {
                    break 2;
                }

                $external_id = (string) ($item['requisitionNumber'] ?? $item['RequisitionNumber'] ?? $item['id'] ?? $item['Id'] ?? '');
                if ($external_id === '' || isset($seen_ids[$external_id])) {
                    continue;
                }

                $location = $this->normalize_oracle_cx_location($item);
                if (!$this->oracle_cx_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $seen_ids[$external_id] = true;
                $company = $this->resolve_oracle_cx_company($item, $organization_names, $source_info);
                $description = $item['shortDescriptionStr'] ?? $item['ShortDescriptionStr'] ?? $item['externalResponsibilitiesStr'] ?? $item['ExternalResponsibilitiesStr'] ?? '';

                $jobs[] = [
                    'id' => 'oracle_cx_' . $source_key . '_' . sanitize_key($external_id),
                    'external_id' => $external_id,
                    'title' => sanitize_text_field((string) ($item['title'] ?? $item['Title'] ?? '')),
                    'company' => sanitize_text_field($company),
                    'location' => sanitize_text_field($location),
                    'description' => wp_strip_all_tags(html_entity_decode((string) $description, ENT_QUOTES, 'UTF-8')),
                    'url' => esc_url_raw($this->build_oracle_cx_job_url($source_info['url'] ?? '', $external_id)),
                    'posted_date' => $this->normalize_oracle_cx_date($item['postedDate'] ?? $item['PostedDate'] ?? ''),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                    'source_platform' => $source_info['source_platform'] ?? 'Oracle HCM',
                    'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                    'category' => sanitize_text_field((string) ($item['jobFunction'] ?? $item['JobFunction'] ?? $source_info['category'] ?? 'Job aggregators')),
                    'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                    'job_type' => sanitize_text_field((string) ($item['jobType'] ?? $item['JobType'] ?? $item['workerType'] ?? $item['WorkerType'] ?? '')),
                    'seniority' => sanitize_text_field((string) ($item['managerLevel'] ?? $item['ManagerLevel'] ?? '')),
                    'skills' => [],
                    'via_recruiter' => false,
                ];
            }

            $total_jobs = (int) ($search['totalJobsCount'] ?? $search['TotalJobsCount'] ?? 0);
            if ($total_jobs > 0 && $offset + $page_size >= $total_jobs) {
                break;
            }
        }

        return $jobs;
    }

    private function extract_oracle_cx_api_base_url($url)
    {
        $parts = wp_parse_url((string) $url);
        if (empty($parts['host'])) {
            return '';
        }

        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
    }

    private function extract_oracle_cx_site_number($url)
    {
        if (preg_match('#/sites/([^/]+)/#', (string) $url, $matches)) {
            return sanitize_text_field($matches[1]);
        }

        return '';
    }

    private function extract_oracle_cx_location_filters(array $source_info)
    {
        $filters = [
            'location' => (string) ($source_info['location'] ?? ''),
            'location_id' => (string) ($source_info['location_id'] ?? ''),
            'location_level' => (string) ($source_info['location_level'] ?? ''),
        ];

        $parts = wp_parse_url((string) ($source_info['url'] ?? ''));
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if ($filters['location'] === '' && !empty($query['location'])) {
                $filters['location'] = (string) $query['location'];
            }
            if ($filters['location_id'] === '' && !empty($query['locationId'])) {
                $filters['location_id'] = (string) $query['locationId'];
            }
            if ($filters['location_level'] === '' && !empty($query['locationLevel'])) {
                $filters['location_level'] = (string) $query['locationLevel'];
            }
        }

        return [
            'location' => sanitize_text_field($filters['location']),
            'location_id' => sanitize_text_field($filters['location_id']),
            'location_level' => sanitize_text_field($filters['location_level']),
        ];
    }

    private function extract_oracle_cx_keyword_filter($url)
    {
        $parts = wp_parse_url((string) $url);
        if (empty($parts['query'])) {
            return '';
        }

        parse_str($parts['query'], $query);
        return sanitize_text_field((string) ($query['keyword'] ?? $query['q'] ?? ''));
    }

    private function build_oracle_cx_job_url($source_url, $external_id)
    {
        $source_url = (string) $source_url;
        if ($source_url === '') {
            return '';
        }

        $base = preg_replace('~/jobs(?:[/?#].*)?$~', '', $source_url);
        return rtrim($base ?: $source_url, '/') . '/job/' . rawurlencode((string) $external_id);
    }

    private function normalize_oracle_cx_location(array $item)
    {
        $work_locations = $item['workLocation'] ?? $item['WorkLocation'] ?? [];
        if (!empty($work_locations) && is_array($work_locations)) {
            $primary = $work_locations[0] ?? [];
            if (is_array($primary)) {
                $city = $primary['townOrCity'] ?? $primary['TownOrCity'] ?? '';
                $country = $primary['country'] ?? $primary['Country'] ?? '';
                if ($city !== '') {
                    return trim($city . ($country !== '' ? ', ' . $this->normalize_oracle_cx_country($country) : ''));
                }

                $location_name = $primary['locationName'] ?? $primary['LocationName'] ?? '';
                if ($location_name !== '') {
                    return (string) $location_name;
                }
            }
        }

        $primary_location = (string) ($item['primaryLocation'] ?? $item['PrimaryLocation'] ?? '');
        if ($primary_location !== '') {
            return $this->normalize_oracle_cx_country($primary_location);
        }

        return $this->normalize_oracle_cx_country((string) ($item['primaryLocationCountry'] ?? $item['PrimaryLocationCountry'] ?? ''));
    }

    private function normalize_oracle_cx_country($country)
    {
        $country = (string) $country;
        $map = [
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'QA' => 'Qatar',
        ];

        return $map[$country] ?? $country;
    }

    private function oracle_cx_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    private function resolve_oracle_cx_company(array $item, array $organization_names, array $source_info)
    {
        $company = (string) ($item['organization'] ?? $item['Organization'] ?? $item['businessUnit'] ?? $item['BusinessUnit'] ?? '');
        if ($company !== '') {
            return $company;
        }

        $organization_id = (string) ($item['organizationId'] ?? $item['OrganizationId'] ?? $item['businessUnitId'] ?? $item['BusinessUnitId'] ?? '');
        if ($organization_id !== '' && !empty($organization_names[$organization_id])) {
            return $organization_names[$organization_id];
        }

        return (string) ($source_info['company_name'] ?? $source_info['name'] ?? 'Emirates NBD');
    }

    private function normalize_oracle_cx_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Fetch jobs from Goldman Sachs Higher GraphQL search.
     */
    private function fetch_goldman_higher_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? 'https://api-higher.gs.com/gateway/api/v1/graphql');
        if ($api_url === '') {
            return [];
        }

        $location_filters = [
            [
                'filter' => 'Saudi Arabia',
                'subFilters' => [
                    [
                        'filter' => 'Riyadh',
                        'subFilters' => [
                            ['filter' => 'Riyadh', 'subFilters' => []],
                        ],
                    ],
                ],
            ],
            [
                'filter' => 'United Arab Emirates',
                'subFilters' => [
                    [
                        'filter' => 'Dubai',
                        'subFilters' => [
                            ['filter' => 'Dubai', 'subFilters' => []],
                        ],
                    ],
                ],
            ],
        ];

        $jobs = [];
        $seen_ids = [];
        $page_size = min(20, max(1, (int) $max_jobs));
        $max_pages = max(1, min(5, (int) ceil(max(1, $max_jobs) / $page_size) + 1));

        $query = 'query GetRoles($searchQueryInput: RoleSearchQueryInput!) { roleSearch(searchQueryInput: $searchQueryInput) { totalCount items { roleId corporateTitle jobTitle jobFunction locations { primary state country city } status division skills jobType { code description } externalSource { sourceId } } } }';

        for ($page = 0; $page < $max_pages && count($jobs) < $max_jobs; $page++) {
            $request_body = [
                'operationName' => 'GetRoles',
                'variables' => [
                    'searchQueryInput' => [
                        'page' => [
                            'pageSize' => $page_size,
                            'pageNumber' => $page,
                        ],
                        'sort' => [
                            'sortStrategy' => 'RELEVANCE',
                            'sortOrder' => 'DESC',
                        ],
                        'filters' => [
                            [
                                'filterCategoryType' => 'LOCATION',
                                'filters' => $location_filters,
                            ],
                        ],
                        'experiences' => ['EARLY_CAREER', 'PROFESSIONAL'],
                        'searchTerm' => '',
                    ],
                ],
                'query' => $query,
            ];

            $response = wp_remote_post($api_url, [
                'timeout' => 30,
                'redirection' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-higher-request-id' => wp_generate_uuid4(),
                    'x-higher-session-id' => 'sffc-feed-manager',
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
                ],
                'body' => wp_json_encode($request_body),
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            $items = $payload['data']['roleSearch']['items'] ?? [];
            if (empty($items) || !is_array($items)) {
                break;
            }

            foreach ($items as $item) {
                if (count($jobs) >= $max_jobs || !is_array($item)) {
                    break 2;
                }

                $external_source = $item['externalSource'] ?? [];
                $external_id = (string) ($external_source['sourceId'] ?? $item['roleId'] ?? '');
                if ($external_id === '' || isset($seen_ids[$external_id])) {
                    continue;
                }

                $location = $this->normalize_goldman_higher_location($item['locations'] ?? []);
                if (!$this->goldman_higher_job_matches_allowed_locations($location, $source_info)) {
                    continue;
                }

                $seen_ids[$external_id] = true;
                $title = sanitize_text_field((string) ($item['jobTitle'] ?? ''));
                $division = sanitize_text_field((string) ($item['division'] ?? ''));
                $job_function = sanitize_text_field((string) ($item['jobFunction'] ?? ''));
                $skills = array_values(array_filter(array_map('sanitize_text_field', (array) ($item['skills'] ?? []))));
                $job_type = $item['jobType'] ?? [];

                $jobs[] = [
                    'id' => 'goldman_higher_' . $source_key . '_' . sanitize_key($external_id),
                    'external_id' => $external_id,
                    'title' => $title,
                    'company' => sanitize_text_field((string) ($source_info['company_name'] ?? 'Goldman Sachs')),
                    'location' => sanitize_text_field($location),
                    'description' => trim($division . ($job_function !== '' ? ' - ' . $job_function : '')),
                    'url' => esc_url_raw('https://higher.gs.com/roles/' . rawurlencode($external_id)),
                    'posted_date' => date('Y-m-d'),
                    'source' => $source_key,
                    'source_key' => $source_key,
                    'source_name' => $source_info['name'] ?? 'Goldman Sachs Middle East Careers',
                    'source_platform' => $source_info['source_platform'] ?? 'Goldman Sachs Higher',
                    'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                    'category' => $job_function !== '' ? $job_function : ($source_info['category'] ?? 'Job aggregators'),
                    'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                    'job_type' => sanitize_text_field((string) ($job_type['description'] ?? '')),
                    'seniority' => sanitize_text_field((string) ($item['corporateTitle'] ?? '')),
                    'skills' => $skills,
                    'via_recruiter' => false,
                ];
            }

            $total_count = (int) ($payload['data']['roleSearch']['totalCount'] ?? 0);
            if ($total_count > 0 && (($page + 1) * $page_size) >= $total_count) {
                break;
            }
        }

        return $jobs;
    }

    private function normalize_goldman_higher_location($locations)
    {
        if (!is_array($locations) || empty($locations)) {
            return '';
        }

        $primary = null;
        foreach ($locations as $location) {
            if (is_array($location) && !empty($location['primary'])) {
                $primary = $location;
                break;
            }
        }

        if ($primary === null) {
            $primary = is_array($locations[0]) ? $locations[0] : [];
        }

        return implode(', ', array_filter([
            (string) ($primary['city'] ?? ''),
            (string) ($primary['country'] ?? ''),
        ]));
    }

    private function goldman_higher_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch jobs from Deutsche Bank's Beesite careers API.
     */
    private function fetch_deutsche_bank_beesite_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $api_url = (string) ($source_info['api_url'] ?? 'https://api-deutschebank.beesite.de/search/');
        if ($api_url === '') {
            return [];
        }

        $country_id = (string) ($source_info['country_id'] ?? $this->extract_deutsche_bank_country_id($source_info['url'] ?? ''));
        if ($country_id === '') {
            $country_id = '230';
        }

        $request_body = [
            'LanguageCode' => 'EN',
            'SearchParameters' => [
                'FirstItem' => 1,
                'CountItem' => max(1, (int) $max_jobs),
                'MatchedObjectDescriptor' => [
                    'PositionID',
                    'PositionTitle',
                    'PositionURI',
                    'OrganizationName',
                    'PositionFormattedDescription.Content',
                    'PositionLocation.CountryName',
                    'PositionLocation.CountrySubDivisionName',
                    'PositionLocation.CityName',
                    'JobCategory.Name',
                    'CareerLevel.Name',
                    'PositionSchedule.Name',
                    'PositionOfferingType.Name',
                    'PublicationStartDate',
                    'PositionHiringYear',
                ],
                'Sort' => [
                    [
                        'Criterion' => 'PublicationStartDate',
                        'Direction' => 'DESC',
                    ],
                ],
            ],
            'SearchCriteria' => [
                [
                    'CriterionName' => 'PositionLocation.Country',
                    'CriterionValue' => $country_id,
                ],
            ],
        ];

        $response = wp_remote_get(add_query_arg('data', wp_json_encode($request_body), $api_url), [
            'timeout' => 30,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $items = $payload['SearchResult']['SearchResultItems'] ?? [];
        if (empty($items) || !is_array($items)) {
            return [];
        }

        $jobs = [];
        $seen_ids = [];
        foreach ($items as $item) {
            if (count($jobs) >= $max_jobs || !is_array($item)) {
                break;
            }

            $descriptor = $item['MatchedObjectDescriptor'] ?? [];
            if (!is_array($descriptor)) {
                continue;
            }

            $external_id = (string) ($descriptor['PositionID'] ?? $item['MatchedObjectId'] ?? '');
            if ($external_id === '' || isset($seen_ids[$external_id])) {
                continue;
            }

            $location = $this->normalize_deutsche_bank_location($descriptor['PositionLocation'] ?? []);
            if (!$this->deutsche_bank_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $seen_ids[$external_id] = true;
            $title = sanitize_text_field(html_entity_decode((string) ($descriptor['PositionTitle'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $career_level = $this->extract_deutsche_bank_named_value($descriptor['CareerLevel'] ?? []);
            $job_category = $this->extract_deutsche_bank_named_value($descriptor['JobCategory'] ?? []);
            $schedule = $this->extract_deutsche_bank_named_value($descriptor['PositionSchedule'] ?? []);
            $offering_type = $this->extract_deutsche_bank_named_value($descriptor['PositionOfferingType'] ?? []);
            $description = $descriptor['PositionFormattedDescription.Content'] ?? '';

            $jobs[] = [
                'id' => 'deutsche_bank_' . $source_key . '_' . sanitize_key($external_id),
                'external_id' => $external_id,
                'title' => $title,
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? 'Deutsche Bank')),
                'location' => sanitize_text_field($location),
                'description' => wp_strip_all_tags(html_entity_decode((string) $description, ENT_QUOTES, 'UTF-8')),
                'url' => esc_url_raw($this->build_deutsche_bank_job_url($descriptor['PositionURI'] ?? '', $external_id)),
                'posted_date' => $this->normalize_deutsche_bank_date($descriptor['PublicationStartDate'] ?? ''),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Deutsche Bank UAE Careers',
                'source_platform' => $source_info['source_platform'] ?? 'Deutsche Bank Beesite',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => sanitize_text_field($job_category ?: ($source_info['category'] ?? 'Job aggregators')),
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'job_type' => sanitize_text_field(trim($schedule . ($offering_type !== '' ? ' - ' . $offering_type : ''))),
                'seniority' => sanitize_text_field($career_level),
                'skills' => [],
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function extract_deutsche_bank_country_id($url)
    {
        $url = (string) $url;
        $query = '';
        $fragment_pos = strpos($url, '#');
        if ($fragment_pos !== false) {
            $fragment = substr($url, $fragment_pos + 1);
            $fragment_query_pos = strpos($fragment, '?');
            if ($fragment_query_pos !== false) {
                $query = substr($fragment, $fragment_query_pos + 1);
            }
        }

        if ($query === '') {
            $parts = wp_parse_url($url);
            $query = (string) ($parts['query'] ?? '');
        }

        parse_str($query, $params);
        return sanitize_text_field((string) ($params['country'] ?? ''));
    }

    private function normalize_deutsche_bank_location($locations)
    {
        if (!is_array($locations) || empty($locations)) {
            return '';
        }

        $location = is_array($locations[0] ?? null) ? $locations[0] : [];
        $city = (string) ($location['CityName'] ?? '');
        $country = $this->normalize_deutsche_bank_country((string) ($location['CountryName'] ?? ''));

        return implode(', ', array_filter([$city, $country]));
    }

    private function normalize_deutsche_bank_country($country)
    {
        $map = [
            'Vereinigte Arabische Emirate' => 'United Arab Emirates',
        ];

        return $map[$country] ?? $country;
    }

    private function extract_deutsche_bank_named_value($values)
    {
        if (!is_array($values) || empty($values)) {
            return '';
        }

        $value = $values[0] ?? [];
        return is_array($value) ? sanitize_text_field((string) ($value['Name'] ?? '')) : '';
    }

    private function build_deutsche_bank_job_url($position_uri, $external_id)
    {
        $position_uri = html_entity_decode((string) $position_uri, ENT_QUOTES, 'UTF-8');
        if ($position_uri !== '') {
            return 'https://careers.db.com/professionals/search-roles/#/professional/job/' . rawurlencode((string) $external_id);
        }

        return 'https://careers.db.com/professionals/search-roles/#/professional/job/' . rawurlencode((string) $external_id);
    }

    private function normalize_deutsche_bank_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function deutsche_bank_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch jobs from Comeet-hosted careers pages with embedded position data.
     */
    private function fetch_comeet_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $company = $this->extract_comeet_json_variable($html, 'COMPANY_DATA', '{');
        $positions = $this->extract_comeet_json_variable($html, 'COMPANY_POSITIONS_DATA', '[');
        if (empty($positions) || !is_array($positions)) {
            return [];
        }

        $company_name = (string) ($source_info['company_name'] ?? $company['name'] ?? $source_info['name'] ?? 'Comeet');
        $company_logo = (string) ($source_info['company_logo'] ?? $company['logos']['medium']['url'] ?? $company['logos']['small']['url'] ?? '');
        $jobs = [];
        $seen_ids = [];

        foreach ($positions as $position) {
            if (count($jobs) >= $max_jobs || !is_array($position)) {
                break;
            }

            $external_id = (string) ($position['uid'] ?? $position['internal_use_custom_id'] ?? '');
            $job_url = (string) ($position['url_active_page'] ?? $position['url_comeet_hosted_page'] ?? $position['url_recruit_hosted_page'] ?? '');
            $dedupe_key = $external_id ?: md5($job_url . ($position['name'] ?? ''));
            if (isset($seen_ids[$dedupe_key])) {
                continue;
            }

            $location = $this->normalize_comeet_location($position['location'] ?? null);
            if (!$this->comeet_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $seen_ids[$dedupe_key] = true;
            $description = $this->build_comeet_description($position);
            $title = (string) ($position['name'] ?? '');

            $jobs[] = [
                'id' => 'comeet_' . $source_key . '_' . sanitize_key($dedupe_key),
                'external_id' => $external_id,
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field($company_name ?: (string) ($position['company_name'] ?? '')),
                'location' => sanitize_text_field($location),
                'description' => wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')),
                'url' => esc_url_raw($job_url ?: $url),
                'posted_date' => $this->normalize_comeet_date($position['time_updated'] ?? ''),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Comeet',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => sanitize_text_field((string) ($position['department'] ?? $source_info['category'] ?? 'Job aggregators')),
                'company_logo' => esc_url_raw($company_logo),
                'job_type' => sanitize_text_field((string) ($position['employment_type'] ?? $position['workplace_type'] ?? '')),
                'seniority' => sanitize_text_field((string) ($position['experience_level'] ?? '')),
                'skills' => [],
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function extract_comeet_json_variable($html, $variable, $opening_char)
    {
        $marker_pos = strpos((string) $html, $variable . ' =');
        if ($marker_pos === false) {
            $marker_pos = strpos((string) $html, $variable . '=');
        }
        if ($marker_pos === false) {
            return [];
        }

        $json_start = strpos((string) $html, $opening_char, $marker_pos);
        if ($json_start === false) {
            return [];
        }

        $json = $opening_char === '['
            ? $this->extract_balanced_json_array($html, $json_start)
            : $this->extract_balanced_json_object($html, $json_start);

        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extract_balanced_json_array($content, $start)
    {
        $length = strlen((string) $content);
        $depth = 0;
        $in_string = false;
        $escaped = false;

        for ($i = (int) $start; $i < $length; $i++) {
            $char = $content[$i];

            if ($in_string) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $in_string = false;
                }
                continue;
            }

            if ($char === '"') {
                $in_string = true;
                continue;
            }

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr((string) $content, (int) $start, $i - (int) $start + 1);
                }
            }
        }

        return '';
    }

    private function normalize_comeet_location($location)
    {
        if (!is_array($location)) {
            return '';
        }

        $city = (string) ($location['city'] ?? '');
        $state = (string) ($location['state'] ?? '');
        $country = $this->normalize_oracle_cx_country((string) ($location['country'] ?? ''));
        $parts = array_values(array_unique(array_filter([$city, $state !== $city ? $state : '', $country])));

        return implode(', ', $parts);
    }

    private function comeet_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    private function build_comeet_description(array $position)
    {
        $sections = [];
        $details = $position['custom_fields']['details'] ?? [];
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                $name = trim((string) ($detail['name'] ?? ''));
                $value = trim((string) ($detail['value'] ?? ''));
                if ($value === '') {
                    continue;
                }
                $sections[] = trim(($name !== '' ? $name . "\n" : '') . $value);
            }
        }

        return trim(implode("\n\n", $sections));
    }

    private function normalize_comeet_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * Fetch active jobs from AGFUND's server-rendered jobs page.
     */
    private function fetch_agfund_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if (trim($html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $cards = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " agf-jobcard ")]');
        if (!$cards || $cards->length === 0) {
            return [];
        }

        $jobs = [];
        $seen = [];
        foreach ($cards as $card) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $status_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " agf-jobcard__status ")]', $card)->item(0);
            $status_class = $status_node instanceof DOMElement ? (string) $status_node->getAttribute('class') : '';
            $status_text = $this->clean_text($status_node ? $status_node->textContent : '');
            if (stripos($status_class, 'agf-jobcard__status--expired') !== false || strtolower($status_text) === 'expired') {
                continue;
            }

            $title_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " agf-jobcard__title ")]', $card)->item(0);
            $link_node = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " agf-btn ") or contains(normalize-space(.), "View job")]', $card)->item(0);
            $raw_title = $this->clean_text($title_node ? $title_node->textContent : '');
            $job_url = $link_node instanceof DOMElement ? $this->absolutize_url((string) $link_node->getAttribute('href'), $url) : $url;
            if ($raw_title === '' || isset($seen[$job_url])) {
                continue;
            }

            $location = $this->extract_agfund_location_from_title($raw_title, $source_info['location'] ?? 'Riyadh, Saudi Arabia');
            $title = $this->clean_agfund_title($raw_title);
            $detail = $this->fetch_agfund_job_detail($job_url);
            $description = $detail['description'] ?? '';
            if ($description === '') {
                $description = trim($status_text . "\n\n" . $title);
            }

            $seen[$job_url] = true;
            $jobs[] = [
                'id' => 'agfund_' . $source_key . '_' . sanitize_key(basename((string) (parse_url($job_url, PHP_URL_PATH) ?: md5($job_url)))),
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? 'AGFUND')),
                'location' => sanitize_text_field($location),
                'description' => wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')),
                'url' => esc_url_raw($job_url),
                'posted_date' => date('Y-m-d'),
                'closing_date' => $this->normalize_agfund_expiry_date($status_text),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'AGFUND Jobs',
                'source_platform' => $source_info['source_platform'] ?? 'AGFUND Careers',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $source_info['category'] ?? 'Job aggregators',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function fetch_agfund_job_detail($job_url)
    {
        $response = wp_remote_get((string) $job_url, [
            'timeout' => 20,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $html = (string) wp_remote_retrieve_body($response);
        if (trim($html) === '' || !class_exists('DOMDocument')) {
            return [];
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $panel = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " agf-panel ")]')->item(0);
        if (!$panel) {
            return [];
        }

        $description_parts = [];
        foreach ($xpath->query('.//*[self::p or self::h2 or self::li]', $panel) as $node) {
            $text = $this->clean_text($node->textContent);
            if ($text !== '') {
                $description_parts[] = $text;
            }
        }

        return [
            'description' => implode("\n", array_unique($description_parts)),
        ];
    }

    private function extract_agfund_location_from_title($title, $fallback)
    {
        if (preg_match('/(?:\x{1F4CD}|Location:)\s*([^|]+)$/u', (string) $title, $match)) {
            return $this->clean_text($match[1]);
        }

        if (stripos((string) $title, 'Riyadh') !== false) {
            return 'Riyadh, Saudi Arabia';
        }

        return (string) $fallback;
    }

    private function clean_agfund_title($title)
    {
        $title = preg_replace('/(?:\x{1F4CD}|Location:)\s*[^|]+$/u', '', (string) $title);
        return $this->clean_text($title);
    }

    private function normalize_agfund_expiry_date($status_text)
    {
        if (preg_match('/Expires on\s+(.+)$/i', (string) $status_text, $match)) {
            $timestamp = strtotime(trim($match[1]));
            if ($timestamp) {
                return date('Y-m-d', $timestamp);
            }
        }

        return '';
    }

    /**
     * Fetch Teamtailor RSS feeds and preserve Teamtailor namespace metadata.
     */
    private function fetch_teamtailor_rss_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $rss_url = (string) ($source_info['rss_url'] ?? '');
        if ($rss_url === '') {
            $source_url = (string) ($source_info['url'] ?? '');
            $rss_url = preg_replace('#/jobs/?(?:\?.*)?$#', '/jobs.rss', $source_url);
        }

        if ($rss_url === '') {
            return [];
        }

        $response = wp_remote_get($rss_url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $xml_body = (string) wp_remote_retrieve_body($response);
        if (trim($xml_body) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_body);
        libxml_clear_errors();
        if (!$xml || empty($xml->channel->item)) {
            return [];
        }

        $jobs = [];
        foreach ($xml->channel->item as $item) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $title = sanitize_text_field((string) $item->title);
            $url = esc_url_raw((string) $item->link);
            if ($title === '' || $url === '') {
                continue;
            }

            $tt = $item->children('https://teamtailor.com/locations');
            $locations = [];
            if (isset($tt->locations->location)) {
                foreach ($tt->locations->location as $location) {
                    $location_name = $this->normalize_teamtailor_location((string) $location->name);
                    if ($location_name !== '') {
                        $locations[] = $location_name;
                    }
                }
            }
            $location = implode(', ', array_values(array_unique($locations)));
            if ($location === '') {
                $location = (string) ($source_info['location'] ?? '');
            }

            if (!$this->teamtailor_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $department = sanitize_text_field((string) ($tt->department ?? ''));
            $description = wp_strip_all_tags(html_entity_decode((string) $item->description, ENT_QUOTES, 'UTF-8'));

            $jobs[] = [
                'id' => 'teamtailor_' . $source_key . '_' . sanitize_key(basename((string) parse_url($url, PHP_URL_PATH))),
                'title' => $title,
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? 'Teamtailor')),
                'location' => sanitize_text_field($location),
                'description' => $this->clean_text($description),
                'url' => $url,
                'posted_date' => $this->normalize_teamtailor_rss_date((string) $item->pubDate),
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
                'source_platform' => $source_info['source_platform'] ?? 'Teamtailor',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => $department ?: ($source_info['category'] ?? 'Job aggregators'),
                'department' => $department,
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function normalize_teamtailor_location($location)
    {
        $location = $this->clean_text($location);
        $location = preg_replace('/\bUAE\b/i', 'United Arab Emirates', (string) $location);
        $location = preg_replace('/\bKSA\b/i', 'Saudi Arabia', (string) $location);
        return $this->clean_text($location);
    }

    private function normalize_teamtailor_rss_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function teamtailor_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    private function fetch_alvarez_marsal_jobs($source_key, array $source_info, $max_jobs = 50)
    {
        $url = $source_info['url'] ?? '';
        if ($url === '') {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 4,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en;q=0.9',
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $html = (string) wp_remote_retrieve_body($response);
        if ($status !== 200 || $this->is_cloudflare_challenge($html)) {
            error_log('SFFC XML Fetcher: Alvarez & Marsal careers page blocked or unavailable for ' . esc_url_raw($url) . ' (status ' . $status . ').');
            return [];
        }

        return $this->parse_alvarez_marsal_jobs_from_html($html, $source_key, $source_info, $max_jobs);
    }

    private function parse_alvarez_marsal_jobs_from_html($html, $source_key, array $source_info, $max_jobs = 50)
    {
        if (trim((string) $html) === '') {
            return [];
        }

        $jobs = [];
        $seen = [];

        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . (string) $html);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[contains(@href, "/jobs/")]');
        if (!$links) {
            return [];
        }

        foreach ($links as $link) {
            if (count($jobs) >= $max_jobs || !($link instanceof DOMElement)) {
                break;
            }

            $href = trim((string) $link->getAttribute('href'));
            $title = $this->clean_text($link->textContent ?? '');
            $title = preg_replace('/\s+NEW\s*$/i', '', (string) $title);
            if ($href === '' || $title === '') {
                continue;
            }

            $job_url = $this->absolutize_url($href, 'https://careers.alvarezandmarsal.com');
            if ($job_url === '') {
                continue;
            }

            preg_match('~/jobs/([0-9]+)~', $job_url, $id_match);
            $external_id = $id_match[1] ?? md5($job_url);
            if (isset($seen[$external_id])) {
                continue;
            }

            $context = $this->get_dom_context_text($link);
            $location = $this->extract_alvarez_marsal_location($context);
            if (!$this->alvarez_marsal_job_matches_allowed_locations($location, $source_info)) {
                continue;
            }

            $seen[$external_id] = true;
            $posted_date = $this->extract_alvarez_marsal_date($context);
            $description = trim($context);

            $jobs[] = [
                'id' => 'alvarez_marsal_' . $source_key . '_' . sanitize_key($external_id),
                'external_id' => $external_id,
                'title' => sanitize_text_field($title),
                'company' => sanitize_text_field((string) ($source_info['company_name'] ?? 'Alvarez & Marsal')),
                'location' => sanitize_text_field($location),
                'description' => wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')),
                'url' => esc_url_raw($job_url),
                'posted_date' => $posted_date,
                'source' => $source_key,
                'source_key' => $source_key,
                'source_name' => $source_info['name'] ?? 'Alvarez & Marsal Careers',
                'source_platform' => $source_info['source_platform'] ?? 'Alvarez & Marsal Careers',
                'source_type' => $source_info['source_type'] ?? 'job_aggregator',
                'category' => 'Job aggregators',
                'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
                'job_type' => '',
                'seniority' => '',
                'skills' => [],
                'via_recruiter' => false,
            ];
        }

        return $jobs;
    }

    private function get_dom_context_text(DOMElement $node)
    {
        $container = $node;
        for ($i = 0; $i < 6 && $container->parentNode instanceof DOMElement; $i++) {
            $container = $container->parentNode;
            $class = ' ' . strtolower((string) $container->getAttribute('class')) . ' ';
            $tag = strtolower($container->tagName);

            if (strpos($class, ' job') !== false || strpos($class, ' search') !== false || $tag === 'li' || $tag === 'article') {
                break;
            }
        }

        return $this->clean_text($this->get_dom_text_with_spacing($container));
    }

    private function get_dom_text_with_spacing(DOMNode $node)
    {
        if ($node instanceof DOMText) {
            return ' ' . $node->wholeText . ' ';
        }

        $text = ' ';
        foreach ($node->childNodes as $child) {
            $text .= $this->get_dom_text_with_spacing($child);
        }

        return $text . ' ';
    }

    private function extract_alvarez_marsal_location($text)
    {
        $text = $this->clean_text($text);
        $patterns = [
            '/\b(Dubai|Abu Dhabi)\b(?:,\s*)?(?:United Arab Emirates|UAE)?/i',
            '/\bRiyadh\b(?:,\s*)?(?:Saudi Arabia)?/i',
            '/\bUnited Arab Emirates\b/i',
            '/\bSaudi Arabia\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $location = trim($match[0], " \t\n\r\0\x0B,");
                if (strcasecmp($location, 'UAE') === 0 || stripos($location, 'United Arab Emirates') !== false) {
                    return stripos($location, 'Dubai') !== false || stripos($location, 'Abu Dhabi') !== false
                        ? $location
                        : 'United Arab Emirates';
                }
                if (stripos($location, 'Saudi Arabia') !== false) {
                    return stripos($location, 'Riyadh') !== false ? $location : 'Saudi Arabia';
                }
                if (strcasecmp($location, 'Riyadh') === 0) {
                    return 'Riyadh, Saudi Arabia';
                }
                if (strcasecmp($location, 'Dubai') === 0) {
                    return 'Dubai, United Arab Emirates';
                }
                if (strcasecmp($location, 'Abu Dhabi') === 0) {
                    return 'Abu Dhabi, United Arab Emirates';
                }
                return $location;
            }
        }

        return '';
    }

    private function extract_alvarez_marsal_date($text)
    {
        $patterns = [
            '/Date\s+Posted:?\s*([A-Z][a-z]{2,9}\s+\d{1,2},\s+\d{4})/i',
            '/Posted:?\s*([A-Z][a-z]{2,9}\s+\d{1,2},\s+\d{4})/i',
            '/\b([A-Z][a-z]{2,9}\s+\d{1,2},\s+\d{4})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, (string) $text, $match)) {
                $timestamp = strtotime($match[1]);
                if ($timestamp) {
                    return date('Y-m-d', $timestamp);
                }
            }
        }

        return date('Y-m-d');
    }

    private function alvarez_marsal_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations)) {
            return true;
        }

        foreach ($allowed_locations as $allowed_location) {
            if (stripos((string) $location, (string) $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_cloudflare_challenge($html)
    {
        $html = (string) $html;
        return stripos($html, 'Just a moment') !== false
            || stripos($html, 'cf-chl') !== false
            || stripos($html, 'Cloudflare') !== false;
    }

    /**
     * Calculate optimal ranges from found job IDs
     */
    private function calculate_job_ranges($active_ids)
    {
        $ranges = [];
        $start = $active_ids[0];
        $end = $active_ids[0];

        for ($i = 1; $i < count($active_ids); $i++) {
            if ($active_ids[$i] - $end <= 100) {
                // Jobs are close together, extend range
                $end = $active_ids[$i];
            } else {
                // Gap found, save range with padding
                $ranges[] = [max(1, $start - 20), $end + 20];
                $start = $active_ids[$i];
                $end = $active_ids[$i];
            }
        }
        // Save last range
        $ranges[] = [max(1, $start - 20), $end + 20];

        return $ranges;
    }

    /**
     * Fetch jobs from website page (job listing page)
     */
    private function fetch_website_jobs($source_key, $source_info, $max_jobs = 50)
    {
        $jobs = [];
        $url = $source_info['url'] ?? '';
        
        if (empty($url)) {
            return [];
        }

        // Fetch the website page content
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return [];
        }

        // Extract jobs from the HTML listing page
        $jobs = $this->extract_jobs_from_html_listing($html, $url, $source_key, $source_info);
        
        return array_slice($jobs, 0, $max_jobs);
    }

    /**
     * Parse XML feed content
     */
    private function parse_xml_feed($xml_content, $source_key, $source_info)
    {
        $jobs = [];

        // Handle Oracle HCM type
        if (isset($source_info['type']) && $source_info['type'] === 'oracle_hcm') {
            return $this->fetch_oracle_hcm_jobs($source_key, $source_info);
        }

        // Handle Greenhouse.io API type
        if (isset($source_info['type']) && $source_info['type'] === 'greenhouse') {
            return $this->fetch_greenhouse_jobs($source_key, $source_info);
        }

        // Handle Pinpoint ATS type
        if (isset($source_info['type']) && $source_info['type'] === 'pinpoint') {
            return $this->fetch_pinpoint_jobs($source_key, $source_info);
        }

        // Handle Website Page type (job listing pages)
        if (isset($source_info['type']) && $source_info['type'] === 'website') {
            return $this->fetch_website_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'workable_board') {
            return $this->fetch_workable_board_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'icims_search') {
            return $this->fetch_icims_search_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'nichehr_supabase') {
            return $this->fetch_nichehr_supabase_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'oracle_cx') {
            return $this->fetch_oracle_cx_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'goldman_higher') {
            return $this->fetch_goldman_higher_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'deutsche_bank_beesite') {
            return $this->fetch_deutsche_bank_beesite_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'comeet') {
            return $this->fetch_comeet_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'agfund') {
            return $this->fetch_agfund_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'teamtailor_rss') {
            return $this->fetch_teamtailor_rss_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'michael_page') {
            return $this->fetch_michael_page_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'aventus') {
            return $this->fetch_aventus_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'venture_search') {
            return $this->fetch_venture_search_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'alvarez_marsal') {
            return $this->fetch_alvarez_marsal_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'recruitee') {
            return $this->fetch_recruitee_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'ashby') {
            return $this->fetch_ashby_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'bamboohr') {
            return $this->fetch_bamboohr_jobs($source_key, $source_info);
        }

        if (isset($source_info['type']) && $source_info['type'] === 'job_listing_page') {
            return $this->fetch_curated_listing_page_jobs($source_key, $source_info);
        }

        // Suppress XML errors
        libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xml_content);
            if (!$xml) {
                return [];
            }

            // Handle sitemap format
            if ($source_info['type'] === 'sitemap') {
                $namespaces = $xml->getNamespaces(true);
                $urls = $xml->url;

                // Filter job URLs for sitemap parsing
                $job_urls = [];
                foreach ($urls as $url) {
                    $url_string = (string)$url->loc;
                    
                    // Check if this looks like a job URL
                    if ($this->is_job_url($url_string)) {
                        $job_urls[] = $url;
                        
                        // Limit the number of URLs we process to avoid timeouts
                        if (count($job_urls) >= 50) {
                            break;
                        }
                    }
                }

                // Process job URLs with proper extraction
                foreach ($job_urls as $url) {
                    $job = $this->extract_job_from_sitemap_url($url, $source_key, $source_info);
                    if (!empty($job) && !empty($job['title'])) {
                        $jobs[] = $job;
                    }
                    
                    // Limit jobs to prevent memory issues
                    if (count($jobs) >= 20) {
                        break;
                    }
                }
            }
            // Handle RSS format
            elseif ($source_info['type'] === 'rss') {
                $items = $xml->channel->item;

                foreach ($items as $item) {
                    $job = $this->extract_job_from_rss_item($item, $source_key, $source_info);
                    if (!empty($job)) {
                        $jobs[] = $job;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('XML parsing error for ' . $source_key . ': ' . $e->getMessage());
        }

        return $jobs;
    }

    private function fetch_rss_jobs($url, $source_key, array $source_info, $max_jobs = 50)
    {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')',
                'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) wp_remote_retrieve_body($response));
        libxml_clear_errors();
        if (!$xml || empty($xml->channel->item)) {
            return [];
        }

        $jobs = [];
        foreach ($xml->channel->item as $item) {
            if (count($jobs) >= $max_jobs) {
                break;
            }

            $job = $this->extract_job_from_rss_item($item, $source_key, $source_info);
            if (!empty($job)) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    private function extract_job_from_rss_item($item, $source_key, array $source_info)
    {
        $title = sanitize_text_field(html_entity_decode((string) $item->title, ENT_QUOTES, 'UTF-8'));
        $url = esc_url_raw((string) $item->link);

        if ($title === '' || $url === '') {
            return [];
        }

        $description = '';
        $content = $item->children('http://purl.org/rss/1.0/modules/content/');
        if (isset($content->encoded)) {
            $description = (string) $content->encoded;
        }
        if ($description === '') {
            $description = (string) $item->description;
        }

        $job_listing = $item->children('https://www.dohabank.com.qa');
        $location = '';
        $company = '';
        if (isset($job_listing->location)) {
            $location = sanitize_text_field(html_entity_decode((string) $job_listing->location, ENT_QUOTES, 'UTF-8'));
        }
        if (isset($job_listing->company)) {
            $company = sanitize_text_field(html_entity_decode((string) $job_listing->company, ENT_QUOTES, 'UTF-8'));
        }

        if ($location === '') {
            $location = sanitize_text_field((string) ($source_info['location'] ?? ''));
        }
        if ($company === '' || !empty($source_info['force_company_name'])) {
            $company = sanitize_text_field((string) ($source_info['company_name'] ?? $source_info['name'] ?? ''));
        }

        if (!$this->rss_job_matches_allowed_locations($location, $source_info)) {
            return [];
        }

        $clean_description = $this->clean_text(wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')));
        $external_id = sanitize_key(basename((string) parse_url($url, PHP_URL_PATH)));
        if ($external_id === '') {
            $external_id = sanitize_key(md5($url));
        }

        $job = [
            'id' => 'rss_' . $source_key . '_' . $external_id,
            'title' => $title,
            'company' => $company,
            'location' => $location,
            'description' => $clean_description,
            'url' => $url,
            'posted_date' => $this->normalize_rss_date((string) $item->pubDate),
            'source' => $source_key,
            'source_key' => $source_key,
            'source_name' => $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key)),
            'source_platform' => $source_info['source_platform'] ?? 'RSS',
            'source_type' => $source_info['source_type'] ?? 'job_aggregator',
            'category' => $source_info['category'] ?? 'Job aggregators',
            'company_logo' => esc_url_raw((string) ($source_info['company_logo'] ?? '')),
            'via_recruiter' => (($source_info['source_type'] ?? '') === 'recruiter'),
        ];

        if (!empty($clean_description)) {
            $job['skills'] = array_slice($this->extract_skills_from_description($title . ' ' . $clean_description), 0, 15);
        }

        return $job;
    }

    private function normalize_rss_date($date)
    {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function rss_job_matches_allowed_locations($location, array $source_info)
    {
        $allowed_locations = $this->get_allowed_locations_for_source($source_info);
        if (empty($allowed_locations) || trim((string) $location) === '') {
            return true;
        }

        $location = strtolower((string) $location);
        foreach ($allowed_locations as $allowed_location) {
            $allowed_location = strtolower((string) $allowed_location);
            if ($allowed_location !== '' && strpos($location, $allowed_location) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL looks like a job URL
     */
    private function is_job_url($url)
    {
        $url_lower = strtolower($url);
        
        // Exclude non-job pages that contain job-related words
        $exclude_patterns = [
            '\\/jobs\\/$',                    // Directory pages like /jobs/
            '\\/jobs\\/thank-you',           // Thank you pages
            '\\/jobs\\/apply',               // Application pages
            '\\/careers\\/$',                // Directory pages like /careers/
            '\\/careers\\/thank-you',        // Thank you pages
            '\\/careers\\/apply',            // Application pages
            'contact',
            'about',
            'home',
            'news',
            'blog',
            'privacy',
            'terms',
            'cookie',
            'login',
            'register',
            'search',
            'filter',
            'sort'
        ];
        
        foreach ($exclude_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $url_lower)) {
                return false;
            }
        }
        
        // Must contain job-related directory AND have additional path content
        $job_patterns = [
            '\\/jobs\\/.+',                 // Must have content after /jobs/
            '\\/job\\/.+',                  // Must have content after /job/
            '\\/careers\\/.+',              // Must have content after /careers/
            '\\/career\\/.+',               // Must have content after /career/
            '\\/vacancies\\/.+',            // Must have content after /vacancies/
            '\\/vacancy\\/.+',              // Must have content after /vacancy/
            '\\/opportunities\\/.+',        // Must have content after /opportunities/
            '\\/opportunity\\/.+',          // Must have content after /opportunity/
            '\\/positions\\/.+',            // Must have content after /positions/
            '\\/position\\/.+',             // Must have content after /position/
            '\\/openings\\/.+',             // Must have content after /openings/
            '\\/opening\\/.+',              // Must have content after /opening/
            '\\/roles\\/.+',                // Must have content after /roles/
            '\\/role\\/.+',                 // Must have content after /role/
            '\\/position-\\d+',             // Pattern: /position-12345-title/
            '\\/job-\\d+',                  // Pattern: /job-12345.ext or /job-12345-title/
            '\\/role-\\d+',                 // Pattern: /role-12345-title/
            '\\/vacancy-\\d+',              // Pattern: /vacancy-12345-title/
            '\\/career-\\d+',               // Pattern: /career-12345-title/
            '\\/\\w*job\\w*\\.(php|html?|aspx?)', // Pattern: job-related files with extensions
            '\\/\\w*position\\w*\\.(php|html?|aspx?)', // Pattern: position-related files
            '\\/\\w*career\\w*\\.(php|html?|aspx?)', // Pattern: career-related files
        ];
        
        foreach ($job_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $url_lower)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract job data from sitemap URL entry
     */
    private function extract_job_from_sitemap_url($url_element, $source_key, $source_info)
    {
        $job_url = (string)$url_element->loc;

        // Use the enhanced extraction method that fetches the actual page and looks for structured data
        $job = $this->extract_job_from_url_enhanced($job_url, $source_key, $source_info);

        // Add source metadata
        $job['source_key'] = $source_key;
        $job['category'] = $source_info['category'] ?? 'General';
        $job['quality'] = $source_info['quality'] ?? 'standard';
        $job['source_name'] = $source_info['name'] ?? ucwords(str_replace('_', ' ', $source_key));

        // Add last modified date if available
        if (isset($url_element->lastmod)) {
            $job['posted_date'] = date('Y-m-d', strtotime((string)$url_element->lastmod));
        }

        // Ensure we have essential fields
        if (empty($job['title'])) {
            // Try to extract from URL as fallback
            $job = $this->extract_job_info_from_url($job, $source_key);
        }

        // Make sure we have skills
        if (empty($job['skills']) || count($job['skills']) < 3) {
            // Force skill extraction from whatever text we have
            $all_text = implode(' ', [
                $job['title'] ?? '',
                $job['description'] ?? '',
                $job['responsibilities'] ?? '',
                $job['qualifications'] ?? '',
                $job['requirements'] ?? ''
            ]);

            if (!empty($all_text)) {
                $skills = $this->extract_skills_from_description($all_text);
                if (!empty($skills)) {
                    $job['skills'] = $skills;
                }
            }

            // If still no skills, add from title
            if (empty($job['skills']) && !empty($job['title'])) {
                $job['skills'] = $this->extract_basic_skills_from_title($job['title']);
            }
        }

        return $job;
    }

    /**
     * Enhanced job extraction method specifically for sitemap URLs
     * Focuses on extracting structured data from individual job pages
     */
    private function extract_job_from_url_enhanced($job_url, $source_key, $source_info)
    {
        $job = [
            'id' => md5($job_url),
            'url' => $job_url,
            'source' => $source_key,
            'posted_date' => date('Y-m-d'),
            'time_type' => 'Full-time',
            'via_recruiter' => ($source_info['source_type'] ?? 'recruiter') === 'recruiter'
        ];

        // Extract domain info first
        $url_parts = parse_url($job_url);
        if (isset($url_parts['host'])) {
            $host = str_replace('www.', '', $url_parts['host']);
            $default_company = ucwords(str_replace(['-', '.'], ' ', explode('.', $host)[0]));
            $job['company'] = $default_company;
        }

        // Fetch the job page with better error handling
        $response = wp_remote_get($job_url, [
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ],
            'sslverify' => false,
            'redirection' => 3
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to fetch job page: ' . $job_url . ' - ' . $response->get_error_message());
            return $this->fallback_job_extraction($job, $job_url);
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return $this->fallback_job_extraction($job, $job_url);
        }

        // Primary: Extract JSON-LD structured data (most reliable)
        $structured_job = $this->extract_structured_data_enhanced($html);
        if (!empty($structured_job) && !empty($structured_job['title'])) {
            $job = array_merge($job, $structured_job);
        } else {
            // Secondary: Extract from HTML content
            $html_job = $this->extract_from_html_enhanced($html, $job_url);
            $job = array_merge($job, $html_job);
        }

        // Ensure we have essential fields
        if (empty($job['title'])) {
            $job['title'] = $this->extract_title_from_url($job_url);
        }

        if (empty($job['location'])) {
            $job['location'] = $this->extract_location_from_title($job['title'] ?? '');
            if ($job['location'] === 'Various Locations') {
                $job['location'] = $this->extract_location_from_url($job_url);
            }
        }

        // Extract and enhance skills
        $all_text = implode(' ', [
            $job['title'] ?? '',
            $job['description'] ?? '',
            $job['responsibilities'] ?? '',
            $job['qualifications'] ?? '',
            $job['requirements'] ?? ''
        ]);

        if (!empty($all_text)) {
            $skills = $this->extract_skills_from_description($all_text);
            if (!empty($skills)) {
                $job['skills'] = array_slice($skills, 0, 15); // Limit to 15 skills
            }
        }

        // Set recruiter information for recruiter sources
        if ($job['via_recruiter'] && !empty($source_info['name'])) {
            $job['recruiter_name'] = $source_info['name'];
            
            // If company is generic, use the recruiter name with "Via" prefix
            if (empty($job['company']) || $job['company'] === $default_company) {
                $job['company'] = 'Via ' . $source_info['name'];
            }
        }

        // Salary estimation if no salary found
        if (empty($job['salary']) && empty($job['salary_min'])) {
            $estimated = $this->estimate_salary_from_title($job['title'] ?? '');
            if (!empty($estimated)) {
                $job['estimated_salary'] = $estimated;
            }
        }

        return $job;
    }

    /**
     * Enhanced structured data extraction with better JSON-LD parsing
     */
    private function extract_structured_data_enhanced($html)
    {
        $job_data = [];

        // Look for all JSON-LD scripts (there might be multiple)
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        foreach ($matches[1] as $json_content) {
            $json_ld = json_decode($json_content, true);
            
            if (!$json_ld) {
                continue;
            }

            // Handle array of items
            if (is_array($json_ld) && !isset($json_ld['@type'])) {
                foreach ($json_ld as $item) {
                    if (isset($item['@type']) && $item['@type'] === 'JobPosting') {
                        $job_data = array_merge($job_data, $this->parse_job_posting_schema($item));
                        break;
                    }
                }
            }
            // Handle single item
            elseif (isset($json_ld['@type']) && $json_ld['@type'] === 'JobPosting') {
                $job_data = array_merge($job_data, $this->parse_job_posting_schema($json_ld));
            }
            // Handle @graph structure
            elseif (isset($json_ld['@graph']) && is_array($json_ld['@graph'])) {
                foreach ($json_ld['@graph'] as $item) {
                    if (isset($item['@type']) && $item['@type'] === 'JobPosting') {
                        $job_data = array_merge($job_data, $this->parse_job_posting_schema($item));
                        break;
                    }
                }
            }

            // If we found job data, we're done
            if (!empty($job_data['title'])) {
                break;
            }
        }

        return $job_data;
    }

    /**
     * Parse JobPosting schema data
     */
    private function parse_job_posting_schema($schema)
    {
        $job_data = [];

        // Extract title
        if (!empty($schema['title'])) {
            $job_data['title'] = trim(strip_tags($schema['title']));
        }

        // Extract description with proper cleaning
        if (!empty($schema['description'])) {
            $description = $schema['description'];
            if (strpos($description, '<') !== false) {
                $description = $this->clean_html($description);
            }
            $job_data['description'] = trim($description);

            // Parse sections from description
            $sections = $this->parse_job_sections($description);
            $job_data = array_merge($job_data, $sections);
        }

        // Extract dates
        if (!empty($schema['datePosted'])) {
            $job_data['posted_date'] = date('Y-m-d', strtotime($schema['datePosted']));
        }

        if (!empty($schema['validThrough'])) {
            $job_data['expires_date'] = date('Y-m-d', strtotime($schema['validThrough']));
        }

        // Extract company information
        if (!empty($schema['hiringOrganization'])) {
            $org = $schema['hiringOrganization'];
            if (!empty($org['name'])) {
                $job_data['company'] = trim($org['name']);
            }
        }

        // Extract location with comprehensive parsing
        if (!empty($schema['jobLocation'])) {
            $location_data = $schema['jobLocation'];
            if (is_array($location_data) && !isset($location_data['address'])) {
                $location_data = reset($location_data); // Get first location
            }

            $location_parts = [];
            if (!empty($location_data['address'])) {
                $addr = $location_data['address'];
                if (!empty($addr['addressLocality'])) $location_parts[] = $addr['addressLocality'];
                if (!empty($addr['addressRegion'])) $location_parts[] = $addr['addressRegion'];
                if (!empty($addr['addressCountry'])) $location_parts[] = $addr['addressCountry'];
            } elseif (!empty($location_data['name'])) {
                $location_parts[] = $location_data['name'];
            }

            if (!empty($location_parts)) {
                $job_data['location'] = implode(', ', $location_parts);
            }
        }

        // Extract salary information
        if (!empty($schema['baseSalary'])) {
            $salary = $schema['baseSalary'];
            if (!empty($salary['value'])) {
                $value = $salary['value'];
                if (!empty($value['minValue']) || !empty($value['value'])) {
                    $min = $value['minValue'] ?? $value['value'] ?? 0;
                    $max = $value['maxValue'] ?? $min;
                    $currency = $value['currency'] ?? $salary['currency'] ?? 'USD';

                    $job_data['salary_min'] = $min;
                    $job_data['salary_max'] = $max;
                    $job_data['salary_currency'] = $currency;

                    // Create display format
                    $symbol = $currency === 'GBP' ? '£' : ($currency === 'EUR' ? '€' : '$');
                    if ($min == $max) {
                        $job_data['salary_display'] = $symbol . number_format($min);
                    } else {
                        $job_data['salary_display'] = $symbol . number_format($min) . ' - ' . $symbol . number_format($max);
                    }
                }
            }
        }

        // Extract employment type
        if (!empty($schema['employmentType'])) {
            $types = is_array($schema['employmentType']) ? $schema['employmentType'] : [$schema['employmentType']];
            $job_data['employment_type'] = $types[0];
            $job_data['time_type'] = str_replace(['_', 'FULL_TIME', 'PART_TIME'], ['-', 'Full-time', 'Part-time'], $types[0]);
        }

        return $job_data;
    }

    /**
     * Enhanced HTML extraction as fallback
     */
    private function extract_from_html_enhanced($html, $job_url)
    {
        $job_data = [];

        // Enhanced title extraction for various platforms including Loxo
        $title_patterns = [
            // Standard patterns
            '/<h1[^>]*class=["\'][^"\']*(?:title|job|position)[^"\']*["\'][^>]*>([^<]+)<\/h1>/i',
            '/<h1[^>]*>([^<]+)<\/h1>/i',
            // Title from page title (common for Loxo-style pages)
            '/<title[^>]*>([^<]+?)\s*[-|–]\s*[^<]*<\/title>/i',
            '/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i',
            // Extract from any prominent text that looks like a job title
            '/<(?:h[2-6]|strong|b)[^>]*>([^<]*(?:manager|director|analyst|consultant|advisor|specialist|coordinator|executive|officer|planner)[^<]*)<\/(?:h[2-6]|strong|b)>/i'
        ];

        foreach ($title_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES));
                // Clean up title from page title format
                $title = preg_replace('/\s*[|–-]\s*(Focus Search|Loxo|Jobs?).*$/i', '', $title);
                if (strlen($title) > 5 && strlen($title) < 120) {
                    $job_data['title'] = $title;
                    break;
                }
            }
        }

        // Enhanced location extraction
        $location_patterns = [
            // Standard location patterns
            '/<span[^>]*class=["\'][^"\']*location[^"\']*["\'][^>]*>([^<]+)<\/span>/i',
            '/<div[^>]*class=["\'][^"\']*location[^"\']*["\'][^>]*>([^<]+)<\/div>/i',
            '/(?:Location|Based in|Office)[:\s]*([^<\n\r]+)/i',
            // Loxo-style location patterns (often just city names in content)
            '/(?:^|\n|\s)([A-Z][a-z]+(?:\/[A-Z][a-z]+)*)\s*(?:\n|$)/m',  // Penn/High Wycombe format
            // UK cities pattern
            '/\b(London|Manchester|Birmingham|Leeds|Glasgow|Edinburgh|Dublin|Belfast|Cardiff|Bristol|Liverpool|Sheffield|Newcastle|Nottingham|Leicester|Coventry|Bradford|Wakefield|Hull|Preston|Wolverhampton|Plymouth|Stoke|Derby|Swansea|Southampton|Salford|Westminster|Portsmouth|York|Peterborough|Dundee|Lancaster|Oxford|Cambridge|Canterbury|Bath|Exeter|Chester|Gloucester|Worcester|Hereford|Shrewsbury|Bangor|St Albans|Chichester|Wells|Ripon|Truro|Carlisle|Armagh|Lisburn|Newry|High Wycombe|Penn|Beaconsfield|Marlow|Amersham|Chesham|Aylesbury|Milton Keynes|Slough|Windsor|Maidenhead|Reading|Bracknell|Woking|Guildford|Dorking|Epsom|Kingston|Richmond|Croydon|Bromley|Dartford|Gravesend|Maidstone|Tunbridge Wells|Sevenoaks|Ashford|Dover|Folkestone|Margate|Ramsgate|Deal|Sandwich|Faversham|Sittingbourne|Chatham|Rochester|Gillingham|Medway|Tonbridge|East Grinstead|Crawley|Horsham|Worthing|Brighton|Hove|Eastbourne|Hastings|Bexhill|Battle|Rye|Lewes|Uckfield|Burgess Hill|Haywards Heath|Redhill|Reigate|Banstead|Sutton|Wimbledon|Putney|Fulham|Chelsea|Kensington|Hammersmith|Chiswick|Ealing|Hounslow|Twickenham|Kingston upon Thames|Surbiton|New Malden|Raynes Park|Morden|Mitcham|Thornton Heath|Norbury|Streatham|Tooting|Wandsworth|Clapham|Battersea|Nine Elms|Vauxhall|Kennington|Elephant and Castle|Borough|London Bridge|Tower Bridge|Canary Wharf|Greenwich|Blackheath|Lewisham|Catford|Forest Hill|Sydenham|Crystal Palace|Norwood|West Norwood|Tulse Hill|Brixton|Stockwell|Oval|Waterloo|South Bank|Bermondsey|Rotherhithe|Surrey Quays|Canada Water|Deptford|New Cross|Telegraph Hill|Nunhead|Peckham|Camberwell|Dulwich|East Dulwich|Herne Hill|West Dulwich|Sydenham Hill|Upper Norwood|South Norwood|Selhurst|Addiscombe|Shirley|West Wickham|Beckenham|Penge|Anerley|Elmers End|Eden Park|Hayes|Bromley Common|Bickley|Chislehurst|Sidcup|Bexley|Welling|Erith|Belvedere|Abbey Wood|Plumstead|Woolwich|Eltham|Mottingham|New Eltham|Kidbrooke|Lee|Hither Green|Downham|Grove Park|Sundridge|Orpington|St Mary Cray|St Paul Cray|Swanley|Stone|Greenhithe|Swanscombe|Northfleet)\b/i'
        ];

        foreach ($location_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $location = trim(strip_tags($matches[1]));
                if (strlen($location) > 2 && strlen($location) < 50) {
                    $job_data['location'] = $location;
                    break;
                }
            }
        }

        // Enhanced salary extraction
        $salary_patterns = [
            // Standard ranges
            '/£(\d{1,3}(?:,\d{3})*)\s*(?:-|to|–|—)\s*£?(\d{1,3}(?:,\d{3})*)/i',
            '/\$(\d{1,3}(?:,\d{3})*)\s*(?:-|to|–|—)\s*\$?(\d{1,3}(?:,\d{3})*)/i',
            // K format
            '/£(\d{2,3})k?\s*(?:-|to|–|—)\s*£?(\d{2,3})k/i',
            // Comprehensive salary patterns for various formats
            '/£(\d{1,3}(?:,\d{3})*)\s*(?:base|salary)?(?:\s*,?\s*with.*?)?(?:\s*\([^)]*potential\))?/i',
            // General salary mentions
            '/(?:Salary|Package|Compensation|Base|Annual)[:\s]*([£$€]\d+[k,\d\s-]*(?:base|salary|annual|per annum|pa|p\.a\.)?[^\.]*)/i'
        ];

        foreach ($salary_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $salary_text = trim($matches[0]);
                // Clean up the salary text
                $salary_text = preg_replace('/\s+/', ' ', $salary_text);
                $job_data['salary_display'] = $salary_text;
                
                // Try to extract numeric values
                if (isset($matches[2])) {
                    $min = (int)str_replace([',', 'k', 'K'], ['', '000', '000'], $matches[1]);
                    $max = (int)str_replace([',', 'k', 'K'], ['', '000', '000'], $matches[2]);
                    if ($min > 0 && $max > 0) {
                        $job_data['salary_min'] = $min;
                        $job_data['salary_max'] = $max;
                        $job_data['salary_currency'] = 'GBP';
                    }
                }
                break;
            }
        }

        // Extract job description content
        $description_patterns = [
            // Look for main content areas
            '/<main[^>]*>(.*?)<\/main>/is',
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<div[^>]*class=["\'][^"\']*(?:content|description|job-detail|job-content)[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            // Extract paragraph content that looks like job description
            '/<p[^>]*>(.*?(?:responsibilities|requirements|qualifications|experience|skills).*?)<\/p>/is'
        ];

        foreach ($description_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $description = $this->clean_html($matches[1]);
                if (strlen($description) > 100) {
                    $job_data['description'] = $description;
                    
                    // Try to parse sections from the description
                    $sections = $this->parse_job_sections($description);
                    if (!empty($sections['responsibilities'])) {
                        $job_data['responsibilities'] = $sections['responsibilities'];
                    }
                    if (!empty($sections['qualifications'])) {
                        $job_data['qualifications'] = $sections['qualifications'];
                    }
                    break;
                }
            }
        }

        return $job_data;
    }

    /**
     * Fallback extraction from URL when page fetch fails
     */
    private function fallback_job_extraction($job, $job_url)
    {
        $url_parts = parse_url($job_url);
        
        if (!empty($url_parts['path'])) {
            $path_segments = explode('/', trim($url_parts['path'], '/'));
            $relevant_segment = '';
            
            // Look for the segment that's most likely the job title
            foreach ($path_segments as $segment) {
                if (strlen($segment) > 10 && !is_numeric($segment)) {
                    $relevant_segment = $segment;
                    break;
                }
            }
            
            if (!empty($relevant_segment)) {
                $title = str_replace(['-', '_'], ' ', $relevant_segment);
                $title = preg_replace('/\d+/', '', $title); // Remove numbers
                $title = ucwords(trim($title));
                
                if (strlen($title) > 5) {
                    $job['title'] = $title;
                }
            }
        }

        return $job;
    }

    /**
     * Extract location from URL patterns
     */
    private function extract_location_from_url($url)
    {
        $common_locations = [
            'london', 'newyork', 'nyc', 'sanfrancisco', 'chicago', 'boston', 
            'losangeles', 'singapore', 'hongkong', 'tokyo', 'paris', 
            'frankfurt', 'dubai', 'sydney', 'toronto', 'mumbai', 'bangalore'
        ];

        $url_lower = strtolower($url);
        foreach ($common_locations as $location) {
            if (strpos($url_lower, $location) !== false) {
                return ucwords(str_replace(['newyork', 'nyc', 'sanfrancisco', 'hongkong'], 
                                        ['New York', 'New York', 'San Francisco', 'Hong Kong'], $location));
            }
        }

        return 'Various Locations';
    }

    /**
     * Extract title from URL as last resort
     */
    private function extract_title_from_url($url)
    {
        $url_parts = parse_url($url);
        if (!empty($url_parts['path'])) {
            $path_segments = explode('/', trim($url_parts['path'], '/'));
            $last_segment = end($path_segments);
            
            // Clean up the segment
            $title = preg_replace('/\.(html?|php|aspx?)$/i', '', $last_segment);
            
            // Special handling for URLs with embedded job IDs (like Piper Maddox)
            // Pattern: 123456jobtitlehere -> extract just the job title part
            if (preg_match('/^(\d+)([a-zA-Z].*)$/', $title, $matches)) {
                $title = $matches[2]; // Use the text part after the number
            } else {
                // For other formats, remove standalone numbers at word boundaries
                $title = preg_replace('/\b\d+\b/', '', $title);
            }
            
            $title = str_replace(['-', '_'], ' ', $title);
            
            // Add spaces before capital letters (for camelCase/PascalCase)
            $title = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $title);
            
            // For all-lowercase compound words, try to break them up using common patterns
            if (strtolower($title) === $title) {
                // Add spaces before common job title patterns
                $patterns = [
                    '/manager(?=\w)/' => 'manager ',
                    '/director(?=\w)/' => 'director ',
                    '/senior(?=\w)/' => 'senior ',
                    '/junior(?=\w)/' => 'junior ',
                    '/vice(?=president)/' => 'vice ',
                    '/principal(?=\w)/' => 'principal ',
                    '/project(?=\w)/' => 'project ',
                    '/business(?=\w)/' => 'business ',
                    '/technical(?=\w)/' => 'technical ',
                    '/software(?=\w)/' => 'software ',
                    '/systems(?=\w)/' => 'systems ',
                    '/finance(?=\w)/' => 'finance ',
                    '/energy(?=\w)/' => 'energy ',
                    '/electrical(?=\w)/' => 'electrical ',
                    '/engineer(?=\w)/' => 'engineer ',
                    '/analyst(?=\w)/' => 'analyst ',
                    '/consultant(?=\w)/' => 'consultant ',
                    '/specialist(?=\w)/' => 'specialist ',
                    '/coordinator(?=\w)/' => 'coordinator ',
                    '/development(?=\w)/' => 'development ',
                    '/regulatory(?=\w)/' => 'regulatory ',
                    '/compliance(?=\w)/' => 'compliance ',
                    '/policy(?=\w)/' => 'policy ',
                    '/technician(?=\w)/' => 'technician ',
                    '/grid(?=\w)/' => 'grid ',
                    '/retainer(?=\w)/' => 'retainer ',
                    '/spain(?=\w)/' => 'spain ',
                    '/italy(?=\w)/' => 'italy ',
                ];
                
                foreach ($patterns as $pattern => $replacement) {
                    $title = preg_replace($pattern, $replacement, $title);
                }
            }
            
            $title = ucwords(trim($title));
            
            if (strlen($title) > 5) {
                return $title;
            }
        }

        return 'Job Opportunity';
    }

    /**
     * Extract job info from URL patterns
     */
    private function extract_job_info_from_url($job, $source_key)
    {
        $url = $job['url'];

        switch ($source_key) {
            case 'pearse_partners':
                // Pattern: /cariera/director-healthcare-ma-paris/
                if (preg_match('/\/cariera\/([^\/]+)\//', $url, $matches)) {
                    $job['title'] = $this->humanize_slug($matches[1]);
                }
                break;
        }

        // Set company based on source
        $job['company'] = $this->get_company_from_source($source_key);

        return $job;
    }

    /**
     * Enrich job with data from the actual job page
     */
    private function enrich_job_with_page_data($job)
    {
        // Fetch the job page
        $response = wp_remote_get($job['url'], [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to fetch job page: ' . $job['url'] . ' - ' . $response->get_error_message());
            return $job;
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return $job;
        }

        // Extract JSON-LD structured data
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            $json_ld = json_decode($matches[1], true);
            if ($json_ld && isset($json_ld['@type']) && $json_ld['@type'] === 'JobPosting') {
                // Extract structured data
                $job['title'] = $json_ld['title'] ?? $job['title'];

                // Get FULL description
                if (isset($json_ld['description'])) {
                    $description = $this->clean_html($json_ld['description']);
                    $job['description'] = $description;

                    // Parse sections
                    $parsed = $this->parse_job_sections($description);
                    if (!empty($parsed['responsibilities'])) {
                        $job['responsibilities'] = $parsed['responsibilities'];
                    }
                    if (!empty($parsed['qualifications'])) {
                        $job['qualifications'] = $parsed['qualifications'];
                    }
                    if (!empty($parsed['requirements'])) {
                        $job['requirements'] = $parsed['requirements'];
                    }

                    // Extract skills
                    $skills = $this->extract_skills_from_description($description);
                    if (!empty($skills)) {
                        $job['skills'] = $skills;
                    }
                }

                $job['posted_date'] = isset($json_ld['datePosted']) ?
                    date('Y-m-d', strtotime($json_ld['datePosted'])) : $job['posted_date'];

                // Extract salary
                if (isset($json_ld['baseSalary'])) {
                    $job['salary'] = $this->extract_salary_from_structured_data($json_ld['baseSalary']);
                } elseif (isset($json_ld['jobBenefits']) && strpos($json_ld['jobBenefits'], '£') !== false) {
                    $job['salary'] = $json_ld['jobBenefits'];
                }

                // Extract location
                if (isset($json_ld['jobLocation'])) {
                    $job['location'] = $this->extract_location_from_structured_data($json_ld['jobLocation']);
                }

                // Extract company
                if (isset($json_ld['hiringOrganization']['name'])) {
                    $job['company'] = $json_ld['hiringOrganization']['name'];
                }

                // Extract employment type
                if (isset($json_ld['employmentType'])) {
                    $job['employment_type'] = is_array($json_ld['employmentType']) ?
                        implode(', ', $json_ld['employmentType']) : $json_ld['employmentType'];
                }

                // Extract qualifications if separate field
                if (isset($json_ld['qualifications'])) {
                    $job['qualifications'] = $json_ld['qualifications'];
                }

                // Extract responsibilities if separate field  
                if (isset($json_ld['responsibilities'])) {
                    $job['responsibilities'] = $json_ld['responsibilities'];
                }
            }
        }

        // If no JSON-LD, try to extract from HTML
        if (empty($job['description'])) {
            $job = $this->extract_from_html_content($html, $job);
        }

        // Extract from meta tags as fallback
        if (empty($job['title'])) {
            if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $matches)) {
                $job['title'] = html_entity_decode($matches[1]);
            }
        }

        if (empty($job['description'])) {
            if (preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $matches)) {
                $job['description'] = html_entity_decode($matches[1]);
            }
        }

        // Extract salary from content if not in structured data
        if (empty($job['salary'])) {
            $job['salary'] = $this->extract_salary_from_html($html);
        }

        return $job;
    }

    /**
     * Extract job content from HTML when no JSON-LD available
     */
    private function extract_from_html_content($html, $job)
    {
        // Try to get the main content area first
        $main_content = '';

        // Try to extract main/article content
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            $main_content = $matches[1];
        } elseif (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            $main_content = $matches[1];
        } else {
            $main_content = $html;
        }

        // Common job description selectors - look for larger blocks
        $description_selectors = [
            // Look for specific job description containers
            '/<div[^>]*(?:class|id)="[^"]*job[_-]?description[^"]*"[^>]*>(.*?)(?=<div[^>]*(?:class|id)="[^"]*(?:apply|share|related)|<footer|$)/is',
            '/<div[^>]*(?:class|id)="[^"]*job[_-]?content[^"]*"[^>]*>(.*?)(?=<div[^>]*(?:class|id)="[^"]*(?:apply|share|related)|<footer|$)/is',
            '/<section[^>]*(?:class|id)="[^"]*job[^"]*"[^>]*>(.*?)<\/section>/is',
            // Look for content between headers
            '/(?:Job Description|Description|About the role|The Role|Overview)[:\s]*<\/?\w+[^>]*>(.*?)(?=(?:Requirements|Qualifications|Apply|Share|<h[1-3]|$))/is',
            // Fallback to any large text block
            '/<div[^>]*class="[^"]*(?:content|description|details)[^"]*"[^>]*>((?:[^<]|<(?!\/div))*(?:<div[^>]*>(?:[^<]|<(?!\/div))*<\/div>)*(?:[^<]|<(?!\/div))*)<\/div>/is'
        ];

        $content_to_search = !empty($main_content) ? $main_content : $html;

        foreach ($description_selectors as $selector) {
            if (preg_match($selector, $content_to_search, $matches)) {
                $content = $this->clean_html($matches[1]);
                if (strlen($content) > 100) { // Make sure we got real content
                    $job['description'] = $content;

                    // Parse sections
                    $parsed = $this->parse_job_sections($content);
                    if (!empty($parsed['responsibilities'])) {
                        $job['responsibilities'] = $parsed['responsibilities'];
                    }
                    if (!empty($parsed['qualifications'])) {
                        $job['qualifications'] = $parsed['qualifications'];
                    }

                    // Extract skills
                    $skills = $this->extract_skills_from_description($content);
                    if (!empty($skills)) {
                        $job['skills'] = $skills;
                    }

                    break;
                }
            }
        }

        return $job;
    }

    /**
     * Clean HTML content
     */
    private function clean_html($html)
    {
        // Remove scripts and styles
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

        // Convert breaks and paragraphs to newlines
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/li>/i', "\n", $html);
        $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);

        // Remove HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Parse job description into sections
     */
    private function parse_job_sections($text)
    {
        $sections = [
            'overview' => '',
            'responsibilities' => '',
            'qualifications' => '',
            'requirements' => ''
        ];

        // Common section headers
        $patterns = [
            'responsibilities' => '/(?:responsibilities|duties|what you.?ll do|role|key activities|accountabilities|the role)/i',
            'qualifications' => '/(?:qualifications|what we.?re looking for|ideal candidate|about you|requirements)/i',
            'requirements' => '/(?:required|must have|essential|minimum|skills and experience)/i'
        ];

        $lines = explode("\n", $text);
        $current_section = 'overview';
        $section_content = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $found_section = false;
            foreach ($patterns as $section => $pattern) {
                if (preg_match($pattern, $line)) {
                    if (!empty($section_content)) {
                        $sections[$current_section] = implode("\n", $section_content);
                    }
                    $current_section = $section;
                    $section_content = [];
                    $found_section = true;
                    break;
                }
            }

            if (!$found_section) {
                $section_content[] = $line;
            }
        }

        if (!empty($section_content)) {
            $sections[$current_section] = implode("\n", $section_content);
        }

        return $sections;
    }

    /**
     * Extract skills from job description
     */
    private function extract_skills_from_description($text)
    {
        $skills = [];

        // Comprehensive finance skills for UK/EU market - SAME AS WORKDAY FETCHER
        $technical_skills = [
            // Programming & Data
            'Python',
            'R',
            'SQL',
            'VBA',
            'MATLAB',
            'SAS',
            'Stata',
            'SPSS',
            'C\+\+',
            'C#',
            'Java',
            'JavaScript',
            'Julia',
            'Scala',
            'Go',
            'Rust',
            'TypeScript',
            'PHP',
            'Ruby',
            'Perl',
            'Shell',
            'Bash',
            'PowerShell',
            'NoSQL',
            'MongoDB',
            'PostgreSQL',
            'MySQL',
            'Oracle SQL',
            'T-SQL',
            'PL/SQL',
            'Hadoop',
            'Spark',

            // Microsoft Office & Productivity
            'Excel',
            'PowerPoint',
            'Word',
            'Outlook',
            'Access',
            'Project',
            'Visio',
            'OneNote',
            'Teams',
            'Advanced Excel',
            'VBA Macros',
            'Power Query',
            'Power Pivot',
            'Google Sheets',
            'Google Suite',

            // Financial Data Platforms
            'Bloomberg Terminal',
            'Bloomberg',
            'Reuters',
            'Refinitiv',
            'Eikon',
            'FactSet',
            'Capital IQ',
            'S&P Capital IQ',
            'Morningstar Direct',
            'Pitchbook',
            'Preqin',
            'Dealogic',
            'ThomsonOne',
            'QUICK',
            'Fidessa',
            'Charles River',
            'SimCorp',
            'Murex',
            'Calypso',
            'Summit',
            'Sophis',
            'Numerix',
            'MSCI Barra',
            'RiskMetrics',
            'Axioma',
            'BlackRock Aladdin',
            'Aladdin',

            // Business Intelligence & Analytics
            'Tableau',
            'Power BI',
            'QlikView',
            'Qlik Sense',
            'Looker',
            'Alteryx',
            'Spotfire',
            'SAS',
            'IBM Cognos',
            'MicroStrategy',
            'Domo',
            'Sisense',
            'ThoughtSpot',
            'DataRobot',
            'H2O.ai',

            // ERP & Accounting Systems
            'SAP',
            'Oracle',
            'NetSuite',
            'Workday',
            'Microsoft Dynamics',
            'Salesforce',
            'HubSpot',
            'QuickBooks',
            'Xero',
            'Sage',
            'FreshBooks',
            'Wave',
            'Zoho Books',
            'MYOB',
            'Intuit',
            'PeopleSoft',
            'JD Edwards',
            'Hyperion',
            'Anaplan',
            'Adaptive Insights',
            'OneStream',

            // Financial Concepts & Techniques
            'DCF',
            'Discounted Cash Flow',
            'LBO',
            'Leveraged Buyout',
            'M&A',
            'Mergers and Acquisitions',
            'Valuation',
            'Financial Modeling',
            'Financial Modelling',
            'Three Statement Model',
            'Risk Management',
            'Portfolio Management',
            'Asset Management',
            'Wealth Management',
            'Investment Management',
            'Fund Management',
            'Hedge Fund',
            'Private Equity',
            'Venture Capital',
            'Investment Banking',
            'Corporate Finance',
            'Project Finance',
            'Structured Finance',
            'Trade Finance',
            'Treasury Management',
            'Cash Management',
            'Working Capital Management',
            'Credit Analysis',
            'Credit Risk',
            'Market Risk',
            'Operational Risk',
            'Liquidity Risk',
            'Counterparty Risk',
            'Regulatory Risk',
            'Compliance Risk',
            'Basel III',
            'Basel IV',
            'IFRS',
            'GAAP',
            'UK GAAP',
            'US GAAP',
            'SOX',
            'Sarbanes-Oxley',
            'MiFID II',
            'MiFID',
            'AIFMD',
            'UCITS',
            'Solvency II',
            'CRD IV',
            'CRD V',
            'EMIR',
            'SFTR',
            'CSDR',
            'Due Diligence',
            'KYC',
            'AML',
            'Anti-Money Laundering',
            'Know Your Customer',
            'Underwriting',
            'Origination',
            'Syndication',
            'Securitization',
            'Securitisation',
            'Derivatives',
            'Options',
            'Futures',
            'Swaps',
            'FX',
            'Forex',
            'Foreign Exchange',
            'Fixed Income',
            'Equities',
            'Commodities',
            'Credit',
            'Rates',
            'Bonds',
            'Stocks',
            'ETFs',
            'Mutual Funds',
            'Alternative Investments',
            'Real Estate',
            'Infrastructure',
            'Distressed Debt',
            'High Yield',
            'Investment Grade',
            'Emerging Markets',
            'Frontier Markets',
            'ESG',
            'Sustainable Finance',
            'Green Finance',
            'Impact Investing',
            'SRI',
            'Responsible Investing',
            'Quantitative Analysis',
            'Quantitative Finance',
            'Quant',
            'Algorithmic Trading',
            'Algo Trading',
            'High Frequency Trading',
            'HFT',
            'Market Making',
            'Arbitrage',
            'Statistical Arbitrage',
            'Pairs Trading',
            'Mean Reversion',
            'Momentum Trading',
            'Factor Investing',
            'Smart Beta',
            'Black-Scholes',
            'Monte Carlo',
            'Monte Carlo Simulation',
            'VaR',
            'Value at Risk',
            'CVaR',
            'Expected Shortfall',
            'Stress Testing',
            'Scenario Analysis',
            'Sensitivity Analysis',
            'Greeks',
            'Delta',
            'Gamma',
            'Vega',
            'Theta',
            'Rho',
            'Duration',
            'Convexity',
            'DV01',
            'ALM',
            'Asset Liability Management',
            'Capital Allocation',
            'RAROC',
            'RORAC',
            'EVA',
            'Transfer Pricing',
            'Fund Transfer Pricing',
            'FTP',
            'Collateral Management',
            'Prime Brokerage',
            'Securities Lending',
            'Repo',
            'Reverse Repo',
            'Stock Loan',

            // UK/EU Specific Terms
            'FCA',
            'PRA',
            'Bank of England',
            'ECB',
            'European Central Bank',
            'BaFin',
            'AMF',
            'CSSF',
            'CBI',
            'DNB',
            'FINMA',
            'FSA',
            'LSE',
            'London Stock Exchange',
            'Euronext',
            'Deutsche Börse',
            'SIX Swiss Exchange',
            'Borsa Italiana',
            'BME',
            'OMX',
            'Nasdaq Nordic',
            'FTSE',
            'FTSE 100',
            'FTSE 250',
            'DAX',
            'CAC 40',
            'STOXX',
            'Euro Stoxx',
            'SMI',
            'AIM',
            'Alternative Investment Market',
            'Main Market',
            'Premium Listing',
            'Standard Listing',
            'Prospectus Directive',
            'Market Abuse Regulation',
            'MAR',
            'PRIIPs',
            'GDPR',
            'Brexit',
            'Passporting',
            'Equivalence',
            'Third Country',
            'QIAIF',
            'ICAV',
            'OEIC',
            'Unit Trust',
            'Investment Trust',
            'SICAV',
            'FCP',
            'RAIF',
            'SIF',
            'ELTIF',
            'Gilt',
            'Gilts',
            'Bund',
            'Bunds',
            'OAT',
            'BTP',
            'Bonos',
            'T-Bill',
            'Treasury Bill',

            // Professional Qualifications
            'CFA',
            'Chartered Financial Analyst',
            'FRM',
            'Financial Risk Manager',
            'CAIA',
            'Chartered Alternative Investment Analyst',
            'PRM',
            'Professional Risk Manager',
            'CPA',
            'Certified Public Accountant',
            'ACCA',
            'Association of Chartered Certified Accountants',
            'ACA',
            'Associate Chartered Accountant',
            'ICAEW',
            'CIMA',
            'Chartered Institute of Management Accountants',
            'CIPFA',
            'Chartered Institute of Public Finance and Accountancy',
            'CA',
            'Chartered Accountant',
            'CTA',
            'Chartered Tax Adviser',
            'TEP',
            'Trust and Estate Practitioner',
            'IMC',
            'Investment Management Certificate',
            'IOC',
            'Investment Operations Certificate',
            'IAD',
            'International Advanced Diploma',
            'CISI',
            'Chartered Institute for Securities & Investment',
            'CII',
            'Chartered Insurance Institute',
            'ACII',
            'Associate of the Chartered Insurance Institute',
            'APMI',
            'Associate of the Pensions Management Institute',
            'PMI',
            'Pensions Management Institute',
            'Series 7',
            'Series 63',
            'Series 65',
            'Series 66',
            'Series 79',
            'Series 86',
            'Series 87',
            'SII',
            'Securities Industry Institute',
            'CeMAP',
            'Certificate in Mortgage Advice and Practice',
            'DipFA',
            'Diploma for Financial Advisers',
            'PRINCE2',
            'PMP',
            'Project Management Professional',
            'Agile',
            'Scrum',
            'Six Sigma',
            'Lean',
            'Lean Six Sigma',
            'ITIL',
            'COBIT',

            // Languages (important for EU roles)
            'English',
            'French',
            'German',
            'Spanish',
            'Italian',
            'Dutch',
            'Portuguese',
            'Polish',
            'Russian',
            'Mandarin',
            'Cantonese',
            'Japanese',
            'Arabic',
            'Hindi',
            'Korean',
            'Fluent English',
            'Native English',
            'Business English',
            'Fluent French',
            'Fluent German',
            'Multilingual',
            'Bilingual',
            'Native Speaker'
        ];

        foreach ($technical_skills as $skill) {
            if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text)) {
                $skills[] = $skill;
            }
        }

        return array_unique($skills);
    }

    /**
     * Extract basic skills from job title (used as fallback)
     */
    private function extract_basic_skills_from_title($title)
    {
        $skills = [];
        $title_lower = strtolower($title);

        // Basic skill mapping from title keywords
        $skill_mappings = [
            'python' => 'Python',
            'sql' => 'SQL',
            'excel' => 'Excel',
            'vba' => 'VBA',
            'tableau' => 'Tableau',
            'power bi' => 'Power BI',
            'bloomberg' => 'Bloomberg',
            'factset' => 'FactSet',
            'capital iq' => 'Capital IQ',
            'financial modeling' => 'Financial Modeling',
            'valuation' => 'Valuation',
            'dcf' => 'DCF',
            'lbo' => 'LBO',
            'm&a' => 'M&A',
            'private equity' => 'Private Equity',
            'investment banking' => 'Investment Banking',
            'hedge fund' => 'Hedge Fund',
            'asset management' => 'Asset Management',
            'risk management' => 'Risk Management',
            'portfolio management' => 'Portfolio Management'
        ];

        foreach ($skill_mappings as $keyword => $skill) {
            if (strpos($title_lower, $keyword) !== false) {
                $skills[] = $skill;
            }
        }

        return array_unique($skills);
    }

    /**
     * Extract salary from structured data
     */
    private function extract_salary_from_structured_data($salary_data)
    {
        if (is_string($salary_data)) {
            return $salary_data;
        }

        if (is_array($salary_data)) {
            if (isset($salary_data['value'])) {
                $value = $salary_data['value'];
                if (isset($value['minValue']) && isset($value['maxValue'])) {
                    $currency = $value['currency'] ?? 'GBP';
                    $symbol = $currency === 'GBP' ? '£' : '$';
                    return $symbol . number_format($value['minValue']) . ' - ' . $symbol . number_format($value['maxValue']);
                }
            }
        }

        return '';
    }

    /**
     * Extract location from structured data
     */
    private function extract_location_from_structured_data($location_data)
    {
        if (is_string($location_data)) {
            return $location_data;
        }

        if (is_array($location_data)) {
            if (isset($location_data['address'])) {
                $address = $location_data['address'];
                $parts = [];

                if (isset($address['addressLocality'])) {
                    $parts[] = $address['addressLocality'];
                }
                if (isset($address['addressRegion'])) {
                    $parts[] = $address['addressRegion'];
                }
                if (isset($address['addressCountry'])) {
                    $parts[] = $address['addressCountry'];
                }

                return implode(', ', $parts);
            }
        }

        return '';
    }

    /**
     * Extract salary from HTML content
     */
    private function extract_salary_from_html($html)
    {
        // Common salary patterns
        $patterns = [
            '/£(\d{1,3},?\d{3})\s*-\s*£(\d{1,3},?\d{3})/',
            '/\$(\d{1,3},?\d{3})\s*-\s*\$(\d{1,3},?\d{3})/',
            '/£(\d{2,3})k\s*-\s*£(\d{2,3})k/',
            '/\$(\d{2,3})k\s*-\s*\$(\d{2,3})k/',
            '/Salary:\s*([£\$][\d,]+\s*-\s*[£\$][\d,]+)/',
            '/Base:\s*([£\$][\d,]+)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $matches[0];
            }
        }

        return '';
    }

    /**
     * Convert URL slug to human readable title
     */
    private function humanize_slug($slug)
    {
        $title = str_replace('-', ' ', $slug);
        $title = ucwords($title);

        // Fix common abbreviations
        $replacements = [
            ' Pe ' => ' PE ',
            ' Ma ' => ' M&A ',
            ' Cfo ' => ' CFO ',
            ' Ceo ' => ' CEO ',
            ' Vp ' => ' VP ',
            ' Uk ' => ' UK ',
            ' Us ' => ' US ',
            ' Ai ' => ' AI '
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $title);
    }

    /**
     * Get company name from source
     */
    private function get_company_from_source($source_key)
    {
        // For recruitment firms, the actual hiring company varies
        // This would be extracted from the job page
        $recruiters = [
            'pearse_partners' => 'Via Pearse Partners',
            'barton_partnership' => 'Via Barton Partnership',
            'dartmouth_partners' => 'Via Dartmouth Partners'
        ];

        return $recruiters[$source_key] ?? 'Finance Industry';
    }

    /**
     * Get jobs matched to user profile
     */
    public function get_jobs_for_profile($profile)
    {
        $matched_jobs = [];

        // Determine which sources to prioritize based on profile
        $sources_to_check = $this->get_sources_for_profile($profile);

        foreach ($sources_to_check as $source_key) {
            $jobs = $this->fetch_jobs_from_source($source_key, 20);

            foreach ($jobs as $job) {
                // Basic matching
                if ($this->job_matches_profile($job, $profile)) {
                    $matched_jobs[] = $job;
                }
            }
        }

        return $matched_jobs;
    }

    /**
     * Get relevant sources based on user profile
     */
    private function get_sources_for_profile($profile)
    {
        $sources = [];

        // Check desired roles
        if (isset($profile['desired_roles'])) {
            foreach ($profile['desired_roles'] as $role) {
                if (stripos($role, 'PE') !== false || stripos($role, 'Private Equity') !== false) {
                    $sources[] = 'pearse_partners';
                }
                if (stripos($role, 'VC') !== false || stripos($role, 'Venture') !== false) {
                    $sources[] = 'venture_capital_careers';
                }
                if (stripos($role, 'Banking') !== false) {
                    $sources[] = 'dartmouth_partners';
                    $sources[] = 'barton_partnership';
                }
            }
        }

        // Check locations
        if (isset($profile['preferred_locations'])) {
            foreach ($profile['preferred_locations'] as $location) {
                if (stripos($location, 'UK') !== false || stripos($location, 'London') !== false) {
                    $sources[] = 'gaap_web';
                }
                if (stripos($location, 'Dubai') !== false || stripos($location, 'private equity') !== false) {
                    $sources[] = 'wuzzuf';
                }
            }
        }

        // Remove duplicates and return
        return array_unique($sources);
    }

    /**
     * Check if job matches user profile
     */
    private function job_matches_profile($job, $profile)
    {
        $match = false;

        // Check title match
        if (isset($profile['desired_roles']) && isset($job['title'])) {
            foreach ($profile['desired_roles'] as $role) {
                if (stripos($job['title'], $role) !== false) {
                    $match = true;
                    break;
                }
            }
        }

        // Check location match
        if (isset($profile['preferred_locations']) && isset($job['location'])) {
            foreach ($profile['preferred_locations'] as $location) {
                if (stripos($job['location'], $location) !== false) {
                    $match = true;
                    break;
                }
            }
        }

        return $match;
    }

    /**
     * Fetch jobs from a specific source URL
     * Public method for feed manager
     */
    public function fetch_from_source($url, $limit = 10)
    {
        $jobs = [];

        if (strpos($url, '.icims.com/jobs/search') !== false) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $is_first_group = strpos($host, 'careers-thefirstgroup') !== false;
            $is_shamal = strpos($host, 'careers-shamal') !== false;
            return $this->fetch_icims_search_jobs('icims_custom_' . sanitize_key($host), [
                'url' => $url,
                'name' => $is_first_group ? 'The First Group Careers' : ($is_shamal ? 'Shamal Careers' : 'iCIMS Careers'),
                'company_name' => $is_first_group ? 'The First Group' : ($is_shamal ? 'Shamal' : ''),
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'iCIMS',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Sharjah'],
            ], $limit);
        }

        if (strpos($url, 'ats.nichehrglobal.com/careers') !== false) {
            return $this->fetch_nichehr_supabase_jobs('nichehr_global_custom', [
                'url' => 'https://ats.nichehrglobal.com/careers',
                'api_url' => 'https://sxfmreydtmwwdrdfhbja.supabase.co/rest/v1/jobs?select=*&status=eq.OPEN&order=created_at.desc',
                'name' => 'NicheHR Global Careers',
                'company_name' => 'NicheHR Global',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'NicheHR',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh', 'Doha', 'Qatar'],
            ], $limit);
        }

        if (strpos($url, 'ezra.world/company/careers') !== false) {
            return $this->fetch_greenhouse_jobs('ezra_custom', [
                'url' => 'https://boards-api.greenhouse.io/v1/boards/ezra/jobs?content=true',
                'name' => 'Ezra Careers',
                'company_name' => 'Ezra',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Greenhouse',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh', 'Doha', 'Qatar'],
            ], $limit);
        }

        if (strpos($url, 'careers.chalhoubgroup.com/jobs') !== false) {
            $rss_url = 'https://careers.chalhoubgroup.com/jobs.rss';
            $name = 'Chalhoub Group Careers';
            $category = 'Job aggregators';
            $allowed_locations = ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh', 'Jeddah', 'Dammam', 'Khobar'];

            if (stripos($url, 'department=ACCOUNTING') !== false && stripos($url, 'country=Saudi') !== false) {
                $rss_url = 'https://careers.chalhoubgroup.com/jobs.rss?department=ACCOUNTING&country=Saudi+Arabia';
                $name = 'Chalhoub Group Accounting Saudi Arabia';
                $category = 'Accounting';
                $allowed_locations = ['Saudi Arabia', 'Riyadh', 'Jeddah', 'Dammam', 'Khobar'];
            } elseif ((stripos($url, 'FINANCE+%26+TREASURY') !== false || stripos($url, 'FINANCE%20%26%20TREASURY') !== false) && stripos($url, 'United+Arab+Emirates') !== false) {
                $rss_url = 'https://careers.chalhoubgroup.com/jobs.rss?department=FINANCE+%26+TREASURY&country=United+Arab+Emirates';
                $name = 'Chalhoub Group Finance UAE';
                $category = 'Finance & Treasury';
                $allowed_locations = ['United Arab Emirates', 'Dubai', 'Abu Dhabi'];
            }

            return $this->fetch_teamtailor_rss_jobs('chalhoub_group_custom', [
                'url' => $url,
                'rss_url' => $rss_url,
                'name' => $name,
                'company_name' => 'Chalhoub Group',
                'category' => $category,
                'source_type' => 'job_aggregator',
                'source_platform' => 'Teamtailor',
                'allowed_locations' => $allowed_locations,
            ], $limit);
        }

        if (strpos($url, '/hcmUI/CandidateExperience') !== false && strpos($url, 'oraclecloud.com') !== false) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $site_number = $this->extract_oracle_cx_site_number($url);
            $api_base_url = $this->extract_oracle_cx_api_base_url($url);
            $company_name = strpos($host, 'fa-evlo-saasfaprod1') !== false ? 'Emirates NBD' : '';
            return $this->fetch_oracle_cx_jobs('oracle_cx_custom_' . sanitize_key($host . '_' . $site_number), [
                'url' => $url,
                'name' => $company_name !== '' ? $company_name . ' Careers' : 'Oracle CX Careers',
                'company_name' => $company_name,
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'api_base_url' => $api_base_url,
                'site_number' => $site_number,
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, '/search/') !== false && (strpos($url, 'careers.fitch.group') !== false || strpos($url, 'careers.nbf.ae') !== false)) {
            $is_nbf = strpos($url, 'careers.nbf.ae') !== false;
            return $this->fetch_successfactors_jobs($is_nbf ? 'nbf_successfactors_custom' : 'fitch_group_custom', [
                'url' => $url,
                'name' => $is_nbf ? 'National Bank of Fujairah Careers' : 'Fitch Group Dubai Careers',
                'company_name' => $is_nbf ? 'National Bank of Fujairah' : 'Fitch Group',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'SAP SuccessFactors',
                'force_company_name' => true,
                'use_unified_api' => $is_nbf,
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Fujairah'],
            ], $limit);
        }

        if (strpos($url, 'job-boards.greenhouse.io/teneolinkedin') !== false || strpos($url, 'boards-api.greenhouse.io/v1/boards/teneolinkedin/jobs') !== false) {
            return $this->fetch_greenhouse_jobs('teneo_middle_east_custom', [
                'url' => 'https://boards-api.greenhouse.io/v1/boards/teneolinkedin/jobs?content=true',
                'name' => 'Teneo Middle East Careers',
                'company_name' => 'Teneo',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Greenhouse',
                'company_logo' => 'https://s3-recruiting.cdn.greenhouse.io/external_greenhouse_job_boards/logos/400/157/100/original/Teneo_Logo_Full_Color_(3).jpg?1584373837',
                'allowed_locations' => ['Dubai', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'job-boards.greenhouse.io/alphafmcroles') !== false || strpos($url, 'boards-api.greenhouse.io/v1/boards/alphafmcroles/jobs') !== false) {
            return $this->fetch_greenhouse_jobs('alpha_fmc_middle_east_custom', [
                'url' => 'https://boards-api.greenhouse.io/v1/boards/alphafmcroles/jobs?content=true',
                'name' => 'Alpha FMC Middle East Careers',
                'company_name' => 'Alpha Financial Markets Consulting',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Greenhouse',
                'company_logo' => 'https://s2-recruiting.cdn.greenhouse.io/external_greenhouse_job_boards/logos/401/165/900/original/Alpha_Group_Colour_RGB.png?1785147933',
                'allowed_locations' => ['Dubai', 'Riyadh', 'Doha'],
            ], $limit);
        }

        // Check if this is a Greenhouse API URL
        if (strpos($url, 'boards-api.greenhouse.io') !== false) {
            return $this->fetch_greenhouse_jobs_by_url($url, $limit);
        }

        if (strpos($url, 'jobs.lever.co/') !== false || strpos($url, 'api.lever.co/v0/postings/') !== false) {
            $company_id = '';
            if (preg_match('#(?:jobs\.lever\.co|api\.lever\.co/v0/postings)/([^/?#]+)#i', $url, $matches)) {
                $company_id = trim(rawurldecode($matches[1]));
            }

            if ($company_id !== '') {
                return $this->fetch_lever_jobs('lever_custom_' . sanitize_key($company_id), [
                    'url' => 'https://jobs.lever.co/' . $company_id,
                    'api_url' => 'https://api.lever.co/v0/postings/' . rawurlencode($company_id) . '?mode=json',
                    'name' => ucwords(str_replace(['-', '_'], ' ', $company_id)) . ' Careers',
                    'company_name' => ucwords(str_replace(['-', '_'], ' ', $company_id)),
                    'category' => 'Job aggregators',
                    'source_type' => 'job_aggregator',
                    'source_platform' => 'Lever',
                    'company_id' => $company_id,
                ], $limit);
            }
        }

        if (strpos($url, 'jobs.ashbyhq.com/') !== false) {
            $company_id = trim((string) basename(parse_url($url, PHP_URL_PATH) ?: ''), '/');
            if ($company_id !== '') {
                return $this->fetch_ashby_jobs('ashby_custom_' . sanitize_key($company_id), [
                    'url' => 'https://jobs.ashbyhq.com/' . $company_id,
                    'name' => ucwords(str_replace(['-', '_'], ' ', $company_id)) . ' Careers',
                    'company_name' => ucwords(str_replace(['-', '_'], ' ', $company_id)),
                    'category' => 'Job aggregators',
                    'source_type' => 'job_aggregator',
                    'source_platform' => 'Ashby',
                    'company_id' => $company_id,
                ], $limit);
            }
        }

        if (strpos($url, '.recruitee.com') !== false) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $company_id = preg_replace('/\.recruitee\.com$/i', '', $host);
            if ($host !== '' && $company_id !== '') {
                return $this->fetch_recruitee_jobs('recruitee_custom_' . sanitize_key($company_id), [
                    'url' => 'https://' . $host . '/',
                    'api_url' => 'https://' . $host . '/api/offers',
                    'name' => ucwords(str_replace(['-', '_'], ' ', $company_id)) . ' Careers',
                    'company_name' => ucwords(str_replace(['-', '_'], ' ', $company_id)),
                    'category' => 'Job aggregators',
                    'source_type' => 'job_aggregator',
                    'source_platform' => 'Recruitee',
                ], $limit);
            }
        }

        if (strpos($url, 'pinpointhq.com') !== false) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $company_id = preg_replace('/\.pinpointhq\.com$/i', '', $host);
            $postings_url = 'https://' . $host . '/postings';
            return $this->fetch_pinpoint_jobs('pinpoint_custom_' . sanitize_key($company_id), [
                'url' => $postings_url,
                'name' => ucwords(str_replace(['-', '_'], ' ', $company_id)) . ' Careers',
                'company_name' => ucwords(str_replace(['-', '_'], ' ', $company_id)),
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Pinpoint',
                'department_keywords' => stripos($url, 'tabby.pinpointhq.com') !== false ? ['finance'] : [],
            ], $limit);
        }

        if (strpos($url, 'bamboohr.com/careers') !== false) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $company_id = preg_replace('/\.bamboohr\.com$/i', '', $host);
            return $this->fetch_bamboohr_jobs('bamboohr_custom_' . sanitize_key($company_id), [
                'url' => 'https://' . $host . '/careers',
                'api_url' => 'https://' . $host . '/careers/list',
                'name' => ucwords(str_replace(['-', '_'], ' ', $company_id)) . ' Careers',
                'company_name' => ucwords(str_replace(['-', '_'], ' ', $company_id)),
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'BambooHR',
            ], $limit);
        }

        if (strpos($url, 'talent.outliers.vc') !== false) {
            return $this->fetch_outliers_vc_jobs('outliers_vc_custom', [
                'url' => 'https://talent.outliers.vc/',
                'name' => 'Outliers VC Careers',
                'company_name' => 'Outliers VC',
                'category' => 'Private Equity',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Outliers VC',
                'allowed_locations' => ['Saudi Arabia', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'proven-sa.zohorecruit.com/jobs/careers') !== false) {
            return $this->fetch_zoho_recruit_page_jobs('proven_arabia_zoho_custom', [
                'url' => 'https://proven-sa.zohorecruit.com/jobs/careers',
                'name' => 'Proven Arabia Careers',
                'company_name' => 'Proven Arabia',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Zoho Recruit',
                'allowed_locations' => ['Saudi Arabia', 'Riyadh', 'Jeddah', 'Dammam', 'Khobar'],
            ], $limit);
        }

        if (strpos($url, 'zohorecruit.com/jobs/') !== false) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $company_id = preg_replace('/\.zohorecruit\.com$/i', '', $host);
            $rss_url = preg_match('#/rss(?:\?.*)?$#i', $url) ? $url : rtrim(strtok($url, '?'), '/') . '/rss';
            return $this->fetch_rss_jobs($rss_url, 'zoho_recruit_custom_' . sanitize_key($company_id), [
                'url' => $rss_url,
                'type' => 'rss',
                'name' => ucwords(str_replace(['-', '_'], ' ', $company_id)) . ' Careers',
                'company_name' => ucwords(str_replace(['-', '_'], ' ', $company_id)),
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Zoho Recruit RSS',
            ], $limit);
        }

        if (strpos($url, 'jobs.workable.com/search') !== false) {
            return $this->fetch_workable_jobs('workable_custom', [
                'url' => $url,
                'name' => 'Workable',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
            ], $limit);
        }

        if (strpos($url, 'jobs.adia.ae') !== false || strpos($url, 'apply.workable.com/adia') !== false) {
            return $this->fetch_workable_board_jobs('adia_workable_custom', [
                'url' => $url,
                'name' => 'ADIA Careers',
                'company_name' => 'Abu Dhabi Investment Authority',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Workable',
                'jobs_md_url' => 'https://apply.workable.com/adia/jobs.md',
                'company_url' => 'https://www.adia.ae',
                'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi'],
            ], $limit);
        }

        if (strpos($url, 'apply.workable.com/emirates-investment-authority') !== false) {
            return $this->fetch_workable_board_jobs('emirates_investment_authority_workable_custom', [
                'url' => $url,
                'name' => 'Emirates Investment Authority Careers',
                'company_name' => 'Emirates Investment Authority',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Workable',
                'jobs_md_url' => 'https://apply.workable.com/emirates-investment-authority/jobs.md',
                'company_url' => 'https://www.eia.gov.ae',
                'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi'],
            ], $limit);
        }

        if (strpos($url, 'apply.workable.com/abu-dhabi-investment-council') !== false) {
            return $this->fetch_workable_board_jobs('abu_dhabi_investment_council_workable_custom', [
                'url' => $url,
                'name' => 'Abu Dhabi Investment Council Careers',
                'company_name' => 'Abu Dhabi Investment Council',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Workable',
                'jobs_md_url' => 'https://apply.workable.com/abu-dhabi-investment-council/jobs.md',
                'company_url' => 'https://www.adcouncil.ae',
                'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi'],
            ], $limit);
        }

        if (strpos($url, 'portal.careers.hsbc.com/careers/search') !== false) {
            return $this->fetch_eightfold_jobs('hsbc_dubai_custom', [
                'url' => $url,
                'name' => 'HSBC Dubai Careers',
                'company_name' => 'HSBC',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Eightfold',
                'company_logo' => 'https://static.vscdn.net/images/careers/demo/hsbc/1727956206::favicon.png',
                'allowed_locations' => ['Dubai', 'United Arab Emirates'],
            ], $limit);
        }

        if (strpos($url, 'jpmc.fa.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('jpmorgan_chase_dubai_custom', [
                'url' => $url,
                'name' => 'JPMorgan Chase Dubai Careers',
                'company_name' => 'JPMorgan Chase & Co.',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'company_logo' => 'https://jpmc.fa.oraclecloud.com/hcmRestApi/CandidateExperience/siteFavicon/favicon-144x144.png?siteNumber=CX_1001&size=144x144',
                'api_base_url' => 'https://jpmc.fa.oraclecloud.com',
                'site_number' => 'CX_1001',
                'location' => 'Dubai, United Arab Emirates',
                'location_id' => '300000020333038',
                'location_level' => 'state',
                'allowed_locations' => ['Dubai', 'United Arab Emirates'],
            ], $limit);
        }

        if (strpos($url, 'fa-evue-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('dubai_investments_custom', [
                'url' => $url,
                'name' => 'Dubai Investments Group Careers',
                'company_name' => 'Dubai Investments Group',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'company_logo' => 'https://www.dubaiinvestments.com/uploads/di-logo.jpg',
                'api_base_url' => 'https://fa-evue-saasfaprod1.fa.ocs.oraclecloud.com',
                'site_number' => 'CX_1',
                'allowed_locations' => ['United Arab Emirates', 'Dubai'],
            ], $limit);
        }

        if (strpos($url, 'iacqey.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('rakbank_custom', [
                'url' => $url,
                'name' => 'RAKBANK Careers',
                'company_name' => 'RAKBANK',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'company_logo' => 'https://campaignme.com/wp-content/uploads/2021/03/rakbank-cover.jpg',
                'api_base_url' => 'https://iacqey.fa.ocs.oraclecloud.com',
                'site_number' => 'CX_1',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Ras Al Khaimah'],
            ], $limit);
        }

        if (strpos($url, 'higher.gs.com/results') !== false || strpos($url, 'api-higher.gs.com/gateway/api/v1/graphql') !== false) {
            return $this->fetch_goldman_higher_jobs('goldman_sachs_middle_east_custom', [
                'url' => $url,
                'name' => 'Goldman Sachs Middle East Careers',
                'company_name' => 'Goldman Sachs',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Goldman Sachs Higher',
                'api_url' => 'https://api-higher.gs.com/gateway/api/v1/graphql',
                'allowed_locations' => ['Dubai', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'careers.db.com/professionals/search-roles') !== false || strpos($url, 'api-deutschebank.beesite.de/search') !== false) {
            return $this->fetch_deutsche_bank_beesite_jobs('deutsche_bank_uae_custom', [
                'url' => $url,
                'name' => 'Deutsche Bank UAE Careers',
                'company_name' => 'Deutsche Bank',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Deutsche Bank Beesite',
                'api_url' => 'https://api-deutschebank.beesite.de/search/',
                'country_id' => '230',
                'allowed_locations' => ['United Arab Emirates', 'Dubai'],
            ], $limit);
        }

        if (strpos($url, 'adcbcareers.com/search') !== false) {
            return $this->fetch_successfactors_jobs('adcb_successfactors_custom', [
                'url' => $url,
                'name' => 'ADCB Careers',
                'company_name' => 'Abu Dhabi Commercial Bank',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'SAP SuccessFactors',
                'company_logo' => 'https://rmkcdn.successfactors.com/b2e2b5cf/d5c2f451-2501-4f9f-b4c7-b.png',
                'force_company_name' => true,
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi'],
            ], $limit);
        }

        if (strpos($url, 'careers.theredsea.sa/go/Job-Opportunities') !== false) {
            $is_residential = stripos($url, 'Residential+Development') !== false || stripos($url, 'Residential%20Development') !== false || stripos($url, 'Residential Development') !== false;
            return $this->fetch_successfactors_jobs($is_residential ? 'red_sea_residential_development_custom' : 'red_sea_finance_custom', [
                'url' => $url,
                'name' => $is_residential ? 'Red Sea Global Residential Development Jobs' : 'Red Sea Global Finance Jobs',
                'company_name' => 'Red Sea Global',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'SAP SuccessFactors',
                'allowed_locations' => ['Riyadh', 'Saudi Arabia', 'Jeddah', 'Tabuk'],
                'force_company_name' => true,
            ], $limit);
        }

        if (strpos($url, '/search/') !== false && (strpos($url, 'careers.stc.com.sa') !== false || strpos($url, 'afuturewithus.com') !== false || strpos($url, 'career.elm.sa') !== false || strpos($url, 'successfactors') !== false)) {
            $is_al_futtaim = strpos($url, 'afuturewithus.com') !== false;
            $is_elm = strpos($url, 'career.elm.sa') !== false;
            return $this->fetch_successfactors_jobs('successfactors_custom', [
                'url' => $url,
                'name' => $is_al_futtaim ? 'Al-Futtaim Finance Jobs' : ($is_elm ? 'Elm Careers' : 'SAP SuccessFactors'),
                'company_name' => $is_al_futtaim ? 'Al Futtaim Private Company LLC' : ($is_elm ? 'Elm' : ''),
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'SAP SuccessFactors',
                'company_logo' => $is_al_futtaim ? 'https://rmkcdn.successfactors.com/5d589df0/8f422567-53e4-4e54-8da4-0.svg' : ($is_elm ? 'https://rmkcdn.successfactors.com/c966bc59/11976c96-eed7-404f-b051-b.png' : ''),
                'force_company_name' => $is_elm,
            ], $limit);
        }

        if (strpos($url, 'comeet.com/jobs/cbd/14.007') !== false) {
            return $this->fetch_comeet_jobs('cbd_comeet_custom', [
                'url' => $url,
                'name' => 'Commercial Bank of Dubai Careers',
                'company_name' => 'Commercial Bank of Dubai',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Comeet',
                'company_logo' => 'https://www.comeet.co/pub/cbd/14.007/logo?size=medium&last-modified=1767858046',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah'],
            ], $limit);
        }

        if (strpos($url, 'fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('emirates_nbd_custom', [
                'url' => $url,
                'name' => 'Emirates NBD Careers',
                'company_name' => 'Emirates NBD',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'company_logo' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com/cs/groups/public/documents/digitalmedia/c25i/zc0y/~edisp/logo-emiratesnbd-2024.png',
                'api_base_url' => 'https://fa-evlo-saasfaprod1.fa.ocs.oraclecloud.com',
                'site_number' => 'CX_1',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'ehjd.fa.em2.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('fab_custom', [
                'url' => $url,
                'name' => 'FAB Careers',
                'company_name' => 'First Abu Dhabi Bank',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'company_logo' => 'https://ehjd-dev1.fa.em2.oraclecloud.com:443/hcmUI/CandidateExperience/images?imageId=49A4C61C-2357-4AC2-8A93-2F7FC21BA611',
                'api_base_url' => 'https://ehjd.fa.em2.oraclecloud.com',
                'site_number' => 'CX_2001',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'hcld.fa.em2.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('mashreq_custom', [
                'url' => $url,
                'name' => 'Mashreq Careers',
                'company_name' => 'Mashreq',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'api_base_url' => 'https://hcld.fa.em2.oraclecloud.com',
                'site_number' => 'CX_1',
                'allowed_locations' => ['United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Saudi Arabia', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'fa-eukk-saasfaprod1.fa.ocs.oraclecloud.com/hcmUI/CandidateExperience') !== false) {
            return $this->fetch_oracle_cx_jobs('adgm_external_custom', [
                'url' => $url,
                'name' => 'ADGM External Site Careers',
                'company_name' => 'ADGM',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Oracle HCM',
                'api_base_url' => 'https://fa-eukk-saasfaprod1.fa.ocs.oraclecloud.com',
                'site_number' => 'CX_1',
                'allowed_locations' => ['United Arab Emirates', 'Abu Dhabi', 'Dubai'],
            ], $limit);
        }

        if (strpos($url, 'michaelpage.ae/jobs/') !== false || strpos($url, 'michaelpage.ae/job-search') !== false) {
            return $this->fetch_michael_page_jobs('michael_page_custom', [
                'url' => $url,
                'name' => 'Michael Page',
                'company_name' => 'Michael Page',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Michael Page',
                'company_logo' => 'https://www.michaelpage.ae/themes/custom/mp_theme/logo.svg',
            ], $limit);
        }

        if (strpos($url, 'aventusglobal.com/jobs') !== false) {
            return $this->fetch_aventus_jobs('aventus_custom', [
                'url' => $url,
                'name' => 'Aventus Global Jobs',
                'company_name' => 'Aventus Global',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Aventus Global',
                'company_logo' => 'https://www.aventusglobal.com/themes/aventus-global-talent/assets/images/aventus-logo-1.png',
            ], $limit);
        }

        if (strpos($url, 'venturesearch.com/jobs') !== false) {
            return $this->fetch_venture_search_jobs('venture_search_custom', [
                'url' => $url,
                'name' => 'Venture Search Jobs',
                'company_name' => 'Venture Search',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Venture Search',
                'company_logo' => 'https://www.venturesearch.com/images/logo.svg',
            ], $limit);
        }

        if (strpos($url, 'agfund.org/en/jobs') !== false) {
            return $this->fetch_agfund_jobs('agfund_custom', [
                'url' => $url,
                'name' => 'AGFUND Jobs',
                'company_name' => 'AGFUND',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'AGFUND Careers',
                'company_logo' => 'https://agfund.org/images/Agfund-Logo-0122_3_82x62.png',
                'location' => 'Riyadh, Saudi Arabia',
                'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
            ], $limit);
        }

        if (strpos($url, 'careers.savills.me/jobs') !== false) {
            return $this->fetch_teamtailor_rss_jobs('savills_middle_east_custom', [
                'url' => $url,
                'rss_url' => 'https://careers.savills.me/jobs.rss',
                'name' => 'Savills Middle East Jobs',
                'company_name' => 'Savills Middle East',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Teamtailor',
                'company_logo' => 'https://images.teamtailor-cdn.com/images/s3/teamtailor-production/logotype-v3/image_uploads/33b57ccb-c6c6-4eb4-93d8-eaa145d29be4/original.png',
            ], $limit);
        }

        if (strpos($url, 'careers.tarmeez.co/jobs') !== false) {
            return $this->fetch_teamtailor_rss_jobs('tarmeez_capital_custom', [
                'url' => 'https://careers.tarmeez.co/jobs',
                'rss_url' => 'https://careers.tarmeez.co/jobs.rss',
                'name' => 'Tarmeez Capital Jobs',
                'company_name' => 'Tarmeez Capital',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Teamtailor',
                'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
            ], $limit);
        }

        if (strpos($url, 'careers.blackrock.com/search-jobs/Saudi') !== false || strpos($url, 'careers.blackrock.com/search-jobs/Saudi%20Arabia') !== false) {
            return $this->fetch_talentbrew_search_jobs('blackrock_saudi_custom', [
                'url' => 'https://careers.blackrock.com/search-jobs/Saudi%20Arabia/45831/2/102358/25/45/0/2',
                'name' => 'BlackRock Saudi Arabia Jobs',
                'company_name' => 'BlackRock',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'TalentBrew',
                'company_logo' => 'https://tbcdn.talentbrew.com/company/45831/cms/img/general/blackrock-logo-308x76.webp',
                'allowed_locations' => ['Saudi Arabia', 'Riyadh'],
            ], $limit);
        }

        if (strpos($url, 'jisr.net/en/merakcapital/careers-page') !== false) {
            return $this->fetch_jisr_careers_jobs('merak_capital_jisr_custom', [
                'url' => 'https://www.jisr.net/en/merakcapital/careers-page?host=1&id=debde4c9-e40d-411f-b5cf-57ada988a20b',
                'name' => 'Merak Capital Careers',
                'company_name' => 'Merak Capital',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Jisr',
                'api_url' => 'https://apis.jisr.net/ats/api/v1/career_websites/jobs_details',
                'career_website_uuid' => 'debde4c9-e40d-411f-b5cf-57ada988a20b',
                'company_slug' => 'merakcapital',
                'location' => 'Riyadh, Saudi Arabia',
                'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
            ], $limit);
        }

        if (strpos($url, 'dohabank.com.qa/careers') !== false || strpos($url, 'dohabank.com.qa/?feed=job_feed') !== false) {
            return $this->fetch_rss_jobs('https://www.dohabank.com.qa/?feed=job_feed', 'doha_bank_careers_custom', [
                'url' => 'https://www.dohabank.com.qa/?feed=job_feed',
                'type' => 'rss',
                'name' => 'Doha Bank Careers',
                'company_name' => 'Doha Bank',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'WP Job Manager RSS',
                'company_logo' => 'https://www.dohabank.com.qa/wp-content/uploads/sites/12/cropped-db-icon-512-192x192.png',
                'allowed_locations' => ['Doha', 'Qatar'],
            ], $limit);
        }

        if (strpos($url, 'mubadala.com/en/careers/professional') !== false || strpos($url, 'mic-cand.takafo.ai/jobs/external') !== false || strpos($url, 'mic-cand.takafo.ai/v1/jobs/external') !== false) {
            return $this->fetch_mubadala_takafo_jobs('mubadala_takafo_custom', [
                'url' => $url,
                'api_url' => 'https://mic-cand.takafo.ai/v1/jobs/external',
                'name' => 'Mubadala Professional Careers',
                'company_name' => 'Mubadala Investment Company',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Takafo',
                'company_logo' => 'https://www.mubadala.com/~/media/Images/M/mubadala/corp/logo/mubadala-logo-dark.svg',
            ], $limit);
        }

        if (strpos($url, 'careers.alvarezandmarsal.com/search/jobs') !== false || strpos($url, 'careers.alvarezandmarsal.com/search/jobs/in/') !== false) {
            $is_saudi = stripos($url, 'Saudi') !== false || stripos($url, 'saudi-arabia') !== false;
            $is_uae = stripos($url, 'United%20Arab%20Emirates') !== false || stripos($url, 'United Arab Emirates') !== false || stripos($url, 'dubai') !== false || stripos($url, 'abu-dhabi') !== false;

            return $this->fetch_alvarez_marsal_jobs('alvarez_marsal_custom', [
                'url' => $url,
                'name' => $is_saudi ? 'Alvarez & Marsal Saudi Arabia Careers' : ($is_uae ? 'Alvarez & Marsal UAE Careers' : 'Alvarez & Marsal Careers'),
                'company_name' => 'Alvarez & Marsal',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Alvarez & Marsal Careers',
                'allowed_locations' => $is_saudi ? ['Saudi Arabia', 'Riyadh'] : ($is_uae ? ['United Arab Emirates', 'Dubai', 'Abu Dhabi'] : ['Saudi Arabia', 'Riyadh', 'United Arab Emirates', 'Dubai', 'Abu Dhabi']),
            ], $limit);
        }

        if (strpos($url, 'careers.gib.com/en/job-search-results') !== false) {
            return $this->fetch_bayt_careers_jobs('gib_bayt_custom', [
                'url' => $url,
                'name' => 'Gulf International Bank Careers',
                'company_name' => 'Gulf International Bank',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Bayt Careers',
                'company_logo' => 'https://ksaimg0.b8cdn.com/images/templates/gib/gib-logo-en.png?vid=28',
            ], $limit);
        }

        if (strpos($url, 'careers.riyadbank.com') !== false) {
            return $this->fetch_bayt_careers_jobs('riyad_bank_custom', [
                'url' => $url,
                'name' => 'Riyad Bank Careers',
                'company_name' => 'Riyad Bank',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Bayt Careers',
                'company_logo' => 'https://ksaimg4.b8cdn.com/images/templates/rdbank/rdbank-logo-en.png',
                'allowed_locations' => ['Riyadh', 'Saudi Arabia'],
                'force_company_name' => true,
            ], $limit);
        }

        if (strpos($url, '/job-search-results') !== false && strpos($url, 'careers.') !== false) {
            return $this->fetch_bayt_careers_jobs('bayt_careers_custom', [
                'url' => $url,
                'name' => 'Bayt Careers',
                'category' => 'Job aggregators',
                'source_type' => 'job_aggregator',
                'source_platform' => 'Bayt Careers',
            ], $limit);
        }

        // Try to fetch and parse the feed
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress Job Fetcher)'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log("SFFC XML Fetcher: Failed to fetch URL {$url}: " . $response->get_error_message());
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("SFFC XML Fetcher: HTTP {$status_code} for URL {$url}");
            return [];
        }

        $content = wp_remote_retrieve_body($response);
        if (empty($content)) {
            error_log("SFFC XML Fetcher: Empty content for URL {$url}");
            return [];
        }

        // First, try to parse as XML (sitemap/RSS)
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml !== false) {
            // Check if it's a sitemap
            if (isset($xml->url)) {
                error_log("SFFC XML Fetcher: Processing sitemap with " . count($xml->url) . " URLs from {$url}");
                foreach ($xml->url as $url_node) {
                    if (count($jobs) >= $limit) break;

                    $job_url = (string)$url_node->loc;
                    $job = $this->extract_job_from_url($job_url);

                    if ($job && !empty($job['title'])) {
                        $jobs[] = $job;
                        error_log("SFFC XML Fetcher: Successfully extracted job: " . $job['title']);
                    }
                }
            }
            // Check if it's an RSS feed
            elseif (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    if (count($jobs) >= $limit) break;

                    $job = [
                        'id' => md5((string)$item->link),
                        'title' => (string)$item->title,
                        'url' => (string)$item->link,
                        'description' => (string)$item->description,
                        'posted_date' => (string)$item->pubDate,
                        'company' => 'Via Recruiter',
                        'location' => $this->extract_location_from_title((string)$item->title)
                    ];

                    if (!empty($job['title'])) {
                        $jobs[] = $job;
                    }
                }
            }
        } else {
            // XML parsing failed, log errors
            $xml_errors = libxml_get_errors();
            if (!empty($xml_errors)) {
                error_log("SFFC XML Fetcher: XML parsing errors for {$url}: " . print_r($xml_errors, true));
            }
            // If not XML, try to parse as HTML job listing page
            $jobs = $this->extract_jobs_from_html_listing($content, $url, $limit);
        }

        if (!empty($jobs)) {
            foreach ($this->xml_sources as $key => $info) {
                if (!empty($info['url']) && $info['url'] === $url) {
                    $this->enrich_jobs_with_source_meta($jobs, $key, $info);
                    break;
                }
            }
        }

        error_log("SFFC XML Fetcher: Completed fetching from {$url}, found " . count($jobs) . " jobs");
        return $jobs;
    }

    /**
     * Extract jobs from HTML listing page (like Focus Selection)
     */
    private function extract_jobs_from_html_listing($html, $source_url, $limit = 10)
    {
        $jobs = [];
        
        // Extract company name from domain
        $url_parts = parse_url($source_url);
        $company_name = isset($url_parts['host']) ? 
            ucwords(str_replace(['www.', '.co.uk', '.com', '.net'], '', $url_parts['host'])) : 'Recruiter';

        // Look for job links in the HTML using various patterns
        $job_links = $this->extract_job_links_from_html($html, $source_url);
        
        if (empty($job_links)) {
            // Fallback: try to extract job information directly from the listing page
            return $this->extract_jobs_directly_from_listing($html, $source_url, $company_name, $limit);
        }

        // Process individual job links
        foreach ($job_links as $job_link) {
            if (count($jobs) >= $limit) break;
            
            $job_data = $this->extract_job_from_url_enhanced($job_link, 'custom', [
                'name' => $company_name,
                'source_type' => 'recruiter',
                'category' => 'General',
                'quality' => 'standard'
            ]);
            
            if (!empty($job_data) && !empty($job_data['title'])) {
                $jobs[] = $job_data;
            }
        }

        return $jobs;
    }

    /**
     * Extract job links from HTML listing page
     */
    private function extract_job_links_from_html($html, $source_url)
    {
        $job_links = [];
        $url_parts = parse_url($source_url);
        $base_url = $url_parts['scheme'] . '://' . $url_parts['host'];

        // Common patterns for job links
        $link_patterns = [
            // Look for links with job-related text or URLs
            '/<a[^>]*href=["\']([^"\']*(?:job|vacancy|position|career|role)[^"\']*)["\'][^>]*>/i',
            // Look for "More Info", "View Details", "Apply" links
            '/<a[^>]*href=["\']([^"\']+)["\'][^>]*(?:more\s+info|view\s+details|apply|read\s+more)/i',
            // Look for links within job containers
            '/<(?:div|li|article)[^>]*(?:class|id)=["\'][^"\']*(?:job|vacancy|position)[^"\']*["\'][^>]*>.*?<a[^>]*href=["\']([^"\']+)["\'][^>]*>/is'
        ];

        foreach ($link_patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $link) {
                    // Convert relative URLs to absolute
                    if (strpos($link, 'http') !== 0) {
                        if (strpos($link, '/') === 0) {
                            $link = $base_url . $link;
                        } else {
                            $link = rtrim($source_url, '/') . '/' . $link;
                        }
                    }
                    
                    // Only include unique links that look like job pages
                    if (!in_array($link, $job_links) && $this->is_likely_job_url($link)) {
                        $job_links[] = $link;
                    }
                }
            }
        }

        return array_unique($job_links);
    }

    /**
     * Check if URL is likely a job page
     */
    private function is_likely_job_url($url)
    {
        $url_lower = strtolower($url);
        
        // Exclude common non-job pages
        $exclude_patterns = [
            'contact', 'about', 'home', 'news', 'blog', 'privacy', 'terms',
            'cookie', 'login', 'register', 'search', 'filter', 'sort'
        ];
        
        foreach ($exclude_patterns as $pattern) {
            if (strpos($url_lower, $pattern) !== false) {
                return false;
            }
        }
        
        // Include if it has job-related patterns or unique identifiers
        $include_patterns = [
            '/job', '/vacancy', '/position', '/career', '/role', '/opportunity',
            // Or has unique ID patterns (like Loxo URLs)
            '/[a-zA-Z0-9]{20,}', // Long alphanumeric strings
            '/[0-9]{4,}' // Job IDs
        ];
        
        foreach ($include_patterns as $pattern) {
            if (preg_match($pattern, $url_lower)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract job information directly from listing page (fallback method)
     */
    private function extract_jobs_directly_from_listing($html, $source_url, $company_name, $limit)
    {
        $jobs = [];
        
        // Try to find job containers in the HTML
        $job_containers = $this->find_job_containers($html);
        
        foreach ($job_containers as $container) {
            if (count($jobs) >= $limit) break;
            
            $job_data = $this->parse_job_container($container, $source_url, $company_name);
            
            if (!empty($job_data) && !empty($job_data['title'])) {
                $jobs[] = $job_data;
            }
        }
        
        return $jobs;
    }

    /**
     * Find job containers in HTML
     */
    private function find_job_containers($html)
    {
        $containers = [];
        
        // Patterns to find job containers
        $container_patterns = [
            // Hanning Recruitment specific: hidden role containers
            '/<div[^>]*class=["\'][^"\']*role[^"\']*hidden[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            // Look for divs/sections with job-related classes
            '/<(?:div|section|article|li)[^>]*class=["\'][^"\']*(?:job|vacancy|position|listing|posting)[^"\']*["\'][^>]*>(.*?)(?=<(?:div|section|article|li)[^>]*class=["\'][^"\']*(?:job|vacancy|position|listing|posting)|<\/(?:div|section|article|ul)>)/is',
            // Look for table rows that might contain job data
            '/<tr[^>]*>(.*?)<\/tr>/is'
        ];
        
        foreach ($container_patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    // Only include containers that seem to have job-like content
                    if (strlen(trim(strip_tags($match))) > 50 && 
                        (stripos($match, '£') !== false || 
                         stripos($match, 'salary') !== false ||
                         stripos($match, 'location') !== false ||
                         preg_match('/\b(?:analyst|manager|director|consultant|advisor)\b/i', $match))) {
                        $containers[] = $match;
                    }
                }
            }
        }
        
        return $containers;
    }

    /**
     * Parse individual job container to extract job data
     */
    private function parse_job_container($container, $source_url, $company_name)
    {
        $job_data = [
            'id' => md5($container . $source_url),
            'url' => $source_url,
            'company' => $company_name,
            'source' => 'html_listing',
            'posted_date' => date('Y-m-d'),
            'time_type' => 'Full-time',
            'via_recruiter' => true,
            'recruiter_name' => $company_name
        ];
        
        // Extract title (usually in h tags or strong emphasis)
        $title_patterns = [
            // Hanning Recruitment specific: job title in first <p><strong> tag
            '/<p[^>]*>\s*<strong[^>]*>(.*?)<\/strong>/i',
            '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i',
            '/<strong[^>]*>(.*?)<\/strong>/i',
            '/<b[^>]*>(.*?)<\/b>/i',
            '/<(?:span|div)[^>]*class=["\'][^"\']*(?:title|name|heading)[^"\']*["\'][^>]*>(.*?)<\/(?:span|div)>/i'
        ];
        
        foreach ($title_patterns as $pattern) {
            if (preg_match($pattern, $container, $matches)) {
                $title = trim(strip_tags($matches[1]));
                if (strlen($title) > 5 && strlen($title) < 100) {
                    $job_data['title'] = $title;
                    break;
                }
            }
        }
        
        // Extract salary
        if (preg_match('/£\s*(\d{1,3}(?:,?\d{3})*)\s*(?:-|to)\s*£?\s*(\d{1,3}(?:,?\d{3})*)/i', $container, $matches)) {
            $job_data['salary_display'] = $matches[0];
            $job_data['salary_min'] = (int)str_replace(',', '', $matches[1]);
            $job_data['salary_max'] = (int)str_replace(',', '', $matches[2]);
            $job_data['salary_currency'] = 'GBP';
        }
        
        // Extract location
        $location_patterns = [
            '/(?:Location|Based)[:\s]*([^<\n\r,]+)/i',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*,\s*[A-Z]{2,3}(?:\s|$)/i', // City, Country pattern
            '/\b(London|Manchester|Birmingham|Leeds|Glasgow|Edinburgh|Dublin|Belfast|Cardiff)\b/i'
        ];
        
        foreach ($location_patterns as $pattern) {
            if (preg_match($pattern, $container, $matches)) {
                $location = trim($matches[1]);
                if (strlen($location) > 2 && strlen($location) < 50) {
                    $job_data['location'] = $location;
                    break;
                }
            }
        }
        
        // Extract contact email (for Hanning Recruitment mailto: links)
        if (preg_match('/mailto:([^"\'>\s]+)/i', $container, $email_matches)) {
            $job_data['contact_email'] = $email_matches[1];
            $job_data['apply_url'] = 'mailto:' . $email_matches[1];
        }
        
        // Extract description (any remaining text)
        $description = strip_tags($container);
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        if (strlen($description) > 100) {
            $job_data['description'] = substr($description, 0, 500) . '...';
        }
        
        // Extract skills from available text
        $all_text = ($job_data['title'] ?? '') . ' ' . ($job_data['description'] ?? '');
        $skills = $this->extract_skills_from_description($all_text);
        if (!empty($skills)) {
            $job_data['skills'] = array_slice($skills, 0, 10);
        }
        
        return $job_data;
    }

    /**
     * Extract job from URL - Enhanced to fetch actual job page content
     */
    private function extract_job_from_url($job_url)
    {
        $job = [
            'id' => md5($job_url),
            'url' => $job_url,
            'source' => 'xml',
            'posted_date' => date('Y-m-d'),
            'time_type' => 'Full-time' // Default
        ];

        // Extract domain as company first
        $url_parts = parse_url($job_url);
        if (isset($url_parts['host'])) {
            $host = str_replace('www.', '', $url_parts['host']);
            $job['company'] = ucwords(str_replace(['-', '.'], ' ', explode('.', $host)[0]));
        }

        // Try to fetch the actual job page
        $response = wp_remote_get($job_url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log("SFFC: Failed to fetch job page {$job_url}: " . $response->get_error_message());
        } elseif (wp_remote_retrieve_response_code($response) !== 200) {
            error_log("SFFC: HTTP " . wp_remote_retrieve_response_code($response) . " for job page {$job_url}");
        } else {
            $html = wp_remote_retrieve_body($response);

            if (!empty($html)) {
                // Extract structured data (JSON-LD)
                $job_data = $this->extract_structured_data($html);

                if (!empty($job_data)) {
                    $job = array_merge($job, $job_data);
                } else {
                    // Fallback to HTML parsing
                    $job_from_html = $this->parse_job_html($html, $job_url);
                    $job = array_merge($job, $job_from_html);
                }

                // Extract skills from all available text
                $text_for_skills = ($job['title'] ?? '') . ' ' .
                    ($job['description'] ?? '') . ' ' .
                    ($job['responsibilities'] ?? '') . ' ' .
                    ($job['qualifications'] ?? '');

                $skills = $this->extract_skills_from_description($text_for_skills);
                if (!empty($skills)) {
                    $job['skills'] = $skills;
                }

                // Use intelligent salary estimation if no salary found
                if (empty($job['salary']) && empty($job['salary_min'])) {
                    // Load intelligent estimator
                    if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php')) {
                        require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php';
                        $estimator = SFFC_Intelligent_Salary_Estimator::get_instance();
                        $job['estimated_salary'] = $estimator->estimate_salary($job);
                    } else {
                        // Fallback to simple estimation
                        $job['estimated_salary'] = $this->estimate_salary_from_title($job['title'] ?? '');
                    }
                }
            }
        }

        // Fallback: extract basic info from URL if fetch failed
        if (empty($job['title']) && isset($url_parts['path'])) {
            $path_parts = explode('/', trim($url_parts['path'], '/'));
            $last_part = end($path_parts);

            $title = preg_replace('/\.(html?|php|aspx?)$/i', '', $last_part);
            $title = str_replace(['-', '_'], ' ', $title);
            $title = ucwords($title);

            if (!empty($title)) {
                $job['title'] = $title;

                // Extract basic skills from title
                $skills = $this->extract_skills_from_description($title);
                if (!empty($skills)) {
                    $job['skills'] = $skills;
                }

                // Use intelligent salary estimation
                if (file_exists(SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php')) {
                    require_once SFFC_PLUGIN_DIR . 'includes/class-intelligent-salary-estimator.php';
                    $estimator = SFFC_Intelligent_Salary_Estimator::get_instance();
                    $job['estimated_salary'] = $estimator->estimate_salary($job);
                } else {
                    $job['estimated_salary'] = $this->estimate_salary_from_title($title);
                }
            }
        }

        // Ensure location is set
        if (empty($job['location'])) {
            $job['location'] = $this->extract_location_from_title($job['title'] ?? '');
        }

        // Ensure we have a title (required for job to be valid)
        if (empty($job['title'])) {
            error_log("SFFC: No title extracted for job URL: {$job_url}");
            return false; // Return false if no title found
        }

        error_log("SFFC: Successfully extracted job: " . $job['title'] . " from {$job_url}");
        return $job;
    }

    /**
     * Extract location from title
     */
    private function extract_location_from_title($title)
    {
        // Common location patterns in job titles
        $locations = [
            'London',
            'New York',
            'NYC',
            'San Francisco',
            'SF',
            'Chicago',
            'Boston',
            'Los Angeles',
            'LA',
            'Singapore',
            'Hong Kong',
            'Tokyo',
            'Paris',
            'Frankfurt',
            'Dubai',
            'Doha',
            'Qatar',
            'Sydney',
            'Toronto',
            'Mumbai',
            'Bangalore',
            'Remote',
            'UK',
            'US'
        ];

        foreach ($locations as $location) {
            if (stripos($title, $location) !== false) {
                return $location;
            }
        }

        // Try to extract location from parentheses or after dash
        if (preg_match('/[\(\-]\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s*[\)\-]?$/', $title, $matches)) {
            return $matches[1];
        }

        return 'Various Locations';
    }

    /**
     * Get job statistics for dashboard
     */
    public function get_feed_statistics()
    {
        $stats = [];

        foreach ($this->xml_sources as $key => $source) {
            $cache_key = 'sffc_xml_stats_' . $key;
            $cached_stats = get_transient($cache_key);

            if ($cached_stats === false) {
                $jobs = $this->fetch_jobs_from_source($key, 100);
                $cached_stats = [
                    'total' => count($jobs),
                    'with_salary' => 0,
                    'last_updated' => current_time('mysql')
                ];

                foreach ($jobs as $job) {
                    if (!empty($job['salary'])) {
                        $cached_stats['with_salary']++;
                    }
                }

                set_transient($cache_key, $cached_stats, DAY_IN_SECONDS);
            }

            $stats[$key] = $cached_stats;
        }

        return $stats;
    }

    /**
     * Extract structured data (JSON-LD) from HTML
     */
    private function extract_structured_data($html)
    {
        $job_data = [];

        // Look for JSON-LD structured data
        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            $json_ld = json_decode($matches[1], true);

            if ($json_ld && isset($json_ld['@type'])) {
                // Handle JobPosting schema
                if (
                    $json_ld['@type'] === 'JobPosting' ||
                    (is_array($json_ld['@type']) && in_array('JobPosting', $json_ld['@type']))
                ) {

                    $job_data['title'] = $json_ld['title'] ?? '';

                    // Get FULL description and clean it properly
                    if (!empty($json_ld['description'])) {
                        $description = $json_ld['description'];
                        // If it contains HTML, clean it
                        if (strpos($description, '<') !== false) {
                            $description = $this->clean_html($description);
                        } else {
                            $description = wp_strip_all_tags($description);
                        }
                        $job_data['description'] = $description;

                        // Also try to parse sections from the description
                        $parsed = $this->parse_job_sections($description);
                        if (!empty($parsed['responsibilities'])) {
                            $job_data['responsibilities'] = $parsed['responsibilities'];
                        }
                        if (!empty($parsed['qualifications'])) {
                            $job_data['qualifications'] = $parsed['qualifications'];
                        }
                        if (!empty($parsed['requirements'])) {
                            $job_data['requirements'] = $parsed['requirements'];
                        }
                    }

                    $job_data['posted_date'] = $json_ld['datePosted'] ?? '';
                    $job_data['valid_through'] = $json_ld['validThrough'] ?? '';

                    // Extract company
                    if (isset($json_ld['hiringOrganization']['name'])) {
                        $job_data['company'] = $json_ld['hiringOrganization']['name'];
                    }

                    // Extract location
                    if (isset($json_ld['jobLocation'])) {
                        $location = $json_ld['jobLocation'];
                        if (is_array($location)) {
                            $location = reset($location); // Get first location
                        }

                        if (isset($location['address'])) {
                            $addr = $location['address'];
                            $location_parts = [];

                            if (isset($addr['addressLocality'])) $location_parts[] = $addr['addressLocality'];
                            if (isset($addr['addressRegion'])) $location_parts[] = $addr['addressRegion'];
                            if (isset($addr['addressCountry'])) $location_parts[] = $addr['addressCountry'];

                            if (!empty($location_parts)) {
                                $job_data['location'] = implode(', ', $location_parts);
                            }
                        }
                    }

                    // Extract salary
                    if (isset($json_ld['baseSalary'])) {
                        $salary = $json_ld['baseSalary'];
                        if (isset($salary['value'])) {
                            if (is_array($salary['value']) && isset($salary['value']['minValue'])) {
                                $job_data['salary_min'] = $salary['value']['minValue'];
                                $job_data['salary_max'] = $salary['value']['maxValue'] ?? $salary['value']['minValue'];
                                $job_data['salary_currency'] = $salary['currency'] ?? 'USD';
                            } elseif (is_numeric($salary['value'])) {
                                $job_data['salary_min'] = $salary['value'];
                                $job_data['salary_max'] = $salary['value'];
                                $job_data['salary_currency'] = $salary['currency'] ?? 'USD';
                            }
                        }
                    }

                    // Extract employment type
                    if (isset($json_ld['employmentType'])) {
                        $types = is_array($json_ld['employmentType']) ? $json_ld['employmentType'] : [$json_ld['employmentType']];
                        $job_data['time_type'] = str_replace('_', '-', $types[0]);
                    }

                    // Extract responsibilities and qualifications
                    if (isset($json_ld['responsibilities'])) {
                        $job_data['responsibilities'] = wp_strip_all_tags($json_ld['responsibilities']);
                    }
                    if (isset($json_ld['qualifications'])) {
                        $job_data['qualifications'] = wp_strip_all_tags($json_ld['qualifications']);
                    }
                }
            }
        }

        return $job_data;
    }

    /**
     * Parse job HTML to extract data
     */
    private function parse_job_html($html, $job_url)
    {
        $job_data = [];

        // Extract title from various meta tags
        if (preg_match('/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $job_data['title'] = html_entity_decode($matches[1], ENT_QUOTES);
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $job_data['title'] = html_entity_decode(trim($matches[1]), ENT_QUOTES);
        }

        // Extract description from meta tags
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $job_data['description'] = html_entity_decode($matches[1], ENT_QUOTES);
        } elseif (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $job_data['description'] = html_entity_decode($matches[1], ENT_QUOTES);
        }

        // Try to extract salary from content
        if (preg_match('/[\$£€]\s*(\d{1,3}(?:,?\d{3})*(?:\.\d{2})?)\s*(?:k|K|,000)?(?:\s*[-–]\s*[\$£€]?\s*(\d{1,3}(?:,?\d{3})*(?:\.\d{2})?)\s*(?:k|K|,000)?)?/i', $html, $matches)) {
            $min_salary = str_replace(',', '', $matches[1]);
            if (stripos($matches[0], 'k') !== false || stripos($matches[0], 'K') !== false) {
                $min_salary *= 1000;
            }

            $job_data['salary_min'] = $min_salary;

            if (isset($matches[2])) {
                $max_salary = str_replace(',', '', $matches[2]);
                if (stripos($matches[0], 'k') !== false || stripos($matches[0], 'K') !== false) {
                    $max_salary *= 1000;
                }
                $job_data['salary_max'] = $max_salary;
            } else {
                $job_data['salary_max'] = $min_salary;
            }

            // Determine currency
            if (strpos($matches[0], '£') !== false) {
                $job_data['salary_currency'] = 'GBP';
            } elseif (strpos($matches[0], '€') !== false) {
                $job_data['salary_currency'] = 'EUR';
            } else {
                $job_data['salary_currency'] = 'USD';
            }

            $job_data['salary_display'] = $matches[0];
        }

        // Extract location patterns
        $location_patterns = [
            '/<span[^>]*class=["\'][^"\']*location[^"\']*["\'][^>]*>([^<]+)<\/span>/i',
            '/<div[^>]*class=["\'][^"\']*location[^"\']*["\'][^>]*>([^<]+)<\/div>/i',
            '/<li[^>]*class=["\'][^"\']*location[^"\']*["\'][^>]*>([^<]+)<\/li>/i',
            '/Location:\s*([^<\n]+)/i'
        ];

        foreach ($location_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $job_data['location'] = trim(strip_tags($matches[1]));
                break;
            }
        }

        // Extract responsibilities and qualifications sections
        if (preg_match('/(?:Responsibilities|Key Responsibilities|What you.?ll do)[:\s]*<[^>]+>(.*?)(?:<h\d|<div class=|$)/si', $html, $matches)) {
            $job_data['responsibilities'] = wp_strip_all_tags($matches[1]);
        }

        if (preg_match('/(?:Requirements|Qualifications|What we.?re looking for|Skills)[:\s]*<[^>]+>(.*?)(?:<h\d|<div class=|$)/si', $html, $matches)) {
            $job_data['qualifications'] = wp_strip_all_tags($matches[1]);
        }

        return $job_data;
    }

    /**
     * Estimate salary from job title
     */
    private function estimate_salary_from_title($title)
    {
        $title_lower = strtolower($title);

        // Default currency based on common locations
        $currency = 'USD';
        if (stripos($title, 'london') !== false || stripos($title, 'uk') !== false) {
            $currency = 'GBP';
        }

        // Salary ranges based on title keywords (similar to Workday fetcher)
        $salary_ranges = [
            // C-Level
            'ceo' => [350000, 800000],
            'cfo' => [300000, 600000],
            'cto' => [280000, 550000],
            'coo' => [250000, 500000],
            'chief' => [250000, 500000],

            // Senior Management
            'managing director' => [200000, 400000],
            'executive director' => [180000, 350000],
            'partner' => [200000, 500000],
            'head of' => [150000, 300000],

            // Mid-Senior
            'director' => [120000, 250000],
            'vp' => [150000, 300000],
            'vice president' => [150000, 300000],
            'senior manager' => [90000, 150000],
            'manager' => [70000, 120000],

            // Professional
            'senior analyst' => [80000, 120000],
            'analyst' => [60000, 90000],
            'associate' => [70000, 110000],
            'senior associate' => [90000, 140000],
            'consultant' => [70000, 120000],
            'senior consultant' => [90000, 150000],

            // Entry Level
            'junior' => [40000, 60000],
            'graduate' => [35000, 50000],
            'trainee' => [30000, 45000],
            'intern' => [25000, 40000]
        ];

        // Find matching salary range
        $min = 60000;
        $max = 100000;

        foreach ($salary_ranges as $keyword => $range) {
            if (stripos($title_lower, $keyword) !== false) {
                $min = $range[0];
                $max = $range[1];
                break;
            }
        }

        // Adjust for UK market
        if ($currency === 'GBP') {
            $min *= 0.8;
            $max *= 0.8;
        }

        // Format display
        $symbol = $currency === 'GBP' ? '£' : '$';
        $display = sprintf('%s%dk - %s%dk', $symbol, round($min / 1000), $symbol, round($max / 1000));

        return [
            'min' => $min,
            'max' => $max,
            'currency' => $currency,
            'display' => $display
        ];
    }
}

// Initialize
function sffc_xml_fetcher()
{
    return new SFFC_XML_Job_Fetcher();
}
