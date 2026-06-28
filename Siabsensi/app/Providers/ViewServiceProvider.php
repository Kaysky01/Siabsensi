<?php

namespace App\Providers;

use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Share pending counts with all admin layout views
        View::composer('layouts.admin', function ($view) {
            $view->with([
                'pendingIzin' => IzinSubmission::where('status', 'pending')->count(),
                'pendingKehadiran' => KehadiranSubmission::where('status', 'pending')->count(),
            ]);
        });
    }
}
