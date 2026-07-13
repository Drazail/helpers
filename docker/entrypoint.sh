#!/usr/bin/env bash
set -euo pipefail

cd /app

composer install --no-interaction --prefer-dist

if [[ -n "${DB_HOST:-}" ]]; then
    echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    for _ in $(seq 1 60); do
        if php -r "
            try {
                new PDO(
                    sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_DATABASE')),
                    getenv('DB_USERNAME'),
                    getenv('DB_PASSWORD')
                );
                exit(0);
            } catch (Throwable \$e) {
                exit(1);
            }
        " 2>/dev/null; then
            echo "MySQL is ready."
            break
        fi
        sleep 1
    done
fi

mkdir -p build

if compgen -G "tests/fixtures/*.sh" > /dev/null; then
    sed -i 's/\r$//' tests/fixtures/*.sh
    chmod +x tests/fixtures/*.sh
fi

if [[ $# -gt 0 ]]; then
    exec vendor/bin/phpunit -c phpunit.docker.xml "$@"
else
    exec vendor/bin/phpunit -c phpunit.docker.xml
fi
