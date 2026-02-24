<?php

namespace App\Events;

use App\Models\Employee;

class EmployeeUpdated
{
    public function __construct(
        public readonly Employee $employee,
        public readonly array $changedFields,
    ) {}
}
