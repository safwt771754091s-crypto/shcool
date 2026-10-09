<?php

namespace App\Providers;

use App\Services\Notifications\Channels\InAppChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\NotificationChannelManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationChannelManager::class, function (): NotificationChannelManager {
            return new NotificationChannelManager([
                new InAppChannel,
                new SmsChannel,
                new WhatsAppChannel,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
