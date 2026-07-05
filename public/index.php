<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (getenv('VERCEL')) {
    $tmpPath = sys_get_temp_dir().'/laravel';

    foreach ([
        $tmpPath,
        $tmpPath.'/cache',
        $tmpPath.'/sessions',
        $tmpPath.'/views',
        $tmpPath.'/logs',
    ] as $path) {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    $serverlessEnv = [
        'APP_CONFIG_CACHE' => $tmpPath.'/cache/config.php',
        'APP_EVENTS_CACHE' => $tmpPath.'/cache/events.php',
        'APP_PACKAGES_CACHE' => $tmpPath.'/cache/packages.php',
        'APP_ROUTES_CACHE' => $tmpPath.'/cache/routes.php',
        'APP_SERVICES_CACHE' => $tmpPath.'/cache/services.php',
        'VIEW_COMPILED_PATH' => $tmpPath.'/views',
        'LOG_CHANNEL' => 'stderr',
        'LOG_STACK' => 'stderr',
        'LOG_PATH' => $tmpPath.'/logs/laravel.log',
    ];

    foreach ($serverlessEnv as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
