<?php

namespace AI_SEO_Captain;

class Activator
{
	public static function activate(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$content_table   = $wpdb->prefix . 'ai_seo_captain_content_index';
		$chat_table      = $wpdb->prefix . 'ai_seo_captain_conversations';
		$message_table   = $wpdb->prefix . 'ai_seo_captain_messages';

		$sql = "CREATE TABLE {$content_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(50) NOT NULL,
			post_type varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			title text NOT NULL,
			slug text NOT NULL,
			permalink text NOT NULL,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			excerpt longtext NULL,
			content_hash varchar(64) NOT NULL,
			modified_gmt datetime NULL,
			indexed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_type_id (object_type, object_id),
			KEY post_type (post_type),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$chat_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(50) NOT NULL,
			title text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY object_lookup (object_type, object_id)
		) {$charset_collate};

		CREATE TABLE {$message_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			content longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id)
		) {$charset_collate};";

		$redirects_table = $wpdb->prefix . 'ai_seo_captain_redirects';
		$sql .= "\n\n		CREATE TABLE {$redirects_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(2048) NOT NULL,
			target_url varchar(2048) NOT NULL DEFAULT '',
			status_code smallint(3) NOT NULL DEFAULT 301,
			type varchar(20) NOT NULL DEFAULT 'redirect',
			hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
			last_hit datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source_url (source_url(191)),
			KEY type (type)
		) {$charset_collate};";

		$runs_table = $wpdb->prefix . 'ai_seo_captain_runs';
		$sql .= "\n\n		CREATE TABLE {$runs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(255) NOT NULL,
			description text NOT NULL,
			page_ids longtext NOT NULL,
			page_count int unsigned NOT NULL DEFAULT 0,
			completed_steps varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta($sql);

		$options = get_option(Settings::OPTION_NAME, array());
		$options = is_array($options) ? wp_parse_args($options, Settings::defaults()) : Settings::defaults();

		if (empty($options['indexnow_key'])) {
			$options['indexnow_key'] = wp_generate_password(32, false, false);
		}

		if (! get_option(Settings::OPTION_NAME)) {
			add_option(Settings::OPTION_NAME, $options);
			return;
		}

		update_option(Settings::OPTION_NAME, $options);

		flush_rewrite_rules();
	}
}
