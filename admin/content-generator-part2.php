<?php
/**
 * Additional Content for Generator - Part 2
 * Financial Terms, Day in Life Guides, and Modeling Guides
 */

// Financial Terms with FULL content
function get_financial_terms_content() {
    return [
        [
            'title' => 'Carried Interest (Carry)',
            'content' => '
<div class="sffc-term-definition">
    <div class="sffc-term-header">
        <h1 class="sffc-term-title">Carried Interest (Carry)</h1>
        <div class="sffc-term-meta">
            <span class="category">Private Equity</span>
            <span class="reading-time">5 min read</span>
        </div>
    </div>
    
    <div class="sffc-definition-box">
        <h2>Definition</h2>
        <p class="sffc-lead-definition">
            Carried interest, commonly known as "carry," is the share of profits (typically 20%) that general partners of private equity and hedge funds receive as compensation, regardless of their capital contribution to the fund.
        </p>
    </div>
    
    <div class="sffc-detailed-explanation">
        <h3>How It Works</h3>
        <div class="sffc-mechanism">
            <p>Carried interest represents the GP\'s share of the fund\'s profits above a certain return threshold (hurdle rate). It aligns the interests of fund managers with investors by ensuring GPs only profit when LPs do well.</p>
            
            <div class="sffc-example-box">
                <h4>Typical Structure (2/20 Model):</h4>
                <ul class="sffc-premium-list">
                    <li><strong>Management Fee:</strong> 2% of AUM annually</li>
                    <li><strong>Carried Interest:</strong> 20% of profits above hurdle rate</li>
                    <li><strong>Hurdle Rate:</strong> Usually 8% preferred return to LPs</li>
                    <li><strong>Catch-up:</strong> GP receives 100% until they reach 20% of total profits</li>
                </ul>
            </div>
        </div>
        
        <h3>Waterfall Example</h3>
        <div class="sffc-calculation-example">
            <p><strong>Scenario:</strong> $1B fund with 8% hurdle and 20% carry</p>
            <table class="sffc-premium-table">
                <thead>
                    <tr>
                        <th>Distribution Tier</th>
                        <th>Recipients</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Return of Capital</td>
                        <td>100% to LPs</td>
                        <td>First $1B</td>
                    </tr>
                    <tr>
                        <td>Preferred Return (8%)</td>
                        <td>100% to LPs</td>
                        <td>Next $80M</td>
                    </tr>
                    <tr>
                        <td>GP Catch-up</td>
                        <td>100% to GP</td>
                        <td>Next $20M</td>
                    </tr>
                    <tr>
                        <td>Carried Interest Split</td>
                        <td>80% LP / 20% GP</td>
                        <td>All remaining</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <h3>Types of Carry Structures</h3>
        <div class="sffc-types-grid">
            <div class="sffc-type-card">
                <h4>European Waterfall</h4>
                <p>Carry calculated on whole fund basis. LPs receive all capital plus preferred return before GP participates.</p>
                <ul>
                    <li>More LP-friendly</li>
                    <li>GP waits longer for carry</li>
                    <li>Common in Europe</li>
                </ul>
            </div>
            
            <div class="sffc-type-card">
                <h4>American Waterfall</h4>
                <p>Carry calculated deal-by-deal. GP can receive carry on profitable deals even if fund hasn\'t returned capital.</p>
                <ul>
                    <li>More GP-friendly</li>
                    <li>Earlier carry distribution</li>
                    <li>Common in US</li>
                </ul>
            </div>
        </div>
        
        <h3>Tax Treatment</h3>
        <div class="sffc-tax-section">
            <p><strong>Capital Gains Treatment:</strong> Historically, carry has been taxed as long-term capital gains (20% + 3.8% NIIT) rather than ordinary income (37% top rate), creating significant tax advantages.</p>
            
            <p><strong>Recent Changes:</strong> Tax legislation now requires 3-year holding period for capital gains treatment on carry.</p>
        </div>
        
        <h3>Clawback Provisions</h3>
        <div class="sffc-important-note">
            <p>Most LPAs include clawback provisions requiring GPs to return excess carry if later investments underperform, ensuring the agreed profit split over the fund\'s entire life.</p>
        </div>
        
        <h3>Industry Context</h3>
        <ul class="sffc-context-list">
            <li>Top-tier funds may negotiate 25-30% carry</li>
            <li>First-time funds might accept 15% carry</li>
            <li>Venture capital often uses 20-30% carry with no hurdle</li>
            <li>Real estate funds typically use 20% carry with 8-10% hurdle</li>
        </ul>
        
        <h3>Interview Insights</h3>
        <div class="sffc-interview-tips">
            <p><strong>Key Points to Remember:</strong></p>
            <ul class="sffc-premium-list">
                <li>Carry aligns GP/LP interests but can create conflicts</li>
                <li>Understand difference between European and American waterfalls</li>
                <li>Be aware of current tax treatment debates</li>
                <li>Know typical carry percentages by fund type</li>
                <li>Understand how carry motivates fund performance</li>
            </ul>
        </div>
    </div>
</div>'
        ],
        [
            'title' => 'Limited Partner (LP)',
            'content' => '
<div class="sffc-term-definition">
    <div class="sffc-term-header">
        <h1 class="sffc-term-title">Limited Partner (LP)</h1>
        <div class="sffc-term-meta">
            <span class="category">Private Equity</span>
            <span class="reading-time">4 min read</span>
        </div>
    </div>
    
    <div class="sffc-definition-box">
        <h2>Definition</h2>
        <p class="sffc-lead-definition">
            Limited Partners (LPs) are the institutional or individual investors who provide capital to private equity funds but have limited liability and no day-to-day management involvement in the fund\'s operations.
        </p>
    </div>
    
    <div class="sffc-detailed-explanation">
        <h3>Role and Characteristics</h3>
        <ul class="sffc-premium-list">
            <li><strong>Capital Providers:</strong> Commit capital to be called over investment period</li>
            <li><strong>Limited Liability:</strong> Losses limited to invested capital</li>
            <li><strong>Passive Investors:</strong> No involvement in investment decisions</li>
            <li><strong>Priority Returns:</strong> Typically receive preferred return before GP carry</li>
        </ul>
        
        <h3>Types of Limited Partners</h3>
        <div class="sffc-lp-types">
            <div class="sffc-type-card">
                <h4>Pension Funds</h4>
                <p>~30% of PE capital</p>
                <ul>
                    <li>CalPERS, CPPIB, APG</li>
                    <li>Long-term horizon</li>
                    <li>$50M-500M+ tickets</li>
                </ul>
            </div>
            
            <div class="sffc-type-card">
                <h4>Sovereign Wealth Funds</h4>
                <p>~15% of PE capital</p>
                <ul>
                    <li>GIC, ADIA, Temasek</li>
                    <li>Large commitments</li>
                    <li>Co-investment appetite</li>
                </ul>
            </div>
            
            <div class="sffc-type-card">
                <h4>Insurance Companies</h4>
                <p>~10% of PE capital</p>
                <ul>
                    <li>MetLife, Prudential</li>
                    <li>Asset-liability matching</li>
                    <li>Regulatory constraints</li>
                </ul>
            </div>
            
            <div class="sffc-type-card">
                <h4>Endowments & Foundations</h4>
                <p>~10% of PE capital</p>
                <ul>
                    <li>Yale, Harvard, Ford Foundation</li>
                    <li>Sophisticated investors</li>
                    <li>Pioneer alternative assets</li>
                </ul>
            </div>
        </div>
        
        <h3>LP Rights and Protections</h3>
        <div class="sffc-rights-section">
            <h4>Key Rights:</h4>
            <ul class="sffc-premium-list">
                <li><strong>Information Rights:</strong> Quarterly reports, annual audits</li>
                <li><strong>LPAC Seats:</strong> Limited Partner Advisory Committee participation</li>
                <li><strong>Key Person Provisions:</strong> Suspension if key partners leave</li>
                <li><strong>No-Fault Divorce:</strong> Ability to remove GP for cause</li>
                <li><strong>Co-investment Rights:</strong> Opportunity to invest alongside fund</li>
            </ul>
        </div>
        
        <h3>Capital Commitment Process</h3>
        <div class="sffc-process-timeline">
            <ol class="sffc-numbered-list">
                <li><strong>Commitment:</strong> LP commits capital amount (e.g., $100M)</li>
                <li><strong>Capital Calls:</strong> GP draws down ~20-30% annually over 3-5 years</li>
                <li><strong>Investment Period:</strong> Capital deployed into portfolio companies</li>
                <li><strong>Harvest Period:</strong> Distributions from exits returned to LPs</li>
                <li><strong>Fund Wind-down:</strong> Final distributions and fund closure</li>
            </ol>
        </div>
        
        <h3>Fee Structure for LPs</h3>
        <table class="sffc-premium-table">
            <thead>
                <tr>
                    <th>Fee Type</th>
                    <th>Typical Rate</th>
                    <th>Basis</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Management Fee</td>
                    <td>2%</td>
                    <td>On committed/invested capital</td>
                </tr>
                <tr>
                    <td>Carried Interest</td>
                    <td>20%</td>
                    <td>On profits above hurdle</td>
                </tr>
                <tr>
                    <td>Fund Expenses</td>
                    <td>0.1-0.5%</td>
                    <td>Legal, audit, administration</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>'
        ],
        // Add more financial terms...
    ];
}

// Day in Life Guides with FULL content
function get_day_in_life_content() {
    return [
        [
            'title' => 'Day in the Life: Investment Banking Analyst at Goldman Sachs',
            'content' => '
<div class="sffc-day-in-life-guide">
    <div class="sffc-hero-timeline">
        <h1 class="sffc-role-title">Investment Banking Analyst</h1>
        <div class="sffc-role-meta">
            <span class="company">Goldman Sachs</span>
            <span class="location">New York</span>
            <span class="division">TMT Coverage</span>
        </div>
    </div>
    
    <div class="sffc-executive-summary">
        <p class="sffc-lead-text">
            As a first-year analyst in Goldman Sachs\' Technology, Media & Telecom group, your days are intense, intellectually challenging, and constantly evolving. You\'re at the heart of billion-dollar transactions, working alongside the brightest minds in finance to advise the world\'s leading companies on their most critical strategic decisions.
        </p>
    </div>
    
    <div class="sffc-typical-schedule">
        <h2 class="sffc-section-title">A Typical Day</h2>
        
        <div class="sffc-timeline">
            <div class="sffc-time-block">
                <span class="time">8:00 AM</span>
                <div class="activity">
                    <h4>Morning Preparation</h4>
                    <p>Arrive at 200 West Street. Check overnight emails from London/Asia teams. Review market movements and relevant news for client sectors. Print and review any comments on presentations from last night.</p>
                    <ul class="tasks">
                        <li>Update market comp sheets with pre-market trading</li>
                        <li>Flag relevant news for deal teams</li>
                        <li>Prepare for 9am team meeting</li>
                    </ul>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">9:00 AM</span>
                <div class="activity">
                    <h4>Team Meeting</h4>
                    <p>Weekly TMT group meeting. MDs discuss pipeline, each deal team provides updates. You present analysis on the semiconductor sector consolidation trends you\'ve been tracking.</p>
                    <ul class="tasks">
                        <li>Present 5-slide sector update</li>
                        <li>Take notes on new deal assignments</li>
                        <li>Get staffed on new software company IPO</li>
                    </ul>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">10:00 AM</span>
                <div class="activity">
                    <h4>Financial Modeling</h4>
                    <p>Deep dive into LBO model for PE client evaluating $3B software acquisition. Model needs to incorporate complex earnout structure and synergy assumptions.</p>
                    <ul class="tasks">
                        <li>Build detailed revenue build-up across 5 product lines</li>
                        <li>Model working capital seasonality</li>
                        <li>Create sensitivity tables for key assumptions</li>
                        <li>Prepare returns analysis across different scenarios</li>
                    </ul>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">1:00 PM</span>
                <div class="activity">
                    <h4>Working Lunch</h4>
                    <p>Eat at desk while on call with Associate reviewing pitch book for tomorrow\'s meeting. CEO wants to explore strategic alternatives - need to outline potential buyers, valuation ranges, and process timeline.</p>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">2:30 PM</span>
                <div class="activity">
                    <h4>Due Diligence Call</h4>
                    <p>Join management presentation for sell-side process. Take detailed notes on Q&A, focusing on technology stack, customer concentration, and growth drivers. Will need to update data room FAQ tonight.</p>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">4:00 PM</span>
                <div class="activity">
                    <h4>Comparable Company Analysis</h4>
                    <p>VP needs updated trading comps for board presentation. Pull data for 25 companies, calculate multiples, identify outliers, and create visualization showing valuation trends over past 24 months.</p>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">6:30 PM</span>
                <div class="activity">
                    <h4>Dinner Break</h4>
                    <p>Order dinner to the office (expensed). Quick break to eat with other analysts. Discuss upcoming PE recruiting and share interview tips. Check personal phone for first time today.</p>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">7:30 PM</span>
                <div class="activity">
                    <h4>Pitch Book Production</h4>
                    <p>MD sends comments on strategic alternatives presentation. Work with graphics team to update charts. Ensure all numbers tie across 80+ slides. Format needs to be perfect - this goes to board tomorrow.</p>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">11:00 PM</span>
                <div class="activity">
                    <h4>Final Review & Tomorrow Prep</h4>
                    <p>Send updated presentation to deal team for final review. Start preliminary work on new IPO assignment. Update to-do list and calendar for tomorrow. Book car service home.</p>
                </div>
            </div>
            
            <div class="sffc-time-block">
                <span class="time">12:30 AM</span>
                <div class="activity">
                    <h4>Head Home</h4>
                    <p>Take company car home. Check emails during ride. Set alarm for 6:30 AM. Will need to review any overnight comments before morning meeting.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="sffc-key-responsibilities">
        <h2 class="sffc-section-title">Core Responsibilities</h2>
        
        <div class="sffc-responsibility-grid">
            <div class="sffc-responsibility">
                <h4>Financial Modeling</h4>
                <p>Build and maintain complex financial models including DCFs, LBOs, merger models, and operating models. Must be extremely detail-oriented and comfortable with large datasets.</p>
                <div class="skill-tags">
                    <span>Excel</span>
                    <span>Valuation</span>
                    <span>Scenario Analysis</span>
                </div>
            </div>
            
            <div class="sffc-responsibility">
                <h4>Presentation Creation</h4>
                <p>Develop client-ready presentations including pitch books, board presentations, and marketing materials. Must tell compelling stories with data and visuals.</p>
                <div class="skill-tags">
                    <span>PowerPoint</span>
                    <span>Data Visualization</span>
                    <span>Storytelling</span>
                </div>
            </div>
            
            <div class="sffc-responsibility">
                <h4>Market Research</h4>
                <p>Track industry trends, monitor comparable companies, analyze precedent transactions. Become sector expert in assigned coverage areas.</p>
                <div class="skill-tags">
                    <span>Capital IQ</span>
                    <span>Bloomberg</span>
                    <span>Industry Analysis</span>
                </div>
            </div>
            
            <div class="sffc-responsibility">
                <h4>Deal Execution Support</h4>
                <p>Support live transactions including due diligence, data room management, buyer outreach tracking, and process coordination.</p>
                <div class="skill-tags">
                    <span>Project Management</span>
                    <span>Due Diligence</span>
                    <span>Process Management</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="sffc-compensation-section">
        <h2 class="sffc-section-title">Compensation & Benefits</h2>
        
        <div class="sffc-comp-breakdown">
            <table class="sffc-premium-table">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th>First Year</th>
                        <th>Second Year</th>
                        <th>Third Year</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Base Salary</td>
                        <td>$110,000</td>
                        <td>$125,000</td>
                        <td>$150,000</td>
                    </tr>
                    <tr>
                        <td>Bonus (% of base)</td>
                        <td>70-100%</td>
                        <td>80-120%</td>
                        <td>90-130%</td>
                    </tr>
                    <tr>
                        <td>All-in Comp</td>
                        <td>$185,000-220,000</td>
                        <td>$225,000-275,000</td>
                        <td>$285,000-345,000</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="sffc-benefits-list">
                <h4>Additional Benefits</h4>
                <ul>
                    <li>Comprehensive health insurance</li>
                    <li>401(k) with match</li>
                    <li>Dinner and car service for late nights</li>
                    <li>Gym membership subsidy</li>
                    <li>Protected Saturday policy (in theory)</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="sffc-career-progression">
        <h2 class="sffc-section-title">Career Progression</h2>
        
        <div class="sffc-progression-timeline">
            <div class="sffc-career-stage">
                <h4>Years 1-2: Analyst</h4>
                <p>Master technical skills, build network, gain deal experience. Many exit to PE/HF.</p>
            </div>
            
            <div class="sffc-career-stage">
                <h4>Years 3-5: Associate</h4>
                <p>Post-MBA or promoted analysts. Manage deal processes, lead client interactions.</p>
            </div>
            
            <div class="sffc-career-stage">
                <h4>Years 6-8: Vice President</h4>
                <p>Own deal execution, develop junior bankers, begin origination efforts.</p>
            </div>
            
            <div class="sffc-career-stage">
                <h4>Years 9-12: Director/ED</h4>
                <p>Focus on origination, maintain key client relationships, revenue responsibility.</p>
            </div>
            
            <div class="sffc-career-stage">
                <h4>Years 13+: Managing Director</h4>
                <p>Pure origination focus, P&L ownership, franchise leadership.</p>
            </div>
        </div>
        
        <div class="sffc-exit-opportunities">
            <h3>Common Exit Opportunities</h3>
            <ul class="sffc-exit-list">
                <li><strong>Private Equity:</strong> Most common path, ~40% of analysts</li>
                <li><strong>Hedge Funds:</strong> Fundamental L/S or event-driven funds</li>
                <li><strong>Corporate Development:</strong> Strategic/M&A roles at corporations</li>
                <li><strong>Venture Capital:</strong> Particularly for TMT bankers</li>
                <li><strong>Business School:</strong> Top MBA programs after 2-3 years</li>
            </ul>
        </div>
    </div>
    
    <div class="sffc-lifestyle-reality">
        <h2 class="sffc-section-title">The Reality Check</h2>
        
        <div class="sffc-pros-cons">
            <div class="sffc-pros">
                <h4>The Rewards</h4>
                <ul>
                    <li>Unparalleled learning experience</li>
                    <li>Work on landmark transactions</li>
                    <li>Build elite professional network</li>
                    <li>Strong compensation for age</li>
                    <li>Opens doors to any finance career</li>
                    <li>Develop incredible work ethic</li>
                </ul>
            </div>
            
            <div class="sffc-cons">
                <h4>The Challenges</h4>
                <ul>
                    <li>80-100 hour weeks are standard</li>
                    <li>Weekend work is common</li>
                    <li>Limited control over schedule</li>
                    <li>High stress and pressure</li>
                    <li>Repetitive tasks early on</li>
                    <li>Limited work-life balance</li>
                </ul>
            </div>
        </div>
        
        <div class="sffc-insider-tips">
            <h3>Insider Tips for Success</h3>
            <ul class="sffc-tips-list">
                <li><strong>Attention to Detail:</strong> One number off can derail a deal</li>
                <li><strong>Be Proactive:</strong> Anticipate needs before being asked</li>
                <li><strong>Build Relationships:</strong> Your analyst class is your support system</li>
                <li><strong>Stay Organized:</strong> Use tools to manage multiple workstreams</li>
                <li><strong>Protect Some Time:</strong> Try to maintain one hobby or routine</li>
                <li><strong>Think Long-term:</strong> 2 years goes quickly, maximize learning</li>
            </ul>
        </div>
    </div>
</div>'
        ],
        // Add more day in life guides...
    ];
}

// Modeling Guides with FULL content
function get_modeling_guides_content() {
    return [
        [
            'title' => 'Complete DCF Modeling Guide: From Theory to Practice',
            'content' => '
<div class="sffc-modeling-guide">
    <h1>Complete DCF Modeling Guide</h1>
    
    <div class="sffc-guide-overview">
        <p>This comprehensive guide will teach you how to build a professional DCF model from scratch, including all the nuances and best practices used by investment banking analysts.</p>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 1: Setting Up Your Model</h2>
        
        <h3>Model Architecture</h3>
        <ul>
            <li><strong>Assumptions Tab:</strong> All inputs in blue font, clearly labeled</li>
            <li><strong>Historical Financials:</strong> 3-5 years of data</li>
            <li><strong>Projections:</strong> Revenue build and operating model</li>
            <li><strong>DCF Tab:</strong> Free cash flow and valuation</li>
            <li><strong>Sensitivity Tab:</strong> Key assumption testing</li>
            <li><strong>Output Tab:</strong> Summary and football field</li>
        </ul>
        
        <h3>Best Practices</h3>
        <ul>
            <li>Use consistent formatting throughout</li>
            <li>Hard-code historical data in black</li>
            <li>Formulas in blue, linked cells in green</li>
            <li>Include source citations for all data</li>
            <li>Build in error checks and circuit breakers</li>
        </ul>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 2: Revenue Projections</h2>
        
        <h3>Revenue Build Approaches</h3>
        
        <h4>1. Growth Rate Method</h4>
        <code>
        Revenue(t) = Revenue(t-1) × (1 + Growth Rate)
        </code>
        <p>Simple but less granular. Best for mature companies with stable growth.</p>
        
        <h4>2. Unit Economics Method</h4>
        <code>
        Revenue = Units Sold × Average Selling Price
        </code>
        <p>More detailed approach. Allows for volume/price analysis.</p>
        
        <h4>3. Segment Build-Up</h4>
        <code>
        Total Revenue = Σ(Segment Revenue)
        </code>
        <p>Most detailed. Build each product line or geography separately.</p>
        
        <h3>Growth Rate Assumptions</h3>
        <ul>
            <li>Analyze historical CAGRs (3, 5, 10 year)</li>
            <li>Consider industry growth rates</li>
            <li>Factor in company guidance</li>
            <li>Account for market maturity</li>
            <li>Build in decay rates for outer years</li>
        </ul>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 3: Operating Assumptions</h2>
        
        <h3>Cost Structure Modeling</h3>
        
        <h4>COGS Assumptions</h4>
        <ul>
            <li>Historical gross margin analysis</li>
            <li>Factor in economies of scale</li>
            <li>Consider input cost inflation</li>
            <li>Account for product mix shifts</li>
        </ul>
        
        <h4>Operating Expenses</h4>
        <ul>
            <li><strong>Variable Costs:</strong> Model as % of revenue</li>
            <li><strong>Fixed Costs:</strong> Model with inflation adjustments</li>
            <li><strong>Semi-Variable:</strong> Step function based on revenue tiers</li>
        </ul>
        
        <h3>Working Capital</h3>
        <code>
        NWC = Current Assets - Current Liabilities
        Change in NWC = NWC(t) - NWC(t-1)
        </code>
        
        <p>Model components as days metrics:</p>
        <ul>
            <li>Days Sales Outstanding (DSO)</li>
            <li>Days Inventory Outstanding (DIO)</li>
            <li>Days Payables Outstanding (DPO)</li>
        </ul>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 4: Free Cash Flow Calculation</h2>
        
        <h3>Unlevered FCF Formula</h3>
        <div class="sffc-formula-breakdown">
            <table>
                <tr><td>EBIT</td><td>$1,000</td></tr>
                <tr><td>Less: Taxes @ 25%</td><td>($250)</td></tr>
                <tr><td>NOPAT</td><td>$750</td></tr>
                <tr><td>Plus: D&A</td><td>$100</td></tr>
                <tr><td>Less: CapEx</td><td>($150)</td></tr>
                <tr><td>Less: Change in NWC</td><td>($50)</td></tr>
                <tr><td><strong>Unlevered FCF</strong></td><td><strong>$650</strong></td></tr>
            </table>
        </div>
        
        <h3>Terminal Value Calculation</h3>
        
        <h4>Gordon Growth Method</h4>
        <code>
        TV = FCF(n) × (1 + g) / (WACC - g)
        </code>
        
        <h4>Exit Multiple Method</h4>
        <code>
        TV = EBITDA(n) × Exit Multiple
        </code>
        
        <p><strong>Pro Tip:</strong> Always cross-check both methods and ensure implied metrics are reasonable.</p>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 5: WACC Calculation</h2>
        
        <h3>Cost of Equity (CAPM)</h3>
        <code>
        Re = Rf + β × (Rm - Rf)
        </code>
        
        <h3>Cost of Debt</h3>
        <code>
        Rd = Risk-free Rate + Credit Spread
        </code>
        
        <h3>WACC Formula</h3>
        <code>
        WACC = (E/V) × Re + (D/V) × Rd × (1 - Tax Rate)
        </code>
        
        <h3>Beta Calculation</h3>
        <ol>
            <li>Find comparable company betas</li>
            <li>Unlever each beta: βu = βl / [1 + (1-T) × (D/E)]</li>
            <li>Calculate median unlevered beta</li>
            <li>Relever for target: βl = βu × [1 + (1-T) × (D/E)]</li>
        </ol>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 6: Sensitivity Analysis</h2>
        
        <h3>Key Variables to Test</h3>
        <ul>
            <li>Revenue growth rates</li>
            <li>EBITDA margins</li>
            <li>Terminal growth rate</li>
            <li>WACC</li>
            <li>Exit multiples</li>
        </ul>
        
        <h3>Creating Data Tables</h3>
        <p>Build two-way sensitivity tables showing valuation across different assumption combinations.</p>
        
        <h3>Scenario Analysis</h3>
        <ul>
            <li><strong>Base Case:</strong> Most likely scenario</li>
            <li><strong>Upside Case:</strong> Optimistic assumptions</li>
            <li><strong>Downside Case:</strong> Conservative assumptions</li>
        </ul>
    </div>
    
    <div class="sffc-guide-section">
        <h2>Part 7: Common Pitfalls</h2>
        
        <ul>
            <li>Circular references in interest calculations</li>
            <li>Inconsistent mid-year convention application</li>
            <li>Double-counting or missing items in FCF</li>
            <li>Unrealistic terminal value assumptions</li>
            <li>Mismatched time periods in discounting</li>
            <li>Forgetting to add cash and subtract debt</li>
        </ul>
    </div>
</div>'
        ],
        // Add more modeling guides...
    ];
}
?>