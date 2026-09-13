<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // One pagination look for the whole system, styled with the app's
        // own design tokens instead of Laravel's stock gray/blue - same
        // "one file decides" idea as x-ui.status. See resources/views/
        // vendor/pagination/tanza.blade.php and UI-KIT.md section 1.
        Paginator::defaultView('vendor.pagination.tanza');
        Paginator::defaultSimpleView('vendor.pagination.simple-tanza');
    }
}
