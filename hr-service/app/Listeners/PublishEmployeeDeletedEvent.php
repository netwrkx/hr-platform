<?php

namespace App\Listeners;

use App\Events\EmployeeDeleted;
use App\Services\EmployeeEventPublisher;

class PublishEmployeeDeletedEvent
{
    public function __construct(
        private EmployeeEventPublisher $publisher,
    ) {}

    public function handle(EmployeeDeleted $event): void
    {
        $this->publisher->publishDeleted($event->employee);
    }
}
