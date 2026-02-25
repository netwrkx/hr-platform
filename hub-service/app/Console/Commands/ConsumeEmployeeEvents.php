<?php

namespace App\Console\Commands;

use App\Services\EventConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeEmployeeEvents extends Command
{
    protected $signature = 'rabbitmq:consume
                            {--queue=hub.employee.events : The queue name to consume from}';

    protected $description = 'Consume employee events from RabbitMQ and route to handlers';

    private const EXCHANGE = 'hr.events';
    private const DLQ_EXCHANGE = 'hr.events.dlx';
    private const RECONNECT_DELAY = 5;

    public function handle(EventConsumer $consumer): int
    {
        $queue = $this->option('queue');

        while (true) {
            try {
                $this->consumeLoop($consumer, $queue);
            } catch (\Throwable $e) {
                Log::warning('RabbitMQ consumer error, reconnecting...', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
                $this->warn(get_class($e) . ": {$e->getMessage()}. Reconnecting in " . self::RECONNECT_DELAY . "s...");
            }

            sleep(self::RECONNECT_DELAY);
        }
    }

    private function consumeLoop(EventConsumer $consumer, string $queue): void
    {
        $dlq = "{$queue}.dlq";

        $this->info("Connecting to RabbitMQ...");

        $connection = new AMQPStreamConnection(
            host: config('rabbitmq.host', 'localhost'),
            port: config('rabbitmq.port', 5672),
            user: config('rabbitmq.user', 'guest'),
            password: config('rabbitmq.password', 'guest'),
            vhost: config('rabbitmq.vhost', '/'),
            heartbeat: 0,
            read_write_timeout: 0,
        );

        $channel = $connection->channel();

        // Declare dead-letter exchange and queue
        $channel->exchange_declare(self::DLQ_EXCHANGE, AMQPExchangeType::FANOUT, false, true, false);
        $channel->queue_declare($dlq, false, true, false, false);
        $channel->queue_bind($dlq, self::DLQ_EXCHANGE);

        // Declare main exchange and queue with DLX
        $channel->exchange_declare(self::EXCHANGE, AMQPExchangeType::TOPIC, false, true, false);
        $channel->queue_declare($queue, false, true, false, false, false, new \PhpAmqpLib\Wire\AMQPTable([
            'x-dead-letter-exchange' => self::DLQ_EXCHANGE,
        ]));

        // Bind to all employee events
        $channel->queue_bind($queue, self::EXCHANGE, 'employee.#');

        $channel->basic_qos(0, 1, false);

        $this->info("RabbitMQ consumer started. Waiting for messages on [{$queue}]...");

        $command = $this;
        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $msg) use ($consumer, $channel, $command) {
                $body = $msg->getBody();
                $decoded = json_decode($body, true);
                $eventType = $decoded['event_type'] ?? 'unknown';
                $eventId = $decoded['event_id'] ?? 'unknown';
                $command->info("[CONSUMER] Received {$eventType} (id: {$eventId})");

                $result = $consumer->processMessage($body);
                $deliveryTag = $msg->getDeliveryTag();

                $command->info("[CONSUMER] {$eventType} → {$result}");

                match ($result) {
                    EventConsumer::ACK => $channel->basic_ack($deliveryTag),
                    EventConsumer::REQUEUE => $channel->basic_nack($deliveryTag, false, true),
                    EventConsumer::REJECT => $channel->basic_nack($deliveryTag, false, false),
                };
            }
        );

        try {
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            try { $channel->close(); } catch (\Throwable) {}
            try { $connection->close(); } catch (\Throwable) {}
        }
    }
}
