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
                $user = auth()->user();
                $view->with('sidebarLabels', $user->labels()->orderBy('name')->get());

                // ── Workspace data for sidebar ──
                $defaultWs = $user->ensureDefaultWorkspace();

                $ownedWorkspaces = $user->workspaces()
                    ->withCount('notes')
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get();

                $sharedWorkspaces = $user->sharedWorkspaces()
                    ->with(['workspace' => fn ($q) => $q->withCount('notes'), 'workspace.user:id,name,avatar_url'])
                    ->get();

                // Determine active workspace
                $activeWsId = session('active_workspace_id', $defaultWs->id);
                // Validate the active workspace still exists and is accessible
                $validOwned = $ownedWorkspaces->pluck('id')->toArray();
                $validShared = $sharedWorkspaces->pluck('workspace_id')->toArray();
                if (!in_array($activeWsId, array_merge($validOwned, $validShared))) {
                    $activeWsId = $defaultWs->id;
                    session(['active_workspace_id' => $activeWsId]);
                }

                $view->with('sidebarWorkspaces', $ownedWorkspaces);
                $view->with('sidebarSharedWorkspaces', $sharedWorkspaces);
                $view->with('activeWorkspaceId', $activeWsId);
            }
        });
    }
}
