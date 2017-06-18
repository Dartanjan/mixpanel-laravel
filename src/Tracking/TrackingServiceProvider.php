<?php

namespace Duct\Tracking;

use Duct\Tracking\Mixpanel;
use Duct\Models\User;
use Duct\Tracking\TrackingUserObserver;
use Duct\Tracking\Listeners\AuthListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Deals with tracking user actions in Mixpanel
 */
class TrackingServiceProvider extends ServiceProvider
{
    protected $defer = false;

    protected $subscribe = [
        AuthListener::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        User::observe(new TrackingUserObserver());
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Duct\Tracking\Mixpanel', function($app) {
            return new Mixpanel($app->make('request'));
        });
    }

    public function provides()
    {
        return ['Duct\Tracking\Mixpanel'];
    }
}
