<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'online',
        'service' => 'Salon Vellora REST API',
        'version' => '1.0.0'
    ]);
});
