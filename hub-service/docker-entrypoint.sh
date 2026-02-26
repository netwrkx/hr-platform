#!/bin/bash
set -e

# Wait for RabbitMQ to accept connections before starting the consumer
echo "Waiting for RabbitMQ to be ready..."
MAX_RETRIES=30
RETRY_INTERVAL=2
for i in $(seq 1 $MAX_RETRIES); do
    if php -r "
        \$c = @stream_socket_client(
            'tcp://' . (getenv('RABBITMQ_HOST') ?: 'rabbitmq') . ':' . (getenv('RABBITMQ_PORT') ?: '5672'),
            \$errno, \$errstr, 3
        );
        exit(\$c ? 0 : 1);
    " 2>/dev/null; then
        echo "RabbitMQ is ready."
        break
    fi
    echo "RabbitMQ not ready yet (attempt $i/$MAX_RETRIES). Retrying in ${RETRY_INTERVAL}s..."
    sleep $RETRY_INTERVAL
done

echo "Starting RabbitMQ consumer in background..."
php artisan rabbitmq:consume &

echo "Starting application..."
exec "$@"
