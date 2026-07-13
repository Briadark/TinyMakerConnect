# Changelog

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
