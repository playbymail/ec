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
