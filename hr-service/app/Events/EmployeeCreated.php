<?php

namespace App\Events;

use App\Models\Employee;

class EmployeeCreated
{
    public function __construct(
        public readonly Employee $employee,
    ) {}
}
