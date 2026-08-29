<?php
/**
 * Intelligence Brief PDF Master Template
 *
 * This template assembles all partial templates into a complete PDF document.
 * Expected data structure: $brief (array with all sections populated)
 *
 * @package Skill_Farm_Finance_Career
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure we have the brief data
if (!isset($brief) || !is_array($brief)) {
    return;
}

// Helper function to safely get nested array values
if (!function_exists('sffc_brief_get')) {
    function sffc_brief_get($array, $key, $default = '') {
        $keys = explode('.', $key);
        $value = $array;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }
}

// Helper function to format currency
if (!function_exists('sffc_format_currency')) {
    function sffc_format_currency($amount, $currency = 'USD', $decimals = 0) {
        if (empty($amount) || !is_numeric($amount)) {
            return 'N/A';
        }
        $symbols = array('USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥');
        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';

        if ($amount >= 1000000000) {
            return $symbol . number_format($amount / 1000000000, 1) . 'B';
        } elseif ($amount >= 1000000) {
            return $symbol . number_format($amount / 1000000, 1) . 'M';
        } elseif ($amount >= 1000) {
            return $symbol . number_format($amount / 1000, 1) . 'K';
        }
        return $symbol . number_format($amount, $decimals);
    }
}

// Helper function to format percentage
if (!function_exists('sffc_format_percent')) {
    function sffc_format_percent($value, $decimals = 1) {
        if (empty($value) || !is_numeric($value)) {
            return 'N/A';
        }
        return number_format($value, $decimals) . '%';
    }
}

// Get template directory
$template_dir = dirname(__FILE__);
$partials_dir = $template_dir . '/partials';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sffc_brief_get($brief, 'title', 'Intelligence Brief')); ?> - MENA Careers Finance</title>
    <style>
        <?php include $template_dir . '/styles/brief.css'; ?>
    </style>
</head>
<body>
    <?php
    // Page 1: Cover
    include $partials_dir . '/cover.php';

    // Page 2: Executive Summary
    include $partials_dir . '/executive-summary.php';

    // Page 3: The Company
    include $partials_dir . '/company-profile.php';

    // Page 4: The Event Explained
    include $partials_dir . '/event-explained.php';

    // Page 5: Key Players
    include $partials_dir . '/key-players.php';

    // Page 6: Financial Analysis
    include $partials_dir . '/financial-analysis.php';

    // Page 7: Market Context
    include $partials_dir . '/market-context.php';

    // Page 8: Industry Projections
    include $partials_dir . '/industry-projections.php';

    // Page 9: Strategic Analysis
    include $partials_dir . '/strategic-analysis.php';

    // Page 10: Key Risks
    include $partials_dir . '/key-risks.php';

    // Page 11: Unique Insights & Future Outlook
    include $partials_dir . '/insights-future.php';

    // Page 12: Sources & Methodology
    include $partials_dir . '/sources.php';
    ?>
</body>
</html>
