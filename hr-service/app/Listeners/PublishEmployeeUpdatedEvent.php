<?php

namespace App\Listeners;

use App\Events\EmployeeUpdated;
use App\Services\EmployeeEventPublisher;

class PublishEmployeeUpdatedEvent
{
    public function __construct(
        private EmployeeEventPublisher $publisher,
    ) {}

    public function handle(EmployeeUpdated $event): void
    {
        $this->publisher->publishUpdated($event->employee, $event->changedFields);
    }
}
