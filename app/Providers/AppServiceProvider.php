<?php

namespace App\Providers;

use App\Models\BlotterCase;
use App\Models\MediationSession;
use App\Observers\CaseNotificationObserver;
use App\Observers\HearingNotificationObserver;
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
        // The application UI uses Bootstrap, so Laravel's pagination markup
        // should use the matching Bootstrap 5 renderer everywhere.
        Paginator::useBootstrapFive();
        BlotterCase::observe(CaseNotificationObserver::class);
        MediationSession::observe(HearingNotificationObserver::class);
    }
}
