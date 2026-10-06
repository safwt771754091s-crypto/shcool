<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Render/Cloudflare terminates TLS before the Laravel container.
        // Always generate application URLs and form actions over HTTPS in production.
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
