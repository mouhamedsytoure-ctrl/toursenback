<?php
// Vercel filesystem is read-only; create writable directories in /tmp
foreach ([
    '/tmp/storage/app/public',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Tell Laravel to use /tmp/storage as its storage directory
putenv('APP_STORAGE=/tmp/storage');
$_ENV['APP_STORAGE'] = '/tmp/storage';

// Bootstrap and run Laravel
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
