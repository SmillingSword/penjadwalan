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
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
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
