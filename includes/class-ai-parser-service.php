<?php

/**
 * AI Parser Service for CV Analysis
 * 
 * Handles AI-powered CV parsing using OpenAI API
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_AI_Parser_Service
{

    /**
     * OpenAI API Key
     */
    private $api_key;

    /**
     * API endpoint
     */
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    /**
     * Model to use
     */
    private $model = 'gpt-4-turbo-preview';

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('sffc_openai_api_key', '');

        // Allow filtering of model
        $this->model = apply_filters('sffc_ai_parser_model', $this->model);
    }

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Parse CV text using AI
     */
    public function parse_cv_text($text)
    {
        if (empty($this->api_key)) {
            return $this->get_fallback_parse($text);
        }

        try {
            $system_prompt = $this->get_system_prompt();
            $user_prompt = $this->get_user_prompt($text);

            $response = $this->call_openai_api($system_prompt, $user_prompt);

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $parsed_json = json_decode($response['choices'][0]['message']['content'], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsed_json['confidence'] = $this->calculate_confidence($parsed_json);
                    $parsed_json['raw_text'] = $text;
                    return $parsed_json;
                }
            }

            // Fallback if AI parsing fails
            return $this->get_fallback_parse($text);
        } catch (Exception $e) {
            error_log('AI Parser Error: ' . $e->getMessage());
            return $this->get_fallback_parse($text);
        }
    }

    /**
     * Get system prompt for CV parsing
     */
    private function get_system_prompt()
    {
        return "You are an expert CV/Resume parser specializing in finance industry applications. 
        Extract structured information from CVs and return it in JSON format.
        Focus on accuracy and completeness, especially for finance-related roles.
        
        Return the data in this exact JSON structure:
        {
            \"personal\": {
                \"full_name\": \"\",
                \"first_name\": \"\",
                \"last_name\": \"\",
                \"email\": \"\",
                \"phone\": \"\",
                \"location\": \"\",
                \"linkedin\": \"\",
                \"summary\": \"\"
            },
            \"experience\": [
                {
                    \"company\": \"\",
                    \"title\": \"\",
                    \"start_date\": \"\",
                    \"end_date\": \"\",
                    \"current\": false,
                    \"description\": \"\",
                    \"achievements\": []
                }
            ],
            \"education\": [
                {
                    \"degree\": \"\",
                    \"field\": \"\",
                    \"institution\": \"\",
                    \"start_date\": \"\",
                    \"end_date\": \"\",
                    \"gpa\": \"\",
                    \"honors\": []
                }
            ],
            \"skills\": {
                \"technical\": [],
                \"languages\": [],
                \"software\": [],
                \"certifications\": []
            },
            \"achievements\": [],
            \"publications\": [],
            \"references\": []
        }";
    }

    /**
     * Get user prompt with CV text
     */
    private function get_user_prompt($text)
    {
        return "Parse the following CV/Resume and extract all relevant information. 
        Pay special attention to finance-related experience, quantitative skills, and relevant certifications.
        
        CV TEXT:
        " . substr($text, 0, 8000); // Limit text to avoid token limits
    }

    /**
     * Call OpenAI API
     */
    private function call_openai_api($system_prompt, $user_prompt)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ];

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => $user_prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        }

        error_log('OpenAI API Error: HTTP ' . $http_code . ' - ' . $response);
        return null;
    }

    /**
     * Calculate confidence score based on extracted data
     */
    private function calculate_confidence($data)
    {
        $score = 0;
        $max_score = 0;

        // Check personal info (30 points)
        $personal_fields = ['full_name', 'email', 'phone', 'location'];
        foreach ($personal_fields as $field) {
            $max_score += 7.5;
            if (!empty($data['personal'][$field])) {
                $score += 7.5;
            }
        }

        // Check experience (30 points)
        $max_score += 30;
        if (!empty($data['experience']) && count($data['experience']) > 0) {
            $score += min(30, count($data['experience']) * 10);
        }

        // Check education (20 points)
        $max_score += 20;
        if (!empty($data['education']) && count($data['education']) > 0) {
            $score += 20;
        }

        // Check skills (20 points)
        $max_score += 20;
        if (!empty($data['skills'])) {
            $skill_count = count($data['skills']['technical'] ?? []) +
                count($data['skills']['languages'] ?? []) +
                count($data['skills']['software'] ?? []);
            if ($skill_count > 0) {
                $score += min(20, $skill_count * 2);
            }
        }

        return round($score / $max_score, 2);
    }

    /**
     * Enhanced fallback parsing with better pattern matching
     */
    private function get_fallback_parse($text)
    {
        $data = [
            'personal' => [],
            'experience' => [],
            'education' => [],
            'skills' => [
                'technical' => [],
                'languages' => [],
                'software' => [],
                'certifications' => []
            ],
            'achievements' => [],
            'raw_text' => $text,
            'confidence' => 0.3
        ];

        // Extract email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $data['personal']['email'] = $matches[0];
        }

        // Extract phone (multiple formats)
        $phone_patterns = [
            '/\+?1?\s*\(?[0-9]{3}\)?\s*[-.]?\s*[0-9]{3}\s*[-.]?\s*[0-9]{4}/',
            '/\+44\s*[0-9]{2,5}\s*[0-9]{6,8}/',
            '/\([0-9]{3}\)\s*[0-9]{3}-[0-9]{4}/'
        ];

        foreach ($phone_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['personal']['phone'] = trim($matches[0]);
                break;
            }
        }

        // Extract LinkedIn
        if (preg_match('/(?:linkedin\.co\/in\/|linkedin\.co\/pub\/)([a-zA-Z0-9-]+)/', $text, $matches)) {
            $data['personal']['linkedin'] = 'https://linkedin.com/in/' . $matches[1];
        }

        // Extract name (improved logic)
        $lines = array_filter(explode("\n", $text), 'trim');
        if (count($lines) > 0) {
            // Look for name in first few lines
            foreach (array_slice($lines, 0, 5) as $line) {
                $line = trim($line);
                // Skip if contains common non-name elements
                if (preg_match('/@|www\.|http|[0-9]{4,}|curriculum|vitae|resume/i', $line)) {
                    continue;
                }
                // Check if likely a name (2-4 words, mostly letters)
                if (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3}$/', $line)) {
                    $data['personal']['full_name'] = $line;
                    $name_parts = explode(' ', $line);
                    $data['personal']['first_name'] = $name_parts[0];
                    $data['personal']['last_name'] = end($name_parts);
                    break;
                }
            }
        }

        // Extract location
        $location_patterns = [
            '/(?:Location|Address|Based in|City)[:\s]+([A-Za-z\s,]+)(?:\n|$)/i',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*),\s*([A-Z]{2})\s+[0-9]{5}/',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*),\s*([A-Z][a-z]+)(?:\n|$)/'
        ];

        foreach ($location_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['personal']['location'] = trim($matches[1]);
                break;
            }
        }

        // Extract experience sections
        $experience_sections = [];
        $exp_patterns = [
            '/(?:EXPERIENCE|EMPLOYMENT|WORK HISTORY|PROFESSIONAL EXPERIENCE)(.*?)(?:EDUCATION|SKILLS|CERTIFICATIONS|$)/si',
            '/(?:Experience)(.*?)(?:Education|Skills|$)/si'
        ];

        foreach ($exp_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $exp_text = $matches[1];

                // Try to parse individual experiences
                $job_pattern = '/([A-Z][^|]*?)\s*(?:\||at|@)\s*([A-Z][^|]*?)(?:\||[0-9]{4}|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/';
                if (preg_match_all($job_pattern, $exp_text, $job_matches, PREG_SET_ORDER)) {
                    foreach ($job_matches as $job) {
                        $experience_sections[] = [
                            'title' => trim($job[1]),
                            'company' => trim($job[2]),
                            'description' => '',
                            'start_date' => '',
                            'end_date' => '',
                            'current' => false
                        ];
                    }
                }
                break;
            }
        }

        if (!empty($experience_sections)) {
            $data['experience'] = array_slice($experience_sections, 0, 5);
        }

        // Extract education
        $education_sections = [];
        $edu_patterns = [
            '/(?:EDUCATION|ACADEMIC|QUALIFICATIONS)(.*?)(?:EXPERIENCE|SKILLS|CERTIFICATIONS|$)/si',
            '/(?:Education)(.*?)(?:Experience|Skills|$)/si'
        ];

        foreach ($edu_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $edu_text = $matches[1];

                // Look for degrees
                $degree_patterns = [
                    '/(Bachelor|Master|PhD|Ph\.D\.|MBA|BSc|MSc|BA|MA|BBA|MS|BS)(?:\'s)?(?:\s+(?:of|in))?\s+([A-Za-z\s]+?)(?:\s*[,\n]|\s+from)/i',
                    '/(B\.[A-Z][a-z]+|M\.[A-Z][a-z]+)\s+([A-Za-z\s]+?)(?:\s*[,\n])/i'
                ];

                foreach ($degree_patterns as $deg_pattern) {
                    if (preg_match_all($deg_pattern, $edu_text, $deg_matches, PREG_SET_ORDER)) {
                        foreach ($deg_matches as $degree) {
                            $education_sections[] = [
                                'degree' => trim($degree[1]),
                                'field' => trim($degree[2]),
                                'institution' => '',
                                'start_date' => '',
                                'end_date' => ''
                            ];
                        }
                    }
                }
                break;
            }
        }

        if (!empty($education_sections)) {
            $data['education'] = array_slice($education_sections, 0, 3);
        }

        // Extract skills
        $skills_pattern = '/(?:SKILLS|TECHNICAL SKILLS|CORE COMPETENCIES)(.*?)(?:EXPERIENCE|EDUCATION|CERTIFICATIONS|$)/si';
        if (preg_match($skills_pattern, $text, $matches)) {
            $skills_text = $matches[1];

            // Common technical skills for finance
            $tech_skills = ['Python', 'R', 'SQL', 'Excel', 'VBA', 'MATLAB', 'Java', 'C\+\+', 'JavaScript', 'SAS', 'SPSS', 'Tableau', 'Power BI'];
            foreach ($tech_skills as $skill) {
                if (preg_match('/\b' . $skill . '\b/i', $skills_text)) {
                    $data['skills']['technical'][] = $skill;
                }
            }

            // Common software for finance
            $software = ['Bloomberg Terminal', 'Reuters', 'FactSet', 'Capital IQ', 'Morningstar', 'QuickBooks', 'SAP', 'Oracle'];
            foreach ($software as $sw) {
                if (stripos($skills_text, $sw) !== false) {
                    $data['skills']['software'][] = $sw;
                }
            }
        }

        // Extract certifications
        $cert_patterns = [
            '/(?:CFA|FRM|PMP|CPA|ACCA|CAIA|CMT|CIPM)(?:\s+Level\s+[IVX123])?/i',
            '/(?:Series\s+(?:7|63|65|66|79|86|87))/i'
        ];

        foreach ($cert_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $cert_matches)) {
                foreach ($cert_matches[0] as $cert) {
                    $data['skills']['certifications'][] = trim($cert);
                }
            }
        }

        // Update confidence based on what was extracted
        $extracted_count = 0;
        if (!empty($data['personal']['email'])) $extracted_count++;
        if (!empty($data['personal']['phone'])) $extracted_count++;
        if (!empty($data['personal']['full_name'])) $extracted_count++;
        if (!empty($data['experience'])) $extracted_count += 2;
        if (!empty($data['education'])) $extracted_count += 2;
        if (!empty($data['skills']['technical'])) $extracted_count++;

        $data['confidence'] = min(0.8, 0.3 + ($extracted_count * 0.1));

        return $data;
    }
}
