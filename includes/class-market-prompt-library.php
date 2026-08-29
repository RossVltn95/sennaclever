<?php
/**
 * Market Prompt Library - Comprehensive Claude prompts for market analysis
 * 
 * @package SennaCareers
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Prompt_Library {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Base system prompt for Market Analysis Mode
     */
    public function get_base_system_prompt() {
        return "You are MENA Careers, an elite financial markets analyst and career advisor for senna. You specialize in explaining WHY market events happen, not just reporting what happened. Your expertise spans private equity, investment banking, hedge funds, and capital markets.

Core traits:
- You explain complex market dynamics in clear, accessible ways
- You always connect market events to career implications
- You teach through current events, building users' understanding
- You identify opportunities others miss
- You think in cause-and-effect chains
- You understand market microstructure and participant behavior

Your analysis framework:
1. Surface level: What everyone sees
2. Mechanism: How markets actually work
3. Psychology: What traders and investors are thinking
4. Structure: Deep market plumbing
5. Strategic: Long-term implications and opportunities

You NEVER just report news. You ALWAYS explain:
- WHY it happened (root causes)
- HOW it affects markets (mechanisms)
- WHAT it means for careers (opportunities)
- WHERE it leads next (predictions)

Remember: Users can get news anywhere. They come to you to UNDERSTAND markets.";
    }
    
    /**
     * WHY Analysis Prompts
     */
    public function get_why_analysis_prompt($event, $context) {
        $event_desc = $event['title'] . ' - ' . $event['description'];
        
        return "Analyze this market event with deep WHY analysis:

Event: {$event_desc}

Provide a comprehensive analysis following this structure:

1. IMMEDIATE TRIGGER
- What is the surface-level catalyst?
- What are the obvious market reactions?
- What are retail investors seeing?

2. ROOT CAUSES (The Real WHY)
- What fundamental factors drove this?
- What's been building up to cause this?
- What are the hidden drivers most miss?

3. CAUSALITY CHAIN
Build a complete cause-and-effect chain:
- Initial trigger → 
- First-order effects (0-24 hours) →
- Second-order effects (1-7 days) →
- Third-order effects (weeks) →
- Ultimate outcome (months)

4. MARKET MECHANICS
- How is price discovery happening?
- What are the liquidity dynamics?
- How are different participant types reacting?
  * Institutional investors
  * Retail traders
  * Algorithmic systems
  * Market makers

5. PSYCHOLOGICAL FACTORS
- What sentiment shift is occurring?
- What behavioral biases are at play?
- Where is the crowd wrong?

6. STRUCTURAL IMPLICATIONS
- How does this change market structure?
- What correlations are breaking/forming?
- What regime change might this signal?

7. CAREER IMPACT
For each role, explain:
- PE Associate: How does this affect deal flow and valuations?
- IB Analyst: What advisory opportunities emerge?
- HF Analyst: What trading strategies become viable?
- Corp Dev: How does this change strategic planning?

8. CONTRARIAN VIEW
- What's the consensus missing?
- What's the opposite bet and why might it work?
- What would make the market wrong?

9. HISTORICAL ANALOG
- When has something similar happened?
- What can we learn from that precedent?
- How is this time different?

10. ACTIONABLE INSIGHTS
- Specific opportunities to pursue
- Risks to hedge against
- Skills to develop based on this

Provide specific numbers, examples, and avoid generic statements. Connect everything back to career advancement opportunities.";
    }
    
    /**
     * Market Education Prompts
     */
    public function get_education_prompt($topic, $current_event) {
        return "Teach the concept of '{$topic}' using this current market event: {$current_event}

Structure your explanation as:

1. THE CONCEPT IN SIMPLE TERMS
- Explain like they're smart but not a finance expert
- Use analogies from everyday life
- Build from first principles

2. WHY IT MATTERS RIGHT NOW
- Connect directly to current event
- Show real-world impact
- Explain why professionals care

3. THE MECHANICS
- Step-by-step how it works
- Key formulas or relationships (simplified)
- Common misconceptions corrected

4. REAL EXAMPLES
- Current market example (the event)
- Historical example for context
- Hypothetical scenario for clarity

5. PROFESSIONAL APPLICATION
- How PE professionals use this
- How IB analysts think about it
- How traders exploit it

6. CAREER ADVANTAGE
- Interview questions about this
- Skills to demonstrate understanding
- Projects to showcase expertise

7. DEEPER LEARNING PATH
- Next concepts to explore
- Resources for mastery
- Practical exercises

Make it engaging and memorable. Use specific numbers and avoid jargon without explanation.";
    }
    
    /**
     * Market Comparison Prompts
     */
    public function get_comparison_prompt($entities, $context) {
        $entities_str = implode(' vs ', $entities);
        
        return "Provide a detailed comparison of: {$entities_str}

Structure the comparison as:

1. KEY METRICS COMPARISON
- Performance (with specific numbers)
- Valuation metrics
- Risk metrics
- Operational metrics

2. STRATEGIC POSITIONING
- Business model differences
- Competitive advantages
- Market positioning
- Growth strategies

3. WHY THE DIFFERENCES EXIST
- Historical factors
- Management decisions
- Market dynamics
- Structural advantages/disadvantages

4. MARKET PERCEPTION
- How institutional investors view each
- Analyst consensus
- Retail sentiment
- Options flow insights

5. FORWARD-LOOKING ANALYSIS
- Next 6 months outlook
- Key catalysts for each
- Risks specific to each
- Potential surprises

6. CAREER RELEVANCE
- Which offers better experience for analysts
- Deal flow implications
- Learning opportunities
- Network value

7. INVESTMENT/TRADING ANGLE
- Pair trade opportunities
- Relative value analysis
- Risk/reward comparison
- Timing considerations

8. WINNER PREDICTION
- Who wins long-term and why
- Conditions that would change this
- Key metrics to watch

Provide specific, quantified insights. Avoid generic comparisons.";
    }
    
    /**
     * Opportunity Detection Prompts
     */
    public function get_opportunity_prompt($market_conditions, $user_profile) {
        return "Given current market conditions and user profile, identify specific opportunities:

Market Conditions:
{$market_conditions}

User Profile:
{$user_profile}

Identify opportunities across:

1. IMMEDIATE TRADING OPPORTUNITIES
- Specific trades with entry/exit
- Risk/reward analysis
- Time horizon
- Position sizing thoughts

2. CAREER OPPORTUNITIES
- Firms likely hiring due to this
- Roles becoming more valuable
- Skills suddenly in demand
- Network connections to make

3. LEARNING OPPORTUNITIES
- Concepts to master now
- Certifications becoming valuable
- Projects to demonstrate understanding
- Mentors to seek

4. STRATEGIC POSITIONING
- Sector rotations to exploit
- Geographic arbitrage
- Asset class opportunities
- Thematic investments

5. CONTRARIAN OPPORTUNITIES
- What everyone's missing
- Overcrowded trades to fade
- Neglected areas with potential
- Timing disconnects

6. RISK MANAGEMENT
- Hedges to implement
- Exposures to reduce
- Correlations to watch
- Tail risks to consider

For each opportunity provide:
- Specific action steps
- Success metrics
- Risk factors
- Time sensitivity

Focus on actionable, specific opportunities not generic advice.";
    }
    
    /**
     * Real-time Market Narrative Prompts
     */
    public function get_market_narrative_prompt($events, $timeframe) {
        return "Create a coherent market narrative from these events:

Events:
" . json_encode($events) . "

Timeframe: {$timeframe}

Build a narrative that:

1. IDENTIFIES THE BIG PICTURE
- What's the overarching theme?
- How do these events connect?
- What's the market trying to tell us?

2. CONNECTS THE DOTS
- Show how Event A influences Event B
- Identify feedback loops
- Spot divergences and convergences

3. REVEALS HIDDEN STORIES
- What's happening beneath the surface?
- Which narrative is wrong?
- What are smart money flows indicating?

4. EXPLAINS MARKET BEHAVIOR
- Why are markets reacting this way?
- What's driving correlations?
- Where's the pain trade?

5. PREDICTS NEXT MOVES
- What happens next and why?
- Key levels and triggers
- Potential catalysts ahead

6. IDENTIFIES REGIME
- What market regime are we in?
- Is it changing?
- Historical parallels

7. CAREER IMPLICATIONS
- How should professionals position?
- Skills becoming critical
- Opportunities emerging

8. ACTIONABLE TAKEAWAYS
- Top 3 things to do now
- Top 3 things to watch
- Top 3 risks to hedge

Create a compelling, insightful narrative that teaches while informing.";
    }
    
    /**
     * Knowledge Building Prompts
     */
    public function get_knowledge_prompt($user_level, $topic, $context) {
        return "Build user's knowledge progressively on '{$topic}' considering their level: {$user_level}

Create a learning sequence:

1. ASSESS CURRENT UNDERSTANDING
- Quick diagnostic question
- Identify knowledge gaps
- Understand their context

2. BUILD FOUNDATION
- Core concepts they must know
- Common misconceptions to correct
- Mental models to develop

3. CONNECT TO CURRENT EVENTS
- Real examples from today's markets
- Why this matters now
- How professionals think about it

4. PROGRESSIVE COMPLEXITY
- Layer 1: Basic mechanism
- Layer 2: Nuances and exceptions
- Layer 3: Advanced applications
- Layer 4: Edge cases and expertise

5. PRACTICAL APPLICATION
- Hands-on exercise
- Real scenario to analyze
- Decision framework to apply

6. KNOWLEDGE VALIDATION
- Questions to test understanding
- Common pitfalls to avoid
- Success indicators

7. NEXT STEPS
- What to learn next
- Resources for deep dive
- Projects to solidify knowledge

8. PROFESSIONAL CONTEXT
- How this knowledge translates to job performance
- Interview applications
- Career advancement impact

Adapt complexity to user level. Use Socratic method where appropriate.";
    }
    
    /**
     * Crisis Analysis Prompts
     */
    public function get_crisis_prompt($crisis_event) {
        return "Analyze this crisis/significant market event with framework for extreme situations:

Event: {$crisis_event}

Provide crisis-specific analysis:

1. CRISIS DYNAMICS
- Speed of contagion
- Feedback loops accelerating it
- Breaking points in system

2. PARTICIPANT BEHAVIOR IN CRISIS
- Forced selling dynamics
- Margin calls and deleveraging
- Flight to quality patterns
- Central bank responses

3. LIQUIDITY BREAKDOWN
- Where liquidity disappears first
- Correlation goes to 1 scenarios
- Funding market stress
- Counterparty risk concerns

4. OPPORTUNITY IN CHAOS
- Distressed opportunities emerging
- Quality assets on sale
- Volatility strategies
- Mean reversion setups

5. RISK MANAGEMENT CRITICAL
- Positions to exit immediately
- Hedges that actually work
- Correlation assumptions breaking
- Tail risk materialization

6. CAREER DURING CRISIS
- Roles becoming critical
- Skills that matter in crisis
- How to stand out positively
- Network importance

7. RECOVERY PATTERNS
- Historical crisis recoveries
- Leading indicators of bottom
- First movers advantage
- Positioning for recovery

8. LESSONS FOR LONG-TERM
- Permanent changes from this
- New regulations coming
- Behavioral shifts
- Structural adaptations

Provide specific, actionable guidance for navigating crisis.";
    }
    
    /**
     * Earnings Analysis Prompts
     */
    public function get_earnings_prompt($company, $earnings_data) {
        return "Analyze {$company} earnings with focus on WHY, not just what:

Earnings Data:
{$earnings_data}

Analyze:

1. BEAT/MISS BREAKDOWN
- Revenue drivers behind numbers
- Margin story and sustainability
- Quality of earnings assessment
- One-time vs recurring items

2. GUIDANCE DECODING
- What management really said
- Reading between the lines
- Sandbagging or aggressive?
- Macro assumptions embedded

3. MARKET REACTION EXPLANATION
- Why stock moved as it did
- Positioning before earnings
- Options flow influence
- Algo reaction vs fundamental

4. COMPETITIVE IMPLICATIONS
- What this means for sector
- Peer companies affected how
- Market share dynamics
- Industry trends revealed

5. FORWARD ANALYSIS
- Next quarter setup
- Key metrics to watch
- Catalyst calendar
- Risk factors emerging

6. CAREER ANGLES
- What analysts focus on
- Questions for earnings calls
- Modeling implications
- Research note structure

Provide specific insights beyond surface numbers.";
    }
    
    /**
     * Generate prompt based on context
     */
    public function get_contextual_prompt($query_type, $parameters) {
        switch ($query_type) {
            case 'why':
                return $this->get_why_analysis_prompt(
                    $parameters['event'],
                    $parameters['context']
                );
                
            case 'education':
                return $this->get_education_prompt(
                    $parameters['topic'],
                    $parameters['current_event']
                );
                
            case 'comparison':
                return $this->get_comparison_prompt(
                    $parameters['entities'],
                    $parameters['context']
                );
                
            case 'opportunity':
                return $this->get_opportunity_prompt(
                    $parameters['market_conditions'],
                    $parameters['user_profile']
                );
                
            case 'narrative':
                return $this->get_market_narrative_prompt(
                    $parameters['events'],
                    $parameters['timeframe']
                );
                
            case 'knowledge':
                return $this->get_knowledge_prompt(
                    $parameters['user_level'],
                    $parameters['topic'],
                    $parameters['context']
                );
                
            case 'crisis':
                return $this->get_crisis_prompt(
                    $parameters['crisis_event']
                );
                
            case 'earnings':
                return $this->get_earnings_prompt(
                    $parameters['company'],
                    $parameters['earnings_data']
                );
                
            default:
                return $this->get_base_system_prompt();
        }
    }
}