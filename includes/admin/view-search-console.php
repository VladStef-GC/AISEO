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
?>
<div class="wrap aiseo-gsc-wrap">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <img src="<?php echo esc_url(AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-d.svg'); ?>" alt="SEO Captain" style="width:40px;height:40px;" />
        <h1 style="margin:0;"><?php esc_html_e('Search Console', 'ai-seo-captain'); ?></h1>
    </div>
    <p><?php esc_html_e('Connect your Google Search Console account to view search performance data directly in your WordPress dashboard.', 'ai-seo-captain'); ?></p>

    <?php if ($gsc_notice) : ?>
        <div class="notice notice-<?php echo esc_attr($gsc_notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($gsc_notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (! $has_creds) : ?>
        <!-- ======= Step 1: Enter API Credentials ======= -->
        <div class="aiseo-gsc-card">
            <h2><?php esc_html_e('Step 1: API Credentials', 'ai-seo-captain'); ?></h2>
            <p class="description"><?php esc_html_e('Create a project in the Google Cloud Console, enable the Search Console API, and create OAuth 2.0 credentials. Enter them below.', 'ai-seo-captain'); ?></p>
            <form method="post">
                <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="gsc_client_id"><?php esc_html_e('Client ID', 'ai-seo-captain'); ?></label></th>
                        <td><input type="text" name="gsc_client_id" id="gsc_client_id" class="regular-text" value="" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gsc_client_secret"><?php esc_html_e('Client Secret', 'ai-seo-captain'); ?></label></th>
                        <td><input type="password" name="gsc_client_secret" id="gsc_client_secret" class="regular-text" value="" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect URI', 'ai-seo-captain'); ?></th>
                        <td>
                            <code id="gsc-redirect-uri"><?php echo esc_html($gsc->get_redirect_uri()); ?></code>
                            <button type="button" class="button button-small aiseo-copy-btn" data-copy-target="#gsc-redirect-uri" title="<?php esc_attr_e('Copy', 'ai-seo-captain'); ?>">
                                <span class="dashicons dashicons-clipboard" style="vertical-align:middle;"></span>
                            </button>
                            <p class="description"><?php esc_html_e('Add this URL as an authorized redirect URI in your Google Cloud Console project.', 'ai-seo-captain'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="gsc_save_credentials" class="button button-primary"><?php esc_html_e('Save Credentials', 'ai-seo-captain'); ?></button>
                </p>
            </form>
        </div>

    <?php elseif (! $is_connected) : ?>
        <!-- ======= Step 2: Authorize ======= -->
        <div class="aiseo-gsc-card">
            <h2><?php esc_html_e('Step 2: Authorize', 'ai-seo-captain'); ?></h2>
            <p class="description"><?php esc_html_e('Click the button below to authorize this plugin to access your Search Console data.', 'ai-seo-captain'); ?></p>
            <p>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-hero"><?php esc_html_e('Connect Google Search Console', 'ai-seo-captain'); ?></a>
            </p>
            <hr />
            <form method="post" style="margin-top:12px;">
                <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                <button type="submit" name="gsc_disconnect" class="button button-link-delete"><?php esc_html_e('Remove credentials and start over', 'ai-seo-captain'); ?></button>
            </form>
        </div>

    <?php elseif ('' === $gsc_config['site_url']) : ?>
        <!-- ======= Step 3: Select a site ======= -->
        <div class="aiseo-gsc-card">
            <h2><?php esc_html_e('Step 3: Select Your Site', 'ai-seo-captain'); ?></h2>
            <?php if (is_wp_error($gsc_sites)) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($gsc_sites->get_error_message()); ?></p></div>
            <?php elseif (empty($gsc_sites)) : ?>
                <p><?php esc_html_e('No sites found in your Search Console account. Verify your site is added at search.google.com/search-console.', 'ai-seo-captain'); ?></p>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="gsc_site_url"><?php esc_html_e('Property', 'ai-seo-captain'); ?></label></th>
                            <td>
                                <select name="gsc_site_url" id="gsc_site_url">
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
                        <button type="submit" name="gsc_select_site" class="button button-primary"><?php esc_html_e('Select Site', 'ai-seo-captain'); ?></button>
                    </p>
                </form>
            <?php endif; ?>
            <hr />
            <form method="post" style="margin-top:12px;">
                <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                <button type="submit" name="gsc_disconnect" class="button button-link-delete"><?php esc_html_e('Disconnect', 'ai-seo-captain'); ?></button>
            </form>
        </div>

    <?php else : ?>
        <!-- ======= Connected: Dashboard ======= -->
        <div class="aiseo-gsc-toolbar">
            <div class="aiseo-gsc-toolbar-left">
                <span class="aiseo-gsc-status aiseo-gsc-connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html(sprintf(__('Connected: %s', 'ai-seo-captain'), $gsc_config['site_url'])); ?>
                </span>
            </div>
            <div class="aiseo-gsc-toolbar-right">
                <label for="gsc-start-date"><?php esc_html_e('From', 'ai-seo-captain'); ?></label>
                <input type="date" id="gsc-start-date" value="<?php echo esc_attr($start_date); ?>" />
                <label for="gsc-end-date"><?php esc_html_e('To', 'ai-seo-captain'); ?></label>
                <input type="date" id="gsc-end-date" value="<?php echo esc_attr($end_date); ?>" />
                <button type="button" id="gsc-refresh-btn" class="button"><?php esc_html_e('Refresh', 'ai-seo-captain'); ?></button>
                <button type="button" id="gsc-sync-btn" class="button" title="<?php esc_attr_e('Pull fresh data from Google', 'ai-seo-captain'); ?>">
                    <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                    <?php esc_html_e('Sync Now', 'ai-seo-captain'); ?>
                </button>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('ai_seo_captain_gsc_settings'); ?>
                    <button type="submit" name="gsc_disconnect" class="button button-link-delete"><?php esc_html_e('Disconnect', 'ai-seo-captain'); ?></button>
                </form>
            </div>
        </div>

        <?php if (! empty($gsc_config['last_sync'])) : ?>
            <p class="description aiseo-gsc-last-sync">
                <?php echo esc_html(sprintf(__('Last synced: %s', 'ai-seo-captain'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gsc_config['last_sync'])))); ?>
            </p>
        <?php endif; ?>

        <!-- Overview Cards -->
        <div class="aiseo-gsc-overview" id="gsc-overview">
            <div class="aiseo-gsc-card aiseo-gsc-metric" data-metric="clicks">
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Clicks', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-clicks"><?php echo esc_html(number_format_i18n($overview['clicks'])); ?></span>
            </div>
            <div class="aiseo-gsc-card aiseo-gsc-metric" data-metric="impressions">
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Impressions', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-impressions"><?php echo esc_html(number_format_i18n($overview['impressions'])); ?></span>
            </div>
            <div class="aiseo-gsc-card aiseo-gsc-metric" data-metric="ctr">
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Avg. CTR', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-ctr"><?php echo esc_html(number_format($overview['ctr'] * 100, 1) . '%'); ?></span>
            </div>
            <div class="aiseo-gsc-card aiseo-gsc-metric" data-metric="position">
                <span class="aiseo-gsc-metric-label"><?php esc_html_e('Avg. Position', 'ai-seo-captain'); ?></span>
                <span class="aiseo-gsc-metric-value" id="gsc-val-position"><?php echo esc_html(number_format($overview['position'], 1)); ?></span>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="aiseo-gsc-card aiseo-gsc-chart-wrap">
            <h3><?php esc_html_e('Performance Trend', 'ai-seo-captain'); ?></h3>
            <canvas id="gsc-trend-chart" height="260"></canvas>
        </div>

        <!-- Tables Row -->
        <div class="aiseo-gsc-tables">
            <!-- Top Queries -->
            <div class="aiseo-gsc-card aiseo-gsc-table-card">
                <h3><?php esc_html_e('Top Queries', 'ai-seo-captain'); ?></h3>
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
                        <?php if (empty($top_queries)) : ?>
                            <tr><td colspan="5"><?php esc_html_e('No data yet. Click "Sync Now" to pull data from Google.', 'ai-seo-captain'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($top_queries as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row->dimension_value); ?></td>
                                    <td class="num"><?php echo esc_html(number_format_i18n($row->clicks)); ?></td>
                                    <td class="num"><?php echo esc_html(number_format_i18n($row->impressions)); ?></td>
                                    <td class="num"><?php echo esc_html(number_format($row->ctr * 100, 1) . '%'); ?></td>
                                    <td class="num"><?php echo esc_html(number_format($row->position, 1)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Pages -->
            <div class="aiseo-gsc-card aiseo-gsc-table-card">
                <h3><?php esc_html_e('Top Pages', 'ai-seo-captain'); ?></h3>
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
                        <?php if (empty($top_pages)) : ?>
                            <tr><td colspan="5"><?php esc_html_e('No data yet. Click "Sync Now" to pull data from Google.', 'ai-seo-captain'); ?></td></tr>
                        <?php else : ?>
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
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
