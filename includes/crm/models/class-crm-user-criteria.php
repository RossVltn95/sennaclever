<?php
/**
 * CRM User Criteria Model
 * Handles user-created search criteria groups for personalized job matching
 *
 * @package SennaCareers
 * @since 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_User_Criteria {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_user_criteria_groups';
    }

    /**
     * Get all criteria groups for a user
     *
     * @param int $user_id
     * @param array $args Optional filters
     * @return array
     */
    public function get_user_criteria($user_id, $args = []) {
        global $wpdb;

        $defaults = [
            'is_active' => 1,
            'order_by' => 'display_order',
            'order' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['user_id = %d'];
        $values = [$user_id];

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = (int)$args['is_active'];
        }

        $where_clause = implode(' AND ', $where);

        $order_by = in_array($args['order_by'], ['display_order', 'name', 'created_at'])
            ? $args['order_by']
            : 'display_order';

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $query = "SELECT * FROM {$this->table}
                  WHERE {$where_clause}
                  ORDER BY {$order_by} {$order}";

        $query = $wpdb->prepare($query, $values);

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get criteria by ID
     *
     * @param int $id
     * @param int $user_id Optional user ID for ownership check
     * @return array|null
     */
    public function get_by_id($id, $user_id = null) {
        global $wpdb;

        if ($user_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d",
                $id,
                $user_id
            ), ARRAY_A);
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get default criteria groups for a user
     *
     * @param int $user_id
     * @return array
     */
    public function get_default_criteria($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND is_default = 1 ORDER BY display_order ASC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Create new criteria group
     *
     * @param array $data
     * @return int|false Criteria ID on success, false on failure
     */
    public function create($data) {
        global $wpdb;
        $this->ensure_years_experience_column();

        // Generate slug from name if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = $this->generate_unique_slug($data['name'], $data['user_id']);
        }

        $insert_data = [
            'user_id' => (int)$data['user_id'],
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['slug']),
            'job_title' => isset($data['job_title']) ? sanitize_text_field($data['job_title']) : '',
            'sector' => isset($data['sector']) ? wp_json_encode($data['sector']) : null,
            'location' => isset($data['location']) ? wp_json_encode($data['location']) : null,
            'experience_level' => isset($data['experience_level']) ? wp_json_encode($data['experience_level']) : null,
            'years_experience' => isset($data['years_experience']) ? sanitize_text_field($data['years_experience']) : '',
            'skills_keywords' => isset($data['skills_keywords']) ? wp_json_encode($data['skills_keywords']) : null,
            'cv_file_id' => isset($data['cv_file_id']) ? (int)$data['cv_file_id'] : null,
            'cover_letter_file_id' => isset($data['cover_letter_file_id']) ? (int)$data['cover_letter_file_id'] : null,
            'is_default' => isset($data['is_default']) ? (int)$data['is_default'] : 0,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'display_order' => isset($data['display_order']) ? (int)$data['display_order'] : 0
        ];

        $result = $wpdb->insert($this->table, $insert_data, [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d'
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update criteria group
     *
     * @param int $id
     * @param int $user_id User ID for ownership check
     * @param array $data
     * @return bool
     */
    public function update($id, $user_id, $data) {
        global $wpdb;
        $this->ensure_years_experience_column();

        // Verify ownership
        $existing = $this->get_by_id($id, $user_id);
        if (!$existing) {
            return false;
        }

        $update_data = [];
        $format = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['slug'])) {
            $update_data['slug'] = sanitize_title($data['slug']);
            $format[] = '%s';
        }

        if (isset($data['job_title'])) {
            $update_data['job_title'] = sanitize_text_field($data['job_title']);
            $format[] = '%s';
        }

        if (isset($data['sector'])) {
            $update_data['sector'] = wp_json_encode($data['sector']);
            $format[] = '%s';
        }

        if (isset($data['location'])) {
            $update_data['location'] = wp_json_encode($data['location']);
            $format[] = '%s';
        }

        if (isset($data['experience_level'])) {
            $update_data['experience_level'] = wp_json_encode($data['experience_level']);
            $format[] = '%s';
        }

        if (isset($data['years_experience'])) {
            $update_data['years_experience'] = sanitize_text_field($data['years_experience']);
            $format[] = '%s';
        }

        if (isset($data['skills_keywords'])) {
            $update_data['skills_keywords'] = wp_json_encode($data['skills_keywords']);
            $format[] = '%s';
        }

        if (isset($data['cv_file_id'])) {
            $update_data['cv_file_id'] = (int)$data['cv_file_id'];
            $format[] = '%d';
        }

        if (isset($data['cover_letter_file_id'])) {
            $update_data['cover_letter_file_id'] = (int)$data['cover_letter_file_id'];
            $format[] = '%d';
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
            $format[] = '%d';
        }

        if (isset($data['display_order'])) {
            $update_data['display_order'] = (int)$data['display_order'];
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $this->table,
            $update_data,
            [
                'id' => $id,
                'user_id' => $user_id
            ],
            $format,
            ['%d', '%d']
        ) !== false;
    }

    /**
     * Delete criteria group
     *
     * @param int $id
     * @param int $user_id User ID for ownership check
     * @return bool
     */
    public function delete($id, $user_id) {
        global $wpdb;

        // Don't allow deleting default criteria
        $criteria = $this->get_by_id($id, $user_id);
        if (!$criteria || $criteria['is_default']) {
            return false;
        }

        return $wpdb->delete(
            $this->table,
            [
                'id' => $id,
                'user_id' => $user_id
            ],
            ['%d', '%d']
        ) !== false;
    }

    /**
     * Create default criteria groups for a new user
     *
     * @param int $user_id
     * @param array $job_preferences User's job preferences from profile
     * @return bool
     */
    public function create_default_groups($user_id, $job_preferences = []) {
        // Check if defaults already exist
        $existing = $this->get_default_criteria($user_id);
        if (!empty($existing)) {
            return true;
        }

        // Create "Best Matches" group
        $best_matches_id = $this->create([
            'user_id' => $user_id,
            'name' => __('Best Matches', 'senna-finance'),
            'slug' => 'best-matches-' . $user_id,
            'job_title' => !empty($job_preferences['target_roles']) ? $job_preferences['target_roles'] : [],
            'sector' => !empty($job_preferences['target_sectors']) ? $job_preferences['target_sectors'] : [],
            'location' => !empty($job_preferences['target_locations']) ? $job_preferences['target_locations'] : [],
            'experience_level' => !empty($job_preferences['target_seniority']) ? $job_preferences['target_seniority'] : [],
            'years_experience' => !empty($job_preferences['years_experience']) ? $job_preferences['years_experience'] : '',
            'is_default' => 1,
            'display_order' => 1
        ]);

        // Create "Skill Matches" group
        $skills_matches_id = $this->create([
            'user_id' => $user_id,
            'name' => __('Skill Matches', 'senna-finance'),
            'slug' => 'skills-matches-' . $user_id,
            'sector' => !empty($job_preferences['target_sectors']) ? $job_preferences['target_sectors'] : [],
            'is_default' => 1,
            'display_order' => 2
        ]);

        return $best_matches_id && $skills_matches_id;
    }

    /**
     * Update default groups based on profile preferences
     *
     * @param int $user_id
     * @param array $job_preferences
     * @return bool
     */
    public function update_default_groups($user_id, $job_preferences) {
        $defaults = $this->get_default_criteria($user_id);

        if (empty($defaults)) {
            return $this->create_default_groups($user_id, $job_preferences);
        }

        foreach ($defaults as $default) {
            if (strpos($default['slug'], 'best-matches') !== false) {
                // Update Best Matches with all preferences
                $this->update($default['id'], $user_id, [
                    'name' => __('Best Matches', 'senna-finance'),
                    'sector' => !empty($job_preferences['target_sectors']) ? $job_preferences['target_sectors'] : [],
                    'location' => !empty($job_preferences['target_locations']) ? $job_preferences['target_locations'] : [],
                    'experience_level' => !empty($job_preferences['target_seniority']) ? $job_preferences['target_seniority'] : [],
                    'years_experience' => !empty($job_preferences['years_experience']) ? $job_preferences['years_experience'] : ''
                ]);
            } elseif (strpos($default['slug'], 'skills-matches') !== false) {
                // Update Skill Matches with sector only
                $this->update($default['id'], $user_id, [
                    'name' => __('Skill Matches', 'senna-finance'),
                    'sector' => !empty($job_preferences['target_sectors']) ? $job_preferences['target_sectors'] : []
                ]);
            }
        }

        return true;
    }

    public function decode_list($value) {
        if (is_array($value)) {
            return array_values(array_filter(array_map('sanitize_text_field', $value)));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('sanitize_text_field', $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,|\r\n]+/', $value))));
    }

    public function normalize_criteria($criteria) {
        $criteria = is_array($criteria) ? $criteria : [];
        $criteria['sector_list'] = $this->decode_list($criteria['sector'] ?? []);
        $criteria['location_list'] = $this->decode_list($criteria['location'] ?? []);
        $criteria['experience_level_list'] = $this->decode_list($criteria['experience_level'] ?? []);
        $criteria['years_experience_value'] = $this->parse_years_experience($criteria['years_experience'] ?? '');
        $criteria['skills_keywords_list'] = $this->decode_list($criteria['skills_keywords'] ?? []);
        $criteria['job_title_list'] = $this->decode_list($criteria['job_title'] ?? []);

        return $criteria;
    }

    public function score_post_against_criteria($post, $criteria) {
        $analysis = $this->get_match_analysis($post, $criteria);
        return (int) ($analysis['score'] ?? 0);
    }

    public function get_match_analysis($post, $criteria) {
        $criteria = $this->normalize_criteria($criteria);
        $weights = [
            'skills' => 0.35,
            'title' => 0.20,
            'seniority' => 0.20,
            'location' => 0.15,
            'sector' => 0.10,
        ];
        $components = [
            'skills' => $this->empty_match_component(__('Skills', 'senna-finance'), $weights['skills']),
            'title' => $this->empty_match_component(__('Role title', 'senna-finance'), $weights['title']),
            'seniority' => $this->empty_match_component(__('Experience', 'senna-finance'), $weights['seniority']),
            'location' => $this->empty_match_component(__('Location', 'senna-finance'), $weights['location']),
            'sector' => $this->empty_match_component(__('Sector', 'senna-finance'), $weights['sector']),
        ];

        $role_title = strtolower((string) ($post['role_title'] ?? ''));
        $sector = strtolower((string) ($post['sector'] ?? ''));
        $location = strtolower(trim(($post['location'] ?? '') . ' ' . ($post['location_city'] ?? '') . ' ' . ($post['location_country'] ?? '')));
        $seniority = strtolower((string) ($post['seniority'] ?? ''));
        $post_years = $this->extract_post_years($post);
        $criteria_years = (int) ($criteria['years_experience_value'] ?? 0);
        $keywords_source = $this->flatten_post_keywords($post['keywords'] ?? '');
        $content = strtolower(trim(($post['content'] ?? '') . ' ' . ($post['content_snippet'] ?? '') . ' ' . $keywords_source . ' ' . $role_title . ' ' . $sector . ' ' . $location));

        $keywords = $criteria['skills_keywords_list'];
        if (!empty($keywords)) {
            $components['skills']['available'] = true;
            $matched_keywords = [];
            foreach ($keywords as $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword !== '' && stripos($content, strtolower($keyword)) !== false) {
                    $matched_keywords[] = $keyword;
                }
            }
            $matched_count = count(array_unique(array_map('strtolower', $matched_keywords)));
            $total_keywords = max(1, count($keywords));
            $components['skills']['score'] = min(100, (int) round(($matched_count / $total_keywords) * 100));
            $components['skills']['matched_terms'] = array_values(array_unique($matched_keywords));
            if ($matched_count > 0) {
                $components['skills']['reason'] = sprintf(_n('%d Skill Match', '%d Skills Match', $matched_count, 'senna-finance'), $matched_count);
            }
        }

        $title_terms = $criteria['job_title_list'];
        if (!empty($title_terms)) {
            $components['title']['available'] = true;
            $best_title_score = 0;
            foreach ($title_terms as $title) {
                $title = trim((string) $title);
                if ($title === '') {
                    continue;
                }
                $title_score = $this->calculate_text_similarity_score($role_title, strtolower($title));
                $best_title_score = max($best_title_score, $title_score);
            }
            $components['title']['score'] = $best_title_score;
            $components['title']['matched_terms'] = $title_terms;
            if ($best_title_score >= 80) {
                $components['title']['reason'] = __('Role Title Match', 'senna-finance');
            } elseif ($best_title_score >= 45) {
                $components['title']['reason'] = __('Similar Job Title', 'senna-finance');
            }
        }

        $levels = array_map('strtolower', $criteria['experience_level_list']);
        if ($criteria_years > 0 && $post_years > 0) {
            $components['seniority']['available'] = true;
            $year_gap = $criteria_years - $post_years;
            if ($year_gap >= 0) {
                $components['seniority']['score'] = 100;
            } elseif ($year_gap === -1) {
                $components['seniority']['score'] = 80;
            } elseif ($year_gap === -2) {
                $components['seniority']['score'] = 60;
            } else {
                $components['seniority']['score'] = 25;
            }
            $components['seniority']['reason'] = $components['seniority']['score'] >= 60 ? __('Experience Match', 'senna-finance') : __('Experience Gap', 'senna-finance');
            $components['seniority']['post_years'] = $post_years;
            $components['seniority']['criteria_years'] = $criteria_years;
        }

        if (!empty($levels)) {
            $components['seniority']['available'] = true;
            foreach ($levels as $level) {
                $level_terms = [$level, str_replace('_', ' ', $level), str_replace('_', '/', $level)];
                if ($level === 'intern_graduate') {
                    $level_terms = ['intern', 'internship', 'graduate', 'entry level', 'junior'];
                } elseif ($level === 'head_of_department') {
                    $level_terms = ['head', 'head of', 'department head', 'hod'];
                }

                foreach ($level_terms as $term) {
                    if ($term !== '' && stripos($seniority . ' ' . $role_title, $term) !== false) {
                        $components['seniority']['score'] = max(100, (int) $components['seniority']['score']);
                        if (empty($components['seniority']['reason']) || $components['seniority']['reason'] === __('Experience Gap', 'senna-finance')) {
                            $components['seniority']['reason'] = __('Seniority Match', 'senna-finance');
                        }
                        break 2;
                    }
                }
            }
        }

        $locations = $criteria['location_list'];
        if (!empty($locations)) {
            $components['location']['available'] = true;
            foreach ($locations as $loc) {
                $loc = trim((string) $loc);
                if ($loc !== '' && stripos($location, strtolower($loc)) !== false) {
                    $components['location']['score'] = 100;
                    $components['location']['reason'] = __('Location Match', 'senna-finance');
                    break;
                }
            }
        }

        $sectors = array_map('strtolower', $criteria['sector_list']);
        if (!empty($sectors)) {
            $components['sector']['available'] = true;
            foreach ($sectors as $target_sector) {
                if ($target_sector !== '' && $sector !== '' && ($sector === $target_sector || stripos($sector, $target_sector) !== false || stripos($target_sector, $sector) !== false)) {
                    $components['sector']['score'] = 100;
                    $components['sector']['reason'] = __('Same Sector', 'senna-finance');
                    break;
                }
            }
        }

        $weighted_score = 0;
        $available_weight = 0;
        $reasons = [];
        foreach ($components as $component) {
            if (empty($component['available'])) {
                continue;
            }
            $available_weight += (float) $component['weight'];
            $weighted_score += ((int) $component['score'] / 100) * (float) $component['weight'];
            if (!empty($component['reason'])) {
                $reasons[] = $component['reason'];
            }
        }

        $score = $available_weight > 0 ? (int) round(($weighted_score / $available_weight) * 100) : 0;

        return [
            'score' => min(100, max(0, $score)),
            'reasons' => array_values(array_unique($reasons)),
            'components' => $components,
            'intel' => $this->build_match_intel($post, $criteria, $components),
        ];
    }

    private function empty_match_component($label, $weight) {
        return [
            'label' => $label,
            'weight' => $weight,
            'available' => false,
            'score' => 0,
            'reason' => '',
            'matched_terms' => [],
        ];
    }

    private function ensure_years_experience_column() {
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$this->table} LIKE %s", 'years_experience'));
        if ($column !== 'years_experience') {
            $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN years_experience varchar(50) DEFAULT NULL AFTER experience_level");
        }
    }

    private function parse_years_experience($value) {
        if (is_array($value)) {
            $value = reset($value);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    private function flatten_post_keywords($keywords_source) {
        if (is_array($keywords_source)) {
            $parts = [];
            foreach ($keywords_source as $keyword) {
                if (is_array($keyword)) {
                    $parts[] = $keyword['label'] ?? $keyword['text'] ?? $keyword['value'] ?? $keyword['keyword'] ?? '';
                } else {
                    $parts[] = (string) $keyword;
                }
            }
            return implode(' ', array_filter($parts));
        }

        $keywords_source = (string) $keywords_source;
        $decoded = json_decode($keywords_source, true);
        if (is_array($decoded)) {
            return $this->flatten_post_keywords($decoded);
        }

        return $keywords_source;
    }

    private function extract_post_years($post) {
        $structured = $this->parse_years_experience($post['experience_years'] ?? '');
        if ($structured > 0) {
            return $structured;
        }

        $source = trim(($post['role_title'] ?? '') . ' ' . ($post['content'] ?? '') . ' ' . ($post['content_snippet'] ?? ''));
        if (preg_match('/(\d+)\+?\s*(?:to\s*\d+\s*)?(?:years?|yrs?)\s*(?:of\s*)?(?:relevant\s*)?(?:experience|exp)?/i', $source, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function build_match_intel($post, $criteria, $components) {
        $segments = [];
        $role_title = trim((string) ($post['role_title'] ?? ''));
        $post_years = (int) ($components['seniority']['post_years'] ?? 0);
        $criteria_years = (int) ($components['seniority']['criteria_years'] ?? 0);

        if ($role_title !== '' && !empty($components['title']['reason'])) {
            $segments[] = [
                'prefix' => __('Similar role title:', 'senna-finance'),
                'value' => $role_title,
            ];
        }

        if ($post_years > 0 && $criteria_years > 0 && (int) ($components['seniority']['score'] ?? 0) >= 60) {
            $segments[] = [
                'prefix' => __('Experience aligns:', 'senna-finance'),
                'value' => sprintf(__('%1$d+ years required, you set %2$d years', 'senna-finance'), $post_years, $criteria_years),
            ];
        } elseif ($post_years > 0) {
            $segments[] = [
                'prefix' => __('Role requires:', 'senna-finance'),
                'value' => sprintf(__('%d+ years experience', 'senna-finance'), $post_years),
            ];
        }

        $matched_skills = $components['skills']['matched_terms'] ?? [];
        if (!empty($matched_skills)) {
            $segments[] = [
                'prefix' => __('Skill signal:', 'senna-finance'),
                'value' => implode(', ', array_slice($matched_skills, 0, 3)),
            ];
        }

        if (!empty($components['location']['reason']) && !empty($criteria['location_list'])) {
            $segments[] = [
                'prefix' => __('Location match:', 'senna-finance'),
                'value' => implode(', ', array_slice($criteria['location_list'], 0, 2)),
            ];
        }

        if (!empty($components['sector']['reason']) && !empty($criteria['sector_list'])) {
            $segments[] = [
                'prefix' => __('Sector match:', 'senna-finance'),
                'value' => implode(', ', array_slice($criteria['sector_list'], 0, 2)),
            ];
        }

        return array_slice($segments, 0, 3);
    }

    private function calculate_text_similarity_score($source, $target) {
        $source = trim((string) $source);
        $target = trim((string) $target);

        if ($source === '' || $target === '') {
            return 0;
        }

        if (stripos($source, $target) !== false || stripos($target, $source) !== false) {
            return 100;
        }

        $source_tokens = $this->tokenize_match_text($source);
        $target_tokens = $this->tokenize_match_text($target);
        if (empty($source_tokens) || empty($target_tokens)) {
            return 0;
        }

        $overlap = array_intersect($source_tokens, $target_tokens);
        $overlap_ratio = count($overlap) / max(1, count(array_unique($target_tokens)));

        return min(95, (int) round($overlap_ratio * 100));
    }

    private function tokenize_match_text($value) {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower((string) $value));
        $stop_words = ['and', 'or', 'the', 'of', 'for', 'to', 'in', 'a', 'an', 'role', 'roles'];

        return array_values(array_filter(array_unique($tokens), function ($token) use ($stop_words) {
            return strlen($token) > 2 && !in_array($token, $stop_words, true);
        }));
    }

    public function get_matching_posts($posts, $criteria, $threshold = 55, $limit = 6) {
        $matches = [];
        foreach ((array) $posts as $post) {
            $analysis = $this->get_match_analysis($post, $criteria);
            $score = (int) ($analysis['score'] ?? 0);
            if ($score < $threshold) {
                continue;
            }
            $post['match_score'] = $score;
            $post['match_reasons'] = $analysis['reasons'] ?? [];
            $post['match_components'] = $analysis['components'] ?? [];
            $post['match_intel'] = $analysis['intel'] ?? [];
            $matches[] = $post;
        }

        usort($matches, function ($a, $b) {
            return (int) ($b['match_score'] ?? 0) <=> (int) ($a['match_score'] ?? 0);
        });

        return $limit > 0 ? array_slice($matches, 0, $limit) : $matches;
    }

    public function get_filter_summary($criteria) {
        $criteria = $this->normalize_criteria($criteria);
        $parts = [];

        if (!empty($criteria['sector_list'])) {
            $parts[] = implode(', ', array_slice($criteria['sector_list'], 0, 2));
        }
        if (!empty($criteria['location_list'])) {
            $parts[] = implode(', ', array_slice($criteria['location_list'], 0, 2));
        }
        if (!empty($criteria['experience_level_list'])) {
            $parts[] = implode(', ', array_slice($criteria['experience_level_list'], 0, 2));
        }
        if (!empty($criteria['years_experience'])) {
            $parts[] = sprintf(__('%s years experience', 'senna-finance'), $criteria['years_experience']);
        }
        if (!empty($criteria['job_title_list'])) {
            $parts[] = implode(', ', array_slice($criteria['job_title_list'], 0, 2));
        }

        return !empty($parts) ? implode(' • ', $parts) : __('Profile preferences', 'senna-finance');
    }

    public function get_materials_summary($criteria) {
        $parts = [];
        if (!empty($criteria['cv_file_id'])) {
            $parts[] = __('CV uploaded', 'senna-finance');
        }
        if (!empty($criteria['cover_letter_file_id'])) {
            $parts[] = __('Cover letter uploaded', 'senna-finance');
        }

        return !empty($parts) ? implode(' • ', $parts) : __('No materials uploaded yet', 'senna-finance');
    }

    /**
     * Generate unique slug for user criteria
     *
     * @param string $name
     * @param int $user_id
     * @return string
     */
    private function generate_unique_slug($name, $user_id) {
        global $wpdb;

        $slug = sanitize_title($name) . '-' . $user_id;
        $original_slug = $slug;
        $counter = 1;

        while ($this->slug_exists($slug, $user_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists for user
     *
     * @param string $slug
     * @param int $user_id
     * @param int|null $exclude_id Optional ID to exclude from check
     * @return bool
     */
    private function slug_exists($slug, $user_id, $exclude_id = null) {
        global $wpdb;

        $query = "SELECT id FROM {$this->table} WHERE slug = %s AND user_id = %d";
        $values = [$slug, $user_id];

        if ($exclude_id) {
            $query .= " AND id != %d";
            $values[] = $exclude_id;
        }

        return (bool)$wpdb->get_var($wpdb->prepare($query, $values));
    }

    /**
     * Get predefined skills keywords list
     *
     * @return array
     */
    public static function get_skills_keywords() {
        return [
            // Certifications
            'ACCA', 'ACA', 'CPA', 'MBA', 'FRM', 'RICS', 'CFA', 'CFP', 'CIMA', 'CIA', 'CMA',

            // Software & Tools
            'Excel', 'Bloomberg', 'Capital IQ', 'PitchBook', 'SAP', 'Oracle Financials',
            'Microsoft Dynamics', 'QuickBooks', 'Xero', 'Sage', 'NetSuite',

            // Data & BI
            'Power BI', 'Tableau', 'Anaplan', 'Adaptive Insights', 'Python', 'SQL', 'MS Access',
            'R', 'MATLAB', 'VBA', 'Alteryx', 'Qlik', 'Looker',

            // Financial Platforms
            'Moody\'s Analytics', 'Finastra', 'Temenos', 'FIS', 'Salesforce',
            'FactSet', 'Refinitiv', 'S&P Capital IQ', 'Dealogic', 'Mergermarket',

            // Office & Presentation
            'PowerPoint', 'Word', 'Outlook', 'Google Workspace', 'Microsoft Office',

            // Core Financial Skills
            'Financial Modeling', 'Financial Analysis', 'Portfolio Valuations', 'Valuations',
            'Portfolio Management', 'Deal Sourcing', 'KYC', 'AML', 'Due Diligence',
            'M&A', 'Risk Management', 'Compliance', 'Auditing', 'Tax Planning',
            'Treasury Management', 'Corporate Finance', 'Investment Analysis',
            'Asset Management', 'Equity Research', 'Credit Analysis', 'Derivatives',
            'Forex', 'Fixed Income', 'Hedge Funds', 'Venture Capital', 'Private Equity',
            'IPO', 'Financial Reporting', 'GAAP', 'IFRS', 'FP&A', 'Budgeting',
            'Forecasting', 'Cash Flow Management', 'Working Capital',

            // Sector-Specific
            'Real Estate Finance', 'Project Finance', 'Infrastructure Finance',
            'Trade Finance', 'Structured Finance', 'Securitization', 'Syndication',
            'Underwriting', 'Capital Markets', 'Investment Banking', 'Wealth Management',
            'Fund Accounting', 'Performance Attribution', 'Risk Analytics',

            // Technical & Emerging
            'Artificial Intelligence', 'Machine Learning', 'Data Analytics',
            'Big Data', 'Blockchain', 'Cryptocurrency', 'Fintech', 'RegTech',
            'Robotic Process Automation', 'Cloud Computing', 'API Integration',

            // Regulatory & Standards
            'Basel III', 'Solvency II', 'MiFID II', 'Dodd-Frank', 'SOX', 'GDPR',
            'Anti-Money Laundering', 'Know Your Customer', 'Financial Crime',

            // Soft Skills
            'Stakeholder Management', 'Client Relationship Management', 'Presentation Skills',
            'Communication', 'Leadership', 'Team Management', 'Project Management',
            'Agile', 'Scrum'
        ];
    }

    public static function get_experience_options() {
        return [
            'intern_graduate' => __('Intern/graduate', 'senna-finance'),
            'analyst' => __('Analyst', 'senna-finance'),
            'associate' => __('Associate', 'senna-finance'),
            'manager' => __('Manager', 'senna-finance'),
            'director' => __('Director', 'senna-finance'),
            'head_of_department' => __('Head of Department', 'senna-finance'),
        ];
    }
}
