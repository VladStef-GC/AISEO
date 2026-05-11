<?php

/**
 * Minimal WordPress function stubs for unit tests.
 *
 * Only functions actually called by the classes under test are defined here.
 * Keep each stub as simple as possible — these are NOT mocks.
 */

declare(strict_types=1);

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $string, bool $remove_breaks = false): string
    {
        $string = strip_tags($string);
        if ($remove_breaks) {
            $string = (string) preg_replace('/[\r\n\t ]+/', ' ', $string);
        }
        return trim($string);
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'http://example.com' . $path;
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(int $post_id = 0): string
    {
        return 'http://example.com/?p=' . $post_id;
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        return (string) preg_replace('/[^a-z0-9_-]/', '', $key);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string
    {
        return trim($str);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(string $value): string
    {
        return stripslashes($value);
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string
    {
        return $data; // simplified — real kses strips disallowed HTML
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args(array $args, array $defaults = array()): array
    {
        return array_merge($defaults, $args);
    }
}

if (! function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        return $default;
    }
}

if (! function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode(string $string, int $quote_style = ENT_QUOTES): string
    {
        return html_entity_decode($string, $quote_style, 'UTF-8');
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw'): string
    {
        return match ($show) {
            'name'    => 'Test Site',
            'url'     => 'http://example.com',
            default   => '',
        };
    }
}

if (! function_exists('number_format_i18n')) {
    function number_format_i18n(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals);
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, array $args = array()): void {}
}

if (! function_exists('remove_accents')) {
    function remove_accents(string $string): string
    {
        // Simplified transliteration for test purposes.
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string) ?: $string;
    }
}

// Minimal WP_Post stub so SEO_Analysis type-hints don't break.
if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public string $post_type = 'post';
        public string $post_status = 'publish';

        public function __construct(array $data = array())
        {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
}
