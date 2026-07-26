<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'Shop Order Management API',
        'version' => '1.0.0',
        'docs' => '/api',
    ]);
});
