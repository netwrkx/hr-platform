<?php

namespace App\Checklist;

class UsaChecklistValidator implements ChecklistValidator
{
    public function validate(array $employee): array
    {
        return [
            $this->checkSsn($employee),
            $this->checkSalary($employee),
            $this->checkAddress($employee),
        ];
    }

    private function checkSsn(array $employee): array
    {
        $value = $employee['ssn'] ?? null;
        if ($value !== null && $value !== '') {
            return ['field' => 'ssn', 'status' => 'complete', 'message' => null];
        }
        return ['field' => 'ssn', 'status' => 'incomplete', 'message' => 'SSN is required'];
    }

    private function checkSalary(array $employee): array
    {
        $value = $employee['salary'] ?? null;
        if ($value !== null && $value > 0) {
            return ['field' => 'salary', 'status' => 'complete', 'message' => null];
        }
        return ['field' => 'salary', 'status' => 'incomplete', 'message' => 'Salary must be greater than 0'];
    }

    private function checkAddress(array $employee): array
    {
        $value = $employee['address'] ?? null;
        if ($value !== null && $value !== '') {
            return ['field' => 'address', 'status' => 'complete', 'message' => null];
        }
        return ['field' => 'address', 'status' => 'incomplete', 'message' => 'Address is required'];
    }
}
