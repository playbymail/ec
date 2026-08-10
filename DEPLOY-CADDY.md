# Epimethean Challenge — Deploying to Ubuntu 26.04 with Caddy

This is the deployment guide for **Epimethean Challenge** (`playbymail/ec`) on a
DigitalOcean Ubuntu 26.04 server at **https://ec.pbbgaming.com**.

The model is deliberately boring:

- The server has a plain Git working copy at `/srv/ec`.
- Deploying is `git pull` plus `composer install`, `npm run build`, `migrate`, `optimize`.
- Node.js and npm **are** installed on the server. The Vite build happens there.
- PHP-FPM runs as the `deploy` user, so there is no `setfacl` juggling and nothing
  is owned by two users at once.
- Caddy serves `/srv/ec/public` and terminates TLS automatically.
- The SQLite database and its backups live outside the working copy, at `/srv/ec-data`.
- There is no `releases/` directory and no `current` symlink.

Assumptions:

| Thing | Value |
| --- | --- |
| Repository | `git@github.com:playbymail/ec.git` |
| Domain | `ec.pbbgaming.com` |
| OS | Ubuntu 26.04 |
| Deploy user | `deploy` (already created, has `sudo`) |
| PHP | 8.5 with PHP-FPM (already installed) |
| Web server | Caddy (already installed) |
| App directory | `/srv/ec` |
| Data directory | `/srv/ec-data` |

Everything in sections 1–9 is done **once**. After that, deploying is section 10.

---

## Why Node is installed on the server

The previous guide built assets on a Mac and shipped them with `rsync` to avoid
installing Node. That forced release directories, shared-directory symlinks, ACLs,
and a two-machine checklist for every deploy.

This project's Vite build needs Node anyway — React 19, Tailwind 4, and the
Wayfinder plugin (which boots Laravel during `vite build` to generate typed route
helpers). Installing Node on the server costs about 120 MB of disk and removes all
of that machinery. The build is also reproducible: it runs on the same Linux, same
lockfile, every time.

The one real cost is memory. `vite build` wants roughly 1–2 GB. If the droplet has
1 GB of RAM, add swap — see section 2.1.

---

## 1. Directory layout

```text
/srv/ec/                     # git working copy — the whole app
├── .env                     # not in git, survives every pull
├── public/                  # Caddy's document root
│   └── build/               # vite output, not in git, rebuilt each deploy
├── storage/                 # logs, sessions, cache — not in git, survives pulls
└── scripts/deploy.sh        # the deploy script

/srv/ec-data/                # nothing here is ever touched by git
├── database.sqlite
└── backups/
    └── database-20260810T120000Z.sqlite
```

Files that must survive a deploy are either gitignored (`.env`, `storage/*`,
`public/build`) or live outside the working copy (the database). `git pull` never
touches any of them.

---

## 2. Install the packages the server needs

SSH in as `deploy`:

```bash
ssh deploy@ec.pbbgaming.com
```

### 2.1 Add swap if the droplet has less than 2 GB of RAM

Check first:

```bash
free -h
```

If `Mem` total is under 2 GB and `Swap` is 0, add 2 GB of swap so `vite build`
and `composer install` do not get OOM-killed:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 2.2 Apt packages

```bash
sudo apt update
sudo apt install -y \
    git \
    curl \
    unzip \
    sqlite3 \
    php8.5-cli \
    php8.5-fpm \
    php8.5-sqlite3 \
    php8.5-mbstring \
    php8.5-xml \
    php8.5-curl \
    php8.5-zip \
    php8.5-bcmath \
    php8.5-intl
```

Confirm the SQLite extensions are loaded:

```bash
php -m | grep -Ei 'pdo_sqlite|sqlite3'
```

Both `pdo_sqlite` and `sqlite3` must appear.

### 2.3 Composer

Install from the official installer rather than `apt install composer`, which can
drag in a second PHP version:

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm /tmp/composer-setup.php

composer --version
```

### 2.4 Node.js 24

Ubuntu's packaged Node is usually older than Vite 8 supports (it needs Node 20.19+
or 22.12+). Use NodeSource:

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt install -y nodejs

node -v    # v24.x
npm -v
```

---

## 3. Run PHP-FPM as the deploy user

This is the step that removes all the permission complexity. Give the app its own
FPM pool that runs as `deploy`, listening on a socket the `caddy` user can reach.

```bash
sudo tee /etc/php/8.5/fpm/pool.d/ec.conf > /dev/null <<'EOF'
[ec]
user = deploy
group = deploy

listen = /run/php/php8.5-fpm-ec.sock
listen.owner = deploy
listen.group = caddy
listen.mode = 0660

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php8.5-fpm-ec.log
php_admin_value[memory_limit] = 256M
EOF
```

Optionally disable the default pool — nothing else on this box uses it:

```bash
sudo mv /etc/php/8.5/fpm/pool.d/www.conf /etc/php/8.5/fpm/pool.d/www.conf.disabled
```

Test the configuration and restart:

```bash
sudo php-fpm8.5 -t
sudo systemctl restart php8.5-fpm
```

Confirm the socket:

```bash
ls -l /run/php/php8.5-fpm-ec.sock
```

It should read approximately:

```text
srw-rw---- 1 deploy caddy 0 ... /run/php/php8.5-fpm-ec.sock
```

Because PHP runs as `deploy` and the entire working copy is owned by `deploy`,
`storage/` and `bootstrap/cache/` are writable with no extra work.

### 3.1 Let the deploy script reload services

The deploy script reloads PHP-FPM at the end. Allow that without a password prompt:

```bash
sudo tee /etc/sudoers.d/deploy-ec > /dev/null <<'EOF'
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.5-fpm, /usr/bin/systemctl reload caddy
EOF

sudo chmod 0440 /etc/sudoers.d/deploy-ec
sudo visudo -c
```

---

## 4. Create the directories

```bash
sudo mkdir -p /srv/ec /srv/ec-data/backups
sudo chown -R deploy:deploy /srv/ec /srv/ec-data
sudo chmod 0755 /srv/ec
sudo chmod 0750 /srv/ec-data
```

`/srv/ec` is world-traversable so Caddy can read `public/`. `/srv/ec-data` is not —
only `deploy` (and therefore PHP-FPM) needs it.

---

## 5. Give the server access to GitHub

Generate a passphrase-less key so `git pull` works unattended:

```bash
ssh-keygen -t ed25519 -N '' -C 'ec.pbbgaming.com deploy' -f ~/.ssh/id_ed25519
cat ~/.ssh/id_ed25519.pub
```

Add that public key to the repository at
`https://github.com/playbymail/ec/settings/keys` as a **read-only deploy key**.

Verify:

```bash
ssh -T git@github.com
```

`Hi playbymail/ec! You've successfully authenticated` is the expected response —
GitHub always closes the connection afterwards.

---

## 6. Clone the application

```bash
git clone git@github.com:playbymail/ec.git /srv/ec
cd /srv/ec
git switch main
```

---

## 7. Create the environment file and database

```bash
cd /srv/ec
cp .env.example .env
chmod 0600 .env
nano .env
```

Set at least these values:

```dotenv
APP_NAME="Epimethean Challenge"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ec.pbbgaming.com

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=/srv/ec-data/database.sqlite

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@pbbgaming.com"

VITE_APP_NAME="${APP_NAME}"
```

`APP_DEBUG` stays `false`. `APP_URL` must be the exact `https://` origin — Fortify's
passkey support derives the WebAuthn relying-party ID from it, so a wrong value
breaks passkey registration.

Create the database file:

```bash
touch /srv/ec-data/database.sqlite
chmod 0640 /srv/ec-data/database.sqlite
```

---

## 8. First build

```bash
cd /srv/ec

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

php artisan key:generate

npm ci
npm run build

php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Notes:

- `composer install` runs before `npm run build` because the Wayfinder Vite plugin
  boots Laravel to generate `resources/js/actions` and `resources/js/routes`.
- Never run Composer with `sudo`.
- `php artisan key:generate` is a **one-time** step. `.env` is not in Git and is not
  replaced by deploys, so the key persists. Regenerating it invalidates every
  session and every encrypted value in the database.

Check the build landed:

```bash
test -f public/build/manifest.json && echo "vite build present"
```

---

## 9. Configure Caddy

The Caddy package does not create a log directory, and Caddy refuses to load a
config whose log file it cannot open. Create it first:

```bash
sudo mkdir -p /var/log/caddy
sudo chown caddy:caddy /var/log/caddy
sudo chmod 0755 /var/log/caddy
```

Create the directory only. Do **not** `touch` the log file: Caddy creates it as
`caddy:caddy` mode 0600 on first load, and a file pre-created by `sudo` is owned
by `root` and unopenable by the `caddy` user.

Then edit the Caddyfile:

```bash
sudo nano /etc/caddy/Caddyfile
```

```caddyfile
ec.pbbgaming.com {
	root /srv/ec/public

	encode zstd gzip

	php_fastcgi unix//run/php/php8.5-fpm-ec.sock

	file_server

	header {
		Strict-Transport-Security "max-age=31536000; includeSubDomains"
		X-Content-Type-Options "nosniff"
		X-Frame-Options "SAMEORIGIN"
		Referrer-Policy "strict-origin-when-cross-origin"
	}

	log {
		output file /var/log/caddy/ec.pbbgaming.com.log
	}
}
```

There is no `resolve_root_symlink` here because the document root is a real
directory, not a release symlink.

Caddy rotates that log file on its own — 100 MB per file, 10 kept, 90 days — so
there is no logrotate config to write.

Validate and reload — reload, never stop:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
sudo systemctl reload caddy
```

`caddy validate` adapts and checks the config but never opens the log file or
binds a port, so it can pass on a config that then fails to load. Always confirm
the reload itself succeeded.

Caddy requests the TLS certificate on its own once `ec.pbbgaming.com` resolves to
this server and ports 80 and 443 are open.

### 9.1 Verify

```bash
systemctl status caddy
systemctl status php8.5-fpm

sudo -u caddy test -r /srv/ec/public/index.php && echo "caddy can read index.php"
sudo -u caddy test -w /run/php/php8.5-fpm-ec.sock && echo "caddy can reach php-fpm"

curl -i https://ec.pbbgaming.com/up
```

`/up` is Laravel's health endpoint and should return HTTP 200.

Then click through the app: home page, sign-in, a page that reads the database, a
page that writes to it, and confirm CSS and JS load from `/build/`.

---

## 10. Deploying a change

Push to `main`, then on the server:

```bash
ssh deploy@ec.pbbgaming.com
/srv/ec/scripts/deploy.sh
```

That script does the whole thing:

1. `php artisan down` — maintenance mode
2. `git pull --ff-only origin main`
3. SQLite online backup to `/srv/ec-data/backups/` (keeps the 10 most recent)
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan optimize:clear` — a stale route cache breaks the Wayfinder build
6. `npm ci && npm run build`
7. `php artisan migrate --force`
8. `php artisan optimize`
9. `sudo systemctl reload php8.5-fpm`
10. `php artisan up`

If any step fails the script stops and brings the app back up, so a failed deploy
leaves the previous code running — the working copy may be on the new commit, but
nothing was migrated or re-cached. Read the error, fix it, run the script again.

Useful overrides:

```bash
BRANCH=hotfix/urgent /srv/ec/scripts/deploy.sh
SKIP_NPM=1 /srv/ec/scripts/deploy.sh    # backend-only change, skips the vite build
```

Caddy does not need reloading for a deploy. Its config never changes.

---

## 11. Rollback

Code first. From `/srv/ec`:

```bash
cd /srv/ec
git log --oneline -10
```

Pick the last good commit and deploy it:

```bash
php artisan down
git checkout <sha>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
npm ci && npm run build
php artisan optimize
sudo systemctl reload php8.5-fpm
php artisan up
```

You are now on a detached HEAD. Get back onto the branch once the fix is pushed:

```bash
git switch main
/srv/ec/scripts/deploy.sh
```

### Rolling back the database

Reverting code does **not** revert a migration. If the bad deploy changed the
schema, restore the backup the deploy script took immediately beforehand:

```bash
ls -1t /srv/ec-data/backups/
php artisan down
cp /srv/ec-data/backups/database-TIMESTAMP.sqlite /srv/ec-data/database.sqlite
php artisan up
```

That discards every write since the backup was taken. Decide that is acceptable
before running it.

---

## 12. Troubleshooting

**502 from Caddy.** PHP-FPM is down or the socket path is wrong.

```bash
ls -l /run/php/php8.5-fpm-ec.sock
sudo journalctl -u php8.5-fpm -n 100 --no-pager
```

**500 with a blank page.** Check the application log — `APP_DEBUG` is `false`, so
the browser will not show you anything.

```bash
tail -50 /srv/ec/storage/logs/laravel.log
tail -50 /var/log/php8.5-fpm-ec.log
```

**"Unable to locate file in Vite manifest".** The build did not run or did not
finish. Re-run it and watch for an OOM kill:

```bash
cd /srv/ec && npm run build
dmesg | tail -20
```

**"Database file does not exist" or "readonly database".** The path in `.env` must
be absolute (`/srv/ec-data/database.sqlite`) and the file plus its directory must be
owned by `deploy`.

```bash
ls -l /srv/ec-data/
php artisan config:clear
```

**`git pull` refuses to fast-forward.** Something on the server modified a tracked
file. Find it and discard it:

```bash
cd /srv/ec && git status
git checkout -- <file>
```

**`systemctl reload caddy` fails.** Get the full error — journalctl truncates
lines at the terminal width, and the useful half is on the right:

```bash
sudo journalctl -u caddy -n 5 --no-pager -o cat
```

`setting up custom log 'log0': ... no such file or directory` means
`/var/log/caddy` is missing; create it as shown at the top of section 9.

The same error ending in `permission denied` means the directory exists but the
log **file** does not belong to `caddy` — almost always because it was created
with `sudo touch`. Check the file, not just the directory, and let Caddy make its
own:

```bash
sudo ls -la /var/log/caddy
sudo rm -f /var/log/caddy/ec.pbbgaming.com.log
sudo systemctl reload caddy
```

A failed reload leaves the previously loaded config running, so the site stays up
while you fix it.

**Caddy will not issue a certificate.** DNS is not pointing here yet, or 80/443 are
blocked.

```bash
dig +short ec.pbbgaming.com
sudo journalctl -u caddy -n 100 --no-pager
```

---

## 13. What the server has

```text
git          php8.5-cli / php8.5-fpm     composer
curl         php8.5-sqlite3              node 24 / npm
unzip        php8.5-mbstring             caddy
sqlite3      php8.5-xml, curl, zip, bcmath, intl
```

There are no queue workers, no scheduler entries, and no Reverb or Octane
processes in this application yet. When that changes, add the systemd units or
cron entry and have `scripts/deploy.sh` restart them after `php artisan optimize`.

---

## 14. References

- Laravel deployment — https://laravel.com/docs/13.x/deployment
- Laravel Vite — https://laravel.com/docs/13.x/vite
- Laravel Wayfinder — https://github.com/laravel/wayfinder
- Caddy `php_fastcgi` — https://caddyserver.com/docs/caddyfile/directives/php_fastcgi
- Caddy service user — https://caddyserver.com/docs/running
- PHP-FPM pool configuration — https://www.php.net/manual/en/install.fpm.configuration.php
