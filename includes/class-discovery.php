<?php

namespace AI_SEO_Captain;

class Discovery
{
    public function __construct()
    {
        add_action('template_redirect', array($this, 'maybe_output_document'), 0);
    }

    public function maybe_output_document(): void
    {
        if (is_admin()) {
            return;
        }

        $document_type = $this->detect_requested_document();

        if ('' === $document_type) {
            return;
        }

        nocache_headers();
        status_header(200);
        header('Content-Type: text/plain; charset=' . get_option('blog_charset'));

        echo $this->build_document('full' === $document_type);
        exit;
    }

    private function detect_requested_document(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $request_path = wp_parse_url($request_uri, PHP_URL_PATH);

        if (! is_string($request_path) || '' === $request_path) {
            return '';
        }

        $request_path = untrailingslashit($request_path);
        $short_path = untrailingslashit((string) wp_parse_url(home_url('/llms.txt'), PHP_URL_PATH));
        $full_path = untrailingslashit((string) wp_parse_url(home_url('/llms-full.txt'), PHP_URL_PATH));

        if ($request_path === $short_path) {
            return 'short';
        }

        if ($request_path === $full_path) {
            return 'full';
        }

        return '';
    }

    private function build_document(bool $full): string
    {
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $site_description = wp_specialchars_decode((string) get_bloginfo('description'), ENT_QUOTES);
        $home_url = home_url('/');
        $sitemap_url = $this->get_sitemap_url();
        $feed_url = home_url('/feed/');
        $page_limit = $full ? 20 : 8;
        $content_limit = $full ? 30 : 12;

        $lines = array(
            '# ' . $site_name,
            '> ' . $site_description,
            '',
            'SEO Captain generated discovery document for language models and AI search agents.',
            'Canonical site: ' . $home_url,
            'Sitemap: ' . $sitemap_url,
            'Feed: ' . $feed_url,
            '',
            '## Important pages',
        );

        foreach ($this->get_page_entries($page_limit) as $entry) {
            $lines[] = '- [' . $entry['title'] . '](' . $entry['url'] . '): ' . $entry['summary'];
        }

        $lines[] = '';
        $lines[] = '## Recent content';

        foreach ($this->get_content_entries($content_limit) as $entry) {
            $lines[] = '- [' . $entry['title'] . '](' . $entry['url'] . '): ' . $entry['summary'];
        }

        if ($full) {
            $lines[] = '';
            $lines[] = '## Key site areas';
            foreach ($this->get_key_area_lines() as $line) {
                $lines[] = '- ' . $line;
            }

            $latest_audit = $this->get_latest_site_audit();

            if (! empty($latest_audit)) {
                $lines[] = '';
                $lines[] = '## Latest AI audit focus';
                $lines[] = '- Audit: ' . $latest_audit['audit_title'];
                $lines[] = '- Summary: ' . $latest_audit['executive_summary'];

                foreach ($latest_audit['priority_actions'] as $priority_action) {
                    $lines[] = '- Priority action: ' . $priority_action;
                }

                foreach ($latest_audit['quick_wins'] as $quick_win) {
                    $lines[] = '- Quick win: ' . $quick_win;
                }
            }

            $lines[] = '';
            $lines[] = '## Crawl hints';
            $lines[] = '- Prefer canonical URLs over archive duplicates.';
            $lines[] = '- Use the sitemap and feed to discover updates.';
            $lines[] = '- Important entity pages usually include services, products, contact, about, and policy information.';
        }

        return implode("\n", $lines) . "\n";
    }

    private function get_page_entries(int $limit): array
    {
        $pages = get_posts(
            array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'menu_order title',
                'order' => 'ASC',
                'no_found_rows' => true,
            )
        );

        if (is_array($pages)) {
            usort(
                $pages,
                function (\WP_Post $left, \WP_Post $right): int {
                    $left_score = $this->get_priority_score($left);
                    $right_score = $this->get_priority_score($right);

                    if ($left_score === $right_score) {
                        return strcmp((string) $left->post_title, (string) $right->post_title);
                    }

                    return $right_score <=> $left_score;
                }
            );
        }

        return array_slice($this->map_posts_to_entries(is_array($pages) ? $pages : array()), 0, $limit);
    }

    private function get_content_entries(int $limit): array
    {
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        unset($post_types['attachment']);

        $items = get_posts(
            array(
                'post_type' => array_values($post_types),
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'modified',
                'order' => 'DESC',
                'no_found_rows' => true,
            )
        );

        return $this->map_posts_to_entries($items);
    }

    private function map_posts_to_entries(array $posts): array
    {
        $entries = array();

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            if ($this->should_exclude_post($post)) {
                continue;
            }

            $title = trim(wp_strip_all_tags((string) $post->post_title));

            if ('' === $title) {
                continue;
            }

            $summary = wp_trim_words(wp_strip_all_tags(Content_Helper::get_content($post)), 28, '...');
            $summary = preg_replace('/\s+/', ' ', (string) $summary);

            $entries[] = array(
                'title' => $title,
                'url' => (string) get_permalink($post),
                'summary' => '' !== $summary ? $summary : $this->build_fallback_summary($post, $title),
            );
        }

        return $entries;
    }

    private function should_exclude_post(\WP_Post $post): bool
    {
        $slug = strtolower((string) $post->post_name);
        $title = strtolower(trim((string) $post->post_title));

        if (false !== strpos($slug, '404') || 0 === strpos($title, '404')) {
            return true;
        }

        return false;
    }

    private function build_fallback_summary(\WP_Post $post, string $title): string
    {
        if ('page' === $post->post_type) {
            return 'Main site page: ' . $title . '.';
        }

        return 'Published ' . $post->post_type . ': ' . $title . '.';
    }

    private function get_priority_score(\WP_Post $post): int
    {
        $slug = strtolower((string) $post->post_name);
        $path = strtolower((string) wp_parse_url((string) get_permalink($post), PHP_URL_PATH));
        $score = 0;

        foreach (array('services', 'products', 'contact', 'about', 'community', 'legal', 'policy') as $keyword) {
            if (false !== strpos($slug, $keyword) || false !== strpos($path, '/' . $keyword)) {
                $score += 20;
            }
        }

        if ((int) $post->menu_order > 0) {
            $score += max(0, 10 - (int) $post->menu_order);
        }

        if ('publish' === $post->post_status) {
            $score += 5;
        }

        return $score;
    }

    private function get_key_area_lines(): array
    {
        return array(
            'Core trust pages usually include About, Contact, Cookies, and policy pages.',
            'Commercial intent is concentrated around services and products sections.',
            'The sitemap and feed should be used as the primary discovery sources for updates.',
        );
    }

    private function get_latest_site_audit(): array
    {
        $history_store = new History_Store();
        $site_audits = $history_store->get_recent_site_audits(1);

        if (empty($site_audits)) {
            return array();
        }

        $latest = $site_audits[0];

        return array(
            'audit_title' => isset($latest['audit_title']) ? (string) $latest['audit_title'] : 'SEO Captain Site Audit',
            'executive_summary' => isset($latest['executive_summary']) ? wp_trim_words((string) $latest['executive_summary'], 40, '...') : '',
            'priority_actions' => isset($latest['priority_actions']) && is_array($latest['priority_actions']) ? array_slice(array_values($latest['priority_actions']), 0, 3) : array(),
            'quick_wins' => isset($latest['quick_wins']) && is_array($latest['quick_wins']) ? array_slice(array_values($latest['quick_wins']), 0, 3) : array(),
        );
    }

    private function get_sitemap_url(): string
    {
        $active_plugins = (array) get_option('active_plugins', array());

        if (in_array('wordpress-seo/wp-seo.php', $active_plugins, true)) {
            return home_url('/sitemap_index.xml');
        }

        return home_url('/wp-sitemap.xml');
    }
}
