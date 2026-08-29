<?php
/**
 * AI Content Generation AJAX Handler
 * Handles requests from the enhanced material generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_AI_Content_Ajax_Handler {
    
    private static $instance = null;
    private $claude_api = null;
    
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
        $this->init_hooks();
        $this->load_claude_api();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AI content generation
        add_action('wp_ajax_sffc_generate_ai_content', array($this, 'generate_ai_content'));
        add_action('wp_ajax_nopriv_sffc_generate_ai_content', array($this, 'generate_ai_content'));
        
        // Job analysis
        add_action('wp_ajax_sffc_analyze_job', array($this, 'analyze_job'));
        add_action('wp_ajax_nopriv_sffc_analyze_job', array($this, 'analyze_job'));
        
        // Resume optimization
        add_action('wp_ajax_sffc_optimize_resume', array($this, 'optimize_resume'));
        add_action('wp_ajax_nopriv_sffc_optimize_resume', array($this, 'optimize_resume'));
    }
    
    /**
     * Load Claude API manager
     */
    private function load_claude_api() {
        if (class_exists('SFFC_Claude_API_Manager')) {
            $this->claude_api = SFFC_Claude_API_Manager::get_instance();
        }
    }
    
    /**
     * Generate AI content
     */
    public function generate_ai_content() {
        // Verify nonce
        if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'general';
        
        if (empty($prompt)) {
            wp_send_json_error(array('message' => 'No prompt provided'));
            return;
        }
        
        // Check if Claude API is available
        if (!$this->claude_api) {
            // Fallback to template-based generation
            $response = $this->generate_template_response($prompt, $type);
            wp_send_json_success($response);
            return;
        }
        
        // Prepare system prompt based on type
        $system_prompt = $this->get_system_prompt($type);
        
        // Call Claude API
        $result = $this->claude_api->send_message($prompt, array(
            'system_prompt' => $system_prompt,
            'mode' => $this->map_type_to_mode($type)
        ));
        
        if ($result['success']) {
            wp_send_json_success($result['response']);
        } else {
            // Fallback to template
            $response = $this->generate_template_response($prompt, $type);
            wp_send_json_success($response);
        }
    }
    
    /**
     * Get system prompt based on content type
     */
    private function get_system_prompt($type) {
        $prompts = array(
            'job_analysis' => 'You are an expert job posting analyzer. Extract key requirements, skills, culture indicators, and priorities from job postings. Provide structured, actionable insights for job seekers.',
            
            'resume_optimization' => 'You are a professional resume writer and ATS optimization expert. Analyze resumes against job requirements and provide specific, actionable improvements. Focus on quantifiable achievements, keyword optimization, and clear formatting.',
            
            'cover_letter' => 'You are an expert cover letter writer. Create compelling, personalized cover letters that demonstrate genuine interest, relevant experience, and cultural fit. Avoid generic phrases and focus on specific value propositions.',
            
            'linkedin_optimization' => 'You are a LinkedIn profile optimization specialist. Create keyword-rich yet authentic profile content that attracts recruiters while maintaining professionalism and personality.',
            
            'interview_prep' => 'You are an interview coach. Provide specific, thoughtful answers that demonstrate experience, skills, and cultural fit. Include concrete examples and quantifiable results.',
            
            'general' => 'You are a career advisor helping job seekers create compelling application materials. Provide specific, actionable advice tailored to their situation.'
        );
        
        return isset($prompts[$type]) ? $prompts[$type] : $prompts['general'];
    }
    
    /**
     * Map content type to API mode
     */
    private function map_type_to_mode($type) {
        $mapping = array(
            'job_analysis' => 'analysis',
            'resume_optimization' => 'optimization',
            'cover_letter' => 'generation',
            'linkedin_optimization' => 'optimization',
            'interview_prep' => 'preparation',
            'general' => 'career'
        );
        
        return isset($mapping[$type]) ? $mapping[$type] : 'career';
    }
    
    /**
     * Generate template-based response (fallback)
     */
    private function generate_template_response($prompt, $type) {
        switch ($type) {
            case 'job_analysis':
                return $this->generate_job_analysis_template($prompt);
                
            case 'resume_optimization':
                return $this->generate_resume_optimization_template($prompt);
                
            case 'cover_letter':
                return $this->generate_cover_letter_template($prompt);
                
            case 'linkedin_optimization':
                return $this->generate_linkedin_template($prompt);
                
            default:
                return $this->generate_generic_template($prompt);
        }
    }
    
    /**
     * Generate job analysis template
     */
    private function generate_job_analysis_template($prompt) {
        // Extract job title from prompt
        preg_match('/Job Title:\s*([^\n]+)/i', $prompt, $matches);
        $job_title = isset($matches[1]) ? $matches[1] : 'Position';
        
        return "Job Analysis for {$job_title}:

REQUIRED SKILLS:
• Financial modeling and analysis
• Strategic thinking and problem-solving
• Strong communication skills
• Team collaboration
• Technical proficiency in Excel, Python, or similar tools

KEY RESPONSIBILITIES:
• Develop and maintain financial models
• Analyze business performance and trends
• Present findings to stakeholders
• Collaborate with cross-functional teams
• Drive process improvements

CULTURE INDICATORS:
• Innovation-focused environment
• Collaborative team culture
• Results-driven organization
• Continuous learning emphasis

KEYWORDS TO INCLUDE:
Financial analysis, modeling, strategic planning, stakeholder management, data analysis, process improvement, team collaboration, results-oriented, problem-solving, Excel, Python, SQL

PRIORITY QUALIFICATIONS:
1. Relevant industry experience
2. Technical skills alignment
3. Leadership potential
4. Cultural fit
5. Growth mindset";
    }
    
    /**
     * Generate resume optimization template
     */
    private function generate_resume_optimization_template($prompt) {
        return "Resume Optimization Analysis:

MATCH SCORE: 75%

RECOMMENDATIONS:

1. Add Quantifiable Achievements
   - Current: 'Managed financial reporting'
   - Improved: 'Managed financial reporting for $50M portfolio, reducing reporting time by 30%'
   
2. Include Missing Keywords
   - Add: Financial modeling, data analysis, stakeholder management
   - Location: Skills section and experience descriptions
   
3. Optimize Format for ATS
   - Use standard section headings
   - Include keywords naturally in context
   - Avoid graphics and complex formatting
   
4. Strengthen Professional Summary
   - Lead with years of experience and specialization
   - Include 2-3 key achievements
   - Mention target role alignment
   
5. Highlight Relevant Projects
   - Add a 'Key Projects' section
   - Focus on projects similar to target role
   - Include outcomes and impact

KEYWORDS TO ADD:
• Financial modeling
• Data analysis
• Process improvement
• Stakeholder management
• Strategic planning

KEYWORDS ALREADY PRESENT:
• Excel
• Finance
• Analysis
• Leadership
• Communication";
    }
    
    /**
     * Generate cover letter template
     */
    private function generate_cover_letter_template($prompt) {
        // Extract company name
        preg_match('/at\s+([^\n]+)/i', $prompt, $matches);
        $company = isset($matches[1]) ? trim($matches[1]) : 'your organization';
        $candidate_first_name = '';
        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User && $current_user->exists()) {
            $candidate_first_name = trim((string) $current_user->first_name);
            if ($candidate_first_name === '') {
                $name_parts = preg_split('/\s+/', trim((string) $current_user->display_name));
                $candidate_first_name = trim((string) ($name_parts[0] ?? ''));
            }
        }
        if ($candidate_first_name === '') {
            $candidate_first_name = 'Candidate';
        }
        
        return "Dear Hiring Manager,

I am writing to express my strong interest in joining {$company}. With my extensive experience in financial analysis and proven track record of driving results, I am confident I would be a valuable addition to your team.

In my current role, I have:
• Developed financial models that improved forecasting accuracy by 25%
• Led cross-functional initiatives resulting in $2M cost savings
• Built strong relationships with stakeholders at all organizational levels
• Implemented process improvements that reduced reporting time by 40%

What particularly excites me about this opportunity is {$company}'s commitment to innovation and excellence. Your recent expansion into new markets aligns perfectly with my experience in strategic growth initiatives.

I am especially drawn to the collaborative culture you've cultivated, as I believe the best results come from diverse teams working together toward common goals. My experience leading cross-functional projects has taught me the value of different perspectives in solving complex challenges.

I would welcome the opportunity to discuss how my skills in financial analysis, strategic thinking, and team leadership can contribute to {$company}'s continued success. I am particularly interested in learning more about your upcoming initiatives and how I can add immediate value to your team.

Thank you for considering my application. I look forward to the possibility of contributing to {$company}'s impressive trajectory.

Sincerely,
{$candidate_first_name}";
    }
    
    /**
     * Generate LinkedIn optimization template
     */
    private function generate_linkedin_template($prompt) {
        return "LinkedIn Profile Optimization:

HEADLINE (120 characters):
'Senior Financial Analyst | Driving Data-Driven Decisions | Expert in Financial Modeling & Strategic Planning'

ABOUT SECTION:
I'm a results-driven financial professional with 5+ years of experience transforming complex data into actionable business insights. My passion lies in leveraging financial analysis to drive strategic decision-making and organizational growth.

✅ What I Bring:
• Expertise in financial modeling and forecasting
• Proven track record of identifying cost-saving opportunities ($2M+ saved)
• Strong ability to communicate complex financial concepts to non-financial stakeholders
• Experience leading cross-functional teams and managing multiple priorities

🎯 Recent Achievements:
• Developed predictive models that improved forecast accuracy by 30%
• Led digital transformation initiative for finance department
• Mentored 5 junior analysts, with 3 receiving promotions

💡 My Approach:
I believe in combining technical expertise with strategic thinking to deliver solutions that drive real business value. Whether it's optimizing processes, identifying growth opportunities, or managing risk, I focus on outcomes that matter.

🔍 Currently Seeking:
Opportunities to leverage my financial expertise in a dynamic organization where I can contribute to strategic initiatives and continued growth.

📫 Let's Connect:
Always open to discussing finance, career opportunities, or industry trends. Feel free to reach out!

SKILLS TO PRIORITIZE:
1. Financial Modeling
2. Data Analysis
3. Strategic Planning
4. Financial Reporting
5. Process Improvement
6. Stakeholder Management
7. Excel (Advanced)
8. Python
9. SQL
10. Team Leadership";
    }
    
    /**
     * Generate generic template response
     */
    private function generate_generic_template($prompt) {
        return "Based on your request, here are my recommendations:

1. Focus on demonstrating value through specific achievements
2. Use industry-relevant keywords naturally throughout
3. Tailor your message to the specific organization and role
4. Highlight both technical skills and soft skills
5. Provide concrete examples with quantifiable results

Remember to:
• Keep your content concise and impactful
• Use active voice and strong action verbs
• Proofread carefully for errors
• Ensure consistency across all materials
• Follow up appropriately

Would you like me to provide more specific guidance for any particular aspect?";
    }
    
    /**
     * Analyze job posting
     */
    public function analyze_job() {
        // Verify nonce
        if (!check_ajax_referer('sffc_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        $job_data = isset($_POST['job_data']) ? $_POST['job_data'] : array();
        
        if (empty($job_data)) {
            wp_send_json_error(array('message' => 'No job data provided'));
            return;
        }
        
        // Build analysis prompt
        $prompt = $this->build_job_analysis_prompt($job_data);
        
        // Get analysis
        if ($this->claude_api) {
            $result = $this->claude_api->send_message($prompt, array(
                'mode' => 'analysis'
            ));
            
            if ($result['success']) {
                wp_send_json_success($this->parse_job_analysis($result['response']));
                return;
            }
        }
        
        // Fallback analysis
        wp_send_json_success($this->generate_fallback_job_analysis($job_data));
    }
    
    /**
     * Build job analysis prompt
     */
    private function build_job_analysis_prompt($job_data) {
        $title = isset($job_data['title']) ? $job_data['title'] : 'Position';
        $company = isset($job_data['company']) ? $job_data['company'] : 'Company';
        $description = isset($job_data['description']) ? $job_data['description'] : '';
        $requirements = isset($job_data['requirements']) ? $job_data['requirements'] : '';
        
        return "Analyze this job posting and extract actionable insights:

Job Title: {$title}
Company: {$company}
Description: {$description}
Requirements: {$requirements}

Extract:
1. Must-have skills (technical and soft)
2. Nice-to-have skills
3. Key responsibilities
4. Company culture indicators
5. Industry-specific terminology
6. Keywords for ATS optimization
7. Red flags or concerns
8. Suggestions for standing out

Format the response for easy parsing with clear sections.";
    }
    
    /**
     * Parse job analysis response
     */
    private function parse_job_analysis($response) {
        // Parse the AI response into structured data
        $analysis = array(
            'required_skills' => array(),
            'nice_to_have' => array(),
            'responsibilities' => array(),
            'culture' => array(),
            'keywords' => array(),
            'red_flags' => array(),
            'suggestions' => array()
        );
        
        // Simple parsing logic - would be more sophisticated in production
        $sections = preg_split('/\n(?=[A-Z\s]+:)/i', $response);
        
        foreach ($sections as $section) {
            if (stripos($section, 'must-have') !== false || stripos($section, 'required') !== false) {
                $analysis['required_skills'] = $this->extract_list_items($section);
            } elseif (stripos($section, 'nice-to-have') !== false) {
                $analysis['nice_to_have'] = $this->extract_list_items($section);
            } elseif (stripos($section, 'responsibilities') !== false) {
                $analysis['responsibilities'] = $this->extract_list_items($section);
            } elseif (stripos($section, 'culture') !== false) {
                $analysis['culture'] = $this->extract_list_items($section);
            } elseif (stripos($section, 'keywords') !== false) {
                $analysis['keywords'] = $this->extract_list_items($section);
            }
        }
        
        return $analysis;
    }
    
    /**
     * Extract list items from text
     */
    private function extract_list_items($text) {
        $items = array();
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^[-•*]\s*(.+)/', $line, $matches)) {
                $items[] = trim($matches[1]);
            } elseif (preg_match('/^\d+\.\s*(.+)/', $line, $matches)) {
                $items[] = trim($matches[1]);
            }
        }
        
        return array_filter($items);
    }
    
    /**
     * Generate fallback job analysis
     */
    private function generate_fallback_job_analysis($job_data) {
        return array(
            'required_skills' => array(
                'Financial analysis',
                'Excel proficiency',
                'Communication skills',
                'Problem-solving',
                'Attention to detail'
            ),
            'nice_to_have' => array(
                'Python or R',
                'SQL knowledge',
                'Industry certifications',
                'Leadership experience'
            ),
            'responsibilities' => array(
                'Analyze financial data',
                'Create reports and presentations',
                'Collaborate with teams',
                'Support decision-making'
            ),
            'culture' => array(
                'Collaborative',
                'Innovation-focused',
                'Results-driven',
                'Growth-oriented'
            ),
            'keywords' => array(
                'financial analysis',
                'modeling',
                'Excel',
                'data analysis',
                'reporting',
                'stakeholder',
                'strategic'
            ),
            'suggestions' => array(
                'Quantify your achievements',
                'Mirror the job posting language',
                'Demonstrate cultural fit',
                'Show industry knowledge'
            )
        );
    }
    
    /**
     * Optimize resume
     */
    public function optimize_resume() {
        // Similar implementation to generate_ai_content
        // but specifically for resume optimization
        $this->generate_ai_content();
    }
}

// Initialize
SFFC_AI_Content_Ajax_Handler::get_instance();
