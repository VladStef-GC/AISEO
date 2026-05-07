<?php

namespace AI_SEO_Keeper;

require_once __DIR__ . '/class-settings.php';

class Sitemap
{
    private const QUERY_VAR = 'ai_seo_keeper_sitemap';

    private const MAX_URLS_PER_SITEMAP = 1000;

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_action('init', array($this, 'register_rewrite_rules'));
        add_filter('query_vars', array($this, 'register_query_var'));
        add_action('template_redirect', array($this, 'render_sitemap'), 1);
        add_filter('wp_sitemaps_enabled', array($this, 'maybe_disable_core_sitemaps'));
        add_filter('robots_txt', array($this, 'add_sitemap_to_robots_txt'), 99, 2);
    }

    public function register_rewrite_rules(): void
    {
        $options = $this->settings->get();

        if (empty($options['sitemap_enabled'])) {
            return;
        }

        add_rewrite_rule('^sitemap_index\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top');
        add_rewrite_rule('^([a-z_]+)-sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-xsl\.xml$', 'index.php?' . self::QUERY_VAR . '=xsl', 'top');
    }

    public function register_query_var(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function maybe_disable_core_sitemaps(bool $enabled): bool
    {
        $options = $this->settings->get();

        if (! empty($options['sitemap_enabled'])) {
            return false;
        }

        return $enabled;
    }

    public function render_sitemap(): void
    {
        $sitemap_type = get_query_var(self::QUERY_VAR, '');

        if ('' === $sitemap_type) {
            return;
        }

        $options = $this->settings->get();

        if (empty($options['sitemap_enabled'])) {
            return;
        }

        if ('xsl' === $sitemap_type) {
            $this->render_xsl();
            return;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        if ('index' === $sitemap_type) {
            echo $this->build_sitemap_index($options);
        } else {
            echo $this->build_sitemap($sitemap_type, $options);
        }

        exit;
    }

    public function get_sitemap_url(): string
    {
        return home_url('/sitemap_index.xml');
    }

    private function build_sitemap_index(array $options): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($this->get_xsl_url()) . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $entries = $this->get_index_entries($options);

        foreach ($entries as $entry) {
            $xml .= '<sitemap>' . "\n";
            $xml .= '  <loc>' . esc_url($entry['loc']) . '</loc>' . "\n";

            if (! empty($entry['lastmod'])) {
                $xml .= '  <lastmod>' . esc_html($entry['lastmod']) . '</lastmod>' . "\n";
            }

            $xml .= '</sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    private function build_sitemap(string $type, array $options): string
    {
        $urls = array();

        if ('post' === $type && ! empty($options['sitemap_include_posts'])) {
            $urls = $this->get_post_type_urls('post', $options);
        } elseif ('page' === $type && ! empty($options['sitemap_include_pages'])) {
            $urls = $this->get_post_type_urls('page', $options);
        } elseif ('category' === $type && ! empty($options['sitemap_include_categories'])) {
            $urls = $this->get_taxonomy_urls('category');
        } elseif ('post_tag' === $type && ! empty($options['sitemap_include_tags'])) {
            $urls = $this->get_taxonomy_urls('post_tag');
        }

        if (empty($urls)) {
            return $this->build_empty_urlset();
        }

        return $this->build_urlset($urls);
    }

    private function build_urlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($this->get_xsl_url()) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '<url>' . "\n";
            $xml .= '  <loc>' . esc_url($url['loc']) . '</loc>' . "\n";

            if (! empty($url['lastmod'])) {
                $xml .= '  <lastmod>' . esc_html($url['lastmod']) . '</lastmod>' . "\n";
            }

            if (! empty($url['changefreq'])) {
                $xml .= '  <changefreq>' . esc_html($url['changefreq']) . '</changefreq>' . "\n";
            }

            if (! empty($url['priority'])) {
                $xml .= '  <priority>' . esc_html($url['priority']) . '</priority>' . "\n";
            }

            $xml .= '</url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private function build_empty_urlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    private function get_index_entries(array $options): array
    {
        $entries = array();

        if (! empty($options['sitemap_include_posts'])) {
            $latest_post = $this->get_latest_modified_date('post');
            $entries[] = array(
                'loc' => home_url('/post-sitemap.xml'),
                'lastmod' => $latest_post,
            );
        }

        if (! empty($options['sitemap_include_pages'])) {
            $latest_page = $this->get_latest_modified_date('page');
            $entries[] = array(
                'loc' => home_url('/page-sitemap.xml'),
                'lastmod' => $latest_page,
            );
        }

        if (! empty($options['sitemap_include_categories'])) {
            $entries[] = array(
                'loc' => home_url('/category-sitemap.xml'),
                'lastmod' => '',
            );
        }

        if (! empty($options['sitemap_include_tags'])) {
            $entries[] = array(
                'loc' => home_url('/post_tag-sitemap.xml'),
                'lastmod' => '',
            );
        }

        return $entries;
    }

    private function get_post_type_urls(string $post_type, array $options): array
    {
        $noindex_meta_key = '_ai_seo_keeper_robots_directives';

        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_URLS_PER_SITEMAP,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ));

        $urls = array();
        $front_page_id = (int) get_option('page_on_front');

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $robots = (string) get_post_meta($post->ID, $noindex_meta_key, true);

            if ('' !== $robots && false !== strpos($robots, 'noindex')) {
                continue;
            }

            $permalink = (string) get_permalink($post);

            if ('' === $permalink) {
                continue;
            }

            $is_front = ($post->ID === $front_page_id);
            $modified = get_post_modified_time('Y-m-d\TH:i:sP', true, $post);
            $is_cornerstone = ! empty(get_post_meta($post->ID, '_ai_seo_keeper_cornerstone', true));

            // Cornerstone content gets boosted priority (0.9).
            if ($is_front) {
                $priority = '1.0';
            } elseif ($is_cornerstone) {
                $priority = '0.9';
            } elseif ('page' === $post_type) {
                $priority = '0.8';
            } else {
                $priority = '0.6';
            }

            $urls[] = array(
                'loc' => $permalink,
                'lastmod' => is_string($modified) ? $modified : '',
                'changefreq' => $is_front ? 'daily' : ('page' === $post_type ? 'weekly' : 'weekly'),
                'priority' => $priority,
            );
        }

        return $urls;
    }

    private function get_taxonomy_urls(string $taxonomy): array
    {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => self::MAX_URLS_PER_SITEMAP,
            'orderby' => 'count',
            'order' => 'DESC',
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $urls = array();

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }

            $link = get_term_link($term);

            if (is_wp_error($link)) {
                continue;
            }

            $urls[] = array(
                'loc' => (string) $link,
                'lastmod' => '',
                'changefreq' => 'weekly',
                'priority' => '0.4',
            );
        }

        return $urls;
    }

    private function get_latest_modified_date(string $post_type): string
    {
        global $wpdb;

        $date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_modified_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_modified_gmt DESC LIMIT 1",
                $post_type
            )
        );

        if (empty($date) || '0000-00-00 00:00:00' === $date) {
            return '';
        }

        return gmdate('Y-m-d\TH:i:s+00:00', strtotime($date));
    }

    private function get_xsl_url(): string
    {
        return home_url('/sitemap-xsl.xml');
    }

    public function render_xsl(): void
    {
        $sitemap_type = get_query_var(self::QUERY_VAR, '');

        if ('xsl' !== $sitemap_type) {
            return;
        }

        status_header(200);
        header('Content-Type: text/xsl; charset=UTF-8');

        echo $this->build_xsl_stylesheet();
        exit;
    }

    private function build_xsl_stylesheet(): string
    {
        $site_name = esc_html(wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES));

        return '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
    xmlns:html="http://www.w3.org/TR/REC-html40"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>XML Sitemap — ' . $site_name . '</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style type="text/css">
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;color:#1d2327;margin:0;padding:24px}
        h1{font-size:22px;margin:0 0 16px}
        p.desc{color:#50575e;margin:0 0 24px}
        table{border-collapse:collapse;width:100%;max-width:960px}
        th{background:#f0f0f1;text-align:left;padding:8px 12px;font-weight:600;border-bottom:2px solid #c3c4c7}
        td{padding:8px 12px;border-bottom:1px solid #dcdcde}
        a{color:#2271b1;text-decoration:none}
        a:hover{text-decoration:underline}
    </style>
</head>
<body>
    <h1>XML Sitemap</h1>
    <p class="desc">Generated by AI SEO Keeper for ' . $site_name . '</p>
    <xsl:choose>
        <xsl:when test="sitemap:sitemapindex">
            <table>
                <thead><tr><th>Sitemap</th><th>Last Modified</th></tr></thead>
                <tbody>
                    <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                        <tr>
                            <td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td>
                            <td><xsl:value-of select="sitemap:lastmod"/></td>
                        </tr>
                    </xsl:for-each>
                </tbody>
            </table>
        </xsl:when>
        <xsl:otherwise>
            <table>
                <thead><tr><th>URL</th><th>Last Modified</th><th>Change Freq</th><th>Priority</th></tr></thead>
                <tbody>
                    <xsl:for-each select="sitemap:urlset/sitemap:url">
                        <tr>
                            <td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td>
                            <td><xsl:value-of select="sitemap:lastmod"/></td>
                            <td><xsl:value-of select="sitemap:changefreq"/></td>
                            <td><xsl:value-of select="sitemap:priority"/></td>
                        </tr>
                    </xsl:for-each>
                </tbody>
            </table>
        </xsl:otherwise>
    </xsl:choose>
</body>
</html>
</xsl:template>
</xsl:stylesheet>';
    }

    public function add_sitemap_to_robots_txt(string $output, bool $public): string
    {
        $options = $this->settings->get();

        if (empty($options['sitemap_enabled']) || ! $public) {
            // Still apply custom rules even if sitemap is off.
            return $this->append_custom_robots_rules($output, $options);
        }

        $sitemap_url = $this->get_sitemap_url();

        if (false === strpos($output, $sitemap_url)) {
            $output .= "\nSitemap: " . $sitemap_url . "\n";
        }

        return $this->append_custom_robots_rules($output, $options);
    }

    /**
     * Append custom robots.txt rules from settings if present.
     */
    private function append_custom_robots_rules(string $output, array $options): string
    {
        $custom = trim((string) ($options['robots_txt_custom'] ?? ''));

        if ('' === $custom) {
            return $output;
        }

        // Append custom rules after a blank line.
        $output = rtrim($output) . "\n\n# AI SEO Keeper custom rules\n" . $custom . "\n";

        return $output;
    }

    public function needs_flush(): bool
    {
        $rules = get_option('rewrite_rules', array());

        if (! is_array($rules)) {
            return true;
        }

        return ! isset($rules['^sitemap_index\.xml$']);
    }
}
