# CLAUDE.md

Guidance for Claude Code (and any contributor) working in this repository.

## Language convention

**Bengali — only these two:**

- `doc/*.md` and `README.md`
- Code comments, docblocks, and inline notes in all `.php`/`.sql` files
  (including comments *inside* view files — anything that does not render or
  reach the user)

**English — everything else**, in particular:

- Displayed strings in `resources/views/**` and `app/Modules/*/Views/**` —
  headings, labels, button text, table column names, placeholders, page titles
- **Any message a user can see**, no matter which file it's written in —
  `RuntimeException` text and `Message::success()/error()` strings thrown from
  Service/Api classes count too, since they surface as toasts/flash messages.
  Being outside `Views/**` does not make a string a "comment"; if a user reads
  it, it's English.
- Commit messages and PR descriptions
- Identifiers (variable, function, class, table, column names) — already the
  case, the codebase is all-English there

Notes:

- This is the rule for **new writing**. Do **not** mass-rewrite existing text to
  match unless explicitly asked. When you edit a file, bring the parts you touch
  in line with the rule.
- Chat conversation with the maintainer may still happen in Bengali; this rule
  governs files committed to the repo, not chat replies.

## Project

Raw PHP (no framework), module-based e-commerce, modelled on `erp_saas`.
Three surfaces: storefront (`/`), admin (`/admin/*`), API (`/api/v1/*`).
Full double-entry accounting behind the catalog. See `doc/` for details
(`doc/00-overview.md` is the entry point).

- `bootstrap.php` — the only global setup (autoload, env, session, route tables)
- `public/index.php` — the only entry point
- `app/Core/` — framework primitives (`Router`, `DB`, `View`, `Env`, `Autoloader`)
- `app/Modules/` — feature modules
- `routes/` — `api.php`, `admin.php`, `web.php` (loaded in that order)
- `database/` — `schema/`, `seed/`, `demo/`, and `install.php`

## Common commands

Runs inside Docker (`../docker-compose.yml`); containers `my_project_nginx`,
`my_project_php`, `my_project_db`.

```bash
# Install / migrate the database (idempotent: CREATE TABLE IF NOT EXISTS + upserts)
docker exec -w /var/www/html/ecommerce my_project_php php database/install.php

# Same, plus demo category tree
docker exec -w /var/www/html/ecommerce my_project_php php database/install.php --demo
```

Default admin after install: `admin@ecommerce.moi` / `admin1234` (change on first login).

## Gotcha: stale bind mount after recreating the project folder

If `~/docker-projects/ecommerce` is deleted and recreated on the host (e.g. a
fresh clone), the running `nginx`/`php` containers keep the old inode and serve
an empty docroot — the site returns PHP-FPM's `File not found.` Fix:

```bash
cd /home/moinul007/docker-projects && docker compose up -d --force-recreate --no-deps nginx php
```

## Gotcha: `public/uploads/` must stay world-writable

`public/uploads/` is a bind mount owned by the host user (uid 1000), but
php-fpm runs as `www-data` inside the container — it can't create the
`categories/`/`products/` subfolders `App\Core\Upload` needs unless the
directory is writable by everyone. If image uploads start failing with
"Could not create the upload folder" (`mkdir(): Permission denied`), fix:

```bash
chmod -R 777 public/uploads
```
