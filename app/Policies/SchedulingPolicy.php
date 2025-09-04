<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class SchedulingPolicy
{
    /**
     * Determine whether the user can view free/busy information.
     */
    public function viewFreeBusy(User $user, User $targetUser): bool
    {
        // Users can view free/busy info for users in the same organization
        $currentOrgId = request()->get('current_organization_id');
        
        if (!$currentOrgId) {
            return false;
        }

        // Check if both users belong to the same organization
        $userInOrg = $user->organizations()->where('organization_id', $currentOrgId)->exists();
        $targetInOrg = $targetUser->organizations()->where('organization_id', $currentOrgId)->exists();
        
        return $userInOrg && $targetInOrg;
    }

    /**
     * Determine whether the user can access scheduling features.
     */
    public function accessScheduling(User $user): bool
    {
        $currentOrgId = request()->get('current_organization_id');
        
        if (!$currentOrgId) {
            return false;
        }

        // Check if user belongs to the current organization
        return $user->organizations()->where('organization_id', $currentOrgId)->exists();
    }

    /**
     * Determine whether the user can create meetings.
     */
    public function createMeeting(User $user): bool
    {
        $currentOrgId = request()->get('current_organization_id');
        
        if (!$currentOrgId) {
            return false;
        }

        // Check if user has permission to create events in the organization
        $userRole = $user->organizations()
            ->where('organization_id', $currentOrgId)
            ->first()?->pivot?->role;

        return in_array($userRole, ['Owner', 'Admin', 'Member']);
    }

    /**
     * Determine whether the user can analyze meeting patterns.
     */
    public function analyzeMeetingPatterns(User $user, User $targetUser): bool
    {
        $currentOrgId = request()->get('current_organization_id');
        
        if (!$currentOrgId) {
            return false;
        }

        // Users can analyze their own patterns or if they're admin/owner
        if ($user->id === $targetUser->id) {
            return true;
        }

        $userRole = $user->organizations()
            ->where('organization_id', $currentOrgId)
            ->first()?->pivot?->role;

        return in_array($userRole, ['Owner', 'Admin']);
    }

    /**
     * Determine whether the user can suggest meeting times.
     */
    public function suggestMeetingTimes(User $user): bool
    {
        return $this->accessScheduling($user);
    }

    /**
     * Determine whether the user can find available slots.
     */
    public function findAvailableSlots(User $user): bool
    {
        return $this->accessScheduling($user);
    }

    /**
     * Determine whether the user can validate meeting times.
     */
    public function validateMeetingTime(User $user): bool
    {
        return $this->accessScheduling($user);
    }

    /**
     * Determine whether the user can check slot availability.
     */
    public function checkSlotAvailability(User $user): bool
    {
        return $this->accessScheduling($user);
    }

    /**
     * Determine whether the user can get next available slot.
     */
    public function getNextAvailableSlot(User $user): bool
    {
        return $this->accessScheduling($user);
    }
}
