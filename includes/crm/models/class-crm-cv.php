<?php
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_CV_Manager {

    private $meta_key = 'sffc_crm_cv_versions';

    public function get_cvs($user_id) {
        $entries = get_user_meta($user_id, $this->meta_key, true);
        if (!is_array($entries)) {
            $entries = [];
        }
        return $entries;
    }

    public function save_cv($user_id, $content, $label = '') {
        $entries = $this->get_cvs($user_id);
        $id = uniqid('cv_', true);

        foreach ($entries as &$existing) {
            $existing['is_default'] = false;
        }
        unset($existing);

        $entry = [
            'id'         => $id,
            'label'      => $label ?: 'CV Version ' . (count($entries) + 1),
            'content'    => $content,
            'created_at' => current_time('mysql'),
            'is_default' => true,
        ];

        $entries[] = $entry;
        update_user_meta($user_id, $this->meta_key, $entries);

        return $entries;
    }

    public function set_default($user_id, $cv_id) {
        $entries = $this->get_cvs($user_id);
        $found = false;
        foreach ($entries as &$entry) {
            if ($entry['id'] === $cv_id) {
                $entry['is_default'] = true;
                $found = true;
            } else {
                $entry['is_default'] = false;
            }
        }
        unset($entry);

        if (!$found) {
            return new WP_Error('cv_not_found', __('CV not found', 'senna-finance'));
        }

        update_user_meta($user_id, $this->meta_key, $entries);
        return $entries;
    }

    public function get_default($user_id) {
        $entries = $this->get_cvs($user_id);
        foreach ($entries as $entry) {
            if (!empty($entry['is_default'])) {
                return $entry;
            }
        }
        return !empty($entries) ? $entries[0] : null;
    }

    public function get_cv_by_id($user_id, $cv_id) {
        $entries = $this->get_cvs($user_id);
        foreach ($entries as $entry) {
            if ($entry['id'] === $cv_id) {
                return $entry;
            }
        }
        return null;
    }

    private function normalize_default($user_id) {
        $entries = $this->get_cvs($user_id);
        $has_default = false;
        foreach ($entries as $entry) {
            if (!empty($entry['is_default'])) {
                $has_default = true;
                break;
            }
        }
        if (!$has_default && !empty($entries)) {
            $entries[0]['is_default'] = true;
            update_user_meta($user_id, $this->meta_key, $entries);
        }
    }
}
