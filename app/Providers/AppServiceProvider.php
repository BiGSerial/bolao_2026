<?php

namespace App\Providers;

use App\Mail\Transport\KingHostApiTransport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make('mail.manager')->extend('kinghost_api', function () {
            return new KingHostApiTransport();
        });

        Gate::define('admin', function ($user) {
            return (bool) $user->is_admin;
        });

        Gate::define('viewHorizon', function ($user) {
            return (bool) $user->is_admin;
        });
    }
}
