#!/bin/sh
set -eu

cd /var/www/html

setup_marker="storage/framework/poc-setup-complete"

run_with_retries() {
    description="$1"
    shift

    attempt=1
    max_attempts="${LARAVEL_SETUP_RETRIES:-30}"

    until "$@"; do
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "$description failed after $max_attempts attempts."
            return 1
        fi

        echo "$description not ready yet; retrying in 2 seconds..."
        attempt=$((attempt + 1))
        sleep 2
    done
}

filament_admin_exists() {
    FILAMENT_ADMIN_EMAIL="$1" php <<'PHP'
<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

exit(App\Models\User::where('email', getenv('FILAMENT_ADMIN_EMAIL'))->exists() ? 0 : 1);
PHP
}

if [ "${COMPOSER_INSTALL_ON_STARTUP:-true}" = "true" ] && [ -f composer.json ]; then
    if [ -f composer.lock ]; then
        composer_hash="$(sha256sum composer.lock | awk '{ print $1 }')"
    else
        composer_hash="$(sha256sum composer.json | awk '{ print $1 }')"
    fi

    stamp_file="vendor/.composer-lock.sha256"
    needs_install=false

    if [ ! -f vendor/autoload.php ]; then
        needs_install=true
    elif [ ! -f "$stamp_file" ]; then
        needs_install=true
    elif [ "$(cat "$stamp_file" 2>/dev/null || true)" != "$composer_hash" ]; then
        needs_install=true
    fi

    if [ "$needs_install" = "true" ]; then
        mkdir -p vendor
        lock_dir="vendor/.composer-install.lock"
        lock_acquired=false

        while [ "$lock_acquired" = "false" ]; do
            if mkdir "$lock_dir" 2>/dev/null; then
                lock_acquired=true
                trap 'rmdir "$lock_dir" 2>/dev/null || true' EXIT INT TERM
            else
                echo "Composer install already running in another container; waiting..."
                sleep 2

                if [ -f vendor/autoload.php ] \
                    && [ -f "$stamp_file" ] \
                    && [ "$(cat "$stamp_file" 2>/dev/null || true)" = "$composer_hash" ]; then
                    needs_install=false
                    break
                fi
            fi
        done

        if [ "$needs_install" = "true" ]; then
            echo "Installing Composer dependencies..."
            composer install --no-interaction --prefer-dist --optimize-autoloader
            echo "$composer_hash" > "$stamp_file"
        fi
    fi
fi

if [ "${LARAVEL_AUTOMATED_SETUP:-false}" = "true" ]; then
    mkdir -p storage/framework
    rm -f "$setup_marker"

    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "Creating local .env from .env.example..."
        cp .env.example .env
    fi

    if [ -f .env ]; then
        app_key="$(grep -E '^APP_KEY=' .env | tail -n 1 | cut -d '=' -f 2- || true)"

        if [ -z "$app_key" ]; then
            echo "Generating Laravel application key..."
            php artisan key:generate --force --no-interaction
        fi
    fi

    run_with_retries "Database migration" php artisan migrate --force --no-interaction

    if [ "${FILAMENT_ADMIN_CREATE:-true}" = "true" ]; then
        admin_name="${FILAMENT_ADMIN_NAME:-NEXUM Admin}"
        admin_email="${FILAMENT_ADMIN_EMAIL:-admin@nexum.local}"
        admin_password="${FILAMENT_ADMIN_PASSWORD:-Password123!}"

        if filament_admin_exists "$admin_email"; then
            echo "Filament admin user already exists: $admin_email"
        else
            echo "Creating Filament admin user: $admin_email"
            php artisan make:filament-user \
                --name="$admin_name" \
                --email="$admin_email" \
                --password="$admin_password" \
                --no-interaction
        fi
    fi

    touch "$setup_marker"
fi

if [ "${LARAVEL_WAIT_FOR_SETUP:-false}" = "true" ]; then
    waited=0
    max_wait="${LARAVEL_SETUP_WAIT_SECONDS:-120}"

    while [ ! -f "$setup_marker" ]; do
        if [ "$waited" -ge "$max_wait" ]; then
            echo "Laravel automated setup did not complete within $max_wait seconds."
            exit 1
        fi

        echo "Waiting for Laravel automated setup to complete..."
        waited=$((waited + 2))
        sleep 2
    done
fi

exec "$@"
