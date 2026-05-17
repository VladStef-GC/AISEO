<?php

/**
 * Cache page view.
 *
 * Variables available (set by Admin::render_cache_page):
 *   $options, $cache_status, $readiness_banner, $wc_active
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

use AI_SEO_Captain\Settings;

/** @var array  $options */
/** @var array  $cache_status */
/** @var string $readiness_banner */
/** @var bool   $wc_active */

$nonce         = wp_create_nonce('aisc_cache_nonce');
$cache_enabled = ! empty($options['cache_enabled']);
$page_files    = isset($cache_status['page_cache_files']) ? (int) $cache_status['page_cache_files'] : 0;
$page_size     = isset($cache_status['page_cache_size']) ? (int) $cache_status['page_cache_size'] : 0;
$last_purge    = isset($cache_status['last_purge']) ? (int) $cache_status['last_purge'] : 0;
$ac_installed  = ! empty($cache_status['advanced_cache']);
$oc_installed  = ! empty($cache_status['object_cache_dropin']);
$wp_cache_on   = ! empty($cache_status['wp_cache_constant']);
$htaccess_on   = ! empty($cache_status['htaccess_installed']);

$option_name = Settings::OPTION_NAME;
?>
<div class="wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_KEEPER_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Cache & Performance', 'ai-seo-captain'); ?></h1>
    </div>
    <p class="description"><?php esc_html_e('Full page cache, browser cache, minification, and lazy loading to speed up your site and improve Core Web Vitals.', 'ai-seo-captain'); ?></p>

    <?php echo $readiness_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>

    <!-- AJAX feedback banner (inserted dynamically by JS) -->
    <div id="aisc-cache-banner-area"></div>

    <?php
    // Show settings-saved flash message after redirect.
    $settings_status = isset($_GET['settings-updated']) ? 'success' : '';
    if ('success' === $settings_status) :
        echo \AI_SEO_Captain\Admin::render_banner(
            'is-success',
            esc_html__('Settings saved', 'ai-seo-captain'),
            esc_html__('Cache settings have been saved successfully.', 'ai-seo-captain'),
            true
        );
    endif;
    ?>

    <!-- Status Dashboard -->
    <div class="aisc-cache-dashboard" id="aisc-cache-dashboard">
        <div class="aisc-cache-card aisc-cache-card--status">
            <span class="aisc-cache-card__label"><?php esc_html_e('Cache Status', 'ai-seo-captain'); ?></span>
            <span class="aisc-cache-card__value" id="aisc-cache-status-indicator">
                <?php if ($cache_enabled) : ?>
                    <span class="aisc-badge aisc-badge--active"><?php esc_html_e('Active', 'ai-seo-captain'); ?></span>
                <?php else : ?>
                    <span class="aisc-badge aisc-badge--inactive"><?php esc_html_e('Inactive', 'ai-seo-captain'); ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="aisc-cache-card">
            <span class="aisc-cache-card__label"><?php esc_html_e('Cached Pages', 'ai-seo-captain'); ?></span>
            <span class="aisc-cache-card__value" id="aisc-cached-pages"><?php echo (int) $page_files; ?> <?php esc_html_e('files', 'ai-seo-captain'); ?></span>
            <span class="aisc-cache-card__sub" id="aisc-cached-size"><?php echo esc_html(size_format($page_size)); ?></span>
        </div>
        <div class="aisc-cache-card">
            <span class="aisc-cache-card__label"><?php esc_html_e('Drop-ins', 'ai-seo-captain'); ?></span>
            <span class="aisc-cache-card__value" id="aisc-dropins-status">
                advanced-cache.php <span id="aisc-ac-check"><?php echo $ac_installed ? '✓' : '✗'; ?></span><br>
                object-cache.php <span id="aisc-oc-check"><?php echo $oc_installed ? '✓' : '✗'; ?></span>
            </span>
            <span class="aisc-cache-card__sub">WP_CACHE: <span id="aisc-wpcache-check"><?php echo $wp_cache_on ? '✓ Enabled' : '✗ Disabled'; ?></span></span>
        </div>
        <div class="aisc-cache-card">
            <span class="aisc-cache-card__label"><?php esc_html_e('Last Full Purge', 'ai-seo-captain'); ?></span>
            <span class="aisc-cache-card__value" id="aisc-last-purge">
                <?php echo $last_purge > 0 ? esc_html(human_time_diff($last_purge) . ' ' . __('ago', 'ai-seo-captain')) : '—'; ?>
            </span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="aisc-cache-actions">
        <button type="button" class="button button-primary" id="aisc-purge-all-btn">
            <span class="dashicons dashicons-trash" style="margin-top:3px;margin-right:4px;"></span>
            <?php esc_html_e('Purge All Cache', 'ai-seo-captain'); ?>
        </button>
        <button type="button" class="button" id="aisc-preload-btn">
            <span class="dashicons dashicons-update" style="margin-top:3px;margin-right:4px;"></span>
            <?php esc_html_e('Preload Cache', 'ai-seo-captain'); ?>
        </button>
        <span id="aisc-cache-action-feedback" class="aisc-cache-feedback"></span>
    </div>

    <!-- Preload Progress Bar (hidden by default) -->
    <div class="aisc-preload-progress" id="aisc-preload-progress" style="display:none;">
        <div class="aisc-preload-progress__bar">
            <div class="aisc-preload-progress__fill" id="aisc-preload-fill" style="width:0%"></div>
        </div>
        <span class="aisc-preload-progress__text" id="aisc-preload-text">0 / 0</span>
    </div>

    <!-- Settings Form -->
    <form method="post" action="options.php" id="aisc-cache-form">
        <?php settings_fields('ai_seo_captain_settings'); ?>
        <input type="hidden" name="<?php echo esc_attr($option_name); ?>[_save_source]" value="cache">

        <div class="ai-seo-accordion">

            <!-- Section 1: Page Cache -->
            <div class="ai-seo-accordion-section is-open">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Page Cache', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('Cache full HTML pages to serve them without loading WordPress. Dramatically reduces server response time.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Cache', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_enabled]" value="1" <?php checked(! empty($options['cache_enabled'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Enable the caching system.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Page Cache', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_page_enabled]" value="1" <?php checked(! empty($options['cache_page_enabled'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Cache full HTML pages to disk.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aisc-page-ttl-hours"><?php esc_html_e('Cache TTL', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <?php
                                $ttl_seconds = (int) ($options['cache_page_ttl'] ?? 86400);
                                $ttl_hours   = (int) floor($ttl_seconds / 3600);
                                $ttl_minutes = (int) floor(($ttl_seconds % 3600) / 60);
                                ?>
                                <input type="hidden" id="aisc-page-ttl" name="<?php echo esc_attr($option_name); ?>[cache_page_ttl]" value="<?php echo $ttl_seconds; ?>">
                                <input type="number" id="aisc-page-ttl-hours" value="<?php echo $ttl_hours; ?>" min="0" max="168" style="width:70px;"> <?php esc_html_e('hours', 'ai-seo-captain'); ?>
                                <input type="number" id="aisc-page-ttl-minutes" value="<?php echo $ttl_minutes; ?>" min="0" max="59" style="width:70px; margin-left:8px;"> <?php esc_html_e('minutes', 'ai-seo-captain'); ?>
                                <p class="description"><?php esc_html_e('How long cached pages are valid before they expire. Default: 24 hours. Min: 5 minutes, Max: 7 days.', 'ai-seo-captain'); ?></p>
                                <script>
                                    (function() {
                                        var h = document.getElementById('aisc-page-ttl-hours');
                                        var m = document.getElementById('aisc-page-ttl-minutes');
                                        var hidden = document.getElementById('aisc-page-ttl');

                                        function sync() {
                                            hidden.value = (parseInt(h.value, 10) || 0) * 3600 + (parseInt(m.value, 10) || 0) * 60;
                                        }
                                        h.addEventListener('input', sync);
                                        m.addEventListener('input', sync);
                                    })();
                                </script>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('GZIP Compression', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_gzip_enabled]" value="1" <?php checked(! empty($options['cache_gzip_enabled'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Enable PHP-level GZIP compression for cached responses.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Cache Query Strings', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_query_string_cache]" value="1" <?php checked(! empty($options['cache_query_string_cache'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Also cache pages with query strings (e.g. ?utm_source=...).', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:12px;padding:10px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;">
                        <strong><?php esc_html_e('Advanced Cache Drop-in', 'ai-seo-captain'); ?></strong>
                        <p class="description" style="margin:4px 0 8px;"><?php esc_html_e('Install advanced-cache.php to wp-content/ so cached pages are served before WordPress fully loads. This also sets the WP_CACHE constant in wp-config.php.', 'ai-seo-captain'); ?></p>
                        <span id="aisc-ac-status" class="aisc-badge <?php echo $ac_installed ? 'aisc-badge--active' : 'aisc-badge--inactive'; ?>" style="margin-right:8px;">
                            <?php echo $ac_installed ? esc_html__('Installed', 'ai-seo-captain') : esc_html__('Not Installed', 'ai-seo-captain'); ?>
                        </span>
                        <button type="button" class="button button-small button-primary" id="aisc-install-ac" <?php echo $ac_installed ? 'style="display:none;"' : ''; ?>>
                            <?php esc_html_e('Install Drop-in', 'ai-seo-captain'); ?>
                        </button>
                        <button type="button" class="button button-small" id="aisc-remove-ac" <?php echo ! $ac_installed ? 'style="display:none;"' : ''; ?>>
                            <?php esc_html_e('Remove Drop-in', 'ai-seo-captain'); ?>
                        </button>
                        <p class="description" style="margin-top:6px;"><?php esc_html_e('A backup of wp-config.php is created automatically before the WP_CACHE constant is changed.', 'ai-seo-captain'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Section 2: Browser Cache -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Browser Cache', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('Set HTTP headers and .htaccess rules so browsers cache static assets locally.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Browser Cache Headers', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_browser_enabled]" value="1" <?php checked(! empty($options['cache_browser_enabled'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Send Cache-Control, Expires, and ETag headers.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('.htaccess Rules', 'ai-seo-captain'); ?></th>
                            <td>
                                <span class="aisc-badge <?php echo $htaccess_on ? 'aisc-badge--active' : 'aisc-badge--inactive'; ?>" id="aisc-htaccess-status">
                                    <?php echo $htaccess_on ? esc_html__('Installed', 'ai-seo-captain') : esc_html__('Not Installed', 'ai-seo-captain'); ?>
                                </span>
                                <button type="button" class="button button-small" id="aisc-install-htaccess" <?php echo $htaccess_on ? 'style="display:none;"' : ''; ?>>
                                    <?php esc_html_e('Install Rules', 'ai-seo-captain'); ?>
                                </button>
                                <button type="button" class="button button-small" id="aisc-remove-htaccess" <?php echo $htaccess_on ? '' : 'style="display:none;"'; ?>>
                                    <?php esc_html_e('Remove Rules', 'ai-seo-captain'); ?>
                                </button>
                                <button type="button" class="button button-small" id="aisc-restore-htaccess" style="margin-left:8px;" title="<?php esc_attr_e('Restore .htaccess from the most recent backup', 'ai-seo-captain'); ?>">
                                    <span class="dashicons dashicons-backup" style="font-size:14px;line-height:1.8;margin-right:2px;"></span>
                                    <?php esc_html_e('Restore Backup', 'ai-seo-captain'); ?>
                                </button>
                                <p class="description" style="margin-top:6px;">
                                    <?php esc_html_e('A backup of .htaccess is created automatically before every change. Up to 5 backups are kept.', 'ai-seo-captain'); ?>
                                    <br>
                                    <?php
                                    printf(
                                        /* translators: %s: backup directory path */
                                        esc_html__('Backups are stored in: %s', 'ai-seo-captain'),
                                        '<code>wp-content/cache/ai-seo-captain/backups/</code>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 3: Object Cache -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Object Cache', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('File-based object cache for transients and database query results. Reduces database load.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Object Cache', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_object_enabled]" value="1" <?php checked(! empty($options['cache_object_enabled'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Enable file-based object cache.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Drop-in Status', 'ai-seo-captain'); ?></th>
                            <td>
                                <span class="aisc-badge <?php echo $oc_installed ? 'aisc-badge--active' : 'aisc-badge--inactive'; ?>" id="aisc-oc-status">
                                    <?php echo $oc_installed ? esc_html__('Installed (ours)', 'ai-seo-captain') : esc_html__('Not Installed', 'ai-seo-captain'); ?>
                                </span>
                                <?php
                                $oc_exists = file_exists(WP_CONTENT_DIR . '/object-cache.php');
                                if ($oc_exists && ! $oc_installed) :
                                ?>
                                    <p class="description" style="color:var(--aisc-warning);"><?php esc_html_e('Another object-cache.php drop-in is already installed. SEO Captain will not overwrite it.', 'ai-seo-captain'); ?></p>
                                <?php else : ?>
                                    <button type="button" class="button button-small" id="aisc-install-oc" <?php echo $oc_installed ? 'style="display:none;"' : ''; ?>>
                                        <?php esc_html_e('Install Drop-in', 'ai-seo-captain'); ?>
                                    </button>
                                    <button type="button" class="button button-small" id="aisc-remove-oc" <?php echo $oc_installed ? '' : 'style="display:none;"'; ?>>
                                        <?php esc_html_e('Remove Drop-in', 'ai-seo-captain'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 4: Minification -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Minification', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('Reduce file sizes by removing unnecessary whitespace and comments from CSS, JS, and HTML output.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Minify CSS', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_minify_css]" value="1" <?php checked(! empty($options['cache_minify_css'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Minify inline CSS in the HTML output.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Minify JavaScript', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_minify_js]" value="1" <?php checked(! empty($options['cache_minify_js'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Minify inline JavaScript (conservative — safe for most sites).', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Minify HTML', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_minify_html]" value="1" <?php checked(! empty($options['cache_minify_html'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Strip whitespace from cached HTML output.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 5: Lazy Loading -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Lazy Loading', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('Defer loading of images and iframes until they are scrolled into view. Improves initial page load.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Lazy Loading', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_lazy_load]" value="1" <?php checked(! empty($options['cache_lazy_load'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Add native loading="lazy" to images and iframes.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aisc-lazy-skip"><?php esc_html_e('Skip First N Images', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <input type="number" id="aisc-lazy-skip" name="<?php echo esc_attr($option_name); ?>[cache_lazy_skip_count]" value="<?php echo (int) ($options['cache_lazy_skip_count'] ?? 2); ?>" min="0" max="10" class="small-text">
                                <p class="description"><?php esc_html_e('Number of above-the-fold images to skip. Default: 2.', 'ai-seo-captain'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 6: Exclusions -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Exclusions', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('Specify URLs, cookies, or user agents that should never be cached.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="aisc-exclude-urls"><?php esc_html_e('Exclude URL Patterns', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <textarea id="aisc-exclude-urls" name="<?php echo esc_attr($option_name); ?>[cache_exclude_urls]" rows="4" class="large-text"><?php echo esc_textarea($options['cache_exclude_urls'] ?? ''); ?></textarea>
                                <p class="description"><?php esc_html_e('One URL pattern per line. Pages matching any pattern will not be cached.', 'ai-seo-captain'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aisc-exclude-cookies"><?php esc_html_e('Exclude Cookies', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <textarea id="aisc-exclude-cookies" name="<?php echo esc_attr($option_name); ?>[cache_exclude_cookies]" rows="3" class="large-text"><?php echo esc_textarea($options['cache_exclude_cookies'] ?? ''); ?></textarea>
                                <p class="description"><?php esc_html_e('One cookie name per line. Requests with these cookies will not be cached.', 'ai-seo-captain'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aisc-exclude-ua"><?php esc_html_e('Exclude User Agents', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <textarea id="aisc-exclude-ua" name="<?php echo esc_attr($option_name); ?>[cache_exclude_useragents]" rows="3" class="large-text"><?php echo esc_textarea($options['cache_exclude_useragents'] ?? ''); ?></textarea>
                                <p class="description"><?php esc_html_e('One user-agent pattern per line. Matching requests will not be cached.', 'ai-seo-captain'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 7: WooCommerce (conditional) -->
            <?php if ($wc_active) : ?>
                <div class="ai-seo-accordion-section">
                    <div class="ai-seo-accordion-header">
                        <h2><?php esc_html_e('WooCommerce', 'ai-seo-captain'); ?></h2>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    <div class="ai-seo-accordion-body">
                        <p class="description"><?php esc_html_e('Automatically exclude dynamic WooCommerce pages from the cache.', 'ai-seo-captain'); ?></p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Exclude Cart & Checkout', 'ai-seo-captain'); ?></th>
                                <td>
                                    <label class="aisc-toggle">
                                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_wc_exclude_cart]" value="1" <?php checked(! empty($options['cache_wc_exclude_cart'])); ?>>
                                        <span class="aisc-toggle__track"></span>
                                        <span class="aisc-toggle__label"><?php esc_html_e('Never cache Cart, Checkout, and My Account pages.', 'ai-seo-captain'); ?></span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section 8: Preloading -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('Preloading', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <p class="description"><?php esc_html_e('Automatically warm the cache after purging by crawling your sitemap URLs.', 'ai-seo-captain'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Auto-Preload', 'ai-seo-captain'); ?></th>
                            <td>
                                <label class="aisc-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[cache_preload_enabled]" value="1" <?php checked(! empty($options['cache_preload_enabled'])); ?>>
                                    <span class="aisc-toggle__track"></span>
                                    <span class="aisc-toggle__label"><?php esc_html_e('Preload purged URLs automatically.', 'ai-seo-captain'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aisc-preload-batch"><?php esc_html_e('Batch Size', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <input type="number" id="aisc-preload-batch" name="<?php echo esc_attr($option_name); ?>[cache_preload_batch_size]" value="<?php echo (int) ($options['cache_preload_batch_size'] ?? 5); ?>" min="1" max="50" class="small-text">
                                <p class="description"><?php esc_html_e('Number of URLs to preload per cron tick. Default: 5.', 'ai-seo-captain'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 9: CDN Integration -->
            <div class="ai-seo-accordion-section">
                <div class="ai-seo-accordion-header">
                    <h2><?php esc_html_e('CDN Integration', 'ai-seo-captain'); ?></h2>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="ai-seo-accordion-body">
                    <?php
                    echo \AI_SEO_Captain\Admin::render_banner(
                        'is-info',
                        esc_html__('CDN Integration', 'ai-seo-captain'),
                        esc_html__('Coming soon in a future update. Currently supports local file-based caching only.', 'ai-seo-captain')
                    );
                    ?>
                </div>
            </div>

        </div><!-- .ai-seo-accordion -->

        <?php submit_button(__('Save Cache Settings', 'ai-seo-captain')); ?>

    </form>
</div>

<script>
    var aiscCacheData = {
        nonce: '<?php echo esc_js($nonce); ?>',
        ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>'
    };
    var AI_SEO_KEEPER_URL = '<?php echo esc_js(AI_SEO_KEEPER_URL); ?>';
</script>