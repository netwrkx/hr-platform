<?php

namespace App\ServerUI;

class SchemaBuilder
{
    private const SUPPORTED_COUNTRIES = ['USA', 'Germany'];

    private const DASHBOARD_WIDGETS = [
        'USA' => [
            [
                'id' => 'employee_count',
                'type' => 'stat',
                'title' => 'Employee Count',
                'data_source' => '/api/employees?country=USA',
                'realtime_channel' => 'employees.USA',
            ],
            [
                'id' => 'average_salary',
                'type' => 'stat',
                'title' => 'Average Salary',
                'data_source' => '/api/employees?country=USA',
                'realtime_channel' => 'employees.USA',
            ],
            [
                'id' => 'completion_rate',
                'type' => 'stat',
                'title' => 'Completion Rate',
                'data_source' => '/api/checklists?country=USA',
                'realtime_channel' => 'checklist.USA',
            ],
        ],
        'Germany' => [
            [
                'id' => 'employee_count',
                'type' => 'stat',
                'title' => 'Employee Count',
                'data_source' => '/api/employees?country=Germany',
                'realtime_channel' => 'employees.Germany',
            ],
            [
                'id' => 'goal_tracking',
                'type' => 'stat',
                'title' => 'Goal Tracking',
                'data_source' => '/api/employees?country=Germany',
                'realtime_channel' => 'employees.Germany',
            ],
        ],
    ];

    private const COMMON_FIELDS = [
        ['name' => 'name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
        ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
        ['name' => 'salary', 'type' => 'number', 'label' => 'Salary', 'required' => true, 'validation' => 'gt:0'],
    ];

    private const COUNTRY_FIELDS = [
        'USA' => [
            ['name' => 'ssn', 'type' => 'text', 'label' => 'SSN', 'required' => true],
            ['name' => 'address', 'type' => 'text', 'label' => 'Address', 'required' => true],
        ],
        'Germany' => [
            ['name' => 'tax_id', 'type' => 'text', 'label' => 'Tax ID', 'required' => true, 'validation' => 'regex:/^DE\\d{9}$/'],
            ['name' => 'goal', 'type' => 'text', 'label' => 'Goal', 'required' => true],
        ],
    ];

    private const VALID_STEPS = ['dashboard', 'employees', 'documentation'];

    /**
     * @throws \InvalidArgumentException
     */
    public function getSchema(string $stepId, string $country): array
    {
        if (!in_array($country, self::SUPPORTED_COUNTRIES)) {
            throw new \InvalidArgumentException("Unsupported country: {$country}");
        }

        if (!in_array($stepId, self::VALID_STEPS)) {
            throw new \InvalidArgumentException("Unknown step: {$stepId}");
        }

        return match ($stepId) {
            'dashboard' => ['widgets' => self::DASHBOARD_WIDGETS[$country]],
            'employees' => ['fields' => array_merge(self::COMMON_FIELDS, self::COUNTRY_FIELDS[$country])],
            'documentation' => ['fields' => []],
        };
    }
}
