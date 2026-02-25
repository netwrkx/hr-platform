#!/bin/bash
set -e

echo "Starting RabbitMQ consumer in background..."
php artisan rabbitmq:consume &

echo "Starting application..."
exec "$@"
