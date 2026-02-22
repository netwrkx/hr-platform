<?php

namespace Tests\Feature;

use App\Handlers\EmployeeCreatedHandler;
use App\Handlers\EmployeeDeletedHandler;
use App\Handlers\EmployeeUpdatedHandler;
use App\Services\EventConsumer;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class EventConsumerIntegrationTest extends TestCase
{
    private function validPayload(
        string $eventType = 'EmployeeCreated',
        string $eventId = 'test-uuid-1234',
    ): array {
        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => '2026-02-22T00:00:00+00:00',
            'country' => 'USA',
            'data' => [
                'employee_id' => 1,
                'changed_fields' => [],
                'employee' => [
                    'id' => 1,
                    'name' => 'John',
                    'last_name' => 'Doe',
                    'salary' => 75000,
                    'country' => 'USA',
                ],
            ],
        ];
    }

    // ── Integration: Successful message ack on handler success ────────────

    public function test_successful_message_processing_returns_ack(): void
    {
        $createdHandler = Mockery::mock(EmployeeCreatedHandler::class);
        $createdHandler->shouldReceive('handle')->once();

        $consumer = new EventConsumer(
            $createdHandler,
            Mockery::mock(EmployeeUpdatedHandler::class),
            Mockery::mock(EmployeeDeletedHandler::class),
        );

        $result = $consumer->processMessage(json_encode($this->validPayload()));

        $this->assertEquals(EventConsumer::ACK, $result);
    }

    // ── Integration: Retry logic — handler succeeds on 3rd attempt ───────

    public function test_handler_succeeds_on_third_attempt_after_two_failures(): void
    {
        Log::spy();

        $callCount = 0;
        $handler = Mockery::mock(EmployeeUpdatedHandler::class);
        $handler->shouldReceive('handle')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount <= 2) {
                    throw new \RuntimeException("Attempt $callCount failed");
                }
            });

        $consumer = new EventConsumer(
            Mockery::mock(EmployeeCreatedHandler::class),
            $handler,
            Mockery::mock(EmployeeDeletedHandler::class),
        );

        $payload = $this->validPayload('EmployeeUpdated');
        $body = json_encode($payload);

        $result1 = $consumer->processMessage($body);
        $this->assertEquals(EventConsumer::REQUEUE, $result1);

        $result2 = $consumer->processMessage($body);
        $this->assertEquals(EventConsumer::REQUEUE, $result2);

        $result3 = $consumer->processMessage($body);
        $this->assertEquals(EventConsumer::ACK, $result3);

        // Verify both retry attempts were logged
        Log::shouldHaveReceived('warning')->twice();
    }

    // ── Integration: Dead-letter after 3 failures with critical log ──────

    public function test_dead_letter_after_three_failures_with_critical_log(): void
    {
        Log::spy();

        $handler = Mockery::mock(EmployeeDeletedHandler::class);
        $handler->shouldReceive('handle')
            ->andThrow(new \RuntimeException('persistent failure'));

        $consumer = new EventConsumer(
            Mockery::mock(EmployeeCreatedHandler::class),
            Mockery::mock(EmployeeUpdatedHandler::class),
            $handler,
        );

        $payload = $this->validPayload('EmployeeDeleted');
        $body = json_encode($payload);

        $consumer->processMessage($body); // attempt 1
        $consumer->processMessage($body); // attempt 2
        $result = $consumer->processMessage($body); // attempt 3

        $this->assertEquals(EventConsumer::REJECT, $result);

        // Critical-level log with event details
        Log::shouldHaveReceived('critical')->withArgs(function ($message, $context) {
            return str_contains($message, 'dead-letter')
                && $context['event_type'] === 'EmployeeDeleted'
                && $context['event_id'] === 'test-uuid-1234'
                && is_array($context['exception_messages'])
                && count($context['exception_messages']) === 3;
        });
    }
}
