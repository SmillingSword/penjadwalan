<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        
        // Get user statistics
        $stats = [
            'eventsCount' => $user->calendars()->withCount('events')->get()->sum('events_count'),
            'calendarsCount' => $user->calendars()->count(),
            'joinedDate' => $user->created_at,
            'lastLogin' => $user->last_seen_at ?? $user->updated_at,
        ];

        return Inertia::render('Profile/ModernEdit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $this->getAvatarUrl($user),
                'google_id' => $user->google_id,
                'location' => $user->location,
                'timezone' => $user->timezone ?? 'Asia/Jakarta',
                'locale' => $user->locale ?? 'en',
                'working_hours_start' => $user->working_hours_start,
                'working_hours_end' => $user->working_hours_end,
                'working_days' => $user->working_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'notification_preferences' => $user->notification_preferences ?? [],
                'email_notifications_enabled' => $user->email_notifications_enabled ?? true,
                'browser_notifications_enabled' => $user->browser_notifications_enabled ?? true,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'last_seen_at' => $user->last_seen_at,
                'email_verified_at' => $user->email_verified_at,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists and is not from Google
            if ($user->avatar && !$user->google_id && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if it exists and is not from Google
        if ($user->avatar && !$user->google_id && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        
        $user->update(['avatar' => $avatarPath]);

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * Remove avatar
     */
    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Only delete if it's not from Google and exists in storage
        if ($user->avatar && !$user->google_id && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Delete user's avatar if it exists and is not from Google
        if ($user->avatar && !$user->google_id && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Get the proper avatar URL
     */
    private function getAvatarUrl($user)
    {
        if (!$user->avatar) {
            return null;
        }

        // If it's a Google avatar (full URL), return as is
        if (filter_var($user->avatar, FILTER_VALIDATE_URL)) {
            return $user->avatar;
        }

        // If it's a local avatar, prepend with storage URL
        return asset('storage/' . $user->avatar);
    }
}
