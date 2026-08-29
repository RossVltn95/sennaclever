<?php
/**
 * Deterministic CRM job description formatter.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH') && !defined('SFFC_CRM_JOB_DESCRIPTION_FORMATTER_CLI')) {
    exit;
}

class SFFC_CRM_Job_Description_Formatter {

    private $context = [];
    private $seen_lines = [];

    public function format(array $context) {
        $this->context = $context;
        $this->seen_lines = [];

        $raw_content = trim((string) ($context['content'] ?? ''));
        if ($raw_content === '') {
            return [
                'content' => '',
                'confidence' => 0,
                'sections' => [],
            ];
        }

        $lines = $this->extract_lines($raw_content);
        if (count($lines) < 4) {
            return [
                'content' => '',
                'confidence' => 0,
                'sections' => [],
            ];
        }

        $sections = $this->build_sections($lines);
        $sections = $this->repair_sections($sections);
        $html = $this->render_sections($sections);
        $confidence = $this->score_sections($sections, $html);

        return [
            'content' => $html,
            'confidence' => $confidence,
            'sections' => $sections,
        ];
    }

    private function extract_lines($content) {
        $content = $this->ensure_valid_utf8((string) $content);
        $content = preg_replace('/<\s*h[1-6][^>]*>/i', "\n", $content);
        $content = preg_replace('/<\s*\/\s*h[1-6]\s*>/i', "\n", $content);
        $content = preg_replace('/<\s*li[^>]*>/i', "\n- ", $content);
        $content = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $content);
        $content = preg_replace('/<\s*(br|p|div|section|article|tr|td|th)\b[^>]*>/i', "\n", $content);
        $content = preg_replace('/<\s*\/\s*(p|div|section|article|tr|td|th)\s*>/i', "\n", $content);
        $content = $this->ensure_valid_utf8(html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $content = str_replace(
            ["\xC2\xA0", '–', '—', '•', '·', '¥', '�', 'Ð', 'Õ', 'É', '‚Äôs', '‚Äô', '‚Äú', '‚Äù', '‚Äì', '‚Äî'],
            [' ', '-', '-', '-', '-', '-', '', '-', "'", '', "'", "'", '"', '"', '-', '-'],
            $content
        );
        $content = $this->insert_inline_breaks($content);

        $raw_lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = [];

        foreach ($raw_lines as $raw_line) {
            foreach ($this->split_long_line($raw_line) as $candidate_line) {
                foreach ($this->split_compound_line($candidate_line) as $line) {
                    $line = trim(preg_replace('/\s+/', ' ', (string) $line));
                    $line = trim($line, " \t\n\r\0\x0B|");
                    if ($line === '' || $this->is_noise_line($line)) {
                        continue;
                    }

                    $normalized = strtolower($line);
                    if (isset($this->seen_lines[$normalized])) {
                        continue;
                    }
                    $this->seen_lines[$normalized] = true;
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    private function ensure_valid_utf8($content) {
        $content = (string) $content;

        if ($content === '') {
            return '';
        }

        if (function_exists('wp_check_invalid_utf8')) {
            return wp_check_invalid_utf8($content, true);
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $converted = function_exists('iconv') ? @iconv('Windows-1252', 'UTF-8//IGNORE', $content) : false;
            if (!is_string($converted) || $converted === '') {
                $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
            }
            if (is_string($converted) && $converted !== '') {
                $content = $converted;
            }
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $content);
        if (is_string($clean)) {
            $content = $clean;
        }

        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        return str_replace(['¥', 'Ð', 'Õ', 'É', '‚Äôs', '‚Äô', '‚Äú', '‚Äù', '‚Äì', '‚Äî'], ['-', '-', "'", '', "'", "'", '"', '"', '-', '-'], $content);
    }

    private function insert_inline_breaks($content) {
        $labels = [
            'Employer',
            'Job Title',
            'Business Unit',
            'Department',
            'Reports To',
            'Position',
            'Position Title',
            'Employment Type',
            'Salary',
            'Compensation',
            'Job Location',
            'Work Location',
            'Location',
            'About the Client',
            'Job Description',
            'Job Description Summary',
            'Programme Description',
            'Program Description',
            'Qualifications',
            'Requirements',
            'What’s Required',
            "What's Required",
            'Key Responsibilities',
            'Key Responsibilities Include',
            'Responsibilities',
            'Responsibilities will consist of',
            'Roles & Responsibilities',
            'Responsibilities may include',
            'Primary responsibilities will include',
            'Role Overview',
            'Role and Responsibilities',
            'Role Description',
            'Role',
            'About The Role',
            'About You',
            'Your Responsibilities',
            'Your Qualifications',
            'What We Offer',
            'We Also Offer',
            'What You’ll Be Doing',
            'What You’ll Bring',
            'What You Will Bring',
            'What You Will Do As An Associate',
            'What qualifications or skills should you possess in this role',
            'What makes you a successful candidate',
            'A bit about the job',
            'How you’ll spend your time',
            "How you'll spend your time",
            'The difference you’ll make',
            "The difference you'll make",
            'In this role you will',
            "Skills and experience we're looking for",
            'Skills and experience we’re looking for',
            'Essential Skills And Experience',
            'Desirable Skills And Experience',
            'Skills and Qualifications',
            'Skills & Qualifications',
            'Skills Desired',
            'Minimum Qualifications',
            'Minimum Experience',
            'Knowledge and Skills',
            'The Candidate',
            'The Process',
            'Process',
            'Application Deadline',
            'Closing date',
            'Special Factors',
            'Where will you be working',
            'Firm Profile',
            'Group Profile',
            'Certified Persons Regulatory Requirements',
            'Internal Applicants',
            'Equal opportunities statement',
        ];

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $labels)) . ')\s*:\s*/iu';
        $content = preg_replace($pattern, "\n" . '$1' . ":\n", (string) $content);
        $content = preg_replace('/\b(Job Description|Programme Description|Program Description|Requirements|Qualifications|Role Overview|Key Responsibilities|Key Responsibilities Include|Responsibilities|Technical Competencies|Essential Skills And Experience|Desirable Skills And Experience|Skills And Experience|The successful candidate must possess|Applicants must meet the following criteria|What we look for|What We Look For|What You Will Do As An Associate|What makes you a successful candidate)\s+(?=[A-Z][a-z])/u', "\n" . '$1' . "\n", (string) $content);
        $content = preg_replace('/\b(YOUR RESPONSIBILITIES|YOUR QUALIFICATIONS|WHAT WE OFFER\?)\b/u', "\n" . '$1' . "\n", (string) $content);

        return $content;
    }

    private function split_long_line($line) {
        $line = trim((string) $line);
        if ($line === '') {
            return [];
        }

        if (strlen($line) < 220) {
            return [$line];
        }

        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $line) ?: [$line];
        $expanded = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $subparts = preg_split('/\s+(?=(?:You will|You’ll|You are|Ideally you|This is|This role|The successful candidate|Responsibilities include|Requirements include)\b)/iu', $part) ?: [$part];
            foreach ($subparts as $subpart) {
                $subpart = trim((string) $subpart);
                if ($subpart !== '') {
                    $expanded[] = $subpart;
                }
            }
        }

        return empty($expanded) ? [$line] : $expanded;
    }

    private function split_compound_line($line) {
        $line = trim((string) $line);
        if ($line === '') {
            return [];
        }

        $heading_like_prefixes = [
            'Summer Analyst work may include',
            'Off Cycle Intern responsibilities may include',
            'Off Cycle responsibilities may include',
            'Intern responsibilities may include',
            'Responsibilities may include',
            'Responsibilities include',
            'Key Responsibilities include',
            'Key Responsibilities Include',
            'Main responsibilities will include',
            'Principal Accountabilities',
            'Requirements include',
            'The successful candidate must possess',
            'Applicants must meet the following criteria',
            'Candidates must have',
            'What we look for',
            'What We Look For',
            'What you will do as an Associate',
            'What You Will Do As An Associate',
            'What makes you a successful candidate',
        ];

        foreach ($heading_like_prefixes as $prefix) {
            if (stripos($line, $prefix . ':') === 0) {
                $body = trim(substr($line, strlen($prefix) + 1));
                return array_merge([$prefix], $this->split_inline_list($body));
            }
        }

        if (strlen($line) < 170 || preg_match('/^https?:\/\//i', $line)) {
            return [$line];
        }

        $parts = $this->split_inline_list($line);
        return count($parts) > 1 ? $parts : [$line];
    }

    private function split_inline_list($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        $text = preg_replace('/\s*;\s*/', "\n", $text);
        $text = preg_replace('/(?<!\b(?:and|to|or)\s)\s+(?=(?:Financial analysis|Financial modelling|Financial modeling|Investment research|Competitive analysis|Industry and thematic research|Assistance in|Drafting of|Communications with|Strong verbal|Strong written|Strong communication|A demonstrated|A desire|A basic knowledge|Self-motivation|Sincere commitment|Excellent attention|Intellectual curiosity|Good judgement|Perseverance|Contribute to|Currently enrolled|Anticipated graduation|CV must|Available to|Applications close|Applications are reviewed|Support|Analyse|Analyze|Build|Prepare|Conduct|Maintain|Coordinate|Develop|Lead|Monitor|Review|Work closely|Partner with|Assist with|Perform|Generate|Attend|Create|Liaise|Represent|Produce|Own|Translate|Provide|Respond to|Collaborate|Participate|Interact|Draft|Communicate|Mentor|Oversee|Establish|Identify|Underwrite|Execute|Manage|Take ownership|Prepare and present|Present)\b)/iu', "\n", $text);

        $raw_parts = preg_split('/\r\n|\r|\n|(?<=[.!?])\s+(?=[A-Z][a-z])/', $text) ?: [$text];
        $parts = [];
        foreach ($raw_parts as $part) {
            $part = $this->clean_content_line($part);
            if ($part !== '' && strlen($part) > 3 && !$this->is_fragment_line($part)) {
                $parts[] = $part;
            }
        }

        return $this->merge_fragmented_parts($parts);
    }

    private function merge_fragmented_parts(array $parts) {
        $merged = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $last_index = count($merged) - 1;
            $should_merge = false;
            if ($last_index >= 0) {
                $last = $merged[$last_index];
                $should_merge = (bool) preg_match('/\b(?:and|to|or|with|including|across|for|of)\s*$/i', $last);
                $should_merge = $should_merge || (bool) preg_match('/^[a-z]/', $part);
            }

            if ($should_merge) {
                $merged[$last_index] = trim($merged[$last_index] . ' ' . $part);
                continue;
            }

            $merged[] = $part;
        }

        return $merged;
    }

    private function is_fragment_line($line) {
        $line = strtolower(trim((string) $line, " \t\n\r\0\x0B:-."));
        if ($line === '') {
            return true;
        }

        $fragments = [
            'skills and',
            'knowledge and',
            'requirements and',
            'qualifications and',
            'experience and',
            'what you',
            'you will',
            'in this role you will',
            'the successful candidate must',
            'applicants must',
            'minimum qual',
            'preferred skills',
            'preferred qualifications',
            'minimum qualifications',
            'to apply',
        ];

        return in_array($line, $fragments, true);
    }

    private function is_noise_line($line) {
        $clean = trim((string) $line);
        $lower = strtolower($clean);

        if ($clean === '' || $lower === 'description') {
            return true;
        }

        if (preg_match('/^\d{5,}$/', $clean)) {
            return true;
        }

        if (preg_match('/^(full time|full-time|part time|part-time|permanent|contract|temporary|employee|permanent,\s*full time|permanent,\s*full-time)$/i', $clean)) {
            return true;
        }

        if (preg_match('/^(intern|internship|analyst|associate|senior associate|manager|senior manager|vice president|vp|director|executive director|managing director)$/i', $clean)) {
            return true;
        }

        if (preg_match('/^(apply now|save job|share job|view job|job id|job number|reference number|worker type|worker sub type|full-time\/part-time|full time \/ part time|job exempt|level \d+|minimum qual|preferred skills|to apply)$/i', $clean)) {
            return true;
        }

        if (preg_match('/^(employer|job title|business unit|position|department|reports to|level|job type|title|application deadline|salary range|working pattern|closing date|application deadline|job type|salary|compensation|based)\s*:/i', $clean)) {
            return true;
        }

        if (preg_match('/^(equal opportunity|privacy notice|cookie|terms and conditions|similar jobs)$/i', $clean)) {
            return true;
        }

        return false;
    }

    private function build_sections(array $lines) {
        $sections = [
            'role' => [],
            'responsibilities' => [],
            'skills' => [],
            'location' => [],
            'benefits' => [],
            'application' => [],
            'standout' => [],
        ];

        $current = 'role';
        $skip_until_next_heading = false;

        foreach ($lines as $line) {
            $heading = $this->map_heading($line);
            if ($heading !== '') {
                $current = $heading;
                $skip_until_next_heading = $heading === 'company';
                continue;
            }

            if ($skip_until_next_heading) {
                continue;
            }

            if ($this->looks_like_heading($line)) {
                $skip_until_next_heading = $this->is_boilerplate_heading($line);
                continue;
            }

            $target = $this->infer_line_section($line, $current);
            if ($target === 'company') {
                continue;
            }

            foreach ($this->expand_content_items($line, $target) as $item) {
                if ($item === '' || $this->is_boilerplate_sentence($item)) {
                    continue;
                }

                $sections[$target][] = $item;
            }
        }

        return $sections;
    }

    private function expand_content_items($line, $target) {
        $line = $this->clean_content_line($line);
        if ($line === '') {
            return [];
        }

        if (in_array($target, ['responsibilities', 'skills', 'application'], true)) {
            $parts = $this->split_inline_list($line);
            if (count($parts) > 1) {
                return $parts;
            }
        }

        return [$line];
    }

    private function map_heading($line) {
        $key = strtolower(trim($line));
        $key = preg_replace('/\s+/', ' ', $key);
        $key = str_replace(['’', '‘', '…'], ["'", "'", ''], $key);
        $key = trim($key, " :-?.");

        $map = [
            'role' => [
                'about this role', 'about the role', 'the role', 'role', 'your role', 'role overview',
                'job purpose', 'purpose', 'the opportunity', 'what you will do', 'what you’ll do',
                'what will you be doing', 'summary', 'position summary', 'about the job',
                'role description', 'a bit about the job', 'job description', 'introduction',
                'about the team', 'about the opportunity', 'job description summary',
                'what impact can you make in this role',
                'private equity department', 'infrastructure group', 'blackstone private equity',
                'blackstone tactical opportunities', 'tactical opportunities', 'programme description',
                'program description',
            ],
            'responsibilities' => [
            'responsibilities', 'key responsibilities', 'the role & responsibilities',
                'roles & responsibilities', 'roles and responsibilities', 'role and responsibilities', 'role & responsibilities', 'primary responsibilities will include',
                'what you will be responsible for', 'what you will be doing',
                'what will you be doing', 'what you’ll do', "what you'll be doing", 'what you’ll be doing', 'duties', 'main duties',
                'essential functions', 'accountabilities', 'your responsibilities',
                'responsibilities include', "how you'll spend your time", 'how you’ll spend your time',
                'in this role you will', 'in this role you will', "the difference you'll make",
                'the difference you’ll make', 'off cycle intern responsibilities may include',
                'responsibilities will consist of', 'key responsibilities include',
                'what you will do as an associate', 'summer analyst work may include',
            ],
            'skills' => [
                'qualifications', 'requirements', 'what we’re looking for', 'what we are looking for',
                'what we look for',
                'skills required', 'skills', 'skills & experience', 'skills and experience',
                'experience', 'minimum qualifications', 'minimum experience', 'knowledge and skills',
                'what will you bring', "what you'll bring", 'what you’ll bring',
                'about you', 'the person', 'essential', 'desirable',
                'what we value', 'these skills will help you succeed in this role',
                'your qualifications', "skills and experience we're looking for",
                'skills and experience we’re looking for', 'candidate profile', 'competencies',
                'profile', 'requirements include', "what's required", 'what is required',
                'key skills & experience', 'key skills and experience', 'technical competencies',
                'skills & qualifications', 'skills and qualifications',
                "you'll need to have", 'you’ll need to have', 'what it takes',
                'to be successful in this role you will have', 'essential skills and experience',
                'desirable skills and experience', 'the candidate', 'skills and qualifications',
                'skills desired', 'the successful candidate must possess',
                'what qualifications or skills should you possess in this role',
                'what makes you a successful candidate',
            ],
            'location' => [
                'location', 'based', 'key indicators', 'work location', 'where you’ll work',
                'where you will work', 'flexible working', 'hybrid working', 'workplace model',
                'in-office collaboration', 'special factors', 'what else you need to know',
                'flexible working options', 'where will you be working',
            ],
            'benefits' => [
                'benefits', 'our benefits', 'what we offer', 'reward', 'salary', 'salary range',
                'compensation', 'our hybrid work model', 'working at', 'why join us',
                'feel rewarded', 'we take care of our people', 'inclusion, work-life balance and benefits at man group',
                "how we'll reward you", 'how we’ll reward you', 'salary and benefits',
                'attivo also offers', 'about working for us',
                'we also offer a wide-ranging benefits package, which includes',
                'what wedbush offers you',
            ],
            'standout' => [
                'why this role stands out', 'why this role', 'why join this team',
                'why join this role', 'why this opportunity', 'why join us?',
                'you\'ll benefit from', 'you’ll benefit from', 'this role offers',
                'what makes this role different',
            ],
            'application' => [
                'application process', 'how to apply', 'next steps', 'recruitment process',
                'selection process', 'apply today', 'application deadline', 'closing date',
                'what to do next', 'the process', 'requirements',
            ],
            'company' => [
                'about us', 'about the company', 'company description', 'about blackrock',
                'about morgan stanley', 'about state street', 'about goldman sachs',
                'who we are', 'our company', 'about the firm', 'about jefferies',
                'about point72', 'about columbia threadneedle investments', 'about the client',
                'building value that matters', 'firm profile', 'group profile',
                'region', 'business unit', 'department', 'job function', 'reports to',
                'employer', 'job title', 'position', 'position title', 'title',
                'certified persons regulatory requirements', 'internal applicants',
                'equal opportunities statement', 'flexible work statement',
            ],
        ];

        foreach ($map as $section => $headings) {
            foreach ($headings as $heading) {
                if ($key === $heading || ($this->looks_like_heading($line) && strpos($key, $heading . ' ') === 0)) {
                    return $section;
                }
            }
        }

        return '';
    }

    private function looks_like_heading($line) {
        $line = trim((string) $line);
        if (strlen($line) > 110) {
            return false;
        }

        if (preg_match('/^[A-Z][A-Z0-9 &\/,\-:]{3,}$/', $line)) {
            return true;
        }

        if (preg_match('/^[A-Z][A-Za-z0-9 &\/,\-]{2,}:$/', $line)) {
            return true;
        }

        return false;
    }

    private function is_boilerplate_heading($line) {
        return (bool) preg_match('/\b(diversity|equal opportunit|culture|values|our people|company|firm|who we are|recruitment agencies|regulatory|disability|privacy|legal notice|certified persons|internal applicants)\b/i', (string) $line);
    }

    private function infer_line_section($line, $current) {
        $lower = strtolower((string) $line);
        $is_bullet = (bool) preg_match('/^\s*[-*]\s+/', (string) $line);

        if (preg_match('/\b(salary|bonus|benefits|pension|medical|insurance|holiday|holidays|annual leave|wellness|hybrid working|flexible working|compensation|paid leave|paid sick|sick time|time off|tuition reimbursement|401\(k\)|health plan|vision coverage|dental)\b/i', $line) || $this->looks_like_salary_line($line)) {
            return 'benefits';
        }

        if ($this->looks_like_standout_item($line)) {
            return 'standout';
        }

        if (preg_match('/\b(location|based in|remote working|hybrid working|work location|office based|office-based|dubai|abu dhabi|riyadh|doha|london|singapore|glasgow|edinburgh|cardiff|cheltenham|reading|madrid|copenhagen|manchester|birmingham|dublin|luxembourg|paris|frankfurt|milan|zurich|geneva|new york|hong kong|tokyo|mumbai|bengaluru|bangalore|gurugram|pune|toronto|sydney|melbourne)\b/i', $line) && strlen($line) <= 180) {
            if (preg_match('/\b(bar|license|licence|member|membership|standing|admission|juris doctor|law degree|state bar|residency|legal|compensation|salary|pay range|eligible employees|equal opportunit|affirmative action)\b/i', $line)) {
                return 'skills';
            }
            if ($this->is_role_action_item($line)) {
                return 'responsibilities';
            }
            return 'location';
        }

        if (preg_match('/\b(if this sounds like you|please apply|apply online|apply today|application deadline|application process|recruitment process|interview date|interview process|shortlist process|assessment process|background check|reference check)\b/i', $line) && strlen($line) <= 180) {
            return 'application';
        }

        if (preg_match('/\b(ideally you|must have|should have|you will have|you’ll have|familiar with|candidate will have|successful candidate will have|requirements include)\b/i', $line)) {
            return 'skills';
        }

        if (preg_match('/\b(bachelor|master|mba|degree|qualification|qualified|experience|required|knowledge|familiarity|proficiency|skills?|ability to|working knowledge|minimum)\b/i', $line)) {
            return 'skills';
        }

        if (preg_match('/\b(you will|you’ll|this role requires|responsible for|responsibilities include)\b/i', $line)) {
            return 'responsibilities';
        }

        if (preg_match('/\b(manage|maintain|support|lead|prepare|produce|monitor|coordinate|conduct|build|develop|execute|review|analyse|analyze|reconcile|oversee|document|improve|deliver|work with)\b/i', $line)) {
            return 'responsibilities';
        }

        if ($is_bullet && $current === 'role') {
            return 'responsibilities';
        }

        return $current;
    }

    private function looks_like_standout_item($line) {
        $line = trim((string) $line);
        if ($line === '' || strlen($line) > 320) {
            return false;
        }

        if ($this->is_boilerplate_sentence($line) || $this->is_application_or_legal_noise($line)) {
            return false;
        }

        if (preg_match('/\b(salary|bonus|pension|medical|insurance|holiday|annual leave|paid leave|401\(k\)|dental|vision)\b/i', $line)) {
            return false;
        }

        $strong_opportunity = (bool) preg_match('/\b(this role offers|role offers|rare opportunity|unique opportunity|opportunity to (?:help|be|work|develop|gain|shape|influence|build)|direct exposure|broad exposure|meaningful exposure|gain exposure|professional growth|senior investment decision|shape investment|influence investment|capital raising lifecycle|global platform|regional scope|full investment lifecycle|work spans|not simply analyse|not simply analyze|investment outcomes|front end of|fund-ready|blank sheet)\b/i', $line);
        if (!$strong_opportunity) {
            return false;
        }

        if (preg_match('/\b(team player|ability to|skills?|experience|degree|qualification|proficien|knowledge|analytical|communication|attention to detail|organis|organized|commercially minded|collaborative approach)\b/i', $line)
            && !preg_match('/\b(this role offers|role offers|rare opportunity|unique opportunity|opportunity to|direct exposure|broad exposure|meaningful exposure|gain exposure|global platform|regional scope|capital raising lifecycle|full investment lifecycle)\b/i', $line)) {
            return false;
        }

        return true;
    }

    private function clean_content_line($line) {
        $line = trim((string) $line);
        $line = preg_replace('/^(?:[-*]+|[0-9]+[.)])\s*/', '', $line);
        $line = preg_replace('/^\.\s+/', '', (string) $line);
        $line = preg_replace('/\s+/', ' ', $line);
        $line = trim((string) $line, " \t\n\r\0\x0B-");
        if (preg_match('/^(employer|job title|business unit|position|department|reports to|level|job type|title|application deadline|salary range|working pattern|closing date|salary|based)\s*:/i', $line)) {
            return '';
        }
        if ($this->is_fragment_line($line)) {
            return '';
        }
        return $line;
    }

    private function looks_like_salary_line($line) {
        return (bool) preg_match('/^(?:from\s+|up\s+to\s+|salary\s+range:\s*)?(?:£|\x{00A3}|\$|€)\s?\d/iu', trim((string) $line));
    }

    private function is_boilerplate_sentence($line) {
        $lower = strtolower((string) $line);
        $patterns = [
            'equal opportunity employer',
            'equal opportunities employer',
            'we are an equal opportunity',
            'all qualified applicants',
            'privacy policy',
            'terms of use',
            'by applying',
            'internal mobility',
            'internal applicants',
            'employee portal',
            'please note that',
            'we value diversity',
            'reasonable accommodation',
            'background check',
            'employment is contingent',
            'no agencies',
            'follow @',
            'further information is available',
            'for more information, visit',
            'www.',
            'http://',
            'https://',
            'linkedin, x',
            'instagram',
            'duties and responsibilities described here are not exhaustive',
            'additional assignments',
            'assignments, duties',
            'securities licenses',
            'client facing role',
            'marketing blackstone funds',
            'structuring or creating',
            'not the exhaustive list',
            'human resources at',
            'veteran or military',
            'sexual orientation',
            'gender identity',
            'pregnancy',
            'marital',
            'privacy notice',
            'personal data',
            'to submit your application',
            'fields marked',
            'red asterisk',
            'skill farm members are invited',
            'skill farm members are inivited',
            'skill farm experts are invited',
            'invited to apply for the following',
            'unsolicited resumes',
            'fees will not be paid',
            'please do not contact hiring managers',
            'all blackstone employees',
            'required to abide',
            'case-by-case basis',
            'equal opportunities',
            'encourages diversity',
            'encourage applicants',
            'look forward to receiving your application',
            'role postings reflect',
            'compensation details',
            'range displayed',
            'compensation that will be offered',
            'applicable geographic location',
            'your recruiter can share',
            'please include',
            'disability confident',
            'if you need any adjustments',
            'if you require',
            'we promote a working environment',
            'we want all of our candidates',
            'we appreciate that',
            'we are committed to',
            'we partner with charitable',
            'this position may fall in-scope',
            'this policy applies',
            'this role.',
            'attending client meetings',
            'supervising or training',
            'advising on marketing plans',
            'please speak with',
            'where you are discussing',
            'client questions',
            'complete your application',
            'contact human resources',
            'red asterisk',
            'failure to provide',
            'may compromise',
            'securities licensed',
            'obtain certain',
            'required to obtain',
            'sales team or developing',
            'and/or client questions',
            'largest alternative asset manager',
            'assets under management include',
            'assets under management',
            'global investment strategies focused',
            'world’s largest',
            "world's largest",
            'nearly $',
            'over $',
            'x (twitter)',
            'twitter',
            'terms and conditions of employment',
            'without regard to race',
            'without regard to color',
            'without regard to colour',
            'status as a victim',
            'domestic violence',
            'unconscious bias',
            'speculative cv',
            'speculative resume',
            'recruitment agencies',
            'data fairly and lawfully',
            'submit at the bottom',
            'affirmative action employer',
            'equal opportunity / affirmative action',
            'provides equal employment opportunity',
            'compensation that will be offered',
            'compensation for the position',
            'eligible employees with an opportunity',
            'which may vary depending',
            'collective bargaining agreements',
            'hybrid work model',
            'as a new joiner',
            'financial well-being',
            'strengthen the global economy',
            'businesses small and large',
            'finance infrastructure projects',
            'drive progress',
            'personal passions',
            'career growth',
            'one of the largest securities firms',
            'one of the largest investment banks',
            'headquartered in',
            'correspondent offices',
            'innovative financial solutions',
            'employees across',
            'locations around the world',
            'we invest in more than',
            'our prime values',
            'prime stands for',
            'values act as our compass',
            'great place to work',
            'we need to be forward-looking',
            'our mission is to advance',
            'one mission:',
            'we are all connected',
            'role postings reflect',
            'benefits may include',
            'range of employee benefits',
            'positive provocation',
            'we do our best work',
            'we always believe',
            'embrace and celebrate diversity',
            'we have one single attitude',
            "we know it's not easy",
            'we know it’s not easy',
            'we will get it out of you',
            'this mission would not be possible',
            'our smartest investment',
            'welcomed, valued and supported',
            'help them thrive',
            'increasing the impactful moments',
            'performance and innovation',
            'saving for retirement',
            'paying for their children',
            'buying homes',
            'starting businesses',
            'days in the office',
            'work from home',
            'office as a hub',
            'preserve the office',
            'be their best selves',
            'we are a client-centric',
            'working together, in-person',
            'face-to-face collaboration',
            'our people are our greatest strength',
            'every individual contributes',
            'unique perspectives',
            'thrive at work and home',
            'your contribution matters',
            'it’s recognised',
            "it's recognised",
            'it is recognised',
            'internships offer a great way',
            'internship offers a great way',
            'offers a great way to develop',
            'opportunity to work with some of the most talented',
            'providing competitive benefit options',
            'healthy and inclusive foundational work culture',
            'as part of our overall compensation package',
            'offers an array of diverse benefits',
        ];

        foreach ($patterns as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function repair_sections(array $sections) {
        $location = $this->sanitize_location((string) ($this->context['location'] ?? ''));
        if ($location !== '' && empty($sections['location'])) {
            $sections['location'][] = $location;
        }

        $summary_sentence = $this->build_role_summary_sentence();
        $sections['role'] = $this->strip_metadata_items($sections['role'] ?? []);
        $sections['role'] = $this->strip_company_overview_items($sections['role'], $summary_sentence !== '');
        $this->move_role_action_items($sections);
        if ($summary_sentence !== '' && !$this->role_contains_title($sections['role'] ?? [])) {
            array_unshift($sections['role'], $summary_sentence);
        }

        if (empty($sections['role'])) {
            $sections['role'][] = $summary_sentence !== '' ? $summary_sentence : 'The hiring team is reviewing candidates for this role.';
        }

        $this->move_misclassified_location_items($sections);
        $this->move_misclassified_application_items($sections);
        $this->move_misclassified_benefit_and_skill_items($sections);

        foreach ($sections as $key => $items) {
            $sections[$key] = $this->normalize_section_items($items, $key);
        }

        $this->move_misclassified_location_items($sections);
        $this->move_misclassified_application_items($sections);
        $this->move_misclassified_benefit_and_skill_items($sections);

        foreach ($sections as $key => $items) {
            if ($key === 'location') {
                $items = array_values(array_filter(array_map([$this, 'sanitize_location'], $items)));
            }
            $sections[$key] = array_slice($this->unique_items($items), 0, $this->section_limit($key));
        }

        if (empty($sections['role'])) {
            $sections['role'][] = $summary_sentence !== '' ? $summary_sentence : 'The hiring team is reviewing candidates for this role.';
        }

        return $sections;
    }

    private function strip_metadata_items(array $items) {
        $clean = [];
        foreach ($items as $item) {
            if (preg_match('/^(employer|job title|business unit|position|department|reports to|level|job type|title|application deadline|salary range|working pattern)\s*:/i', (string) $item)) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    private function strip_company_overview_items(array $items, $has_role_context) {
        if (empty($items)) {
            return $items;
        }

        $clean = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            if ($this->is_company_overview_item($item, (bool) $has_role_context)) {
                continue;
            }

            $clean[] = $item;
        }

        return $clean;
    }

    private function move_role_action_items(array &$sections) {
        if (empty($sections['role'])) {
            return;
        }

        $role_context = [];
        $responsibilities = [];

        foreach ($sections['role'] as $item) {
            if ($this->is_role_action_item($item)) {
                $responsibilities[] = $item;
                continue;
            }

            $role_context[] = $item;
        }

        if (!empty($responsibilities)) {
            $sections['responsibilities'] = array_merge($sections['responsibilities'] ?? [], $responsibilities);
        }

        $sections['role'] = $role_context;
    }

    private function is_role_action_item($item) {
        $item = trim((string) $item);
        if ($item === '' || strlen($item) > 360) {
            return false;
        }

        if ($this->is_company_overview_item($item, false) || $this->is_application_or_legal_noise($item)) {
            return false;
        }

        if (preg_match('/^(?:Act|Analyse|Analyze|Assist|Build|Collaborate|Conduct|Contribute|Coordinate|Create|Define|Deliver|Develop|Draft|Drive|Ensure|Establish|Execute|Generate|Identify|Interact|Lead|Liaise|Maintain|Manage|Mentor|Monitor|Oversee|Own|Partner|Perform|Prepare|Present|Produce|Provide|Reconcile|Represent|Respond|Review|Run|Support|Take ownership|Translate|Underwrite|Work)\b/i', $item)) {
            return true;
        }

        if (preg_match('/^(?:Analysis|Coordination|Development|Drafting|Elaboration|Execution|Maintenance|Management|Monitoring|Onboarding|Oversight|Preparation|Production|Review|Support)\s+of\b/i', $item)) {
            return true;
        }

        return (bool) preg_match('/\b(?:responsible for|supporting|preparing|managing|reviewing|coordinating|delivering|analysing|analyzing|maintaining|building|developing|working|leading|assisting|providing|executing|drafting|monitoring|producing|owning|presenting|conducting|performing|generating|reconciling|overseeing)\b/i', $item);
    }

    private function has_action_context($item) {
        return (bool) preg_match('/\b(is|are|will|would|seeking|hiring|join|support|work|responsible|looking|offers|provides|focuses|covers|includes|reports|based|invests|advises|manages)\b/i', (string) $item);
    }

    private function is_company_overview_item($item, $allow_role_context = false) {
        $item = trim((string) $item);
        if ($item === '') {
            return false;
        }

        $lower = strtolower($item);
        $hard_company_overview = (
            strpos($lower, 'largest alternative asset manager') !== false ||
            strpos($lower, 'assets under management include') !== false ||
            strpos($lower, 'assets under management') !== false ||
            strpos($lower, 'world’s largest') !== false ||
            strpos($lower, "world's largest") !== false ||
            strpos($lower, 'global investment strategies focused') !== false ||
            strpos($lower, 'further information') !== false ||
            strpos($lower, 'follow @') !== false ||
            strpos($lower, 'our mission') !== false ||
            strpos($lower, 'mission is') !== false ||
            strpos($lower, 'this mission would not be possible') !== false ||
            strpos($lower, 'we are all connected') !== false ||
            strpos($lower, 'financial well-being') !== false ||
            strpos($lower, 'global economy') !== false ||
            strpos($lower, 'career growth') !== false ||
            strpos($lower, 'provides investment strategies') !== false ||
            strpos($lower, 'delivered through a range of') !== false ||
            strpos($lower, 'clients, and the people they serve') !== false ||
            strpos($lower, 'workplace where inclusion') !== false ||
            strpos($lower, 'culture and values') !== false ||
            strpos($lower, 'worldwide media agency network') !== false ||
            strpos($lower, 'the more inclusive we are') !== false ||
            strpos($lower, 'employees across') !== false ||
            strpos($lower, 'locations around the world') !== false ||
            strpos($lower, 'we invest in more than') !== false ||
            strpos($lower, 'our prime values') !== false ||
            strpos($lower, 'prime stands for') !== false ||
            strpos($lower, 'values act as our compass') !== false ||
            strpos($lower, 'great place to work') !== false ||
            strpos($lower, 'we need to be forward-looking') !== false ||
            strpos($lower, 'for more details') !== false ||
            strpos($lower, 'inclusive recruitment process') !== false ||
            strpos($lower, 'avoid unconscious bias') !== false ||
            strpos($lower, 'we commit to an inclusive') !== false ||
            strpos($lower, 'we aim to make our recruitment process') !== false ||
            (bool) preg_match('/\b(manages|manage|managed)\s+(?:over|nearly|approximately|around)?\s*(?:\$|£|€)\s?\d/i', $item)
        );

        if ($hard_company_overview) {
            return true;
        }

        $looks_like_company_overview = (
            strpos($lower, 'leading global') !== false ||
            strpos($lower, 'privately owned company') !== false ||
            strpos($lower, 'we pride ourselves') !== false ||
            strpos($lower, 'our experienced') !== false ||
            strpos($lower, 'our clients have access') !== false ||
            strpos($lower, 'our reach is expansive') !== false ||
            strpos($lower, 'our capability is diverse') !== false ||
            strpos($lower, 'we are a people business') !== false ||
            strpos($lower, 'we recognise that our success') !== false ||
            strpos($lower, 'we provide advisory') !== false
        );

        $is_standalone_label = strlen($item) <= 80
            && !$this->has_action_context($item)
            && (bool) preg_match('/^[A-Z][A-Za-z0-9&.,\'’ \-]+$/u', $item);

        $looks_like_role_context = (bool) preg_match('/\b(role|programme|program|team|division|department|analyst|associate|manager|intern|portfolio|investment|research|private wealth|summer|off cycle|responsibilities|qualifications|transaction|fund|client|product)\b/i', $item);
        if ($allow_role_context && $looks_like_role_context && !$is_standalone_label) {
            return false;
        }

        return $looks_like_company_overview || $is_standalone_label;
    }

    private function is_application_or_legal_noise($item) {
        return (bool) preg_match('/\b(equal opportunit|reasonable accommodation|reasonable adjustment|privacy notice|privacy statement|personal data|disability confident|unsolicited (?:resumes|cvs)|recruitment agenc|fees will not be paid|candidate submitted|without regard to|gender identity|sexual orientation|domestic violence|red asterisk|submit at the bottom|case-by-case basis|securities licen[cs]es|not exhaustive|additional assignments|duties and responsibilities described|required to abide|terms and conditions of employment|failure to provide|may compromise|employment is contingent|background check|please speak with|human resources at|we are committed to providing|all employees and applicants)\b/i', (string) $item);
    }

    private function build_role_summary_sentence() {
        $title = trim((string) ($this->context['role_title'] ?? ''));
        $descriptor = trim((string) ($this->context['company_descriptor'] ?? ''));
        $location = $this->sanitize_location((string) ($this->context['location'] ?? ''));

        if ($title === '') {
            return '';
        }

        $subject = $descriptor !== '' ? ucfirst($descriptor) : 'The hiring team';
        $sentence = $subject . ' is hiring ' . $this->article_for($title) . ' ' . $title;
        if ($location !== '') {
            $sentence .= ' based in ' . $location;
        }

        return rtrim($sentence, '.') . '.';
    }

    private function sanitize_location($location) {
        $location = trim((string) $location);
        if ($location === '') {
            return '';
        }

        $location = preg_replace('/^(?:location|work location|job location|based|address|role address)\s*:\s*/i', '', $location);
        $location = preg_replace('/^\s*[:\-|]+\s*/', '', (string) $location);
        $location = preg_replace('/\s+/', ' ', (string) $location);
        $location = trim((string) $location, " \t\n\r\0\x0B,.;:-");

        $known_places = [
            'Dubai',
            'Abu Dhabi',
            'Riyadh',
            'Doha',
            'London',
            'Singapore',
            'New York',
            'Hong Kong',
            'Tokyo',
            'Paris',
            'Frankfurt',
            'Milan',
            'Madrid',
            'Copenhagen',
            'Glasgow',
            'Edinburgh',
            'Cardiff',
            'Cheltenham',
            'Reading',
        ];

        foreach ($known_places as $place) {
            if (preg_match('/\b' . preg_quote($place, '/') . '\b/i', $location)) {
                return $place;
            }
        }

        if (strlen($location) > 90 && preg_match('/\b(?:United States?|United Kingdom|UAE|Saudi Arabia|Singapore)\b/i', $location, $match)) {
            return $match[0];
        }

        return $location;
    }

    private function role_contains_title(array $items) {
        $title = strtolower(trim((string) ($this->context['role_title'] ?? '')));
        if ($title === '') {
            return true;
        }

        foreach ($items as $item) {
            if (strpos(strtolower((string) $item), $title) !== false) {
                return true;
            }
        }

        return false;
    }

    private function move_misclassified_application_items(array &$sections) {
        foreach (['skills', 'responsibilities', 'benefits'] as $source) {
            $remaining = [];
            foreach ($sections[$source] ?? [] as $item) {
                if ($this->is_noise_line($item) || $this->is_fragment_line($item)) {
                    continue;
                }
                if (preg_match('/\b(applications? (?:close|are reviewed)|application deadline|cv must|anticipated graduation|available to intern|please apply|apply online|interview date|recruitment process)\b/i', (string) $item)) {
                    $sections['application'][] = $item;
                    continue;
                }
                $remaining[] = $item;
            }
            $sections[$source] = $remaining;
        }
    }

    private function move_misclassified_location_items(array &$sections) {
        $locations = [];
        foreach ($sections['location'] ?? [] as $item) {
            if ($this->is_role_action_item($item)) {
                $sections['responsibilities'][] = $item;
                continue;
            }

            if ($this->looks_like_experience_requirement($item)) {
                $sections['skills'][] = $item;
                continue;
            }

            if ($this->looks_like_benefit_item($item)) {
                $sections['benefits'][] = $item;
                continue;
            }

            $locations[] = $item;
        }
        $sections['location'] = $locations;
    }

    private function move_misclassified_benefit_and_skill_items(array &$sections) {
        foreach (['skills', 'responsibilities', 'standout'] as $source) {
            $remaining = [];
            foreach ($sections[$source] ?? [] as $item) {
                if ($this->looks_like_benefit_item($item)) {
                    $sections['benefits'][] = $item;
                    continue;
                }
                $remaining[] = $item;
            }
            $sections[$source] = $remaining;
        }

        $benefits = [];
        foreach ($sections['benefits'] ?? [] as $item) {
            if ($this->looks_like_experience_requirement($item)) {
                $sections['skills'][] = $item;
                continue;
            }
            $benefits[] = $item;
        }
        $sections['benefits'] = $benefits;
    }

    private function looks_like_benefit_item($item) {
        return (bool) preg_match('/(?:401\s*\(k\)|\b(?:pension|medical|dental|vision|insurance|holiday|annual leave|paid leave|paid time off|flexible time off|private medical|gym|tuition|parental leave|sick pay|life insurance|income protection|wellness|volunteer days|share schemes|cashplan|death in service)\b)/i', (string) $item);
    }

    private function looks_like_experience_requirement($item) {
        $item = (string) $item;
        $benefit_context = preg_replace('/\bbonus points?\b/i', '', $item);
        if (preg_match('/\b(holiday|annual leave|pension|insurance|medical|benefits|salary|bonus|paid leave|time off|wellness)\b/i', (string) $benefit_context)) {
            return false;
        }

        return (bool) preg_match('/(?:\b\d+\s*[-–]\s*\d+\s*years?\b|\b\d+\+?\s*years?\b|\bsome years?\b)\s+(?:of\s+)?(?:relevant\s+)?experience\b|\bexperience\s+(?:in|with|within|from)\b/i', $item);
    }

    private function normalize_section_items(array $items, $section) {
        $normalized = [];

        foreach ($items as $item) {
            $item = $this->clean_content_line($item);
            if ($item === '' || $this->is_noise_line($item) || $this->is_boilerplate_sentence($item) || $this->is_application_or_legal_noise($item)) {
                continue;
            }

            if ($section !== 'role' && $this->is_company_overview_item($item, false)) {
                continue;
            }

            foreach ($this->split_oversized_item($item, (string) $section) as $part) {
                $part = $this->clean_content_line($part);
                if ($part === '' || $this->is_noise_line($part) || $this->is_fragment_line($part) || $this->is_boilerplate_sentence($part) || $this->is_application_or_legal_noise($part)) {
                    continue;
                }

                if ($section !== 'role' && $this->is_company_overview_item($part, false)) {
                    continue;
                }
                $normalized[] = $part;
            }
        }

        return $normalized;
    }

    private function split_oversized_item($item, $section) {
        $item = trim((string) $item);
        if ($item === '') {
            return [];
        }

        $max_length = $section === 'role' ? 520 : 280;
        if (strlen($item) <= $max_length) {
            return [$item];
        }

        $parts = preg_split('/\s*;\s+/', $item) ?: [$item];
        if (count($parts) === 1) {
            $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $item) ?: [$item];
        }
        if (count($parts) === 1 && in_array($section, ['responsibilities', 'skills', 'benefits', 'application'], true)) {
            $parts = preg_split('/\s+(?=(?:including|covering|while|with responsibility for|as well as|together with|and (?:supporting|preparing|managing|reviewing|coordinating|delivering|analysing|analyzing|maintaining|building|developing|working|leading|assisting|providing|executing|drafting|monitoring|producing|owning|presenting)|(?:Support|Analyse|Analyze|Build|Prepare|Conduct|Maintain|Coordinate|Develop|Lead|Monitor|Review|Work closely|Partner with|Assist with|Perform|Generate|Attend|Create|Liaise|Represent|Produce|Own|Translate|Provide|Respond to|Collaborate|Participate|Interact|Draft|Communicate|Mentor|Oversee|Establish|Identify|Underwrite|Execute|Manage|Take ownership|Present)\b))/i', $item) ?: [$item];
        }
        if (count($parts) === 1 && in_array($section, ['responsibilities', 'skills'], true)) {
            $parts = preg_split('/,\s+(?=(?:and\s+)?(?:support|analyse|analyze|build|prepare|conduct|maintain|coordinate|develop|lead|monitor|review|work|partner|assist|perform|generate|attend|create|liaise|represent|produce|own|translate|provide|respond|collaborate|participate|interact|draft|communicate|mentor|oversee|establish|identify|underwrite|execute|manage)\b)/i', $item) ?: [$item];
        }

        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (strlen($part) > $max_length + 120) {
                $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $part) ?: [$part];
                foreach ($sentences as $sentence) {
                    $sentence = trim((string) $sentence);
                    if ($sentence === '') {
                        continue;
                    }

                    if (strlen($sentence) > $max_length + 80) {
                        foreach (preg_split('/\n/', wordwrap($sentence, $max_length, "\n", false)) ?: [$sentence] as $chunk) {
                            $chunk = trim((string) $chunk);
                            if ($chunk !== '') {
                                $clean[] = $chunk;
                            }
                        }
                    } else {
                        $clean[] = $sentence;
                    }
                }
                continue;
            }

            $clean[] = $part;
        }

        return count($clean) > 1 ? $clean : [$item];
    }

    private function article_for($phrase) {
        $phrase = trim((string) $phrase);
        if ($phrase === '') {
            return 'a';
        }

        return preg_match('/^[aeiou]/i', $phrase) ? 'an' : 'a';
    }

    private function unique_items(array $items) {
        $unique = [];
        $seen = [];

        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            $key = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $item));
            $key = trim((string) preg_replace('/\s+/', ' ', (string) $key));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    private function section_limit($key) {
        switch ($key) {
            case 'role':
                return 4;
            case 'responsibilities':
            case 'skills':
                return 12;
            case 'benefits':
                return 8;
            case 'standout':
                return 6;
            case 'location':
            case 'application':
                return 4;
        }

        return 8;
    }

    private function render_sections(array $sections) {
        $html = [];
        $this->append_paragraph_section($html, 'The Role', $sections['role'] ?? []);
        $this->append_list_section($html, 'Key Responsibilities', $sections['responsibilities'] ?? []);
        $this->append_list_section($html, 'Skills & Experience', $sections['skills'] ?? []);
        $this->append_list_section($html, 'Why This Role Stands Out', $sections['standout'] ?? []);
        $this->append_paragraph_section($html, 'Location', $sections['location'] ?? []);
        $this->append_list_section($html, 'Benefits', $sections['benefits'] ?? []);
        $this->append_list_section($html, 'Application Process', $sections['application'] ?? []);

        return trim(implode("\n", $html));
    }

    private function append_paragraph_section(array &$html, $heading, array $items) {
        $items = array_values(array_filter($items));
        if (empty($items)) {
            return;
        }

        $html[] = '<h3>' . esc_html($heading) . '</h3>';
        foreach ($items as $item) {
            $html[] = '<p>' . esc_html($item) . '</p>';
        }
    }

    private function append_list_section(array &$html, $heading, array $items) {
        $items = array_values(array_filter($items));
        if (empty($items)) {
            return;
        }

        $html[] = '<h3>' . esc_html($heading) . '</h3>';
        $html[] = '<ul>';
        foreach ($items as $item) {
            $html[] = '<li>' . esc_html($item) . '</li>';
        }
        $html[] = '</ul>';
    }

    private function score_sections(array $sections, $html) {
        if (trim((string) $html) === '') {
            return 0;
        }

        $score = 0;
        if (!empty($sections['role'])) {
            $score += 20;
        }
        if (count($sections['responsibilities'] ?? []) >= 3) {
            $score += 30;
        }
        if (count($sections['skills'] ?? []) >= 2) {
            $score += 25;
        }
        if (!empty($sections['location'])) {
            $score += 10;
        }
        if (!empty($sections['benefits'])) {
            $score += 5;
        }
        if (strlen(wp_strip_all_tags((string) $html)) >= 450) {
            $score += 10;
        }

        return min(100, $score);
    }
}
