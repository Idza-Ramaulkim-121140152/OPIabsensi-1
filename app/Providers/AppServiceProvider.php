<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('face-api', function (Request $request): Limit {
            $perMinute = max((int) env('FACE_API_RATE_LIMIT', 60), 1);

            return Limit::perMinute($perMinute)->by(
                $request->ip().'|'.($request->bearerToken() ?? 'anonymous')
            );
        });
    }
}
