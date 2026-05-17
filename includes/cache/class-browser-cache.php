<?php

namespace AI_SEO_Captain\Cache;

/**
 * Browser cache headers and .htaccess rules for static assets.
 */
class Browser_Cache
{

    /** @var int Page cache TTL in seconds. */
    private $page_ttl;

    /** @var bool GZIP enabled. */
    private $gzip_enabled;

    /** @var string Marker for .htaccess rules. */
    private const HTACCESS_MARKER = 'AI SEO Captain Cache';

    public function __construct(array $options = array())
    {
        $this->page_ttl     = isset($options['cache_page_ttl']) ? (int) $options['cache_page_ttl'] : 86400;
        $this->gzip_enabled = ! empty($options['cache_gzip_enabled']);
    }

    /**
     * Hook into 'send_headers' for HTML pages.
     */
    public function set_page_headers(): void
    {
        if (is_admin() || is_user_logged_in()) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        header('Cache-Control: public, max-age=' . $this->page_ttl);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $this->page_ttl) . ' GMT');

        if ($this->gzip_enabled) {
            header('Vary: Accept-Encoding');
        }
    }

    /**
     * Generate .htaccess rules for static assets (Apache only).
     */
    public function generate_htaccess_rules(): string
    {
        $rules  = '# BEGIN ' . self::HTACCESS_MARKER . "\n";
        $rules .= '<IfModule mod_expires.c>' . "\n";
        $rules .= '    ExpiresActive On' . "\n";
        $rules .= '    ExpiresByType text/css "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType application/javascript "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType image/jpeg "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType image/png "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType image/gif "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType image/svg+xml "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType image/webp "access plus 1 year"' . "\n";
        $rules .= '    ExpiresByType font/woff2 "access plus 1 year"' . "\n";
        $rules .= '</IfModule>' . "\n";

        if ($this->gzip_enabled) {
            $rules .= '<IfModule mod_deflate.c>' . "\n";
            $rules .= '    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml' . "\n";
            $rules .= '</IfModule>' . "\n";
        }

        $rules .= '# END ' . self::HTACCESS_MARKER . "\n";

        return $rules;
    }

    /**
     * Install rules into .htaccess.
     */
    public function install_htaccess(): bool
    {
        $htaccess_file = ABSPATH . '.htaccess';

        if (! file_exists($htaccess_file) || ! is_writable($htaccess_file)) {
            return false;
        }

        // Remove existing rules first.
        $this->remove_htaccess();

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $contents = file_get_contents($htaccess_file);

        if (false === $contents) {
            return false;
        }

        $rules = $this->generate_htaccess_rules();

        // Insert before WordPress rules.
        $wp_marker = '# BEGIN WordPress';
        $position  = strpos($contents, $wp_marker);

        if (false !== $position) {
            $contents = substr($contents, 0, $position) . $rules . "\n" . substr($contents, $position);
        } else {
            $contents = $rules . "\n" . $contents;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents($htaccess_file, $contents, LOCK_EX);
    }

    /**
     * Remove rules from .htaccess.
     */
    public function remove_htaccess(): bool
    {
        $htaccess_file = ABSPATH . '.htaccess';

        if (! file_exists($htaccess_file) || ! is_writable($htaccess_file)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $contents = file_get_contents($htaccess_file);

        if (false === $contents) {
            return false;
        }

        $pattern = '/# BEGIN ' . preg_quote(self::HTACCESS_MARKER, '/') . '.*?# END ' . preg_quote(self::HTACCESS_MARKER, '/') . "\n?/s";
        $cleaned = preg_replace($pattern, '', $contents);

        if ($cleaned === $contents) {
            return true; // Nothing to remove.
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents($htaccess_file, $cleaned, LOCK_EX);
    }

    /**
     * Check if our .htaccess rules are installed.
     */
    public function is_htaccess_installed(): bool
    {
        $htaccess_file = ABSPATH . '.htaccess';

        if (! file_exists($htaccess_file)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $contents = file_get_contents($htaccess_file);

        return false !== $contents && false !== strpos($contents, '# BEGIN ' . self::HTACCESS_MARKER);
    }
}
