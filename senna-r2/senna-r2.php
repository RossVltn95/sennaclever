<?php
/**
 * Plugin Name: Senna R2
 * Description: Cloudflare R2 media offload and media URL rewriting for Senna.
 * Version: 1.0.0
 * Author: Senna
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Senna_R2_Plugin {
	const OPTION = 'senna_r2_settings';
	const META_UPLOADED = '_senna_r2_uploaded';

	/**
	 * Boot the plugin once.
	 */
	public static function init() {
		static $instance = null;

		if (!$instance) {
			$instance = new self();
		}
	}

	public static function activate() {
		$existing = get_option(self::OPTION);

		if (!is_array($existing)) {
			add_option(self::OPTION, self::defaults(), '', false);
			return;
		}

		update_option(self::OPTION, array_merge(self::defaults(), $existing), false);
	}

	private function __construct() {
		add_action('admin_menu', [$this, 'admin_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_notices', [$this, 'admin_notices']);
		add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
		add_action('admin_bar_menu', [$this, 'add_admin_bar_purge_button'], 100);
		add_action('admin_post_senna_r2_purge_cache', [$this, 'handle_purge_cache_request']);

		add_filter('wp_generate_attachment_metadata', [$this, 'upload_attachment_metadata'], 20, 3);
		add_filter('wp_get_attachment_url', [$this, 'filter_attachment_url'], 20, 2);
		add_filter('image_downsize', [$this, 'filter_image_downsize'], 20, 3);
		add_filter('wp_calculate_image_srcset', [$this, 'filter_image_srcset'], 20, 5);
		add_filter('wp_prepare_attachment_for_js', [$this, 'filter_prepare_attachment_for_js'], 20, 3);
		add_action('delete_attachment', [$this, 'delete_attachment_objects'], 20, 2);
	}

	private static function defaults() {
		return [
			'enabled' => 0,
			'rewrite_urls' => 1,
			'offload_uploads' => 0,
			'delete_local' => 0,
			'delete_remote' => 0,
			'account_id' => '',
			'bucket' => '',
			'access_key_id' => '',
			'secret_access_key' => '',
			'public_base_url' => 'https://media.joinsenna.com',
			'cloudflare_zone_id' => '',
			'cloudflare_analytics_token' => '',
			'show_purge_admin_bar' => 1,
		];
	}

	private function settings() {
		$settings = get_option(self::OPTION, []);

		if (!is_array($settings)) {
			$settings = [];
		}

		return array_merge(self::defaults(), $settings);
	}

	private function rewrite_enabled() {
		$settings = $this->settings();

		return !empty($settings['enabled']) && !empty($settings['rewrite_urls']) && $this->public_base_url();
	}

	private function offload_enabled() {
		$settings = $this->settings();

		return !empty($settings['enabled'])
			&& !empty($settings['offload_uploads'])
			&& !empty($settings['account_id'])
			&& !empty($settings['bucket'])
			&& !empty($settings['access_key_id'])
			&& !empty($settings['secret_access_key']);
	}

	private function public_base_url() {
		$settings = $this->settings();
		$url = isset($settings['public_base_url']) ? trim((string) $settings['public_base_url']) : '';

		if (!$url) {
			return '';
		}

		return untrailingslashit(esc_url_raw($url));
	}

	public function admin_menu() {
		add_options_page(
			__('Senna R2', 'senna-r2'),
			__('Senna R2', 'senna-r2'),
			'manage_options',
			'senna-r2',
			[$this, 'render_settings_page']
		);
	}

	public function register_settings() {
		register_setting('senna_r2', self::OPTION, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_settings'],
			'default' => self::defaults(),
		]);
	}

	public function sanitize_settings($input) {
		$existing = $this->settings();
		$input = is_array($input) ? $input : [];

		$settings = self::defaults();
		$settings['enabled'] = empty($input['enabled']) ? 0 : 1;
		$settings['rewrite_urls'] = empty($input['rewrite_urls']) ? 0 : 1;
		$settings['offload_uploads'] = empty($input['offload_uploads']) ? 0 : 1;
		$settings['delete_local'] = empty($input['delete_local']) ? 0 : 1;
		$settings['delete_remote'] = empty($input['delete_remote']) ? 0 : 1;
		$settings['show_purge_admin_bar'] = empty($input['show_purge_admin_bar']) ? 0 : 1;
		$settings['account_id'] = sanitize_text_field($input['account_id'] ?? '');
		$settings['bucket'] = sanitize_text_field($input['bucket'] ?? '');
		$settings['access_key_id'] = sanitize_text_field($input['access_key_id'] ?? '');
		$settings['public_base_url'] = untrailingslashit(esc_url_raw($input['public_base_url'] ?? self::defaults()['public_base_url']));
		$settings['cloudflare_zone_id'] = sanitize_text_field($input['cloudflare_zone_id'] ?? '');

		$secret = isset($input['secret_access_key']) ? trim((string) $input['secret_access_key']) : '';
		$settings['secret_access_key'] = $secret === '' ? ($existing['secret_access_key'] ?? '') : $secret;

		$analytics_token = isset($input['cloudflare_analytics_token']) ? trim((string) $input['cloudflare_analytics_token']) : '';
		$settings['cloudflare_analytics_token'] = $analytics_token === '' ? ($existing['cloudflare_analytics_token'] ?? '') : $analytics_token;

		delete_transient('senna_r2_cloudflare_analytics_day');
		delete_transient('senna_r2_cloudflare_analytics_week');
		delete_transient('senna_r2_cloudflare_analytics_month');

		return $settings;
	}

	public function admin_notices() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$settings = $this->settings();

		if (!empty($_GET['senna_r2_purge'])) {
			$type = sanitize_text_field(wp_unslash($_GET['senna_r2_purge']));

			if ($type === 'success') {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cloudflare cache cleared.', 'senna-r2') . '</p></div>';
			} elseif ($type === 'failed') {
				$message = get_transient('senna_r2_last_purge_error');
				$message = $message ? $message : __('Check the Senna R2 Cloudflare token, zone ID, and Cache Purge permission.', 'senna-r2');
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Cloudflare cache purge failed:', 'senna-r2') . ' ' . esc_html($message) . '</p></div>';
			}
		}

		if (empty($settings['enabled']) || empty($settings['offload_uploads']) || $this->offload_enabled()) {
			return;
		}

		if ($screen && strpos((string) $screen->id, 'settings_page_senna-r2') === false && $screen->base !== 'upload') {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__('Senna R2 upload offload is enabled, but Cloudflare R2 credentials are incomplete. URL rewriting can still work, but new uploads will stay local until credentials are saved.', 'senna-r2');
		echo '</p></div>';
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = $this->settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Senna R2', 'senna-r2'); ?></h1>
			<p><?php esc_html_e('Rewrite WordPress media URLs to the Senna media domain and optionally offload new uploads to Cloudflare R2.', 'senna-r2'); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields('senna_r2'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e('Enable Senna R2', 'senna-r2'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($settings['enabled']); ?>>
								<?php esc_html_e('Enable media URL rewriting and selected R2 features.', 'senna-r2'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Public media URL', 'senna-r2'); ?></th>
						<td>
							<input class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION); ?>[public_base_url]" value="<?php echo esc_attr($settings['public_base_url']); ?>" placeholder="https://media.joinsenna.com">
							<p class="description"><?php esc_html_e('Existing attachments will be served as this URL plus their _wp_attached_file path, for example /2026/02/file.jpeg.', 'senna-r2'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Rewrite attachment URLs', 'senna-r2'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[rewrite_urls]" value="1" <?php checked($settings['rewrite_urls']); ?>>
								<?php esc_html_e('Rewrite front-end and Media Library attachment URLs to the public media URL.', 'senna-r2'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Offload new uploads', 'senna-r2'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[offload_uploads]" value="1" <?php checked($settings['offload_uploads']); ?>>
								<?php esc_html_e('Upload newly generated attachment files and image sizes to R2.', 'senna-r2'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Delete local files after upload', 'senna-r2'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[delete_local]" value="1" <?php checked($settings['delete_local']); ?>>
								<?php esc_html_e('Only enable after confirming R2 uploads and media rendering are stable.', 'senna-r2'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Delete R2 objects when attachment is deleted', 'senna-r2'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[delete_remote]" value="1" <?php checked($settings['delete_remote']); ?>>
								<?php esc_html_e('Remove main and generated image objects from R2 when the WordPress attachment is deleted.', 'senna-r2'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('R2 account ID', 'senna-r2'); ?></th>
						<td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[account_id]" value="<?php echo esc_attr($settings['account_id']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('R2 bucket', 'senna-r2'); ?></th>
						<td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[bucket]" value="<?php echo esc_attr($settings['bucket']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('R2 access key ID', 'senna-r2'); ?></th>
						<td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[access_key_id]" value="<?php echo esc_attr($settings['access_key_id']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('R2 secret access key', 'senna-r2'); ?></th>
						<td>
							<input class="regular-text" type="password" name="<?php echo esc_attr(self::OPTION); ?>[secret_access_key]" value="" autocomplete="new-password">
							<p class="description"><?php esc_html_e('Leave blank to keep the existing saved secret.', 'senna-r2'); ?></p>
						</td>
					</tr>
					<tr>
						<th colspan="2"><h2><?php esc_html_e('Cloudflare dashboard analytics', 'senna-r2'); ?></h2></th>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Cloudflare zone ID', 'senna-r2'); ?></th>
						<td>
							<input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[cloudflare_zone_id]" value="<?php echo esc_attr($settings['cloudflare_zone_id']); ?>">
							<p class="description"><?php esc_html_e('Required for the WordPress dashboard visitor widget.', 'senna-r2'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Cloudflare analytics API token', 'senna-r2'); ?></th>
						<td>
							<input class="regular-text" type="password" name="<?php echo esc_attr(self::OPTION); ?>[cloudflare_analytics_token]" value="" autocomplete="new-password">
							<p class="description"><?php esc_html_e('Use a Cloudflare API token with Zone Analytics: Read. Add Cache Purge: Purge if you want the top-bar cache button to work. Leave blank to keep the existing saved token.', 'senna-r2'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Top-bar cache purge button', 'senna-r2'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[show_purge_admin_bar]" value="1" <?php checked($settings['show_purge_admin_bar']); ?>>
								<?php esc_html_e('Show a Clear Cloudflare Cache button in the WordPress admin bar for administrators.', 'senna-r2'); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function add_admin_bar_purge_button($wp_admin_bar) {
		if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
			return;
		}

		$settings = $this->settings();

		if (empty($settings['enabled']) || empty($settings['show_purge_admin_bar']) || empty($settings['cloudflare_zone_id']) || empty($settings['cloudflare_analytics_token'])) {
			return;
		}

		$href = wp_nonce_url(
			admin_url('admin-post.php?action=senna_r2_purge_cache'),
			'senna_r2_purge_cache'
		);

		$wp_admin_bar->add_node([
			'id' => 'senna-r2-purge-cache',
			'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__('Clear CF Cache', 'senna-r2') . '</span>',
			'href' => $href,
		]);

		add_action('wp_print_footer_scripts', [$this, 'print_admin_bar_purge_assets']);
		add_action('admin_print_footer_scripts', [$this, 'print_admin_bar_purge_assets']);
	}

	public function print_admin_bar_purge_assets() {
		static $printed = false;

		if ($printed || !is_admin_bar_showing() || !current_user_can('manage_options')) {
			return;
		}

		$printed = true;
		?>
		<style>
			#wpadminbar #wp-admin-bar-senna-r2-purge-cache .ab-icon:before {
				content: "\f463";
				top: 2px;
			}
			#wpadminbar #wp-admin-bar-senna-r2-purge-cache .ab-icon.is-senna-r2-purging:before {
				animation: senna-r2-adminbar-spin 1s linear infinite;
				color: #8effd1;
			}
			#wpadminbar #wp-admin-bar-senna-r2-purge-cache .ab-icon.is-senna-r2-purged:before {
				color: #46b450;
			}
			#wpadminbar #wp-admin-bar-senna-r2-purge-cache .ab-icon.is-senna-r2-failed:before {
				color: #dc3232;
			}
			@keyframes senna-r2-adminbar-spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
			@media screen and (max-width: 782px) {
				#wpadminbar li#wp-admin-bar-senna-r2-purge-cache {
					display: block;
				}
			}
		</style>
		<script>
			window.addEventListener('DOMContentLoaded', function () {
				var node = document.querySelector('#wp-admin-bar-senna-r2-purge-cache a');
				if (!node) {
					return;
				}

				node.addEventListener('click', async function (event) {
					event.preventDefault();

					var icon = document.querySelector('#wp-admin-bar-senna-r2-purge-cache .ab-icon');
					var label = document.querySelector('#wp-admin-bar-senna-r2-purge-cache .ab-label');
					var originalLabel = label ? label.textContent : '';

					if (icon) {
						icon.classList.remove('is-senna-r2-purged', 'is-senna-r2-failed');
						icon.classList.add('is-senna-r2-purging');
					}
					if (label) {
						label.textContent = 'Clearing...';
					}

					try {
						var response = await fetch(node.href, {
							method: 'POST',
							credentials: 'same-origin',
							headers: {
								'X-Requested-With': 'XMLHttpRequest'
							}
						});
						var payload = await response.json();

						if (!response.ok || !payload.success) {
							throw new Error(payload.data && payload.data.message ? payload.data.message : 'Cache purge failed');
						}

						if (icon) {
							icon.classList.remove('is-senna-r2-purging');
							icon.classList.add('is-senna-r2-purged');
						}
						if (label) {
							label.textContent = 'Cache cleared';
						}
					} catch (error) {
						console.error(error);
						if (icon) {
							icon.classList.remove('is-senna-r2-purging');
							icon.classList.add('is-senna-r2-failed');
						}
						if (label) {
							label.textContent = error.message ? error.message.slice(0, 36) : 'Purge failed';
							node.title = error.message || 'Purge failed';
						}
					}

					window.setTimeout(function () {
						if (icon) {
							icon.classList.remove('is-senna-r2-purging', 'is-senna-r2-purged', 'is-senna-r2-failed');
						}
						if (label) {
							label.textContent = originalLabel;
						}
					}, 2500);
				});
			});
		</script>
		<?php
	}

	public function handle_purge_cache_request() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to purge Cloudflare cache.', 'senna-r2'));
		}

		check_admin_referer('senna_r2_purge_cache');

		$result = $this->purge_cloudflare_cache();
		$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

		if (is_wp_error($result)) {
			set_transient('senna_r2_last_purge_error', $result->get_error_message(), 15 * MINUTE_IN_SECONDS);

			if ($is_ajax) {
				wp_send_json_error(['message' => $result->get_error_message()], 500);
			}

			wp_safe_redirect(add_query_arg('senna_r2_purge', 'failed', wp_get_referer() ?: admin_url()));
			exit;
		}

		delete_transient('senna_r2_cloudflare_analytics_day');
		delete_transient('senna_r2_cloudflare_analytics_week');
		delete_transient('senna_r2_cloudflare_analytics_month');
		delete_transient('senna_r2_last_purge_error');

		if ($is_ajax) {
			wp_send_json_success(['message' => __('Cloudflare cache cleared.', 'senna-r2')]);
		}

		wp_safe_redirect(add_query_arg('senna_r2_purge', 'success', wp_get_referer() ?: admin_url()));
		exit;
	}

	private function purge_cloudflare_cache() {
		$settings = $this->settings();

		if (empty($settings['cloudflare_zone_id']) || empty($settings['cloudflare_analytics_token'])) {
			return new WP_Error('senna_r2_missing_cloudflare_purge_config', 'Cloudflare zone ID or API token is missing.');
		}

		$response = wp_remote_post(
			'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($settings['cloudflare_zone_id']) . '/purge_cache',
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $settings['cloudflare_analytics_token'],
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode([
					'purge_everything' => true,
				]),
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if ($status < 200 || $status >= 300) {
			$message = !empty($body['errors'][0]['message']) ? $body['errors'][0]['message'] : wp_remote_retrieve_body($response);
			return new WP_Error('senna_r2_cloudflare_purge_http_error', sprintf('Cloudflare purge failed with HTTP %d: %s', $status, $message));
		}

		if (empty($body['success'])) {
			$message = !empty($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Cloudflare purge failed.';
			return new WP_Error('senna_r2_cloudflare_purge_error', $message);
		}

		return true;
	}

	public function register_dashboard_widget() {
		if (!current_user_can('manage_options')) {
			return;
		}

		wp_add_dashboard_widget(
			'senna_r2_cloudflare_traffic',
			__('Senna Cloudflare Traffic', 'senna-r2'),
			[$this, 'render_dashboard_widget']
		);
	}

	public function render_dashboard_widget() {
		$settings = $this->settings();

		if (empty($settings['cloudflare_zone_id']) || empty($settings['cloudflare_analytics_token'])) {
			echo '<p>' . esc_html__('Add a Cloudflare zone ID and analytics API token in Settings > Senna R2 to show traffic analytics here.', 'senna-r2') . '</p>';
			echo '<p><a class="button" href="' . esc_url(admin_url('options-general.php?page=senna-r2')) . '">' . esc_html__('Configure Senna R2', 'senna-r2') . '</a></p>';
			return;
		}

		$ranges = [
			'day' => __('Last 24 hours', 'senna-r2'),
			'week' => __('Last 7 days', 'senna-r2'),
			'month' => __('Last 30 days', 'senna-r2'),
		];
		$rows = [];

		foreach ($ranges as $range => $label) {
			$data = $this->get_cloudflare_analytics($range);
			$rows[] = [
				'label' => $label,
				'data' => $data,
			];
		}

		echo '<style>
			.senna-r2-dashboard-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 14px}
			.senna-r2-dashboard-stat{border:1px solid #dcdcde;background:#fff;border-radius:8px;padding:10px}
			.senna-r2-dashboard-stat strong{display:block;font-size:20px;line-height:1.1;color:#0d1d42}
			.senna-r2-dashboard-stat span{display:block;margin-top:4px;color:#646970;font-size:12px}
			.senna-r2-dashboard-table{width:100%;border-collapse:collapse}
			.senna-r2-dashboard-table th,.senna-r2-dashboard-table td{padding:8px 6px;border-bottom:1px solid #dcdcde;text-align:left}
			.senna-r2-dashboard-table th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#646970}
			.senna-r2-dashboard-error{color:#b32d2e}
			@media (max-width:782px){.senna-r2-dashboard-stats{grid-template-columns:1fr}.senna-r2-dashboard-table{display:block;overflow:auto}}
		</style>';

		$primary = $rows[0]['data'];
		if (is_wp_error($primary)) {
			echo '<p class="senna-r2-dashboard-error">' . esc_html($primary->get_error_message()) . '</p>';
			return;
		}

		echo '<div class="senna-r2-dashboard-stats">';
		echo '<div class="senna-r2-dashboard-stat"><strong>' . esc_html(number_format_i18n($primary['unique_visitors'])) . '</strong><span>' . esc_html__('Unique visitors, last 24h', 'senna-r2') . '</span></div>';
		echo '<div class="senna-r2-dashboard-stat"><strong>' . esc_html(number_format_i18n($primary['requests'])) . '</strong><span>' . esc_html__('Requests, last 24h', 'senna-r2') . '</span></div>';
		echo '<div class="senna-r2-dashboard-stat"><strong>' . esc_html($primary['cache_hit_percent'] . '%') . '</strong><span>' . esc_html__('Cache hit rate, last 24h', 'senna-r2') . '</span></div>';
		echo '</div>';

		echo '<table class="senna-r2-dashboard-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Range', 'senna-r2') . '</th>';
		echo '<th>' . esc_html__('Visitors', 'senna-r2') . '</th>';
		echo '<th>' . esc_html__('Page views', 'senna-r2') . '</th>';
		echo '<th>' . esc_html__('Requests', 'senna-r2') . '</th>';
		echo '<th>' . esc_html__('Bandwidth', 'senna-r2') . '</th>';
		echo '<th>' . esc_html__('Cached', 'senna-r2') . '</th>';
		echo '<th>' . esc_html__('Threats', 'senna-r2') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$data = $row['data'];

			if (is_wp_error($data)) {
				echo '<tr><td>' . esc_html($row['label']) . '</td><td colspan="6" class="senna-r2-dashboard-error">' . esc_html($data->get_error_message()) . '</td></tr>';
				continue;
			}

			echo '<tr>';
			echo '<td>' . esc_html($row['label']) . '</td>';
			echo '<td>' . esc_html(number_format_i18n($data['unique_visitors'])) . '</td>';
			echo '<td>' . esc_html(number_format_i18n($data['page_views'])) . '</td>';
			echo '<td>' . esc_html(number_format_i18n($data['requests'])) . '</td>';
			echo '<td>' . esc_html(size_format($data['bytes'], 1)) . '</td>';
			echo '<td>' . esc_html($data['cache_hit_percent'] . '%') . '</td>';
			echo '<td>' . esc_html(number_format_i18n($data['threats'])) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin-top:10px;color:#646970">' . esc_html__('Cloudflare results are cached for 15 minutes to keep wp-admin fast.', 'senna-r2') . '</p>';
	}

	private function get_cloudflare_analytics($range = 'day') {
		$range = in_array($range, ['day', 'week', 'month'], true) ? $range : 'day';
		$cache_key = 'senna_r2_cloudflare_analytics_' . $range;
		$cached = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$days = [
			'day' => 1,
			'week' => 7,
			'month' => 30,
		][$range];

		$result = $this->query_cloudflare_zone_analytics($days);

		if (is_wp_error($result)) {
			return $result;
		}

		set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);

		return $result;
	}

	private function query_cloudflare_zone_analytics($days) {
		$settings = $this->settings();
		$days = max(1, min(30, (int) $days));
		$until = time() - 60;
		$since = $until - ($days * DAY_IN_SECONDS);

		if ($days === 1) {
			$group_name = 'httpRequests1hGroups';
			$timeslot = 'datetime';
			$date_format = 'Y-m-d\TH:i:s\Z';
		} else {
			$group_name = 'httpRequests1dGroups';
			$timeslot = 'date';
			$date_format = 'Y-m-d';
		}

		$query = 'query SennaR2ZoneAnalytics($zoneTag: string, $since: string, $until: string) {
			viewer {
				zones(filter: {zoneTag: $zoneTag}) {
					totals: ' . $group_name . '(limit: 10000, filter: {' . $timeslot . '_geq: $since, ' . $timeslot . '_lt: $until}) {
						uniq {
							uniques
						}
					}
					zones: ' . $group_name . '(limit: 10000, filter: {' . $timeslot . '_geq: $since, ' . $timeslot . '_lt: $until}) {
						sum {
							bytes
							cachedBytes
							cachedRequests
							pageViews
							requests
							threats
						}
					}
				}
			}
		}';

		$response = wp_remote_post('https://api.cloudflare.com/client/v4/graphql', [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $settings['cloudflare_analytics_token'],
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode([
				'operationName' => 'SennaR2ZoneAnalytics',
				'query' => $query,
				'variables' => [
					'zoneTag' => $settings['cloudflare_zone_id'],
					'since' => gmdate($date_format, $since),
					'until' => gmdate($date_format, $until),
				],
			]),
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if ($status < 200 || $status >= 300) {
			return new WP_Error('senna_r2_cloudflare_http_error', sprintf('Cloudflare analytics request failed with HTTP %d.', $status));
		}

		if (!empty($body['errors']) && is_array($body['errors'])) {
			$message = !empty($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Cloudflare GraphQL returned an error.';
			return new WP_Error('senna_r2_cloudflare_graphql_error', $message);
		}

		$zone = $body['data']['viewer']['zones'][0] ?? null;

		if (!$zone) {
			return new WP_Error('senna_r2_cloudflare_no_zone', 'Cloudflare returned no analytics for this zone ID.');
		}

		$unique_visitors = 0;
		if (!empty($zone['totals']) && is_array($zone['totals'])) {
			foreach ($zone['totals'] as $total) {
				$unique_visitors += isset($total['uniq']['uniques']) ? (int) $total['uniq']['uniques'] : 0;
			}
		}

		$requests = 0;
		$page_views = 0;
		$bytes = 0;
		$cached_bytes = 0;
		$cached_requests = 0;
		$threats = 0;

		if (!empty($zone['zones']) && is_array($zone['zones'])) {
			foreach ($zone['zones'] as $group) {
				$sum = $group['sum'] ?? [];
				$requests += isset($sum['requests']) ? (int) $sum['requests'] : 0;
				$page_views += isset($sum['pageViews']) ? (int) $sum['pageViews'] : 0;
				$bytes += isset($sum['bytes']) ? (int) $sum['bytes'] : 0;
				$cached_bytes += isset($sum['cachedBytes']) ? (int) $sum['cachedBytes'] : 0;
				$cached_requests += isset($sum['cachedRequests']) ? (int) $sum['cachedRequests'] : 0;
				$threats += isset($sum['threats']) ? (int) $sum['threats'] : 0;
			}
		}

		return [
			'unique_visitors' => $unique_visitors,
			'page_views' => $page_views,
			'requests' => $requests,
			'bytes' => $bytes,
			'cached_bytes' => $cached_bytes,
			'cached_requests' => $cached_requests,
			'cache_hit_percent' => $requests > 0 ? round(($cached_requests / $requests) * 100, 1) : 0,
			'threats' => $threats,
		];
	}

	public function upload_attachment_metadata($meta, $attachment_id, $context = '') {
		if (!$this->offload_enabled() || !is_array($meta)) {
			return $meta;
		}

		$files = $this->attachment_files($attachment_id, $meta);

		if (!$files) {
			return $meta;
		}

		$uploaded = [];
		$failed = [];

		foreach ($files as $local_path => $key) {
			$result = $this->upload_file($local_path, $key);

			if (is_wp_error($result)) {
				$failed[$key] = $result->get_error_message();
				continue;
			}

			$uploaded[$key] = time();
		}

		if ($uploaded) {
			$meta['senna_r2'] = [
				'uploaded' => true,
				'uploaded_at' => time(),
				'public_base_url' => $this->public_base_url(),
			];
			update_post_meta($attachment_id, self::META_UPLOADED, $uploaded);
		}

		if ($failed) {
			update_post_meta($attachment_id, '_senna_r2_upload_errors', $failed);
		} else {
			delete_post_meta($attachment_id, '_senna_r2_upload_errors');
			$this->maybe_delete_local_files(array_keys($files));
		}

		return $meta;
	}

	public function filter_attachment_url($url, $post_id) {
		if (!$this->rewrite_enabled()) {
			return $url;
		}

		$relative = $this->attachment_relative_path($post_id);

		return $relative ? $this->remote_url($relative) : $url;
	}

	public function filter_image_downsize($downsize, $attachment_id, $size) {
		if (!$this->rewrite_enabled()) {
			return $downsize;
		}

		$relative = $this->attachment_relative_path($attachment_id);

		if (!$relative) {
			return $downsize;
		}

		$meta = wp_get_attachment_metadata($attachment_id);
		$width = 0;
		$height = 0;
		$is_intermediate = false;
		$target_relative = $relative;

		if ($size === 'full' || empty($meta['sizes']) || !is_string($size) || empty($meta['sizes'][$size]['file'])) {
			$width = isset($meta['width']) ? (int) $meta['width'] : 0;
			$height = isset($meta['height']) ? (int) $meta['height'] : 0;
		} else {
			$target_relative = trailingslashit(dirname($relative)) . $meta['sizes'][$size]['file'];
			$width = isset($meta['sizes'][$size]['width']) ? (int) $meta['sizes'][$size]['width'] : 0;
			$height = isset($meta['sizes'][$size]['height']) ? (int) $meta['sizes'][$size]['height'] : 0;
			$is_intermediate = true;
		}

		return [$this->remote_url($target_relative), $width, $height, $is_intermediate];
	}

	public function filter_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
		if (!$this->rewrite_enabled() || !is_array($sources)) {
			return $sources;
		}

		$upload = wp_get_upload_dir();
		$local_base_url = isset($upload['baseurl']) ? untrailingslashit($upload['baseurl']) : '';
		$remote_base_url = $this->public_base_url();

		foreach ($sources as $width => $source) {
			if (empty($source['url'])) {
				continue;
			}

			if ($local_base_url && strpos($source['url'], $local_base_url) === 0) {
				$sources[$width]['url'] = $remote_base_url . substr($source['url'], strlen($local_base_url));
			}
		}

		return $sources;
	}

	public function filter_prepare_attachment_for_js($response, $attachment, $meta) {
		if (!$this->rewrite_enabled() || empty($attachment->ID) || !is_array($response)) {
			return $response;
		}

		$relative = $this->attachment_relative_path($attachment->ID);

		if (!$relative) {
			return $response;
		}

		$response['url'] = $this->remote_url($relative);

		if (isset($response['sizes']) && is_array($response['sizes']) && is_array($meta) && !empty($meta['sizes']) && is_array($meta['sizes'])) {
			$dir = trim(dirname($relative), './');

			foreach ($meta['sizes'] as $size_name => $size_meta) {
				if (empty($size_meta['file']) || empty($response['sizes'][$size_name])) {
					continue;
				}

				$size_relative = ($dir ? trailingslashit($dir) : '') . $size_meta['file'];
				$response['sizes'][$size_name]['url'] = $this->remote_url($size_relative);
			}
		}

		$response['sennaR2Url'] = $response['url'];
		$response['sennaR2Enabled'] = true;

		return $response;
	}

	public function delete_attachment_objects($post_id, $post = null) {
		$settings = $this->settings();

		if (empty($settings['delete_remote']) || !$this->offload_enabled()) {
			return;
		}

		$meta = wp_get_attachment_metadata($post_id);
		$files = $this->attachment_files($post_id, is_array($meta) ? $meta : []);

		foreach ($files as $key) {
			$this->r2_request('DELETE', $key);
		}
	}

	private function attachment_relative_path($attachment_id) {
		$relative = get_post_meta($attachment_id, '_wp_attached_file', true);

		if (!$relative) {
			return '';
		}

		return $this->normalise_key($relative);
	}

	private function attachment_files($attachment_id, array $meta) {
		$relative = '';

		if (!empty($meta['file'])) {
			$relative = $meta['file'];
		}

		if (!$relative) {
			$relative = $this->attachment_relative_path($attachment_id);
		}

		if (!$relative) {
			return [];
		}

		$upload = wp_get_upload_dir();
		$base_dir = trailingslashit($upload['basedir']);
		$relative = $this->normalise_key($relative);
		$dir = trim(dirname($relative), './');
		$files = [];

		$main_path = $base_dir . $relative;
		if (is_readable($main_path)) {
			$files[$main_path] = $relative;
		}

		if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
			foreach ($meta['sizes'] as $size) {
				if (empty($size['file'])) {
					continue;
				}

				$size_relative = ($dir ? trailingslashit($dir) : '') . $size['file'];
				$size_path = $base_dir . $size_relative;

				if (is_readable($size_path)) {
					$files[$size_path] = $this->normalise_key($size_relative);
				}
			}
		}

		if (!empty($meta['original_image'])) {
			$original_relative = ($dir ? trailingslashit($dir) : '') . $meta['original_image'];
			$original_path = $base_dir . $original_relative;

			if (is_readable($original_path)) {
				$files[$original_path] = $this->normalise_key($original_relative);
			}
		}

		return $files;
	}

	private function upload_file($local_path, $key) {
		$contents = file_get_contents($local_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ($contents === false || $contents === '') {
			return new WP_Error('senna_r2_empty_file', sprintf('Local file could not be read: %s', $local_path));
		}

		$filetype = wp_check_filetype($local_path);
		$content_type = !empty($filetype['type']) ? $filetype['type'] : 'application/octet-stream';

		return $this->r2_request('PUT', $key, $contents, [
			'Content-Type' => $content_type,
			'Cache-Control' => 'public, max-age=31536000, immutable',
		]);
	}

	private function maybe_delete_local_files(array $local_paths) {
		$settings = $this->settings();

		if (empty($settings['delete_local'])) {
			return;
		}

		foreach ($local_paths as $local_path) {
			if (is_readable($local_path)) {
				wp_delete_file($local_path);
			}
		}
	}

	private function remote_url($relative) {
		return $this->public_base_url() . '/' . ltrim($this->normalise_key($relative), '/');
	}

	private function normalise_key($key) {
		$key = str_replace('\\', '/', (string) $key);
		$key = preg_replace('#/+#', '/', $key);
		$key = ltrim($key, '/');
		$key = str_replace('../', '', $key);

		return $key;
	}

	private function r2_request($method, $key, $body = '', array $extra_headers = []) {
		$settings = $this->settings();
		$bucket = $settings['bucket'];
		$account_id = $settings['account_id'];
		$key = $this->normalise_key($key);
		$method = strtoupper($method);
		$payload_hash = hash('sha256', (string) $body);
		$time = time();
		$amz_date = gmdate('Ymd\THis\Z', $time);
		$date = gmdate('Ymd', $time);
		$host = $account_id . '.r2.cloudflarestorage.com';
		$canonical_uri = '/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($key));
		$url = 'https://' . $host . $canonical_uri;

		$amz_headers = [
			'host' => $host,
			'x-amz-content-sha256' => $payload_hash,
			'x-amz-date' => $amz_date,
		];
		ksort($amz_headers);

		$canonical_headers = '';
		foreach ($amz_headers as $header => $value) {
			$canonical_headers .= $header . ':' . $value . "\n";
		}

		$signed_headers = implode(';', array_keys($amz_headers));
		$canonical_request = $method . "\n"
			. $canonical_uri . "\n\n"
			. $canonical_headers . "\n"
			. $signed_headers . "\n"
			. $payload_hash;
		$scope = $date . '/auto/s3/aws4_request';
		$string_to_sign = "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $scope . "\n" . hash('sha256', $canonical_request);
		$signature = hash_hmac('sha256', $string_to_sign, $this->signing_key($settings['secret_access_key'], $date), false);

		$headers = array_merge($extra_headers, [
			'Authorization' => 'AWS4-HMAC-SHA256 Credential=' . $settings['access_key_id'] . '/' . $scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature,
			'Host' => $host,
			'X-Amz-Content-Sha256' => $payload_hash,
			'X-Amz-Date' => $amz_date,
		]);

		$response = wp_remote_request($url, [
			'method' => $method,
			'headers' => $headers,
			'body' => $body,
			'timeout' => 30,
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		if ($status < 200 || $status >= 300) {
			return new WP_Error('senna_r2_request_failed', sprintf('R2 %s failed for %s with HTTP %d: %s', $method, $key, $status, wp_remote_retrieve_body($response)));
		}

		return $response;
	}

	private function signing_key($secret, $date) {
		$date_key = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
		$region_key = hash_hmac('sha256', 'auto', $date_key, true);
		$service_key = hash_hmac('sha256', 's3', $region_key, true);

		return hash_hmac('sha256', 'aws4_request', $service_key, true);
	}
}

add_action('plugins_loaded', ['Senna_R2_Plugin', 'init']);
register_activation_hook(__FILE__, ['Senna_R2_Plugin', 'activate']);
