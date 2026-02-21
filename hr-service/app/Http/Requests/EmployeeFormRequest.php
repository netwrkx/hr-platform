<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // No auth required per PRD
    }

    /**
     * Validation rules — country-aware.
     *
     * Shared: name, last_name, salary, country
     * USA:   ssn (required), address (required)
     * Germany: tax_id (DE + 9 digits), goal (required)
     */
    public function rules(): array
    {
        // TODO: Implement country-specific validation rules
        return [];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        // TODO: Implement custom messages for country-specific rules
        return [];
    }
}
