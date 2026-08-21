# WP FileTrace

Tracked downloads and file analytics for WordPress.

WP FileTrace creates tracked download links for WordPress Media Library files and external file URLs. It records download activity, separates shortcode and external-link traffic, provides a lightweight WordPress admin interface for managing/exporting tracked-file data, and can optionally trigger Google Analytics / `gtag` events when tracked downloads occur.

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
- Provide the `wft_download_tracked` action hook for custom integrations.
- Optionally inject a complete global Google/site tag into frontend page `<head>` output.
- Optionally execute custom `gtag('event', ...)` JavaScript when a tracked download is recorded.
- Optionally overwrite a chosen event parameter with the actual downloaded file name.
- Support the analytics event flow for both shortcode and external/email tracked links.
- Include a temporary 200-row synthetic test-data generator for pagination and sorting tests.
- Check GitHub Releases for new WP FileTrace versions and surface updates through the normal WordPress plugin updater.
- View installed/latest versions, connection status, and last GitHub check from the WP FileTrace **Updates** tab.
- Force an immediate GitHub release check with **Check for Updates**, bypassing the normal one-hour WP FileTrace release cache.

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

The rendered button points to WP FileTrace's tracked endpoint. The plugin records the request and then continues to the actual file URL.

## Tracked Links

External/email links use the same tracking pipeline as shortcode downloads. Each tracked file maintains separate counts for:

- **Total** downloads
- **Shortcode** downloads
- **External** downloads

This keeps reporting consolidated even when the same file is shared in multiple ways.

## Admin

Open **WP FileTrace** from the WordPress admin menu.

The plugin interface contains three tabs:

### Tracked Files

1. A tracked-link creator with a WordPress Media Library selector and manual URL field.
2. The **Tracked Files** table for management, counts, sorting, pagination, bulk deletion, and CSV export.
3. A temporary test-data tool that can create 200 synthetic tracker rows for development testing.

### Analytics

The Analytics tab contains three optional settings:

#### Global Site Tag

Paste a complete global/site tag, including its `<script>` elements. When configured, WP FileTrace outputs the snippet near the beginning of `wp_head` on frontend pages.

This setting can be left blank when Google Analytics is already installed by a theme, another plugin, Google Tag Manager, or another integration.

#### Download Event

Paste JavaScript such as:

```js
gtag('event', 'file_download', {
    'download_source': 'WP FileTrace'
});
```

The event code runs after WP FileTrace successfully increments a tracked download. It applies to both `[wft]` shortcode downloads and external/email tracked links.

When Download Event code is configured, WP FileTrace briefly serves a lightweight browser handoff page so the event has an opportunity to run before the visitor is redirected to the actual file. When no Download Event code is configured, WP FileTrace retains its normal direct redirect behavior.

#### File Name Event Parameter

Optionally enter a parameter name such as:

```text
file_name
```

WP FileTrace will add that parameter to each `gtag('event', ...)` call in the configured Download Event snippet and set its value to the actual downloaded file name. If the event snippet already contains the same parameter, WP FileTrace overwrites that value for the tracked download.

The Global Site Tag and Download Event settings are independent. Either can be configured without the other.

### Updates

The Updates tab shows:

- Installed WP FileTrace version
- Latest normal GitHub release
- Last time WP FileTrace actually queried GitHub
- GitHub connection status

The **Check for Updates** button clears WP FileTrace's GitHub-release cache and WordPress's plugin-update transient, immediately requests the latest GitHub Release, and then rebuilds WordPress's plugin-update data. This is useful when a release has just been published and WordPress has not surfaced it yet.

Automatic GitHub release metadata is cached for up to one hour.

## Developer Hooks

WP FileTrace fires the existing server-side download hook after a tracked request is successfully recorded:

```php
do_action( 'wft_download_tracked', $download_id, $file_url, $source, $tracker );
```

`$source` is currently either `shortcode` or `external`.

The analytics handoff context can also be modified before browser output with:

```php
apply_filters( 'wft_analytics_event_context', $context, $tracker, $source );
```

The context includes the tracker ID, file name, file URL, source, and configured file-name parameter.

## Installation

1. Upload the `wp-filetrace` plugin directory or install a packaged ZIP through WordPress.
2. Activate **WP FileTrace**.
3. Open **WP FileTrace** in the WordPress admin menu.
4. Select a Media Library file or enter a direct file URL.
5. Click **Generate Tracking Link**.
6. Copy the shortcode or external link from the file's row under **Tracked Files**.
7. Optionally configure Google Analytics / `gtag` under the **Analytics** tab.

## GitHub Updates

WP FileTrace checks the public [Asenka-Dev-Team/WP-FileTrace](https://github.com/Asenka-Dev-Team/WP-FileTrace) repository for the latest normal GitHub Release and surfaces newer versions through WordPress's standard plugin updater.

Release workflow:

1. Update the plugin version and `changelog.md`.
2. Commit and push the version to GitHub.
3. Create a GitHub Release with a matching tag such as `v0.1.6`.
4. Publish the release as a normal release, not a draft or prerelease.

Beginning with v0.1.5, no manually uploaded release ZIP is required. The updater uses GitHub's automatically generated release source ZIP and normalizes its extracted repository directory back to `wp-filetrace/` during the WordPress upgrade process.

## Data Storage

WP FileTrace uses dedicated WordPress database tables:

- `{prefix}wft_downloads`
- `{prefix}wft_download_events`

Analytics configuration is stored in WordPress options. No visitor IP address or other personally identifying visitor data is intentionally stored by the current download-tracking implementation.

## Uninstall Behavior

Deleting WP FileTrace through the WordPress Plugins screen runs `uninstall.php` and permanently removes WP FileTrace tracker/event tables, analytics configuration, and plugin database-version options. Legacy pre-v0.1.2 ADT tables/options are also cleaned up when present.

## Changelog

See [changelog.md](changelog.md).

## License

GPL-2.0-or-later
