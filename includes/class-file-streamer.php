<?php
/**
 * File Streamer
 *
 * Handles on-demand generation and streaming of Excel and PDF files.
 * Files are generated in memory and streamed directly to the browser
 * without being saved to disk.
 *
 * @package SFFC
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load autoloader first to ensure classes are available
$autoload_path = SFFC_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

// Check if required libraries are available
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet') || !class_exists('Dompdf\Dompdf')) {
    // Define a stub class if libraries aren't available
    if (!class_exists('SFFC_File_Streamer')) {
        class SFFC_File_Streamer {
            private static $instance = null;
            public static function get_instance() {
                if (self::$instance === null) {
                    self::$instance = new self();
                }
                return self::$instance;
            }
            public function stream_excel($post_id, $model_type, $analysis = array()) {
                return new WP_Error('missing_dependencies', 'PhpSpreadsheet or Dompdf library not installed. Please run: composer require phpoffice/phpspreadsheet dompdf/dompdf');
            }
            public function stream_pdf($post_id, $model_type, $analysis = array()) {
                return new WP_Error('missing_dependencies', 'PhpSpreadsheet or Dompdf library not installed. Please run: composer require phpoffice/phpspreadsheet dompdf/dompdf');
            }
        }
    }
    return;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Dompdf\Dompdf;
use Dompdf\Options;

class SFFC_File_Streamer {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Template cache
     */
    private static $template_cache = array();

    /**
     * Brand colors
     */
    private $colors = array(
        'navy' => '0A1628',
        'navy_light' => '1A3A5C',
        'green' => '1E6B4A',
        'cream' => 'FAF8F5',
        'gray' => '71717A',
        'gray_light' => 'F4F4F5',
        'white' => 'FFFFFF',
        'red' => '9A3C3C',
        'amber' => '8A6D35',
    );

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Autoloader is loaded at the top of the file
    }

    /**
     * Stream Excel file to browser
     *
     * @param int    $post_id    Post ID
     * @param string $model_type Model type
     * @param array  $analysis   Generated analysis data
     * @return void
     */
    public function stream_excel($post_id, $model_type, $analysis = null) {
        // Get analysis if not provided
        if ($analysis === null) {
            $analysis = get_post_meta($post_id, '_sffc_generated_analysis', true);
        }

        // Get model config
        $config = $this->get_model_config($model_type);
        if (!$config) {
            wp_die('Model configuration not found.');
        }

        // Check for master template
        $template_path = SFFC_PLUGIN_DIR . 'templates/models/' . $model_type . '/master.xlsx';

        if (file_exists($template_path)) {
            // Load existing template
            $spreadsheet = $this->load_template($template_path);
        } else {
            // Generate template programmatically
            $spreadsheet = $this->generate_excel_template($model_type, $config);
        }

        // Inject data into spreadsheet
        $this->inject_excel_data($spreadsheet, $analysis, $config);

        // Generate filename
        $post = get_post($post_id);
        $filename = $this->generate_filename($config['model_name'], $post->post_title, 'xlsx');

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Stream to output
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        // Track download
        $this->track_download($post_id, 'excel');

        // Clean up
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit;
    }

    /**
     * Track download for analytics
     *
     * @param int    $post_id Post ID
     * @param string $format  File format (excel/pdf)
     */
    private function track_download($post_id, $format) {
        if (class_exists('SFFC_Intelligence_Schema')) {
            $schema = SFFC_Intelligence_Schema::get_instance();
            $schema->track_download($post_id, $format);
        }
    }

    /**
     * Stream PDF file to browser
     *
     * @param int    $post_id    Post ID
     * @param string $model_type Model type
     * @param array  $analysis   Generated analysis data
     * @return void
     */
    public function stream_pdf($post_id, $model_type, $analysis = null) {
        // Get analysis if not provided
        if ($analysis === null) {
            $analysis = get_post_meta($post_id, '_sffc_generated_analysis', true);
        }

        // Get model config
        $config = $this->get_model_config($model_type);
        if (!$config) {
            wp_die('Model configuration not found.');
        }

        // Get post data
        $post = get_post($post_id);

        // Load or generate HTML template
        $template_path = SFFC_PLUGIN_DIR . 'templates/models/' . $model_type . '/guide.html';

        if (file_exists($template_path)) {
            $html = file_get_contents($template_path);
        } else {
            $html = $this->generate_pdf_template($model_type, $config);
        }

        // Render template with data
        $html = $this->render_pdf_template($html, $analysis, $config, $post);

        // Generate filename
        $filename = $this->generate_filename($config['model_name'] . ' Guide', $post->post_title, 'pdf');

        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Inter');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Track download
        $this->track_download($post_id, 'pdf');

        // Stream to browser
        $dompdf->stream($filename, array('Attachment' => true));

        exit;
    }

    /**
     * Load cached template or from disk
     */
    private function load_template($path) {
        $cache_key = md5($path);

        // Check cache
        if (isset(self::$template_cache[$cache_key])) {
            return clone self::$template_cache[$cache_key];
        }

        // Load from disk
        $spreadsheet = IOFactory::load($path);

        // Cache for reuse (clone when returning)
        self::$template_cache[$cache_key] = $spreadsheet;

        return clone $spreadsheet;
    }

    /**
     * Get model configuration
     */
    private function get_model_config($model_type) {
        $config_path = SFFC_PLUGIN_DIR . 'templates/models/' . $model_type . '/config.json';

        if (!file_exists($config_path)) {
            // Fallback to DCF if model config not found
            $fallback_path = SFFC_PLUGIN_DIR . 'templates/models/dcf/config.json';
            if (file_exists($fallback_path)) {
                error_log('[SFFC] Model config not found for: ' . $model_type . ', falling back to DCF');
                return json_decode(file_get_contents($fallback_path), true);
            }
            return null;
        }

        return json_decode(file_get_contents($config_path), true);
    }

    /**
     * Generate Excel template programmatically
     */
    private function generate_excel_template($model_type, $config) {
        $spreadsheet = new Spreadsheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator('MENA Careers Finance')
            ->setTitle($config['model_name'])
            ->setSubject($config['description'] ?? '')
            ->setDescription('Generated by MENA Careers Finance Intelligence Platform');

        // Generate sheets based on model type
        switch ($model_type) {
            case 'lbo':
                $this->build_lbo_template($spreadsheet, $config);
                break;
            case 'merger':
                $this->build_merger_template($spreadsheet, $config);
                break;
            case 'dcf':
                $this->build_dcf_template($spreadsheet, $config);
                break;
            case 'three-statement':
                $this->build_three_statement_template($spreadsheet, $config);
                break;
            case 'commercial-re':
                $this->build_commercial_re_template($spreadsheet, $config);
                break;
            default:
                $this->build_generic_template($spreadsheet, $config);
        }

        return $spreadsheet;
    }

    /**
     * Build LBO model template
     */
    private function build_lbo_template($spreadsheet, $config) {
        // Cover Sheet
        $cover = $spreadsheet->getActiveSheet();
        $cover->setTitle('Cover');
        $this->style_cover_sheet($cover, $config['model_name']);

        // Inputs Sheet
        $inputs = $spreadsheet->createSheet();
        $inputs->setTitle('Inputs');
        $this->build_lbo_inputs_sheet($inputs, $config);

        // Sources & Uses Sheet
        $sources = $spreadsheet->createSheet();
        $sources->setTitle('Sources & Uses');
        $this->build_sources_uses_sheet($sources);

        // Debt Schedule Sheet
        $debt = $spreadsheet->createSheet();
        $debt->setTitle('Debt Schedule');
        $this->build_debt_schedule_sheet($debt);

        // Projections Sheet
        $projections = $spreadsheet->createSheet();
        $projections->setTitle('Projections');
        $this->build_projections_sheet($projections);

        // Returns Sheet
        $returns = $spreadsheet->createSheet();
        $returns->setTitle('Returns');
        $this->build_lbo_returns_sheet($returns);

        // Set active sheet to Cover
        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Build M&A/Merger model template
     */
    private function build_merger_template($spreadsheet, $config) {
        // Cover Sheet
        $cover = $spreadsheet->getActiveSheet();
        $cover->setTitle('Cover');
        $this->style_cover_sheet($cover, $config['model_name']);

        // Transaction Sheet
        $transaction = $spreadsheet->createSheet();
        $transaction->setTitle('Transaction');
        $this->build_merger_transaction_sheet($transaction, $config);

        // Acquirer Sheet
        $acquirer = $spreadsheet->createSheet();
        $acquirer->setTitle('Acquirer');
        $this->build_company_sheet($acquirer, 'Acquirer');

        // Target Sheet
        $target = $spreadsheet->createSheet();
        $target->setTitle('Target');
        $this->build_company_sheet($target, 'Target');

        // Synergies Sheet
        $synergies = $spreadsheet->createSheet();
        $synergies->setTitle('Synergies');
        $this->build_synergies_sheet($synergies);

        // Pro Forma Sheet
        $proforma = $spreadsheet->createSheet();
        $proforma->setTitle('Pro Forma');
        $this->build_proforma_sheet($proforma);

        // Accretion/Dilution Sheet
        $accretion = $spreadsheet->createSheet();
        $accretion->setTitle('Accretion-Dilution');
        $this->build_accretion_sheet($accretion);

        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Build DCF model template
     */
    private function build_dcf_template($spreadsheet, $config) {
        // Cover Sheet
        $cover = $spreadsheet->getActiveSheet();
        $cover->setTitle('Cover');
        $this->style_cover_sheet($cover, $config['model_name']);

        // Inputs Sheet
        $inputs = $spreadsheet->createSheet();
        $inputs->setTitle('Inputs');
        $this->build_dcf_inputs_sheet($inputs, $config);

        // Financials Sheet
        $financials = $spreadsheet->createSheet();
        $financials->setTitle('Financials');
        $this->build_financials_sheet($financials);

        // FCF Sheet
        $fcf = $spreadsheet->createSheet();
        $fcf->setTitle('FCF');
        $this->build_fcf_sheet($fcf);

        // WACC Sheet
        $wacc = $spreadsheet->createSheet();
        $wacc->setTitle('WACC');
        $this->build_wacc_sheet($wacc);

        // Valuation Sheet
        $valuation = $spreadsheet->createSheet();
        $valuation->setTitle('Valuation');
        $this->build_dcf_valuation_sheet($valuation);

        // Sensitivity Sheet
        $sensitivity = $spreadsheet->createSheet();
        $sensitivity->setTitle('Sensitivity');
        $this->build_sensitivity_sheet($sensitivity);

        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Build Three-Statement model template
     */
    private function build_three_statement_template($spreadsheet, $config) {
        // Cover Sheet
        $cover = $spreadsheet->getActiveSheet();
        $cover->setTitle('Cover');
        $this->style_cover_sheet($cover, $config['model_name']);

        // Assumptions Sheet
        $assumptions = $spreadsheet->createSheet();
        $assumptions->setTitle('Assumptions');
        $this->build_assumptions_sheet($assumptions, $config);

        // Income Statement Sheet
        $income = $spreadsheet->createSheet();
        $income->setTitle('Income Statement');
        $this->build_income_statement_sheet($income);

        // Balance Sheet
        $balance = $spreadsheet->createSheet();
        $balance->setTitle('Balance Sheet');
        $this->build_balance_sheet_sheet($balance);

        // Cash Flow Sheet
        $cashflow = $spreadsheet->createSheet();
        $cashflow->setTitle('Cash Flow');
        $this->build_cashflow_sheet($cashflow);

        // Ratios Sheet
        $ratios = $spreadsheet->createSheet();
        $ratios->setTitle('Ratios');
        $this->build_ratios_sheet($ratios);

        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Build Commercial RE model template
     */
    private function build_commercial_re_template($spreadsheet, $config) {
        // Cover Sheet
        $cover = $spreadsheet->getActiveSheet();
        $cover->setTitle('Cover');
        $this->style_cover_sheet($cover, $config['model_name']);

        // Property Sheet
        $property = $spreadsheet->createSheet();
        $property->setTitle('Property');
        $this->build_property_sheet($property, $config);

        // Rent Roll Sheet
        $rentroll = $spreadsheet->createSheet();
        $rentroll->setTitle('Rent Roll');
        $this->build_rentroll_sheet($rentroll);

        // Income Sheet
        $income = $spreadsheet->createSheet();
        $income->setTitle('Income');
        $this->build_re_income_sheet($income);

        // Expenses Sheet
        $expenses = $spreadsheet->createSheet();
        $expenses->setTitle('Expenses');
        $this->build_re_expenses_sheet($expenses);

        // NOI Sheet
        $noi = $spreadsheet->createSheet();
        $noi->setTitle('NOI');
        $this->build_noi_sheet($noi);

        // Valuation Sheet
        $valuation = $spreadsheet->createSheet();
        $valuation->setTitle('Valuation');
        $this->build_re_valuation_sheet($valuation);

        // Returns Sheet
        $returns = $spreadsheet->createSheet();
        $returns->setTitle('Returns');
        $this->build_re_returns_sheet($returns);

        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Build generic template
     */
    private function build_generic_template($spreadsheet, $config) {
        $cover = $spreadsheet->getActiveSheet();
        $cover->setTitle('Cover');
        $this->style_cover_sheet($cover, $config['model_name'] ?? 'Financial Model');

        $inputs = $spreadsheet->createSheet();
        $inputs->setTitle('Inputs');
        $this->build_generic_inputs_sheet($inputs, $config);

        $outputs = $spreadsheet->createSheet();
        $outputs->setTitle('Outputs');
        $this->build_generic_outputs_sheet($outputs, $config);

        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Style cover sheet
     */
    private function style_cover_sheet($sheet, $title) {
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(20);

        // Title
        $sheet->setCellValue('B3', 'MENA CAREERS FINANCE');
        $sheet->getStyle('B3')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['green']));

        $sheet->setCellValue('B4', $title);
        $sheet->getStyle('B4')->getFont()->setBold(true)->setSize(24)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));

        // Placeholder for company name
        $sheet->setCellValue('B6', 'Company:');
        $sheet->setCellValue('C6', '');
        $sheet->getStyle('B6')->getFont()->setBold(true);

        // Date
        $sheet->setCellValue('B7', 'Date:');
        $sheet->setCellValue('C7', date('F j, Y'));
        $sheet->getStyle('B7')->getFont()->setBold(true);

        // Version
        $sheet->setCellValue('B8', 'Version:');
        $sheet->setCellValue('C8', '1.0');
        $sheet->getStyle('B8')->getFont()->setBold(true);

        // Disclaimer
        $sheet->setCellValue('B12', 'Disclaimer:');
        $sheet->getStyle('B12')->getFont()->setBold(true)->setSize(10);
        $sheet->setCellValue('B13', 'This model is for informational purposes only. Users should verify all inputs and assumptions.');
        $sheet->getStyle('B13')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['gray']));
        $sheet->mergeCells('B13:D13');

        // Style the header area
        $sheet->getStyle('B3:D8')->applyFromArray(array(
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('rgb' => $this->colors['cream']),
            ),
        ));
    }

    /**
     * Build LBO inputs sheet
     */
    private function build_lbo_inputs_sheet($sheet, $config) {
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(15);

        // Header
        $sheet->setCellValue('B2', 'LBO MODEL INPUTS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Transaction Inputs
        $sheet->setCellValue('B4', 'TRANSACTION DETAILS');
        $sheet->getStyle('B4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));

        $row = 5;
        $inputs = array(
            'Target Company' => '',
            'Deal Value (£m)' => '',
            'LTM EBITDA (£m)' => '',
            'Entry Multiple (x)' => '',
            'Transaction Date' => date('Y-m-d'),
        );

        foreach ($inputs as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Capital Structure
        $row += 2;
        $sheet->setCellValue('B' . $row, 'CAPITAL STRUCTURE');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $row++;

        $structure = array(
            'Equity %' => '40%',
            'Senior Debt %' => '40%',
            'Subordinated Debt %' => '20%',
            'Senior Debt Multiple (x)' => '4.0',
            'Senior Interest Rate' => '7.0%',
            'Sub Debt Interest Rate' => '12.0%',
        );

        foreach ($structure as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Operating Assumptions
        $row += 2;
        $sheet->setCellValue('B' . $row, 'OPERATING ASSUMPTIONS');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $row++;

        $operating = array(
            'Revenue Growth Y1' => '5.0%',
            'Revenue Growth Y2-5' => '3.0%',
            'EBITDA Margin' => '20.0%',
            'Capex % Revenue' => '3.0%',
            'NWC % Revenue Change' => '10.0%',
            'Hold Period (Years)' => '5',
        );

        foreach ($operating as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Exit Assumptions
        $row += 2;
        $sheet->setCellValue('B' . $row, 'EXIT ASSUMPTIONS');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $row++;

        $exit = array(
            'Exit Multiple - Downside' => '8.0',
            'Exit Multiple - Base' => '10.0',
            'Exit Multiple - Upside' => '12.0',
        );

        foreach ($exit as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }
    }

    /**
     * Style input cell
     */
    private function style_input_cell($sheet, $cell) {
        $sheet->getStyle($cell)->applyFromArray(array(
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('rgb' => 'E8F4EA'),
            ),
            'borders' => array(
                'allBorders' => array(
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => array('rgb' => $this->colors['green']),
                ),
            ),
        ));
    }

    /**
     * Build sources and uses sheet
     */
    private function build_sources_uses_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(12);

        $sheet->setCellValue('B2', 'SOURCES & USES OF FUNDS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Sources
        $sheet->setCellValue('B4', 'SOURCES');
        $sheet->getStyle('B4')->getFont()->setBold(true);

        $sources = array(
            array('Senior Debt', '=Inputs!C12*Inputs!C6', '%'),
            array('Subordinated Debt', '=Inputs!C13*Inputs!C6', '%'),
            array('Sponsor Equity', '=Inputs!C11*Inputs!C6', '%'),
            array('Total Sources', '=SUM(C5:C7)', '100%'),
        );

        $row = 5;
        foreach ($sources as $item) {
            $sheet->setCellValue('B' . $row, $item[0]);
            $sheet->setCellValue('C' . $row, $item[1]);
            $sheet->setCellValue('D' . $row, $item[2]);
            $row++;
        }

        $row += 2;

        // Uses
        $sheet->setCellValue('B' . $row, 'USES');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $uses = array(
            array('Purchase Enterprise Value', '=Inputs!C6', '%'),
            array('Transaction Fees', '=Inputs!C6*0.02', '%'),
            array('Financing Fees', '=Inputs!C6*0.01', '%'),
            array('Total Uses', '=SUM(C' . ($row) . ':C' . ($row + 2) . ')', '100%'),
        );

        foreach ($uses as $item) {
            $sheet->setCellValue('B' . $row, $item[0]);
            $sheet->setCellValue('C' . $row, $item[1]);
            $sheet->setCellValue('D' . $row, $item[2]);
            $row++;
        }
    }

    /**
     * Build debt schedule sheet
     */
    private function build_debt_schedule_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'DEBT SCHEDULE');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Years header
        $sheet->setCellValue('B4', '');
        $years = array('Entry', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        // Senior Debt section
        $sheet->setCellValue('B6', 'SENIOR DEBT');
        $sheet->getStyle('B6')->getFont()->setBold(true);

        $rows = array(
            'Beginning Balance',
            'Mandatory Amortization',
            'Optional Prepayment',
            'Ending Balance',
            'Interest Expense',
        );

        $row = 7;
        foreach ($rows as $label) {
            $sheet->setCellValue('B' . $row, $label);
            $row++;
        }

        // Subordinated Debt section
        $row += 2;
        $sheet->setCellValue('B' . $row, 'SUBORDINATED DEBT');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        foreach ($rows as $label) {
            $sheet->setCellValue('B' . $row, $label);
            $row++;
        }

        // Total Debt section
        $row += 2;
        $sheet->setCellValue('B' . $row, 'TOTAL DEBT');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $totals = array('Total Debt', 'Total Interest', 'Net Debt / EBITDA');
        foreach ($totals as $label) {
            $sheet->setCellValue('B' . $row, $label);
            $row++;
        }
    }

    /**
     * Build projections sheet
     */
    private function build_projections_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'FINANCIAL PROJECTIONS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Years header
        $sheet->setCellValue('B4', '');
        $years = array('LTM', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        // Income Statement Items
        $sheet->setCellValue('B6', 'INCOME STATEMENT');
        $sheet->getStyle('B6')->getFont()->setBold(true);

        $items = array(
            'Revenue',
            'Growth %',
            'EBITDA',
            'EBITDA Margin %',
            'D&A',
            'EBIT',
            'Interest Expense',
            'EBT',
            'Taxes',
            'Net Income',
        );

        $row = 7;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            $row++;
        }

        // Cash Flow Items
        $row += 2;
        $sheet->setCellValue('B' . $row, 'CASH FLOW');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $cf_items = array(
            'Net Income',
            'D&A',
            'Change in NWC',
            'Capex',
            'Free Cash Flow',
            'Debt Service',
            'Cash Available for Debt Paydown',
        );

        foreach ($cf_items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            $row++;
        }
    }

    /**
     * Build LBO returns sheet
     */
    private function build_lbo_returns_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        $sheet->setCellValue('B2', 'RETURNS ANALYSIS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Scenario headers
        $sheet->setCellValue('C4', 'Downside');
        $sheet->setCellValue('D4', 'Base');
        $sheet->setCellValue('E4', 'Upside');
        $sheet->getStyle('C4:E4')->getFont()->setBold(true);

        // Returns metrics
        $metrics = array(
            'Exit Multiple',
            'Exit Enterprise Value',
            'Exit Equity Value',
            'Initial Equity',
            'Gross IRR',
            'Net IRR',
            'MOIC',
            'Cash-on-Cash Return',
        );

        $row = 5;
        foreach ($metrics as $metric) {
            $sheet->setCellValue('B' . $row, $metric);
            $row++;
        }

        // Sensitivity section
        $row += 3;
        $sheet->setCellValue('B' . $row, 'SENSITIVITY ANALYSIS');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12);
        $row += 2;

        $sheet->setCellValue('B' . $row, 'IRR by Exit Multiple & EBITDA Growth');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
    }

    // Additional helper methods for other sheet types...

    /**
     * Build merger transaction sheet
     */
    private function build_merger_transaction_sheet($sheet, $config) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(25);

        $sheet->setCellValue('B2', 'TRANSACTION DETAILS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $fields = array(
            'Acquirer Name' => '',
            'Target Name' => '',
            'Announcement Date' => date('Y-m-d'),
            'Deal Value (£m)' => '',
            'Offer Price per Share' => '',
            'Premium to Unaffected Price' => '',
            'Cash Consideration %' => '50%',
            'Stock Consideration %' => '50%',
            'Expected Close Date' => '',
        );

        $row = 4;
        foreach ($fields as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }
    }

    /**
     * Build company sheet (for acquirer/target)
     */
    private function build_company_sheet($sheet, $type) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);

        $sheet->setCellValue('B2', strtoupper($type) . ' FINANCIALS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $fields = array(
            'Company Name' => '',
            'Revenue (£m)' => '',
            'EBITDA (£m)' => '',
            'EBITDA Margin %' => '',
            'Net Income (£m)' => '',
            'Total Debt (£m)' => '',
            'Cash (£m)' => '',
            'Shares Outstanding (m)' => '',
            'Share Price' => '',
            'Market Cap (£m)' => '',
            'Enterprise Value (£m)' => '',
        );

        $row = 4;
        foreach ($fields as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }
    }

    /**
     * Build synergies sheet
     */
    private function build_synergies_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        $sheet->setCellValue('B2', 'SYNERGY ANALYSIS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Year 1');
        $sheet->setCellValue('D4', 'Year 2');
        $sheet->setCellValue('E4', 'Run-Rate');
        $sheet->getStyle('C4:E4')->getFont()->setBold(true);

        $items = array(
            'COST SYNERGIES',
            'Headcount Reduction',
            'Facility Consolidation',
            'Procurement Savings',
            'IT Rationalization',
            'Total Cost Synergies',
            '',
            'REVENUE SYNERGIES',
            'Cross-Selling',
            'Geographic Expansion',
            'Total Revenue Synergies',
            '',
            'TOTAL SYNERGIES',
            '',
            'Integration Costs',
            'Net Synergies',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (strpos($item, 'SYNERGIES') !== false || $item === 'Integration Costs' || $item === 'Net Synergies') {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build pro forma sheet
     */
    private function build_proforma_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        $sheet->setCellValue('B2', 'PRO FORMA COMBINED');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Acquirer');
        $sheet->setCellValue('D4', 'Target');
        $sheet->setCellValue('E4', 'Combined');
        $sheet->getStyle('C4:E4')->getFont()->setBold(true);

        $items = array(
            'Revenue',
            'EBITDA',
            'Synergies',
            'Pro Forma EBITDA',
            'Net Income',
            'EPS',
            '',
            'Total Debt',
            'Cash',
            'Net Debt',
            'Shares Outstanding',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            $row++;
        }
    }

    /**
     * Build accretion/dilution sheet
     */
    private function build_accretion_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);

        $sheet->setCellValue('B2', 'ACCRETION / DILUTION ANALYSIS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Year 1');
        $sheet->setCellValue('D4', 'Year 2');
        $sheet->getStyle('C4:D4')->getFont()->setBold(true);

        $items = array(
            'Acquirer Standalone EPS',
            'Pro Forma EPS (No Synergies)',
            'EPS Impact (No Synergies)',
            '% Accretion/(Dilution)',
            '',
            'Pro Forma EPS (With Synergies)',
            'EPS Impact (With Synergies)',
            '% Accretion/(Dilution)',
            '',
            'Breakeven Synergies Required',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (strpos($item, 'Accretion') !== false) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build DCF inputs sheet
     */
    private function build_dcf_inputs_sheet($sheet, $config) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('B2', 'DCF MODEL INPUTS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Company Info
        $sheet->setCellValue('B4', 'COMPANY INFORMATION');
        $sheet->getStyle('B4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));

        $fields = array(
            'Company Name' => '',
            'Valuation Date' => date('Y-m-d'),
            'Currency' => 'GBP',
            'LTM Revenue (£m)' => '',
            'LTM EBITDA (£m)' => '',
        );

        $row = 5;
        foreach ($fields as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Growth Assumptions
        $row += 2;
        $sheet->setCellValue('B' . $row, 'GROWTH ASSUMPTIONS');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $row++;

        for ($y = 1; $y <= 5; $y++) {
            $sheet->setCellValue('B' . $row, "Revenue Growth Year $y");
            $sheet->setCellValue('C' . $row, '5.0%');
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Margin & Operating
        $row += 2;
        $sheet->setCellValue('B' . $row, 'OPERATING ASSUMPTIONS');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $row++;

        $operating = array(
            'Target EBITDA Margin' => '20.0%',
            'D&A % of Revenue' => '3.0%',
            'Capex % of Revenue' => '4.0%',
            'NWC % of Revenue' => '10.0%',
            'Tax Rate' => '25.0%',
        );

        foreach ($operating as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Terminal Value
        $row += 2;
        $sheet->setCellValue('B' . $row, 'TERMINAL VALUE');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $row++;

        $terminal = array(
            'Terminal Growth Rate' => '2.5%',
        );

        foreach ($terminal as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }
    }

    /**
     * Build financials sheet
     */
    private function build_financials_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'HISTORICAL & PROJECTED FINANCIALS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $years = array('LTM', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        $items = array(
            'Revenue',
            'Growth %',
            '',
            'EBITDA',
            'EBITDA Margin %',
            '',
            'D&A',
            'EBIT',
            'Tax',
            'NOPAT',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            $row++;
        }
    }

    /**
     * Build FCF sheet
     */
    private function build_fcf_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'FREE CASH FLOW BUILD-UP');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $years = array('LTM', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        $items = array(
            'EBIT',
            'Tax @ Rate',
            'NOPAT',
            '',
            'Add: D&A',
            'Less: Capex',
            'Less: Change in NWC',
            '',
            'Unlevered Free Cash Flow',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if ($item === 'Unlevered Free Cash Flow') {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build WACC sheet
     */
    private function build_wacc_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('B2', 'WACC CALCULATION');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Cost of Equity
        $sheet->setCellValue('B4', 'COST OF EQUITY (CAPM)');
        $sheet->getStyle('B4')->getFont()->setBold(true);

        $equity = array(
            'Risk-Free Rate' => '4.0%',
            'Equity Risk Premium' => '5.5%',
            'Levered Beta' => '1.10',
            'Size Premium' => '0.0%',
            'Country Risk Premium' => '0.0%',
            'Cost of Equity' => '',
        );

        $row = 5;
        foreach ($equity as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            if ($label !== 'Cost of Equity') {
                $this->style_input_cell($sheet, 'C' . $row);
            } else {
                $sheet->getStyle('B' . $row . ':C' . $row)->getFont()->setBold(true);
            }
            $row++;
        }

        // Cost of Debt
        $row += 2;
        $sheet->setCellValue('B' . $row, 'COST OF DEBT');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $debt = array(
            'Pre-Tax Cost of Debt' => '6.0%',
            'Tax Rate' => '25.0%',
            'After-Tax Cost of Debt' => '',
        );

        foreach ($debt as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            if ($label !== 'After-Tax Cost of Debt') {
                $this->style_input_cell($sheet, 'C' . $row);
            }
            $row++;
        }

        // Capital Structure
        $row += 2;
        $sheet->setCellValue('B' . $row, 'CAPITAL STRUCTURE');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $structure = array(
            'Debt / Total Capital' => '30.0%',
            'Equity / Total Capital' => '70.0%',
        );

        foreach ($structure as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // WACC Result
        $row += 2;
        $sheet->setCellValue('B' . $row, 'WACC');
        $sheet->setCellValue('C' . $row, '');
        $sheet->getStyle('B' . $row . ':C' . $row)->getFont()->setBold(true)->setSize(14);
    }

    /**
     * Build DCF valuation sheet
     */
    private function build_dcf_valuation_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('B2', 'DCF VALUATION');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Present Value of FCF
        $sheet->setCellValue('B4', 'PRESENT VALUE CALCULATION');
        $sheet->getStyle('B4')->getFont()->setBold(true);

        $items = array(
            'Sum of Discounted FCF' => '',
            '',
            'Terminal Value Calculation',
            'Terminal Year FCF' => '',
            'Terminal Growth Rate' => '',
            'Terminal Value' => '',
            'PV of Terminal Value' => '',
            '',
            'ENTERPRISE VALUE',
            '',
            'Less: Net Debt' => '',
            'Less: Minority Interest' => '',
            'Add: Associates' => '',
            '',
            'EQUITY VALUE',
            '',
            'Shares Outstanding (m)' => '',
            'IMPLIED SHARE PRICE' => '',
            '',
            'IMPLIED MULTIPLES',
            'EV / LTM Revenue' => '',
            'EV / LTM EBITDA' => '',
        );

        $row = 5;
        foreach ($items as $label => $value) {
            if (is_string($label)) {
                $sheet->setCellValue('B' . $row, $label);
                $sheet->setCellValue('C' . $row, $value);
                if (in_array($label, array('ENTERPRISE VALUE', 'EQUITY VALUE', 'IMPLIED SHARE PRICE', 'IMPLIED MULTIPLES'))) {
                    $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                }
            }
            $row++;
        }
    }

    /**
     * Build sensitivity sheet
     */
    private function build_sensitivity_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(20);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'SENSITIVITY ANALYSIS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('B4', 'Share Price Sensitivity to WACC & Terminal Growth');
        $sheet->getStyle('B4')->getFont()->setBold(true);

        // Terminal growth header
        $sheet->setCellValue('C6', '1.5%');
        $sheet->setCellValue('D6', '2.0%');
        $sheet->setCellValue('E6', '2.5%');
        $sheet->setCellValue('F6', '3.0%');
        $sheet->setCellValue('G6', '3.5%');
        $sheet->mergeCells('C5:G5');
        $sheet->setCellValue('C5', 'Terminal Growth Rate');
        $sheet->getStyle('C5:G6')->getFont()->setBold(true);
        $sheet->getStyle('C5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // WACC labels
        $sheet->setCellValue('B7', '8.0%');
        $sheet->setCellValue('B8', '8.5%');
        $sheet->setCellValue('B9', '9.0%');
        $sheet->setCellValue('B10', '9.5%');
        $sheet->setCellValue('B11', '10.0%');
        $sheet->getStyle('B7:B11')->getFont()->setBold(true);

        $sheet->setCellValue('A7', 'WACC');
        $sheet->mergeCells('A7:A11');
        $sheet->getStyle('A7')->getAlignment()->setTextRotation(90)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Build assumptions sheet for three-statement
     */
    private function build_assumptions_sheet($sheet, $config) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        $sheet->setCellValue('B2', 'MODEL ASSUMPTIONS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Headers
        $sheet->setCellValue('C4', 'Year 1');
        $sheet->setCellValue('D4', 'Year 2');
        $sheet->setCellValue('E4', 'Year 3');
        $sheet->getStyle('C4:E4')->getFont()->setBold(true);

        $assumptions = array(
            'Revenue Growth %',
            'Gross Margin %',
            'SG&A % of Revenue',
            'R&D % of Revenue',
            'D&A % of Revenue',
            '',
            'A/R Days',
            'Inventory Days',
            'A/P Days',
            '',
            'Capex % of Revenue',
            'Interest Rate on Debt',
            'Tax Rate',
        );

        $row = 5;
        foreach ($assumptions as $item) {
            $sheet->setCellValue('B' . $row, $item);
            $row++;
        }
    }

    /**
     * Build income statement sheet
     */
    private function build_income_statement_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($col = 'C'; $col <= 'F'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        $sheet->setCellValue('B2', 'INCOME STATEMENT');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Base');
        $sheet->setCellValue('D4', 'Year 1');
        $sheet->setCellValue('E4', 'Year 2');
        $sheet->setCellValue('F4', 'Year 3');
        $sheet->getStyle('C4:F4')->getFont()->setBold(true);

        $items = array(
            'Revenue',
            'COGS',
            'Gross Profit',
            'Gross Margin %',
            '',
            'SG&A',
            'R&D',
            'D&A',
            'Operating Expenses',
            '',
            'Operating Income (EBIT)',
            'Operating Margin %',
            '',
            'Interest Expense',
            'Other Income/(Expense)',
            'EBT',
            '',
            'Tax Expense',
            'Net Income',
            'Net Margin %',
            '',
            'EPS',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('Gross Profit', 'Operating Income (EBIT)', 'Net Income', 'EPS'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build balance sheet sheet
     */
    private function build_balance_sheet_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        for ($col = 'C'; $col <= 'F'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        $sheet->setCellValue('B2', 'BALANCE SHEET');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Base');
        $sheet->setCellValue('D4', 'Year 1');
        $sheet->setCellValue('E4', 'Year 2');
        $sheet->setCellValue('F4', 'Year 3');
        $sheet->getStyle('C4:F4')->getFont()->setBold(true);

        $items = array(
            'ASSETS',
            'Cash & Equivalents',
            'Accounts Receivable',
            'Inventory',
            'Other Current Assets',
            'Total Current Assets',
            '',
            'PP&E (Net)',
            'Intangibles',
            'Other Non-Current Assets',
            'Total Non-Current Assets',
            '',
            'TOTAL ASSETS',
            '',
            'LIABILITIES',
            'Accounts Payable',
            'Accrued Expenses',
            'Short-Term Debt',
            'Total Current Liabilities',
            '',
            'Long-Term Debt',
            'Other Non-Current Liabilities',
            'Total Non-Current Liabilities',
            '',
            'TOTAL LIABILITIES',
            '',
            'EQUITY',
            'Share Capital',
            'Retained Earnings',
            'Total Equity',
            '',
            'TOTAL LIABILITIES & EQUITY',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('ASSETS', 'LIABILITIES', 'EQUITY', 'Total Current Assets', 'Total Non-Current Assets', 'TOTAL ASSETS', 'Total Current Liabilities', 'Total Non-Current Liabilities', 'TOTAL LIABILITIES', 'Total Equity', 'TOTAL LIABILITIES & EQUITY'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build cash flow sheet
     */
    private function build_cashflow_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($col = 'C'; $col <= 'F'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        $sheet->setCellValue('B2', 'CASH FLOW STATEMENT');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Base');
        $sheet->setCellValue('D4', 'Year 1');
        $sheet->setCellValue('E4', 'Year 2');
        $sheet->setCellValue('F4', 'Year 3');
        $sheet->getStyle('C4:F4')->getFont()->setBold(true);

        $items = array(
            'OPERATING ACTIVITIES',
            'Net Income',
            'D&A',
            'Change in Working Capital',
            '  Change in A/R',
            '  Change in Inventory',
            '  Change in A/P',
            'Cash from Operations',
            '',
            'INVESTING ACTIVITIES',
            'Capital Expenditures',
            'Cash from Investing',
            '',
            'FINANCING ACTIVITIES',
            'Debt Issuance/(Repayment)',
            'Dividends',
            'Cash from Financing',
            '',
            'Net Change in Cash',
            'Beginning Cash',
            'Ending Cash',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('OPERATING ACTIVITIES', 'INVESTING ACTIVITIES', 'FINANCING ACTIVITIES', 'Cash from Operations', 'Cash from Investing', 'Cash from Financing', 'Net Change in Cash', 'Ending Cash'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build ratios sheet
     */
    private function build_ratios_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($col = 'C'; $col <= 'F'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        $sheet->setCellValue('B2', 'KEY FINANCIAL RATIOS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('C4', 'Base');
        $sheet->setCellValue('D4', 'Year 1');
        $sheet->setCellValue('E4', 'Year 2');
        $sheet->setCellValue('F4', 'Year 3');
        $sheet->getStyle('C4:F4')->getFont()->setBold(true);

        $ratios = array(
            'PROFITABILITY',
            'Gross Margin',
            'EBITDA Margin',
            'Operating Margin',
            'Net Margin',
            'ROE',
            'ROA',
            '',
            'LIQUIDITY',
            'Current Ratio',
            'Quick Ratio',
            '',
            'LEVERAGE',
            'Debt / Equity',
            'Debt / EBITDA',
            'Interest Coverage',
            '',
            'EFFICIENCY',
            'Asset Turnover',
            'Days Sales Outstanding',
            'Days Inventory Outstanding',
            'Days Payables Outstanding',
            'Cash Conversion Cycle',
        );

        $row = 5;
        foreach ($ratios as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('PROFITABILITY', 'LIQUIDITY', 'LEVERAGE', 'EFFICIENCY'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
            }
            $row++;
        }
    }

    /**
     * Build property sheet for Commercial RE
     */
    private function build_property_sheet($sheet, $config) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(25);

        $sheet->setCellValue('B2', 'PROPERTY DETAILS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $fields = array(
            'Property Name' => '',
            'Address' => '',
            'Property Type' => 'Office',
            'Class' => 'A',
            '',
            'Total Square Feet' => '',
            'Net Leasable Area' => '',
            'Year Built' => '',
            'Year Renovated' => '',
            '',
            'Current Occupancy %' => '',
            'Number of Tenants' => '',
            'WALT (Years)' => '',
        );

        $row = 4;
        foreach ($fields as $label => $value) {
            if ($label !== '') {
                $sheet->setCellValue('B' . $row, $label);
                $sheet->setCellValue('C' . $row, $value);
                $this->style_input_cell($sheet, 'C' . $row);
            }
            $row++;
        }
    }

    /**
     * Build rent roll sheet
     */
    private function build_rentroll_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);

        $sheet->setCellValue('B2', 'RENT ROLL');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Headers
        $headers = array('Tenant', 'Sq Ft', 'Rent/SF', 'Annual Rent', 'Lease Start', 'Lease End');
        $col = 'B';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '4', $header);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        // Summary section
        $sheet->setCellValue('B20', 'SUMMARY');
        $sheet->getStyle('B20')->getFont()->setBold(true);

        $summary = array(
            'Total Occupied SF' => '',
            'Vacancy SF' => '',
            'Average Rent per SF' => '',
            'Market Rent per SF' => '',
            'Weighted Avg Lease Term' => '',
        );

        $row = 21;
        foreach ($summary as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $row++;
        }
    }

    /**
     * Build RE income sheet
     */
    private function build_re_income_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'INCOME PROJECTIONS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $years = array('Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        $items = array(
            'Gross Potential Rent',
            'Rent Escalation',
            'Vacancy Loss',
            'Effective Rental Income',
            '',
            'Other Income',
            '  Parking',
            '  Storage',
            '  Other',
            'Total Other Income',
            '',
            'Effective Gross Income',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('Effective Rental Income', 'Total Other Income', 'Effective Gross Income'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build RE expenses sheet
     */
    private function build_re_expenses_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'OPERATING EXPENSES');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $years = array('Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        $items = array(
            'Property Taxes',
            'Insurance',
            'Utilities',
            'Repairs & Maintenance',
            'Management Fee',
            'Leasing Commissions',
            'Professional Fees',
            'Marketing',
            'General & Admin',
            '',
            'Total Operating Expenses',
            'OpEx Ratio %',
            '',
            'Capital Reserves',
            'Tenant Improvements',
            '',
            'Total Expenses',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('Total Operating Expenses', 'Total Expenses'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }
    }

    /**
     * Build NOI sheet
     */
    private function build_noi_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        for ($col = 'C'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->setCellValue('B2', 'NET OPERATING INCOME');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $years = array('Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6');
        $col = 'C';
        foreach ($years as $year) {
            $sheet->setCellValue($col . '4', $year);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $col++;
        }

        $items = array(
            'Effective Gross Income',
            'Total Operating Expenses',
            '',
            'NET OPERATING INCOME',
            'NOI Growth %',
            'NOI Margin %',
            '',
            'Less: Capital Reserves',
            'Less: Tenant Improvements',
            '',
            'CASH NOI',
        );

        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('NET OPERATING INCOME', 'CASH NOI'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12);
            }
            $row++;
        }
    }

    /**
     * Build RE valuation sheet
     */
    private function build_re_valuation_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('B2', 'PROPERTY VALUATION');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Cap Rate Valuation
        $sheet->setCellValue('B4', 'DIRECT CAPITALIZATION');
        $sheet->getStyle('B4')->getFont()->setBold(true);

        $cap_items = array(
            'Stabilized NOI' => '',
            'Going-In Cap Rate' => '',
            'Implied Value' => '',
            'Price per SF' => '',
        );

        $row = 5;
        foreach ($cap_items as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $row++;
        }

        // Transaction Details
        $row += 2;
        $sheet->setCellValue('B' . $row, 'TRANSACTION');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $transaction = array(
            'Purchase Price' => '',
            'Transaction Costs' => '',
            'Total Investment' => '',
        );

        foreach ($transaction as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }

        // Exit Valuation
        $row += 2;
        $sheet->setCellValue('B' . $row, 'EXIT VALUATION');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $exit = array(
            'Exit Year NOI' => '',
            'Exit Cap Rate' => '',
            'Gross Sale Price' => '',
            'Disposition Costs' => '',
            'Net Sale Proceeds' => '',
        );

        foreach ($exit as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $row++;
        }
    }

    /**
     * Build RE returns sheet
     */
    private function build_re_returns_sheet($sheet) {
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);

        $sheet->setCellValue('B2', 'INVESTMENT RETURNS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Scenarios
        $sheet->setCellValue('C4', 'Downside');
        $sheet->setCellValue('D4', 'Base');
        $sheet->setCellValue('E4', 'Upside');
        $sheet->getStyle('C4:E4')->getFont()->setBold(true);

        $returns = array(
            'Exit Cap Rate',
            'Exit Value',
            '',
            'Unlevered IRR',
            'Levered IRR',
            'Equity Multiple',
            'Cash-on-Cash (Y1)',
            'Avg Cash-on-Cash',
            '',
            'Total Distributions',
            'Total Profit',
        );

        $row = 5;
        foreach ($returns as $item) {
            $sheet->setCellValue('B' . $row, $item);
            if (in_array($item, array('Unlevered IRR', 'Levered IRR', 'Equity Multiple'))) {
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            }
            $row++;
        }

        // Financing section
        $row += 2;
        $sheet->setCellValue('B' . $row, 'FINANCING ASSUMPTIONS');
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $financing = array(
            'Loan-to-Value' => '',
            'Loan Amount' => '',
            'Interest Rate' => '',
            'Amortization (Years)' => '',
            'Annual Debt Service' => '',
            'DSCR' => '',
        );

        foreach ($financing as $label => $value) {
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, $value);
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }
    }

    /**
     * Build generic inputs sheet
     */
    private function build_generic_inputs_sheet($sheet, $config) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('B2', 'MODEL INPUTS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $mappings = $config['input_mappings'] ?? array();
        $row = 4;

        foreach ($mappings as $field => $mapping) {
            $label = $mapping['label'] ?? ucfirst(str_replace('_', ' ', $field));
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, '');
            $this->style_input_cell($sheet, 'C' . $row);
            $row++;
        }
    }

    /**
     * Build generic outputs sheet
     */
    private function build_generic_outputs_sheet($sheet, $config) {
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('B2', 'MODEL OUTPUTS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        $mappings = $config['output_mappings'] ?? array();
        $row = 4;

        foreach ($mappings as $field => $mapping) {
            $label = ucfirst(str_replace('_', ' ', $field));
            $sheet->setCellValue('B' . $row, $label);
            $sheet->setCellValue('C' . $row, '');
            $row++;
        }
    }

    /**
     * Inject analysis data into spreadsheet
     */
    private function inject_excel_data($spreadsheet, $analysis, $config) {
        if (empty($analysis)) {
            return;
        }

        $inputs = $analysis['inputs'] ?? array();
        $mappings = $config['input_mappings'] ?? array();

        // Inject inputs from mappings
        foreach ($mappings as $field => $mapping) {
            $value = $this->get_nested_value($inputs, $field);
            if ($value === null) continue;

            // Handle array values (from contextual data format)
            if (is_array($value)) {
                $value = $value['amount'] ?? $value['value'] ?? $value['label'] ?? '';
            }

            try {
                $sheet = $spreadsheet->getSheetByName($mapping['sheet']);
                if ($sheet && $mapping['cell']) {
                    $sheet->setCellValue($mapping['cell'], $value);

                    // Apply format
                    $this->apply_cell_format($sheet, $mapping['cell'], $mapping['format'] ?? 'text');
                }
            } catch (Exception $e) {
                continue;
            }
        }

        // Inject scenario data if available (for LBO)
        if (!empty($analysis['scenarios'])) {
            $this->inject_scenarios($spreadsheet, $analysis['scenarios']);
        }

        // Update cover sheet with company info
        $cover = $spreadsheet->getSheetByName('Cover');
        if ($cover) {
            if (!empty($inputs['target_name'])) {
                $cover->setCellValue('C6', is_array($inputs['target_name']) ? $inputs['target_name']['value'] ?? '' : $inputs['target_name']);
            } elseif (!empty($inputs['company_name'])) {
                $cover->setCellValue('C6', is_array($inputs['company_name']) ? $inputs['company_name']['value'] ?? '' : $inputs['company_name']);
            } elseif (!empty($inputs['property_name'])) {
                $cover->setCellValue('C6', is_array($inputs['property_name']) ? $inputs['property_name']['value'] ?? '' : $inputs['property_name']);
            }

            // Add data source if available
            if (!empty($analysis['data_source'])) {
                $cover->setCellValue('C8', 'Source: ' . $analysis['data_source']);
            }
        }

        // Inject contextual metrics into Inputs sheet if available
        $inputsSheet = $spreadsheet->getSheetByName('Inputs') ?? $spreadsheet->getSheetByName('Assumptions');
        if ($inputsSheet) {
            $this->inject_contextual_metrics($inputsSheet, $inputs, $analysis);
        }
    }

    /**
     * Inject contextual market metrics into spreadsheet
     */
    private function inject_contextual_metrics($sheet, $inputs, $analysis) {
        // Start row for dynamic metrics
        $row = 10;

        // Inject any metrics that have value/label format (from contextual data)
        foreach ($inputs as $key => $data) {
            if (is_array($data) && isset($data['value']) && isset($data['label'])) {
                try {
                    $sheet->setCellValue('A' . $row, $data['label']);
                    $sheet->setCellValue('B' . $row, $data['value']);
                    if (!empty($data['source'])) {
                        $sheet->setCellValue('C' . $row, $data['source']);
                    }
                    $row++;
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        // Add summary if available
        if (!empty($analysis['summary'])) {
            try {
                $sheet->setCellValue('A' . ($row + 2), 'Summary:');
                $sheet->setCellValue('B' . ($row + 2), $analysis['summary']);
            } catch (Exception $e) {
                // Ignore errors
            }
        }
    }

    /**
     * Apply cell format
     */
    private function apply_cell_format($sheet, $cell, $format) {
        $formats = array(
            'currency' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'currency_millions' => '#,##0.0" m"',
            'percentage' => NumberFormat::FORMAT_PERCENTAGE_00,
            'multiple' => '0.0"x"',
            'integer' => NumberFormat::FORMAT_NUMBER,
            'decimal' => NumberFormat::FORMAT_NUMBER_00,
            'date' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        );

        if (isset($formats[$format])) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($formats[$format]);
        }
    }

    /**
     * Inject scenario data
     */
    private function inject_scenarios($spreadsheet, $scenarios) {
        $returns = $spreadsheet->getSheetByName('Returns');
        if (!$returns) return;

        $scenario_cols = array('downside' => 'C', 'base' => 'D', 'upside' => 'E');
        $metrics = array('exit_multiple' => 5, 'irr' => 9, 'moic' => 11);

        foreach ($scenarios as $scenario => $data) {
            if (!isset($scenario_cols[$scenario])) continue;
            $col = $scenario_cols[$scenario];

            foreach ($metrics as $metric => $row) {
                if (isset($data[$metric])) {
                    $returns->setCellValue($col . $row, $data[$metric]);
                }
            }
        }
    }

    /**
     * Get nested array value
     */
    private function get_nested_value($array, $key) {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Generate PDF template programmatically
     */
    private function generate_pdf_template($model_type, $config) {
        $model_name = $config['model_name'] ?? 'Financial Model';
        $description = $config['description'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$model_name} Guide</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #18181b;
            background: #fff;
        }

        .header {
            background: #0a1628;
            color: #fff;
            padding: 40px;
            margin-bottom: 30px;
        }

        .header-brand {
            font-size: 12pt;
            color: #2d7d5a;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .header-title {
            font-size: 28pt;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .header-subtitle {
            font-size: 12pt;
            color: #a1a1aa;
        }

        .content {
            padding: 0 40px 40px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 14pt;
            font-weight: 700;
            color: #0a1628;
            border-bottom: 2px solid #1e6b4a;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .metrics-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .metrics-row {
            display: table-row;
        }

        .metric-box {
            display: table-cell;
            width: 25%;
            padding: 15px;
            background: #f4f4f5;
            border: 1px solid #e4e4e7;
            text-align: center;
        }

        .metric-value {
            font-size: 20pt;
            font-weight: 700;
            color: #0a1628;
        }

        .metric-label {
            font-size: 9pt;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th, .table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e4e4e7;
        }

        .table th {
            background: #f4f4f5;
            font-weight: 600;
            font-size: 9pt;
            text-transform: uppercase;
            color: #52525b;
        }

        .note {
            background: #faf8f5;
            border-left: 4px solid #1e6b4a;
            padding: 15px;
            margin: 20px 0;
            font-size: 10pt;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e4e4e7;
            font-size: 9pt;
            color: #71717a;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 600;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-amber {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-brand">MENA CAREERS FINANCE</div>
        <h1 class="header-title">{$model_name}</h1>
        <p class="header-subtitle">{$description}</p>
    </div>

    <div class="content">
        <div class="section">
            <h2 class="section-title">Executive Summary</h2>
            <p>{{summary}}</p>
        </div>

        <div class="section">
            <h2 class="section-title">Key Metrics</h2>
            <div class="metrics-grid">
                <div class="metrics-row">
                    <div class="metric-box">
                        <div class="metric-value">{{metric_1_value}}</div>
                        <div class="metric-label">{{metric_1_label}}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value">{{metric_2_value}}</div>
                        <div class="metric-label">{{metric_2_label}}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value">{{metric_3_value}}</div>
                        <div class="metric-label">{{metric_3_label}}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value">{{metric_4_value}}</div>
                        <div class="metric-label">{{metric_4_label}}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Analysis Details</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    {{analysis_rows}}
                </tbody>
            </table>
        </div>

        <div class="note">
            <strong>Data Confidence:</strong> <span class="badge badge-{{confidence_class}}">{{data_confidence}}</span><br>
            {{notes}}
        </div>

        <div class="footer">
            <p>Generated by MENA Careers Finance Intelligence Platform | {{date}}</p>
            <p>This analysis is for informational purposes only and should not be considered financial advice.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render PDF template with data
     */
    private function render_pdf_template($html, $analysis, $config, $post) {
        $inputs = $analysis['inputs'] ?? array();
        $scenarios = $analysis['scenarios'] ?? array();

        // Base replacements
        $replacements = array(
            '{{title}}' => esc_html($post->post_title),
            '{{date}}' => date('F j, Y'),
            '{{generated_date}}' => date('F j, Y'),
            '{{article_id}}' => $post->ID,
            '{{summary}}' => esc_html($analysis['summary'] ?? 'Financial analysis based on available data and sector benchmarks.'),
            '{{executive_summary}}' => esc_html($analysis['executive_summary'] ?? $analysis['summary'] ?? 'Comprehensive financial analysis based on disclosed transaction data and sector benchmarks.'),
            '{{transaction_overview}}' => esc_html($analysis['transaction_overview'] ?? 'This analysis provides a detailed examination of the transaction structure, financing terms, and projected returns.'),
            '{{data_confidence}}' => ucfirst($analysis['data_confidence'] ?? 'medium'),
            '{{confidence_class}}' => ($analysis['data_confidence'] ?? 'medium') === 'high' ? 'green' : 'amber',
            '{{notes}}' => esc_html($analysis['notes'] ?? ''),
            '{{sector_context}}' => esc_html($analysis['sector_context'] ?? 'Analysis based on current market conditions and comparable transactions.'),
        );

        // Map all input fields to placeholders
        foreach ($inputs as $key => $value) {
            $placeholder = '{{' . $key . '}}';

            if (is_array($value)) {
                // Handle contextual data format (from Market Data)
                if (isset($value['value']) && isset($value['label'])) {
                    $replacements[$placeholder] = esc_html($value['value']);
                    $replacements['{{' . $key . '_label}}'] = esc_html($value['label']);
                    if (isset($value['source'])) {
                        $replacements['{{' . $key . '_source}}'] = esc_html($value['source']);
                    }
                }
                // Handle currency format
                elseif (isset($value['amount'])) {
                    $display = $this->format_number($value['amount'], $key);
                    if (isset($value['currency'])) {
                        $symbol = array('USD' => '$', 'GBP' => '£', 'EUR' => '€')[$value['currency']] ?? '';
                        $display = $symbol . $display;
                    }
                    $replacements[$placeholder] = $display;
                    $replacements['{{' . $key . '_currency}}'] = $value['currency'] ?? 'USD';
                }
            } else {
                $replacements[$placeholder] = $this->format_number($value, $key);
            }
        }

        // Add data source if available
        if (!empty($analysis['data_source'])) {
            $replacements['{{data_source}}'] = esc_html($analysis['data_source']);
        }

        // Add model title if available
        if (!empty($analysis['model_title'])) {
            $replacements['{{model_title}}'] = esc_html($analysis['model_title']);
        }

        // Map scenario data (base, upside, downside)
        foreach ($scenarios as $scenario => $data) {
            foreach ($data as $metric => $value) {
                $placeholder = '{{' . $metric . '_' . $scenario . '}}';
                $replacements[$placeholder] = $this->format_number($value, $metric);
            }
        }

        // Handle specific LBO placeholders
        $replacements = array_merge($replacements, array(
            '{{deal_type}}' => $inputs['deal_type'] ?? 'Leveraged Buyout',
            '{{target_name}}' => esc_html($inputs['target_name'] ?? $inputs['company_name'] ?? 'Target Company'),
            '{{buyer_name}}' => esc_html($inputs['buyer_name'] ?? $inputs['sponsor'] ?? 'Financial Sponsor'),
            '{{deal_value}}' => $this->format_currency($inputs['deal_value'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{deal_currency}}' => $inputs['deal_currency'] ?? 'USD',
            '{{entry_multiple}}' => number_format($inputs['entry_multiple'] ?? 10.0, 1),
            '{{exit_year}}' => $inputs['exit_year'] ?? 5,
            '{{revenue_growth}}' => number_format(($inputs['revenue_growth'] ?? 0.05) * 100, 1),
            '{{ebitda_margin}}' => number_format(($inputs['ebitda_margin'] ?? 0.20) * 100, 1),
        ));

        // Handle sources & uses
        $replacements = array_merge($replacements, array(
            '{{senior_debt_amount}}' => $this->format_currency($inputs['senior_debt'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{sub_debt_amount}}' => $this->format_currency($inputs['sub_debt'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{sponsor_equity}}' => $this->format_currency($inputs['sponsor_equity'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{mgmt_rollover}}' => $this->format_currency($inputs['mgmt_rollover'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{total_sources}}' => $this->format_currency($inputs['total_sources'] ?? $inputs['deal_value'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{purchase_equity}}' => $this->format_currency($inputs['purchase_equity'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{refinance_debt}}' => $this->format_currency($inputs['refinance_debt'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{transaction_fees}}' => $this->format_currency($inputs['transaction_fees'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{cash_to_bs}}' => $this->format_currency($inputs['cash_to_bs'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{total_uses}}' => $this->format_currency($inputs['total_uses'] ?? $inputs['deal_value'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
        ));

        // Handle returns scenarios
        $base = $scenarios['base'] ?? array();
        $upside = $scenarios['upside'] ?? array();
        $downside = $scenarios['downside'] ?? array();

        $replacements = array_merge($replacements, array(
            '{{exit_multiple_base}}' => number_format($base['exit_multiple'] ?? $inputs['exit_multiple_base'] ?? 11.0, 1),
            '{{exit_multiple_upside}}' => number_format($upside['exit_multiple'] ?? $inputs['exit_multiple_upside'] ?? 12.0, 1),
            '{{exit_multiple_downside}}' => number_format($downside['exit_multiple'] ?? $inputs['exit_multiple_downside'] ?? 9.0, 1),
            '{{exit_ev_base}}' => $this->format_currency($base['exit_ev'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{exit_ev_upside}}' => $this->format_currency($upside['exit_ev'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{exit_ev_downside}}' => $this->format_currency($downside['exit_ev'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{exit_equity_base}}' => $this->format_currency($base['exit_equity'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{exit_equity_upside}}' => $this->format_currency($upside['exit_equity'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{exit_equity_downside}}' => $this->format_currency($downside['exit_equity'] ?? 0, $inputs['deal_currency'] ?? 'USD'),
            '{{irr_base}}' => number_format(($base['irr'] ?? $inputs['irr_base'] ?? 0.20) * 100, 1),
            '{{irr_upside}}' => number_format(($upside['irr'] ?? $inputs['irr_upside'] ?? 0.25) * 100, 1),
            '{{irr_downside}}' => number_format(($downside['irr'] ?? $inputs['irr_downside'] ?? 0.12) * 100, 1),
            '{{moic_base}}' => number_format($base['moic'] ?? $inputs['moic_base'] ?? 2.0, 1),
            '{{moic_upside}}' => number_format($upside['moic'] ?? $inputs['moic_upside'] ?? 2.5, 1),
            '{{moic_downside}}' => number_format($downside['moic'] ?? $inputs['moic_downside'] ?? 1.5, 1),
        ));

        // Build numbered metric slots (for generic templates)
        $metric_count = 1;
        foreach ($inputs as $key => $value) {
            if ($metric_count > 4) break;

            if (is_array($value)) {
                $display_value = isset($value['amount']) ? number_format($value['amount'], 1) : '';
                if (isset($value['currency'])) {
                    $symbol = array('USD' => '$', 'GBP' => '£', 'EUR' => '€')[$value['currency']] ?? '';
                    $display_value = $symbol . $display_value;
                }
            } else {
                $display_value = is_numeric($value) ? number_format($value, 1) : $value;
            }

            $replacements["{{metric_{$metric_count}_value}}"] = $display_value;
            $replacements["{{metric_{$metric_count}_label}}"] = ucwords(str_replace('_', ' ', $key));
            $metric_count++;
        }

        // Fill remaining metric slots
        for ($i = $metric_count; $i <= 4; $i++) {
            $replacements["{{metric_{$i}_value}}"] = '-';
            $replacements["{{metric_{$i}_label}}"] = '-';
        }

        // Build analysis rows
        $rows = '';
        foreach ($inputs as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));

            if (is_array($value)) {
                $display = isset($value['amount']) ? number_format($value['amount'], 1) : json_encode($value);
                $source = isset($value['estimated']) && $value['estimated'] ? 'Estimated' : 'Disclosed';
            } else {
                $display = is_numeric($value) ? number_format($value, 2) : $value;
                $source = 'Input';
            }

            $rows .= "<tr><td>{$label}</td><td>{$display}</td><td>{$source}</td></tr>";
        }
        $replacements['{{analysis_rows}}'] = $rows;

        // Replace all placeholders
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        // Remove any unreplaced placeholders (clean up)
        $html = preg_replace('/\{\{[a-zA-Z_]+\}\}/', '-', $html);

        return $html;
    }

    /**
     * Format number based on field type
     */
    private function format_number($value, $field) {
        if (!is_numeric($value)) {
            return $value;
        }

        // Detect field type from name
        if (strpos($field, 'multiple') !== false || strpos($field, 'moic') !== false) {
            return number_format($value, 1);
        }
        if (strpos($field, 'irr') !== false || strpos($field, 'rate') !== false || strpos($field, 'margin') !== false || strpos($field, 'growth') !== false) {
            return number_format($value * 100, 1);
        }
        if (strpos($field, 'year') !== false) {
            return intval($value);
        }
        return number_format($value, 1);
    }

    /**
     * Format currency value
     */
    private function format_currency($value, $currency = 'USD') {
        $symbols = array('USD' => '$', 'GBP' => '£', 'EUR' => '€');
        $symbol = $symbols[$currency] ?? '$';

        if ($value >= 1000000000) {
            return $symbol . number_format($value / 1000000000, 1) . 'bn';
        } elseif ($value >= 1000000) {
            return $symbol . number_format($value / 1000000, 1) . 'm';
        } elseif ($value >= 1000) {
            return $symbol . number_format($value / 1000, 1) . 'k';
        }
        return $symbol . number_format($value, 0);
    }

    /**
     * Generate clean filename
     */
    private function generate_filename($model_name, $post_title, $extension) {
        $clean_model = preg_replace('/[^a-zA-Z0-9]+/', '_', $model_name);
        $clean_title = preg_replace('/[^a-zA-Z0-9]+/', '_', substr($post_title, 0, 30));
        $date = date('Y-m-d');

        return "{$clean_model}_{$clean_title}_{$date}.{$extension}";
    }
}
