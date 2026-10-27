# AP Framework 1.2.0

A PHP 8.1+ framework and admin platform that runs on plain cPanel hosting: no Composer, SSH or Node needed.

## Install
1. Upload everything into the one folder your domain points to (no `public_html` or folder above it needed). The package is already laid out for this: `index.php`, `.htaccess`, `assets/` and `application/` side by side. The `.htaccess` blocks browser access to `application`, `tools`, `tests` and `docs`.
2. Make `application/storage` and `application/config` writable.
3. Open your site in a browser; the installer starts automatically.
4. Optional: run `php tools/fetch-vendor.php` on your computer first and upload `assets/vendor`. Until then Bootstrap and Font Awesome load from a CDN and the admin shows a notice.

Optional hardening: if your host does allow a folder above the web root, move `application/` there; `index.php` finds it automatically. Nothing else changes.

## Updating
1. Upload the files from the update package over your site (same folders, overwrite). Never delete `application/config` or `application/storage`.
2. Open the admin. A yellow banner says the database needs updating: press **Finish update** (or run `php ap migrate`). This applies database changes, gives new permissions to the Administrator role and clears caches.

## Keeping tasks running (scheduler)
Open Admin > System > Scheduled tasks. Nothing is required: the built-in scheduler runs due tasks after normal page views. For reliable timing add a cron job in cPanel (once per minute): `/usr/local/bin/php /path/to/ap schedule:run`, or call the secret web-cron address shown on that page from a free cron service.

## Email
Admin > Settings > Email: choose SMTP (recommended) or PHP mail(). Then Admin > Email > Status and test: test the connection and send yourself a test message. Templates can be edited under Admin > Email > Email templates.

## Test
- `php ap test` runs the built-in tests (also available in Admin > System > Tools when debug is on).
- Tests cover the router, query builder, models, auth (lockout, 2FA, sessions), settings encryption and a smoke render of every view.

## Known limits in 1.0.0
- Not yet run against a real MySQL/MariaDB server. Database logic is tested on SQLite only, so please test the installer on your host.
- The 2FA setup shows the secret and an otpauth URI; there is no QR image yet.
- SMTP is tested against a fake server only; try it with your real mail account (Admin > Email).
- Languages: visitors switch with `/lang/<code>` or the switcher; there are no per-language URLs like `/fr/...` yet.
- Deferred: route caching, email-code 2FA, member front end (Phase 2 follow-up), upload validation service (1.2.0).

## Manual
`documentation/index.html` is the manual for Money: open it in a browser or print it. It is built from the Help Centre articles (`application/Modules/Money/Help.php`) and `CHANGELOG.md` by `php tools/build-docs.php`; `php tools/build-docs.php --check` says whether it is up to date, and the Money tests check it too.

## Building modules
Run `php ap make:module Name` to start one (or copy a small module such as `application/Modules/Footer`). A module is a folder with `module.php` (metadata, permissions, menu, settings, widgets), `routes.php`, controllers, views, `lang/en.php` and `migrations/`.

New in 1.2 for module authors:
- Menu items take `info` (shown behind the page title's info button) and `keywords` (extra words for the admin search).
- `setting_groups` describes a settings page: `label`, `icon`, `category` (the heading in the settings menu), `order`, `description`, `tips` (shown on the right) or `url` for a link to a page elsewhere.
- Settings take `section` (the card they appear in) and `width` (`half` puts two side by side).
- Setting type `repeater` stores a list of rows: give it `fields` (columns with `label`, `type`, `placeholder`, `options`, `width`, `rules`), `add_label` and `max_rows`. `setting()` returns an array of rows.
- Setting type `segmented` shows a few choices as buttons.
- `search` adds results to the top-bar search: `['things' => ['label' => 'Things', 'icon' => 'fa-box', 'permission' => 'things.view', 'handler' => fn(string $term, int $limit): array => [['title' => .., 'subtitle' => .., 'url' => ..]]]]`.
- Dashboard widgets with `'type' => 'stat'` and a `stat` callback returning `value`, `label`, `note`, `url` and `tone` show as number boxes.
- Admin views can fill the sections `actions` (buttons next to the page title) and `subtitle` (a line under the breadcrumbs).

New in 1.37 for module authors:
- `install_step` adds a page to the installer after the admin account: `['key' => .., 'label' => .., 'order' => .., 'view' => 'module::view', 'data' => fn(array $state): array, 'save' => fn(Request $r, array $state): array{errors, value}, 'summary' => fn(array $value): string, 'apply' => fn(DB $db, Settings $settings, array $value, array $state): array]`. The answers are used once the tables exist, and the module is kept on.
- Menu items take `when` (`fn(): bool`): the item is left out of the menu and the admin search while it gives false, for features that can be switched off.
