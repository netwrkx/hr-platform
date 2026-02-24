<?php

namespace Tests\Unit;

use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Tests\TestCase;

class EmployeeResourceTest extends TestCase
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

        $resource = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertEquals('***-**-6789', $resource['ssn']);
    }

    public function test_ssn_masking_handles_null(): void
    {
        $employee = new Employee([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
        ]);

        $resource = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertArrayNotHasKey('ssn', $resource);
    }

    public function test_ssn_masking_with_digits_only(): void
    {
        $employee = new Employee([
            'name' => 'Jane',
            'last_name' => 'Smith',
            'salary' => 80000,
            'country' => 'USA',
            'ssn' => '123456789',
            'address' => '456 Oak Ave',
        ]);

        $resource = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertEquals('***-**-6789', $resource['ssn']);
    }
}
