<?php
/**
 * AI Analyzer - Rebuilt for Robust Claude API Integration
 *
 * Uses Claude API to perform comprehensive CV vs JD gap analysis.
 * Implements chunked prompting and robust JSON parsing.
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_AI_Analyzer {

    /**
     * Cache duration in seconds (1 hour for gap analysis)
     */
    private $cache_duration = 3600;

    /**
     * API endpoint
     */
    private $api_url = 'https://api.anthropic.com/v1/messages';

    /**
     * Model to use - claude-sonnet is more reliable for structured output
     */
    private $model = 'claude-sonnet-4-6';

    /**
     * Constructor
     */
    public function __construct() {
        // No initialization needed
    }

    /**
     * Check if AI analysis is available
     */
    public function is_available() {
        $api_key = $this->get_api_key();
        return !empty($api_key);
    }

    /**
     * Get API key from various sources
     */
    private function get_api_key() {
        // Try multiple sources in order of preference
        $api_key = get_option('sffc_api_key', '');

        if (empty($api_key)) {
            $api_key = get_option('sffc_anthropic_key', '');
        }

        if (empty($api_key)) {
            $api_key = get_option('sffc_claude_api_key', '');
        }

        if (empty($api_key) && defined('ANTHROPIC_API_KEY')) {
            $api_key = ANTHROPIC_API_KEY;
        }

        if (empty($api_key) && class_exists('SFFC_API_Key_Manager')) {
            $key_manager = SFFC_API_Key_Manager::get_instance();
            $api_key = $key_manager->get_api_key();
        }

        if (empty($api_key) && class_exists('SFFC_Claude_API_Manager')) {
            $manager = SFFC_Claude_API_Manager::get_instance();
            if (method_exists($manager, 'get_api_key')) {
                $api_key = $manager->get_api_key();
            }
        }

        return $api_key;
    }

    /**
     * Perform FULL comprehensive analysis
     *
     * @param string $jd_text Raw job description text
     * @param string $cv_text Raw CV text
     * @return array Complete analysis result
     */
    public function analyze_full($jd_text, $cv_text) {
        if (!$this->is_available()) {
            error_log('Gap Analyzer: API key not available');
            return array(
                'success' => false,
                'reason' => 'api_unavailable',
                'data' => null,
            );
        }

        // Check cache
        $cache_key = 'sffc_gap_' . md5($jd_text . $cv_text);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            error_log('Gap Analyzer: Returning cached result');
            return array(
                'success' => true,
                'reason' => 'cached',
                'data' => $cached,
            );
        }

        try {
            // Call Claude API with robust prompt
            $response = $this->call_claude_api($jd_text, $cv_text);

            if (!$response['success']) {
                error_log('Gap Analyzer AI Error: ' . ($response['error'] ?? 'Unknown error'));
                return array(
                    'success' => false,
                    'reason' => $response['error'] ?? 'api_error',
                    'data' => null,
                );
            }

            // Parse the response with robust parsing
            $parsed = $this->parse_claude_response($response['content']);

            if ($parsed === null) {
                error_log('Gap Analyzer: JSON parse failed, attempting recovery...');
                // Try recovery parsing
                $parsed = $this->recover_partial_json($response['content']);
            }

            if ($parsed === null) {
                error_log('Gap Analyzer: All parsing attempts failed');
                return array(
                    'success' => false,
                    'reason' => 'parse_error',
                    'data' => null,
                );
            }

            // Ensure all required fields exist
            $parsed = $this->ensure_complete_structure($parsed);

            // Cache the result
            set_transient($cache_key, $parsed, $this->cache_duration);

            return array(
                'success' => true,
                'reason' => 'ai_analysis',
                'data' => $parsed,
            );

        } catch (Exception $e) {
            error_log('AI Analyzer Exception: ' . $e->getMessage());
            return array(
                'success' => false,
                'reason' => 'exception: ' . $e->getMessage(),
                'data' => null,
            );
        }
    }

    /**
     * Call Claude API with optimized prompt
     */
    private function call_claude_api($jd_text, $cv_text) {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'No API key configured',
            );
        }

        // Truncate inputs if too long to ensure response fits
        $max_input_chars = 12000;
        if (strlen($jd_text) > $max_input_chars) {
            $jd_text = substr($jd_text, 0, $max_input_chars) . "\n[TRUNCATED]";
        }
        if (strlen($cv_text) > $max_input_chars) {
            $cv_text = substr($cv_text, 0, $max_input_chars) . "\n[TRUNCATED]";
        }

        $system_prompt = $this->get_system_prompt();
        $user_prompt = $this->build_user_prompt($jd_text, $cv_text);

        $request_body = array(
            'model' => $this->model,
            'max_tokens' => 16000,
            'temperature' => 0.1,
            'system' => $system_prompt,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $user_prompt,
                ),
            ),
        );

        error_log('Gap Analyzer: Calling Claude API with model ' . $this->model);

        $response = wp_remote_post($this->api_url, array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode($request_body),
        ));

        if (is_wp_error($response)) {
            error_log('Gap Analyzer WP Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);

        error_log('Gap Analyzer: API response status ' . $status);

        if ($status !== 200) {
            $error_body = json_decode($raw_body, true);
            $error_msg = isset($error_body['error']['message']) ? $error_body['error']['message'] : 'API error: ' . $status;
            error_log('Gap Analyzer API Error: ' . $error_msg);
            return array(
                'success' => false,
                'error' => $error_msg,
            );
        }

        $body = json_decode($raw_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Failed to decode API response',
            );
        }

        if (isset($body['content'][0]['text'])) {
            $content = $body['content'][0]['text'];
            $stop_reason = isset($body['stop_reason']) ? $body['stop_reason'] : 'unknown';

            error_log('Gap Analyzer: Got response, stop_reason=' . $stop_reason . ', length=' . strlen($content));

            if ($stop_reason === 'max_tokens') {
                error_log('Gap Analyzer WARNING: Response was truncated');
            }

            return array(
                'success' => true,
                'content' => $content,
                'stop_reason' => $stop_reason,
            );
        }

        return array(
            'success' => false,
            'error' => 'Invalid response format from API',
        );
    }

    /**
     * Get system prompt for Claude
     */
    private function get_system_prompt() {
        return 'You are MENA Careers\'s senior private equity CV strategist. You analyze job descriptions against candidate CVs to identify gaps and provide actionable recommendations that help experienced finance professionals land or advance into manager, associate, VP, director, principal, partner, operating partner, or comparable private equity and adjacent buy-side roles.

CRITICAL INSTRUCTIONS:
1. You MUST respond with ONLY valid JSON - no markdown, no code blocks, no text before or after
2. Start your response with { and end with }
3. Ensure all strings are properly escaped
4. Do not use line breaks within JSON string values - use \n instead
5. Keep each array limited to 5-8 items maximum to ensure complete output
6. Be concise but specific in your assessments
7. Default to a senior private equity hiring lens: screen for deal judgement, investment thinking, commercial ownership, modelling depth where relevant, portfolio value-creation exposure, stakeholder management, leadership signals, and evidence that the profile can operate credibly with senior decision-makers
8. If the role is adjacent to the target market, explain how the candidate can position it as a credible next step into private equity, growth equity, private credit, portfolio operations, infrastructure investing, or another relevant buy-side path
9. Never invent experience, transaction volume, portfolio responsibility, modelling exposure, or technical depth that is not evidenced in the CV
10. Prioritise advice that makes the CV and positioning more credible to senior recruiters, hiring managers, finance leaders, investment committees, and executive stakeholders.';
    }

    /**
     * Build user prompt - optimized for reliable JSON output
     */
    private function build_user_prompt($jd_text, $cv_text) {
        return 'Analyze this job description against the candidate CV and return a JSON analysis.

The candidate is using MENA Careers to improve their chances of landing or advancing in a senior private equity role. Use that lens throughout the assessment:
- if the job is directly in the target market, evaluate fit against hiring expectations in private equity, growth equity, private credit, infrastructure investing, portfolio operations, value creation, or closely related buy-side roles
- if the role is adjacent to that target, evaluate how well it strengthens the path into a stronger private equity seat with greater scope, ownership, or leadership exposure
- highlight where the CV supports investor-grade readiness and where it fails to show the signals senior recruiters, hiring managers, and investment leaders want to see

JOB DESCRIPTION:
' . $jd_text . '

CANDIDATE CV:
' . $cv_text . '

Return ONLY this JSON structure (no markdown, no code blocks):

{
  "executive_summary": {
    "role_title": "extracted job title",
    "company": "company name or empty string",
    "match_score": 0-100,
    "risk_level": "HIGH_RISK|MODERATE|COMPETITIVE|STRONG_FIT",
    "verdict": "2-3 sentence assessment",
    "recommendation": "APPLY|APPLY_WITH_CAUTION|CONSIDER_ALTERNATIVES|DO_NOT_APPLY",
    "key_insight": "most important thing to know"
  },
  "scores": {
    "overall": 0-100,
    "skills_match": 0-100,
    "experience_match": 0-100,
    "education_match": 0-100,
    "keywords_match": 0-100
  },
  "skills_breakdown": {
    "matched_skills": [
      {"skill": "name", "jd_requirement": "how JD describes it", "cv_evidence": "how CV shows it", "strength_level": "expert|proficient|basic"}
    ],
    "missing_skills": [
      {"skill": "name", "importance": "critical|important|nice_to_have", "suggestion": "how to address"}
    ],
    "transferable_skills": [
      {"skill": "name", "relevance": "how it applies", "positioning": "how to frame it"}
    ]
  },
  "experience_analysis": {
    "years_required": "from JD",
    "years_candidate_has": "from CV",
    "experience_gap": "assessment",
    "relevant_roles": [
      {
        "role": "title",
        "company": "company",
        "relevance_score": 0-100,
        "key_matches": ["matching point"],
        "gaps": ["missing element"]
      }
    ],
    "industry_fit": {
      "required_industries": ["industry"],
      "candidate_industries": ["industry"],
      "assessment": "match analysis"
    }
  },
  "experience_improvements": {
    "summary": "overall assessment",
    "priority_fixes": [
      {
        "priority": 1,
        "issue": "problem",
        "current_text": "current",
        "improved_text": "improved",
        "impact": "high|medium|low",
        "jd_alignment": "which requirement"
      }
    ],
    "action_verb_upgrades": [
      {"weak_verb": "current", "strong_verb": "better", "example_rewrite": "full rewrite"}
    ],
    "quantification_fixes": [
      {"original": "vague", "improved": "with numbers"}
    ]
  },
  "keyword_analysis": {
    "total_jd_keywords": 0,
    "matched_keywords": 0,
    "match_percentage": 0,
    "critical_missing": ["keyword"],
    "well_represented": ["keyword"],
    "suggested_additions": ["keyword"]
  },
  "red_flags": [
    {"issue": "concern", "severity": "dealbreaker|serious|minor", "evidence": "what triggers it", "mitigation": "how to address"}
  ],
  "strengths_to_highlight": [
    {"strength": "strength", "relevance": "why it matters", "how_to_leverage": "advice"}
  ],
  "cv_improvements": [
    {"section": "which section", "current": "current text", "suggested": "improved", "impact": "why it helps"}
  ],
  "cover_letter_points": ["key point to address"],
  "interview_prep": [
    {
      "category": "behavioral|technical|situational",
      "likely_question": "question",
      "why_theyll_ask": "reason",
      "suggested_response_angle": "strategy",
      "example_answer": "sample answer"
    }
  ],
  "overall_assessment": {
    "fit_percentage": 0-100,
    "primary_strengths": ["strength"],
    "primary_gaps": ["gap"],
    "competitive_position": "assessment",
    "success_probability": "percentage",
    "final_recommendation": "detailed advice"
  }
}

When writing the analysis:
- prefer private equity-relevant wording over generic filler where justified by the JD
- pay close attention to investment judgment, forecasting quality, modelling depth, diligence, portfolio monitoring, budgeting ownership, capital allocation, market awareness, stakeholder exposure, team leadership, measurable results, and any tools or domain signals in the brief
- make recommendations concrete enough that the candidate could use them to reposition the CV for senior private equity recruiting';
    }

    /**
     * Parse Claude response with multiple strategies
     */
    private function parse_claude_response($content) {
        if (empty($content)) {
            error_log('Gap Analyzer: Empty content received');
            return null;
        }

        // Strategy 1: Direct parse (content is clean JSON)
        $result = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            error_log('Gap Analyzer: Direct JSON parse successful');
            return $result;
        }

        // Strategy 2: Remove markdown code blocks
        $cleaned = $content;
        $cleaned = preg_replace('/^```json\s*/i', '', $cleaned);
        $cleaned = preg_replace('/^```\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```\s*$/i', '', $cleaned);
        $cleaned = trim($cleaned);

        $result = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            error_log('Gap Analyzer: JSON parse after markdown removal successful');
            return $result;
        }

        // Strategy 3: Extract JSON object from text
        if (preg_match('/\{[\s\S]+\}/s', $content, $matches)) {
            $json_str = $matches[0];
            $result = json_decode($json_str, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                error_log('Gap Analyzer: JSON extraction successful');
                return $result;
            }
        }

        // Strategy 4: Fix common JSON issues
        $fixed = $this->fix_json_issues($content);
        if ($fixed !== null) {
            $result = json_decode($fixed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                error_log('Gap Analyzer: JSON fix successful');
                return $result;
            }
        }

        error_log('Gap Analyzer: All parse strategies failed. Error: ' . json_last_error_msg());
        error_log('Gap Analyzer: Content preview: ' . substr($content, 0, 500));

        return null;
    }

    /**
     * Fix common JSON formatting issues
     */
    private function fix_json_issues($content) {
        // Extract just the JSON part
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($content, $start, $end - $start + 1);

        // Remove BOM and control characters
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        $json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json);

        // Fix trailing commas before closing brackets/braces
        $json = preg_replace('/,(\s*[\]\}])/s', '$1', $json);

        // Fix unescaped newlines in strings (common Claude issue)
        // This is tricky - we need to be careful not to break valid escapes
        $json = preg_replace('/(?<!\\\\)\n/', '\\n', $json);

        // Fix unescaped quotes in strings
        // Look for patterns like "text with "quoted" words"
        $json = preg_replace_callback('/"([^"]*)"/', function($matches) {
            $inner = $matches[1];
            // If inner content has unescaped quotes, escape them
            $inner = preg_replace('/(?<!\\\\)"/', '\\"', $inner);
            return '"' . $inner . '"';
        }, $json);

        return $json;
    }

    /**
     * Attempt to recover partial JSON when response was truncated
     */
    private function recover_partial_json($content) {
        error_log('Gap Analyzer: Attempting partial JSON recovery');

        // Find the start of JSON
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $json = substr($content, $start);

        // Count brackets to find where we need to close
        $open_braces = 0;
        $open_brackets = 0;
        $in_string = false;
        $escape_next = false;

        for ($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];

            if ($escape_next) {
                $escape_next = false;
                continue;
            }

            if ($char === '\\') {
                $escape_next = true;
                continue;
            }

            if ($char === '"' && !$escape_next) {
                $in_string = !$in_string;
                continue;
            }

            if (!$in_string) {
                if ($char === '{') $open_braces++;
                if ($char === '}') $open_braces--;
                if ($char === '[') $open_brackets++;
                if ($char === ']') $open_brackets--;
            }
        }

        // If we're in a string, close it
        if ($in_string) {
            $json .= '"';
        }

        // Close any open brackets
        while ($open_brackets > 0) {
            $json .= ']';
            $open_brackets--;
        }

        // Close any open braces
        while ($open_braces > 0) {
            $json .= '}';
            $open_braces--;
        }

        // Remove any trailing commas before closures we just added
        $json = preg_replace('/,(\s*[\]\}]+)$/', '$1', $json);

        // Try to parse
        $result = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            error_log('Gap Analyzer: Partial recovery successful');
            return $result;
        }

        error_log('Gap Analyzer: Partial recovery failed: ' . json_last_error_msg());
        return null;
    }

    /**
     * Ensure all required fields exist in the parsed result
     */
    private function ensure_complete_structure($parsed) {
        $defaults = array(
            'executive_summary' => array(
                'role_title' => 'Position',
                'company' => '',
                'match_score' => 50,
                'risk_level' => 'MODERATE',
                'verdict' => 'Analysis completed.',
                'recommendation' => 'APPLY_WITH_CAUTION',
                'key_insight' => 'Review the detailed analysis below.',
            ),
            'scores' => array(
                'overall' => 50,
                'skills_match' => 50,
                'experience_match' => 50,
                'education_match' => 50,
                'keywords_match' => 50,
            ),
            'skills_breakdown' => array(
                'matched_skills' => array(),
                'missing_skills' => array(),
                'transferable_skills' => array(),
            ),
            'experience_analysis' => array(
                'years_required' => 'Not specified',
                'years_candidate_has' => 'Not specified',
                'experience_gap' => 'Unable to determine',
                'relevant_roles' => array(),
                'industry_fit' => array(
                    'required_industries' => array(),
                    'candidate_industries' => array(),
                    'assessment' => 'Not analyzed',
                ),
            ),
            'experience_improvements' => array(
                'summary' => '',
                'priority_fixes' => array(),
                'action_verb_upgrades' => array(),
                'quantification_fixes' => array(),
            ),
            'keyword_analysis' => array(
                'total_jd_keywords' => 0,
                'matched_keywords' => 0,
                'match_percentage' => 0,
                'critical_missing' => array(),
                'well_represented' => array(),
                'suggested_additions' => array(),
            ),
            'red_flags' => array(),
            'strengths_to_highlight' => array(),
            'cv_improvements' => array(),
            'cover_letter_points' => array(),
            'interview_prep' => array(),
            'overall_assessment' => array(
                'fit_percentage' => 50,
                'primary_strengths' => array(),
                'primary_gaps' => array(),
                'competitive_position' => 'Moderate fit',
                'success_probability' => '40-60%',
                'final_recommendation' => 'Consider applying with a tailored approach.',
            ),
        );

        // Deep merge parsed data with defaults
        $result = $this->deep_merge($defaults, $parsed);

        // Ensure scores are numbers
        if (isset($result['scores'])) {
            foreach ($result['scores'] as $key => $value) {
                $result['scores'][$key] = intval($value);
            }
        }

        // Ensure executive_summary match_score is set from scores if missing
        if (isset($result['scores']['overall']) && empty($result['executive_summary']['match_score'])) {
            $result['executive_summary']['match_score'] = $result['scores']['overall'];
        }

        return $result;
    }

    /**
     * Deep merge two arrays
     */
    private function deep_merge($defaults, $data) {
        if (!is_array($data)) {
            return $defaults;
        }

        $result = $defaults;

        foreach ($data as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                // Check if this is an associative array (object-like) or indexed array
                $is_assoc = array_keys($value) !== range(0, count($value) - 1);
                $default_is_assoc = !empty($result[$key]) && array_keys($result[$key]) !== range(0, count($result[$key]) - 1);

                if ($is_assoc && $default_is_assoc) {
                    // Both are associative - deep merge
                    $result[$key] = $this->deep_merge($result[$key], $value);
                } else {
                    // At least one is indexed array - replace with data if not empty
                    if (!empty($value)) {
                        $result[$key] = $value;
                    }
                }
            } else {
                // Not an array or not in defaults - just use the value if not empty
                if ($value !== null && $value !== '') {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Legacy method for backwards compatibility
     */
    public function analyze($jd_data, $cv_data, $preliminary) {
        // Convert parsed data back to text for full analysis
        $jd_text = isset($jd_data['raw_text']) ? $jd_data['raw_text'] : wp_json_encode($jd_data);
        $cv_text = isset($cv_data['raw_text']) ? $cv_data['raw_text'] : wp_json_encode($cv_data);

        return $this->analyze_full($jd_text, $cv_text);
    }

    /**
     * Clear cache
     */
    public function clear_cache($jd_text = null, $cv_text = null) {
        if ($jd_text && $cv_text) {
            $cache_key = 'sffc_gap_' . md5($jd_text . $cv_text);
            delete_transient($cache_key);
        }
    }
}
