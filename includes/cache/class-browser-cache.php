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

    /** @var string Directory for .htaccess backups. */
    private $backup_dir;

    /** @var int Maximum number of backups to keep. */
    private const MAX_BACKUPS = 5;

    public function __construct(array $options = array())
    {
        $this->page_ttl     = isset($options['cache_page_ttl']) ? (int) $options['cache_page_ttl'] : 86400;
        $this->gzip_enabled = ! empty($options['cache_gzip_enabled']);
        $this->backup_dir   = WP_CONTENT_DIR . '/cache/ai-seo-captain/backups/';
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

        // Create automatic backup before any modification.
        $this->backup_htaccess();

        // Remove existing rules first.
        $this->remove_htaccess_internal(false);

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
        return $this->remove_htaccess_internal(true);
    }

    /**
     * Internal removal — optionally creates a backup first.
     */
    private function remove_htaccess_internal(bool $with_backup): bool
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

        if ($with_backup) {
            $this->backup_htaccess();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents($htaccess_file, $cleaned, LOCK_EX);
    }

    // ───────────────────────────────────────────────────────────
    // Backup & Restore
    // ───────────────────────────────────────────────────────────

    /**
     * Create a timestamped backup of .htaccess before any modification.
     */
    public function backup_htaccess(): bool
    {
        $htaccess_file = ABSPATH . '.htaccess';

        if (! file_exists($htaccess_file)) {
            return false;
        }

        if (! is_dir($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);

            // Protect backup directory.
            $index = $this->backup_dir . 'index.php';
            if (! file_exists($index)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents($index, '<?php // Silence is golden.');
            }

            $htaccess_guard = $this->backup_dir . '.htaccess';
            if (! file_exists($htaccess_guard)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents($htaccess_guard, "Deny from all\n");
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $contents = file_get_contents($htaccess_file);

        if (false === $contents) {
            return false;
        }

        $timestamp   = gmdate('Y-m-d_H-i-s');
        $backup_file = $this->backup_dir . 'htaccess_' . $timestamp . '.bak';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents($backup_file, $contents, LOCK_EX);

        if (false !== $written) {
            $this->prune_old_backups();
        }

        return false !== $written;
    }

    /**
     * Restore .htaccess from the most recent backup.
     */
    public function restore_htaccess(): bool
    {
        $latest = $this->get_latest_backup();

        if (null === $latest) {
            return false;
        }

        $htaccess_file = ABSPATH . '.htaccess';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $backup_contents = file_get_contents($latest['path']);

        if (false === $backup_contents) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents($htaccess_file, $backup_contents, LOCK_EX);
    }

    /**
     * Get list of available backups (newest first).
     *
     * @return array<int, array{path: string, filename: string, date: string, size: int}>
     */
    public function get_backups(): array
    {
        if (! is_dir($this->backup_dir)) {
            return array();
        }

        $backups = array();
        $files   = glob($this->backup_dir . 'htaccess_*.bak');

        if (! is_array($files)) {
            return array();
        }

        foreach ($files as $file) {
            $filename = basename($file);
            // Extract timestamp from filename: htaccess_2026-05-18_14-30-00.bak
            $date_part = str_replace(array('htaccess_', '.bak'), '', $filename);
            $date_part = str_replace('_', ' ', $date_part);
            $date_part = str_replace('-', ':', $date_part, $count);
            // Only replace the last 2 hyphens with colons for time part.
            $readable = preg_replace('/(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})/', '$1-$2-$3 $4:$5:$6', $date_part);

            $backups[] = array(
                'path'     => $file,
                'filename' => $filename,
                'date'     => $readable ?: $date_part,
                'size'     => filesize($file),
            );
        }

        // Sort newest first.
        usort($backups, function ($a, $b) {
            return strcmp($b['filename'], $a['filename']);
        });

        return $backups;
    }

    /**
     * Get the most recent backup.
     *
     * @return array{path: string, filename: string, date: string, size: int}|null
     */
    public function get_latest_backup(): ?array
    {
        $backups = $this->get_backups();
        return ! empty($backups) ? $backups[0] : null;
    }

    /**
     * Keep only the most recent backups, delete older ones.
     */
    private function prune_old_backups(): void
    {
        $backups = $this->get_backups();

        if (count($backups) <= self::MAX_BACKUPS) {
            return;
        }

        $to_delete = array_slice($backups, self::MAX_BACKUPS);

        foreach ($to_delete as $old) {
            wp_delete_file($old['path']);
        }
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
