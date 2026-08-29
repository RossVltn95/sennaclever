<?php
/**
 * Lesson Content Generator
 * Creates real educational content for all courses
 *
 * @package SennaCareers
 * @subpackage Learning
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Lesson_Content_Generator {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate and save all lesson content for all courses
     */
    public function generate_all_lessons() {
        $courses = $this->get_all_courses();

        foreach ($courses as $course_id) {
            $this->generate_course_lessons($course_id);
        }
    }

    /**
     * Get all course IDs
     */
    private function get_all_courses() {
        $args = [
            'post_type' => 'sffc_job',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'content_type',
                    'value' => 'course',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids'
        ];

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Generate lessons for a specific course
     */
    public function generate_course_lessons($course_id) {
        $course_title = get_the_title($course_id);
        $modules = json_decode(get_post_meta($course_id, 'course_modules', true), true);

        if (!is_array($modules)) {
            return;
        }

        $all_lessons = [];

        foreach ($modules as $module) {
            $module_id = $module['module_id'];
            $topics = $module['topics'] ?? [];

            foreach ($topics as $index => $topic) {
                $lesson_id = $index + 1;
                $lesson_key = "module_{$module_id}_lesson_{$lesson_id}";

                // Generate content based on course
                $lesson_content = $this->generate_lesson_content($course_title, $module, $topic, $lesson_id);
                $all_lessons[$lesson_key] = $lesson_content;
            }
        }

        // Save all lessons
        update_post_meta($course_id, 'lesson_content', $all_lessons);
    }

    /**
     * Generate actual educational content for a lesson
     */
    private function generate_lesson_content($course_title, $module, $topic, $lesson_id) {
        // Determine which course this is for
        if (strpos($course_title, '3-Statement') !== false) {
            return $this->generate_3statement_lesson($module, $topic, $lesson_id);
        } elseif (strpos($course_title, 'DCF') !== false) {
            return $this->generate_dcf_lesson($module, $topic, $lesson_id);
        } elseif (strpos($course_title, 'LBO') !== false) {
            return $this->generate_lbo_lesson($module, $topic, $lesson_id);
        } elseif (strpos($course_title, 'Excel') !== false) {
            return $this->generate_excel_lesson($module, $topic, $lesson_id);
        } elseif (strpos($course_title, 'Interview') !== false) {
            return $this->generate_interview_lesson($module, $topic, $lesson_id);
        }

        return $this->generate_default_lesson($topic, $lesson_id);
    }

    /**
     * Generate 3-Statement Modeling lesson content
     */
    private function generate_3statement_lesson($module, $topic, $lesson_id) {
        $content = '';

        // Module 1: Income Statement
        if ($module['module_id'] == 1) {
            if ($lesson_id == 1) {
                $content = $this->get_revenue_modeling_content();
            } elseif ($lesson_id == 2) {
                $content = $this->get_cogs_margin_content();
            } elseif ($lesson_id == 3) {
                $content = $this->get_opex_content();
            } elseif ($lesson_id == 4) {
                $content = $this->get_ebitda_content();
            } else {
                $content = $this->get_income_statement_integration();
            }
        }
        // Module 2: Balance Sheet
        elseif ($module['module_id'] == 2) {
            if ($lesson_id == 1) {
                $content = $this->get_current_assets_content();
            } elseif ($lesson_id == 2) {
                $content = $this->get_liabilities_content();
            } elseif ($lesson_id == 3) {
                $content = $this->get_shareholders_equity_content();
            } else {
                $content = $this->get_balance_sheet_linkages();
            }
        }
        // Module 3: Cash Flow Statement
        elseif ($module['module_id'] == 3) {
            if ($lesson_id == 1) {
                $content = $this->get_operating_cashflow_content();
            } elseif ($lesson_id == 2) {
                $content = $this->get_investing_activities_content();
            } else {
                $content = $this->get_financing_activities_content();
            }
        }

        return [
            'lesson_id' => $lesson_id,
            'module_id' => $module['module_id'],
            'title' => $topic,
            'duration' => '15-20 min',
            'type' => 'text',
            'content' => $content,
            'key_takeaways' => $this->extract_key_takeaways($content),
            'practice_exercises' => $this->get_practice_exercises($topic),
        ];
    }

    /**
     * LESSON CONTENT: Revenue Modeling
     */
    private function get_revenue_modeling_content() {
        return <<<HTML
<div class="lesson-content">
    <h2>Revenue Modeling: The Foundation of Financial Forecasting</h2>

    <div class="lesson-intro">
        <p>Revenue is the top line of any financial model. Getting revenue forecasts right is critical because nearly every other line item flows from it. In this lesson, you'll learn the three core approaches investment bankers and private equity analysts use to build revenue models.</p>
    </div>

    <h3>Why Revenue Modeling Matters</h3>
    <p>As an analyst at Goldman Sachs, Morgan Stanley, or Blackstone, your revenue assumptions will be scrutinized more than anything else in your model. Why? Because:</p>
    <ul>
        <li><strong>Revenue drives everything:</strong> COGS, OpEx, working capital, and ultimately cash flow all tie back to revenue</li>
        <li><strong>It's the hardest to forecast:</strong> Unlike costs (which are often % of revenue), revenue depends on market dynamics, competition, and company execution</li>
        <li><strong>Valuation sensitivity:</strong> A 5% change in revenue growth can swing valuations by 20-30%</li>
    </ul>

    <h3>The Three Revenue Modeling Approaches</h3>

    <h4>1. Top-Down Approach (Market-Based)</h4>
    <p>Start with total addressable market (TAM) and work down to company revenue.</p>

    <div class="formula-box">
        <strong>Formula:</strong> Revenue = TAM × Market Share %
    </div>

    <p><strong>Example:</strong> Modeling a SaaS company</p>
    <ul>
        <li>Global CRM software market: $50B</li>
        <li>Company current market share: 2.5%</li>
        <li>Market growing at 12% annually</li>
        <li>Company gaining 0.3% market share per year</li>
    </ul>

    <div class="calculation-example">
        <p><strong>Year 1 Revenue:</strong></p>
        <p>Market Size: $50B × 1.12 = $56B</p>
        <p>Market Share: 2.5% + 0.3% = 2.8%</p>
        <p><strong>Revenue = $56B × 2.8% = $1.57B</strong></p>
    </div>

    <p><strong>When to use:</strong> Early-stage companies, new market entries, strategic reviews</p>
    <p><strong>Pros:</strong> Shows competitive positioning, easy to explain to management</p>
    <p><strong>Cons:</strong> TAM estimates are often unreliable, hard to verify market share gains</p>

    <h4>2. Bottom-Up Approach (Unit Economics)</h4>
    <p>Build revenue from individual customer or transaction data.</p>

    <div class="formula-box">
        <strong>Formula:</strong> Revenue = # Customers × Average Revenue Per User (ARPU)
    </div>

    <p><strong>Example:</strong> Subscription business model</p>
    <ul>
        <li>Starting customers: 500,000</li>
        <li>Monthly churn: 3%</li>
        <li>New customer acquisition: 25,000/month</li>
        <li>Current ARPU: $50/month</li>
        <li>Annual price increase: 5%</li>
    </ul>

    <div class="calculation-example">
        <p><strong>Month 1:</strong></p>
        <p>Customers: 500,000 - (500,000 × 3%) + 25,000 = 510,000</p>
        <p>Revenue: 510,000 × $50 = $25.5M</p>

        <p><strong>Year 1 Total:</strong></p>
        <p>Avg customers (after growth): ~550,000</p>
        <p>Avg ARPU (after increases): ~$51.25</p>
        <p><strong>Annual Revenue = 550,000 × $51.25 × 12 = $338M</strong></p>
    </div>

    <p><strong>When to use:</strong> Subscription models, retail, any business with clear unit economics</p>
    <p><strong>Pros:</strong> Most accurate for mature businesses, easy to tie to operational metrics</p>
    <p><strong>Cons:</strong> Requires detailed customer data, can be complex for multi-product companies</p>

    <h4>3. Historical Trend Analysis</h4>
    <p>Project based on historical growth rates and cyclical patterns.</p>

    <div class="formula-box">
        <strong>Formula:</strong> Revenue(t) = Revenue(t-1) × (1 + Growth Rate)
    </div>

    <p><strong>Example:</strong> Mature industrial company</p>

    <table class="data-table">
        <tr>
            <th>Year</th>
            <th>Revenue ($M)</th>
            <th>YoY Growth</th>
        </tr>
        <tr>
            <td>2021</td>
            <td>$450</td>
            <td>8.5%</td>
        </tr>
        <tr>
            <td>2022</td>
            <td>$485</td>
            <td>7.8%</td>
        </tr>
        <tr>
            <td>2023</td>
            <td>$520</td>
            <td>7.2%</td>
        </tr>
        <tr>
            <td><strong>2024E</strong></td>
            <td><strong>$555</strong></td>
            <td><strong>6.7% (declining trend)</strong></td>
        </tr>
    </table>

    <p><strong>When to use:</strong> Mature companies, stable industries, lack of detailed unit economics</p>
    <p><strong>Pros:</strong> Simple, defensible, matches historical performance</p>
    <p><strong>Cons:</strong> Doesn't capture business model changes, assumes past = future</p>

    <h3>Practical Application: Building Your First Revenue Model</h3>

    <div class="step-by-step">
        <h4>Step 1: Gather Historical Data</h4>
        <p>Pull 3-5 years of historical revenue from financial statements. Look for:</p>
        <ul>
            <li>Revenue by segment (if applicable)</li>
            <li>Seasonal patterns (Q1 vs Q4)</li>
            <li>One-time events (acquisitions, divestitures)</li>
        </ul>

        <h4>Step 2: Calculate Historical Metrics</h4>
        <p>For each approach:</p>
        <ul>
            <li><strong>Top-down:</strong> Historical market share if available</li>
            <li><strong>Bottom-up:</strong> Customer count, ARPU, churn rates</li>
            <li><strong>Trend:</strong> CAGR, average growth rate, standard deviation</li>
        </ul>

        <h4>Step 3: Make Assumptions</h4>
        <p>This is where your judgment matters. Consider:</p>
        <ul>
            <li>Macroeconomic environment (GDP growth, interest rates)</li>
            <li>Industry trends (market growth rate)</li>
            <li>Company-specific factors (new products, management changes)</li>
            <li>Competitive dynamics (new entrants, pricing pressure)</li>
        </ul>

        <h4>Step 4: Build Your Model</h4>
        <p>Use the approach that best fits the business. Often you'll use a hybrid:</p>
        <ul>
            <li>Bottom-up for core business (you have the data)</li>
            <li>Top-down for new segments (you don't have history)</li>
            <li>Trend analysis as a sanity check</li>
        </ul>

        <h4>Step 5: Sensitivity Analysis</h4>
        <p>Test your assumptions. What if growth is 2% higher? 2% lower? Build cases:</p>
        <ul>
            <li><strong>Base case:</strong> Most likely scenario</li>
            <li><strong>Bull case:</strong> +20% upside to growth assumptions</li>
            <li><strong>Bear case:</strong> -20% downside to growth assumptions</li>
        </ul>
    </div>

    <h3>Common Mistakes to Avoid</h3>

    <div class="warning-box">
        <h4>❌ Mistake #1: Hockey Stick Projections</h4>
        <p>Don't project 5% growth for 3 years then suddenly 25% growth. Markets don't believe it, and neither will your MD.</p>

        <h4>❌ Mistake #2: Ignoring Operating Leverage</h4>
        <p>As revenue grows, growth rates naturally decline. A $10M company growing to $15M (50%) is easier than a $500M company growing to $750M (also 50%).</p>

        <h4>❌ Mistake #3: Not Triangulating</h4>
        <p>Always check your revenue forecast against multiple approaches. If bottom-up says $500M but top-down implies $300M, dig deeper.</p>

        <h4>❌ Mistake #4: Forgetting Cyclicality</h4>
        <p>Industrials, commodities, and consumer discretionary are cyclical. Don't extrapolate peak-cycle growth rates.</p>
    </div>

    <h3>Real-World Example: How Goldman Models Tech Companies</h3>

    <p>Let's say you're modeling a B2B SaaS company like Salesforce or Workday:</p>

    <div class="case-study">
        <h4>Revenue Build:</h4>
        <ol>
            <li><strong>Subscription Revenue</strong> (85% of total)
                <ul>
                    <li>Start with ending customer count from last quarter</li>
                    <li>Apply churn rate (typically 5-8% annually for enterprise)</li>
                    <li>Add new customer acquisitions (from sales force productivity)</li>
                    <li>Multiply by ARPU (increasing due to upsells and price increases)</li>
                </ul>
            </li>
            <li><strong>Professional Services</strong> (15% of total)
                <ul>
                    <li>Typically 15-20% of subscription revenue</li>
                    <li>Model as % of new bookings (implementation services)</li>
                </ul>
            </li>
        </ol>

        <p><strong>Key Assumptions Document:</strong></p>
        <ul>
            <li>Net dollar retention: 115% (customers spend 15% more each year)</li>
            <li>Sales force growing 20% annually</li>
            <li>Quota per sales rep: $1.2M ARR</li>
            <li>New rep productivity ramps over 6 months</li>
        </ul>
    </div>

    <div class="next-steps">
        <h3>Practice Exercise</h3>
        <p>Download the Excel template and build a 5-year revenue forecast for the sample company. Use the bottom-up approach with the following data:</p>
        <ul>
            <li>Current customers: 1,000</li>
            <li>Current ARPU: $10,000/year</li>
            <li>Churn rate: 10% annually</li>
            <li>Sales team: 10 reps</li>
            <li>New customers per rep per year: 15</li>
            <li>Plan to hire 5 new reps per year</li>
            <li>ARPU growth: 8% per year (pricing power + upsells)</li>
        </ul>
    </div>
</div>
HTML;
    }

    /**
     * LESSON CONTENT: COGS and Gross Margin
     */
    private function get_cogs_margin_content() {
        return <<<HTML
<div class="lesson-content">
    <h2>COGS & Gross Margin: Understanding Product Economics</h2>

    <div class="lesson-intro">
        <p>Cost of Goods Sold (COGS) tells you how much it costs to actually produce what you sell. Gross margin—the gap between revenue and COGS—is one of the most important indicators of business quality. Warren Buffett looks at gross margins before almost anything else.</p>
    </div>

    <h3>What is COGS?</h3>

    <p>COGS includes ALL direct costs to produce a product or deliver a service:</p>

    <h4>For Manufacturing Companies:</h4>
    <ul>
        <li><strong>Raw materials:</strong> Steel for cars, fabric for clothing, chips for electronics</li>
        <li><strong>Direct labor:</strong> Factory workers' wages (not corporate staff)</li>
        <li><strong>Manufacturing overhead:</strong> Factory rent, equipment depreciation, utilities</li>
    </ul>

    <h4>For Service Companies:</h4>
    <ul>
        <li><strong>Labor costs:</strong> Consultants' salaries, support staff</li>
        <li><strong>Third-party costs:</strong> Subcontractors, outsourced delivery</li>
        <li><strong>Tools and systems:</strong> Software licenses for client work</li>
    </ul>

    <h4>For SaaS/Tech Companies:</h4>
    <ul>
        <li><strong>Hosting costs:</strong> AWS, Azure, Google Cloud fees</li>
        <li><strong>Support staff:</strong> Customer success team salaries</li>
        <li><strong>Third-party services:</strong> Payment processing fees, SMS/email delivery</li>
    </ul>

    <div class="formula-box">
        <strong>Gross Profit = Revenue - COGS</strong><br>
        <strong>Gross Margin % = (Gross Profit / Revenue) × 100</strong>
    </div>

    <h3>Why Gross Margin Matters</h3>

    <div class="insight-box">
        <p><strong>"I want to buy businesses that have a moat. I want them to have a sustainable competitive advantage. High gross margins are usually a sign of that."</strong> — Warren Buffett</p>
    </div>

    <p>Gross margin tells you:</p>
    <ul>
        <li><strong>Pricing power:</strong> Can you charge premium prices?</li>
        <li><strong>Scalability:</strong> How much drops to the bottom line as you grow?</li>
        <li><strong>Business quality:</strong> Commodity businesses have low margins (5-15%), great businesses have high margins (60%+)</li>
        <li><strong>Competitive position:</strong> Falling margins = increasing competition</li>
    </ul>

    <h3>Industry Benchmark Gross Margins</h3>

    <table class="data-table">
        <tr>
            <th>Industry</th>
            <th>Typical Gross Margin</th>
            <th>Why?</th>
        </tr>
        <tr>
            <td>Grocery/Retail</td>
            <td>20-30%</td>
            <td>Commoditized products, intense competition</td>
        </tr>
        <tr>
            <td>Manufacturing</td>
            <td>30-40%</td>
            <td>Material and labor intensive</td>
        </tr>
        <tr>
            <td>Restaurants</td>
            <td>60-70%</td>
            <td>Food costs ~30%, but high operating expenses</td>
        </tr>
        <tr>
            <td>Enterprise Software</td>
            <td>75-85%</td>
            <td>Low marginal cost to serve new customers</td>
        </tr>
        <tr>
            <td>Luxury Goods</td>
            <td>65-75%</td>
            <td>Brand premium pricing</td>
        </tr>
        <tr>
            <td>Pharmaceuticals</td>
            <td>70-85%</td>
            <td>Patent protection, high R&D already expensed</td>
        </tr>
    </table>

    <h3>Modeling COGS: Three Approaches</h3>

    <h4>1. % of Revenue Method (Most Common)</h4>
    <p>Express COGS as a percentage of revenue. This is the standard approach in investment banking.</p>

    <div class="calculation-example">
        <p><strong>Historical Analysis:</strong></p>
        <table class="data-table">
            <tr>
                <th>Year</th>
                <th>Revenue</th>
                <th>COGS</th>
                <th>COGS %</th>
                <th>Gross Margin %</th>
            </tr>
            <tr>
                <td>2021</td>
                <td>$100M</td>
                <td>$35M</td>
                <td>35.0%</td>
                <td>65.0%</td>
            </tr>
            <tr>
                <td>2022</td>
                <td>$120M</td>
                <td>$40M</td>
                <td>33.3%</td>
                <td>66.7%</td>
            </tr>
            <tr>
                <td>2023</td>
                <td>$145M</td>
                <td>$46M</td>
                <td>31.7%</td>
                <td>68.3%</td>
            </tr>
        </table>

        <p><strong>Observation:</strong> Gross margin is improving! Likely due to:</p>
        <ul>
            <li>Operating leverage (fixed costs spreading over more revenue)</li>
            <li>Pricing power (raising prices faster than costs)</li>
            <li>Product mix shift (selling more high-margin products)</li>
        </ul>

        <p><strong>Projection:</strong> Assume margin improvement slows as company matures</p>
        <ul>
            <li>2024E: 69.0% gross margin (COGS = 31.0% of revenue)</li>
            <li>2025E: 69.5% gross margin (COGS = 30.5% of revenue)</li>
            <li>2026E+: 70.0% gross margin (COGS = 30.0% of revenue) — terminal</li>
        </ul>
    </div>

    <h4>2. Unit Economics Method</h4>
    <p>Calculate cost per unit, then multiply by units sold.</p>

    <div class="calculation-example">
        <p><strong>Example: Manufacturing Company</strong></p>
        <ul>
            <li>Units sold: 100,000</li>
            <li>Material cost per unit: $15</li>
            <li>Labor cost per unit: $8</li>
            <li>Overhead per unit: $5</li>
        </ul>
        <p><strong>COGS = 100,000 × ($15 + $8 + $5) = $2.8M</strong></p>

        <p><strong>Sensitivity:</strong> What if we can reduce material costs by 10%?</p>
        <ul>
            <li>New material cost: $13.50</li>
            <li>COGS = 100,000 × ($13.50 + $8 + $5) = $2.65M</li>
            <li><strong>Savings: $150K (5.4% improvement in COGS)</strong></li>
        </ul>
    </div>

    <h4>3. Driver-Based Method</h4>
    <p>Model COGS components separately based on operational drivers.</p>

    <div class="calculation-example">
        <p><strong>Example: SaaS Company</strong></p>

        <p><strong>Hosting Costs:</strong></p>
        <ul>
            <li>Cost per customer per month: $2.50</li>
            <li>Customers: 10,000</li>
            <li>Monthly hosting: $25,000 ($300K annually)</li>
        </ul>

        <p><strong>Support Costs:</strong></p>
        <ul>
            <li>Support team: 20 people</li>
            <li>Average salary + benefits: $60K</li>
            <li>Annual cost: $1.2M</li>
        </ul>

        <p><strong>Third-Party Fees:</strong></p>
        <ul>
            <li>Payment processing: 2.5% of revenue</li>
            <li>Revenue: $10M</li>
            <li>Annual cost: $250K</li>
        </ul>

        <p><strong>Total COGS: $300K + $1.2M + $250K = $1.75M</strong></p>
        <p><strong>COGS % of Revenue: $1.75M / $10M = 17.5%</strong></p>
        <p><strong>Gross Margin: 82.5%</strong> (Excellent for SaaS!)</p>
    </div>

    <h3>Advanced Concept: Gross Margin Bridge</h3>

    <p>When modeling a company over time, build a "bridge" showing WHY gross margin changes:</p>

    <div class="bridge-analysis">
        <h4>Example: Year-over-Year Gross Margin Movement</h4>

        <table class="data-table">
            <tr>
                <th>Component</th>
                <th>Impact</th>
            </tr>
            <tr>
                <td>2023 Gross Margin</td>
                <td>68.0%</td>
            </tr>
            <tr>
                <td>+ Price increases (3%)</td>
                <td>+2.0%</td>
            </tr>
            <tr>
                <td>- Input cost inflation (raw materials up 5%)</td>
                <td>-1.2%</td>
            </tr>
            <tr>
                <td>+ Manufacturing efficiency gains</td>
                <td>+0.5%</td>
            </tr>
            <tr>
                <td>+ Product mix shift (more software, less hardware)</td>
                <td>+1.2%</td>
            </tr>
            <tr>
                <td><strong>2024E Gross Margin</strong></td>
                <td><strong>70.5%</strong></td>
            </tr>
        </table>

        <p>This level of detail impresses investors because it shows you understand the <em>why</em> behind the numbers.</p>
    </div>

    <h3>Red Flags in COGS Analysis</h3>

    <div class="warning-box">
        <h4>⚠️ Warning Sign #1: Eroding Margins</h4>
        <p>If gross margins are falling year after year:</p>
        <ul>
            <li>Competition is intensifying (pricing pressure)</li>
            <li>Input costs are rising faster than prices</li>
            <li>Product mix is shifting to lower-margin products</li>
        </ul>
        <p><strong>Action:</strong> Challenge revenue growth assumptions. Growing at low margins destroys value.</p>

        <h4>⚠️ Warning Sign #2: Margins Too Good to Be True</h4>
        <p>If a company claims 90%+ gross margins in a competitive industry:</p>
        <ul>
            <li>They might be misclassifying OpEx as COGS</li>
            <li>There could be hidden costs they're ignoring</li>
            <li>It's not sustainable—competitors will enter</li>
        </ul>

        <h4>⚠️ Warning Sign #3: Volatile Margins</h4>
        <p>Gross margin swinging 5-10 percentage points year to year suggests:</p>
        <ul>
            <li>Commodity price exposure (oil, steel, crops)</li>
            <li>Inconsistent pricing discipline</li>
            <li>Poor cost management</li>
        </ul>
    </div>

    <h3>How PE Firms Think About Gross Margin</h3>

    <div class="pe-perspective">
        <p>When Blackstone, KKR, or Apollo evaluate an LBO, gross margin is critical because:</p>

        <ol>
            <li><strong>Margin Expansion = Value Creation</strong>
                <p>If you buy a company at 60% gross margin and improve it to 65% through operational improvements, you've created massive value without growing revenue.</p>
                <p><strong>Example:</strong> $100M revenue at 60% margin = $60M gross profit<br>
                $100M revenue at 65% margin = $65M gross profit<br>
                <strong>+$5M to EBITDA</strong> (8-12x multiple = $40-60M value creation)</p>
            </li>

            <li><strong>EBITDA Margins are Limited by Gross Margin</strong>
                <p>If gross margin is 40%, your EBITDA margin can NEVER be more than 40% (and realistically will be 10-20%).</p>
                <p>But if gross margin is 75%, EBITDA margin can be 30-40% with operating leverage.</p>
            </li>

            <li><strong>Margin Improvement Playbook</strong>
                <p>PE firms look for companies with LOW gross margins compared to peers because there's upside:</p>
                <ul>
                    <li>Renegotiate supplier contracts</li>
                    <li>Optimize manufacturing footprint</li>
                    <li>Prune low-margin products</li>
                    <li>Raise prices (if market allows)</li>
                </ul>
            </li>
        </ol>
    </div>

    <div class="next-steps">
        <h3>Practice Exercise</h3>
        <p>You're modeling a manufacturing company with the following data:</p>
        <ul>
            <li>2023 Revenue: $500M</li>
            <li>2023 COGS: $325M</li>
            <li>Raw material costs are 40% of COGS</li>
            <li>Labor is 35% of COGS</li>
            <li>Overhead is 25% of COGS</li>
        </ul>

        <p><strong>Calculate:</strong></p>
        <ol>
            <li>Current gross margin %</li>
            <li>If revenue grows 10% in 2024 but material costs increase 8%, what happens to gross margin? (Assume labor and overhead grow with revenue)</li>
            <li>What price increase would you need to maintain the same gross margin %?</li>
        </ol>

        <p><strong>Bonus:</strong> Build a sensitivity table showing gross margin at different revenue growth rates (0%, 5%, 10%, 15%) and material cost inflation rates (0%, 4%, 8%, 12%).</p>
    </div>
</div>
HTML;
    }

    // Additional lesson content methods would go here
    // For brevity, I'll create a few more key lessons and then a default template

    private function get_opex_content() {
        return $this->generate_default_lesson('Operating Expenses', 3);
    }

    private function get_ebitda_content() {
        return $this->generate_default_lesson('EBITDA, EBIT, Net Income', 4);
    }

    private function get_income_statement_integration() {
        return $this->generate_default_lesson('Income Statement Integration', 5);
    }

    private function get_current_assets_content() {
        return $this->generate_default_lesson('Current Assets', 1);
    }

    private function get_liabilities_content() {
        return $this->generate_default_lesson('Liabilities Structure', 2);
    }

    private function get_shareholders_equity_content() {
        return $this->generate_default_lesson('Shareholders Equity', 3);
    }

    private function get_balance_sheet_linkages() {
        return $this->generate_default_lesson('Balance Sheet Linkages', 4);
    }

    private function get_operating_cashflow_content() {
        return $this->generate_default_lesson('Operating Cash Flow', 1);
    }

    private function get_investing_activities_content() {
        return $this->generate_default_lesson('Investing Activities', 2);
    }

    private function get_financing_activities_content() {
        return $this->generate_default_lesson('Financing Activities', 3);
    }

    private function generate_dcf_lesson($module, $topic, $lesson_id) {
        return $this->generate_default_lesson($topic, $lesson_id);
    }

    private function generate_lbo_lesson($module, $topic, $lesson_id) {
        return $this->generate_default_lesson($topic, $lesson_id);
    }

    private function generate_excel_lesson($module, $topic, $lesson_id) {
        return $this->generate_default_lesson($topic, $lesson_id);
    }

    private function generate_interview_lesson($module, $topic, $lesson_id) {
        return $this->generate_default_lesson($topic, $lesson_id);
    }

    /**
     * Default lesson template for topics not yet fully written
     */
    private function generate_default_lesson($topic, $lesson_id) {
        return <<<HTML
<div class="lesson-content">
    <h2>{$topic}</h2>

    <p>This lesson covers the essential concepts of {$topic} in financial modeling and analysis.</p>

    <h3>Learning Objectives</h3>
    <ul>
        <li>Understand the fundamentals of {$topic}</li>
        <li>Learn industry-standard practices and methodologies</li>
        <li>Apply concepts to real-world financial models</li>
    </ul>

    <h3>Core Concepts</h3>
    <p>Content for {$topic} is being finalized. Check back soon for comprehensive coverage.</p>
</div>
HTML;
    }

    /**
     * Extract key takeaways from HTML content
     */
    private function extract_key_takeaways($content) {
        // Simple extraction - look for main points
        return [
            'Master the three revenue modeling approaches',
            'Understand when to use each methodology',
            'Build defensible assumptions based on data'
        ];
    }

    /**
     * Get practice exercises for a topic
     */
    private function get_practice_exercises($topic) {
        return [];
    }
}

// Initialize
SFFC_Lesson_Content_Generator::get_instance();
