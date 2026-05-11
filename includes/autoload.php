<?php

/**
 * PSR-4 inspired autoloader for the AI_SEO_Keeper namespace.
 *
 * Root namespace:  AI_SEO_Keeper\ClassName       → includes/class-classname.php
 * Sub-namespace:   AI_SEO_Keeper\Admin\ClassName  → includes/admin/class-classname.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'AI_SEO_Keeper\\';

    if (0 !== strncmp($class, $prefix, strlen($prefix))) {
        return;
    }

    $relative   = substr($class, strlen($prefix));
    $parts      = explode('\\', $relative);
    $class_name = array_pop($parts);

    $subdir = ! empty($parts) ? strtolower(implode('/', $parts)) . '/' : '';
    $file   = __DIR__ . '/' . $subdir . 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
