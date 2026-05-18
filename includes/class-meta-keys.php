<?php

namespace AI_SEO_Captain;

/**
 * Centralized post/term meta key constants.
 *
 * Use these instead of duplicating key strings across classes.
 * Example: Meta_Keys::TITLE instead of '_ai_seo_captain_meta_title'.
 */
final class Meta_Keys
{
    // --- Post meta keys ---------------------------------------------------

    public const TITLE              = '_ai_seo_captain_meta_title';
    public const DESCRIPTION        = '_ai_seo_captain_meta_description';
    public const FOCUS_KEYPHRASE    = '_ai_seo_captain_focus_keyphrase';
    public const SOCIAL_TITLE       = '_ai_seo_captain_social_title';
    public const SOCIAL_DESCRIPTION = '_ai_seo_captain_social_description';
    public const SOCIAL_IMAGE       = '_ai_seo_captain_social_image';
    public const CANONICAL          = '_ai_seo_captain_canonical_url';
    public const ROBOTS             = '_ai_seo_captain_robots_directives';
    public const SCHEMA_TYPE        = '_ai_seo_captain_schema_type';
    public const FRONTEND_ON        = '_ai_seo_captain_frontend_enabled';
    public const APPROVED_MSG       = '_ai_seo_captain_approved_message_id';
    public const BRANDING_OFF       = '_ai_seo_captain_title_branding_off';
    public const CORNERSTONE        = '_ai_seo_captain_cornerstone';
    public const HREFLANG           = '_ai_seo_captain_hreflang';
    public const PAGE_AUDIT         = '_ai_seo_captain_page_audit';
    public const PENDING_CHANGES    = '_ai_seo_captain_pending_content_changes';
    public const CONTENT_BACKUP     = '_ai_seo_captain_content_backup';
    public const AUDIT_SKIP         = '_ai_seo_captain_audit_skip';
    public const KEYWORDS           = '_ai_seo_captain_keywords';
    public const EXCLUDE_SITEMAP    = '_ai_seo_captain_exclude_sitemap';

    // --- Term meta keys ---------------------------------------------------

    public const TERM_SEO_TITLE       = '_ai_seo_captain_seo_title';
    public const TERM_META_DESCRIPTION = '_ai_seo_captain_meta_description';
    public const TERM_CANONICAL       = '_ai_seo_captain_canonical';
    public const TERM_NOINDEX         = '_ai_seo_captain_noindex';

    /**
     * All post meta keys managed by the plugin.
     * Used by uninstall.php for complete cleanup.
     *
     * @return string[]
     */
    public static function all_post_meta_keys(): array
    {
        return array(
            self::TITLE,
            self::DESCRIPTION,
            self::FOCUS_KEYPHRASE,
            self::SOCIAL_TITLE,
            self::SOCIAL_DESCRIPTION,
            self::SOCIAL_IMAGE,
            self::CANONICAL,
            self::ROBOTS,
            self::SCHEMA_TYPE,
            self::FRONTEND_ON,
            self::APPROVED_MSG,
            self::BRANDING_OFF,
            self::CORNERSTONE,
            self::HREFLANG,
            self::PAGE_AUDIT,
            self::PENDING_CHANGES,
            self::CONTENT_BACKUP,
            self::AUDIT_SKIP,
            self::KEYWORDS,
            self::EXCLUDE_SITEMAP,
        );
    }

    /**
     * All term meta keys managed by the plugin.
     *
     * @return string[]
     */
    public static function all_term_meta_keys(): array
    {
        return array(
            self::TERM_SEO_TITLE,
            self::TERM_META_DESCRIPTION,
            self::TERM_CANONICAL,
            self::TERM_NOINDEX,
        );
    }
}
