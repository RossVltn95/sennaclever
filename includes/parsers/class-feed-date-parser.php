<?php
/**
 * Feed Date Parser - Handles various date formats from different feeds
 * 
 * @package SennaCareers
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Feed_Date_Parser {
    
    /**
     * Parse any date format to Unix timestamp
     */
    public static function parse($date_string) {
        if (empty($date_string)) {
            return false;
        }
        
        // Clean the date string
        $date_string = trim($date_string);
        
        // Try standard PHP parsing first
        $timestamp = strtotime($date_string);
        if ($timestamp !== false && $timestamp > 0) {
            return $timestamp;
        }
        
        // Try specific formats
        return self::try_specific_formats($date_string);
    }
    
    /**
     * Try specific date formats
     */
    private static function try_specific_formats($date_string) {
        $formats = array(
            // ISO 8601 variants
            'Y-m-d\TH:i:sP',      // 2024-12-04T14:30:00+00:00
            'Y-m-d\TH:i:s\Z',     // 2024-12-04T14:30:00Z
            'Y-m-d\TH:i:s',       // 2024-12-04T14:30:00
            
            // RSS/RFC 822
            'D, d M Y H:i:s O',   // Mon, 04 Dec 2024 14:30:00 +0000
            'D, d M Y H:i:s T',   // Mon, 04 Dec 2024 14:30:00 GMT
            
            // Common formats
            'Y-m-d H:i:s',        // 2024-12-04 14:30:00
            'd/m/Y H:i:s',        // 04/12/2024 14:30:00
            'm/d/Y H:i:s',        // 12/04/2024 14:30:00
            'd-m-Y H:i:s',        // 04-12-2024 14:30:00
            'm-d-Y H:i:s',        // 12-04-2024 14:30:00
            
            // Date only formats
            'Y-m-d',              // 2024-12-04
            'd/m/Y',              // 04/12/2024
            'm/d/Y',              // 12/04/2024
            'd-m-Y',              // 04-12-2024
            'm-d-Y',              // 12-04-2024
            
            // Human readable
            'F j, Y',             // December 4, 2024
            'M j, Y',             // Dec 4, 2024
            'j F Y',              // 4 December 2024
            'j M Y',              // 4 Dec 2024
        );
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                return $date->getTimestamp();
            }
        }
        
        // Try with DateTime directly (handles many formats)
        try {
            $date = new DateTime($date_string);
            return $date->getTimestamp();
        } catch (Exception $e) {
            // Failed to parse
        }
        
        return false;
    }
    
    /**
     * Convert timestamp to human-readable time ago
     */
    public static function time_ago($timestamp) {
        if (!$timestamp) {
            return 'Unknown time';
        }
        
        $current = time();
        $diff = $current - $timestamp;
        
        // Future date
        if ($diff < 0) {
            return 'Upcoming';
        }
        
        // Less than a minute
        if ($diff < 60) {
            return 'Just now';
        }
        
        // Less than an hour
        if ($diff < 3600) {
            $minutes = round($diff / 60);
            return $minutes . ' min' . ($minutes != 1 ? 's' : '') . ' ago';
        }
        
        // Less than a day
        if ($diff < 86400) {
            $hours = round($diff / 3600);
            return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
        }
        
        // Less than a week
        if ($diff < 604800) {
            $days = round($diff / 86400);
            if ($days == 1) {
                return 'Yesterday';
            }
            return $days . ' days ago';
        }
        
        // Less than a month
        if ($diff < 2592000) {
            $weeks = round($diff / 604800);
            return $weeks . ' week' . ($weeks != 1 ? 's' : '') . ' ago';
        }
        
        // Older - show actual date
        return date('M j, Y', $timestamp);
    }
    
    /**
     * Check if date is within specified days
     */
    public static function is_within_days($timestamp, $days = 7) {
        if (!$timestamp) {
            return false;
        }
        
        $max_age = strtotime("-{$days} days");
        return $timestamp >= $max_age;
    }
    
    /**
     * Format timestamp for display
     */
    public static function format_display($timestamp) {
        if (!$timestamp) {
            return '';
        }
        
        $diff = time() - $timestamp;
        
        // Today
        if ($diff < 86400 && date('d', $timestamp) == date('d')) {
            return 'Today ' . date('g:i A', $timestamp);
        }
        
        // Yesterday
        if ($diff < 172800 && date('d', $timestamp) == date('d', strtotime('-1 day'))) {
            return 'Yesterday ' . date('g:i A', $timestamp);
        }
        
        // This week
        if ($diff < 604800) {
            return date('l g:i A', $timestamp);
        }
        
        // Older
        return date('M j, Y', $timestamp);
    }
}