<?php
/**
 * CRM SendGrid Service
 *
 * Handles direct SendGrid V3 API calls for scheduled internship alerts.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_SendGrid_Service {

    private static $instance = null;
    private $endpoint = 'https://api.sendgrid.com/v3/mail/send';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function is_configured() {
        return $this->get_api_key() !== '';
    }

    public function get_api_key() {
        $env_key = defined('SFFC_SENDGRID_API_KEY') ? SFFC_SENDGRID_API_KEY : getenv('SFFC_SENDGRID_API_KEY');
        if (is_string($env_key) && trim($env_key) !== '') {
            return trim($env_key);
        }

        return trim((string) get_option('sffc_crm_sendgrid_alert_api_key', ''));
    }

    public function get_from_email() {
        if (function_exists('sffc_crm_get_email_sender')) {
            $sender = sffc_crm_get_email_sender('default');
            if (!empty($sender['from_email'])) {
                return sanitize_email($sender['from_email']);
            }
        }

        $email = sanitize_email(get_option('sffc_crm_sendgrid_alert_from_email', 'support.team@joinsenna.com'));
        if (!$email) {
            $email = sanitize_email(get_option('admin_email'));
        }

        return $email;
    }

    public function get_from_name() {
        if (function_exists('sffc_crm_get_email_sender')) {
            $sender = sffc_crm_get_email_sender('default');
            if (!empty($sender['from_name'])) {
                return sanitize_text_field($sender['from_name']);
            }
        }

        $name = sanitize_text_field(get_option('sffc_crm_sendgrid_alert_from_name', 'MENA Careers'));
        return $name ?: 'MENA Careers';
    }

    private function get_sender_payload($context = 'default') {
        if (function_exists('sffc_crm_get_email_sender')) {
            $sender = sffc_crm_get_email_sender($context);
            return $this->get_sender_payload_from_sender($sender);
        }

        return [
            'from' => [
                'email' => $this->get_from_email(),
                'name'  => $this->get_from_name(),
            ],
        ];
    }

    private function get_sender_payload_from_sender(array $sender) {
        $payload = [
            'from' => [
                'email' => sanitize_email($sender['from_email'] ?? ''),
                'name'  => sanitize_text_field($sender['from_name'] ?? 'MENA Careers'),
            ],
        ];

        if (!empty($sender['reply_to_email'])) {
            $payload['reply_to'] = [
                'email' => sanitize_email($sender['reply_to_email']),
                'name'  => sanitize_text_field(!empty($sender['reply_to_name']) ? $sender['reply_to_name'] : ($sender['from_name'] ?? 'MENA Careers')),
            ];
        }

        if (!empty($payload['from']['email'])) {
            return $payload;
        }

        return [
            'from' => [
                'email' => $this->get_from_email(),
                'name'  => $this->get_from_name(),
            ],
        ];
    }

    public function send_email_from_sender(array $sender, $subject, $html_body, $recipient_email, $send_at_unix = null, array $custom_args = [], array $categories = ['sender_test']) {
        if (function_exists('sffc_crm_alert_email_sending_disabled') && sffc_crm_alert_email_sending_disabled()) {
            return new WP_Error('alert_email_sending_disabled', 'Alert email sending is currently disabled.');
        }

        $api_key = $this->get_api_key();
        if ($api_key === '') {
            return new WP_Error('sendgrid_missing_key', 'SendGrid API key is not configured.');
        }

        $recipient_email = sanitize_email($recipient_email);
        if (!$recipient_email) {
            return new WP_Error('sendgrid_missing_recipient', 'No valid recipient email was provided.');
        }

        $payload = array_merge($this->get_sender_payload_from_sender($sender), [
            'subject' => $subject,
            'content' => [
                [
                    'type'  => 'text/html',
                    'value' => $html_body,
                ],
            ],
            'personalizations' => [
                array_filter([
                    'to' => [
                        ['email' => $recipient_email],
                    ],
                    'custom_args' => !empty($custom_args) ? array_map('strval', $custom_args) : null,
                ]),
            ],
            'categories' => array_values(array_filter(array_map('sanitize_key', $categories))),
        ]);

        if ($send_at_unix !== null) {
            $payload['send_at'] = (int) $send_at_unix;
        }

        $provider_reference = $this->submit_payload($payload, $api_key, 'email');
        if (is_wp_error($provider_reference)) {
            return $provider_reference;
        }

        return [
            'provider_reference' => $provider_reference,
            'scheduled_for'      => $send_at_unix !== null ? (int) $send_at_unix : null,
            'recipient_count'    => 1,
        ];
    }

    private function submit_payload(array $payload, $api_key, $error_label = 'email') {
        $response = wp_remote_post($this->endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 202) {
            return new WP_Error(
                'sendgrid_api_error',
                sprintf(
                    'SendGrid API rejected %s with status %d: %s',
                    $error_label,
                    $code,
                    wp_remote_retrieve_body($response)
                )
            );
        }

        $headers = wp_remote_retrieve_headers($response);
        $provider_reference = '';
        if ($headers) {
            $provider_reference = $headers['x-message-id'] ?? ($headers['X-Message-Id'] ?? '');
        }
        if (!$provider_reference) {
            $provider_reference = 'sg_' . wp_generate_uuid4();
        }

        return $provider_reference;
    }

    private function get_sender_key(array $sender) {
        $key = sanitize_key($sender['key'] ?? '');
        if ($key) {
            return $key;
        }

        $email = strtolower((string) sanitize_email($sender['from_email'] ?? ''));
        return $email ? md5($email) : 'default';
    }

    private function get_recipient_identity(array $recipient) {
        $user_id = !empty($recipient['user_id']) ? intval($recipient['user_id']) : 0;
        $email = sanitize_email($recipient['email'] ?? '');

        if ($user_id > 0) {
            return [
                'type' => 'user',
                'id' => $user_id,
                'email' => $email,
            ];
        }

        return [
            'type' => 'email',
            'id' => md5(strtolower($email)),
            'email' => $email,
        ];
    }

    private function get_last_sender_for_recipient($context, array $identity) {
        $context = sanitize_key($context ?: 'default');

        if (($identity['type'] ?? '') === 'user' && !empty($identity['id'])) {
            return sanitize_key((string) get_user_meta((int) $identity['id'], '_sffc_last_email_sender_' . $context, true));
        }

        if (!empty($identity['id'])) {
            return sanitize_key((string) get_transient('sffc_last_sender_' . $context . '_' . $identity['id']));
        }

        return '';
    }

    private function set_last_sender_for_recipient($context, array $identity, $sender_key) {
        $context = sanitize_key($context ?: 'default');
        $sender_key = sanitize_key($sender_key);
        if (!$sender_key) {
            return;
        }

        if (($identity['type'] ?? '') === 'user' && !empty($identity['id'])) {
            update_user_meta((int) $identity['id'], '_sffc_last_email_sender_' . $context, $sender_key);
            return;
        }

        if (!empty($identity['id'])) {
            set_transient('sffc_last_sender_' . $context . '_' . $identity['id'], $sender_key, 90 * DAY_IN_SECONDS);
        }
    }

    private function choose_sender_for_recipient(array $sender_pool, $context, array $recipient, $index = 0) {
        $sender_pool = array_values($sender_pool);
        if (count($sender_pool) <= 1) {
            return $sender_pool[0] ?? [];
        }

        $identity = $this->get_recipient_identity($recipient);
        $last_sender_key = $this->get_last_sender_for_recipient($context, $identity);
        $email_seed = strtolower((string) ($identity['email'] ?? $identity['id'] ?? ''));
        $seed = abs(crc32($email_seed . '|' . sanitize_key($context ?: 'default') . '|' . gmdate('Y-m-d')));
        $sender_count = count($sender_pool);
        $start = ($seed + (int) $index) % $sender_count;

        for ($offset = 0; $offset < $sender_count; $offset++) {
            $sender = (array) $sender_pool[($start + $offset) % $sender_count];
            if ($this->get_sender_key($sender) !== $last_sender_key) {
                return $sender;
            }
        }

        return (array) $sender_pool[$start];
    }

    public function resolve_contextual_sender(array $recipient, $sender_context = 'custom_email') {
        $sender_pool = function_exists('sffc_crm_get_email_sender_pool')
            ? sffc_crm_get_email_sender_pool($sender_context)
            : [];
        if (empty($sender_pool)) {
            $sender_pool = [function_exists('sffc_crm_get_email_sender') ? sffc_crm_get_email_sender($sender_context) : []];
        }

        return (array) $this->choose_sender_for_recipient($sender_pool, $sender_context, $recipient);
    }

    public function send_scheduled_batch($subject, $html_body, array $recipients, $send_at_unix, $sender_context = 'internship_alerts') {
        if (function_exists('sffc_crm_alert_email_sending_disabled') && sffc_crm_alert_email_sending_disabled()) {
            return new WP_Error('alert_email_sending_disabled', 'Alert email sending is currently disabled.');
        }

        $api_key = $this->get_api_key();
        if ($api_key === '') {
            return new WP_Error('sendgrid_missing_key', 'SendGrid API key is not configured.');
        }

        if (empty($recipients)) {
            return new WP_Error('sendgrid_missing_recipients', 'No recipients provided for SendGrid batch.');
        }

        $personalizations = [];
        $seen_emails = [];
        foreach ($recipients as $recipient) {
            $email = sanitize_email($recipient['email'] ?? '');
            if (!$email) {
                continue;
            }

            $email_key = strtolower($email);
            if (isset($seen_emails[$email_key])) {
                continue;
            }
            $seen_emails[$email_key] = true;

            $entry = [
                'to' => [
                    ['email' => $email],
                ],
            ];

            $custom_args = [];
            if (!empty($recipient['user_id'])) {
                $custom_args['user_id'] = (string) intval($recipient['user_id']);
            }
            if (!empty($recipient['post_id'])) {
                $custom_args['post_id'] = (string) intval($recipient['post_id']);
            }
            if (!empty($recipient['queue_id'])) {
                $custom_args['queue_id'] = (string) intval($recipient['queue_id']);
            }
            if (!empty($custom_args)) {
                $entry['custom_args'] = $custom_args;
            }

            $entry['_sffc_recipient'] = [
                'email' => $email,
                'user_id' => !empty($recipient['user_id']) ? intval($recipient['user_id']) : 0,
            ];

            $personalizations[] = $entry;
        }

        if (empty($personalizations)) {
            return new WP_Error('sendgrid_invalid_recipients', 'All SendGrid recipients were invalid.');
        }

        $sender_pool = function_exists('sffc_crm_get_email_sender_pool')
            ? sffc_crm_get_email_sender_pool($sender_context)
            : [];
        if (empty($sender_pool)) {
            $sender_pool = [['batch_limit' => count($personalizations)]];
        }

        $personalizations_by_sender = [];
        foreach ($personalizations as $index => $entry) {
            $recipient_meta = (array) ($entry['_sffc_recipient'] ?? []);
            unset($entry['_sffc_recipient']);

            $sender = $this->choose_sender_for_recipient($sender_pool, $sender_context, $recipient_meta, $index);
            $sender_key = $this->get_sender_key((array) $sender);
            if (!isset($personalizations_by_sender[$sender_key])) {
                $personalizations_by_sender[$sender_key] = [
                    'sender' => (array) $sender,
                    'items' => [],
                    'recipients' => [],
                ];
            }

            $personalizations_by_sender[$sender_key]['items'][] = $entry;
            $personalizations_by_sender[$sender_key]['recipients'][] = $recipient_meta;
        }

        $provider_references = [];
        $submitted_count = 0;
        foreach ($personalizations_by_sender as $sender_group) {
            $sender = (array) $sender_group['sender'];
            $sender_key = $this->get_sender_key($sender);
            $remaining = (array) $sender_group['items'];
            $remaining_recipients = (array) $sender_group['recipients'];
            $limit = max(1, min(10000, (int) ($sender['batch_limit'] ?? 500)));

            while (!empty($remaining)) {
                $chunk = array_splice($remaining, 0, $limit);
                $chunk_recipients = array_splice($remaining_recipients, 0, $limit);
                $payload = array_merge($this->get_sender_payload_from_sender((array) $sender), [
                    'subject' => $subject,
                    'content' => [
                        [
                            'type'  => 'text/html',
                            'value' => $html_body,
                        ],
                    ],
                    'personalizations' => $chunk,
                    'categories' => ['internship_alert'],
                    'send_at' => (int) $send_at_unix,
                ]);

                $provider_reference = $this->submit_payload($payload, $api_key, 'batch');
                if (is_wp_error($provider_reference)) {
                    return $provider_reference;
                }

                $provider_references[] = $provider_reference;
                $submitted_count += count($chunk);

                foreach ($chunk_recipients as $recipient_meta) {
                    $this->set_last_sender_for_recipient(
                        $sender_context,
                        $this->get_recipient_identity((array) $recipient_meta),
                        $sender_key
                    );
                }
            }
        }

        return [
            'provider_reference' => implode(',', $provider_references),
            'provider_references' => $provider_references,
            'scheduled_for'      => (int) $send_at_unix,
            'recipient_count'    => $submitted_count,
        ];
    }

    public function send_contextual_email($subject, $html_body, array $recipient, $send_at_unix = null, $sender_context = 'custom_email', array $custom_args = [], array $categories = ['custom_email']) {
        $email = sanitize_email($recipient['email'] ?? '');
        if (!$email) {
            return new WP_Error('sendgrid_missing_recipient', 'No valid recipient email was provided.');
        }

        $sender_pool = function_exists('sffc_crm_get_email_sender_pool')
            ? sffc_crm_get_email_sender_pool($sender_context)
            : [];
        if (empty($sender_pool)) {
            $sender_pool = [function_exists('sffc_crm_get_email_sender') ? sffc_crm_get_email_sender($sender_context) : []];
        }

        $sender = $this->choose_sender_for_recipient($sender_pool, $sender_context, $recipient);
        $sender_key = $this->get_sender_key((array) $sender);

        if (!empty($recipient['user_id'])) {
            $custom_args['user_id'] = (string) intval($recipient['user_id']);
        }

        $result = $this->send_email_from_sender(
            (array) $sender,
            $subject,
            $html_body,
            $email,
            $send_at_unix,
            $custom_args,
            $categories
        );

        if (!is_wp_error($result)) {
            $this->set_last_sender_for_recipient(
                $sender_context,
                $this->get_recipient_identity($recipient),
                $sender_key
            );
        }

        return $result;
    }

    public function send_email($subject, $html_body, $recipient_email, $send_at_unix = null, array $custom_args = [], array $categories = ['internship_alert'], $sender_context = 'default') {
        if (function_exists('sffc_crm_alert_email_sending_disabled') && sffc_crm_alert_email_sending_disabled()) {
            return new WP_Error('alert_email_sending_disabled', 'Alert email sending is currently disabled.');
        }

        $api_key = $this->get_api_key();
        if ($api_key === '') {
            return new WP_Error('sendgrid_missing_key', 'SendGrid API key is not configured.');
        }

        $recipient_email = sanitize_email($recipient_email);
        if (!$recipient_email) {
            return new WP_Error('sendgrid_missing_recipient', 'No valid recipient email was provided.');
        }

        $payload = array_merge($this->get_sender_payload($sender_context), [
            'subject' => $subject,
            'content' => [
                [
                    'type'  => 'text/html',
                    'value' => $html_body,
                ],
            ],
            'personalizations' => [
                array_filter([
                    'to' => [
                        ['email' => $recipient_email],
                    ],
                    'custom_args' => !empty($custom_args) ? array_map('strval', $custom_args) : null,
                ]),
            ],
            'categories' => array_values(array_filter(array_map('sanitize_key', $categories))),
        ]);

        if ($send_at_unix !== null) {
            $payload['send_at'] = (int) $send_at_unix;
        }

        $response = wp_remote_post($this->endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 202) {
            return new WP_Error(
                'sendgrid_api_error',
                sprintf(
                    'SendGrid API rejected email with status %d: %s',
                    $code,
                    wp_remote_retrieve_body($response)
                )
            );
        }

        $headers = wp_remote_retrieve_headers($response);
        $provider_reference = '';
        if ($headers) {
            $provider_reference = $headers['x-message-id'] ?? ($headers['X-Message-Id'] ?? '');
        }
        if (!$provider_reference) {
            $provider_reference = 'sg_' . wp_generate_uuid4();
        }

        return [
            'provider_reference' => $provider_reference,
            'scheduled_for'      => $send_at_unix !== null ? (int) $send_at_unix : null,
            'recipient_count'    => 1,
        ];
    }
}
