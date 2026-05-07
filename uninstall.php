<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_messages");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_conversations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_seo_keeper_content_index");

delete_option('ai_seo_keeper_options');
delete_option('ai_seo_keeper_indexnow_log');

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
);

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key), array('%s'));
}
