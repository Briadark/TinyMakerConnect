# TinyMaker Connect

TinyMaker Connect is the PHP/MySQL web service for sharing ready-to-print TinyMaker model archives, linking printers, moderation, and future connected features.

## Requirements

- PHP 8.0+
- MySQL or MariaDB
- PHP extensions:
  - PDO MySQL
  - fileinfo
  - openssl

## Install

1. Create an empty MySQL database and database user.
2. Open the folder for the subdomain/site where TinyMaker Connect should run.
3. Upload everything inside `Web_Server/` directly into that folder.
4. Create a `storage/` folder in that same folder, or let the installer create it.
5. Browse to `https://your-tinymaker-connect-domain.example/health.php`.
6. Browse to `https://your-tinymaker-connect-domain.example/install.php`.
7. Fill in the MySQL fields and storage path.
8. Create the first admin account when prompted.
9. Login at `https://your-tinymaker-connect-domain.example/admin.php`.

The installer creates/updates the database tables through `app/Migrations.php`, creates the storage folders, writes `app/config.php`, and then opens the admin-account setup. If `app/config.php` is not writable, the installer shows the exact file contents to create manually.

After the first install, database changes are applied automatically by the PHP migration layer in `app/Migrations.php`. When a newer server version is uploaded, the first request updates missing tables, columns, and indexes before the page/API continues.

Suggested server layout:

```txt
/home/account/your-tinymaker-connect-domain.example/
  .htaccess
  index.php
  install.php
  admin.php
  api.php
  health.php
  app_loader.php
  README.md
  schema.sql
  app/
  storage/
    models/
    previews/
    tmp/
```

This folder is intended to be drag-and-drop deployable. The root `.htaccess` blocks direct web access to `app/`, `storage/`, `README.md`, `schema.sql`, and `app_loader.php` on Apache-compatible hosts. The app never serves storage files directly; downloads go through PHP.

## Public URLs

```txt
/                         Public model list
/model/{public_id}         Model detail page
/install.php               First-run installer
/admin.php                 Admin dashboard
/health.php                Deployment health check
/api/models                API list/publish
/api/printers/register     Register printer
/api/leaderboard           Public leaderboard data
```

## Admin Dashboard

The admin dashboard currently includes:

- model counts by status
- download/rating/bookmark counts
- latest published/hidden/removed models
- edit model name, credits and status
- printer list with firmware/last-seen data
- block or unblock a printer
- hide all public models from a printer
- add and delete regular admins
- first admin is the super admin and cannot be deleted
- printer leaderboard by uploads, downloads, ratings, bookmarks and uploaded layers

## First API Flow

Register a printer:

```bash
curl -X POST https://your-tinymaker-connect-domain.example/api/printers/register \
  -F hardware_id=ESP32_MAC_OR_HASH \
  -F firmware_version=0.10.0 \
  -F printer_name=TinyMaker \
  -F leaderboard_opt_in=0
```

Set `leaderboard_opt_in=1` only when the user explicitly chooses to share printer stats on the public leaderboard. Registration, publishing, ratings and bookmarks work without leaderboard sharing.

Publish a model:

```bash
curl -X POST https://your-tinymaker-connect-domain.example/api/models \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN_FROM_REGISTER" \
  -F model_name="Demo Model" \
  -F original_credits="Original author / license" \
  -F layers=240 \
  -F height_mm=12.0 \
  -F resin_ml=8.4 \
  -F archive=@DemoModel.zip \
  -F preview=@preview.png
```

Manage published models:

```bash
curl https://your-tinymaker-connect-domain.example/api/printers/me/models \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

Rate a model once per printer:

```bash
curl -X POST https://your-tinymaker-connect-domain.example/api/models/PUBLIC_ID/rating \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN" \
  -d "rating=5"
```

Bookmark a model for the printer:

```bash
curl -X POST https://your-tinymaker-connect-domain.example/api/models/PUBLIC_ID/bookmark \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

List printer bookmarks:

```bash
curl https://your-tinymaker-connect-domain.example/api/printers/me/bookmarks \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

Hide a model:

```bash
curl -X PATCH https://your-tinymaker-connect-domain.example/api/models/PUBLIC_ID \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN" \
  -d "status=hidden"
```

Remove a model:

```bash
curl -X DELETE https://your-tinymaker-connect-domain.example/api/models/PUBLIC_ID \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

## Notes

- Deleting is soft-delete. Files remain on disk for moderation/audit.
- Blocked printers cannot publish or manage models.
- The firmware should store `printer_public_id` and `publish_token` in NVS after registration.
- Downloads only increase the public download counter once per printer token. Anonymous public downloads are logged separately but do not increase the printer-counted total.
- Ratings and bookmarks are stored once per printer.
- Time is not required in v1 because it depends on printer settings.
