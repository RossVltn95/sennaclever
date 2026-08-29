<?php
/**
 * Application Pack Claude Prompts
 *
 * Centralized, high-quality prompts for generating application documents.
 * All prompts are designed to produce professional, unique, role-specific content
 * with zero AI artifacts.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Pack_Claude_Prompts {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Claude API instance
     */
    private $claude = null;

    /**
     * Get singleton instance
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
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude = SFFC_Claude_API_Manager::get_instance();
        }
    }

    /**
     * Check if Claude is available
     */
    public function is_available() {
        return $this->claude && $this->claude->is_available();
    }

    /**
     * Get the master system prompt that prevents AI artifacts
     */
    private function get_system_prompt() {
        return <<<SYSTEM
You are a senior career consultant at a top-tier executive search firm (McKinsey, Bain, BCG caliber).
You write polished, professional career documents for finance and consulting professionals.

CRITICAL RULES - NEVER VIOLATE:
1. NEVER include meta-commentary like "I'll help you...", "Here's...", "I've written...", "Let me..."
2. NEVER include phrases like "I'm analyzing", "Based on my analysis", "I understand that..."
3. NEVER start with greetings or sign-offs unless explicitly requested
4. NEVER include placeholder text like "[Insert X here]" or "[Your Name]"
5. NEVER include formatting instructions in output - just the actual content
6. NEVER repeat the same sentence structure or phrase within a document
7. Output ONLY the requested content - no preamble, no explanation, no commentary

QUALITY STANDARDS:
- Every sentence must add unique value
- Use specific, concrete language (not vague or generic)
- Vary sentence structure and length
- Write with confidence but not arrogance
- Sound human, not robotic or templated
- Tailor everything to the SPECIFIC role and company mentioned
SYSTEM;
    }

    /**
     * Build comprehensive context about the job
     */
    private function build_job_context($job_data) {
        $context = [];

        $context[] = "POSITION: {$job_data['title']}";
        $context[] = "COMPANY: {$job_data['company']}";

        if (!empty($job_data['industry'])) {
            $context[] = "INDUSTRY: {$job_data['industry']}";
        }
        if (!empty($job_data['location'])) {
            $context[] = "LOCATION: {$job_data['location']}";
        }
        if (!empty($job_data['department'])) {
            $context[] = "DEPARTMENT: {$job_data['department']}";
        }
        if (!empty($job_data['firm_type'])) {
            $context[] = "FIRM TYPE: {$job_data['firm_type']}";
        }
        if (!empty($job_data['experience_level'])) {
            $context[] = "LEVEL: {$job_data['experience_level']}";
        }
        if (!empty($job_data['salary_range'])) {
            $context[] = "COMPENSATION: {$job_data['salary_range']}";
        }

        // Add key requirements
        if (!empty($job_data['skills']) && is_array($job_data['skills'])) {
            $context[] = "\nKEY SKILLS REQUIRED:\n- " . implode("\n- ", array_slice($job_data['skills'], 0, 8));
        }

        // Add job description excerpt
        if (!empty($job_data['requirements'])) {
            $context[] = "\nJOB REQUIREMENTS (excerpt):\n" . $this->truncate_text($job_data['requirements'], 800);
        } elseif (!empty($job_data['description'])) {
            $context[] = "\nJOB DESCRIPTION (excerpt):\n" . $this->truncate_text($job_data['description'], 800);
        }

        // Add enhanced summary data if available
        if (!empty($job_data['enhanced_summary'])) {
            $enhanced = $job_data['enhanced_summary'];

            if (!empty($enhanced['role_reality'])) {
                $context[] = "\nROLE INSIGHTS:\n" . $this->truncate_text(
                    is_array($enhanced['role_reality']) ? json_encode($enhanced['role_reality']) : $enhanced['role_reality'],
                    300
                );
            }
        }

        return implode("\n", $context);
    }

    /**
     * Build comprehensive context about the candidate
     */
    private function build_candidate_context($user_profile) {
        $context = [];

        $context[] = "CANDIDATE: {$user_profile['name']}";

        if (!empty($user_profile['headline'])) {
            $context[] = "CURRENT POSITION: {$user_profile['headline']}";
        }

        // Experience
        if (!empty($user_profile['experience']) && is_array($user_profile['experience'])) {
            $context[] = "\nEXPERIENCE:";
            foreach (array_slice($user_profile['experience'], 0, 3) as $exp) {
                $role = $exp['title'] ?? 'Unknown Role';
                $company = $exp['company'] ?? 'Unknown Company';
                $duration = '';
                if (!empty($exp['start_date'])) {
                    $end = $exp['end_date'] ?? 'Present';
                    $duration = " ({$exp['start_date']} - {$end})";
                }
                $context[] = "- {$role} at {$company}{$duration}";

                if (!empty($exp['description'])) {
                    $context[] = "  " . $this->truncate_text($exp['description'], 200);
                }
            }
        }

        // Skills
        if (!empty($user_profile['skills']) && is_array($user_profile['skills'])) {
            $context[] = "\nSKILLS: " . implode(', ', array_slice($user_profile['skills'], 0, 10));
        }

        // Education
        if (!empty($user_profile['education']) && is_array($user_profile['education'])) {
            $context[] = "\nEDUCATION:";
            foreach (array_slice($user_profile['education'], 0, 2) as $edu) {
                $degree = $edu['degree'] ?? '';
                $field = $edu['field'] ?? '';
                $school = $edu['school'] ?? 'Unknown Institution';
                $context[] = "- {$degree} {$field}, {$school}";
            }
        }

        // Summary
        if (!empty($user_profile['summary'])) {
            $context[] = "\nPROFESSIONAL SUMMARY:\n" . $this->truncate_text($user_profile['summary'], 400);
        }

        // Career goals if available
        if (!empty($user_profile['career_journey']['goal_description'])) {
            $context[] = "\nCAREER GOAL: " . $user_profile['career_journey']['goal_description'];
        }

        return implode("\n", $context);
    }

    /**
     * Generate Executive Summary for CV
     */
    public function generate_executive_summary($job_data, $user_profile) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);
        $candidate_context = $this->build_candidate_context($user_profile);

        $prompt = <<<PROMPT
Write an executive summary for a CV. This summary appears at the top of the resume and must immediately capture the hiring manager's attention.

{$job_context}

{$candidate_context}

REQUIREMENTS:
- Exactly 3-4 sentences (50-70 words total)
- Written in third person implied (no "I" statements)
- First sentence: Powerful positioning statement connecting their background to this specific role
- Second sentence: Key value proposition with quantifiable achievement if available
- Third sentence: What they uniquely bring that matches this company's needs
- Optional fourth sentence: Forward-looking statement about contribution

EXAMPLE STRUCTURE (do not copy, just follow pattern):
"Results-driven [descriptor] with [X years] experience in [relevant areas]. Proven track record [specific achievement]. Expertise in [skills matching job requirements]. Seeking to leverage [capability] to [benefit for company]."

OUTPUT: Just the summary paragraph, nothing else.
PROMPT;

        return $this->call_claude($prompt, 250, 0.7);
    }

    /**
     * Generate Cover Letter Body
     */
    public function generate_cover_letter($job_data, $user_profile) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);
        $candidate_context = $this->build_candidate_context($user_profile);
        $today = date('F j, Y');

        $prompt = <<<PROMPT
Write the body paragraphs of a cover letter for a senior finance/consulting position. This letter will be reviewed by experienced hiring managers who read hundreds of applications.

{$job_context}

{$candidate_context}

DATE: {$today}

STRUCTURE (exactly 4 paragraphs):

PARAGRAPH 1 - THE HOOK (2-3 sentences):
- Open with genuine, specific interest in THIS role at THIS company
- Reference something specific about the company (recent deal, strategy, culture)
- Connect your background to their needs in one compelling statement
- DO NOT use "I am writing to apply" or "I am excited to apply"

PARAGRAPH 2 - VALUE PROPOSITION (3-4 sentences):
- What unique combination of skills/experience you bring
- How your background directly addresses their requirements
- Include ONE specific, quantified achievement relevant to this role
- Show you understand what success looks like in this position

PARAGRAPH 3 - EVIDENCE & FIT (3-4 sentences):
- Concrete example demonstrating relevant capability
- Show understanding of their business challenges
- Explain why this company specifically (not just any company in the industry)
- Bridge your past success to future contribution

PARAGRAPH 4 - CONFIDENT CLOSE (2 sentences):
- Express enthusiasm for discussing further
- Strong, confident close (not "I hope to hear from you")

STYLE REQUIREMENTS:
- Professional but personable (Goldman Sachs / McKinsey tone)
- Confident without arrogance
- Specific, never generic
- Total: 280-350 words
- Vary sentence structure throughout

FORBIDDEN PHRASES:
- "I am writing to apply"
- "I am excited about this opportunity"
- "I believe I would be a great fit"
- "Please find attached"
- "Thank you for considering"
- "I look forward to hearing from you"

OUTPUT FORMAT:
Return exactly 4 paragraphs separated by blank lines. No greetings, no closings, no signature - just the body paragraphs.
PROMPT;

        return $this->call_claude($prompt, 800, 0.75);
    }

    /**
     * Generate Interview Questions with Guidance
     */
    public function generate_interview_questions($job_data, $user_profile) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);
        $candidate_context = $this->build_candidate_context($user_profile);

        $prompt = <<<PROMPT
Generate 8 likely interview questions for this specific role, with personalized answer guidance for this candidate.

{$job_context}

{$candidate_context}

QUESTION CATEGORIES (include at least one from each):
1. OPENING: "Tell me about yourself" variant tailored to this role
2. MOTIVATION: Why this company/role specifically
3. BEHAVIORAL: Past experience using STAR method
4. TECHNICAL: Role-specific skills/knowledge
5. SITUATIONAL: How they'd handle specific scenarios
6. CHALLENGE: Addressing potential weaknesses or gaps
7. CULTURE FIT: Values and working style
8. CLOSING: Their questions and close

FOR EACH QUESTION PROVIDE:

QUESTION: [The exact question an interviewer would ask - make it specific to this role/company]

WHY THEY ASK: [One sentence on what interviewer is really assessing]

HOW TO ANSWER: [3-4 sentences of specific guidance using THIS candidate's actual background. Reference their specific experience, skills, and achievements. Tell them exactly what example to use and how to structure the response.]

KEY POINTS TO HIT:
- [Specific point 1 from their background]
- [Specific point 2]
- [What to emphasize for this company]

---

Make questions SPECIFIC to {$job_data['company']} and the {$job_data['title']} role. Generic questions like "What's your greatest weakness?" should be reframed for this context.

OUTPUT: Exactly 8 questions in the format above, separated by "---"
PROMPT;

        return $this->call_claude($prompt, 2000, 0.7);
    }

    /**
     * Generate Networking Messages
     */
    public function generate_networking_messages($job_data, $user_profile) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);
        $candidate_context = $this->build_candidate_context($user_profile);

        $prompt = <<<PROMPT
Generate 5 distinct networking/outreach messages for someone pursuing this role. Each message serves a different purpose and should feel natural, not salesy.

{$job_context}

{$candidate_context}

GENERATE THESE 5 MESSAGES:

=== MESSAGE 1: LINKEDIN CONNECTION REQUEST ===
Purpose: Connect with someone at the company (hiring manager, team member, or recruiter)
Constraints: Under 300 characters (LinkedIn limit)
Tone: Professional but warm, not desperate
Must include: Specific reason for connecting related to the role

=== MESSAGE 2: LINKEDIN INMAIL / COLD MESSAGE ===
Purpose: Reach out to someone you're not connected with
Length: 150-200 words
Include: Personal hook, value proposition, soft ask
Avoid: Being pushy or asking for a job directly

=== MESSAGE 3: EMAIL TO HIRING MANAGER ===
Purpose: Direct outreach after applying or instead of applying
Length: 200-250 words
Structure: Hook → Brief background → Why this role → Ask for conversation
Tone: Confident, professional, shows you've done research

=== MESSAGE 4: EMAIL TO RECRUITER ===
Purpose: Engaging with internal or external recruiter about the role
Length: 150-200 words
Focus: Making their job easier, highlighting relevant qualifications concisely
Include: Why you're a strong match, availability

=== MESSAGE 5: REFERRAL REQUEST ===
Purpose: Asking a connection to refer you or introduce you
Length: 200-250 words
Tone: Personal, appreciative, makes it easy for them to help
Include: Context on your relationship, why you're reaching out, specific ask

FOR ALL MESSAGES:
- Use the candidate's actual background and the specific role/company
- Sound human, not templated
- Include specific details that show research
- Never sound desperate or generic
- Provide ready-to-send copy (just needs minor personalization)

OUTPUT FORMAT:
=== MESSAGE 1: LINKEDIN CONNECTION REQUEST ===
[Subject if applicable]
[Message body]

[Continue for all 5 messages]
PROMPT;

        return $this->call_claude($prompt, 2000, 0.75);
    }

    /**
     * Generate Company Intelligence Brief
     */
    public function generate_company_brief($job_data) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);

        $prompt = <<<PROMPT
Generate a comprehensive company intelligence brief for interview preparation. This should give the candidate a competitive edge by providing insights not easily found on the company website.

{$job_context}

SECTIONS TO INCLUDE:

=== COMPANY OVERVIEW ===
- What they do (2-3 sentences, specific)
- Market position and reputation
- Size, structure, key facts

=== BUSINESS MODEL & STRATEGY ===
- How they make money
- Competitive advantages
- Recent strategic moves or pivots
- Growth trajectory

=== CULTURE & VALUES ===
- Working environment and style
- What they value in employees
- Red flags or considerations
- Diversity and inclusion stance

=== RECENT NEWS & DEVELOPMENTS ===
- Major deals, announcements, changes (last 12 months)
- Leadership changes
- Market performance
- Challenges they're facing

=== COMPETITIVE LANDSCAPE ===
- Key competitors
- How they differentiate
- Market threats and opportunities

=== INTERVIEW INTELLIGENCE ===
- Typical interview process for this role type
- What they likely assess for
- Common questions they ask
- How to demonstrate culture fit

=== KEY PEOPLE TO KNOW ===
- Relevant leadership (CEO, relevant VPs)
- Likely interviewers for this role type
- People to research on LinkedIn

=== TALKING POINTS FOR INTERVIEW ===
- 3-4 specific things to mention that show you've done research
- Questions to ask that demonstrate insight
- Topics to avoid

OUTPUT FORMAT:
Use the section headers above with "===" markers. Write in clear, scannable bullet points and short paragraphs. Be specific - if you don't have specific information, indicate what the candidate should research.

Total length: 800-1000 words
PROMPT;

        return $this->call_claude($prompt, 2500, 0.7);
    }

    /**
     * Generate Tailored Experience Bullets
     */
    public function generate_tailored_experience($job_data, $experience_item, $user_profile) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);
        $role = $experience_item['title'] ?? 'Unknown';
        $company = $experience_item['company'] ?? 'Unknown';
        $description = $experience_item['description'] ?? '';

        $prompt = <<<PROMPT
Rewrite these experience bullets to be tailored for the target role. Make achievements more impactful and relevant.

TARGET ROLE:
{$job_context}

EXPERIENCE TO TAILOR:
Role: {$role}
Company: {$company}
Original Description:
{$description}

REQUIREMENTS:
- Write 4-6 bullet points (not more)
- Start each with strong action verb (past tense)
- Include metrics/numbers where possible (estimate if needed)
- Emphasize aspects relevant to the TARGET role
- Each bullet should be 1-2 lines max
- Focus on achievements, not just responsibilities
- Use language that resonates with the target industry

STRONG ACTION VERBS TO USE:
Led, Drove, Delivered, Structured, Executed, Negotiated, Managed, Built, Launched, Optimized, Advised, Analyzed, Transformed, Spearheaded, Achieved

OUTPUT FORMAT:
Return only the bullet points, one per line, starting with "• "
PROMPT;

        return $this->call_claude($prompt, 500, 0.7);
    }

    /**
     * Generate Questions to Ask Interviewer
     */
    public function generate_questions_to_ask($job_data) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);

        $prompt = <<<PROMPT
Generate 8 impressive questions a candidate should ask during an interview for this role. These questions should demonstrate preparation, strategic thinking, and genuine interest.

{$job_context}

QUESTION CATEGORIES:
1. ROLE DEPTH: Understanding day-to-day reality
2. TEAM DYNAMICS: Who they'd work with
3. SUCCESS METRICS: What great looks like
4. GROWTH PATH: Career development
5. COMPANY STRATEGY: Forward-looking insight
6. CHALLENGES: What keeps leadership up at night
7. CULTURE: Working environment reality
8. CLOSING: Strong finish question

REQUIREMENTS:
- Each question must be specific to {$job_data['company']} and the {$job_data['title']} role
- Questions should NOT be easily answered by the website
- Show you've done research without being showy
- Balance between "getting information" and "demonstrating value"
- Avoid cliché questions like "What do you like about working here?"

FOR EACH QUESTION:
- The question itself
- Brief note on why it's impressive / what it reveals

OUTPUT FORMAT:
Q: [Question]
Why: [One sentence on why this is a strong question]

[Repeat for all 8 questions]
PROMPT;

        return $this->call_claude($prompt, 1000, 0.7);
    }

    /**
     * Generate Talking Points
     */
    public function generate_talking_points($job_data, $user_profile) {
        if (!$this->is_available()) {
            return null;
        }

        $job_context = $this->build_job_context($job_data);
        $candidate_context = $this->build_candidate_context($user_profile);

        $prompt = <<<PROMPT
Generate key talking points for this candidate to use throughout their interview process. These should be memorable, specific, and relevant.

{$job_context}

{$candidate_context}

CREATE THESE TALKING POINTS:

=== YOUR STORY (60-second pitch) ===
Write a concise narrative they can use to answer "Tell me about yourself" that positions them perfectly for this role. 4-5 sentences that flow naturally.

=== 3 KEY VALUE PROPOSITIONS ===
What are the 3 most compelling reasons to hire this person for THIS role? For each:
- The core message (one sentence)
- Supporting evidence from their background
- How to phrase it naturally

=== ACHIEVEMENT STORIES (STAR Format) ===
Based on their experience, identify 3 achievements they should definitely mention. For each:
- Situation: Brief context
- Task: What they needed to do
- Action: What they specifically did
- Result: Quantified outcome
- When to use: Which interview questions this answers

=== ADDRESSING POTENTIAL CONCERNS ===
Identify 2-3 potential weaknesses or gaps in their profile for this role, and provide:
- The concern
- How to proactively address or reframe it
- Example language to use

=== MEMORABLE PHRASES ===
Provide 3-4 specific phrases or sentences they can weave into the conversation that will stick with the interviewer. These should highlight their unique value.

OUTPUT: Use the section headers above. Be specific to this candidate and this role.
PROMPT;

        return $this->call_claude($prompt, 1500, 0.75);
    }

    /**
     * Call Claude API with system prompt
     */
    private function call_claude($prompt, $max_tokens = 1000, $temperature = 0.7) {
        if (!$this->is_available()) {
            return null;
        }

        $result = $this->claude->call_api($prompt, array(
            'mode' => 'document_generation',
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'system' => $this->get_system_prompt(),
        ));

        if (isset($result['content'][0]['text'])) {
            $text = trim($result['content'][0]['text']);

            // Clean any residual AI artifacts that might have slipped through
            $text = $this->clean_output($text);

            return $text;
        }

        return null;
    }

    /**
     * Clean output of any AI artifacts
     */
    private function clean_output($text) {
        // Remove common AI preambles
        $patterns = array(
            '/^(Here\'s|Here is|I\'ve|I have|Let me|I\'ll|Sure|Certainly|Of course)[^.]*\.\s*/i',
            '/^(Based on|Looking at|Analyzing|After reviewing)[^.]*\.\s*/i',
            '/\n\n(I hope|Let me know|Feel free|Please let)[^.]*\.?\s*$/i',
            '/^(The following|Below is|Here are)[^:]*:\s*/i',
        );

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        return trim($text);
    }

    /**
     * Truncate text to specified length
     */
    private function truncate_text($text, $max_length) {
        if (strlen($text) <= $max_length) {
            return $text;
        }

        $truncated = substr($text, 0, $max_length);
        $last_space = strrpos($truncated, ' ');

        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }

        return $truncated . '...';
    }

    /**
     * Parse interview questions from Claude response
     */
    public function parse_interview_questions($response) {
        if (empty($response)) {
            return array();
        }

        $questions = array();
        $sections = preg_split('/---+/', $response);

        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;

            $question = array(
                'question' => '',
                'why_asked' => '',
                'how_to_answer' => '',
                'key_points' => array(),
            );

            // Extract question
            if (preg_match('/QUESTION:\s*(.+?)(?=\n\n|WHY|$)/is', $section, $m)) {
                $question['question'] = trim($m[1]);
            }

            // Extract why they ask
            if (preg_match('/WHY THEY ASK:\s*(.+?)(?=\n\n|HOW|$)/is', $section, $m)) {
                $question['why_asked'] = trim($m[1]);
            }

            // Extract how to answer
            if (preg_match('/HOW TO ANSWER:\s*(.+?)(?=\n\n|KEY|$)/is', $section, $m)) {
                $question['how_to_answer'] = trim($m[1]);
            }

            // Extract key points
            if (preg_match('/KEY POINTS[^:]*:\s*(.+?)(?=\n\n|---+|$)/is', $section, $m)) {
                $points_text = trim($m[1]);
                preg_match_all('/[-•]\s*(.+?)(?=\n[-•]|\n\n|$)/s', $points_text, $points);
                if (!empty($points[1])) {
                    $question['key_points'] = array_map('trim', $points[1]);
                }
            }

            if (!empty($question['question'])) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * Parse networking messages from Claude response
     */
    public function parse_networking_messages($response) {
        if (empty($response)) {
            return array();
        }

        $messages = array();
        $sections = preg_split('/===\s*MESSAGE\s*\d+[^=]*===/i', $response);

        $types = array(
            'linkedin_connection',
            'linkedin_inmail',
            'email_hiring_manager',
            'email_recruiter',
            'referral_request',
        );

        $type_index = 0;
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            if ($type_index >= count($types)) break;

            $messages[$types[$type_index]] = array(
                'type' => $types[$type_index],
                'subject' => '',
                'body' => $section,
            );

            // Extract subject if present
            if (preg_match('/Subject:\s*(.+?)(?=\n\n|\n[^S])/i', $section, $m)) {
                $messages[$types[$type_index]]['subject'] = trim($m[1]);
                $messages[$types[$type_index]]['body'] = trim(
                    preg_replace('/Subject:\s*.+?\n/i', '', $section)
                );
            }

            $type_index++;
        }

        return $messages;
    }

    /**
     * Parse company brief sections from Claude response
     */
    public function parse_company_brief($response) {
        if (empty($response)) {
            return array();
        }

        $sections = array();
        $parts = preg_split('/===\s*([^=]+?)\s*===/i', $response, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($parts); $i += 2) {
            $title = trim($parts[$i]);
            $content = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';

            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title));
            $sections[$key] = array(
                'title' => $title,
                'content' => $content,
            );
        }

        return $sections;
    }
}

// Initialize
SFFC_Application_Pack_Claude_Prompts::get_instance();
