<?php

// Immediate CORS header dispatch for all requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'https://salonvellora.com.lk',
    'http://salonvellora.com.lk',
    'https://api.salonvellora.com.lk',
    'http://api.salonvellora.com.lk',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:8000',
    'http://127.0.0.1:8000'
];

// Use exact origin if allowed, otherwise use the main domain
$allowOrigin = in_array($origin, $allowedOrigins) ? $origin : 'https://salonvellora.com.lk';

header("Access-Control-Allow-Origin: $allowOrigin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Answer browser preflight OPTIONS requests immediately
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
