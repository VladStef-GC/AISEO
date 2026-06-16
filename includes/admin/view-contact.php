<?php

/**
 * Native Contact & Feedback page view.
 *
 * Replaces the Freemius-hosted contact iframe (and its third-party cookie
 * banner) with a fully on-site form that emails the support address directly.
 *
 * Variables available (set by Admin::render_contact_page):
 *   $support_email   string  Destination address for submissions.
 *   $topics          array   Subject options [value => label].
 *   $prefill_name    string  Current user's display name.
 *   $prefill_email   string  Current user's email.
 *   $sent_status     string  '' | 'ok' | 'fail' (from redirect after submit).
 *   $form_action     string  admin-post.php URL.
 *   $nonce_field     string  Pre-rendered nonce + action hidden fields.
 *
 * @package AI_SEO_Captain
 */

defined('ABSPATH') || exit;

/** @var string $support_email */
/** @var array  $topics */
/** @var string $prefill_name */
/** @var string $prefill_email */
/** @var string $sent_status */
/** @var string $form_action */
/** @var string $nonce_field */

// Branded contact artwork. Resolved from the plugin URL constant so it works
// regardless of install location (no hardcoded paths). Swap the file at
// assets/img/ai-seo-captain-logo.jpg to rebrand — everything else picks it up.
$logo_url = AI_SEO_CAPTAIN_URL . 'assets/img/ai-seo-captain-logo.jpg';
?>
<div class="wrap aisc-wrap aisc-contact">

    <?php if ('ok' === $sent_status) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Thanks! Your message has been sent — we\'ll get back to you by email as soon as we can.', 'ai-seo-captain'); ?></p>
        </div>
    <?php elseif ('fail' === $sent_status) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e('Sorry, your message could not be sent right now. Please try again, or email us directly at', 'ai-seo-captain'); ?>
                <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a>.</p>
        </div>
    <?php endif; ?>

    <div class="aisc-contact-header">
        <div class="aisc-contact-header-logo">
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('AI SEO Captain', 'ai-seo-captain'); ?>" />
        </div>
        <div class="aisc-contact-header-text">
            <h1><?php esc_html_e('Have questions? We\'re happy to help!', 'ai-seo-captain'); ?></h1>
            <p class="aisc-contact-header-brand"><?php esc_html_e('AI SEO Captain', 'ai-seo-captain'); ?></p>
            <p class="aisc-contact-header-sub"><?php esc_html_e('Send us a message or share your feedback — we\'ll do our best to get back to you as soon as we can.', 'ai-seo-captain'); ?></p>
        </div>
    </div>

    <div class="aisc-contact-body">

        <form class="aisc-contact-form" method="post" action="<?php echo esc_url($form_action); ?>">
            <?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_nonce_field output is safe. 
            ?>

            <div class="aisc-contact-field">
                <span class="aisc-contact-icon dashicons dashicons-admin-users"></span>
                <input type="text" name="aisc_name" required value="<?php echo esc_attr($prefill_name); ?>" placeholder="<?php esc_attr_e('Your Name', 'ai-seo-captain'); ?>" />
            </div>

            <div class="aisc-contact-field">
                <span class="aisc-contact-icon dashicons dashicons-email-alt"></span>
                <input type="email" name="aisc_email" required value="<?php echo esc_attr($prefill_email); ?>" placeholder="<?php esc_attr_e('Your Email Address', 'ai-seo-captain'); ?>" />
            </div>

            <fieldset class="aisc-contact-topics">
                <legend class="screen-reader-text"><?php esc_html_e('What can we help you with?', 'ai-seo-captain'); ?></legend>
                <?php $first = true;
                foreach ($topics as $value => $label) : ?>
                    <label class="aisc-contact-topic">
                        <input type="radio" name="aisc_topic" value="<?php echo esc_attr($value); ?>" <?php checked($first); ?> />
                        <span><?php echo esc_html($label); ?></span>
                    </label>
                <?php $first = false;
                endforeach; ?>
            </fieldset>

            <div class="aisc-contact-rating" data-rating="0">
                <span class="aisc-contact-rating-label"><?php esc_html_e('How would you rate AI SEO Captain? (optional)', 'ai-seo-captain'); ?></span>
                <div class="aisc-contact-stars" role="radiogroup" aria-label="<?php esc_attr_e('Rating', 'ai-seo-captain'); ?>">
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <button type="button" class="aisc-contact-star dashicons dashicons-star-empty" data-value="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr(sprintf(_n('%d star', '%d stars', $i, 'ai-seo-captain'), $i)); ?>"></button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="aisc_rating" value="0" />
            </div>

            <div class="aisc-contact-field">
                <textarea name="aisc_message" rows="6" required placeholder="<?php esc_attr_e('How can we help? Tell us about your question, issue, or feedback…', 'ai-seo-captain'); ?>"></textarea>
            </div>

            <button type="submit" class="aisc-contact-submit"><?php esc_html_e('Send Message', 'ai-seo-captain'); ?></button>
        </form>

        <aside class="aisc-contact-faq">
            <h2><?php esc_html_e('Frequently Asked Questions', 'ai-seo-captain'); ?></h2>
            <ul>
                <li><?php esc_html_e('All submitted data is used solely for the purposes of your support request. You will not be added to a mailing list or contacted without your permission, nor will your site be administered after this case is closed.', 'ai-seo-captain'); ?></li>
                <li><?php
                    /* translators: %s: support email address. */
                    echo esc_html(sprintf(__('Prefer email? Reach us anytime at %s.', 'ai-seo-captain'), $support_email));
                    ?></li>
                <li><?php esc_html_e('Include your site URL and a clear description so we can help you faster.', 'ai-seo-captain'); ?></li>
            </ul>
        </aside>

    </div>
</div>
