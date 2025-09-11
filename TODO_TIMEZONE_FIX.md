# Timezone Handling Fix - EventModal Component

## Problem Identified
- User timezone is Asia/Jakarta but events are showing UTC time when editing
- Frontend EventModal doesn't properly handle timezone conversion for datetime-local inputs
- When editing events, the `formatDateTimeLocal()` function strips timezone info

## Tasks to Complete

### ✅ Analysis Phase
- [x] Analyzed backend timezone handling (working correctly)
- [x] Analyzed EventResource (working correctly) 
- [x] Analyzed EventController (working correctly)
- [x] Identified issue in EventModal.vue

### ✅ Implementation Phase
- [x] Fix formatDateTimeLocal() function to handle timezone properly
- [x] Add user timezone detection from props/auth
- [x] Improve timezone handling when creating/editing events
- [x] Add browser timezone fallback detection
- [x] Test event creation with different timezones
- [x] Test event editing to ensure correct time display

### 📋 Files to Modify
- `resources/js/Components/EventModal.vue` - Main timezone fix

## Expected Outcome
- Events created in Asia/Jakarta timezone should display correctly when editing
- Datetime-local inputs should show the correct local time for the user's timezone
- No more UTC time confusion for users in Asia/Jakarta timezone
