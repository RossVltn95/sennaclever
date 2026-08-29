<?php
/**
 * Shared CRM job title normalizer.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Job_Title_Normalizer {

    public static function normalize($title, $company = '', $location = '') {
        $original = sanitize_text_field((string) $title);
        $working = html_entity_decode(wp_strip_all_tags($original), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $working = str_replace(['–', '—'], '-', $working);
        $working = preg_replace('/(?<=[a-z0-9\)])\.(?=[A-Z])/', ' - ', (string) $working);
        $working = preg_replace('/\s+/', ' ', trim((string) $working));

        $signals = [
            'original_title' => $original,
            'title' => $working,
            'changed' => false,
            'cleanup_score' => 0,
            'removed_company' => [],
            'removed_location' => [],
            'nationality_requirement' => [],
            'work_mode' => '',
            'employment_type' => '',
            'salary_text' => '',
            'language_requirements' => [],
            'programme_type' => '',
            'intake_year' => '',
            'removed_noise' => [],
        ];

        if ($working === '') {
            $signals['title'] = '';
            return $signals;
        }

        $working = self::normalize_programme_language($working, $signals);
        $next = preg_replace('/^\s*[A-Z]{2,}\s+Lab\s*[-|]\s*/i', '', (string) $working);
        if ($next !== $working) {
            $signals['removed_company'][] = 'Lab prefix';
            $working = $next;
        }
        $working = self::remove_known_company($working, (string) $company, $signals);
        $working = self::remove_known_locations($working, (string) $location, $signals);
        $working = self::remove_signal_patterns($working, $signals);
        $working = self::protect_and_clean_parentheticals($working, $signals);
        $working = self::finalize_title($working);

        if ($working === '') {
            $working = $original;
        }

        $signals['title'] = sanitize_text_field($working);
        $signals['changed'] = strcasecmp($signals['title'], $original) !== 0;
        $signals['cleanup_score'] = self::calculate_cleanup_score($original, $signals);

        return $signals;
    }

    private static function normalize_programme_language($title, array &$signals) {
        $before = (string) $title;
        if (preg_match('/\b(emirati[sz]ation|emiritisation)\b/i', $before)) {
            $signals['nationality_requirement'][] = 'UAE national';
            $signals['programme_type'] = 'national_programme';
        }

        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+initiative\b/i', 'National Initiative', $before);
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+(graduate\s+)?programme\b/i', 'National $2Programme', (string) $title);
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+(graduate\s+)?program\b/i', 'National $2Programme', (string) $title);
        $title = preg_replace('/\bgraduate\s+program\b/i', 'Graduate Programme', (string) $title);
        $title = preg_replace('/\bprogram\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\bNational Initiative\s*[\/|,-]\s*Graduate Programme\b/i', 'National Initiative Programme', (string) $title);
        $title = preg_replace('/\bNational\s+Graduate\s+Programme\b/i', 'National Graduate Programme', (string) $title);

        if (preg_match('/\b(internship|intern|summer analyst|graduate programme|trainee|national initiative programme|national graduate programme)\b/i', (string) $title, $match)) {
            $signals['programme_type'] = sanitize_key($match[1]);
        }

        return (string) $title;
    }

    private static function remove_known_company($title, $company, array &$signals) {
        $patterns = self::build_company_patterns($company);
        foreach ($patterns as $label => $pattern) {
            $next = preg_replace($pattern, ' ', (string) $title);
            if ($next !== $title) {
                $signals['removed_company'][] = $label;
                $title = $next;
            }
        }

        return (string) $title;
    }

    private static function build_company_patterns($company) {
        $company = trim((string) $company);
        if ($company === '') {
            return [];
        }

        if (self::is_generic_company_label($company)) {
            return [];
        }

        $labels = [$company];
        $root = preg_replace('/\b(?:llc|ltd|limited|plc|inc|corp|corporation|company|co|group|bank|asset management|capital|partners?|holdings?|management|advisors?)\b\.?/i', '', $company);
        $root = preg_replace('/\s+/', ' ', trim((string) $root));
        if ($root !== '' && strlen($root) >= 4 && strcasecmp($root, $company) !== 0) {
            $labels[] = $root;
        }

        $patterns = [];
        foreach (array_unique($labels) as $label) {
            $quoted = preg_quote($label, '/');
            $patterns[$label] = '/(?:^|\b(?:at|with|for)\s+|\s*[-|]\s*)' . $quoted . '(?:\s*[-|]\s*|\b|$)/i';
            $patterns[$label . ' anywhere'] = '/\b' . $quoted . '\b/i';
        }

        return $patterns;
    }

    private static function is_generic_company_label($company) {
        $label = strtolower(trim(preg_replace('/[^a-z0-9&+\/\s-]+/i', ' ', (string) $company)));
        $label = preg_replace('/\s+/', ' ', (string) $label);
        $generic_labels = [
            'accounting',
            'asset management',
            'banking',
            'capital markets',
            'compliance',
            'consulting',
            'corporate finance',
            'finance',
            'financial services',
            'hedge fund',
            'insurance',
            'investment',
            'investment banking',
            'private banking',
            'private credit',
            'private equity',
            'real estate',
            'risk',
            'technology',
            'treasury',
            'venture capital',
            'wealth management',
        ];

        return in_array($label, $generic_labels, true);
    }

    private static function remove_known_locations($title, $location, array &$signals) {
        $locations = [
            'Dubai', 'Abu Dhabi', 'Riyadh', 'Doha', 'United Arab Emirates', 'UAE', 'U.A.E.',
            'Saudi Arabia', 'KSA', 'Qatar', 'Bahrain', 'Kuwait', 'Oman', 'Middle East', 'MENA',
            'London', 'United Kingdom', 'UK', 'Europe', 'Germany',
        ];

        foreach (preg_split('/[,\/|]+/', (string) $location) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $locations[] = $part;
            }
        }

        foreach (array_unique($locations) as $label) {
            if (strlen($label) < 2) {
                continue;
            }

            $quoted = preg_quote($label, '/');
            $patterns = [
                '/(?:^|\s*[-|,\/]\s*)' . $quoted . '(?:\s*[-|,\/]\s*|\s*$)/i',
                '/\s*[\(\[\{]\s*' . $quoted . '\s*[\)\]\}]\s*/i',
            ];

            foreach ($patterns as $pattern) {
                $next = preg_replace($pattern, ' ', (string) $title);
                if ($next !== $title) {
                    $signals['removed_location'][] = $label;
                    $title = $next;
                }
            }
        }

        return (string) $title;
    }

    private static function remove_signal_patterns($title, array &$signals) {
        $patterns = [
            'nationality_requirement' => [
                '/\b(?:for\s+)?((?:uae|u\.a\.e\.|emirati|saudi|ksa|qatar(?:i)?|bahraini|kuwaiti|omani)\s+nationals?)\b/i',
                '/\b((?:uae|u\.a\.e\.|saudi|ksa|qatari|qatar|bahraini|kuwaiti|omani)\s+citizens?)\b/i',
                '/\b(national\s+talent|nationals?\s+only|local\s+national)\b/i',
                '/(?:^|[\s\-|,\/])((?:emirati|saudi|qatari|bahraini|kuwaiti|omani)\s*(?:only)?)\b/i',
            ],
            'work_mode' => [
                '/\b(remote\s+or\s+relocate\s+to\s+[A-Za-z][A-Za-z\s,.]+)\b/i',
                '/\b(relocate\s+to\s+[A-Za-z][A-Za-z\s,.]+)\b/i',
                '/\b(remote|hybrid|on[-\s]?site)\b/i',
            ],
            'employment_type' => ['/\b(full[-\s]?time|part[-\s]?time|permanent|temporary|contract|fixed[-\s]?term)\b/i'],
            'salary_text' => [
                '/((?:[$£€]|AED|SAR|QAR|USD|GBP|EUR)\s?\d[\d,]*(?:\.\d+)?\s*(?:k|m)?(?:\s*[-\/]\s*(?:[$£€]|AED|SAR|QAR|USD|GBP|EUR)?\s?\d[\d,]*(?:\.\d+)?\s*(?:k|m)?)?(?:\s*(?:\/|per\s+)?(?:hour|hr|day|month|year|annum|pa|p\.a\.))?)/i',
                '/(\b\d[\d,]*(?:\.\d+)?\s*(?:k|m)?\s*(?:[-\/]\s*\d[\d,]*(?:\.\d+)?\s*(?:k|m)?)?\s*(?:\/|per\s+)?(?:hour|hr|day|month|year|annum|pa|p\.a\.)\b)/i',
            ],
            'language_requirements' => [
                '/\b((?:english|arabic|french|german|spanish|mandarin|bilingual)\s+(?:speaker|speaking|required|language))\b/i',
                '/\(([^)]*\b(?:english|arabic|french|german|spanish|mandarin|bilingual)\b[^)]*)\)/i',
            ],
            'intake_year' => ['/(?:^|[\s\(\[-])(20\d{2})(?=\s+[A-Z])/i'],
            'removed_noise' => [
                '/\b(rbg|retail banking group|private banking investment advisors?)\b/i',
                '/\b(m\/f\/d|f\/m\/d|m\/w\/d|f\/m\/x|all genders)\b/i',
                '/\b(relocate\s+to)\b/i',
                '/\b(apply now|job details|careers?|external|easy apply|linkedin)\b/i',
                '/\b(?:job\s*)?(?:id|req|requisition|reference)\s*[:#-]?\s*([a-z]{0,4}[-_ ]?\d{2,})\b/i',
                '/\b((?:jr|req|r)[-_ ]?\d{3,})\b/i',
            ],
        ];

        foreach ($patterns as $field => $field_patterns) {
            foreach ($field_patterns as $pattern) {
                if (preg_match_all($pattern, (string) $title, $matches)) {
                    foreach ((array) ($matches[1] ?? []) as $match) {
                        self::record_signal($signals, $field, sanitize_text_field((string) $match));
                    }
                }
                $title = preg_replace($pattern, ' ', (string) $title);
            }
        }

        $title = preg_replace_callback('/(?:^|[\s\-\.])(\d{1,4})(?=[\s\-\.]|$)/', static function ($matches) {
            $number = (string) ($matches[1] ?? '');
            return preg_match('/^(?:9|2|3)$/', $number) ? $matches[0] : ' ';
        }, (string) $title);

        return (string) $title;
    }

    private static function protect_and_clean_parentheticals($title, array &$signals) {
        $protected = ['M&A', 'FP&A', 'ESG', 'IR', 'VC', 'PE', 'AI', 'ML', 'IPO', 'CFA', 'CPA', 'ACA', 'ACCA', 'IFRS'];

        return preg_replace_callback('/\(([^)]{1,40})\)/', static function ($matches) use ($protected, &$signals) {
            $inner = trim((string) ($matches[1] ?? ''));
            foreach ($protected as $term) {
                if (strcasecmp($inner, $term) === 0) {
                    return ' ' . $term . ' ';
                }
            }

            if (preg_match('/^[A-Z]{2,8}$/', $inner)) {
                $signals['removed_noise'][] = sanitize_text_field($inner);
                return ' ';
            }

            return ' ' . $inner . ' ';
        }, (string) $title);
    }

    private static function finalize_title($title) {
        $title = preg_replace('/\s*[\(\[\{]\s*[\)\]\}]\s*/', ' ', (string) $title);
        $title = preg_replace('/\s+([,\|\/])\s+/', '$1 ', (string) $title);
        $title = preg_replace('/\s*[\|\/]\s*/', ' / ', (string) $title);
        $title = preg_replace('/\s+-\s+/', ' - ', (string) $title);
        $title = preg_replace('/(?:\s*[-\/]\s*){2,}/', ' - ', (string) $title);
        $title = preg_replace('/(?:\s*[-,\.\|\/]\s*)+$/', '', (string) $title);
        $title = preg_replace('/^(?:\s*[-,\.\|\/]\s*)+/', '', (string) $title);
        $title = preg_replace('/\b(senior)\s+\1\b/i', '$1', (string) $title);
        $title = preg_replace('/\b(associate)\s+\1\b/i', '$1', (string) $title);
        $title = preg_replace('/\bprogramme\s+programme\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\s+/', ' ', trim((string) $title));

        return (string) $title;
    }

    private static function record_signal(array &$signals, $field, $value) {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        if (in_array($field, ['nationality_requirement', 'language_requirements', 'removed_noise'], true)) {
            $signals[$field][] = $value;
            $signals[$field] = array_values(array_unique($signals[$field]));
            return;
        }

        if ($field === 'salary_text' && $signals[$field] !== '') {
            return;
        }

        $signals[$field] = $value;
    }

    private static function calculate_cleanup_score($original, array $signals) {
        $score = 0;
        foreach (['removed_company', 'removed_location', 'nationality_requirement', 'language_requirements', 'removed_noise'] as $field) {
            $score += count((array) ($signals[$field] ?? [])) * 12;
        }

        foreach (['work_mode', 'employment_type', 'salary_text', 'programme_type', 'intake_year'] as $field) {
            if (!empty($signals[$field])) {
                $score += 10;
            }
        }

        $original_length = max(1, strlen((string) $original));
        $clean_length = strlen((string) ($signals['title'] ?? ''));
        if ($clean_length < $original_length) {
            $score += min(30, (int) round((($original_length - $clean_length) / $original_length) * 100));
        }

        return min(100, $score);
    }
}
