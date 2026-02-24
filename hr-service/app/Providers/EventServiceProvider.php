<?php

namespace App\Providers;

use App\Events\EmployeeCreated;
use App\Events\EmployeeDeleted;
use App\Events\EmployeeUpdated;
use App\Listeners\PublishEmployeeCreatedEvent;
use App\Listeners\PublishEmployeeDeletedEvent;
use App\Listeners\PublishEmployeeUpdatedEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        EmployeeCreated::class => [
            PublishEmployeeCreatedEvent::class,
        ],
        EmployeeUpdated::class => [
            PublishEmployeeUpdatedEvent::class,
        ],
        EmployeeDeleted::class => [
            PublishEmployeeDeletedEvent::class,
        ],
    ];
}
