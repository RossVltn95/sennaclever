<?php
/**
 * Prompt-led entry shortcode for the gap analyzer.
 *
 * @package SennaCareers
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Gap_Analyzer_Prompt_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('sffc_gap_analyzer_prompt', array($this, 'render'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    public function maybe_enqueue_assets() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'sffc_gap_analyzer_prompt')) {
            return;
        }

        $this->enqueue_assets();
    }

    private function enqueue_assets() {
        wp_enqueue_style(
            'sffc-gap-analyzer-prompt',
            SFFC_PLUGIN_URL . 'assets/css/gap-analyzer-prompt.css',
            array(),
            SFFC_VERSION
        );

        wp_enqueue_script(
            'sffc-gap-analyzer-prompt',
            SFFC_PLUGIN_URL . 'assets/js/gap-analyzer-prompt.js',
            array(),
            SFFC_VERSION,
            true
        );
    }

    public function render($atts) {
        $atts = shortcode_atts(
            array(
                'target_url' => '',
                'title' => __('Find out what is missing before you apply.', 'senna-finance'),
                'subtitle' => __('Paste the full job description first. MENA Careers will load the role, then ask for your CV in the next step.', 'senna-finance'),
                'button_text' => __('Check this role', 'senna-finance'),
                'label' => __('Start with the role', 'senna-finance'),
                'placeholder' => __('Paste the LinkedIn job description here. You can add your CV after the role check.', 'senna-finance'),
                'job_title_placeholder' => __('Optional job title', 'senna-finance'),
            ),
            $atts,
            'sffc_gap_analyzer_prompt'
        );

        $this->enqueue_assets();

        $target_url = $this->resolve_target_url($atts['target_url']);

        ob_start();
        ?>
        <section class="sffc-gap-prompt" data-component="gap-analyzer-prompt">
            <div class="sffc-gap-prompt__hero">
                <span class="sffc-gap-prompt__eyebrow"><?php echo esc_html((string) $atts['label']); ?></span>
                <h2 class="sffc-gap-prompt__title"><?php echo esc_html((string) $atts['title']); ?></h2>
                <p class="sffc-gap-prompt__subtitle"><?php echo esc_html((string) $atts['subtitle']); ?></p>
            </div>

            <form class="sffc-gap-prompt__shell" method="post" action="<?php echo esc_url($target_url); ?>" data-gap-prompt-form>
                <div class="sffc-gap-prompt__top">
                    <span class="sffc-gap-prompt__label"><?php esc_html_e('Job description', 'senna-finance'); ?></span>
                    <input
                        type="text"
                        class="sffc-gap-prompt__job-title"
                        name="senna_gap_prefill_job_title"
                        placeholder="<?php echo esc_attr((string) $atts['job_title_placeholder']); ?>"
                    >
                </div>

                <div class="sffc-gap-prompt__body">
                    <textarea
                        class="sffc-gap-prompt__textarea"
                        name="senna_gap_prefill_jd"
                        rows="8"
                        placeholder="<?php echo esc_attr((string) $atts['placeholder']); ?>"
                        required
                        data-gap-prompt-input
                    ></textarea>
                </div>

                <div class="sffc-gap-prompt__toolbar">
                    <div class="sffc-gap-prompt__toolbar-left">
                        <span class="sffc-gap-prompt__chip sffc-gap-prompt__chip--icon" aria-hidden="true">+</span>
                        <span class="sffc-gap-prompt__chip"><?php esc_html_e('Paste job description', 'senna-finance'); ?></span>
                        <span class="sffc-gap-prompt__chip"><?php esc_html_e('CV comes next', 'senna-finance'); ?></span>
                    </div>

                    <div class="sffc-gap-prompt__toolbar-right">
                        <span class="sffc-gap-prompt__hint" data-gap-prompt-hint><?php esc_html_e('Paste the full role brief to continue.', 'senna-finance'); ?></span>
                        <button type="submit" class="sffc-gap-prompt__submit" data-gap-prompt-submit>
                            <span><?php echo esc_html((string) $atts['button_text']); ?></span>
                            <span class="sffc-gap-prompt__submit-icon" aria-hidden="true">↑</span>
                        </button>
                    </div>
                </div>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    private function resolve_target_url($target_url) {
        $target_url = trim((string) $target_url);
        if ($target_url !== '') {
            return $target_url;
        }

        return apply_filters('sffc_gap_analyzer_prompt_target_url', home_url('/'));
    }
}

SFFC_Gap_Analyzer_Prompt_Shortcode::get_instance();
