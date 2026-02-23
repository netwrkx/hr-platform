<?php

namespace App\Checklist;

interface ChecklistValidator
{
    /**
     * Validate an employee against country-specific checklist rules.
     *
     * @param array $employee The employee data
     * @return array<int, array{field: string, status: string, message: ?string}>
     */
    public function validate(array $employee): array;
}
