<?php

/**
 * MENA Careers Booking Form Handler
 * Professional sophisticated design matching MENA Careers chat system
 * With conditional content for logged-in vs logged-out users
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
     * Main render method - decides what to show based on login status
     */
    public function render_booking_form($atts = array())
    {
        $atts = shortcode_atts(array(
            'title' => 'Expert Career Consultation',
            'subtitle' => 'Personalized guidance from our professional team'
        ), $atts);

        $plugin_url = defined('SFFC_PLUGIN_URL') ? SFFC_PLUGIN_URL : plugin_dir_url(__FILE__) . '../';
        $senna_avatar = $plugin_url . 'assets/images/senna.jpeg';

        // Check if user is logged in
        $is_logged_in = is_user_logged_in();

        ob_start();

        // Show shared styles first
        echo $this->get_shared_styles();

        if (!$is_logged_in) {
            // Show join encouragement for logged-out users
            echo $this->render_join_encouragement($atts, $senna_avatar);
        } else {
            // Show full booking form for logged-in users
            echo $this->render_booking_steps($atts, $senna_avatar);
        }

        return ob_get_clean();
    }

    /**
     * Get shared CSS styles
     */
    private function get_shared_styles()
    {
        return '
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
            background: radial-gradient(
                circle at 30% 50%,
                rgba(45, 106, 79, 0.03) 0%,
                transparent 50%
            ),
            radial-gradient(
                circle at 70% 80%,
                rgba(45, 106, 79, 0.02) 0%,
                transparent 50%
            ),
            radial-gradient(
                circle at 50% 20%,
                rgba(255, 249, 240, 0.5) 0%,
                transparent 40%
            );
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
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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

        @media (max-width: 768px) {
            .senna-booking-container {
                padding: 20px 15px;
            }
            
            .senna-booking-title {
                font-size: 2.2rem;
            }
        }
        </style>';
    }

    /**
     * Render join encouragement for logged-out users
     */
    private function render_join_encouragement($atts, $senna_avatar)
    {
        ob_start();
?>

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

                <!-- Join Encouragement -->
                <div class="senna-join-encouragement">
                    <div class="join-card">
                        <div class="join-header">
                            <div class="join-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12,2A2,2 0 0,1 14,4C14,4.74 13.6,5.39 13,5.73V7H14A7,7 0 0,1 21,14H22A1,1 0 0,1 23,15V18A1,1 0 0,1 22,19H21A7,7 0 0,1 14,26H10A7,7 0 0,1 3,19H2A1,1 0 0,1 1,18V15A1,1 0 0,1 2,14H3A7,7 0 0,1 10,7H11V5.73C10.4,5.39 10,4.74 10,4A2,2 0 0,1 12,2Z" />
                                </svg>
                            </div>
                            <h3>Unlock Expert Career Consultation</h3>
                            <p class="join-subheader">Exclusive access for finance professionals</p>
                        </div>

                        <div class="join-benefits">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,17C9.24,17 7,14.76 7,12C7,9.24 9.24,7 12,7C14.76,7 17,9.24 17,12C17,14.76 14.76,17 12,17M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9Z" />
                                    </svg>
                                </div>
                                <div class="benefit-content">
                                    <h4>Industry Expert Consultation</h4>
                                    <p>Direct access to finance industry experts with deep knowledge of investment banking, private equity, asset management, and fintech sectors</p>
                                </div>
                            </div>

                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                    </svg>
                                </div>
                                <div class="benefit-content">
                                    <h4>Personalized CV Optimization</h4>
                                    <p>Career Assessment and CV enhancement specifically tailored for finance roles, highlighting quantifiable achievements and technical skills</p>
                                </div>
                            </div>

                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16,6L18.29,8.29L13.41,13.17L9.41,9.17L2,16.59L3.41,18L9.41,12L13.41,16L19.71,9.71L22,12V6H16Z" />
                                    </svg>
                                </div>
                                <div class="benefit-content">
                                    <h4>Strategic Career Planning</h4>
                                    <p>Comprehensive guidance on career progression, salary benchmarking, and positioning for senior finance roles at top-tier firms</p>
                                </div>
                            </div>

                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z" />
                                    </svg>
                                </div>
                                <div class="benefit-content">
                                    <h4>Interview Mastery</h4>
                                    <p>Advanced interview preparation including technical finance questions, case studies, and behavioral interview techniques for elite finance positions</p>
                                </div>
                            </div>
                        </div>

                        <div class="join-action">
                            <a href="https://joinsenna.com/join-senna/" class="join-btn">
                                Join senna to Access Expert Consultation
                            </a>
                            <p class="join-note">Already a member? <a href="https://joinsenna.com/login-auth/">Sign in here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .senna-join-encouragement {
                margin: 40px 0;
            }

            .join-card {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 244, 237, 0.95) 100%);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(45, 106, 79, 0.2);
                border-radius: 24px;
                padding: 40px;
                box-shadow: 0 12px 40px rgba(7, 50, 57, 0.15);
                text-align: center;
            }

            .join-header {
                margin-bottom: 40px;
            }

            .join-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 24px;
                background: linear-gradient(135deg, var(--gold), var(--dark-green));
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }

            .join-icon svg {
                width: 40px;
                height: 40px;
            }

            .join-header h3 {
                font-family: "Playfair Display", serif;
                font-size: 2.4rem;
                font-weight: 500;
                color: var(--dark-green);
                margin: 0 0 12px 0;
                line-height: 1.2;
            }

            .join-subheader {
                font-size: 1.1rem;
                color: var(--sage);
                margin: 0;
                font-weight: 400;
            }

            .join-benefits {
                text-align: left;
                margin: 40px 0;
                display: grid;
                gap: 24px;
            }

            .benefit-item {
                display: flex;
                align-items: flex-start;
                padding: 24px;
                background: rgba(255, 255, 255, 0.7);
                border-radius: 16px;
                border: 1px solid rgba(45, 106, 79, 0.1);
                transition: all 0.3s ease;
            }

            .benefit-item:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(7, 50, 57, 0.1);
            }

            .benefit-icon {
                width: 56px;
                height: 56px;
                margin-right: 20px;
                background: linear-gradient(135deg, var(--gold), var(--dark-green));
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                flex-shrink: 0;
            }

            .benefit-icon svg {
                width: 28px;
                height: 28px;
            }

            .benefit-content h4 {
                font-family: "Playfair Display", serif;
                font-size: 1.3rem;
                font-weight: 500;
                color: var(--dark-green);
                margin: 0 0 10px 0;
                line-height: 1.3;
            }

            .benefit-content p {
                color: var(--sage);
                font-size: 1rem;
                line-height: 1.6;
                margin: 0;
            }

            .join-action {
                margin-top: 40px;
                text-align: center;
            }

            .join-btn {
                display: inline-block;
                background: linear-gradient(135deg, var(--gold) 0%, var(--dark-green) 100%);
                color: var(--pure-white);
                padding: 20px 40px;
                border-radius: 16px;
                font-family: "Playfair Display", serif;
                font-size: 1.2rem;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.3s ease;
                box-shadow: 0 12px 32px rgba(7, 50, 57, 0.2);
                position: relative;
                overflow: hidden;
            }

            .join-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
            }

            .join-btn:hover::before {
                left: 100%;
            }

            .join-btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 16px 40px rgba(7, 50, 57, 0.3);
                text-decoration: none;
                color: white;
            }

            .join-note {
                margin-top: 20px;
                font-size: 1rem;
                color: var(--sage);
            }

            .join-note a {
                color: var(--gold);
                text-decoration: none;
                font-weight: 500;
                transition: color 0.3s ease;
            }

            .join-note a:hover {
                text-decoration: underline;
                color: var(--dark-green);
            }

            @media (max-width: 768px) {
                .join-card {
                    padding: 24px;
                }

                .join-header h3 {
                    font-size: 1.8rem;
                }

                .benefit-item {
                    flex-direction: column;
                    text-align: center;
                }

                .benefit-icon {
                    margin: 0 auto 16px auto;
                }

                .benefit-content {
                    text-align: center;
                }

                .join-btn {
                    padding: 16px 32px;
                    font-size: 1.1rem;
                }
            }
        </style>

    <?php
        return ob_get_clean();
    }

    /**
     * Render full booking form for logged-in users
     */
    private function render_booking_steps($atts, $senna_avatar)
    {
        ob_start();
    ?>

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
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">CV Optimization</h3>
                                <p class="option-description">Professional review and enhancement of your CV to maximize impact</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="interview-prep">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Interview Preparation</h3>
                                <p class="option-description">Comprehensive coaching for your upcoming interviews</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="career-strategy">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M2.81,14.12L5.64,11.29L8.17,10.79C11.39,6.41 17.55,4.22 19.78,4.22C19.78,6.45 17.59,12.61 13.21,15.83L12.71,18.36L9.88,21.19C9.29,21.78 8.28,21.78 7.69,21.19L2.81,16.31C2.22,15.72 2.22,14.71 2.81,14.12M7.68,7.68C8.39,6.97 9.53,6.97 10.24,7.68C10.95,8.39 10.95,9.53 10.24,10.24C9.53,10.95 8.39,10.95 7.68,10.24C6.97,9.53 6.97,8.39 7.68,7.68Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Career Strategy</h3>
                                <p class="option-description">Strategic planning for career transitions and advancement</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="salary-negotiation">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M10,2H14A2,2 0 0,1 16,4V6H20A2,2 0 0,1 22,8V19A2,2 0 0,1 20,21H4A2,2 0 0,1 2,19V8A2,2 0 0,1 4,6H8V4A2,2 0 0,1 10,2M14,6V4H10V6H14Z" />
                                    </svg>
                                </div>
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
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12C2,17.35 4.5,20.9 9,21.85C10.15,21.95 11,21.15 11,20V19.07C10.85,19.09 10.7,19.1 10.55,19.1C9.26,19.1 8.55,18.07 8.55,17.1C8.55,16.13 9.26,15.1 10.55,15.1C10.7,15.1 10.85,15.11 11,15.13V14.2C11,13.05 11.85,12.2 13,12.2H13.93C14.95,10.85 15.5,9.15 15.5,7.5C15.5,4.46 13.04,2 10,2L12,2Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Entry Level</h3>
                                <p class="option-description">0-2 years of professional experience</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="mid-level">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16,6L18.29,8.29L13.41,13.17L9.41,9.17L2,16.59L3.41,18L9.41,12L13.41,16L19.71,9.71L22,12V6H16Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Mid Level</h3>
                                <p class="option-description">3-7 years of professional experience</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="senior-level">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M5,16L3,5L6.5,10L12,4L17.5,10L21,5L19,16H5M19,19A1,1 0 0,1 18,20H6A1,1 0 0,1 5,19V18H19V19Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Senior Level</h3>
                                <p class="option-description">8+ years or leadership positions</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="executive">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A2,2 0 0,1 14,4C14,4.74 13.6,5.39 13,5.73V7H14A7,7 0 0,1 21,14H22A1,1 0 0,1 23,15V18A1,1 0 0,1 22,19H21A7,7 0 0,1 14,26H10A7,7 0 0,1 3,19H2A1,1 0 0,1 1,18V15A1,1 0 0,1 2,14H3A7,7 0 0,1 10,7H11V5.73C10.4,5.39 10,4.74 10,4A2,2 0 0,1 12,2Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Executive</h3>
                                <p class="option-description">C-level or senior executive roles</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Finance Sector -->
                <div class="senna-booking-step" data-step="3">
                    <h2 class="step-question">Which finance sector best describes your focus?</h2>
                    <p class="step-description">Select your primary finance industry for specialized guidance</p>

                    <div class="senna-options-grid">
                        <div class="senna-option-card" data-value="investment-banking">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3,21H21V19H3V21M5,17H19V7L16,3H8L5,7V17M7,9V11H9V9H7M11,9V11H13V9H11M15,9V11H17V9H15M7,13V15H9V13H7M11,13V15H13V13H11M15,13V15H17V13H15Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Investment Banking</h3>
                                <p class="option-description">M&A, capital markets, equity research, sales & trading</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="private-equity">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16,6L18.29,8.29L13.41,13.17L9.41,9.17L2,16.59L3.41,18L9.41,12L13.41,16L19.71,9.71L22,12V6H16Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Private Equity & Finance</h3>
                                <p class="option-description">Private equity, investment banking, corporate finance, and buy-side careers</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="asset-management">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Asset Management</h3>
                                <p class="option-description">Portfolio management, private markets, wealth management</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="corporate-finance">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Corporate Finance</h3>
                                <p class="option-description">FP&A, treasury, corporate development, risk management</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="fintech">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9,4V6H15V4H17V6H19A2,2 0 0,1 21,8V18A2,2 0 0,1 19,20H5A2,2 0 0,1 3,18V8A2,2 0 0,1 5,6H7V4H9M5,8V18H19V10H5V8Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">FinTech & Banking</h3>
                                <p class="option-description">Digital banking, payments, financial technology, crypto</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="consulting">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Financial Consulting</h3>
                                <p class="option-description">Strategy consulting, financial advisory, management consulting</p>
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
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13,14H11V10H13M13,18H11V16H13M1,21H23L12,2L1,21Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">Urgent</h3>
                                <p class="option-description">Need assistance within 24-48 hours</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="this-week">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19,3H18V1H16V3H8V1H6V3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M19,19H5V8H19V19Z" />
                                    </svg>
                                </div>
                                <h3 class="option-title">This Week</h3>
                                <p class="option-description">Would like to connect within this week</p>
                            </div>
                        </div>

                        <div class="senna-option-card" data-value="flexible">
                            <div class="option-content">
                                <div class="option-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M16.2,16.2L11,13V7H12.5V12.2L17,14.9L16.2,16.2Z" />
                                    </svg>
                                </div>
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

        <style>
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
                margin: 0 0 20px 0;
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

            .senna-option-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 40px rgba(7, 50, 57, 0.15);
                border-color: var(--gold);
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
                width: 30px;
                height: 30px;
                fill: currentColor;
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
                margin: 30px 0;
                border: 1px solid rgba(7, 50, 57, 0.1);
            }

            .form-group {
                margin-bottom: 30px;
            }

            .form-label {
                display: block;
                font-family: "Playfair Display", serif;
                font-size: 1.1rem;
                font-weight: 500;
                color: var(--dark-green);
                margin-bottom: 10px;
            }

            .form-input,
            .form-textarea {
                width: 100%;
                padding: 16px 20px;
                border: 2px solid rgba(7, 50, 57, 0.1);
                border-radius: 12px;
                font-family: "Montserrat", sans-serif;
                font-size: 1rem;
                color: var(--dark-green);
                background: var(--pure-white);
                transition: all 0.3s ease;
            }

            .form-input:focus,
            .form-textarea:focus {
                outline: none;
                border-color: var(--gold);
                box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.1);
            }

            .form-textarea {
                min-height: 120px;
                resize: vertical;
            }

            .senna-submit-btn {
                background: linear-gradient(135deg, var(--gold) 0%, var(--dark-green) 100%);
                color: var(--pure-white);
                border: none;
                padding: 18px 36px;
                border-radius: 12px;
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

            @media (max-width: 768px) {
                .senna-options-grid {
                    grid-template-columns: 1fr;
                }

                .senna-booking-step {
                    padding: 30px 20px;
                }

                .senna-form-section {
                    padding: 25px 20px;
                }

                .step-question {
                    font-size: 1.8rem;
                }
            }
        </style>

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
                    bookingData[`step${step}`] = {
                        value: value,
                        title: title
                    };

                    // Auto-advance after selection
                    setTimeout(() => {
                        if (currentStep < 5) {
                            nextStep();
                        }
                    }, 500);
                });

                // Next button
                $('#sennaNextBtn').on('click', function() {
                    if (currentStep < 5) {
                        nextStep();
                    }
                });

                // Previous button
                $('#sennaPrevBtn').on('click', function() {
                    if (currentStep > 1) {
                        previousStep();
                    }
                });

                function nextStep() {
                    // Hide current step
                    $(`.senna-booking-step[data-step="${currentStep}"]`).removeClass('active');

                    currentStep++;

                    // Show next step
                    $(`.senna-booking-step[data-step="${currentStep}"]`).addClass('active');

                    updateProgress();
                    updateNavigation();

                    // Show summary on last step
                    if (currentStep === 5) {
                        updateSummary();
                    }
                }

                function previousStep() {
                    // Hide current step
                    $(`.senna-booking-step[data-step="${currentStep}"]`).removeClass('active');

                    currentStep--;

                    // Show previous step
                    $(`.senna-booking-step[data-step="${currentStep}"]`).addClass('active');

                    updateProgress();
                    updateNavigation();
                }

                function updateSummary() {
                    let summaryHTML = '<div class="summary-items">';

                    for (let i = 1; i <= 4; i++) {
                        if (bookingData[`step${i}`]) {
                            summaryHTML += `
                            <div class="summary-item">
                                <strong>Step ${i}:</strong> ${bookingData[`step${i}`].title}
                            </div>
                        `;
                        }
                    }

                    summaryHTML += '</div>';
                    $('.summary-content').html(summaryHTML);
                    $('.senna-summary').show();
                }

                // Submit booking
                $('#sennaSubmitBooking').on('click', function() {
                    const formData = {
                        action: 'senna_submit_booking',
                        nonce: window.senna_booking?.nonce || '',
                        name: $('#userName').val(),
                        email: $('#userEmail').val(),
                        situation: $('#userSituation').val(),
                        selections: bookingData
                    };

                    $(this).prop('disabled', true).text('Sending...');

                    $.post(window.senna_booking?.ajax_url || '/wp-admin/admin-ajax.php', formData)
                        .done(function(response) {
                            if (response.success) {
                                $('.senna-booking-step[data-step="5"]').html(`
                                <div style="text-align: center; padding: 40px;">
                                    <div style="color: var(--gold); font-size: 4rem; margin-bottom: 20px;">✓</div>
                                    <h2 style="color: var(--dark-green); margin-bottom: 15px;">Request Submitted Successfully!</h2>
                                    <p style="color: var(--sage); font-size: 1.1rem;">Our expert team will review your request and contact you within 24 hours.</p>
                                </div>
                            `);
                            } else {
                                alert('There was an error submitting your request. Please try again.');
                            }
                        })
                        .fail(function() {
                            alert('There was an error submitting your request. Please try again.');
                        })
                        .always(function() {
                            $('#sennaSubmitBooking').prop('disabled', false).text('Send My Request to Expert Team');
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
     * Handle booking submission
     */
    public function handle_booking_submission()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'senna_booking_form')) {
            wp_send_json_error('Security check failed');
        }

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $situation = sanitize_textarea_field($_POST['situation']);
        $selections = $_POST['selections'];

        // Prepare email content
        $subject = 'New Expert Consultation Request from ' . $name;

        $message_content = "
        New expert consultation request received:
        
        Name: {$name}
        Email: {$email}
        
        Selections:
        ";

        foreach ($selections as $step => $data) {
            $message_content .= "- {$data['title']}\n";
        }

        $message_content .= "
        Situation Details:
        {$situation}
        
        Please follow up with this request within 24 hours.
        ";

        // Send to Emily Bradshaw
        $messages = SkillFarm_Messaging_Messages::get_instance();
        $result = $messages->send_message(array(
            'sender_id' => 0, // System
            'recipient_id' => 28134, // Emily Bradshaw
            'subject' => $subject,
            'content' => nl2br($message_content),
            'category' => 'admin',
            'priority' => 'high'
        ));

        if ($result) {
            wp_send_json_success('Request submitted successfully');
        } else {
            wp_send_json_error('Failed to submit request');
        }
    }
}

// Initialize
if (class_exists('Senna_Booking_Form')) {
    Senna_Booking_Form::get_instance();
}
