<?php

namespace AI_SEO_Keeper;

require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-content-indexer.php';
require_once __DIR__ . '/class-ai-generator.php';
require_once __DIR__ . '/class-history-store.php';
require_once __DIR__ . '/class-frontend.php';
require_once __DIR__ . '/class-audit-engine.php';
require_once __DIR__ . '/class-indexnow.php';

class Admin
{
    private const META_BOX_ID = 'ai_seo_keeper_meta_box';

    private const FRONTEND_ENABLE_META_KEY = '_ai_seo_keeper_frontend_enabled';

    private const AJAX_SAVE_ACTION = 'ai_seo_keeper_save_meta';

    private const AJAX_GENERATE_ACTION = 'ai_seo_keeper_generate_meta';

    private const AJAX_APPROVE_ACTION = 'ai_seo_keeper_approve_suggestion';

    private const AJAX_CHAT_ACTION = 'ai_seo_keeper_chat';

    private const AJAX_BULK_GENERATE_ACTION = 'ai_seo_keeper_bulk_generate';

    private const AJAX_PAGE_AUDIT_ACTION = 'ai_seo_keeper_page_audit';

    private const AJAX_SETUP_INDEX_ACTION = 'ai_seo_keeper_setup_index';

    private const GENERATE_SITE_AUDIT_ACTION = 'ai_seo_keeper_generate_site_audit';

    private const SUBMIT_INDEXNOW_ACTION = 'ai_seo_keeper_submit_indexnow';

    private const YOAST_IMPORT_ACTION = 'ai_seo_keeper_import_yoast_metadata';

    private const BULK_FRONTEND_ACTION = 'ai_seo_keeper_bulk_frontend_rollout';

    private const AJAX_TOGGLE_AUDIT_SKIP_ACTION = 'ai_seo_keeper_toggle_audit_skip';

    private const AJAX_SAVE_SKIP_PATTERNS_ACTION = 'ai_seo_keeper_save_skip_patterns';

    private const AJAX_CONTENT_EDIT_ACTION = 'ai_seo_keeper_content_edit';

    private const AJAX_APPLY_CHANGES_ACTION = 'ai_seo_keeper_apply_changes';

    private const AJAX_APPLY_SUGGESTION_ACTION = 'ai_seo_keeper_apply_suggestion';

    private const AJAX_RESTORE_BACKUP_ACTION = 'ai_seo_keeper_restore_backup';
    private const AJAX_CLEAR_CHAT_ACTION = 'ai_seo_keeper_clear_chat';
    private const AJAX_TEST_MODEL_ACTION = 'ai_seo_keeper_test_model';

    private const CHAT_OBJECT_TYPE = 'post_chat';

    private const META_TITLE_KEY = '_ai_seo_keeper_meta_title';

    private const META_DESCRIPTION_KEY = '_ai_seo_keeper_meta_description';

    private const TITLE_BRANDING_OFF_META_KEY = '_ai_seo_keeper_title_branding_off';

    private const FOCUS_KEYPHRASE_META_KEY = '_ai_seo_keeper_focus_keyphrase';

    private const SOCIAL_TITLE_META_KEY = '_ai_seo_keeper_social_title';

    private const SOCIAL_DESCRIPTION_META_KEY = '_ai_seo_keeper_social_description';

    private const SOCIAL_IMAGE_META_KEY = '_ai_seo_keeper_social_image';

    private const CANONICAL_URL_META_KEY = '_ai_seo_keeper_canonical_url';

    private const ROBOTS_DIRECTIVES_META_KEY = '_ai_seo_keeper_robots_directives';

    private const SCHEMA_TYPE_META_KEY = '_ai_seo_keeper_schema_type';

    private const TITLE_MIN_LENGTH = 30;

    private const TITLE_MAX_LENGTH = 60;

    private const DESCRIPTION_MIN_LENGTH = 70;

    private const DESCRIPTION_MAX_LENGTH = 155;

    private const READABILITY_TRANSITION_WORDS = array(
        'however',
        'therefore',
        'meanwhile',
        'moreover',
        'instead',
        'because',
        'also',
        'next',
        'finally',
        'for example',
        'for instance',
        'in addition',
        'as a result',
        'on the other hand',
        'in contrast',
        'then',
        'yet',
        'otherwise',
    );

    private const GENERIC_ANCHOR_TEXTS = array(
        'click here',
        'read more',
        'learn more',
        'more',
        'here',
        'link',
        'this page',
        'view more',
    );

    private Settings $settings;

    private Content_Indexer $content_indexer;

    private $ai_generator;

    private $history_store;

    private $audit_engine;

    private $indexnow_service;

    public function __construct(Settings $settings, Content_Indexer $content_indexer, $ai_generator, $history_store, $indexnow_service = null)
    {
        $this->settings = $settings;
        $this->content_indexer = $content_indexer;
        $this->ai_generator = $ai_generator;
        $this->history_store = $history_store;
        $audit_engine_class = __NAMESPACE__ . '\\Audit_Engine';
        $this->audit_engine = new $audit_engine_class($content_indexer);
        $this->indexnow_service = $indexnow_service;

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_editor_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_page_assets'));
        add_action('admin_post_ai_seo_keeper_sync_index', array($this, 'handle_sync_index'));
        add_action('admin_post_' . self::SUBMIT_INDEXNOW_ACTION, array($this, 'handle_submit_indexnow'));
        add_action('admin_post_' . self::YOAST_IMPORT_ACTION, array($this, 'handle_import_yoast_metadata'));
        add_action('admin_post_' . self::BULK_FRONTEND_ACTION, array($this, 'handle_bulk_frontend_rollout'));
        add_action('add_meta_boxes', array($this, 'register_editor_metabox'), 10, 2);
        add_action('save_post', array($this, 'save_editor_meta'));
        add_action('admin_post_' . self::GENERATE_SITE_AUDIT_ACTION, array($this, 'handle_generate_site_audit'));
        add_action('wp_ajax_' . self::AJAX_SAVE_ACTION, array($this, 'handle_ajax_save_editor_meta'));
        add_action('wp_ajax_' . self::AJAX_GENERATE_ACTION, array($this, 'handle_ajax_generate_editor_meta'));
        add_action('wp_ajax_' . self::AJAX_APPROVE_ACTION, array($this, 'handle_ajax_approve_suggestion'));
        add_action('wp_ajax_' . self::AJAX_CHAT_ACTION, array($this, 'handle_ajax_chat_for_post'));
        add_action('wp_ajax_' . self::AJAX_BULK_GENERATE_ACTION, array($this, 'handle_ajax_bulk_generate'));
        add_action('wp_ajax_' . self::AJAX_PAGE_AUDIT_ACTION, array($this, 'handle_ajax_page_audit'));
        add_action('wp_ajax_' . self::AJAX_SETUP_INDEX_ACTION, array($this, 'handle_ajax_setup_index'));
        add_action('wp_ajax_' . self::AJAX_TOGGLE_AUDIT_SKIP_ACTION, array($this, 'handle_ajax_toggle_audit_skip'));
        add_action('wp_ajax_' . self::AJAX_SAVE_SKIP_PATTERNS_ACTION, array($this, 'handle_ajax_save_skip_patterns'));
        add_action('wp_ajax_' . self::AJAX_CONTENT_EDIT_ACTION, array($this, 'handle_ajax_content_edit'));
        add_action('wp_ajax_' . self::AJAX_APPLY_CHANGES_ACTION, array($this, 'handle_ajax_apply_changes'));
        add_action('wp_ajax_' . self::AJAX_APPLY_SUGGESTION_ACTION, array($this, 'handle_ajax_apply_suggestion'));
        add_action('wp_ajax_' . self::AJAX_RESTORE_BACKUP_ACTION, array($this, 'handle_ajax_restore_backup'));
        add_action('wp_ajax_' . self::AJAX_CLEAR_CHAT_ACTION, array($this, 'handle_ajax_clear_chat'));
        add_action('wp_ajax_' . self::AJAX_TEST_MODEL_ACTION, array($this, 'handle_ajax_test_model'));
        add_action('wp_ajax_ai_seo_keeper_delete_edit_plan', array($this, 'handle_ajax_delete_edit_plan'));
        add_action('wp_ajax_ai_seo_keeper_bulk_save_seo', array($this, 'handle_ajax_bulk_save_seo'));
        add_action('wp_ajax_ai_seo_keeper_save_image_alt', array($this, 'handle_ajax_save_image_alt'));
        add_action('admin_post_ai_seo_keeper_export', array($this, 'handle_export'));
        add_action('admin_post_ai_seo_keeper_import', array($this, 'handle_import'));

        // Taxonomy SEO fields for all public taxonomies.
        add_action('admin_init', array($this, 'register_taxonomy_seo_fields'));
    }

    private function is_supported_post_type(string $post_type): bool
    {
        $supported_post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        unset($supported_post_types['attachment']);

        return isset($supported_post_types[$post_type]);
    }

    /**
     * Register SEO fields on all public taxonomy term edit screens.
     */
    public function register_taxonomy_seo_fields(): void
    {
        $taxonomies = get_taxonomies(array('public' => true), 'names');
        foreach ($taxonomies as $taxonomy) {
            add_action("{$taxonomy}_edit_form_fields", array($this, 'render_term_seo_fields'), 10, 2);
            add_action("edited_{$taxonomy}", array($this, 'save_term_seo_fields'), 10, 2);
        }
    }

    /**
     * Render SEO fields on term edit screen.
     */
    public function render_term_seo_fields(\WP_Term $term, string $taxonomy): void
    {
        $seo_title       = get_term_meta($term->term_id, '_ai_seo_keeper_seo_title', true);
        $meta_description = get_term_meta($term->term_id, '_ai_seo_keeper_meta_description', true);
        $canonical       = get_term_meta($term->term_id, '_ai_seo_keeper_canonical', true);
        $noindex         = get_term_meta($term->term_id, '_ai_seo_keeper_noindex', true);
        wp_nonce_field('ai_seo_keeper_term_seo', '_ai_seo_keeper_term_nonce');
?>
        <tr class="form-field">
            <th scope="row" colspan="2">
                <h2 style="margin:0;">AI SEO Keeper</h2>
            </th>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai-seo-keeper-term-seo-title">SEO Title</label></th>
            <td>
                <input id="ai-seo-keeper-term-seo-title" type="text" name="ai_seo_keeper_seo_title" value="<?php echo esc_attr($seo_title); ?>" class="large-text" />
                <p class="description">Override the default title tag. Leave blank to use the WordPress default.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai-seo-keeper-term-meta-desc">Meta description</label></th>
            <td>
                <textarea id="ai-seo-keeper-term-meta-desc" name="ai_seo_keeper_meta_description" rows="3" class="large-text"><?php echo esc_textarea($meta_description); ?></textarea>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai-seo-keeper-term-canonical">Canonical URL</label></th>
            <td>
                <input id="ai-seo-keeper-term-canonical" type="url" name="ai_seo_keeper_canonical" value="<?php echo esc_attr($canonical); ?>" class="large-text" placeholder="https://" />
                <p class="description">Leave blank for the default canonical URL.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row">Noindex</th>
            <td>
                <label><input type="checkbox" name="ai_seo_keeper_noindex" value="1" <?php checked($noindex, '1'); ?> /> Prevent search engines from indexing this taxonomy archive</label>
            </td>
        </tr>
    <?php
    }

    /**
     * Save taxonomy term SEO fields.
     */
    public function save_term_seo_fields(int $term_id, int $tt_id): void
    {
        if (! isset($_POST['_ai_seo_keeper_term_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ai_seo_keeper_term_nonce'])), 'ai_seo_keeper_term_seo')) {
            return;
        }
        if (! current_user_can('manage_categories')) {
            return;
        }

        $fields = array(
            'ai_seo_keeper_seo_title'        => '_ai_seo_keeper_seo_title',
            'ai_seo_keeper_meta_description'  => '_ai_seo_keeper_meta_description',
            'ai_seo_keeper_canonical'         => '_ai_seo_keeper_canonical',
        );
        foreach ($fields as $input_key => $meta_key) {
            $value = isset($_POST[$input_key]) ? sanitize_text_field(wp_unslash($_POST[$input_key])) : '';
            if ('_ai_seo_keeper_canonical' === $meta_key) {
                $value = esc_url_raw($value);
            }
            if ('' !== $value) {
                update_term_meta($term_id, $meta_key, $value);
            } else {
                delete_term_meta($term_id, $meta_key);
            }
        }

        $noindex = isset($_POST['ai_seo_keeper_noindex']) ? '1' : '';
        if ('' !== $noindex) {
            update_term_meta($term_id, '_ai_seo_keeper_noindex', '1');
        } else {
            delete_term_meta($term_id, '_ai_seo_keeper_noindex');
        }
    }

    public function register_menu(): void
    {
        add_menu_page(
            'AI SEO Keeper',
            'AI SEO Keeper',
            'manage_options',
            'ai-seo-keeper',
            array($this, 'render_dashboard'),
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'ai-seo-keeper',
            'AI SEO Keeper Audit',
            'Audit',
            'manage_options',
            'ai-seo-keeper-audit',
            array($this, 'render_audit_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'AI SEO Keeper Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'ai-seo-keeper-setup',
            array($this, 'render_setup_wizard_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'AI SEO Keeper Settings',
            'Settings',
            'manage_options',
            'ai-seo-keeper-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'Redirects &amp; 404 Monitor',
            'Redirects',
            'manage_options',
            'ai-seo-keeper-redirects',
            array($this, 'render_redirects_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'Bulk SEO Editor',
            'Bulk Editor',
            'manage_options',
            'ai-seo-keeper-bulk-editor',
            array($this, 'render_bulk_editor_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'Image SEO',
            'Image SEO',
            'manage_options',
            'ai-seo-keeper-images',
            array($this, 'render_image_seo_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'Keyword Tracking',
            'Keywords',
            'manage_options',
            'ai-seo-keeper-keywords',
            array($this, 'render_keyword_tracking_page')
        );

        add_submenu_page(
            'ai-seo-keeper',
            'Export / Import',
            'Export / Import',
            'manage_options',
            'ai-seo-keeper-export-import',
            array($this, 'render_export_import_page')
        );
    }

    public function enqueue_editor_assets(string $hook_suffix): void
    {
        if (! in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || ! $this->is_supported_post_type($screen->post_type)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery');

        wp_localize_script(
            'jquery',
            'aiSeoKeeperEditor',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'saveAction' => self::AJAX_SAVE_ACTION,
                'generateAction' => self::AJAX_GENERATE_ACTION,
                'approveAction' => self::AJAX_APPROVE_ACTION,
                'chatAction' => self::AJAX_CHAT_ACTION,
                'savingText' => 'Saving SEO draft...',
                'savedText' => 'SEO draft saved.',
                'generatingText' => 'Generating SEO draft with AI...',
                'generatedText' => 'AI suggestion loaded. Review it and save the draft if you want to keep it.',
                'approvingText' => 'Approving suggestion...',
                'approvedText' => 'Suggestion approved for future output.',
                'chattingText' => 'Thinking through your SEO question...',
                'chatReplyText' => 'AI assistant replied.',
                'historyTitle' => 'Recent AI suggestion history',
                'errorText' => 'Could not save the SEO draft.',
                'generateErrorText' => 'Could not generate SEO suggestions.',
                'approveErrorText' => 'Could not approve the suggestion.',
                'chatErrorText' => 'Could not get an AI assistant reply.',
                'missingPostText' => 'Save the post once before using the SEO draft button.',
                'emptyChatText' => 'Enter a question before asking the AI assistant.',
                'limits' => array(
                    'titleMin' => self::TITLE_MIN_LENGTH,
                    'titleMax' => self::TITLE_MAX_LENGTH,
                    'descriptionMin' => self::DESCRIPTION_MIN_LENGTH,
                    'descriptionMax' => self::DESCRIPTION_MAX_LENGTH,
                ),
                'brandingSuffix' => $this->settings->get_branding_suffix(),
                'brandingSuffixLength' => function_exists('mb_strlen')
                    ? mb_strlen($this->settings->get_branding_suffix())
                    : strlen($this->settings->get_branding_suffix()),
            )
        );

        wp_add_inline_script('jquery', $this->get_editor_script(), 'after');
    }

    /**
     * Enqueue shared CSS/JS on any AI SEO Keeper admin page, plus page-specific assets.
     */
    public function enqueue_page_assets(string $hook_suffix): void
    {
        // Only load on our own admin pages.
        if (false === strpos($hook_suffix, 'ai-seo-keeper')) {
            return;
        }

        $url = AI_SEO_KEEPER_URL . 'assets/';
        $ver = AI_SEO_KEEPER_VERSION;

        // Shared styles and scripts for all plugin pages.
        wp_enqueue_style('ai-seo-admin-common', $url . 'css/admin-common.css', array(), $ver);
        wp_enqueue_script('ai-seo-admin-common', $url . 'js/admin-common.js', array('jquery'), $ver, true);

        // Map hook suffixes to page-specific asset slugs.
        $page_map = array(
            'ai-seo-keeper-settings'      => 'settings',
            'ai-seo-keeper-audit'         => 'audit',
            'toplevel_page_ai-seo-keeper' => 'dashboard',
            'ai-seo-keeper-bulk-editor'   => 'bulk-editor',
            'ai-seo-keeper-images'        => 'images',
            'ai-seo-keeper-keywords'      => 'keywords',
            'ai-seo-keeper-redirects'     => 'redirects',
            'ai-seo-keeper-export-import' => 'export-import',
            'ai-seo-keeper-setup'         => 'setup-wizard',
        );

        // Determine the page slug from the hook suffix.
        $page_slug = '';
        foreach ($page_map as $hook_fragment => $slug) {
            if (false !== strpos($hook_suffix, $hook_fragment)) {
                $page_slug = $slug;
                break;
            }
        }

        if ('' === $page_slug) {
            return;
        }

        $css_file = AI_SEO_KEEPER_PATH . 'assets/css/page-' . $page_slug . '.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('ai-seo-page-' . $page_slug, $url . 'css/page-' . $page_slug . '.css', array('ai-seo-admin-common'), $ver);
        }

        $js_file = AI_SEO_KEEPER_PATH . 'assets/js/page-' . $page_slug . '.js';
        if (file_exists($js_file)) {
            wp_enqueue_script('ai-seo-page-' . $page_slug, $url . 'js/page-' . $page_slug . '.js', array('jquery', 'ai-seo-admin-common'), $ver, true);
        }
    }

    private function get_editor_script(): string
    {
        return <<<'JS'
jQuery(function ($) {
    function setAccordionState($toggle, shouldOpen) {
        var targetId = $toggle.attr('aria-controls');
        var $panel = $('#' + targetId);

        $toggle.attr('aria-expanded', shouldOpen ? 'true' : 'false');
        $toggle.find('.ai-seo-keeper-accordion-symbol').text(shouldOpen ? '-' : '+');
        $panel.prop('hidden', ! shouldOpen);
    }

    function activateTab($panel, targetId) {
        if (! targetId) {
            return;
        }

        $panel.find('.ai-seo-keeper-tab-button').each(function () {
            var $button = $(this);
            var isActive = $button.data('tabTarget') === targetId;

            $button.attr('aria-selected', isActive ? 'true' : 'false');
            $button.toggleClass('is-active', isActive);
        });

        $panel.find('.ai-seo-keeper-tab-panel').each(function () {
            var $tabPanel = $(this);
            $tabPanel.prop('hidden', $tabPanel.attr('id') !== targetId);
        });
    }

    function updateSearchPreview($panel) {
        var $preview = $panel.find('.ai-seo-keeper-search-preview');

        if (! $preview.length) {
            return;
        }

        var fallbackTitle = $preview.data('fallbackTitle') || '';
        var fallbackDescription = $preview.data('fallbackDescription') || '';
        var previewUrl = $preview.data('previewUrl') || '';
        var fallbackImage = $preview.data('fallbackImage') || '';
        var title = $.trim($panel.find('#ai-seo-keeper-meta-title').val()) || fallbackTitle;
        var description = $.trim($panel.find('#ai-seo-keeper-meta-description').val()) || fallbackDescription;
        var brandingSuffix = $preview.data('brandingSuffix') || '';
        var brandingOff = $panel.find('#ai-seo-keeper-title-branding-off').is(':checked');
        var displayTitle = (brandingOff || !brandingSuffix) ? title : title + brandingSuffix;
        var previewImage = $.trim($panel.find('#ai-seo-keeper-social-image').val()) || fallbackImage;
        var $image = $preview.find('.ai-seo-keeper-preview-image');
        var $placeholder = $preview.find('.ai-seo-keeper-preview-image-placeholder');

        $preview.find('.ai-seo-keeper-preview-title').text(displayTitle);
        $preview.find('.ai-seo-keeper-preview-description').text(description);
        $preview.find('.ai-seo-keeper-preview-url').text(previewUrl);

        if (previewImage) {
            $image.attr('src', previewImage).prop('hidden', false);
            $placeholder.prop('hidden', true);
            return;
        }

        $image.attr('src', '').prop('hidden', true);
        $placeholder.prop('hidden', false);
    }

    function updateSocialImageCard($panel) {
        var $input = $panel.find('#ai-seo-keeper-social-image');
        var $frame = $panel.find('.ai-seo-keeper-preview-image-card-frame');

        if (! $input.length || ! $frame.length) {
            return;
        }

        var overrideImage = $.trim($input.val());
        var fallbackImage = $panel.find('.ai-seo-keeper-search-preview').data('fallbackImage') || '';
        var effectiveImage = overrideImage || fallbackImage;
        var $image = $frame.find('.ai-seo-keeper-preview-image-card-image');
        var $placeholder = $frame.find('.ai-seo-keeper-preview-image-card-empty');

        $panel.find('.ai-seo-keeper-remove-social-image').prop('disabled', ! overrideImage);

        if (effectiveImage) {
            $image.attr('src', effectiveImage).prop('hidden', false);
            $placeholder.prop('hidden', true);
            return;
        }

        $image.attr('src', '').prop('hidden', true);
        $placeholder.prop('hidden', false);
    }

    function updateSocialPreviewCards($panel) {
        var $cards = $panel.find('.ai-seo-keeper-social-preview-card');

        if (! $cards.length) {
            return;
        }

        var $searchPreview = $panel.find('.ai-seo-keeper-search-preview');
        var fallbackTitle = $searchPreview.data('fallbackTitle') || '';
        var fallbackDescription = $searchPreview.data('fallbackDescription') || '';
        var previewUrl = $searchPreview.data('previewUrl') || '';
        var fallbackImage = $searchPreview.data('fallbackImage') || '';
        var seoTitle = normalizeSeoDraftText($panel.find('#ai-seo-keeper-meta-title').val()) || fallbackTitle;
        var seoDescription = normalizeSeoDraftText($panel.find('#ai-seo-keeper-meta-description').val()) || fallbackDescription;
        var socialTitle = normalizeSeoDraftText($panel.find('#ai-seo-keeper-social-title').val()) || seoTitle;
        var socialDescription = normalizeSeoDraftText($panel.find('#ai-seo-keeper-social-description').val()) || seoDescription;
        var socialImage = $.trim($panel.find('#ai-seo-keeper-social-image').val()) || fallbackImage;

        $cards.each(function () {
            var $card = $(this);
            var $image = $card.find('.ai-seo-keeper-social-preview-image');
            var $placeholder = $card.find('.ai-seo-keeper-social-preview-placeholder');

            $card.find('.ai-seo-keeper-social-preview-title').text(socialTitle);
            $card.find('.ai-seo-keeper-social-preview-description').text(socialDescription);
            $card.find('.ai-seo-keeper-social-preview-url').text(previewUrl);

            if (socialImage) {
                $image.attr('src', socialImage).prop('hidden', false);
                $placeholder.prop('hidden', true);
            } else {
                $image.attr('src', '').prop('hidden', true);
                $placeholder.prop('hidden', false);
            }
        });
    }

    function normalizeSeoDraftText(value) {
        return $.trim(String(value || '').replace(/\s+/g, ' '));
    }

    function updateFieldCharacterCounters($panel) {
        var brandingOff = $panel.find('#ai-seo-keeper-title-branding-off').is(':checked');

        $panel.find('.ai-seo-keeper-field-counter').each(function () {
            var $counter = $(this);
            var fieldId = $counter.data('fieldId');
            var $field = $panel.find('#' + fieldId);
            var maxLength = parseInt($field.attr('maxlength'), 10) || 0;
            var currentLength = String($field.val() || '').length;
            var suffixLength = parseInt($counter.data('brandingSuffixLength'), 10) || 0;

            if (! $field.length || ! maxLength) {
                return;
            }

            // Only apply branding suffix to the title field and when branding is on
            var isTitleField = (fieldId === 'ai-seo-keeper-meta-title');
            var effectiveSuffix = (isTitleField && !brandingOff) ? suffixLength : 0;
            var totalLength = currentLength + effectiveSuffix;

            $counter.removeClass('is-neutral is-warning is-limit');

            if (totalLength > maxLength) {
                $counter.addClass('is-limit');
            } else if (totalLength >= Math.max(1, maxLength - 10)) {
                $counter.addClass('is-warning');
            } else {
                $counter.addClass('is-neutral');
            }

            if (effectiveSuffix > 0) {
                $counter.text(currentLength + ' + ' + effectiveSuffix + ' (brand) = ' + totalLength + ' / ' + maxLength + ' characters');
            } else {
                $counter.text(currentLength + ' / ' + maxLength + ' characters');
            }
        });
    }

    function setSnippetMetricState($metric, stateClass, valueText, helperText) {
        $metric.removeClass('is-good is-warning is-neutral').addClass(stateClass);
        $metric.find('.ai-seo-keeper-snippet-metric-value').text(valueText);
        $metric.find('.ai-seo-keeper-snippet-metric-helper').text(helperText);
    }

    function updateSnippetAnalyzer($panel) {
        var $analyzer = $panel.find('.ai-seo-keeper-snippet-analyzer');
        var limits = aiSeoKeeperEditor.limits || {};
        var titleMin = parseInt(limits.titleMin, 10) || 30;
        var titleMax = parseInt(limits.titleMax, 10) || 60;
        var descriptionMin = parseInt(limits.descriptionMin, 10) || 70;
        var descriptionMax = parseInt(limits.descriptionMax, 10) || 155;

        if (! $analyzer.length) {
            return;
        }

        var brandingOff = $panel.find('#ai-seo-keeper-title-branding-off').is(':checked');
        var brandingSuffixLen = parseInt(aiSeoKeeperEditor.brandingSuffixLength || 0, 10);
        var effectiveSuffix = brandingOff ? 0 : brandingSuffixLen;

        var title = normalizeSeoDraftText($panel.find('#ai-seo-keeper-meta-title').val());
        var description = normalizeSeoDraftText($panel.find('#ai-seo-keeper-meta-description').val());
        var keyphrase = normalizeSeoDraftText($panel.find('#ai-seo-keeper-focus-keyphrase').val()).toLowerCase();
        var titleLength = title.length + effectiveSuffix;
        var descriptionLength = description.length;
        var titleLower = title.toLowerCase();
        var descriptionLower = description.toLowerCase();
        var totalChecks = 2;
        var passedChecks = 0;
        var score = 0;
        var scoreState = 'is-warning';
        var scoreLabel = 'Needs work';
        var scoreSummary = 'Tune the draft length and keyphrase placement to get closer to a search-friendly snippet.';
        var $score = $analyzer.find('.ai-seo-keeper-snippet-score');

        if (! titleLength) {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="title-length"]'),
                'is-neutral',
                '0 chars',
                'Add a title draft to start scoring.'
            );
        } else if (titleLength < titleMin) {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="title-length"]'),
                'is-warning',
                titleLength + ' chars',
                'Too short. Aim for roughly ' + titleMin + '-' + titleMax + ' characters.'
            );
        } else if (titleLength <= titleMax) {
            passedChecks += 1;
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="title-length"]'),
                'is-good',
                titleLength + ' chars',
                'Strong range for search results.'
            );
        } else {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="title-length"]'),
                'is-warning',
                titleLength + ' chars',
                'Too long. Keep it at or below ' + titleMax + ' characters.'
            );
        }

        if (! descriptionLength) {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="description-length"]'),
                'is-neutral',
                '0 chars',
                'Add a meta description to score it.'
            );
        } else if (descriptionLength < descriptionMin) {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="description-length"]'),
                'is-warning',
                descriptionLength + ' chars',
                'Too short. Aim for roughly ' + descriptionMin + '-' + descriptionMax + ' characters.'
            );
        } else if (descriptionLength <= descriptionMax) {
            passedChecks += 1;
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="description-length"]'),
                'is-good',
                descriptionLength + ' chars',
                'Strong range for search snippets.'
            );
        } else {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="description-length"]'),
                'is-warning',
                descriptionLength + ' chars',
                'Too long. Keep it at or below ' + descriptionMax + ' characters.'
            );
        }

        if (keyphrase) {
            totalChecks += 2;

            if (titleLower.indexOf(keyphrase) !== -1) {
                passedChecks += 1;
                setSnippetMetricState(
                    $analyzer.find('[data-snippet-metric="keyphrase-title"]'),
                    'is-good',
                    'Found',
                    'The focus keyphrase appears in the title draft.'
                );
            } else {
                setSnippetMetricState(
                    $analyzer.find('[data-snippet-metric="keyphrase-title"]'),
                    'is-warning',
                    'Missing',
                    'Use the focus keyphrase naturally in the title draft.'
                );
            }

            if (descriptionLower.indexOf(keyphrase) !== -1) {
                passedChecks += 1;
                setSnippetMetricState(
                    $analyzer.find('[data-snippet-metric="keyphrase-description"]'),
                    'is-good',
                    'Found',
                    'The focus keyphrase appears in the description draft.'
                );
            } else {
                setSnippetMetricState(
                    $analyzer.find('[data-snippet-metric="keyphrase-description"]'),
                    'is-warning',
                    'Missing',
                    'Use the focus keyphrase naturally in the description draft.'
                );
            }
        } else {
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="keyphrase-title"]'),
                'is-neutral',
                'Waiting',
                'Add a focus keyphrase to evaluate title relevance.'
            );
            setSnippetMetricState(
                $analyzer.find('[data-snippet-metric="keyphrase-description"]'),
                'is-neutral',
                'Waiting',
                'Add a focus keyphrase to evaluate description relevance.'
            );
        }

        if (titleLength || descriptionLength || keyphrase) {
            score = Math.round((passedChecks / totalChecks) * 100);
        }

        if (! titleLength && ! descriptionLength) {
            scoreState = 'is-neutral';
            scoreLabel = 'Start writing';
            scoreSummary = 'Add a title and description, then tune length and keyphrase coverage.';
        } else if (score >= 75) {
            scoreState = 'is-good';
            scoreLabel = 'Strong';
            scoreSummary = 'Title and description are well-sized and contain your focus keyphrase.';
        } else if (score >= 50) {
            scoreState = 'is-neutral';
            scoreLabel = 'Fair';
            scoreSummary = 'Almost there — adjust title/description length or add your focus keyphrase.';
        }

        $analyzer.removeClass('is-good is-warning is-neutral').addClass(scoreState);
        $score.removeClass('is-good is-warning is-neutral').addClass(scoreState);
        $score.find('.ai-seo-keeper-snippet-score-number').text(score);
        $score.find('.ai-seo-keeper-snippet-score-label').text(scoreLabel);
        $analyzer.find('.ai-seo-keeper-snippet-summary-text').text(scoreSummary);
        $analyzer.find('.ai-seo-keeper-snippet-score-fill').css('width', score + '%');
    }

    function refreshSeoDraftFeedback($panel) {
        updateFieldCharacterCounters($panel);
        updateSearchPreview($panel);
        updateSnippetAnalyzer($panel);
        updateSocialImageCard($panel);
        updateSocialPreviewCards($panel);
    }

    $(document).on('click', '.ai-seo-keeper-tab-button', function (event) {
        event.preventDefault();
        activateTab($(this).closest('.ai-seo-keeper-editor-panel'), $(this).data('tabTarget'));
    });

    $(document).on('click', '.ai-seo-keeper-accordion-toggle', function (event) {
        event.preventDefault();

        var $toggle = $(this);
        var isOpen = $toggle.attr('aria-expanded') === 'true';
        setAccordionState($toggle, ! isOpen);
    });

    $(document).on('click', '.ai-seo-keeper-open-media', function (event) {
        event.preventDefault();

        if (typeof wp === 'undefined' || ! wp.media) {
            return;
        }

        var $button = $(this);
        var $panel = $button.closest('.ai-seo-keeper-editor-panel');
        var frame = $panel.data('aiSeoKeeperMediaFrame');

        if (! frame) {
            frame = wp.media({
                title: 'Choose social image',
                button: {
                    text: 'Use this image'
                },
                library: {
                    type: 'image'
                },
                multiple: false
            });

            frame.on('select', function () {
                var selection = frame.state().get('selection').first();

                if (! selection) {
                    return;
                }

                var attachment = selection.toJSON();
                $panel.find('#ai-seo-keeper-social-image').val(attachment.url || '').trigger('input');
            });

            $panel.data('aiSeoKeeperMediaFrame', frame);
        }

        frame.open();
    });

    $(document).on('click', '.ai-seo-keeper-remove-social-image', function (event) {
        event.preventDefault();

        var $panel = $(this).closest('.ai-seo-keeper-editor-panel');
        $panel.find('#ai-seo-keeper-social-image').val('').trigger('input').trigger('focus');
    });

    $(document).on('click', '.ai-seo-keeper-approve-suggestion', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $panel = $button.closest('.ai-seo-keeper-editor-panel');
        var $status = $panel.find('.ai-seo-keeper-save-status');
        var $notes = $panel.find('.ai-seo-keeper-ai-notes');
        var postId = $('#post_ID').val();
        var messageId = $button.data('messageId');

        if (! postId || ! messageId) {
            $status.text(aiSeoKeeperEditor.approveErrorText).css('color', '#8a2424');
            return;
        }

        $button.prop('disabled', true);
        $status.text(aiSeoKeeperEditor.approvingText).css('color', 'inherit');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: aiSeoKeeperEditor.approveAction,
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId,
            message_id: messageId
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    $('#ai-seo-keeper-meta-title').val(response.data.seoTitle || '');
                    $('#ai-seo-keeper-meta-description').val(response.data.metaDescription || '');

                    if (response.data.notes) {
                        $notes.text(response.data.notes);
                    }

                    if (response.data.historyHtml) {
                        $panel.find('.ai-seo-keeper-history-shell').html(response.data.historyHtml);
                    }

                    if (response.data.analysisHtml) {
                        $panel.find('.ai-seo-keeper-analysis-shell').html(response.data.analysisHtml);
                    }

                    refreshSeoDraftFeedback($panel);

                    $status.text(response.data.message || aiSeoKeeperEditor.approvedText).css('color', '#135e16');
                    return;
                }

                $status.text(aiSeoKeeperEditor.approveErrorText).css('color', '#8a2424');
            })
            .fail(function (xhr) {
                var message = aiSeoKeeperEditor.approveErrorText;

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }

                $status.text(message).css('color', '#8a2424');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.ai-seo-keeper-generate-draft', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $panel = $button.closest('.ai-seo-keeper-editor-panel');
        var $status = $panel.find('.ai-seo-keeper-save-status');
        var $notes = $panel.find('.ai-seo-keeper-ai-notes');
        var postId = $('#post_ID').val();

        if (! postId) {
            $status.text(aiSeoKeeperEditor.missingPostText).css('color', '#8a2424');
            return;
        }

        $button.prop('disabled', true);
        $status.text(aiSeoKeeperEditor.generatingText).css('color', 'inherit');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: aiSeoKeeperEditor.generateAction,
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    $('#ai-seo-keeper-meta-title').val(response.data.seoTitle || '');
                    $('#ai-seo-keeper-meta-description').val(response.data.metaDescription || '');

                    if (response.data.notes) {
                        $notes.text(response.data.notes);
                    }

                    if (response.data.historyHtml) {
                        $panel.find('.ai-seo-keeper-history-shell').html(response.data.historyHtml);
                    }

                    if (response.data.analysisHtml) {
                        $panel.find('.ai-seo-keeper-analysis-shell').html(response.data.analysisHtml);
                    }

                    refreshSeoDraftFeedback($panel);

                    $status.text(response.data.message || aiSeoKeeperEditor.generatedText).css('color', '#135e16');
                    return;
                }

                $status.text(aiSeoKeeperEditor.generateErrorText).css('color', '#8a2424');
            })
            .fail(function (xhr) {
                var message = aiSeoKeeperEditor.generateErrorText;

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }

                $status.text(message).css('color', '#8a2424');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.ai-seo-keeper-save-draft', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $panel = $button.closest('.ai-seo-keeper-editor-panel');
        var $status = $panel.find('.ai-seo-keeper-save-status');
        var postId = $('#post_ID').val();

        if (! postId) {
            $status.text(aiSeoKeeperEditor.missingPostText).css('color', '#8a2424');
            return;
        }

        $button.prop('disabled', true);
        $status.text(aiSeoKeeperEditor.savingText).css('color', 'inherit');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: aiSeoKeeperEditor.saveAction,
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId,
            ai_seo_keeper_focus_keyphrase: $('#ai-seo-keeper-focus-keyphrase').val(),
            ai_seo_keeper_meta_title: $('#ai-seo-keeper-meta-title').val(),
            ai_seo_keeper_meta_description: $('#ai-seo-keeper-meta-description').val(),
            ai_seo_keeper_social_title: $('#ai-seo-keeper-social-title').val(),
            ai_seo_keeper_social_description: $('#ai-seo-keeper-social-description').val(),
            ai_seo_keeper_social_image: $('#ai-seo-keeper-social-image').val(),
            ai_seo_keeper_schema_type: $('#ai-seo-keeper-schema-type').val(),
            ai_seo_keeper_canonical_url: $('#ai-seo-keeper-canonical-url').val(),
            ai_seo_keeper_robots_directives: $('#ai-seo-keeper-robots-directives').val(),
            ai_seo_keeper_frontend_enabled: $('#ai-seo-keeper-frontend-enabled').is(':checked') ? 1 : 0,
            ai_seo_keeper_cornerstone: $('#ai-seo-keeper-cornerstone').is(':checked') ? 1 : 0,
            ai_seo_keeper_hreflang: $('#ai-seo-keeper-hreflang').val()
        })
            .done(function (response) {
                if (response && response.success) {
                    if (response.data.analysisHtml) {
                        $panel.find('.ai-seo-keeper-analysis-shell').html(response.data.analysisHtml);
                    }

                    $status.text(aiSeoKeeperEditor.savedText + ' ' + response.data.savedAt).css('color', '#135e16');
                    return;
                }

                $status.text(aiSeoKeeperEditor.errorText).css('color', '#8a2424');
            })
            .fail(function (xhr) {
                var message = aiSeoKeeperEditor.errorText;

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }

                $status.text(message).css('color', '#8a2424');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.ai-seo-keeper-send-chat', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $panel = $button.closest('.ai-seo-keeper-editor-panel');
        var $chatStatus = $button.closest('.ai-seo-keeper-assistant-panel').find('.ai-seo-keeper-chat-status');
        var $status = $chatStatus.length ? $chatStatus : $panel.find('.ai-seo-keeper-save-status');
        var $input = $panel.find('.ai-seo-keeper-chat-input');
        var postId = $('#post_ID').val();
        var message = $.trim($input.val());

        if (! postId) {
            $status.text(aiSeoKeeperEditor.missingPostText).css('color', '#8a2424');
            return;
        }

        if (! message) {
            $status.text(aiSeoKeeperEditor.emptyChatText).css('color', '#8a2424');
            return;
        }

        $button.prop('disabled', true);
        $status.text(aiSeoKeeperEditor.chattingText).css('color', 'inherit');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: aiSeoKeeperEditor.chatAction,
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId,
            message: message
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    if (response.data.chatHtml) {
                        $panel.find('.ai-seo-keeper-chat-shell').html(response.data.chatHtml);
                    }

                    if (response.data.notes) {
                        $panel.find('.ai-seo-keeper-ai-notes').text(response.data.notes);
                    }

                    $input.val('');

                    // If AI triggered content edit proposals, render diff cards.
                    var $review = $panel.find('.ai-seo-keeper-content-review');
                    if (response.data.changes && response.data.changes.length) {
                        var changes = response.data.changes;
                        var html = '<div class="ai-seo-keeper-diff-review">';
                        html += '<p style="margin:0 0 12px;font-size:13px;color:#50575e;">' + escHtml(response.data.summary || '') + '</p>';
                        html += '<p style="margin:0 0 8px;font-size:13px;font-weight:600;">' + changes.length + ' proposed change(s) — review and accept individually:</p>';

                        for (var i = 0; i < changes.length; i++) {
                            var ch = changes[i];
                            html += '<div class="ai-seo-keeper-diff-card" data-idx="' + i + '" style="border:1px solid #dcdcde;border-radius:4px;padding:12px;margin-bottom:10px;background:#fff;">';
                            html += '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">';
                            html += '<strong style="font-size:13px;">' + escHtml(ch.section) + '</strong>';
                            if (ch.tag_change) html += ' <span style="font-size:11px;background:#e5f5fa;color:#0a4b78;padding:1px 6px;border-radius:3px;">' + escHtml(ch.tag_change) + '</span>';
                            html += '</div>';
                            html += '<div style="display:flex;gap:12px;margin-bottom:8px;">';
                            html += '<div style="flex:1;"><span style="font-size:11px;color:#8a2424;font-weight:600;">BEFORE</span><div style="font-size:13px;background:#fef0f0;padding:6px 8px;border-radius:3px;word-break:break-word;">' + escHtml(ch.old) + '</div></div>';
                            html += '<div style="flex:1;"><span style="font-size:11px;color:#135e16;font-weight:600;">AFTER</span><div style="font-size:13px;background:#eef8ee;padding:6px 8px;border-radius:3px;word-break:break-word;">' + escHtml(ch.new) + '</div></div>';
                            html += '</div>';
                            if (ch.reason) html += '<p style="font-size:12px;color:#787c82;margin:0 0 8px;font-style:italic;">' + escHtml(ch.reason) + '</p>';
                            html += '<label style="font-size:13px;cursor:pointer;"><input type="checkbox" class="ai-seo-keeper-diff-accept" data-idx="' + i + '" checked /> Accept this change</label>';
                            html += '</div>';
                        }

                        html += '<div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
                        html += '<button type="button" class="button ai-seo-keeper-toggle-all-changes" style="font-size:12px;">Deselect All</button>';
                        html += '<button type="button" class="button button-primary ai-seo-keeper-apply-content-changes">Approve Selected</button>';
                        html += '<button type="button" class="button ai-seo-keeper-discard-changes">Disregard</button>';
                        html += '<span class="ai-seo-keeper-apply-status" style="font-size:13px;"></span>';
                        html += '</div>';
                        html += '</div>';

                        $review.html(html);
                        $review.data('proposedChanges', changes);
                        $review.data('changeSummary', response.data.summary || '');
                        $status.text('Review the proposed changes below.').css('color', '#135e16');
                    } else {
                        $review.empty();
                        $status.text(response.data.message || aiSeoKeeperEditor.chatReplyText).css('color', '#135e16');
                    }
                    return;
                }

                $status.text(aiSeoKeeperEditor.chatErrorText).css('color', '#8a2424');
            })
            .fail(function (xhr) {
                var messageText = aiSeoKeeperEditor.chatErrorText;

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    messageText = xhr.responseJSON.data.message;
                }

                $status.text(messageText).css('color', '#8a2424');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    // ── Apply suggestion button (title / description from chat) ──────
    $(document).on('click', '.ai-seo-keeper-apply-suggestion', function (event) {
        event.preventDefault();
        var $btn = $(this);
        var field = $btn.data('field');
        var value = $btn.data('value');
        var postId = $('#post_ID').val();
        var $panel = $btn.closest('.ai-seo-keeper-editor-panel');

        $btn.prop('disabled', true).text('Applying…');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: 'ai_seo_keeper_apply_suggestion',
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId,
            field: field,
            value: value
        })
        .done(function (response) {
            if (response && response.success) {
                if (field === 'meta_title') {
                    $('#ai-seo-keeper-meta-title').val(value).trigger('input');
                } else if (field === 'meta_description') {
                    $('#ai-seo-keeper-meta-description').val(value).trigger('input');
                }
                $btn.text('✓ Applied').css('color', '#135e16');
                if ($panel.length) {
                    refreshSeoDraftFeedback($panel);
                }
            } else {
                $btn.text('Failed').css('color', '#8a2424');
            }
        })
        .fail(function () {
            $btn.text('Failed').css('color', '#8a2424');
        });
    });

    // Approve selected content changes (stored as pending — applied on Update/Publish).
    $(document).on('click', '.ai-seo-keeper-apply-content-changes', function () {
        var $btn = $(this);
        var $contentReview = $btn.closest('.ai-seo-keeper-content-review');
        var $review = $btn.closest('.ai-seo-keeper-diff-review');
        var $statusEl = $review.find('.ai-seo-keeper-apply-status');
        var allChanges = $contentReview.data('proposedChanges');
        var summary = $contentReview.data('changeSummary') || '';
        var postId = $('#post_ID').val();

        var accepted = [];
        $review.find('.ai-seo-keeper-diff-accept:checked').each(function () {
            var idx = parseInt($(this).data('idx'), 10);
            if (allChanges[idx]) {
                accepted.push({ old: allChanges[idx].old, 'new': allChanges[idx]['new'], section: allChanges[idx].section || '' });
            }
        });

        if (accepted.length === 0) {
            $statusEl.text('No changes selected.').css('color', '#8a2424');
            return;
        }

        $btn.prop('disabled', true).text('Approving…');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: 'ai_seo_keeper_apply_changes',
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId,
            changes: JSON.stringify(accepted),
            summary: summary
        })
        .done(function (response) {
            if (response && response.success && response.data) {
                var d = response.data;
                $statusEl.text('✓ ' + d.applied + ' change(s) approved — click Update/Publish to make them live.').css('color', '#135e16');
                $btn.text('✓ Approved').prop('disabled', true);
                $review.find('.ai-seo-keeper-discard-changes, .ai-seo-keeper-toggle-all-changes').hide();
                $review.find('.ai-seo-keeper-diff-accept').prop('disabled', true);
                // Show pending notice at the top of the panel.
                var $panel = $btn.closest('.ai-seo-keeper-editor-panel');
                if (! $panel.find('.ai-seo-keeper-pending-notice').length) {
                    $panel.find('.ai-seo-keeper-toolbar').after(
                        '<div class="ai-seo-keeper-pending-notice" style="background:#fff8e1;border-left:4px solid #ffb300;padding:8px 12px;margin:8px 0;font-size:13px;">' +
                        '⏳ <strong>' + d.applied + ' AI content change(s) pending</strong> — Preview the page, then click <strong>Update</strong> to publish them.' +
                        '</div>'
                    );
                }
            } else {
                $statusEl.text('Approval failed.').css('color', '#8a2424');
            }
        })
        .fail(function () {
            $statusEl.text('Approval failed.').css('color', '#8a2424');
        })
        .always(function () {
            if ($btn.text() !== '✓ Approved') $btn.prop('disabled', false);
        });
    });

    // Toggle select/deselect all checkboxes.
    $(document).on('click', '.ai-seo-keeper-toggle-all-changes', function () {
        var $btn = $(this);
        var $review = $btn.closest('.ai-seo-keeper-diff-review');
        var $boxes = $review.find('.ai-seo-keeper-diff-accept');
        var allChecked = $boxes.length === $boxes.filter(':checked').length;
        $boxes.prop('checked', ! allChecked);
        $btn.text(allChecked ? 'Select All' : 'Deselect All');
    });

    // Disregard — collapse the diff review but keep it recoverable via chat context.
    $(document).on('click', '.ai-seo-keeper-discard-changes', function () {
        var $review = $(this).closest('.ai-seo-keeper-content-review');
        $review.find('.ai-seo-keeper-diff-review').slideUp(200);
        var $chatStatus = $review.closest('.ai-seo-keeper-assistant-panel').find('.ai-seo-keeper-chat-status');
        if ($chatStatus.length) {
            $chatStatus.text('Changes disregarded. You can ask AI for a different plan.').css('color', '#787c82');
        }
    });

    // ── Clear chat conversation ───────────────────────────────────────
    $(document).on('click', '.ai-seo-keeper-clear-chat', function () {
        var $btn = $(this);
        var $panel = $btn.closest('.ai-seo-keeper-editor-panel');
        var postId = $('#post_ID').val();

        if (! confirm('Clear all chat messages for this page? This cannot be undone.')) return;

        $btn.prop('disabled', true);

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: 'ai_seo_keeper_clear_chat',
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId
        })
        .done(function (response) {
            if (response && response.success) {
                $panel.find('.ai-seo-keeper-chat-shell').empty();
                $panel.find('.ai-seo-keeper-content-review').empty();
            }
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.ai-seo-keeper-restore-backup', function () {
        var $btn = $(this);
        var postId = $('#post_ID').val();
        $btn.prop('disabled', true).text('Restoring…');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: 'ai_seo_keeper_restore_backup',
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId
        })
        .done(function (response) {
            if (response && response.success) {
                $btn.text('✓ Restored — reload page to see changes');
            } else {
                $btn.text('Restore failed').css('color', '#8a2424');
            }
        })
        .fail(function () {
            $btn.text('Restore failed').css('color', '#8a2424');
        });
    });

    // ── Toggle content edit plan details in History ───────────────────
    $(document).on('click', '.ai-seo-keeper-toggle-edit-details', function () {
        var $details = $(this).closest('.ai-seo-keeper-history-item').find('.ai-seo-keeper-edit-details');
        if ($details.is(':visible')) {
            $details.slideUp(200);
            $(this).html($(this).html().replace('▾', '▸'));
        } else {
            $details.slideDown(200);
            $(this).html($(this).html().replace('▸', '▾'));
        }
    });

    // ── Delete content edit plan from History ─────────────────────────
    $(document).on('click', '.ai-seo-keeper-delete-edit-plan', function () {
        var $btn = $(this);
        var editId = $btn.data('editId');
        var postId = $('#post_ID').val();

        if (! confirm('Remove this content edit plan from history?')) return;

        $btn.prop('disabled', true);

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: 'ai_seo_keeper_delete_edit_plan',
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId,
            edit_id: editId
        })
        .done(function (response) {
            if (response && response.success) {
                $btn.closest('.ai-seo-keeper-history-item').slideUp(200, function () {
                    $(this).remove();
                });
            }
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ── Help-tip tooltip positioning ──────────────────────────────────
    $(document).on('mouseenter', '.ai-seo-keeper-help-tip', function () {
        var rect = this.getBoundingClientRect();
        var style = document.documentElement.style;
        style.setProperty('--tip-top', Math.max(0, rect.top - 4) + 'px');
        style.setProperty('--tip-left', Math.min(rect.left, window.innerWidth - 320) + 'px');
    });

    // ── AI Assistant sub-tab switching ────────────────────────────────
    $(document).on('click', '.ai-seo-keeper-assistant-tab', function () {
        var $tab = $(this);
        var target = $tab.data('target');
        var $group = $tab.closest('.ai-seo-keeper-accordion-panel');

        $group.find('.ai-seo-keeper-assistant-tab').removeClass('is-active').css({'border-bottom-color': 'transparent', 'color': '#787c82'});
        $tab.addClass('is-active').css({'border-bottom-color': '#2271b1', 'color': '#1d2327'});

        $group.find('.ai-seo-keeper-assistant-panel').hide();
        $group.find('.ai-seo-keeper-assistant-panel[data-panel="' + target + '"]').show();
    });

    // ── Run AI Page Audit from editor ─────────────────────────────────
    $(document).on('click', '.ai-seo-keeper-run-page-audit', function () {
        var $btn = $(this);
        var $status = $btn.closest('div').find('.ai-seo-keeper-audit-status');
        var postId = $('#post_ID').val();

        $btn.prop('disabled', true);
        $status.text('Running AI audit…').css('color', 'inherit');

        $.post(aiSeoKeeperEditor.ajaxUrl, {
            action: 'ai_seo_keeper_page_audit',
            nonce: $('#ai_seo_keeper_editor_nonce').val(),
            post_id: postId
        })
        .done(function (response) {
            if (response && response.success && response.data) {
                var d = response.data;
                var scoreColor = d.score >= 70 ? '#00a32a' : (d.score >= 40 ? '#dba617' : '#d63638');
                var html = 'AI SEO Score: <strong style="color:' + scoreColor + ';">' + d.score + '/100</strong>';

                if (d.issues && d.issues.length > 0) {
                    html += ' — ' + d.issues.length + ' issue(s):';
                    html += '<ul style="margin:6px 0 0 18px;font-size:12px;color:#50575e;">';
                    for (var i = 0; i < d.issues.length; i++) {
                        html += '<li>' + escHtml(d.issues[i]) + '</li>';
                    }
                    html += '</ul>';
                } else {
                    html += ' — No issues found.';
                }

                if (d.suggestions && d.suggestions.length > 0) {
                    html += '<div style="margin-top:6px;font-size:12px;color:#135e16;"><strong>Suggestions:</strong><ul style="margin:4px 0 0 18px;">';
                    for (var j = 0; j < d.suggestions.length; j++) {
                        html += '<li>' + escHtml(d.suggestions[j]) + '</li>';
                    }
                    html += '</ul></div>';
                }

                $status.html(html).css('color', '#1d2327');

                // Update the score badge inline.
                var $scoreEl = $btn.closest('.ai-seo-keeper-snippet-analyzer').find('.ai-seo-keeper-snippet-score-number');
                // No — that's the metadata fit score, don't overwrite it.
            } else {
                $status.text('Audit failed.').css('color', '#8a2424');
            }
        })
        .fail(function (xhr) {
            var msg = 'Audit failed.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            $status.text(msg).css('color', '#8a2424');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('input', '#ai-seo-keeper-focus-keyphrase, #ai-seo-keeper-meta-title, #ai-seo-keeper-meta-description, #ai-seo-keeper-social-title, #ai-seo-keeper-social-description', function () {
        refreshSeoDraftFeedback($(this).closest('.ai-seo-keeper-editor-panel'));
    });

    $(document).on('change', '#ai-seo-keeper-title-branding-off', function () {
        var $panel = $(this).closest('.ai-seo-keeper-editor-panel');
        var isOff = $(this).is(':checked');
        $panel.find('.ai-seo-keeper-branding-suffix').toggle(!isOff);
        refreshSeoDraftFeedback($panel);
    });

    $('.ai-seo-keeper-editor-panel').each(function () {
        var $panel = $(this);
        var firstTab = $panel.find('.ai-seo-keeper-tab-button').first().data('tabTarget');

        if (firstTab) {
            activateTab($panel, firstTab);
        }

        $panel.find('.ai-seo-keeper-accordion-toggle').each(function () {
            var $toggle = $(this);
            setAccordionState($toggle, String($toggle.data('defaultOpen')) === '1');
        });

        refreshSeoDraftFeedback($panel);
    });

    $(document).on('input', '#ai-seo-keeper-social-image', function () {
        refreshSeoDraftFeedback($(this).closest('.ai-seo-keeper-editor-panel'));
    });
});
JS;
    }

    public function render_dashboard(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $summary                     = $this->content_indexer->get_summary();
        $audit_summary               = $this->content_indexer->get_audit_summary();
        $audit_rows                  = $this->content_indexer->get_audit_rows(8);
        $options                     = $this->settings->get();
        $sync_count                  = isset($_GET['synced']) ? (int) $_GET['synced'] : 0;
        $frontend_conflict           = $this->has_conflicting_seo_plugin();
        $frontend_enabled            = ! empty($options['frontend_output_enabled']);
        $frontend_override_conflicts = ! empty($options['frontend_override_conflicts']);
        $llms_url                    = home_url('/llms.txt');
        $llms_full_url               = home_url('/llms-full.txt');
        $sitemap_url                 = $frontend_conflict ? home_url('/sitemap_index.xml') : home_url('/wp-sitemap.xml');

        require __DIR__ . '/admin/view-dashboard.php';
    }

    /**
     * Render the Redirects & 404 Monitor sub-page (delegates to Redirects class).
     */
    public function render_redirects_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $redirects_instance = Plugin::instance()->get_redirects();

        if ($redirects_instance instanceof Redirects) {
            $redirects_instance->render_admin_page();
        } else {
            echo '<div class="wrap"><h1>Redirects</h1><p>Redirects module not available. Please deactivate and reactivate the plugin.</p></div>';
        }
    }

    /**
     * Render the Bulk SEO Editor page — spreadsheet-style table for editing SEO titles and descriptions.
     */
    public function render_bulk_editor_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $post_type_filter = isset($_GET['pt']) ? sanitize_key($_GET['pt']) : 'page';
        $paged            = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page         = 30;

        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']);

        if (! isset($post_types[$post_type_filter])) {
            $post_type_filter = 'page';
        }

        $query = new \WP_Query(array(
            'post_type'      => $post_type_filter,
            'post_status'    => array('publish', 'draft', 'pending'),
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $total_pages    = $query->max_num_pages;
        $nonce          = wp_create_nonce('ai_seo_keeper_nonce');
        $meta_title_key = self::META_TITLE_KEY;
        $meta_desc_key  = self::META_DESCRIPTION_KEY;

        // Pass nonce to external JS via wp_localize_script.
        wp_localize_script('ai-seo-keeper-page-bulk-editor', 'aiSeoBulkEditor', array(
            'nonce' => $nonce,
        ));

        require __DIR__ . '/admin/view-bulk-editor.php';
    }

    /**
     * AJAX handler for bulk saving SEO meta.
     */
    public function handle_ajax_bulk_save_seo(): void
    {
        check_ajax_referer('ai_seo_keeper_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id <= 0 || ! get_post($post_id)) {
            wp_send_json_error('Invalid post ID.');
        }

        $seo_title = isset($_POST['seo_title']) ? sanitize_text_field(wp_unslash($_POST['seo_title'])) : '';
        $seo_description = isset($_POST['seo_description']) ? sanitize_textarea_field(wp_unslash($_POST['seo_description'])) : '';

        update_post_meta($post_id, self::META_TITLE_KEY, $seo_title);
        update_post_meta($post_id, self::META_DESCRIPTION_KEY, $seo_description);

        wp_send_json_success(array('message' => 'Saved.'));
    }

    /**
     * Render the Image SEO dashboard — site-wide image inventory with alt tag status and editing.
     */
    public function render_image_seo_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 40;
        $filter   = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : 'all';

        global $wpdb;

        $published_image_ids_sql = "
            SELECT DISTINCT a.ID FROM {$wpdb->posts} a
            INNER JOIN {$wpdb->posts} parent ON a.post_parent = parent.ID AND parent.post_status = 'publish'
            WHERE a.post_type = 'attachment' AND a.post_mime_type LIKE 'image/%'
            UNION
            SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish'
            INNER JOIN {$wpdb->posts} a ON a.ID = pm.meta_value AND a.post_type = 'attachment' AND a.post_mime_type LIKE 'image/%'
            WHERE pm.meta_key = '_thumbnail_id'
        ";

        $published_image_ids = $wpdb->get_col($published_image_ids_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $published_image_ids = array_map('intval', $published_image_ids);

        if (empty($published_image_ids)) {
            $published_image_ids = array(0);
        }

        $used_on_map = array();

        $featured_lookup = $wpdb->get_results(
            "SELECT pm.meta_value AS att_id, p.ID AS post_id, p.post_title
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish'
            WHERE pm.meta_key = '_thumbnail_id'",
            ARRAY_A
        );
        foreach ($featured_lookup as $fl) {
            $aid = (int) $fl['att_id'];
            $used_on_map[$aid][(int) $fl['post_id']] = $fl['post_title'];
        }

        if (! empty($published_image_ids) && ! (1 === count($published_image_ids) && 0 === $published_image_ids[0])) {
            $content_matches = $wpdb->get_results(
                "SELECT p.ID AS post_id, p.post_title, p.post_content
                FROM {$wpdb->posts} p
                WHERE p.post_status = 'publish'
                AND p.post_type NOT IN ('attachment','revision','nav_menu_item')
                AND (p.post_content LIKE '%wp-image-%' OR p.post_content LIKE '%wp-att-%')",
                ARRAY_A
            );
            foreach ($content_matches as $cm) {
                if (preg_match_all('/wp-(?:image|att)-(\d+)/', $cm['post_content'], $m)) {
                    foreach ($m[1] as $found_id) {
                        $found_id = (int) $found_id;
                        if (in_array($found_id, $published_image_ids, true)) {
                            $used_on_map[$found_id][(int) $cm['post_id']] = $cm['post_title'];
                        }
                    }
                }
            }
        }

        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post__in'       => $published_image_ids,
        );

        if ('missing_alt' === $filter) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array('key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'),
                array('key' => '_wp_attachment_image_alt', 'value' => ''),
            );
        } elseif ('with_alt' === $filter) {
            $args['meta_query'] = array(
                array('key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '!='),
            );
        }

        $query       = new \WP_Query($args);
        $total_pages = $query->max_num_pages;

        $total_images = count($published_image_ids);
        if (1 === $total_images && 0 === $published_image_ids[0]) {
            $total_images = 0;
        }
        $id_list        = implode(',', $published_image_ids);
        $total_with_alt = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt' AND pm.meta_value != ''
            WHERE p.ID IN ({$id_list})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        $total_missing_alt = $total_images - $total_with_alt;

        $nonce = wp_create_nonce('ai_seo_keeper_nonce');

        wp_localize_script('ai-seo-keeper-page-images', 'aiSeoImages', array(
            'nonce' => $nonce,
        ));

        require __DIR__ . '/admin/view-images.php';
    }

    /**
     * AJAX handler for saving image alt text.
     */
    public function handle_ajax_save_image_alt(): void
    {
        check_ajax_referer('ai_seo_keeper_nonce', '_nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        if ($attachment_id <= 0 || 'attachment' !== get_post_type($attachment_id)) {
            wp_send_json_error('Invalid attachment.');
        }

        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field(wp_unslash($_POST['alt_text'])) : '';
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

        wp_send_json_success(array('message' => 'Alt text saved.'));
    }

    /**
     * Render the keyword tracking page — cross-page keyphrase map and cannibalization detection.
     */
    public function render_keyword_tracking_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $keyphrase_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.post_id, pm.meta_value AS keyphrase, p.post_title, p.post_type
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                    AND pm.meta_value != ''
                    AND p.post_status = 'publish'
                ORDER BY LOWER(pm.meta_value) ASC, p.post_title ASC",
                '_ai_seo_keeper_focus_keyphrase'
            ),
            ARRAY_A
        );

        $keyphrase_map        = array();
        $total_with_keyphrase = 0;
        foreach ($keyphrase_rows as $row) {
            $key = strtolower(trim($row['keyphrase']));
            if ('' === $key) {
                continue;
            }
            $total_with_keyphrase++;
            if (! isset($keyphrase_map[$key])) {
                $keyphrase_map[$key] = array(
                    'keyphrase' => $row['keyphrase'],
                    'pages'     => array(),
                );
            }
            $keyphrase_map[$key]['pages'][] = array(
                'id'        => (int) $row['post_id'],
                'title'     => $row['post_title'],
                'post_type' => $row['post_type'],
            );
        }

        $cannibalized = array_filter($keyphrase_map, static function ($group) {
            return count($group['pages']) > 1;
        });

        $total_published   = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_type != 'attachment'"
        );
        $without_keyphrase = $total_published - $total_with_keyphrase;

        require __DIR__ . '/admin/view-keywords.php';
    }

    /**
     * Render the Export / Import page.
     */
    public function render_export_import_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $import_status = isset($_GET['import_status']) ? sanitize_key($_GET['import_status']) : '';
        $import_msg    = isset($_GET['import_msg']) ? sanitize_text_field(wp_unslash($_GET['import_msg'])) : '';

        require __DIR__ . '/admin/view-export-import.php';
    }

    /**
     * Handle JSON export download.
     */
    public function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ai_seo_keeper_export');

        $data = array(
            'plugin' => 'ai-seo-keeper',
            'version' => defined('AI_SEO_KEEPER_VERSION') ? AI_SEO_KEEPER_VERSION : '1.0.0',
            'exported_at' => gmdate('c'),
            'site_url' => home_url('/'),
        );

        // Settings.
        if (! empty($_POST['export_settings'])) {
            $data['settings'] = $this->settings->get();
            // Never export API keys.
            unset($data['settings']['api_key'], $data['settings']['google_api_key']);
        }

        // Per-page SEO metadata.
        if (! empty($_POST['export_seo_meta'])) {
            global $wpdb;
            $meta_keys = array(
                '_ai_seo_keeper_meta_title',
                '_ai_seo_keeper_meta_description',
                '_ai_seo_keeper_focus_keyphrase',
                '_ai_seo_keeper_social_title',
                '_ai_seo_keeper_social_description',
                '_ai_seo_keeper_social_image',
                '_ai_seo_keeper_schema_type',
                '_ai_seo_keeper_canonical_url',
                '_ai_seo_keeper_robots_directives',
                '_ai_seo_keeper_frontend_enabled',
                '_ai_seo_keeper_cornerstone',
                '_ai_seo_keeper_hreflang',
            );
            $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_name, pm.meta_key, pm.meta_value
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key IN ({$placeholders})
                        AND pm.meta_value != ''
                    ORDER BY p.ID ASC",
                    ...$meta_keys
                ),
                ARRAY_A
            );

            $seo_meta = array();
            foreach ($rows as $row) {
                $pid = (int) $row['ID'];
                if (! isset($seo_meta[$pid])) {
                    $seo_meta[$pid] = array(
                        'post_id' => $pid,
                        'post_title' => $row['post_title'],
                        'post_slug' => $row['post_name'],
                        'meta' => array(),
                    );
                }
                $seo_meta[$pid]['meta'][$row['meta_key']] = $row['meta_value'];
            }
            $data['seo_meta'] = array_values($seo_meta);
        }

        // Redirects.
        if (! empty($_POST['export_redirects'])) {
            global $wpdb;
            $redirects_table = $wpdb->prefix . 'ai_seo_keeper_redirects';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $redirects = $wpdb->get_results(
                "SELECT source_url, target_url, status_code, type FROM {$redirects_table} WHERE type = 'redirect' ORDER BY source_url ASC",
                ARRAY_A
            );
            $data['redirects'] = is_array($redirects) ? $redirects : array();
        }

        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ai-seo-keeper-export-' . gmdate('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    /**
     * Handle JSON import upload.
     */
    public function handle_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ai_seo_keeper_import');

        $redirect_url = admin_url('admin.php?page=ai-seo-keeper-export-import');

        if (empty($_FILES['import_file']['tmp_name']) || 0 !== (int) $_FILES['import_file']['error']) {
            wp_redirect(add_query_arg(array('import_status' => 'error', 'import_msg' => 'No file uploaded or upload error.'), $redirect_url));
            exit;
        }

        $json = file_get_contents($_FILES['import_file']['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = json_decode($json, true);

        if (! is_array($data) || 'ai-seo-keeper' !== ($data['plugin'] ?? '')) {
            wp_redirect(add_query_arg(array('import_status' => 'error', 'import_msg' => 'Invalid import file. Must be an AI SEO Keeper export.'), $redirect_url));
            exit;
        }

        $imported = array();

        // Import settings.
        if (! empty($data['settings']) && is_array($data['settings'])) {
            $current = $this->settings->get();
            // Preserve existing API keys — never import them.
            $data['settings']['api_key'] = $current['api_key'] ?? '';
            $data['settings']['google_api_key'] = $current['google_api_key'] ?? '';
            update_option(Settings::OPTION_NAME, wp_parse_args($data['settings'], $current));
            $imported[] = 'settings';
        }

        // Import SEO metadata.
        if (! empty($data['seo_meta']) && is_array($data['seo_meta'])) {
            $meta_count = 0;
            foreach ($data['seo_meta'] as $entry) {
                $post_id = (int) ($entry['post_id'] ?? 0);
                if ($post_id <= 0 || ! get_post($post_id)) {
                    continue;
                }
                if (! empty($entry['meta']) && is_array($entry['meta'])) {
                    foreach ($entry['meta'] as $key => $value) {
                        // Only allow known meta keys.
                        if (0 !== strpos($key, '_ai_seo_keeper_')) {
                            continue;
                        }
                        update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
                    }
                    $meta_count++;
                }
            }
            $imported[] = "{$meta_count} pages SEO data";
        }

        // Import redirects.
        if (! empty($data['redirects']) && is_array($data['redirects'])) {
            $redir_instance = Plugin::instance()->get_redirects();
            $redir_count = 0;
            if ($redir_instance) {
                foreach ($data['redirects'] as $r) {
                    if (! empty($r['source_url']) && ! empty($r['target_url'])) {
                        $redir_instance->add_redirect(
                            sanitize_text_field($r['source_url']),
                            esc_url_raw($r['target_url']),
                            (int) ($r['status_code'] ?? 301)
                        );
                        $redir_count++;
                    }
                }
            }
            $imported[] = "{$redir_count} redirects";
        }

        $msg = empty($imported) ? 'Nothing to import.' : 'Imported: ' . implode(', ', $imported) . '.';
        wp_redirect(add_query_arg(array('import_status' => 'success', 'import_msg' => $msg), $redirect_url));
        exit;
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options              = $this->settings->get();
        $indexnow_enabled     = ! empty($options['indexnow_enabled']);
        $indexnow_auto_submit = ! empty($options['indexnow_auto_submit']);
        $indexnow_key         = isset($options['indexnow_key']) ? (string) $options['indexnow_key'] : '';
        $indexnow_key_url     = $this->indexnow_service ? $this->indexnow_service->get_key_url() : '';
        $settings_status      = isset($_GET['settings_status']) ? sanitize_key((string) wp_unslash($_GET['settings_status'])) : '';
        $settings_message     = isset($_GET['settings_message']) ? sanitize_text_field((string) wp_unslash($_GET['settings_message'])) : '';
        $yoast_import_action  = self::YOAST_IMPORT_ACTION;

        require __DIR__ . '/admin/view-settings.php';
    }

    public function render_audit_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $report        = $this->audit_engine->get_report(12);
        $summary       = $report['summary'];
        $readiness     = $report['readiness'];
        $options       = $this->settings->get();
        $has_api_key   = ! empty($options['api_key']);
        $site_audits   = $this->history_store->get_recent_site_audits(3);
        $audit_status  = isset($_GET['audit_status']) ? sanitize_key((string) wp_unslash($_GET['audit_status'])) : '';
        $audit_message = isset($_GET['audit_message']) ? sanitize_text_field((string) wp_unslash($_GET['audit_message'])) : '';
        $indexnow_enabled     = ! empty($options['indexnow_enabled']);
        $indexnow_auto_submit = ! empty($options['indexnow_auto_submit']);
        $indexnow_key_url     = $this->indexnow_service ? $this->indexnow_service->get_key_url() : '';
        $indexnow_log         = $this->indexnow_service ? $this->indexnow_service->get_log(5) : array();

        $admin                      = $this;
        $generate_site_audit_action = self::GENERATE_SITE_AUDIT_ACTION;
        $submit_indexnow_action     = self::SUBMIT_INDEXNOW_ACTION;
        $bulk_frontend_action       = self::BULK_FRONTEND_ACTION;

        require __DIR__ . '/admin/view-audit.php';
    }

    public function register_editor_metabox(string $post_type, $post): void
    {
        if (! $this->is_supported_post_type($post_type)) {
            return;
        }

        if (! $post || ! current_user_can('edit_post', $post->ID)) {
            return;
        }

        add_meta_box(
            self::META_BOX_ID,
            'AI SEO Keeper',
            array($this, 'render_editor_metabox'),
            $post_type,
            'normal',
            'high'
        );
    }

    public function render_editor_metabox($post): void
    {
        $options          = $this->settings->get();
        $summary          = $this->content_indexer->get_summary();
        $focus_keyphrase  = (string) get_post_meta($post->ID, self::FOCUS_KEYPHRASE_META_KEY, true);
        $seo_title        = (string) get_post_meta($post->ID, self::META_TITLE_KEY, true);
        $seo_description  = (string) get_post_meta($post->ID, self::META_DESCRIPTION_KEY, true);
        $social_title     = (string) get_post_meta($post->ID, self::SOCIAL_TITLE_META_KEY, true);
        $social_description = (string) get_post_meta($post->ID, self::SOCIAL_DESCRIPTION_META_KEY, true);
        $social_image     = (string) get_post_meta($post->ID, self::SOCIAL_IMAGE_META_KEY, true);
        $schema_type      = (string) get_post_meta($post->ID, self::SCHEMA_TYPE_META_KEY, true);
        $canonical_url    = (string) get_post_meta($post->ID, self::CANONICAL_URL_META_KEY, true);
        $robots_directives = (string) get_post_meta($post->ID, self::ROBOTS_DIRECTIVES_META_KEY, true);
        $frontend_post_enabled = '1' === (string) get_post_meta($post->ID, self::FRONTEND_ENABLE_META_KEY, true);
        $title_branding_off = '1' === (string) get_post_meta($post->ID, self::TITLE_BRANDING_OFF_META_KEY, true);
        $branding_suffix = $this->settings->get_branding_suffix();
        $site_brand = $this->settings->get_site_brand();
        $recent_suggestions = $this->history_store->get_recent_suggestions((int) $post->ID, 'post', 3);
        $recent_content_edits = $this->history_store->get_recent_content_edits((int) $post->ID, 5);
        $approved_suggestion = $this->history_store->get_approved_suggestion((int) $post->ID, 'post');
        $has_api_key      = ! empty($options['api_key']);
        $chat_is_enabled  = ! empty($options['editor_chat_enabled']);
        $chat_messages = $chat_is_enabled ? $this->history_store->get_recent_chat_messages((int) $post->ID, 8) : array();
        $frontend_enabled = ! empty($options['frontend_output_enabled']);
        $frontend_override_conflicts = ! empty($options['frontend_override_conflicts']);
        $search_appearance_auto_enabled = ! empty($options['search_appearance_auto_enabled']);
        $frontend_conflict = $this->has_conflicting_seo_plugin();
        $analysis_markup = $this->render_focus_keyphrase_checks_markup($post, $focus_keyphrase, $seo_title, $seo_description);
        $page_audit_data = get_post_meta($post->ID, '_ai_seo_keeper_page_audit', true);
        $page_audit_score = is_array($page_audit_data) && isset($page_audit_data['score']) ? (int) $page_audit_data['score'] : null;
        $pending_changes = Content_Writer::get_pending_changes((int) $post->ID);
        $preview_title = '' !== $seo_title ? $seo_title : (string) get_the_title($post->ID);
        $preview_title_full = $title_branding_off ? $preview_title : $preview_title . $branding_suffix;
        $preview_description = '' !== $seo_description ? $seo_description : wp_trim_words(wp_strip_all_tags((string) ($post->post_excerpt ?: Content_Helper::get_content($post))), 24, '...');
        $preview_url = (string) get_permalink($post->ID);
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $default_preview_image_url = $this->get_metabox_preview_image_url((int) $post->ID, '');
        $preview_image_url = '' !== $social_image ? esc_url_raw($social_image) : $default_preview_image_url;
        $effective_social_title = '' !== $social_title ? $social_title : $preview_title;
        $effective_social_description = '' !== $social_description ? $social_description : $preview_description;
        $has_saved_frontend_data = $this->has_saved_frontend_data(
            array(
                'seo_title' => $seo_title,
                'seo_description' => $seo_description,
                'social_title' => $social_title,
                'social_description' => $social_description,
                'social_image' => $social_image,
                'canonical_url' => $canonical_url,
                'robots_directives' => $robots_directives,
                'schema_type' => $schema_type,
            )
        );
        $panel_id_prefix = 'ai-seo-keeper-' . (int) $post->ID;
        $seo_tab_id = $panel_id_prefix . '-tab-seo';
        $social_tab_id = $panel_id_prefix . '-tab-social';
        $schema_tab_id = $panel_id_prefix . '-tab-schema';
        $advanced_tab_id = $panel_id_prefix . '-tab-advanced';
        $checks_tab_id = $panel_id_prefix . '-tab-checks';
        $links_tab_id = $panel_id_prefix . '-tab-links';
        $chat_accordion_id = $panel_id_prefix . '-accordion-chat';
        $history_accordion_id = $panel_id_prefix . '-accordion-history';
        $readiness_accordion_id = $panel_id_prefix . '-accordion-readiness';
        $frontend_readiness_markup = $this->render_frontend_readiness_markup(
            ! empty($approved_suggestion),
            $has_saved_frontend_data,
            $frontend_enabled,
            $search_appearance_auto_enabled,
            $frontend_post_enabled,
            $frontend_conflict,
            $frontend_override_conflicts,
            $options,
            $summary,
            $has_api_key,
            $chat_is_enabled
        );

        wp_nonce_field('ai_seo_keeper_save_editor_meta', 'ai_seo_keeper_editor_nonce');
    ?>
        <div class="ai-seo-keeper-editor-panel">
            <?php echo $this->render_editor_panel_styles(); ?>

            <p class="ai-seo-keeper-panel-intro">This is the page-level workspace for AI SEO Keeper. Generate metadata drafts, define SEO and social overrides, choose schema and advanced directives, and control whether approved output is allowed on the frontend.</p>

            <div class="ai-seo-keeper-toolbar">
                <div class="ai-seo-keeper-toolbar-actions">
                    <button type="button" class="button button-primary ai-seo-keeper-generate-draft" <?php disabled(! $has_api_key); ?>>Generate with AI</button>
                    <button type="button" class="button button-secondary ai-seo-keeper-save-draft">Save SEO draft</button>
                </div>
                <span class="ai-seo-keeper-save-status" aria-live="polite"></span>
            </div>
            <p class="ai-seo-keeper-toolbar-note">Generate fills the draft fields only. Save SEO draft persists them without publishing or updating the main page content.</p>

            <?php if (! empty($pending_changes['changes'])) : ?>
                <div class="ai-seo-keeper-pending-notice" style="background:#fff8e1;border-left:4px solid #ffb300;padding:8px 12px;margin:8px 0;font-size:13px;">
                    ⏳ <strong><?php echo esc_html(count($pending_changes['changes'])); ?> AI content change(s) pending</strong> — Preview the page, then click <strong>Update</strong> to publish them.
                    <?php if ('' !== ($pending_changes['summary'] ?? '')) : ?>
                        <br><em style="color:#50575e;"><?php echo esc_html($pending_changes['summary']); ?></em>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ai-seo-keeper-ai-notes-shell ai-seo-keeper-surface ai-seo-keeper-notes-surface">
                <strong>AI notes</strong>
                <p class="ai-seo-keeper-ai-notes">Use Generate with AI to draft a differentiated title and description for this page.</p>
            </div>

            <div class="ai-seo-keeper-tab-list" role="tablist" aria-label="AI SEO Keeper panels">
                <button type="button" class="ai-seo-keeper-tab-button" role="tab" aria-selected="false" data-tab-target="<?php echo esc_attr($seo_tab_id); ?>">SEO</button>
                <button type="button" class="ai-seo-keeper-tab-button" role="tab" aria-selected="false" data-tab-target="<?php echo esc_attr($social_tab_id); ?>">Social</button>
                <button type="button" class="ai-seo-keeper-tab-button" role="tab" aria-selected="false" data-tab-target="<?php echo esc_attr($schema_tab_id); ?>">Schema</button>
                <button type="button" class="ai-seo-keeper-tab-button" role="tab" aria-selected="false" data-tab-target="<?php echo esc_attr($advanced_tab_id); ?>">Advanced</button>
                <button type="button" class="ai-seo-keeper-tab-button" role="tab" aria-selected="false" data-tab-target="<?php echo esc_attr($checks_tab_id); ?>">Basic SEO checks</button>
                <button type="button" class="ai-seo-keeper-tab-button" role="tab" aria-selected="false" data-tab-target="<?php echo esc_attr($links_tab_id); ?>">Links</button>
            </div>

            <div class="ai-seo-keeper-tab-panels">
                <section id="<?php echo esc_attr($seo_tab_id); ?>" class="ai-seo-keeper-tab-panel ai-seo-keeper-surface" role="tabpanel" hidden>
                    <div class="ai-seo-keeper-search-preview" data-fallback-title="<?php echo esc_attr($preview_title); ?>" data-fallback-description="<?php echo esc_attr($preview_description); ?>" data-preview-url="<?php echo esc_attr($preview_url); ?>" data-fallback-image="<?php echo esc_attr($default_preview_image_url); ?>" data-branding-suffix="<?php echo esc_attr($branding_suffix); ?>">
                        <strong>Search preview</strong>
                        <p class="ai-seo-keeper-panel-note">A quick preview of how the current title and description draft can look in search results, including the current preview image used by SEO Keeper.</p>
                        <div class="ai-seo-keeper-preview-card">
                            <div class="ai-seo-keeper-preview-copy">
                                <div class="ai-seo-keeper-preview-brand">GreenCoders</div>
                                <div class="ai-seo-keeper-preview-url"><?php echo esc_html($preview_url); ?></div>
                                <div class="ai-seo-keeper-preview-title"><?php echo esc_html($preview_title_full); ?></div>
                                <div class="ai-seo-keeper-preview-description"><?php echo esc_html($preview_description); ?></div>
                            </div>
                            <div class="ai-seo-keeper-preview-media">
                                <img class="ai-seo-keeper-preview-image" src="<?php echo esc_url($preview_image_url); ?>" alt="" <?php echo '' === $preview_image_url ? 'hidden' : ''; ?> />
                                <div class="ai-seo-keeper-preview-image-placeholder" <?php echo '' !== $preview_image_url ? 'hidden' : ''; ?>>No preview image</div>
                            </div>
                        </div>
                    </div>

                    <div class="ai-seo-keeper-snippet-analyzer is-neutral">
                        <div class="ai-seo-keeper-snippet-header">
                            <div>
                                <strong>Live snippet health <span class="ai-seo-keeper-help-tip" data-tip="Checks ONLY whether your title and description have correct length and contain the focus keyphrase. This is NOT an overall SEO score — it tracks metadata formatting only. Use the AI audit below for a full SEO analysis.">&#9432;</span></strong>
                                <p class="ai-seo-keeper-panel-note">Title length, description length, and focus-keyphrase coverage update instantly while you type.</p>
                            </div>
                            <div class="ai-seo-keeper-snippet-score is-neutral">
                                <span class="ai-seo-keeper-snippet-score-number">0</span>
                                <span class="ai-seo-keeper-snippet-score-caption">Metadata fit</span>
                                <span class="ai-seo-keeper-snippet-score-label">Start writing</span>
                            </div>
                        </div>

                        <div class="ai-seo-keeper-snippet-score-track" aria-hidden="true">
                            <span class="ai-seo-keeper-snippet-score-fill" style="width:0%;"></span>
                        </div>

                        <?php if (null !== $page_audit_score) : ?>
                            <div style="display:flex;align-items:center;gap:12px;margin:10px 0;padding:8px 12px;background:#f6f7f7;border-radius:4px;">
                                <span style="font-size:13px;">AI SEO Score: <strong style="color:<?php echo $page_audit_score >= 70 ? '#00a32a' : ($page_audit_score >= 40 ? '#dba617' : '#d63638'); ?>;"><?php echo esc_html((string) $page_audit_score); ?>/100</strong> <span class="ai-seo-keeper-help-tip" data-tip="Full AI-powered SEO audit of this page. Unlike the metadata fit score above, this analyzes content quality, heading structure, word count, image alt tags, readability, and more.">&#9432;</span></span>
                                <button type="button" class="button button-small ai-seo-keeper-run-page-audit" <?php disabled(! $has_api_key); ?>>🔄 Re-run AI Audit</button>
                                <span class="ai-seo-keeper-audit-status" style="font-size:12px;color:#787c82;"></span>
                            </div>
                        <?php else : ?>
                            <div style="display:flex;align-items:center;gap:12px;margin:10px 0;padding:8px 12px;background:#fef8ee;border-radius:4px;border:1px solid #f0c33c;">
                                <span style="font-size:13px;">No AI SEO audit has been run on this page yet.</span>
                                <button type="button" class="button button-small button-primary ai-seo-keeper-run-page-audit" <?php disabled(! $has_api_key); ?>>▶ Run AI Audit</button>
                                <span class="ai-seo-keeper-audit-status" style="font-size:12px;color:#787c82;"></span>
                            </div>
                        <?php endif; ?>

                        <p class="ai-seo-keeper-snippet-summary-text">Length and focus-keyphrase signals update as you type.</p>

                        <div class="ai-seo-keeper-snippet-metrics">
                            <div class="ai-seo-keeper-snippet-metric is-neutral" data-snippet-metric="title-length">
                                <span class="ai-seo-keeper-snippet-metric-label">Title length</span>
                                <strong class="ai-seo-keeper-snippet-metric-value">0 chars</strong>
                                <span class="ai-seo-keeper-snippet-metric-helper">Add a title draft to start scoring.</span>
                            </div>

                            <div class="ai-seo-keeper-snippet-metric is-neutral" data-snippet-metric="description-length">
                                <span class="ai-seo-keeper-snippet-metric-label">Description length</span>
                                <strong class="ai-seo-keeper-snippet-metric-value">0 chars</strong>
                                <span class="ai-seo-keeper-snippet-metric-helper">Add a meta description to score it.</span>
                            </div>

                            <div class="ai-seo-keeper-snippet-metric is-neutral" data-snippet-metric="keyphrase-title">
                                <span class="ai-seo-keeper-snippet-metric-label">Keyphrase in title</span>
                                <strong class="ai-seo-keeper-snippet-metric-value">Waiting</strong>
                                <span class="ai-seo-keeper-snippet-metric-helper">Add a focus keyphrase to evaluate title relevance.</span>
                            </div>

                            <div class="ai-seo-keeper-snippet-metric is-neutral" data-snippet-metric="keyphrase-description">
                                <span class="ai-seo-keeper-snippet-metric-label">Keyphrase in description</span>
                                <strong class="ai-seo-keeper-snippet-metric-value">Waiting</strong>
                                <span class="ai-seo-keeper-snippet-metric-helper">Add a focus keyphrase to evaluate description relevance.</span>
                            </div>
                        </div>
                    </div>

                    <div class="ai-seo-keeper-field-grid">
                        <label class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Focus keyphrase</span>
                            <input id="ai-seo-keeper-focus-keyphrase" type="text" name="ai_seo_keeper_focus_keyphrase" value="<?php echo esc_attr($focus_keyphrase); ?>" />
                            <span class="ai-seo-keeper-field-help">The main keyword or phrase this page should rank for. AI uses it to optimize your title, description, and content.</span>
                        </label>

                        <label class="ai-seo-keeper-field ai-seo-keeper-field-textarea-wide">
                            <span class="ai-seo-keeper-field-label">SEO title <span class="ai-seo-keeper-help-tip" data-tip="This is the page-specific part of the title. The separator and site brand from Settings are appended automatically unless you check 'Use as full title' below.">&#9432;</span></span>
                            <div style="display:flex;align-items:center;">
                                <input id="ai-seo-keeper-meta-title" type="text" name="ai_seo_keeper_meta_title" value="<?php echo esc_attr($seo_title); ?>" maxlength="<?php echo esc_attr((string) self::TITLE_MAX_LENGTH); ?>" style="flex:1;" />
                                <?php if ('' !== $branding_suffix && ! $title_branding_off) : ?>
                                    <span class="ai-seo-keeper-branding-suffix" style="white-space:nowrap;margin-left:5px;padding:0 8px;background:#643d87;border:1px solid #643d87;border-radius:4px;line-height:50px;color:#ffffff;font-size:13px;" title="Auto-appended from Settings > Search Appearance"><?php echo esc_html($branding_suffix); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php echo $this->render_field_counter('ai-seo-keeper-meta-title', $seo_title, self::TITLE_MAX_LENGTH, $title_branding_off ? '' : $branding_suffix); ?>
                            <label style="display:flex;align-items:center;gap:6px;margin-top:4px;font-size:12px;color:#50575e;">
                                <input id="ai-seo-keeper-title-branding-off" type="checkbox" name="ai_seo_keeper_title_branding_off" value="1" <?php checked($title_branding_off); ?> />
                                Use as full title (skip auto separator &amp; brand)
                            </label>
                            <span class="ai-seo-keeper-field-help">The page-specific title. Separator + brand (<strong><?php echo esc_html('' !== $branding_suffix ? $branding_suffix : 'none set'); ?></strong>) are appended automatically from <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-keeper-settings')); ?>">Settings</a>. Check the box above to use this field as the complete title.</span>
                        </label>

                        <label class="ai-seo-keeper-field ai-seo-keeper-field-textarea-wide">
                            <span class="ai-seo-keeper-field-label">Meta description <span class="ai-seo-keeper-help-tip" data-tip="This description appears below the title in search results. Approve it via the readiness section to make it live. AI can generate or edit it for you.">&#9432;</span></span>
                            <textarea id="ai-seo-keeper-meta-description" rows="5" name="ai_seo_keeper_meta_description" maxlength="<?php echo esc_attr((string) self::DESCRIPTION_MAX_LENGTH); ?>"><?php echo esc_textarea($seo_description); ?></textarea>
                            <?php echo $this->render_field_counter('ai-seo-keeper-meta-description', $seo_description, self::DESCRIPTION_MAX_LENGTH); ?>
                            <span class="ai-seo-keeper-field-help">Shown under the title in search results. Approve it below to go live. Max <?php echo esc_html((string) self::DESCRIPTION_MAX_LENGTH); ?> chars.</span>
                        </label>
                    </div>

                    <div class="ai-seo-keeper-accordion-group">
                        <?php if ($chat_is_enabled) : ?>
                            <?php
                            $ai_assistant_content =
                                '<div class="ai-seo-keeper-chat-intro">Your AI SEO copilot — ask questions, get metadata suggestions, or request page content edits. Everything happens in one conversation. <span class="ai-seo-keeper-help-tip" data-tip="AI sees your full page content, SEO title, meta description, focus keyphrase, snippet scores, audit results, related pages, and the full conversation history.">&#9432;</span></div>' .

                                '<div class="ai-seo-keeper-assistant-tabs" style="display:flex;gap:0;border-bottom:2px solid #dcdcde;margin-bottom:12px;">' .
                                '<button type="button" class="ai-seo-keeper-assistant-tab is-active" data-target="chat" style="padding:8px 16px;font-size:13px;font-weight:600;background:none;border:none;border-bottom:2px solid #2271b1;margin-bottom:-2px;cursor:pointer;color:#1d2327;">💬 Chat</button>' .
                                '<button type="button" class="ai-seo-keeper-assistant-tab" data-target="history" style="padding:8px 16px;font-size:13px;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;color:#787c82;">📋 History</button>' .
                                '</div>' .

                                '<div class="ai-seo-keeper-assistant-panel" data-panel="chat">' .
                                '<textarea class="widefat ai-seo-keeper-chat-input" rows="3" placeholder="Ask about SEO, request content edits, or follow up on previous advice — AI handles it all in one conversation…"></textarea>' .
                                '<p class="ai-seo-keeper-chat-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' .
                                '<button type="button" class="button button-primary ai-seo-keeper-send-chat" ' . disabled(! $has_api_key, true, false) . '>Send <span class="ai-seo-keeper-help-tip" data-tip="AI reads your full page content, SEO data, audit results, and conversation history. Ask questions, request metadata changes, or ask for page content edits — AI decides what to do automatically. When edits are needed, you get BEFORE/AFTER diffs to review before anything is saved.">&#9432;</span></button>' .
                                '<button type="button" class="button ai-seo-keeper-clear-chat" style="margin-left:auto;color:#8a2424;" ' . disabled(empty($chat_messages), true, false) . '>🗑 Clear</button>' .
                                '</p>' .
                                '<span class="ai-seo-keeper-chat-status" aria-live="polite" style="display:block;font-size:13px;margin:4px 0 8px;"></span>' .
                                '<div class="ai-seo-keeper-chat-shell">' . $this->render_chat_history_markup($chat_messages) . '</div>' .
                                '<div class="ai-seo-keeper-content-review"></div>' .
                                '</div>' .

                                '<div class="ai-seo-keeper-assistant-panel" data-panel="history" style="display:none;">' .
                                '<div class="ai-seo-keeper-history-shell">' . $this->render_history_markup($recent_suggestions, $recent_content_edits) . '</div>' .
                                '</div>';

                            echo $this->render_accordion_section(
                                $chat_accordion_id,
                                '🤖 AI Assistant <span class="ai-seo-keeper-help-tip" data-tip="Unified AI workspace: chat for SEO advice, edit page content, and view suggestion history — all in one place.">&#9432;</span>',
                                $ai_assistant_content,
                                false
                            );
                            ?>
                        <?php endif; ?>

                        <?php
                        echo $this->render_accordion_section(
                            $readiness_accordion_id,
                            'Frontend readiness <span class="ai-seo-keeper-help-tip" data-tip="Shows whether this page\'s SEO metadata is approved and ready to be served on the live site. All checks must pass for AI SEO Keeper to output your title and description.">&#9432;</span>',
                            $frontend_readiness_markup,
                            true
                        );
                        ?>
                    </div>
                </section>

                <section id="<?php echo esc_attr($social_tab_id); ?>" class="ai-seo-keeper-tab-panel ai-seo-keeper-surface" role="tabpanel" hidden>
                    <div class="ai-seo-keeper-field-grid">
                        <label class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Social title override</span>
                            <input id="ai-seo-keeper-social-title" type="text" name="ai_seo_keeper_social_title" value="<?php echo esc_attr($social_title); ?>" maxlength="<?php echo esc_attr((string) self::TITLE_MAX_LENGTH); ?>" />
                            <?php echo $this->render_field_counter('ai-seo-keeper-social-title', $social_title, self::TITLE_MAX_LENGTH); ?>
                            <span class="ai-seo-keeper-field-help">Optional. If empty, Open Graph and Twitter titles fall back to the approved SEO title. Maximum: <?php echo esc_html((string) self::TITLE_MAX_LENGTH); ?> characters.</span>
                        </label>

                        <label class="ai-seo-keeper-field ai-seo-keeper-field-textarea-wide">
                            <span class="ai-seo-keeper-field-label">Social description override</span>
                            <textarea id="ai-seo-keeper-social-description" rows="5" name="ai_seo_keeper_social_description" maxlength="<?php echo esc_attr((string) self::DESCRIPTION_MAX_LENGTH); ?>"><?php echo esc_textarea($social_description); ?></textarea>
                            <?php echo $this->render_field_counter('ai-seo-keeper-social-description', $social_description, self::DESCRIPTION_MAX_LENGTH); ?>
                            <span class="ai-seo-keeper-field-help">Optional. If empty, social descriptions fall back to the approved meta description. Maximum: <?php echo esc_html((string) self::DESCRIPTION_MAX_LENGTH); ?> characters.</span>
                        </label>

                        <label class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Social image URL</span>
                            <input id="ai-seo-keeper-social-image" type="url" name="ai_seo_keeper_social_image" value="<?php echo esc_attr($social_image); ?>" />
                            <span class="ai-seo-keeper-media-actions">
                                <button type="button" class="button button-secondary ai-seo-keeper-open-media">Choose from Media Library</button>
                                <button type="button" class="button button-link-delete ai-seo-keeper-remove-social-image" <?php disabled('' === $social_image); ?>>Remove override</button>
                            </span>
                            <span class="ai-seo-keeper-field-help">Optional absolute URL. If empty, AI SEO Keeper falls back to the featured image or site logo.</span>
                        </label>

                        <div class="ai-seo-keeper-field ai-seo-keeper-preview-image-card">
                            <span class="ai-seo-keeper-field-label">Current preview image</span>
                            <div class="ai-seo-keeper-preview-image-card-frame">
                                <img class="ai-seo-keeper-preview-image-card-image" src="<?php echo esc_url($preview_image_url); ?>" alt="" <?php echo '' === $preview_image_url ? 'hidden' : ''; ?> />
                                <div class="ai-seo-keeper-preview-image-card-empty" <?php echo '' !== $preview_image_url ? 'hidden' : ''; ?>>No image available yet</div>
                            </div>
                            <span class="ai-seo-keeper-field-help">This is the image that will be used in the search preview area unless you set a different social image.</span>
                        </div>

                        <div class="ai-seo-keeper-field ai-seo-keeper-field-textarea-wide ai-seo-keeper-social-preview-shell">
                            <span class="ai-seo-keeper-field-label">Social sharing previews</span>
                            <p class="ai-seo-keeper-field-help">These cards show how the current social title, description, and image are likely to look for Open Graph and Twitter output.</p>
                            <div class="ai-seo-keeper-social-preview-grid">
                                <div class="ai-seo-keeper-social-preview-card is-open-graph">
                                    <div class="ai-seo-keeper-social-preview-header">
                                        <span class="ai-seo-keeper-social-preview-badge">Open Graph preview</span>
                                        <span class="ai-seo-keeper-social-preview-network">Facebook, LinkedIn, Messenger</span>
                                    </div>
                                    <div class="ai-seo-keeper-social-preview-media">
                                        <img class="ai-seo-keeper-social-preview-image" src="<?php echo esc_url($preview_image_url); ?>" alt="" <?php echo '' === $preview_image_url ? 'hidden' : ''; ?> />
                                        <div class="ai-seo-keeper-social-preview-placeholder" <?php echo '' !== $preview_image_url ? 'hidden' : ''; ?>>No social image selected</div>
                                    </div>
                                    <div class="ai-seo-keeper-social-preview-copy">
                                        <div class="ai-seo-keeper-social-preview-url"><?php echo esc_html($preview_url); ?></div>
                                        <div class="ai-seo-keeper-social-preview-title"><?php echo esc_html($effective_social_title); ?></div>
                                        <div class="ai-seo-keeper-social-preview-description"><?php echo esc_html($effective_social_description); ?></div>
                                    </div>
                                </div>

                                <div class="ai-seo-keeper-social-preview-card is-twitter">
                                    <div class="ai-seo-keeper-social-preview-header">
                                        <span class="ai-seo-keeper-social-preview-badge">Twitter preview</span>
                                        <span class="ai-seo-keeper-social-preview-network"><?php echo esc_html($site_name); ?> summary_large_image card</span>
                                    </div>
                                    <div class="ai-seo-keeper-social-preview-media is-twitter">
                                        <img class="ai-seo-keeper-social-preview-image" src="<?php echo esc_url($preview_image_url); ?>" alt="" <?php echo '' === $preview_image_url ? 'hidden' : ''; ?> />
                                        <div class="ai-seo-keeper-social-preview-placeholder" <?php echo '' !== $preview_image_url ? 'hidden' : ''; ?>>No social image selected</div>
                                    </div>
                                    <div class="ai-seo-keeper-social-preview-copy">
                                        <div class="ai-seo-keeper-social-preview-title"><?php echo esc_html($effective_social_title); ?></div>
                                        <div class="ai-seo-keeper-social-preview-description"><?php echo esc_html($effective_social_description); ?></div>
                                        <div class="ai-seo-keeper-social-preview-url"><?php echo esc_html($preview_url); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="<?php echo esc_attr($schema_tab_id); ?>" class="ai-seo-keeper-tab-panel ai-seo-keeper-surface" role="tabpanel" hidden>
                    <div class="ai-seo-keeper-field-grid">
                        <label class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Schema type override</span>
                            <select id="ai-seo-keeper-schema-type" name="ai_seo_keeper_schema_type">
                                <?php foreach ($this->get_schema_type_options() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($schema_type, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ai-seo-keeper-field-help">Leave this on automatic detection or force a page type such as Product, Service, About, or Contact.</span>
                        </label>

                        <div class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Schema note</span>
                            <div class="ai-seo-keeper-note-box">AI SEO Keeper already outputs structured data for WebSite, Organization, BreadcrumbList, and the detected primary entity. Use overrides only when the page intentionally breaks the usual site pattern. For visible breadcrumbs in the theme content, use the <code>[ai_seo_keeper_breadcrumbs]</code> shortcode.</div>
                        </div>
                    </div>
                </section>

                <section id="<?php echo esc_attr($advanced_tab_id); ?>" class="ai-seo-keeper-tab-panel ai-seo-keeper-surface" role="tabpanel" hidden>
                    <div class="ai-seo-keeper-field-grid">
                        <label class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Canonical URL override</span>
                            <input id="ai-seo-keeper-canonical-url" type="url" name="ai_seo_keeper_canonical_url" value="<?php echo esc_attr($canonical_url); ?>" />
                            <span class="ai-seo-keeper-field-help">Optional absolute URL. If empty, the page permalink remains canonical.</span>
                        </label>

                        <label class="ai-seo-keeper-field">
                            <span class="ai-seo-keeper-field-label">Robots override</span>
                            <select id="ai-seo-keeper-robots-directives" name="ai_seo_keeper_robots_directives">
                                <?php foreach ($this->get_robots_directive_options() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($robots_directives, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ai-seo-keeper-field-help">Leave on automatic site defaults or force an index or follow combination per page.</span>
                        </label>

                        <div class="ai-seo-keeper-field ai-seo-keeper-toggle-field">
                            <span class="ai-seo-keeper-field-label">Page-level frontend gate</span>
                            <label class="ai-seo-keeper-checkbox-row">
                                <input id="ai-seo-keeper-frontend-enabled" type="checkbox" name="ai_seo_keeper_frontend_enabled" value="1" <?php checked($frontend_post_enabled); ?> />
                                <span>Force this page to use its approved AI suggestion and saved SEO overrides on the frontend when the global AI SEO Keeper settings also allow it.</span>
                            </label>
                            <span class="ai-seo-keeper-field-help">This is still useful when automatic search appearance is turned off, or when you want this page explicitly managed with page-level SEO data.</span>
                        </div>

                        <div class="ai-seo-keeper-field ai-seo-keeper-toggle-field">
                            <span class="ai-seo-keeper-field-label">Cornerstone content</span>
                            <label class="ai-seo-keeper-checkbox-row">
                                <input id="ai-seo-keeper-cornerstone" type="checkbox" name="ai_seo_keeper_cornerstone" value="1" <?php checked(! empty(get_post_meta($post->ID, '_ai_seo_keeper_cornerstone', true))); ?> />
                                <span>Mark this page as cornerstone content — your most important, comprehensive articles.</span>
                            </label>
                            <span class="ai-seo-keeper-field-help">Cornerstone pages get higher priority in the sitemap, are prioritized in internal linking suggestions, and receive extra audit weight.</span>
                        </div>

                        <div class="ai-seo-keeper-field">
                            <label class="ai-seo-keeper-field-label" for="ai-seo-keeper-hreflang">Hreflang tags (multi-language)</label>
                            <textarea id="ai-seo-keeper-hreflang" class="ai-seo-keeper-input" rows="3" name="ai_seo_keeper_hreflang" placeholder="en|https://example.com/page&#10;fr|https://example.fr/page&#10;x-default|https://example.com/page"><?php echo esc_textarea(get_post_meta($post->ID, '_ai_seo_keeper_hreflang', true)); ?></textarea>
                            <span class="ai-seo-keeper-field-help">One entry per line: <code>lang|URL</code>. Auto-detected from WPML/Polylang if installed. Manual entries take priority.</span>
                        </div>
                    </div>
                </section>

                <section id="<?php echo esc_attr($checks_tab_id); ?>" class="ai-seo-keeper-tab-panel ai-seo-keeper-surface" role="tabpanel" hidden>
                    <div class="ai-seo-keeper-analysis-shell">
                        <?php echo $analysis_markup; ?>
                    </div>
                </section>

                <section id="<?php echo esc_attr($links_tab_id); ?>" class="ai-seo-keeper-tab-panel ai-seo-keeper-surface" role="tabpanel" hidden>
                    <?php echo $this->render_internal_links_tab($post); ?>
                </section>
            </div>

            <?php if (! $has_api_key) : ?>
                <p class="ai-seo-keeper-missing-key">Add an API key in AI SEO Keeper Settings before the generation tools can be activated.</p>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render the internal links tab for the editor metabox.
     */
    private function render_internal_links_tab(\WP_Post $post): string
    {
        $content = Content_Helper::get_content($post);
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

        // 1. Outbound internal links from this page.
        $outbound = array();
        if (preg_match_all('/<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $href = trim($m[2]);
                if ('' === $href || '#' === $href[0]) {
                    continue;
                }
                if ('/' === $href[0]) {
                    $href = home_url($href);
                }
                $parsed = wp_parse_url($href);
                if (! is_array($parsed) || empty($parsed['host'])) {
                    continue;
                }
                if (strtolower($parsed['host']) !== strtolower((string) $site_host)) {
                    continue;
                }
                $target_id = url_to_postid($href);
                if ($target_id > 0 && $target_id !== $post->ID) {
                    $anchor = trim(wp_strip_all_tags($m[3]));
                    $outbound[$target_id] = array(
                        'id' => $target_id,
                        'title' => get_the_title($target_id),
                        'url' => get_permalink($target_id),
                        'anchor' => '' !== $anchor ? $anchor : '(no text)',
                    );
                }
            }
        }

        // 2. Inbound internal links to this page (scan other published pages).
        $inbound = array();
        $this_permalink = get_permalink($post->ID);
        $this_path = trailingslashit((string) wp_parse_url($this_permalink, PHP_URL_PATH));

        global $wpdb;
        $index_table = $wpdb->prefix . 'ai_seo_keeper_content_index';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $other_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT object_id FROM {$index_table} WHERE object_type = %s AND status = %s AND object_id != %d",
                'post',
                'publish',
                $post->ID
            )
        );

        if (is_array($other_ids)) {
            foreach ($other_ids as $other_id) {
                $other_post = get_post((int) $other_id);
                if (! $other_post instanceof \WP_Post) {
                    continue;
                }
                $other_content = Content_Helper::get_content($other_post);
                if (preg_match_all('/<a\s[^>]*href=("|\')(.*?)\1/is', $other_content, $link_matches)) {
                    foreach ($link_matches[2] as $href) {
                        $href = trim($href);
                        if ('/' === ($href[0] ?? '')) {
                            $href = home_url($href);
                        }
                        $linked_id = url_to_postid($href);
                        if ($linked_id === $post->ID) {
                            $inbound[(int) $other_id] = array(
                                'id' => (int) $other_id,
                                'title' => $other_post->post_title,
                                'url' => get_permalink($other_id),
                            );
                            break; // Only count each source page once.
                        }
                    }
                }
            }
        }

        // 3. Linking suggestions — related pages NOT already linked from this page.
        $suggestions = array();
        $already_linked = array_keys($outbound);
        $already_linked[] = $post->ID;
        $related = $this->content_indexer->get_related_entries(
            $post->ID,
            $post->post_type,
            (int) $post->post_parent,
            10
        );
        foreach ($related as $r) {
            $rid = (int) $r['object_id'];
            if (in_array($rid, $already_linked, true)) {
                continue;
            }
            if ('publish' !== $r['status']) {
                continue;
            }
            $suggestions[] = array(
                'id' => $rid,
                'title' => $r['title'],
                'url' => $r['permalink'],
            );
        }

        ob_start();
    ?>
        <div class="ai-seo-keeper-links-tab">
            <div style="margin-bottom:16px;">
                <strong>Outbound internal links (<?php echo count($outbound); ?>)</strong>
                <p class="ai-seo-keeper-muted" style="margin:4px 0 8px;">Pages this post links to.</p>
                <?php if (empty($outbound)) : ?>
                    <p style="color:#d63638;margin:0;">This page has no outbound internal links. Adding internal links helps search engines discover and understand your site structure.</p>
                <?php else : ?>
                    <ul style="margin:0;padding-left:20px;">
                        <?php foreach ($outbound as $link) : ?>
                            <li style="margin-bottom:4px;">
                                <a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($link['title']); ?></a>
                                <span style="color:#50575e;font-size:12px;"> — anchor: "<?php echo esc_html($link['anchor']); ?>"</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div style="margin-bottom:16px;">
                <strong>Inbound internal links (<?php echo count($inbound); ?>)</strong>
                <p class="ai-seo-keeper-muted" style="margin:4px 0 8px;">Pages that link to this post.</p>
                <?php if (empty($inbound)) : ?>
                    <p style="color:#d63638;margin:0;">No other pages link to this post. It may be orphaned and harder for search engines to discover.</p>
                <?php else : ?>
                    <ul style="margin:0;padding-left:20px;">
                        <?php foreach ($inbound as $link) : ?>
                            <li style="margin-bottom:4px;">
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $link['id'] . '&action=edit')); ?>"><?php echo esc_html($link['title']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if (! empty($suggestions)) : ?>
                <div>
                    <strong>Suggested pages to link to</strong>
                    <p class="ai-seo-keeper-muted" style="margin:4px 0 8px;">Related pages you could link from this post to improve site structure.</p>
                    <ul style="margin:0;padding-left:20px;">
                        <?php foreach ($suggestions as $s) : ?>
                            <li style="margin-bottom:4px;">
                                <a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($s['title']); ?></a>
                                <button type="button" class="button button-small ai-seo-keeper-copy-link" data-url="<?php echo esc_attr($s['url']); ?>" data-title="<?php echo esc_attr($s['title']); ?>" style="margin-left:6px;font-size:11px;">Copy link</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <script>
            (function($) {
                $(document).on('click', '.ai-seo-keeper-copy-link', function() {
                    var url = $(this).data('url');
                    var title = $(this).data('title');
                    var html = '<a href="' + url + '">' + title + '</a>';
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(html).then(function() {});
                    }
                    var btn = $(this);
                    btn.text('Copied!');
                    setTimeout(function() {
                        btn.text('Copy link');
                    }, 1500);
                });
            })(jQuery);
        </script>
    <?php
        return ob_get_clean();
    }

    private function render_editor_panel_styles(): string
    {
        static $styles_rendered = false;

        if ($styles_rendered) {
            return '';
        }

        $styles_rendered = true;

        return <<<'HTML'
<style>
.ai-seo-keeper-editor-panel {
    margin-top: 4px;
}

.ai-seo-keeper-panel-intro,
.ai-seo-keeper-panel-note,
.ai-seo-keeper-toolbar-note,
.ai-seo-keeper-field-help,
.ai-seo-keeper-chat-intro,
.ai-seo-keeper-muted,
.ai-seo-keeper-note-box,
.ai-seo-keeper-empty-state {
    color: #5f6b7a;
    font-size: 13px;
    line-height: 1.65;
}

.ai-seo-keeper-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.ai-seo-keeper-toolbar-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.ai-seo-keeper-toolbar-note {
    margin: 8px 0 0;
}

.ai-seo-keeper-surface,
.ai-seo-keeper-accordion-panel {
    background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%);
    border: 1px solid #dcdcde;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.04);
}

.ai-seo-keeper-notes-surface {
    margin-top: 16px;
    padding: 16px 18px;
}

.ai-seo-keeper-ai-notes {
    margin: 8px 0 0;
}

.ai-seo-keeper-tab-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 20px;
    border-bottom: 1px solid #dcdcde;
    padding-bottom: 10px;
}

.ai-seo-keeper-tab-button {
    appearance: none;
    border: 1px solid #c3c4c7;
    border-radius: 14px 14px 0 0;
    background: #f6f7f7;
    color: #1d2327;
    padding: 11px 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.ai-seo-keeper-tab-button:hover,
.ai-seo-keeper-tab-button:focus-visible {
    border-color: #2271b1;
    color: #0a4b78;
    outline: none;
}

.ai-seo-keeper-tab-button.is-active {
    background: #ffffff;
    border-color: #2271b1;
    color: #0a4b78;
    box-shadow: inset 0 3px 0 #2271b1;
}

.ai-seo-keeper-tab-panels {
    margin-top: 16px;
}

.ai-seo-keeper-tab-panel {
    padding: 18px;
}

.ai-seo-keeper-search-preview {
    margin-bottom: 18px;
}

.ai-seo-keeper-snippet-analyzer {
    margin-bottom: 18px;
    padding: 18px;
    border-radius: 20px;
    border: 1px solid #e4e7ec;
    background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.ai-seo-keeper-snippet-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
}

.ai-seo-keeper-snippet-score {
    min-width: 156px;
    display: grid;
    gap: 4px;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid #dcdcde;
    background: #ffffff;
    text-align: center;
}

.ai-seo-keeper-snippet-score-number {
    font-size: 30px;
    line-height: 1;
    font-weight: 700;
    color: #111827;
}

.ai-seo-keeper-snippet-score-caption,
.ai-seo-keeper-snippet-metric-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.ai-seo-keeper-snippet-score-label {
    font-size: 13px;
    font-weight: 700;
}

.ai-seo-keeper-snippet-score-track {
    margin-top: 14px;
    width: 100%;
    height: 10px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
}

.ai-seo-keeper-snippet-score-fill {
    display: block;
    width: 0;
    height: 100%;
    border-radius: inherit;
    transition: width 0.18s ease;
}

.ai-seo-keeper-snippet-summary-text {
    margin: 12px 0 0;
    color: #5f6b7a;
}

.ai-seo-keeper-snippet-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(220px, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.ai-seo-keeper-snippet-metric {
    display: grid;
    gap: 6px;
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid #dcdcde;
    background: #ffffff;
}

.ai-seo-keeper-snippet-metric-value {
    font-size: 18px;
    color: #111827;
}

.ai-seo-keeper-snippet-metric-helper {
    color: #5f6b7a;
    font-size: 13px;
    line-height: 1.55;
}

.ai-seo-keeper-snippet-analyzer.is-good .ai-seo-keeper-snippet-score-fill {
    background: linear-gradient(90deg, #34a853 0%, #6bd98d 100%);
}

.ai-seo-keeper-snippet-analyzer.is-neutral .ai-seo-keeper-snippet-score-fill {
    background: linear-gradient(90deg, #2271b1 0%, #76a9ff 100%);
}

.ai-seo-keeper-snippet-analyzer.is-warning .ai-seo-keeper-snippet-score-fill {
    background: linear-gradient(90deg, #d97706 0%, #fbbf24 100%);
}

.ai-seo-keeper-snippet-score.is-good,
.ai-seo-keeper-snippet-metric.is-good {
    border-color: #a6d8a8;
    background: linear-gradient(180deg, #f2fcf5 0%, #ffffff 100%);
}

.ai-seo-keeper-snippet-score.is-good .ai-seo-keeper-snippet-score-label,
.ai-seo-keeper-snippet-metric.is-good .ai-seo-keeper-snippet-metric-value {
    color: #135e16;
}

.ai-seo-keeper-snippet-score.is-neutral,
.ai-seo-keeper-snippet-metric.is-neutral {
    border-color: #bfd7ea;
    background: linear-gradient(180deg, #f5fbff 0%, #ffffff 100%);
}

.ai-seo-keeper-snippet-score.is-neutral .ai-seo-keeper-snippet-score-label,
.ai-seo-keeper-snippet-metric.is-neutral .ai-seo-keeper-snippet-metric-value {
    color: #0a4b78;
}

.ai-seo-keeper-snippet-score.is-warning,
.ai-seo-keeper-snippet-metric.is-warning {
    border-color: #f0c36d;
    background: linear-gradient(180deg, #fff8eb 0%, #ffffff 100%);
}

.ai-seo-keeper-snippet-score.is-warning .ai-seo-keeper-snippet-score-label,
.ai-seo-keeper-snippet-metric.is-warning .ai-seo-keeper-snippet-metric-value {
    color: #8a5a00;
}

.ai-seo-keeper-preview-card {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(150px, 0.8fr);
    gap: 18px;
    margin-top: 12px;
    padding: 18px;
    border-radius: 22px;
    border: 1px solid #e4e7ec;
    background: #ffffff;
    box-shadow: 0 14px 34px rgba(17, 24, 39, 0.08);
}

.ai-seo-keeper-preview-brand {
    font-size: 12px;
    font-weight: 700;
    color: #188038;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.ai-seo-keeper-preview-url {
    font-size: 13px;
    color: #188038;
    word-break: break-all;
    margin-top: 4px;
}

.ai-seo-keeper-preview-title {
    font-size: 23px;
    line-height: 1.2;
    color: #1a0dab;
    margin-top: 8px;
}

.ai-seo-keeper-preview-description {
    font-size: 14px;
    line-height: 1.6;
    color: #4d5156;
    margin-top: 10px;
}

.ai-seo-keeper-preview-media {
    min-height: 168px;
    border-radius: 18px;
    overflow: hidden;
    background: linear-gradient(135deg, #f2f7ff 0%, #eef6f0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ai-seo-keeper-preview-image,
.ai-seo-keeper-preview-image-card-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.ai-seo-keeper-preview-image-placeholder,
.ai-seo-keeper-preview-image-card-empty {
    color: #6b7280;
    font-size: 13px;
    padding: 16px;
    text-align: center;
}

.ai-seo-keeper-field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(280px, 1fr));
    gap: 16px;
}

.ai-seo-keeper-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
    border-radius: 18px;
    border: 1px solid #e4e7ec;
    background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
}

.ai-seo-keeper-field.ai-seo-keeper-field-textarea-wide,
.ai-seo-keeper-toggle-field {
    grid-column: 1 / -1;
}

.ai-seo-keeper-field-label {
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
}

.ai-seo-keeper-editor-panel .ai-seo-keeper-field input[type="text"],
.ai-seo-keeper-editor-panel .ai-seo-keeper-field input[type="url"],
.ai-seo-keeper-editor-panel .ai-seo-keeper-field select,
.ai-seo-keeper-editor-panel .ai-seo-keeper-chat-input,
.ai-seo-keeper-editor-panel .ai-seo-keeper-field textarea {
    width: 100%;
    border-radius: 14px;
    border: 1px solid #c8d0da;
    background: #ffffff;
    padding: 12px 14px;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
}

.ai-seo-keeper-editor-panel .ai-seo-keeper-field textarea,
.ai-seo-keeper-editor-panel .ai-seo-keeper-chat-input {
    min-height: 140px;
    resize: vertical;
    background: linear-gradient(180deg, #fffdf8 0%, #ffffff 100%);
    border: 1px solid #d6c6a8;
}

.ai-seo-keeper-editor-panel .ai-seo-keeper-chat-input {
    min-height: 110px;
    margin-top: 12px;
}

.ai-seo-keeper-field-counter {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    padding: 4px 10px;
    border-radius: 999px;
    background: #eef2f7;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}

.ai-seo-keeper-field-counter.is-warning {
    background: #fff4d6;
    color: #8a5a00;
}

.ai-seo-keeper-field-counter.is-limit {
    background: #fde7e7;
    color: #8a2424;
}

.ai-seo-keeper-media-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.ai-seo-keeper-editor-panel .ai-seo-keeper-field input:focus,
.ai-seo-keeper-editor-panel .ai-seo-keeper-field textarea:focus,
.ai-seo-keeper-editor-panel .ai-seo-keeper-field select:focus,
.ai-seo-keeper-editor-panel .ai-seo-keeper-chat-input:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
    outline: none;
}

.ai-seo-keeper-preview-image-card-frame {
    min-height: 180px;
    border-radius: 16px;
    overflow: hidden;
    background: #f3f4f6;
    position: relative;
}

.ai-seo-keeper-social-preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(260px, 1fr));
    gap: 14px;
}

.ai-seo-keeper-social-preview-card {
    overflow: hidden;
    border-radius: 20px;
    border: 1px solid #d8dee6;
    background: #ffffff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
}

.ai-seo-keeper-social-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #f8fbff 0%, #f4f8fc 100%);
}

.ai-seo-keeper-social-preview-badge {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #0a4b78;
}

.ai-seo-keeper-social-preview-network {
    font-size: 12px;
    color: #6b7280;
    text-align: right;
}

.ai-seo-keeper-social-preview-media {
    min-height: 180px;
    background: linear-gradient(135deg, #eef3fb 0%, #eef6f0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.ai-seo-keeper-social-preview-media.is-twitter {
    min-height: 168px;
}

.ai-seo-keeper-social-preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.ai-seo-keeper-social-preview-placeholder {
    padding: 18px;
    color: #6b7280;
    font-size: 13px;
    text-align: center;
}

.ai-seo-keeper-social-preview-copy {
    display: grid;
    gap: 8px;
    padding: 14px 16px 16px;
}

.ai-seo-keeper-social-preview-url {
    font-size: 12px;
    color: #6b7280;
    word-break: break-all;
}

.ai-seo-keeper-social-preview-title {
    font-size: 17px;
    line-height: 1.35;
    font-weight: 700;
    color: #111827;
}

.ai-seo-keeper-social-preview-description {
    font-size: 13px;
    line-height: 1.55;
    color: #4b5563;
}

.ai-seo-keeper-note-box {
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px dashed #c7d2fe;
    background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
}

.ai-seo-keeper-checkbox-row {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    color: #1f2937;
}

.ai-seo-keeper-accordion-group {
    display: grid;
    gap: 12px;
    margin-top: 18px;
}

.ai-seo-keeper-accordion-item {
    border: 1px solid #dcdcde;
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
}

.ai-seo-keeper-accordion-toggle {
    width: 100%;
    border: 0;
    background: linear-gradient(135deg, #ffffff 0%, #f7f9fc 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
    cursor: pointer;
}

.ai-seo-keeper-accordion-toggle:hover,
.ai-seo-keeper-accordion-toggle:focus-visible {
    background: linear-gradient(135deg, #f8fbff 0%, #eef4fa 100%);
    outline: none;
}

.ai-seo-keeper-accordion-symbol {
    font-size: 18px;
    font-weight: 400;
    color: #2271b1;
}

.ai-seo-keeper-accordion-panel {
    margin: 0;
    border-radius: 0;
    border-width: 1px 0 0;
    padding: 18px;
    box-shadow: none;
}

.ai-seo-keeper-chat-actions {
    margin: 12px 0 0;
}

.ai-seo-keeper-stack {
    display: grid;
    gap: 12px;
}

.ai-seo-keeper-history-item,
.ai-seo-keeper-chat-item,
.ai-seo-keeper-readiness-card,
.ai-seo-keeper-status-card {
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid #dcdcde;
    background: linear-gradient(180deg, #ffffff 0%, #f6f7f7 100%);
}

.ai-seo-keeper-chat-item.is-assistant {
    background: linear-gradient(180deg, #f8fbff 0%, #eef5fb 100%);
}

.ai-seo-keeper-history-meta,
.ai-seo-keeper-chat-meta {
    color: #5f6b7a;
    font-size: 12px;
}

.ai-seo-keeper-help-tip {
    display: inline-block;
    cursor: help;
    color: #2271b1;
    font-weight: 400;
    font-size: 14px;
    position: static;
}

.ai-seo-keeper-help-tip:hover::after {
    content: attr(data-tip);
    position: fixed;
    top: var(--tip-top, 0);
    left: var(--tip-left, 0);
    transform: translateY(-100%);
    background: #1d2327;
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.4;
    max-width: 300px;
    width: max-content;
    z-index: 999999;
    box-shadow: 0 4px 12px rgba(0,0,0,.25);
    pointer-events: none;
    white-space: normal;
}

.ai-seo-keeper-readiness-grid,
.ai-seo-keeper-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(180px, 1fr));
    gap: 12px;
}

.ai-seo-keeper-readiness-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
}

.ai-seo-keeper-readiness-value {
    display: block;
    margin-top: 6px;
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.ai-seo-keeper-check-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
    display: grid;
    gap: 12px;
}

.ai-seo-keeper-check-section + .ai-seo-keeper-check-section {
    margin-top: 18px;
}

.ai-seo-keeper-check-section-title {
    margin: 0 0 10px;
}

.ai-seo-keeper-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin: 12px 0 16px;
}

.ai-seo-keeper-metric-card {
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid #dcdcde;
    background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
}

.ai-seo-keeper-metric-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #6b7280;
}

.ai-seo-keeper-metric-value {
    display: block;
    margin-top: 8px;
    font-size: 22px;
    line-height: 1.1;
    font-weight: 700;
    color: #111827;
}

.ai-seo-keeper-check-item {
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid #dcdcde;
    background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
}

.ai-seo-keeper-check-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    margin-right: 8px;
}

.ai-seo-keeper-check-pill.is-pass {
    border: 1px solid #a6d8a8;
    background: #dff3df;
    color: #135e16;
}

.ai-seo-keeper-check-pill.is-warning {
    border: 1px solid #e0b44c;
    background: #fff4d6;
    color: #8a5a00;
}

.ai-seo-keeper-missing-key {
    margin-top: 14px;
    color: #8a2424;
}

@media (max-width: 960px) {
    .ai-seo-keeper-snippet-header,
    .ai-seo-keeper-snippet-metrics,
    .ai-seo-keeper-metrics-grid,
    .ai-seo-keeper-social-preview-grid,
    .ai-seo-keeper-field-grid,
    .ai-seo-keeper-preview-card,
    .ai-seo-keeper-readiness-grid,
    .ai-seo-keeper-status-grid {
        grid-template-columns: 1fr;
    }

    .ai-seo-keeper-snippet-header {
        display: grid;
    }

    .ai-seo-keeper-preview-media {
        min-height: 220px;
    }
}
</style>
HTML;
    }

    private function render_accordion_section(string $accordion_id, string $title, string $content, bool $open = false): string
    {
        ob_start();
    ?>
        <div class="ai-seo-keeper-accordion-item">
            <button type="button" class="ai-seo-keeper-accordion-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($accordion_id); ?>" data-default-open="<?php echo $open ? '1' : '0'; ?>">
                <span><?php echo wp_kses($title, array('span' => array('class' => true, 'data-tip' => true))); ?></span>
                <span class="ai-seo-keeper-accordion-symbol"><?php echo $open ? '-' : '+'; ?></span>
            </button>
            <div id="<?php echo esc_attr($accordion_id); ?>" class="ai-seo-keeper-accordion-panel" <?php echo $open ? '' : 'hidden'; ?>>
                <?php echo $content; ?>
            </div>
        </div>
    <?php

        return (string) ob_get_clean();
    }

    private function render_frontend_readiness_markup(bool $has_approved_suggestion, bool $has_saved_frontend_data, bool $frontend_enabled, bool $search_appearance_auto_enabled, bool $frontend_post_enabled, bool $frontend_conflict, bool $frontend_override_conflicts, array $options, array $summary, bool $has_api_key, bool $chat_is_enabled): string
    {
        ob_start();
    ?>
        <div class="ai-seo-keeper-stack">
            <div class="ai-seo-keeper-readiness-grid">
                <div class="ai-seo-keeper-readiness-card">
                    <span class="ai-seo-keeper-readiness-label">Approved AI suggestion</span>
                    <span class="ai-seo-keeper-readiness-value"><?php echo $has_approved_suggestion ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="ai-seo-keeper-readiness-card">
                    <span class="ai-seo-keeper-readiness-label">Saved SEO fields</span>
                    <span class="ai-seo-keeper-readiness-value"><?php echo $has_saved_frontend_data ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="ai-seo-keeper-readiness-card">
                    <span class="ai-seo-keeper-readiness-label">Global frontend output</span>
                    <span class="ai-seo-keeper-readiness-value"><?php echo $frontend_enabled ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <div class="ai-seo-keeper-readiness-card">
                    <span class="ai-seo-keeper-readiness-label">Automatic search appearance</span>
                    <span class="ai-seo-keeper-readiness-value"><?php echo $search_appearance_auto_enabled ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <div class="ai-seo-keeper-readiness-card">
                    <span class="ai-seo-keeper-readiness-label">Page gate enabled</span>
                    <span class="ai-seo-keeper-readiness-value"><?php echo $frontend_post_enabled ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="ai-seo-keeper-readiness-card">
                    <span class="ai-seo-keeper-readiness-label">Conflict status</span>
                    <span class="ai-seo-keeper-readiness-value"><?php echo $frontend_conflict ? 'Detected' : 'Clear'; ?></span>
                </div>
            </div>

            <p class="ai-seo-keeper-muted">Frontend output will render when the global frontend rules permit it and either automatic search appearance is enabled for singular content or this page is explicitly gated on. Approved AI suggestions and saved SEO fields still take precedence over the automatic baseline when they exist. Conflict override is currently <strong><?php echo $frontend_override_conflicts ? 'enabled' : 'off'; ?></strong>.</p>

            <div class="ai-seo-keeper-status-grid">
                <div class="ai-seo-keeper-status-card">
                    <strong>AI status</strong>
                    <p class="ai-seo-keeper-muted" style="margin:8px 0 0;">
                        Provider: <?php echo esc_html(strtoupper($options['provider'])); ?><br />
                        Model: <?php echo esc_html($options['model']); ?><br />
                        API key: <?php echo $has_api_key ? 'Configured' : 'Missing'; ?>
                    </p>
                </div>
                <div class="ai-seo-keeper-status-card">
                    <strong>Site context</strong>
                    <p class="ai-seo-keeper-muted" style="margin:8px 0 0;">
                        Indexed records: <?php echo esc_html((string) $summary['total_items']); ?><br />
                        Last sync: <?php echo $summary['last_sync'] ? esc_html($summary['last_sync']) : 'Never'; ?><br />
                        Editor chat: <?php echo $chat_is_enabled ? 'Enabled in settings' : 'Disabled in settings'; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php

        return (string) ob_get_clean();
    }

    private function get_metabox_preview_image_url(int $post_id, string $social_image): string
    {
        $social_image = esc_url_raw($social_image);

        if ('' !== $social_image) {
            return $social_image;
        }

        if (has_post_thumbnail($post_id)) {
            $featured_image = get_the_post_thumbnail_url($post_id, 'full');

            if (is_string($featured_image) && '' !== $featured_image) {
                return $featured_image;
            }
        }

        $custom_logo_id = (int) get_theme_mod('custom_logo');

        if ($custom_logo_id > 0) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');

            if (is_string($logo_url) && '' !== $logo_url) {
                return $logo_url;
            }
        }

        $site_icon_url = get_site_icon_url(512);

        return is_string($site_icon_url) ? $site_icon_url : '';
    }

    private function has_saved_frontend_data(array $data): bool
    {
        foreach ($data as $value) {
            if ('' !== trim((string) $value)) {
                return true;
            }
        }

        return false;
    }

    private function has_saved_frontend_data_for_post(int $post_id): bool
    {
        return $this->has_saved_frontend_data(
            array(
                get_post_meta($post_id, self::META_TITLE_KEY, true),
                get_post_meta($post_id, self::META_DESCRIPTION_KEY, true),
                get_post_meta($post_id, self::SOCIAL_TITLE_META_KEY, true),
                get_post_meta($post_id, self::SOCIAL_DESCRIPTION_META_KEY, true),
                get_post_meta($post_id, self::SOCIAL_IMAGE_META_KEY, true),
                get_post_meta($post_id, self::CANONICAL_URL_META_KEY, true),
                get_post_meta($post_id, self::ROBOTS_DIRECTIVES_META_KEY, true),
                get_post_meta($post_id, self::SCHEMA_TYPE_META_KEY, true),
            )
        );
    }

    public function handle_ajax_save_editor_meta(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => 'Unsupported content type.'), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        $saved_meta = $this->persist_editor_meta($post_id, $_POST);
        $post = get_post($post_id);

        wp_send_json_success(
            array(
                'message' => 'SEO draft saved.',
                'savedAt' => current_time('mysql'),
                'analysisHtml' => $post instanceof \WP_Post
                    ? $this->render_focus_keyphrase_checks_markup($post, $saved_meta['focus_keyphrase'], $saved_meta['seo_title'], $saved_meta['seo_description'])
                    : '',
            )
        );
    }

    public function handle_ajax_generate_editor_meta(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => 'Unsupported content type.'), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        try {
            $suggestion = $this->ai_generator->generate_for_post($post_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
        }

        $suggestion = array_merge(
            $suggestion,
            $this->apply_editor_text_limits(
                array(
                    'seo_title' => isset($suggestion['seo_title']) ? (string) $suggestion['seo_title'] : '',
                    'meta_description' => isset($suggestion['meta_description']) ? (string) $suggestion['meta_description'] : '',
                )
            )
        );

        $history_warning = '';

        try {
            $this->history_store->log_generation(
                $post_id,
                'post',
                get_the_title($post_id) . ' SEO history',
                array(
                    'system_prompt' => $suggestion['system_prompt'],
                    'user_prompt' => $suggestion['user_prompt'],
                    'provider' => $suggestion['provider'],
                    'model' => $suggestion['model'],
                ),
                array(
                    'seo_title' => $suggestion['seo_title'],
                    'meta_description' => $suggestion['meta_description'],
                    'notes' => $suggestion['notes'],
                    'provider' => $suggestion['provider'],
                    'model' => $suggestion['model'],
                )
            );
        } catch (\Throwable $throwable) {
            $history_warning = ' The suggestion was generated, but history could not be stored.';
        }

        $recent_suggestions = $this->history_store->get_recent_suggestions($post_id, 'post', 3);
        $post = get_post($post_id);
        $focus_keyphrase = (string) get_post_meta($post_id, self::FOCUS_KEYPHRASE_META_KEY, true);

        wp_send_json_success(
            array(
                'message' => 'AI suggestion loaded. Review it and save the draft if you want to keep it.' . $history_warning,
                'seoTitle' => $suggestion['seo_title'],
                'metaDescription' => $suggestion['meta_description'],
                'notes' => $suggestion['notes'],
                'provider' => $suggestion['provider'],
                'model' => $suggestion['model'],
                'historyHtml' => $this->render_history_markup($recent_suggestions, $this->history_store->get_recent_content_edits($post_id, 5)),
                'analysisHtml' => $post instanceof \WP_Post
                    ? $this->render_focus_keyphrase_checks_markup($post, $focus_keyphrase, $suggestion['seo_title'], $suggestion['meta_description'])
                    : '',
            )
        );
    }

    public function handle_ajax_approve_suggestion(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;

        if (! $post_id || ! $message_id) {
            wp_send_json_error(array('message' => 'Missing suggestion approval details.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => 'Unsupported content type.'), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        try {
            $approved = $this->history_store->approve_suggestion($post_id, 'post', $message_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
        }

        $approved = array_merge(
            $approved,
            $this->apply_editor_text_limits(
                array(
                    'seo_title' => isset($approved['seo_title']) ? (string) $approved['seo_title'] : '',
                    'meta_description' => isset($approved['meta_description']) ? (string) $approved['meta_description'] : '',
                )
            )
        );

        $recent_suggestions = $this->history_store->get_recent_suggestions($post_id, 'post', 5);
        $post = get_post($post_id);
        $focus_keyphrase = (string) get_post_meta($post_id, self::FOCUS_KEYPHRASE_META_KEY, true);

        wp_send_json_success(
            array(
                'message' => 'Suggestion approved for future output.',
                'seoTitle' => $approved['seo_title'],
                'metaDescription' => $approved['meta_description'],
                'notes' => $approved['notes'],
                'historyHtml' => $this->render_history_markup($recent_suggestions, $this->history_store->get_recent_content_edits($post_id, 5)),
                'analysisHtml' => $post instanceof \WP_Post
                    ? $this->render_focus_keyphrase_checks_markup($post, $focus_keyphrase, $approved['seo_title'], $approved['meta_description'])
                    : '',
            )
        );
    }

    public function handle_ajax_chat_for_post(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->is_supported_post_type($post_type)) {
            wp_send_json_error(array('message' => 'Unsupported content type.'), 400);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        if ('' === trim($message)) {
            wp_send_json_error(array('message' => 'Enter a question before asking the AI assistant.'), 400);
        }

        $options = $this->settings->get();

        if (empty($options['editor_chat_enabled'])) {
            wp_send_json_error(array('message' => 'The AI assistant is disabled in settings.'), 400);
        }

        try {
            $recent_messages = $this->history_store->get_recent_chat_messages($post_id, 8);
            $reply = $this->ai_generator->chat_for_post($post_id, $message, $recent_messages);
            $this->history_store->log_generation(
                $post_id,
                self::CHAT_OBJECT_TYPE,
                get_the_title($post_id) . ' AI chat',
                array(
                    'message' => $message,
                ),
                array(
                    'reply' => $reply['reply'],
                    'suggested_title' => $reply['suggested_title'],
                    'suggested_description' => $reply['suggested_description'],
                    'notes' => $reply['notes'],
                    'provider' => $reply['provider'],
                    'model' => $reply['model'],
                )
            );

            // If AI decided content edits are needed, auto-generate proposals.
            $content_changes = null;
            $content_summary = '';
            $content_builder = '';
            if (! empty($reply['wants_edits'])) {
                try {
                    $all_messages = $this->history_store->get_recent_chat_messages($post_id, 12);
                    $edit_result = $this->ai_generator->generate_content_changes($post_id, $message, $all_messages);
                    $content_changes = $edit_result['changes'];
                    $content_summary = $edit_result['summary'];
                    $content_builder = Content_Writer::detect_builder($post_id);
                } catch (\Throwable $edit_err) {
                    // Content edit failed; chat reply still goes through.
                    $content_changes = null;
                    $content_summary = 'Content edit proposals could not be generated: ' . $edit_err->getMessage();
                }
            }
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
        }

        $chat_messages = $this->history_store->get_recent_chat_messages($post_id, 12);

        $response_data = array(
            'message' => 'AI assistant replied.',
            'notes' => $reply['notes'],
            'chatHtml' => $this->render_chat_history_markup($chat_messages),
        );

        if (null !== $content_changes) {
            $response_data['changes'] = $content_changes;
            $response_data['summary'] = $content_summary;
            $response_data['builder'] = $content_builder;
        }

        wp_send_json_success($response_data);
    }

    public function handle_ajax_setup_index(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $count = $this->content_indexer->sync();

        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $ids = $wpdb->get_col("SELECT object_id FROM {$table} WHERE object_type = 'post' AND status = 'publish' ORDER BY object_id ASC");

        wp_send_json_success(array(
            'message' => sprintf('Site index synced. %d content records stored.', $count),
            'count' => $count,
            'publishedIds' => array_map('intval', $ids ?: array()),
        ));
    }

    public function handle_ajax_bulk_generate(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            wp_send_json_error(array('message' => 'Page not found.'), 404);
        }

        $existing_title = trim((string) get_post_meta($post_id, self::META_TITLE_KEY, true));
        $existing_desc = trim((string) get_post_meta($post_id, self::META_DESCRIPTION_KEY, true));
        $existing_keyphrase = trim((string) get_post_meta($post_id, self::FOCUS_KEYPHRASE_META_KEY, true));

        if ('' !== $existing_title && '' !== $existing_desc && '' !== $existing_keyphrase) {
            wp_send_json_success(array(
                'message' => 'Already has all metadata, skipped.',
                'skipped' => true,
                'post_id' => $post_id,
                'title' => $post->post_title,
            ));
            return;
        }

        try {
            $suggestion = $this->ai_generator->generate_for_post($post_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array(
                'message' => $throwable->getMessage(),
                'post_id' => $post_id,
                'title' => $post->post_title,
            ), 500);
            return;
        }

        $suggestion = array_merge(
            $suggestion,
            $this->apply_editor_text_limits(array(
                'seo_title' => isset($suggestion['seo_title']) ? (string) $suggestion['seo_title'] : '',
                'meta_description' => isset($suggestion['meta_description']) ? (string) $suggestion['meta_description'] : '',
            ))
        );

        if ('' === $existing_title) {
            update_post_meta($post_id, self::META_TITLE_KEY, $suggestion['seo_title']);
        }
        if ('' === $existing_desc) {
            update_post_meta($post_id, self::META_DESCRIPTION_KEY, $suggestion['meta_description']);
        }
        if ('' === $existing_keyphrase && ! empty($suggestion['focus_keyphrase'])) {
            update_post_meta($post_id, self::FOCUS_KEYPHRASE_META_KEY, $suggestion['focus_keyphrase']);
        }

        try {
            $this->history_store->log_generation(
                $post_id,
                'post',
                $post->post_title . ' SEO history',
                array(
                    'system_prompt' => $suggestion['system_prompt'],
                    'user_prompt' => $suggestion['user_prompt'],
                    'provider' => $suggestion['provider'],
                    'model' => $suggestion['model'],
                ),
                array(
                    'seo_title' => $suggestion['seo_title'],
                    'meta_description' => $suggestion['meta_description'],
                    'notes' => $suggestion['notes'],
                    'provider' => $suggestion['provider'],
                    'model' => $suggestion['model'],
                )
            );
        } catch (\Throwable $throwable) {
            // History failure is non-fatal
        }

        wp_send_json_success(array(
            'message' => 'Generated metadata for: ' . $post->post_title,
            'skipped' => false,
            'post_id' => $post_id,
            'title' => $post->post_title,
            'seo_title' => $suggestion['seo_title'],
            'meta_description' => $suggestion['meta_description'],
            'focus_keyphrase' => $suggestion['focus_keyphrase'] ?? '',
            'notes' => $suggestion['notes'],
        ));
    }

    public function handle_ajax_page_audit(): void
    {
        // Accept both editor nonce and wizard nonce since this is called from both contexts.
        if (
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'ai_seo_keeper_save_editor_meta')
            && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'ai_seo_keeper_setup_wizard')
        ) {
            wp_send_json_error(array('message' => 'Security check failed.'), 403);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        $post = get_post($post_id);

        if (! $post instanceof \WP_Post) {
            wp_send_json_error(array('message' => 'Page not found.'), 404);
        }

        // Return cached audit if already completed.
        $cached = get_post_meta($post_id, '_ai_seo_keeper_page_audit', true);

        if (is_array($cached) && isset($cached['score'])) {
            wp_send_json_success(array(
                'post_id' => $post_id,
                'title' => $post->post_title,
                'permalink' => get_permalink($post_id),
                'score' => $cached['score'],
                'issues' => $cached['issues'],
                'suggestions' => $cached['suggestions'],
                'missing_alt_tags' => $cached['missing_alt_tags'],
                'word_count' => $cached['word_count'],
                'heading_structure' => $cached['heading_structure'],
                'summary' => $cached['summary'],
                'cached' => true,
            ));
            return;
        }

        try {
            $audit = $this->ai_generator->generate_page_audit($post_id);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array(
                'message' => $throwable->getMessage(),
                'post_id' => $post_id,
                'title' => $post->post_title,
            ), 500);
            return;
        }

        // Persist audit results for resume and for use elsewhere.
        update_post_meta($post_id, '_ai_seo_keeper_page_audit', array(
            'score' => $audit['score'],
            'issues' => $audit['issues'],
            'suggestions' => $audit['suggestions'],
            'missing_alt_tags' => $audit['missing_alt_tags'],
            'word_count' => $audit['word_count'],
            'heading_structure' => $audit['heading_structure'],
            'summary' => $audit['summary'],
            'audited_at' => current_time('mysql', true),
        ));

        wp_send_json_success(array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'permalink' => get_permalink($post_id),
            'score' => $audit['score'],
            'issues' => $audit['issues'],
            'suggestions' => $audit['suggestions'],
            'missing_alt_tags' => $audit['missing_alt_tags'],
            'word_count' => $audit['word_count'],
            'heading_structure' => $audit['heading_structure'],
            'summary' => $audit['summary'],
            'cached' => false,
        ));
    }

    public function handle_ajax_toggle_audit_skip(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        $current = get_post_meta($post_id, '_ai_seo_keeper_audit_skip', true);
        $new_value = empty($current) ? '1' : '';

        if ('' === $new_value) {
            delete_post_meta($post_id, '_ai_seo_keeper_audit_skip');
        } else {
            update_post_meta($post_id, '_ai_seo_keeper_audit_skip', '1');
        }

        wp_send_json_success(array(
            'post_id' => $post_id,
            'skipped' => '' !== $new_value,
        ));
    }

    public function handle_ajax_save_skip_patterns(): void
    {
        check_ajax_referer('ai_seo_keeper_setup_wizard', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $patterns = isset($_POST['patterns']) ? sanitize_textarea_field(wp_unslash($_POST['patterns'])) : '';

        $options = $this->settings->get();
        $options['audit_skip_patterns'] = $patterns;
        update_option('ai_seo_keeper_options', $options);

        // Count how many published pages match.
        $matched = $this->count_pages_matching_skip_patterns($patterns);

        wp_send_json_success(array(
            'patterns' => $patterns,
            'matched_count' => $matched,
        ));
    }

    private function count_pages_matching_skip_patterns(string $patterns_text): int
    {
        $patterns = $this->parse_skip_patterns($patterns_text);

        if (empty($patterns)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $rows = $wpdb->get_col("SELECT permalink FROM {$table} WHERE object_type = 'post' AND status = 'publish'");
        $count = 0;
        $home = trailingslashit(home_url());

        foreach ($rows as $url) {
            $path = '/' . ltrim(str_replace($home, '', $url), '/');

            if ($this->path_matches_skip_patterns($path, $patterns)) {
                $count++;
            }
        }

        return $count;
    }

    private function parse_skip_patterns(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $patterns = array();

        foreach ($lines as $line) {
            if ('' === $line || '#' === $line[0]) {
                continue;
            }
            $patterns[] = $line;
        }

        return $patterns;
    }

    public function is_audit_skipped(int $post_id): bool
    {
        // Individual page skip.
        if (! empty(get_post_meta($post_id, '_ai_seo_keeper_audit_skip', true))) {
            return true;
        }

        // Pattern-based skip.
        $options = $this->settings->get();
        $patterns = $this->parse_skip_patterns((string) $options['audit_skip_patterns']);

        if (empty($patterns)) {
            return false;
        }

        $permalink = (string) get_permalink($post_id);
        $home = trailingslashit(home_url());
        $path = '/' . ltrim(str_replace($home, '', $permalink), '/');

        return $this->path_matches_skip_patterns($path, $patterns);
    }

    private function path_matches_skip_patterns(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Convert glob-like pattern to regex.
            // Supports: * (any segment chars), ** (any depth), ? (single char).
            $regex = str_replace(array('\\*\\*', '\\*', '\\?'), array('.*', '[^/]*', '[^/]'), preg_quote($pattern, '#'));
            if (preg_match('#^' . $regex . '$#i', $path)) {
                return true;
            }
        }

        return false;
    }

    public function handle_ajax_content_edit(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $instruction = isset($_POST['instruction']) ? sanitize_textarea_field(wp_unslash($_POST['instruction'])) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        if ('' === trim($instruction)) {
            wp_send_json_error(array('message' => 'Provide an instruction for the AI content editor.'), 400);
        }

        try {
            $result = $this->ai_generator->generate_content_changes($post_id, $instruction);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 500);
        }

        wp_send_json_success(array(
            'changes' => $result['changes'],
            'summary' => $result['summary'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'builder' => Content_Writer::detect_builder($post_id),
        ));
    }

    public function handle_ajax_apply_changes(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $changes_json = isset($_POST['changes']) ? wp_unslash($_POST['changes']) : '';
        $summary = isset($_POST['summary']) ? sanitize_textarea_field(wp_unslash($_POST['summary'])) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        $changes = json_decode($changes_json, true);
        if (! is_array($changes) || empty($changes)) {
            wp_send_json_error(array('message' => 'No changes to apply.'), 400);
        }

        // Store as pending — changes will be applied when the user clicks Update/Publish.
        Content_Writer::store_pending_changes($post_id, $changes, $summary);

        // Log to history as a content edit plan.
        $this->history_store->log_generation(
            $post_id,
            'content_edit',
            get_the_title($post_id) . ' — content edit plan',
            array('instruction' => $summary),
            array(
                'content_edit_summary' => $summary,
                'content_edit_count' => count($changes),
                'content_edit_status' => 'pending',
                'changes' => $changes,
                'provider' => 'ai',
                'model' => '',
            )
        );

        wp_send_json_success(array(
            'applied' => count($changes),
            'failed' => 0,
            'details' => array(),
            'message' => sprintf(
                '%d change(s) approved. Preview the page, then click Update to publish them.',
                count($changes)
            ),
        ));
    }

    public function handle_ajax_apply_suggestion(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $field = isset($_POST['field']) ? sanitize_text_field(wp_unslash($_POST['field'])) : '';
        $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        $allowed_fields = array(
            'meta_title' => self::META_TITLE_KEY,
            'meta_description' => self::META_DESCRIPTION_KEY,
        );

        if (! isset($allowed_fields[$field])) {
            wp_send_json_error(array('message' => 'Invalid field: ' . $field), 400);
        }

        $meta_key = $allowed_fields[$field];
        $sanitized = 'meta_title' === $field ? sanitize_text_field($value) : sanitize_textarea_field($value);
        update_post_meta($post_id, $meta_key, $sanitized);

        wp_send_json_success(array(
            'field' => $field,
            'value' => $sanitized,
            'message' => ucfirst(str_replace('_', ' ', $field)) . ' updated.',
        ));
    }

    public function handle_ajax_restore_backup(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'You are not allowed to edit this post.'), 403);
        }

        $restored = Content_Writer::restore_backup($post_id);

        if (! $restored) {
            wp_send_json_error(array('message' => 'No backup found for this page.'), 404);
        }

        wp_send_json_success(array('message' => 'Content restored to the version before AI edits.'));
    }

    public function handle_ajax_clear_chat(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        if (! $post_id) {
            wp_send_json_error(array('message' => 'Missing post id.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $deleted = $this->history_store->clear_chat_messages($post_id);

        wp_send_json_success(array('message' => $deleted . ' message(s) cleared.', 'deleted' => $deleted));
    }

    public function handle_ajax_test_model(): void
    {
        check_ajax_referer('ai_seo_keeper_settings_test_model', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You are not allowed to test AI model access.'), 403);
        }

        $options = $this->settings->get();
        $provider = isset($_POST['provider']) ? sanitize_key((string) wp_unslash($_POST['provider'])) : (string) ($options['provider'] ?? 'openai');
        $supported_providers = Settings::get_supported_providers();

        if (! in_array($provider, $supported_providers, true)) {
            wp_send_json_error(array('message' => 'Unsupported AI provider selected.'), 400);
        }

        $requested_model = isset($_POST['model']) ? sanitize_text_field((string) wp_unslash($_POST['model'])) : '';
        $allowed_models = Settings::get_models_for_provider($provider);
        if (isset($allowed_models[$requested_model])) {
            $model = $requested_model;
        } else {
            $custom_model = Settings::sanitize_custom_model_id($requested_model);
            $model = '' !== $custom_model ? $custom_model : Settings::sanitize_provider_model($provider, $requested_model);
        }

        $posted_temperature = isset($_POST['temperature']) ? (string) wp_unslash($_POST['temperature']) : '';
        $temperature = is_numeric($posted_temperature) ? (float) $posted_temperature : (float) ($options['ai_temperature'] ?? 0.3);
        $temperature = max(0.0, min(2.0, $temperature));
        $temperature = round($temperature, 1);

        $posted_api_key = isset($_POST['api_key']) ? sanitize_text_field((string) wp_unslash($_POST['api_key'])) : '';
        $api_key = '' !== trim($posted_api_key) ? $posted_api_key : (string) ($options['api_key'] ?? '');

        if ('' === trim($api_key)) {
            wp_send_json_error(array('message' => 'Enter an API key before testing model availability.'), 400);
        }

        try {
            $result = $this->ai_generator->test_model_connection($provider, $api_key, $model, $temperature);
        } catch (\Throwable $throwable) {
            wp_send_json_error(array('message' => $throwable->getMessage()), 400);
        }

        $preview = isset($result['preview']) ? sanitize_text_field((string) $result['preview']) : '';
        $temperature_note = '';
        if ('openai' === $provider && preg_match('/^o[1-9]/i', $model)) {
            $temperature_note = ' (provider default temperature mode)';
        }
        $message = sprintf(
            'Model is available: %s / %s%s',
            ucfirst($provider),
            $model,
            $temperature_note . ('' !== $preview ? ' - ' . $preview : '')
        );

        wp_send_json_success(array(
            'message' => $message,
            'provider' => $provider,
            'model' => $model,
            'temperature' => $temperature,
        ));
    }

    public function handle_ajax_delete_edit_plan(): void
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $edit_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;

        if (! $post_id || ! $edit_id) {
            wp_send_json_error(array('message' => 'Missing parameters.'), 400);
        }

        check_ajax_referer('ai_seo_keeper_save_editor_meta', 'nonce');

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $this->history_store->delete_content_edit_plan($edit_id);

        wp_send_json_success(array('message' => 'Plan removed from history.'));
    }

    public function render_setup_wizard_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = $this->settings->get();
        $has_api_key = ! empty($options['api_key']);
        $summary = $this->content_indexer->get_summary();
        $has_index = (int) $summary['total_items'] > 0;

        // Check if Step 2 was already completed (any page has AI-generated metadata).
        $has_metadata = false;
        if ($has_index) {
            global $wpdb;
            $meta_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ai_seo_keeper_meta_title' AND meta_value != ''"
            );
            $has_metadata = $meta_count > 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ai_seo_keeper_content_index';
        $published_ids = $wpdb->get_col("SELECT object_id FROM {$table} WHERE object_type = 'post' AND status = 'publish' ORDER BY object_id ASC");
        $published_ids_json = wp_json_encode(array_map('intval', $published_ids ?: array()));
        // Count pages already audited for resume detection.
        $audited_count = 0;
        $existing_audits = array();
        $skipped_ids = array();
        if ($has_index) {
            $audit_rows = $wpdb->get_results(
                "SELECT pm.post_id, pm.meta_value, p.post_title
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_ai_seo_keeper_page_audit'
                ORDER BY pm.post_id ASC",
                ARRAY_A
            );
            $audited_count = count($audit_rows);

            // Collect individually skipped page IDs.
            $skip_rows = $wpdb->get_col(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ai_seo_keeper_audit_skip' AND meta_value = '1'"
            );
            $skipped_ids = array_map('intval', $skip_rows ?: array());

            foreach ($audit_rows as $ar) {
                $val = maybe_unserialize($ar['meta_value']);
                if (is_array($val) && isset($val['score'])) {
                    $post_id_row = (int) $ar['post_id'];
                    $existing_audits[] = array(
                        'post_id'           => $post_id_row,
                        'title'             => (string) $ar['post_title'],
                        'permalink'         => (string) get_permalink($post_id_row),
                        'score'             => (int) $val['score'],
                        'issues'            => isset($val['issues']) ? $val['issues'] : array(),
                        'suggestions'       => isset($val['suggestions']) ? $val['suggestions'] : array(),
                        'missing_alt_tags'  => isset($val['missing_alt_tags']) ? (int) $val['missing_alt_tags'] : 0,
                        'word_count'        => isset($val['word_count']) ? (int) $val['word_count'] : 0,
                        'heading_structure' => isset($val['heading_structure']) ? (string) $val['heading_structure'] : '',
                        'summary'           => isset($val['summary']) ? (string) $val['summary'] : '',
                        'cached'            => true,
                        'audit_skipped'     => in_array($post_id_row, $skipped_ids, true) || $this->is_audit_skipped($post_id_row),
                    );
                }
            }
        }
        $existing_audits_json = wp_json_encode($existing_audits);
        $skipped_ids_json = wp_json_encode($skipped_ids);
        $skip_patterns = (string) $options['audit_skip_patterns'];
        $total_pages = count($published_ids ?: array());
        $step2_all_done = $has_metadata && $total_pages > 0 && $meta_count >= $total_pages;
        $step3_all_done = $audited_count > 0 && $total_pages > 0 && $audited_count >= $total_pages;

        // Pass private constants as local vars for view file.
        $ajax_setup_index_action      = self::AJAX_SETUP_INDEX_ACTION;
        $ajax_bulk_generate_action    = self::AJAX_BULK_GENERATE_ACTION;
        $ajax_page_audit_action       = self::AJAX_PAGE_AUDIT_ACTION;
        $ajax_toggle_audit_skip_action = self::AJAX_TOGGLE_AUDIT_SKIP_ACTION;
        $ajax_save_skip_patterns_action = self::AJAX_SAVE_SKIP_PATTERNS_ACTION;

        require __DIR__ . '/admin/view-setup-wizard.php';
    }

    public function save_editor_meta(int $post_id): void
    {
        if (! isset($_POST['ai_seo_keeper_editor_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai_seo_keeper_editor_nonce'])), 'ai_seo_keeper_save_editor_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        $post_type = get_post_type($post_id);

        if (! $post_type || ! $this->is_supported_post_type($post_type)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->persist_editor_meta($post_id, $_POST);

        // Apply any pending AI content changes on Update/Publish.
        $pending = Content_Writer::get_pending_changes($post_id);
        if (! empty($pending)) {
            $result = Content_Writer::apply_pending_changes($post_id);

            // Only mark as published if at least one change was actually applied.
            if (! empty($result) && $result['applied'] > 0) {
                $this->history_store->update_content_edit_status($post_id, 'published');
            }
        }
    }

    private function persist_editor_meta(int $post_id, array $raw_input): array
    {
        $focus_keyphrase = isset($raw_input['ai_seo_keeper_focus_keyphrase']) ? sanitize_text_field(wp_unslash($raw_input['ai_seo_keeper_focus_keyphrase'])) : '';
        $seo_title = isset($raw_input['ai_seo_keeper_meta_title']) ? sanitize_text_field(wp_unslash($raw_input['ai_seo_keeper_meta_title'])) : '';
        $seo_description = isset($raw_input['ai_seo_keeper_meta_description']) ? sanitize_textarea_field(wp_unslash($raw_input['ai_seo_keeper_meta_description'])) : '';
        $social_title = isset($raw_input['ai_seo_keeper_social_title']) ? sanitize_text_field(wp_unslash($raw_input['ai_seo_keeper_social_title'])) : '';
        $social_description = isset($raw_input['ai_seo_keeper_social_description']) ? sanitize_textarea_field(wp_unslash($raw_input['ai_seo_keeper_social_description'])) : '';
        $social_image = isset($raw_input['ai_seo_keeper_social_image']) ? esc_url_raw(wp_unslash($raw_input['ai_seo_keeper_social_image'])) : '';
        $schema_type = isset($raw_input['ai_seo_keeper_schema_type']) ? sanitize_text_field(wp_unslash($raw_input['ai_seo_keeper_schema_type'])) : '';
        $canonical_url = isset($raw_input['ai_seo_keeper_canonical_url']) ? esc_url_raw(wp_unslash($raw_input['ai_seo_keeper_canonical_url'])) : '';
        $robots_directives = isset($raw_input['ai_seo_keeper_robots_directives']) ? sanitize_text_field(wp_unslash($raw_input['ai_seo_keeper_robots_directives'])) : '';
        $frontend_enabled = empty($raw_input['ai_seo_keeper_frontend_enabled']) ? '' : '1';
        $title_branding_off = empty($raw_input['ai_seo_keeper_title_branding_off']) ? '' : '1';
        $cornerstone = empty($raw_input['ai_seo_keeper_cornerstone']) ? '' : '1';

        $limited_fields = $this->apply_editor_text_limits(
            array(
                'seo_title' => $seo_title,
                'meta_description' => $seo_description,
                'social_title' => $social_title,
                'social_description' => $social_description,
            )
        );

        $seo_title = $limited_fields['seo_title'];
        $seo_description = $limited_fields['meta_description'];
        $social_title = $limited_fields['social_title'];
        $social_description = $limited_fields['social_description'];

        if (! in_array($schema_type, array_keys($this->get_schema_type_options()), true)) {
            $schema_type = '';
        }

        if (! in_array($robots_directives, array_keys($this->get_robots_directive_options()), true)) {
            $robots_directives = '';
        }

        if ('' === $focus_keyphrase) {
            delete_post_meta($post_id, self::FOCUS_KEYPHRASE_META_KEY);
        } else {
            update_post_meta($post_id, self::FOCUS_KEYPHRASE_META_KEY, $focus_keyphrase);
        }

        if ('' === $seo_title) {
            delete_post_meta($post_id, self::META_TITLE_KEY);
        } else {
            update_post_meta($post_id, self::META_TITLE_KEY, $seo_title);
        }

        if ('' === $seo_description) {
            delete_post_meta($post_id, self::META_DESCRIPTION_KEY);
        } else {
            update_post_meta($post_id, self::META_DESCRIPTION_KEY, $seo_description);
        }

        if ('' === $social_title) {
            delete_post_meta($post_id, self::SOCIAL_TITLE_META_KEY);
        } else {
            update_post_meta($post_id, self::SOCIAL_TITLE_META_KEY, $social_title);
        }

        if ('' === $social_description) {
            delete_post_meta($post_id, self::SOCIAL_DESCRIPTION_META_KEY);
        } else {
            update_post_meta($post_id, self::SOCIAL_DESCRIPTION_META_KEY, $social_description);
        }

        if ('' === $social_image) {
            delete_post_meta($post_id, self::SOCIAL_IMAGE_META_KEY);
        } else {
            update_post_meta($post_id, self::SOCIAL_IMAGE_META_KEY, $social_image);
        }

        if ('' === $schema_type) {
            delete_post_meta($post_id, self::SCHEMA_TYPE_META_KEY);
        } else {
            update_post_meta($post_id, self::SCHEMA_TYPE_META_KEY, $schema_type);
        }

        if ('' === $canonical_url) {
            delete_post_meta($post_id, self::CANONICAL_URL_META_KEY);
        } else {
            update_post_meta($post_id, self::CANONICAL_URL_META_KEY, $canonical_url);
        }

        if ('' === $robots_directives) {
            delete_post_meta($post_id, self::ROBOTS_DIRECTIVES_META_KEY);
        } else {
            update_post_meta($post_id, self::ROBOTS_DIRECTIVES_META_KEY, $robots_directives);
        }

        if ('' === $frontend_enabled) {
            delete_post_meta($post_id, self::FRONTEND_ENABLE_META_KEY);
        } else {
            update_post_meta($post_id, self::FRONTEND_ENABLE_META_KEY, '1');
        }

        if ('' === $title_branding_off) {
            delete_post_meta($post_id, self::TITLE_BRANDING_OFF_META_KEY);
        } else {
            update_post_meta($post_id, self::TITLE_BRANDING_OFF_META_KEY, '1');
        }

        if ('' === $cornerstone) {
            delete_post_meta($post_id, '_ai_seo_keeper_cornerstone');
        } else {
            update_post_meta($post_id, '_ai_seo_keeper_cornerstone', '1');
        }

        // Hreflang manual entries.
        $hreflang = isset($raw_input['ai_seo_keeper_hreflang']) ? sanitize_textarea_field(wp_unslash($raw_input['ai_seo_keeper_hreflang'])) : '';
        if ('' === $hreflang) {
            delete_post_meta($post_id, '_ai_seo_keeper_hreflang');
        } else {
            update_post_meta($post_id, '_ai_seo_keeper_hreflang', $hreflang);
        }

        return array(
            'focus_keyphrase' => $focus_keyphrase,
            'seo_title' => $seo_title,
            'seo_description' => $seo_description,
            'social_title' => $social_title,
            'social_description' => $social_description,
            'social_image' => $social_image,
            'schema_type' => $schema_type,
            'canonical_url' => $canonical_url,
            'robots_directives' => $robots_directives,
            'frontend_enabled' => $frontend_enabled,
        );
    }

    private function render_field_counter(string $field_id, string $field_value, int $max_length, string $branding_suffix = ''): string
    {
        $current_length = $this->get_text_length($field_value);
        $suffix_length = '' !== $branding_suffix ? $this->get_text_length($branding_suffix) : 0;
        $total_length = $current_length + $suffix_length;
        $state_class = 'is-neutral';

        if ($total_length > $max_length) {
            $state_class = 'is-limit';
        } elseif ($total_length >= max(1, $max_length - 10)) {
            $state_class = 'is-warning';
        }

        $display = $suffix_length > 0
            ? sprintf('%d + %d (brand) = %d / %d characters', $current_length, $suffix_length, $total_length, $max_length)
            : sprintf('%d / %d characters', $current_length, $max_length);

        return sprintf(
            '<span class="ai-seo-keeper-field-counter %s" data-field-id="%s" data-branding-suffix-length="%d" aria-live="polite">%s</span>',
            esc_attr($state_class),
            esc_attr($field_id),
            $suffix_length,
            esc_html($display)
        );
    }

    private function apply_editor_text_limits(array $fields): array
    {
        $limit_map = array(
            'seo_title' => self::TITLE_MAX_LENGTH,
            'meta_description' => self::DESCRIPTION_MAX_LENGTH,
            'social_title' => self::TITLE_MAX_LENGTH,
            'social_description' => self::DESCRIPTION_MAX_LENGTH,
        );

        foreach ($limit_map as $field_key => $max_length) {
            if (! array_key_exists($field_key, $fields)) {
                continue;
            }

            $fields[$field_key] = $this->truncate_text((string) $fields[$field_key], $max_length);
        }

        return $fields;
    }

    private function get_text_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private function truncate_text(string $text, int $max_length): string
    {
        if ('' === $text || $max_length < 1 || $this->get_text_length($text) <= $max_length) {
            return $text;
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max_length) : substr($text, 0, $max_length);
    }

    private function get_schema_type_options(): array
    {
        return array(
            '' => 'Automatic detection',
            'WebPage' => 'Web Page',
            'AboutPage' => 'About Page',
            'ContactPage' => 'Contact Page',
            'Article' => 'Article',
            'Service' => 'Service',
            'Product' => 'Product',
            'CollectionPage' => 'Collection Page',
        );
    }

    private function get_robots_directive_options(): array
    {
        return array(
            '' => 'Automatic site default',
            'index,follow' => 'Index, follow',
            'noindex,follow' => 'Noindex, follow',
            'index,nofollow' => 'Index, nofollow',
            'noindex,nofollow' => 'Noindex, nofollow',
        );
    }

    private function render_focus_keyphrase_checks_markup(\WP_Post $post, string $focus_keyphrase, string $seo_title, string $seo_description): string
    {
        $title_length = $this->get_text_length($seo_title);
        $description_length = $this->get_text_length($seo_description);
        $raw_content = Content_Helper::get_content($post);
        $content = wp_strip_all_tags($raw_content);
        $normalized_content = $this->normalize_text_for_match($content);
        $content_word_count = '' === $normalized_content ? 0 : count(preg_split('/\s+/', $normalized_content));
        $subheading_count = preg_match_all('/<h[2-6][^>]*>/i', $raw_content);
        $sentences = $this->extract_sentences($content);
        $sentence_count = count($sentences);
        $paragraphs = $this->extract_content_blocks($raw_content);
        $paragraph_count = count($paragraphs);
        $transition_word_count = $this->count_transition_words($normalized_content);
        $passive_voice_sentence_count = $this->count_passive_voice_sentences($sentences);
        $repeated_sentence_start_count = $this->count_repeated_sentence_starts($sentences);
        $list_count = preg_match_all('/<(ul|ol)\b/i', $raw_content);
        $question_heading_count = $this->count_question_style_headings($raw_content);
        $long_sentence_count = 0;
        $total_sentence_words = 0;
        $long_paragraph_count = 0;
        $image_matches = array();
        $images_with_alt = 0;
        $internal_link_count = 0;
        $external_link_count = 0;
        $generic_anchor_count = 0;
        $link_count = 0;
        $site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);

        foreach ($sentences as $sentence) {
            $sentence_word_count = $this->count_words($sentence);

            if ($sentence_word_count <= 0) {
                continue;
            }

            $total_sentence_words += $sentence_word_count;

            if ($sentence_word_count > 24) {
                $long_sentence_count += 1;
            }
        }

        foreach ($paragraphs as $paragraph) {
            if ($this->count_words($paragraph) > 120) {
                $long_paragraph_count += 1;
            }
        }

        $average_sentence_words = $sentence_count > 0 ? round($total_sentence_words / $sentence_count, 1) : 0;

        preg_match_all('/<img\b[^>]*>/i', $raw_content, $image_matches);

        foreach ($image_matches[0] as $image_html) {
            if (preg_match('/\balt=("|\')(.*?)\1/i', $image_html, $alt_match) && '' !== trim(wp_strip_all_tags($alt_match[2]))) {
                $images_with_alt += 1;
            }
        }

        $link_matches = array();
        preg_match_all('/<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $raw_content, $link_matches, PREG_SET_ORDER);

        foreach ($link_matches as $link_match) {
            $href = isset($link_match[2]) ? trim(html_entity_decode((string) $link_match[2])) : '';
            $anchor_text = isset($link_match[3]) ? trim(wp_strip_all_tags(html_entity_decode((string) $link_match[3]))) : '';

            if ('' === $href || 0 === strpos($href, '#') || 0 === strpos($href, 'mailto:') || 0 === strpos($href, 'tel:')) {
                continue;
            }

            $link_count += 1;

            if ($this->is_generic_anchor_text($anchor_text)) {
                $generic_anchor_count += 1;
            }

            $link_host = (string) wp_parse_url($href, PHP_URL_HOST);

            if ('' === $link_host || $link_host === $site_host) {
                $internal_link_count += 1;
                continue;
            }

            $external_link_count += 1;
        }

        $checks = array(
            array(
                'label' => 'SEO title length',
                'passed' => $title_length >= self::TITLE_MIN_LENGTH && $title_length <= self::TITLE_MAX_LENGTH,
                'message' => '' === $seo_title
                    ? 'Add an SEO title.'
                    : sprintf('Current length: %d characters. Target roughly %d to %d, with a maximum of %d.', $title_length, self::TITLE_MIN_LENGTH, self::TITLE_MAX_LENGTH, self::TITLE_MAX_LENGTH),
            ),
            array(
                'label' => 'Meta description length',
                'passed' => $description_length >= self::DESCRIPTION_MIN_LENGTH && $description_length <= self::DESCRIPTION_MAX_LENGTH,
                'message' => '' === $seo_description
                    ? 'Add a meta description.'
                    : sprintf('Current length: %d characters. Target roughly %d to %d, with a maximum of %d.', $description_length, self::DESCRIPTION_MIN_LENGTH, self::DESCRIPTION_MAX_LENGTH, self::DESCRIPTION_MAX_LENGTH),
            ),
            array(
                'label' => 'Content length',
                'passed' => $content_word_count >= 250,
                'message' => 0 === $content_word_count
                    ? 'Add meaningful page content before relying on metadata alone.'
                    : sprintf('Current body length: %d words. For most pages, aim for at least 250 words of useful content.', $content_word_count),
            ),
            array(
                'label' => 'Subheadings in content',
                'passed' => $subheading_count >= 1,
                'message' => $subheading_count >= 1
                    ? sprintf('Found %d subheading%s in the page body.', $subheading_count, 1 === $subheading_count ? '' : 's')
                    : 'Add at least one H2 or H3 subheading to make the page easier to scan.',
            ),
            array(
                'label' => 'List structure in content',
                'passed' => $list_count >= 1 || $content_word_count < 220,
                'message' => $list_count >= 1
                    ? sprintf('Found %d list%s in the page body for scannable structure.', $list_count, 1 === $list_count ? '' : 's')
                    : ($content_word_count < 220
                        ? 'This page is short enough that list structure is optional.'
                        : 'Consider adding at least one bullet or numbered list for steps, features, or grouped points.'),
            ),
            array(
                'label' => 'Question-style subheadings',
                'passed' => $question_heading_count >= 1 || $subheading_count < 2 || $content_word_count < 250,
                'message' => $question_heading_count >= 2
                    ? sprintf('Found %d question-style heading%s. This content may qualify for live FAQ schema output when each question is followed by a direct answer.', $question_heading_count, 1 === $question_heading_count ? '' : 's')
                    : ($question_heading_count === 1
                        ? 'Found one question-style heading that can help match informational search intent.'
                        : (($subheading_count < 2 || $content_word_count < 250)
                            ? 'Question-style headings are optional on shorter or simpler pages.'
                            : 'Consider adding a question-style subheading when the page targets informational search intent or FAQ-style queries.')),
            ),
            array(
                'label' => 'Internal links in content',
                'passed' => $internal_link_count >= 1,
                'message' => $internal_link_count >= 1
                    ? sprintf('Found %d internal link%s that connect this page to the rest of the site.', $internal_link_count, 1 === $internal_link_count ? '' : 's')
                    : 'Add at least one internal link to strengthen crawl paths and user navigation.',
            ),
            array(
                'label' => 'Outbound links in content',
                'passed' => $external_link_count >= 1,
                'message' => $external_link_count >= 1
                    ? sprintf('Found %d outbound link%s that point to external sources or references.', $external_link_count, 1 === $external_link_count ? '' : 's')
                    : 'Add an outbound link when the page benefits from citing a credible outside source or reference.',
            ),
            array(
                'label' => 'Descriptive anchor text',
                'passed' => 0 === $link_count || 0 === $generic_anchor_count,
                'message' => 0 === $link_count
                    ? 'No content links were found yet, so anchor-text quality cannot be assessed.'
                    : (0 === $generic_anchor_count
                        ? sprintf('Checked %d link%s and none use obvious generic anchor text.', $link_count, 1 === $link_count ? '' : 's')
                        : sprintf('Generic anchor text detected on %d of %d link%s. Replace phrases like "click here" with destination-specific wording.', $generic_anchor_count, $link_count, 1 === $link_count ? '' : 's')),
            ),
            array(
                'label' => 'Image alt coverage',
                'passed' => 0 === count($image_matches[0]) || $images_with_alt === count($image_matches[0]),
                'message' => 0 === count($image_matches[0])
                    ? 'No inline images found in the page body, so alt text is not required here.'
                    : sprintf('Images with alt text: %d of %d.', $images_with_alt, count($image_matches[0])),
            ),
        );

        $recommended_transition_count = $sentence_count >= 8 ? 3 : ($sentence_count >= 3 ? 2 : 1);
        $recommended_passive_voice_limit = max(1, (int) ceil(max(1, $sentence_count) * 0.2));
        $recommended_repeated_starts_limit = max(1, (int) ceil(max(1, $sentence_count - 1) * 0.15));
        $readability_checks = array(
            array(
                'label' => 'Sentence length balance',
                'passed' => $sentence_count > 0 && $average_sentence_words <= 20 && $long_sentence_count <= max(1, (int) ceil($sentence_count * 0.25)),
                'message' => 0 === $sentence_count
                    ? 'Add body copy before readability can be assessed.'
                    : sprintf('Average sentence length: %s words. Long sentences over 24 words: %d of %d.', number_format_i18n($average_sentence_words, 1), $long_sentence_count, $sentence_count),
            ),
            array(
                'label' => 'Paragraph length balance',
                'passed' => $paragraph_count > 0 && $long_paragraph_count <= max(1, (int) ceil($paragraph_count * 0.34)),
                'message' => 0 === $paragraph_count
                    ? 'Add structured paragraphs to assess reading flow.'
                    : sprintf('Detected %d paragraph%s. Long paragraphs over 120 words: %d.', $paragraph_count, 1 === $paragraph_count ? '' : 's', $long_paragraph_count),
            ),
            array(
                'label' => 'Transition word usage',
                'passed' => $sentence_count < 2 || $transition_word_count >= $recommended_transition_count,
                'message' => sprintf('Detected %d transition word%s. Aim for at least %d to improve flow between ideas.', $transition_word_count, 1 === $transition_word_count ? '' : 's', $recommended_transition_count),
            ),
            array(
                'label' => 'Passive voice estimate',
                'passed' => $sentence_count < 2 || $passive_voice_sentence_count <= $recommended_passive_voice_limit,
                'message' => 0 === $sentence_count
                    ? 'Add body copy before passive voice can be estimated.'
                    : sprintf('Estimated passive-voice sentences: %d of %d. This is a heuristic, not a full grammar parser.', $passive_voice_sentence_count, $sentence_count),
            ),
            array(
                'label' => 'Repeated sentence starts',
                'passed' => $sentence_count < 3 || $repeated_sentence_start_count <= $recommended_repeated_starts_limit,
                'message' => 0 === $sentence_count
                    ? 'Add body copy before sentence-start variety can be assessed.'
                    : sprintf('Consecutive repeated sentence openings detected: %d. Varying openings keeps the copy from sounding mechanical.', $repeated_sentence_start_count),
            ),
        );

        if ('' !== $focus_keyphrase) {
            $normalized_keyphrase = $this->normalize_text_for_match($focus_keyphrase);
            $checks[] = array(
                'label' => 'Focus keyphrase in SEO title',
                'passed' => '' !== $normalized_keyphrase && false !== strpos($this->normalize_text_for_match($seo_title), $normalized_keyphrase),
                'message' => 'Use the focus keyphrase naturally in the SEO title.',
            );
            $checks[] = array(
                'label' => 'Focus keyphrase in meta description',
                'passed' => '' !== $normalized_keyphrase && false !== strpos($this->normalize_text_for_match($seo_description), $normalized_keyphrase),
                'message' => 'Use the focus keyphrase naturally in the meta description.',
            );
            $checks[] = array(
                'label' => 'Focus keyphrase in URL',
                'passed' => '' !== $normalized_keyphrase && false !== strpos($this->normalize_text_for_match((string) get_permalink($post)), $normalized_keyphrase),
                'message' => 'Short URLs that reflect the target phrase are easier for users and crawlers.',
            );
            $checks[] = array(
                'label' => 'Focus keyphrase in page content',
                'passed' => '' !== $normalized_keyphrase && false !== strpos($this->normalize_text_for_match($content), $normalized_keyphrase),
                'message' => 'The real page content should reinforce the target phrase, not just the metadata.',
            );

            // Keyphrase density.
            if ($content_word_count > 0 && '' !== $normalized_keyphrase) {
                $keyphrase_word_count = count(preg_split('/\s+/', $normalized_keyphrase));
                $keyphrase_occurrences = mb_substr_count($this->normalize_text_for_match($content), $normalized_keyphrase);
                $density = ($keyphrase_occurrences * $keyphrase_word_count / $content_word_count) * 100;
                $density_rounded = round($density, 1);
                $density_ok = $density >= 0.3 && $density <= 3.0;

                $checks[] = array(
                    'label' => 'Focus keyphrase density',
                    'passed' => $density_ok,
                    'message' => sprintf(
                        'Found %d occurrence%s (%.1f%% density). Aim for 0.5%%–2.5%% for a natural feel — too low means weak signal, too high risks keyword stuffing.',
                        $keyphrase_occurrences,
                        1 === $keyphrase_occurrences ? '' : 's',
                        $density_rounded
                    ),
                );
            }

            // Keyphrase in first paragraph.
            if ('' !== $normalized_keyphrase) {
                $first_paragraph = '';
                if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $raw_content, $fp_match)) {
                    $first_paragraph = $this->normalize_text_for_match(wp_strip_all_tags($fp_match[1]));
                } elseif (! empty($sentences)) {
                    // Fallback: first 2 sentences.
                    $first_paragraph = $this->normalize_text_for_match(implode(' ', array_slice($sentences, 0, 2)));
                }
                $kw_in_intro = '' !== $first_paragraph && false !== strpos($first_paragraph, $normalized_keyphrase);
                $checks[] = array(
                    'label' => 'Focus keyphrase in introduction',
                    'passed' => $kw_in_intro,
                    'message' => $kw_in_intro
                        ? 'The keyphrase appears in the opening paragraph — good for early relevance signal.'
                        : 'Try to include the focus keyphrase in the first paragraph so search engines see it early.',
                );
            }

            // Keyphrase in subheadings (H2-H6).
            if ('' !== $normalized_keyphrase && $subheading_count > 0) {
                $subheading_kw_count = 0;
                $subheading_texts = array();
                preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $raw_content, $sub_matches);
                foreach ($sub_matches[1] as $sub_text) {
                    if (false !== strpos($this->normalize_text_for_match(wp_strip_all_tags($sub_text)), $normalized_keyphrase)) {
                        $subheading_kw_count++;
                    }
                }
                $checks[] = array(
                    'label' => 'Focus keyphrase in subheadings',
                    'passed' => $subheading_kw_count >= 1,
                    'message' => $subheading_kw_count >= 1
                        ? sprintf('The keyphrase appears in %d of %d subheading%s.', $subheading_kw_count, $subheading_count, $subheading_count > 1 ? 's' : '')
                        : 'Consider adding the keyphrase to at least one H2 or H3 subheading to reinforce topical relevance.',
                );
            }

            // Keyphrase in image alt attributes.
            if ('' !== $normalized_keyphrase && count($image_matches[0]) > 0) {
                $img_alt_kw_count = 0;
                foreach ($image_matches[0] as $img_tag) {
                    if (preg_match('/\balt=("|\')(.*?)\1/i', $img_tag, $alt_match)) {
                        if (false !== strpos($this->normalize_text_for_match($alt_match[2]), $normalized_keyphrase)) {
                            $img_alt_kw_count++;
                        }
                    }
                }
                $checks[] = array(
                    'label' => 'Focus keyphrase in image alt tags',
                    'passed' => $img_alt_kw_count >= 1,
                    'message' => $img_alt_kw_count >= 1
                        ? sprintf('The keyphrase appears in %d image alt tag%s.', $img_alt_kw_count, $img_alt_kw_count > 1 ? 's' : '')
                        : 'Add the focus keyphrase to at least one image alt attribute — helps image search and reinforces page relevance.',
                );
            }
        }

        ob_start();
    ?>
        <strong>Basic SEO checks</strong>
        <p class="ai-seo-keeper-muted" style="margin:8px 0 12px;">Lightweight deterministic checks against the saved draft fields and the current page body.</p>
        <?php if ('' === $focus_keyphrase) : ?>
            <p class="ai-seo-keeper-muted" style="margin:0 0 12px;">Add a focus keyphrase to unlock phrase-matching checks similar to Yoast's page analysis.</p>
        <?php endif; ?>
        <div class="ai-seo-keeper-check-section">
            <p class="ai-seo-keeper-check-section-title"><strong>SEO and structure</strong></p>
            <ul class="ai-seo-keeper-check-list">
                <?php foreach ($checks as $check) : ?>
                    <li class="ai-seo-keeper-check-item">
                        <span class="ai-seo-keeper-check-pill <?php echo $check['passed'] ? 'is-pass' : 'is-warning'; ?>"><?php echo $check['passed'] ? 'Pass' : 'Needs work'; ?></span>
                        <strong><?php echo esc_html($check['label']); ?></strong><br />
                        <span class="ai-seo-keeper-muted"><?php echo esc_html($check['message']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="ai-seo-keeper-check-section">
            <p class="ai-seo-keeper-check-section-title"><strong>Readability and flow</strong></p>
            <p class="ai-seo-keeper-muted" style="margin:0 0 12px;">A first-pass reading-flow scan based on sentence length, paragraph size, and transition usage.</p>
            <div class="ai-seo-keeper-metrics-grid">
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label">Sentences</span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $sentence_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label">Avg sentence</span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) number_format_i18n($average_sentence_words, 1)); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label">Paragraphs</span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $paragraph_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label">Transitions</span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $transition_word_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label">Passive est.</span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $passive_voice_sentence_count); ?></span>
                </div>
                <div class="ai-seo-keeper-metric-card">
                    <span class="ai-seo-keeper-metric-label">Repeat starts</span>
                    <span class="ai-seo-keeper-metric-value"><?php echo esc_html((string) $repeated_sentence_start_count); ?></span>
                </div>
            </div>
            <ul class="ai-seo-keeper-check-list">
                <?php foreach ($readability_checks as $check) : ?>
                    <li class="ai-seo-keeper-check-item">
                        <span class="ai-seo-keeper-check-pill <?php echo $check['passed'] ? 'is-pass' : 'is-warning'; ?>"><?php echo $check['passed'] ? 'Pass' : 'Needs work'; ?></span>
                        <strong><?php echo esc_html($check['label']); ?></strong><br />
                        <span class="ai-seo-keeper-muted"><?php echo esc_html($check['message']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php

        return (string) ob_get_clean();
    }

    private function extract_content_blocks(string $raw_content): array
    {
        $content_with_breaks = preg_replace('/<\/(p|div|section|article|li|blockquote|h[1-6])>/i', "$0\n\n", $raw_content);
        $plain_content = trim(wp_strip_all_tags((string) $content_with_breaks));

        if ('' === $plain_content) {
            return array();
        }

        $blocks = preg_split('/\n\s*\n+/u', $plain_content);

        return array_values(
            array_filter(
                array_map('trim', is_array($blocks) ? $blocks : array()),
                static function ($block): bool {
                    return '' !== (string) $block;
                }
            )
        );
    }

    private function extract_sentences(string $content): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content));

        if ('' === $content) {
            return array();
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $content);

        return array_values(
            array_filter(
                array_map('trim', is_array($sentences) ? $sentences : array()),
                static function ($sentence): bool {
                    return '' !== (string) $sentence;
                }
            )
        );
    }

    private function count_words(string $text): int
    {
        $normalized_text = $this->normalize_text_for_match($text);

        if ('' === $normalized_text) {
            return 0;
        }

        return count(preg_split('/\s+/u', $normalized_text));
    }

    private function count_transition_words(string $normalized_content): int
    {
        if ('' === $normalized_content) {
            return 0;
        }

        $transition_word_count = 0;

        foreach (self::READABILITY_TRANSITION_WORDS as $transition_word) {
            $matches = preg_match_all('/\b' . preg_quote($transition_word, '/') . '\b/u', $normalized_content, $found_matches);

            if (false !== $matches) {
                $transition_word_count += $matches;
            }
        }

        return $transition_word_count;
    }

    private function count_passive_voice_sentences(array $sentences): int
    {
        $passive_voice_sentence_count = 0;

        foreach ($sentences as $sentence) {
            $normalized_sentence = $this->normalize_text_for_match($sentence);

            if ('' === $normalized_sentence) {
                continue;
            }

            if (preg_match('/\b(am|is|are|was|were|be|been|being)\b\s+(?:\w+\s+){0,2}\w+(ed|en)\b/u', $normalized_sentence)) {
                $passive_voice_sentence_count += 1;
            }
        }

        return $passive_voice_sentence_count;
    }

    private function count_repeated_sentence_starts(array $sentences): int
    {
        $repeated_sentence_start_count = 0;
        $previous_signature = '';

        foreach ($sentences as $sentence) {
            $normalized_sentence = $this->normalize_text_for_match($sentence);

            if ('' === $normalized_sentence) {
                continue;
            }

            $words = preg_split('/\s+/u', $normalized_sentence);
            $signature = implode(' ', array_slice(is_array($words) ? $words : array(), 0, 2));

            if ('' === $signature) {
                continue;
            }

            if ($signature === $previous_signature) {
                $repeated_sentence_start_count += 1;
            }

            $previous_signature = $signature;
        }

        return $repeated_sentence_start_count;
    }

    private function count_question_style_headings(string $raw_content): int
    {
        $heading_matches = array();
        $question_heading_count = 0;

        preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $raw_content, $heading_matches);

        foreach ($heading_matches[1] as $heading_html) {
            $heading_text = trim(wp_strip_all_tags((string) html_entity_decode($heading_html)));

            if ('' === $heading_text) {
                continue;
            }

            if ($this->is_question_style_heading($heading_text)) {
                $question_heading_count += 1;
            }
        }

        return $question_heading_count;
    }

    private function is_question_style_heading(string $heading_text): bool
    {
        $normalized_heading = $this->normalize_text_for_match($heading_text);

        if ('' === $normalized_heading) {
            return false;
        }

        if (false !== strpos($heading_text, '?')) {
            return true;
        }

        return 1 === preg_match('/^(how|what|why|when|where|who|can|should|is|are|do|does|will|which)\b/u', $normalized_heading);
    }

    private function is_generic_anchor_text(string $anchor_text): bool
    {
        $normalized_anchor = $this->normalize_text_for_match($anchor_text);

        if ('' === $normalized_anchor) {
            return true;
        }

        return in_array($normalized_anchor, self::GENERIC_ANCHOR_TEXTS, true);
    }

    private function normalize_text_for_match(string $text): string
    {
        $text = remove_accents(wp_strip_all_tags($text));
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    private function render_audit_post_links(array $ids): string
    {
        $links = array();

        foreach ($ids as $post_id) {
            $post_id = (int) $post_id;

            if ($post_id <= 0) {
                continue;
            }

            $links[] = '<a href="' . esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')) . '">#' . $post_id . '</a>';
        }

        return implode(', ', $links);
    }

    private function has_conflicting_seo_plugin(): bool
    {
        $active_plugins = (array) get_option('active_plugins', array());
        $network_plugins = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array();
        $all_active = array_unique(array_merge($active_plugins, $network_plugins));

        $conflicts = array(
            'wordpress-seo/wp-seo.php',
            'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'seo-by-rank-math/rank-math.php',
            'autodescription/autodescription.php',
            'seopress/seopress.php',
            'slim-seo/slim-seo.php',
        );

        foreach ($conflicts as $plugin_file) {
            if (in_array($plugin_file, $all_active, true)) {
                return true;
            }
        }

        return false;
    }

    private function render_history_markup(array $recent_suggestions, array $content_edits = array()): string
    {
        ob_start();
    ?>
        <?php if (empty($recent_suggestions) && empty($content_edits)) : ?>
            <p class="ai-seo-keeper-empty-state ai-seo-keeper-history-empty">No AI suggestions have been saved for this page yet.</p>
        <?php else : ?>
            <div class="ai-seo-keeper-history-list ai-seo-keeper-stack">
                <?php if (! empty($content_edits)) : ?>
                    <h4 style="margin:0 0 8px;font-size:13px;color:#50575e;border-bottom:1px solid #dcdcde;padding-bottom:6px;">Content Edit Plans</h4>
                    <?php foreach ($content_edits as $edit) : ?>
                        <div class="ai-seo-keeper-history-item" style="border-left:3px solid <?php echo 'published' === $edit['status'] ? '#00a32a' : '#ffb300'; ?>;padding-left:10px;" data-edit-id="<?php echo esc_attr((string) $edit['id']); ?>">
                            <p style="margin:0 0 6px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                <strong style="cursor:pointer;" class="ai-seo-keeper-toggle-edit-details"><?php echo esc_html($edit['change_count']); ?> content change(s) ▸</strong>
                                <span style="display:flex;gap:6px;align-items:center;">
                                    <?php if ('published' === $edit['status']) : ?>
                                        <span class="ai-seo-keeper-check-pill is-pass" style="background:#00a32a;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;white-space:nowrap;">Approved | Published</span>
                                    <?php else : ?>
                                        <span class="ai-seo-keeper-check-pill" style="background:#ffb300;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;white-space:nowrap;">Approved | Not Published</span>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small ai-seo-keeper-delete-edit-plan" data-edit-id="<?php echo esc_attr((string) $edit['id']); ?>" style="color:#8a2424;font-size:11px;padding:0 6px;line-height:1.8;" title="Remove this plan from history">✕</button>
                                </span>
                            </p>
                            <?php if ('' !== ($edit['summary'] ?? '')) : ?>
                                <p style="margin:0 0 6px;font-size:13px;"><em><?php echo esc_html($edit['summary']); ?></em></p>
                            <?php endif; ?>
                            <p class="ai-seo-keeper-history-meta" style="margin:0 0 6px;font-size:12px;color:#787c82;">
                                <?php echo esc_html($edit['created_at'] ?? ''); ?>
                                <?php if ('published' === $edit['status'] && '' !== ($edit['published_at'] ?? '')) : ?>
                                    <?php echo ' | Published: ' . esc_html($edit['published_at']); ?>
                                <?php endif; ?>
                            </p>
                            <?php if (! empty($edit['changes'])) : ?>
                                <div class="ai-seo-keeper-edit-details" style="display:none;margin-top:8px;">
                                    <?php foreach ($edit['changes'] as $idx => $ch) : ?>
                                        <div style="border:1px solid #dcdcde;border-radius:4px;padding:8px;margin-bottom:6px;background:#fff;font-size:12px;">
                                            <strong><?php echo esc_html($ch['section'] ?? ('Change ' . ($idx + 1))); ?></strong>
                                            <?php if (! empty($ch['tag_change'])) : ?>
                                                <span style="font-size:11px;background:#e5f5fa;color:#0a4b78;padding:1px 4px;border-radius:3px;margin-left:6px;"><?php echo esc_html($ch['tag_change']); ?></span>
                                            <?php endif; ?>
                                            <div style="display:flex;gap:8px;margin-top:4px;">
                                                <div style="flex:1;"><span style="font-size:10px;color:#8a2424;font-weight:600;">BEFORE</span>
                                                    <div style="background:#fef0f0;padding:4px 6px;border-radius:3px;word-break:break-word;"><?php echo esc_html($ch['old'] ?? ''); ?></div>
                                                </div>
                                                <div style="flex:1;"><span style="font-size:10px;color:#135e16;font-weight:600;">AFTER</span>
                                                    <div style="background:#eef8ee;padding:4px 6px;border-radius:3px;word-break:break-word;"><?php echo esc_html($ch['new'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                            <?php if (! empty($ch['reason'])) : ?>
                                                <p style="font-size:11px;color:#787c82;margin:4px 0 0;font-style:italic;"><?php echo esc_html($ch['reason']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (! empty($recent_suggestions)) : ?>
                    <?php if (! empty($content_edits)) : ?>
                        <h4 style="margin:12px 0 8px;font-size:13px;color:#50575e;border-bottom:1px solid #dcdcde;padding-bottom:6px;">Metadata Suggestions</h4>
                    <?php endif; ?>
                    <?php foreach ($recent_suggestions as $entry) : ?>
                        <div class="ai-seo-keeper-history-item">
                            <p style="margin:0 0 8px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                <strong><?php echo esc_html($entry['seo_title']); ?></strong>
                                <?php if (! empty($entry['is_approved'])) : ?>
                                    <span class="ai-seo-keeper-check-pill is-pass">Approved</span>
                                <?php endif; ?>
                            </p>
                            <p style="margin:0 0 8px;"><?php echo esc_html($entry['meta_description']); ?></p>
                            <?php if ('' !== $entry['notes']) : ?>
                                <p style="margin:0 0 8px;"><em><?php echo esc_html($entry['notes']); ?></em></p>
                            <?php endif; ?>
                            <p class="ai-seo-keeper-history-meta" style="margin:0;">
                                <?php echo esc_html(strtoupper($entry['provider'])); ?>
                                <?php if ('' !== $entry['model']) : ?>
                                    <?php echo ' | ' . esc_html($entry['model']); ?>
                                <?php endif; ?>
                                <?php if ('' !== $entry['created_at']) : ?>
                                    <?php echo ' | ' . esc_html($entry['created_at']); ?>
                                <?php endif; ?>
                            </p>
                            <p style="margin:12px 0 0;">
                                <?php if (! empty($entry['is_approved'])) : ?>
                                    <button type="button" class="button button-small" disabled>Approved for future output</button>
                                <?php else : ?>
                                    <button type="button" class="button button-small ai-seo-keeper-approve-suggestion" data-message-id="<?php echo esc_attr((string) $entry['id']); ?>">Approve for future output</button>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php

        return (string) ob_get_clean();
    }

    private function render_chat_history_markup(array $chat_messages): string
    {
        ob_start();
    ?>
        <?php if (empty($chat_messages)) : ?>
            <p class="ai-seo-keeper-empty-state">No AI assistant messages yet for this page.</p>
        <?php else : ?>
            <div class="ai-seo-keeper-stack">
                <?php foreach ($chat_messages as $entry) : ?>
                    <div class="ai-seo-keeper-chat-item <?php echo 'assistant' === $entry['role'] ? 'is-assistant' : ''; ?>">
                        <p style="margin:0 0 8px;"><strong><?php echo 'assistant' === $entry['role'] ? 'AI assistant' : 'You'; ?></strong></p>
                        <?php if ('assistant' === $entry['role']) : ?>
                            <p style="margin:0 0 8px;"><?php echo esc_html($entry['reply']); ?></p>
                            <?php if ('' !== $entry['suggested_title']) : ?>
                                <p style="margin:0 0 4px;"><strong>Suggested title:</strong> <?php echo esc_html($entry['suggested_title']); ?></p>
                                <button type="button" class="button button-small ai-seo-keeper-apply-suggestion" data-field="meta_title" data-value="<?php echo esc_attr($entry['suggested_title']); ?>" style="margin-bottom:8px;">Apply to title draft</button>
                            <?php endif; ?>
                            <?php if ('' !== $entry['suggested_description']) : ?>
                                <p style="margin:0 0 4px;"><strong>Suggested description:</strong> <?php echo esc_html($entry['suggested_description']); ?></p>
                                <button type="button" class="button button-small ai-seo-keeper-apply-suggestion" data-field="meta_description" data-value="<?php echo esc_attr($entry['suggested_description']); ?>" style="margin-bottom:8px;">Apply to description draft</button>
                            <?php endif; ?>
                            <?php if ('' !== $entry['notes']) : ?>
                                <p style="margin:0 0 8px;"><em><?php echo esc_html($entry['notes']); ?></em></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p style="margin:0 0 8px;"><?php echo esc_html($entry['message']); ?></p>
                        <?php endif; ?>
                        <p class="ai-seo-keeper-chat-meta" style="margin:0;">
                            <?php if ('assistant' === $entry['role']) : ?>
                                <?php echo esc_html(strtoupper($entry['provider'])); ?>
                                <?php if ('' !== $entry['model']) : ?>
                                    <?php echo ' | ' . esc_html($entry['model']); ?>
                                <?php endif; ?>
                                <?php if ('' !== $entry['created_at']) : ?>
                                    <?php echo ' | ' . esc_html($entry['created_at']); ?>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo '' !== $entry['created_at'] ? esc_html($entry['created_at']) : ''; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php

        return (string) ob_get_clean();
    }

    public function handle_sync_index(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_sync_index');

        $sync_count = $this->content_indexer->sync();

        $redirect_url = add_query_arg(
            array(
                'page'   => 'ai-seo-keeper',
                'synced' => $sync_count,
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_generate_site_audit(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_generate_site_audit');

        try {
            $report = $this->content_indexer->build_site_audit_report(10);
            $audit = $this->ai_generator->generate_site_audit($report);

            $this->history_store->log_generation(
                0,
                'site_audit',
                'AI SEO Keeper Site Audit',
                array(
                    'provider' => $audit['provider'],
                    'model' => $audit['model'],
                    'system_prompt' => $audit['system_prompt'],
                    'user_prompt' => $audit['user_prompt'],
                    'report_summary' => $report['summary'],
                ),
                array(
                    'audit_title' => $audit['audit_title'],
                    'executive_summary' => $audit['executive_summary'],
                    'priority_actions' => $audit['priority_actions'],
                    'quick_wins' => $audit['quick_wins'],
                    'notes' => $audit['notes'],
                    'provider' => $audit['provider'],
                    'model' => $audit['model'],
                )
            );

            $redirect_url = add_query_arg(
                array(
                    'page' => 'ai-seo-keeper-audit',
                    'audit_status' => 'success',
                    'audit_message' => rawurlencode('AI strategic audit generated successfully.'),
                ),
                admin_url('admin.php')
            );
        } catch (\Throwable $throwable) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'ai-seo-keeper-audit',
                    'audit_status' => 'error',
                    'audit_message' => rawurlencode($throwable->getMessage()),
                ),
                admin_url('admin.php')
            );
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_submit_indexnow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_submit_indexnow');

        $report = $this->audit_engine->get_report(10);
        $urls = array();

        foreach ($report['priority_rows'] as $row) {
            if (! empty($row['permalink'])) {
                $urls[] = (string) $row['permalink'];
            }
        }

        $result = $this->indexnow_service
            ? $this->indexnow_service->submit_urls($urls, 'manual_priority_queue')
            : array('status' => 'error', 'message' => 'IndexNow service is not available.');

        $redirect_url = add_query_arg(
            array(
                'page' => 'ai-seo-keeper-audit',
                'audit_status' => 'success' === ($result['status'] ?? '') ? 'success' : 'error',
                'audit_message' => rawurlencode((string) ($result['message'] ?? 'IndexNow request finished.')),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_import_yoast_metadata(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_import_yoast_metadata');

        $result = array();

        try {
            $result = $this->import_yoast_metadata();
        } catch (\Throwable $throwable) {
            $this->redirect_to_settings_page('error', $throwable->getMessage());
        }

        if (0 === $result['posts_detected']) {
            $this->redirect_to_settings_page('success', 'No Yoast metadata was found to import.');
        }

        $message = sprintf('Yoast import finished. %d item(s) were updated and %d field(s) were copied.', $result['posts_updated'], $result['fields_imported']);

        if ($result['frontend_enabled'] > 0) {
            $message .= ' ' . sprintf('Frontend output was enabled on %d item(s).', $result['frontend_enabled']);
        }

        if ($result['skipped_existing'] > 0) {
            $message .= ' ' . sprintf('%d existing AI SEO Keeper field(s) were left unchanged.', $result['skipped_existing']);
        }

        if ($result['unsupported_advanced_robots'] > 0) {
            $message .= ' ' . sprintf('%d item(s) had advanced Yoast robots directives that were not mapped.', $result['unsupported_advanced_robots']);
        }

        $this->redirect_to_settings_page('success', $message);
    }

    public function handle_bulk_frontend_rollout(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to do that.');
        }

        check_admin_referer('ai_seo_keeper_bulk_frontend_rollout');

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array) wp_unslash($_POST['post_ids'])) : array();
        $mode = isset($_POST['bulk_mode']) ? sanitize_key((string) wp_unslash($_POST['bulk_mode'])) : '';

        if (empty($post_ids) || ! in_array($mode, array('enable_frontend', 'disable_frontend'), true)) {
            $this->redirect_to_audit_page('error', 'Select at least one row and a valid bulk action.');
        }

        $result = $this->apply_bulk_frontend_gate($post_ids, 'enable_frontend' === $mode);
        $message = 'enable_frontend' === $mode
            ? sprintf('Enabled frontend output on %d page(s).', $result['updated'])
            : sprintf('Disabled frontend output on %d page(s).', $result['updated']);

        if ($result['unchanged'] > 0) {
            $message .= ' ' . sprintf('%d page(s) were already in that state.', $result['unchanged']);
        }

        if ($result['skipped_unapproved'] > 0) {
            $message .= ' ' . sprintf('%d page(s) were skipped because they do not have an approved suggestion or saved SEO data.', $result['skipped_unapproved']);
        }

        if ('enable_frontend' === $mode && ! empty($result['urls']) && $this->indexnow_service) {
            $indexnow_result = $this->indexnow_service->submit_urls($result['urls'], 'bulk_frontend_rollout');
            if (! empty($indexnow_result['message'])) {
                $message .= ' IndexNow: ' . (string) $indexnow_result['message'];
            }
        }

        $this->redirect_to_audit_page('success', $message);
    }

    private function apply_bulk_frontend_gate(array $post_ids, bool $enabled): array
    {
        $updated = 0;
        $unchanged = 0;
        $skipped_unapproved = 0;
        $urls = array();

        foreach (array_values(array_unique(array_filter($post_ids))) as $post_id) {
            $post = get_post((int) $post_id);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $currently_enabled = '1' === (string) get_post_meta((int) $post_id, self::FRONTEND_ENABLE_META_KEY, true);

            if ($enabled) {
                $approved_id = $this->history_store->get_approved_suggestion_id((int) $post_id, 'post');
                $has_saved_frontend_data = $this->has_saved_frontend_data_for_post((int) $post_id);

                if ($approved_id <= 0 && ! $has_saved_frontend_data) {
                    $skipped_unapproved++;
                    continue;
                }

                if ($currently_enabled) {
                    $unchanged++;
                    continue;
                }

                update_post_meta((int) $post_id, self::FRONTEND_ENABLE_META_KEY, '1');
                $updated++;
                $urls[] = (string) get_permalink((int) $post_id);
                continue;
            }

            if (! $currently_enabled) {
                $unchanged++;
                continue;
            }

            delete_post_meta((int) $post_id, self::FRONTEND_ENABLE_META_KEY);
            $updated++;
        }

        return array(
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped_unapproved' => $skipped_unapproved,
            'urls' => $urls,
        );
    }

    private function import_yoast_metadata(): array
    {
        $posts_detected = 0;
        $posts_updated = 0;
        $fields_imported = 0;
        $skipped_existing = 0;
        $frontend_enabled = 0;
        $unsupported_advanced_robots = 0;
        $frontend_field_keys = array(
            'seo_title',
            'seo_description',
            'social_title',
            'social_description',
            'social_image',
            'canonical_url',
            'robots_directives',
        );
        $field_map = array(
            'focus_keyphrase' => self::FOCUS_KEYPHRASE_META_KEY,
            'seo_title' => self::META_TITLE_KEY,
            'seo_description' => self::META_DESCRIPTION_KEY,
            'social_title' => self::SOCIAL_TITLE_META_KEY,
            'social_description' => self::SOCIAL_DESCRIPTION_META_KEY,
            'social_image' => self::SOCIAL_IMAGE_META_KEY,
            'canonical_url' => self::CANONICAL_URL_META_KEY,
            'robots_directives' => self::ROBOTS_DIRECTIVES_META_KEY,
        );

        foreach ($this->get_yoast_import_candidate_ids() as $post_id) {
            $post = get_post($post_id);

            if (! $post instanceof \WP_Post || ! $this->is_supported_post_type($post->post_type)) {
                continue;
            }

            $posts_detected++;

            $yoast_title = sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_title', true));
            $yoast_description = sanitize_textarea_field((string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true));
            $yoast_social_title = sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true));
            $yoast_social_description = sanitize_textarea_field((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true));
            $yoast_social_image = esc_url_raw((string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true));
            $yoast_twitter_title = sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_twitter-title', true));
            $yoast_twitter_description = sanitize_textarea_field((string) get_post_meta($post_id, '_yoast_wpseo_twitter-description', true));
            $yoast_twitter_image = esc_url_raw((string) get_post_meta($post_id, '_yoast_wpseo_twitter-image', true));
            $limited_fields = $this->apply_editor_text_limits(
                array(
                    'seo_title' => $yoast_title,
                    'meta_description' => $yoast_description,
                    'social_title' => '' !== $yoast_social_title ? $yoast_social_title : $yoast_twitter_title,
                    'social_description' => '' !== $yoast_social_description ? $yoast_social_description : $yoast_twitter_description,
                )
            );
            $import_payload = array(
                'focus_keyphrase' => sanitize_text_field((string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true)),
                'seo_title' => $limited_fields['seo_title'],
                'seo_description' => $limited_fields['meta_description'],
                'social_title' => $limited_fields['social_title'],
                'social_description' => $limited_fields['social_description'],
                'social_image' => '' !== $yoast_social_image ? $yoast_social_image : $yoast_twitter_image,
                'canonical_url' => esc_url_raw((string) get_post_meta($post_id, '_yoast_wpseo_canonical', true)),
                'robots_directives' => $this->map_yoast_robots_directives(
                    (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true),
                    (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true)
                ),
            );
            $post_updated = false;
            $imported_frontend_field = false;

            if ('' !== trim((string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-adv', true))) {
                $unsupported_advanced_robots++;
            }

            foreach ($field_map as $field_key => $meta_key) {
                $value = isset($import_payload[$field_key]) ? (string) $import_payload[$field_key] : '';

                if ('' === trim($value)) {
                    continue;
                }

                $existing_value = trim((string) get_post_meta($post_id, $meta_key, true));

                if ('' !== $existing_value) {
                    $skipped_existing++;
                    continue;
                }

                update_post_meta($post_id, $meta_key, $value);
                $fields_imported++;
                $post_updated = true;

                if (in_array($field_key, $frontend_field_keys, true)) {
                    $imported_frontend_field = true;
                }
            }

            if ($imported_frontend_field && '1' !== (string) get_post_meta($post_id, self::FRONTEND_ENABLE_META_KEY, true)) {
                update_post_meta($post_id, self::FRONTEND_ENABLE_META_KEY, '1');
                $frontend_enabled++;
            }

            if ($post_updated) {
                $posts_updated++;
            }
        }

        return array(
            'posts_detected' => $posts_detected,
            'posts_updated' => $posts_updated,
            'fields_imported' => $fields_imported,
            'skipped_existing' => $skipped_existing,
            'frontend_enabled' => $frontend_enabled,
            'unsupported_advanced_robots' => $unsupported_advanced_robots,
        );
    }

    private function get_yoast_import_candidate_ids(): array
    {
        global $wpdb;

        $supported_post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );

        unset($supported_post_types['attachment']);

        if (empty($supported_post_types)) {
            return array();
        }

        $yoast_meta_keys = array(
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-title',
            '_yoast_wpseo_twitter-description',
            '_yoast_wpseo_twitter-image',
            '_yoast_wpseo_canonical',
            '_yoast_wpseo_meta-robots-noindex',
            '_yoast_wpseo_meta-robots-nofollow',
            '_yoast_wpseo_meta-robots-adv',
        );

        $meta_placeholders = implode(', ', array_fill(0, count($yoast_meta_keys), '%s'));
        $post_type_placeholders = implode(', ', array_fill(0, count($supported_post_types), '%s'));
        $query_args = array_merge($yoast_meta_keys, array_values($supported_post_types), array('auto-draft', 'trash', 'inherit'));
        $sql = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} posts ON posts.ID = pm.post_id
            WHERE pm.meta_key IN ({$meta_placeholders})
                AND posts.post_type IN ({$post_type_placeholders})
                AND posts.post_status NOT IN (%s, %s, %s)
            ORDER BY pm.post_id ASC",
            $query_args
        );
        $post_ids = $wpdb->get_col($sql);

        return array_map('intval', is_array($post_ids) ? $post_ids : array());
    }

    private function map_yoast_robots_directives(string $noindex_value, string $nofollow_value): string
    {
        $is_noindex = '1' === trim($noindex_value);
        $is_nofollow = '1' === trim($nofollow_value);

        if ($is_noindex && $is_nofollow) {
            return 'noindex,nofollow';
        }

        if ($is_noindex) {
            return 'noindex,follow';
        }

        if ($is_nofollow) {
            return 'index,nofollow';
        }

        return '';
    }

    private function redirect_to_audit_page(string $status, string $message): void
    {
        $redirect_url = add_query_arg(
            array(
                'page' => 'ai-seo-keeper-audit',
                'audit_status' => $status,
                'audit_message' => rawurlencode($message),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function redirect_to_settings_page(string $status, string $message): void
    {
        $redirect_url = add_query_arg(
            array(
                'page' => 'ai-seo-keeper-settings',
                'settings_status' => $status,
                'settings_message' => rawurlencode($message),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }
}
