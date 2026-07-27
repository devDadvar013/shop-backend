<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Artisan; // <-- اضافه کنید

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Default API rate limit (per user, falls back to IP for guests).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Stricter limit on the login endpoint to discourage brute-force.
        RateLimiter::for('login', function (Request $request) {
            $key = 'login:' . $request->input('email', '') . '|' . $request->ip();
            return Limit::perMinute(10)->by($key)->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts. Please try again in a minute.',
                ], 429);
            });
        });

        if (app()->environment('production')) {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Migration failed: ' . $e->getMessage());
            }
        }
    }
}