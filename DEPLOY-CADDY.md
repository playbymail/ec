# Epimethean Challenge — Laravel 13 Deployment with Caddy

This document describes a simple, production-oriented deployment for **Epimethean Challenge** (`playbymail/ec`) to a DigitalOcean Ubuntu 26.04 server.

The deployment model is:

- Development and frontend builds happen on the Mac.
- The Mac uploads a complete application release with `rsync`.
- Node.js / npm are **not** required on the production server.
- Composer runs on the production server to install PHP dependencies for Linux.
- Caddy serves only Laravel's `public/` directory.
- SQLite, `.env`, and Laravel's `storage/` directory live outside individual releases.
- Releases are activated with an atomic `current` symlink.
- Old releases remain available for a quick code rollback.

The examples assume:

- Repository: `https://github.com/playbymail/ec`
- Deployment user: `deploy`
- PHP-FPM user: `www-data` (Ubuntu default)
- Caddy service user: `caddy`
- Ubuntu 26.04 default PHP: PHP 8.5
- Application root: `/srv/ec`
- Production domain: replace `ec.example.com` with the real domain
- SSH target: replace `YOUR_SERVER` with the server hostname or IP

---

## 1. Directory Layout

Use `/srv/ec` rather than deploying directly into `/var/www`.

```text
/srv/ec/
├── current -> /srv/ec/releases/20260810T120000Z-a1b2c3d
├── releases/
│   ├── 20260810T120000Z-a1b2c3d/
│   └── 20260812T181500Z-e4f5a6b/
└── shared/
    ├── .env
    ├── database/
    │   ├── database.sqlite
    │   └── backups/
    └── storage/
```

Each release contains the application source and `public/build` assets created on the Mac.

The following survive every release:

- `/srv/ec/shared/.env`
- `/srv/ec/shared/database/database.sqlite`
- `/srv/ec/shared/storage`

Each release gets symlinks to the shared `.env` and `storage` directory. The production `.env` explicitly points Laravel at the shared SQLite database.

Caddy always serves:

```text
/srv/ec/current/public
```

The `current` symlink changes when a new release is activated.

---

# 2. One-Time Server Preparation

SSH into the server:

```bash
ssh deploy@YOUR_SERVER
```

## 2.1 Verify PHP and PHP-FPM

Ubuntu 26.04 ships PHP 8.5 by default.

```bash
php -v
systemctl status php8.5-fpm
```

Confirm the FPM socket:

```bash
ls -l /run/php/php*-fpm.sock
```

These instructions assume:

```text
/run/php/php8.5-fpm.sock
```

If the installed PHP version differs, use the actual socket name in the Caddyfile and service commands below.

## 2.2 Install Deployment Utilities

Caddy and PHP are assumed to be installed already.

Install Composer, SQLite support, the SQLite command-line tool, `rsync`, and ACL support:

```bash
sudo apt update
sudo apt install -y \
    composer \
    php-sqlite3 \
    sqlite3 \
    rsync \
    acl
```

Verify the PHP SQLite extensions:

```bash
php -m | grep -Ei 'pdo_sqlite|sqlite3'
```

You should see both `pdo_sqlite` and `sqlite3`.

Restart PHP-FPM if the SQLite extension was newly installed:

```bash
sudo systemctl restart php8.5-fpm
```

Verify Composer:

```bash
composer --version
```

## 2.3 Let Caddy Connect to PHP-FPM

The Caddy systemd service runs as user `caddy`. PHP-FPM's Unix socket must therefore allow the `caddy` group to connect.

Open the default FPM pool configuration:

```bash
sudo nano /etc/php/8.5/fpm/pool.d/www.conf
```

Find the `listen` settings and make sure these values are active:

```ini
listen = /run/php/php8.5-fpm.sock
listen.owner = www-data
listen.group = caddy
listen.mode = 0660
```

Do **not** change the PHP worker user:

```ini
user = www-data
group = www-data
```

Check the FPM configuration:

```bash
sudo php-fpm8.5 -t
```

Then restart FPM:

```bash
sudo systemctl restart php8.5-fpm
```

Confirm the socket now has group `caddy`:

```bash
ls -l /run/php/php8.5-fpm.sock
```

It should look approximately like:

```text
srw-rw---- 1 www-data caddy ... /run/php/php8.5-fpm.sock
```

## 2.4 Create the Application Directories

```bash
sudo mkdir -p \
    /srv/ec/releases \
    /srv/ec/shared/database/backups \
    /srv/ec/shared/storage

sudo chown -R deploy:www-data /srv/ec
```

Caddy needs to be able to traverse `/srv/ec` and the release directories, but it does not need access to `shared`.

```bash
sudo chmod 0755 /srv/ec
sudo chmod 2755 /srv/ec/releases
sudo chmod 2750 /srv/ec/shared
sudo chmod 2770 /srv/ec/shared/database
sudo chmod 2770 /srv/ec/shared/database/backups
sudo chmod 2770 /srv/ec/shared/storage
```

Laravel runs as `www-data`; deployment commands run as `deploy`. Give both users persistent write access to the shared database and storage trees:

```bash
sudo setfacl -R \
    -m u:deploy:rwx,u:www-data:rwx \
    /srv/ec/shared/database \
    /srv/ec/shared/storage

sudo setfacl -R \
    -d -m u:deploy:rwx,u:www-data:rwx \
    /srv/ec/shared/database \
    /srv/ec/shared/storage
```

The default ACL is important: files later created by PHP-FPM remain writable by the deployment user, and files created by deployment commands remain writable by PHP-FPM.

---

# 3. First Build on the Mac

From the local `ec` repository:

```bash
cd /path/to/ec
```

Make sure the intended source is checked out:

```bash
git switch main
git pull --ff-only
git status
```

The working tree should be clean before a production deployment.

## 3.1 Install Local PHP Dependencies

The EC Vite configuration uses Laravel Wayfinder. Wayfinder invokes Laravel during the Vite build, so the PHP dependencies need to exist on the Mac before `npm run build`.

```bash
composer install
```

Clear any locally cached route table before Wayfinder runs:

```bash
php artisan route:clear
```

## 3.2 Install Frontend Dependencies

EC uses npm and has a `package-lock.json`, so use `npm ci` for a reproducible build:

```bash
npm ci
```

## 3.3 Build the Production Assets

```bash
npm run build
```

This generates Laravel/Vite production assets under:

```text
public/build/
```

Do not run the Vite development server for a production deployment.

## 3.4 Optional Pre-Deployment Check

Before uploading a release, run the project's tests/checks appropriate to the state of the project.

At minimum, make sure the production asset build completed successfully and that:

```bash
test -f public/build/manifest.json && echo "Vite build present"
```

---

# 4. Upload the First Release from the Mac

Set a server variable:

```bash
SERVER="deploy@YOUR_SERVER"
```

Generate a release name containing a UTC timestamp and Git commit:

```bash
RELEASE="$(date -u +%Y%m%dT%H%M%SZ)-$(git rev-parse --short HEAD)"
echo "$RELEASE"
```

Create the release directory remotely:

```bash
ssh "$SERVER" "mkdir -p /srv/ec/releases/$RELEASE"
```

Upload the application:

```bash
rsync -az --delete \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='.env.production' \
    --exclude='.env.backup' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='database/*.sqlite*' \
    --exclude='public/hot' \
    --exclude='bootstrap/cache/*.php' \
    --exclude='.phpunit.cache' \
    --exclude='.DS_Store' \
    ./ \
    "$SERVER:/srv/ec/releases/$RELEASE/"
```

The upload **does include `public/build/`**. That is intentional: those assets were built on the Mac and are what Caddy will serve in production.

Remember the release name; the next section uses it.

---

# 5. Prepare the First Release on the Server

SSH to the server:

```bash
ssh deploy@YOUR_SERVER
```

Set the release name to the value generated on the Mac:

```bash
RELEASE="20260810T120000Z-a1b2c3d"
RELEASE_DIR="/srv/ec/releases/$RELEASE"
```

Replace the example with the actual release name.

## 5.1 Normalize Source Permissions

The application source should be owned by `deploy` and readable by Caddy and PHP-FPM.

```bash
sudo chown -R deploy:deploy "$RELEASE_DIR"

find "$RELEASE_DIR" -type d -exec chmod 0755 {} +
find "$RELEASE_DIR" -type f -exec chmod 0644 {} +

chmod 0755 "$RELEASE_DIR/artisan"
```

Do this **before** Composer creates `vendor/`; do not run this normalization over an installed `vendor/` tree.

## 5.2 Initialize Shared Laravel Storage

For the first release, copy Laravel's storage skeleton into the shared directory:

```bash
rsync -a "$RELEASE_DIR/storage/" /srv/ec/shared/storage/
```

Apply the shared storage ACLs again after the copy:

```bash
sudo setfacl -R \
    -m u:deploy:rwx,u:www-data:rwx \
    /srv/ec/shared/storage

sudo setfacl -R \
    -d -m u:deploy:rwx,u:www-data:rwx \
    /srv/ec/shared/storage
```

Replace the release's `storage` directory with the shared symlink:

```bash
rm -rf "$RELEASE_DIR/storage"
ln -s /srv/ec/shared/storage "$RELEASE_DIR/storage"
```

## 5.3 Create the Production Environment File

Copy the example into the shared location:

```bash
cp "$RELEASE_DIR/.env.example" /srv/ec/shared/.env
sudo chown deploy:www-data /srv/ec/shared/.env
sudo chmod 0640 /srv/ec/shared/.env
```

Link it into the release:

```bash
ln -s /srv/ec/shared/.env "$RELEASE_DIR/.env"
```

Edit the production environment:

```bash
nano /srv/ec/shared/.env
```

Start with at least:

```dotenv
APP_NAME="Epimethean Challenge"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ec.example.com

DB_CONNECTION=sqlite
DB_DATABASE=/srv/ec/shared/database/database.sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Replace `ec.example.com` with the real hostname.

Review the mail settings and any other application-specific environment values before production use.

`APP_DEBUG` must remain `false` in production.

## 5.4 Create the SQLite Database

```bash
touch /srv/ec/shared/database/database.sqlite
sudo chown deploy:www-data /srv/ec/shared/database/database.sqlite
sudo chmod 0660 /srv/ec/shared/database/database.sqlite
```

Reapply the database ACL:

```bash
sudo setfacl \
    -m u:deploy:rw,u:www-data:rw \
    /srv/ec/shared/database/database.sqlite
```

## 5.5 Install PHP Dependencies

Change into the release:

```bash
cd "$RELEASE_DIR"
```

Install production Composer dependencies:

```bash
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction
```

Do not run Composer with `sudo`.

## 5.6 Generate the Laravel Application Key

This is done **once** for a new production environment:

```bash
php artisan key:generate
```

Because `.env` is shared, future releases retain the same `APP_KEY`.

Do not generate a new application key on later deployments.

## 5.7 Make Laravel's Release Cache Writable

`storage` is shared. `bootstrap/cache` belongs to the individual release.

```bash
sudo chown -R deploy:www-data "$RELEASE_DIR/bootstrap/cache"
sudo chmod -R g+rwX "$RELEASE_DIR/bootstrap/cache"
```

## 5.8 Run the Initial Database Migrations

```bash
php artisan migrate --force
```

## 5.9 Optimize Laravel for Production

```bash
php artisan optimize
```

Laravel's `optimize` command caches production configuration, routes, events, and views.

## 5.10 Activate the Release

Create a temporary symlink and atomically move it into place:

```bash
ln -s "$RELEASE_DIR" /srv/ec/current.next
mv -Tf /srv/ec/current.next /srv/ec/current
```

Verify it:

```bash
readlink -f /srv/ec/current
```

It should resolve to the new release.

---

# 6. Initial Caddyfile

Caddy must serve **only** Laravel's `public` directory.

Edit:

```bash
sudo nano /etc/caddy/Caddyfile
```

Use:

```caddyfile
ec.example.com {
    root /srv/ec/current/public

    encode zstd gzip

    php_fastcgi unix//run/php/php8.5-fpm.sock {
        resolve_root_symlink
    }

    file_server
}
```

Replace `ec.example.com` with the real DNS hostname.

If the server is using a PHP-FPM version other than 8.5, change the socket path to the value shown by:

```bash
ls -l /run/php/php*-fpm.sock
```

### Why `resolve_root_symlink`?

The Caddy document root contains `/srv/ec/current`, which is intentionally a symlink to the active release. `resolve_root_symlink` tells Caddy's PHP FastCGI handling to resolve that deployment symlink to the actual release path.

### HTTPS

When the hostname's DNS records point at the server and ports 80 and 443 are reachable, Caddy will obtain and manage the site's public TLS certificate automatically.

---

# 7. Validate and Reload Caddy

Validate the configuration before loading it:

```bash
sudo caddy validate \
    --config /etc/caddy/Caddyfile \
    --adapter caddyfile
```

If validation succeeds:

```bash
sudo systemctl reload caddy
```

Do not stop Caddy for normal configuration changes; reload it.

Check status:

```bash
systemctl status caddy
systemctl status php8.5-fpm
```

Verify that Caddy can read the application entry point:

```bash
sudo -u caddy test -r /srv/ec/current/public/index.php \
    && echo "Caddy can read public/index.php"
```

Verify that Caddy can connect to the PHP-FPM socket:

```bash
sudo -u caddy test -r /run/php/php8.5-fpm.sock \
    && sudo -u caddy test -w /run/php/php8.5-fpm.sock \
    && echo "Caddy can access PHP-FPM socket"
```

---

# 8. Smoke Test

Laravel 13 provides `/up` as its default health endpoint.

From the server:

```bash
curl -i https://ec.example.com/up
```

From the Mac:

```bash
curl -i https://ec.example.com/up
```

A healthy application should return HTTP 200.

Then visit the application in a browser and exercise at least:

- the home page;
- sign-in / authentication pages;
- a page that reads the database;
- a page that writes to the database;
- CSS and JavaScript assets under `public/build`.

Useful logs:

```bash
sudo journalctl -u caddy -n 100 --no-pager
sudo journalctl -u php8.5-fpm -n 100 --no-pager
tail -f /srv/ec/shared/storage/logs/laravel.log
```

---

# 9. Normal Subsequent Deployment

The first deployment has extra initialization. Later deployments are shorter.

## 9.1 On the Mac: Update, Build, and Upload

From the repository:

```bash
cd /path/to/ec

git switch main
git pull --ff-only
git status
```

Install the locked PHP dependencies needed locally by Laravel / Wayfinder:

```bash
composer install
```

Clear any stale local route cache before the Wayfinder-enabled Vite build:

```bash
php artisan route:clear
```

Install the locked frontend dependencies and build:

```bash
npm ci
npm run build
```

Create the release identifier:

```bash
SERVER="deploy@YOUR_SERVER"
RELEASE="$(date -u +%Y%m%dT%H%M%SZ)-$(git rev-parse --short HEAD)"

echo "$RELEASE"
```

Create the destination:

```bash
ssh "$SERVER" "mkdir -p /srv/ec/releases/$RELEASE"
```

Upload:

```bash
rsync -az --delete \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='.env.production' \
    --exclude='.env.backup' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='database/*.sqlite*' \
    --exclude='public/hot' \
    --exclude='bootstrap/cache/*.php' \
    --exclude='.phpunit.cache' \
    --exclude='.DS_Store' \
    ./ \
    "$SERVER:/srv/ec/releases/$RELEASE/"
```

## 9.2 On the Server: Prepare the Release

SSH to the server:

```bash
ssh deploy@YOUR_SERVER
```

Set the release:

```bash
RELEASE="THE_RELEASE_NAME_FROM_THE_MAC"
RELEASE_DIR="/srv/ec/releases/$RELEASE"
```

Normalize the uploaded source **before** installing Composer dependencies:

```bash
sudo chown -R deploy:deploy "$RELEASE_DIR"

find "$RELEASE_DIR" -type d -exec chmod 0755 {} +
find "$RELEASE_DIR" -type f -exec chmod 0644 {} +
chmod 0755 "$RELEASE_DIR/artisan"
```

Merge any new Laravel storage skeleton directories into shared storage:

```bash
rsync -a "$RELEASE_DIR/storage/" /srv/ec/shared/storage/
```

Replace release-local storage with the shared storage symlink:

```bash
rm -rf "$RELEASE_DIR/storage"
ln -s /srv/ec/shared/storage "$RELEASE_DIR/storage"
```

Link the production environment:

```bash
ln -s /srv/ec/shared/.env "$RELEASE_DIR/.env"
```

Reapply shared storage ACLs:

```bash
sudo setfacl -R \
    -m u:deploy:rwx,u:www-data:rwx \
    /srv/ec/shared/storage

sudo setfacl -R \
    -d -m u:deploy:rwx,u:www-data:rwx \
    /srv/ec/shared/storage
```

Install the PHP dependencies:

```bash
cd "$RELEASE_DIR"

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction
```

Make the release cache writable:

```bash
sudo chown -R deploy:www-data "$RELEASE_DIR/bootstrap/cache"
sudo chmod -R g+rwX "$RELEASE_DIR/bootstrap/cache"
```

## 9.3 Put the Application into Maintenance Mode

For this small SQLite-backed application, favor a short maintenance window over trying to do schema changes while requests are active.

If there is already a current release:

```bash
cd /srv/ec/current
php artisan down --retry=60
```

Because `storage/` is shared, the maintenance state is visible to both the old and new release.

## 9.4 Back Up SQLite Before Migrating

Never make a plain `cp` of a SQLite database while it may be active.

Use SQLite's online backup command:

```bash
BACKUP="/srv/ec/shared/database/backups/database-$(date -u +%Y%m%dT%H%M%SZ).sqlite"

sqlite3 /srv/ec/shared/database/database.sqlite \
    ".backup '$BACKUP'"

chmod 0660 "$BACKUP"
```

Confirm the backup exists:

```bash
ls -lh "$BACKUP"
```

## 9.5 Migrate and Optimize the New Release

```bash
cd "$RELEASE_DIR"

php artisan migrate --force
php artisan optimize
```

If either command fails, **do not switch `current`**.

Bring the existing release back online:

```bash
cd /srv/ec/current
php artisan up
```

Then investigate the failure.

## 9.6 Activate the New Release

When migration and optimization succeed:

```bash
ln -s "$RELEASE_DIR" /srv/ec/current.next
mv -Tf /srv/ec/current.next /srv/ec/current
```

Bring the application online through the newly active release:

```bash
cd /srv/ec/current
php artisan up
```

Verify:

```bash
readlink -f /srv/ec/current
curl -i https://ec.example.com/up
```

A normal code deployment does **not** require a Caddy reload because the Caddyfile still points at `/srv/ec/current/public`.

## 9.7 Long-Running Laravel Processes

If EC later runs queue workers, Reverb, Octane, or other long-running Laravel services, those processes must be reloaded after activating new code.

Laravel 13 provides:

```bash
cd /srv/ec/current
php artisan reload
```

This is not necessary while the application has no long-running Laravel workers.

---

# 10. Rollback

The release layout makes a code rollback straightforward, but database migrations require care.

List releases:

```bash
ls -1dt /srv/ec/releases/*
```

Find the previous release, for example:

```text
/srv/ec/releases/20260810T120000Z-a1b2c3d
```

Put the application into maintenance mode:

```bash
cd /srv/ec/current
php artisan down
```

Switch `current` back:

```bash
PREVIOUS="/srv/ec/releases/20260810T120000Z-a1b2c3d"

ln -s "$PREVIOUS" /srv/ec/current.next
mv -Tf /srv/ec/current.next /srv/ec/current
```

Bring it back:

```bash
cd /srv/ec/current
php artisan up
```

Verify:

```bash
curl -i https://ec.example.com/up
```

## Database Warning

Switching application code does **not** undo a database migration.

If a deployment included a schema change that is incompatible with the previous release, restoring the previous code alone may not be sufficient.

That is why every deployment takes a SQLite backup immediately before `php artisan migrate --force`.

Restore a database backup only after deliberately deciding that the data changes made since the backup can be discarded.

---

# 11. Release Cleanup

Keep a few known-good releases for rollback.

For example, after a successful deployment:

```bash
ls -1dt /srv/ec/releases/*
```

Once confident in the new release, manually remove old releases that are no longer needed:

```bash
rm -rf /srv/ec/releases/OLD_RELEASE_NAME
```

Never remove the directory returned by:

```bash
readlink -f /srv/ec/current
```

Do not automatically delete SQLite backups as part of the same command until a backup-retention policy has been decided.

---

# 12. What Runs Where

## Mac

The Mac needs the full development toolchain:

```text
Git
PHP
Composer
Node.js
npm
```

The Mac performs:

```text
composer install
php artisan route:clear
npm ci
npm run build
rsync upload
```

The Vite build is done on the Mac because EC uses React, Vite, Tailwind, and Laravel Wayfinder.

## Production Server

The production server needs:

```text
Caddy
PHP 8.5 / PHP-FPM
PHP SQLite extension
Composer
SQLite
rsync
ACL utilities
```

It does **not** need:

```text
Node.js
npm
Bun
```

The production server performs:

```text
composer install --no-dev ...
php artisan migrate --force
php artisan optimize
```

---

# 13. Deployment Checklist

For a routine release:

```text
[ ] Mac working tree is the intended commit and clean
[ ] composer install succeeds on Mac
[ ] php artisan route:clear succeeds
[ ] npm ci succeeds
[ ] npm run build succeeds
[ ] public/build/manifest.json exists
[ ] new release directory created on server
[ ] rsync upload succeeds
[ ] shared .env linked
[ ] shared storage linked
[ ] production composer install succeeds
[ ] application enters maintenance mode
[ ] SQLite backup succeeds
[ ] php artisan migrate --force succeeds
[ ] php artisan optimize succeeds
[ ] current symlink switched
[ ] php artisan up succeeds
[ ] /up returns HTTP 200
[ ] browser smoke test succeeds
```

---

# 14. Reference Notes

This layout follows the important deployment requirements from Laravel and Caddy:

- Laravel's web server document root must be the application's `public/` directory.
- Laravel requires `storage` and `bootstrap/cache` to be writable by the PHP process.
- Laravel recommends `php artisan optimize` during production deployment.
- Laravel production environments must use `APP_DEBUG=false`.
- Laravel Vite production assets are created with `npm run build`.
- EC's Wayfinder Vite plugin invokes Laravel route generation during the Vite build, so Laravel must be available on the Mac and stale route caches should be cleared before building.
- Caddy's `php_fastcgi` directive is designed for PHP applications using an `index.php` front controller.
- Caddy supports `resolve_root_symlink` specifically for deployments where the web root contains a release-switching symlink.
- Caddy's systemd package runs as user `caddy`.
- PHP-FPM Unix sockets require filesystem permissions that allow the web server to connect.

Official references:

- Laravel 13 deployment:
  https://laravel.com/docs/13.x/deployment
- Laravel 13 Vite:
  https://laravel.com/docs/13.x/vite
- Laravel Wayfinder:
  https://github.com/laravel/wayfinder
- Caddy `php_fastcgi`:
  https://caddyserver.com/docs/caddyfile/directives/php_fastcgi
- Caddy `root`:
  https://caddyserver.com/docs/caddyfile/directives/root
- Caddy systemd service:
  https://caddyserver.com/docs/running
- PHP-FPM pool configuration:
  https://www.php.net/manual/en/install.fpm.configuration.php
