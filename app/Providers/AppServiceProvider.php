<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
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
        // Response flat (tanpa wrapper `data`) agar kontrak API konsisten dengan paginator Laravel.
        JsonResource::withoutWrapping();

        // Admin bypass policy (Spatie role admin punya permission '*').
        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });
    }
}
