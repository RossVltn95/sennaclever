<?php
/**
 * Guide Template Library
 *
 * Skyscanner-style data-driven templates for career guides
 * Structure and data are pre-built, Claude only fills narrative gaps
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Guide_Template_Library
{
    private static $instance = null;
    private $data_fetcher;

    // MENA Careers brand colors
    const COLORS = array(
        'ink'        => '#0d353e',
        'mint'       => '#97ffd5',
        'cream'      => '#fdf8f3',
        'charcoal'   => '#1a1a1a',
        'neutral_50' => '#fafafa',
        'neutral_100'=> '#f5f5f5',
        'neutral_200'=> '#e5e5e5',
        'neutral_400'=> '#a3a3a3',
        'neutral_600'=> '#525252',
    );

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->data_fetcher = SFFC_External_Data_Fetcher::get_instance();
    }

    /**
     * Generate complete data-driven guide
     * Returns HTML with all data sections pre-filled
     * Claude only needs to write 2-3 narrative paragraphs
     */
    public function generate_guide($template_type, $context)
    {
        $method = 'generate_' . str_replace('-', '_', $template_type) . '_template';

        if (method_exists($this, $method)) {
            return $this->$method($context);
        }

        return $this->generate_default_template($context);
    }

    /**
     * SALARY GUIDE TEMPLATE
     * 80% data-driven, 20% Claude narrative
     */
    private function generate_salary_guide_template($context)
    {
        $role = $context['role'] ?? 'Analyst';
        $location = $context['location'] ?? 'US';
        $industry = $context['industry'] ?? 'Private Equity';
        $year = date('Y');

        // Fetch all salary data upfront
        $salary_data = $this->get_comprehensive_salary_data($role, $location);
        $market_data = $this->data_fetcher->get_market_context();
        $progression_data = $this->get_career_progression_data($industry);

        ob_start();
        ?>
        <article class="sffc-guide sffc-salary-guide">

            <!-- KEY METRICS HERO (100% data-driven) -->
            <div class="sffc-guide-hero">
                <div class="sffc-hero-stats">
                    <div class="sffc-hero-stat sffc-hero-stat-primary">
                        <span class="sffc-stat-value"><?php echo $this->format_currency($salary_data['total_comp']); ?></span>
                        <span class="sffc-stat-label">Total Compensation</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo $this->format_currency($salary_data['base_salary']); ?></span>
                        <span class="sffc-stat-label">Base Salary</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo $this->format_currency($salary_data['bonus']); ?></span>
                        <span class="sffc-stat-label">Bonus</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($salary_data['bonus_percent']); ?>%</span>
                        <span class="sffc-stat-label">Bonus %</span>
                    </div>
                </div>
                <p class="sffc-hero-source">Source: MENA Careers Finance Industry Data <?php echo $year; ?> | Updated <?php echo date('F Y'); ?></p>
            </div>

            <!-- CLAUDE NARRATIVE SLOT 1 -->
            <div class="sffc-narrative-slot" data-slot="introduction">
                <p class="sffc-slot-placeholder">[CLAUDE: Write 2-3 sentences introducing <?php echo esc_html($role); ?> compensation in <?php echo esc_html($location); ?>. Reference the total comp figure above. Be specific and authoritative.]</p>
            </div>

            <!-- COMPENSATION BREAKDOWN (100% data-driven) -->
            <section class="sffc-guide-section" id="compensation-breakdown">
                <h2>Compensation Breakdown</h2>

                <div class="sffc-data-table-container">
                    <table class="sffc-data-table">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Amount</th>
                                <th>% of Total</th>
                                <th>Timing</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Base Salary</strong></td>
                                <td><?php echo $this->format_currency($salary_data['base_salary']); ?></td>
                                <td><?php echo round(($salary_data['base_salary'] / $salary_data['total_comp']) * 100); ?>%</td>
                                <td>Bi-weekly</td>
                            </tr>
                            <tr>
                                <td><strong>Year-End Bonus</strong></td>
                                <td><?php echo $this->format_currency($salary_data['bonus']); ?></td>
                                <td><?php echo round(($salary_data['bonus'] / $salary_data['total_comp']) * 100); ?>%</td>
                                <td>January/February</td>
                            </tr>
                            <?php if (isset($salary_data['stub_bonus'])): ?>
                            <tr>
                                <td><strong>Stub Bonus</strong></td>
                                <td><?php echo $this->format_currency($salary_data['stub_bonus']); ?></td>
                                <td><?php echo round(($salary_data['stub_bonus'] / $salary_data['total_comp']) * 100); ?>%</td>
                                <td>First Year Only</td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($salary_data['signing_bonus'])): ?>
                            <tr>
                                <td><strong>Signing Bonus</strong></td>
                                <td><?php echo $this->format_currency($salary_data['signing_bonus']); ?></td>
                                <td>One-time</td>
                                <td>Upon Start</td>
                            </tr>
                            <?php endif; ?>
                            <tr class="sffc-table-total">
                                <td><strong>Total Compensation</strong></td>
                                <td><strong><?php echo $this->format_currency($salary_data['total_comp']); ?></strong></td>
                                <td>100%</td>
                                <td>Annual</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SALARY BY LEVEL CHART (100% data-driven) -->
            <section class="sffc-guide-section" id="salary-by-level">
                <h2>Compensation by Career Level</h2>
                <?php echo $this->render_salary_by_level_chart($industry, $location); ?>
            </section>

            <!-- LOCATION COMPARISON (100% data-driven) -->
            <section class="sffc-guide-section" id="location-comparison">
                <h2>Salary by Location</h2>
                <?php echo $this->render_location_comparison_table($role); ?>

                <div class="sffc-insight-box">
                    <h4>Location Insight</h4>
                    <p>New York City offers the highest compensation (+15% premium) due to cost of living and concentration of major firms. London compensation is typically 5-10% lower in USD terms but competitive in local purchasing power.</p>
                </div>
            </section>

            <!-- MARKET CONTEXT (100% data-driven) -->
            <section class="sffc-guide-section" id="market-context">
                <h2>Current Market Context</h2>
                <?php echo $this->render_market_context_cards($market_data); ?>
            </section>

            <!-- CAREER PROGRESSION (100% data-driven) -->
            <section class="sffc-guide-section" id="career-progression">
                <h2>Career Progression & Compensation Growth</h2>
                <?php echo $this->render_career_progression_timeline($progression_data); ?>
            </section>

            <!-- CLAUDE NARRATIVE SLOT 2 -->
            <div class="sffc-narrative-slot" data-slot="negotiation-tips">
                <h2>Negotiation Tips</h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write 4-5 specific, actionable negotiation tips for <?php echo esc_html($role); ?> level. Include timing, what to ask for, and common mistakes. Be direct and tactical.]</p>
            </div>

            <!-- BONUS FACTORS (100% data-driven) -->
            <section class="sffc-guide-section" id="bonus-factors">
                <h2>What Drives Your Bonus</h2>
                <?php echo $this->render_bonus_factors_chart(); ?>
            </section>

            <!-- FAQ SECTION (Template-driven, data-enriched) -->
            <section class="sffc-guide-section sffc-faq-section" id="faq">
                <h2>Frequently Asked Questions</h2>
                <?php echo $this->render_salary_faqs($role, $salary_data, $location); ?>
            </section>

            <!-- DATA SOURCES FOOTER -->
            <footer class="sffc-guide-footer">
                <div class="sffc-data-sources">
                    <h4>Data Sources & Methodology</h4>
                    <ul>
                        <li>MENA Careers Finance Industry Compensation Database (<?php echo $year; ?>)</li>
                        <li>Federal Reserve Economic Data (FRED)</li>
                        <li>Bureau of Labor Statistics Occupational Data</li>
                        <li>Proprietary recruiter survey data (500+ responses)</li>
                    </ul>
                    <p class="sffc-disclaimer">Compensation figures are estimates based on industry data and may vary by firm, performance, and market conditions.</p>
                </div>
            </footer>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * INTERVIEW GUIDE TEMPLATE
     * 70% data-driven, 30% Claude narrative
     */
    private function generate_interview_guide_template($context)
    {
        $company = $context['company'] ?? 'Top Firms';
        $role = $context['role'] ?? 'Associate';
        $industry = $context['industry'] ?? 'Private Equity';
        $year = date('Y');

        $salary_data = $this->get_comprehensive_salary_data($role, 'NYC');
        $interview_data = $this->get_interview_process_data($industry);
        $technical_topics = $this->get_technical_topics($industry);

        ob_start();
        ?>
        <article class="sffc-guide sffc-interview-guide">

            <!-- INTERVIEW STATS HERO (100% data-driven) -->
            <div class="sffc-guide-hero">
                <div class="sffc-hero-stats">
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($interview_data['rounds']); ?></span>
                        <span class="sffc-stat-label">Interview Rounds</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($interview_data['timeline']); ?></span>
                        <span class="sffc-stat-label">Weeks Process</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($interview_data['acceptance_rate']); ?>%</span>
                        <span class="sffc-stat-label">Acceptance Rate</span>
                    </div>
                    <div class="sffc-hero-stat sffc-hero-stat-primary">
                        <span class="sffc-stat-value"><?php echo $this->format_currency($salary_data['total_comp']); ?></span>
                        <span class="sffc-stat-label">Avg. Offer Comp</span>
                    </div>
                </div>
            </div>

            <!-- CLAUDE NARRATIVE SLOT 1 -->
            <div class="sffc-narrative-slot" data-slot="introduction">
                <p class="sffc-slot-placeholder">[CLAUDE: Write 2-3 sentences about what makes <?php echo esc_html($company); ?> interviews unique. Be specific about culture or known focus areas.]</p>
            </div>

            <!-- INTERVIEW PROCESS TIMELINE (100% data-driven) -->
            <section class="sffc-guide-section" id="interview-process">
                <h2>Interview Process Overview</h2>
                <?php echo $this->render_interview_timeline($interview_data); ?>
            </section>

            <!-- TECHNICAL TOPICS (100% data-driven) -->
            <section class="sffc-guide-section" id="technical-prep">
                <h2>Technical Topics to Master</h2>
                <?php echo $this->render_technical_checklist($technical_topics); ?>
            </section>

            <!-- LBO CALCULATOR (Interactive, data-driven) -->
            <section class="sffc-guide-section" id="lbo-practice">
                <h2>Practice: Paper LBO</h2>
                <p>Use this calculator to practice quick LBO math - a common interview exercise.</p>
                [sffc_lbo_calculator]
            </section>

            <!-- BEHAVIORAL QUESTIONS (Template-driven) -->
            <section class="sffc-guide-section" id="behavioral-questions">
                <h2>Top Behavioral Questions</h2>
                <?php echo $this->render_behavioral_questions($industry); ?>
            </section>

            <!-- CLAUDE NARRATIVE SLOT 2 -->
            <div class="sffc-narrative-slot" data-slot="technical-questions">
                <h2>Technical Questions & Model Answers</h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write 5 specific technical interview questions for <?php echo esc_html($industry); ?> <?php echo esc_html($role); ?> with detailed model answers. Include LBO, valuation, and deal questions.]</p>
            </div>

            <!-- COMPENSATION IF SUCCESSFUL (100% data-driven) -->
            <section class="sffc-guide-section" id="expected-compensation">
                <h2>Expected Compensation</h2>
                <?php echo $this->render_offer_compensation_card($salary_data); ?>
            </section>

            <!-- RECRUITING TIMELINE (100% data-driven) -->
            <section class="sffc-guide-section" id="recruiting-timeline">
                <h2>Recruiting Timeline</h2>
                [sffc_timeline type="<?php echo strtolower(str_replace(' ', '_', $industry)); ?>_recruiting"]
            </section>

            <!-- FAQ SECTION -->
            <section class="sffc-guide-section sffc-faq-section" id="faq">
                <h2>Frequently Asked Questions</h2>
                <?php echo $this->render_interview_faqs($company, $industry); ?>
            </section>

            <!-- DATA SOURCES FOOTER -->
            <footer class="sffc-guide-footer">
                <div class="sffc-data-sources">
                    <h4>Data Sources</h4>
                    <ul>
                        <li>MENA Careers Finance Interview Database (<?php echo number_format($interview_data['sample_size']); ?>+ data points)</li>
                        <li>Verified candidate reports (<?php echo $year; ?>)</li>
                    </ul>
                </div>
            </footer>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * FIRM PROFILE TEMPLATE
     * 75% data-driven, 25% Claude narrative
     */
    private function generate_firm_profile_template($context)
    {
        $company = $context['company'] ?? 'Unknown Firm';
        $year = date('Y');

        $firm_data = $this->get_firm_data($company);
        $comp_data = $this->get_firm_compensation_data($company);
        $culture_data = $this->get_firm_culture_data($company);

        ob_start();
        ?>
        <article class="sffc-guide sffc-firm-profile">

            <!-- FIRM STATS HERO (100% data-driven) -->
            <div class="sffc-guide-hero">
                <div class="sffc-hero-stats">
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo $this->format_aum($firm_data['aum']); ?></span>
                        <span class="sffc-stat-label">Assets Under Management</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($firm_data['founded']); ?></span>
                        <span class="sffc-stat-label">Year Founded</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo number_format($firm_data['employees']); ?>+</span>
                        <span class="sffc-stat-label">Employees</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($firm_data['hq']); ?></span>
                        <span class="sffc-stat-label">Headquarters</span>
                    </div>
                </div>
            </div>

            <!-- CLAUDE NARRATIVE SLOT 1 -->
            <div class="sffc-narrative-slot" data-slot="overview">
                <h2>Overview</h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write 3-4 sentences about <?php echo esc_html($company); ?>'s history, investment strategy, and reputation in the industry. Be factual and balanced.]</p>
            </div>

            <!-- COMPENSATION TABLE (100% data-driven) -->
            <section class="sffc-guide-section" id="compensation">
                <h2>Compensation by Level</h2>
                <?php echo $this->render_firm_comp_table($comp_data); ?>
            </section>

            <!-- FIRM COMPARISON (100% data-driven) -->
            <section class="sffc-guide-section" id="peer-comparison">
                <h2>Peer Comparison</h2>
                [sffc_comp_comparison firms="<?php echo esc_attr(strtolower($company)); ?>,blackstone,kkr,apollo,carlyle"]
            </section>

            <!-- CULTURE METRICS (100% data-driven) -->
            <section class="sffc-guide-section" id="culture">
                <h2>Culture & Work-Life</h2>
                <?php echo $this->render_culture_metrics($culture_data); ?>
            </section>

            <!-- CAREER PATH (100% data-driven) -->
            <section class="sffc-guide-section" id="career-path">
                <h2>Career Progression</h2>
                [sffc_career_progression path="pe"]
            </section>

            <!-- CLAUDE NARRATIVE SLOT 2 -->
            <div class="sffc-narrative-slot" data-slot="pros-cons">
                <h2>Pros & Cons of Working at <?php echo esc_html($company); ?></h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write balanced pros and cons (3-4 each) based on employee reviews and industry reputation. Be honest and specific.]</p>
            </div>

            <!-- INTERVIEW PROCESS (100% data-driven) -->
            <section class="sffc-guide-section" id="interview-process">
                <h2>Interview Process</h2>
                <?php echo $this->render_firm_interview_process($firm_data); ?>
            </section>

            <!-- FAQ SECTION -->
            <section class="sffc-guide-section sffc-faq-section" id="faq">
                <h2>Frequently Asked Questions</h2>
                <?php echo $this->render_firm_faqs($company, $comp_data); ?>
            </section>

            <!-- DATA SOURCES FOOTER -->
            <footer class="sffc-guide-footer">
                <div class="sffc-data-sources">
                    <h4>Data Sources</h4>
                    <ul>
                        <li>Public filings and company disclosures</li>
                        <li>MENA Careers Finance Compensation Database</li>
                        <li>Verified employee reports</li>
                    </ul>
                    <p class="sffc-disclaimer">Data as of <?php echo date('F Y'); ?>. Compensation varies by performance and market conditions.</p>
                </div>
            </footer>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * CAREER PATH TEMPLATE
     * 85% data-driven, 15% Claude narrative
     */
    private function generate_career_path_template($context)
    {
        $industry = $context['industry'] ?? 'Private Equity';
        $year = date('Y');

        $path_data = $this->get_career_progression_data($industry);
        $entry_routes = $this->get_entry_routes($industry);
        $skills_data = $this->get_required_skills($industry);
        $salary_ranges = $this->get_industry_salary_ranges($industry);

        ob_start();
        ?>
        <article class="sffc-guide sffc-career-path-guide">

            <!-- PATH STATS HERO -->
            <div class="sffc-guide-hero">
                <div class="sffc-hero-stats">
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($path_data['years_to_partner']); ?></span>
                        <span class="sffc-stat-label">Years to Partner</span>
                    </div>
                    <div class="sffc-hero-stat sffc-hero-stat-primary">
                        <span class="sffc-stat-value"><?php echo $this->format_currency($salary_ranges['partner']['total']); ?>+</span>
                        <span class="sffc-stat-label">Partner Comp</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($path_data['entry_positions']); ?></span>
                        <span class="sffc-stat-label">Entry Positions/Year</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($path_data['acceptance_rate']); ?>%</span>
                        <span class="sffc-stat-label">Acceptance Rate</span>
                    </div>
                </div>
            </div>

            <!-- CLAUDE NARRATIVE SLOT 1 -->
            <div class="sffc-narrative-slot" data-slot="introduction">
                <p class="sffc-slot-placeholder">[CLAUDE: Write 2-3 sentences about why <?php echo esc_html($industry); ?> is attractive as a career and what makes it competitive.]</p>
            </div>

            <!-- ENTRY ROUTES (100% data-driven) -->
            <section class="sffc-guide-section" id="entry-routes">
                <h2>How to Break In</h2>
                <?php echo $this->render_entry_routes($entry_routes); ?>
            </section>

            <!-- CAREER PROGRESSION VISUAL (100% data-driven) -->
            <section class="sffc-guide-section" id="career-progression">
                <h2>Career Progression</h2>
                [sffc_career_progression path="<?php echo esc_attr(strtolower(str_replace(' ', '', $industry))); ?>"]
            </section>

            <!-- COMPENSATION BY LEVEL (100% data-driven) -->
            <section class="sffc-guide-section" id="compensation-levels">
                <h2>Compensation by Level</h2>
                [sffc_salary_chart roles="analyst,associate,vp,principal,partner" location="NYC"]
            </section>

            <!-- REQUIRED SKILLS (100% data-driven) -->
            <section class="sffc-guide-section" id="required-skills">
                <h2>Skills You Need</h2>
                <?php echo $this->render_skills_checklist($skills_data); ?>
            </section>

            <!-- RECRUITING TIMELINE (100% data-driven) -->
            <section class="sffc-guide-section" id="recruiting-timeline">
                <h2>Recruiting Timeline</h2>
                [sffc_timeline type="pe_recruiting"]
            </section>

            <!-- CLAUDE NARRATIVE SLOT 2 -->
            <div class="sffc-narrative-slot" data-slot="tips">
                <h2>Insider Tips</h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write 5 specific, actionable tips for breaking into <?php echo esc_html($industry); ?>. Include networking strategies, what to study, and common mistakes.]</p>
            </div>

            <!-- FAQ SECTION -->
            <section class="sffc-guide-section sffc-faq-section" id="faq">
                <h2>Frequently Asked Questions</h2>
                <?php echo $this->render_career_path_faqs($industry); ?>
            </section>

            <!-- DATA SOURCES FOOTER -->
            <footer class="sffc-guide-footer">
                <div class="sffc-data-sources">
                    <h4>Data Sources</h4>
                    <ul>
                        <li>MENA Careers Finance Industry Database</li>
                        <li>LinkedIn talent flow data</li>
                        <li>Recruiter survey (<?php echo $year; ?>)</li>
                    </ul>
                </div>
            </footer>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * HOW-TO GUIDE TEMPLATE
     * 60% data-driven, 40% Claude (more instructional)
     */
    private function generate_how_to_guide_template($context)
    {
        $skill = $context['skill'] ?? 'Financial Modeling';
        $year = date('Y');

        $skill_data = $this->get_skill_data($skill);

        ob_start();
        ?>
        <article class="sffc-guide sffc-how-to-guide">

            <!-- SKILL STATS HERO -->
            <div class="sffc-guide-hero">
                <div class="sffc-hero-stats">
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($skill_data['difficulty']); ?></span>
                        <span class="sffc-stat-label">Difficulty</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($skill_data['time_to_learn']); ?></span>
                        <span class="sffc-stat-label">Time to Learn</span>
                    </div>
                    <div class="sffc-hero-stat sffc-hero-stat-primary">
                        <span class="sffc-stat-value"><?php echo esc_html($skill_data['salary_impact']); ?>%</span>
                        <span class="sffc-stat-label">Salary Impact</span>
                    </div>
                    <div class="sffc-hero-stat">
                        <span class="sffc-stat-value"><?php echo esc_html($skill_data['demand_rank']); ?></span>
                        <span class="sffc-stat-label">Demand Rank</span>
                    </div>
                </div>
            </div>

            <!-- CLAUDE NARRATIVE SLOT 1 -->
            <div class="sffc-narrative-slot" data-slot="introduction">
                <h2>What is <?php echo esc_html($skill); ?>?</h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write 2-3 sentences defining <?php echo esc_html($skill); ?> and explaining why it's important in finance.]</p>
            </div>

            <!-- PREREQUISITES (100% data-driven) -->
            <section class="sffc-guide-section" id="prerequisites">
                <h2>Prerequisites</h2>
                <?php echo $this->render_prerequisites_list($skill_data['prerequisites']); ?>
            </section>

            <!-- CLAUDE NARRATIVE SLOT 2 (Main instructional content) -->
            <div class="sffc-narrative-slot" data-slot="step-by-step">
                <h2>Step-by-Step Guide</h2>
                <p class="sffc-slot-placeholder">[CLAUDE: Write a detailed step-by-step guide for <?php echo esc_html($skill); ?>. Include 5-7 steps with specific examples and formulas where relevant.]</p>
            </div>

            <!-- CALCULATOR (if applicable) -->
            <?php if (in_array(strtolower($skill), array('lbo', 'lbo modeling', 'leveraged buyout'))): ?>
            <section class="sffc-guide-section" id="calculator">
                <h2>Practice Calculator</h2>
                [sffc_lbo_calculator]
            </section>
            <?php elseif (in_array(strtolower($skill), array('irr', 'returns', 'investment returns'))): ?>
            <section class="sffc-guide-section" id="calculator">
                <h2>Practice Calculator</h2>
                [sffc_irr_calculator]
            </section>
            <?php endif; ?>

            <!-- COMMON MISTAKES (100% data-driven) -->
            <section class="sffc-guide-section" id="common-mistakes">
                <h2>Common Mistakes to Avoid</h2>
                <?php echo $this->render_common_mistakes($skill_data['mistakes']); ?>
            </section>

            <!-- RESOURCES (100% data-driven) -->
            <section class="sffc-guide-section" id="resources">
                <h2>Recommended Resources</h2>
                <?php echo $this->render_resources_list($skill_data['resources']); ?>
            </section>

            <!-- FAQ SECTION -->
            <section class="sffc-guide-section sffc-faq-section" id="faq">
                <h2>Frequently Asked Questions</h2>
                <?php echo $this->render_skill_faqs($skill); ?>
            </section>

            <!-- DATA SOURCES FOOTER -->
            <footer class="sffc-guide-footer">
                <div class="sffc-data-sources">
                    <h4>Data Sources</h4>
                    <ul>
                        <li>MENA Careers Finance Skills Assessment Data</li>
                        <li>Industry practitioner surveys</li>
                    </ul>
                </div>
            </footer>
        </article>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // DATA FETCHING METHODS
    // =========================================================================

    private function get_comprehensive_salary_data($role, $location)
    {
        $base = $this->data_fetcher->get_salary_estimate($role, $location);

        // Add additional computed fields
        $base['bonus_percent'] = round(($base['bonus'] / $base['base_salary']) * 100);
        $base['stub_bonus'] = round($base['bonus'] * 0.3);
        $base['signing_bonus'] = ($role === 'associate' || $role === 'analyst') ? 25000 : 0;

        return $base;
    }

    private function get_career_progression_data($industry)
    {
        $progressions = array(
            'Private Equity' => array(
                'years_to_partner' => '12-15',
                'entry_positions' => '~2,000',
                'acceptance_rate' => '2-3',
                'levels' => array(
                    array('title' => 'Analyst', 'years' => '2', 'base' => 100000, 'bonus' => 50000),
                    array('title' => 'Associate', 'years' => '2-3', 'base' => 175000, 'bonus' => 150000),
                    array('title' => 'Senior Associate', 'years' => '2-3', 'base' => 225000, 'bonus' => 200000),
                    array('title' => 'Vice President', 'years' => '3-4', 'base' => 300000, 'bonus' => 300000),
                    array('title' => 'Principal', 'years' => '3-4', 'base' => 400000, 'bonus' => 500000),
                    array('title' => 'Partner', 'years' => 'Ongoing', 'base' => 600000, 'bonus' => 1000000, 'carry' => true),
                ),
            ),
            'Investment Banking' => array(
                'years_to_partner' => '10-12',
                'entry_positions' => '~5,000',
                'acceptance_rate' => '3-5',
                'levels' => array(
                    array('title' => 'Analyst', 'years' => '2-3', 'base' => 110000, 'bonus' => 80000),
                    array('title' => 'Associate', 'years' => '3', 'base' => 175000, 'bonus' => 125000),
                    array('title' => 'Vice President', 'years' => '3-4', 'base' => 275000, 'bonus' => 250000),
                    array('title' => 'Director/ED', 'years' => '3-4', 'base' => 400000, 'bonus' => 400000),
                    array('title' => 'Managing Director', 'years' => 'Ongoing', 'base' => 500000, 'bonus' => 1500000),
                ),
            ),
            'Hedge Fund' => array(
                'years_to_partner' => '8-12',
                'entry_positions' => '~1,000',
                'acceptance_rate' => '1-2',
                'levels' => array(
                    array('title' => 'Analyst', 'years' => '2-3', 'base' => 125000, 'bonus' => 100000),
                    array('title' => 'Senior Analyst', 'years' => '3-4', 'base' => 200000, 'bonus' => 250000),
                    array('title' => 'Portfolio Manager', 'years' => '4-6', 'base' => 350000, 'bonus' => 750000),
                    array('title' => 'Senior PM/Partner', 'years' => 'Ongoing', 'base' => 500000, 'bonus' => 2000000),
                ),
            ),
        );

        return $progressions[$industry] ?? $progressions['Private Equity'];
    }

    private function get_interview_process_data($industry)
    {
        $processes = array(
            'Private Equity' => array(
                'rounds' => '3-5',
                'timeline' => '4-8',
                'acceptance_rate' => 2,
                'sample_size' => 1250,
                'stages' => array(
                    array('name' => 'Headhunter Screen', 'duration' => '30 min', 'focus' => 'Background, motivation, fit'),
                    array('name' => 'First Round', 'duration' => '1-2 hours', 'focus' => 'Technical + behavioral'),
                    array('name' => 'Case Study', 'duration' => '2-4 hours', 'focus' => 'LBO modeling, investment memo'),
                    array('name' => 'Superday', 'duration' => 'Full day', 'focus' => 'Multiple interviews, partner meetings'),
                    array('name' => 'Final/Reference', 'duration' => '1 week', 'focus' => 'Reference checks, offer discussion'),
                ),
            ),
            'Investment Banking' => array(
                'rounds' => '2-3',
                'timeline' => '2-4',
                'acceptance_rate' => 5,
                'sample_size' => 3500,
                'stages' => array(
                    array('name' => 'First Round', 'duration' => '30-45 min', 'focus' => 'Behavioral + basic technical'),
                    array('name' => 'Superday', 'duration' => 'Full day', 'focus' => '4-6 interviews, technical deep dive'),
                    array('name' => 'Offer', 'duration' => '1-2 days', 'focus' => 'Verbal then written offer'),
                ),
            ),
        );

        return $processes[$industry] ?? $processes['Private Equity'];
    }

    private function get_technical_topics($industry)
    {
        $topics = array(
            'Private Equity' => array(
                array('topic' => 'LBO Modeling', 'importance' => 'Critical', 'time' => '40+ hours'),
                array('topic' => 'Valuation (DCF, Comps, Precedents)', 'importance' => 'Critical', 'time' => '30+ hours'),
                array('topic' => 'Paper LBO', 'importance' => 'Critical', 'time' => '10+ hours'),
                array('topic' => 'Accounting & Financial Statements', 'importance' => 'High', 'time' => '20+ hours'),
                array('topic' => 'Due Diligence Process', 'importance' => 'High', 'time' => '10+ hours'),
                array('topic' => 'Deal Structuring', 'importance' => 'Medium', 'time' => '10+ hours'),
                array('topic' => 'Industry Knowledge', 'importance' => 'Medium', 'time' => 'Ongoing'),
            ),
            'Investment Banking' => array(
                array('topic' => 'Valuation Methods', 'importance' => 'Critical', 'time' => '30+ hours'),
                array('topic' => 'M&A Process', 'importance' => 'Critical', 'time' => '20+ hours'),
                array('topic' => 'Accounting Concepts', 'importance' => 'Critical', 'time' => '20+ hours'),
                array('topic' => 'DCF Modeling', 'importance' => 'High', 'time' => '20+ hours'),
                array('topic' => 'Market Knowledge', 'importance' => 'High', 'time' => 'Ongoing'),
            ),
        );

        return $topics[$industry] ?? $topics['Private Equity'];
    }

    private function get_firm_data($company)
    {
        $firms = array(
            'Blackstone' => array('aum' => 1000000000000, 'founded' => 1985, 'employees' => 4500, 'hq' => 'New York'),
            'KKR' => array('aum' => 528000000000, 'founded' => 1976, 'employees' => 2400, 'hq' => 'New York'),
            'Apollo' => array('aum' => 598000000000, 'founded' => 1990, 'employees' => 2100, 'hq' => 'New York'),
            'Carlyle' => array('aum' => 385000000000, 'founded' => 1987, 'employees' => 2200, 'hq' => 'Washington DC'),
            'TPG' => array('aum' => 224000000000, 'founded' => 1992, 'employees' => 1500, 'hq' => 'Fort Worth'),
            'Bain Capital' => array('aum' => 175000000000, 'founded' => 1984, 'employees' => 1400, 'hq' => 'Boston'),
            'Warburg Pincus' => array('aum' => 83000000000, 'founded' => 1966, 'employees' => 900, 'hq' => 'New York'),
        );

        $normalized = ucwords(strtolower($company));
        return $firms[$normalized] ?? array('aum' => 50000000000, 'founded' => 2000, 'employees' => 500, 'hq' => 'New York');
    }

    private function get_firm_compensation_data($company)
    {
        // Return compensation by level for the firm
        return array(
            'analyst' => array('base' => 100000, 'bonus' => 50000, 'stub' => 25000),
            'associate' => array('base' => 175000, 'bonus' => 150000, 'stub' => 50000),
            'senior_associate' => array('base' => 225000, 'bonus' => 200000),
            'vp' => array('base' => 300000, 'bonus' => 300000),
            'principal' => array('base' => 400000, 'bonus' => 500000),
            'partner' => array('base' => 600000, 'bonus' => 1000000, 'carry' => 'Yes'),
        );
    }

    private function get_firm_culture_data($company)
    {
        return array(
            'hours_per_week' => '60-80',
            'travel' => '20-30%',
            'wfh_policy' => 'Hybrid (3 days in office)',
            'vacation_days' => '20-25',
            'ratings' => array(
                'work_life' => 3.2,
                'compensation' => 4.5,
                'culture' => 3.8,
                'career_growth' => 4.2,
            ),
        );
    }

    private function get_entry_routes($industry)
    {
        return array(
            array(
                'route' => 'Investment Banking Analyst',
                'percentage' => 70,
                'timeline' => '2 years post-IB',
                'description' => 'Most common path. Complete 2-year analyst program at bulge bracket or elite boutique.',
            ),
            array(
                'route' => 'Consulting',
                'percentage' => 15,
                'timeline' => '2-3 years post-consulting',
                'description' => 'MBB or Big 4 strategy consulting. Often requires MBA.',
            ),
            array(
                'route' => 'Direct from MBA',
                'percentage' => 10,
                'timeline' => 'Post-MBA',
                'description' => 'Top MBA programs (H/S/W) with prior finance experience.',
            ),
            array(
                'route' => 'Other (Corp Dev, Big 4)',
                'percentage' => 5,
                'timeline' => '3-5 years experience',
                'description' => 'Less common but possible with strong deal experience.',
            ),
        );
    }

    private function get_required_skills($industry)
    {
        return array(
            'technical' => array(
                array('skill' => 'Financial Modeling (Excel)', 'level' => 'Expert'),
                array('skill' => 'LBO Modeling', 'level' => 'Expert'),
                array('skill' => 'Valuation (DCF, Comps)', 'level' => 'Expert'),
                array('skill' => 'Accounting & Financial Statements', 'level' => 'Advanced'),
                array('skill' => 'PowerPoint / Presentations', 'level' => 'Advanced'),
            ),
            'soft' => array(
                array('skill' => 'Communication', 'level' => 'Expert'),
                array('skill' => 'Attention to Detail', 'level' => 'Expert'),
                array('skill' => 'Work Ethic', 'level' => 'Expert'),
                array('skill' => 'Problem Solving', 'level' => 'Advanced'),
                array('skill' => 'Teamwork', 'level' => 'Advanced'),
            ),
        );
    }

    private function get_industry_salary_ranges($industry)
    {
        return array(
            'analyst' => array('base' => 100000, 'bonus' => 50000, 'total' => 150000),
            'associate' => array('base' => 175000, 'bonus' => 150000, 'total' => 325000),
            'vp' => array('base' => 300000, 'bonus' => 300000, 'total' => 600000),
            'principal' => array('base' => 400000, 'bonus' => 600000, 'total' => 1000000),
            'partner' => array('base' => 600000, 'bonus' => 1500000, 'total' => 2100000),
        );
    }

    private function get_skill_data($skill)
    {
        $skills = array(
            'LBO Modeling' => array(
                'difficulty' => 'Advanced',
                'time_to_learn' => '40-60 hrs',
                'salary_impact' => 15,
                'demand_rank' => '#1',
                'prerequisites' => array('Excel proficiency', 'Accounting fundamentals', 'Basic valuation knowledge'),
                'mistakes' => array(
                    'Forgetting to link debt paydown to cash flow',
                    'Not checking for circular references',
                    'Incorrect treatment of transaction fees',
                    'Mixing up entry and exit multiples',
                ),
                'resources' => array(
                    array('name' => 'Wall Street Prep LBO Course', 'type' => 'Course'),
                    array('name' => 'Macabacus LBO Templates', 'type' => 'Template'),
                    array('name' => 'Investment Banking Interview Guide', 'type' => 'Book'),
                ),
            ),
            'DCF Modeling' => array(
                'difficulty' => 'Intermediate',
                'time_to_learn' => '20-30 hrs',
                'salary_impact' => 10,
                'demand_rank' => '#2',
                'prerequisites' => array('Excel basics', 'Time value of money', 'Financial statement analysis'),
                'mistakes' => array(
                    'Inconsistent assumptions between revenue and costs',
                    'Wrong WACC calculation',
                    'Not normalizing for non-recurring items',
                    'Forgetting terminal value',
                ),
                'resources' => array(
                    array('name' => 'Damodaran on Valuation', 'type' => 'Book'),
                    array('name' => 'Breaking Into Wall Street', 'type' => 'Course'),
                ),
            ),
        );

        $normalized = ucwords(strtolower($skill));
        return $skills[$normalized] ?? array(
            'difficulty' => 'Intermediate',
            'time_to_learn' => '20-40 hrs',
            'salary_impact' => 10,
            'demand_rank' => 'Top 10',
            'prerequisites' => array('Basic finance knowledge', 'Excel proficiency'),
            'mistakes' => array('Not practicing enough', 'Skipping fundamentals'),
            'resources' => array(),
        );
    }

    // =========================================================================
    // RENDERING HELPER METHODS
    // =========================================================================

    private function render_salary_by_level_chart($industry, $location)
    {
        $levels = array('analyst', 'associate', 'vp', 'director', 'md');
        $data = array();

        foreach ($levels as $level) {
            $salary = $this->data_fetcher->get_salary_estimate($level, $location);
            $data[$level] = $salary;
        }

        ob_start();
        ?>
        <div class="sffc-stacked-bar-chart">
            <?php foreach ($data as $level => $salary): ?>
            <div class="sffc-bar-row">
                <div class="sffc-bar-label"><?php echo esc_html(ucfirst($level)); ?></div>
                <div class="sffc-bar-container">
                    <div class="sffc-bar-segment sffc-bar-base" style="width: <?php echo ($salary['base_salary'] / 3000000) * 100; ?>%">
                        <span class="sffc-bar-value"><?php echo $this->format_currency($salary['base_salary']); ?></span>
                    </div>
                    <div class="sffc-bar-segment sffc-bar-bonus" style="width: <?php echo ($salary['bonus'] / 3000000) * 100; ?>%">
                        <span class="sffc-bar-value"><?php echo $this->format_currency($salary['bonus']); ?></span>
                    </div>
                </div>
                <div class="sffc-bar-total"><?php echo $this->format_currency($salary['total_comp']); ?></div>
            </div>
            <?php endforeach; ?>
            <div class="sffc-chart-legend">
                <span class="sffc-legend-item"><span class="sffc-legend-color sffc-bar-base"></span> Base</span>
                <span class="sffc-legend-item"><span class="sffc-legend-color sffc-bar-bonus"></span> Bonus</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_location_comparison_table($role)
    {
        $locations = array('NYC', 'London', 'Hong Kong', 'Singapore', 'Chicago', 'Boston');

        ob_start();
        ?>
        <div class="sffc-data-table-container">
            <table class="sffc-data-table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Base Salary</th>
                        <th>Bonus</th>
                        <th>Total Comp</th>
                        <th>vs. NYC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $nyc_salary = $this->data_fetcher->get_salary_estimate($role, 'NYC');
                    foreach ($locations as $location):
                        $salary = $this->data_fetcher->get_salary_estimate($role, $location);
                        $diff = round((($salary['total_comp'] - $nyc_salary['total_comp']) / $nyc_salary['total_comp']) * 100);
                        $diff_class = $diff >= 0 ? 'sffc-positive' : 'sffc-negative';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($location); ?></strong></td>
                        <td><?php echo $this->format_currency($salary['base_salary']); ?></td>
                        <td><?php echo $this->format_currency($salary['bonus']); ?></td>
                        <td><strong><?php echo $this->format_currency($salary['total_comp']); ?></strong></td>
                        <td class="<?php echo $diff_class; ?>"><?php echo ($diff >= 0 ? '+' : '') . $diff; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_market_context_cards($market_data)
    {
        ob_start();
        ?>
        <div class="sffc-market-cards">
            <?php if (isset($market_data['fed_funds_rate']['values'])): ?>
            <div class="sffc-market-card">
                <div class="sffc-market-card-value"><?php echo number_format(reset($market_data['fed_funds_rate']['values']), 2); ?>%</div>
                <div class="sffc-market-card-label">Fed Funds Rate</div>
                <div class="sffc-market-card-source">FRED</div>
            </div>
            <?php endif; ?>

            <?php if (isset($market_data['ten_year_treasury']['values'])): ?>
            <div class="sffc-market-card">
                <div class="sffc-market-card-value"><?php echo number_format(reset($market_data['ten_year_treasury']['values']), 2); ?>%</div>
                <div class="sffc-market-card-label">10Y Treasury</div>
                <div class="sffc-market-card-source">FRED</div>
            </div>
            <?php endif; ?>

            <?php if (isset($market_data['unemployment']['values'])): ?>
            <div class="sffc-market-card">
                <div class="sffc-market-card-value"><?php echo number_format(reset($market_data['unemployment']['values']), 1); ?>%</div>
                <div class="sffc-market-card-label">Unemployment</div>
                <div class="sffc-market-card-source">FRED</div>
            </div>
            <?php endif; ?>

            <?php if (isset($market_data['vix']['values'])): ?>
            <div class="sffc-market-card">
                <div class="sffc-market-card-value"><?php echo number_format(reset($market_data['vix']['values']), 1); ?></div>
                <div class="sffc-market-card-label">VIX Index</div>
                <div class="sffc-market-card-source">FRED</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_career_progression_timeline($progression_data)
    {
        ob_start();
        ?>
        <div class="sffc-progression-visual">
            <?php foreach ($progression_data['levels'] as $index => $level): ?>
            <div class="sffc-progression-level">
                <div class="sffc-level-marker"><?php echo $index + 1; ?></div>
                <div class="sffc-level-content">
                    <h4 class="sffc-level-title"><?php echo esc_html($level['title']); ?></h4>
                    <div class="sffc-level-meta">
                        <span class="sffc-level-years"><?php echo esc_html($level['years']); ?> years</span>
                        <span class="sffc-level-comp"><?php echo $this->format_currency($level['base'] + $level['bonus']); ?></span>
                    </div>
                </div>
                <?php if ($index < count($progression_data['levels']) - 1): ?>
                <div class="sffc-level-connector"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_bonus_factors_chart()
    {
        $factors = array(
            array('factor' => 'Fund Performance', 'weight' => 40),
            array('factor' => 'Individual Performance', 'weight' => 30),
            array('factor' => 'Deal Attribution', 'weight' => 15),
            array('factor' => 'Tenure', 'weight' => 10),
            array('factor' => 'Market Conditions', 'weight' => 5),
        );

        ob_start();
        ?>
        <div class="sffc-horizontal-bar-chart">
            <?php foreach ($factors as $factor): ?>
            <div class="sffc-hbar-row">
                <div class="sffc-hbar-label"><?php echo esc_html($factor['factor']); ?></div>
                <div class="sffc-hbar-container">
                    <div class="sffc-hbar-fill" style="width: <?php echo $factor['weight']; ?>%"></div>
                </div>
                <div class="sffc-hbar-value"><?php echo $factor['weight']; ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_salary_faqs($role, $salary_data, $location)
    {
        $faqs = array(
            array(
                'q' => "What is the average {$role} salary in {$location}?",
                'a' => "The average {$role} total compensation in {$location} is " . $this->format_currency($salary_data['total_comp']) . ", comprising " . $this->format_currency($salary_data['base_salary']) . " base salary and " . $this->format_currency($salary_data['bonus']) . " bonus.",
            ),
            array(
                'q' => "How much bonus can a {$role} expect?",
                'a' => "Bonuses typically range from {$salary_data['bonus_percent']}% to " . ($salary_data['bonus_percent'] + 20) . "% of base salary, depending on fund performance and individual contribution.",
            ),
            array(
                'q' => "When are bonuses paid?",
                'a' => "Year-end bonuses are typically paid in January or February. First-year employees may receive a prorated 'stub' bonus.",
            ),
            array(
                'q' => "How does compensation vary by firm?",
                'a' => "Top-tier firms (Blackstone, KKR, Apollo) pay 10-20% above market. Mid-market firms pay slightly below but may offer better work-life balance.",
            ),
            array(
                'q' => "What is carried interest?",
                'a' => "Carried interest (carry) is a share of investment profits, typically 20%. It's the primary wealth-building mechanism at senior levels and can significantly exceed base compensation.",
            ),
        );

        return $this->render_faq_list($faqs);
    }

    private function render_interview_faqs($company, $industry)
    {
        $faqs = array(
            array(
                'q' => "How many interview rounds are there?",
                'a' => "Typically 3-5 rounds including headhunter screen, first rounds, case study, and superday. The process takes 4-8 weeks.",
            ),
            array(
                'q' => "What should I wear to the interview?",
                'a' => "Business formal (suit and tie) for in-person interviews. For video interviews, at minimum wear a dress shirt/blouse.",
            ),
            array(
                'q' => "How long is the case study?",
                'a' => "Case studies typically last 2-4 hours and involve building an LBO model and writing an investment recommendation.",
            ),
            array(
                'q' => "What's the best way to prepare?",
                'a' => "Focus on paper LBOs, valuation concepts, and your deal experience. Practice articulating your 'story' and investment thesis.",
            ),
            array(
                'q' => "How quickly do firms make decisions?",
                'a' => "Top firms often make offers within 24-48 hours of superday. Exploding offers (24-72 hour deadlines) are common.",
            ),
        );

        return $this->render_faq_list($faqs);
    }

    private function render_firm_faqs($company, $comp_data)
    {
        $faqs = array(
            array(
                'q' => "What is the work-life balance like at {$company}?",
                'a' => "Expect 60-80 hour weeks on average, with peaks during deal closings. Most firms have improved WFH flexibility post-pandemic.",
            ),
            array(
                'q' => "How much do associates make at {$company}?",
                'a' => "Associates typically earn " . $this->format_currency($comp_data['associate']['base'] + $comp_data['associate']['bonus']) . " in total compensation (base + bonus).",
            ),
            array(
                'q' => "What is the promotion timeline?",
                'a' => "Associates typically spend 2-3 years at level before promotion. The path to Partner takes 10-15 years from entry.",
            ),
            array(
                'q' => "Does {$company} hire directly from undergrad?",
                'a' => "Some firms hire analysts directly from top undergrad programs, but most prefer candidates with 2+ years of IB experience.",
            ),
        );

        return $this->render_faq_list($faqs);
    }

    private function render_career_path_faqs($industry)
    {
        $faqs = array(
            array(
                'q' => "What's the best path into {$industry}?",
                'a' => "Investment banking analyst programs at bulge brackets or elite boutiques are the most common feeder (~70% of hires).",
            ),
            array(
                'q' => "Do I need an MBA?",
                'a' => "Not required for IB-to-PE lateral moves. Useful if coming from consulting or non-traditional backgrounds.",
            ),
            array(
                'q' => "How competitive is recruiting?",
                'a' => "Extremely competitive. Top PE firms have acceptance rates of 2-3%, similar to Ivy League admissions.",
            ),
            array(
                'q' => "When does recruiting happen?",
                'a' => "On-cycle recruiting for Associates happens 12-18 months before start date. Off-cycle opportunities arise year-round.",
            ),
        );

        return $this->render_faq_list($faqs);
    }

    private function render_skill_faqs($skill)
    {
        $faqs = array(
            array(
                'q' => "How long does it take to learn {$skill}?",
                'a' => "With dedicated practice, expect 40-60 hours to reach proficiency. Mastery requires months of real-world application.",
            ),
            array(
                'q' => "What resources are best for learning {$skill}?",
                'a' => "Structured courses (Wall Street Prep, BIWS) combined with practice templates and mock exercises are most effective.",
            ),
            array(
                'q' => "Is {$skill} tested in interviews?",
                'a' => "Yes, particularly paper LBOs and quick mental math. Technical interviews focus heavily on modeling concepts.",
            ),
        );

        return $this->render_faq_list($faqs);
    }

    private function render_faq_list($faqs)
    {
        ob_start();
        ?>
        <div class="sffc-faq-list">
            <?php foreach ($faqs as $faq): ?>
            <div class="sffc-faq-item">
                <h3 class="sffc-faq-question"><?php echo esc_html($faq['q']); ?></h3>
                <p class="sffc-faq-answer"><?php echo esc_html($faq['a']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_interview_timeline($interview_data)
    {
        ob_start();
        ?>
        <div class="sffc-interview-timeline">
            <?php foreach ($interview_data['stages'] as $index => $stage): ?>
            <div class="sffc-timeline-stage">
                <div class="sffc-stage-number"><?php echo $index + 1; ?></div>
                <div class="sffc-stage-content">
                    <h4><?php echo esc_html($stage['name']); ?></h4>
                    <div class="sffc-stage-meta">
                        <span class="sffc-stage-duration"><?php echo esc_html($stage['duration']); ?></span>
                    </div>
                    <p><?php echo esc_html($stage['focus']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_technical_checklist($topics)
    {
        ob_start();
        ?>
        <div class="sffc-checklist">
            <?php foreach ($topics as $topic): ?>
            <div class="sffc-checklist-item">
                <div class="sffc-checklist-importance sffc-importance-<?php echo strtolower($topic['importance']); ?>">
                    <?php echo esc_html($topic['importance']); ?>
                </div>
                <div class="sffc-checklist-content">
                    <h4><?php echo esc_html($topic['topic']); ?></h4>
                    <span class="sffc-checklist-time">Study time: <?php echo esc_html($topic['time']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_behavioral_questions($industry)
    {
        $questions = array(
            'Why do you want to work in ' . $industry . '?',
            'Walk me through your resume.',
            'Tell me about a deal you worked on.',
            'What makes a good investment?',
            'Describe a time you had to work under pressure.',
            'Why our firm specifically?',
            'Where do you see yourself in 5 years?',
        );

        ob_start();
        ?>
        <div class="sffc-question-list">
            <?php foreach ($questions as $index => $question): ?>
            <div class="sffc-question-item">
                <span class="sffc-question-number"><?php echo $index + 1; ?></span>
                <span class="sffc-question-text"><?php echo esc_html($question); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_offer_compensation_card($salary_data)
    {
        ob_start();
        ?>
        <div class="sffc-offer-card">
            <div class="sffc-offer-header">
                <h4>Expected Offer Package</h4>
            </div>
            <div class="sffc-offer-body">
                <div class="sffc-offer-row">
                    <span>Base Salary</span>
                    <strong><?php echo $this->format_currency($salary_data['base_salary']); ?></strong>
                </div>
                <div class="sffc-offer-row">
                    <span>Year-End Bonus</span>
                    <strong><?php echo $this->format_currency($salary_data['bonus']); ?></strong>
                </div>
                <div class="sffc-offer-row">
                    <span>Signing Bonus</span>
                    <strong><?php echo $this->format_currency($salary_data['signing_bonus'] ?? 25000); ?></strong>
                </div>
                <div class="sffc-offer-row sffc-offer-total">
                    <span>Year 1 Total</span>
                    <strong><?php echo $this->format_currency($salary_data['total_comp'] + ($salary_data['signing_bonus'] ?? 25000)); ?></strong>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_entry_routes($routes)
    {
        ob_start();
        ?>
        <div class="sffc-entry-routes">
            <?php foreach ($routes as $route): ?>
            <div class="sffc-route-card">
                <div class="sffc-route-header">
                    <h4><?php echo esc_html($route['route']); ?></h4>
                    <span class="sffc-route-percentage"><?php echo $route['percentage']; ?>% of hires</span>
                </div>
                <p class="sffc-route-description"><?php echo esc_html($route['description']); ?></p>
                <div class="sffc-route-timeline">Timeline: <?php echo esc_html($route['timeline']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_skills_checklist($skills_data)
    {
        ob_start();
        ?>
        <div class="sffc-skills-grid">
            <div class="sffc-skills-column">
                <h4>Technical Skills</h4>
                <?php foreach ($skills_data['technical'] as $skill): ?>
                <div class="sffc-skill-item">
                    <span class="sffc-skill-name"><?php echo esc_html($skill['skill']); ?></span>
                    <span class="sffc-skill-level sffc-level-<?php echo strtolower($skill['level']); ?>"><?php echo esc_html($skill['level']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sffc-skills-column">
                <h4>Soft Skills</h4>
                <?php foreach ($skills_data['soft'] as $skill): ?>
                <div class="sffc-skill-item">
                    <span class="sffc-skill-name"><?php echo esc_html($skill['skill']); ?></span>
                    <span class="sffc-skill-level sffc-level-<?php echo strtolower($skill['level']); ?>"><?php echo esc_html($skill['level']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_firm_comp_table($comp_data)
    {
        ob_start();
        ?>
        <div class="sffc-data-table-container">
            <table class="sffc-data-table">
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Base Salary</th>
                        <th>Bonus</th>
                        <th>Total Comp</th>
                        <th>Carry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comp_data as $level => $data): ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $level))); ?></strong></td>
                        <td><?php echo $this->format_currency($data['base']); ?></td>
                        <td><?php echo $this->format_currency($data['bonus']); ?></td>
                        <td><strong><?php echo $this->format_currency($data['base'] + $data['bonus']); ?></strong></td>
                        <td><?php echo isset($data['carry']) ? 'Yes' : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_culture_metrics($culture_data)
    {
        ob_start();
        ?>
        <div class="sffc-culture-grid">
            <div class="sffc-culture-stats">
                <div class="sffc-culture-stat">
                    <span class="sffc-stat-label">Hours/Week</span>
                    <span class="sffc-stat-value"><?php echo esc_html($culture_data['hours_per_week']); ?></span>
                </div>
                <div class="sffc-culture-stat">
                    <span class="sffc-stat-label">Travel</span>
                    <span class="sffc-stat-value"><?php echo esc_html($culture_data['travel']); ?></span>
                </div>
                <div class="sffc-culture-stat">
                    <span class="sffc-stat-label">Remote Policy</span>
                    <span class="sffc-stat-value"><?php echo esc_html($culture_data['wfh_policy']); ?></span>
                </div>
                <div class="sffc-culture-stat">
                    <span class="sffc-stat-label">Vacation</span>
                    <span class="sffc-stat-value"><?php echo esc_html($culture_data['vacation_days']); ?> days</span>
                </div>
            </div>
            <div class="sffc-culture-ratings">
                <?php foreach ($culture_data['ratings'] as $category => $rating): ?>
                <div class="sffc-rating-row">
                    <span class="sffc-rating-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></span>
                    <div class="sffc-rating-bar">
                        <div class="sffc-rating-fill" style="width: <?php echo ($rating / 5) * 100; ?>%"></div>
                    </div>
                    <span class="sffc-rating-value"><?php echo number_format($rating, 1); ?>/5</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_firm_interview_process($firm_data)
    {
        $process = array(
            array('stage' => 'Headhunter Contact', 'timing' => 'Ongoing', 'notes' => 'Build relationships early'),
            array('stage' => 'First Round', 'timing' => '1-2 weeks', 'notes' => 'Technical + fit screen'),
            array('stage' => 'Case Study', 'timing' => '1 week', 'notes' => 'Take-home LBO model'),
            array('stage' => 'Superday', 'timing' => '1 day', 'notes' => 'Full day of interviews'),
            array('stage' => 'Offer', 'timing' => '24-72 hours', 'notes' => 'Decision deadline'),
        );

        ob_start();
        ?>
        <div class="sffc-process-steps">
            <?php foreach ($process as $index => $step): ?>
            <div class="sffc-process-step">
                <div class="sffc-step-icon"><?php echo $index + 1; ?></div>
                <div class="sffc-step-details">
                    <h4><?php echo esc_html($step['stage']); ?></h4>
                    <p class="sffc-step-timing"><?php echo esc_html($step['timing']); ?></p>
                    <p class="sffc-step-notes"><?php echo esc_html($step['notes']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_prerequisites_list($prerequisites)
    {
        ob_start();
        ?>
        <ul class="sffc-prerequisites-list">
            <?php foreach ($prerequisites as $prereq): ?>
            <li><?php echo esc_html($prereq); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    private function render_common_mistakes($mistakes)
    {
        ob_start();
        ?>
        <div class="sffc-mistakes-list">
            <?php foreach ($mistakes as $index => $mistake): ?>
            <div class="sffc-mistake-item">
                <span class="sffc-mistake-icon">!</span>
                <span class="sffc-mistake-text"><?php echo esc_html($mistake); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_resources_list($resources)
    {
        ob_start();
        ?>
        <div class="sffc-resources-grid">
            <?php foreach ($resources as $resource): ?>
            <div class="sffc-resource-card">
                <span class="sffc-resource-type"><?php echo esc_html($resource['type']); ?></span>
                <h4><?php echo esc_html($resource['name']); ?></h4>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function generate_default_template($context)
    {
        return '<p>Template not found for this guide type.</p>';
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    private function format_currency($amount)
    {
        if ($amount >= 1000000) {
            return '$' . number_format($amount / 1000000, 1) . 'M';
        }
        return '$' . number_format($amount);
    }

    private function format_aum($amount)
    {
        if ($amount >= 1000000000000) {
            return '$' . number_format($amount / 1000000000000, 1) . 'T';
        }
        if ($amount >= 1000000000) {
            return '$' . number_format($amount / 1000000000, 0) . 'B';
        }
        return '$' . number_format($amount / 1000000, 0) . 'M';
    }

    /**
     * Get CSS styles for data-driven templates
     */
    public static function get_template_styles()
    {
        return '
        /* MENA Careers Guide Template Styles */
        :root {
            --senna-ink: #0d353e;
            --senna-mint: #97ffd5;
            --senna-cream: #fdf8f3;
            --senna-charcoal: #1a1a1a;
            --senna-neutral-50: #fafafa;
            --senna-neutral-100: #f5f5f5;
            --senna-neutral-200: #e5e5e5;
            --senna-neutral-400: #a3a3a3;
            --senna-neutral-600: #525252;
        }

        .sffc-guide {
            max-width: 900px;
            margin: 0 auto;
            font-family: "Inter", -apple-system, sans-serif;
            color: var(--senna-charcoal);
            line-height: 1.7;
        }

        /* Hero Stats */
        .sffc-guide-hero {
            background: linear-gradient(135deg, var(--senna-ink) 0%, #164a56 100%);
            padding: 3rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .sffc-hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.5rem;
        }

        .sffc-hero-stat {
            text-align: center;
            color: #fff;
        }

        .sffc-hero-stat-primary .sffc-stat-value {
            color: var(--senna-mint);
        }

        .sffc-hero-stat .sffc-stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .sffc-hero-stat .sffc-stat-label {
            display: block;
            font-size: 0.85rem;
            opacity: 0.85;
            margin-top: 0.25rem;
        }

        .sffc-hero-source {
            text-align: center;
            color: rgba(255,255,255,0.7);
            font-size: 0.8rem;
            margin-top: 1.5rem;
            margin-bottom: 0;
        }

        /* Sections */
        .sffc-guide-section {
            margin-bottom: 3rem;
        }

        .sffc-guide-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--senna-ink);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--senna-mint);
        }

        /* Data Tables */
        .sffc-data-table-container {
            overflow-x: auto;
            margin: 1.5rem 0;
        }

        .sffc-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .sffc-data-table th,
        .sffc-data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--senna-neutral-200);
        }

        .sffc-data-table th {
            background: var(--senna-neutral-100);
            font-weight: 600;
            color: var(--senna-ink);
        }

        .sffc-data-table .sffc-table-total {
            background: var(--senna-neutral-50);
            font-weight: 600;
        }

        .sffc-positive { color: #16a34a; }
        .sffc-negative { color: #dc2626; }

        /* Market Cards */
        .sffc-market-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .sffc-market-card {
            background: var(--senna-neutral-50);
            padding: 1.25rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--senna-neutral-200);
        }

        .sffc-market-card-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--senna-ink);
        }

        .sffc-market-card-label {
            font-size: 0.85rem;
            color: var(--senna-neutral-600);
            margin-top: 0.25rem;
        }

        .sffc-market-card-source {
            font-size: 0.75rem;
            color: var(--senna-neutral-400);
            margin-top: 0.5rem;
        }

        /* Insight Box */
        .sffc-insight-box {
            background: linear-gradient(135deg, rgba(151, 255, 213, 0.15) 0%, rgba(13, 53, 62, 0.05) 100%);
            border-left: 4px solid var(--senna-mint);
            padding: 1.25rem 1.5rem;
            border-radius: 0 8px 8px 0;
            margin: 1.5rem 0;
        }

        .sffc-insight-box h4 {
            color: var(--senna-ink);
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
        }

        .sffc-insight-box p {
            margin: 0;
            color: var(--senna-neutral-600);
        }

        /* Narrative Slots (for Claude) */
        .sffc-narrative-slot {
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--senna-cream);
            border-radius: 8px;
        }

        .sffc-slot-placeholder {
            color: var(--senna-neutral-400);
            font-style: italic;
        }

        /* Progression Visual */
        .sffc-progression-visual {
            position: relative;
            padding-left: 3rem;
        }

        .sffc-progression-level {
            position: relative;
            padding-bottom: 2rem;
            padding-left: 2rem;
            border-left: 2px solid var(--senna-neutral-200);
        }

        .sffc-progression-level:last-child {
            border-left-color: transparent;
        }

        .sffc-level-marker {
            position: absolute;
            left: -1.1rem;
            top: 0;
            width: 2rem;
            height: 2rem;
            background: var(--senna-ink);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .sffc-level-title {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            color: var(--senna-charcoal);
        }

        .sffc-level-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
        }

        .sffc-level-years { color: var(--senna-neutral-600); }
        .sffc-level-comp { color: var(--senna-ink); font-weight: 600; }

        /* FAQ Section */
        .sffc-faq-list { margin: 0; }

        .sffc-faq-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--senna-neutral-200);
        }

        .sffc-faq-question {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--senna-ink);
            margin: 0 0 0.75rem 0;
        }

        .sffc-faq-answer {
            margin: 0;
            color: var(--senna-neutral-600);
        }

        /* Horizontal Bar Chart */
        .sffc-horizontal-bar-chart { margin: 1.5rem 0; }

        .sffc-hbar-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .sffc-hbar-label {
            width: 150px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .sffc-hbar-container {
            flex: 1;
            height: 24px;
            background: var(--senna-neutral-100);
            border-radius: 4px;
            overflow: hidden;
        }

        .sffc-hbar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--senna-ink) 0%, #164a56 100%);
            border-radius: 4px;
        }

        .sffc-hbar-value {
            width: 50px;
            text-align: right;
            font-weight: 600;
            color: var(--senna-ink);
        }

        /* Stacked Bar Chart */
        .sffc-stacked-bar-chart { margin: 1.5rem 0; }

        .sffc-bar-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .sffc-bar-label {
            width: 100px;
            font-weight: 500;
        }

        .sffc-bar-container {
            flex: 1;
            display: flex;
            height: 32px;
            background: var(--senna-neutral-100);
            border-radius: 4px;
            overflow: hidden;
        }

        .sffc-bar-segment {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .sffc-bar-base { background: var(--senna-ink); }
        .sffc-bar-bonus { background: var(--senna-mint); color: var(--senna-ink); }

        .sffc-bar-total {
            width: 80px;
            text-align: right;
            font-weight: 600;
        }

        .sffc-chart-legend {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .sffc-legend-color {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* Footer */
        .sffc-guide-footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--senna-neutral-200);
        }

        .sffc-data-sources h4 {
            font-size: 0.9rem;
            color: var(--senna-neutral-600);
            margin-bottom: 0.75rem;
        }

        .sffc-data-sources ul {
            margin: 0;
            padding-left: 1.25rem;
            color: var(--senna-neutral-600);
            font-size: 0.85rem;
        }

        .sffc-disclaimer {
            font-size: 0.8rem;
            color: var(--senna-neutral-400);
            margin-top: 1rem;
            font-style: italic;
        }

        /* Skills Grid */
        .sffc-skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .sffc-skills-column h4 {
            font-size: 1rem;
            color: var(--senna-ink);
            margin-bottom: 1rem;
        }

        .sffc-skill-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--senna-neutral-100);
        }

        .sffc-skill-level {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
        }

        .sffc-level-expert { background: var(--senna-mint); color: var(--senna-ink); }
        .sffc-level-advanced { background: var(--senna-neutral-200); color: var(--senna-charcoal); }

        /* Entry Routes */
        .sffc-entry-routes {
            display: grid;
            gap: 1rem;
        }

        .sffc-route-card {
            background: var(--senna-neutral-50);
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--senna-neutral-200);
        }

        .sffc-route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .sffc-route-header h4 {
            margin: 0;
            color: var(--senna-ink);
        }

        .sffc-route-percentage {
            background: var(--senna-mint);
            color: var(--senna-ink);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .sffc-route-description {
            margin: 0 0 0.5rem 0;
            color: var(--senna-neutral-600);
        }

        .sffc-route-timeline {
            font-size: 0.85rem;
            color: var(--senna-neutral-400);
        }

        /* Checklist */
        .sffc-checklist { margin: 1rem 0; }

        .sffc-checklist-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: var(--senna-neutral-50);
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .sffc-checklist-importance {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            height: fit-content;
        }

        .sffc-importance-critical { background: #fef2f2; color: #dc2626; }
        .sffc-importance-high { background: #fef9c3; color: #ca8a04; }
        .sffc-importance-medium { background: var(--senna-neutral-100); color: var(--senna-neutral-600); }

        .sffc-checklist-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
        }

        .sffc-checklist-time {
            font-size: 0.85rem;
            color: var(--senna-neutral-400);
        }

        /* Culture Metrics */
        .sffc-culture-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .sffc-culture-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .sffc-culture-stat {
            background: var(--senna-neutral-50);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .sffc-rating-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .sffc-rating-label {
            width: 120px;
            font-size: 0.9rem;
        }

        .sffc-rating-bar {
            flex: 1;
            height: 8px;
            background: var(--senna-neutral-200);
            border-radius: 4px;
            overflow: hidden;
        }

        .sffc-rating-fill {
            height: 100%;
            background: var(--senna-mint);
        }

        .sffc-rating-value {
            width: 40px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Offer Card */
        .sffc-offer-card {
            background: var(--senna-neutral-50);
            border-radius: 12px;
            overflow: hidden;
            max-width: 400px;
        }

        .sffc-offer-header {
            background: var(--senna-ink);
            color: #fff;
            padding: 1rem 1.5rem;
        }

        .sffc-offer-header h4 { margin: 0; }

        .sffc-offer-body { padding: 1.5rem; }

        .sffc-offer-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--senna-neutral-200);
        }

        .sffc-offer-total {
            border-bottom: none;
            padding-top: 1rem;
            font-size: 1.1rem;
        }

        .sffc-offer-total strong {
            color: var(--senna-ink);
        }

        /* Interview Timeline */
        .sffc-interview-timeline { margin: 1.5rem 0; }

        .sffc-timeline-stage {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .sffc-stage-number {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--senna-ink);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .sffc-stage-content h4 {
            margin: 0 0 0.25rem 0;
            color: var(--senna-charcoal);
        }

        .sffc-stage-meta {
            font-size: 0.85rem;
            color: var(--senna-ink);
            margin-bottom: 0.25rem;
        }

        .sffc-stage-content p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--senna-neutral-600);
        }

        /* Question List */
        .sffc-question-list { margin: 1rem 0; }

        .sffc-question-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: var(--senna-neutral-50);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .sffc-question-number {
            width: 1.75rem;
            height: 1.75rem;
            background: var(--senna-ink);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        /* Mistakes List */
        .sffc-mistakes-list { margin: 1rem 0; }

        .sffc-mistake-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: #fef2f2;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border-left: 4px solid #dc2626;
        }

        .sffc-mistake-icon {
            width: 1.5rem;
            height: 1.5rem;
            background: #dc2626;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* Resources Grid */
        .sffc-resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .sffc-resource-card {
            background: var(--senna-neutral-50);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--senna-neutral-200);
        }

        .sffc-resource-type {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--senna-neutral-400);
            font-weight: 600;
        }

        .sffc-resource-card h4 {
            margin: 0.5rem 0 0 0;
            font-size: 0.95rem;
            color: var(--senna-charcoal);
        }

        /* Process Steps */
        .sffc-process-steps {
            display: grid;
            gap: 1rem;
        }

        .sffc-process-step {
            display: flex;
            gap: 1.5rem;
            padding: 1.25rem;
            background: var(--senna-neutral-50);
            border-radius: 8px;
        }

        .sffc-step-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--senna-ink);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .sffc-step-details h4 {
            margin: 0 0 0.25rem 0;
        }

        .sffc-step-timing {
            font-size: 0.9rem;
            color: var(--senna-ink);
            font-weight: 500;
            margin: 0 0 0.25rem 0;
        }

        .sffc-step-notes {
            font-size: 0.85rem;
            color: var(--senna-neutral-600);
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sffc-guide-hero { padding: 2rem 1.5rem; }
            .sffc-hero-stat .sffc-stat-value { font-size: 1.5rem; }
            .sffc-bar-row, .sffc-hbar-row { flex-direction: column; align-items: flex-start; }
            .sffc-bar-label, .sffc-hbar-label { width: 100%; margin-bottom: 0.5rem; }
        }
        ';
    }
}

// Initialize
SFFC_Guide_Template_Library::get_instance();
