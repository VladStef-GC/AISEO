<?php

/**
 * Plugin Name: AI SEO Keeper
 * Description: AI-assisted SEO copilot for WordPress with metadata approval workflows, audits, discovery documents, schema, and refresh signaling.
 * Version: 1.3.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Green Coders
 * Text Domain: ai-seo-keeper
 */

if (! defined('ABSPATH')) {
    exit;
}

define('AI_SEO_KEEPER_VERSION', '1.3.1');
define('AI_SEO_KEEPER_FILE', __FILE__);
define('AI_SEO_KEEPER_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_KEEPER_URL', plugin_dir_url(__FILE__));

require_once AI_SEO_KEEPER_PATH . 'includes/autoload.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-activator.php';

add_action('init', static function () {
    load_plugin_textdomain('ai-seo-keeper', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

register_activation_hook(__FILE__, array('AI_SEO_Keeper\\Activator', 'activate'));
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

add_action(
    'plugins_loaded',
    static function () {
        // Auto-upgrade DB schema when version changes.
        $db_version = get_option('ai_seo_keeper_db_version', '0');
        if (version_compare($db_version, AI_SEO_KEEPER_VERSION, '<')) {
            AI_SEO_Keeper\Activator::activate();
            update_option('ai_seo_keeper_db_version', AI_SEO_KEEPER_VERSION);
        }

        AI_SEO_Keeper\Plugin::instance()->boot();
    }
);
