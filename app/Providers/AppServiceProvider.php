<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

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
        // Register broadcasting auth route for Pusher
        Broadcast::routes(['middleware' => ['web', 'auth']]);

        // Force HTTPS in production (Render, etc.)
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::addNamespace('components', resource_path('components'));

        View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                $view->with('sidebarLabels', auth()->user()->labels()->orderBy('name')->get());
            }
        });
    }
}
