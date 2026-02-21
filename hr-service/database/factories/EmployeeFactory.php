<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'salary' => fake()->numberBetween(40000, 120000),
            'country' => 'USA',
            'ssn' => fake()->numerify('###-##-####'),
            'address' => fake()->address(),
        ];
    }

    public function usa(): static
    {
        return $this->state(fn () => [
            'country' => 'USA',
            'ssn' => fake()->numerify('###-##-####'),
            'address' => fake()->address(),
            'tax_id' => null,
            'goal' => null,
        ]);
    }

    public function germany(): static
    {
        return $this->state(fn () => [
            'country' => 'Germany',
            'tax_id' => 'DE' . fake()->numerify('#########'),
            'goal' => fake()->sentence(),
            'ssn' => null,
            'address' => null,
        ]);
    }
}
