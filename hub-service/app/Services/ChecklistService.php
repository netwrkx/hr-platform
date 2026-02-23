<?php

namespace App\Services;

use App\Checklist\ChecklistValidator;
use App\Checklist\GermanyChecklistValidator;
use App\Checklist\UsaChecklistValidator;

class ChecklistService
{
    /** @var array<string, ChecklistValidator> */
    private array $validators;

    public function __construct(private CacheService $cacheService)
    {
        $this->validators = [
            'USA' => new UsaChecklistValidator(),
            'Germany' => new GermanyChecklistValidator(),
        ];
    }

    /**
     * Evaluate checklist completion for employees of a given country.
     *
     * @param string $country USA | Germany
     * @return array{summary: array, employees: array}
     * @throws \InvalidArgumentException if country is not supported
     */
    public function evaluate(string $country): array
    {
        if (!isset($this->validators[$country])) {
            throw new \InvalidArgumentException("Unsupported country: {$country}");
        }

        return $this->cacheService->rememberChecklist($country, function () use ($country) {
            $employees = $this->cacheService->getEmployeesByCountry($country);
            $validator = $this->validators[$country];

            $results = [];
            $complete = 0;

            foreach ($employees as $employee) {
                $checklist = $validator->validate($employee);
                $completeItems = count(array_filter($checklist, fn ($item) => $item['status'] === 'complete'));
                $totalItems = count($checklist);
                $overallCompletion = $totalItems > 0 ? round(($completeItems / $totalItems) * 100, 2) : 0;

                if ($overallCompletion == 100) {
                    $complete++;
                }

                $results[] = [
                    'id' => $employee['id'],
                    'name' => $employee['name'],
                    'last_name' => $employee['last_name'],
                    'overall_completion' => $overallCompletion,
                    'checklist' => $checklist,
                ];
            }

            $total = count($results);
            $incomplete = $total - $complete;

            return [
                'summary' => [
                    'country' => $country,
                    'total_employees' => $total,
                    'complete' => $complete,
                    'incomplete' => $incomplete,
                    'completion_rate' => $total > 0 ? round(($complete / $total) * 100, 2) : 0,
                ],
                'employees' => $results,
            ];
        });
    }
}
