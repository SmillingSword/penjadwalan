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
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Test routes for Pusher events (matching Laravel documentation)
Route::get('/test-pusher', function () {
    event(new App\Events\MyEvent('hello world'));
    return 'MyEvent triggered!';
});

Route::get('/test-message', function () {
    // Create a test message and conversation for demonstration
    $user = App\Models\User::first();
    if (!$user) {
        return 'No users found. Please create a user first.';
    }
    
    $conversation = App\Models\Conversation::first();
    if (!$conversation) {
        return 'No conversations found. Please create a conversation first.';
    }
    
    $message = new App\Models\Message([
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'content' => 'Test message from Laravel documentation format',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $message->id = 1; // Mock ID for testing
    $message->sender = $user;
    
    event(new App\Events\MessageSent($message, $conversation));
    return 'MessageSent event triggered!';
});

Route::get('/test-reminder', function () {
    // Create test data for demonstration
    $user = App\Models\User::first();
    if (!$user) {
        return 'No users found. Please create a user first.';
    }
    
    $event = App\Models\Event::first();
    if (!$event) {
        return 'No events found. Please create an event first.';
    }
    
    $reminder = App\Models\Reminder::first();
    if (!$reminder) {
        return 'No reminders found. Please create a reminder first.';
    }
    
    event(new App\Events\ReminderTriggered($event, $reminder, $user));
    return 'ReminderTriggered event triggered!';
});

require __DIR__.'/auth.php';
