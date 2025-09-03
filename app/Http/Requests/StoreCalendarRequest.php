<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreCalendarRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_default' => 'boolean',
            'is_public' => 'boolean',
            'timezone' => 'nullable|string|max:64|in:' . implode(',', timezone_identifiers_list()),
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Calendar name is required.',
            'name.max' => 'Calendar name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000).',
            'timezone.in' => 'Invalid timezone provided.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default color if not provided
        if (!$this->has('color')) {
            $this->merge([
                'color' => '#3B82F6'
            ]);
        }

        // Set default timezone if not provided
        if (!$this->has('timezone')) {
            $this->merge([
                'timezone' => Auth::user()->timezone ?? 'Asia/Jakarta'
            ]);
        }

        // Convert boolean fields
        if ($this->has('is_default')) {
            $this->merge([
                'is_default' => filter_var($this->is_default, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        if ($this->has('is_public')) {
            $this->merge([
                'is_public' => filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }

    /**
     * Get the validated data with additional fields.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();
        
        // Add organization_id and owner_user_id
        $validated['organization_id'] = $this->get('current_organization_id');
        $validated['owner_user_id'] = Auth::id();
        
        return $validated;
    }
}
