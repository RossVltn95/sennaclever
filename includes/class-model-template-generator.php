<?php
/**
 * Financial Model Template Generator
 *
 * Generates Excel model templates programmatically using PhpSpreadsheet.
 * Templates are created on first use and cached.
 *
 * @package SFFC
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SFFC_Model_Template_Generator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Templates directory
     */
    private $templates_dir;

    /**
     * Color palette
     */
    private $colors = array(
        'navy'       => '0A1628',
        'navy_light' => '1E3A5F',
        'blue'       => '2563EB',
        'cream'      => 'FDF6E3',
        'gray_100'   => 'F8FAFC',
        'gray_200'   => 'E2E8F0',
        'gray_500'   => '64748B',
        'white'      => 'FFFFFF',
        'green'      => '1E6B4A',
        'red'        => '9A3C3C',
        'amber'      => '8A6D35',
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
        $this->templates_dir = SFFC_PLUGIN_DIR . 'templates/models/';
    }

    /**
     * Get or create a model template
     *
     * @param string $model_type The model type (lbo, dcf, merger, etc.)
     * @return string|WP_Error Path to the template file or error
     */
    public function get_template($model_type) {
        $template_path = $this->templates_dir . $model_type . '/master.xlsx';

        // If template exists and is recent, return it
        if (file_exists($template_path)) {
            $config_path = $this->templates_dir . $model_type . '/config.json';
            $config_modified = file_exists($config_path) ? filemtime($config_path) : 0;
            $template_modified = filemtime($template_path);

            // Regenerate if config is newer than template
            if ($template_modified >= $config_modified) {
                return $template_path;
            }
        }

        // Generate the template
        $result = $this->generate_template($model_type);

        if (is_wp_error($result)) {
            return $result;
        }

        return $template_path;
    }

    /**
     * Generate a template based on model type
     *
     * @param string $model_type The model type
     * @return bool|WP_Error True on success or error
     */
    public function generate_template($model_type) {
        $method = 'generate_' . str_replace('-', '_', $model_type) . '_template';

        if (!method_exists($this, $method)) {
            return new WP_Error('invalid_model', "Unknown model type: {$model_type}");
        }

        return $this->$method();
    }

    /**
     * Generate LBO Model Template
     */
    private function generate_lbo_template() {
        $spreadsheet = new Spreadsheet();

        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        // Create sheets
        $this->create_lbo_cover_sheet($spreadsheet);
        $this->create_lbo_inputs_sheet($spreadsheet);
        $this->create_lbo_sources_uses_sheet($spreadsheet);
        $this->create_lbo_debt_schedule_sheet($spreadsheet);
        $this->create_lbo_projections_sheet($spreadsheet);
        $this->create_lbo_returns_sheet($spreadsheet);

        // Set Cover as active sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Save
        $writer = new Xlsx($spreadsheet);
        $path = $this->templates_dir . 'lbo/master.xlsx';

        try {
            $writer->save($path);
            return true;
        } catch (Exception $e) {
            return new WP_Error('save_failed', $e->getMessage());
        }
    }

    /**
     * Create LBO Cover Sheet
     */
    private function create_lbo_cover_sheet($spreadsheet) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Cover');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(20);

        // Header
        $sheet->setCellValue('B2', 'LEVERAGED BUYOUT ANALYSIS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(18)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));

        $sheet->setCellValue('B3', 'Confidential');
        $sheet->getStyle('B3')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['gray_500']));

        // Deal Info Section
        $sheet->setCellValue('B4', 'Target Company');
        $sheet->setCellValue('C4', ''); // Placeholder for target name

        $sheet->setCellValue('B5', 'Buyer / Sponsor');
        $sheet->setCellValue('C5', ''); // Placeholder for buyer name

        $sheet->setCellValue('B6', 'Transaction Date');
        $sheet->setCellValue('C6', ''); // Placeholder for date

        $sheet->setCellValue('B7', 'Deal Type');
        $sheet->setCellValue('C7', 'Leveraged Buyout');

        // Style labels
        $sheet->getStyle('B4:B7')->getFont()->setBold(true);
        $sheet->getStyle('C4:C7')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

        // Key Metrics Section
        $sheet->setCellValue('B9', 'KEY METRICS');
        $sheet->getStyle('B9')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('B10', 'Enterprise Value');
        $sheet->setCellValue('C10', '=Inputs!C6');

        $sheet->setCellValue('B11', 'Entry Multiple');
        $sheet->setCellValue('C11', '=Inputs!C8');

        $sheet->setCellValue('B12', 'LTM EBITDA');
        $sheet->setCellValue('C12', '=Inputs!C10');

        $sheet->setCellValue('B13', 'Equity Contribution');
        $sheet->setCellValue('C13', '=Inputs!C14');

        $sheet->setCellValue('B15', 'RETURNS SUMMARY');
        $sheet->getStyle('B15')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('B16', 'Base Case IRR');
        $sheet->setCellValue('C16', '=Returns!C10');

        $sheet->setCellValue('B17', 'Base Case MOIC');
        $sheet->setCellValue('C17', '=Returns!C12');

        // Format percentages and multiples
        $sheet->getStyle('C13')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('C16')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('C11:C12')->getNumberFormat()->setFormatCode('#,##0.0"x"');
        $sheet->getStyle('C17')->getNumberFormat()->setFormatCode('#,##0.0"x"');

        // MENA Careers branding footer
        $sheet->setCellValue('B25', 'Generated by MENA Careers Intelligence');
        $sheet->getStyle('B25')->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['gray_500']));

        return $sheet;
    }

    /**
     * Create LBO Inputs Sheet
     */
    private function create_lbo_inputs_sheet($spreadsheet) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Inputs');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(25);

        // Header
        $sheet->setCellValue('B2', 'TRANSACTION INPUTS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));

        // Section: Valuation
        $sheet->setCellValue('B4', 'VALUATION');
        $this->style_section_header($sheet, 'B4:F4');

        $sheet->setCellValue('B6', 'Enterprise Value');
        $sheet->setCellValue('C6', 1000); // Default placeholder
        $sheet->setCellValue('D6', 'USD');
        $sheet->setCellValue('F6', 'millions');

        $sheet->setCellValue('B8', 'Entry EV/EBITDA Multiple');
        $sheet->setCellValue('C8', 10.0);
        $sheet->getStyle('C8')->getNumberFormat()->setFormatCode('#,##0.0"x"');

        $sheet->setCellValue('B10', 'LTM EBITDA');
        $sheet->setCellValue('C10', '=C6/C8');
        $sheet->getStyle('C10')->getNumberFormat()->setFormatCode('#,##0.0');

        $sheet->setCellValue('B11', 'LTM Revenue');
        $sheet->setCellValue('C11', 500);
        $sheet->getStyle('C11')->getNumberFormat()->setFormatCode('#,##0.0');

        // Section: Capital Structure
        $sheet->setCellValue('B13', 'CAPITAL STRUCTURE');
        $this->style_section_header($sheet, 'B13:F13');

        $sheet->setCellValue('B14', 'Equity Contribution %');
        $sheet->setCellValue('C14', 0.40);
        $sheet->getStyle('C14')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        $sheet->setCellValue('B15', 'Equity Amount');
        $sheet->setCellValue('C15', '=C6*C14');
        $sheet->getStyle('C15')->getNumberFormat()->setFormatCode('#,##0.0');

        $sheet->setCellValue('B16', 'Senior Debt / EBITDA');
        $sheet->setCellValue('C16', 5.0);
        $sheet->getStyle('C16')->getNumberFormat()->setFormatCode('#,##0.0"x"');

        $sheet->setCellValue('B17', 'Senior Debt Amount');
        $sheet->setCellValue('C17', '=C10*C16');
        $sheet->getStyle('C17')->getNumberFormat()->setFormatCode('#,##0.0');

        $sheet->setCellValue('B18', 'Senior Debt Interest Rate');
        $sheet->setCellValue('C18', 0.08);
        $sheet->getStyle('C18')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        // Section: Exit Assumptions
        $sheet->setCellValue('B20', 'EXIT ASSUMPTIONS');
        $this->style_section_header($sheet, 'B20:F20');

        $sheet->setCellValue('C21', 'Downside');
        $sheet->setCellValue('D21', 'Base');
        $sheet->setCellValue('E21', 'Upside');
        $sheet->getStyle('C21:E21')->getFont()->setBold(true)->setSize(9);

        $sheet->setCellValue('B22', 'Exit Year');
        $sheet->setCellValue('C22', 5);
        $sheet->setCellValue('D22', 5);
        $sheet->setCellValue('E22', 5);

        $sheet->setCellValue('B23', 'Exit EV/EBITDA Multiple');
        $sheet->setCellValue('C23', 8.0);
        $sheet->setCellValue('D23', 10.0);
        $sheet->setCellValue('E23', 12.0);
        $sheet->getStyle('C23:E23')->getNumberFormat()->setFormatCode('#,##0.0"x"');

        // Section: Operating Assumptions
        $sheet->setCellValue('B25', 'OPERATING ASSUMPTIONS');
        $this->style_section_header($sheet, 'B25:F25');

        $sheet->setCellValue('B26', 'Revenue Growth Rate');
        $sheet->setCellValue('C26', 0.05);
        $sheet->getStyle('C26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        $sheet->setCellValue('B27', 'EBITDA Margin');
        $sheet->setCellValue('C27', 0.20);
        $sheet->getStyle('C27')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        $sheet->setCellValue('B28', 'Capex % of Revenue');
        $sheet->setCellValue('C28', 0.03);
        $sheet->getStyle('C28')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        $sheet->setCellValue('B29', 'NWC % of Revenue');
        $sheet->setCellValue('C29', 0.10);
        $sheet->getStyle('C29')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        // Highlight input cells
        $input_cells = array('C6', 'C8', 'C11', 'C14', 'C16', 'C18', 'C22:E23', 'C26:C29');
        foreach ($input_cells as $range) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($this->colors['cream']);
            $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['gray_200']));
        }

        return $sheet;
    }

    /**
     * Create LBO Sources & Uses Sheet
     */
    private function create_lbo_sources_uses_sheet($spreadsheet) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sources & Uses');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(5);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(12);

        // Header
        $sheet->setCellValue('B2', 'SOURCES & USES OF FUNDS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Sources Header
        $sheet->setCellValue('B4', 'SOURCES');
        $sheet->setCellValue('C4', 'Amount');
        $sheet->setCellValue('D4', '%');
        $this->style_section_header($sheet, 'B4:D4');

        // Sources
        $sheet->setCellValue('B5', 'Senior Debt');
        $sheet->setCellValue('C5', '=Inputs!C17');
        $sheet->setCellValue('D5', '=C5/$C$9');

        $sheet->setCellValue('B6', 'Subordinated Debt');
        $sheet->setCellValue('C6', 0);
        $sheet->setCellValue('D6', '=C6/$C$9');

        $sheet->setCellValue('B7', 'Sponsor Equity');
        $sheet->setCellValue('C7', '=Inputs!C15');
        $sheet->setCellValue('D7', '=C7/$C$9');

        $sheet->setCellValue('B8', 'Management Rollover');
        $sheet->setCellValue('C8', 0);
        $sheet->setCellValue('D8', '=C8/$C$9');

        $sheet->setCellValue('B9', 'Total Sources');
        $sheet->setCellValue('C9', '=SUM(C5:C8)');
        $sheet->setCellValue('D9', '=SUM(D5:D8)');
        $sheet->getStyle('B9:D9')->getFont()->setBold(true);
        $sheet->getStyle('B9:D9')->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

        // Uses Header
        $sheet->setCellValue('F4', 'USES');
        $sheet->setCellValue('G4', 'Amount');
        $sheet->setCellValue('H4', '%');
        $this->style_section_header($sheet, 'F4:H4');

        // Uses
        $sheet->setCellValue('F5', 'Purchase Equity');
        $sheet->setCellValue('G5', '=Inputs!C6');
        $sheet->setCellValue('H5', '=G5/$G$9');

        $sheet->setCellValue('F6', 'Refinance Existing Debt');
        $sheet->setCellValue('G6', 0);
        $sheet->setCellValue('H6', '=G6/$G$9');

        $sheet->setCellValue('F7', 'Transaction Fees');
        $sheet->setCellValue('G7', '=Inputs!C6*0.02');
        $sheet->setCellValue('H7', '=G7/$G$9');

        $sheet->setCellValue('F8', 'Cash to Balance Sheet');
        $sheet->setCellValue('G8', 0);
        $sheet->setCellValue('H8', '=G8/$G$9');

        $sheet->setCellValue('F9', 'Total Uses');
        $sheet->setCellValue('G9', '=SUM(G5:G8)');
        $sheet->setCellValue('H9', '=SUM(H5:H8)');
        $sheet->getStyle('F9:H9')->getFont()->setBold(true);
        $sheet->getStyle('F9:H9')->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

        // Format numbers
        $sheet->getStyle('C5:C9')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('G5:G9')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('D5:D9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
        $sheet->getStyle('H5:H9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        return $sheet;
    }

    /**
     * Create LBO Debt Schedule Sheet
     */
    private function create_lbo_debt_schedule_sheet($spreadsheet) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Debt Schedule');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(25);

        for ($i = 0; $i <= 5; $i++) {
            $col = chr(67 + $i); // C, D, E, F, G, H
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        // Header
        $sheet->setCellValue('B2', 'DEBT SCHEDULE');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Year headers
        $sheet->setCellValue('B4', 'Senior Debt');
        $sheet->setCellValue('C4', 'Year 0');
        $sheet->setCellValue('D4', 'Year 1');
        $sheet->setCellValue('E4', 'Year 2');
        $sheet->setCellValue('F4', 'Year 3');
        $sheet->setCellValue('G4', 'Year 4');
        $sheet->setCellValue('H4', 'Year 5');
        $this->style_section_header($sheet, 'B4:H4');

        // Debt rows
        $sheet->setCellValue('B5', 'Beginning Balance');
        $sheet->setCellValue('C5', '=Inputs!C17');
        $sheet->setCellValue('D5', '=C7');
        $sheet->setCellValue('E5', '=D7');
        $sheet->setCellValue('F5', '=E7');
        $sheet->setCellValue('G5', '=F7');
        $sheet->setCellValue('H5', '=G7');

        $sheet->setCellValue('B6', 'Mandatory Repayment');
        $sheet->setCellValue('C6', 0);
        $sheet->setCellValue('D6', '=-D5*0.05');
        $sheet->setCellValue('E6', '=-E5*0.05');
        $sheet->setCellValue('F6', '=-F5*0.05');
        $sheet->setCellValue('G6', '=-G5*0.05');
        $sheet->setCellValue('H6', '=-H5*0.05');

        $sheet->setCellValue('B7', 'Ending Balance');
        $sheet->setCellValue('C7', '=C5+C6');
        $sheet->setCellValue('D7', '=D5+D6');
        $sheet->setCellValue('E7', '=E5+E6');
        $sheet->setCellValue('F7', '=F5+F6');
        $sheet->setCellValue('G7', '=G5+G6');
        $sheet->setCellValue('H7', '=H5+H6');
        $sheet->getStyle('B7:H7')->getFont()->setBold(true);

        $sheet->setCellValue('B9', 'Interest Rate');
        $sheet->setCellValue('C9', '=Inputs!C18');
        $sheet->getStyle('C9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        $sheet->setCellValue('B10', 'Interest Expense');
        $sheet->setCellValue('C10', 0);
        $sheet->setCellValue('D10', '=-(C5+D5)/2*$C$9');
        $sheet->setCellValue('E10', '=-(D5+E5)/2*$C$9');
        $sheet->setCellValue('F10', '=-(E5+F5)/2*$C$9');
        $sheet->setCellValue('G10', '=-(F5+G5)/2*$C$9');
        $sheet->setCellValue('H10', '=-(G5+H5)/2*$C$9');

        // Format numbers
        $sheet->getStyle('C5:H7')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('C10:H10')->getNumberFormat()->setFormatCode('#,##0.0');

        return $sheet;
    }

    /**
     * Create LBO Projections Sheet
     */
    private function create_lbo_projections_sheet($spreadsheet) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Projections');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(25);

        for ($i = 0; $i <= 5; $i++) {
            $col = chr(67 + $i);
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        // Header
        $sheet->setCellValue('B2', 'FINANCIAL PROJECTIONS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Year headers
        $sheet->setCellValue('B4', '($ millions)');
        $sheet->setCellValue('C4', 'Year 0');
        $sheet->setCellValue('D4', 'Year 1');
        $sheet->setCellValue('E4', 'Year 2');
        $sheet->setCellValue('F4', 'Year 3');
        $sheet->setCellValue('G4', 'Year 4');
        $sheet->setCellValue('H4', 'Year 5');
        $this->style_section_header($sheet, 'B4:H4');

        // Revenue
        $sheet->setCellValue('B6', 'Revenue');
        $sheet->setCellValue('C6', '=Inputs!C11');
        $sheet->setCellValue('D6', '=C6*(1+Inputs!$C$26)');
        $sheet->setCellValue('E6', '=D6*(1+Inputs!$C$26)');
        $sheet->setCellValue('F6', '=E6*(1+Inputs!$C$26)');
        $sheet->setCellValue('G6', '=F6*(1+Inputs!$C$26)');
        $sheet->setCellValue('H6', '=G6*(1+Inputs!$C$26)');

        // EBITDA
        $sheet->setCellValue('B7', 'EBITDA');
        $sheet->setCellValue('C7', '=C6*Inputs!$C$27');
        $sheet->setCellValue('D7', '=D6*Inputs!$C$27');
        $sheet->setCellValue('E7', '=E6*Inputs!$C$27');
        $sheet->setCellValue('F7', '=F6*Inputs!$C$27');
        $sheet->setCellValue('G7', '=G6*Inputs!$C$27');
        $sheet->setCellValue('H7', '=H6*Inputs!$C$27');
        $sheet->getStyle('B7:H7')->getFont()->setBold(true);

        // EBITDA Margin
        $sheet->setCellValue('B8', 'EBITDA Margin');
        $sheet->setCellValue('C8', '=C7/C6');
        $sheet->setCellValue('D8', '=D7/D6');
        $sheet->setCellValue('E8', '=E7/E6');
        $sheet->setCellValue('F8', '=F7/F6');
        $sheet->setCellValue('G8', '=G7/G6');
        $sheet->setCellValue('H8', '=H7/H6');
        $sheet->getStyle('C8:H8')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

        // D&A (placeholder)
        $sheet->setCellValue('B10', 'D&A');
        $sheet->setCellValue('C10', '=-C6*0.03');
        $sheet->setCellValue('D10', '=-D6*0.03');
        $sheet->setCellValue('E10', '=-E6*0.03');
        $sheet->setCellValue('F10', '=-F6*0.03');
        $sheet->setCellValue('G10', '=-G6*0.03');
        $sheet->setCellValue('H10', '=-H6*0.03');

        // EBIT
        $sheet->setCellValue('B11', 'EBIT');
        $sheet->setCellValue('C11', '=C7+C10');
        $sheet->setCellValue('D11', '=D7+D10');
        $sheet->setCellValue('E11', '=E7+E10');
        $sheet->setCellValue('F11', '=F7+F10');
        $sheet->setCellValue('G11', '=G7+G10');
        $sheet->setCellValue('H11', '=H7+H10');

        // Interest
        $sheet->setCellValue('B12', 'Interest Expense');
        $sheet->setCellValue('C12', '=\'Debt Schedule\'!C10');
        $sheet->setCellValue('D12', '=\'Debt Schedule\'!D10');
        $sheet->setCellValue('E12', '=\'Debt Schedule\'!E10');
        $sheet->setCellValue('F12', '=\'Debt Schedule\'!F10');
        $sheet->setCellValue('G12', '=\'Debt Schedule\'!G10');
        $sheet->setCellValue('H12', '=\'Debt Schedule\'!H10');

        // EBT
        $sheet->setCellValue('B13', 'EBT');
        $sheet->setCellValue('C13', '=C11+C12');
        $sheet->setCellValue('D13', '=D11+D12');
        $sheet->setCellValue('E13', '=E11+E12');
        $sheet->setCellValue('F13', '=F11+F12');
        $sheet->setCellValue('G13', '=G11+G12');
        $sheet->setCellValue('H13', '=H11+H12');

        // Taxes
        $sheet->setCellValue('B14', 'Taxes (25%)');
        $sheet->setCellValue('C14', '=-MAX(C13,0)*0.25');
        $sheet->setCellValue('D14', '=-MAX(D13,0)*0.25');
        $sheet->setCellValue('E14', '=-MAX(E13,0)*0.25');
        $sheet->setCellValue('F14', '=-MAX(F13,0)*0.25');
        $sheet->setCellValue('G14', '=-MAX(G13,0)*0.25');
        $sheet->setCellValue('H14', '=-MAX(H13,0)*0.25');

        // Net Income
        $sheet->setCellValue('B15', 'Net Income');
        $sheet->setCellValue('C15', '=C13+C14');
        $sheet->setCellValue('D15', '=D13+D14');
        $sheet->setCellValue('E15', '=E13+E14');
        $sheet->setCellValue('F15', '=F13+F14');
        $sheet->setCellValue('G15', '=G13+G14');
        $sheet->setCellValue('H15', '=H13+H14');
        $sheet->getStyle('B15:H15')->getFont()->setBold(true);
        $sheet->getStyle('B15:H15')->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

        // Free Cash Flow Section
        $sheet->setCellValue('B17', 'FREE CASH FLOW');
        $this->style_section_header($sheet, 'B17:H17');

        $sheet->setCellValue('B18', 'EBITDA');
        $sheet->setCellValue('C18', '=C7');
        $sheet->setCellValue('D18', '=D7');
        $sheet->setCellValue('E18', '=E7');
        $sheet->setCellValue('F18', '=F7');
        $sheet->setCellValue('G18', '=G7');
        $sheet->setCellValue('H18', '=H7');

        $sheet->setCellValue('B19', 'Less: Cash Taxes');
        $sheet->setCellValue('C19', '=C14');
        $sheet->setCellValue('D19', '=D14');
        $sheet->setCellValue('E19', '=E14');
        $sheet->setCellValue('F19', '=F14');
        $sheet->setCellValue('G19', '=G14');
        $sheet->setCellValue('H19', '=H14');

        $sheet->setCellValue('B20', 'Less: Capex');
        $sheet->setCellValue('C20', '=-C6*Inputs!$C$28');
        $sheet->setCellValue('D20', '=-D6*Inputs!$C$28');
        $sheet->setCellValue('E20', '=-E6*Inputs!$C$28');
        $sheet->setCellValue('F20', '=-F6*Inputs!$C$28');
        $sheet->setCellValue('G20', '=-G6*Inputs!$C$28');
        $sheet->setCellValue('H20', '=-H6*Inputs!$C$28');

        $sheet->setCellValue('B21', 'Less: Change in NWC');
        $sheet->setCellValue('C21', 0);
        $sheet->setCellValue('D21', '=-(D6-C6)*Inputs!$C$29');
        $sheet->setCellValue('E21', '=-(E6-D6)*Inputs!$C$29');
        $sheet->setCellValue('F21', '=-(F6-E6)*Inputs!$C$29');
        $sheet->setCellValue('G21', '=-(G6-F6)*Inputs!$C$29');
        $sheet->setCellValue('H21', '=-(H6-G6)*Inputs!$C$29');

        $sheet->setCellValue('B22', 'Unlevered FCF');
        $sheet->setCellValue('C22', '=SUM(C18:C21)');
        $sheet->setCellValue('D22', '=SUM(D18:D21)');
        $sheet->setCellValue('E22', '=SUM(E18:E21)');
        $sheet->setCellValue('F22', '=SUM(F18:F21)');
        $sheet->setCellValue('G22', '=SUM(G18:G21)');
        $sheet->setCellValue('H22', '=SUM(H18:H21)');
        $sheet->getStyle('B22:H22')->getFont()->setBold(true);
        $sheet->getStyle('B22:H22')->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

        // Format all numbers
        $sheet->getStyle('C6:H7')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('C10:H15')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('C18:H22')->getNumberFormat()->setFormatCode('#,##0.0');

        return $sheet;
    }

    /**
     * Create LBO Returns Sheet
     */
    private function create_lbo_returns_sheet($spreadsheet) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Returns');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        // Header
        $sheet->setCellValue('B2', 'RETURNS ANALYSIS');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14);

        // Scenario headers
        $sheet->setCellValue('C4', 'Downside');
        $sheet->setCellValue('D4', 'Base');
        $sheet->setCellValue('E4', 'Upside');
        $this->style_section_header($sheet, 'B4:E4');

        // Entry Assumptions
        $sheet->setCellValue('B5', 'Entry EV');
        $sheet->setCellValue('C5', '=Inputs!C6');
        $sheet->setCellValue('D5', '=Inputs!C6');
        $sheet->setCellValue('E5', '=Inputs!C6');

        $sheet->setCellValue('B6', 'Entry Equity');
        $sheet->setCellValue('C6', '=Inputs!C15');
        $sheet->setCellValue('D6', '=Inputs!C15');
        $sheet->setCellValue('E6', '=Inputs!C15');

        // Exit Assumptions
        $sheet->setCellValue('B7', 'Exit Year');
        $sheet->setCellValue('C7', '=Inputs!C22');
        $sheet->setCellValue('D7', '=Inputs!D22');
        $sheet->setCellValue('E7', '=Inputs!E22');

        $sheet->setCellValue('B8', 'Exit EBITDA');
        $sheet->setCellValue('C8', '=Projections!H7');
        $sheet->setCellValue('D8', '=Projections!H7');
        $sheet->setCellValue('E8', '=Projections!H7');

        $sheet->setCellValue('B9', 'Exit Multiple');
        $sheet->setCellValue('C9', '=Inputs!C23');
        $sheet->setCellValue('D9', '=Inputs!D23');
        $sheet->setCellValue('E9', '=Inputs!E23');
        $sheet->getStyle('C9:E9')->getNumberFormat()->setFormatCode('#,##0.0"x"');

        $sheet->setCellValue('B10', 'Exit EV');
        $sheet->setCellValue('C10', '=C8*C9');
        $sheet->setCellValue('D10', '=D8*D9');
        $sheet->setCellValue('E10', '=E8*E9');

        $sheet->setCellValue('B11', 'Less: Net Debt at Exit');
        $sheet->setCellValue('C11', '=-\'Debt Schedule\'!H7');
        $sheet->setCellValue('D11', '=-\'Debt Schedule\'!H7');
        $sheet->setCellValue('E11', '=-\'Debt Schedule\'!H7');

        $sheet->setCellValue('B12', 'Exit Equity Value');
        $sheet->setCellValue('C12', '=C10+C11');
        $sheet->setCellValue('D12', '=D10+D11');
        $sheet->setCellValue('E12', '=E10+E11');
        $sheet->getStyle('B12:E12')->getFont()->setBold(true);

        // Returns
        $sheet->setCellValue('B14', 'RETURNS');
        $this->style_section_header($sheet, 'B14:E14');

        $sheet->setCellValue('B15', 'IRR');
        $sheet->setCellValue('C15', '=IFERROR((C12/C6)^(1/C7)-1,0)');
        $sheet->setCellValue('D15', '=IFERROR((D12/D6)^(1/D7)-1,0)');
        $sheet->setCellValue('E15', '=IFERROR((E12/E6)^(1/E7)-1,0)');
        $sheet->getStyle('C15:E15')->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('B15:E15')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('B16', 'MOIC');
        $sheet->setCellValue('C16', '=IFERROR(C12/C6,0)');
        $sheet->setCellValue('D16', '=IFERROR(D12/D6,0)');
        $sheet->setCellValue('E16', '=IFERROR(E12/E6,0)');
        $sheet->getStyle('C16:E16')->getNumberFormat()->setFormatCode('#,##0.0"x"');
        $sheet->getStyle('B16:E16')->getFont()->setBold(true)->setSize(12);

        // Color code returns
        $sheet->getStyle('C15:C16')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['red']));
        $sheet->getStyle('D15:D16')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['navy']));
        $sheet->getStyle('E15:E16')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['green']));

        // Format numbers
        $sheet->getStyle('C5:E6')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('C8:E8')->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('C10:E12')->getNumberFormat()->setFormatCode('#,##0.0');

        return $sheet;
    }

    /**
     * Style a section header
     */
    private function style_section_header($sheet, $range) {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($this->colors['navy']);
        $sheet->getStyle($range)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($this->colors['white']))->setBold(true);
    }

    /**
     * Generate all Tier 1 templates
     */
    public function generate_all_tier1_templates() {
        $results = array();
        $tier1_models = array('lbo', 'merger', 'dcf', 'three-statement', 'commercial-re');

        foreach ($tier1_models as $model) {
            $result = $this->generate_template($model);
            $results[$model] = is_wp_error($result) ? $result->get_error_message() : 'success';
        }

        return $results;
    }
}
