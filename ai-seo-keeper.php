<?php

/**
 * Plugin Name: AI SEO Keeper
 * Description: AI-assisted SEO copilot for WordPress with metadata approval workflows, audits, discovery documents, schema, and refresh signaling.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Green Coders
 * Text Domain: ai-seo-keeper
 */

if (! defined('ABSPATH')) {
    exit;
}

define('AI_SEO_KEEPER_VERSION', '1.0.0');
define('AI_SEO_KEEPER_FILE', __FILE__);
define('AI_SEO_KEEPER_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_KEEPER_URL', plugin_dir_url(__FILE__));

require_once AI_SEO_KEEPER_PATH . 'includes/class-activator.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-content-helper.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-content-writer.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-settings.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-content-indexer.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-ai-generator.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-history-store.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-audit-engine.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-indexnow.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-frontend.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-discovery.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-sitemap.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-admin.php';
require_once AI_SEO_KEEPER_PATH . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('AI_SEO_Keeper\\Activator', 'activate'));
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

add_action(
    'plugins_loaded',
    static function () {
        AI_SEO_Keeper\Plugin::instance()->boot();
    }
);
