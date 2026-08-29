<?php
/**
 * Contextual Engagement Buttons Generator
 * Provides dynamic, context-aware buttons to encourage user engagement
 * 
 * @package SennaCareers
 * @since 5.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Contextual_Engagement_Buttons {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Button variations to prevent repetition
     */
    private $button_variations = array();
    
    /**
     * Last used button set
     */
    private $last_button_set = '';
    
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
     * Constructor
     */
    private function __construct() {
        $this->init_button_variations();
    }
    
    /**
     * Initialize button variations
     */
    private function init_button_variations() {
        $this->button_variations = array(
            'market_conditions' => array(
                array(
                    'Analyze specific sector',
                    'Show PE/VC activity', 
                    'Compare to last quarter'
                ),
                array(
                    'Deep dive on trends',
                    'View deal pipeline',
                    'Explore opportunities'
                ),
                array(
                    'Focus on M&A activity',
                    'Review fund performance',
                    'Check market drivers'
                )
            ),
            
            'roles_jobs' => array(
                array(
                    'Match my profile',
                    'Show salary ranges',
                    'View requirements'
                ),
                array(
                    'Find similar roles',
                    'Compare compensation',
                    'See career paths'
                ),
                array(
                    'Filter by location',
                    'Show growth firms',
                    'Explore laterals'
                )
            ),
            
            'firms' => array(
                array(
                    'Show recent deals',
                    'View team info',
                    'Compare to peers'
                ),
                array(
                    'Analyze portfolio',
                    'Check hiring status',
                    'Review culture'
                ),
                array(
                    'See fund performance',
                    'View leadership',
                    'Track investments'
                )
            ),
            
            'compensation' => array(
                array(
                    'Show by seniority',
                    'Include carry details',
                    'Compare markets'
                ),
                array(
                    'View total comp',
                    'Show bonus ranges',
                    'Benchmark my role'
                ),
                array(
                    'Regional differences',
                    'Show progressions',
                    'Include benefits'
                )
            ),
            
            'skills' => array(
                array(
                    'Build learning path',
                    'Show prerequisites',
                    'Find resources'
                ),
                array(
                    'Create study plan',
                    'View timelines',
                    'Get practice cases'
                ),
                array(
                    'Focus on modeling',
                    'Learn valuation',
                    'Master LBO basics'
                )
            ),
            
            'interview' => array(
                array(
                    'Practice questions',
                    'Review frameworks',
                    'Get case examples'
                ),
                array(
                    'Mock interview',
                    'Check preparation',
                    'Common mistakes'
                ),
                array(
                    'Technical prep',
                    'Behavioral guide',
                    'Final checklist'
                )
            ),
            
            'general' => array(
                array(
                    'Tell me more',
                    'Show examples',
                    'Different angle'
                ),
                array(
                    'Provide details',
                    'Give specifics',
                    'Explore further'
                ),
                array(
                    'Break it down',
                    'Show data',
                    'Next steps'
                )
            )
        );
    }
    
    /**
     * Get contextual buttons for a query
     */
    public function get_contextual_buttons($query, $message_type = 'assistant', $context = array()) {
        $buttons = array();
        
        // Above message buttons (always the same for consistency)
        if ($message_type === 'assistant') {
            $buttons['above'] = array(
                array(
                    'text' => 'Clarify',
                    'action' => 'clarify',
                    'style' => 'subtle'
                ),
                array(
                    'text' => 'Ask MENA Careers',
                    'action' => 'new_question',
                    'style' => 'subtle'
                )
            );
        }
        
        // Below message buttons (contextual)
        $intent = $this->analyze_query_intent($query);
        $buttons['below'] = $this->get_varied_buttons($intent);
        
        // Mini engagement buttons for MENA Careers's messages
        if ($message_type === 'assistant') {
            $buttons['mini'] = $this->get_mini_engagement_buttons($intent);
        }
        
        return $buttons;
    }
    
    /**
     * Analyze query intent
     */
    private function analyze_query_intent($query) {
        $query_lower = strtolower($query);
        
        // Market conditions
        if (preg_match('/market|today|happening|conditions|outlook|trends|movement/i', $query)) {
            return 'market_conditions';
        }
        
        // Jobs and roles
        if (preg_match('/job|role|position|opportunity|hiring|recruit|lateral|associate|analyst|vp|principal/i', $query)) {
            return 'roles_jobs';
        }
        
        // Firms
        if (preg_match('/goldman|morgan|jpmorgan|blackstone|kkr|apollo|carlyle|firm|fund|portfolio/i', $query)) {
            return 'firms';
        }
        
        // Compensation
        if (preg_match('/salary|compensation|comp|pay|bonus|carry|package|earning/i', $query)) {
            return 'compensation';
        }
        
        // Skills
        if (preg_match('/skill|learn|model|lbo|dcf|valuation|analysis|excel|python/i', $query)) {
            return 'skills';
        }
        
        // Interview
        if (preg_match('/interview|prepare|case|question|behavioral|technical|assessment/i', $query)) {
            return 'interview';
        }
        
        return 'general';
    }
    
    /**
     * Get varied buttons to prevent repetition
     */
    private function get_varied_buttons($intent) {
        if (!isset($this->button_variations[$intent])) {
            $intent = 'general';
        }
        
        $variations = $this->button_variations[$intent];
        $total_variations = count($variations);
        
        // Pick a different set than last time
        $index = array_rand($variations);
        $key = $intent . '_' . $index;
        
        // If same as last time, pick next
        if ($key === $this->last_button_set && $total_variations > 1) {
            $index = ($index + 1) % $total_variations;
            $key = $intent . '_' . $index;
        }
        
        $this->last_button_set = $key;
        
        $button_texts = $variations[$index];
        $buttons = array();
        
        foreach ($button_texts as $text) {
            $buttons[] = array(
                'text' => $text,
                'action' => 'follow_up',
                'data' => array(
                    'query' => $text,
                    'context' => $intent
                ),
                'style' => 'primary'
            );
        }
        
        return $buttons;
    }
    
    /**
     * Get mini engagement buttons
     */
    private function get_mini_engagement_buttons($intent) {
        $mini_buttons = array();
        
        // Quick actions relevant to the context
        switch ($intent) {
            case 'market_conditions':
                $mini_buttons = array('📊', '🔍', '💡');
                break;
            case 'roles_jobs':
                $mini_buttons = array('🎯', '📋', '➡️');
                break;
            case 'firms':
                $mini_buttons = array('🏢', '📈', 'ℹ️');
                break;
            case 'compensation':
                $mini_buttons = array('💰', '📊', '⬆️');
                break;
            case 'skills':
                $mini_buttons = array('📚', '✅', '🎓');
                break;
            case 'interview':
                $mini_buttons = array('💪', '📝', '✓');
                break;
            default:
                $mini_buttons = array('👍', '💡', '→');
        }
        
        // Return as professional text alternatives (no emojis)
        return array(
            array('text' => 'Helpful', 'action' => 'positive_feedback'),
            array('text' => 'More', 'action' => 'expand'),
            array('text' => 'Next', 'action' => 'continue')
        );
    }
    
    /**
     * Generate button HTML
     */
    public function generate_button_html($buttons, $message_id) {
        $html = '';
        
        // Above message buttons
        if (isset($buttons['above']) && !empty($buttons['above'])) {
            $html .= '<div class="sffc-above-message-buttons">';
            foreach ($buttons['above'] as $button) {
                $html .= sprintf(
                    '<button class="sffc-engagement-btn sffc-btn-%s" data-action="%s" data-message-id="%s">%s</button>',
                    esc_attr($button['style']),
                    esc_attr($button['action']),
                    esc_attr($message_id),
                    esc_html($button['text'])
                );
            }
            $html .= '</div>';
        }
        
        // Below message buttons
        if (isset($buttons['below']) && !empty($buttons['below'])) {
            $html .= '<div class="sffc-below-message-buttons">';
            foreach ($buttons['below'] as $button) {
                $data_attrs = '';
                if (isset($button['data'])) {
                    foreach ($button['data'] as $key => $value) {
                        $data_attrs .= sprintf(' data-%s="%s"', esc_attr($key), esc_attr($value));
                    }
                }
                $html .= sprintf(
                    '<button class="sffc-engagement-btn sffc-btn-%s" data-action="%s" data-message-id="%s"%s>%s</button>',
                    esc_attr($button['style']),
                    esc_attr($button['action']),
                    esc_attr($message_id),
                    $data_attrs,
                    esc_html($button['text'])
                );
            }
            $html .= '</div>';
        }
        
        // Mini engagement buttons
        if (isset($buttons['mini']) && !empty($buttons['mini'])) {
            $html .= '<div class="sffc-mini-engagement-buttons">';
            foreach ($buttons['mini'] as $button) {
                $html .= sprintf(
                    '<button class="sffc-mini-btn" data-action="%s" data-message-id="%s" title="%s">%s</button>',
                    esc_attr($button['action']),
                    esc_attr($message_id),
                    esc_attr($button['text']),
                    esc_html($button['text'])
                );
            }
            $html .= '</div>';
        }
        
        return $html;
    }
}