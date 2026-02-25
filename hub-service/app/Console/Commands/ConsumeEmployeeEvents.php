<?php

namespace App\Console\Commands;

use App\Services\EventConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;
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
            } catch (AMQPHeartbeatMissedException $e) {
                Log::warning('RabbitMQ heartbeat missed, reconnecting...', [
                    'exception' => $e->getMessage(),
                ]);
                $this->warn("Heartbeat missed: {$e->getMessage()}. Reconnecting in " . self::RECONNECT_DELAY . "s...");
            } catch (AMQPConnectionClosedException $e) {
                Log::warning('RabbitMQ connection closed, reconnecting...', [
                    'exception' => $e->getMessage(),
                ]);
                $this->warn("Connection closed: {$e->getMessage()}. Reconnecting in " . self::RECONNECT_DELAY . "s...");
            } catch (AMQPIOException $e) {
                Log::warning('RabbitMQ IO error, reconnecting...', [
                    'exception' => $e->getMessage(),
                ]);
                $this->warn("IO error: {$e->getMessage()}. Reconnecting in " . self::RECONNECT_DELAY . "s...");
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'Broken pipe') || str_contains($e->getMessage(), 'Connection reset')) {
                    Log::warning('RabbitMQ connection lost, reconnecting...', [
                        'exception' => $e->getMessage(),
                    ]);
                    $this->warn("Connection lost: {$e->getMessage()}. Reconnecting in " . self::RECONNECT_DELAY . "s...");
                } else {
                    throw $e;
                }
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

        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $msg) use ($consumer, $channel) {
                $result = $consumer->processMessage($msg->getBody());
                $deliveryTag = $msg->getDeliveryTag();

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
