<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description_md' => 'nullable|string|max:10000',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
            'start_at' => 'sometimes|required|date',
            'end_at' => 'nullable|date|after:start_at',
            'all_day' => 'boolean',
            'timezone' => 'nullable|string|max:64|in:' . implode(',', timezone_identifiers_list()),
            'rrule' => 'nullable|string|max:500',
            'exdates' => 'nullable|array',
            'exdates.*' => 'date',
            'is_private' => 'boolean',
            'participants' => 'nullable|array|max:100',
            'participants.*.email' => 'required_with:participants|email|max:255',
            'participants.*.name' => 'nullable|string|max:255',
            'participants.*.role' => 'nullable|in:required,optional,resource',
            'reminders' => 'nullable|array|max:10',
            'reminders.*.method' => 'required_with:reminders|in:email,popup,sms',
            'reminders.*.minutes_before' => 'required_with:reminders|integer|min:0|max:43200', // Max 30 days
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'title.max' => 'Event title cannot exceed 255 characters.',
            'end_at.after' => 'End time must be after start time.',
            'meeting_link.url' => 'Meeting link must be a valid URL.',
            'timezone.in' => 'Invalid timezone provided.',
            'participants.max' => 'Cannot add more than 100 participants.',
            'participants.*.email.email' => 'Participant email must be valid.',
            'reminders.max' => 'Cannot add more than 10 reminders.',
            'reminders.*.minutes_before.max' => 'Reminder cannot be set more than 30 days before the event.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default timezone if not provided but datetime fields are being updated
        if (($this->has('start_at') || $this->has('end_at')) && !$this->has('timezone')) {
            $this->merge([
                'timezone' => Auth::user()->timezone ?? 'Asia/Jakarta'
            ]);
        }

        // Convert all_day to boolean if provided
        if ($this->has('all_day')) {
            $this->merge([
                'all_day' => filter_var($this->all_day, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Convert is_private to boolean if provided
        if ($this->has('is_private')) {
            $this->merge([
                'is_private' => filter_var($this->is_private, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}
