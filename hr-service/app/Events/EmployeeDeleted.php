<?php

namespace App\Events;

use App\Models\Employee;

class EmployeeDeleted
{
    public function __construct(
        public readonly Employee $employee,
    ) {}
}
