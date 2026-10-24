# Linky Dz

Linky Dz is a self-hosted link-in-bio and NFC/QR card platform built with plain
PHP and MySQL. It lets an administrator create short token-based link pages,
generate matching QR codes and printable card PDFs, manage orders and delivery
pricing, and give customers a small portal to customise their own page.

## Features

- Token-based public link pages (`/u/{token}`) with profiles, links, and themes
- QR code generation and printable card PDFs (including CMYK output)
- Bulk card/label generation and ZIP export for orders
- Product catalogue, order capture, delivery pricing, and order management
- Customer portal for profile, background, and link customisation
- Page-view and link-click analytics
- Admin dashboard with authentication, rate limiting, and CSRF protection
- Optional Telegram notifications for new orders

## Tech stack

- PHP 8.2 (tested with PHP 8.2.12) with extensions: `pdo_mysql`, `curl`,
  `gd`, `mbstring`, `fileinfo`, `zip`
- MySQL 5.7+/MariaDB 10.4+ (`utf8mb4`)
- Apache 2.4 with `mod_rewrite` and `AllowOverride All`
- Bundled [FPDF](fpdf/) and [phpqrcode](phpqrcode/) libraries
- Python 3 with `PyMuPDF` and `Pillow` (optional, only for CMYK PDF conversion)

## Installation

1. Clone the repository into your web root, for example
   `C:\xampp\htdocs\linktree`.
2. Create a MySQL database (default name: `lokpixtreee`).
3. Import the schema files in this order:

   ```bash
   mysql -u root -p lokpixtreee < schema.sql
   mysql -u root -p lokpixtreee < schema_update.sql
   mysql -u root -p lokpixtreee < analytics_schema.sql
   mysql -u root -p lokpixtreee < add_phone_number_column.sql
   ```

   `schema.sql` contains structure only: no admin account, tokens, or customer
   data are included.

4. Copy `.env.example` to `.env` and set the database and recovery values:

   ```bash
   copy .env.example .env    # Windows
   cp .env.example .env      # Linux/macOS
   ```

5. Make `uploads/`, `qr-codes/`, and `backups/` writable by the web server.
6. Create the first admin account: set `RECOVERY_USER`, `RECOVERY_EMAIL`,
   `RECOVERY_PASS`, and a long random `RECOVERY_KEY` in `.env`, open
   `/reset-admin.php`, and submit the recovery key. Afterwards, remove or
   disable `reset-admin.php` on production.
7. Optional, for CMYK PDF conversion:

   ```bash
   pip install -r requirements.txt
   ```

   Set `PYTHON_BIN` in `.env` if `python` is not on the web server's `PATH`.

## Running

Serve the project through Apache (the app relies on `.htaccess` rewrites and
will not work correctly with `php -S`). With XAMPP, place the folder in
`htdocs` and open:

- Public site: `http://localhost/linktree/`
- Admin login: `http://localhost/linktree/login`
- Customer portal: `http://localhost/linktree/portal-login`

## Default credentials

No admin account is seeded. Create one with `reset-admin.php` as described
above, then log in and change the password immediately.

## Project structure

```text
.
├── index.php                 # Public landing page
├── login.php / auth.php      # Admin authentication
├── dashboard.php             # Admin dashboard
├── linkpage.php              # Public token-based link page
├── portal.php                # Customer portal
├── order.php                 # Order page
├── api-*.php                 # JSON endpoints
├── qr-generator.php          # QR card generation
├── api-bulk-action.php       # Bulk PDF/ZIP generation
├── config.php                # Configuration and .env loader
├── database.php              # PDO singleton
├── security.php              # Input sanitisation and CSRF
├── rate-limiter.php          # Login/global rate limiting
├── schema*.sql               # Database structure and migrations
├── fpdf/                     # Bundled FPDF library
├── phpqrcode/                # Bundled QR library
├── uploads/                  # Runtime user uploads (not tracked)
├── qr-codes/                 # Generated QR/card images (not tracked)
└── backups/                  # Local database backups (not tracked)
```

## Configuration

Configuration is read from real environment variables or from `.env` in the
project root. `.env` is ignored by git; use `.env.example` as the template.

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | `lokpixtreee` | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | empty | Database password |
| `BASE_URL` | auto-detected | Public base URL, e.g. `https://example.com` |
| `SITE_NAME` | `Linky Dz` | Brand name |
| `RECOVERY_USER` | `admin` | Username created by `reset-admin.php` |
| `RECOVERY_EMAIL` | `admin@example.com` | Email created by `reset-admin.php` |
| `RECOVERY_PASS` | empty | Password for the recovery account |
| `RECOVERY_KEY` | empty | Shared secret required by `reset-admin.php` |
| `TELEGRAM_BOT_TOKEN` | empty | Bot token for order notifications |
| `TELEGRAM_CHAT_ID` | empty | Telegram chat ID for order notifications |
| `PYTHON_BIN` | `python` | Python executable for CMYK conversion |

## Operational notes

- `uploads/`, `qr-codes/`, and `backups/` hold runtime data and are excluded
  from git. Back them up separately from the code.
- Database backups can be downloaded from the admin dashboard
  (`export-db.php`), which also writes a copy into `backups/`.
- The admin dashboard can generate large PDF/ZIP files; ensure PHP
  `memory_limit`, `max_execution_time`, and disk space are sufficient.
- Bundled fonts (`arial.ttf`, `arialbd.ttf`) and template images are required
  for card/PDF rendering.

## Security notes

- Serve the site over HTTPS and set `BASE_URL` to the HTTPS URL in production.
- Never commit `.env` or database dumps. Any credentials that were previously
  stored in source files must be rotated.
- Restrict or remove `reset-admin.php` after creating the admin account.
- `uploads/` and `qr-codes/` deny script execution via `.htaccess`; keep those
  rules when deploying to Apache.
- One-off migration scripts are denied over HTTP in the root `.htaccess`.
- No automated tests or CI pipeline are included in this repository.

## License

No license has been added. Add one before distributing or publishing the code
publicly.
