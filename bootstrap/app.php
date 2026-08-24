<?php

if (getenv('VERCEL') === '1') {
    $vercelDefaults = [
        'LOG_CHANNEL' => 'stderr',
        'LOG_STACK' => 'stderr',
        'CACHE_STORE' => 'database',
        'CACHE_DRIVER' => 'database',
        'SESSION_DRIVER' => 'database',
        'SESSION_LIFETIME' => '120',
        'SESSION_SECURE_COOKIE' => 'true',
        'SESSION_SAME_SITE' => 'lax',
        'QUEUE_CONNECTION' => 'database',
        'FILESYSTEM_DISK' => 'local',
        'BROADCAST_CONNECTION' => 'log',
        'MAIL_MAILER' => 'log',
        'REDIS_CLIENT' => 'phpredis',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
    ];

    foreach ($vercelDefaults as $key => $value) {
        $current = getenv($key);

        if ($current === false || $current === '') {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    $storage = '/tmp/storage';

    foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
        $path = $storage.'/'.$dir;

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    putenv('APP_STORAGE_PATH='.$storage);
    $_ENV['APP_STORAGE_PATH'] = $storage;

    $appUrl = getenv('APP_URL');

    if ($appUrl === false || $appUrl === '') {
        $vercelUrl = getenv('VERCEL_URL');

        if ($vercelUrl !== false && $vercelUrl !== '') {
            $appUrl = 'https://'.$vercelUrl;
        }
    } else {
        $appUrl = preg_replace('#^http://#i', 'https://', $appUrl);
    }

    if ($appUrl !== false && $appUrl !== '') {
        putenv('APP_URL='.$appUrl);
        $_ENV['APP_URL'] = $appUrl;
        $_SERVER['APP_URL'] = $appUrl;
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (getenv('VERCEL') === '1') {
            $middleware->trustProxies(at: '*');
        }

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();

if (getenv('VERCEL') === '1') {
    $app->useStoragePath('/tmp/storage');
}

return $app;
