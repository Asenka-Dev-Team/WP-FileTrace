# WP FileTrace

**Tracked downloads and file analytics for WordPress.**

WP FileTrace creates tracked download links for WordPress Media Library files and external file URLs. It records download activity, separates shortcode and external-link traffic, and provides a lightweight WordPress admin interface for managing and exporting tracked-file data.

Developed by **Asenka Interactive**.  
Primary Developer: **Brian McLendon**  
[asenka.com](https://asenka.com/)

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer

## Features

- Track WordPress Media Library files or arbitrary HTTP/HTTPS file URLs.
- Generate `[wft]` download-button shortcodes.
- Generate shareable tracked links for email, documents, and other external use.
- Record total, shortcode, and external download counts separately.
- Store the latest download time and tracker creation date.
- Manage tracked files from a sortable WordPress admin table.
- Sort by file title, download counts, last download, or creation date.
- Paginate tracked files at 20 rows per page.
- Copy shortcode or external tracking links directly from each tracked-file row.
- Edit tracker title, destination URL, Media Library association, and button text.
- Permanently delete a tracker together with its stored download-event history.
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

WP FileTrace v0.1.3 includes a lightweight GitHub Release updater for public repositories.

Before publishing v0.1.3, set the repository constant in `wp-filetrace.php`:

```php
define( 'WFT_GITHUB_REPOSITORY', 'YOUR-GITHUB-OWNER/wp-filetrace' );
```

Release workflow:

1. Update the plugin version and `changelog.md`.
2. Commit and push the version to GitHub.
3. Create a GitHub Release with a tag such as `v0.1.3`.
4. Attach the packaged plugin ZIP named `wp-filetrace-v0.1.3.zip`.
5. Future installed versions will periodically check GitHub's latest public release.

The updater deliberately uses the packaged GitHub Release asset instead of GitHub's automatic source archive. The release asset must be a ZIP containing the top-level `wp-filetrace/` plugin directory. GitHub releases marked as drafts or prereleases are not offered as normal plugin updates.

A release does **not** need to exist before adding the updater. v0.1.3 can be published as the first release that contains update support; the first end-to-end update test can then be performed by publishing v0.1.4.

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
