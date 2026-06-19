<?php

namespace AI_SEO_Captain;

defined('ABSPATH') || exit;

/**
 * Centralized Freemius licensing / feature-gating helper.
 *
 * All premium gating in the plugin should go through this class so the
 * logic lives in one place. When the Freemius SDK is not present (e.g. a
 * stripped build), the plugin falls back to fully-unlocked behaviour so
 * default operation is never broken.
 *
 * @package AI_SEO_Captain
 */
final class Licensing
{
    /** Free-plan lifetime limit for AI-powered generation (pages). */
    const FREE_AI_PAGE_LIMIT = 30;

    /** Option key storing how many pages a Free user has AI-generated. */
    const QUOTA_OPTION = 'ai_seo_captain_ai_quota_used';

    /**
     * Get the Freemius instance, or null if the SDK is unavailable.
     *
     * @return \Freemius|null
     */
    public static function fs()
    {
        return function_exists('asc_fs') ? asc_fs() : null;
    }

    /**
     * Whether the current site may use premium (Pro) features.
     *
     * True for paying customers and active trials. When the SDK is absent
     * the method returns true to preserve default (unlocked) behaviour.
     */
    public static function is_pro(): bool
    {
        $fs = self::fs();

        if (null === $fs) {
            // SDK not loaded — do not break default operation.
            $is_pro = true;
        } else {
            $is_pro = $fs->can_use_premium_code();
        }

        /**
         * Filter the Pro/premium gating decision.
         *
         * Primarily intended for testing the Free experience on a licensed
         * site (e.g. add_filter('aisc_is_pro', '__return_false')).
         *
         * @param bool $is_pro Whether premium features are unlocked.
         */
        return (bool) apply_filters('aisc_is_pro', $is_pro);
    }

    /**
     * Whether the current site is on the Free plan.
     */
    public static function is_free(): bool
    {
        return ! self::is_pro();
    }

    /**
     * Whether the site is a paying customer (excludes trials).
     */
    public static function is_paying(): bool
    {
        $fs = self::fs();

        if (null === $fs) {
            return true;
        }

        return $fs->is_paying();
    }

    /**
     * Hard server-side gate for Pro-only AJAX endpoints.
     *
     * UI menus already hide Pro pages behind an upgrade teaser, but the
     * underlying AJAX actions ship in every build, so they must enforce the
     * license server-side too. Call this right after the nonce + capability
     * checks in any Pro-only AJAX handler. On a Free site it terminates the
     * request with a 403 JSON error; on Pro it returns and lets the handler
     * continue.
     */
    public static function require_pro(): void
    {
        if (self::is_pro()) {
            return;
        }

        wp_send_json_error(
            array(
                'message'     => __('This is a Pro feature. Please upgrade to unlock it.', 'ai-seo-captain'),
                'upgrade_url' => self::upgrade_url(),
                'is_pro_gate' => true,
            ),
            403
        );
    }

    /* ------------------------------------------------------------------ *
     *  AI-generation quota (Free plan only)
     * ------------------------------------------------------------------ */

    /**
     * Number of AI-generated pages allowed. PHP_INT_MAX for Pro.
     */
    public static function ai_limit(): int
    {
        return self::is_pro() ? PHP_INT_MAX : self::FREE_AI_PAGE_LIMIT;
    }

    /**
     * Number of AI-generated pages already consumed by a Free site.
     */
    public static function ai_used(): int
    {
        return (int) get_option(self::QUOTA_OPTION, 0);
    }

    /**
     * Remaining AI-generation allowance. PHP_INT_MAX for Pro.
     */
    public static function ai_remaining(): int
    {
        if (self::is_pro()) {
            return PHP_INT_MAX;
        }

        return max(0, self::FREE_AI_PAGE_LIMIT - self::ai_used());
    }

    /**
     * Whether the site may AI-generate $count more pages.
     *
     * @param int $count Number of pages about to be generated.
     */
    public static function ai_can_generate(int $count = 1): bool
    {
        if (self::is_pro()) {
            return true;
        }

        return (self::ai_used() + max(1, $count)) <= self::FREE_AI_PAGE_LIMIT;
    }

    /**
     * Record consumption of the AI-generation quota. No-op for Pro.
     *
     * @param int $count Number of pages generated.
     */
    public static function ai_record_usage(int $count = 1): void
    {
        if (self::is_pro()) {
            return;
        }

        update_option(self::QUOTA_OPTION, self::ai_used() + max(1, $count), false);
    }

    /**
     * Friendly, translatable message shown when a Free site hits the AI
     * generation limit. Includes the remaining count and an upgrade hint.
     */
    public static function ai_quota_message(): string
    {
        return sprintf(
            /* translators: %d: free AI page generation limit. */
            __('You have reached the free limit of %d AI-generated pages. Manual SEO editing stays unlimited — upgrade to Pro for unlimited AI generation.', 'ai-seo-captain'),
            self::FREE_AI_PAGE_LIMIT
        );
    }

    /* ------------------------------------------------------------------ *
     *  AI-audit quota (Free plan only)
     * ------------------------------------------------------------------ */

    /** Free-plan lifetime limit for AI SEO audits (pages). */
    const FREE_AUDIT_PAGE_LIMIT = 30;

    /** Option key storing how many pages a Free user has AI-audited. */
    const AUDIT_QUOTA_OPTION = 'ai_seo_captain_audit_quota_used';

    /**
     * Number of AI-audited pages already consumed by a Free site.
     */
    public static function audit_used(): int
    {
        return (int) get_option(self::AUDIT_QUOTA_OPTION, 0);
    }

    /**
     * Remaining AI-audit allowance. PHP_INT_MAX for Pro.
     */
    public static function audit_remaining(): int
    {
        if (self::is_pro()) {
            return PHP_INT_MAX;
        }

        return max(0, self::FREE_AUDIT_PAGE_LIMIT - self::audit_used());
    }

    /**
     * Whether the site may AI-audit $count more pages.
     *
     * @param int $count Number of pages about to be audited.
     */
    public static function audit_can_run(int $count = 1): bool
    {
        if (self::is_pro()) {
            return true;
        }

        return (self::audit_used() + max(1, $count)) <= self::FREE_AUDIT_PAGE_LIMIT;
    }

    /**
     * Record consumption of the AI-audit quota. No-op for Pro.
     *
     * @param int $count Number of pages audited.
     */
    public static function audit_record_usage(int $count = 1): void
    {
        if (self::is_pro()) {
            return;
        }

        update_option(self::AUDIT_QUOTA_OPTION, self::audit_used() + max(1, $count), false);
    }

    /**
     * Friendly, translatable message shown when a Free site hits the AI
     * audit limit.
     */
    public static function audit_quota_message(): string
    {
        return sprintf(
            /* translators: %d: free AI audit page limit. */
            __('You have reached the free limit of %d AI-audited pages. Upgrade to Pro for unlimited SEO audits.', 'ai-seo-captain'),
            self::FREE_AUDIT_PAGE_LIMIT
        );
    }

    /**
     * Upgrade URL for the plugin's Freemius pricing page.
     */
    public static function upgrade_url(): string
    {
        $fs = self::fs();

        if (null !== $fs && method_exists($fs, 'get_upgrade_url')) {
            return $fs->get_upgrade_url();
        }

        return admin_url('admin.php?page=ai-seo-captain-pricing');
    }
}
