#!/bin/bash
set -e

echo "Waiting for PostgreSQL to be ready..."
until php -r "
    try {
        \$pdo = new PDO(
            'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        echo 'connected';
    } catch (Exception \$e) {
        exit(1);
    }
"; do
    echo "PostgreSQL not ready — retrying in 2 seconds..."
    sleep 2
done

echo "PostgreSQL is ready."

echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "Starting application..."
exec "$@"
