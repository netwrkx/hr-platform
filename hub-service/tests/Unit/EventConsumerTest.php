<?php

namespace Tests\Unit;

use App\Handlers\EmployeeCreatedHandler;
use App\Handlers\EmployeeDeletedHandler;
use App\Handlers\EmployeeUpdatedHandler;
use App\Services\EventConsumer;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class EventConsumerTest extends TestCase
{
    private function makeConsumer(
        ?EmployeeCreatedHandler $createdHandler = null,
        ?EmployeeUpdatedHandler $updatedHandler = null,
        ?EmployeeDeletedHandler $deletedHandler = null,
    ): EventConsumer {
        return new EventConsumer(
            $createdHandler ?? Mockery::mock(EmployeeCreatedHandler::class),
            $updatedHandler ?? Mockery::mock(EmployeeUpdatedHandler::class),
            $deletedHandler ?? Mockery::mock(EmployeeDeletedHandler::class),
        );
    }

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

    // ── Consumer routing ─────────────────────────────────────────────────

    public function test_routes_employee_created_to_created_handler(): void
    {
        $handler = Mockery::mock(EmployeeCreatedHandler::class);
        $handler->shouldReceive('handle')->once()->with(Mockery::type('array'));

        $consumer = $this->makeConsumer(createdHandler: $handler);
        $result = $consumer->processMessage(json_encode($this->validPayload('EmployeeCreated')));

        $this->assertEquals(EventConsumer::ACK, $result);
    }

    public function test_routes_employee_updated_to_updated_handler(): void
    {
        $payload = $this->validPayload('EmployeeUpdated');
        $payload['data']['changed_fields'] = ['salary'];

        $handler = Mockery::mock(EmployeeUpdatedHandler::class);
        $handler->shouldReceive('handle')->once()->with(Mockery::type('array'));

        $consumer = $this->makeConsumer(updatedHandler: $handler);
        $result = $consumer->processMessage(json_encode($payload));

        $this->assertEquals(EventConsumer::ACK, $result);
    }

    public function test_routes_employee_deleted_to_deleted_handler(): void
    {
        $handler = Mockery::mock(EmployeeDeletedHandler::class);
        $handler->shouldReceive('handle')->once()->with(Mockery::type('array'));

        $consumer = $this->makeConsumer(deletedHandler: $handler);
        $result = $consumer->processMessage(json_encode($this->validPayload('EmployeeDeleted')));

        $this->assertEquals(EventConsumer::ACK, $result);
    }

    public function test_unknown_event_type_logs_warning_and_acks(): void
    {
        Log::spy();

        $consumer = $this->makeConsumer();
        $result = $consumer->processMessage(json_encode($this->validPayload('EmployeePromoted')));

        $this->assertEquals(EventConsumer::ACK, $result);
        Log::shouldHaveReceived('warning')->withArgs(function ($message) {
            return str_contains($message, 'Unknown event_type');
        });
    }

    // ── Message deserialisation and payload schema validation ─────────────

    public function test_malformed_json_logs_error_and_rejects_without_retry(): void
    {
        Log::spy();

        $consumer = $this->makeConsumer();
        $result = $consumer->processMessage('not valid json{{{');

        $this->assertEquals(EventConsumer::REJECT, $result);
        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
            return str_contains($message, 'Malformed JSON')
                && isset($context['raw_payload']);
        });
    }

    public function test_missing_event_type_rejects_message(): void
    {
        Log::spy();

        $payload = $this->validPayload();
        unset($payload['event_type']);

        $consumer = $this->makeConsumer();
        $result = $consumer->processMessage(json_encode($payload));

        $this->assertEquals(EventConsumer::REJECT, $result);
    }

    public function test_missing_data_field_rejects_message(): void
    {
        Log::spy();

        $payload = $this->validPayload();
        unset($payload['data']);

        $consumer = $this->makeConsumer();
        $result = $consumer->processMessage(json_encode($payload));

        $this->assertEquals(EventConsumer::REJECT, $result);
    }

    public function test_missing_event_id_rejects_message(): void
    {
        Log::spy();

        $payload = $this->validPayload();
        unset($payload['event_id']);

        $consumer = $this->makeConsumer();
        $result = $consumer->processMessage(json_encode($payload));

        $this->assertEquals(EventConsumer::REJECT, $result);
    }

    // ── Retry logic (nack and requeue on handler failure, max 3 attempts) ─

    public function test_handler_failure_returns_requeue_on_first_attempt(): void
    {
        Log::spy();

        $handler = Mockery::mock(EmployeeCreatedHandler::class);
        $handler->shouldReceive('handle')
            ->andThrow(new \RuntimeException('Something went wrong'));

        $consumer = $this->makeConsumer(createdHandler: $handler);
        $result = $consumer->processMessage(json_encode($this->validPayload()));

        $this->assertEquals(EventConsumer::REQUEUE, $result);
    }

    public function test_handler_failure_returns_requeue_on_second_attempt(): void
    {
        Log::spy();

        $handler = Mockery::mock(EmployeeCreatedHandler::class);
        $handler->shouldReceive('handle')
            ->andThrow(new \RuntimeException('fail'));

        $consumer = $this->makeConsumer(createdHandler: $handler);
        $body = json_encode($this->validPayload());

        $consumer->processMessage($body); // attempt 1
        $result = $consumer->processMessage($body); // attempt 2

        $this->assertEquals(EventConsumer::REQUEUE, $result);
    }

    public function test_handler_failure_returns_reject_after_three_attempts(): void
    {
        Log::spy();

        $handler = Mockery::mock(EmployeeCreatedHandler::class);
        $handler->shouldReceive('handle')
            ->andThrow(new \RuntimeException('fail'));

        $consumer = $this->makeConsumer(createdHandler: $handler);
        $body = json_encode($this->validPayload());

        $consumer->processMessage($body); // attempt 1 → REQUEUE
        $consumer->processMessage($body); // attempt 2 → REQUEUE
        $result = $consumer->processMessage($body); // attempt 3 → REJECT

        $this->assertEquals(EventConsumer::REJECT, $result);
    }

    // ── Structured logging on each retry attempt and on dead-letter ───────

    public function test_each_retry_logged_with_attempt_number_and_error(): void
    {
        Log::spy();

        $handler = Mockery::mock(EmployeeCreatedHandler::class);
        $handler->shouldReceive('handle')
            ->andThrow(new \RuntimeException('handler error'));

        $consumer = $this->makeConsumer(createdHandler: $handler);
        $body = json_encode($this->validPayload());

        $consumer->processMessage($body); // attempt 1

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return str_contains($message, 'failed')
                && $context['attempt_number'] === 1
                && $context['event_type'] === 'EmployeeCreated'
                && $context['event_id'] === 'test-uuid-1234'
                && str_contains($context['exception_message'], 'handler error');
        });
    }

    public function test_critical_log_on_dead_letter_includes_all_exception_messages(): void
    {
        Log::spy();

        $handler = Mockery::mock(EmployeeCreatedHandler::class);
        $handler->shouldReceive('handle')
            ->andThrow(new \RuntimeException('persistent failure'));

        $consumer = $this->makeConsumer(createdHandler: $handler);
        $body = json_encode($this->validPayload());

        $consumer->processMessage($body); // attempt 1
        $consumer->processMessage($body); // attempt 2
        $consumer->processMessage($body); // attempt 3

        Log::shouldHaveReceived('critical')->withArgs(function ($message, $context) {
            return str_contains($message, 'dead-letter')
                && $context['event_type'] === 'EmployeeCreated'
                && $context['event_id'] === 'test-uuid-1234'
                && is_array($context['exception_messages'])
                && count($context['exception_messages']) === 3;
        });
    }
}
