# Changelog

## 0.2.3 - Official logo assets

- Switched Connect public/admin pages to the official TinyMaker Connect logo/favicon from TinyMakerWifi.
- Switched the browser USB flash page to the official TinyMaker flash logo/favicon.
- Removed the temporary generated favicon asset.

## 0.2.2 - Browser USB flash tool

- Added a dedicated `/flash.php` first-time setup page for flashing TinyMakerWifi over USB from Chrome/Edge using Web Serial.
- Added a simple default flow: connect the printer, then flash the latest official `firmware-full.bin`.
- Added advanced local-file flashing for trusted custom `firmware-full.bin` builds, with baud-rate fallback hidden from normal users.
- Added server-side caching for the latest TinyMakerWifi `firmware-full.bin` under `storage/firmware`.
- Added `/api/firmware/latest-full`, which checks the latest TinyMakerWifi GitHub release and reuses the cached full firmware when already current.
- Removed arbitrary custom firmware URL flashing to avoid server-side request risk and keep the public tool focused.
- Added TinyMaker Connect logo/favicon/shared footer polish across public/admin pages.
- Added public-site footer credits for TinyMakerConnect and TinyMakerWifi.

## 0.2.1 - Public site browser polish

- Added public model preview switching between 0.05 mm and 0.10 mm views.
- Added public model search.
- Added a public boot animations section with browser-rendered TMB previews.
- Added public leaderboard category sorting for uploads, downloads, ratings, bookmarks, print time and uploaded layers.
- Added dark/light theme switching on the public site.
- Fixed long SHA256 values wrapping in public detail views.

## 0.2.0 - Hosted printer Connect app

- Added the server-hosted printer Connect tab script at `assets/printer-connect.js`.
- Moved Connect tab layout, model/manager cards, leaderboard rendering, boot animation previews, sharing workflows and Connect button behavior out of the firmware dashboard.
- Compatible printer firmware can now load Connect UI updates from the server without requiring a firmware reflash.
- Printer profile stats now refresh through existing authenticated Connect communication instead of a separate stats endpoint.
- Firmware version now refreshes after normal printer settings/profile syncs and model publishing, not only registration.
- Leaderboard opt-in now includes firmware version and lifetime print time.

## 0.1.5 - Token header compatibility

- Accept `X-TinyMaker-Token` from PHP server variables as well as `getallheaders()`, fixing authenticated printer calls on hosts that do not expose custom headers through `getallheaders()`.

## 0.1.4 - Printer recovery code

- Added a separate per-printer recovery code for reclaiming Connect profiles.
- Registration now returns both the hidden publish token and the user-visible recovery code.
- Reclaim now validates the recovery code instead of accepting the publish token.
- Added database migration `010_printer_recovery_token`.

## 0.1.3 - Admin dashboard tabs

- Split the admin dashboard into tabs for Overview, Models, Boot animations, Printers, Admins and Leaderboard.
- Kept update checks and server statistics in the Overview tab.
- Remember the last selected admin tab in the browser.

## 0.1.2 - Model licenses

- Added structured model license storage, API output, admin editing and public display.
- Added database migration `008_model_license`.
- Added source-layer-safe model publishing support with separate `preview_05` and `preview_1` uploads.
- Removed the legacy model `preview_path` field from the schema/API in favor of `preview_05_path` and `preview_1_path`.
- Added database migration `009_model_dual_previews`.

## 0.1.1 - Connect recovery and backups

- Added printer lookup endpoint so firmware can detect when a printer was connected before.
- Added recovery-code reclaim endpoint using the existing printer publish token.
- Added authenticated printer settings backup upload and download endpoints.
- Added `printer_backups` database table and automatic migration `007_printer_backups`.
- Updated registration so known printers without a token return a reclaim-required response instead of silently taking over the profile.
- Added explicit new-profile registration path for printers that should not reclaim an old profile.

## 0.1.0 - Initial release

- Added TinyMaker Connect setup flow with MySQL configuration and first-admin creation.
- Added public model browser, model detail pages, preview endpoint and download tracking.
- Added printer registration, per-printer identity, optional leaderboard stats, ratings and bookmarks.
- Added admin dashboard for models, printers, moderation, admins and leaderboard data.
- Added admin server update banner and self-update action from the latest GitHub release.
- Added database migrations so deployed servers can update automatically.
- Added Connect API endpoints for printer registration, model listing, publishing, downloads, ratings and bookmarks.
- Added boot animation API, admin upload, version/checksum metadata and install tracking.
- Added boot animation admin replace and confirmed delete actions.
- Reserved `Default` and `Shuffle` as blocked boot-animation install names.
