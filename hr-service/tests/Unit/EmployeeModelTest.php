<?php

namespace Tests\Unit;

use App\Models\Employee;
use Tests\TestCase;

class EmployeeModelTest extends TestCase
{
    public function test_ssn_is_masked_for_usa_employee(): void
    {
        $employee = new Employee([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertEquals('***-**-6789', $employee->masked_ssn);
    }

    public function test_ssn_masking_handles_null(): void
    {
        $employee = new Employee([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
        ]);

        $this->assertNull($employee->masked_ssn);
    }

    public function test_ssn_masking_with_digits_only(): void
    {
        $employee = new Employee([
            'ssn' => '123456789',
        ]);

        $this->assertEquals('***-**-6789', $employee->masked_ssn);
    }

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
