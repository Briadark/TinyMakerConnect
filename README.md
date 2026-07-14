# TinyMaker Connect

TinyMaker Connect is the PHP/MySQL companion service for TinyMaker printers running firmware based on [slibbinas/TinyMakerWifi](https://github.com/slibbinas/TinyMakerWifi/).

Supported TinyMakerWifi firmware builds can use the default hosted TinyMaker Connect service directly from the firmware. You do not need to host this project yourself to use Connect.

This repository exists for people who want to run their own TinyMaker Connect instance, inspect the server code, contribute to the service, or build compatible integrations. A self-hosted instance provides the same model sharing, printer registration, settings backups, boot animations, moderation and connected-feature APIs as the hosted service.

This is not for stock printers. A printer needs firmware with TinyMaker Connect support before it can register, publish models, import models or use Connect backups.

## What It Enables

- Browse shared ready-to-print TinyMaker model archives.
- Import Connect models to the printer SD card from the firmware dashboard.
- Publish models from the printer SD manager, including credits, license, layer count, height, resin estimate and preview images.
- Register printers without user accounts by using a printer-specific identity and recovery code.
- Store and restore printer settings backups through Connect.
- Track downloads, ratings, bookmarks and optional leaderboard stats per printer.
- Upload, replace, moderate and serve boot animations.
- Moderate models, printers, admins and leaderboard data from the admin dashboard.
- Update the Connect server from GitHub releases through the admin dashboard.

## Requirements

- PHP 8.0+
- MySQL or MariaDB
- PHP extensions:
  - PDO MySQL
  - fileinfo
  - openssl

## Optional Self-Hosting

1. Create an empty MySQL database and database user.
2. Download the latest TinyMakerConnect release ZIP, or clone this repository.
3. Open the folder for the subdomain/site where TinyMaker Connect should run.
4. Upload the contents of the TinyMakerConnect project root directly into that folder.
5. Create a `storage/` folder in that same folder, or let the installer create it.
6. Browse to `https://your-connect-domain.example/health.php`.
7. Browse to `https://your-connect-domain.example/install.php`.
8. Fill in the MySQL fields and storage path.
9. Create the first admin account when prompted.
10. Login at `https://your-connect-domain.example/admin.php`.

Upload the project root contents, not a parent folder. After upload, files like `index.php`, `admin.php`, `api.php`, `.htaccess`, `app/` and `schema.sql` should sit directly in the web root for the Connect domain.

When deploying from a Git checkout, do not upload `.git/`.

The installer creates/updates the database tables through `app/Migrations.php`, creates the storage folders, writes `app/config.php`, and then opens the admin-account setup. If `app/config.php` is not writable, the installer shows the exact file contents to create manually.

After the first install, database changes are applied automatically by the PHP migration layer in `app/Migrations.php`. When a newer server version is uploaded, the first request updates missing tables, columns, and indexes before the page/API continues.

Suggested server layout:

```txt
/home/account/your-connect-domain.example/
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
    boot_animations/
    tmp/
```

This folder is intended to be drag-and-drop deployable. The root `.htaccess` blocks direct web access to `app/`, `storage/`, `README.md`, `schema.sql`, and `app_loader.php` on Apache-compatible hosts. The app never serves storage files directly; downloads go through PHP.

## Firmware Compatibility

TinyMaker Connect is designed for printers programmed with [slibbinas/TinyMakerWifi](https://github.com/slibbinas/TinyMakerWifi/) builds that include Connect support.

Firmware builds may provide a default Connect server URL for the hosted service. Users who self-host can change the Connect server URL in the firmware settings when their build exposes that option.

The firmware is expected to:

- register the printer through `/api/printers/register`
- store the returned `printer_public_id` and `publish_token`
- send `X-TinyMaker-Token` for printer-owned actions
- publish models through `/api/models`
- import model and boot-animation downloads through the printer dashboard
- optionally store and restore settings through `/api/printers/me/backup`

Stock firmware will not know how to use these endpoints.

## Public URLs

```txt
/                         Public model list
/model/{public_id}         Model detail page
/install.php               First-run installer
/admin.php                 Admin dashboard
/health.php                Deployment health check
/api/models                API list/publish
/api/boot-animations       API list boot animations
/api/printers/register     Register printer
/api/leaderboard           Public leaderboard data
```

## Admin Dashboard

The admin dashboard is split into tabs for Overview, Models, Boot animations, Printers, Admins and Leaderboard. It currently includes:

- model counts by status
- boot animation upload, replacement, delete and moderation
- server update check and one-click update from the latest GitHub release
- download/rating/bookmark counts
- latest published/hidden/removed models
- edit model name, credits and status
- printer list with firmware/last-seen data
- block or unblock a printer
- hide all public models from a printer
- add and delete regular admins
- first admin is the super admin and cannot be deleted
- printer leaderboard by uploads, downloads, ratings, bookmarks, uploaded layers, firmware version and lifetime print time

## First API Flow

Register a printer:

```bash
curl -X POST https://your-connect-domain.example/api/printers/register \
  -F hardware_id=ESP32_MAC_OR_HASH \
  -F firmware_version=0.10.0 \
  -F printer_name=TinyMaker \
  -F leaderboard_opt_in=0
```

Set `leaderboard_opt_in=1` only when the user explicitly chooses to share printer stats on the public leaderboard. This includes firmware version and lifetime print time. Registration, publishing, ratings and bookmarks work without leaderboard sharing.
If a printer was connected before and does not send its saved token, registration returns `409` with `reclaim_required=true`.

Refresh printer profile after settings changes or prints without storing a full backup:

```bash
curl -X POST https://your-connect-domain.example/api/printers/me/backup \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"profileOnly":true,"firmware":"0.10.0","connectPrinterName":"Workshop TinyMaker","connectLeaderboard":true,"printSecs":12345}'
```

The same endpoint stores a full settings backup when the JSON contains `backupVersion`.

Check whether a printer is already known:

```bash
curl -X POST https://your-connect-domain.example/api/printers/lookup \
  -F hardware_id=ESP32_MAC_OR_HASH
```

Reclaim an existing printer profile with the recovery code shown by the firmware:

```bash
curl -X POST https://your-connect-domain.example/api/printers/reclaim \
  -F hardware_id=ESP32_MAC_OR_HASH \
  -F recovery_code=PUBLISH_TOKEN_FROM_BACKUP_OR_DISPLAY \
  -F firmware_version=0.10.0 \
  -F printer_name="Workshop TinyMaker" \
  -F leaderboard_opt_in=0
```

Create a fresh profile instead of reclaiming:

```bash
curl -X POST https://your-connect-domain.example/api/printers/register \
  -F hardware_id=ESP32_MAC_OR_HASH \
  -F firmware_version=0.10.0 \
  -F printer_name="Workshop TinyMaker" \
  -F leaderboard_opt_in=0 \
  -F new_profile=1
```

Store a printer settings backup:

```bash
curl -X POST https://your-connect-domain.example/api/printers/me/backup \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN" \
  --data-binary @tinymaker-settings-backup.json
```

Fetch the latest printer settings backup:

```bash
curl https://your-connect-domain.example/api/printers/me/backup \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

Publish a model:

```bash
curl -X POST https://your-connect-domain.example/api/models \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN_FROM_REGISTER" \
  -F model_name="Demo Model" \
  -F original_credits="Original author / license" \
  -F license=CC-BY-NC \
  -F layers=240 \
  -F height_mm=12.0 \
  -F resin_ml=8.4 \
  -F archive=@DemoModel.zip \
  -F preview05=@preview05.png \
  -F preview1=@preview1.png
```

`layers` should be the full source-layer count in the archive. The printer decides how to print those layers based on its current layer-height settings.

Manage published models:

```bash
curl https://your-connect-domain.example/api/printers/me/models \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

List boot animations:

```bash
curl https://your-connect-domain.example/api/boot-animations
```

Boot animations are uploaded, replaced and deleted from the admin dashboard for now. The API exposes published animations and direct `.tmb` downloads so firmware can install them through its local boot-animation install endpoint.
Install counts increase at most once per printer token; preview rendering does not increment the install counter.
Boot animation records include a version and checksum. Firmware that installs from Connect can store optional `/bootanim/{install_name}.json` metadata next to the `.tmb`; manually copied `.tmb` files still work, but version/update detection requires the JSON metadata.

## Server Updates

The admin dashboard shows the installed TinyMaker Connect version and checks GitHub releases for updates. Press **Update now** to download the latest release ZIP and replace the application files.

The updater preserves:

- `app/config.php`
- `storage/`

After updating, the normal migration system keeps the database schema current on the next request.

To publish a server version:

1. Update `TINYMAKER_CONNECT_VERSION` in `app/bootstrap.php`.
2. Update `CHANGELOG.md`.
3. Push the code to GitHub.
4. Create a GitHub Release using tag `vX.Y.Z`.

The admin updater reads the latest GitHub Release from the repository configured in `app/config.php` under `updates.github_repo`.

Rate a model once per printer:

```bash
curl -X POST https://your-connect-domain.example/api/models/PUBLIC_ID/rating \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN" \
  -d "rating=5"
```

Bookmark a model for the printer:

```bash
curl -X POST https://your-connect-domain.example/api/models/PUBLIC_ID/bookmark \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

List printer bookmarks:

```bash
curl https://your-connect-domain.example/api/printers/me/bookmarks \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

Hide a model:

```bash
curl -X PATCH https://your-connect-domain.example/api/models/PUBLIC_ID \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN" \
  -d "status=hidden"
```

Remove a model:

```bash
curl -X DELETE https://your-connect-domain.example/api/models/PUBLIC_ID \
  -H "X-TinyMaker-Token: PUBLISH_TOKEN"
```

## Notes

- Deleting is soft-delete. Files remain on disk for moderation/audit.
- Blocked printers cannot publish or manage models.
- The firmware should store `printer_public_id` and `publish_token` in NVS after registration.
- Downloads only increase the public download counter once per printer token. Anonymous public downloads are logged separately but do not increase the printer-counted total.
- Ratings and bookmarks are stored once per printer.
- Time is not required in v1 because it depends on printer settings.
