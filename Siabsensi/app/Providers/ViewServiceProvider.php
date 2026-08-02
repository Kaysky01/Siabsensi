<?php

namespace App\Providers;

use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Auth;
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
            $user = Auth::user();

            if ($user && ($user->role === 'garda' || $user->role === 'timdis') && $user->assigned_kompi) {
                $kompi = $user->assigned_kompi;
                $mhsTable = (new Mahasiswa)->getTable();

                $view->with([
                    'pendingIzin' => IzinSubmission::join($mhsTable, 'izin_submissions.mahasiswa_id', '=', "$mhsTable.id")
                        ->where("$mhsTable.kompi", $kompi)
                        ->where('izin_submissions.status', 'pending')
                        ->count(),
                    'pendingKehadiran' => KehadiranSubmission::join($mhsTable, 'kehadiran_submissions.mahasiswa_id', '=', "$mhsTable.id")
                        ->where("$mhsTable.kompi", $kompi)
                        ->where('kehadiran_submissions.status', 'pending')
                        ->count(),
                ]);
            } else {
                $view->with([
                    'pendingIzin' => IzinSubmission::where('status', 'pending')->count(),
                    'pendingKehadiran' => KehadiranSubmission::where('status', 'pending')->count(),
                ]);
            }
        });
    }
}
