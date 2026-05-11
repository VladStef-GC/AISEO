<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Tables (order matters: messages before conversations for FK safety).
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_messages");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_conversations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_content_index");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_redirects");

// Options.
delete_option('ai_seo_keeper_options');
delete_option('ai_seo_keeper_indexnow_log');
delete_option('ai_seo_keeper_db_version');

// Post meta.
$meta_keys = array(
    '_ai_seo_keeper_focus_keyphrase',
    '_ai_seo_keeper_meta_title',
    '_ai_seo_keeper_meta_description',
    '_ai_seo_keeper_social_title',
    '_ai_seo_keeper_social_description',
    '_ai_seo_keeper_social_image',
    '_ai_seo_keeper_canonical_url',
    '_ai_seo_keeper_robots_directives',
    '_ai_seo_keeper_schema_type',
    '_ai_seo_keeper_approved_message_id',
    '_ai_seo_keeper_frontend_enabled',
    '_ai_seo_keeper_title_branding_off',
    '_ai_seo_keeper_cornerstone',
    '_ai_seo_keeper_page_audit',
    '_ai_seo_keeper_pending_content_changes',
    '_ai_seo_keeper_content_backup',
    '_ai_seo_keeper_hreflang',
);

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key), array('%s'));
}

// Term meta (taxonomy SEO fields).
$term_meta_keys = array(
    '_ai_seo_keeper_seo_title',
    '_ai_seo_keeper_meta_description',
    '_ai_seo_keeper_canonical',
    '_ai_seo_keeper_noindex',
);

foreach ($term_meta_keys as $meta_key) {
    $wpdb->delete($wpdb->termmeta, array('meta_key' => $meta_key), array('%s'));
}

