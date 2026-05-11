<?php

/**
 * PHPUnit bootstrap for AI SEO Keeper unit tests.
 *
 * Loads the plugin autoloader and defines lightweight WP function stubs
 * so pure-PHP classes can be tested without a live WordPress installation.
 */

declare(strict_types=1);

// Prevent WordPress from loading when running tests.
define('ABSPATH', __DIR__ . '/../');
define('AI_SEO_KEEPER_VERSION', '1.2.0');
define('AI_SEO_KEEPER_PATH', __DIR__ . '/../');
define('AI_SEO_KEEPER_URL', 'http://localhost/');
define('WPINC', 'wp-includes');

// Plugin autoloader.
require_once __DIR__ . '/../includes/autoload.php';

// WP function stubs (only what unit-testable classes actually call).
require_once __DIR__ . '/stubs/wp-stubs.php';
