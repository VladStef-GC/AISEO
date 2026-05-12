<?php

namespace AI_SEO_Keeper;

use AI_SEO_Keeper\Admin\SEO_Analysis;
use AI_SEO_Keeper\Admin\Admin_Taxonomy;
use AI_SEO_Keeper\Admin\Admin_Import_Export;
use AI_SEO_Keeper\Admin\Admin_Rollout;
use AI_SEO_Keeper\Admin\Admin_Ajax;

class Admin
{
    private const META_BOX_ID = 'ai_seo_keeper_meta_box';

    public const FRONTEND_ENABLE_META_KEY = '_ai_seo_keeper_frontend_enabled';

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

    private const AJAX_SITE_CHAT_ACTION = 'ai_seo_keeper_site_chat';
    private const AJAX_SITE_CHAT_CLEAR_ACTION = 'ai_seo_keeper_site_chat_clear';

    public const CHAT_OBJECT_TYPE = 'post_chat';

    public const META_TITLE_KEY = '_ai_seo_keeper_meta_title';

    public const META_DESCRIPTION_KEY = '_ai_seo_keeper_meta_description';

    public const TITLE_BRANDING_OFF_META_KEY = '_ai_seo_keeper_title_branding_off';

    public const FOCUS_KEYPHRASE_META_KEY = '_ai_seo_keeper_focus_keyphrase';

    public const SOCIAL_TITLE_META_KEY = '_ai_seo_keeper_social_title';

    public const SOCIAL_DESCRIPTION_META_KEY = '_ai_seo_keeper_social_description';

    public const SOCIAL_IMAGE_META_KEY = '_ai_seo_keeper_social_image';

    public const CANONICAL_URL_META_KEY = '_ai_seo_keeper_canonical_url';

    public const ROBOTS_DIRECTIVES_META_KEY = '_ai_seo_keeper_robots_directives';

    public const SCHEMA_TYPE_META_KEY = '_ai_seo_keeper_schema_type';

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

    /** @var AI_Generator */
    private $ai_generator;

    /** @var History_Store */
    private $history_store;

    /** @var Audit_Engine */
    private $audit_engine;

    /** @var IndexNow|null */
    private $indexnow_service;

    // --- Delegates (extracted sub-classes) ---

    private Admin_Taxonomy $taxonomy;
    private Admin_Import_Export $import_export;
    private Admin_Rollout $rollout;
    private Admin_Ajax $ajax;

    /** @var Site_Chat */
    private $site_chat;

    /**
     * @param AI_Generator  $ai_generator
     * @param History_Store  $history_store
     * @param IndexNow|null  $indexnow_service
     */
    public function __construct(Settings $settings, Content_Indexer $content_indexer, $ai_generator, $history_store, $indexnow_service = null)
    {
        $this->settings = $settings;
        $this->content_indexer = $content_indexer;
        $this->ai_generator = $ai_generator;
        $this->history_store = $history_store;
        $audit_engine_class = __NAMESPACE__ . '\\Audit_Engine';
        $this->audit_engine = new $audit_engine_class($content_indexer);
        $this->indexnow_service = $indexnow_service;

        // Instantiate delegates.
        $this->taxonomy      = new Admin_Taxonomy();
        $this->import_export = new Admin_Import_Export($settings, $this);
        $this->rollout       = new Admin_Rollout($content_indexer, $ai_generator, $history_store, $this->audit_engine, $indexnow_service, $this);
        $this->ajax          = new Admin_Ajax($this, $settings, $ai_generator, $content_indexer, $history_store);
        $this->site_chat     = new Site_Chat($settings, $content_indexer, $this->audit_engine, $ai_generator, $history_store);

        // --- Menu, assets, metabox ---
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_editor_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_page_assets'));
        add_action('add_meta_boxes', array($this, 'register_editor_metabox'), 10, 2);
        add_action('save_post', array($this, 'save_editor_meta'));

        // --- Admin-post handlers → delegates ---
        add_action('admin_post_ai_seo_keeper_sync_index', array($this->rollout, 'handle_sync_index'));
        add_action('admin_post_' . self::GENERATE_SITE_AUDIT_ACTION, array($this->rollout, 'handle_generate_site_audit'));
        add_action('admin_post_' . self::SUBMIT_INDEXNOW_ACTION, array($this->rollout, 'handle_submit_indexnow'));
        add_action('admin_post_' . self::BULK_FRONTEND_ACTION, array($this->rollout, 'handle_bulk_frontend_rollout'));
        add_action('admin_post_' . self::YOAST_IMPORT_ACTION, array($this->import_export, 'handle_import_yoast'));
        add_action('admin_post_ai_seo_keeper_export', array($this->import_export, 'handle_export'));
        add_action('admin_post_ai_seo_keeper_import', array($this->import_export, 'handle_import'));

        // --- AJAX handlers → delegates ---
        add_action('wp_ajax_' . self::AJAX_SAVE_ACTION, array($this->ajax, 'handle_save_editor_meta'));
        add_action('wp_ajax_' . self::AJAX_GENERATE_ACTION, array($this->ajax, 'handle_generate_editor_meta'));
        add_action('wp_ajax_' . self::AJAX_APPROVE_ACTION, array($this->ajax, 'handle_approve_suggestion'));
        add_action('wp_ajax_' . self::AJAX_CHAT_ACTION, array($this->ajax, 'handle_chat_for_post'));
        add_action('wp_ajax_' . self::AJAX_BULK_GENERATE_ACTION, array($this->ajax, 'handle_bulk_generate'));
        add_action('wp_ajax_' . self::AJAX_PAGE_AUDIT_ACTION, array($this->ajax, 'handle_page_audit'));
        add_action('wp_ajax_' . self::AJAX_SETUP_INDEX_ACTION, array($this->ajax, 'handle_setup_index'));
        add_action('wp_ajax_' . self::AJAX_TOGGLE_AUDIT_SKIP_ACTION, array($this->ajax, 'handle_toggle_audit_skip'));
        add_action('wp_ajax_' . self::AJAX_SAVE_SKIP_PATTERNS_ACTION, array($this->ajax, 'handle_save_skip_patterns'));
        add_action('wp_ajax_' . self::AJAX_CONTENT_EDIT_ACTION, array($this->ajax, 'handle_content_edit'));
        add_action('wp_ajax_' . self::AJAX_APPLY_CHANGES_ACTION, array($this->ajax, 'handle_apply_changes'));
        add_action('wp_ajax_' . self::AJAX_APPLY_SUGGESTION_ACTION, array($this->ajax, 'handle_apply_suggestion'));
        add_action('wp_ajax_' . self::AJAX_RESTORE_BACKUP_ACTION, array($this->ajax, 'handle_restore_backup'));
        add_action('wp_ajax_' . self::AJAX_CLEAR_CHAT_ACTION, array($this->ajax, 'handle_clear_chat'));
        add_action('wp_ajax_' . self::AJAX_TEST_MODEL_ACTION, array($this->ajax, 'handle_test_model'));
        add_action('wp_ajax_ai_seo_keeper_delete_edit_plan', array($this->ajax, 'handle_delete_edit_plan'));
        add_action('wp_ajax_ai_seo_keeper_bulk_save_seo', array($this->ajax, 'handle_bulk_save_seo'));
        add_action('wp_ajax_ai_seo_keeper_save_image_alt', array($this->ajax, 'handle_save_image_alt'));

        // --- Site Chat AJAX handlers ---
        add_action('wp_ajax_' . self::AJAX_SITE_CHAT_ACTION, array($this->site_chat, 'handle_chat'));
        add_action('wp_ajax_' . self::AJAX_SITE_CHAT_CLEAR_ACTION, array($this->site_chat, 'handle_clear_chat'));

        // --- Taxonomy SEO fields → delegate ---
        add_action('admin_init', array($this->taxonomy, 'register'));

        // --- REST API: expose post meta to the block editor ---
        add_action('rest_api_init', array($this, 'register_gutenberg_meta'));

        // Show pending AI content changes in page builder editors (BeTheme, Elementor, etc.)
        // by intercepting their meta reads so the editor loads the modified content.
        add_filter('get_post_metadata', array($this, 'filter_admin_pending_builder_meta'), 1, 4);
    }

    /**
     * Register core SEO post meta keys with the REST API so the Gutenberg
     * block editor can read and track them via wp.data 'core/editor' store.
     */
    public function register_gutenberg_meta(): void
    {
        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);

        $meta_keys = array(
            self::META_TITLE_KEY => array(
                'type'         => 'string',
                'description'  => 'AI SEO Keeper SEO title',
                'single'       => true,
                'default'      => '',
            ),
            self::META_DESCRIPTION_KEY => array(
                'type'         => 'string',
                'description'  => 'AI SEO Keeper meta description',
                'single'       => true,
                'default'      => '',
            ),
            self::FOCUS_KEYPHRASE_META_KEY => array(
                'type'         => 'string',
                'description'  => 'AI SEO Keeper focus keyphrase',
                'single'       => true,
                'default'      => '',
            ),
            self::ROBOTS_DIRECTIVES_META_KEY => array(
                'type'         => 'string',
                'description'  => 'AI SEO Keeper robots directives',
                'single'       => true,
                'default'      => '',
            ),
        );

        foreach ($post_types as $post_type) {
            foreach ($meta_keys as $key => $args) {
                register_post_meta(
                    $post_type,
                    $key,
                    array_merge(
                        $args,
                        array(
                            'show_in_rest'      => true,
                            'auth_callback'     => static function () {
                                return current_user_can('edit_posts');
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        )
                    )
                );
            }
        }
    }

    public function is_supported_post_type(string $post_type): bool
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

        add_submenu_page(
            'ai-seo-keeper',
            'AI SEO Strategist',
            'AI Strategist',
            'manage_options',
            'ai-seo-keeper-site-chat',
            array($this, 'render_site_chat_page')
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

        // Gutenberg block editor sidebar — only when the block editor is active.
        if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type($screen->post_type)) {
            $this->enqueue_gutenberg_sidebar($screen->post_type);
        }
    }

    /**
     * Enqueue the Gutenberg sidebar plugin panel script and styles.
     *
     * Uses WordPress bundled wp-* packages — no build step required.
     */
    private function enqueue_gutenberg_sidebar(string $post_type): void
    {
        $url = AI_SEO_KEEPER_URL . 'assets/';
        $ver = AI_SEO_KEEPER_VERSION;

        wp_enqueue_style(
            'ai-seo-keeper-gutenberg-sidebar',
            $url . 'css/gutenberg-sidebar.css',
            array(),
            $ver
        );

        wp_enqueue_script(
            'ai-seo-keeper-gutenberg-sidebar',
            $url . 'js/gutenberg-sidebar.js',
            array(
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-i18n',
            ),
            $ver,
            true
        );

        $suffix     = $this->settings->get_branding_suffix();
        $suffix_len = function_exists('mb_strlen') ? mb_strlen($suffix) : strlen($suffix);

        wp_localize_script(
            'ai-seo-keeper-gutenberg-sidebar',
            'aiSeoKeeperGutenberg',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('ai_seo_keeper_nonce'),
                'actions' => array(
                    'save'     => self::AJAX_SAVE_ACTION,
                    'generate' => self::AJAX_GENERATE_ACTION,
                    'chat'     => self::AJAX_CHAT_ACTION,
                ),
                'metaKeys' => array(
                    'title'       => self::META_TITLE_KEY,
                    'description' => self::META_DESCRIPTION_KEY,
                    'keyphrase'   => self::FOCUS_KEYPHRASE_META_KEY,
                    'robots'      => self::ROBOTS_DIRECTIVES_META_KEY,
                ),
                'limits' => array(
                    'titleMin'       => self::TITLE_MIN_LENGTH,
                    'titleMax'       => self::TITLE_MAX_LENGTH,
                    'descriptionMin' => self::DESCRIPTION_MIN_LENGTH,
                    'descriptionMax' => self::DESCRIPTION_MAX_LENGTH,
                ),
                'brandingSuffix'       => $suffix,
                'brandingSuffixLength' => $suffix_len,
                'i18n' => array(
                    'sidebarTitle'   => __('AI SEO Keeper', 'ai-seo-keeper'),
                    'seoScore'       => __('SEO Score', 'ai-seo-keeper'),
                    'snippetPreview' => __('Snippet Preview', 'ai-seo-keeper'),
                    'seoFields'      => __('SEO Fields', 'ai-seo-keeper'),
                    'seoChecks'      => __('SEO Checks', 'ai-seo-keeper'),
                    'aiAssistant'    => __('AI Assistant', 'ai-seo-keeper'),
                    'seoTitle'       => __('SEO Title', 'ai-seo-keeper'),
                    'metaDescription' => __('Meta Description', 'ai-seo-keeper'),
                    'focusKeyphrase' => __('Focus Keyphrase', 'ai-seo-keeper'),
                    'noindex'        => __('Set to noindex', 'ai-seo-keeper'),
                    'saveDraft'      => __('Save Draft', 'ai-seo-keeper'),
                    'generateAi'     => __('Generate with AI', 'ai-seo-keeper'),
                    'askAi'          => __('Ask AI', 'ai-seo-keeper'),
                    'askQuestion'    => __('Ask the AI assistant', 'ai-seo-keeper'),
                    'chatPlaceholder' => __('e.g. How can I improve the title for this page?', 'ai-seo-keeper'),
                    'saved'          => __('SEO draft saved.', 'ai-seo-keeper'),
                    'saveError'      => __('Could not save the SEO draft.', 'ai-seo-keeper'),
                    'generated'      => __('AI suggestion loaded. Review and save to keep it.', 'ai-seo-keeper'),
                    'generateError'  => __('Could not generate SEO suggestions.', 'ai-seo-keeper'),
                    'chatError'      => __('Could not get an AI assistant reply.', 'ai-seo-keeper'),
                    'noTitle'        => __('No SEO title set', 'ai-seo-keeper'),
                    'noDescription'  => __('No meta description set', 'ai-seo-keeper'),
                    'saveToContinue' => __('Save the post once to see SEO checks.', 'ai-seo-keeper'),
                    'brandingNote'   => __('Branding suffix will be appended:', 'ai-seo-keeper'),
                    'checks' => array(
                        'titleLength' => __('SEO title is between 30–60 characters', 'ai-seo-keeper'),
                        'descLength'  => __('Meta description is between 70–155 characters', 'ai-seo-keeper'),
                        'titleFilled' => __('SEO title is set', 'ai-seo-keeper'),
                        'descFilled'  => __('Meta description is set', 'ai-seo-keeper'),
                        'kpInTitle'   => __('Focus keyphrase appears in the SEO title', 'ai-seo-keeper'),
                        'kpInDesc'    => __('Focus keyphrase appears in the meta description', 'ai-seo-keeper'),
                    ),
                ),
            )
        );
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
            'ai-seo-keeper-site-chat'     => 'site-chat',
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
            post_id: postId,
            current_focus_keyphrase: $('#ai-seo-keeper-focus-keyphrase').val(),
            current_seo_title: $('#ai-seo-keeper-meta-title').val(),
            current_meta_description: $('#ai-seo-keeper-meta-description').val(),
            current_social_title: $('#ai-seo-keeper-social-title').val(),
            current_social_description: $('#ai-seo-keeper-social-description').val(),
            current_schema_type: $('#ai-seo-keeper-schema-type').val(),
            current_canonical_url: $('#ai-seo-keeper-canonical-url').val(),
            current_robots_directives: $('#ai-seo-keeper-robots-directives').val(),
            current_cornerstone: $('#ai-seo-keeper-cornerstone').is(':checked') ? '1' : '0'
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    $('#ai-seo-keeper-meta-title').val(response.data.seoTitle || '');
                    $('#ai-seo-keeper-meta-description').val(response.data.metaDescription || '');

                    if (response.data.focusKeyphrase) {
                        var $kw = $('#ai-seo-keeper-focus-keyphrase');
                        if ('' === $.trim($kw.val())) {
                            $kw.val(response.data.focusKeyphrase);
                        }
                    }

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
        $tab.addClass('is-active').css({'border-bottom-color': '#643d87', 'color': '#1d2327'});

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

    public function render_site_chat_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $site_chat      = $this->site_chat;
        $dashboard      = $site_chat->get_dashboard_data();
        $chat_messages  = $site_chat->get_recent_messages(20);

        // Calculate model capacity data for the UI.
        $options       = $this->settings->get();
        $active_model  = trim((string) ($options['model'] ?? ''));
        $page_count    = $this->site_chat->get_published_page_count_via_indexer();
        $max_pages     = Settings::get_max_pages_for_model($active_model);
        $context_window = Settings::get_context_window($active_model);
        $needs_focus   = $page_count > $max_pages;

        // Localize the JS with AJAX data.
        wp_localize_script('ai-seo-page-site-chat', 'aiSeoSiteChat', array(
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('ai_seo_keeper_site_chat'),
            'chatAction'    => self::AJAX_SITE_CHAT_ACTION,
            'clearAction'   => self::AJAX_SITE_CHAT_CLEAR_ACTION,
            'activeModel'   => $active_model,
            'contextWindow' => $context_window,
            'pageCount'     => $page_count,
            'maxPages'      => $max_pages,
            'needsFocus'    => $needs_focus,
        ));

        require __DIR__ . '/admin/view-site-chat.php';
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

    /**
     * @param \WP_Post $post
     */
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

    /**
     * @param \WP_Post $post
     */
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
                                <strong>Live snippet health <span class="ai-seo-keeper-help-tip" data-tip="Checks ONLY whether your title and description have correct length and contain the focus keyphrase. This is NOT an overall SEO score — it tracks metadata formatting only. Use the AI audit below for a full SEO analysis."></span></strong>
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
                            <div style="display:flex;align-items:center;gap:12px;margin:10px 0;padding:10px 14px;background:#f6f7f7;border-radius:8px;">
                                <span style="font-size:14px;">AI SEO Score: <strong style="color:<?php echo $page_audit_score >= 70 ? '#00a32a' : ($page_audit_score >= 40 ? '#dba617' : '#d63638'); ?>; font-size:16px;"><?php echo esc_html((string) $page_audit_score); ?>/100</strong> <span class="ai-seo-keeper-help-tip" data-tip="Full AI-powered SEO audit of this page. Unlike the metadata fit score above, this analyzes content quality, heading structure, word count, image alt tags, readability, and more."></span></span>
                                <button type="button" class="button button-primary ai-seo-keeper-run-page-audit" style="font-size:13px;min-height:34px;padding:0 18px;border-radius:12px;" <?php disabled(! $has_api_key); ?>>↻ Re-run AI Audit</button>
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
                            <span class="ai-seo-keeper-field-label">SEO title <span class="ai-seo-keeper-help-tip" data-tip="This is the page-specific part of the title. The separator and site brand from Settings are appended automatically unless you check 'Use as full title' below."></span></span>
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
                            <span class="ai-seo-keeper-field-label">Meta description <span class="ai-seo-keeper-help-tip" data-tip="This description appears below the title in search results. Approve it via the readiness section to make it live. AI can generate or edit it for you."></span></span>
                            <textarea id="ai-seo-keeper-meta-description" rows="5" name="ai_seo_keeper_meta_description" maxlength="<?php echo esc_attr((string) self::DESCRIPTION_MAX_LENGTH); ?>"><?php echo esc_textarea($seo_description); ?></textarea>
                            <?php echo $this->render_field_counter('ai-seo-keeper-meta-description', $seo_description, self::DESCRIPTION_MAX_LENGTH); ?>
                            <span class="ai-seo-keeper-field-help">Shown under the title in search results. Approve it below to go live. Max <?php echo esc_html((string) self::DESCRIPTION_MAX_LENGTH); ?> chars.</span>
                        </label>
                    </div>

                    <div class="ai-seo-keeper-accordion-group">
                        <?php if ($chat_is_enabled) : ?>
                            <?php
                            $ai_assistant_content =
                                '<div class="ai-seo-keeper-chat-intro">Your AI SEO copilot — ask questions, get metadata suggestions, or request page content edits. Everything happens in one conversation. <span class="ai-seo-keeper-help-tip" data-tip="AI sees your full page content, SEO title, meta description, focus keyphrase, snippet scores, audit results, related pages, and the full conversation history."></span></div>' .

                                '<div class="ai-seo-keeper-assistant-tabs" style="display:flex;gap:0;border-bottom:2px solid #dcdcde;margin-bottom:12px;">' .
                                '<button type="button" class="ai-seo-keeper-assistant-tab is-active" data-target="chat" style="padding:8px 16px;font-size:14px;font-weight:600;background:none;border:none;border-bottom:2px solid #643d87;margin-bottom:-2px;cursor:pointer;color:#1d2327;">💬 Chat</button>' .
                                '<button type="button" class="ai-seo-keeper-assistant-tab" data-target="history" style="padding:8px 16px;font-size:14px;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;color:#787c82;">📋 History</button>' .
                                '</div>' .

                                '<div class="ai-seo-keeper-assistant-panel" data-panel="chat">' .
                                '<textarea class="widefat ai-seo-keeper-chat-input" rows="3" placeholder="Ask about SEO, request content edits, or follow up on previous advice — AI handles it all in one conversation…"></textarea>' .
                                '<p class="ai-seo-keeper-chat-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' .
                                '<button type="button" class="button button-primary ai-seo-keeper-send-chat" ' . disabled(! $has_api_key, true, false) . '>Send</button>' .
                                '<span class="ai-seo-keeper-help-tip" data-tip="AI reads your full page content, SEO data, audit results, and conversation history. Ask questions, request metadata changes, or ask for page content edits \u2014 AI decides what to do automatically. When edits are needed, you get BEFORE/AFTER diffs to review before anything is saved."></span>' .
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
                                'AI Assistant <span class="ai-seo-keeper-help-tip" data-tip="Unified AI workspace: chat for SEO advice, edit page content, and view suggestion history — all in one place."></span>',
                                $ai_assistant_content,
                                false,
                                true
                            );
                            ?>
                        <?php endif; ?>

                        <?php
                        echo $this->render_accordion_section(
                            $readiness_accordion_id,
                            'Frontend readiness <span class="ai-seo-keeper-help-tip" data-tip="Shows whether this page\'s SEO metadata is approved and ready to be served on the live site. All checks must pass for AI SEO Keeper to output your title and description."></span>',
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
    border-color: #c3c4c7;
    color: #1d2327;
    outline: none;
}

.ai-seo-keeper-tab-button.is-active {
    background: #ffffff;
    border-color: #c3c4c7;
    border-bottom: 3px solid #1db954;
    color: #1d2327;
    box-shadow: none;
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
    background: linear-gradient(90deg, #643d87 0%, #a66dd4 100%);
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
    border-color: #643d87;
    box-shadow: 0 0 0 3px rgba(100, 61, 135, 0.12);
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
    gap: 4px;
    align-items: start;
    color: #1f2937;
}

.ai-seo-keeper-checkbox-row input[type="checkbox"] {
    margin: 3px 0 0;
    vertical-align: top;
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

.ai-seo-keeper-accordion-item.is-promoted {
    border: 2px solid transparent;
    background-image: linear-gradient(#fff, #fff), linear-gradient(135deg, #643d87 0%, #1db954 100%);
    background-origin: border-box;
    background-clip: padding-box, border-box;
    box-shadow: 0 4px 18px rgba(100, 61, 135, 0.13);
}

.ai-seo-keeper-accordion-item.is-promoted .ai-seo-keeper-accordion-toggle {
    background: linear-gradient(135deg, #643d87 0%, #1db954 100%);
    color: #ffffff;
}

.ai-seo-keeper-accordion-item.is-promoted .ai-seo-keeper-accordion-toggle:hover,
.ai-seo-keeper-accordion-item.is-promoted .ai-seo-keeper-accordion-toggle:focus-visible {
    background: linear-gradient(135deg, #7a4fa0 0%, #22d362 100%);
}

.ai-seo-keeper-accordion-item.is-promoted .ai-seo-keeper-accordion-symbol {
    color: #ffffff;
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
    color: #643d87;
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
    font-weight: 400;
    font-size: 14px;
    position: static;
    color: #50575e;
}

.ai-seo-keeper-help-tip::before {
    content: "\1D48A";
    margin-left: 5px;
    font-size: 14px;
    font-weight: 700;
    vertical-align: baseline;
}

.ai-seo-keeper-help-tip.is-light,
.ai-seo-keeper-accordion-item.is-promoted .ai-seo-keeper-accordion-toggle .ai-seo-keeper-help-tip {
    color: #ffffff;
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

/* GreenCoders button branding */
.ai-seo-keeper-editor-panel .button.button-primary {
    display: inline-block;
    text-decoration: none;
    font-size: 13px;
    line-height: 2.15384615;
    min-height: 40px;
    padding: 0 40px;
    border-width: 1px;
    border-style: solid;
    border-radius: 15px;
    white-space: nowrap;
    box-sizing: border-box;
    -webkit-appearance: none;
    background: linear-gradient(135deg, #643d87 0%, #1db954 100%);
    border-color: #643d87;
    color: #ffffff;
    text-shadow: none;
    box-shadow: 0 2px 6px rgba(100, 61, 135, 0.25);
    transition: opacity 0.15s ease, box-shadow 0.15s ease;
    cursor: pointer;
}

.ai-seo-keeper-editor-panel .button.button-primary:hover,
.ai-seo-keeper-editor-panel .button.button-primary:focus {
    background: linear-gradient(135deg, #7a4fa0 0%, #22d362 100%);
    border-color: #7a4fa0;
    box-shadow: 0 4px 12px rgba(100, 61, 135, 0.35);
    color: #ffffff;
}

.ai-seo-keeper-editor-panel .button.button-primary:disabled {
    background: linear-gradient(135deg, #9b7db5 0%, #7dd4a0 100%);
    border-color: #9b7db5;
    opacity: 0.6;
    box-shadow: none;
}

.ai-seo-keeper-editor-panel .button:not(.button-primary) {
    border-color: #643d87;
    color: #643d87;
}

.ai-seo-keeper-editor-panel .button:not(.button-primary):hover,
.ai-seo-keeper-editor-panel .button:not(.button-primary):focus {
    background: rgba(100, 61, 135, 0.06);
    border-color: #4a2d64;
    color: #4a2d64;
}
</style>
HTML;
    }

    private function render_accordion_section(string $accordion_id, string $title, string $content, bool $open = false, bool $promoted = false): string
    {
        $item_class = 'ai-seo-keeper-accordion-item' . ($promoted ? ' is-promoted' : '');
        ob_start();
    ?>
        <div class="<?php echo esc_attr($item_class); ?>">
            <button type="button" class="ai-seo-keeper-accordion-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($accordion_id); ?>" data-default-open="<?php echo $open ? '1' : '0'; ?>">
                <span><?php echo wp_kses($title, array('span' => array('class' => true, 'data-tip' => true), 'img' => array('class' => true, 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true))); ?></span>
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

    public function has_saved_frontend_data(array $data): bool
    {
        foreach ($data as $value) {
            if ('' !== trim((string) $value)) {
                return true;
            }
        }

        return false;
    }

    public function has_saved_frontend_data_for_post(int $post_id): bool
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

    public function count_pages_matching_skip_patterns(string $patterns_text): int
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
        $meta_count = 0;
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

    /**
     * In the admin editor, intercept page builder meta reads so that pending
     * AI content changes are visible in BeTheme, Elementor, and other builders
     * before the user clicks Update/Publish.
     *
     * @param mixed  $value     The value to return (null = let WordPress handle it).
     * @param int    $object_id The post ID.
     * @param string $meta_key  The meta key being read.
     * @param bool   $single    Whether to return a single value.
     * @return mixed Modified meta value or null.
     */
    public function filter_admin_pending_builder_meta($value, int $object_id, string $meta_key, bool $single)
    {
        // Only operate when editing a post in admin.
        global $pagenow;
        if (! is_admin() || ! in_array($pagenow, array('post.php', 'post-new.php'), true)) {
            return $value;
        }

        static $builder_meta_keys = array(
            'mfn-page-items',
            '_elementor_data',
            '_fl_builder_data',
            '_bricks_page_content_2',
            '_themify_builder_settings_json',
            'ct_builder_shortcodes',
            'tve_updated_post',
            'brizy-post-editor-data',
            '_seedprod_page',
            'tatsu_sections',
        );

        if (! in_array($meta_key, $builder_meta_keys, true)) {
            return $value;
        }

        $pending = Content_Writer::get_pending_changes($object_id);
        if (empty($pending)) {
            return $value;
        }

        // Map builder name → meta key.
        static $builder_map = array(
            'betheme'  => 'mfn-page-items',
            'elementor' => '_elementor_data',
            'beaver'   => '_fl_builder_data',
            'bricks'   => '_bricks_page_content_2',
            'themify'  => '_themify_builder_settings_json',
            'oxygen'   => 'ct_builder_shortcodes',
            'thrive'   => 'tve_updated_post',
            'brizy'    => 'brizy-post-editor-data',
            'seedprod' => '_seedprod_page',
            'tatsu'    => 'tatsu_sections',
        );

        $pending_builder = $pending['builder'] ?? 'post_content';
        $target_key = $builder_map[$pending_builder] ?? '';
        if ($meta_key !== $target_key) {
            return $value;
        }

        // Remove filter temporarily to read actual meta without recursion.
        remove_filter('get_post_metadata', array($this, 'filter_admin_pending_builder_meta'), 1);
        $raw = get_post_meta($object_id, $meta_key, $single);
        add_filter('get_post_metadata', array($this, 'filter_admin_pending_builder_meta'), 1, 4);

        if (empty($raw)) {
            return $value;
        }

        $is_betheme = ('betheme' === $pending_builder);

        if ($is_betheme && is_string($raw)) {
            $decoded_b64 = base64_decode($raw, true);
            if (false === $decoded_b64) {
                return $value;
            }
            $data = @unserialize($decoded_b64);
            if (! is_array($data)) {
                return $value;
            }

            $changes = $pending['changes'] ?? array();
            foreach ($changes as $change) {
                $old = (string) ($change['old'] ?? '');
                $new = (string) ($change['new'] ?? '');
                if ('' === $old) {
                    continue;
                }

                if (
                    preg_match('/^<(h[1-6])>(.*)<\/\1>$/is', trim($old), $old_m)
                    && preg_match('/^<(h[1-6])>(.*)<\/\1>$/is', trim($new), $new_m)
                ) {
                    Content_Writer::betheme_heading_replace_public($data, strtolower($old_m[1]), $old_m[2], strtolower($new_m[1]), $new_m[2]);
                    continue;
                }

                $found = false;
                $data = Content_Writer::walk_replace_public($data, $old, $new, $found);
            }

            $modified = base64_encode(serialize($data));
            return $single ? array($modified) : array($modified);
        }

        // Non-BeTheme builders: string-based replacement.
        $content = is_string($raw) ? $raw : maybe_serialize($raw);
        $modified = Content_Writer::apply_changes_to_string($content, $pending['changes']);

        $unserialized = maybe_unserialize($modified);
        return $single ? array($unserialized) : array($unserialized);
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
            // When editing via a page builder (BeTheme, Elementor, etc.), our
            // filter_admin_pending_builder_meta already injected the modified
            // content into the builder's read, so the builder saved the changes
            // itself. In that case, just clear the pending meta and mark published.
            $pending_builder = $pending['builder'] ?? 'post_content';
            if ('post_content' !== $pending_builder) {
                Content_Writer::clear_pending_changes($post_id);
                $this->history_store->update_content_edit_status($post_id, 'published');
            } else {
                $result = Content_Writer::apply_pending_changes($post_id);
                if (! empty($result) && $result['applied'] > 0) {
                    $this->history_store->update_content_edit_status($post_id, 'published');
                }
            }
        }
    }

    public function persist_editor_meta(int $post_id, array $raw_input): array
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
        return SEO_Analysis::apply_editor_text_limits($fields);
    }

    private function get_text_length(string $text): int
    {
        return SEO_Analysis::get_text_length($text);
    }

    private function truncate_text(string $text, int $max_length): string
    {
        return SEO_Analysis::truncate_text($text, $max_length);
    }

    private function get_schema_type_options(): array
    {
        return SEO_Analysis::get_schema_type_options();
    }

    private function get_robots_directive_options(): array
    {
        return SEO_Analysis::get_robots_directive_options();
    }

    private function render_focus_keyphrase_checks_markup(\WP_Post $post, string $focus_keyphrase, string $seo_title, string $seo_description): string
    {
        return SEO_Analysis::render_checks_markup($post, $focus_keyphrase, $seo_title, $seo_description);
    }

    public function render_audit_post_links(array $ids): string
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

    public function render_history_markup(array $recent_suggestions, array $content_edits = array()): string
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

    public function render_chat_history_markup(array $chat_messages): string
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

    public function redirect_to_audit_page(string $status, string $message): void
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

    public function redirect_to_settings_page(string $status, string $message): void
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
