<?php

namespace AI_SEO_Captain\Cache;

/**
 * CDN URL rewriter — rewrites local asset URLs to a CDN origin.
 *
 * Hooks into the HTML output buffer to replace wp-content and wp-includes
 * asset URLs with the configured CDN URL. Only rewrites static assets
 * (CSS, JS, images, fonts, videos, etc.) — never rewrites HTML pages.
 */
class CDN
{
    /** @var string CDN base URL (e.g. https://cdn.example.com). */
    private string $cdn_url;

    /** @var string[] Directories to rewrite (relative to site URL). */
    private array $include_dirs;

    /** @var string[] File extensions to rewrite. */
    private array $extensions;

    /** @var string[] URL substrings to exclude from rewriting. */
    private array $exclude_patterns;

    /** @var string Site URL without trailing slash. */
    private string $site_url;

    public function __construct(array $options = array())
    {
        $this->cdn_url = untrailingslashit(trim($options['cache_cdn_url'] ?? ''));
        $this->site_url = untrailingslashit(site_url());

        // Directories to rewrite — default to wp-content and wp-includes.
        $dirs_raw = trim($options['cache_cdn_dirs'] ?? 'wp-content,wp-includes');
        $this->include_dirs = array_filter(array_map('trim', explode(',', $dirs_raw)));

        // Supported static asset extensions.
        $this->extensions = array(
            'css',
            'js',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'avif',
            'svg',
            'ico',
            'woff',
            'woff2',
            'ttf',
            'eot',
            'otf',
            'mp4',
            'webm',
            'ogg',
            'mp3',
            'pdf',
            'zip',
        );

        // URL patterns to exclude.
        $exclude_raw = trim($options['cache_cdn_exclude'] ?? '');
        $this->exclude_patterns = array_filter(array_map('trim', explode("\n", $exclude_raw)));
    }

    /**
     * Register hooks for CDN URL rewriting.
     */
    public function register_hooks(): void
    {
        if ('' === $this->cdn_url || is_admin()) {
            return;
        }

        // Rewrite URLs in HTML output.
        add_action('template_redirect', array($this, 'start_output_buffer'), 2);
    }

    /**
     * Start output buffer.
     */
    public function start_output_buffer(): void
    {
        ob_start(array($this, 'rewrite_urls'));
    }

    /**
     * Rewrite local asset URLs in the HTML to use the CDN.
     */
    public function rewrite_urls(string $html): string
    {
        if ('' === $this->cdn_url || strlen($html) < 100) {
            return $html;
        }

        // Build regex to match URLs pointing to included directories.
        $escaped_site = preg_quote($this->site_url, '#');
        $escaped_dirs = array_map(function ($dir) {
            return preg_quote($dir, '#');
        }, $this->include_dirs);

        if (empty($escaped_dirs)) {
            return $html;
        }

        $dirs_pattern = implode('|', $escaped_dirs);
        $ext_pattern  = implode('|', $this->extensions);

        // Match full URLs: https://example.com/wp-content/...file.ext
        // Also match protocol-relative //example.com/wp-content/...
        $site_host = preg_quote(wp_parse_url($this->site_url, PHP_URL_HOST), '#');
        $site_path = preg_quote(wp_parse_url($this->site_url, PHP_URL_PATH) ?: '', '#');

        $pattern = '#(?:https?:)?//' . $site_host . $site_path . '/(' . $dirs_pattern . ')/([^\s\'"<>]+\.(?:' . $ext_pattern . '))(\?[^\s\'"<>]*)?#i';

        $html = preg_replace_callback($pattern, function ($match) {
            $full_url = $match[0];

            // Check exclusions.
            foreach ($this->exclude_patterns as $exclude) {
                if ('' !== $exclude && false !== strpos($full_url, $exclude)) {
                    return $full_url;
                }
            }

            $path  = '/' . $match[1] . '/' . $match[2];
            $query = $match[3] ?? '';

            return $this->cdn_url . $path . $query;
        }, $html);

        return $html;
    }

    /**
     * Check if CDN is configured and active.
     */
    public function is_active(): bool
    {
        return '' !== $this->cdn_url;
    }

    /**
     * Get the configured CDN URL.
     */
    public function get_cdn_url(): string
    {
        return $this->cdn_url;
    }
}
