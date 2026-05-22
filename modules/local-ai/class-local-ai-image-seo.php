<?php

/**
 * Local AI Image SEO — Two-model vision pipeline for image metadata generation.
 *
 * Pipeline:
 *   1. Vision model describes the image (2-3 sentences)
 *   2. Text model generates SEO metadata using page context + vision description
 *
 * If only a single multimodal model is available, both steps use that model.
 * If no vision model → text-only fallback using filename + page context.
 *
 * PRIVACY: Images are NEVER sent to cloud APIs. All processing is local.
 *
 * @package AI_SEO_Captain\Modules\LocalAI
 */

namespace AI_SEO_Captain\Modules\LocalAI;

defined('ABSPATH') || exit;

class Local_AI_Image_SEO
{
    /** @var Local_AI_Provider */
    private $provider;

    public function __construct(Local_AI_Provider $provider = null)
    {
        $this->provider = $provider ?: Local_AI_Provider::from_options();
    }

    /**
     * Generate SEO metadata for an image attachment.
     *
     * @param int   $attachment_id WP attachment ID.
     * @param int   $page_id      The page/post context where the image is used (0 = no context).
     * @param array $opts         Optional overrides: 'force_text_only' => true to skip vision.
     *
     * @return array{success: bool, alt_text?: string, title?: string, caption?: string, decorative?: bool, method?: string, error?: string}
     */
    public function generate(int $attachment_id, int $page_id = 0, array $opts = array())
    {
        $attachment = get_post($attachment_id);

        if (! $attachment || 'attachment' !== $attachment->post_type) {
            return array('success' => false, 'error' => 'Invalid attachment ID.');
        }

        $mime = (string) $attachment->post_mime_type;

        if (0 !== strpos($mime, 'image/')) {
            return array('success' => false, 'error' => 'Attachment is not an image.');
        }

        $file_path = get_attached_file($attachment_id);
        $filename  = $file_path ? basename($file_path) : '';
        $metadata  = wp_get_attachment_metadata($attachment_id);
        $width     = (int) ($metadata['width'] ?? 0);
        $height    = (int) ($metadata['height'] ?? 0);

        // Check if decorative by heuristics before using AI.
        if ($this->is_likely_decorative($filename, $width, $height)) {
            return array(
                'success'    => true,
                'alt_text'   => '',
                'title'      => $filename,
                'caption'    => '',
                'decorative' => true,
                'method'     => 'heuristic',
            );
        }

        // Gather page context.
        $page_context = $this->build_page_context($attachment_id, $page_id);

        // Decide pipeline: vision or text-only.
        $force_text_only = ! empty($opts['force_text_only']);
        $has_vision      = ! $force_text_only && $this->provider_has_vision();

        if ($has_vision && $file_path && is_readable($file_path)) {
            return $this->pipeline_vision($file_path, $mime, $filename, $width, $height, $page_context);
        }

        return $this->pipeline_text_only($filename, $width, $height, $page_context);
    }

    /**
     * Two-model vision pipeline.
     *
     * Step 1: Vision model describes the image.
     * Step 2: Text model writes SEO metadata from context + description.
     */
    private function pipeline_vision(string $file_path, string $mime, string $filename, int $width, int $height, array $page_context)
    {
        // Read and encode image (limit to 1MB to stay within context window).
        $image_data = file_get_contents($file_path);

        if (false === $image_data) {
            return $this->pipeline_text_only($filename, $width, $height, $page_context);
        }

        // Downsample large images — vision doesn't need 4K resolution.
        if (strlen($image_data) > 1048576) {
            $thumb_url = wp_get_attachment_image_url(
                $this->get_attachment_id_from_path($file_path),
                'large'
            );
            if ($thumb_url) {
                $thumb_path = str_replace(
                    wp_get_upload_dir()['baseurl'],
                    wp_get_upload_dir()['basedir'],
                    $thumb_url
                );
                if ($thumb_path && is_readable($thumb_path)) {
                    $image_data = file_get_contents($thumb_path);
                    if (false === $image_data) {
                        return $this->pipeline_text_only($filename, $width, $height, $page_context);
                    }
                }
            }
        }

        $base64 = base64_encode($image_data);

        // Step 1: Vision — describe the image.
        $vision_prompt = 'Describe this image objectively in 2-3 sentences. Focus on: what the image shows, ' .
            'key objects/people/text visible, colors, and composition. Be factual, not creative.';

        $vision_result = $this->provider->vision($base64, $mime, $vision_prompt);

        if (empty($vision_result['success'])) {
            // Vision failed — fall back to text-only.
            return $this->pipeline_text_only($filename, $width, $height, $page_context);
        }

        $vision_description = $vision_result['content'] ?? '';

        // Step 2: Text model — generate SEO metadata.
        return $this->generate_seo_from_context($filename, $width, $height, $page_context, $vision_description);
    }

    /**
     * Text-only pipeline (no vision model available).
     * Uses filename + page context to generate best-effort metadata.
     */
    private function pipeline_text_only(string $filename, int $width, int $height, array $page_context)
    {
        return $this->generate_seo_from_context($filename, $width, $height, $page_context, '');
    }

    /**
     * Use the text/chat model to generate SEO metadata from context.
     *
     * @param string $filename
     * @param int    $width
     * @param int    $height
     * @param array  $page_context
     * @param string $vision_description Empty string if no vision was used.
     *
     * @return array
     */
    private function generate_seo_from_context(string $filename, int $width, int $height, array $page_context, string $vision_description)
    {
        $system = 'You are an SEO specialist. Generate image metadata optimized for search engines and accessibility. ' .
            'Reply ONLY with a valid JSON object — no markdown, no explanation, no code fences.';

        $user_parts = array();
        $user_parts[] = 'Generate SEO metadata for this image.';
        $user_parts[] = '';
        $user_parts[] = 'IMAGE INFO:';
        $user_parts[] = '- Filename: ' . $filename;

        if ($width > 0 && $height > 0) {
            $user_parts[] = '- Dimensions: ' . $width . 'x' . $height . 'px';
        }

        if ('' !== $vision_description) {
            $user_parts[] = '';
            $user_parts[] = 'VISUAL DESCRIPTION (from vision analysis):';
            $user_parts[] = $vision_description;
        }

        if (! empty($page_context['page_title'])) {
            $user_parts[] = '';
            $user_parts[] = 'PAGE CONTEXT:';
            $user_parts[] = '- Page title: ' . $page_context['page_title'];

            if (! empty($page_context['page_url'])) {
                $user_parts[] = '- URL: ' . $page_context['page_url'];
            }

            if (! empty($page_context['nearest_heading'])) {
                $user_parts[] = '- Nearest heading: ' . $page_context['nearest_heading'];
            }

            if (! empty($page_context['surrounding_text'])) {
                $user_parts[] = '- Surrounding text: ' . $page_context['surrounding_text'];
            }
        }

        $user_parts[] = '';
        $user_parts[] = 'Return this exact JSON structure:';
        $user_parts[] = '{"alt_text": "descriptive alt text (max 125 chars)", "title": "concise title", "caption": "optional caption or empty string", "decorative": false}';
        $user_parts[] = '';
        $user_parts[] = 'Rules:';
        $user_parts[] = '- alt_text: Describe what the image shows in context of the page. Max 125 characters.';
        $user_parts[] = '- Do NOT start alt_text with "Image of" or "Picture of"';
        $user_parts[] = '- If the image is purely decorative (spacer, divider, background pattern), set decorative=true and alt_text=""';
        $user_parts[] = '- caption: Only if the image would benefit from a visible caption. Empty string if not needed.';

        $user_prompt = implode("\n", $user_parts);

        $messages = array(
            array('role' => 'system', 'content' => $system),
            array('role' => 'user', 'content' => $user_prompt),
        );

        $result = $this->provider->chat($messages, '', 0.3, 512);

        if (empty($result['success'])) {
            return array(
                'success' => false,
                'error'   => $result['error'] ?? 'Local AI text model request failed.',
            );
        }

        $content = $result['content'] ?? '';

        return $this->parse_seo_response($content, '' !== $vision_description ? 'vision+text' : 'text-only');
    }

    /**
     * Parse the AI JSON response into a structured result.
     */
    private function parse_seo_response(string $raw, string $method)
    {
        // Strip markdown code fences if present.
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/i', '', $raw);

        $data = json_decode(trim($raw), true);

        if (! is_array($data)) {
            return array(
                'success' => false,
                'error'   => 'AI returned invalid JSON. Raw: ' . substr($raw, 0, 200),
            );
        }

        $alt_text = isset($data['alt_text']) ? sanitize_text_field((string) $data['alt_text']) : '';
        $title    = isset($data['title']) ? sanitize_text_field((string) $data['title']) : '';
        $caption  = isset($data['caption']) ? sanitize_text_field((string) $data['caption']) : '';
        $decorative = ! empty($data['decorative']);

        // Enforce max length for alt text.
        if (function_exists('mb_substr')) {
            $alt_text = mb_substr($alt_text, 0, 125);
        } else {
            $alt_text = substr($alt_text, 0, 125);
        }

        if ($decorative) {
            $alt_text = '';
        }

        return array(
            'success'    => true,
            'alt_text'   => $alt_text,
            'title'      => $title,
            'caption'    => $caption,
            'decorative' => $decorative,
            'method'     => $method,
        );
    }

    /**
     * Check if the provider has a vision-capable model configured.
     */
    private function provider_has_vision()
    {
        $options = get_option('ai_seo_captain_options', array());

        // Has a dedicated vision model.
        if (! empty($options['local_vision_model'])) {
            return true;
        }

        // Check if current chat model passed the vision probe.
        $model = $options['local_model'] ?? '';
        if ('' === $model) {
            return false;
        }

        // Check cached probe result.
        $cached = get_transient('aisc_vision_probe_' . md5($model));
        return true === $cached;
    }

    /**
     * Heuristic check: is this image likely decorative?
     */
    private function is_likely_decorative(string $filename, int $width, int $height)
    {
        // Tracking pixels, spacers.
        if ($width > 0 && $height > 0 && $width <= 3 && $height <= 3) {
            return true;
        }

        $lower = strtolower($filename);
        $decorative_patterns = array(
            'spacer', 'divider', 'separator', 'blank', 'pixel',
            'tracking', 'beacon', 'bg-pattern', 'background-',
        );

        foreach ($decorative_patterns as $pattern) {
            if (false !== strpos($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build page context for the AI prompt.
     */
    private function build_page_context(int $attachment_id, int $page_id)
    {
        $context = array(
            'page_title'       => '',
            'page_url'         => '',
            'nearest_heading'  => '',
            'surrounding_text' => '',
        );

        if ($page_id <= 0) {
            // Try to find a published page using this image.
            $parent_id = wp_get_post_parent_id($attachment_id);
            if ($parent_id && 'publish' === get_post_status($parent_id)) {
                $page_id = $parent_id;
            }
        }

        if ($page_id <= 0) {
            return $context;
        }

        $page = get_post($page_id);

        if (! $page) {
            return $context;
        }

        $context['page_title'] = $page->post_title;
        $context['page_url']   = get_permalink($page_id);

        // Extract text near the image from page content.
        $content = $page->post_content;
        $src     = wp_get_attachment_url($attachment_id);

        if ($src && $content) {
            $pos = strpos($content, basename($src));

            if (false !== $pos) {
                // Find nearest heading before the image.
                $before = substr($content, 0, $pos);
                if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $before, $matches)) {
                    $context['nearest_heading'] = wp_strip_all_tags(end($matches[1]));
                }

                // Get surrounding plain text (200 chars before, 200 after).
                $plain   = wp_strip_all_tags($content);
                $src_pos = strpos($plain, basename($src));
                if (false !== $src_pos) {
                    $start = max(0, $src_pos - 200);
                    $end   = min(strlen($plain), $src_pos + 200);
                    $context['surrounding_text'] = trim(substr($plain, $start, $end - $start));
                }
            }
        }

        return $context;
    }

    /**
     * Helper: get attachment ID from file path.
     */
    private function get_attachment_id_from_path(string $path)
    {
        $upload_dir = wp_get_upload_dir();
        $relative   = str_replace($upload_dir['basedir'] . '/', '', $path);

        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                $relative
            )
        );
    }
}
