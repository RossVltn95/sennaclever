<?php

define('ABSPATH', dirname(__DIR__) . '/');
define('SFFC_PLUGIN_DIR', dirname(__DIR__) . '/');

require_once dirname(__DIR__) . '/includes/class-intelligent-salary-estimator-v3.php';

$estimator = SFFC_Intelligent_Salary_Estimator_V3::get_instance();

$cases = [
    [
        'label' => 'Dubai private equity analyst',
        'job' => [
            'title' => 'Private Equity Analyst',
            'company' => 'Gulf Capital',
            'location' => 'Dubai, United Arab Emirates',
            'sector' => 'Private Equity',
        ],
        'currency' => 'AED',
        'min' => 20000,
        'max' => 40000,
    ],
    [
        'label' => 'Riyadh investment banking associate',
        'job' => [
            'title' => 'Investment Banking Associate',
            'company' => 'Saudi Bank',
            'location' => 'Riyadh, Saudi Arabia',
            'sector' => 'Investment Banking',
        ],
        'currency' => 'SAR',
        'min' => 30000,
        'max' => 50000,
    ],
    [
        'label' => 'Dubai finance manager',
        'job' => [
            'title' => 'Finance Manager',
            'company' => 'International Group',
            'location' => 'Dubai',
            'sector' => 'Finance and Accounting',
        ],
        'currency' => 'AED',
        'min' => 25000,
        'max' => 45000,
    ],
];

$failures = 0;

foreach ($cases as $case) {
    $result = $estimator->estimate_salary($case['job']);
    $errors = [];

    foreach (['currency', 'min', 'max'] as $key) {
        if (($result[$key] ?? null) !== $case[$key]) {
            $errors[] = $key . '=' . var_export($result[$key] ?? null, true);
        }
    }

    if (($result['salary_period'] ?? '') !== 'monthly') {
        $errors[] = 'salary_period=' . var_export($result['salary_period'] ?? null, true);
    }

    if (empty($result['source']) || stripos($result['source'], '2026') === false) {
        $errors[] = 'missing source';
    }

    if ($errors) {
        $failures++;
        fwrite(STDERR, 'FAIL ' . $case['label'] . ': ' . implode(', ', $errors) . PHP_EOL);
        fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        continue;
    }

    echo 'PASS ' . $case['label'] . ': ' . $result['display'] . ' monthly' . PHP_EOL;
}

exit($failures > 0 ? 1 : 0);
