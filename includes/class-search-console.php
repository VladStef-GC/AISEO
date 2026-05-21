<?php

namespace AI_SEO_Captain;

/**
 * Google Search Console integration.
 *
 * Handles OAuth 2.0 authorization, token management, and Search Analytics API queries.
 * Data is cached in a dedicated DB table and refreshed daily via cron.
 */
class Search_Console
{
    /** Google OAuth endpoints. */
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Google Search Console API. */
    private const API_BASE = 'https://www.googleapis.com/webmasters/v3';

    /** OAuth scope — read-only access to Search Console data. */
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /** Option key for GSC credentials and tokens. */
    public const OPTION_KEY = 'ai_seo_captain_gsc';

    /** DB table name suffix. */
    public const TABLE_SUFFIX = 'ai_seo_captain_search_analytics';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    // ------------------------------------------------------------------
    //  OAuth credential helpers
    // ------------------------------------------------------------------

    /**
     * Get stored GSC data (client_id, client_secret, tokens, site_url, etc.).
     *
     * @return array{client_id: string, client_secret: string, access_token: string, refresh_token: string, token_expires: int, site_url: string, last_sync: string}
     */
    public function get_config(): array
    {
        $defaults = array(
            'client_id'     => '',
            'client_secret' => '',
            'access_token'  => '',
            'refresh_token' => '',
            'token_expires' => 0,
            'site_url'      => '',
            'last_sync'     => '',
        );

        $stored = get_option(self::OPTION_KEY, array());

        return wp_parse_args(is_array($stored) ? $stored : array(), $defaults);
    }

    /**
     * Save GSC config (partial merge).
     */
    public function save_config(array $data): void
    {
        $current = $this->get_config();
        update_option(self::OPTION_KEY, array_merge($current, $data));
    }

    /**
     * Whether OAuth credentials are configured (client ID + secret).
     */
    public function has_credentials(): bool
    {
        $cfg = $this->get_config();
        return '' !== $cfg['client_id'] && '' !== $cfg['client_secret'];
    }

    /**
     * Whether we have a valid (or refreshable) access token.
     */
    public function is_connected(): bool
    {
        $cfg = $this->get_config();
        return '' !== $cfg['refresh_token'];
    }

    // ------------------------------------------------------------------
    //  OAuth flow
    // ------------------------------------------------------------------

    /**
     * Build the Google OAuth authorization URL.
     *
     * The redirect_uri points to the plugin's admin page with an `oauth_callback` param
     * so we can capture the authorization code.
     */
    public function get_auth_url(): string
    {
        $cfg = $this->get_config();

        $params = array(
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $this->get_redirect_uri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce('gsc_oauth_state'),
        );

        return self::AUTH_URL . '?' . http_build_query($params, '', '&');
    }

    /**
     * Exchange authorization code for access + refresh tokens.
     *
     * @return true|\WP_Error
     */
    public function exchange_code(string $code)
    {
        $cfg = $this->get_config();

        $response = wp_remote_post(self::TOKEN_URL, array(
            'timeout' => 30,
            'body'    => array(
                'code'          => $code,
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri'  => $this->get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! isset($body['access_token'])) {
            $error_desc = $body['error_description'] ?? ($body['error'] ?? 'Unknown OAuth error');
            return new \WP_Error('gsc_token_error', $error_desc);
        }

        $this->save_config(array(
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? $cfg['refresh_token'],
            'token_expires' => time() + (int) ($body['expires_in'] ?? 3600),
        ));

        return true;
    }

    /**
     * Refresh the access token using the stored refresh token.
     *
     * @return true|\WP_Error
     */
    public function refresh_access_token()
    {
        $cfg = $this->get_config();

        if ('' === $cfg['refresh_token']) {
            return new \WP_Error('gsc_no_refresh', 'No refresh token available. Please re-authorize.');
        }

        $response = wp_remote_post(self::TOKEN_URL, array(
            'timeout' => 30,
            'body'    => array(
                'refresh_token' => $cfg['refresh_token'],
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'grant_type'    => 'refresh_token',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! isset($body['access_token'])) {
            $error_desc = $body['error_description'] ?? ($body['error'] ?? 'Token refresh failed');
            return new \WP_Error('gsc_refresh_error', $error_desc);
        }

        $this->save_config(array(
            'access_token'  => $body['access_token'],
            'token_expires' => time() + (int) ($body['expires_in'] ?? 3600),
        ));

        return true;
    }

    /**
     * Disconnect — clear tokens but keep client credentials.
     */
    public function disconnect(): void
    {
        $this->save_config(array(
            'access_token'  => '',
            'refresh_token' => '',
            'token_expires' => 0,
            'site_url'      => '',
            'last_sync'     => '',
        ));
    }

    /**
     * Get a valid access token, refreshing if expired.
     *
     * @return string|\WP_Error
     */
    public function get_access_token()
    {
        $cfg = $this->get_config();

        if ('' === $cfg['access_token']) {
            return new \WP_Error('gsc_not_connected', 'Not connected to Google Search Console.');
        }

        // Refresh if token expires within the next 60 seconds.
        if ($cfg['token_expires'] <= time() + 60) {
            $result = $this->refresh_access_token();
            if (is_wp_error($result)) {
                return $result;
            }
            $cfg = $this->get_config();
        }

        return $cfg['access_token'];
    }

    // ------------------------------------------------------------------
    //  Search Console API calls
    // ------------------------------------------------------------------

    /**
     * List verified sites from the user's Search Console account.
     *
     * @return array|\WP_Error
     */
    public function list_sites()
    {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $response = wp_remote_get(self::API_BASE . '/sites', array(
            'timeout' => 30,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new \WP_Error('gsc_api_error', $body['error']['message'] ?? "API error ($code)");
        }

        return $body['siteEntry'] ?? array();
    }

    /**
     * Query Search Analytics for the configured site.
     *
     * @param string $start_date  YYYY-MM-DD
     * @param string $end_date    YYYY-MM-DD
     * @param array  $dimensions  e.g. ['query'], ['page'], ['query','page'], ['date']
     * @param int    $row_limit   Max rows (API max 25000)
     * @param int    $start_row   Pagination offset
     * @return array|\WP_Error
     */
    public function query_analytics(string $start_date, string $end_date, array $dimensions = array('query'), int $row_limit = 1000, int $start_row = 0)
    {
        $cfg = $this->get_config();

        if ('' === $cfg['site_url']) {
            return new \WP_Error('gsc_no_site', 'No Search Console site selected.');
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $api_url = self::API_BASE . '/sites/' . rawurlencode($cfg['site_url']) . '/searchAnalytics/query';

        $request_body = array(
            'startDate'  => $start_date,
            'endDate'    => $end_date,
            'dimensions' => $dimensions,
            'rowLimit'   => min($row_limit, 25000),
            'startRow'   => $start_row,
        );

        $response = wp_remote_post($api_url, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode($request_body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new \WP_Error('gsc_api_error', $body['error']['message'] ?? "API error ($code)");
        }

        return $body['rows'] ?? array();
    }

    // ------------------------------------------------------------------
    //  Data storage — search analytics table
    // ------------------------------------------------------------------

    /**
     * Get the full table name.
     */
    public function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Fetch and store analytics data for a date range.
     *
     * Pulls both query-level and page-level data and merges into the local table.
     *
     * @return int|\WP_Error  Number of rows stored, or error.
     */
    public function sync_data(string $start_date, string $end_date)
    {
        global $wpdb;

        $table = $this->table_name();
        $total = 0;

        // 1. Fetch query-level data (date + query).
        $query_rows = $this->query_analytics($start_date, $end_date, array('date', 'query'), 5000);
        if (is_wp_error($query_rows)) {
            return $query_rows;
        }

        foreach ($query_rows as $row) {
            $date  = $row['keys'][0] ?? '';
            $query = $row['keys'][1] ?? '';

            $wpdb->replace($table, array(
                'fetch_date'  => $date,
                'dimension'   => 'query',
                'dimension_value' => mb_substr($query, 0, 500),
                'clicks'      => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr'         => (float) ($row['ctr'] ?? 0),
                'position'    => (float) ($row['position'] ?? 0),
            ), array('%s', '%s', '%s', '%d', '%d', '%f', '%f'));

            $total++;
        }

        // 2. Fetch page-level data (date + page).
        $page_rows = $this->query_analytics($start_date, $end_date, array('date', 'page'), 5000);
        if (is_wp_error($page_rows)) {
            return $page_rows;
        }

        foreach ($page_rows as $row) {
            $date = $row['keys'][0] ?? '';
            $page = $row['keys'][1] ?? '';

            $wpdb->replace($table, array(
                'fetch_date'  => $date,
                'dimension'   => 'page',
                'dimension_value' => mb_substr($page, 0, 500),
                'clicks'      => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr'         => (float) ($row['ctr'] ?? 0),
                'position'    => (float) ($row['position'] ?? 0),
            ), array('%s', '%s', '%s', '%d', '%d', '%f', '%f'));

            $total++;
        }

        $this->save_config(array('last_sync' => gmdate('Y-m-d H:i:s')));

        return $total;
    }

    /**
     * Run the daily sync — fetches the last 3 days of data (GSC has a ~2-day delay).
     *
     * @return int|\WP_Error
     */
    public function daily_sync()
    {
        if (! $this->is_connected()) {
            return 0;
        }

        $cfg = $this->get_config();
        if ('' === $cfg['site_url']) {
            return 0;
        }

        $end_date   = gmdate('Y-m-d', strtotime('-2 days'));
        $start_date = gmdate('Y-m-d', strtotime('-4 days'));

        return $this->sync_data($start_date, $end_date);
    }

    /**
     * Prune analytics data older than a given number of days.
     */
    public function prune_old_data(int $days = 90): int
    {
        global $wpdb;

        $cutoff = gmdate('Y-m-d', strtotime("-{$days} days"));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name()} WHERE fetch_date < %s",
                $cutoff
            )
        );
    }

    // ------------------------------------------------------------------
    //  Dashboard queries — read from local table
    // ------------------------------------------------------------------

    /**
     * Get overview metrics (totals/averages) for a date range.
     *
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    public function get_overview(string $start_date, string $end_date, string $dimension = 'query'): array
    {
        global $wpdb;
        $table = $this->table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(clicks) AS clicks,
                        SUM(impressions) AS impressions,
                        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                        AVG(position) AS position
                 FROM {$table}
                 WHERE dimension = %s AND fetch_date BETWEEN %s AND %s",
                $dimension,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        return array(
            'clicks'      => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr'         => round((float) ($row['ctr'] ?? 0), 4),
            'position'    => round((float) ($row['position'] ?? 0), 1),
        );
    }

    /**
     * Get top queries or pages.
     *
     * @return array List of rows sorted by clicks DESC.
     */
    public function get_top_items(string $start_date, string $end_date, string $dimension = 'query', int $limit = 20, string $order_by = 'clicks'): array
    {
        global $wpdb;
        $table = $this->table_name();

        $allowed_order = array('clicks', 'impressions', 'ctr', 'position');
        if (! in_array($order_by, $allowed_order, true)) {
            $order_by = 'clicks';
        }

        $direction = 'position' === $order_by ? 'ASC' : 'DESC';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT dimension_value,
                        SUM(clicks) AS clicks,
                        SUM(impressions) AS impressions,
                        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                        AVG(position) AS position
                 FROM {$table}
                 WHERE dimension = %s AND fetch_date BETWEEN %s AND %s
                 GROUP BY dimension_value
                 ORDER BY {$order_by} {$direction}
                 LIMIT %d",
                $dimension,
                $start_date,
                $end_date,
                $limit
            )
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * Get daily trend data for charts.
     *
     * @return array Rows with fetch_date, clicks, impressions, ctr, position.
     */
    public function get_daily_trend(string $start_date, string $end_date, string $dimension = 'query'): array
    {
        global $wpdb;
        $table = $this->table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT fetch_date,
                        SUM(clicks) AS clicks,
                        SUM(impressions) AS impressions,
                        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                        AVG(position) AS position
                 FROM {$table}
                 WHERE dimension = %s AND fetch_date BETWEEN %s AND %s
                 GROUP BY fetch_date
                 ORDER BY fetch_date ASC",
                $dimension,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * Check if local data exists for the given range.
     */
    public function has_data(string $start_date, string $end_date): bool

    {
        global $wpdb;
        $table = $this->table_name();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE fetch_date BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        return $count > 0;
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    /**
     * Get performance data for a specific page URL.
     *
     * @return array{clicks: int, impressions: int, ctr: float, position: float, top_queries: array}|null
     */
    public function get_page_performance(string $page_url, int $days = 30): ?array
    {
        global $wpdb;
        $table = $this->table_name();

        $start = gmdate('Y-m-d', strtotime("-{$days} days"));
        $end   = gmdate('Y-m-d', strtotime('-2 days'));

        // Aggregate page-level metrics.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(clicks) AS clicks,
                        SUM(impressions) AS impressions,
                        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                        AVG(position) AS position
                 FROM {$table}
                 WHERE dimension = 'page' AND dimension_value = %s AND fetch_date BETWEEN %s AND %s",
                $page_url,
                $start,
                $end
            )
        );

        if (! $row || null === $row->clicks) {
            return null;
        }

        return array(
            'clicks'      => (int) $row->clicks,
            'impressions' => (int) $row->impressions,
            'ctr'         => round((float) $row->ctr, 4),
            'position'    => round((float) $row->position, 1),
        );
    }

    /**
     * Get a compact site-wide summary for display in the audit card.
     *
     * @return array{clicks: int, impressions: int, ctr: float, position: float, top_page_count: int, period_days: int}
     */
    public function get_site_summary(int $days = 30): array
    {
        global $wpdb;
        $table = $this->table_name();

        $start = gmdate('Y-m-d', strtotime("-{$days} days"));
        $end   = gmdate('Y-m-d', strtotime('-2 days'));

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(clicks) AS clicks,
                        SUM(impressions) AS impressions,
                        CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                        AVG(position) AS position
                 FROM {$table}
                 WHERE dimension = 'query' AND fetch_date BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        $page_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT dimension_value)
                 FROM {$table}
                 WHERE dimension = 'page' AND fetch_date BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        return array(
            'clicks'         => (int) ($row->clicks ?? 0),
            'impressions'    => (int) ($row->impressions ?? 0),
            'ctr'            => round((float) ($row->ctr ?? 0), 4),
            'position'       => round((float) ($row->position ?? 0), 1),
            'top_page_count' => $page_count,
            'period_days'    => $days,
        );
    }

    /**
     * The OAuth redirect URI — always points back to our admin page.
     */
    public function get_redirect_uri(): string
    {
        return admin_url('admin.php?page=ai-seo-captain-search-console&gsc_oauth_callback=1');
    }
}
