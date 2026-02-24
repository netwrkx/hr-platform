<?php

namespace Tests\Unit;

use App\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Tests\TestCase;

class EmployeeResourceTest extends TestCase
{
    public function test_usa_employee_ssn_is_masked(): void
    {
        $employee = [
            'id' => 1,
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ];

        $data = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertEquals('***-**-6789', $data['ssn']);
    }

    public function test_germany_employee_has_no_ssn_masking(): void
    {
        $employee = [
            'id' => 1,
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Increase productivity',
        ];

        $data = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertArrayNotHasKey('ssn', $data);
        $this->assertEquals('DE123456789', $data['tax_id']);
    }

    public function test_usa_employee_null_ssn_stays_null(): void
    {
        $employee = [
            'id' => 1,
            'name' => 'Jane',
            'last_name' => 'Smith',
            'salary' => 80000,
            'country' => 'USA',
            'ssn' => null,
            'address' => '456 Oak Ave',
        ];

        $data = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertNull($data['ssn']);
    }

    public function test_usa_employee_empty_ssn_stays_empty(): void
    {
        $employee = [
            'id' => 1,
            'name' => 'Bob',
            'last_name' => 'Brown',
            'salary' => 60000,
            'country' => 'USA',
            'ssn' => '',
            'address' => '789 Elm St',
        ];

        $data = (new EmployeeResource($employee))->resolve(new Request());

        $this->assertEquals('', $data['ssn']);
    }
}
