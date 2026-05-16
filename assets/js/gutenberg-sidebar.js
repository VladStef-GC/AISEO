/**
 * SEO Captain — Gutenberg Block Editor Sidebar Panel
 *
 * Uses global wp.* APIs (bundled with WordPress core) — no build step required.
 * Registered via PHP as a script with wp-* handle dependencies.
 *
 * Config object: window.aiSeoKeeperGutenberg (localised by PHP)
 */
(function () {
    'use strict';

    var cfg = window.aiSeoKeeperGutenberg || {};

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;

    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;

    var PanelBody = wp.components.PanelBody;
    var PanelRow = wp.components.PanelRow;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var RangeControl = wp.components.RangeControl;
    var ToggleControl = wp.components.ToggleControl;

    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------
    var TITLE_MAX = cfg.limits ? cfg.limits.titleMax : 60;
    var TITLE_MIN = cfg.limits ? cfg.limits.titleMin : 30;
    var DESC_MAX = cfg.limits ? cfg.limits.descriptionMax : 155;
    var DESC_MIN = cfg.limits ? cfg.limits.descriptionMin : 70;
    var SUFFIX_LEN = cfg.brandingSuffixLength || 0;
    var SUFFIX = cfg.brandingSuffix || '';

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------
    function charCount(str) {
        return str ? str.length : 0;
    }

    function countClass(len, min, max) {
        if (len === 0) { return 'aisc-count aisc-count--empty'; }
        if (len < min) { return 'aisc-count aisc-count--short'; }
        if (len > max) { return 'aisc-count aisc-count--long'; }
        return 'aisc-count aisc-count--ok';
    }

    function apiFetch(action, bodyData) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', cfg.nonce || '');
        Object.keys(bodyData).forEach(function (k) {
            formData.append(k, bodyData[k]);
        });
        return fetch(cfg.ajaxUrl, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); });
    }

    // -----------------------------------------------------------------------
    // SEO Score meter
    // -----------------------------------------------------------------------
    function ScoreMeter(props) {
        var score = props.score;
        var label = score >= 75 ? 'Good' : score >= 45 ? 'OK' : 'Needs work';
        var colour = score >= 75 ? '#00a32a' : score >= 45 ? '#dba617' : '#d63638';
        return el('div', { className: 'aisc-score-meter' },
            el('div', { className: 'aisc-score-bar-wrap' },
                el('div', {
                    className: 'aisc-score-bar',
                    style: { width: score + '%', background: colour }
                })
            ),
            el('span', { className: 'aisc-score-label', style: { color: colour } },
                score + '/100 — ' + label
            )
        );
    }

    // -----------------------------------------------------------------------
    // Title field with counter
    // -----------------------------------------------------------------------
    function TitleField(props) {
        var raw = props.value || '';
        var effective = SUFFIX ? raw.replace(new RegExp(SUFFIX.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'), '').trim() : raw;
        var displayLen = raw.length;
        var budgetUsed = effective.length;

        return el(Fragment, null,
            el(TextControl, {
                label: cfg.i18n.seoTitle,
                value: raw,
                onChange: props.onChange,
                help: SUFFIX ? cfg.i18n.brandingNote + ' "' + SUFFIX + '"' : ''
            }),
            el('div', { className: 'aisc-field-meta' },
                el('span', { className: countClass(displayLen, TITLE_MIN, TITLE_MAX) },
                    displayLen + '/' + TITLE_MAX + ' chars'
                ),
                SUFFIX_LEN > 0 && el('span', { className: 'aisc-budget-note' },
                    ' (' + budgetUsed + '/' + (TITLE_MAX - SUFFIX_LEN) + ' content budget)'
                )
            )
        );
    }

    // -----------------------------------------------------------------------
    // Snippet preview
    // -----------------------------------------------------------------------
    function SnippetPreview(props) {
        var title = props.title || '(' + cfg.i18n.noTitle + ')';
        var desc = props.description || '(' + cfg.i18n.noDescription + ')';
        var url = props.url || window.location.host;
        return el('div', { className: 'aisc-snippet-preview' },
            el('div', { className: 'aisc-snippet-url' }, url),
            el('div', { className: 'aisc-snippet-title' }, title),
            el('div', { className: 'aisc-snippet-desc' }, desc)
        );
    }

    // -----------------------------------------------------------------------
    // Checks list
    // -----------------------------------------------------------------------
    function ChecksList(props) {
        var checks = props.checks || [];
        if (!checks.length) {
            return el('p', { style: { color: '#646970', fontSize: '12px' } }, cfg.i18n.saveToContinue);
        }
        return el('ul', { className: 'aisc-checks-list' },
            checks.map(function (c, i) {
                return el('li', { key: i, className: 'aisc-check aisc-check--' + (c.pass ? 'pass' : 'fail') },
                    el('span', { className: 'aisc-check-icon' }, c.pass ? '✓' : '✗'),
                    ' ',
                    c.label
                );
            })
        );
    }

    // -----------------------------------------------------------------------
    // Main sidebar component
    // -----------------------------------------------------------------------
    function AiSeoSidebar() {
        // Post data from the editor store
        var postId = useSelect(function (s) { return s('core/editor').getCurrentPostId(); });
        var postStatus = useSelect(function (s) { return s('core/editor').getEditedPostAttribute('status'); });
        var postMeta = useSelect(function (s) { return s('core/editor').getEditedPostAttribute('meta') || {}; });
        var permalink = useSelect(function (s) {
            var post = s('core/editor').getCurrentPost();
            return post ? (post.link || post.slug || '') : '';
        });

        var editPost = useDispatch('core/editor').editPost;

        // Local state
        var initialised = useRef(false);
        var _state = useState({ seoTitle: '', metaDesc: '', keyphrase: '', noindex: false });
        var fields = _state[0];
        var setFields = _state[1];

        var _saving = useState(false);
        var saving = _saving[0];
        var setSaving = _saving[1];

        var _generating = useState(false);
        var generating = _generating[0];
        var setGenerating = _generating[1];

        var _notice = useState(null);
        var notice = _notice[0];
        var setNotice = _notice[1];

        var _score = useState(0);
        var score = _score[0];
        var setScore = _score[1];

        var _checks = useState([]);
        var checks = _checks[0];
        var setChecks = _checks[1];

        var _chatInput = useState('');
        var chatInput = _chatInput[0];
        var setChatInput = _chatInput[1];

        var _chatReply = useState('');
        var chatReply = _chatReply[0];
        var setChatReply = _chatReply[1];

        var _chatting = useState(false);
        var chatting = _chatting[0];
        var setChatting = _chatting[1];

        // Initialise fields from post meta when postId becomes available
        useEffect(function () {
            if (!postId || initialised.current) { return; }
            initialised.current = true;
            setFields({
                seoTitle: postMeta[cfg.metaKeys.title] || '',
                metaDesc: postMeta[cfg.metaKeys.description] || '',
                keyphrase: postMeta[cfg.metaKeys.keyphrase] || '',
                noindex: postMeta[cfg.metaKeys.robots] ? postMeta[cfg.metaKeys.robots].indexOf('noindex') !== -1 : false
            });
        }, [postId]);

        // Recompute checks whenever fields change
        useEffect(function () {
            var t = fields.seoTitle;
            var d = fields.metaDesc;
            var kp = (fields.keyphrase || '').toLowerCase();
            var tc = t.toLowerCase();
            var dc = d.toLowerCase();

            var c = [
                { label: cfg.i18n.checks.titleLength, pass: t.length >= TITLE_MIN && t.length <= TITLE_MAX },
                { label: cfg.i18n.checks.descLength, pass: d.length >= DESC_MIN && d.length <= DESC_MAX },
                { label: cfg.i18n.checks.titleFilled, pass: t.length > 0 },
                { label: cfg.i18n.checks.descFilled, pass: d.length > 0 }
            ];

            if (kp) {
                c.push({ label: cfg.i18n.checks.kpInTitle, pass: tc.indexOf(kp) !== -1 });
                c.push({ label: cfg.i18n.checks.kpInDesc, pass: dc.indexOf(kp) !== -1 });
            }

            setChecks(c);

            var passing = c.filter(function (x) { return x.pass; }).length;
            setScore(Math.round((passing / c.length) * 100));
        }, [fields]);

        function handleFieldChange(key, val) {
            setFields(function (prev) {
                var next = Object.assign({}, prev);
                next[key] = val;
                return next;
            });
        }

        function handleSave() {
            if (!postId) { return; }
            setSaving(true);
            setNotice(null);
            apiFetch(cfg.actions.save, {
                post_id: postId,
                seo_title: fields.seoTitle,
                meta_description: fields.metaDesc,
                focus_keyphrase: fields.keyphrase,
                robots_directives: fields.noindex ? 'noindex' : ''
            }).then(function (res) {
                setSaving(false);
                if (res.success) {
                    setNotice({ type: 'success', msg: cfg.i18n.saved });
                    // Update block editor meta store so WP knows fields are persisted
                    var metaUpdate = {};
                    metaUpdate[cfg.metaKeys.title] = fields.seoTitle;
                    metaUpdate[cfg.metaKeys.description] = fields.metaDesc;
                    metaUpdate[cfg.metaKeys.keyphrase] = fields.keyphrase;
                    editPost({ meta: metaUpdate });
                } else {
                    setNotice({ type: 'error', msg: res.data && res.data.message ? res.data.message : cfg.i18n.saveError });
                }
            }).catch(function () {
                setSaving(false);
                setNotice({ type: 'error', msg: cfg.i18n.saveError });
            });
        }

        function handleGenerate() {
            if (!postId) { return; }
            setGenerating(true);
            setNotice(null);
            apiFetch(cfg.actions.generate, {
                post_id: postId,
                focus_keyphrase: fields.keyphrase,
                seo_title: fields.seoTitle,
                meta_description: fields.metaDesc
            }).then(function (res) {
                setGenerating(false);
                if (res.success && res.data) {
                    var d = res.data;
                    setFields(function (prev) {
                        return Object.assign({}, prev, {
                            seoTitle: d.seo_title || prev.seoTitle,
                            metaDesc: d.meta_description || prev.metaDesc,
                            keyphrase: d.focus_keyphrase || prev.keyphrase
                        });
                    });
                    setNotice({ type: 'success', msg: cfg.i18n.generated });
                } else {
                    setNotice({ type: 'error', msg: res.data && res.data.message ? res.data.message : cfg.i18n.generateError });
                }
            }).catch(function () {
                setGenerating(false);
                setNotice({ type: 'error', msg: cfg.i18n.generateError });
            });
        }

        function handleChat() {
            if (!postId || !chatInput.trim()) { return; }
            setChatting(true);
            setChatReply('');
            apiFetch(cfg.actions.chat, {
                post_id: postId,
                message: chatInput
            }).then(function (res) {
                setChatting(false);
                if (res.success && res.data && res.data.reply) {
                    setChatReply(res.data.reply);
                } else {
                    setChatReply(res.data && res.data.message ? res.data.message : cfg.i18n.chatError);
                }
            }).catch(function () {
                setChatting(false);
                setChatReply(cfg.i18n.chatError);
            });
        }

        var titleLen = charCount(fields.seoTitle);
        var descLen = charCount(fields.metaDesc);

        return el(Fragment, null,
            // "More" menu item to open the sidebar
            el(PluginSidebarMoreMenuItem, { target: 'ai-seo-captain-sidebar' },
                cfg.i18n.sidebarTitle
            ),

            el(PluginSidebar, {
                name: 'ai-seo-captain-sidebar',
                title: cfg.i18n.sidebarTitle,
                icon: el('span', { style: { fontSize: '16px' } }, '🔍')
            },

                // Notice
                notice && el(Notice, {
                    status: notice.type,
                    isDismissible: true,
                    onRemove: function () { setNotice(null); }
                }, notice.msg),

                // Score
                el(PanelBody, { title: cfg.i18n.seoScore, initialOpen: true },
                    el(PanelRow, null, el(ScoreMeter, { score: score }))
                ),

                // Snippet Preview
                el(PanelBody, { title: cfg.i18n.snippetPreview, initialOpen: false },
                    el(SnippetPreview, {
                        title: fields.seoTitle,
                        description: fields.metaDesc,
                        url: permalink
                    })
                ),

                // SEO Fields
                el(PanelBody, { title: cfg.i18n.seoFields, initialOpen: true },
                    el(PanelRow, null,
                        el(TitleField, {
                            value: fields.seoTitle,
                            onChange: function (v) { handleFieldChange('seoTitle', v); }
                        })
                    ),
                    el(PanelRow, null,
                        el('div', { className: 'aisc-full-width' },
                            el(TextareaControl, {
                                label: cfg.i18n.metaDescription,
                                value: fields.metaDesc,
                                onChange: function (v) { handleFieldChange('metaDesc', v); },
                                rows: 3
                            }),
                            el('div', { className: 'aisc-field-meta' },
                                el('span', { className: countClass(descLen, DESC_MIN, DESC_MAX) },
                                    descLen + '/' + DESC_MAX + ' chars'
                                )
                            )
                        )
                    ),
                    el(PanelRow, null,
                        el(TextControl, {
                            label: cfg.i18n.focusKeyphrase,
                            value: fields.keyphrase,
                            onChange: function (v) { handleFieldChange('keyphrase', v); }
                        })
                    ),
                    el(PanelRow, null,
                        el(ToggleControl, {
                            label: cfg.i18n.noindex,
                            checked: fields.noindex,
                            onChange: function (v) { handleFieldChange('noindex', v); }
                        })
                    ),
                    el(PanelRow, null,
                        el('div', { className: 'aisc-actions' },
                            el(Button, {
                                variant: 'primary',
                                onClick: handleSave,
                                isBusy: saving,
                                disabled: saving || generating
                            }, saving ? el(Spinner) : cfg.i18n.saveDraft),
                            el(Button, {
                                variant: 'secondary',
                                onClick: handleGenerate,
                                isBusy: generating,
                                disabled: saving || generating
                            }, generating ? el(Spinner) : cfg.i18n.generateAi)
                        )
                    )
                ),

                // SEO Checks
                el(PanelBody, { title: cfg.i18n.seoChecks, initialOpen: false },
                    el(ChecksList, { checks: checks })
                ),

                // AI Assistant
                el(PanelBody, { title: cfg.i18n.aiAssistant, initialOpen: false },
                    el(PanelRow, null,
                        el(TextareaControl, {
                            label: cfg.i18n.askQuestion,
                            value: chatInput,
                            onChange: setChatInput,
                            rows: 3,
                            placeholder: cfg.i18n.chatPlaceholder
                        })
                    ),
                    el(PanelRow, null,
                        el(Button, {
                            variant: 'secondary',
                            onClick: handleChat,
                            isBusy: chatting,
                            disabled: chatting || !chatInput.trim()
                        }, chatting ? el(Spinner) : cfg.i18n.askAi)
                    ),
                    chatReply && el(PanelRow, null,
                        el('div', { className: 'aisc-chat-reply' }, chatReply)
                    )
                )
            )
        );
    }

    // -----------------------------------------------------------------------
    // Register the plugin
    // -----------------------------------------------------------------------
    registerPlugin('ai-seo-captain', {
        render: AiSeoSidebar,
        icon: 'search'
    });

})();
