#!/usr/bin/env bash
#
# Deploy Epimethean Challenge on the production server.
#
# Run this ON THE SERVER, as the deploy user:
#
#     /srv/ec/scripts/deploy.sh
#
# Environment overrides:
#
#     BRANCH=hotfix/x   deploy a branch other than main
#     SKIP_NPM=1        skip `npm ci && npm run build` (backend-only change)
#     APP_DIR=...       working copy location    (default /srv/ec)
#     DATA_DIR=...      database + backups       (default /srv/ec-data)
#
# See DEPLOY-CADDY.md for the one-time server setup this script assumes.
#
# Everything lives inside main() on purpose: the `git pull` below rewrites this
# very file, and Bash reads scripts incrementally. Parsing the whole body up
# front, then exiting on the same line that calls it, means a changed file can
# never alter what this run executes.

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/srv/ec}"
DATA_DIR="${DATA_DIR:-/srv/ec-data}"
DATABASE="${DATABASE:-$DATA_DIR/database.sqlite}"
BRANCH="${BRANCH:-main}"
FPM_SERVICE="${FPM_SERVICE:-php8.5-fpm}"
BACKUPS_TO_KEEP="${BACKUPS_TO_KEEP:-10}"
SKIP_NPM="${SKIP_NPM:-}"

step() {
    printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$1"
}

warn() {
    printf '\033[1;33m warning:\033[0m %s\n' "$1" >&2
}

die() {
    printf '\033[1;31m error:\033[0m %s\n' "$1" >&2
    exit 1
}

back_up_database() {
    local backup

    if [[ ! -f "$DATABASE" ]]; then
        warn "no SQLite database at $DATABASE; nothing to back up"
        return 0
    fi

    mkdir -p "$DATA_DIR/backups"
    backup="$DATA_DIR/backups/database-$(date -u +%Y%m%dT%H%M%SZ).sqlite"

    sqlite3 "$DATABASE" ".backup '$backup'"
    chmod 0600 "$backup"
    echo "backed up to $backup"

    # Keep only the most recent backups. The names are generated timestamps,
    # so sorting them lexically is the same as sorting them by age.
    ls -1 "$DATA_DIR"/backups/database-*.sqlite \
        | sort -r \
        | tail -n "+$((BACKUPS_TO_KEEP + 1))" \
        | xargs -r rm -f
}

main() {
    [[ -d "$APP_DIR/.git" ]] || die "$APP_DIR is not a git working copy"

    cd "$APP_DIR"

    step "Entering maintenance mode"
    php artisan down --retry=30 || warn "could not enter maintenance mode; continuing"

    # From here on, any failure brings the site back up before exiting.
    trap 'php artisan up || true' EXIT

    step "Pulling $BRANCH"
    git fetch --prune origin
    git pull --ff-only origin "$BRANCH"
    git --no-pager log -1 --format='%h %s (%an, %ar)'

    step "Backing up the database"
    back_up_database

    step "Installing PHP dependencies"
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

    step "Clearing cached config, routes and views"
    # Must happen before the Vite build: Wayfinder boots Laravel during
    # `vite build` and a stale route cache generates stale route helpers.
    php artisan optimize:clear

    if [[ -n "$SKIP_NPM" ]]; then
        step "Skipping frontend build (SKIP_NPM is set)"
    else
        step "Building frontend assets"
        npm ci
        npm run build
        [[ -f public/build/manifest.json ]] || die "vite build produced no manifest"
    fi

    step "Running migrations"
    php artisan migrate --force

    step "Caching config, routes, views and events"
    php artisan optimize

    step "Reloading PHP-FPM"
    sudo -n systemctl reload "$FPM_SERVICE" \
        || warn "could not reload $FPM_SERVICE (see the sudoers step in DEPLOY-CADDY.md)"

    trap - EXIT

    step "Leaving maintenance mode"
    php artisan up

    step "Deployed"
}

main "$@"; exit $?
