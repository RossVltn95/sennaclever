<?php
/**
 * CRM Email Account Model
 *
 * Stores OAuth/App Password credentials for connected mailboxes.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_Email_Account {

    private static $instance = null;
    private $table;

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sffc_crm_email_accounts';
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Return sanitized list of accounts for the current user.
     */
    public function get_accounts($user_id) {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d AND is_active = 1 ORDER BY is_primary DESC, created_at ASC",
                $user_id
            ),
            ARRAY_A
        );

        if ($rows) {
            $has_primary = array_filter($rows, function($row) {
                return intval($row['is_primary']) === 1;
            });
            if (!$has_primary) {
                $this->set_primary($rows[0]['id'], $user_id);
                $rows[0]['is_primary'] = 1;
            }
        }

        return array_map([$this, 'prepare_account_for_output'], $rows ?: []);
    }

    /**
     * Fetch a single account (optionally with decrypted secrets).
     */
    public function get_account($account_id, $user_id, $with_secrets = false) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d AND is_active = 1",
                $account_id,
                $user_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        if ($with_secrets) {
            $row['access_token'] = $this->decrypt($row['access_token']);
            $row['refresh_token'] = $this->decrypt($row['refresh_token']);
            $row['settings'] = $this->decode_settings($row['settings'], true);
            return $row;
        }

        return $this->prepare_account_for_output($row);
    }

    /**
     * Return the user's primary account with decrypted secrets.
     */
    public function get_primary_account($user_id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d AND is_active = 1 ORDER BY is_primary DESC, created_at ASC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['access_token'] = $this->decrypt($row['access_token']);
        $row['refresh_token'] = $this->decrypt($row['refresh_token']);
        $row['settings'] = $this->decode_settings($row['settings'], true);
        return $row;
    }

    /**
     * Store/refresh an OAuth account.
     */
    public function save_oauth_account($user_id, $provider, $email_address, $display_name, $tokens, $expires_at, $metadata = []) {
        global $wpdb;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE user_id = %d AND email_address = %s AND provider = %s",
                $user_id,
                $email_address,
                $provider
            ),
            ARRAY_A
        );

        $data = [
            'user_id'        => $user_id,
            'provider'       => $provider,
            'email_address'  => sanitize_email($email_address),
            'display_name'   => sanitize_text_field($display_name),
            'access_token'   => $this->encrypt($tokens['access_token'] ?? ''),
            'refresh_token'  => $this->encrypt($tokens['refresh_token'] ?? ''),
            'token_expires_at' => $expires_at,
            'settings'       => !empty($metadata) ? wp_json_encode($metadata) : null,
            'is_active'      => 1,
        ];

        if ($existing) {
            $wpdb->update($this->table, $data, ['id' => intval($existing['id'])]);
            $account_id = intval($existing['id']);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($this->table, $data);
            $account_id = $wpdb->insert_id;
            $this->maybe_set_primary($user_id, $account_id);
        }

        return $account_id;
    }

    /**
     * Store SMTP/App Password credentials.
     */
    public function save_app_password_account($user_id, $email_address, $display_name, $smtp_settings) {
        global $wpdb;

        $settings = [
            'smtp_host' => sanitize_text_field($smtp_settings['host'] ?? ''),
            'smtp_port' => intval($smtp_settings['port'] ?? 587),
            'smtp_encryption' => in_array($smtp_settings['encryption'] ?? 'tls', ['ssl', 'tls', 'none'], true) ? $smtp_settings['encryption'] : 'tls',
            'smtp_username' => sanitize_text_field($smtp_settings['username'] ?? ''),
            'smtp_password' => $this->encrypt($smtp_settings['password'] ?? ''),
        ];

        $data = [
            'user_id'       => $user_id,
            'provider'      => 'other',
            'email_address' => sanitize_email($email_address),
            'display_name'  => sanitize_text_field($display_name),
            'settings'      => wp_json_encode($settings),
            'is_active'     => 1,
            'created_at'    => current_time('mysql'),
        ];

        $wpdb->insert($this->table, $data);
        $account_id = $wpdb->insert_id;
        $this->maybe_set_primary($user_id, $account_id);

        return $account_id;
    }

    public function delete_account($account_id, $user_id) {
        global $wpdb;
        return $wpdb->update(
            $this->table,
            ['is_active' => 0],
            ['id' => intval($account_id), 'user_id' => $user_id]
        ) !== false;
    }

    public function set_primary($account_id, $user_id) {
        global $wpdb;
        $wpdb->update($this->table, ['is_primary' => 0], ['user_id' => $user_id]);
        $wpdb->update($this->table, ['is_primary' => 1], ['id' => intval($account_id), 'user_id' => $user_id]);
    }

    public function update_tokens($account_id, $user_id, $tokens, $expires_at) {
        global $wpdb;
        return $wpdb->update(
            $this->table,
            [
                'access_token'    => $this->encrypt($tokens['access_token'] ?? ''),
                'refresh_token'   => $this->encrypt($tokens['refresh_token'] ?? ''),
                'token_expires_at'=> $expires_at,
                'updated_at'      => current_time('mysql'),
            ],
            ['id' => intval($account_id), 'user_id' => $user_id]
        ) !== false;
    }

    public function mark_used($account_id) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            [
                'last_sync_at' => current_time('mysql'),
                'last_sync_error' => null,
            ],
            ['id' => intval($account_id)]
        );
    }

    private function maybe_set_primary($user_id, $account_id) {
        global $wpdb;
        $has_primary = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND is_primary = 1 AND is_active = 1",
                $user_id
            )
        );

        if (!$has_primary) {
            $wpdb->update($this->table, ['is_primary' => 1], ['id' => intval($account_id)]);
        }
    }

    private function prepare_account_for_output($row) {
        if (!$row) {
            return null;
        }

        $settings = $this->decode_settings($row['settings']);

        return [
            'id'            => intval($row['id']),
            'provider'      => $row['provider'],
            'email_address' => $row['email_address'],
            'display_name'  => $row['display_name'],
            'is_primary'    => intval($row['is_primary']) === 1,
            'last_sync_at'  => $row['last_sync_at'],
            'sync_error'    => $row['last_sync_error'],
            'token_expires_at' => $row['token_expires_at'],
            'settings'      => $settings,
        ];
    }

    private function decode_settings($settings, $include_password = false) {
        $decoded = !empty($settings) ? json_decode($settings, true) : [];
        if (!$decoded) {
            return [];
        }
        if (!$include_password && isset($decoded['smtp_password'])) {
            unset($decoded['smtp_password']);
        }
        if ($include_password && isset($decoded['smtp_password'])) {
            $decoded['smtp_password'] = $this->decrypt($decoded['smtp_password']);
        }
        return $decoded;
    }

    private function encrypt($value) {
        if (empty($value)) {
            return null;
        }

        if (!function_exists('openssl_encrypt')) {
            return base64_encode($value);
        }

        $key = hash('sha256', wp_salt('auth'), true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return base64_encode($value);
        }
        return base64_encode($iv . $cipher);
    }

    private function decrypt($value) {
        if (empty($value)) {
            return null;
        }

        if (!function_exists('openssl_decrypt')) {
            return base64_decode($value);
        }

        $data = base64_decode($value);
        if (empty($data) || strlen($data) < 17) {
            return base64_decode($value);
        }
        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);
        $key = hash('sha256', wp_salt('auth'), true);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : null;
    }
}
