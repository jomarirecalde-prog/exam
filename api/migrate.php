<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain');

try {
    $token = $_GET['token'] ?? '';
    $expected = getenv('DEPLOY_SECRET') ?: '';

    if ($expected === '' || ! hash_equals($expected, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }

    echo 'PHP: '.PHP_VERSION.PHP_EOL;
    echo 'pgsql: '.(extension_loaded('pdo_pgsql') ? 'yes' : 'no').PHP_EOL;
    echo 'DB_CONNECTION: '.(getenv('DB_CONNECTION') ?: 'unset').PHP_EOL;
    echo 'DB_URL set: '.(getenv('DB_URL') ? 'yes' : 'no').PHP_EOL;

    require __DIR__.'/../vendor/autoload.php';

    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo 'Laravel booted'.PHP_EOL;

    $fresh = filter_var($_GET['fresh'] ?? false, FILTER_VALIDATE_BOOL);

    if ($fresh) {
        echo 'Running migrate:fresh --seed'.PHP_EOL;
        $kernel->call('migrate:fresh', ['--force' => true, '--seed' => true]);
    } else {
        echo 'Running migrate --force'.PHP_EOL;
        $kernel->call('migrate', ['--force' => true]);
    }

    echo $kernel->output();

    echo 'Done.'.PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: '.$e->getMessage().PHP_EOL;
    echo $e->getFile().':'.$e->getLine().PHP_EOL;
    echo $e->getTraceAsString();
}
