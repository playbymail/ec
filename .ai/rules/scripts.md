---
paths:
  - 'scripts/**'
---

# Scripts

## Production deploys run on the server, not from a workstation
Production is a plain git working copy at /srv/ec on the Ubuntu server. Deploying is `ssh deploy@ec.pbbgaming.com` then `/srv/ec/scripts/deploy.sh` — nothing is built or uploaded from a Mac.

Node is installed on the server on purpose so `vite build` runs there. An earlier design kept Node off the server and paid for it with release directories, a `current` symlink, shared-dir symlinks and setfacl ACLs. Do not reintroduce any of that; see DEPLOY-CADDY.md.

PHP-FPM runs as the `deploy` user in its own pool (/etc/php/8.5/fpm/pool.d/ec.conf), so one user owns everything and no ACLs are needed. The SQLite database lives outside the working copy at /srv/ec-data/ so `git pull` cannot touch it.

In deploy.sh, `php artisan optimize:clear` must stay before the Vite build: Wayfinder boots Laravel during `vite build` and a stale route cache generates stale route helpers. The script body stays inside `main()` because `git pull` rewrites the file while bash is still reading it.

## Editing production .env does nothing until the config cache is rebuilt
deploy.sh ends with `php artisan optimize`, which writes bootstrap/cache/config.php. Once that file exists Laravel never parses .env again, and `env()` returns null outside config/ files — so copying a new .env to /srv/ec/.env changes nothing, and secrets read as unset.

After changing .env on the server:

    ssh deploy@ec.pbbgaming.com 'cd /srv/ec && php artisan config:cache && sudo -n systemctl reload php8.5-fpm'

`config:cache` clears before it writes, so it is one step. The FPM reload drops opcache's compiled copy of the old config.php. Re-running deploy.sh also works but pays for a full npm ci + Vite build; `SKIP_NPM=1` avoids that.

Symptom to recognise: `php artisan config:show <key>` returns the old value (or NULL for anything env-only) while .env plainly shows the new one.
