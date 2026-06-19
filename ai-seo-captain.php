<?php

/**
 * Plugin Name: SEO Captain
 * Description: AI-assisted SEO copilot for WordPress with metadata approval workflows, audits, discovery documents, schema, and refresh signaling.
 * Version: 1.4.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Green Coders
 * Text Domain: ai-seo-captain
 *
 * Premium-only files and folders excluded from the free (WordPress.org) build
 * by the Freemius processor. These contain the paid feature engines.
 *
 * @fs_premium_only /includes/class-search-console.php, /includes/class-broken-link-scanner.php, /includes/class-rest-api.php, /includes/class-woocommerce-integration.php, /includes/cache/, /includes/importexport/, /modules/, /advanced-cache.php
 */

if (! defined('ABSPATH')) {
    exit;
}

define('AI_SEO_CAPTAIN_VERSION', '1.4.0');
define('AI_SEO_CAPTAIN_FILE', __FILE__);
define('AI_SEO_CAPTAIN_PATH', plugin_dir_path(__FILE__));
define('AI_SEO_CAPTAIN_URL', plugin_dir_url(__FILE__));

// --- Freemius SDK Integration ---------------------------------------------------
// Auto-deactivation wrapper: when the premium version activates, the free one
// calls set_basename() and stops loading its own logic to avoid conflicts.
if ( function_exists( 'asc_fs' ) ) {
    asc_fs()->set_basename( true, __FILE__ );
} else {
    /**
     * DO NOT REMOVE THIS IF — it is essential for the function_exists()
     * call above to work properly when switching between free ↔ premium.
     */
    if ( ! function_exists( 'asc_fs' ) ) {
        function asc_fs() {
            global $asc_fs;

            if ( ! isset( $asc_fs ) ) {
                require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

                $asc_fs = fs_dynamic_init( array(
                    'id'                  => '31577',
                    'slug'                => 'ai-seo-captain',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_b7c40c5a6ac98d533226cd08039cd',
                    'is_premium'          => true,
                    'premium_suffix'      => 'PRO',
                    'has_premium_version' => true,
                    'has_addons'          => false,
                    'has_paid_plans'      => true,
                    'is_org_compliant'    => true,
                    'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
                    'menu'                => array(
                        'slug'    => 'ai-seo-captain',
                        'first-path' => 'admin.php?page=ai-seo-captain-setup',
                        'support' => false,
                        'account' => true,
                        'contact' => true,
                    ),
                ) );
            }

            return $asc_fs;
        }

        asc_fs();
        do_action( 'asc_fs_loaded' );
    }
    // --- End Freemius ---------------------------------------------------------------

    require_once AI_SEO_CAPTAIN_PATH . 'includes/autoload.php';
    require_once AI_SEO_CAPTAIN_PATH . 'includes/class-activator.php';

    add_action('init', static function () {
        load_plugin_textdomain('ai-seo-captain', false, dirname(plugin_basename(AI_SEO_CAPTAIN_FILE)) . '/languages');
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

        // Clean up cache drop-ins and WP_CACHE constant. (Pro-only — stripped from free.)
        if (asc_fs()->is__premium_only()) {
            AI_SEO_Captain\Cache\Cache_Manager::deactivate();
        }

        flush_rewrite_rules();
    });

    add_action(
        'plugins_loaded',
        static function () {
            // Auto-upgrade DB schema when version changes.
            $db_version = get_option('ai_seo_captain_db_version', '0');
            if (version_compare($db_version, AI_SEO_CAPTAIN_VERSION, '<')) {
                AI_SEO_Captain\Activator::activate();
                update_option('ai_seo_captain_db_version', AI_SEO_CAPTAIN_VERSION);
            }

            AI_SEO_Captain\Plugin::instance()->boot();
        }
    );
} // End else — Freemius auto-deactivation wrapper.
