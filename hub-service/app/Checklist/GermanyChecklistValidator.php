<?php

namespace App\Checklist;

class GermanyChecklistValidator implements ChecklistValidator
{
    public function validate(array $employee): array
    {
        return [
            $this->checkSalary($employee),
            $this->checkGoal($employee),
            $this->checkTaxId($employee),
        ];
    }

    private function checkSalary(array $employee): array
    {
        $value = $employee['salary'] ?? null;
        if ($value !== null && $value > 0) {
            return ['field' => 'salary', 'status' => 'complete', 'message' => null];
        }
        return ['field' => 'salary', 'status' => 'incomplete', 'message' => 'Salary must be greater than 0'];
    }

    private function checkGoal(array $employee): array
    {
        $value = $employee['goal'] ?? null;
        if ($value !== null && $value !== '') {
            return ['field' => 'goal', 'status' => 'complete', 'message' => null];
        }
        return ['field' => 'goal', 'status' => 'incomplete', 'message' => 'Goal is required'];
    }

    private function checkTaxId(array $employee): array
    {
        $value = $employee['tax_id'] ?? null;
        if ($value !== null && preg_match('/^DE\d{9}$/', $value)) {
            return ['field' => 'tax_id', 'status' => 'complete', 'message' => null];
        }
        return ['field' => 'tax_id', 'status' => 'incomplete', 'message' => 'Tax ID must be DE followed by 9 digits'];
    }
}
