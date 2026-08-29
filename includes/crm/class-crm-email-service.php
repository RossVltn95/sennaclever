<?php
/**
 * CRM Email Service
 *
 * Handles OAuth flows and server-side sending via Gmail, Outlook, or SMTP.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SFFC_CRM_Email_Service {

    private static $instance = null;
    private $accounts;

    private function __construct() {
        $this->accounts = SFFC_CRM_Email_Account::get_instance();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_redirect_uri() {
        return home_url('/?sffc_crm_oauth=1');
    }

    public function build_oauth_url($user_id, $provider) {
        $state = wp_generate_uuid4();
        set_transient(
            'sffc_crm_oauth_' . $state,
            [
                'user_id'  => $user_id,
                'provider'=> $provider,
                'created' => time(),
            ],
            15 * MINUTE_IN_SECONDS
        );

        $redirect = $this->get_redirect_uri();

        if ($provider === 'gmail') {
            $client_id = trim(get_option('sffc_crm_gmail_client_id', ''));
            if (empty($client_id)) {
                return new WP_Error('missing_credentials', 'Missing Gmail client ID.');
            }
            $params = [
                'client_id' => $client_id,
                'redirect_uri' => $redirect,
                'response_type' => 'code',
                'scope' => 'https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/userinfo.email',
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state,
                'include_granted_scopes' => 'true',
            ];
            return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        }

        if ($provider === 'outlook') {
            $client_id = trim(get_option('sffc_crm_outlook_client_id', ''));
            if (empty($client_id)) {
                return new WP_Error('missing_credentials', 'Missing Outlook client ID.');
            }
            $params = [
                'client_id' => $client_id,
                'redirect_uri' => $redirect,
                'response_type' => 'code',
                'scope' => 'offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read',
                'response_mode' => 'query',
                'state' => $state,
            ];
            $tenant = trim(get_option('sffc_crm_outlook_tenant', 'common')) ?: 'common';
            return 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/authorize?' . http_build_query($params);
        }

        return new WP_Error('invalid_provider', 'Unsupported provider');
    }

    public function handle_oauth_callback($provider, $state, $code) {
        $stored = get_transient('sffc_crm_oauth_' . $state);
        delete_transient('sffc_crm_oauth_' . $state);

        if (!$stored || empty($stored['user_id']) || $stored['provider'] !== $provider) {
            if (empty($provider) && $stored && !empty($stored['provider'])) {
                $provider = $stored['provider'];
            } else {
                return new WP_Error('invalid_state', 'OAuth session expired. Please try again.');
            }
        }

        if (empty($code)) {
            return new WP_Error('missing_code', 'OAuth authorization code missing.');
        }

        if ($provider === 'gmail') {
            return $this->complete_gmail_oauth($stored['user_id'], $code);
        }

        if ($provider === 'outlook') {
            return $this->complete_outlook_oauth($stored['user_id'], $code);
        }

        return new WP_Error('invalid_provider', 'Unsupported provider');
    }

    public function send_connected_email($user_id, $args) {
        $account = null;
        if (!empty($args['account_id'])) {
            $account = $this->accounts->get_account(intval($args['account_id']), $user_id, true);
        } else {
            $account = $this->accounts->get_primary_account($user_id);
        }

        if (!$account) {
            return new WP_Error('email_account_missing', 'Connect Gmail, Outlook, or SMTP to send email.');
        }

        $recruiter_model = new SFFC_CRM_Recruiter();
        $recruiter = $recruiter_model->get(intval($args['recruiter_id']));
        if (!$recruiter) {
            return new WP_Error('recruiter_missing', 'Recruiter not found.');
        }
        if (empty($recruiter['email'])) {
            return new WP_Error('recruiter_email_missing', 'Recruiter email address is missing.');
        }

        $post = null;
        if (!empty($args['post_id'])) {
            $post_model = new SFFC_CRM_Post();
            $post = $post_model->get(intval($args['post_id']));
        }

        $subject = sanitize_text_field($args['subject'] ?? '');
        $message = wp_kses_post($args['content'] ?? '');
        if (!$subject || !$message) {
            return new WP_Error('missing_content', 'Subject and message are required.');
        }

        $candidate = wp_get_current_user();
        $from_email = $account['email_address'];
        $from_name = $account['display_name'] ?: $candidate->display_name;

        $outreach_model = new SFFC_CRM_Outreach();
        $outreach_id = $outreach_model->create($user_id, [
            'recruiter_id' => $recruiter['id'],
            'post_id'      => $post['id'] ?? null,
            'channel'      => 'email',
            'subject'      => $subject,
            'content'      => $message,
            'content_html' => wpautop(wp_kses_post($message)),
        ]);

        if (!$outreach_id || is_wp_error($outreach_id)) {
            return new WP_Error('outreach_error', 'Unable to log outreach message.');
        }

        $tracking = $outreach_model->get($outreach_id);
        $tracking_id = $tracking['tracking_id'];
        $pixel_url = add_query_arg(
            [
                'sffc_crm_track' => 'open',
                'tid' => rawurlencode($tracking_id),
            ],
            home_url('/')
        );

        $cv_snapshot = $this->maybe_append_cv($user_id, $tracking_id);
        $text_body = $this->build_text_body($message, $cv_snapshot);
        $html_body = $this->build_html_body($message, $pixel_url, $cv_snapshot);

        $mail_result = $this->dispatch_provider_email($account, [
            'from_email' => $from_email,
            'from_name'  => $from_name,
            'to_email'   => $recruiter['email'],
            'to_name'    => $recruiter['name'],
            'subject'    => $subject,
            'text_body'  => $text_body,
            'html_body'  => $html_body,
        ]);

        if (is_wp_error($mail_result)) {
            return $mail_result;
        }

        $outreach_model->send($outreach_id, $user_id);
        $this->accounts->mark_used($account['id']);

        $conversation_model = new SFFC_CRM_Conversation();
        $conversation_id = $conversation_model->find_or_create($user_id, $recruiter['id'], $subject);
        if ($conversation_id) {
            $message_model = new SFFC_CRM_Message();
            $message_model->create($conversation_id, [
                'direction'    => 'outbound',
                'channel'      => 'email',
                'from_email'   => $from_email,
                'from_name'    => $from_name,
                'to_email'     => $recruiter['email'],
                'to_name'      => $recruiter['name'],
                'subject'      => $subject,
                'content'      => $text_body,
                'content_html' => $html_body,
                'outreach_id'  => $outreach_id,
                'external_id'  => $mail_result['message_id'] ?? null,
            ]);
        }

        return [
            'outreach_id'     => $outreach_id,
            'conversation_id' => $conversation_id,
            'tracking_id'     => $tracking_id,
            'provider'        => $account['provider'],
            'message_id'      => $mail_result['message_id'] ?? null,
        ];
    }

    private function build_text_body($message, $cv_snapshot) {
        $text = wp_strip_all_tags($message);
        if ($cv_snapshot && !empty($cv_snapshot['link'])) {
            $text .= "\n\nView my CV: " . esc_url_raw($cv_snapshot['link']);
        }
        return $text;
    }

    private function build_html_body($message, $pixel_url, $cv_snapshot) {
        $html = wpautop(wp_kses_post($message));
        if ($cv_snapshot && !empty($cv_snapshot['link'])) {
            $html .= '<p style="margin-top:18px;">'
                . '<a href="' . esc_url($cv_snapshot['link']) . '" '
                . 'style="display:inline-flex;align-items:center;gap:8px;color:#0D353E;font-weight:600;text-decoration:none;">'
                . '<span>View my CV</span>'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0D353E" stroke-width="2"><path d="M5 12h14"></path><path d="M12 5l7 7-7 7"></path></svg>'
                . '</a></p>';
        }
        $html .= '<img src="' . esc_url($pixel_url) . '" alt="" width="1" height="1" style="display:none;max-height:1px;max-width:1px;" />';
        return $html;
    }

    private function maybe_append_cv($user_id, $tracking_id) {
        $cv_manager = new SFFC_CRM_CV_Manager();
        $cv = $cv_manager->get_default($user_id);
        if (!$cv) {
            return null;
        }
        $link = add_query_arg(
            [
                'sffc_crm_track' => 'cv',
                'tid' => rawurlencode($tracking_id),
                'cv'  => rawurlencode($cv['id']),
            ],
            home_url('/')
        );
        return [
            'id' => $cv['id'],
            'link' => $link,
            'content' => $cv['content'],
        ];
    }

    private function dispatch_provider_email($account, $payload) {
        if ($account['provider'] === 'gmail') {
            return $this->send_via_gmail($account, $payload);
        }
        if ($account['provider'] === 'outlook') {
            return $this->send_via_outlook($account, $payload);
        }
        return $this->send_via_smtp($account, $payload);
    }

    private function send_via_gmail($account, $payload) {
        $token = $this->ensure_token($account, 'gmail');
        if (is_wp_error($token)) {
            return $token;
        }

        $raw = $this->build_raw_message($payload);
        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        $response = wp_remote_post(
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['raw' => $encoded]),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('gmail_send_failed', 'Gmail send failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            $body = wp_remote_retrieve_body($response);
            return new WP_Error('gmail_send_failed', 'Gmail send failed: ' . $body);
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
        return ['message_id' => $json['id'] ?? null];
    }

    private function send_via_outlook($account, $payload) {
        $token = $this->ensure_token($account, 'outlook');
        if (is_wp_error($token)) {
            return $token;
        }

        $body = [
            'message' => [
                'subject' => $payload['subject'],
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $payload['html_body'],
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $payload['to_email'],
                            'name' => $payload['to_name'],
                        ],
                    ],
                ],
            ],
            'saveToSentItems' => true,
        ];

        $response = wp_remote_post(
            'https://graph.microsoft.com/v1.0/me/sendMail',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('outlook_send_failed', 'Outlook send failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('outlook_send_failed', 'Outlook send failed: ' . wp_remote_retrieve_body($response));
        }

        return ['message_id' => null];
    }

    private function send_via_smtp($account, $payload) {
        $settings = $account['settings'];
        if (!$settings || empty($settings['smtp_host'])) {
            return new WP_Error('smtp_missing', 'SMTP settings incomplete.');
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->Port = $settings['smtp_port'] ?? 587;
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'] ?? $account['email_address'];
            $mail->Password = $settings['smtp_password'];
            $encryption = $settings['smtp_encryption'] ?? 'tls';
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom($payload['from_email'], $payload['from_name']);
            $mail->addAddress($payload['to_email'], $payload['to_name']);
            $mail->Subject = $payload['subject'];
            $mail->Body = $payload['html_body'];
            $mail->AltBody = $payload['text_body'];
            $mail->isHTML(true);
            $mail->send();
            return ['message_id' => null];
        } catch (PHPMailerException $e) {
            return new WP_Error('smtp_send_failed', 'SMTP send failed: ' . $e->getMessage());
        }
    }

    private function ensure_token(&$account, $provider) {
        $access = $account['access_token'];
        $expires = !empty($account['token_expires_at']) ? strtotime($account['token_expires_at']) : 0;
        if ($access && $expires > (time() + 60)) {
            return $access;
        }

        $refresh = $account['refresh_token'];
        if (!$refresh) {
            return new WP_Error('token_missing', 'Please reconnect your ' . ucfirst($provider) . ' account.');
        }

        if ($provider === 'gmail') {
            $client_id = trim(get_option('sffc_crm_gmail_client_id', ''));
            $client_secret = trim(get_option('sffc_crm_gmail_client_secret', ''));
            if (!$client_id || !$client_secret) {
                return new WP_Error('missing_credentials', 'Missing Gmail credentials.');
            }
            $body = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ];
            $response = wp_remote_post('https://oauth2.googleapis.com/token', ['body' => $body, 'timeout' => 15]);
        } else {
            $client_id = trim(get_option('sffc_crm_outlook_client_id', ''));
            $client_secret = trim(get_option('sffc_crm_outlook_client_secret', ''));
            if (!$client_id || !$client_secret) {
                return new WP_Error('missing_credentials', 'Missing Outlook credentials.');
            }
            $tenant = trim(get_option('sffc_crm_outlook_tenant', 'common')) ?: 'common';
            $body = [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
                'scope' => 'offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read',
            ];
            $response = wp_remote_post('https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token', ['body' => $body, 'timeout' => 15]);
        }

        if (is_wp_error($response)) {
            return new WP_Error('token_refresh_failed', 'Token refresh failed: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            return new WP_Error('token_refresh_failed', 'Unable to refresh token.');
        }

        $expires_at = gmdate('Y-m-d H:i:s', time() + intval($data['expires_in'] ?? 3600) - 120);
        $this->accounts->update_tokens($account['id'], $account['user_id'], [
            'access_token' => $data['access_token'],
            'refresh_token' => $refresh,
        ], $expires_at);
        $account['access_token'] = $data['access_token'];
        $account['token_expires_at'] = $expires_at;

        return $data['access_token'];
    }

    private function build_raw_message($payload) {
        $boundary = '=_Senna_' . wp_generate_password(16, false);
        $headers = [];
        $headers[] = 'From: ' . $this->format_address($payload['from_name'], $payload['from_email']);
        $headers[] = 'To: ' . $this->format_address($payload['to_name'], $payload['to_email']);
        $headers[] = 'Subject: ' . $this->encode_header($payload['subject']);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $parts = [];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = '';
        $parts[] = $payload['text_body'];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = '';
        $parts[] = $payload['html_body'];
        $parts[] = '--' . $boundary . '--';

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }

    private function format_address($name, $email) {
        $name = trim($name);
        if (!$name) {
            return $email;
        }
        return sprintf('"%s" <%s>', addcslashes($name, '"'), $email);
    }

    private function encode_header($value) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B');
    }

    private function complete_gmail_oauth($user_id, $code) {
        $client_id = trim(get_option('sffc_crm_gmail_client_id', ''));
        $client_secret = trim(get_option('sffc_crm_gmail_client_secret', ''));
        if (!$client_id || !$client_secret) {
            return new WP_Error('missing_credentials', 'Gmail OAuth is not configured.');
        }

        $body = [
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $this->get_redirect_uri(),
            'grant_type' => 'authorization_code',
        ];

        $response = wp_remote_post('https://oauth2.googleapis.com/token', ['body' => $body, 'timeout' => 15]);
        if (is_wp_error($response)) {
            return new WP_Error('oauth_failed', 'Unable to exchange Gmail code: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            return new WP_Error('oauth_failed', 'Gmail token exchange failed.');
        }

        $expires_at = gmdate('Y-m-d H:i:s', time() + intval($data['expires_in'] ?? 3600) - 120);

        $profile = wp_remote_get(
            'https://gmail.googleapis.com/gmail/v1/users/me/profile',
            ['headers' => ['Authorization' => 'Bearer ' . $data['access_token']], 'timeout' => 15]
        );

        $profile_data = !is_wp_error($profile) ? json_decode(wp_remote_retrieve_body($profile), true) : [];

        if (empty($profile_data['emailAddress'])) {
            // Fallback to generic OAuth2 userinfo endpoint
            $userinfo = wp_remote_get(
                'https://www.googleapis.com/oauth2/v2/userinfo',
                ['headers' => ['Authorization' => 'Bearer ' . $data['access_token']], 'timeout' => 15]
            );
            if (!is_wp_error($userinfo)) {
                $profile_data = json_decode(wp_remote_retrieve_body($userinfo), true);
            }
        }

        $email = $profile_data['emailAddress'] ?? $profile_data['email'] ?? '';
        if (!$email) {
            return new WP_Error('oauth_failed', 'Gmail account email not available.');
        }

        $tokens = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
        ];

        $account_id = $this->accounts->save_oauth_account(
            $user_id,
            'gmail',
            $email,
            $profile_data['emailAddress'],
            $tokens,
            $expires_at,
            ['scope' => $data['scope'] ?? '']
        );

        return ['account_id' => $account_id];
    }

    private function complete_outlook_oauth($user_id, $code) {
        $client_id = trim(get_option('sffc_crm_outlook_client_id', ''));
        $client_secret = trim(get_option('sffc_crm_outlook_client_secret', ''));
        if (!$client_id || !$client_secret) {
            return new WP_Error('missing_credentials', 'Outlook OAuth is not configured.');
        }

        $tenant = trim(get_option('sffc_crm_outlook_tenant', 'common')) ?: 'common';
        $body = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'redirect_uri' => $this->get_redirect_uri(),
            'grant_type' => 'authorization_code',
            'scope' => 'offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read',
        ];

        $response = wp_remote_post('https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token', ['body' => $body, 'timeout' => 15]);
        if (is_wp_error($response)) {
            return new WP_Error('oauth_failed', 'Unable to exchange Outlook code: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            return new WP_Error('oauth_failed', 'Outlook token exchange failed.');
        }

        $expires_at = gmdate('Y-m-d H:i:s', time() + intval($data['expires_in'] ?? 3600) - 120);
        $profile = wp_remote_get(
            'https://graph.microsoft.com/v1.0/me',
            ['headers' => ['Authorization' => 'Bearer ' . $data['access_token']], 'timeout' => 15]
        );
        if (is_wp_error($profile)) {
            return new WP_Error('oauth_failed', 'Unable to fetch Outlook profile.');
        }
        $profile_data = json_decode(wp_remote_retrieve_body($profile), true);
        $email = $profile_data['mail'] ?? $profile_data['userPrincipalName'] ?? '';
        if (!$email) {
            return new WP_Error('oauth_failed', 'Outlook email address unavailable.');
        }

        $name = $profile_data['displayName'] ?? $email;
        $tokens = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
        ];

        $account_id = $this->accounts->save_oauth_account(
            $user_id,
            'outlook',
            $email,
            $name,
            $tokens,
            $expires_at,
            []
        );

        return ['account_id' => $account_id];
    }
}
