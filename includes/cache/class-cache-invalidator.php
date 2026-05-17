<?php

namespace AI_SEO_Captain\Cache;

/**
 * Smart cache invalidation.
 *
 * Maps post changes to the full list of URLs that must be purged,
 * and hooks into WordPress lifecycle events to trigger purges.
 */
class Cache_Invalidator
{

    /** @var Page_Cache */
    private $page_cache;

    /** @var Cache_Preloader|null */
    private $preloader;

    /** @var bool */
    private $auto_preload;

    /** @var bool Guard against duplicate purges in the same request. */
    private $purged_all = false;

    public function __construct(Page_Cache $page_cache, $preloader = null, bool $auto_preload = true)
    {
        $this->page_cache   = $page_cache;
        $this->preloader    = $preloader;
        $this->auto_preload = $auto_preload;
    }

    /**
     * Register all invalidation hooks.
     */
    public function register_hooks(): void
    {
        // Post lifecycle — priority 99, after SEO Captain's own save_post at 50.
        add_action('save_post', array($this, 'on_post_save'), 99, 2);
        add_action('delete_post', array($this, 'on_post_delete'), 10);
        add_action('trashed_post', array($this, 'on_post_delete'), 10);
        add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);

        // Comments.
        add_action('comment_post', array($this, 'on_comment_change'), 10);
        add_action('edit_comment', array($this, 'on_comment_change'), 10);
        add_action('delete_comment', array($this, 'on_comment_change'), 10);
        add_action('wp_set_comment_status', array($this, 'on_comment_change'), 10);

        // Terms.
        add_action('edited_term', array($this, 'on_term_edit'), 10, 3);
        add_action('delete_term', array($this, 'on_term_edit'), 10, 3);
        add_action('created_term', array($this, 'on_term_edit'), 10, 3);

        // Global changes → purge all.
        add_action('switch_theme', array($this, 'on_full_purge_event'));
        add_action('update_option_sidebars_widgets', array($this, 'on_full_purge_event'));
        add_action('wp_update_nav_menu', array($this, 'on_full_purge_event'));
        add_action('permalink_structure_changed', array($this, 'on_full_purge_event'));

        // WooCommerce stock changes.
        add_action('woocommerce_product_set_stock', array($this, 'on_wc_stock_change'));
        add_action('woocommerce_variation_set_stock', array($this, 'on_wc_variation_stock_change'));
    }

    /**
     * Given a post ID, return all URLs that must be purged.
     */
    public function get_related_urls(int $post_id): array
    {
        $post = get_post($post_id);

        if (! $post || 'publish' !== $post->post_status) {
            // If the post is no longer published, still purge its permalink and homepage.
            $urls   = array();
            $urls[] = get_permalink($post_id);
            $urls[] = home_url('/');
            return array_filter(array_unique($urls));
        }

        $urls = array();

        // 1. The post permalink itself.
        $urls[] = get_permalink($post_id);

        // 2. The homepage.
        $urls[] = home_url('/');

        // 3. Category archive pages.
        $categories = get_the_category($post_id);
        if (is_array($categories)) {
            foreach ($categories as $cat) {
                $urls[] = get_category_link($cat->term_id);
            }
        }

        // 4. Tag archive pages.
        $tags = get_the_tags($post_id);
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $urls[] = get_tag_link($tag->term_id);
            }
        }

        // 5. Author archive page.
        $urls[] = get_author_posts_url((int) $post->post_author);

        // 6. Post type archive (if applicable).
        $archive_link = get_post_type_archive_link($post->post_type);
        if ($archive_link) {
            $urls[] = $archive_link;
        }

        // 7. Pagination pages for affected archives.
        $posts_per_page = (int) get_option('posts_per_page', 10);
        if ($posts_per_page > 0) {
            $total_posts = wp_count_posts($post->post_type);
            $published   = isset($total_posts->publish) ? (int) $total_posts->publish : 0;
            $max_pages   = min((int) ceil($published / $posts_per_page), 5); // Cap at 5 pages.

            for ($p = 2; $p <= $max_pages; $p++) {
                $urls[] = home_url('/page/' . $p . '/');
            }
        }

        // 8. RSS feed URLs.
        $urls[] = get_bloginfo_rss('rss2_url');
        if (is_array($categories)) {
            foreach ($categories as $cat) {
                $urls[] = get_category_feed_link($cat->term_id);
            }
        }

        return array_filter(array_unique($urls));
    }

    /**
     * Hook: save_post — purge post URL + related URLs.
     */
    public function on_post_save(int $post_id, \WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! in_array($post->post_status, array('publish', 'private'), true)) {
            return;
        }

        $this->purge_post_and_related($post_id);
    }

    /**
     * Hook: delete_post, trashed_post.
     */
    public function on_post_delete(int $post_id): void
    {
        $this->purge_post_and_related($post_id);
    }

    /**
     * Hook: transition_post_status — purge on publish/unpublish transitions.
     */
    public function on_post_status_change(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($new_status === $old_status) {
            return;
        }

        // Only care about transitions involving 'publish'.
        if ('publish' !== $new_status && 'publish' !== $old_status) {
            return;
        }

        $this->purge_post_and_related($post->ID);
    }

    /**
     * Hook: comment lifecycle events.
     */
    public function on_comment_change(int $comment_id): void
    {
        $comment = get_comment($comment_id);

        if (! $comment || empty($comment->comment_post_ID)) {
            return;
        }

        $this->purge_post_and_related((int) $comment->comment_post_ID);
    }

    /**
     * Hook: term edits — purge term archive URL.
     */
    public function on_term_edit(int $term_id, int $tt_id, string $taxonomy): void
    {
        $term_link = get_term_link($term_id, $taxonomy);

        if (is_wp_error($term_link)) {
            return;
        }

        $this->page_cache->purge($term_link);
        $this->page_cache->purge(home_url('/'));
    }

    /**
     * Hook: events requiring full cache purge.
     */
    public function on_full_purge_event(): void
    {
        if ($this->purged_all) {
            return;
        }

        $this->page_cache->purge_all();
        $this->purged_all = true;
    }

    /**
     * Hook: WooCommerce product stock change.
     *
     * @param \WC_Product|object $product
     */
    public function on_wc_stock_change($product): void
    {
        if (is_object($product) && method_exists($product, 'get_id')) {
            $this->purge_post_and_related($product->get_id());

            // Also purge the shop page.
            $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
            if ($shop_page_id > 0) {
                $this->page_cache->purge(get_permalink($shop_page_id));
            }
        }
    }

    /**
     * Hook: WooCommerce variation stock change — purge the parent product.
     *
     * @param \WC_Product_Variation|object $variation
     */
    public function on_wc_variation_stock_change($variation): void
    {
        if (is_object($variation) && method_exists($variation, 'get_parent_id')) {
            $parent_id = $variation->get_parent_id();
            if ($parent_id > 0) {
                $this->purge_post_and_related($parent_id);
            }
        }
    }

    /**
     * Purge a post and all its related URLs.
     */
    private function purge_post_and_related(int $post_id): void
    {
        $urls = $this->get_related_urls($post_id);

        foreach ($urls as $url) {
            $this->page_cache->purge($url);
        }

        // Auto-preload purged URLs if enabled.
        if ($this->auto_preload && null !== $this->preloader) {
            $this->preloader->preload_urls($urls);
        }
    }
}
