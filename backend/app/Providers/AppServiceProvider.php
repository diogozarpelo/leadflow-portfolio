<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'lead-submissions',
            fn (Request $request): Limit => Limit::perMinute(5)
                ->by($request->ip())
        );
    }
}
