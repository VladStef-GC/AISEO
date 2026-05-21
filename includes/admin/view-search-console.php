<?php

/**
 * Google Search Console page view.
 *
 * Variables available (set by Admin::render_search_console_page):
 *   $gsc, $gsc_config, $is_connected, $has_creds, $auth_url,
 *   $gsc_sites, $gsc_notice, $overview, $top_queries, $top_pages,
 *   $trend_data, $start_date, $end_date
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var \AI_SEO_Captain\Search_Console $gsc */
/** @var array  $gsc_config */
/** @var bool   $is_connected */
/** @var bool   $has_creds */
/** @var string $auth_url */
/** @var array  $gsc_sites */
/** @var array|null $gsc_notice */
/** @var array  $overview */
/** @var array  $top_queries */
/** @var array  $top_pages */
/** @var array  $trend_data */
/** @var string $start_date */
/** @var string $end_date */

$redirect_uri = $gsc->get_redirect_uri();
?>
<div class="wrap aiseo-gsc-wrap">
    <div class="aiseo-gsc-header">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" class="aiseo-gsc-logo" />
        <div>
            <h1><?php esc_html_e('Search Console', 'ai-seo-captain'); ?></h1>
            <p class="aiseo-gsc-subtitle"><?php esc_html_e('Connect your Google Search Console account to view search performance data directly in your WordPress dashboard.', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <?php if ($gsc_notice) : ?>
        <div class="notice notice-<?php echo esc_attr($gsc_notice['type']); ?> is-dismissible aiseo-gsc-notice">
            <p>
                <?php if ('success' === $gsc_notice['type']) : ?>
                    <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
                <?php else : ?>
                    <span class="dashicons dashicons-warning" style="color:#d63638;"></span>
                <?php endif; ?>
                <?php echo esc_html($gsc_notice['message']); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (! $has_creds) : ?>
        <!-- ======= Step 1: Enter API Credentials ======= -->
        <div class="aiseo-gsc-setup">
            <div class="aiseo-gsc-steps">
                <div class="aiseo-gsc-step active"><span class="aiseo-gsc-step-num">1</span> <?php esc_html_e('Credentials', 'ai-seo-captain'); ?></div>
                <div class="aiseo-gsc-step-divider"></div>
                <div class="aiseo-gsc-step"><span class="aiseo-gsc-step-num">2</span> <?php esc_html_e('Authorize', 'ai-seo-captain'); ?></div>
                <div class="aiseo-gsc-step-divider"></div>
                <div class="aiseo-gsc-step"><span class="aiseo-gsc-step-num">3</span> <?php esc_html_e('Select Site', 'ai-seo-captain'); ?></div>
            </div>

            <div class="aiseo-gsc-card aiseo-gsc-card-setup">
                <div class="aiseo-gsc-card-header">
                    <span class="dashicons dashicons-admin-network" style="font-size:24px;color:#2271b1;"></span>
                    <h2><?php esc_html_e('API Credentials', 'ai-seo-captain'); ?></h2>
                </div>
                <div class="aiseo-gsc-instructions">
                    <p><?php esc_html_e('To connect, you need Google Cloud OAuth credentials:', 'ai-seo-captain'); ?></p>
                    <ol>
                        <li><?php
                            printf(
                                /* translators: %s: link to Google Cloud Console */
                                esc_html__('Go to %s and create a project (or select an existing one).', 'ai-seo-captain'),
                                '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a>'
                            );
                        ?></li>
                        <li><?php esc_html_e('Enable the "Google Search Console API" for your project.', 'ai-seo-captain'); ?></li>
                        <li><?php esc_html_e('Create OAuth 2.0 credentials (Web Application type).', 'ai-seo-captain'); ?></li>
                        <li><?php esc_html_e('Add the Redirect URI shown below to your authorized redirect URIs.', 'ai-seo-captain'); ?></li>
                    </ol>
                </div>
                <form method="post">
                    <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="gsc_client_id"><?php esc_html_e('Client ID', 'ai-seo-captain'); ?></label></th>
                            <td><input type="text" name="gsc_client_id" id="gsc_client_id" class="regular-text" value="" autocomplete="off" placeholder="xxxx.apps.googleusercontent.com" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gsc_client_secret"><?php esc_html_e('Client Secret', 'ai-seo-captain'); ?></label></th>
                            <td><input type="password" name="gsc_client_secret" id="gsc_client_secret" class="regular-text" value="" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Redirect URI', 'ai-seo-captain'); ?></th>
                            <td>
                                <div class="aiseo-gsc-uri-row">
                                    <code id="gsc-redirect-uri"><?php echo esc_html($redirect_uri); ?></code>
                                    <button type="button" class="button button-small aiseo-copy-btn" data-copy-target="#gsc-redirect-uri" title="<?php esc_attr_e('Copy to clipboard', 'ai-seo-captain'); ?>">
                                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy', 'ai-seo-captain'); ?>
                                    </button>
                                </div>
                                <p class="description"><?php esc_html_e('Add this exact URL as an authorized redirect URI in your Google Cloud Console project.', 'ai-seo-captain'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="gsc_save_credentials" class="button button-primary button-hero"><?php esc_html_e('Save Credentials & Continue', 'ai-seo-captain'); ?></button>
                    </p>
                </form>
            </div>
        </div>

    <?php elseif (! $is_connected) : ?>
        <!-- ======= Step 2: Authorize ======= -->
        <div class="aiseo-gsc-setup">
            <div class="aiseo-gsc-steps">
                <div class="aiseo-gsc-step done"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Credentials', 'ai-seo-captain'); ?></div>
                <div class="aiseo-gsc-step-divider done"></div>
                <div class="aiseo-gsc-step active"><span class="aiseo-gsc-step-num">2</span> <?php esc_html_e('Authorize', 'ai-seo-captain'); ?></div>
                <div class="aiseo-gsc-step-divider"></div>
                <div class="aiseo-gsc-step"><span class="aiseo-gsc-step-num">3</span> <?php esc_html_e('Select Site', 'ai-seo-captain'); ?></div>
            </div>

            <div class="aiseo-gsc-card aiseo-gsc-card-setup" style="text-align:center;">
                <div class="aiseo-gsc-card-header" style="justify-content:center;">
                    <span class="dashicons dashicons-shield" style="font-size:48px;color:#2271b1;margin-bottom:12px;"></span>
                </div>
                <h2><?php esc_html_e('Authorize Access', 'ai-seo-captain'); ?></h2>
                <p class="description" style="max-width:500px;margin:0 auto 20px;"><?php esc_html_e('Click below to sign in with Google and grant read-only access to your Search Console data. You will be redirected to Google and back.', 'ai-seo-captain'); ?></p>
                <p>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-google" style="vertical-align:middle;margin-right:4px;"></span>
                        <?php esc_html_e('Connect Google Search Console', 'ai-seo-captain'); ?>
                    </a>
                </p>
                <hr style="margin-top:24px;" />
                <form method="post" style="margin-top:12px;">
                    <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                    <button type="submit" name="gsc_disconnect" class="button button-link-delete"><?php esc_html_e('Remove credentials and start over', 'ai-seo-captain'); ?></button>
                </form>
            </div>
        </div>

    <?php elseif ('' === $gsc_config['site_url']) : ?>
        <!-- ======= Step 3: Select a site ======= -->
        <div class="aiseo-gsc-setup">
            <div class="aiseo-gsc-steps">
                <div class="aiseo-gsc-step done"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Credentials', 'ai-seo-captain'); ?></div>
                <div class="aiseo-gsc-step-divider done"></div>
                <div class="aiseo-gsc-step done"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Authorize', 'ai-seo-captain'); ?></div>
                <div class="aiseo-gsc-step-divider done"></div>
                <div class="aiseo-gsc-step active"><span class="aiseo-gsc-step-num">3</span> <?php esc_html_e('Select Site', 'ai-seo-captain'); ?></div>
            </div>

            <div class="aiseo-gsc-card aiseo-gsc-card-setup">
                <div class="aiseo-gsc-card-header">
                    <span class="dashicons dashicons-admin-site-alt3" style="font-size:24px;color:#2271b1;"></span>
                    <h2><?php esc_html_e('Select Your Property', 'ai-seo-captain'); ?></h2>
                </div>
                <?php if (is_wp_error($gsc_sites)) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html($gsc_sites->get_error_message()); ?></p></div>
                <?php elseif (empty($gsc_sites)) : ?>
                    <p><?php esc_html_e('No sites found in your Search Console account. Make sure your site is verified at search.google.com/search-console.', 'ai-seo-captain'); ?></p>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="gsc_site_url"><?php esc_html_e('Property', 'ai-seo-captain'); ?></label></th>
                                <td>
                                    <select name="gsc_site_url" id="gsc_site_url" class="regular-text">
                                        <?php foreach ($gsc_sites as $site) : ?>
                                            <option value="<?php echo esc_attr($site['siteUrl']); ?>">
                                                <?php echo esc_html($site['siteUrl']); ?>
                                                (<?php echo esc_html($site['permissionLevel'] ?? ''); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" name="gsc_select_site" class="button button-primary button-hero"><?php esc_html_e('Select & Finish Setup', 'ai-seo-captain'); ?></button>
                        </p>
                    </form>
                <?php endif; ?>
                <hr />
                <form method="post" style="margin-top:12px;">
                    <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                    <button type="submit" name="gsc_disconnect" class="button button-link-delete"><?php esc_html_e('Disconnect', 'ai-seo-captain'); ?></button>
                </form>
            </div>
        </div>

    <?php else : ?>
        <!-- ======= Connected: Dashboard ======= -->

        <!-- Connection status bar -->
        <div class="aiseo-gsc-toolbar">
            <div class="aiseo-gsc-toolbar-left">
                <span class="aiseo-gsc-status aiseo-gsc-connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html(sprintf(__('Connected: %s', 'ai-seo-captain'), $gsc_config['site_url'])); ?>
                </span>
                <?php if (! empty($gsc_config['last_sync'])) : ?>
                    <span class="aiseo-gsc-last-sync">
                        <?php echo esc_html(sprintf(
                            __('Last sync: %s', 'ai-seo-captain'),
                            wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gsc_config['last_sync']))
                        )); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="aiseo-gsc-toolbar-right">
                <label for="gsc-start-date"><?php esc_html_e('From', 'ai-seo-captain'); ?></label>
                <input type="date" id="gsc-start-date" value="<?php echo esc_attr($start_date); ?>" />
                <label for="gsc-end-date"><?php esc_html_e('To', 'ai-seo-captain'); ?></label>
                <input type="date" id="gsc-end-date" value="<?php echo esc_attr($end_date); ?>" />
                <button type="button" id="gsc-refresh-btn" class="button button-primary">
                    <span class="dashicons dashicons-image-rotate" style="vertical-align:middle;"></span>
                    <?php esc_html_e('Apply', 'ai-seo-captain'); ?>
                </button>
                <button type="button" id="gsc-sync-btn" class="button" title="<?php esc_attr_e('Pull fresh data from Google', 'ai-seo-captain'); ?>">
                    <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                    <?php esc_html_e('Sync Now', 'ai-seo-captain'); ?>
                </button>
                <button type="button" id="gsc-test-btn" class="button" title="<?php esc_attr_e('Test connection to Google', 'ai-seo-captain'); ?>">
                    <span class="dashicons dashicons-admin-plugins" style="vertical-align:middle;"></span>
                    <?php esc_html_e('Test', 'ai-seo-captain'); ?>
                </button>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                    <button type="submit" name="gsc_disconnect" class="button button-link-delete"><?php esc_html_e('Disconnect', 'ai-seo-captain'); ?></button>
                </form>
            </div>
        </div>

        <!-- AJAX result banner (populated by JS) -->
        <div id="gsc-ajax-notice" class="notice aiseo-gsc-notice" style="display:none;" role="alert">
            <p id="gsc-ajax-notice-msg"></p>
        </div>

        <!-- Overview Cards -->
        <div class="aiseo-gsc-overview" id="gsc-overview">
            <div class="aiseo-gsc-card aiseo-gsc-metric active" data-metric="clicks">
                <div class="aiseo-gsc-metric-icon"><span class="dashicons dashicons-admin-links"></span></div>
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Total Clicks', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-clicks"><?php echo esc_html(number_format_i18n($overview['clicks'])); ?></span>
            </div>
            <div class="aiseo-gsc-card aiseo-gsc-metric active" data-metric="impressions">
                <div class="aiseo-gsc-metric-icon"><span class="dashicons dashicons-visibility"></span></div>
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Total Impressions', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-impressions"><?php echo esc_html(number_format_i18n($overview['impressions'])); ?></span>
            </div>
            <div class="aiseo-gsc-card aiseo-gsc-metric" data-metric="ctr">
                <div class="aiseo-gsc-metric-icon"><span class="dashicons dashicons-chart-line"></span></div>
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Average CTR', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-ctr"><?php echo esc_html(number_format($overview['ctr'] * 100, 1) . '%'); ?></span>
            </div>
            <div class="aiseo-gsc-card aiseo-gsc-metric" data-metric="position">
                <div class="aiseo-gsc-metric-icon"><span class="dashicons dashicons-sort"></span></div>
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Avg. Position', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-position"><?php echo esc_html(number_format($overview['position'], 1)); ?></span>
            </div>
        </div>

        <?php if (empty($top_queries) && empty($top_pages)) : ?>
            <div class="aiseo-gsc-card aiseo-gsc-empty-state">
                <span class="dashicons dashicons-chart-area" style="font-size:48px;color:#c3c4c7;display:block;text-align:center;margin-bottom:12px;"></span>
                <h3 style="text-align:center;margin:0 0 8px;"><?php esc_html_e('No search data yet', 'ai-seo-captain'); ?></h3>
                <p style="text-align:center;color:#646970;max-width:450px;margin:0 auto;">
                    <?php esc_html_e('Click "Sync Now" above to pull your latest search performance data from Google. Data usually has a 2-3 day delay.', 'ai-seo-captain'); ?>
                </p>
            </div>
        <?php else : ?>
            <!-- Trend Chart -->
            <div class="aiseo-gsc-card aiseo-gsc-chart-wrap">
                <h3><?php esc_html_e('Performance Trend', 'ai-seo-captain'); ?></h3>
                <canvas id="gsc-trend-chart" height="260"></canvas>
            </div>

            <!-- Tables Row -->
            <div class="aiseo-gsc-tables">
                <!-- Top Queries -->
                <div class="aiseo-gsc-card aiseo-gsc-table-card">
                    <h3>
                        <span class="dashicons dashicons-search" style="vertical-align:middle;margin-right:4px;"></span>
                        <?php esc_html_e('Top Queries', 'ai-seo-captain'); ?>
                        <span class="aiseo-gsc-badge"><?php echo esc_html(count($top_queries)); ?></span>
                    </h3>
                    <table class="widefat striped" id="gsc-table-queries">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Query', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('Clicks', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('Impressions', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('CTR', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('Position', 'ai-seo-captain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_queries as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row->dimension_value); ?></td>
                                    <td class="num"><?php echo esc_html(number_format_i18n($row->clicks)); ?></td>
                                    <td class="num"><?php echo esc_html(number_format_i18n($row->impressions)); ?></td>
                                    <td class="num"><?php echo esc_html(number_format($row->ctr * 100, 1) . '%'); ?></td>
                                    <td class="num"><?php echo esc_html(number_format($row->position, 1)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Top Pages -->
                <div class="aiseo-gsc-card aiseo-gsc-table-card">
                    <h3>
                        <span class="dashicons dashicons-admin-page" style="vertical-align:middle;margin-right:4px;"></span>
                        <?php esc_html_e('Top Pages', 'ai-seo-captain'); ?>
                        <span class="aiseo-gsc-badge"><?php echo esc_html(count($top_pages)); ?></span>
                    </h3>
                    <table class="widefat striped" id="gsc-table-pages">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Page', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('Clicks', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('Impressions', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('CTR', 'ai-seo-captain'); ?></th>
                                <th class="num"><?php esc_html_e('Position', 'ai-seo-captain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_pages as $row) : ?>
                                <tr>
                                    <td title="<?php echo esc_attr($row->dimension_value); ?>">
                                        <?php
                                        $path = wp_parse_url($row->dimension_value, PHP_URL_PATH);
                                        echo esc_html($path ?: $row->dimension_value);
                                        ?>
                                    </td>
                                    <td class="num"><?php echo esc_html(number_format_i18n($row->clicks)); ?></td>
                                    <td class="num"><?php echo esc_html(number_format_i18n($row->impressions)); ?></td>
                                    <td class="num"><?php echo esc_html(number_format($row->ctr * 100, 1) . '%'); ?></td>
                                    <td class="num"><?php echo esc_html(number_format($row->position, 1)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>