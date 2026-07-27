<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan; 

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/run-migrations', function () {
    try {
        Artisan::call('migrate:fresh', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/force-reset', function () {
    try {
        Artisan::call('db:wipe', ['--force' => true]);
        
        // سپس migrate
        Artisan::call('migrate', ['--force' => true]);
        
        return response()->json([
            'status' => 'success',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/create-admin-user', function () {
    try {
        $user = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@shop.io',
            'password' => bcrypt('password'),
        ]);
        return response()->json(['status' => 'success', 'email' => 'admin@shop.io', 'password' => 'password']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::get('/seed-data', function () {
    try {
        Artisan::call('db:seed', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});
// ================================

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app'    => config('app.name'),
        'time'   => now()->toIso8601String(),
    ]);
});

// Auth (throttled to discourage brute-force)
Route::prefix('auth')->middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Protected routes (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth helpers
    Route::prefix('auth')->group(function () {
        Route::get('/me',          [AuthController::class, 'me']);
        Route::post('/logout',     [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::put('/profile',     [AuthController::class, 'updateProfile']);
    });

    Route::get('/user', fn (Request $r) => $r->user());

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary',       [DashboardController::class, 'summary']);
        Route::get('/recent-orders', [DashboardController::class, 'recentOrders']);
        Route::get('/top-products',  [DashboardController::class, 'topProducts']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/',                   [OrderController::class, 'index']);
        Route::post('/',                  [OrderController::class, 'store']);
        Route::get('/statuses/list',      [OrderController::class, 'statuses']);
        Route::get('/stats',              [OrderController::class, 'stats']);
        Route::get('/{order}',            [OrderController::class, 'show']);
        Route::put('/{order}',            [OrderController::class, 'update']);
        Route::patch('/{order}/status',   [OrderController::class, 'updateStatus']);
        Route::delete('/{order}',         [OrderController::class, 'destroy']);
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/',            [ProductController::class, 'index']);
        Route::post('/',           [ProductController::class, 'store']);
        Route::get('/stats',       [ProductController::class, 'stats']);
        Route::get('/{product}',   [ProductController::class, 'show']);
        Route::put('/{product}',   [ProductController::class, 'update']);
        Route::delete('/{product}',[ProductController::class, 'destroy']);
    });

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('/',               [CustomerController::class, 'index']);
        Route::post('/',              [CustomerController::class, 'store']);
        Route::get('/stats',          [CustomerController::class, 'stats']);
        Route::get('/{customer}',     [CustomerController::class, 'show']);
        Route::put('/{customer}',     [CustomerController::class, 'update']);
        Route::delete('/{customer}',  [CustomerController::class, 'destroy']);
    });
});