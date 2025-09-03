<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view events in their organizations
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        // User can view event if they belong to the same organization as the calendar
        return $user->organizations()->where('organization_id', $event->calendar->organization_id)->exists() &&
               !$event->is_private; // Cannot view private events unless they are the owner or participant
    }

    /**
     * Determine whether the user can view private events.
     */
    public function viewPrivate(User $user, Event $event): bool
    {
        // Can view private events if they are the calendar owner or event participant
        return $event->calendar->owner_user_id === $user->id ||
               $event->participants()->where('email', $user->email)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create events
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        // Calendar owner or organization Admin/Owner can update events
        return $event->calendar->owner_user_id === $user->id || 
               ($user->organizations()->where('organization_id', $event->calendar->organization_id)->exists() && 
                $user->hasAnyRole(['Owner', 'Admin']));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // Calendar owner or organization Owner can delete events
        return $event->calendar->owner_user_id === $user->id || 
               ($user->organizations()->where('organization_id', $event->calendar->organization_id)->exists() && 
                $user->hasRole('Owner'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $this->delete($user, $event);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->hasRole('Owner') && 
               $user->organizations()->where('organization_id', $event->calendar->organization_id)->exists();
    }

    /**
     * Determine whether the user can manage participants.
     */
    public function manageParticipants(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
