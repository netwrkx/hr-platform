<?php

namespace Tests\Unit;

use App\Http\Requests\EmployeeFormRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmployeeFormRequestTest extends TestCase
{
    private function rules(array $data): \Illuminate\Validation\Validator
    {
        $request = new EmployeeFormRequest();
        $request->merge($data);
        $request->setMethod('POST');

        return Validator::make($data, $request->rules());
    }

    // ── USA Validation Rules ──────────────────────────────────────────

    public function test_usa_valid_payload_passes(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St, NY',
        ]);

        $this->assertTrue($v->passes());
    }

    public function test_usa_requires_ssn(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('ssn', $v->errors()->toArray());
    }

    public function test_usa_requires_address(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('address', $v->errors()->toArray());
    }

    public function test_usa_address_must_not_be_empty(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('address', $v->errors()->toArray());
    }

    public function test_usa_salary_must_be_greater_than_zero(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 0,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('salary', $v->errors()->toArray());
    }

    public function test_usa_salary_must_be_numeric(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 'not-a-number',
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('salary', $v->errors()->toArray());
    }

    // ── Germany Validation Rules ──────────────────────────────────────

    public function test_germany_valid_payload_passes(): void
    {
        $v = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Increase productivity',
        ]);

        $this->assertTrue($v->passes());
    }

    public function test_germany_requires_tax_id(): void
    {
        $v = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'goal' => 'Increase productivity',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('tax_id', $v->errors()->toArray());
    }

    public function test_germany_tax_id_must_match_format(): void
    {
        // DE + 9 digits is valid
        $valid = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Increase productivity',
        ]);
        $this->assertTrue($valid->passes());

        // Too few digits
        $short = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE12345678',
            'goal' => 'Increase productivity',
        ]);
        $this->assertTrue($short->fails());
        $this->assertArrayHasKey('tax_id', $short->errors()->toArray());

        // Wrong prefix
        $wrongPrefix = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'US123456789',
            'goal' => 'Increase productivity',
        ]);
        $this->assertTrue($wrongPrefix->fails());

        // Empty string
        $empty = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => '',
            'goal' => 'Increase productivity',
        ]);
        $this->assertTrue($empty->fails());
    }

    public function test_germany_requires_goal(): void
    {
        $v = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('goal', $v->errors()->toArray());
    }

    public function test_germany_goal_must_not_be_empty(): void
    {
        $v = $this->rules([
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => '',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('goal', $v->errors()->toArray());
    }

    // ── Shared Validation Rules ───────────────────────────────────────

    public function test_name_is_required(): void
    {
        $v = $this->rules([
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors()->toArray());
    }

    public function test_last_name_is_required(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('last_name', $v->errors()->toArray());
    }

    public function test_country_is_required(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('country', $v->errors()->toArray());
    }

    // ── Edge Case: Unsupported Country ────────────────────────────────

    public function test_unsupported_country_fails_validation(): void
    {
        $v = $this->rules([
            'name' => 'Pierre',
            'last_name' => 'Dupont',
            'salary' => 60000,
            'country' => 'France',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('country', $v->errors()->toArray());
    }

    public function test_negative_salary_fails(): void
    {
        $v = $this->rules([
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => -100,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('salary', $v->errors()->toArray());
    }
}
