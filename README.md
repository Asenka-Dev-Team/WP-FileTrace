# WP FileTrace

**Tracked downloads and file analytics for WordPress.**

WP FileTrace creates tracked download links for WordPress Media Library files and external file URLs. It records download activity, separates shortcode and external-link traffic, and provides a lightweight WordPress admin interface for managing and exporting tracked-file data.

Developed by **Asenka Interactive** - [asenka.com](https://asenka.com/)  
Primary Developer: **Brian McLendon** - [u/eyeofbri](https://github.com/eyeofbri)

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer

## Features

- Track WordPress Media Library files or arbitrary HTTP/HTTPS file URLs.
- Generate `[wft]` download-button shortcodes.
- Generate shareable tracked links for email, documents, and other external use.
- Record total, shortcode, and external download counts separately.
- Store the latest download time and **Created On** date.
- Manage tracked files from a sortable WordPress admin table.
- Sort by file title, download counts, last download, or creation date.
- Paginate tracked files at 20 rows per page.
- Copy shortcode or external tracking links directly from each tracked-file row.
- Edit tracker title, destination URL, Media Library association, and button text.
- Permanently delete a tracker together with its stored download-event history.
- Select individual tracked files for bulk deletion or permanently delete all tracked files across every page.
- Export tracked-file summaries as CSV.
- Store anonymous individual download events for future reporting.
- Ignore `HEAD` requests and common link-preview/prefetch user agents when counting downloads.
- Provide the `wft_download_tracked` action hook for future analytics integrations such as GA4.
- Include a temporary 200-row synthetic test-data generator for pagination and sorting tests.
- Check GitHub Releases for new WP FileTrace versions and surface updates through the normal WordPress plugin updater.

## Shortcodes

### WordPress Media Library file

```text
[wft media="123"]
```

### External file URL

```text
[wft url="https://example.com/files/report.pdf"]
```

### Custom button text

```text
[wft media="123" text="Download Report"]
```

The rendered button points to WP FileTrace's tracked redirect endpoint. The plugin records the request and then redirects the visitor to the actual file URL.

## Tracked Links

External/email links use the same tracking pipeline as shortcode downloads. Each tracked file maintains separate counts for:

- **Total** downloads
- **Shortcode** downloads
- **External** downloads

This keeps reporting consolidated even when the same file is shared in multiple ways.

## Admin

Open **WP FileTrace** from the WordPress admin menu.

The page contains:

1. A tracked-link creator with a WordPress Media Library selector and manual URL field.
2. The **Tracked Files** table for management, counts, sorting, pagination, and CSV export.
3. A temporary test-data tool that can create 200 synthetic tracker rows for development testing.

## Analytics Integration

WP FileTrace does not send a Google Analytics event yet. Successful tracked requests expose this hook for a future GA4 or other analytics integration:

```php
do_action( 'wft_download_tracked', $download_id, $file_url, $source, $tracker );
```

`$source` is currently either `shortcode` or `external`.

## Installation

1. Upload the `wp-filetrace` plugin directory or install the packaged ZIP through WordPress.
2. Activate **WP FileTrace**.
3. Open **WP FileTrace** in the WordPress admin menu.
4. Select a Media Library file or enter a direct file URL.
5. Click **Generate Tracking Link**.
6. Copy the shortcode or external link from the file's row under **Tracked Files**.

## v0.1.2 Rename Note

v0.1.2 renames the project and its internal prefix from **Asenka Download Tracker / ADT** to **WP FileTrace / WFT**. This includes the plugin folder, main PHP file, text domain, internal PHP class prefixes, database table names, WordPress actions, admin asset selectors, shortcode, and tracked-link route.

Older `[adt]` shortcodes and `/adt-download/` URLs are **not aliases** in v0.1.2. Freshly generated WP FileTrace links use the new namespace.

## GitHub Updates

WP FileTrace checks the public [Asenka-Dev-Team/WP-FileTrace](https://github.com/Asenka-Dev-Team/WP-FileTrace) repository for the latest GitHub Release and surfaces newer versions through WordPress's normal plugin updater.

Release workflow:

1. Update the plugin version and `changelog.md`.
2. Commit and push the version to GitHub.
3. Create a GitHub Release with a matching tag such as `v0.1.4`.
4. Publish the release as a normal release, not a draft or prerelease.

WP FileTrace v0.1.4 changes the updater to use GitHub's automatically generated source ZIP and normalizes the extracted repository directory back to `wp-filetrace/` during the WordPress upgrade process. This means **v0.1.5 and later releases will not require a manually uploaded plugin ZIP**.

**Transition note:** the v0.1.4 GitHub Release should still include `wp-filetrace-v0.1.4.zip` as an attached asset. Installed v0.1.3 copies still use the older asset-based updater and need that one final binary asset in order to discover/install v0.1.4. After v0.1.4 is installed, future updates can use GitHub's generated source ZIP directly.

## Data Storage

WP FileTrace uses dedicated WordPress database tables:

- `{prefix}wft_downloads`
- `{prefix}wft_download_events`

No visitor IP address or other personally identifying visitor data is intentionally stored by the current tracking implementation.

## Uninstall Behavior

Deleting WP FileTrace through the WordPress Plugins screen runs `uninstall.php` and permanently removes WP FileTrace tracker/event tables and plugin database-version options. Legacy pre-v0.1.2 ADT tables/options are also cleaned up when present.

## Changelog

See [changelog.md](changelog.md).

## License

GPL-2.0-or-later
