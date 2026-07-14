# Changelog

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
