<?php

namespace App\Listeners;

use App\Events\EmployeeCreated;
use App\Services\EmployeeEventPublisher;

class PublishEmployeeCreatedEvent
{
    public function __construct(
        private EmployeeEventPublisher $publisher,
    ) {}

    public function handle(EmployeeCreated $event): void
    {
        $this->publisher->publishCreated($event->employee);
    }
}
