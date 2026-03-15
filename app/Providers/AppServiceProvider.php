<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
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
        // CRITICAL: without this, POST /broadcasting/auth returns 404
        // and private channel authentication fails with 403.
        Broadcast::routes(['middleware' => ['web', 'auth']]);
    }
}
