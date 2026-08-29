<?php

/**
 * Document Parser Service
 * 
 * Handles server-side text extraction from PDF, DOCX, and DOC files
 * 
 * @package MENA Careers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Document_Parser
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Track vendor autoload status
     */
    private $vendor_loaded = false;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor ensures vendor libraries are available when possible
     */
    private function __construct()
    {
        $this->ensure_vendor_loaded();
    }

    /**
     * Extract text from PDF file
     */
    public function extract_pdf_text($filepath, $mode = 'full')
    {
        try {
            $mode = strtolower(trim((string) $mode));
            $text = '';

            if ($mode === 'fast') {
                if ($this->is_pdftotext_available()) {
                    $text = $this->extract_pdf_with_pdftotext_pages($filepath, 1, 1);
                    if (!empty($text)) {
                        return $this->finalize_text($text);
                    }
                }

                $text = $this->extract_pdf_with_smalot($filepath);
                if (!empty($text)) {
                    return $this->finalize_text($text);
                }

                $text = $this->extract_pdf_with_php_library($filepath);
                if (!empty($text)) {
                    return $this->finalize_text($text);
                }

                $text = $this->extract_pdf_metadata($filepath);
                if (!empty($text)) {
                    return $this->finalize_text($text);
                }

                $text = $this->extract_pdf_basic($filepath);
                return $this->finalize_text($text);
            }

            // Method 1: Try using pdftotext command if available
            if ($this->is_pdftotext_available()) {
                $text = $this->extract_pdf_with_pdftotext($filepath);
                if (!empty($text)) {
                    return $this->finalize_text($text);
                }
            }

            // Method 2: Try Smalot PDF Parser library (from vendor)
            $text = $this->extract_pdf_with_smalot($filepath);
            if (!empty($text)) {
                return $this->finalize_text($text);
            }

            // Method 3: Extract metadata and basic text (works for compressed PDFs)
            $text = $this->extract_pdf_metadata($filepath);
            if (!empty($text)) {
                return $this->finalize_text($text);
            }

            // Method 4: Use PHP PDF parser library
            $text = $this->extract_pdf_with_php_library($filepath);
            if (!empty($text)) {
                return $this->finalize_text($text);
            }

            // Method 5: Basic regex extraction (last resort)
            $text = $this->extract_pdf_basic($filepath);

            return $this->finalize_text($text);
        } catch (\Throwable $e) {
            error_log('Document Parser PDF extraction fatal: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from DOCX file
     */
    public function extract_docx_text($filepath)
    {
        $text = '';

        // Method 1: Try PhpWord library from vendor
        $text = $this->extract_docx_with_phpword($filepath);
        if (!empty($text)) {
            return $this->finalize_text($text);
        }

        // Method 2: DOCX files are essentially ZIP archives
        if (!class_exists('ZipArchive')) {
            error_log('ZipArchive class not available for DOCX extraction');
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) === TRUE) {
            // Read the main document content
            $xml = $zip->getFromName('word/document.xml');
            if ($xml) {
                // Remove XML tags and extract text
                $text = $this->extract_text_from_xml($xml);
            }

            // Also check for headers and footers
            $header = $zip->getFromName('word/header1.xml');
            if ($header) {
                $text = $this->extract_text_from_xml($header) . "\n" . $text;
            }

            $footer = $zip->getFromName('word/footer1.xml');
            if ($footer) {
                $text .= "\n" . $this->extract_text_from_xml($footer);
            }

            $zip->close();
        } else {
            error_log('Failed to open DOCX file: ' . $filepath);
        }

        return $this->finalize_text($text);
    }

    /**
     * Extract text from DOC file (legacy format)
     */
    public function extract_doc_text($filepath)
    {
        // DOC format is more complex, try different methods

        // Method 1: Try using antiword command if available
        if ($this->is_antiword_available()) {
            $text = $this->extract_doc_with_antiword($filepath);
            if (!empty($text)) {
                return $this->finalize_text($text);
            }
        }

        // Method 2: Try using catdoc command if available
        if ($this->is_catdoc_available()) {
            $text = $this->extract_doc_with_catdoc($filepath);
            if (!empty($text)) {
                return $this->finalize_text($text);
            }
        }

        // Method 3: Basic binary extraction (very limited)
        $text = $this->extract_doc_basic($filepath);

        return $this->finalize_text($text);
    }

    /**
     * Extract DOCX text with PhpWord library
     */
    private function extract_docx_with_phpword($filepath)
    {
        try {
            if (!$this->ensure_vendor_loaded()) {
                return '';
            }

            // Check if the class exists
            if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
                return '';
            }

            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filepath);
            $text = '';

            // Extract text from all sections
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif (method_exists($element, 'getElements')) {
                        // Handle nested elements (like paragraphs containing text runs)
                        foreach ($element->getElements() as $subelement) {
                            if (method_exists($subelement, 'getText')) {
                                $text .= $subelement->getText() . ' ';
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            return $this->finalize_text($text);
        } catch (\Throwable $e) {
            error_log('PhpWord extraction error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text from XML (for DOCX)
     */
    private function extract_text_from_xml($xml)
    {
        // Remove XML tags but preserve structure
        $xml = str_replace('</w:p>', "\n", $xml); // Paragraph breaks
        $xml = str_replace('</w:br/>', "\n", $xml); // Line breaks
        $xml = str_replace('<w:tab/>', "\t", $xml); // Tabs

        // Strip all other XML tags
        $text = strip_tags($xml);

        // Clean up
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $text = preg_replace('/\n\s+/', "\n", $text); // Clean line starts
        $text = trim($text);

        return $text;
    }

    /**
     * Check if pdftotext is available
     */
    private function is_pdftotext_available()
    {
        $output = [];
        $return_var = 0;
        exec('which pdftotext 2>/dev/null', $output, $return_var);
        return $return_var === 0;
    }

    /**
     * Extract PDF with pdftotext command
     */
    private function extract_pdf_with_pdftotext($filepath)
    {
        $output = [];
        $return_var = 0;
        $temp_file = sys_get_temp_dir() . '/pdf_extract_' . uniqid() . '.txt';

        // Execute pdftotext
        // Prefer streaming output for immediate parsing
        $streamed = $this->run_pdftotext_stdout($filepath, ['-layout', '-enc', 'UTF-8']);
        if (!empty($streamed)) {
            return $streamed;
        }

        // Fall back to temporary file approach if streaming fails
        exec($this->build_command('pdftotext -layout', $filepath, $temp_file) . ' 2>&1', $output, $return_var);

        if ($return_var === 0 && file_exists($temp_file)) {
            $text = file_get_contents($temp_file);
            unlink($temp_file);
            return $text;
        }

        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        // Final attempt with raw flag for troublesome layouts
        $raw = $this->run_pdftotext_stdout($filepath, ['-raw', '-enc', 'UTF-8']);
        return $raw ?: '';
    }

    private function extract_pdf_with_pdftotext_pages($filepath, $first_page, $last_page)
    {
        $flags = [
            '-f', (string) max(1, (int) $first_page),
            '-l', (string) max((int) $first_page, (int) $last_page),
            '-layout',
            '-enc', 'UTF-8'
        ];
        $streamed = $this->run_pdftotext_stdout($filepath, $flags);
        if (!empty($streamed)) {
            return $streamed;
        }

        $raw_flags = [
            '-f', (string) max(1, (int) $first_page),
            '-l', (string) max((int) $first_page, (int) $last_page),
            '-raw',
            '-enc', 'UTF-8'
        ];

        return $this->run_pdftotext_stdout($filepath, $raw_flags);
    }

    /**
     * Extract PDF with Smalot PDF Parser library
     */
    private function extract_pdf_with_smalot($filepath)
    {
        try {
            // Check if autoloader exists
            $this->ensure_vendor_loaded();
            // Check if the class exists
            if (!class_exists('\Smalot\PdfParser\Parser')) {
                return '';
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filepath);
            $text = $pdf->getText();

            return $text;
        } catch (\Throwable $e) {
            error_log('Smalot PDF Parser error: ' . $e->getMessage());
            // Return empty so it falls back to other methods
            return '';
        }
    }

    /**
     * Extract PDF metadata and basic text (improved method)
     */
    private function extract_pdf_metadata($filepath)
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }

        $extractedText = '';

        // Extract metadata fields
        $metadataPatterns = [
            'title' => '/\/Title\s*\(\s*([^)]+)\s*\)/',
            'author' => '/\/Author\s*\(\s*([^)]+)\s*\)/',
            'subject' => '/\/Subject\s*\(\s*([^)]+)\s*\)/',
            'creator' => '/\/Creator\s*\(\s*([^)]+)\s*\)/'
        ];

        foreach ($metadataPatterns as $field => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                // Clean up the extracted text (remove extra spaces, decode if needed)
                $cleanText = trim($matches[1]);
                $cleanText = str_replace(['  ', ' - ', '- '], [' ', ' ', ''], $cleanText);
                $extractedText .= $cleanText . ' ';
            }
        }

        // Also try to extract from uncompressed streams
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $btMatches)) {
            foreach ($btMatches[1] as $btContent) {
                // Look for text in Tj operators
                if (preg_match_all('/\((.*?)\)\s*Tj/', $btContent, $tjMatches)) {
                    foreach ($tjMatches[1] as $text) {
                        $extractedText .= $text . ' ';
                    }
                }
            }
        }

        return trim($extractedText);
    }

    /**
     * Extract PDF with PHP library
     */
    private function extract_pdf_with_php_library($filepath)
    {
        // Simple PDF text extraction without external library
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }

        $text = '';

        // Look for text between BT and ET markers (PDF text objects)
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Extract text from Tj and TJ operators
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $match, $text_matches)) {
                    foreach ($text_matches[1] as $text_match) {
                        // Decode PDF string
                        $decoded = $this->decode_pdf_string($text_match);
                        $text .= $decoded . ' ';
                    }
                }

                // Handle TJ arrays
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $match, $array_matches)) {
                    foreach ($array_matches[1] as $array_match) {
                        if (preg_match_all('/\((.*?)\)/', $array_match, $string_matches)) {
                            foreach ($string_matches[1] as $string_match) {
                                $decoded = $this->decode_pdf_string($string_match);
                                $text .= $decoded;
                            }
                        }
                    }
                }
            }
        }

        // Also try to extract from streams
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $stream_matches)) {
            foreach ($stream_matches[1] as $stream) {
                // Try to decompress if FlateDecode
                $decompressed = $this->decode_pdf_stream($stream);
                if (!empty($decompressed)) {
                    if (preg_match_all('/\((.*?)\)\s*Tj/s', $decompressed, $text_matches)) {
                        foreach ($text_matches[1] as $text_match) {
                            $decoded = $this->decode_pdf_string($text_match);
                            $text .= $decoded . ' ';
                        }
                    }
                    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decompressed, $array_matches)) {
                        foreach ($array_matches[1] as $array_match) {
                            if (preg_match_all('/\((.*?)\)/', $array_match, $string_matches)) {
                                foreach ($string_matches[1] as $string_match) {
                                    $decoded = $this->decode_pdf_string($string_match);
                                    $text .= $decoded;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Basic PDF text extraction
     */
    private function extract_pdf_basic($filepath)
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }

        // Very basic extraction - look for readable text
        $text = '';

        // Remove binary content
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $content);

        // Extract visible ASCII text
        if (preg_match_all('/[a-zA-Z0-9\s\.\,\;\:\!\?\@\#\$\%\&\*\(\)\-\_\+\=\[\]\{\}\'\"]{10,}/', $content, $matches)) {
            $text = implode(' ', $matches[0]);
        }

        return $text;
    }

    /**
     * Decode PDF string
     */
    private function decode_pdf_string($string)
    {
        // Handle escape sequences
        $string = str_replace('\\n', "\n", $string);
        $string = str_replace('\\r', "\r", $string);
        $string = str_replace('\\t', "\t", $string);
        $string = str_replace('\\(', '(', $string);
        $string = str_replace('\\)', ')', $string);
        $string = str_replace('\\\\', '\\', $string);

        // Handle octal codes
        $string = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($matches) {
            return chr(octdec($matches[1]));
        }, $string);

        return $string;
    }

    /**
     * Check if antiword is available
     */
    private function is_antiword_available()
    {
        $output = [];
        $return_var = 0;
        exec('which antiword 2>/dev/null', $output, $return_var);
        return $return_var === 0;
    }

    /**
     * Extract DOC with antiword
     */
    private function extract_doc_with_antiword($filepath)
    {
        $output = [];
        $return_var = 0;

        exec($this->build_command('antiword', $filepath) . ' 2>&1', $output, $return_var);

        if ($return_var === 0) {
            return implode("\n", $output);
        }

        return '';
    }

    /**
     * Check if catdoc is available
     */
    private function is_catdoc_available()
    {
        $output = [];
        $return_var = 0;
        exec('which catdoc 2>/dev/null', $output, $return_var);
        return $return_var === 0;
    }

    /**
     * Extract DOC with catdoc
     */
    private function extract_doc_with_catdoc($filepath)
    {
        $output = [];
        $return_var = 0;

        exec($this->build_command('catdoc', $filepath) . ' 2>&1', $output, $return_var);

        if ($return_var === 0) {
            return implode("\n", $output);
        }

        return '';
    }

    /**
     * Basic DOC text extraction
     */
    private function extract_doc_basic($filepath)
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            return '';
        }

        // DOC files have text in various places
        // This is a very basic extraction
        $text = '';

        // Remove null bytes and non-printable characters
        $content = str_replace("\x00", ' ', $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $content);

        // Try to find continuous text blocks
        if (preg_match_all('/[a-zA-Z0-9\s\.\,\;\:\!\?\@\#\$\%\&\*\(\)\-\_\+\=\[\]\{\}\'\"]{20,}/', $content, $matches)) {
            // Filter out likely binary data
            foreach ($matches[0] as $match) {
                // Check if it looks like real text (has spaces and reasonable character distribution)
                if (substr_count($match, ' ') > strlen($match) / 20) {
                    $text .= $match . "\n";
                }
            }
        }

        return $this->finalize_text($text);
    }

    /**
     * Attempt to decode compressed PDF stream data
     */
    private function decode_pdf_stream($stream)
    {
        $stream = ltrim($stream, "\r\n");
        $attempts = [
            'gzdecode',
            'gzuncompress',
            'gzinflate',
            'zlib_decode'
        ];

        foreach ($attempts as $function) {
            if (function_exists($function)) {
                $decoded = @$function($stream);
                if ($decoded !== false && !empty($decoded)) {
                    return $decoded;
                }
            }
        }

        return '';
    }

    /**
     * Clean extracted text
     */
    public function clean_text($text)
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Fix line breaks
        $text = preg_replace('/\n\s+/', "\n", $text);

        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Normalize quotes
        $text = str_replace(["\u201c", "\u201d", "\u2018", "\u2019"], ['"', '"', "'", "'"], $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Normalize text while preserving basic structure
     */
    /**
     * Normalize and clean extracted text while preserving readable structure
     */
    private function finalize_text($text)
    {
        if (!is_string($text) || trim($text) === '') {
            return '';
        }

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove soft hyphens and strange unicode dashes
        $text = str_replace("\xC2\xAD", '', $text); // soft hyphen
        $text = str_replace(["\xE2\x80\x93", "\xE2\x80\x94"], "-", $text); // en dash & em dash to simple dash

        // Join hyphenated line breaks (e.g., "Tur-\nkey" -> "Turkey")
        $text = preg_replace('/-\s*\n\s*/u', '', $text);

        // Remove standalone dashes on lines (those "–" lines you saw)
        // Remove isolated dash lines or blocks (e.g. "– \n\n –" sequences)
        $text = preg_replace('/(\n\s*[-–—]\s*\n)+/u', "\n", $text);
        $text = preg_replace('/^\s*[-–—]\s*$/mu', '', $text);


        // Collapse multiple spaces/tabs
        $text = preg_replace('/[ \t\f\v]+/u', ' ', $text);

        // Replace 3+ consecutive newlines with double newline (paragraphs)
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);

        // Trim spaces at line starts/ends
        $text = preg_replace('/[ \t]+\n/u', "\n", $text);
        $text = preg_replace('/\n[ \t]+/u', "\n", $text);

        // Decode UTF-8 smart quotes to normal quotes
        $text = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99"],
            ['"', '"', "'", "'"],
            $text
        );

        // Clean control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return trim($text);
    }


    /**
     * Ensure composer autoload is available
     */
    private function ensure_vendor_loaded()
    {
        if ($this->vendor_loaded) {
            return true;
        }

        $vendor_autoload = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
        if (function_exists('plugin_dir_path')) {
            $vendor_autoload = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
        }

        if (file_exists($vendor_autoload)) {
            require_once $vendor_autoload;
            $this->vendor_loaded = true;
        }

        return $this->vendor_loaded;
    }

    /**
     * Build a safe shell command with escaped arguments
     */
    private function build_command($base_command, ...$paths)
    {
        $segments = array($base_command);
        foreach ($paths as $path) {
            if ($path === null || $path === '') {
                continue;
            }
            $segments[] = escapeshellarg($path);
        }
        return implode(' ', $segments);
    }

    /**
     * Run pdftotext and capture stdout
     */
    private function run_pdftotext_stdout($filepath, array $flags)
    {
        if (!function_exists('shell_exec')) {
            return '';
        }

        $parts = ['pdftotext'];
        foreach ($flags as $flag) {
            if ($flag === null || $flag === '') {
                continue;
            }
            $parts[] = $flag;
        }
        $parts[] = escapeshellarg($filepath);
        $parts[] = '-';

        $command = implode(' ', $parts) . ' 2>&1';
        $output = shell_exec($command);

        if (empty($output)) {
            return '';
        }

        $normalized = trim($output);
        if ($this->command_failed($normalized)) {
            return '';
        }

        return $normalized;
    }

    /**
     * Detect command failure strings
     */
    private function command_failed($output)
    {
        $failure_signatures = [
            'command not found',
            'No such file or directory',
            'syntax error',
            'error'
        ];

        $lower = strtolower($output);
        foreach ($failure_signatures as $signature) {
            if (strpos($lower, strtolower($signature)) !== false) {
                return true;
            }
        }

        return false;
    }
}

// Initialize
SFFC_Document_Parser::get_instance();
