<?php
/**
 * Ultimate Content Generator - Robust and Error-Free
 * This version works without any dependencies or complex features
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Process form submission
$message = '';
$error = '';
$generated_items = array();

if (isset($_POST['generate_prep_content']) && check_admin_referer('generate_ultimate_content')) {
    
    // Disable problematic hooks during generation
    remove_all_filters('the_excerpt');
    remove_all_filters('the_content');
    remove_all_filters('wp_insert_post_data');
    
    $total_generated = 0;
    
    // 1. CASE STUDIES (5 comprehensive studies)
    if (isset($_POST['generate_case_studies'])) {
        $case_studies = array(
            array(
                'title' => 'Microsoft Activision Deal Analysis - $68.7B Gaming Acquisition',
                'content' => '<div class="prep-content"><h2>Deal Overview</h2><p>Microsoft\'s $68.7 billion acquisition of Activision Blizzard represents the largest gaming acquisition in history. This all-cash transaction demonstrates Microsoft\'s commitment to gaming and the metaverse.</p><h3>Strategic Rationale</h3><ul><li>Content acquisition: Call of Duty, World of Warcraft, Candy Crush</li><li>Game Pass expansion: Adding premium content to subscription service</li><li>Mobile gaming entry through King</li><li>Metaverse building blocks</li></ul><h3>Valuation Metrics</h3><p>EV/Revenue: 7.9x | EV/EBITDA: 21.5x | Premium: 45%</p><h3>Regulatory Journey</h3><p>Faced scrutiny from FTC, UK CMA, and EU. Microsoft offered behavioral remedies including 10-year access agreements.</p><h3>Integration Plan</h3><p>Maintain studio independence while integrating backend infrastructure with Azure.</p><h3>Key Takeaways</h3><p>Content is king in platform economy. Regulatory strategy critical for mega-deals. Strategic buyers can justify premium valuations.</p></div>'
            ),
            array(
                'title' => 'Apollo Tegna LBO - $8.6B Media Consolidation Play',
                'content' => '<div class="prep-content"><h2>Transaction Summary</h2><p>Apollo\'s $8.6B take-private of broadcaster Tegna showcases PE approach to traditional media.</p><h3>Investment Thesis</h3><ul><li>Stable cash flows from retransmission fees</li><li>Political advertising revenue cycles</li><li>Digital transformation opportunity</li><li>Operational improvement potential</li></ul><h3>Deal Structure</h3><p>Entry: 7.8x EBITDA | Leverage: 6.0x | Equity: 30%</p><h3>Value Creation Plan</h3><ul><li>Centralize operations across 64 stations</li><li>Renegotiate carriage agreements</li><li>Expand digital platform Premion</li><li>Pursue bolt-on acquisitions</li></ul><h3>Expected Returns</h3><p>Target IRR: 24% | MOIC: 2.8x | Exit: Year 5 at 6.5x EBITDA</p></div>'
            ),
            array(
                'title' => 'BlackRock ESG Strategy - Managing $10 Trillion Sustainably',
                'content' => '<div class="prep-content"><h2>Strategic Shift</h2><p>BlackRock\'s transformation to ESG leader with $4T+ in sustainable strategies reshapes global capital allocation.</p><h3>Implementation</h3><ul><li>400+ sustainable funds launched</li><li>Aladdin Climate analytics platform</li><li>Active stewardship program</li><li>Net Zero commitment by 2050</li></ul><h3>Business Impact</h3><p>ESG ETFs: $300B AUM | Higher fees on ESG products | Thought leadership position</p><h3>Challenges</h3><ul><li>Political backlash from 19 US states</li><li>Greenwashing accusations</li><li>Performance questions during energy rallies</li></ul><h3>Industry Implications</h3><p>ESG becoming mainstream. Scale and technology increasingly important. Corporate governance transformation.</p></div>'
            ),
            array(
                'title' => 'KKR April Group Acquisition - €3.5B European Insurance Roll-up',
                'content' => '<div class="prep-content"><h2>Deal Rationale</h2><p>KKR\'s €3.5B acquisition of April from CVC demonstrates insurance brokerage consolidation in fragmented European markets.</p><h3>Market Opportunity</h3><ul><li>Fragmented European insurance distribution</li><li>Digital transformation potential</li><li>Cross-border expansion opportunities</li></ul><h3>April\'s Position</h3><p>Leading French wholesale broker | €350M revenue | 20 countries | 7M policyholders</p><h3>Value Creation</h3><ul><li>Organic growth: 8-10% annually</li><li>M&A: 3-5 acquisitions yearly</li><li>Digital investment: €50M</li><li>Geographic expansion</li></ul><h3>Financial Engineering</h3><p>Entry: 12.5x EBITDA | Target Exit: 14.5x | Expected IRR: 26%</p></div>'
            ),
            array(
                'title' => 'Vista Equity Cvent Transformation - Software Operational Excellence',
                'content' => '<div class="prep-content"><h2>Investment Journey</h2><p>Vista\'s two take-privates of Cvent (2016: $1.65B, 2023: $5.3B) showcase operational value creation.</p><h3>Vista Operating System</h3><ul><li>Sales productivity +65%</li><li>Release velocity 6x improvement</li><li>Gross retention: 82% to 94%</li><li>EBITDA margins: 12% to 38%</li></ul><h3>Strategic M&A</h3><p>6 acquisitions including Social Tables, DoubleDutch, Splash to expand platform.</p><h3>Financial Transformation</h3><p>Revenue: $186M to $560M | Rule of 40: 25 to 55 | LTV/CAC: 2.1x to 6.2x</p><h3>Key Lessons</h3><p>Systematic operational improvement drives returns. Speed of execution critical. Platform consolidation creates value.</p></div>'
            )
        );
        
        foreach ($case_studies as $study) {
            $post_id = wp_insert_post(array(
                'post_title' => $study['title'],
                'post_content' => $study['content'],
                'post_type' => 'prep_case_study',
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ), true);
            
            if (!is_wp_error($post_id)) {
                $total_generated++;
                $generated_items[] = "Case Study: " . $study['title'];
            }
        }
    }
    
    // 2. INTERVIEW QUESTIONS (10 essential questions)
    if (isset($_POST['generate_questions'])) {
        $questions = array(
            array(
                'title' => 'Walk me through a DCF',
                'content' => '<div class="prep-content"><h2>DCF Steps</h2><ol><li><strong>Project FCF (5-10 years):</strong> Revenue growth, margins, capex, NWC</li><li><strong>Calculate Terminal Value:</strong> Gordon Growth or Exit Multiple</li><li><strong>Discount to Present Value:</strong> Using WACC</li><li><strong>Enterprise to Equity Bridge:</strong> Add cash, subtract debt</li></ol><h3>Key Formula</h3><p>FCF = EBIT(1-Tax) + D&A - CapEx - ΔNWC</p><h3>Common Mistakes</h3><ul><li>Terminal value often 60-80% of total</li><li>Check implied multiples vs comps</li><li>Use mid-year convention</li></ul></div>'
            ),
            array(
                'title' => 'How do you calculate WACC?',
                'content' => '<div class="prep-content"><h2>WACC Formula</h2><p>WACC = (E/V × Re) + (D/V × Rd × (1-Tc))</p><h3>Components</h3><ul><li><strong>Re (Cost of Equity):</strong> Rf + β(Rm-Rf)</li><li><strong>Rd (Cost of Debt):</strong> Current yield on bonds</li><li><strong>E/V:</strong> Equity weight (market values)</li><li><strong>D/V:</strong> Debt weight</li><li><strong>Tc:</strong> Tax rate</li></ul><h3>Example</h3><p>Re=11.7%, Rd=6.5%, E/V=67%, D/V=33%, Tax=25%<br>WACC = 0.67×11.7% + 0.33×6.5%×0.75 = 9.4%</p></div>'
            ),
            array(
                'title' => 'Walk through an LBO model',
                'content' => '<div class="prep-content"><h2>LBO Steps</h2><ol><li><strong>Entry:</strong> Purchase price, sources & uses</li><li><strong>Debt Schedule:</strong> Senior, mezz, equity layers</li><li><strong>Operations:</strong> Revenue/EBITDA projections</li><li><strong>Exit:</strong> Year 3-7, exit multiple</li><li><strong>Returns:</strong> IRR and MOIC calculation</li></ol><h3>Value Creation</h3><ul><li>EBITDA growth</li><li>Multiple expansion</li><li>Debt paydown</li></ul><h3>Target Returns</h3><p>IRR: 20-25% | MOIC: 2.5-3.0x</p></div>'
            ),
            array(
                'title' => 'Explain EV vs Equity Value',
                'content' => '<div class="prep-content"><h2>Definitions</h2><p><strong>Enterprise Value:</strong> Value to all investors (debt + equity)</p><p><strong>Equity Value:</strong> Value to equity holders only</p><h3>The Bridge</h3><p>EV - Net Debt + Cash - Minority Interest - Preferred = Equity Value</p><h3>Usage</h3><ul><li>EV for operational multiples (EV/EBITDA)</li><li>Equity for per-share metrics (P/E)</li></ul></div>'
            ),
            array(
                'title' => 'What are the main valuation methods?',
                'content' => '<div class="prep-content"><h2>Three Main Methods</h2><ol><li><strong>DCF:</strong> Intrinsic value based on cash flows</li><li><strong>Comps:</strong> Trading multiples of similar companies</li><li><strong>Precedents:</strong> Transaction multiples from M&A</li></ol><h3>When to Use</h3><ul><li>DCF: Stable cash flows, long-term view</li><li>Comps: Current market sentiment</li><li>Precedents: M&A context, control premium</li></ul></div>'
            ),
            array(
                'title' => 'How does leverage affect returns?',
                'content' => '<div class="prep-content"><h2>Leverage Impact</h2><p>Amplifies both gains and losses through financial leverage.</p><h3>Benefits</h3><ul><li>Higher equity returns if successful</li><li>Tax shield from interest</li><li>Disciplined cash management</li></ul><h3>Risks</h3><ul><li>Financial distress costs</li><li>Reduced flexibility</li><li>Covenant restrictions</li></ul><h3>Optimal Level</h3><p>Balance tax benefits vs distress costs. Industry-specific norms.</p></div>'
            ),
            array(
                'title' => 'Walk through the three financial statements',
                'content' => '<div class="prep-content"><h2>Income Statement</h2><p>Revenue - Expenses = Net Income (profitability over period)</p><h2>Balance Sheet</h2><p>Assets = Liabilities + Equity (snapshot at point in time)</p><h2>Cash Flow Statement</h2><p>Operating + Investing + Financing = Change in Cash</p><h3>How They Link</h3><ul><li>Net Income → Retained Earnings</li><li>D&A → PP&E and Cash Flow</li><li>CapEx → PP&E and Cash</li><li>NWC changes affect all three</li></ul></div>'
            ),
            array(
                'title' => 'What drives M&A activity?',
                'content' => '<div class="prep-content"><h2>Strategic Drivers</h2><ul><li>Synergies (cost and revenue)</li><li>Market consolidation</li><li>Geographic expansion</li><li>Technology acquisition</li><li>Vertical integration</li></ul><h2>Financial Drivers</h2><ul><li>Accretive to EPS</li><li>Multiple arbitrage</li><li>Tax benefits</li><li>Cheap financing</li></ul><h2>Market Conditions</h2><p>High valuations, available credit, CEO confidence, regulatory environment</p></div>'
            ),
            array(
                'title' => 'Explain working capital',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Current Assets - Current Liabilities (operating liquidity)</p><h3>Components</h3><ul><li>+ Accounts Receivable</li><li>+ Inventory</li><li>- Accounts Payable</li><li>= Net Working Capital</li></ul><h3>In Models</h3><p>Increase in NWC = cash outflow<br>Decrease in NWC = cash inflow</p><h3>Management</h3><p>Optimize cash cycle: Collect faster, pay slower, minimize inventory</p></div>'
            ),
            array(
                'title' => 'Why investment banking?',
                'content' => '<div class="prep-content"><h2>Strong Answer Elements</h2><ul><li>Passion for finance and markets</li><li>Enjoy analytical problem-solving</li><li>Thrive in fast-paced environments</li><li>Want to advise on transformational deals</li><li>Learn from brilliant colleagues</li></ul><h3>What NOT to Say</h3><ul><li>Just for the money</li><li>Stepping stone to PE</li><li>Parents want me to</li><li>Prestige only</li></ul><h3>Show You\'ve Researched</h3><p>Mention specific deals, team members, firm culture, recent news</p></div>'
            )
        );
        
        foreach ($questions as $q) {
            $post_id = wp_insert_post(array(
                'post_title' => $q['title'],
                'post_content' => $q['content'],
                'post_type' => 'prep_interview_q',
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ), true);
            
            if (!is_wp_error($post_id)) {
                $total_generated++;
                $generated_items[] = "Question: " . $q['title'];
            }
        }
    }
    
    // 3. FINANCIAL TERMS (10 key terms)
    if (isset($_POST['generate_terms'])) {
        $terms = array(
            array(
                'title' => 'EBITDA',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Earnings Before Interest, Taxes, Depreciation, and Amortization - operating performance metric.</p><h3>Calculation</h3><p>Net Income + Interest + Taxes + D&A</p><h3>Usage</h3><ul><li>Valuation multiples (EV/EBITDA)</li><li>Leverage ratios (Debt/EBITDA)</li><li>Coverage (EBITDA/Interest)</li></ul><h3>Limitations</h3><p>Ignores capex, working capital, not actual cash flow</p></div>'
            ),
            array(
                'title' => 'Carried Interest',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>GP\'s share of profits (typically 20%) as performance compensation.</p><h3>Structure</h3><ul><li>After LP preferred return (8%)</li><li>GP catch-up period</li><li>80/20 split thereafter</li></ul><h3>Tax Treatment</h3><p>Capital gains vs ordinary income debate. 3-year holding for cap gains.</p></div>'
            ),
            array(
                'title' => 'IRR (Internal Rate of Return)',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Discount rate that makes NPV = 0. Annualized return metric.</p><h3>PE Context</h3><p>Target: 20-25% gross IRR<br>Considers timing of cash flows<br>Key performance metric for funds</p><h3>vs MOIC</h3><p>IRR considers time, MOIC doesn\'t<br>Both important for evaluation</p></div>'
            ),
            array(
                'title' => 'Multiple on Invested Capital (MOIC)',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Total value returned / Total capital invested</p><h3>Example</h3><p>Invest $100M, return $300M = 3.0x MOIC</p><h3>PE Targets</h3><p>2.5-3.0x over 3-7 years<br>Doesn\'t consider time value<br>Simple but effective metric</p></div>'
            ),
            array(
                'title' => 'Limited Partner (LP)',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Investors in PE/VC funds with limited liability, no management role.</p><h3>Types</h3><ul><li>Pension funds (30%)</li><li>Sovereign wealth</li><li>Insurance companies</li><li>Endowments</li></ul><h3>Rights</h3><p>Information rights, LPAC seats, key person provisions</p></div>'
            ),
            array(
                'title' => 'General Partner (GP)',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Fund managers making investment decisions, unlimited liability.</p><h3>Compensation</h3><ul><li>Management fee: 2%</li><li>Carried interest: 20%</li></ul><h3>Responsibilities</h3><p>Deal sourcing, due diligence, portfolio management, exits</p></div>'
            ),
            array(
                'title' => 'Leveraged Buyout (LBO)',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Acquisition using significant debt to amplify equity returns.</p><h3>Typical Structure</h3><ul><li>60-70% debt</li><li>30-40% equity</li></ul><h3>Value Creation</h3><p>EBITDA growth, multiple expansion, debt paydown</p></div>'
            ),
            array(
                'title' => 'Accretion/Dilution',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Impact on acquirer\'s EPS from M&A transaction.</p><h3>Accretive</h3><p>Deal increases EPS (good for stock price)</p><h3>Dilutive</h3><p>Deal decreases EPS (needs strategic justification)</p><h3>Calculation</h3><p>Compare pro forma EPS to standalone EPS</p></div>'
            ),
            array(
                'title' => 'Terminal Value',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Company value beyond projection period in DCF.</p><h3>Methods</h3><ul><li>Gordon Growth: TV = FCF(1+g)/(WACC-g)</li><li>Exit Multiple: TV = EBITDA × Multiple</li></ul><h3>Importance</h3><p>Often 60-80% of DCF value. Critical assumption.</p></div>'
            ),
            array(
                'title' => 'Cost of Capital',
                'content' => '<div class="prep-content"><h2>Definition</h2><p>Required return for investment, opportunity cost.</p><h3>Components</h3><ul><li>Cost of Equity (CAPM)</li><li>Cost of Debt (yields)</li><li>Weighted Average (WACC)</li></ul><h3>Usage</h3><p>Discount rate, hurdle rate, performance benchmark</p></div>'
            )
        );
        
        foreach ($terms as $term) {
            $post_id = wp_insert_post(array(
                'post_title' => $term['title'],
                'post_content' => $term['content'],
                'post_type' => 'prep_term',
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ), true);
            
            if (!is_wp_error($post_id)) {
                $total_generated++;
                $generated_items[] = "Term: " . $term['title'];
            }
        }
    }
    
    // 4. DAY IN LIFE GUIDES (5 key roles)
    if (isset($_POST['generate_day_in_life'])) {
        $guides = array(
            array(
                'title' => 'Day in the Life - Investment Banking Analyst',
                'content' => '<div class="prep-content"><h2>Typical Schedule</h2><p><strong>8:00 AM:</strong> Arrive, check emails, market update<br><strong>9:00 AM:</strong> Team meeting, deal updates<br><strong>10:00 AM:</strong> Financial modeling (DCF, LBO, comps)<br><strong>1:00 PM:</strong> Working lunch at desk<br><strong>2:00 PM:</strong> Client presentation prep<br><strong>4:00 PM:</strong> Due diligence calls<br><strong>7:00 PM:</strong> Dinner at desk<br><strong>8:00 PM:</strong> Pitch book revisions<br><strong>12:00 AM:</strong> Head home</p><h3>Key Responsibilities</h3><ul><li>Financial modeling</li><li>Presentation creation</li><li>Market research</li><li>Deal execution support</li></ul><h3>Compensation</h3><p>Base: $110-150k | Bonus: 70-100% | Hours: 80-100/week</p></div>'
            ),
            array(
                'title' => 'Day in the Life - Private Equity Associate',
                'content' => '<div class="prep-content"><h2>Typical Day</h2><p><strong>9:00 AM:</strong> Portfolio company call<br><strong>10:00 AM:</strong> Deal team meeting<br><strong>11:00 AM:</strong> LBO model refinement<br><strong>1:00 PM:</strong> Lunch with management<br><strong>2:30 PM:</strong> Due diligence session<br><strong>4:00 PM:</strong> IC memo drafting<br><strong>6:00 PM:</strong> Industry research<br><strong>8:00 PM:</strong> Wrap up, some evening events</p><h3>Focus Areas</h3><ul><li>Deal sourcing & evaluation</li><li>Due diligence management</li><li>Portfolio company monitoring</li><li>Exit planning</li></ul><h3>Compensation</h3><p>Base: $200-275k | Bonus: 100%+ | Carry participation</p></div>'
            ),
            array(
                'title' => 'Day in the Life - Hedge Fund Analyst',
                'content' => '<div class="prep-content"><h2>Market Hours</h2><p><strong>6:30 AM:</strong> Pre-market prep<br><strong>7:00 AM:</strong> Morning call<br><strong>9:30 AM:</strong> Market open, position monitoring<br><strong>10:00 AM:</strong> Deep dive research<br><strong>12:00 PM:</strong> Lunch at desk<br><strong>2:00 PM:</strong> Model updates<br><strong>4:00 PM:</strong> Market close review<br><strong>5:00 PM:</strong> Team discussion<br><strong>7:00 PM:</strong> Head home, continue research</p><h3>Core Work</h3><ul><li>Investment research</li><li>Financial modeling</li><li>Risk monitoring</li><li>Idea generation</li></ul><h3>Compensation</h3><p>Base: $150-200k | Bonus: 50-150%+ performance-based</p></div>'
            ),
            array(
                'title' => 'Day in the Life - VC Associate',
                'content' => '<div class="prep-content"><h2>Typical Schedule</h2><p><strong>9:00 AM:</strong> Email, deal flow review<br><strong>10:00 AM:</strong> Founder meeting<br><strong>11:30 AM:</strong> Partner discussion<br><strong>1:00 PM:</strong> Lunch with entrepreneur<br><strong>2:30 PM:</strong> Due diligence calls<br><strong>4:00 PM:</strong> Investment memo<br><strong>5:30 PM:</strong> Industry event<br><strong>8:00 PM:</strong> Wrap up</p><h3>Key Activities</h3><ul><li>Deal sourcing</li><li>Founder evaluation</li><li>Market analysis</li><li>Portfolio support</li></ul><h3>Compensation</h3><p>Base: $150-200k | Bonus: 30-50% | Carry in later years</p></div>'
            ),
            array(
                'title' => 'Day in the Life - Asset Management Analyst',
                'content' => '<div class="prep-content"><h2>Daily Routine</h2><p><strong>7:00 AM:</strong> Market news, overnight moves<br><strong>8:00 AM:</strong> Morning meeting<br><strong>9:00 AM:</strong> Sector research<br><strong>11:00 AM:</strong> Company call<br><strong>12:30 PM:</strong> Lunch break<br><strong>2:00 PM:</strong> Model building<br><strong>4:00 PM:</strong> PM discussion<br><strong>5:30 PM:</strong> Report writing<br><strong>6:30 PM:</strong> Head home</p><h3>Responsibilities</h3><ul><li>Equity research</li><li>Financial analysis</li><li>Portfolio monitoring</li><li>Client reporting</li></ul><h3>Compensation</h3><p>Base: $90-120k | Bonus: 20-60% | Better work-life balance</p></div>'
            )
        );
        
        foreach ($guides as $guide) {
            $post_id = wp_insert_post(array(
                'post_title' => $guide['title'],
                'post_content' => $guide['content'],
                'post_type' => 'prep_day_in_life',
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ), true);
            
            if (!is_wp_error($post_id)) {
                $total_generated++;
                $generated_items[] = "Guide: " . $guide['title'];
            }
        }
    }
    
    // 5. MODELING GUIDES (5 essential guides)
    if (isset($_POST['generate_modeling'])) {
        $modeling = array(
            array(
                'title' => 'DCF Modeling Guide',
                'content' => '<div class="prep-content"><h2>Model Structure</h2><ol><li>Assumptions tab (blue inputs)</li><li>Historical financials (3-5 years)</li><li>Projections (5-10 years)</li><li>DCF calculations</li><li>Sensitivity analysis</li></ol><h3>Best Practices</h3><ul><li>Use consistent formulas</li><li>Build error checks</li><li>Document assumptions</li><li>Create scenarios</li></ul><h3>Common Formulas</h3><p>Revenue: Prior year × (1+growth)<br>FCF: EBIT(1-T) + D&A - Capex - ΔNWC<br>TV: FCF × (1+g) / (WACC-g)</p></div>'
            ),
            array(
                'title' => 'LBO Modeling Guide',
                'content' => '<div class="prep-content"><h2>Model Components</h2><ol><li>Transaction assumptions</li><li>Sources & uses</li><li>Purchase price allocation</li><li>Debt schedule</li><li>Income statement</li><li>Cash flow</li><li>Returns analysis</li></ol><h3>Key Mechanics</h3><ul><li>Debt paydown waterfall</li><li>Cash sweep calculations</li><li>PIK interest toggle</li><li>Management rollover</li></ul><h3>Return Sensitivities</h3><p>Test: Entry/exit multiples, EBITDA growth, leverage levels</p></div>'
            ),
            array(
                'title' => 'M&A Model Guide',
                'content' => '<div class="prep-content"><h2>Model Framework</h2><ol><li>Standalone projections</li><li>Transaction structure</li><li>Purchase price allocation</li><li>Pro forma adjustments</li><li>Synergy modeling</li><li>Accretion/dilution</li></ol><h3>Key Analyses</h3><ul><li>EPS impact</li><li>Credit impact</li><li>Contribution analysis</li><li>Value creation</li></ul><h3>Synergy Types</h3><p>Revenue: Cross-sell, pricing power<br>Cost: Overhead, procurement, operations</p></div>'
            ),
            array(
                'title' => 'Three Statement Model Guide',
                'content' => '<div class="prep-content"><h2>Model Architecture</h2><ol><li>Income statement build</li><li>Balance sheet projections</li><li>Cash flow statement</li><li>Supporting schedules</li></ol><h3>Key Linkages</h3><ul><li>Net income → Retained earnings</li><li>D&A → PP&E and CFO</li><li>Capex → PP&E and CFI</li><li>Debt → Interest expense</li></ul><h3>Balancing</h3><p>Assets = Liabilities + Equity must balance<br>Use cash as plug or revolver</p></div>'
            ),
            array(
                'title' => 'Comps Analysis Guide',
                'content' => '<div class="prep-content"><h2>Process Steps</h2><ol><li>Select comparable companies</li><li>Gather financial data</li><li>Calculate multiples</li><li>Normalize adjustments</li><li>Apply to target</li></ol><h3>Key Multiples</h3><ul><li>EV/Revenue</li><li>EV/EBITDA</li><li>EV/EBIT</li><li>P/E</li><li>P/B</li></ul><h3>Adjustments</h3><p>Calendarization, one-time items, stock comp, leases</p></div>'
            )
        );
        
        foreach ($modeling as $model) {
            $post_id = wp_insert_post(array(
                'post_title' => $model['title'],
                'post_content' => $model['content'],
                'post_type' => 'prep_model_guide',
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ), true);
            
            if (!is_wp_error($post_id)) {
                $total_generated++;
                $generated_items[] = "Model Guide: " . $model['title'];
            }
        }
    }
    
    // Re-enable hooks
    add_filter('the_excerpt', 'wp_trim_excerpt');
    
    if ($total_generated > 0) {
        $message = "✅ Successfully generated $total_generated content items!";
    } else {
        $error = "No content was generated. Please select at least one content type.";
    }
}

// Get current content counts
$content_counts = array();
$post_types = array(
    'prep_case_study' => 'Case Studies',
    'prep_interview_q' => 'Interview Questions',
    'prep_term' => 'Financial Terms',
    'prep_day_in_life' => 'Day in Life Guides',
    'prep_model_guide' => 'Modeling Guides'
);

foreach ($post_types as $type => $label) {
    if (post_type_exists($type)) {
        $count = wp_count_posts($type);
        $content_counts[$type] = isset($count->publish) ? $count->publish : 0;
    } else {
        // Register the post type if it doesn't exist
        register_post_type($type, array(
            'public' => true,
            'labels' => array('name' => $label),
            'supports' => array('title', 'editor', 'excerpt')
        ));
        $content_counts[$type] = 0;
    }
}

?>
<div class="wrap">
    <h1>🚀 Ultimate Prep Content Generator</h1>
    
    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible">
            <p style="font-size: 16px;"><?php echo esc_html($message); ?></p>
            <?php if (!empty($generated_items)): ?>
                <details>
                    <summary>View generated items</summary>
                    <ul>
                        <?php foreach ($generated_items as $item): ?>
                            <li><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>
    
    <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <h2>Generate Premium Prep Content</h2>
        <p style="font-size: 16px;">This generator creates comprehensive prep materials for finance interviews. All content is professional-grade and ready to use.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('generate_ultimate_content'); ?>
            
            <table class="widefat" style="margin: 20px 0;">
                <thead>
                    <tr>
                        <th>Content Type</th>
                        <th>Current Count</th>
                        <th>Generate</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Case Studies</strong></td>
                        <td><?php echo $content_counts['prep_case_study']; ?> published</td>
                        <td><input type="checkbox" name="generate_case_studies" value="1" checked></td>
                        <td>5 detailed M&A and PE case studies</td>
                    </tr>
                    <tr>
                        <td><strong>Interview Questions</strong></td>
                        <td><?php echo $content_counts['prep_interview_q']; ?> published</td>
                        <td><input type="checkbox" name="generate_questions" value="1" checked></td>
                        <td>10 essential technical and behavioral questions</td>
                    </tr>
                    <tr>
                        <td><strong>Financial Terms</strong></td>
                        <td><?php echo $content_counts['prep_term']; ?> published</td>
                        <td><input type="checkbox" name="generate_terms" value="1" checked></td>
                        <td>10 key finance terms and concepts</td>
                    </tr>
                    <tr>
                        <td><strong>Day in Life Guides</strong></td>
                        <td><?php echo $content_counts['prep_day_in_life']; ?> published</td>
                        <td><input type="checkbox" name="generate_day_in_life" value="1" checked></td>
                        <td>5 role-specific career guides</td>
                    </tr>
                    <tr>
                        <td><strong>Modeling Guides</strong></td>
                        <td><?php echo $content_counts['prep_model_guide']; ?> published</td>
                        <td><input type="checkbox" name="generate_modeling" value="1" checked></td>
                        <td>5 financial modeling tutorials</td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <button type="submit" name="generate_prep_content" class="button button-primary button-hero" style="font-size: 18px;">
                    Generate Selected Content
                </button>
            </p>
        </form>
        
        <hr style="margin: 30px 0;">
        
        <h3>ℹ️ What This Generator Creates:</h3>
        <ul style="font-size: 14px; line-height: 1.8;">
            <li><strong>Case Studies:</strong> Real deal analyses including Microsoft/Activision, Apollo/Tegna, BlackRock ESG, KKR/April, Vista/Cvent</li>
            <li><strong>Interview Questions:</strong> Technical questions with detailed answers (DCF, WACC, LBO, valuation methods)</li>
            <li><strong>Financial Terms:</strong> Key concepts explained (EBITDA, Carried Interest, IRR, MOIC, etc.)</li>
            <li><strong>Day in Life:</strong> Realistic daily schedules for IB Analyst, PE Associate, HF Analyst, VC, AM roles</li>
            <li><strong>Modeling Guides:</strong> Step-by-step tutorials for DCF, LBO, M&A, 3-statement, and comps models</li>
        </ul>
        
        <p style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin-top: 20px;">
            <strong>Note:</strong> This generator creates real WordPress posts that will be visible on your site. The content is comprehensive and professional-grade, suitable for finance interview preparation.
        </p>
    </div>
</div>