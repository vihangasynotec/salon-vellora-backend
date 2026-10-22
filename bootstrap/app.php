<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Support external environment file in AppData (avoids EPERM in Program Files)
if ($customEnv = getenv('LARAVEL_ENV_PATH')) {
    $app->useEnvironmentPath(dirname($customEnv));
    $app->loadEnvironmentFrom(basename($customEnv));
}

// Support external storage path in AppData (avoids EPERM in Program Files)
if ($customStorage = getenv('LARAVEL_STORAGE_PATH')) {
    $app->useStoragePath($customStorage);
}

return $app;
