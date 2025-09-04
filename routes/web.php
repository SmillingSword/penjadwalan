<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PageController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

// Static pages
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');

// Google OAuth routes
Route::get('/auth/google', [App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/api/dashboard/events', [App\Http\Controllers\DashboardController::class, 'getEvents'])
    ->middleware(['auth'])
    ->name('dashboard.events');

Route::post('/api/dashboard/events', [App\Http\Controllers\DashboardController::class, 'createEvent'])
    ->middleware(['auth'])
    ->name('dashboard.create-event');

Route::put('/api/dashboard/events/{id}', [App\Http\Controllers\DashboardController::class, 'updateEvent'])
    ->middleware(['auth'])
    ->name('dashboard.update-event');

Route::delete('/api/dashboard/events/{id}', [App\Http\Controllers\DashboardController::class, 'deleteEvent'])
    ->middleware(['auth'])
    ->name('dashboard.delete-event');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
