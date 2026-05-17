<?php

namespace AI_SEO_Captain\Admin;

use AI_SEO_Captain\Meta_Keys;

/**
 * Taxonomy term SEO fields — register, render, save.
 */
class Admin_Taxonomy
{
    /**
     * Hook taxonomy edit screens to add SEO fields.
     */
    public function register(): void
    {
        $taxonomies = get_taxonomies(array('public' => true), 'names');

        foreach ($taxonomies as $taxonomy) {
            add_action("{$taxonomy}_edit_form_fields", array($this, 'render_fields'), 10, 2);
            add_action("edited_{$taxonomy}", array($this, 'save_fields'), 10, 2);
        }
    }

    /**
     * Render SEO fields on term edit screen.
     */
    public function render_fields(\WP_Term $term, string $taxonomy): void
    {
        $seo_title        = get_term_meta($term->term_id, Meta_Keys::TERM_SEO_TITLE, true);
        $meta_description = get_term_meta($term->term_id, Meta_Keys::TERM_META_DESCRIPTION, true);
        $canonical        = get_term_meta($term->term_id, Meta_Keys::TERM_CANONICAL, true);
        $noindex          = get_term_meta($term->term_id, Meta_Keys::TERM_NOINDEX, true);

        wp_nonce_field('ai_seo_captain_term_seo', '_ai_seo_captain_term_nonce');
?>
        <tr class="form-field">
            <th scope="row" colspan="2">
                <h2 style="margin:0;"><?php esc_html_e('SEO Captain', 'ai-seo-captain'); ?></h2>
            </th>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai-seo-captain-term-seo-title"><?php esc_html_e('SEO Title', 'ai-seo-captain'); ?></label></th>
            <td>
                <input id="ai-seo-captain-term-seo-title" type="text" name="ai_seo_captain_seo_title" value="<?php echo esc_attr($seo_title); ?>" class="large-text" />
                <p class="description"><?php esc_html_e('Override the default title tag. Leave blank to use the WordPress default.', 'ai-seo-captain'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai-seo-captain-term-meta-desc"><?php esc_html_e('Meta description', 'ai-seo-captain'); ?></label></th>
            <td>
                <textarea id="ai-seo-captain-term-meta-desc" name="ai_seo_captain_meta_description" rows="3" class="large-text"><?php echo esc_textarea($meta_description); ?></textarea>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai-seo-captain-term-canonical"><?php esc_html_e('Canonical URL', 'ai-seo-captain'); ?></label></th>
            <td>
                <input id="ai-seo-captain-term-canonical" type="url" name="ai_seo_captain_canonical" value="<?php echo esc_attr($canonical); ?>" class="large-text" placeholder="https://" />
                <p class="description"><?php esc_html_e('Leave blank for the default canonical URL.', 'ai-seo-captain'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Noindex', 'ai-seo-captain'); ?></th>
            <td>
                <label class="aisc-toggle"><input type="checkbox" name="ai_seo_captain_noindex" value="1" <?php checked($noindex, '1'); ?> /><span class="aisc-toggle__track"></span><span class="aisc-toggle__label"><?php esc_html_e('Prevent search engines from indexing this taxonomy archive', 'ai-seo-captain'); ?></span></label>
            </td>
        </tr>
<?php
    }

    /**
     * Save taxonomy term SEO fields.
     */
    public function save_fields(int $term_id, int $tt_id): void
    {
        if (
            ! isset($_POST['_ai_seo_captain_term_nonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ai_seo_captain_term_nonce'])), 'ai_seo_captain_term_seo')
        ) {
            return;
        }

        if (! current_user_can('manage_categories')) {
            return;
        }

        $fields = array(
            'ai_seo_captain_seo_title'       => Meta_Keys::TERM_SEO_TITLE,
            'ai_seo_captain_meta_description' => Meta_Keys::TERM_META_DESCRIPTION,
            'ai_seo_captain_canonical'       => Meta_Keys::TERM_CANONICAL,
        );

        foreach ($fields as $input_key => $meta_key) {
            $value = isset($_POST[$input_key]) ? sanitize_text_field(wp_unslash($_POST[$input_key])) : '';

            if (Meta_Keys::TERM_CANONICAL === $meta_key) {
                $value = esc_url_raw($value);
            }

            if ('' !== $value) {
                update_term_meta($term_id, $meta_key, $value);
            } else {
                delete_term_meta($term_id, $meta_key);
            }
        }

        $noindex = isset($_POST['ai_seo_captain_noindex']) ? '1' : '';

        if ('' !== $noindex) {
            update_term_meta($term_id, Meta_Keys::TERM_NOINDEX, '1');
        } else {
            delete_term_meta($term_id, Meta_Keys::TERM_NOINDEX);
        }
    }
}
