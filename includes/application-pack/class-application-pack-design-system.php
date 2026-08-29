<?php
/**
 * Application Pack Design System
 *
 * Consulting-grade design constants and helpers for all Application Pack documents.
 * Inspired by McKinsey/Bain report styling.
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Application_Pack_Design_System {

    /**
     * Color Palette - Navy & Gold Professional Theme
     */
    const COLORS = array(
        'primary'       => '#1a365d',  // Deep Navy
        'primary_light' => '#2c5282',  // Lighter Navy
        'secondary'     => '#c9a227',  // Gold Accent
        'secondary_light' => '#d4af37', // Light Gold
        'text_dark'     => '#1a202c',  // Near Black
        'text_medium'   => '#4a5568',  // Medium Gray
        'text_light'    => '#718096',  // Light Gray
        'background'    => '#ffffff',  // White
        'background_alt'=> '#f7fafc',  // Off White
        'border'        => '#e2e8f0',  // Light Border
        'border_dark'   => '#cbd5e0',  // Medium Border
        'success'       => '#276749',  // Green
        'warning'       => '#c05621',  // Orange
    );

    /**
     * Typography Settings
     */
    const TYPOGRAPHY = array(
        'font_primary'   => 'Helvetica Neue, Helvetica, Arial, sans-serif',
        'font_secondary' => 'Georgia, Times New Roman, serif',
        'size_h1'        => 18,
        'size_h2'        => 14,
        'size_h3'        => 12,
        'size_body'      => 10,
        'size_small'     => 9,
        'size_tiny'      => 8,
        'line_height'    => 1.4,
        'letter_spacing' => 0.02,
    );

    /**
     * Spacing & Layout (in points for PDF, converted to px for web)
     */
    const SPACING = array(
        'margin_page'      => 50,   // 50pt = ~0.7in
        'margin_section'   => 20,
        'margin_paragraph' => 8,
        'margin_item'      => 4,
        'padding_box'      => 12,
        'padding_cell'     => 6,
        'column_gap'       => 24,
    );

    /**
     * Document Dimensions (in points, A4)
     */
    const DIMENSIONS = array(
        'page_width'   => 595,  // A4 width in points
        'page_height'  => 842,  // A4 height in points
        'content_width'=> 495,  // 595 - (50*2)
    );

    /**
     * Get CSS styles for web preview
     */
    public static function get_preview_css() {
        $colors = self::COLORS;
        $typo = self::TYPOGRAPHY;
        $space = self::SPACING;

        return "
        :root {
            --app-pack-primary: {$colors['primary']};
            --app-pack-primary-light: {$colors['primary_light']};
            --app-pack-secondary: {$colors['secondary']};
            --app-pack-text-dark: {$colors['text_dark']};
            --app-pack-text-medium: {$colors['text_medium']};
            --app-pack-text-light: {$colors['text_light']};
            --app-pack-background: {$colors['background']};
            --app-pack-background-alt: {$colors['background_alt']};
            --app-pack-border: {$colors['border']};
        }

        .sffc-app-pack-preview {
            font-family: {$typo['font_primary']};
            font-size: {$typo['size_body']}pt;
            line-height: {$typo['line_height']};
            color: {$colors['text_dark']};
            background: {$colors['background']};
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }

        .sffc-app-pack-preview h1 {
            font-size: {$typo['size_h1']}pt;
            color: {$colors['primary']};
            font-weight: 600;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
        }

        .sffc-app-pack-preview h2 {
            font-size: {$typo['size_h2']}pt;
            color: {$colors['primary']};
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid {$colors['secondary']};
            padding-bottom: 4px;
            margin: 20px 0 12px 0;
        }

        .sffc-app-pack-preview h3 {
            font-size: {$typo['size_h3']}pt;
            color: {$colors['text_dark']};
            font-weight: 600;
            margin: 12px 0 6px 0;
        }

        .sffc-app-pack-preview .subtitle {
            font-size: {$typo['size_body']}pt;
            color: {$colors['text_medium']};
            margin: 0 0 16px 0;
        }

        .sffc-app-pack-preview .executive-box {
            background: {$colors['background_alt']};
            border-left: 3px solid {$colors['secondary']};
            padding: 16px 20px;
            margin: 16px 0;
        }

        .sffc-app-pack-preview .executive-box p {
            margin: 0;
            font-size: {$typo['size_body']}pt;
            line-height: 1.5;
        }

        .sffc-app-pack-preview .metrics-row {
            display: flex;
            gap: 24px;
            margin: 16px 0;
        }

        .sffc-app-pack-preview .metric-box {
            flex: 1;
            text-align: center;
            padding: 12px;
            border: 1px solid {$colors['border']};
        }

        .sffc-app-pack-preview .metric-value {
            font-size: 18pt;
            font-weight: 700;
            color: {$colors['primary']};
        }

        .sffc-app-pack-preview .metric-label {
            font-size: {$typo['size_small']}pt;
            color: {$colors['text_light']};
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sffc-app-pack-preview .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .sffc-app-pack-preview ul {
            margin: 8px 0;
            padding-left: 16px;
        }

        .sffc-app-pack-preview li {
            margin: 4px 0;
            position: relative;
        }

        .sffc-app-pack-preview li::marker {
            color: {$colors['secondary']};
        }

        .sffc-app-pack-preview .experience-entry {
            margin: 16px 0;
        }

        .sffc-app-pack-preview .experience-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 4px;
        }

        .sffc-app-pack-preview .copany-name {
            font-weight: 600;
            color: {$colors['text_dark']};
        }

        .sffc-app-pack-preview .date-range {
            font-size: {$typo['size_small']}pt;
            color: {$colors['text_light']};
        }

        .sffc-app-pack-preview .job-title {
            font-style: italic;
            color: {$colors['text_medium']};
            margin-bottom: 6px;
        }

        .sffc-app-pack-preview .skill-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0;
        }

        .sffc-app-pack-preview .skill-tag {
            background: {$colors['background_alt']};
            border: 1px solid {$colors['border']};
            padding: 4px 10px;
            font-size: {$typo['size_small']}pt;
            color: {$colors['text_dark']};
        }

        .sffc-app-pack-preview .footer {
            margin-top: 32px;
            padding-top: 12px;
            border-top: 1px solid {$colors['border']};
            font-size: {$typo['size_tiny']}pt;
            color: {$colors['text_light']};
            text-align: center;
        }
        ";
    }

    /**
     * Get TCPDF-compatible color array from hex
     */
    public static function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    /**
     * Get color as RGB array
     */
    public static function get_color($name) {
        $hex = isset(self::COLORS[$name]) ? self::COLORS[$name] : self::COLORS['text_dark'];
        return self::hex_to_rgb($hex);
    }

    /**
     * Format section header for PDF
     */
    public static function format_section_header($text) {
        return strtoupper($text);
    }

    /**
     * Format bullet point with consulting-style marker
     */
    public static function format_bullet($text, $level = 0) {
        $indent = str_repeat('  ', $level);
        $marker = $level === 0 ? "\u{2022}" : "\u{2013}"; // Bullet or en-dash
        return $indent . $marker . ' ' . trim($text);
    }

    /**
     * Format metric display (e.g., "$50M AUM")
     */
    public static function format_metric($value, $label) {
        return array(
            'value' => $value,
            'label' => $label
        );
    }

    /**
     * Get standard document footer
     */
    public static function get_document_footer($type = 'cv') {
        $types = array(
            'cv' => 'Prepared with MENA Careers Finance',
            'cover_letter' => '',
            'interview_prep' => 'MENA Careers Finance Interview Preparation Brief',
            'company_brief' => 'MENA Careers Finance Company Intelligence',
        );
        return isset($types[$type]) ? $types[$type] : '';
    }

    /**
     * Get PHPWord style definitions
     */
    public static function get_phpword_styles() {
        $colors = self::COLORS;
        $typo = self::TYPOGRAPHY;

        return array(
            'Title' => array(
                'name' => 'Arial',
                'size' => $typo['size_h1'],
                'bold' => true,
                'color' => ltrim($colors['primary'], '#'),
            ),
            'Heading1' => array(
                'name' => 'Arial',
                'size' => $typo['size_h2'],
                'bold' => true,
                'color' => ltrim($colors['primary'], '#'),
                'allCaps' => true,
                'spaceAfter' => 120,
            ),
            'Heading2' => array(
                'name' => 'Arial',
                'size' => $typo['size_h3'],
                'bold' => true,
                'color' => ltrim($colors['text_dark'], '#'),
            ),
            'Normal' => array(
                'name' => 'Arial',
                'size' => $typo['size_body'],
                'color' => ltrim($colors['text_dark'], '#'),
            ),
            'Subtitle' => array(
                'name' => 'Arial',
                'size' => $typo['size_body'],
                'color' => ltrim($colors['text_medium'], '#'),
            ),
            'Small' => array(
                'name' => 'Arial',
                'size' => $typo['size_small'],
                'color' => ltrim($colors['text_light'], '#'),
            ),
            'Bullet' => array(
                'name' => 'Arial',
                'size' => $typo['size_body'],
                'color' => ltrim($colors['text_dark'], '#'),
            ),
            'MetricValue' => array(
                'name' => 'Arial',
                'size' => 16,
                'bold' => true,
                'color' => ltrim($colors['primary'], '#'),
            ),
            'MetricLabel' => array(
                'name' => 'Arial',
                'size' => $typo['size_tiny'],
                'color' => ltrim($colors['text_light'], '#'),
                'allCaps' => true,
            ),
        );
    }

    /**
     * Get TCPDF style definitions
     */
    public static function get_tcpdf_styles() {
        return array(
            'title' => array(
                'font' => 'helvetica',
                'size' => self::TYPOGRAPHY['size_h1'],
                'style' => 'B',
                'color' => self::get_color('primary'),
            ),
            'heading1' => array(
                'font' => 'helvetica',
                'size' => self::TYPOGRAPHY['size_h2'],
                'style' => 'B',
                'color' => self::get_color('primary'),
            ),
            'heading2' => array(
                'font' => 'helvetica',
                'size' => self::TYPOGRAPHY['size_h3'],
                'style' => 'B',
                'color' => self::get_color('text_dark'),
            ),
            'body' => array(
                'font' => 'helvetica',
                'size' => self::TYPOGRAPHY['size_body'],
                'style' => '',
                'color' => self::get_color('text_dark'),
            ),
            'small' => array(
                'font' => 'helvetica',
                'size' => self::TYPOGRAPHY['size_small'],
                'style' => '',
                'color' => self::get_color('text_light'),
            ),
        );
    }
}
