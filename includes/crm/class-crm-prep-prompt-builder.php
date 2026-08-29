<?php
/**
 * CRM Prep Materials Prompt Builder
 * Constructs Claude prompts for cover letters and interview questions
 *
 * @package SennaCareers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Prep_Prompt_Builder {

    /**
     * Build cover letter generation prompt
     *
     * @param array $post_data Post data from database
     * @return string Claude prompt
     */
    public function build_cover_letter_prompt($post_data) {
        $role = $post_data['role_title'] ?? 'the role';
        $company = $post_data['company'] ?? 'the company';
        $sector = $post_data['sector'] ?? '';
        $seniority = $post_data['seniority'] ?? '';
        $content = $post_data['content'] ?? '';
        $keywords = $this->parse_keywords($post_data['keywords'] ?? '');
        $candidate_first_name = trim((string) ($post_data['first_name'] ?? ($post_data['candidate_first_name'] ?? ($post_data['user_first_name'] ?? ''))));
        if ($candidate_first_name === '') {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User && $current_user->exists()) {
                $candidate_first_name = trim((string) $current_user->first_name);
                if ($candidate_first_name === '') {
                    $name_parts = preg_split('/\s+/', trim((string) $current_user->display_name));
                    $candidate_first_name = trim((string) ($name_parts[0] ?? ''));
                }
            }
        }
        if ($candidate_first_name === '') {
            $candidate_first_name = 'Candidate';
        }

        // Extract skills and qualifications from keywords
        $key_requirements = $this->extract_key_requirements($keywords);

        $prompt = "Context:\n";
        $prompt .= "- Target audience: Finance students/interns (NOT experienced professionals)\n";
        $prompt .= "- Tone: Professional but enthusiastic, NOT overly formal\n";
        $prompt .= "- Focus: Learning mindset, relevant coursework, transferable skills\n";
        $prompt .= "- Avoid: 'I am a seasoned professional', generic AI slop\n\n";

        $prompt .= "Job Information:\n";
        $prompt .= "- Role: {$role}\n";
        $prompt .= "- Company: {$company}\n";
        if ($sector) {
            $prompt .= "- Sector: {$sector}\n";
        }
        if ($seniority) {
            $prompt .= "- Seniority: " . $this->format_seniority($seniority) . "\n";
        }
        if (!empty($key_requirements)) {
            $prompt .= "- Key Requirements: " . implode(', ', $key_requirements) . "\n";
        }
        if ($content) {
            $prompt .= "\nJob Description:\n{$content}\n\n";
        }

        $prompt .= "\nInstructions:\n";
        $prompt .= "Write a cover letter for an intern/student application with:\n\n";

        $prompt .= "1. OPENING (2-3 sentences):\n";
        $prompt .= "   - State the specific role and company\n";
        $prompt .= "   - Express SPECIFIC interest based on company's focus/values from JD\n";
        $prompt .= "   - NO generic 'I am writing to apply' - start with why THIS role at THIS company\n\n";

        $prompt .= "2. ACADEMIC & RELEVANT EXPERIENCE (1 paragraph):\n";
        $prompt .= "   - Current studies (mention specific relevant modules if finance-related)\n";
        $prompt .= "   - Previous internships, projects, or part-time work (even if not finance)\n";
        $prompt .= "   - Specific technical skills: Excel, Python, financial modeling, valuation, etc.\n";
        $prompt .= "   - Quantify achievements where possible\n\n";

        $prompt .= "3. WHY THIS ROLE (1 paragraph):\n";
        $prompt .= "   - What SPECIFIC aspects of the role excite you (reference JD specifics)\n";
        $prompt .= "   - What you want to LEARN (not what you already know)\n";
        $prompt .= "   - How your background makes you well-prepared to contribute\n";
        $prompt .= "   - Show you understand what interns actually do (support analysis, research, modeling)\n\n";

        $prompt .= "4. VALUE PROPOSITION (1 paragraph):\n";
        $prompt .= "   - How you can contribute as an intern (be realistic - you're learning)\n";
        $prompt .= "   - Specific examples of relevant work (coursework projects, case competitions, etc.)\n";
        $prompt .= "   - Demonstrate: reliability, attention to detail, eagerness to learn\n";
        $prompt .= "   - Reference company-specific work if mentioned in JD (deal types, sectors, strategies)\n\n";

        $prompt .= "5. CLOSING (2-3 sentences):\n";
        $prompt .= "   - Restate enthusiasm for the opportunity\n";
        $prompt .= "   - Availability (full-time/part-time, dates)\n";
        $prompt .= "   - Professional sign-off\n\n";

        $prompt .= "CRITICAL STYLE REQUIREMENTS:\n";
        $prompt .= "- NO roleplay text like '*clears throat*' or '*puts on professional hat*'\n";
        $prompt .= "- NO AI slop phrases like 'I am writing to express my interest'\n";
        $prompt .= "- NO generic 'fit and culture' talk without specifics\n";
        $prompt .= "- USE concrete examples and numbers where applicable\n";
        $prompt .= "- REFERENCE specific parts of the job description\n";
        $prompt .= "- KEEP it under 400 words\n";
        $prompt .= "- WRITE naturally - like a smart student, not a robot\n";
        $prompt .= "- Sign off with {$candidate_first_name}, not [Your Name]; use placeholders only for unknown education details like [University] or [Degree]\n\n";

        $prompt .= "Example of BAD opening:\n";
        $prompt .= "\"Dear Hiring Manager, I am writing to express my sincere interest in the {$role} position...\"\n\n";

        $prompt .= "Example of GOOD opening:\n";
        $prompt .= "\"The {$role} role at {$company} combines [specific aspects from JD]. During my studies in [relevant field], I've focused on [relevant topics] - making this role an ideal fit for my interests and career direction.\"\n\n";

        $prompt .= "Format the output as clean HTML with proper paragraph tags (<p>). Do NOT include a greeting like 'Dear Hiring Manager' - start directly with the opening paragraph.\n";

        return $prompt;
    }

    /**
     * Build interview questions generation prompt
     *
     * @param array $post_data Post data from database
     * @return string Claude prompt
     */
    public function build_interview_questions_prompt($post_data) {
        $role = $post_data['role_title'] ?? 'the role';
        $company = $post_data['company'] ?? 'the company';
        $sector = $post_data['sector'] ?? 'Finance';
        $seniority = $post_data['seniority'] ?? '';
        $content = $post_data['content'] ?? '';
        $keywords = $this->parse_keywords($post_data['keywords'] ?? '');

        // Extract skills from keywords
        $skills = $this->extract_skills($keywords);

        $prompt = "Context:\n";
        $prompt .= "- Target: Finance students/interns preparing for technical + behavioral interviews\n";
        $prompt .= "- Role: {$role}\n";
        $prompt .= "- Company: {$company}\n";
        $prompt .= "- Sector: {$sector}\n";
        if ($seniority) {
            $prompt .= "- Seniority: " . $this->format_seniority($seniority) . "\n";
        }
        if (!empty($skills)) {
            $prompt .= "- Key Skills Required: " . implode(', ', $skills) . "\n";
        }
        if ($content) {
            $prompt .= "\nJob Description:\n{$content}\n\n";
        }

        $prompt .= "\nGenerate 15-20 interview questions across 4 categories:\n\n";

        $prompt .= "1. TECHNICAL QUESTIONS (8-10 questions):\n";
        $prompt .= "   - Based on required skills from JD\n";
        $prompt .= "   - For PE: LBO mechanics, valuation methods, debt schedules, IRR/MOIC, deal analysis\n";
        $prompt .= "   - For IB: DCF, comps, M&A process, accounting fundamentals\n";
        $prompt .= "   - For Real Estate: NOI, cap rates, development pro formas, debt sizing\n";
        $prompt .= "   - Include basic AND intermediate questions (intern-appropriate)\n";
        $prompt .= "   - Provide brief model answers or key talking points\n\n";

        $prompt .= "2. BEHAVIORAL QUESTIONS (4-5 questions):\n";
        $prompt .= "   - Teamwork, pressure, deadlines, attention to detail\n";
        $prompt .= "   - STAR framework prompts\n";
        $prompt .= "   - Intern-specific: 'Tell me about a time you had to learn something complex quickly'\n\n";

        $prompt .= "3. SECTOR/COMPANY SPECIFIC (3-4 questions):\n";
        $prompt .= "   - Based on company's focus from JD\n";
        $prompt .= "   - Recent deals or themes mentioned in JD\n";
        $prompt .= "   - 'Why {$sector}?' / 'Why {$company}?'\n\n";

        $prompt .= "4. CASE STUDY PREP (1-2 example scenarios):\n";
        $prompt .= "   - Mini case study relevant to sector\n";
        $prompt .= "   - Example framework to approach it\n";
        $prompt .= "   - Key metrics to calculate\n\n";

        $prompt .= "Format:\n";
        $prompt .= "For each question, provide:\n";
        $prompt .= "- The question\n";
        $prompt .= "- [WHY THEY ASK THIS] - context for the candidate\n";
        $prompt .= "- [HOW TO ANSWER] - brief guidance or framework\n";
        $prompt .= "- [KEY POINTS] - 2-3 talking points to include\n\n";

        $prompt .= "Output as clean HTML with:\n";
        $prompt .= "- Each category as <h2>Category Name</h2>\n";
        $prompt .= "- Each question wrapped in <div class=\"sffc-crm-prep-question\">\n";
        $prompt .= "- Question text in <p class=\"sffc-crm-prep-question-text\"><strong>Q: Question here?</strong></p>\n";
        $prompt .= "- Each metadata section in <div class=\"sffc-crm-prep-question-meta\"><strong>WHY THEY ASK THIS</strong><p>Explanation</p></div>\n";
        $prompt .= "- Use proper HTML structure throughout\n\n";

        $prompt .= "Example Output Structure:\n";
        $prompt .= "<h2>Technical Questions</h2>\n";
        $prompt .= "<div class=\"sffc-crm-prep-question\">\n";
        $prompt .= "  <p class=\"sffc-crm-prep-question-text\"><strong>Q: Walk me through how you'd build a basic LBO model from scratch.</strong></p>\n";
        $prompt .= "  <div class=\"sffc-crm-prep-question-meta\"><strong>WHY THEY ASK THIS</strong><p>This is the foundational PE technical question...</p></div>\n";
        $prompt .= "  <div class=\"sffc-crm-prep-question-meta\"><strong>HOW TO ANSWER</strong><p>Use a step-by-step framework...</p></div>\n";
        $prompt .= "  <div class=\"sffc-crm-prep-question-meta\"><strong>KEY POINTS</strong>\n";
        $prompt .= "    <ul>\n";
        $prompt .= "      <li>Start with entry assumptions: purchase price, entry multiple, debt/equity mix</li>\n";
        $prompt .= "      <li>Build 3-statement model projecting cash flows</li>\n";
        $prompt .= "      <li>Calculate returns (IRR and MOIC)</li>\n";
        $prompt .= "    </ul>\n";
        $prompt .= "  </div>\n";
        $prompt .= "</div>\n\n";

        $prompt .= "CRITICAL: Make questions specific to {$sector} and appropriate for {$seniority} level interns/students.\n";

        return $prompt;
    }

    /**
     * Parse keywords JSON
     *
     * @param string $keywords_json
     * @return array
     */
    private function parse_keywords($keywords_json) {
        if (empty($keywords_json)) {
            return [];
        }

        $keywords = json_decode($keywords_json, true);
        return is_array($keywords) ? $keywords : [];
    }

    /**
     * Extract key requirements from keywords
     *
     * @param array $keywords
     * @return array
     */
    private function extract_key_requirements($keywords) {
        $requirements = [];

        foreach ($keywords as $kw) {
            if (in_array($kw['type'] ?? '', ['skill', 'qualification']) && !empty($kw['label'])) {
                $requirements[] = $kw['label'];
            }
        }

        return array_slice($requirements, 0, 10); // Top 10
    }

    /**
     * Extract skills from keywords
     *
     * @param array $keywords
     * @return array
     */
    private function extract_skills($keywords) {
        $skills = [];

        foreach ($keywords as $kw) {
            if (($kw['type'] ?? '') === 'skill' && !empty($kw['label'])) {
                $skills[] = $kw['label'];
            }
        }

        return array_slice($skills, 0, 8); // Top 8
    }

    /**
     * Format seniority level
     *
     * @param string $seniority
     * @return string
     */
    private function format_seniority($seniority) {
        $map = [
            'intern' => 'Intern / Off-cycle',
            'analyst' => 'Analyst',
            'senior_analyst' => 'Senior Analyst',
            'associate' => 'Associate',
            'senior_associate' => 'Senior Associate',
            'vp' => 'Vice President / Principal',
            'senior_vp' => 'Senior Vice President',
            'director' => 'Director',
            'md' => 'Managing Director',
            'partner' => 'Partner',
            'c_level' => 'C-Level / Head of Function',
            'board' => 'Board / Advisor',
            'junior' => 'Junior / Entry-Level',
            'mid' => 'Mid-Level',
            'senior' => 'Senior',
            'lead' => 'Lead',
            'principal' => 'Principal'
        ];

        return $map[$seniority] ?? ucfirst($seniority);
    }
}
