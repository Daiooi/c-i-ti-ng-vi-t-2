<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$defaultConfig = require BASE_PATH . '/config/config.example.php';
$localConfig = [];

if (is_file(BASE_PATH . '/config/config.php')) {
    $loaded = require BASE_PATH . '/config/config.php';
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$GLOBALS['app_config'] = array_replace($defaultConfig, $localConfig);

require_once BASE_PATH . '/app/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = (string) app_config('session_path');
    if ($sessionPath !== '' && !is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    if ($sessionPath !== '') {
        session_save_path($sessionPath);
    }

    session_name((string) app_config('session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once BASE_PATH . '/app/database.php';

initialize_database(db());
