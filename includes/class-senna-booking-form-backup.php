<?php

/**
 * MENA Careers Booking Form Handler
 * Professional sophisticated design matching MENA Careers chat system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Senna_Booking_Form
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('senna_booking_form', array($this, 'render_booking_form'));
        add_action('wp_ajax_senna_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_senna_submit_booking', array($this, 'handle_booking_submission'));
    }

    /**
     * Render the booking form
     */
    public function render_booking_form($atts = array())
    {
        $atts = shortcode_atts(array(
            'title' => 'Expert Career Consultation',
            'subtitle' => 'Personalized guidance from our professional team'
        ), $atts);

        $plugin_url = defined('SFFC_PLUGIN_URL') ? SFFC_PLUGIN_URL : plugin_dir_url(__FILE__) . '../';
        $senna_avatar = $plugin_url . 'assets/images/senna.jpeg';

        ob_start();
?>

        <style>
            @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@300;400;500;600&family=Montserrat:wght@300;400;500;600&display=swap");

            :root {
                --cream: #f8f4ed;
                --dark-green: rgb(7, 50, 57);
                --gold: #2d6a4f;
                --sage: #8b9b88;
                --muted-brown: #6b5d54;
                --pure-white: #ffffff;
                --light-gold: #faf7f2;
                --shadow-light: rgba(7, 50, 57, 0.03);
                --shadow-medium: rgba(7, 50, 57, 0.08);
                --shadow-heavy: rgba(7, 50, 57, 0.15);
                --glass-bg: rgba(255, 255, 255, 0.25);
                --glass-border: rgba(255, 255, 255, 0.18);
                --glass-shadow: 0 8px 32px 0 rgba(7, 50, 57, 0.1);
            }

            .senna-booking-wrapper {
                position: relative;
                background: linear-gradient(135deg, #fffdf8 0%, #faf7f2 100%);
                font-family: "Montserrat", sans-serif;
                color: var(--dark-green);
                font-weight: 300;
                letter-spacing: 0.02em;
                min-height: 100vh;
                padding: 0;
                margin: 0;
                overflow-x: hidden;
            }

            .senna-booking-wrapper::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: radial-gradient(circle at 30% 50%,
                        rgba(45, 106, 79, 0.03) 0%,
                        transparent 50%),
                    radial-gradient(circle at 70% 80%,
                        rgba(45, 106, 79, 0.02) 0%,
                        transparent 50%),
                    radial-gradient(circle at 50% 20%,
                        rgba(255, 249, 240, 0.5) 0%,
                        transparent 40%);
                pointer-events: none;
                z-index: 1;
            }

            .senna-booking-container {
                position: relative;
                z-index: 2;
                max-width: 800px;
                margin: 0 auto;
                padding: 40px 20px;
            }

            .senna-booking-header {
                text-align: center;
                margin-bottom: 50px;
                position: relative;
            }

            .senna-avatar-container {
                position: relative;
                display: inline-block;
                margin-bottom: 30px;
            }

            .senna-booking-avatar {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid var(--pure-white);
                box-shadow: var(--glass-shadow);
                background: var(--pure-white);
            }

            .senna-status-indicator {
                position: absolute;
                bottom: 8px;
                right: 8px;
                width: 24px;
                height: 24px;
                background: var(--gold);
                border: 3px solid var(--pure-white);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .senna-status-dot {
                width: 8px;
                height: 8px;
                background: var(--pure-white);
                border-radius: 50%;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% {
                    opacity: 1;
                }

                50% {
                    opacity: 0.5;
                }

                100% {
                    opacity: 1;
                }
            }

            .senna-booking-title {
                font-family: "Playfair Display", serif;
                font-size: 3rem;
                font-weight: 400;
                color: var(--dark-green);
                margin: 0 0 15px 0;
                line-height: 1.2;
            }

            .senna-booking-subtitle {
                font-size: 1.3rem;
                color: var(--sage);
                font-weight: 300;
                margin: 0 0 40px 0;
            }

            .senna-booking-step {
                display: none;
                background: var(--glass-bg);
                backdrop-filter: blur(16px);
                border: 1px solid var(--glass-border);
                border-radius: 24px;
                padding: 50px 40px;
                margin: 30px 0;
                box-shadow: var(--glass-shadow);
                text-align: center;
                position: relative;
            }

            .senna-booking-step.active {
                display: block;
                animation: fadeInUp 0.6s ease-out;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .step-question {
                font-family: "Playfair Display", serif;
                font-size: 2.2rem;
                font-weight: 400;
                color: var(--dark-green);
                margin: 0 0 40px 0;
                line-height: 1.3;
            }

            .step-description {
                font-size: 1.1rem;
                color: var(--sage);
                margin: 0 0 40px 0;
                line-height: 1.6;
            }

            .senna-options-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin: 40px 0;
            }

            .senna-option-card {
                background: var(--pure-white);
                border: 2px solid rgba(7, 50, 57, 0.1);
                border-radius: 16px;
                padding: 30px 25px;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }

            .senna-option-card::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, var(--gold), var(--dark-green));
                opacity: 0;
                transition: opacity 0.3s ease;
                z-index: 1;
            }

            .senna-option-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 40px rgba(7, 50, 57, 0.15);
                border-color: var(--gold);
            }

            .senna-option-card:hover::before {
                opacity: 0.03;
            }

            .senna-option-card.selected {
                border-color: var(--gold);
                background: linear-gradient(135deg, rgba(45, 106, 79, 0.05), rgba(7, 50, 57, 0.03));
                transform: scale(1.02);
            }

            .option-content {
                position: relative;
                z-index: 2;
            }

            .option-icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 20px;
                background: var(--light-gold);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.8rem;
                color: var(--gold);
            }

            .option-icon svg {
                width: 28px;
                height: 28px;
                color: var(--gold);
            }

            .option-title {
                font-family: "Playfair Display", serif;
                font-size: 1.4rem;
                font-weight: 500;
                color: var(--dark-green);
                margin: 0 0 10px 0;
            }

            .option-description {
                font-size: 0.95rem;
                color: var(--sage);
                line-height: 1.5;
            }

            .senna-form-section {
                background: var(--pure-white);
                border-radius: 20px;
                padding: 40px;
                margin: 40px 0;
                box-shadow: var(--shadow-medium);
            }

            .form-group {
                margin: 25px 0;
                text-align: left;
            }

            .form-label {
                display: block;
                font-family: "Playfair Display", serif;
                font-size: 1.3rem;
                font-weight: 500;
                color: var(--dark-green);
                margin-bottom: 12px;
            }

            .form-input,
            .form-textarea {
                width: 100%;
                padding: 16px 20px;
                border: 2px solid rgba(7, 50, 57, 0.1);
                border-radius: 12px;
                font-family: "Montserrat", sans-serif;
                font-size: 1rem;
                background: var(--light-gold);
                transition: all 0.3s ease;
                box-sizing: border-box;
            }

            .form-input:focus,
            .form-textarea:focus {
                outline: none;
                border-color: var(--gold);
                background: var(--pure-white);
                box-shadow: 0 0 0 4px rgba(45, 106, 79, 0.1);
            }

            .form-textarea {
                min-height: 120px;
                resize: vertical;
            }

            .senna-summary {
                background: linear-gradient(135deg, var(--light-gold), var(--pure-white));
                border: 2px solid var(--gold);
                border-radius: 20px;
                padding: 40px;
                margin: 40px 0;
                text-align: left;
            }

            .summary-title {
                font-family: "Playfair Display", serif;
                font-size: 1.8rem;
                font-weight: 500;
                color: var(--dark-green);
                margin: 0 0 25px 0;
            }

            .summary-item {
                padding: 12px 0;
                border-bottom: 1px solid rgba(7, 50, 57, 0.1);
                font-size: 1.1rem;
                line-height: 1.6;
            }

            .summary-item:last-child {
                border-bottom: none;
            }

            .summary-label {
                font-weight: 500;
                color: var(--dark-green);
            }

            .summary-value {
                color: var(--sage);
                margin-left: 8px;
            }

            .senna-submit-btn {
                background: linear-gradient(135deg, var(--gold), var(--dark-green));
                color: var(--pure-white);
                border: none;
                padding: 20px 40px;
                border-radius: 16px;
                font-family: "Playfair Display", serif;
                font-size: 1.3rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 100%;
                margin-top: 30px;
                position: relative;
                overflow: hidden;
            }

            .senna-submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 40px rgba(7, 50, 57, 0.3);
            }

            .senna-submit-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }

            .senna-navigation {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 40px 0;
                padding: 0 20px;
            }

            .senna-nav-btn {
                background: var(--pure-white);
                border: 2px solid var(--gold);
                color: var(--gold);
                padding: 12px 24px;
                border-radius: 12px;
                font-family: "Montserrat", sans-serif;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .senna-nav-btn:hover {
                background: var(--gold);
                color: var(--pure-white);
            }

            .senna-progress-bar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--pure-white);
                padding: 20px;
                box-shadow: 0 -4px 20px rgba(7, 50, 57, 0.1);
                z-index: 1000;
            }

            .progress-container {
                max-width: 600px;
                margin: 0 auto;
            }

            .progress-track {
                background: rgba(7, 50, 57, 0.1);
                height: 6px;
                border-radius: 3px;
                overflow: hidden;
                margin: 12px 0;
            }

            .progress-fill {
                background: linear-gradient(90deg, var(--gold), var(--dark-green));
                height: 100%;
                transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                border-radius: 3px;
            }

            .progress-text {
                text-align: center;
                font-size: 0.9rem;
                font-weight: 500;
                color: var(--dark-green);
            }

            .typing-cursor {
                display: inline-block;
                width: 2px;
                height: 1.2em;
                background: var(--gold);
                margin-left: 2px;
                animation: blink 1s infinite;
            }

            @keyframes blink {

                0%,
                50% {
                    opacity: 1;
                }

                51%,
                100% {
                    opacity: 0;
                }
            }

            @media (max-width: 768px) {
                .senna-booking-container {
                    padding: 20px 15px;
                }

                .senna-booking-title {
                    font-size: 2.2rem;
                }

                .senna-options-grid {
                    grid-template-columns: 1fr;
                }

                .senna-booking-step {
                    padding: 30px 20px;
                }

                .senna-form-section {
                    padding: 25px 20px;
                }
            }
        </style>

        <div class="senna-booking-wrapper">
            <div class="senna-booking-container">

                <!-- Header -->
                <div class="senna-booking-header">
                    <div class="senna-avatar-container">
                        <img src="<?php echo esc_url($senna_avatar); ?>" alt="MENA Careers AI" class="senna-booking-avatar" onerror="this.style.display='none'">
                        <div class="senna-status-indicator">
                            <div class="senna-status-dot"></div>
                        </div>
                    </div>
                    <h1 class="senna-booking-title"><?php echo esc_html($atts['title']); ?></h1>
                    <p class="senna-booking-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                </div>

                <!-- Step 1: Help Type -->
                <div class="senna-booking-step active" data-step="1">
                    <h2 class="step-question">What type of career guidance are you looking for?</h2>
                    <p class="step-description">Select the area where you need expert assistance</p>

                    <div class="senna-options-grid">
                        <div class="senna-option-card" data-value="cv-optimization">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                    </svg></div>
                                <h3 class="option-title">CV Optimization</h3>
                                <p class="option-description">Professional review and enhancement of your CV to maximize impact</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="interview-prep">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10Z" />
                                    </svg></div>
                                <h3 class="option-title">Interview Preparation</h3>
                                <p class="option-description">Comprehensive coaching for your upcoming interviews</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="career-strategy">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M2.81,14.12L5.64,11.29L8.17,10.79C11.39,6.41 17.55,4.22 19.78,4.22C19.78,6.45 17.59,12.61 13.21,15.83L12.71,18.36L9.88,21.19C9.29,21.78 8.28,21.78 7.69,21.19L2.81,16.31C2.22,15.72 2.22,14.71 2.81,14.12M7.68,7.68C8.39,6.97 9.53,6.97 10.24,7.68C10.95,8.39 10.95,9.53 10.24,10.24C9.53,10.95 8.39,10.95 7.68,10.24C6.97,9.53 6.97,8.39 7.68,7.68Z" />
                                    </svg></div>
                                <h3 class="option-title">Career Strategy</h3>
                                <p class="option-description">Strategic planning for career transitions and advancement</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="salary-negotiation">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M10,2H14A2,2 0 0,1 16,4V6H20A2,2 0 0,1 22,8V19A2,2 0 0,1 20,21H4A2,2 0 0,1 2,19V8A2,2 0 0,1 4,6H8V4A2,2 0 0,1 10,2M14,6V4H10V6H14Z" />
                                    </svg></div>
                                <h3 class="option-title">Salary Negotiation</h3>
                                <p class="option-description">Expert guidance on compensation discussions and offers</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Experience Level -->
                <div class="senna-booking-step" data-step="2">
                    <h2 class="step-question">What's your current experience level?</h2>
                    <p class="step-description">This helps us match you with the most suitable expert</p>

                    <div class="senna-options-grid">
                        <div class="senna-option-card" data-value="entry-level">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12C2,17.35 4.5,20.9 9,21.85C10.15,21.95 11,21.15 11,20V19.07C10.85,19.09 10.7,19.1 10.55,19.1C9.26,19.1 8.55,18.07 8.55,17.1C8.55,16.13 9.26,15.1 10.55,15.1C10.7,15.1 10.85,15.11 11,15.13V14.2C11,13.05 11.85,12.2 13,12.2H13.93C14.95,10.85 15.5,9.15 15.5,7.5C15.5,4.46 13.04,2 10,2L12,2Z" />
                                    </svg></div>
                                <h3 class="option-title">Entry Level</h3>
                                <p class="option-description">0-2 years of professional experience</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="mid-level">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16,6L18.29,8.29L13.41,13.17L9.41,9.17L2,16.59L3.41,18L9.41,12L13.41,16L19.71,9.71L22,12V6H16Z" />
                                    </svg></div>
                                <h3 class="option-title">Mid Level</h3>
                                <p class="option-description">3-7 years of professional experience</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="senior-level">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M5,16L3,5L6.5,10L12,4L17.5,10L21,5L19,16H5M19,19A1,1 0 0,1 18,20H6A1,1 0 0,1 5,19V18H19V19Z" />
                                    </svg></div>
                                <h3 class="option-title">Senior Level</h3>
                                <p class="option-description">8+ years or leadership positions</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="executive">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A2,2 0 0,1 14,4C14,4.74 13.6,5.39 13,5.73V7H14A7,7 0 0,1 21,14H22A1,1 0 0,1 23,15V18A1,1 0 0,1 22,19H21A7,7 0 0,1 14,26H10A7,7 0 0,1 3,19H2A1,1 0 0,1 1,18V15A1,1 0 0,1 2,14H3A7,7 0 0,1 10,7H11V5.73C10.4,5.39 10,4.74 10,4A2,2 0 0,1 12,2M12,4A0,0 0 0,0 12,4A0,0 0 0,0 12,4M7,9V10A5,5 0 0,0 12,15A5,5 0 0,0 17,10V9A5,5 0 0,0 12,14A5,5 0 0,0 7,9Z" />
                                    </svg></div>
                                <h3 class="option-title">Executive</h3>
                                <p class="option-description">C-level or senior executive roles</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Industry -->
                <div class="senna-booking-step" data-step="3">
                    <h2 class="step-question">Which industry best describes your focus?</h2>
                    <p class="step-description">Select your primary industry for specialized guidance</p>

                    <div class="senna-options-grid">
                        <div class="senna-option-card" data-value="finance">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7,15H9C9,16.08 10.37,17 12,17C13.63,17 15,16.08 15,15C15,13.9 13.96,13.5 11.76,12.97C9.64,12.44 7,11.78 7,9C7,7.21 8.47,5.69 10.5,5.18V3H13.5V5.18C15.53,5.69 17,7.21 17,9H15C15,7.92 13.63,7 12,7C10.37,7 9,7.92 9,9C9,10.1 10.04,10.5 12.24,11.03C14.36,11.56 17,12.22 17,15C17,16.79 15.53,18.31 13.5,18.82V21H10.5V18.82C8.47,18.31 7,16.79 7,15Z" />
                                    </svg></div>
                                <h3 class="option-title">Finance & Banking</h3>
                                <p class="option-description">Financial services, investment banking, fintech</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="technology">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M4,6H20V16H4M20,18A2,2 0 0,0 22,16V6C22,4.89 21.1,4 20,4H4C2.89,4 2,4.89 2,6V16A2,2 0 0,0 4,18H0V20H24V18H20Z" />
                                    </svg></div>
                                <h3 class="option-title">Technology</h3>
                                <p class="option-description">Software, hardware, IT services, startups</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="consulting">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10Z" />
                                    </svg></div>
                                <h3 class="option-title">Consulting</h3>
                                <p class="option-description">Management consulting, strategy, advisory</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="other">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.9,17.39C17.64,16.59 16.89,16 16,16H15V13A1,1 0 0,0 14,12H8V10H10A1,1 0 0,0 11,9V7H13A2,2 0 0,0 15,5V4.59C17.93,5.77 20,8.64 20,12C20,14.08 19.2,15.97 17.9,17.39M11,19.93C7.05,19.44 4,16.08 4,12C4,11.38 4.08,10.78 4.21,10.21L9,15V16A2,2 0 0,0 11,18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
                                    </svg></div>
                                <h3 class="option-title">Other Industries</h3>
                                <p class="option-description">Healthcare, law, marketing, or other sectors</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Urgency -->
                <div class="senna-booking-step" data-step="4">
                    <h2 class="step-question">How urgent is your request?</h2>
                    <p class="step-description">This helps us prioritize and schedule your consultation</p>

                    <div class="senna-options-grid">
                        <div class="senna-option-card" data-value="urgent">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13,14H11V10H13M13,18H11V16H13M1,21H23L12,2L1,21Z" />
                                    </svg></div>
                                <h3 class="option-title">Urgent</h3>
                                <p class="option-description">Need assistance within 24-48 hours</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="this-week">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19,3H18V1H16V3H8V1H6V3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M19,19H5V8H19V19Z" />
                                    </svg></div>
                                <h3 class="option-title">This Week</h3>
                                <p class="option-description">Would like to connect within this week</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="flexible">
                            <div class="option-content">
                                <div class="option-icon"><svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M16.2,16.2L11,13V7H12.5V12.2L17,14.9L16.2,16.2Z" />
                                    </svg></div>
                                <h3 class="option-title">Flexible</h3>
                                <p class="option-description">No specific timeline, when convenient</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Details -->
                <div class="senna-booking-step" data-step="5">
                    <h2 class="step-question">Tell us about your specific situation</h2>
                    <p class="step-description">Provide details to help our expert prepare for your consultation</p>

                    <div class="senna-form-section">
                        <div class="form-group">
                            <label class="form-label" for="userName">Your Name</label>
                            <input type="text" id="userName" class="form-input" placeholder="Enter your full name" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userEmail">Email Address</label>
                            <input type="email" id="userEmail" class="form-input" placeholder="Enter your email address" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="userSituation">Describe Your Situation</label>
                            <textarea id="userSituation" class="form-textarea" placeholder="Please provide details about your current situation, goals, and what specific help you're looking for..." required></textarea>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="senna-summary" style="display: none;">
                        <h3 class="summary-title">Your Request Summary</h3>
                        <div class="summary-content"></div>

                        <button type="button" id="sennaSubmitBooking" class="senna-submit-btn">
                            Send My Request to Expert Team
                        </button>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="senna-navigation">
                    <button type="button" id="sennaPrevBtn" class="senna-nav-btn" style="display: none;">
                        Previous
                    </button>
                    <div style="flex: 1;"></div>
                    <button type="button" id="sennaNextBtn" class="senna-nav-btn">
                        Next
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="senna-progress-bar">
                <div class="progress-container">
                    <div class="progress-track">
                        <div class="progress-fill" style="width: 20%;"></div>
                    </div>
                    <div class="progress-text">Step 1 of 5</div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                let currentStep = 1;
                let bookingData = {};

                // Update progress
                function updateProgress() {
                    const progress = (currentStep / 5) * 100;
                    $('.progress-fill').css('width', progress + '%');
                    $('.progress-text').text(`Step ${currentStep} of 5`);
                }

                // Update navigation
                function updateNavigation() {
                    $('#sennaPrevBtn').toggle(currentStep > 1);
                    $('#sennaNextBtn').toggle(currentStep < 5);
                }

                // Option selection
                $(document).on('click', '.senna-option-card', function() {
                    const step = $(this).closest('.senna-booking-step').data('step');
                    const value = $(this).data('value');
                    const title = $(this).find('.option-title').text();

                    // Remove previous selections
                    $(this).siblings().removeClass('selected');
                    $(this).addClass('selected');

                    // Store selection
                    bookingData['step_' + step] = {
                        value: value,
                        title: title
                    };

                    // Auto-advance after brief delay
                    setTimeout(() => {
                        if (currentStep < 5) {
                            nextStep();
                        }
                    }, 600);
                });

                // Next step
                function nextStep() {
                    if (currentStep < 5) {
                        $('.senna-booking-step.active').removeClass('active');
                        currentStep++;
                        $(`.senna-booking-step[data-step="${currentStep}"]`).addClass('active');
                        updateProgress();
                        updateNavigation();

                        // Show summary on step 5
                        if (currentStep === 5) {
                            setTimeout(showSummary, 1000);
                        }

                        // Scroll to top
                        $('html, body').animate({
                            scrollTop: 0
                        }, 300);
                    }
                }

                // Previous step
                function prevStep() {
                    if (currentStep > 1) {
                        $('.senna-booking-step.active').removeClass('active');
                        currentStep--;
                        $(`.senna-booking-step[data-step="${currentStep}"]`).addClass('active');
                        updateProgress();
                        updateNavigation();

                        // Scroll to top
                        $('html, body').animate({
                            scrollTop: 0
                        }, 300);
                    }
                }

                // Show summary
                function showSummary() {
                    let summaryHtml = '';

                    if (bookingData.step_1) {
                        summaryHtml += `<div class="summary-item"><span class="summary-label">Help Type:</span><span class="summary-value">${bookingData.step_1.title}</span></div>`;
                    }
                    if (bookingData.step_2) {
                        summaryHtml += `<div class="summary-item"><span class="summary-label">Experience Level:</span><span class="summary-value">${bookingData.step_2.title}</span></div>`;
                    }
                    if (bookingData.step_3) {
                        summaryHtml += `<div class="summary-item"><span class="summary-label">Industry Focus:</span><span class="summary-value">${bookingData.step_3.title}</span></div>`;
                    }
                    if (bookingData.step_4) {
                        summaryHtml += `<div class="summary-item"><span class="summary-label">Urgency:</span><span class="summary-value">${bookingData.step_4.title}</span></div>`;
                    }

                    $('.summary-content').html(summaryHtml);
                    $('.senna-summary').fadeIn(600);
                }

                // Navigation handlers
                $('#sennaNextBtn').click(nextStep);
                $('#sennaPrevBtn').click(prevStep);

                // Form submission
                $('#sennaSubmitBooking').click(function() {
                    const name = $('#userName').val().trim();
                    const email = $('#userEmail').val().trim();
                    const situation = $('#userSituation').val().trim();

                    if (!name || !email || !situation) {
                        alert('Please fill in all fields before submitting.');
                        return;
                    }

                    const $btn = $(this);
                    $btn.prop('disabled', true).text('Sending...');

                    const submissionData = {
                        ...bookingData,
                        name: name,
                        email: email,
                        situation: situation,
                        action: 'senna_submit_booking',
                        nonce: '<?php echo wp_create_nonce("senna_booking_nonce"); ?>'
                    };

                    $.ajax({
                        url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
                        type: 'POST',
                        data: submissionData,
                        success: function(response) {
                            if (response.success) {
                                if (response.data && response.data.message) {
                                    alert(response.data.message);
                                } else {
                                    alert('Perfect! Your consultation request has been sent to our expert team. You\'ll receive a confirmation message in your dashboard shortly.');
                                }
                                window.location.reload();
                            } else {
                                alert('There was an error submitting your request. Please try again.');
                                $btn.prop('disabled', false).text('Send My Request to Expert Team');
                            }
                        },
                        error: function() {
                            alert('There was an error submitting your request. Please try again.');
                            $btn.prop('disabled', false).text('Send My Request to Expert Team');
                        }
                    });
                });

                // Initialize
                updateProgress();
                updateNavigation();
            });
        </script>

<?php
        return ob_get_clean();
    }

    /**
     * Handle booking form submission
     */
    public function handle_booking_submission()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'senna_booking_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Sanitize data
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $situation = sanitize_textarea_field($_POST['situation']);

        // Get current user info
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // Build detailed content for Emily
        $detailed_content = "<h3>New Expert Consultation Request</h3>\n\n";
        $detailed_content .= "<p><strong>Client Details:</strong></p>\n";
        $detailed_content .= "<p>Name: {$name}<br>\n";
        $detailed_content .= "Email: {$email}</p>\n\n";

        if (isset($_POST['step_1'])) {
            $detailed_content .= "<p><strong>Help Type:</strong> " . sanitize_text_field($_POST['step_1']['title']) . "</p>\n";
        }
        if (isset($_POST['step_2'])) {
            $detailed_content .= "<p><strong>Experience Level:</strong> " . sanitize_text_field($_POST['step_2']['title']) . "</p>\n";
        }
        if (isset($_POST['step_3'])) {
            $detailed_content .= "<p><strong>Industry Focus:</strong> " . sanitize_text_field($_POST['step_3']['title']) . "</p>\n";
        }
        if (isset($_POST['step_4'])) {
            $detailed_content .= "<p><strong>Urgency:</strong> " . sanitize_text_field($_POST['step_4']['title']) . "</p>\n";
        }

        $detailed_content .= "<p><strong>Detailed Situation:</strong></p>\n";
        $detailed_content .= "<p>" . nl2br($situation) . "</p>\n\n";
        $detailed_content .= "<p><em>Submitted: " . current_time('Y-m-d H:i:s') . "</em></p>";

        // Check if messaging system is available
        if (class_exists('SkillFarm_Messaging_Messages')) {
            $messaging = SkillFarm_Messaging_Messages::get_instance();

            // Send message to Emily Bradshaw (user_id = 28134)
            $emily_message = array(
                'sender_id' => $user_id,
                'recipient_id' => 28134,
                'subject' => "Expert Consultation Request from {$name}",
                'content' => $detailed_content,
                'category' => 'consultation',
                'priority' => 'high'
            );

            $emily_message_id = $messaging->send_message($emily_message);

            // Send confirmation message to user
            if ($user_id > 0) {
                $user_content = "<h3>Expert Consultation Request Confirmed</h3>\n\n";
                $user_content .= "<p>Dear {$name},</p>\n\n";
                $user_content .= "<p>Thank you for submitting your expert consultation request. We have received your request and our professional team will review it shortly.</p>\n\n";
                $user_content .= "<p><strong>Your Request Summary:</strong></p>\n";

                if (isset($_POST['step_1'])) {
                    $user_content .= "<p>• <strong>Help Type:</strong> " . sanitize_text_field($_POST['step_1']['title']) . "</p>\n";
                }
                if (isset($_POST['step_2'])) {
                    $user_content .= "<p>• <strong>Experience Level:</strong> " . sanitize_text_field($_POST['step_2']['title']) . "</p>\n";
                }
                if (isset($_POST['step_3'])) {
                    $user_content .= "<p>• <strong>Industry Focus:</strong> " . sanitize_text_field($_POST['step_3']['title']) . "</p>\n";
                }
                if (isset($_POST['step_4'])) {
                    $user_content .= "<p>• <strong>Urgency:</strong> " . sanitize_text_field($_POST['step_4']['title']) . "</p>\n";
                }

                $user_content .= "\n<p><strong>Next Steps:</strong></p>\n";
                $user_content .= "<p>Our expert team will review your request and reach out to you within 24-48 hours to schedule your consultation. You'll receive a follow-up message here in your dashboard with available time slots.</p>\n\n";
                $user_content .= "<p>If you have any urgent questions, please don't hesitate to reach out.</p>\n\n";
                $user_content .= "<p>Best regards,<br>The senna Expert Team</p>";

                $user_message = array(
                    'sender_id' => 28134, // From Emily
                    'recipient_id' => $user_id,
                    'subject' => "Consultation Request Received - We'll Be In Touch Soon",
                    'content' => $user_content,
                    'category' => 'confirmation',
                    'priority' => 'normal'
                );

                $user_message_id = $messaging->send_message($user_message);
            }

            if ($emily_message_id && !is_wp_error($emily_message_id)) {
                wp_send_json_success(array(
                    'message' => 'Your consultation request has been sent successfully. You\'ll receive a confirmation message in your dashboard.',
                    'emily_message_id' => $emily_message_id,
                    'user_message_id' => isset($user_message_id) ? $user_message_id : null
                ));
            } else {
                wp_send_json_error('Failed to send message to expert team');
            }
        } else {
            // Fallback to email if messaging system not available
            $email_content = strip_tags($detailed_content);
            $subject = "New Expert Consultation Request from {$name}";
            $headers = array('Content-Type: text/plain; charset=UTF-8');

            $sent = wp_mail(
                'support.team@senna.com',
                $subject,
                $email_content,
                $headers
            );

            if ($sent) {
                wp_send_json_success('Request submitted successfully');
            } else {
                wp_send_json_error('Failed to send email');
            }
        }
    }
}

// Initialize
if (class_exists('Senna_Booking_Form')) {
    Senna_Booking_Form::get_instance();
}
