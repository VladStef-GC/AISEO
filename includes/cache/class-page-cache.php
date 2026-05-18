<?php

namespace AI_SEO_Captain\Cache;

/**
 * File-based full-page HTML cache.
 *
 * Writes rendered HTML to disk and serves it on subsequent requests.
 */
class Page_Cache
{

    /** @var string */
    private $cache_dir;

    /** @var int TTL in seconds. */
    private $ttl;

    /** @var bool */
    private $gzip_enabled;

    /** @var bool */
    private $cache_query_strings;

    /** @var array */
    private $exclude_urls;

    /** @var array */
    private $exclude_cookies;

    /** @var array */
    private $exclude_useragents;

    /** @var bool */
    private $minify_html;

    /** @var bool */
    private $wc_exclude_cart;

    public function __construct(array $options = array())
    {
        $this->cache_dir          = WP_CONTENT_DIR . '/cache/ai-seo-captain/pages/';
        $this->ttl                = isset($options['cache_page_ttl']) ? (int) $options['cache_page_ttl'] : 86400;
        $this->gzip_enabled       = ! empty($options['cache_gzip_enabled']);
        $this->cache_query_strings = ! empty($options['cache_query_string_cache']);
        $this->minify_html        = ! empty($options['cache_minify_html']);
        $this->wc_exclude_cart    = ! isset($options['cache_wc_exclude_cart']) || ! empty($options['cache_wc_exclude_cart']);
        $this->exclude_urls       = array();

        if (! empty($options['cache_exclude_urls'])) {
            $this->exclude_urls = array_filter(array_map('trim', explode("\n", $options['cache_exclude_urls'])));
        }

        $this->exclude_cookies = array();
        if (! empty($options['cache_exclude_cookies'])) {
            $this->exclude_cookies = array_filter(array_map('trim', explode("\n", $options['cache_exclude_cookies'])));
        }

        $this->exclude_useragents = array();
        if (! empty($options['cache_exclude_useragents'])) {
            $this->exclude_useragents = array_filter(array_map('trim', explode("\n", $options['cache_exclude_useragents'])));
        }
    }

    /**
     * Start output buffering on template_redirect (priority 0).
     */
    public function start_capture(): void
    {
        if (! $this->is_cacheable()) {
            return;
        }

        ob_start(array($this, 'end_capture'));
    }

    /**
     * End capture: write HTML to file (callback from ob_start).
     */
    public function end_capture(string $html): string
    {
        if (strlen($html) < 255) {
            return $html;
        }

        // Check for DONOTCACHEPAGE set during rendering.
        if (defined('DONOTCACHEPAGE') && \DONOTCACHEPAGE) {
            return $html;
        }

        $request_uri = $this->get_request_uri();
        $hash        = md5($request_uri);
        $prefix      = substr($hash, 0, 2);
        $dir         = $this->cache_dir . $prefix . '/';

        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        // Optionally minify HTML.
        if ($this->minify_html) {
            $html = $this->minify_html_output($html);
        }

        // Write the HTML cache file.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($dir . $hash . '.html', $html, LOCK_EX);

        // Write metadata file.
        $meta = array(
            'url'       => $request_uri,
            'created'   => time(),
            'ttl'       => $this->ttl,
            'gzip'      => $this->gzip_enabled,
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($dir . $hash . '.meta', serialize($meta), LOCK_EX);

        // Add cache header for debugging.
        if (! headers_sent()) {
            header('X-Cache: MISS');
            header('X-Cache-Engine: AI-SEO-Captain');
        }

        return $html;
    }

    /**
     * Serve cached file if it exists (called from advanced-cache.php, before WP loads).
     *
     * @return bool True if a cached page was served.
     */
    public static function serve_if_cached(string $request_uri, array $config = array()): bool
    {
        $cache_dir = WP_CONTENT_DIR . '/cache/ai-seo-captain/pages/';
        $hash      = md5($request_uri);
        $prefix    = substr($hash, 0, 2);
        $file      = $cache_dir . $prefix . '/' . $hash . '.html';
        $meta_file = $cache_dir . $prefix . '/' . $hash . '.meta';

        if (! file_exists($file)) {
            return false;
        }

        // Check TTL from meta file.
        if (file_exists($meta_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
            $raw_meta = file_get_contents($meta_file);
            if (false !== $raw_meta) {
                $meta = @unserialize($raw_meta, array('allowed_classes' => false));
                if (is_array($meta) && isset($meta['created'], $meta['ttl'])) {
                    if ((time() - $meta['created']) > $meta['ttl']) {
                        // Expired — remove stale files.
                        @unlink($file);
                        @unlink($meta_file);
                        return false;
                    }
                }
            }
        }

        // Serve the cached file.
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Cache: HIT');
        header('X-Cache-Engine: AI-SEO-Captain');

        $ttl = isset($config['ttl']) ? (int) $config['ttl'] : 86400;
        header('Cache-Control: public, max-age=' . $ttl);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $html = file_get_contents($file);

        if (false === $html) {
            return false;
        }

        // GZIP compression if accepted.
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode')) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            echo gzencode($html, 6);
        } else {
            echo $html;
        }

        return true;
    }

    /**
     * Purge: delete a specific cached file by URL.
     */
    public function purge(string $url): void
    {
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (empty($path)) {
            $path = '/';
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        $uri   = $query ? $path . '?' . $query : $path;
        $hash  = md5($uri);
        $prefix = substr($hash, 0, 2);

        $html_file = $this->cache_dir . $prefix . '/' . $hash . '.html';
        $meta_file = $this->cache_dir . $prefix . '/' . $hash . '.meta';

        if (file_exists($html_file)) {
            wp_delete_file($html_file);
        }
        if (file_exists($meta_file)) {
            wp_delete_file($meta_file);
        }
    }

    /**
     * Purge all cached files.
     */
    public function purge_all(): void
    {
        $this->delete_directory_contents($this->cache_dir);
    }

    /**
     * Get cache directory size and file count.
     */
    public function get_stats(): array
    {
        $stats = array(
            'files' => 0,
            'size'  => 0,
        );

        if (! is_dir($this->cache_dir)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 'html' === $file->getExtension()) {
                ++$stats['files'];
                $stats['size'] += $file->getSize();
            }
        }

        return $stats;
    }

    /**
     * Get the cache directory path.
     */
    public function get_cache_dir(): string
    {
        return $this->cache_dir;
    }

    /**
     * Should this request be cached?
     */
    private function is_cacheable(): bool
    {
        // Never cache admin, login, cron, xmlrpc, REST API, admin-ajax.
        if (is_admin() || wp_doing_cron() || wp_doing_ajax()) {
            return false;
        }

        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        // Never cache POST requests.
        if (isset($_SERVER['REQUEST_METHOD']) && 'GET' !== strtoupper($_SERVER['REQUEST_METHOD'])) {
            return false;
        }

        // Never cache logged-in users.
        if (is_user_logged_in()) {
            return false;
        }

        // Never cache 404s.
        if (is_404()) {
            return false;
        }

        // Never cache search results.
        if (is_search()) {
            return false;
        }

        // Check for DONOTCACHEPAGE constant.
        if (defined('DONOTCACHEPAGE') && \DONOTCACHEPAGE) {
            return false;
        }

        // Skip pages with query strings unless configured.
        if (! $this->cache_query_strings && ! empty($_SERVER['QUERY_STRING'])) {
            return false;
        }

        // Check WooCommerce cookies.
        if (isset($_COOKIE['woocommerce_items_in_cart']) || isset($_COOKIE['woocommerce_cart_hash'])) {
            return false;
        }

        // Exclude WooCommerce dynamic pages (cart, checkout, my-account).
        if ($this->wc_exclude_cart) {
            if (function_exists('is_cart') && is_cart()) {
                return false;
            }
            if (function_exists('is_checkout') && is_checkout()) {
                return false;
            }
            if (function_exists('is_account_page') && is_account_page()) {
                return false;
            }
        }

        // Check URL exclusion patterns.
        $request_uri = $this->get_request_uri();
        foreach ($this->exclude_urls as $pattern) {
            if ('' === $pattern) {
                continue;
            }

            if (false !== strpos($request_uri, $pattern)) {
                return false;
            }
        }

        // Check excluded cookie names.
        if (! empty($this->exclude_cookies)) {
            foreach ($this->exclude_cookies as $cookie_name) {
                if (isset($_COOKIE[$cookie_name])) {
                    return false;
                }
            }
        }

        // Check excluded user-agent patterns.
        if (! empty($this->exclude_useragents) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
            foreach ($this->exclude_useragents as $pattern) {
                if ('' !== $pattern && false !== stripos($ua, $pattern)) {
                    return false;
                }
            }
        }

        // Already cached? Serve cached version in output buffer instead.
        $hash   = md5($request_uri);
        $prefix = substr($hash, 0, 2);
        $file   = $this->cache_dir . $prefix . '/' . $hash . '.html';

        if (file_exists($file)) {
            $meta      = null;
            $meta_file = $this->cache_dir . $prefix . '/' . $hash . '.meta';
            if (file_exists($meta_file)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
                $raw = file_get_contents($meta_file);
                $meta = @unserialize($raw, array('allowed_classes' => false));
                if (is_array($meta) && isset($meta['created'], $meta['ttl'])) {
                    if ((time() - $meta['created']) > $meta['ttl']) {
                        // Expired — allow re-capture.
                        return true;
                    }
                }
            }

            // Still valid — serve from cache and exit.
            if (! headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
                header('X-Cache: HIT');
                header('X-Cache-Engine: AI-SEO-Captain');

                $ttl = (is_array($meta) && isset($meta['ttl'])) ? (int) $meta['ttl'] : $this->ttl;
                header('Cache-Control: public, max-age=' . $ttl);
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
            $cached_html = file_get_contents($file);

            if (false === $cached_html) {
                // File became unreadable between checks — let WordPress render normally.
                return true;
            }

            if (
                $this->gzip_enabled
                && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
                && false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')
                && function_exists('gzencode')
            ) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                echo gzencode($cached_html, 6);
            } else {
                echo $cached_html;
            }
            exit;
        }

        return true;
    }

    /**
     * Get normalized request URI.
     */
    private function get_request_uri(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

        if (! $this->cache_query_strings) {
            $uri = strtok($uri, '?');
        }

        return $uri;
    }

    /**
     * Recursively delete directory contents.
     */
    private function delete_directory_contents(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                wp_delete_file($item->getPathname());
            }
        }
    }

    /**
     * Simple HTML minification: collapse whitespace, remove HTML comments.
     */
    private function minify_html_output(string $html): string
    {
        // Preserve <pre>, <script>, <style>, <textarea> contents.
        $protect = array();
        $html = preg_replace_callback('/<(pre|script|style|textarea)\b[^>]*>.*?<\/\1>/is', function ($m) use (&$protect) {
            $key = '<!--AISC_PROTECT_' . count($protect) . '-->';
            $protect[$key] = $m[0];
            return $key;
        }, $html);

        // Remove HTML comments (but keep IE conditional comments and protected blocks).
        $html = preg_replace('/<!--(?!\[if\s|AISC_PROTECT_).*?-->/s', '', $html);

        // Collapse whitespace.
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '> <', $html);

        // Restore protected blocks.
        foreach ($protect as $key => $value) {
            $html = str_replace($key, $value, $html);
        }

        return trim($html);
    }
}
