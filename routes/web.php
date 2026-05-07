<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return view('login');
});
Route::post('/auth/login', [AuthController::class, 'auth'])->name('auth');

Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');