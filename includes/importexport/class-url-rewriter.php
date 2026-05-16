<?php

namespace AI_SEO_Keeper\ImportExport;

/**
 * Replaces source domain with target domain in URLs.
 */
class Url_Rewriter
{
    private string $source_domain;
    private string $target_domain;
    private bool $enabled;

    /**
     * @param string $source_domain The domain from the export file.
     * @param string $target_domain The current site domain.
     * @param bool   $enabled       Whether URL rewriting is active.
     */
    public function __construct(string $source_domain, string $target_domain, bool $enabled = true)
    {
        $this->source_domain = rtrim($source_domain, '/');
        $this->target_domain = rtrim($target_domain, '/');
        $this->enabled       = $enabled && ($this->source_domain !== $this->target_domain);
    }

    /**
     * Whether rewriting is active.
     */
    public function is_active(): bool
    {
        return $this->enabled;
    }

    /**
     * Rewrite a single URL or empty string.
     */
    public function rewrite(string $url): string
    {
        if (! $this->enabled || '' === $url) {
            return $url;
        }

        // Build patterns for both http and https variants of the source domain.
        $patterns = array(
            'https://' . $this->source_domain,
            'http://' . $this->source_domain,
        );

        $target_scheme = is_ssl() ? 'https://' : 'http://';
        $replacement   = $target_scheme . $this->target_domain;

        foreach ($patterns as $pattern) {
            if (0 === strpos($url, $pattern)) {
                return $replacement . substr($url, strlen($pattern));
            }
        }

        return $url;
    }

    /**
     * Rewrite all URL-type meta keys in a meta array.
     *
     * Only rewrites keys that are known to contain URLs.
     */
    public function rewrite_post_meta(array $meta): array
    {
        if (! $this->enabled) {
            return $meta;
        }

        $url_keys = array(
            '_ai_seo_keeper_canonical_url',
            '_ai_seo_keeper_social_image',
        );

        foreach ($url_keys as $key) {
            if (! empty($meta[$key])) {
                $meta[$key] = $this->rewrite($meta[$key]);
            }
        }

        return $meta;
    }

    /**
     * Rewrite redirect target URL.
     */
    public function rewrite_redirect_target(string $target_url): string
    {
        return $this->rewrite($target_url);
    }

    /**
     * Rewrite content index permalink.
     */
    public function rewrite_permalink(string $permalink): string
    {
        return $this->rewrite($permalink);
    }
}
