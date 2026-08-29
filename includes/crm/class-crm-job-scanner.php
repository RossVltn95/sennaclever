<?php
/**
 * CRM Job Scanner
 * Extracts raw job fields from URLs or pasted text before editorial approval.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Job_Scanner {

    public function scan(array $input) {
        $source_url = esc_url_raw((string) ($input['source_url'] ?? ''));
        $pasted_text = trim((string) ($input['raw_content'] ?? ''));
        $source_platform = sanitize_text_field((string) ($input['source_platform'] ?? ''));

        $html = '';
        $fetch_error = '';
        $status_code = 0;
        $used_url = $source_url;

        if ($source_url !== '') {
            $fetched = $this->fetch_url($source_url);
            $html = (string) ($fetched['html'] ?? '');
            $fetch_error = (string) ($fetched['error'] ?? '');
            $status_code = (int) ($fetched['status_code'] ?? 0);
            $used_url = (string) ($fetched['used_url'] ?? $source_url);
        }

        $json_ld = $html !== '' ? $this->extract_jobposting_json_ld($html) : [];
        $meta = $html !== '' ? $this->extract_meta_payload($html) : [];
        $body_text = $html !== '' ? $this->extract_text($html) : '';
        $raw_content = $pasted_text !== '' ? $pasted_text : $body_text;

        $payload = $this->merge_payloads($json_ld, $meta, [
            'source_url' => $source_url,
            'used_url' => $used_url,
            'status_code' => $status_code,
            'source_platform' => $source_platform !== '' ? $source_platform : $this->infer_platform($used_url),
            'raw_content' => $raw_content,
        ]);

        if (trim((string) ($payload['raw_title'] ?? '')) === '' && $raw_content !== '') {
            $payload['raw_title'] = $this->guess_title_from_text($raw_content);
        }
        $payload['original_title'] = $payload['original_title'] ?: sanitize_text_field((string) ($payload['raw_title'] ?? ''));

        $payload['application_url'] = $payload['application_url'] ?: $this->extract_application_url($html, $source_url);
        $payload['application_url'] = $this->resolve_url((string) $payload['application_url'], $source_url);
        $payload['raw_company_logo'] = $payload['raw_company_logo'] ?: $this->build_logo_fallback($used_url);
        $payload['raw_location'] = $payload['raw_location'] ?: $this->detect_location($raw_content . ' ' . ($payload['raw_title'] ?? ''));
        $title_cleanup = $this->normalize_title($payload['raw_title'] ?? '', $payload['raw_company'] ?? '', $payload['raw_location'] ?? '');
        $payload['raw_title'] = $title_cleanup['title'] ?? ($payload['raw_title'] ?? '');
        $payload['title_cleanup'] = $title_cleanup;
        $payload['raw_sector'] = $this->detect_sector(($payload['raw_title'] ?? '') . ' ' . $raw_content);
        $payload['raw_seniority'] = $this->detect_seniority($payload['raw_title'] ?? '', $raw_content);
        $payload['raw_experience_years'] = $this->detect_experience_years($raw_content);
        $payload['confidence_score'] = $this->score_payload($payload);

        if ($fetch_error !== '') {
            $payload['error_message'] = $fetch_error;
            if ($raw_content === '') {
                $payload['status'] = 'failed';
            }
        }

        return $payload;
    }

    private function fetch_url($url) {
        if (!$this->is_public_http_url($url)) {
            return [
                'used_url' => $url,
                'error' => __('Only public HTTP/HTTPS URLs can be scanned.', 'senna-finance'),
            ];
        }

        $response = wp_safe_remote_get($url, [
            'timeout' => 18,
            'redirection' => 4,
            'limit_response_size' => 1024 * 1024,
            'user-agent' => 'MENA Careers Job Scanner/1.0; ' . home_url('/'),
        ]);

        if (is_wp_error($response)) {
            return [
                'used_url' => $url,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 400 || trim($body) === '') {
            return [
                'used_url' => $url,
                'status_code' => $status_code,
                'error' => sprintf(__('Source returned HTTP %d.', 'senna-finance'), $status_code),
            ];
        }

        return [
            'used_url' => $url,
            'status_code' => $status_code,
            'html' => $body,
        ];
    }

    private function is_public_http_url($url) {
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return true;
    }

    private function extract_jobposting_json_ld($html) {
        $payload = [];

        if (!preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', (string) $html, $matches)) {
            return $payload;
        }

        foreach ((array) $matches[1] as $json) {
            $decoded = json_decode(html_entity_decode(trim((string) $json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            $job = $this->find_jobposting_node($decoded);
            if (!$job) {
                continue;
            }

            $payload['raw_title'] = sanitize_text_field((string) ($job['title'] ?? ''));
            $payload['original_title'] = $payload['raw_title'];
            $payload['raw_company'] = $this->extract_organization_name($job['hiringOrganization'] ?? '');
            $payload['raw_content'] = wp_kses_post((string) ($job['description'] ?? ''));
            $payload['application_url'] = esc_url_raw((string) ($job['url'] ?? ''));
            $payload['external_job_id'] = $this->extract_identifier($job['identifier'] ?? '');
            $payload['raw_salary_text'] = $this->extract_salary_text($job['baseSalary'] ?? null);
            $payload['raw_location'] = $this->extract_job_location($job['jobLocation'] ?? null);
            $payload['raw_company_logo'] = $this->extract_organization_logo($job['hiringOrganization'] ?? '');
            $payload['raw_posted_at'] = sanitize_text_field((string) ($job['datePosted'] ?? ''));
            $payload['posted_at'] = $this->normalize_posted_at($payload['raw_posted_at']);
            break;
        }

        return array_filter($payload, static function ($value) {
            return $value !== '' && $value !== null;
        });
    }

    private function find_jobposting_node($decoded) {
        if (!is_array($decoded)) {
            return null;
        }

        $type = $decoded['@type'] ?? '';
        if (is_array($type)) {
            $type = implode(' ', $type);
        }
        if (is_string($type) && stripos($type, 'JobPosting') !== false) {
            return $decoded;
        }

        foreach (['@graph', 'itemListElement'] as $key) {
            if (!empty($decoded[$key]) && is_array($decoded[$key])) {
                foreach ($decoded[$key] as $node) {
                    $found = $this->find_jobposting_node($node);
                    if ($found) {
                        return $found;
                    }
                }
            }
        }

        foreach ($decoded as $node) {
            if (is_array($node)) {
                $found = $this->find_jobposting_node($node);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function extract_meta_payload($html) {
        $title = $this->extract_title($html);
        $description = $this->extract_meta($html, 'description');
        $og_title = $this->extract_meta($html, 'og:title', 'property');
        $og_description = $this->extract_meta($html, 'og:description', 'property');
        $site_name = $this->extract_meta($html, 'og:site_name', 'property');
        $og_image = $this->extract_meta($html, 'og:image', 'property');
        $posted_at = $this->extract_meta($html, 'datePosted')
            ?: $this->extract_meta($html, 'article:published_time', 'property')
            ?: $this->extract_meta($html, 'article:modified_time', 'property');

        $content = trim(implode("\n\n", array_filter([$og_description, $description])));

        return array_filter([
            'raw_title' => sanitize_text_field((string) ($og_title ?: $title)),
            'original_title' => sanitize_text_field((string) ($og_title ?: $title)),
            'raw_company' => $site_name,
            'raw_content' => $content,
            'raw_company_logo' => $og_image,
            'raw_posted_at' => sanitize_text_field((string) $posted_at),
            'posted_at' => $this->normalize_posted_at($posted_at),
        ], static function ($value) {
            return $value !== '';
        });
    }

    private function merge_payloads(array ...$payloads) {
        $merged = [
            'source_url' => '',
            'used_url' => '',
            'status_code' => 0,
            'application_url' => '',
            'source_platform' => '',
            'external_job_id' => '',
            'original_title' => '',
            'title_cleanup' => [],
            'raw_title' => '',
            'raw_company' => '',
            'raw_location' => '',
            'raw_location_city' => '',
            'raw_location_country' => '',
            'raw_salary_text' => '',
            'raw_company_logo' => '',
            'raw_sector' => '',
            'raw_seniority' => '',
            'raw_experience_years' => '',
            'posted_at' => '',
            'raw_posted_at' => '',
            'raw_content' => '',
            'confidence_score' => 0,
            'error_message' => '',
            'status' => 'new',
        ];

        foreach ($payloads as $payload) {
            foreach ($payload as $key => $value) {
                if (!array_key_exists($key, $merged)) {
                    continue;
                }
                if ($merged[$key] === '' || $merged[$key] === 0) {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    private function normalize_posted_at($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}$/', $value) || preg_match('/^0{4}-0{2}-0{2}/', $value)) {
            return '';
        }

        $timestamp = strtotime($value);
        if (!$timestamp || $timestamp <= 0) {
            return '';
        }

        $now = current_time('timestamp');
        if ($timestamp > ($now + DAY_IN_SECONDS) || $timestamp < strtotime('2000-01-01 00:00:00')) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function extract_title($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', (string) $html, $matches)) {
            return trim(html_entity_decode(wp_strip_all_tags((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    private function extract_meta($html, $name, $attribute = 'name') {
        $attribute = $attribute === 'property' ? 'property' : 'name';
        $quoted = preg_quote((string) $name, '/');
        if (preg_match('/<meta[^>]+' . $attribute . '=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']+)["\']/i', (string) $html, $matches)) {
            return trim(html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+' . $attribute . '=["\']' . $quoted . '["\']/i', (string) $html, $matches)) {
            return trim(html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    private function extract_text($html) {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', (string) $html);
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
        $html = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', ' ', $html);
        $html = preg_replace('#<svg\b[^>]*>.*?</svg>#is', ' ', $html);
        $text = html_entity_decode(wp_strip_all_tags($html, true), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return substr(trim((string) $text), 0, 20000);
    }

    private function extract_application_url($html, $fallback) {
        if ((string) $html !== '' && preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>\s*(?:apply|apply now|submit application)/i', (string) $html, $matches)) {
            return esc_url_raw(html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return esc_url_raw((string) $fallback);
    }

    private function resolve_url($url, $base_url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return esc_url_raw($url);
        }

        $base = wp_parse_url((string) $base_url);
        if (empty($base['scheme']) || empty($base['host'])) {
            return esc_url_raw($url);
        }

        if (strpos($url, '//') === 0) {
            return esc_url_raw($base['scheme'] . ':' . $url);
        }

        if ($url[0] === '/') {
            return esc_url_raw($base['scheme'] . '://' . $base['host'] . $url);
        }

        $path = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', (string) $base['path']) : '/';
        return esc_url_raw($base['scheme'] . '://' . $base['host'] . $path . $url);
    }

    private function extract_organization_name($organization) {
        if (is_array($organization)) {
            return sanitize_text_field((string) ($organization['name'] ?? ''));
        }

        return sanitize_text_field((string) $organization);
    }

    private function extract_organization_logo($organization) {
        if (is_array($organization)) {
            return esc_url_raw((string) ($organization['logo'] ?? ''));
        }

        return '';
    }

    private function extract_identifier($identifier) {
        if (is_array($identifier)) {
            if (!empty($identifier['value']) && !is_array($identifier['value'])) {
                return sanitize_text_field((string) $identifier['value']);
            }

            if (!empty($identifier['name']) && !is_array($identifier['name'])) {
                return sanitize_text_field((string) $identifier['name']);
            }

            return '';
        }

        return sanitize_text_field((string) $identifier);
    }

    private function extract_job_location($location) {
        $locations = is_array($location) && $this->is_list_array($location) ? $location : [$location];
        $labels = [];

        foreach ($locations as $item) {
            if (!is_array($item)) {
                continue;
            }
            $address = $item['address'] ?? $item;
            if (!is_array($address)) {
                continue;
            }

            $labels[] = implode(', ', array_filter([
                sanitize_text_field((string) ($address['addressLocality'] ?? '')),
                sanitize_text_field((string) ($address['addressRegion'] ?? '')),
                sanitize_text_field((string) ($address['addressCountry'] ?? '')),
            ]));
        }

        return implode(' / ', array_filter(array_unique($labels)));
    }

    private function extract_salary_text($salary) {
        if (!is_array($salary)) {
            return '';
        }

        $value = $salary['value'] ?? [];
        if (!is_array($value)) {
            return '';
        }

        $currency = sanitize_text_field((string) ($salary['currency'] ?? ''));
        $min = sanitize_text_field((string) ($value['minValue'] ?? ''));
        $max = sanitize_text_field((string) ($value['maxValue'] ?? ''));
        $unit = sanitize_text_field((string) ($value['unitText'] ?? ''));

        return trim(implode(' ', array_filter([$currency, trim($min . ($max !== '' ? '-' . $max : '')), $unit])));
    }

    private function clean_title($title, $company = '', $location = '') {
        $cleanup = $this->normalize_title($title, $company, $location);
        return sanitize_text_field((string) ($cleanup['title'] ?? $title));
    }

    private function normalize_title($title, $company = '', $location = '') {
        if (!class_exists('SFFC_CRM_Job_Title_Normalizer') && defined('SFFC_PLUGIN_DIR') && file_exists(SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php')) {
            require_once SFFC_PLUGIN_DIR . 'includes/class-crm-job-title-normalizer.php';
        }

        if (class_exists('SFFC_CRM_Job_Title_Normalizer')) {
            return SFFC_CRM_Job_Title_Normalizer::normalize($title, $company, $location);
        }

        $original = sanitize_text_field((string) $title);
        $title = html_entity_decode(wp_strip_all_tags($original), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = str_replace(['–', '—'], '-', $title);
        $title = preg_replace('/(?<=[a-z0-9\)])\.(?=[A-Z])/', ' - ', (string) $title);
        $title = preg_replace('/\s+/', ' ', trim((string) $title));

        if ($title === '') {
            return [
                'original_title' => $original,
                'title' => '',
                'changed' => false,
                'cleanup_score' => 0,
            ];
        }

        $company = sanitize_text_field((string) $company);
        $location = sanitize_text_field((string) $location);
        $title = $this->normalize_title_programme_language($title);
        $title = preg_replace('/^\s*[A-Z]{2,}\s+Lab\s*[-|]\s*/', '', (string) $title);
        $company_patterns = $this->build_title_cleanup_company_patterns($company);
        $location_patterns = $this->build_title_cleanup_location_patterns($location);

        foreach ($company_patterns as $pattern) {
            $title = preg_replace($pattern, ' ', $title);
        }

        $noise_patterns = array_merge($location_patterns, [
            '/\b(?:for\s+)?(?:uae|u\.a\.e\.|emirati|saudi|ksa|qatar(?:i)?|bahraini|kuwaiti|omani)\s+nationals?\b/i',
            '/\b(?:uae|u\.a\.e\.|saudi|ksa|qatari|qatar|bahraini|kuwaiti|omani)\s+citizens?\b/i',
            '/\b(?:national\s+talent|nationals?\s+only|local\s+national)\b/i',
            '/\b(?:rbg|retail banking group|private banking investment advisors?)\b/i',
            '/\b(?:english|arabic|french|german|spanish|mandarin|bilingual)\s+(?:speaker|speaking|required|language)\b/i',
            '/\((?:[^)]*\b(?:english|arabic|french|german|spanish|mandarin|bilingual)\b[^)]*)\)/i',
            '/\(([A-Z]{2,6})\)/',
            '/\b(?:m\/f\/d|f\/m\/d|m\/w\/d|f\/m\/x|all genders)\b/i',
            '/(?:^|[\s\(\[-])(?:20\d{2})(?=\s+[A-Z])/i',
            '/(?:[$£€]|AED|SAR|QAR|USD|GBP|EUR)\s?\d[\d,]*(?:\.\d+)?\s*(?:k|m)?(?:\s*[-\/]\s*(?:[$£€]|AED|SAR|QAR|USD|GBP|EUR)?\s?\d[\d,]*(?:\.\d+)?\s*(?:k|m)?)?(?:\s*(?:per\s+)?(?:hour|hr|day|month|year|annum|pa|p\.a\.))?/i',
            '/\b\d[\d,]*(?:\.\d+)?\s*(?:k|m)?\s*(?:[-\/]\s*\d[\d,]*(?:\.\d+)?\s*(?:k|m)?)?\s*(?:per\s+)?(?:hour|hr|day|month|year|annum|pa|p\.a\.)\b/i',
            '/\b(?:remote|hybrid|on[-\s]?site)\b/i',
            '/\b(?:full[-\s]?time|part[-\s]?time|permanent|temporary|contract|fixed[-\s]?term)\b/i',
            '/\b(?:apply now|job details|careers?|external|easy apply|linkedin)\b/i',
            '/\b(?:job\s*)?(?:id|req|requisition|reference)\s*[:#-]?\s*[a-z]{0,4}[-_ ]?\d{2,}\b/i',
            '/\b(?:jr|req|r)[-_ ]?\d{3,}\b/i',
            '/(?:^|[\s\-\.])\d{1,4}(?=[\s\-\.]|$)/',
        ]);

        foreach ($noise_patterns as $pattern) {
            $title = preg_replace($pattern, ' ', $title);
        }

        $title = preg_replace('/\s*[\(\[\{]\s*[\)\]\}]\s*/', ' ', (string) $title);
        $title = preg_replace('/\s+([,\|\/])\s+/', '$1 ', (string) $title);
        $title = preg_replace('/\s*[\|\/]\s*/', ' / ', (string) $title);
        $title = preg_replace('/\s+-\s+/', ' - ', (string) $title);
        $title = preg_replace('/(?:\s*[-\/]\s*){2,}/', ' - ', (string) $title);
        $title = preg_replace('/(?:\s*[-,\.\|\/]\s*)+$/', '', (string) $title);
        $title = preg_replace('/^(?:\s*[-,\.\|\/]\s*)+/', '', (string) $title);
        $title = preg_replace('/\b(senior)\s+\1\b/i', '$1', (string) $title);
        $title = preg_replace('/\b(associate)\s+\1\b/i', '$1', (string) $title);
        $title = preg_replace('/\b(program)\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\bprogramme\s+programme\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\s+/', ' ', trim((string) $title));

        $clean_title = sanitize_text_field($title !== '' ? $title : $original);
        return [
            'original_title' => $original,
            'title' => $clean_title,
            'changed' => strcasecmp($clean_title, $original) !== 0,
            'cleanup_score' => strcasecmp($clean_title, $original) !== 0 ? 50 : 0,
        ];
    }

    private function normalize_title_programme_language($title) {
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+initiative\b/i', 'National Initiative', (string) $title);
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+(graduate\s+)?programme\b/i', 'National $2Programme', (string) $title);
        $title = preg_replace('/\b(emirati[sz]ation|emiritisation)\s+(graduate\s+)?program\b/i', 'National $2Programme', (string) $title);
        $title = preg_replace('/\bgraduate\s+program\b/i', 'Graduate Programme', (string) $title);
        $title = preg_replace('/\bprogram\b/i', 'Programme', (string) $title);
        $title = preg_replace('/\bNational Initiative\s*[\/|,-]\s*Graduate Programme\b/i', 'National Initiative Programme', (string) $title);

        return $title;
    }

    private function build_title_cleanup_company_patterns($company) {
        $company = trim((string) $company);
        if ($company === '') {
            return [];
        }

        $company_quoted = preg_quote($company, '/');
        $company_root = preg_replace('/\b(?:llc|ltd|limited|plc|inc|corp|corporation|company|co|group|bank|asset management|capital|partners?)\b\.?/i', '', $company);
        $company_root = preg_replace('/\s+/', ' ', trim((string) $company_root));
        $patterns = [
            '/(?:^|\s+[-|]\s*)' . $company_quoted . '(?:\s*[-|]\s*|\s*$)/i',
            '/\b(?:at|with|for)\s+' . $company_quoted . '\b/i',
            '/\b' . $company_quoted . '\b/i',
        ];

        if ($company_root !== '' && strlen($company_root) >= 4 && strcasecmp($company_root, $company) !== 0) {
            $root_quoted = preg_quote($company_root, '/');
            $patterns[] = '/(?:^|\s+[-|]\s*)' . $root_quoted . '(?:\s*[-|]\s*|\s*$)/i';
            $patterns[] = '/\b(?:at|with|for)\s+' . $root_quoted . '\b/i';
            $patterns[] = '/\b' . $root_quoted . '\b/i';
        }

        return $patterns;
    }

    private function build_title_cleanup_location_patterns($location) {
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

        $patterns = [];
        foreach (array_unique($locations) as $item) {
            if (strlen($item) < 2) {
                continue;
            }
            $quoted = preg_quote($item, '/');
            $patterns[] = '/(?:^|\s+[-|,\/]\s*)' . $quoted . '(?:\s*[-|,\/]\s*|\s*$)/i';
            $patterns[] = '/\s*[\(\[\{]\s*' . $quoted . '\s*[\)\]\}]\s*/i';
        }

        return $patterns;
    }

    private function guess_title_from_text($text) {
        $lines = preg_split('/\r\n|\r|\n|\. /', trim((string) $text)) ?: [];
        foreach ($lines as $line) {
            $line = trim(wp_strip_all_tags((string) $line));
            if (strlen($line) >= 8 && strlen($line) <= 100 && preg_match('/\b(analyst|associate|manager|director|principal|vp|investment|finance|private equity|credit)\b/i', $line)) {
                return sanitize_text_field($line);
            }
        }

        return '';
    }

    private function detect_location($text) {
        $locations = [
            'Dubai, United Arab Emirates' => ['dubai', 'uae', 'united arab emirates'],
            'Abu Dhabi, United Arab Emirates' => ['abu dhabi'],
            'Riyadh, Saudi Arabia' => ['riyadh', 'saudi arabia', 'ksa'],
            'Doha, Qatar' => ['doha', 'qatar'],
            'Kuwait City, Kuwait' => ['kuwait'],
            'Bahrain' => ['bahrain', 'manama'],
        ];

        $haystack = strtolower((string) $text);
        foreach ($locations as $label => $needles) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    return $label;
                }
            }
        }

        return '';
    }

    private function detect_sector($text) {
        $haystack = strtolower((string) $text);
        $map = [
            'private_credit' => ['private credit', 'direct lending', 'credit fund'],
            'private_real_estate' => ['real estate private equity', 'real estate investing'],
            'ib' => ['investment banking', 'm&a', 'mergers and acquisitions'],
            'vc' => ['venture capital', 'startup investment'],
            'asset_management' => ['asset management', 'portfolio management'],
            'capital_formation' => ['investor relations', 'capital formation', 'fundraising'],
            'pe' => ['private equity', 'buyout', 'growth equity'],
        ];

        foreach ($map as $sector => $needles) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    return $sector;
                }
            }
        }

        return 'other';
    }

    private function detect_seniority($title, $description = '') {
        $title = $this->normalize_seniority_text($title);
        $text = $this->normalize_seniority_text(trim((string) $title . ' ' . (string) $description));

        $title_rules = [
            'intern' => '/\b(intern(ship)?|off cycle|offcycle|summer analyst|summer intern|placement|trainee|management trainee|graduate trainee|graduate(?:\s+[a-z0-9]+){0,4}\s+(programme|program)|campus|emirati[sz]ation graduate|emiritisation graduate|emiratisation programme|emiratization program)\b/',
            'board' => '/\b(board member|board director|chair(man|woman)|non executive director|non-executive director|independent director)\b/',
            'c_level' => '/\b(c suite|c-suite|head of function|chief\s+(executive|financial|operating|investment|risk|technology|information|marketing|people|commercial|strategy|compliance|legal)\s+officer|chief\s+[a-z]+(?:\s+[a-z]+)?\s+officer|ceo|cfo|coo|cio|cto|cmo|cro|chro|cpo|ciso)\b/',
            'partner' => '/\b(managing partner|founding partner|general partner)\b|(?<!business\s)(?<!customer\s)(?<!success\s)\bpartner\b(?!\s+(manager|success|operations|sales|marketing|finance|account|channel|relationship|solutions))/',
            'md' => '/\b(managing director|general manager)\b|\bmd\b/',
            'senior_vp' => '/\b(executive vice president|senior vice president|svp|evp)\b/',
            'vp' => '/\b(assistant vice president|associate vice president|vice president|principal|avp|vp)\b/',
            'director' => '/\b(executive director|senior director|director|associate director|regional head|country head|global head|head of|chief of staff)\b/',
            'senior_associate' => '/\b(senior associate|senior relationship manager|senior manager|lead manager|senior consultant)\b/',
            'senior_analyst' => '/\b(senior analyst|sr analyst|sr\. analyst|lead analyst|senior officer|senior specialist)\b/',
            'analyst' => '/\b(analyst|junior analyst|investment analyst|research analyst|data analyst|finance analyst|assistant relationship manager|junior relationship manager|assistant manager|junior manager|relationship officer|officer|specialist|coordinator|graduate|entry level|entry-level)\b/',
            'associate' => '/\b(associate|relationship manager|investment manager|portfolio manager|finance manager|trade finance manager|operations manager|product manager|project manager|programme manager|program manager|manager|consultant)\b/',
        ];

        foreach ($title_rules as $seniority => $pattern) {
            if ($title !== '' && preg_match($pattern, $title)) {
                return $seniority;
            }
        }

        $years = $this->detect_seniority_years($text);
        if ($years !== null) {
            if ($years <= 2) {
                return 'analyst';
            }
            if ($years <= 4) {
                return 'senior_analyst';
            }
            if ($years <= 6) {
                return 'associate';
            }
            if ($years <= 8) {
                return 'senior_associate';
            }
            if ($years <= 10) {
                return 'vp';
            }
            if ($years <= 12) {
                return 'senior_vp';
            }
            return 'director';
        }

        return 'other';
    }

    private function normalize_seniority_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = strtolower(str_replace(['–', '—', '/', '&'], ['-', '-', ' ', ' and '], $value));
        $value = preg_replace('/[^a-z0-9\+\.\-\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function detect_seniority_years($text) {
        $text = (string) $text;
        if (preg_match('/\b(?:minimum of|at least|minimum|required|requires|requirement)\s+(\d{1,2})\+?\s+years?\b/', $text, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/\b(\d{1,2})\+?\s+years?\s+(?:of\s+)?(?:relevant\s+)?experience\b/', $text, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/\b(\d{1,2})\s*(?:-|to)\s*(\d{1,2})\s+years?\b/', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function detect_experience_years($text) {
        if (preg_match('/(\d{1,2})\s*(?:\+|plus)?\s*(?:-|to)?\s*(\d{1,2})?\s*(?:years|yrs|year)/i', (string) $text, $matches)) {
            $first = (string) ($matches[1] ?? '');
            $second = (string) ($matches[2] ?? '');
            return $second !== '' ? $first . '-' . $second : $first . '+';
        }

        return '';
    }

    private function infer_platform($url) {
        $host = strtolower((string) wp_parse_url((string) $url, PHP_URL_HOST));
        if ($host === '') {
            return '';
        }

        foreach (['linkedin' => 'LinkedIn', 'indeed' => 'Indeed', 'glassdoor' => 'Glassdoor', 'greenhouse' => 'Greenhouse', 'lever' => 'Lever', 'ashby' => 'Ashby', 'workday' => 'Workday'] as $needle => $label) {
            if (strpos($host, $needle) !== false) {
                return $label;
            }
        }

        return 'Company Website';
    }

    private function build_logo_fallback($url) {
        $host = strtolower((string) wp_parse_url((string) $url, PHP_URL_HOST));
        if ($host === '') {
            return '';
        }

        return esc_url_raw('https://www.google.com/s2/favicons?domain=' . rawurlencode($host) . '&sz=128');
    }

    private function score_payload(array $payload) {
        $score = 0;
        foreach (['raw_title', 'raw_company', 'raw_location', 'raw_content', 'application_url'] as $field) {
            if (!empty($payload[$field])) {
                $score += $field === 'raw_content' ? 30 : 15;
            }
        }

        if (!empty($payload['external_job_id'])) {
            $score += 10;
        }

        return min(100, $score);
    }

    private function is_list_array(array $value) {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
