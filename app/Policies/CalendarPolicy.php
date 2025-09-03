<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CalendarPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view calendars in their organizations
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Calendar $calendar): bool
    {
        // User can view calendar if they belong to the same organization
        return $user->organizations()->where('organization_id', $calendar->organization_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Users with Admin or Owner role can create calendars
        return $user->hasAnyRole(['Owner', 'Admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Calendar $calendar): bool
    {
        // Owner of the calendar or organization Admin/Owner can update
        return $calendar->owner_user_id === $user->id || 
               ($user->organizations()->where('organization_id', $calendar->organization_id)->exists() && 
                $user->hasAnyRole(['Owner', 'Admin']));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Calendar $calendar): bool
    {
        // Owner of the calendar or organization Owner can delete
        return $calendar->owner_user_id === $user->id || 
               ($user->organizations()->where('organization_id', $calendar->organization_id)->exists() && 
                $user->hasRole('Owner'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Calendar $calendar): bool
    {
        return $this->delete($user, $calendar);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Calendar $calendar): bool
    {
        return $user->hasRole('Owner') && 
               $user->organizations()->where('organization_id', $calendar->organization_id)->exists();
    }
}
