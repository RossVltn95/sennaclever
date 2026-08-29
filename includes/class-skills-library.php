<?php
/**
 * Skills Library
 * Intelligent skill assignment based on job title, description, and company type
 *
 * @package SennaCareers
 * @since 11.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Skills_Library {

    private static $instance = null;

    /**
     * Role-based skill mappings
     * Maps job title keywords to relevant skills
     */
    private $role_skills = array(
        // Analyst roles
        'analyst' => array(
            'skills' => array('Financial Modeling', 'Excel', 'PowerPoint', 'Valuation', 'Research', 'Data Analysis'),
            'weight' => 10
        ),
        'financial analyst' => array(
            'skills' => array('Financial Modeling', 'Excel', 'Budgeting', 'Forecasting', 'Variance Analysis', 'FP&A'),
            'weight' => 15
        ),
        'investment analyst' => array(
            'skills' => array('DCF', 'LBO Modeling', 'Due Diligence', 'Market Research', 'Valuation', 'Comps Analysis'),
            'weight' => 15
        ),
        'research analyst' => array(
            'skills' => array('Equity Research', 'Financial Analysis', 'Industry Research', 'Report Writing', 'Sector Analysis'),
            'weight' => 15
        ),
        'credit analyst' => array(
            'skills' => array('Credit Analysis', 'Risk Assessment', 'Financial Statements', 'Underwriting', 'Debt Structuring'),
            'weight' => 15
        ),
        'quantitative analyst' => array(
            'skills' => array('Python', 'Statistics', 'Quantitative Modeling', 'Risk Analysis', 'SQL', 'Machine Learning'),
            'weight' => 15
        ),
        'data analyst' => array(
            'skills' => array('SQL', 'Python', 'Data Visualization', 'Excel', 'Tableau', 'Power BI'),
            'weight' => 15
        ),
        'junior analyst' => array(
            'skills' => array('Excel', 'Financial Modeling', 'Research', 'PowerPoint', 'Attention to Detail', 'Data Entry'),
            'weight' => 18
        ),
        'senior analyst' => array(
            'skills' => array('Advanced Modeling', 'Deal Support', 'Mentoring', 'Process Improvement', 'Stakeholder Management'),
            'weight' => 15
        ),
        'business analyst' => array(
            'skills' => array('Business Analysis', 'Requirements Gathering', 'Process Mapping', 'Stakeholder Management', 'Agile'),
            'weight' => 15
        ),
        'risk analyst' => array(
            'skills' => array('Risk Assessment', 'VaR', 'Stress Testing', 'Regulatory Reporting', 'Risk Frameworks'),
            'weight' => 15
        ),
        'esg analyst' => array(
            'skills' => array('ESG Analysis', 'Sustainability Reporting', 'Impact Measurement', 'SASB', 'GRI Standards'),
            'weight' => 15
        ),
        'fp&a analyst' => array(
            'skills' => array('FP&A', 'Budgeting', 'Forecasting', 'Variance Analysis', 'Management Reporting', 'Hyperion'),
            'weight' => 15
        ),

        // Associate roles
        'associate' => array(
            'skills' => array('Financial Modeling', 'Deal Execution', 'Due Diligence', 'PowerPoint', 'Transaction Support'),
            'weight' => 10
        ),
        'investment associate' => array(
            'skills' => array('LBO Modeling', 'DCF', 'Deal Sourcing', 'Due Diligence', 'Portfolio Support', 'CIM Review'),
            'weight' => 15
        ),
        'private equity associate' => array(
            'skills' => array('LBO Modeling', 'Due Diligence', 'Deal Execution', 'Portfolio Management', 'Valuation', 'Add-on Acquisitions'),
            'weight' => 20
        ),
        'vc associate' => array(
            'skills' => array('Market Sizing', 'Competitive Analysis', 'Startup Evaluation', 'Term Sheets', 'Cap Tables'),
            'weight' => 18
        ),
        'venture associate' => array(
            'skills' => array('Deal Flow', 'Founder Meetings', 'Investment Memos', 'Sector Research', 'Portfolio Support'),
            'weight' => 18
        ),
        'senior associate' => array(
            'skills' => array('Deal Leadership', 'Junior Mentoring', 'IC Presentations', 'Complex Modeling', 'Client Management'),
            'weight' => 15
        ),
        'investment banking associate' => array(
            'skills' => array('Pitch Books', 'M&A Execution', 'Capital Markets', 'Financial Modeling', 'Client Coverage'),
            'weight' => 18
        ),

        // VP / Director roles
        'vice president' => array(
            'skills' => array('Deal Leadership', 'Client Management', 'Team Management', 'Strategic Analysis', 'Execution Oversight'),
            'weight' => 10
        ),
        'director' => array(
            'skills' => array('Deal Origination', 'Client Relations', 'Team Leadership', 'Strategic Planning', 'P&L Responsibility'),
            'weight' => 10
        ),
        'principal' => array(
            'skills' => array('Deal Sourcing', 'Investment Committee', 'Portfolio Oversight', 'Value Creation', 'Board Observer'),
            'weight' => 12
        ),
        'svp' => array(
            'skills' => array('Business Development', 'Senior Client Coverage', 'Team Building', 'Strategic Initiatives'),
            'weight' => 12
        ),

        // Partner / MD roles
        'partner' => array(
            'skills' => array('Deal Origination', 'Fundraising', 'LP Relations', 'Board Governance', 'Strategic Vision'),
            'weight' => 15
        ),
        'managing director' => array(
            'skills' => array('Business Development', 'Client Leadership', 'P&L Management', 'Strategic Direction'),
            'weight' => 15
        ),
        'general partner' => array(
            'skills' => array('Fund Strategy', 'LP Relationships', 'Investment Committee Chair', 'Portfolio Governance'),
            'weight' => 15
        ),

        // Operations & Finance
        'controller' => array(
            'skills' => array('Fund Accounting', 'Financial Reporting', 'GAAP', 'Audit Management', 'Internal Controls'),
            'weight' => 15
        ),
        'fund controller' => array(
            'skills' => array('Fund Accounting', 'NAV Calculations', 'Investor Reporting', 'Waterfall Calculations', 'Carried Interest'),
            'weight' => 20
        ),
        'accountant' => array(
            'skills' => array('Financial Reporting', 'GAAP', 'Reconciliations', 'Month-End Close', 'Excel', 'Journal Entries'),
            'weight' => 12
        ),
        'fund accountant' => array(
            'skills' => array('Fund Accounting', 'NAV', 'Capital Calls', 'Distributions', 'Investor Statements', 'Partnership Accounting'),
            'weight' => 18
        ),
        'cfo' => array(
            'skills' => array('Financial Strategy', 'Fundraising', 'Investor Relations', 'Risk Management', 'Board Reporting'),
            'weight' => 15
        ),
        'finance manager' => array(
            'skills' => array('Financial Planning', 'Budgeting', 'Reporting', 'Team Management', 'Process Improvement'),
            'weight' => 12
        ),
        'operations' => array(
            'skills' => array('Process Optimization', 'Project Management', 'Compliance', 'Vendor Management', 'Workflow Automation'),
            'weight' => 8
        ),
        'chief operating officer' => array(
            'skills' => array('Operations Strategy', 'Process Excellence', 'Team Leadership', 'Vendor Management', 'Scalability'),
            'weight' => 15
        ),
        'treasury' => array(
            'skills' => array('Cash Management', 'Liquidity Planning', 'FX Management', 'Banking Relationships', 'Working Capital'),
            'weight' => 12
        ),
        'tax' => array(
            'skills' => array('Tax Compliance', 'Tax Planning', 'K-1s', 'UBTI', 'International Tax', 'Tax Structuring'),
            'weight' => 12
        ),

        // Investor Relations
        'investor relations' => array(
            'skills' => array('LP Communications', 'Fundraising Support', 'Reporting', 'CRM', 'Presentation Skills', 'DDQ Management'),
            'weight' => 15
        ),
        'ir' => array(
            'skills' => array('Investor Communications', 'Fundraising', 'Due Diligence Coordination', 'Marketing', 'Relationship Management'),
            'weight' => 10
        ),
        'fundraising' => array(
            'skills' => array('Capital Raising', 'LP Outreach', 'Roadshows', 'Investor Targeting', 'Pitch Materials'),
            'weight' => 15
        ),
        'client services' => array(
            'skills' => array('Client Relationship Management', 'Reporting', 'Query Resolution', 'Service Excellence'),
            'weight' => 12
        ),

        // Compliance & Legal
        'compliance' => array(
            'skills' => array('Regulatory Compliance', 'SEC Filings', 'Policy Development', 'Risk Assessment', 'AML/KYC'),
            'weight' => 12
        ),
        'legal' => array(
            'skills' => array('Contract Review', 'Transaction Documentation', 'Regulatory Compliance', 'Negotiation', 'Fund Formation'),
            'weight' => 10
        ),
        'counsel' => array(
            'skills' => array('Legal Advisory', 'Transaction Support', 'Fund Formation', 'Regulatory', 'Corporate Governance'),
            'weight' => 12
        ),
        'aml' => array(
            'skills' => array('AML Compliance', 'KYC', 'Transaction Monitoring', 'Sanctions Screening', 'SAR Filing'),
            'weight' => 15
        ),

        // Technology
        'technology' => array(
            'skills' => array('Systems Management', 'Data Infrastructure', 'Cybersecurity', 'Vendor Management'),
            'weight' => 8
        ),
        'developer' => array(
            'skills' => array('Python', 'SQL', 'APIs', 'Cloud Infrastructure', 'Agile', 'Git'),
            'weight' => 12
        ),
        'engineer' => array(
            'skills' => array('Software Development', 'System Architecture', 'Problem Solving', 'Technical Leadership', 'DevOps'),
            'weight' => 10
        ),
        'data engineer' => array(
            'skills' => array('ETL', 'Data Pipelines', 'AWS', 'Spark', 'Data Warehousing', 'Airflow'),
            'weight' => 15
        ),
        'data scientist' => array(
            'skills' => array('Machine Learning', 'Python', 'Statistical Modeling', 'NLP', 'Deep Learning', 'R'),
            'weight' => 15
        ),

        // Deal / Transaction specific
        'deal' => array(
            'skills' => array('Deal Execution', 'Due Diligence', 'Negotiation', 'Transaction Management', 'Closing Coordination'),
            'weight' => 8
        ),
        'm&a' => array(
            'skills' => array('M&A Advisory', 'Valuation', 'Deal Structuring', 'Negotiation', 'Integration', 'Synergy Analysis'),
            'weight' => 15
        ),
        'origination' => array(
            'skills' => array('Deal Sourcing', 'Relationship Building', 'Market Analysis', 'Pipeline Management', 'Cold Outreach'),
            'weight' => 12
        ),
        'business development' => array(
            'skills' => array('BD Strategy', 'Partnership Development', 'Market Expansion', 'Client Acquisition', 'Networking'),
            'weight' => 12
        ),

        // Portfolio
        'portfolio' => array(
            'skills' => array('Portfolio Monitoring', 'Value Creation', 'Strategic Support', 'Board Participation', 'Operating Improvement'),
            'weight' => 10
        ),
        'portfolio manager' => array(
            'skills' => array('Portfolio Construction', 'Risk Management', 'Asset Allocation', 'Performance Analysis', 'Rebalancing'),
            'weight' => 15
        ),
        'operating partner' => array(
            'skills' => array('Operational Excellence', 'Value Creation', 'CEO Coaching', 'Transformation', 'Best Practices'),
            'weight' => 15
        ),
        'value creation' => array(
            'skills' => array('EBITDA Improvement', 'Cost Optimization', 'Revenue Growth', 'Digital Transformation', '100-Day Plan'),
            'weight' => 15
        ),

        // Administrative
        'executive assistant' => array(
            'skills' => array('Calendar Management', 'Travel Coordination', 'Communication', 'Organization', 'Discretion'),
            'weight' => 12
        ),
        'office manager' => array(
            'skills' => array('Office Operations', 'Vendor Management', 'Event Planning', 'Administration', 'Facilities'),
            'weight' => 12
        ),

        // Recruiting
        'recruiting' => array(
            'skills' => array('Talent Acquisition', 'Candidate Assessment', 'Interview Coordination', 'ATS', 'Sourcing'),
            'weight' => 10
        ),
        'talent' => array(
            'skills' => array('Talent Management', 'HR Strategy', 'Employee Development', 'Culture Building', 'Performance Management'),
            'weight' => 10
        ),
        'human resources' => array(
            'skills' => array('HR Management', 'Employee Relations', 'Benefits Administration', 'Compliance', 'HRIS'),
            'weight' => 12
        ),

        // Trading
        'trader' => array(
            'skills' => array('Trade Execution', 'Market Making', 'Risk Management', 'Order Flow', 'Bloomberg Terminal'),
            'weight' => 15
        ),
        'trading' => array(
            'skills' => array('Execution', 'Liquidity Management', 'Best Execution', 'TCA', 'OMS/EMS'),
            'weight' => 12
        ),

        // Real Estate
        'real estate' => array(
            'skills' => array('Property Analysis', 'Underwriting', 'Argus', 'Cap Rate Analysis', 'Lease Analysis'),
            'weight' => 12
        ),
        'acquisitions' => array(
            'skills' => array('Deal Sourcing', 'Underwriting', 'Market Analysis', 'Broker Relations', 'LOI Negotiation'),
            'weight' => 15
        ),
        'asset manager' => array(
            'skills' => array('Asset Management', 'Property Oversight', 'NOI Optimization', 'Tenant Relations', 'Capex Planning'),
            'weight' => 15
        ),

        // Structured Finance
        'structured' => array(
            'skills' => array('Structured Products', 'CLO', 'ABS', 'Cash Flow Modeling', 'Waterfall Analysis'),
            'weight' => 12
        ),
        'securitization' => array(
            'skills' => array('Securitization', 'Intex', 'Deal Structuring', 'Rating Agency', 'Tranche Analysis'),
            'weight' => 15
        ),

        // ESG
        'sustainability' => array(
            'skills' => array('ESG Integration', 'Impact Reporting', 'Carbon Footprint', 'TCFD', 'Sustainable Investing'),
            'weight' => 12
        ),
        'impact' => array(
            'skills' => array('Impact Measurement', 'Theory of Change', 'IRIS+', 'SDG Alignment', 'Impact Due Diligence'),
            'weight' => 15
        ),
    );

    /**
     * Industry/Company type skill additions
     */
    private $industry_skills = array(
        'private equity' => array('Private Equity', 'LBO', 'Portfolio Companies', 'Buyout'),
        'pe' => array('Private Equity', 'Buyout', 'Value Creation', 'Control Investments'),
        'venture capital' => array('Venture Capital', 'Startup Ecosystem', 'Growth Investing', 'Series A-C'),
        'vc' => array('Venture Capital', 'Early Stage', 'Tech Investing', 'Seed Funding'),
        'hedge fund' => array('Hedge Fund', 'Trading', 'Risk Management', 'Alpha Generation'),
        'asset management' => array('Asset Management', 'Portfolio Management', 'Client Service', 'AUM'),
        'investment bank' => array('Investment Banking', 'Capital Markets', 'Advisory', 'DCM/ECM'),
        'real estate' => array('Real Estate', 'Property Analysis', 'Asset Management', 'REIT'),
        'infrastructure' => array('Infrastructure', 'Project Finance', 'Long-term Investing', 'PPP'),
        'credit' => array('Credit', 'Fixed Income', 'Debt Analysis', 'Leveraged Finance'),
        'growth equity' => array('Growth Equity', 'Scaling Companies', 'Minority Investments', 'Pre-IPO'),
        'family office' => array('Family Office', 'Wealth Management', 'Multi-Asset', 'Direct Investing'),
        'fund of funds' => array('Fund of Funds', 'Manager Selection', 'Portfolio Construction', 'GP Monitoring'),
        'secondaries' => array('Secondary Transactions', 'LP Stakes', 'GP-led', 'Continuation Funds'),
        'distressed' => array('Distressed Investing', 'Turnaround', 'Restructuring', 'Special Situations'),
        'mezzanine' => array('Mezzanine Finance', 'Subordinated Debt', 'Unitranche', 'PIK'),
        'direct lending' => array('Direct Lending', 'Middle Market', 'Sponsor Finance', 'ABL'),
        'sovereign wealth' => array('Sovereign Wealth', 'Long-term Capital', 'Co-Investment', 'Direct Deals'),
        'pension' => array('Pension Fund', 'Liability Matching', 'LDI', 'Asset-Liability'),
        'endowment' => array('Endowment', 'OCIO', 'Spending Policy', 'Perpetual Capital'),
        'insurance' => array('Insurance', 'ALM', 'Statutory Accounting', 'Surplus Management'),
        'wealth management' => array('Wealth Management', 'UHNW', 'Family Governance', 'Estate Planning'),
        'fintech' => array('Fintech', 'Digital Assets', 'Blockchain', 'Payment Systems'),
        'proptech' => array('PropTech', 'Real Estate Technology', 'Smart Buildings', 'Digital Twin'),
        'healthtech' => array('HealthTech', 'Digital Health', 'MedTech', 'Life Sciences'),
        'cleantech' => array('CleanTech', 'Renewable Energy', 'Climate Tech', 'Energy Transition'),
    );

    /**
     * Seniority-based skill additions
     */
    private $seniority_skills = array(
        'entry' => array('Attention to Detail', 'Learning Agility', 'Team Collaboration', 'Time Management', 'Proactive'),
        'junior' => array('Analytical Skills', 'Problem Solving', 'Communication', 'Self-Starter', 'Adaptability'),
        'mid' => array('Project Management', 'Stakeholder Management', 'Mentoring', 'Cross-Functional', 'Process Improvement'),
        'senior' => array('Leadership', 'Strategic Thinking', 'Decision Making', 'Influence', 'Change Management'),
        'executive' => array('Executive Leadership', 'Board Management', 'Vision & Strategy', 'P&L Ownership', 'Transformation'),
    );

    /**
     * Tool/Platform skills commonly needed
     */
    private $common_tools = array(
        'finance' => array('Excel', 'PowerPoint', 'Bloomberg', 'Capital IQ', 'FactSet', 'PitchBook'),
        'data' => array('SQL', 'Python', 'Tableau', 'Power BI', 'R', 'Alteryx'),
        'operations' => array('Salesforce', 'Microsoft Office', 'Monday.com', 'Asana', 'Jira'),
        'accounting' => array('Excel', 'NetSuite', 'QuickBooks', 'SAP', 'Workday'),
        'ir' => array('Dynamo', 'iLevel', 'Backstop', 'DealCloud', 'Salesforce'),
        'compliance' => array('ComplySci', 'ACA', 'RegTech', 'NICE Actimize'),
        'trading' => array('Bloomberg', 'Reuters', 'Charles River', 'Aladdin', 'OMS'),
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get skills for a job based on title, description, and meta
     *
     * @param int $job_id Job post ID
     * @param array $meta Optional pre-fetched meta
     * @return array Array of skills
     */
    public function get_job_skills($job_id, $meta = null) {
        if ($meta === null) {
            $meta = get_post_meta($job_id);
        }

        $skills = array();
        $scores = array(); // Track skill relevance scores

        // 1. First try to get stored skills
        $stored_skills = $this->get_stored_skills($meta);
        foreach ($stored_skills as $skill) {
            $skills[$skill] = 100; // Stored skills get highest priority
        }

        // 2. Extract skills from job title
        $title = get_the_title($job_id);
        $title_skills = $this->extract_skills_from_title($title);
        foreach ($title_skills as $skill => $score) {
            if (!isset($skills[$skill])) {
                $skills[$skill] = $score;
            }
        }

        // 3. Add industry-specific skills
        $industry_skills = $this->get_industry_skills($title, $meta);
        foreach ($industry_skills as $skill) {
            if (!isset($skills[$skill])) {
                $skills[$skill] = 50;
            }
        }

        // 4. Add seniority-based skills
        $seniority = $this->detect_seniority($title);
        if (isset($this->seniority_skills[$seniority])) {
            foreach ($this->seniority_skills[$seniority] as $skill) {
                if (!isset($skills[$skill])) {
                    $skills[$skill] = 30;
                }
            }
        }

        // 5. Extract from job description if still need more
        if (count($skills) < 5) {
            $job_post = get_post($job_id);
            $content = $job_post->post_content ?? '';
            $content_skills = $this->extract_skills_from_content($content);
            foreach ($content_skills as $skill => $score) {
                if (!isset($skills[$skill])) {
                    $skills[$skill] = $score;
                }
            }
        }

        // 6. Add common tools based on role type
        $role_type = $this->detect_role_type($title);
        if ($role_type && isset($this->common_tools[$role_type])) {
            foreach ($this->common_tools[$role_type] as $skill) {
                if (!isset($skills[$skill])) {
                    $skills[$skill] = 20;
                }
            }
        }

        // Sort by score and return top skills
        arsort($skills);
        return array_keys(array_slice($skills, 0, 8, true));
    }

    /**
     * Get stored skills from meta fields
     */
    private function get_stored_skills($meta) {
        $skills = array();

        // Try various skill meta fields
        $skill_fields = array('sffc_skills', 'sffc_skills_list', 'sffc_technical_skills', 'sffc_soft_skills');

        foreach ($skill_fields as $field) {
            if (!empty($meta[$field][0])) {
                $value = $meta[$field][0];

                // Try JSON decode
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $skills = array_merge($skills, $decoded);
                    continue;
                }

                // Try unserialize
                $unserialized = maybe_unserialize($value);
                if (is_array($unserialized)) {
                    $skills = array_merge($skills, $unserialized);
                    continue;
                }

                // Try comma-separated
                if (strpos($value, ',') !== false) {
                    $skills = array_merge($skills, array_map('trim', explode(',', $value)));
                }
            }
        }

        return array_filter(array_unique($skills));
    }

    /**
     * Extract skills from job title
     */
    private function extract_skills_from_title($title) {
        $skills = array();
        $title_lower = strtolower($title);

        foreach ($this->role_skills as $keyword => $data) {
            if (strpos($title_lower, $keyword) !== false) {
                foreach ($data['skills'] as $skill) {
                    $current_score = $skills[$skill] ?? 0;
                    $skills[$skill] = max($current_score, $data['weight'] * 5);
                }
            }
        }

        return $skills;
    }

    /**
     * Get industry-specific skills
     */
    private function get_industry_skills($title, $meta) {
        $skills = array();
        $title_lower = strtolower($title);

        // Check title and company type meta
        $company_type = strtolower($meta['sffc_company_type'][0] ?? '');
        $company = strtolower($meta['sffc_company'][0] ?? $meta['sffc_actual_company'][0] ?? '');

        $search_text = $title_lower . ' ' . $company_type . ' ' . $company;

        foreach ($this->industry_skills as $keyword => $industry_skills) {
            if (strpos($search_text, $keyword) !== false) {
                $skills = array_merge($skills, $industry_skills);
            }
        }

        return array_unique($skills);
    }

    /**
     * Detect seniority level from title
     */
    private function detect_seniority($title) {
        $title_lower = strtolower($title);

        $seniority_map = array(
            'executive' => array('partner', 'managing director', 'md', 'ceo', 'cfo', 'coo', 'chief', 'head of'),
            'senior' => array('senior', 'director', 'principal', 'vice president', 'vp'),
            'mid' => array('manager', 'lead', 'associate'),
            'junior' => array('junior', 'jr'),
            'entry' => array('analyst', 'intern', 'trainee', 'entry', 'graduate'),
        );

        foreach ($seniority_map as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($title_lower, $keyword) !== false) {
                    return $level;
                }
            }
        }

        return 'mid'; // Default to mid-level
    }

    /**
     * Detect role type for tool recommendations
     */
    private function detect_role_type($title) {
        $title_lower = strtolower($title);

        $role_types = array(
            'finance' => array('analyst', 'associate', 'investment', 'finance', 'portfolio', 'fund'),
            'data' => array('data', 'quantitative', 'quant', 'research', 'analytics'),
            'operations' => array('operations', 'ops', 'project', 'office', 'administrative'),
            'accounting' => array('accountant', 'accounting', 'controller', 'bookkeeper', 'audit'),
        );

        foreach ($role_types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($title_lower, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return 'finance'; // Default for PE/finance jobs
    }

    /**
     * Extract skills from job description content
     */
    private function extract_skills_from_content($content) {
        $skills = array();
        $content_lower = strtolower(wp_strip_all_tags($content));

        // Skills to look for in content
        $content_skills = array(
            // Technical Tools
            'Excel' => array('excel', 'spreadsheet', 'pivot table'),
            'PowerPoint' => array('powerpoint', 'presentation', 'slide deck'),
            'Python' => array('python', 'pandas', 'numpy'),
            'SQL' => array('sql', 'database', 'queries'),
            'VBA' => array('vba', 'macro', 'excel automation'),
            'Bloomberg' => array('bloomberg', 'terminal', 'bbt'),
            'Capital IQ' => array('capital iq', 'capitaliq', 's&p capital'),
            'FactSet' => array('factset'),
            'PitchBook' => array('pitchbook'),
            'Tableau' => array('tableau', 'data visualization'),
            'Power BI' => array('power bi', 'powerbi'),
            'Alteryx' => array('alteryx'),
            'R' => array(' r ', 'r programming', 'rstudio'),
            'MATLAB' => array('matlab'),
            'SAS' => array(' sas ', 'sas programming'),
            'Argus' => array('argus', 'argus enterprise'),
            'CoStar' => array('costar'),
            'Yardi' => array('yardi'),

            // Finance concepts
            'Financial Modeling' => array('financial model', 'modeling', '3-statement'),
            'Valuation' => array('valuation', 'dcf', 'comparable', 'precedent'),
            'LBO' => array('lbo', 'leveraged buyout', 'buyout model'),
            'M&A' => array('m&a', 'merger', 'acquisition', 'deal execution'),
            'Due Diligence' => array('due diligence', 'diligence', 'dd process'),
            'DCF' => array('dcf', 'discounted cash flow'),
            'Comps Analysis' => array('comps', 'comparable companies', 'trading comps'),
            'Accretion/Dilution' => array('accretion', 'dilution', 'eps impact'),
            'Cap Table' => array('cap table', 'capitalization table'),
            'Waterfall' => array('waterfall', 'distribution waterfall'),
            'IRR' => array('irr', 'internal rate of return', 'moic'),
            'NAV' => array('nav', 'net asset value'),
            'EBITDA' => array('ebitda', 'adjusted ebitda'),
            'Enterprise Value' => array('enterprise value', 'ev/', 'ev/ebitda'),

            // Certifications
            'CFA' => array('cfa', 'chartered financial analyst', 'cfa charterholder'),
            'CPA' => array('cpa', 'certified public accountant'),
            'MBA' => array('mba', 'master of business'),
            'CAIA' => array('caia', 'chartered alternative'),
            'FRM' => array('frm', 'financial risk manager'),
            'ACCA' => array('acca'),
            'ACA' => array('aca', 'chartered accountant'),
            'Series 7' => array('series 7', 'series 63', 'series 79'),
            'CISI' => array('cisi', 'chartered institute'),
            'CMA' => array('cma', 'certified management accountant'),

            // Industry Knowledge
            'Private Equity' => array('private equity', 'pe fund', 'buyout'),
            'Venture Capital' => array('venture capital', 'vc fund', 'startup'),
            'Hedge Fund' => array('hedge fund', 'long/short', 'event driven'),
            'Real Estate' => array('real estate', 'property', 'reit'),
            'Infrastructure' => array('infrastructure', 'infra fund'),
            'Credit' => array('credit', 'leveraged finance', 'high yield'),
            'ESG' => array('esg', 'sustainable', 'impact investing'),

            // Soft skills
            'Communication' => array('communication', 'written and verbal', 'articulate'),
            'Leadership' => array('leadership', 'lead team', 'manage team'),
            'Problem Solving' => array('problem solving', 'analytical thinking'),
            'Team Collaboration' => array('team player', 'collaborative', 'cross-functional'),
            'Attention to Detail' => array('attention to detail', 'detail-oriented', 'meticulous'),
            'Time Management' => array('time management', 'prioritization', 'deadline'),
            'Client Facing' => array('client facing', 'client interaction', 'client service'),
            'Presentation Skills' => array('presentation skills', 'public speaking'),
            'Stakeholder Management' => array('stakeholder', 'senior management'),
            'Multitasking' => array('multitasking', 'multiple priorities', 'fast-paced'),

            // Process & Compliance
            'GAAP' => array('gaap', 'us gaap', 'accounting standards'),
            'IFRS' => array('ifrs', 'international financial'),
            'SOX' => array('sox', 'sarbanes-oxley', '404 compliance'),
            'AML/KYC' => array('aml', 'kyc', 'anti-money laundering'),
            'SEC Reporting' => array('sec reporting', 'form adv', '13f'),
        );

        foreach ($content_skills as $skill => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content_lower, $keyword) !== false) {
                    $skills[$skill] = 40;
                    break;
                }
            }
        }

        return $skills;
    }

    /**
     * Get skill type for styling
     */
    public function get_skill_type($skill) {
        $skill_lower = strtolower($skill);

        // Technical tools
        $technical = array('excel', 'powerpoint', 'python', 'sql', 'vba', 'bloomberg',
            'factset', 'capital iq', 'pitchbook', 'tableau', 'power bi', 'sap', 'oracle',
            'salesforce', 'netsuite', 'alteryx', 'matlab', 'argus', 'costar', 'yardi',
            'dynamo', 'ilevel', 'backstop', 'dealcloud', 'workday', 'hyperion', 'aladdin',
            'git', 'aws', 'spark', 'airflow', 'etl', 'api', 'oms', 'ems', 'intex');

        // Finance modeling skills
        $modeling = array('financial modeling', 'lbo', 'dcf', 'valuation', 'comps',
            '3-statement', 'waterfall', 'cap table', 'accretion', 'irr', 'moic', 'nav',
            'ebitda', 'enterprise value', 'underwriting');

        // Certifications
        $certifications = array('cfa', 'cpa', 'mba', 'caia', 'series 7', 'series 63', 'acca', 'frm',
            'aca', 'cisi', 'cma', 'prm', 'qcf');

        // Finance/Industry terms
        $finance = array('private equity', 'venture capital', 'investment banking',
            'portfolio management', 'fund accounting', 'investor relations',
            'asset management', 'credit analysis', 'risk management', 'm&a', 'due diligence',
            'hedge fund', 'real estate', 'growth equity', 'secondaries', 'direct lending',
            'infrastructure', 'distressed', 'mezzanine', 'clo', 'abs', 'securitization',
            'esg', 'impact', 'sustainability', 'fintech', 'cleantech');

        // Seniority/Role indicators
        $seniority = array('leadership', 'team management', 'strategic', 'executive',
            'board', 'client management', 'deal origination', 'p&l', 'transformation',
            'vision', 'mentoring', 'coaching');

        // Soft skills
        $soft = array('communication', 'problem solving', 'attention to detail', 'time management',
            'collaboration', 'team player', 'analytical', 'proactive', 'adaptability',
            'multitasking', 'presentation skills', 'stakeholder');

        foreach ($technical as $t) {
            if (strpos($skill_lower, $t) !== false) {
                return 'technical';
            }
        }

        foreach ($modeling as $m) {
            if (strpos($skill_lower, $m) !== false) {
                return 'modeling';
            }
        }

        foreach ($certifications as $c) {
            if ($skill_lower === $c || strpos($skill_lower, $c) !== false) {
                return 'certification';
            }
        }

        foreach ($finance as $f) {
            if (strpos($skill_lower, $f) !== false) {
                return 'finance';
            }
        }

        foreach ($seniority as $s) {
            if (strpos($skill_lower, $s) !== false) {
                return 'seniority';
            }
        }

        foreach ($soft as $so) {
            if (strpos($skill_lower, $so) !== false) {
                return 'soft';
            }
        }

        return 'default';
    }
}

// Initialize
SFFC_Skills_Library::get_instance();
