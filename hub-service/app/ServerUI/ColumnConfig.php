<?php

namespace App\ServerUI;

class ColumnConfig
{
    private const COLUMNS = [
        'USA' => [
            ['key' => 'name', 'display' => 'First Name'],
            ['key' => 'last_name', 'display' => 'Last Name'],
            ['key' => 'salary', 'display' => 'Salary', 'format' => 'currency'],
            ['key' => 'ssn', 'display' => 'SSN', 'masked' => true],
        ],
        'Germany' => [
            ['key' => 'name', 'display' => 'First Name'],
            ['key' => 'last_name', 'display' => 'Last Name'],
            ['key' => 'salary', 'display' => 'Salary', 'format' => 'currency'],
            ['key' => 'goal', 'display' => 'Goal'],
        ],
    ];

    /**
     * @return array<int, array{key: string, display: string}>
     * @throws \InvalidArgumentException
     */
    public function getColumns(string $country): array
    {
        if (!isset(self::COLUMNS[$country])) {
            throw new \InvalidArgumentException("Unsupported country: {$country}");
        }

        return self::COLUMNS[$country];
    }

    /**
     * Mask SSN to ***-**-XXXX format, showing only last 4 digits.
     */
    public static function maskSsn(?string $ssn): ?string
    {
        if ($ssn === null) {
            return null;
        }

        if ($ssn === '') {
            return '';
        }

        $last4 = substr($ssn, -4);

        return "***-**-{$last4}";
    }
}
