<?php

namespace Tests\Unit;

use App\Models\Employee;
use Tests\TestCase;

class EmployeeModelTest extends TestCase
{
    public function test_fillable_includes_all_country_fields(): void
    {
        $employee = new Employee();
        $fillable = $employee->getFillable();

        // Shared fields
        $this->assertContains('name', $fillable);
        $this->assertContains('last_name', $fillable);
        $this->assertContains('salary', $fillable);
        $this->assertContains('country', $fillable);

        // USA fields
        $this->assertContains('ssn', $fillable);
        $this->assertContains('address', $fillable);

        // Germany fields
        $this->assertContains('tax_id', $fillable);
        $this->assertContains('goal', $fillable);
    }

    public function test_salary_is_cast_to_decimal(): void
    {
        $employee = new Employee(['salary' => 75000]);

        $casts = $employee->getCasts();
        $this->assertArrayHasKey('salary', $casts);
    }
}
