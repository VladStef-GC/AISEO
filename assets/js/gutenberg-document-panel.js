/**
 * SEO Captain — Enhanced Block Editor Integration
 * 
 * This file provides full Gutenberg block editor support with:
 * - Document settings panel (always visible, not in More menu)
 * - Matches Classic Editor metabox functionality
 * - Modern Gutenberg UI patterns
 */
(function () {
    'use strict';

    var cfg = window.aiSeoKeeperGutenberg || {};
    
    // WordPress packages
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;

    // Components
    var PanelBody = wp.components.PanelBody;
    var PanelRow = wp.components.PanelRow;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var ToggleControl = wp.components.ToggleControl;
    var Spinner = wp.components.Spinner;

    // Constants
    var TITLE_MAX = cfg.limits ? cfg.limits.titleMax : 60;
    var TITLE_MIN = cfg.limits ? cfg.limits.titleMin : 30;
    var DESC_MAX = cfg.limits ? cfg.limits.descriptionMax : 155;
    var DESC_MIN = cfg.limits ? cfg.limits.descriptionMin : 70;
    var SUFFIX = cfg.brandingSuffix || '';
    var SUFFIX_LEN = cfg.brandingSuffixLength || 0;

    /**
     * Main SEO Settings Panel for Block Editor
     * Appears in the Document Settings sidebar (always visible, not in More menu)
     */
    function SeoSettingsPanel() {
        var postId = useSelect(function (s) { return s('core/editor').getCurrentPostId(); });
        var postMeta = useSelect(function (s) { return s('core/editor').getEditedPostAttribute('meta') || {}; });

        var editPost = useDispatch('core/editor').editPost;

        // Local state
        var _seoTitle = useState(postMeta[cfg.metaKeys.title] || '');
        var seoTitle = _seoTitle[0];
        var setSeoTitle = _seoTitle[1];

        var _metaDesc = useState(postMeta[cfg.metaKeys.description] || '');
        var metaDesc = _metaDesc[0];
        var setMetaDesc = _metaDesc[1];

        var _keyphrase = useState(postMeta[cfg.metaKeys.keyphrase] || '');
        var keyphrase = _keyphrase[0];
        var setKeyphrase = _keyphrase[1];

        var _noindex = useState(postMeta[cfg.metaKeys.robots] ? 
            postMeta[cfg.metaKeys.robots].indexOf('noindex') !== -1 : false);
        var noindex = _noindex[0];
        var setNoindex = _noindex[1];

        var _saving = useState(false);
        var saving = _saving[0];
        var setSaving = _saving[1];

        var _notice = useState(null);
        var notice = _notice[0];
        var setNotice = _notice[1];

        var initialised = useRef(false);

        // Initialize from post meta
        useEffect(function () {
            if (!postId || initialised.current) { return; }
            initialised.current = true;
            setSeoTitle(postMeta[cfg.metaKeys.title] || '');
            setMetaDesc(postMeta[cfg.metaKeys.description] || '');
            setKeyphrase(postMeta[cfg.metaKeys.keyphrase] || '');
            setNoindex(postMeta[cfg.metaKeys.robots] ? 
                postMeta[cfg.metaKeys.robots].indexOf('noindex') !== -1 : false);
        }, [postId]);

        function handleSave() {
            if (!postId) { return; }
            setSaving(true);
            setNotice(null);

            // Prepare form data
            var formData = new FormData();
            formData.append('action', cfg.actions.save);
            formData.append('nonce', cfg.nonce || '');
            formData.append('post_id', postId);
            formData.append('seo_title', seoTitle);
            formData.append('meta_description', metaDesc);
            formData.append('focus_keyphrase', keyphrase);
            formData.append('robots_directives', noindex ? 'noindex' : '');

            fetch(cfg.ajaxUrl, { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    setSaving(false);
                    if (res.success) {
                        setNotice({ type: 'success', msg: cfg.i18n.saved });
                        // Update the editor meta store
                        var metaUpdate = {};
                        metaUpdate[cfg.metaKeys.title] = seoTitle;
                        metaUpdate[cfg.metaKeys.description] = metaDesc;
                        metaUpdate[cfg.metaKeys.keyphrase] = keyphrase;
                        editPost({ meta: metaUpdate });
                    } else {
                        setNotice({ type: 'error', msg: res.data && res.data.message ? res.data.message : cfg.i18n.saveError });
                    }
                })
                .catch(function () {
                    setSaving(false);
                    setNotice({ type: 'error', msg: cfg.i18n.saveError });
                });
        }

        return el(PluginDocumentSettingPanel, {
            name: 'ai-seo-captain-settings',
            title: el(Fragment, null,
                el('span', { style: { marginRight: '6px' } }, '🔍'),
                'SEO Settings'
            ),
            className: 'aisc-block-editor-panel'
        },
            // Notice
            notice && el(Notice, {
                status: notice.type,
                isDismissible: true,
                onRemove: function () { setNotice(null); }
            }, notice.msg),

            // SEO Title
            el(PanelBody, { title: cfg.i18n.seoTitle, initialOpen: true },
                el(TextControl, {
                    label: cfg.i18n.seoTitle,
                    value: seoTitle,
                    onChange: function (v) { setSeoTitle(v); },
                    help: 'Length: ' + seoTitle.length + '/' + TITLE_MAX + ' chars' + 
                        (seoTitle.length < TITLE_MIN ? ' (too short)' : seoTitle.length > TITLE_MAX ? ' (too long)' : ' (good)')
                })
            ),

            // Meta Description
            el(PanelBody, { title: cfg.i18n.metaDescription, initialOpen: true },
                el(TextareaControl, {
                    label: cfg.i18n.metaDescription,
                    value: metaDesc,
                    onChange: function (v) { setMetaDesc(v); },
                    rows: 3,
                    help: 'Length: ' + metaDesc.length + '/' + DESC_MAX + ' chars'
                })
            ),

            // Focus Keyphrase
            el(PanelBody, { title: cfg.i18n.focusKeyphrase, initialOpen: false },
                el(TextControl, {
                    label: cfg.i18n.focusKeyphrase,
                    value: keyphrase,
                    onChange: function (v) { setKeyphrase(v); },
                    help: 'Primary keyword to optimize this page for'
                })
            ),

            // Noindex Toggle
            el(PanelBody, { title: cfg.i18n.noindex, initialOpen: false },
                el(ToggleControl, {
                    label: cfg.i18n.noindex,
                    checked: noindex,
                    onChange: function (v) { setNoindex(v); },
                    help: 'Prevent search engines from indexing this page'
                })
            ),

            // Save Button
            el(PanelRow, null,
                el(Button, {
                    isPrimary: true,
                    onClick: handleSave,
                    disabled: saving,
                    style: { width: '100%' }
                },
                    saving ? el(Spinner, null) : null,
                    saving ? ' Saving...' : 'Save SEO Settings'
                )
            )
        );
    }

    // Register the panel plugin
    registerPlugin('ai-seo-captain-document-settings', {
        render: SeoSettingsPanel
    });

})();