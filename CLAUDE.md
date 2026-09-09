# CLAUDE.md

Guidance for Claude Code (and any contributor) working in this repository.

## Language convention

**English — only text that renders in the UI (the "view" layer):**

- Displayed strings in `resources/views/**` and `app/Modules/*/Views/**` —
  headings, labels, button text, table column names, placeholders, page titles,
  and flash / validation messages shown to the user.

**Bengali — everything else:**

- Code comments, docblocks, and inline notes in all `.php` files (including
  comments *inside* view files — anything that does not render)
- SQL file comments (`database/**/*.sql`)
- `doc/*.md` and `README.md`
- Commit messages and PR descriptions

**Identifiers stay English** as a practical matter — variable, function, class,
table, and column names. This is not "writing" in the prose sense and the
codebase is already all-English here.

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
