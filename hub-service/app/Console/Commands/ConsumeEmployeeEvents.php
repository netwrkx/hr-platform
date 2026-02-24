<?php

namespace App\Console\Commands;

use App\Services\EventConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
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
                false,       // insist
                'AMQPLAIN',  // login_method
                null,        // login_response
                'en_US',     // locale
                3.0,         // connection_timeout
                130.0,       // read_write_timeout (> 2 * heartbeat)
                null,        // context
                false,       // keepalive
                60           // heartbeat
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
        $channel->exchange_declare(self::DLQ_EXCHANGE, 'fanout', false, true, false);
        $channel->queue_declare($dlq, false, true, false, false);
        $channel->queue_bind($dlq, self::DLQ_EXCHANGE);

        // Declare main exchange and queue with DLX
        $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
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
                $result = $consumer->processMessage($msg->body);
                $deliveryTag = $msg->delivery_info['delivery_tag'];

                match ($result) {
                    EventConsumer::ACK => $channel->basic_ack($deliveryTag),
                    EventConsumer::REQUEUE => $channel->basic_nack($deliveryTag, false, true),
                    EventConsumer::REJECT => $channel->basic_nack($deliveryTag, false, false),
                };
            }
        );

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

        return Command::SUCCESS;
    }
}
