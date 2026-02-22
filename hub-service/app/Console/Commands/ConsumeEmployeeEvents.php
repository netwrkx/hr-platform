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

    public function handle(EventConsumer $consumer): int
    {
        $queue = $this->option('queue');
        $dlq = "{$queue}.dlq";

        $this->info("Connecting to RabbitMQ...");

        try {
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host', 'localhost'),
                config('rabbitmq.port', 5672),
                config('rabbitmq.user', 'guest'),
                config('rabbitmq.password', 'guest'),
                config('rabbitmq.vhost', '/'),
            );
        } catch (\Throwable $e) {
            $this->error("Failed to connect to RabbitMQ: {$e->getMessage()}");
            Log::critical('RabbitMQ consumer connection failed', [
                'exception' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }

        $channel = $connection->channel();

        // Declare dead-letter exchange and queue
        $channel->exchange_declare(self::DLQ_EXCHANGE, AMQPExchangeType::FANOUT, false, true, false);
        $channel->queue_declare($dlq, false, true, false, false);
        $channel->queue_bind($dlq, self::DLQ_EXCHANGE);

        // Declare main exchange and queue with DLX
        $channel->exchange_declare(self::EXCHANGE, AMQPExchangeType::TOPIC, false, true, false);
        $channel->queue_declare($queue, false, true, false, false, false, [
            'x-dead-letter-exchange' => ['S', self::DLQ_EXCHANGE],
        ]);

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

                match ($result) {
                    EventConsumer::ACK => $channel->basic_ack($msg->getDeliveryTag()),
                    EventConsumer::REQUEUE => $channel->basic_nack($msg->getDeliveryTag(), false, true),
                    EventConsumer::REJECT => $channel->basic_nack($msg->getDeliveryTag(), false, false),
                };
            }
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

        return Command::SUCCESS;
    }
}
