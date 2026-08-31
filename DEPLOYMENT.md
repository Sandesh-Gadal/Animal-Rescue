# Deployment Guide

Server deployment steps for the Dead Body Mapping System. This is a plain PHP application (no Composer/build step) intended for traditional Apache + MySQL/MariaDB hosting. Docker (`docker-compose.yml`, `docker/Dockerfile`) is a **local development convenience only** — it is not part of the production deployment path described here.

## 1. Requirements

- **PHP 8.2+** with extensions: `pdo_mysql`, `mysqli`, `gd`, `mbstring`, `zip`
- **MySQL 8.0+ or MariaDB 10.11+**
- **Apache** with `mod_rewrite` enabled, and `AllowOverride All` on the document root (the app relies entirely on `.htaccess` for its friendly URLs, e.g. `/report`, `/admin`, `/case/DB-...`, `/admin/export/csv` — without this, every route except the raw `.php` files will 404)
- HTTPS (a valid TLS certificate) — required for browser Geolocation to work on any host other than `localhost`, and for secure session cookies
- Write access for the web server user to `uploads/`

## 2. Fresh installation

1. **Upload the application files** to the web root (e.g. `/public_html/db/`).
2. **Create the database and a dedicated database user**, and grant that user full privileges on the database only (not global). Do **not** import `database.sql` manually — `setup.php` (step 4) does this for you.
3. **Create `config.php`**: copy `config.example.php` to `config.php` and fill in:
   - `db.host` / `db.name` / `db.user` / `db.pass` — your real database credentials
   - `base_url` — your site URL (e.g. `https://example.com/db`), or leave blank to auto-detect from the request
   - `security.setup_key` — replace the placeholder with a long random secret (used once, to gate the install/first-admin step below)
   - `security.session_name`, `max_upload_mb`, `max_photos`, `public_coordinate_decimals` — defaults are usually fine
4. **Run the installer**: visit `/setup.php?key=YOUR_SETUP_KEY` (the key from `config.php` → `security.setup_key`). This creates the full database schema (all tables, at the current schema version) and lets you create the first Admin account in one step. It refuses to run if an admin already exists or the setup key is wrong/still the placeholder, and it's safe to reload if something fails partway (e.g. a DB privilege issue) — just fix the issue and try again.
5. **Set `uploads/` permissions** so the web server user can write to it (typically `755` on the directory is enough if the web server owns it; use `775`/appropriate group ownership if it doesn't). Confirm `uploads/.htaccess` (blocks PHP execution inside uploads) made it onto the server — it's a real security control, not optional.
6. **Delete `setup.php`** (and the `/setup/` folder, a legacy single-purpose admin-creation tool now superseded by `setup.php`) from the server immediately after the admin account is created. They have no further purpose, and leaving them live is a needless attack surface.
7. **Create at least one Operator account** via `/admin/users.php` (logged in as the admin you just created) — this is for staff who need dashboard/case-management access. The public report form itself (`/report`) does not require login; anyone can file a report.
8. **Verify HTTPS is enforced** (redirect HTTP → HTTPS at the server/vhost level; the app does not do this itself).

## 3. Post-deploy verification checklist

- [ ] Homepage (`/`) loads and shows the public stats + map with no PHP errors
- [ ] `/admin/login.php` loads and you can log in with the admin account from step 6
- [ ] `/report` loads without logging in, and a test report can be filed end-to-end (submit → success page → case shows up on `/admin`)
- [ ] `/case/<public_id>` (the tracking page) resolves correctly for a filed report
- [ ] `/admin/export/csv` downloads a CSV — confirms the pretty-URL rewrite rules are active (if this 404s, `AllowOverride`/`mod_rewrite` isn't working)
- [ ] Directly requesting `/config.php`, `/database.sql`, or `/includes/functions.php` in a browser returns a 403/404, not file contents
- [ ] `/setup/create_admin.php` returns 404 or is otherwise gone
- [ ] A photo upload on a test report succeeds, and hitting that uploaded file's URL directly with a `.php` extension appended is blocked (uploads execution guard)

## 4. Upgrading an existing deployment

Each release with schema changes ships an `UPGRADE_vX.Y.txt` file with the exact steps for that version — **read the one matching the version you're upgrading to before doing anything else**. The current one is `UPGRADE_v1.6.txt`.

General pattern used by every upgrade in this app:
1. Back up the database and the existing site files first.
2. Upload/overwrite the new version's files.
3. If the `UPGRADE_vX.Y.txt` says a migration is needed, run the corresponding `migrate_vX_Y.php?key=YOUR_SETUP_KEY` (preferred — it's safe to re-run) or import the matching `migrations/vX.Y_*.sql` file (a one-time script, **not** safe to re-run).
4. Delete the `migrate_vX_Y.php` file from the server once it reports success.
5. Spot-check an existing case in `/admin` to confirm nothing broke.

## 5. Security checklist before going live

- [ ] `config.php`'s `security.setup_key` is a real random value, not the placeholder
- [ ] `config.php` is not readable via a direct browser request (covered by `.htaccess`, but verify — some hosts ignore `.htaccess` `<FilesMatch>` blocks depending on config)
- [ ] `/setup/` has been deleted after the first admin was created
- [ ] Any `migrate_vX_Y.php` files have been deleted after use
- [ ] Database user has no privileges beyond its own database
- [ ] HTTPS is enforced site-wide
- [ ] Admin/Operator account passwords are strong (the app enforces 10+ characters at creation, but pick real passwords, not the minimum)
- [ ] Reporter/operator accounts you don't recognize should be deactivated via `/admin/users.php`

## 6. Troubleshooting

**Pretty URLs (`/report`, `/admin`, `/case/...`) 404, but the raw `.php` files work.**
`mod_rewrite` isn't enabled, or `AllowOverride` for the document root isn't set to `All` (or at least `FileInfo`), so `.htaccess` is being ignored. Fix at the Apache vhost/server config level — this app cannot work around it from userland.

**Geolocation ("Use My Exact Location") doesn't work.**
Browsers only allow the Geolocation API on secure contexts (`https://` or `localhost`). Confirm the site is served over valid HTTPS.

**Photo uploads fail.**
Check `uploads/` is writable by the web server user, and that `security.max_upload_mb` in `config.php` isn't larger than PHP's own `upload_max_filesize`/`post_max_size` in `php.ini` (the app's own limit is enforced in code, but PHP will reject the upload first if its ini limits are smaller).

**"Configuration missing" error on every page.**
`config.php` doesn't exist yet — copy it from `config.example.php` and fill in real values (see step 4 above).
