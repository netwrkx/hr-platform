<?php

namespace App\ServerUI;

class StepRegistry
{
    private const STEPS = [
        'USA' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'path' => '/dashboard', 'order' => 1],
            ['id' => 'employees', 'label' => 'Employees', 'icon' => 'people', 'path' => '/employees', 'order' => 2],
        ],
        'Germany' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'path' => '/dashboard', 'order' => 1],
            ['id' => 'employees', 'label' => 'Employees', 'icon' => 'people', 'path' => '/employees', 'order' => 2],
            ['id' => 'documentation', 'label' => 'Documentation', 'icon' => 'description', 'path' => '/documentation', 'order' => 3],
        ],
    ];

    /**
     * @return array<int, array{id: string, label: string, icon: string, path: string, order: int}>
     * @throws \InvalidArgumentException
     */
    public function getSteps(string $country): array
    {
        if (!isset(self::STEPS[$country])) {
            throw new \InvalidArgumentException("Unsupported country: {$country}");
        }

        return self::STEPS[$country];
    }
}
