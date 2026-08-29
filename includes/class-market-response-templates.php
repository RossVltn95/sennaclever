<?php
/**
 * Market Response Templates - MENA Careers's personality in Market Analysis Mode
 * 
 * @package SennaCareers
 * @subpackage Market
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Market_Response_Templates {
    
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
     * Market Mode Greetings - Time and context aware
     */
    public function get_market_greetings() {
        return array(
            'morning' => array(
                'general' => array(
                    "Good morning! Let me show you what moved in the markets overnight and why it matters for your career.",
                    "Morning! The markets have been busy while you slept. Here's what you need to know and why...",
                    "Good morning! Some fascinating developments overnight. Let me break down what's really happening...",
                    "Morning! Ready to understand what's driving today's markets? Let's dive into the why behind the moves...",
                    "Good morning! The overnight action tells an interesting story. Let me connect the dots for you..."
                ),
                'volatile' => array(
                    "Morning! The markets are showing significant volatility. Let me explain what's causing this and how to position...",
                    "Good morning! We're seeing some dramatic moves. Here's the real story behind the headlines...",
                    "Morning! Volatility creates opportunity. Let me show you what's driving these swings and where the smart money is going..."
                ),
                'calm' => array(
                    "Good morning! Markets are relatively calm, but there's important developments beneath the surface...",
                    "Morning! While markets appear quiet, there are subtle shifts worth understanding...",
                    "Good morning! The calm surface hides interesting dynamics. Let me show you what's really happening..."
                )
            ),
            'afternoon' => array(
                'general' => array(
                    "Good afternoon! The market's showing its hand. Let me explain what these moves really mean...",
                    "Afternoon! Perfect timing to understand today's market dynamics and their implications...",
                    "Good afternoon! The day's patterns are becoming clear. Here's what they're telling us...",
                    "Afternoon! Let's decode what the market's been saying today and what it means for opportunities..."
                ),
                'trending' => array(
                    "Afternoon! A clear trend is emerging. Let me explain the forces behind it...",
                    "Good afternoon! The market's momentum has a story to tell. Here's what's driving it...",
                    "Afternoon! Today's trend isn't random. Let me show you the mechanics behind these moves..."
                )
            ),
            'evening' => array(
                'general' => array(
                    "Good evening! Let's make sense of today's market action and what it sets up for tomorrow...",
                    "Evening! Time to understand what really happened in markets today and why...",
                    "Good evening! Today's close tells us something important. Let me break it down...",
                    "Evening! Perfect time to analyze today's developments and their longer-term implications..."
                ),
                'post_close' => array(
                    "Evening! With markets closed, let's dig into what today's action really meant...",
                    "Good evening! Now we can properly analyze today's moves without the noise...",
                    "Evening! The dust has settled. Here's what today's market action was really about..."
                )
            ),
            'night' => array(
                'general' => array(
                    "Working late? Let's dive deep into market dynamics when it's quiet...",
                    "Late night analysis session? Perfect time to really understand market mechanics...",
                    "Burning the midnight oil? Let me help you understand what's moving markets...",
                    "Late night learning? Great time to build deep market understanding..."
                )
            )
        );
    }
    
    /**
     * WHY Analysis Response Templates
     */
    public function get_why_templates() {
        return array(
            'introduction' => array(
                "Excellent question! Let me peel back the layers on this market move...",
                "You're asking exactly the right question. Here's what's really happening beneath the surface...",
                "That's the million-dollar question. Let me show you the chain of causality here...",
                "Smart to dig deeper. The real story is more interesting than the headlines suggest...",
                "You're thinking like a pro. Let me walk you through the actual mechanics driving this..."
            ),
            
            'surface_level' => array(
                "On the surface, everyone sees [EVENT]. But that's just the trigger, not the cause...",
                "The headlines say [EVENT], which is true but incomplete. Here's what they're missing...",
                "Most people stop at [OBVIOUS_FACT]. Let me show you what's actually driving this...",
                "The market reacted to [TRIGGER], but that's just the tip of the iceberg..."
            ),
            
            'deeper_dive' => array(
                "But here's what's really happening: [MECHANISM]. This creates a chain reaction where...",
                "The real driver is [ROOT_CAUSE]. This matters because it fundamentally changes...",
                "Digging deeper, we find [HIDDEN_FACTOR]. This is crucial because...",
                "What most miss is [KEY_INSIGHT]. This explains why we're seeing..."
            ),
            
            'connecting_dots' => array(
                "Connect this to [RELATED_EVENT] and the picture becomes clear...",
                "This links directly to [BROADER_THEME], which explains why...",
                "Notice how this correlates with [OTHER_MARKET]? That's because...",
                "The connection most miss is with [UNEXPECTED_LINK]. This reveals..."
            ),
            
            'implications' => array(
                "For PE professionals, this means [PE_IMPACT] because...",
                "If you're in IB, watch for [IB_OPPORTUNITY] as firms will need...",
                "Hedge funds are already positioning for [HF_STRATEGY] based on this...",
                "This creates a career opportunity in [AREA] as demand for [SKILL] will spike..."
            )
        );
    }
    
    /**
     * Market Education Templates
     */
    public function get_education_templates() {
        return array(
            'concept_intro' => array(
                "Let me break down [CONCEPT] using what's happening right now in markets...",
                "Perfect teaching moment! [CONCEPT] is exactly what we're seeing play out...",
                "[CONCEPT] might sound complex, but today's market gives us a perfect example...",
                "This is a textbook case of [CONCEPT]. Let me show you how it works in practice..."
            ),
            
            'analogies' => array(
                "Think of it like [EVERYDAY_ANALOGY]. In markets, this means...",
                "Imagine [SIMPLE_SCENARIO]. That's essentially what's happening with...",
                "It's similar to [RELATABLE_EXAMPLE], except in finance...",
                "Picture [VISUAL_ANALOGY]. Now apply that to markets and you get..."
            ),
            
            'mechanics' => array(
                "Here's how it actually works: Step 1: [STEP]. This causes Step 2: [STEP]...",
                "The mechanism is fascinating: First [ACTION], which triggers [REACTION]...",
                "Let me walk you through the process: [DETAILED_EXPLANATION]",
                "The sequence goes like this: [CAUSE] → [EFFECT] → [CONSEQUENCE]..."
            ),
            
            'professional_context' => array(
                "In PE, professionals use this to [PE_APPLICATION]...",
                "IB analysts model this by [IB_METHOD]...",
                "Traders exploit this through [TRADING_STRATEGY]...",
                "This is why firms hire people who understand [SKILL_AREA]..."
            ),
            
            'knowledge_check' => array(
                "Quick check: If [SCENARIO], what would you expect to happen? Think about it...",
                "Test your understanding: Why would [OBSERVATION] lead to [OUTCOME]?",
                "Here's how you know you get it: You can explain why [CAUSE] creates [EFFECT]...",
                "Interview question alert: 'Explain how [CONCEPT] affected [RECENT_EVENT]'..."
            )
        );
    }
    
    /**
     * Comparison Templates
     */
    public function get_comparison_templates() {
        return array(
            'introduction' => array(
                "Let's put [ENTITY1] and [ENTITY2] side by side and see what the differences reveal...",
                "Interesting comparison! The contrast between [ENTITY1] and [ENTITY2] tells us a lot about...",
                "Great question. The [ENTITY1] vs [ENTITY2] dynamic is fascinating because...",
                "[ENTITY1] and [ENTITY2] represent different approaches to [THEME]. Here's how they stack up..."
            ),
            
            'metrics' => array(
                "By the numbers: [ENTITY1] shows [METRICS] while [ENTITY2] has [METRICS]. This gap exists because...",
                "The quantitative story: [ENTITY1] at [VALUE] versus [ENTITY2] at [VALUE]. But context matters...",
                "Performance divergence: [COMPARISON]. The driver behind this is...",
                "Key metrics reveal: [DATA_POINTS]. This tells us that..."
            ),
            
            'qualitative' => array(
                "Beyond numbers, [ENTITY1]'s strength is [STRENGTH] while [ENTITY2] excels at [STRENGTH]...",
                "Strategically, [ENTITY1] is positioning for [STRATEGY] whereas [ENTITY2] bets on [STRATEGY]...",
                "The real difference is philosophy: [ENTITY1] believes [VIEW] but [ENTITY2] thinks [VIEW]...",
                "Culture matters here: [ENTITY1]'s approach reflects [CULTURE] while [ENTITY2] embraces [CULTURE]..."
            ),
            
            'verdict' => array(
                "Long-term winner? [ENTITY] because [REASONING]. But watch for [RISK]...",
                "For career purposes, [ENTITY] offers [ADVANTAGE] though [ENTITY2] provides [DIFFERENT_ADVANTAGE]...",
                "The smart money is on [ENTITY] but contrarians might prefer [ENTITY2] if [CONDITION]...",
                "No clear winner, but [ENTITY] has the edge in [AREA] while [ENTITY2] dominates [AREA]..."
            )
        );
    }
    
    /**
     * Opportunity Spotting Templates
     */
    public function get_opportunity_templates() {
        return array(
            'identification' => array(
                "I'm seeing an opportunity forming in [AREA]. Here's why this is actionable...",
                "While everyone focuses on [OBVIOUS], there's a hidden opportunity in [HIDDEN]...",
                "This creates a clear opportunity for [ACTION]. The window is [TIMEFRAME] because...",
                "Smart money is quietly positioning for [OPPORTUNITY]. Here's how you can too..."
            ),
            
            'career_opportunities' => array(
                "[FIRM] will likely need [SKILL] given this development. Perfect time to...",
                "This market shift makes [ROLE] suddenly valuable. Companies will be looking for...",
                "If you have [EXPERIENCE], this is your moment because [REASONING]...",
                "Watch for hiring in [AREA] as firms scramble to address [NEED]..."
            ),
            
            'trading_opportunities' => array(
                "The setup: [CONDITION] creates [OPPORTUNITY]. Entry at [LEVEL], stop at [LEVEL]...",
                "Risk/reward favors [POSITION] here. The catalyst is [EVENT] and downside is limited by...",
                "Pairs trade opportunity: Long [ASSET1] / Short [ASSET2] because [REASONING]...",
                "Volatility play: [STRATEGY] makes sense given [CONDITION]..."
            ),
            
            'learning_opportunities' => array(
                "This is the perfect time to master [SKILL] while it's playing out in real-time...",
                "If you understand [CONCEPT] now, you'll have an edge when [FUTURE_EVENT]...",
                "Study [EXAMPLE] closely. This pattern will repeat and next time you'll be ready...",
                "Document this for your portfolio. Being able to explain [EVENT] shows [COMPETENCY]..."
            )
        );
    }
    
    /**
     * Market Psychology Templates
     */
    public function get_psychology_templates() {
        return array(
            'sentiment_reading' => array(
                "Market psychology has shifted to [STATE]. You can see this in [INDICATORS]...",
                "We're in the [STAGE] stage of market sentiment. This typically means...",
                "The crowd is [BEHAVIOR], which historically suggests [OUTCOME]...",
                "Fear and greed are battling it out, with [EMOTION] currently winning because..."
            ),
            
            'contrarian' => array(
                "Everyone's convinced [CONSENSUS]. That's exactly why [CONTRARIAN] might work...",
                "The crowded trade is [POSITION]. Consider the opposite because [REASONING]...",
                "When everyone agrees on [VIEW], it's time to question [ASSUMPTION]...",
                "The pain trade is [DIRECTION]. Markets love to hurt the most people possible..."
            ),
            
            'behavioral' => array(
                "Classic case of [BIAS] bias. Investors are [BEHAVIOR] when they should be [CORRECT_BEHAVIOR]...",
                "The market's showing [PSYCHOLOGICAL_PATTERN]. This usually resolves with [OUTCOME]...",
                "Recency bias is making people forget [IMPORTANT_FACT]. This creates opportunity in...",
                "Anchoring to [LEVEL] is preventing traders from seeing [REALITY]..."
            )
        );
    }
    
    /**
     * Crisis Response Templates
     */
    public function get_crisis_templates() {
        return array(
            'assessment' => array(
                "This is a [SEVERITY] situation. Not 2008, but significant because [REASONING]...",
                "We're seeing [TYPE] crisis dynamics. The key difference from [PAST_CRISIS] is...",
                "Contagion risk is [LEVEL]. The transmission mechanism is [PATHWAY]...",
                "This could escalate if [CONDITION], but [FACTOR] should provide a floor..."
            ),
            
            'action_items' => array(
                "Immediate priorities: 1) [ACTION] 2) [ACTION] 3) [ACTION]. Here's why each matters...",
                "If you're in [ROLE], focus on [PRIORITY] as [REASONING]...",
                "Quality will outperform. Look for [CHARACTERISTICS] and avoid [RISKS]...",
                "This is when career reputations are made. Step up by [ACTION]..."
            ),
            
            'opportunity_in_crisis' => array(
                "Crises create opportunities. Watch for [OPPORTUNITY] as [REASONING]...",
                "While others panic, consider [CONTRARIAN_ACTION] because [LOGIC]...",
                "Distressed situations emerging in [AREA]. If you have [SKILL], this is valuable...",
                "The recovery play will be [STRATEGY]. Position for it by [ACTION]..."
            )
        );
    }
    
    /**
     * Follow-up and Engagement Templates
     */
    public function get_engagement_templates() {
        return array(
            'follow_up_questions' => array(
                "Want me to dig deeper into how this affects [SPECIFIC_AREA]?",
                "Should we explore what this means for [RELATED_TOPIC]?",
                "Curious about the [ASPECT]? I can break that down further...",
                "Would you like to understand the [TECHNICAL_DETAIL] behind this?"
            ),
            
            'encouraging' => array(
                "Great question - you're thinking about this the right way.",
                "Exactly the kind of analysis that separates professionals from amateurs.",
                "You're connecting dots most people miss. Let's go deeper...",
                "That insight shows you understand the mechanics. Building on that..."
            ),
            
            'knowledge_building' => array(
                "Let's test your understanding: What would happen if [SCENARIO]?",
                "Based on what we discussed, how would you position for [EVENT]?",
                "Quick exercise: Explain this to someone outside finance. How would you do it?",
                "Interview prep: How would you answer 'What caused [EVENT] and why does it matter?'"
            ),
            
            'closing' => array(
                "The key takeaway: [SUMMARY]. Watch for [INDICATOR] as confirmation.",
                "Remember: [PRINCIPLE]. This will serve you well in understanding markets.",
                "Action items: 1) [ACTION] 2) [MONITOR] 3) [LEARN]. Let me know what you discover.",
                "This pattern will repeat. When you see [SIGNAL], remember what we discussed today."
            )
        );
    }
    
    /**
     * Personality Traits and Variations
     */
    public function get_personality_modifiers() {
        return array(
            'confident_insights' => array(
                "I've seen this pattern before, and here's what typically happens next...",
                "Based on years of market observation, this is significant because...",
                "The data strongly suggests [OUTCOME]. Here's my conviction level and why...",
                "This is one of those moments where the opportunity is clear if you know where to look..."
            ),
            
            'humble_uncertainty' => array(
                "Markets can surprise us, but the probability favors [OUTCOME] because...",
                "I could be wrong, but the evidence points to [CONCLUSION]...",
                "There are multiple scenarios here. Let me walk through the most likely...",
                "Historical patterns suggest [OUTCOME], though this time could be different because..."
            ),
            
            'teaching_moments' => array(
                "This is a perfect learning opportunity. Notice how [OBSERVATION]...",
                "File this away for your toolkit: [LESSON]...",
                "Career tip: Being able to explain this will set you apart because...",
                "This is interview gold. Here's how to talk about it professionally..."
            ),
            
            'market_wisdom' => array(
                "Old market saying: '[SAYING]'. Today's action proves why...",
                "Remember: Markets can remain [STATE] longer than you can remain [STATE]...",
                "The market is a voting machine short-term but a weighing machine long-term...",
                "As they say on trading floors: '[EXPRESSION]'. That applies here because..."
            )
        );
    }
    
    /**
     * Get random template from category
     */
    public function get_random_template($category, $subcategory = null) {
        $templates = $this->{"get_{$category}_templates"}();
        
        if ($subcategory && isset($templates[$subcategory])) {
            $options = $templates[$subcategory];
        } else {
            // Flatten all subcategories
            $options = array();
            foreach ($templates as $sub_templates) {
                if (is_array($sub_templates)) {
                    $options = array_merge($options, $sub_templates);
                }
            }
        }
        
        return $options[array_rand($options)];
    }
    
    /**
     * Build complete response from templates
     */
    public function build_response($components) {
        $response = array();
        
        foreach ($components as $component) {
            $template = $this->get_random_template(
                $component['category'],
                $component['subcategory'] ?? null
            );
            
            // Replace placeholders if provided
            if (isset($component['replacements'])) {
                foreach ($component['replacements'] as $placeholder => $value) {
                    $template = str_replace("[{$placeholder}]", $value, $template);
                }
            }
            
            $response[] = $template;
        }
        
        return implode("\n\n", $response);
    }
}