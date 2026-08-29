<?php

/**
 * Link Content Extractor - Fetches and extracts content from article URLs
 * 
 * @package SennaCareers
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SFFC_Link_Content_Extractor
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache duration in seconds (1 hour)
     */
    private $cache_duration = 3600;

    /**
     * Maximum content length
     */
    private $max_content_length = 500;

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
     * Extract content from URL
     * 
     * @param string $url Article URL
     * @param array $metadata Existing metadata
     * @return array Enhanced metadata with content
     */
    public function extract_content($url, $metadata = array())
    {
        // Check cache first
        $cache_key = 'sffc_link_content_' . md5($url);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return array_merge($metadata, $cached);
        }

        // Fetch the page
        $response = $this->fetch_url($url);

        if (!$response['success']) {
            return $metadata;
        }

        $html = $response['content'];

        // Extract content based on common patterns
        $extracted = array(
            'full_title' => $this->extract_title($html),
            'description' => $this->extract_description($html),
            'image' => $this->extract_image($html, $url),
            'content' => $this->extract_article_content($html),
            'author' => $this->extract_author($html),
            'published_date' => $this->extract_publish_date($html),
            'keywords' => $this->extract_keywords($html)
        );

        // Remove empty values
        $extracted = array_filter($extracted);

        // Cache the extracted content
        if (!empty($extracted)) {
            set_transient($cache_key, $extracted, $this->cache_duration);
        }

        return array_merge($metadata, $extracted);
    }

    /**
     * Fetch URL content
     * 
     * @param string $url
     * @return array
     */
    private function fetch_url($url)
    {
        $args = array(
            'timeout' => 10,
            'redirection' => 3,
            'user-agent' => 'Mozilla/5.0 (compatible; SennaCareers/3.0; +https://joinsenna.com)',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-US,en;q=0.9'
            )
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return array(
                'success' => false,
                'error' => 'HTTP ' . $code
            );
        }

        $content = wp_remote_retrieve_body($response);

        return array(
            'success' => true,
            'content' => $content
        );
    }

    /**
     * Extract title from HTML
     * 
     * @param string $html
     * @return string
     */
    private function extract_title($html)
    {
        // Try Open Graph first
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try Twitter Card
        if (preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try regular title tag
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Remove site name if present
            $title = preg_replace('/\s*[\|\-]\s*[^|\-]+$/', '', $title);
            return trim($title);
        }

        return '';
    }

    /**
     * Extract description from HTML
     * 
     * @param string $html
     * @return string
     */
    private function extract_description($html)
    {
        // Try Open Graph
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try Twitter description
        if (preg_match('/<meta\s+name=["\']twitter:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * Extract main image from HTML
     * 
     * @param string $html
     * @param string $base_url
     * @return string
     */
    private function extract_image($html, $base_url)
    {
        // Try Open Graph image
        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return $this->make_absolute_url($matches[1], $base_url);
        }

        // Try Twitter image
        if (preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return $this->make_absolute_url($matches[1], $base_url);
        }

        // Try to find first article image
        if (preg_match('/<article[^>]*>.*?<img[^>]+src=["\'](.*?)["\']/is', $html, $matches)) {
            return $this->make_absolute_url($matches[1], $base_url);
        }

        return '';
    }

    /**
     * Extract article content
     * 
     * @param string $html
     * @return string
     */
    private function extract_article_content($html)
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/is', '', $html);

        // Try to find article content
        $content = '';

        // Look for article tag
        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            $content = $matches[1];
        }
        // Look for main content divs
        elseif (preg_match('/<div[^>]*(?:class|id)=["\'][^"\']*(?:content|article|post|entry)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $content = $matches[1];
        }

        if ($content) {
            // Extract text from first few paragraphs
            preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $content, $paragraphs);

            if (!empty($paragraphs[1])) {
                $text = '';
                foreach (array_slice($paragraphs[1], 0, 3) as $para) {
                    $para = strip_tags($para);
                    $para = html_entity_decode($para, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $para = trim(preg_replace('/\s+/', ' ', $para));
                    if (strlen($para) > 50) {
                        $text .= $para . ' ';
                        if (strlen($text) > $this->max_content_length) {
                            break;
                        }
                    }
                }

                return substr(trim($text), 0, $this->max_content_length);
            }
        }

        return '';
    }

    /**
     * Extract author from HTML
     * 
     * @param string $html
     * @return string
     */
    private function extract_author($html)
    {
        // Try Open Graph
        if (preg_match('/<meta\s+property=["\'](?:og:)?article:author["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try schema.org
        if (preg_match('/(?:"author":\s*{[^}]*"name":\s*"([^"]+)")/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try byline
        if (preg_match('/<(?:span|div)[^>]*class=["\'][^"\']*byline[^"\']*["\'][^>]*>.*?(?:by\s+)?([^<]+)</i', $html, $matches)) {
            $author = strip_tags($matches[1]);
            $author = html_entity_decode($author, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim(preg_replace('/^by\s+/i', '', $author));
        }

        return '';
    }

    /**
     * Extract publish date from HTML
     * 
     * @param string $html
     * @return string
     */
    private function extract_publish_date($html)
    {
        // Try Open Graph
        if (preg_match('/<meta\s+property=["\']article:published_time["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        // Try schema.org
        if (preg_match('/(?:"datePublished":\s*"([^"]+)")/i', $html, $matches)) {
            return $matches[1];
        }

        // Try time tag
        if (preg_match('/<time[^>]*datetime=["\'](.*?)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extract keywords from HTML
     * 
     * @param string $html
     * @return array
     */
    private function extract_keywords($html)
    {
        $keywords = array();

        // Try meta keywords
        if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $keywords = array_map('trim', explode(',', $matches[1]));
        }

        // Try article tags
        if (preg_match('/<meta\s+property=["\']article:tag["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $keywords[] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return array_slice($keywords, 0, 5);
    }

    /**
     * Make URL absolute
     * 
     * @param string $url
     * @param string $base
     * @return string
     */
    private function make_absolute_url($url, $base)
    {
        if (empty($url)) {
            return '';
        }

        // Already absolute
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // Protocol relative
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        // Absolute path
        if (strpos($url, '/') === 0) {
            $parts = parse_url($base);
            return $parts['scheme'] . '://' . $parts['host'] . $url;
        }

        // Relative path
        return rtrim($base, '/') . '/' . $url;
    }

    /**
     * Batch extract content for multiple URLs
     * 
     * @param array $items Feed items with URLs
     * @return array Enhanced items
     */
    public function batch_extract($items)
    {
        foreach ($items as &$item) {
            // Only process items without description or with short descriptions
            if (empty($item['description']) || strlen($item['description']) < 50) {
                if (!empty($item['link'])) {
                    $enhanced = $this->extract_content($item['link'], $item);
                    $item = array_merge($item, $enhanced);
                }
            }
        }

        return $items;
    }
}

// Initialize
SFFC_Link_Content_Extractor::get_instance();
