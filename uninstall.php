<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// --- Freemius uninstall event --------------------------------------------------
// When a plugin ships its own uninstall.php, WordPress runs it instead of any
// registered uninstall hooks, so Freemius never gets a chance to clean up its
// own data or report the uninstall. Boot the SDK and fire its uninstall event
// first so licensing/account data is handled correctly before we purge ours.
if (file_exists(dirname(__FILE__) . '/vendor/freemius/start.php')) {
    if (! function_exists('asc_fs')) {
        require_once dirname(__FILE__) . '/ai-seo-captain.php';
    }

    if (function_exists('asc_fs')) {
        asc_fs()->_uninstall_plugin_event();
    }
}
// --- End Freemius --------------------------------------------------------------

global $wpdb;

// Tables (order matters: messages before conversations for FK safety).
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_captain_messages");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_captain_conversations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_captain_content_index");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_captain_redirects");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_captain_runs");

// Options.
delete_option('ai_seo_captain_options');
delete_option('ai_seo_captain_indexnow_log');
delete_option('ai_seo_captain_db_version');

// Post meta.
$meta_keys = array(
    '_ai_seo_captain_focus_keyphrase',
    '_ai_seo_captain_meta_title',
    '_ai_seo_captain_meta_description',
    '_ai_seo_captain_social_title',
    '_ai_seo_captain_social_description',
    '_ai_seo_captain_social_image',
    '_ai_seo_captain_canonical_url',
    '_ai_seo_captain_robots_directives',
    '_ai_seo_captain_schema_type',
    '_ai_seo_captain_approved_message_id',
    '_ai_seo_captain_frontend_enabled',
    '_ai_seo_captain_title_branding_off',
    '_ai_seo_captain_cornerstone',
    '_ai_seo_captain_page_audit',
    '_ai_seo_captain_audit_skip',
    '_ai_seo_captain_pending_content_changes',
    '_ai_seo_captain_content_backup',
    '_ai_seo_captain_hreflang',
    '_ai_seo_captain_keywords',
    '_ai_seo_captain_exclude_sitemap',
);

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key), array('%s'));
}

// Dynamic video meta keys (pattern: _ai_seo_captain_video_title_{hash} / _ai_seo_captain_video_desc_{hash}).
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
    $wpdb->esc_like('_ai_seo_captain_video_title_') . '%'
));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
    $wpdb->esc_like('_ai_seo_captain_video_desc_') . '%'
));

// Term meta (taxonomy SEO fields).
$term_meta_keys = array(
    '_ai_seo_captain_seo_title',
    '_ai_seo_captain_meta_description',
    '_ai_seo_captain_canonical',
    '_ai_seo_captain_noindex',
);

foreach ($term_meta_keys as $meta_key) {
    $wpdb->delete($wpdb->termmeta, array('meta_key' => $meta_key), array('%s'));
}

// User meta (active runs selection).
$wpdb->delete($wpdb->usermeta, array('meta_key' => '_ai_seo_captain_active_runs'), array('%s'));
