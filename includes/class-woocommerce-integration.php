<?php

namespace AI_SEO_Keeper;

// WooCommerce global functions — imported so Intelephense resolves them inside this namespace.
use function wc_get_product;
use function wc_get_page_id;
use function wc_get_page_permalink;
use function get_woocommerce_currency;
use function is_shop;
use function is_product_category;
use function is_product_tag;

/**
 * WooCommerce Integration for AI SEO Keeper.
 *
 * Boots only when WooCommerce is active. Enriches:
 *  - Product schema with real WC data (price, SKU, availability, ratings)
 *  - Open Graph tags with og:price and og:availability for products
 *  - WC archive pages (shop, product_cat, product_tag) with proper SEO titles/descriptions
 *  - Sitemaps with product / product_cat / product_tag entries
 *  - AI generation context with product-specific data
 *
 * @package AI_SEO_Keeper
 */
class WooCommerce_Integration
{
    /**
     * Boot the integration if WooCommerce is active.
     * Called on 'plugins_loaded' after WC itself has initialised.
     */
    public static function maybe_boot(): void
    {
        if (! self::is_woocommerce_active()) {
            return;
        }

        $instance = new self();
        $instance->init();
    }

    private static function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce', false);
    }

    private function init(): void
    {
        // Schema enrichment — hooked from class-frontend.php filter.
        add_filter('ai_seo_keeper_product_schema', array($this, 'enrich_product_schema'), 10, 2);

        // Extra Open Graph tags for product pages.
        add_filter('ai_seo_keeper_extra_og_tags', array($this, 'add_product_og_tags'), 10, 2);

        // Non-singular context for WC archives.
        add_filter('ai_seo_keeper_wc_archive_context', array($this, 'build_wc_archive_context'), 10, 3);

        // Sitemap entries.
        add_filter('ai_seo_keeper_sitemap_index_entries', array($this, 'add_sitemap_entries'), 10, 2);
        add_filter('ai_seo_keeper_sitemap_urls', array($this, 'get_sitemap_urls'), 10, 2);

        // AI generator context.
        add_filter('ai_seo_keeper_product_context', array($this, 'get_ai_product_context'), 10, 2);
    }

    // -----------------------------------------------------------------------
    // Schema enrichment
    // -----------------------------------------------------------------------

    /**
     * Enrich a Product schema entity with real WooCommerce data.
     *
     * @param array    $entity  The schema entity being built.
     * @param \WP_Post $post    The post object.
     * @return array
     */
    public function enrich_product_schema(array $entity, \WP_Post $post): array
    {
        if (! function_exists('wc_get_product')) {
            return $entity;
        }

        $product = wc_get_product($post->ID);

        if (! $product instanceof \WC_Product) {
            return $entity;
        }

        // SKU.
        $sku = $product->get_sku();
        if ('' !== $sku) {
            $entity['sku'] = $sku;
        }

        // Brand (if set via meta or WC Brands extension).
        $brand = $this->get_product_brand($product);
        if ('' !== $brand) {
            $entity['brand'] = array('@type' => 'Brand', 'name' => $brand);
        }

        // Offer with real price, currency and availability.
        $offer = $this->build_wc_offer($product);
        if (! empty($offer)) {
            $entity['offers'] = $offer;
        }

        // Aggregate rating — only when at least one review exists.
        if ($product->get_rating_count() > 0) {
            $entity['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format((float) $product->get_average_rating(), 1),
                'reviewCount' => (int) $product->get_rating_count(),
                'bestRating'  => '5',
                'worstRating' => '1',
            );
        }

        // GTIN-13 / EAN from common meta keys.
        $gtin = $this->get_candidate_meta($post->ID, array('_gtin', '_ean', '_barcode', 'gtin', 'ean'));
        if ('' !== $gtin) {
            $entity['gtin13'] = $gtin;
        }

        // Product category as category breadcrumb hint.
        $categories = get_the_terms($post->ID, 'product_cat');
        if (is_array($categories) && ! empty($categories)) {
            $entity['category'] = esc_html($categories[0]->name);
        }

        return $entity;
    }

    // -----------------------------------------------------------------------
    // Open Graph — product price tags
    // -----------------------------------------------------------------------

    /**
     * Append og:price:amount and og:availability for WC product pages.
     *
     * @param array $extra_tags  Accumulated extra tag lines.
     * @param array $context     Frontend context array.
     * @return array
     */
    public function add_product_og_tags(array $extra_tags, array $context): array
    {
        if (empty($context['post']) || ! function_exists('wc_get_product')) {
            return $extra_tags;
        }

        if ('Product' !== ($context['schema_type'] ?? '')) {
            return $extra_tags;
        }

        $product = wc_get_product($context['post']->ID);
        if (! $product instanceof \WC_Product) {
            return $extra_tags;
        }

        $extra_tags[] = array('name' => 'og:type',               'content' => 'product');
        $extra_tags[] = array('name' => 'product:price:currency', 'content' => get_woocommerce_currency());

        $price = $product->get_price();
        if ('' !== $price) {
            $extra_tags[] = array('name' => 'product:price:amount', 'content' => $price);
        }

        $extra_tags[] = array(
            'name'    => 'product:availability',
            'content' => $product->is_in_stock() ? 'in stock' : 'out of stock',
        );

        $sku = $product->get_sku();
        if ('' !== $sku) {
            $extra_tags[] = array('name' => 'product:retailer_item_id', 'content' => $sku);
        }

        return $extra_tags;
    }

    // -----------------------------------------------------------------------
    // Non-singular context for WC archives
    // -----------------------------------------------------------------------

    /**
     * Build frontend context for WooCommerce archive pages.
     *
     * Called via filter from Frontend::get_non_singular_context() when
     * is_shop(), is_product_category(), or is_product_tag() returns true.
     *
     * @param array  $context   Partial context to fill.
     * @param array  $options   Plugin settings.
     * @param string $separator Title separator.
     * @return array
     */
    public function build_wc_archive_context(array $context, array $options, string $separator): array
    {
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

        if (function_exists('is_shop') && is_shop()) {
            $shop_page_id  = (int) wc_get_page_id('shop');
            $archive_title = $shop_page_id > 0
                ? wp_strip_all_tags((string) get_the_title($shop_page_id))
                : __('Shop', 'ai-seo-keeper');

            $context['context_type']       = 'wc_shop';
            $context['title']              = $archive_title . ' ' . $separator . ' ' . $site_name;
            $context['description']        = $shop_page_id > 0
                ? wp_strip_all_tags((string) get_post_field('post_excerpt', $shop_page_id))
                : '';
            $context['canonical_url']      = (string) wc_get_page_permalink('shop');
            $context['schema_type']        = 'CollectionPage';
            $context['open_graph_type']    = 'website';
        } elseif (function_exists('is_product_category') && is_product_category()) {
            $term = get_queried_object();
            if (! $term instanceof \WP_Term) {
                return $context;
            }
            $thumb_id    = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
            $thumb_url   = $thumb_id > 0 ? (string) wp_get_attachment_url($thumb_id) : '';
            $description = wp_strip_all_tags((string) term_description($term->term_id, 'product_cat'));

            $context['context_type']    = 'product_cat';
            $context['title']           = $term->name . ' ' . $separator . ' ' . $site_name;
            $context['description']     = $description;
            $context['canonical_url']   = (string) get_term_link($term);
            $context['schema_type']     = 'CollectionPage';
            $context['open_graph_type'] = 'website';

            if ('' !== $thumb_url) {
                $context['social_image_url'] = $thumb_url;
            }

            // Respect term-level noindex.
            $noindex = get_term_meta($term->term_id, '_ai_seo_keeper_noindex', true);
            if ('1' === $noindex) {
                $context['robots_directives'] = 'noindex,follow';
            }
        } elseif (function_exists('is_product_tag') && is_product_tag()) {
            $term = get_queried_object();
            if (! $term instanceof \WP_Term) {
                return $context;
            }
            $context['context_type']    = 'product_tag';
            $context['title']           = $term->name . ' ' . $separator . ' ' . $site_name;
            $context['description']     = wp_strip_all_tags((string) term_description($term->term_id, 'product_tag'));
            $context['canonical_url']   = (string) get_term_link($term);
            $context['schema_type']     = 'CollectionPage';
            $context['open_graph_type'] = 'website';
        }

        return $context;
    }

    // -----------------------------------------------------------------------
    // Sitemap
    // -----------------------------------------------------------------------

    /**
     * Add product / product_cat / product_tag entries to the sitemap index.
     *
     * @param array $entries Existing sitemap index entries.
     * @param array $options Plugin settings.
     * @return array
     */
    public function add_sitemap_entries(array $entries, array $options): array
    {
        if (! empty($options['sitemap_include_wc_products'])) {
            $latest = $this->get_latest_modified_date('product');
            $entries[] = array(
                'loc'     => home_url('/product-sitemap.xml'),
                'lastmod' => $latest,
            );
        }

        if (! empty($options['sitemap_include_wc_product_cat'])) {
            $entries[] = array(
                'loc'     => home_url('/product_cat-sitemap.xml'),
                'lastmod' => '',
            );
        }

        if (! empty($options['sitemap_include_wc_product_tag'])) {
            $entries[] = array(
                'loc'     => home_url('/product_tag-sitemap.xml'),
                'lastmod' => '',
            );
        }

        return $entries;
    }

    /**
     * Serve product / product_cat / product_tag sitemap URLs.
     *
     * @param array  $urls  Accumulated URL list.
     * @param string $type  Sitemap type slug.
     * @return array
     */
    public function get_sitemap_urls(array $urls, string $type): array
    {
        if ('product' === $type) {
            return $this->get_product_post_urls();
        }

        if ('product_cat' === $type) {
            return $this->get_taxonomy_term_urls('product_cat');
        }

        if ('product_tag' === $type) {
            return $this->get_taxonomy_term_urls('product_tag');
        }

        return $urls;
    }

    // -----------------------------------------------------------------------
    // AI context
    // -----------------------------------------------------------------------

    /**
     * Provide WooCommerce product data for the AI generator context.
     *
     * @param array    $wc_data Accumulated product context (starts empty).
     * @param \WP_Post $post    The post being generated for.
     * @return array
     */
    public function get_ai_product_context(array $wc_data, \WP_Post $post): array
    {
        if ('product' !== $post->post_type || ! function_exists('wc_get_product')) {
            return $wc_data;
        }

        $product = wc_get_product($post->ID);
        if (! $product instanceof \WC_Product) {
            return $wc_data;
        }

        $wc_data['wc_price']        = $product->get_price_html();
        $wc_data['wc_sku']          = $product->get_sku();
        $wc_data['wc_availability'] = $product->is_in_stock() ? 'In stock' : 'Out of stock';
        $wc_data['wc_type']         = $product->get_type();

        $rating = $product->get_average_rating();
        $count  = $product->get_rating_count();
        if ($count > 0) {
            $wc_data['wc_rating'] = $rating . '/5 (' . $count . ' review' . ($count > 1 ? 's' : '') . ')';
        }

        $cats = get_the_terms($post->ID, 'product_cat');
        if (is_array($cats) && ! empty($cats)) {
            $wc_data['wc_categories'] = implode(', ', wp_list_pluck($cats, 'name'));
        }

        $tags = get_the_terms($post->ID, 'product_tag');
        if (is_array($tags) && ! empty($tags)) {
            $wc_data['wc_tags'] = implode(', ', wp_list_pluck($tags, 'name'));
        }

        $brand = $this->get_product_brand($product);
        if ('' !== $brand) {
            $wc_data['wc_brand'] = $brand;
        }

        return $wc_data;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function build_wc_offer(\WC_Product $product): array
    {
        $price = $product->get_price();
        if ('' === $price) {
            return array();
        }

        $offer = array(
            '@type'         => 'Offer',
            'url'           => get_permalink($product->get_id()),
            'price'         => $price,
            'priceCurrency' => get_woocommerce_currency(),
            'availability'  => $product->is_in_stock()
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        );

        // Sale price validity.
        if ($product->is_on_sale()) {
            $sale_ends = $product->get_date_on_sale_to();
            if ($sale_ends) {
                $offer['priceValidUntil'] = $sale_ends->format('Y-m-d');
            }
        }

        // Seller.
        $offer['seller'] = array(
            '@type' => 'Organization',
            '@id'   => home_url('/#organization'),
        );

        return $offer;
    }

    private function get_product_brand(\WC_Product $product): string
    {
        // WooCommerce Brands plugin.
        $terms = get_the_terms($product->get_id(), 'product_brand');
        if (is_array($terms) && ! empty($terms)) {
            return (string) $terms[0]->name;
        }

        // PW WooCommerce Brands / generic meta fallback.
        return $this->get_candidate_meta($product->get_id(), array('_brand', 'brand', 'product_brand', '_product_brand'));
    }

    private function get_candidate_meta(int $post_id, array $keys): string
    {
        foreach ($keys as $key) {
            $val = trim((string) get_post_meta($post_id, $key, true));
            if ('' !== $val) {
                return $val;
            }
        }
        return '';
    }

    private function get_product_post_urls(): array
    {
        $noindex_key = '_ai_seo_keeper_robots_directives';
        $urls        = array();

        $posts = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($posts as $post_id) {
            $post_id = (int) $post_id;
            $robots  = (string) get_post_meta($post_id, $noindex_key, true);
            if (false !== strpos($robots, 'noindex')) {
                continue;
            }
            $post_url = (string) get_permalink($post_id);
            if ('' === $post_url) {
                continue;
            }
            $urls[] = array(
                'loc'        => $post_url,
                'lastmod'    => (string) get_post_modified_time(DATE_W3C, true, $post_id),
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            );
        }

        return $urls;
    }

    private function get_taxonomy_term_urls(string $taxonomy): array
    {
        $urls  = array();
        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'fields'     => 'all',
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return $urls;
        }

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }
            $noindex = get_term_meta($term->term_id, '_ai_seo_keeper_noindex', true);
            if ('1' === $noindex) {
                continue;
            }
            $term_url = (string) get_term_link($term);
            if (is_wp_error($term_url) || '' === $term_url) {
                continue;
            }
            $urls[] = array(
                'loc'        => $term_url,
                'lastmod'    => '',
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            );
        }

        return $urls;
    }

    private function get_latest_modified_date(string $post_type): string
    {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT post_modified_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_modified_gmt DESC LIMIT 1",
            $post_type
        ));
        if (! $result) {
            return '';
        }
        $ts = strtotime($result);
        return $ts ? gmdate(DATE_W3C, $ts) : '';
    }
}
