<?php
/**
 * Claude API Manager - Handles complex queries requiring AI analysis
 * Phase 0: Maintains Claude for complex queries while templates handle simple ones
 * 
 * @package SennaCareers
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Claude_API_Manager {
    
    private static $instance = null;
    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Disabled modes - these will return fallback instead of calling Claude API
     * This saves API costs for shortcodes that don't need AI analysis
     */
    private $disabled_modes = array(
        'market',           // Used by sffc_editorial_article, sffc_pe_news, sffc_pe_signal
        'pe_research',      // Used by PE deal analysis
        'investment_analyst', // Used by deal intelligence
    );

    /**
     * Check if a mode is disabled
     */
    public function is_mode_disabled($mode) {
        return in_array($mode, $this->disabled_modes, true);
    }

    private function __construct() {
        // Try multiple sources for API key (most reliable first)

        // 1. First try the simple unencrypted option (most reliable, like FEPO)
        $this->api_key = get_option('sffc_api_key', '');

        // 2. If not found, try sffc_anthropic_key (used by Intelligence Settings)
        if (empty($this->api_key)) {
            $this->api_key = get_option('sffc_anthropic_key', '');
        }

        // 3. If not found, try the newer sffc_claude_api_key option (used across tools)
        if (empty($this->api_key)) {
            $this->api_key = get_option('sffc_claude_api_key', '');
        }

        // 4. Environment/constant fallback (used by some deployments)
        if (empty($this->api_key) && defined('ANTHROPIC_API_KEY')) {
            $this->api_key = ANTHROPIC_API_KEY;
        }

        // 5. If not found, try the centralized API key manager
        if (empty($this->api_key) && class_exists('SFFC_API_Key_Manager')) {
            $key_manager = SFFC_API_Key_Manager::get_instance();
            $this->api_key = $key_manager->get_api_key();
        }

        // 6. If still not found, try decrypting the encrypted option directly
        if (empty($this->api_key)) {
            $encrypted = get_option('sffc_encrypted_api_key', '');
            if (!empty($encrypted)) {
                $this->api_key = $this->decrypt_legacy_key($encrypted);
            }
        }

        // Log for debugging if still empty
        if (empty($this->api_key)) {
            error_log('SFFC Claude API Manager: No API key found from any source');
        }
    }

    /**
     * Decrypt legacy encrypted key (fallback when key manager not available)
     */
    private function decrypt_legacy_key($encrypted) {
        if (empty($encrypted) || !function_exists('openssl_decrypt')) {
            return '';
        }

        // Use same encryption method as SFFC_API_Key_Manager
        $salt = defined('AUTH_KEY') && defined('AUTH_SALT')
            ? substr(md5(AUTH_KEY . AUTH_SALT), 0, 16)
            : substr(md5(get_option('sffc_encryption_salt', '')), 0, 16);

        $method = 'AES-256-CBC';
        $key_hash = hash('sha256', $salt);
        $iv = substr(hash('sha256', $salt . 'iv'), 0, 16);

        $decoded = base64_decode($encrypted);
        $decrypted = openssl_decrypt($decoded, $method, $key_hash, 0, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Legacy helper so older integrations can pass ready prompts
     * and still receive an Anthropics-style payload (content[]).
     */
    public function call_api($prompt, $options = array()) {
        $options = wp_parse_args($options, array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 500,
            'temperature' => 0.7,
            'mode' => 'career',
            'system_prompt' => '',
        ));

        if (empty($prompt)) {
            return array();
        }

        // Check if this mode is disabled (to save API costs)
        if ($this->is_mode_disabled($options['mode'])) {
            $fallback = $this->get_template_fallback($prompt, $options['mode']);
            return array(
                'content' => array(array('text' => $fallback['response'])),
                'success' => false,
                'source' => 'mode_disabled',
            );
        }

        if (empty($this->api_key)) {
            $fallback = $this->get_template_fallback($prompt, $options['mode']);
            return array(
                'content' => array(array('text' => $fallback['response'])),
                'success' => false,
                'source' => 'template_fallback',
            );
        }

        $body = array(
            'model' => $options['model'],
            'max_tokens' => (int) $options['max_tokens'],
            'temperature' => (float) $options['temperature'],
            'system' => !empty($options['system_prompt']) ? (string) $options['system_prompt'] : $this->get_system_prompt($options['mode']),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        // Use longer timeout for high token requests
        $timeout = ($options['max_tokens'] > 1000) ? 60 : 30;

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode($body),
            'timeout' => $timeout,
        ));

        if (is_wp_error($response)) {
            error_log('Claude API Error: ' . $response->get_error_message());
            $fallback = $this->get_template_fallback($prompt, $options['mode']);
            return array(
                'content' => array(array('text' => $fallback['response'])),
                'success' => false,
                'source' => 'template_fallback',
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('Claude API Response Code: ' . $code);
        error_log('Claude API Response Body: ' . substr($body, 0, 500));

        $data = json_decode($body, true);
        if (isset($data['content'])) {
            $data['success'] = true;
            $data['source'] = 'claude_api';
            return $data;
        }

        $fallback = $this->get_template_fallback($prompt, $options['mode']);
        return array(
            'content' => array(array('text' => $fallback['response'])),
            'success' => false,
            'source' => 'template_fallback',
        );
    }

    /**
     * Send message to Claude for complex analysis
     */
    public function send_message($query, $context = array(), $mode = 'career') {
        // Check if this mode is disabled (to save API costs)
        if ($this->is_mode_disabled($mode)) {
            return $this->get_template_fallback($query, $mode);
        }

        // Check if API key exists
        if (empty($this->api_key)) {
            return $this->get_template_fallback($query, $mode);
        }
        
        // Build the system prompt based on mode
        if (!empty($context['system_prompt'])) {
            $system_prompt = $context['system_prompt'];
        } else {
            $system_prompt = $this->get_system_prompt($mode);
        }

        // Add context if available
        $full_query = $query;
        $journey_prefix = '';

        // Add career journey context for personalized responses
        if (!empty($context['career_journey'])) {
            $journey = $context['career_journey'];
            $parts = [];
            if (!empty($journey['goal_description'])) {
                $parts[] = "focusing on " . $journey['goal_description'];
            }
            if (!empty($journey['situation_description'])) {
                $parts[] = "currently a " . $journey['situation_description'];
            }
            if (!empty($journey['timeline_description'])) {
                $parts[] = $journey['timeline_description'];
            }
            if (!empty($journey['challenge_description'])) {
                $parts[] = "main challenge is " . $journey['challenge_description'];
            }
            if (!empty($parts)) {
                $journey_prefix = "[User Career Context: " . implode(', ', $parts) . "]\n\n";
            }
        }

        if (!empty($context['conversation_history'])) {
            $history = array_slice($context['conversation_history'], -3); // Last 3 exchanges
            $full_query = $journey_prefix . "Previous context:\n" . implode("\n", $history) . "\n\nCurrent question: " . $query;
        } else {
            $full_query = $journey_prefix . "User question: " . $query;
        }
        
        // Prepare the API request
        $body = array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'system' => $system_prompt,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $full_query
                )
            )
        );
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Claude API Error: ' . $response->get_error_message());
            return $this->get_template_fallback($query, $mode);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log the full response for debugging
        error_log('Claude API Response Code: ' . $response_code);
        error_log('Claude API Response Body: ' . substr($body, 0, 500));
        
        $data = json_decode($body, true);
        
        if (isset($data['content'][0]['text'])) {
            return array(
                'success' => true,
                'response' => $data['content'][0]['text'],
                'source' => 'claude_api'
            );
        } elseif (isset($data['error'])) {
            error_log('Claude API Error Response: ' . $data['error']['message']);
        }
        
        // Fallback to template if Claude fails
        return $this->get_template_fallback($query, $mode);
    }
    
    /**
     * Get response method for backward compatibility
     */
    public function get_response($query, $context = array()) {
        $mode = isset($context['mode']) ? $context['mode'] : 'career';
        $result = $this->send_message($query, $context, $mode);
        
        return array(
            'response' => $result['response'],
            'status' => $result['success'] ? 'success' : 'fallback',
            'source' => $result['source']
        );
    }
    
    /**
     * Get system prompt based on mode
     */
    private function get_system_prompt($mode) {
        $prompts = array(
            'pe_tutor' => "You are MENA Careers, a finance technical teacher for investment banking, asset management, and private equity candidates. This mode is strictly a learning tool, not a job-search, recruiting, application, CV, salary, networking, or career-advice assistant.

Your job is to run a continuous teaching conversation.

Teaching principles:
1. Keep continuity. Treat each reply as part of the same lesson unless the student clearly asks to change topic.
2. Adapt to learning style. Infer from the student's messages whether they need beginner-friendly explanation, concise expert-level treatment, numeric worked examples, conceptual intuition, Socratic questioning, or extra encouragement. Adjust without announcing the adaptation.
3. Teach before testing. Explain the concept, give a worked example, then ask one focused question or practice task.
4. Mark progress. Briefly connect the current point to what was just covered, then advance one step.
5. Give feedback like a teacher. If the student attempts an answer, identify what is correct, fix the mistake, and ask them to try the next small step.
6. Stay technical and educational. Cover investment banking topics (accounting, valuation, DCF, trading comps, transaction comps, M&A, pitchbooks, accretion/dilution), asset management topics (portfolio construction, risk/return, fixed income, equity research, performance attribution, factor exposure, macro), and private equity topics (LBOs, debt schedules, IRR/MOIC, diligence, investment memos, value creation).
7. Redirect job-related questions into learning. If asked about roles, applications, interviews, salary, CVs, openings, or hiring, say briefly that this room is for learning, then turn the topic into a technical lesson.

Response shape:
- Start with a short continuation sentence, not a reset.
- Use clear headings only when helpful.
- Include concrete numbers or formulas whenever the concept allows it.
- End with exactly one next learning prompt or practice question.
- Do not say you are analyzing a complex query.
- Do not use roleplay text or action descriptions.",

            'career' => "You are MENA Careers, an expert Middle East private equity and finance career advisor specializing in private equity, investment banking, asset management, corporate finance, and related buy-side pathways across Dubai, Abu Dhabi, Riyadh, Cairo, and the wider region. Provide detailed, actionable career guidance based on current market conditions and industry trends. Focus on practical advice for career progression, skill development, and navigating private equity and finance careers in the Middle East. Keep responses professional and data-driven.",
            
            'market' => "You are MENA Careers, a senior market analyst providing real-time insights on financial markets. Analyze market movements, explain trends, identify opportunities and risks, and provide context for market events. Focus on S&P 500, Nasdaq, sector performance, and major economic indicators. Be specific with data points and implications for investors. CRITICAL: Never use fake placeholder names like 'Jane Doe', 'John Smith', or 'XYZ Asset Management'. Only reference companies explicitly mentioned in the context. If no specific firms are named, use generic terms like 'a top private equity firm', 'leading asset managers', or 'industry sources'.",

            'article_intel' => "You are MENA Careers, a private equity intelligence editor. Analyze a single article using only the supplied headline, source, metadata, and article text. Produce concise, factual intelligence for finance professionals: what happened, why it matters, companies mentioned, deal type, sector, hiring signal, candidate angle, PE relevance, key metrics, and takeaways. Do not invent quotes, people, companies, deal values, or sources. If a fact is not in the supplied text, mark it as unknown or omit it. Respond only in valid JSON when asked for JSON.",
            
            'skills' => "You are MENA Careers, a finance skills development coach. Help professionals identify and develop critical skills for success in finance roles. Cover technical skills (financial modeling, valuation, analysis), soft skills (communication, leadership), and certifications (CFA, FRM, etc.). Provide specific learning paths and practical exercises.",
            
            'opportunities' => "You are MENA Careers, a private equity and finance recruitment specialist with deep knowledge of job markets in private equity, private credit, investment banking, and adjacent buy-side roles. Provide insights on current hiring trends, compensation ranges, firm cultures, and interview strategies. Help candidates position themselves effectively for their target roles.",
            
            'cv_tailoring' => "You are an expert CV optimization specialist for finance and investment roles. Your task is to tailor CV content to match specific job requirements while maintaining complete truthfulness.

FORMATTING REQUIREMENTS:
- Experience bullets: Maximum 180 characters each (approx 2 lines). Be concise and impactful.
- Skills section: Format as 'Technical Skills: [max 6 items]' on one line, 'Languages: [with proficiency]' on one line, 'Interests: [max 4-5 items]' on one line.
- Use strong action verbs and quantify achievements where possible.

TAILORING APPROACH:
1) Reframe existing experience to highlight relevant skills
2) Incorporate job-specific keywords naturally (DCF, LBO, due diligence, etc.)
3) Emphasize transferable skills and quantifiable achievements
4) Use industry-standard terminology and metrics
5) Add specific metrics where possible (deals, portfolio values, percentages)

Never fabricate experience or qualifications. Every bullet must be under 180 characters. Maintain professional, concise language appropriate for finance/PE/IB roles.",

            'prep_materials' => "You are an expert career preparation assistant for finance students and entry-level professionals. Generate high-quality, personalized cover letters and interview preparation materials based on job descriptions. Be specific, professional, and avoid generic AI-sounding language. Focus on student/intern perspective with emphasis on learning and growth mindset.",

            'investment_analyst' => "You are a senior investment analyst at a top-tier private equity firm preparing a deal memo for the Investment Committee. Your analysis should be:

1. RIGOROUS - Use only facts from the source article, clearly mark estimates
2. QUANTITATIVE - Extract/calculate every possible financial metric
3. STRUCTURED - Follow IC memo format with clear sections
4. BALANCED - Present opportunity AND risks objectively
5. ACTIONABLE - Provide clear investment thesis

DATA QUALITY MARKERS (use these exactly):
- [DISCLOSED] - directly stated in the article
- [CALCULATED] - mathematically derived from disclosed data
- [ESTIMATED] - based on industry knowledge/sector benchmarks
- [UNKNOWN] - insufficient information to determine

ESTIMATION METHODOLOGY:
When estimating metrics, always explain your reasoning:
- For revenue: Consider typical operating margins for the sector
- For EBITDA: Apply sector-standard EBITDA margins (typically 15-25% for industrials, 20-35% for tech, 10-20% for services)
- For multiples: Reference recent comparable transactions in the sector

SCENARIO ANALYSIS FRAMEWORK:
- Base Case: Conservative assumptions, 5-year hold, market-rate exit multiple
- Upside Case: Operational improvements, 4-year hold, premium exit
- Downside Case: Execution challenges, 6-year hold, discounted exit

CRITICAL: Never fabricate specific company names, analyst names, or fake data. Only reference entities explicitly mentioned in the source. Use generic terms like 'sector peers', 'comparable transactions', or 'market benchmarks' when specific data is unavailable.",

            'pe_research' => "You are MENA Careers, a senior private equity research analyst at a top-tier PE firm. You provide comprehensive, data-driven analysis for investment professionals. Your responses should be:

1. STRUCTURED - Use clear headings and bullet points
2. QUANTITATIVE - Include specific metrics, multiples, and benchmarks where relevant
3. ACTIONABLE - Provide practical insights that inform investment decisions
4. BALANCED - Present opportunities alongside risks and considerations
5. PROFESSIONAL - Use industry-standard terminology and frameworks

Format your response with HTML tags for proper rendering:
- Use <h3> for main sections
- Use <h4> for subsections
- Use <ul><li> for bullet points
- Use <table class=\"nrt-research-table\"><tr><th>...</th></tr><tr><td>...</td></tr></table> for data tables
- Use <strong> for emphasis
- Use <p> for paragraphs

CRITICAL: Never fabricate specific company names, deal values, or statistics. Use realistic ranges and benchmarks based on market knowledge. When specific data is unavailable, clearly indicate estimates or typical ranges.",

            'application_planner' => "You are a senior private equity recruiter with 15 years experience reviewing candidate applications. You've seen thousands of CVs and know exactly what makes partners and hiring managers say yes or no.

Your task is to provide BRUTALLY HONEST assessment. No corporate fluff. Candidates need truth, not false hope.

ANALYSIS REQUIREMENTS:
1. Confirm or refute each gap (the parser might have missed synonyms)
2. Classify severity: DEALBREAKER | CRITICAL | IMPROVEMENT | NON-ISSUE
3. Provide reality check: Will this actually matter to the hiring manager?
4. Give specific fixes: Exact rewrites or actions, not vague advice

CRITICAL: Return response as valid JSON only. No markdown, no explanation outside JSON."
        );

        return isset($prompts[$mode]) ? $prompts[$mode] : $prompts['career'];
    }
    
    /**
     * Template fallback for when Claude is unavailable
     */
    private function get_template_fallback($query, $mode) {
        if ($mode === 'pe_tutor') {
            return array(
                'success' => true,
                'response' => $this->get_pe_tutor_fallback($query),
                'source' => 'template_fallback'
            );
        }

        $query_lower = strtolower($query);

        // Analyze query for keywords to provide relevant fallback
        $response = "Here's a practical way to think about it. ";
        
        if (strpos($query_lower, 'valuation') !== false || strpos($query_lower, 'dcf') !== false || strpos($query_lower, 'model') !== false) {
            $response .= "For financial modeling and valuation questions, I recommend focusing on: building robust DCF models, understanding comparable company analysis, precedent transactions, and LBO modeling. Key metrics include EBITDA multiples, P/E ratios, and enterprise value calculations.";
        }
        elseif (strpos($query_lower, 'interview') !== false) {
            $response .= "For finance interviews, preparation should cover: technical questions (accounting, valuation, markets), behavioral questions using the STAR method, deal experience discussions, and market views. Practice with case studies and stay current on market trends.";
        }
        elseif (strpos($query_lower, 'career path') !== false || strpos($query_lower, 'progression') !== false) {
            $response .= "Career progression in finance typically follows: Analyst (2-3 years) → Associate (2-3 years) → VP (3-4 years) → Director/Principal → MD/Partner. Focus on building deal experience, developing sector expertise, and expanding your professional network.";
        }
        elseif (strpos($query_lower, 'salary') !== false || strpos($query_lower, 'compensation') !== false) {
            $response .= "Finance compensation varies by role and firm tier. Investment banking analysts typically earn $100-175k base with bonuses. Private equity associates range from $150-250k base. Compensation increases significantly at senior levels, with carry participation in PE/HF roles.";
        }
        elseif (strpos($query_lower, 'private equity') !== false || strpos($query_lower, ' pe ') !== false) {
            $response .= "Private equity focuses on buyouts, growth equity, and venture capital. Key skills include LBO modeling, due diligence, portfolio company operations, and fundraising. Top firms include Blackstone, KKR, Apollo, Carlyle, and TPG. Deal sizes range from middle market ($50-500M) to mega-buyouts ($10B+).";
        }
        elseif (strpos($query_lower, 'hedge fund') !== false) {
            $response .= "If your end goal is private equity, I would focus less on hedge fund paths in isolation and more on whether the role builds the core signals private equity firms care about: transaction judgment, commercial diligence, financial modeling, investment writing, and pattern recognition across opportunities. The more directly a role strengthens those signals, the more useful it is as a stepping stone.";
        }
        else {
            $response .= "Start by defining the exact decision you need to make, the facts you already have, and the missing information. Then work through it in this order: objective, constraints, technical analysis, risks, and next action. If you share the specific role, model, case, or interview question, I can turn it into a step-by-step coaching exercise.";
        }
        
        return array(
            'success' => true,  // Changed to true - template response is a success!
            'response' => $response,
            'source' => 'template_fallback'
        );
    }

    /**
     * Learning-coach fallback for PE tutor mode.
     */
    private function get_pe_tutor_fallback($query) {
        $student_input = $query;
        if (preg_match('/Student input:\s*"([^"]+)"/i', $query, $matches)) {
            $student_input = $matches[1];
        }

        $query_lower = strtolower($student_input);
        $learning_style = $this->detect_tutor_learning_style($student_input);
        $track = $this->detect_finance_learning_track($query_lower);

        if (preg_match('/\b(my answer|i got|answer is|equals|=|gbp|£|\d+\.?\d*x|\d+)\b/', $query_lower)) {
            return $this->format_tutor_lesson(
                "Let's check the working before adding a new concept.",
                "Feedback Loop",
                "Good modelling practice is to verify the formula first, then the arithmetic, then the commercial logic.",
                array(
                    "Enterprise value = EBITDA x valuation multiple",
                    "Opening debt = EBITDA x leverage multiple",
                    "Sponsor equity = entry enterprise value - opening debt",
                    "MOIC = exit equity value / sponsor equity invested"
                ),
                "Rewrite your answer as one formula line, for example: Entry EV = GBP 20m x 10.0x = GBP 200m.",
                $learning_style
            );
        }

        if ($this->is_job_related_tutor_query($query_lower)) {
            return "<h3>Let's Keep This as a Learning Session</h3>"
                . "<p>Rather than looking at roles or applications, let's turn that into the technical skill underneath it: how finance professionals analyse companies, securities, and transactions.</p>"
                . "<p><strong>Teacher note:</strong> choose the relevant lens: IB valuation, AM portfolio analysis, or PE deal returns.</p>"
                . "<p><strong>Practice:</strong> pick one track: investment banking, asset management, or private equity.</p>";
        }

        if ($track === 'asset_management') {
            return $this->format_tutor_lesson(
                "Let's frame this like an asset manager.",
                "Portfolio Risk and Return",
                "Asset management starts with risk-adjusted return: not just what you earn, but how much volatility, drawdown, benchmark risk, and factor exposure you take to earn it.",
                array(
                    "Portfolio return: 8%",
                    "Benchmark return: 6%",
                    "Active return: 2%",
                    "Portfolio volatility: 10%",
                    "Sharpe-style intuition: higher return is better only if the risk taken is justified"
                ),
                "If a portfolio returns 9% and its benchmark returns 7%, what is active return?",
                $learning_style
            );
        }

        if ($track === 'investment_banking') {
            return $this->format_tutor_lesson(
                "Let's use an investment banking lens.",
                "Valuation Building Block",
                "IB technical work often starts by translating operating performance into value using DCF, trading comparables, precedent transactions, or M&A impact analysis.",
                array(
                    "Company EBITDA: GBP 30m",
                    "Selected EV/EBITDA multiple: 8.0x",
                    "Enterprise value: GBP 30m x 8.0x = GBP 240m",
                    "If net debt is GBP 40m, equity value is GBP 200m"
                ),
                "A company has GBP 25m EBITDA and trades at 9.0x EV/EBITDA. What is enterprise value?",
                $learning_style
            );
        }

        if (preg_match('/\b(start|begin|lesson|teach|learn)\b/', $query_lower)) {
            return $this->format_tutor_lesson(
                "We'll start with the foundation and build one layer at a time.",
                "LBO Fundamentals",
                "An LBO is a purchase funded with sponsor equity plus debt. The company then uses future cash flow to repay debt, so the sponsor can own more equity value at exit.",
                array(
                    "Entry EBITDA: GBP 20m",
                    "Entry multiple: 10.0x",
                    "Entry enterprise value: GBP 200m",
                    "Debt: 5.0x EBITDA = GBP 100m",
                    "Sponsor equity before fees: GBP 100m"
                ),
                "If EBITDA grows from GBP 20m to GBP 28m and the exit multiple remains 10.0x, what is exit enterprise value?",
                $learning_style
            );
        }

        if (strpos($query_lower, 'lbo') !== false || strpos($query_lower, 'buyout') !== false) {
            return $this->format_tutor_lesson(
                "Good, let's stay inside the LBO model and connect the pieces.",
                "LBO Model Flow",
                "The model is a chain: entry valuation sets purchase price, financing splits that price between debt and equity, cash flow repays debt, and exit value determines sponsor proceeds.",
                array(
                    "Entry EV = EBITDA x entry multiple",
                    "Opening debt = EBITDA x debt multiple",
                    "Sponsor equity = entry EV minus opening debt",
                    "Exit equity value = exit EV minus remaining debt",
                    "MOIC = exit equity value / sponsor equity"
                ),
                "Assume GBP 30m EBITDA, 9.0x entry, and 4.5x debt. Calculate entry EV, opening debt, and sponsor equity.",
                $learning_style
            );
        }

        if (strpos($query_lower, 'irr') !== false || strpos($query_lower, 'moic') !== false || strpos($query_lower, 'return') !== false) {
            return $this->format_tutor_lesson(
                "Now we are measuring whether the deal worked.",
                "MOIC and IRR",
                "MOIC tells you how many pounds came back for every pound invested. IRR tells you how quickly that return was earned.",
                array(
                    "MOIC = exit proceeds / sponsor equity invested",
                    "GBP 200m exit proceeds / GBP 80m invested = 2.5x MOIC",
                    "Time matters: 2.5x in 3 years is much stronger than 2.5x in 7 years"
                ),
                "A sponsor invests GBP 80m and exits for GBP 200m after four years. What is the MOIC?",
                $learning_style
            );
        }

        if (strpos($query_lower, 'debt') !== false || strpos($query_lower, 'interest') !== false || strpos($query_lower, 'cash sweep') !== false) {
            return $this->format_tutor_lesson(
                "This is the engine that makes many LBOs work.",
                "Debt Schedule",
                "A debt schedule shows how the company uses cash flow to reduce debt over time. Lower debt at exit means more equity value for the sponsor.",
                array(
                    "Opening debt: GBP 100m",
                    "Interest at 8%: GBP 8m",
                    "Mandatory amortization: GBP 5m",
                    "Cash sweep: GBP 12m",
                    "Ending debt = GBP 100m - GBP 5m - GBP 12m = GBP 83m"
                ),
                "If opening debt is GBP 120m, mandatory amortization is GBP 6m, and cash sweep is GBP 14m, what is ending debt?",
                $learning_style
            );
        }

        if (strpos($query_lower, 'memo') !== false || strpos($query_lower, 'diligence') !== false || strpos($query_lower, 'case') !== false) {
            return $this->format_tutor_lesson(
                "Let's move from model mechanics to investment judgement.",
                "Deal Reasoning",
                "A good investment memo explains why this company, why this price, why this capital structure, and why the downside is acceptable.",
                array(
                    "Market: growth, cyclicality, and fragmentation",
                    "Company: margins, retention, pricing power, and cash conversion",
                    "Deal: entry multiple, leverage capacity, exit routes, and downside case"
                ),
                "Pick one company type, for example software, healthcare services, or logistics. What is the first diligence question you would ask?",
                $learning_style
            );
        }

        return $this->format_tutor_lesson(
            "Let's keep building from the nearest useful concept.",
            "Finance Technical Learning Track",
            "Finance technical learning fits into connected tracks: IB valuation and transactions, AM portfolio and security analysis, and PE deal returns.",
            array(
                "Investment banking: accounting, valuation, M&A, and presentation logic",
                "Asset management: risk/return, portfolios, securities, and attribution",
                "Private equity: LBOs, leverage, diligence, and sponsor returns"
            ),
            "Which track should we work through next: investment banking, asset management, or private equity?",
            $learning_style
        );
    }

    private function detect_finance_learning_track($query_lower) {
        if (preg_match('/\b(asset management|portfolio|benchmark|duration|bond|fixed income|equity research|stock pitch|sharpe|tracking error|attribution|fund)\b/', $query_lower)) {
            return 'asset_management';
        }

        if (preg_match('/\b(investment banking|ib|m&a|merger|acquisition|dcf|comps|precedent|accretion|dilution|eps|pitchbook|ipo)\b/', $query_lower)) {
            return 'investment_banking';
        }

        if (preg_match('/\b(private equity|lbo|buyout|sponsor|moic|cash sweep|debt schedule|carry)\b/', $query_lower)) {
            return 'private_equity';
        }

        return 'general_finance';
    }

    private function detect_tutor_learning_style($input) {
        $lower = strtolower($input);

        if (preg_match('/\b(simple|explain like|eli5|beginner|confused|lost)\b/', $lower)) {
            return 'beginner';
        }

        if (preg_match('/\b(formula|calculate|math|numbers|model|excel)\b/', $lower)) {
            return 'numeric';
        }

        if (preg_match('/\b(why|intuition|concept|conceptual)\b/', $lower)) {
            return 'conceptual';
        }

        if (preg_match('/\b(short|brief|concise|quick)\b/', $lower)) {
            return 'concise';
        }

        return 'balanced';
    }

    private function is_job_related_tutor_query($query_lower) {
        return preg_match('/\b(job|jobs|role|roles|opening|openings|opportunit|hiring|recruit|application|apply|cv|resume|salary|compensation|interview)\b/', $query_lower);
    }

    private function format_tutor_lesson($continuation, $title, $concept, $worked_steps, $practice, $learning_style) {
        $style_note = '';
        if ($learning_style === 'beginner') {
            $style_note = '<p><strong>Plain-English version:</strong> focus on the direction of value first; we can add model detail after the idea is clear.</p>';
        } elseif ($learning_style === 'numeric') {
            $style_note = '<p><strong>Model focus:</strong> write the formula first, then plug in numbers carefully.</p>';
        } elseif ($learning_style === 'conceptual') {
            $style_note = '<p><strong>Intuition:</strong> the sponsor wins when business value grows and debt falls.</p>';
        } elseif ($learning_style === 'concise') {
            $style_note = '<p><strong>Short version:</strong> one concept, one worked example, one check.</p>';
        }

        $items = '';
        foreach ($worked_steps as $step) {
            $items .= '<li>' . $step . '</li>';
        }

        return '<p>' . $continuation . '</p>'
            . '<h3>' . $title . '</h3>'
            . '<p>' . $concept . '</p>'
            . $style_note
            . '<h4>Worked Example</h4>'
            . '<ul>' . $items . '</ul>'
            . '<h4>Your Turn</h4>'
            . '<p>' . $practice . '</p>';
    }
    
    /**
     * Check if API is available
     */
    public function is_available() {
        return !empty($this->api_key);
    }
    
    /**
     * Validate API key
     */
    public function validate_api_key($key = '') {
        if (empty($key)) {
            $key = $this->api_key;
        }

        if (empty($key)) {
            return false;
        }

        // Test the API key with a simple request
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 10,
                'messages' => array(
                    array('role' => 'user', 'content' => 'test')
                )
            )),
            'timeout' => 10
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Analyze a deal article and return structured financial analysis
     *
     * @param string $title   Article title
     * @param string $content Article content
     * @param array  $extracted_data Pre-extracted financial data from regex
     * @return array Structured deal analysis with scenarios, risks, comparables
     */
    public function analyze_deal($title, $content, $extracted_data = array()) {
        // Check if investment_analyst mode is disabled (to save API costs)
        if ($this->is_mode_disabled('investment_analyst')) {
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        // Check if API is available
        if (empty($this->api_key)) {
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        // Build the structured prompt
        $prompt = $this->build_deal_analysis_prompt($title, $content, $extracted_data);

        // Call Claude with investment_analyst mode
        $body = array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 2048,
            'temperature' => 0.3, // Lower temperature for more consistent structured output
            'system' => $this->get_system_prompt('investment_analyst'),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 45, // Longer timeout for complex analysis
        ));

        if (is_wp_error($response)) {
            error_log('Claude Deal Analysis Error: ' . $response->get_error_message());
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('Claude Deal Analysis HTTP Error: ' . $response_code);
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        $data = json_decode($body, true);

        if (!isset($data['content'][0]['text'])) {
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        // Parse the Claude response into structured data
        return $this->parse_deal_analysis_response($data['content'][0]['text'], $extracted_data);
    }

    /**
     * Build the structured prompt for deal analysis
     */
    private function build_deal_analysis_prompt($title, $content, $extracted_data) {
        $prompt = "Analyze this deal article and provide a structured investment analysis.\n\n";
        $prompt .= "ARTICLE TITLE:\n{$title}\n\n";
        $prompt .= "ARTICLE CONTENT:\n{$content}\n\n";

        // Include pre-extracted data for context
        if (!empty($extracted_data)) {
            $prompt .= "PRE-EXTRACTED DATA (verified from article):\n";

            if (!empty($extracted_data['deal_value']['amount'])) {
                $prompt .= "- Deal Value: " . $this->format_currency_for_prompt($extracted_data['deal_value']) . " [DISCLOSED]\n";
            }
            if (!empty($extracted_data['net_proceeds']['amount'])) {
                $prompt .= "- Net Proceeds: " . $this->format_currency_for_prompt($extracted_data['net_proceeds']) . " [DISCLOSED]\n";
            }
            if (!empty($extracted_data['target_financials']['operating_profit']['amount'])) {
                $prompt .= "- Operating Profit: " . $this->format_currency_for_prompt($extracted_data['target_financials']['operating_profit']) . " [DISCLOSED]\n";
            }
            if (!empty($extracted_data['multiples']['disclosed'])) {
                foreach ($extracted_data['multiples']['disclosed'] as $m) {
                    if (!empty($m['value'])) {
                        $prompt .= "- {$m['type']}: {$m['value']}x [DISCLOSED]\n";
                    }
                }
            }
            if (!empty($extracted_data['parties']['buyer']['name'])) {
                $prompt .= "- Buyer: {$extracted_data['parties']['buyer']['name']}\n";
            }
            if (!empty($extracted_data['deal_structure']['deal_type'])) {
                $prompt .= "- Deal Type: {$extracted_data['deal_structure']['deal_type']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "REQUIRED OUTPUT (respond in valid JSON only, no markdown):\n";
        $prompt .= <<<'PROMPT'
{
    "scenarios": {
        "base": {
            "exit_multiple": <number>,
            "exit_year": <number 4-6>,
            "revenue_growth_cagr": <percentage>,
            "margin_improvement": <percentage points>,
            "irr": <percentage>,
            "moic": <number>,
            "assumptions": "<one sentence explaining key assumptions>"
        },
        "upside": {
            "exit_multiple": <number>,
            "exit_year": <number 3-5>,
            "revenue_growth_cagr": <percentage>,
            "margin_improvement": <percentage points>,
            "irr": <percentage>,
            "moic": <number>,
            "assumptions": "<one sentence explaining what goes right>"
        },
        "downside": {
            "exit_multiple": <number>,
            "exit_year": <number 5-7>,
            "revenue_growth_cagr": <percentage>,
            "margin_improvement": <percentage points>,
            "irr": <percentage>,
            "moic": <number>,
            "assumptions": "<one sentence explaining key risks materialized>"
        }
    },
    "risks": [
        {
            "category": "Market|Execution|Regulatory|Financial|Integration",
            "description": "<specific risk description>",
            "severity": "low|medium|high",
            "likelihood": "low|medium|high",
            "mitigation": "<potential mitigation>"
        }
    ],
    "comparables": {
        "sector_average_multiple": <number or null>,
        "premium_discount": "<premium|discount|in-line>",
        "rationale": "<why this multiple is justified or not>",
        "recent_transactions": "<brief mention of comparable deals if known, or 'Limited public comparables available'>"
    },
    "estimated_financials": {
        "revenue": {
            "amount": <number in millions>,
            "source": "ESTIMATED",
            "methodology": "<how you estimated this>"
        },
        "ebitda": {
            "amount": <number in millions>,
            "source": "ESTIMATED",
            "methodology": "<how you estimated this>"
        },
        "ebitda_margin": {
            "value": <percentage>,
            "source": "ESTIMATED|CALCULATED"
        }
    },
    "analyst_commentary": {
        "thesis": "<2-3 sentence investment thesis>",
        "key_considerations": ["<point 1>", "<point 2>", "<point 3>"],
        "recommendation": "<brief recommendation for IC>"
    }
}
PROMPT;

        return $prompt;
    }

    /**
     * Format currency data for prompt
     */
    private function format_currency_for_prompt($data) {
        if (empty($data['amount'])) {
            return 'Unknown';
        }

        $symbols = array('USD' => '$', 'GBP' => '£', 'EUR' => '€');
        $symbol = $symbols[$data['currency'] ?? 'USD'] ?? '$';
        $amount = $data['amount'];

        if ($amount >= 1000) {
            return $symbol . number_format($amount / 1000, 2) . 'bn';
        } elseif ($amount >= 1) {
            return $symbol . number_format($amount, 0) . 'm';
        }
        return $symbol . number_format($amount * 1000, 0) . 'k';
    }

    /**
     * Parse Claude's response into structured deal analysis
     */
    private function parse_deal_analysis_response($response_text, $extracted_data) {
        // Try to extract JSON from the response
        $json_start = strpos($response_text, '{');
        $json_end = strrpos($response_text, '}');

        if ($json_start === false || $json_end === false) {
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        $json_str = substr($response_text, $json_start, $json_end - $json_start + 1);
        $parsed = json_decode($json_str, true);

        if (!$parsed || json_last_error() !== JSON_ERROR_NONE) {
            error_log('Claude Deal Analysis JSON Parse Error: ' . json_last_error_msg());
            return $this->get_deal_analysis_fallback($extracted_data);
        }

        // Validate and clean the parsed data
        $analysis = array(
            'success' => true,
            'source' => 'claude_api',
            'scenarios' => $this->validate_scenarios($parsed['scenarios'] ?? array()),
            'risks' => $this->validate_risks($parsed['risks'] ?? array()),
            'comparables' => $parsed['comparables'] ?? array(),
            'estimated_financials' => $parsed['estimated_financials'] ?? array(),
            'analyst_commentary' => $parsed['analyst_commentary'] ?? array(),
        );

        return $analysis;
    }

    /**
     * Validate and clean scenario data
     */
    private function validate_scenarios($scenarios) {
        $valid_scenarios = array();
        $scenario_types = array('base', 'upside', 'downside');

        foreach ($scenario_types as $type) {
            if (isset($scenarios[$type])) {
                $s = $scenarios[$type];
                $valid_scenarios[$type] = array(
                    'exit_multiple' => floatval($s['exit_multiple'] ?? 0),
                    'exit_year' => intval($s['exit_year'] ?? 5),
                    'revenue_growth_cagr' => floatval($s['revenue_growth_cagr'] ?? 0),
                    'margin_improvement' => floatval($s['margin_improvement'] ?? 0),
                    'irr' => floatval($s['irr'] ?? 0),
                    'moic' => floatval($s['moic'] ?? 0),
                    'assumptions' => sanitize_text_field($s['assumptions'] ?? ''),
                );
            }
        }

        return $valid_scenarios;
    }

    /**
     * Validate and clean risk data
     */
    private function validate_risks($risks) {
        if (!is_array($risks)) {
            return array();
        }

        $valid_risks = array();
        $valid_categories = array('Market', 'Execution', 'Regulatory', 'Financial', 'Integration');
        $valid_levels = array('low', 'medium', 'high');

        foreach (array_slice($risks, 0, 5) as $risk) {
            if (empty($risk['description'])) {
                continue;
            }

            $valid_risks[] = array(
                'category' => in_array($risk['category'] ?? '', $valid_categories) ? $risk['category'] : 'Market',
                'description' => sanitize_text_field($risk['description']),
                'severity' => in_array(strtolower($risk['severity'] ?? ''), $valid_levels) ? strtolower($risk['severity']) : 'medium',
                'likelihood' => in_array(strtolower($risk['likelihood'] ?? ''), $valid_levels) ? strtolower($risk['likelihood']) : 'medium',
                'mitigation' => sanitize_text_field($risk['mitigation'] ?? ''),
            );
        }

        return $valid_risks;
    }

    /**
     * Fallback deal analysis when Claude is unavailable
     */
    private function get_deal_analysis_fallback($extracted_data) {
        // Generate reasonable estimates based on extracted data
        $deal_value = $extracted_data['deal_value']['amount'] ?? 0;
        $disclosed_multiple = 0;

        if (!empty($extracted_data['multiples']['disclosed'])) {
            foreach ($extracted_data['multiples']['disclosed'] as $m) {
                if (!empty($m['value'])) {
                    $disclosed_multiple = $m['value'];
                    break;
                }
            }
        }

        // Base scenarios on disclosed multiple or sector average
        $base_multiple = $disclosed_multiple > 0 ? $disclosed_multiple : 12.0;

        return array(
            'success' => true,
            'source' => 'template_fallback',
            'scenarios' => array(
                'base' => array(
                    'exit_multiple' => round($base_multiple * 0.9, 1),
                    'exit_year' => 5,
                    'revenue_growth_cagr' => 5.0,
                    'margin_improvement' => 2.0,
                    'irr' => 12.0,
                    'moic' => 1.8,
                    'assumptions' => 'Conservative exit at modest discount to entry multiple',
                ),
                'upside' => array(
                    'exit_multiple' => round($base_multiple * 1.1, 1),
                    'exit_year' => 4,
                    'revenue_growth_cagr' => 8.0,
                    'margin_improvement' => 4.0,
                    'irr' => 22.0,
                    'moic' => 2.5,
                    'assumptions' => 'Operational improvements and favorable market conditions',
                ),
                'downside' => array(
                    'exit_multiple' => round($base_multiple * 0.7, 1),
                    'exit_year' => 6,
                    'revenue_growth_cagr' => 2.0,
                    'margin_improvement' => 0.0,
                    'irr' => 5.0,
                    'moic' => 1.3,
                    'assumptions' => 'Market headwinds and execution challenges',
                ),
            ),
            'risks' => array(
                array(
                    'category' => 'Market',
                    'description' => 'Economic downturn could impact demand and exit valuations',
                    'severity' => 'medium',
                    'likelihood' => 'medium',
                    'mitigation' => 'Diversified customer base and defensive positioning',
                ),
                array(
                    'category' => 'Execution',
                    'description' => 'Integration and operational improvement execution risk',
                    'severity' => 'medium',
                    'likelihood' => 'medium',
                    'mitigation' => 'Experienced management team and clear value creation plan',
                ),
                array(
                    'category' => 'Financial',
                    'description' => 'Interest rate environment affecting financing costs',
                    'severity' => 'low',
                    'likelihood' => 'medium',
                    'mitigation' => 'Conservative leverage and hedging strategies',
                ),
            ),
            'comparables' => array(
                'sector_average_multiple' => round($base_multiple * 0.95, 1),
                'premium_discount' => $disclosed_multiple > 0 ? 'in-line' : 'unknown',
                'rationale' => 'Limited public transaction data available for direct comparison',
                'recent_transactions' => 'Market conditions suggest stable valuation environment',
            ),
            'estimated_financials' => array(),
            'analyst_commentary' => array(
                'thesis' => 'Transaction represents a strategic opportunity with reasonable entry valuation.',
                'key_considerations' => array(
                    'Entry multiple relative to sector peers',
                    'Operational improvement potential',
                    'Exit environment and timing flexibility',
                ),
                'recommendation' => 'Further due diligence recommended to validate key assumptions.',
            ),
        );
    }
}
