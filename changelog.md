# Changelog

All notable changes to WP FileTrace will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

### Changed

### Fixed

## [0.1.6] - 2026-08-21

### Added

- Added a dedicated **Updates** tab to the WP FileTrace admin interface.
- Added a **Check for Updates** button that explicitly clears WP FileTrace and WordPress plugin-update caches before immediately querying GitHub Releases.
- Added updater diagnostics showing the installed version, latest GitHub release, last real GitHub check time, and connection status.
- Added visible GitHub/API error reporting to the updater status panel instead of silently treating failed checks as no available update.
- Added a direct link from the Updates tab to the WP FileTrace GitHub Releases page.

### Changed

- Bumped WP FileTrace to v0.1.6.
- Reduced GitHub release metadata caching from six hours to one hour.
- Manual update checks now rebuild WordPress plugin-update data in the same request after a successful fresh GitHub response.
- Updated README documentation for the new update diagnostics and manual release-check workflow.

### Fixed

- Removed the need for temporary code or WP-CLI when forcing WP FileTrace to recognize a newly published GitHub Release.

## [0.1.5] - 2026-08-21

### Added

- Added a new **Analytics** tab to the WP FileTrace admin interface.
- Added an optional saved Global Site Tag field that can output a complete administrator-provided analytics snippet near the top of frontend page heads.
- Added an optional saved Download Event field for custom `gtag('event', ...)` JavaScript.
- Added browser-side analytics-event execution after a tracked download is successfully recorded for both shortcode and external/email links.
- Added an optional File Name Event Parameter setting that injects or overwrites a chosen event parameter with the actual downloaded file name.
- Added individual controls to clear the Global Site Tag or Download Event settings.
- Added the `wft_analytics_event_context` filter for customizing analytics event context before the browser handoff is rendered.

### Changed

- Bumped WP FileTrace to v0.1.5.
- Updated the tracked-download handler so custom browser analytics events run only after the database download tick succeeds.
- Preserved the existing `wft_download_tracked` server-side action as the general integration hook.
- Updated the plugin to use the current `icon--wp-filetrace.svg` and `logo--wp-filetrace.svg` asset names without replacing their branding artwork.
- Updated README documentation for analytics configuration and the GitHub source-ZIP release workflow.
- Updated uninstall cleanup to remove saved WP FileTrace analytics options.

### Fixed

- Ensured custom analytics event handling works through the same tracked route for shortcode and external downloads instead of relying on the originating page.

## [0.1.4] - 2026-08-21

### Added

- Added per-row selection checkboxes and a select-all checkbox for the current page.
- Added **Delete Selected** for bulk-removing selected trackers and all associated download-event history.
- Added **Delete All** for permanently removing every tracked file and all associated download-event history across all pages.
- Added Dashicons to the Copy Shortcode and Copy Link actions.
- Added confirmation prompts and admin notices for bulk deletion actions.

### Changed

- Bumped WP FileTrace to v0.1.4.
- Changed row Edit and Delete controls to compact Dashicon buttons.
- Renamed **Date Created** to **Created On** and moved it before **Last Download**.
- Added stronger horizontal separators between tracked-file rows.
- Constrained the WP FileTrace admin-menu SVG to the WordPress menu icon area.
- Configured the GitHub updater for `Asenka-Dev-Team/WP-FileTrace`.
- Changed the updater to use GitHub's automatically generated release source ZIP for v0.1.5 and later updates; the v0.1.4 transition release still requires one attached ZIP for installed v0.1.3 copies.
- Preserved Asenka Interactive and Brian McLendon developer attribution in the README.

### Fixed

- Fixed the admin menu icon overflowing its WordPress menu-item container.

## [0.1.3] - 2026-08-21

### Added

- Added GitHub Release update checks integrated with WordPress's normal plugin update system.
- Added a version-details response using GitHub release information.
- Added six-hour caching of GitHub release metadata to limit API requests.
- Added release-asset selection for packaged `wp-filetrace-vX.Y.Z.zip` files.
- Bundled the full-data-removal uninstall handler so repository releases clean up WP FileTrace and legacy ADT data when deleted through WordPress.

### Changed

- Bumped WP FileTrace to v0.1.3.
- Documented the GitHub release/update workflow in `readme.md`.

## [0.1.2] - 2026-08-21

### Added

- Added a standalone GitHub-friendly `readme.md`.
- Added `changelog.md` for version history.
- Delete-success notices now identify the tracked file that was removed.

### Changed

- Renamed the plugin from **Asenka Download Tracker** to **WP FileTrace**.
- Renamed the plugin directory and bootstrap file to `wp-filetrace`.
- Changed the text domain to `wp-filetrace`.
- Changed internal PHP prefixes from `ADT_` to `WFT_`.
- Changed internal WordPress actions, nonces, query variables, database option names, CSS/JavaScript selectors, and asset handles from `adt` to `wft`.
- Changed database tables to `{prefix}wft_downloads` and `{prefix}wft_download_events`.
- Changed the shortcode from `[adt]` to `[wft]`.
- Changed tracked redirects from `/adt-download/{key}/` to `/wft-download/{key}/`.
- Renamed the options-page logo placeholder to `wp-filetrace-logo.svg`.
- Retained Asenka Interactive attribution and links to [asenka.com](https://asenka.com/).

## [0.1.1] - 2026-08-21

### Added

- Added a **Date Created** column to Tracked Files.
- Added server-side sorting for file title, total downloads, shortcode downloads, external downloads, last download, and creation date.
- Added 20-row server-side pagination.
- Added temporary **Generate 200 Test Rows** tooling for pagination and sorting tests.
- Added persisted shortcode button text to each tracker.
- Added Button Text to tracker editing and CSV export.

### Changed

- Reworked the creator so generated files are managed directly in **Tracked Files** instead of a separate generated-links panel.
- Renamed the reporting section to **Tracked Files**.
- Changed the creator action label to **Generate Tracking Link**.
- Styled the Edit action in yellow for faster visual identification.

## [0.1.0] - 2026-08-20

### Added

- Initial plugin structure and WordPress admin interface.
- Media Library and manual-URL tracked-download creation.
- Download-button shortcode support.
- Shareable external/email tracking links.
- Dedicated tracker and event database tables.
- Total, shortcode, and external counters.
- Latest-download timestamp.
- CSV summary export.
- Tracked redirect endpoint.
- Basic prefetch/link-preview filtering.
- Analytics-ready download action hook.
- Nonce-protected tracker editing and deletion.
- Confirmed deletion of a tracker together with all associated download-event history.
- Asenka website links, admin icon placeholder, options-page logo placeholder, and primary developer attribution.
