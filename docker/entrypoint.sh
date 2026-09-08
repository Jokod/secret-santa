#!/bin/sh
set -e

# Bootstrap (migrations / assets / cache) uniquement pour le serveur web.
if [ "$1" = "frankenphp" ]; then
    echo "[entrypoint] Attente de la base…"
    i=0
    until php bin/console dbal:run-sql "SELECT 1" --quiet >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "[entrypoint] Base indisponible après 120s." >&2
            exit 1
        fi
        sleep 2
    done

    echo "[entrypoint] Sync metadata migrations…"
    php bin/console doctrine:migrations:sync-metadata-storage --no-interaction

    echo "[entrypoint] Migrations…"
    php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing

    echo "[entrypoint] Assets…"
    php bin/console assets:install --no-interaction
    php bin/console asset-map:compile --no-interaction

    echo "[entrypoint] Cache warmup…"
    php bin/console cache:warmup --no-interaction

    echo "[entrypoint] Démarrage de l’application…"
fi

exec "$@"
