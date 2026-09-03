<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Illuminate\Support\Facades\Event;

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
       Event::listen(function (SocialiteWasCalled $event) {
         $event->extendSocialite('linkedin', \SocialiteProviders\LinkedIn\Provider::class);
        }); 
    }

}
