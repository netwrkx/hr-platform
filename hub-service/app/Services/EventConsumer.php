<?php

namespace App\Services;

use App\Handlers\EmployeeCreatedHandler;
use App\Handlers\EmployeeDeletedHandler;
use App\Handlers\EmployeeUpdatedHandler;
use Illuminate\Support\Facades\Log;

class EventConsumer
{
    public const ACK = 'ack';
    public const REJECT = 'reject';
    public const REQUEUE = 'requeue';

    private const MAX_ATTEMPTS = 3;

    private const REQUIRED_FIELDS = ['event_id', 'event_type', 'data'];

    /** @var array<string, int> Track retry attempts per event_id */
    private array $attempts = [];

    /** @var array<string, list<string>> Track exception messages per event_id */
    private array $exceptionMessages = [];

    private array $handlers;

    public function __construct(
        private EmployeeCreatedHandler $createdHandler,
        private EmployeeUpdatedHandler $updatedHandler,
        private EmployeeDeletedHandler $deletedHandler,
    ) {
        $this->handlers = [
            'EmployeeCreated' => $this->createdHandler,
            'EmployeeUpdated' => $this->updatedHandler,
            'EmployeeDeleted' => $this->deletedHandler,
        ];
    }

    public function processMessage(string $body): string
    {
        $payload = $this->deserialize($body);
        if ($payload === null) {
            return self::REJECT;
        }

        if (!$this->validateSchema($payload)) {
            return self::REJECT;
        }

        $eventType = $payload['event_type'];
        $eventId = $payload['event_id'];

        $handler = $this->handlers[$eventType] ?? null;

        if ($handler === null) {
            Log::warning("Unknown event_type: {$eventType}", [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);
            return self::ACK;
        }

        return $this->executeWithRetry($handler, $payload, $eventId, $eventType);
    }

    private function deserialize(string $body): ?array
    {
        $payload = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Malformed JSON in consumed message', [
                'raw_payload' => $body,
                'json_error' => json_last_error_msg(),
            ]);
            return null;
        }

        return $payload;
    }

    private function validateSchema(array $payload): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($payload[$field])) {
                Log::error("Missing required field: {$field}", [
                    'payload' => $payload,
                ]);
                return false;
            }
        }

        return true;
    }

    private function executeWithRetry(object $handler, array $payload, string $eventId, string $eventType): string
    {
        if (!isset($this->attempts[$eventId])) {
            $this->attempts[$eventId] = 0;
            $this->exceptionMessages[$eventId] = [];
        }

        $this->attempts[$eventId]++;
        $attemptNumber = $this->attempts[$eventId];

        try {
            $handler->handle($payload);

            unset($this->attempts[$eventId], $this->exceptionMessages[$eventId]);

            return self::ACK;
        } catch (\Throwable $e) {
            $this->exceptionMessages[$eventId][] = $e->getMessage();

            if ($attemptNumber >= self::MAX_ATTEMPTS) {
                Log::critical("Message sent to dead-letter after {$attemptNumber} failed attempts", [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'exception_messages' => $this->exceptionMessages[$eventId],
                ]);

                unset($this->attempts[$eventId], $this->exceptionMessages[$eventId]);

                return self::REJECT;
            }

            Log::warning("Handler failed for event, attempt {$attemptNumber}", [
                'attempt_number' => $attemptNumber,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'exception_message' => $e->getMessage(),
            ]);

            return self::REQUEUE;
        }
    }
}
