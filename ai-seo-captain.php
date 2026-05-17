<?php

/**
 * Plugin Name: SEO Captain
 * Description: AI-assisted SEO copilot for WordPress with metadata approval workflows, audits, discovery documents, schema, and refresh signaling.
 * Version: 1.0.0-beta
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Green Coders
 * Text Domain: ai-seo-captain
 */

if (! defined('ABSPATH')) {
    exit;
}

define('AI_SEO_KEEPER_VERSION', '1.0.0-beta');
define('AI_SEO_KEEPER_FILE', __FILE__);
define('AI_SEO_KEEPER_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_KEEPER_URL', plugin_dir_url(__FILE__));

require_once AI_SEO_KEEPER_PATH . 'includes/autoload.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-activator.php';

add_action('init', static function () {
    load_plugin_textdomain('ai-seo-captain', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

register_activation_hook(__FILE__, array('AI_SEO_Captain\\Activator', 'activate'));
register_activation_hook(__FILE__, static function () {
    // Schedule cron jobs on activation (deferred so Plugin boots first on next load).
    $settings = new AI_SEO_Captain\Settings();
    $indexer  = new AI_SEO_Captain\Content_Indexer();
    $cron     = new AI_SEO_Captain\Cron_Manager($settings, $indexer);
    $cron->schedule_all();
});
register_deactivation_hook(__FILE__, static function () {
    $settings = new AI_SEO_Captain\Settings();
    $indexer  = new AI_SEO_Captain\Content_Indexer();
    $cron     = new AI_SEO_Captain\Cron_Manager($settings, $indexer);
    $cron->unschedule_all();
    flush_rewrite_rules();
});

add_action(
    'plugins_loaded',
    static function () {
        // Auto-upgrade DB schema when version changes.
        $db_version = get_option('ai_seo_captain_db_version', '0');
        if (version_compare($db_version, AI_SEO_KEEPER_VERSION, '<')) {
            AI_SEO_Captain\Activator::activate();
            update_option('ai_seo_captain_db_version', AI_SEO_KEEPER_VERSION);
        }

        AI_SEO_Captain\Plugin::instance()->boot();
    }
);
