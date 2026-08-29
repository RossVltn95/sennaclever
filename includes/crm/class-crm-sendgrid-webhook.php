<?php
/**
 * CRM SendGrid Webhook
 *
 * Persists and applies SendGrid event webhook updates for internship alerts.
 *
 * @package SennaCareers
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_CRM_SendGrid_Webhook {

    private static $instance = null;
    private $events_table;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->events_table = $wpdb->prefix . 'sffc_crm_sendgrid_events';
    }

    public function get_webhook_token() {
        $token = (string) get_option('sffc_crm_sendgrid_webhook_token', '');
        if ($token === '') {
            $token = wp_generate_password(32, false, false);
            update_option('sffc_crm_sendgrid_webhook_token', $token, false);
        }

        return $token;
    }

    public function get_webhook_url() {
        return add_query_arg(
            ['token' => rawurlencode($this->get_webhook_token())],
            rest_url('sffc-crm/v1/sendgrid/webhook')
        );
    }

    public function handle_webhook($request) {
        $request_token = (string) $request->get_param('token');
        if ($request_token === '') {
            $request_token = (string) $request->get_header('x-sffc-crm-webhook-token');
        }

        if (!hash_equals($this->get_webhook_token(), $request_token)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid webhook token.',
            ], 401);
        }

        $events = $request->get_json_params();
        if (!is_array($events)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Expected JSON array payload.',
            ], 400);
        }

        $processed = 0;
        $matched = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $processed++;
            $logged_event = $this->log_event($event);
            if ($logged_event) {
                $matched += $this->apply_event_to_queue($event);
            }
        }

        update_option('sffc_crm_last_sendgrid_webhook_received_at', current_time('mysql'), false);

        return new WP_REST_Response([
            'success'   => true,
            'processed' => $processed,
            'matched'   => $matched,
        ], 200);
    }

    private function log_event($event) {
        global $wpdb;

        $custom_args = isset($event['custom_args']) && is_array($event['custom_args']) ? $event['custom_args'] : [];
        $queue_id = !empty($custom_args['queue_id']) ? intval($custom_args['queue_id']) : 0;
        $post_id = !empty($custom_args['post_id']) ? intval($custom_args['post_id']) : 0;
        $user_id = !empty($custom_args['user_id']) ? intval($custom_args['user_id']) : 0;
        $sg_event_id = sanitize_text_field($event['sg_event_id'] ?? '');

        if ($sg_event_id !== '') {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->events_table} WHERE sg_event_id = %s LIMIT 1",
                $sg_event_id
            ));

            if ($existing) {
                return false;
            }
        }

        $event_at = !empty($event['timestamp']) ? date('Y-m-d H:i:s', intval($event['timestamp'])) : current_time('mysql');

        $wpdb->insert(
            $this->events_table,
            [
                'queue_id'          => $queue_id ?: null,
                'post_id'           => $post_id ?: null,
                'user_id'           => $user_id ?: null,
                'email'             => sanitize_email($event['email'] ?? ''),
                'event_type'        => sanitize_key($event['event'] ?? 'unknown'),
                'event_at'          => $event_at,
                'sg_event_id'       => $sg_event_id ?: null,
                'sg_message_id'     => sanitize_text_field($event['sg_message_id'] ?? ''),
                'provider_reference'=> sanitize_text_field($event['smtp-id'] ?? ''),
                'reason'            => sanitize_text_field($event['reason'] ?? ''),
                'payload'           => wp_json_encode($event),
                'created_at'        => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id > 0;
    }

    private function apply_event_to_queue($event) {
        $custom_args = isset($event['custom_args']) && is_array($event['custom_args']) ? $event['custom_args'] : [];
        $queue_id = !empty($custom_args['queue_id']) ? intval($custom_args['queue_id']) : 0;
        if ($queue_id <= 0) {
            return 0;
        }

        if (!class_exists('SFFC_CRM_Internship_Alert_Queue')) {
            return 0;
        }

        SFFC_CRM_Internship_Alert_Queue::get_instance()->apply_sendgrid_event($queue_id, $event);
        return 1;
    }
}
