<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules — country-aware.
     *
     * Shared: name, last_name, salary, country (USA|Germany only)
     * USA:   ssn (required), address (required, non-empty)
     * Germany: tax_id (DE + 9 digits), goal (required, non-empty)
     */
    public function rules(): array
    {
        $rules = [
            'name'      => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'salary'    => ['required', 'numeric', 'gt:0'],
            'country'   => ['required', 'string', 'in:USA,Germany'],
        ];

        $country = $this->input('country');

        if ($country === 'USA') {
            $rules['ssn']     = ['required', 'string'];
            $rules['address'] = ['required', 'string', 'min:1'];
        }

        if ($country === 'Germany') {
            $rules['tax_id'] = ['required', 'string', 'regex:/^DE\d{9}$/'];
            $rules['goal']   = ['required', 'string', 'min:1'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'country.in'     => 'The selected country is not supported. Supported countries: USA, Germany.',
            'salary.gt'      => 'Salary must be greater than 0.',
            'tax_id.regex'   => 'Tax ID must be in format DE followed by 9 digits (e.g., DE123456789).',
            'address.min'    => 'Address is required and must not be empty.',
            'goal.min'       => 'Goal is required and must not be empty.',
        ];
    }
}
