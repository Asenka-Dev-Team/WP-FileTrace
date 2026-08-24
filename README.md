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
- Use AJAX-powered admin interactions for tabs, sorting, pagination, tracker creation/editing/deletion, analytics settings, test-data generation, and update checks without full page reloads.
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
- Optionally overwrite chosen event parameters with the stable WP FileTrace tracker ID, actual downloaded file name, and/or download source (`shortcode` or `external`).
- Support the analytics event flow for both shortcode and external/email tracked links.
- Show an improved centered download handoff page for successfully tracked downloads, even when analytics is not configured.
- Customize the download handoff page with saved HTML/CSS and preview it without incrementing counts or firing analytics.
- Use download-page template variables for tracker title, actual filename, no-track retry URL, download source, and site name.
- Provide a delayed manual-download fallback that does not increment counters or fire analytics a second time.
- Include a temporary 200-row synthetic test-data generator for pagination and sorting tests.
- Check GitHub Releases for new WP FileTrace versions and surface updates through the normal WordPress plugin updater.
- View installed/latest versions, connection status, last GitHub check, and force update checks from the **Settings** tab.
- Force an immediate GitHub release check with **Check for Updates**, bypassing the normal one-hour WP FileTrace release cache.
- Optionally enable the **Simple Download Monitor Migration (Beta)** feature from Settings to scan and migrate individual `[sdm_download]` / `[sdm-download]` shortcodes into WP FileTrace trackers and `[wft]` shortcodes.
- Preview SDM migrations as a dry run before any tracker/content changes are made.
- Flag password-protected, unpublished, behavior-changing, or post-meta/page-builder SDM usages for manual review instead of replacing them blindly.
- Keep rollback copies of affected `post_content` values until the migration is verified or the backup is explicitly discarded.

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

The plugin interface contains four standard tabs, plus a fifth Migration tab when the SDM migration beta is enabled. In JavaScript-enabled wp-admin sessions, switching tabs happens in place without a full page reload:

### Tracked Files

1. A tracked-link creator with a WordPress Media Library selector and manual URL field.
2. The **Tracked Files** table for management, counts, sorting, pagination, bulk deletion, and CSV export.
3. A temporary test-data tool that can create 200 synthetic tracker rows for development testing.

### Analytics

The Analytics tab contains optional Google Analytics / `gtag` configuration:

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

Successfully tracked downloads use the WP FileTrace handoff page before the file begins. If Download Event code is configured, the event runs on that page first. If no event code is configured, the same handoff page is shown without sending a custom analytics event.

#### Download ID Event Parameter

Optionally enter a parameter name such as:

```text
download_id
```

WP FileTrace will add that parameter to each `gtag('event', ...)` call in the configured Download Event snippet and set its value to the stable WP FileTrace tracker ID. If the event snippet already contains the same parameter, WP FileTrace overwrites that value for the tracked download.

#### File Name Event Parameter

Optionally enter a parameter name such as:

```text
download_name
```

WP FileTrace will add that parameter to each `gtag('event', ...)` call in the configured Download Event snippet and set its value to the actual downloaded file name. If the event snippet already contains the same parameter, WP FileTrace overwrites that value for the tracked download.

For example, the following administrator-provided event can keep placeholder values in the saved snippet:

```js
gtag('event', 'haver_download', {
    'download_id': '<<INSERT ID HERE>>',
    'download_name': '<<INSERT NAME HERE>>'
});
```

Set **Download ID Event Parameter** to `download_id` and **File Name Event Parameter** to `download_name`; WP FileTrace replaces both values when the tracked event runs.

#### Download Source Event Parameter

Optionally enter a parameter name such as:

```text
download_source
```

WP FileTrace sets this value to `shortcode` when a visitor clicks a `[wft]` download button and `external` when the shareable/direct tracked link is used. The mapping is optional and independent of the ID and filename mappings.

The Global Site Tag, Download Event, and all dynamic parameter mappings remain optional. The Global Site Tag and Download Event can still be configured independently.

### Download Page

The Download Page tab controls the lightweight frontend handoff shown after WP FileTrace successfully records a download. Leaving both fields blank uses the built-in centered WP FileTrace layout with a minimal card, loading indicator, automatic file start, and delayed manual-download fallback.

Optional **Custom HTML** replaces the built-in content card. Optional **Custom CSS** loads after WP FileTrace's base handoff styles so the page can be white-labeled without editing plugin files.

Available HTML variables include:

- `{{download_name}}` — human-readable WP FileTrace tracker title.
- `{{file_name}}` — actual filename from the destination URL.
- `{{download_url}}` — no-track retry URL for custom manual-download links.
- `{{download_source}}` — `shortcode` or `external`.
- `{{site_name}}` — current WordPress site name.

**Preview Saved Page** opens an administrator-only preview using sample values. Preview mode does not increment a tracker, start a file download, or run the configured custom `gtag` event.

The automatic download and delayed retry button use a dedicated retry route after the original request has already been counted. Retry requests do not increment WP FileTrace counters, create another event-history row, or fire the configured download analytics event again.

### Settings

#### Updates

Settings now contains the GitHub updater status that previously lived in a standalone Updates tab:

- Installed WP FileTrace version
- Latest normal GitHub release
- Last time WP FileTrace actually queried GitHub
- GitHub connection status

The **Check for Updates** button clears WP FileTrace's GitHub-release cache and WordPress's plugin-update transient, immediately requests the latest GitHub Release, and then rebuilds WordPress's plugin-update data. Automatic GitHub release metadata is cached for up to one hour.

The Settings tab contains optional plugin features that are not required for normal download tracking.

#### Beta Features

**Enable Simple Download Monitor Migration** is clearly marked as a beta/experimental feature. It is disabled by default. Turning it on adds the **Migration** tab to WP FileTrace; turning it off hides that tab and prevents the SDM migration handlers from loading on subsequent requests.

The current migration beta supports **Simple Download Monitor (SDM) only**. Additional migration sources may be added later without making the migration utility part of the normal WP FileTrace workflow.

### Migration

The **Migration (Beta)** tab provides a Simple Download Monitor migration assistant for sites moving existing individual download shortcodes to WP FileTrace.

**Scan Site / Dry Run** searches normal WordPress `post_content` for `[sdm_download]` and the legacy `[sdm-download]` alias. It resolves the SDM download ID through the `sdm_downloads` post and its `sdm_upload` file URL, maps the URL back to a Media Library attachment when possible, preserves download button text, and previews the proposed `[wft]` replacement. The dry run does not create trackers or edit content.

Rows are marked **Ready** only when WP FileTrace can perform a conservative one-to-one migration. Password-protected/unpublished SDM items, behavior-changing unsupported shortcode attributes, and globally enabled SDM Terms & Conditions or reCAPTCHA are marked **Needs Review** and are not auto-migrated. `fancy` templates can be migrated, but their SDM template presentation is replaced by the normal WP FileTrace download button and is called out in the dry-run notes.

Shortcode references found in post meta/page-builder data are report-only and are never automatically edited. SDM counter/info/category shortcodes and direct SDM process URLs are outside the automatic replacement pass.

Beginning with v0.1.9, the dry run also includes an **SDM Usage Audit**. The audit inventories all non-trashed SDM download records separately from migration shortcode occurrences and reports how many unique SDM IDs are referenced by standard download shortcodes, direct SDM process URLs, counter/info/link shortcodes, hidden-download shortcodes, and post-meta references. It also lists records where no direct ID reference was found. These records are deliberately labeled **No Direct Reference Found**, not unused, because SDM category/listing shortcodes such as `[sdm_show_dl_from_category]` can expose many downloads dynamically without embedding each individual item ID.

The audit helps explain common differences such as dozens of visible shortcode occurrences versus hundreds of SDM download records, and also distinguishes repeated shortcode uses from the smaller number of unique WP FileTrace tracker destinations that will be created or reused.

**Apply Safe Replacements** creates or reuses the corresponding WP FileTrace tracker and updates only Ready shortcodes in `post_content`. Before the first change to each content item, WP FileTrace stores an internal rollback copy of its original `post_content`. **Roll Back Content Changes** restores those backups; tracker records are intentionally left in place because they may have existed before migration or may already contain download activity. Once the migration has been verified, **Discard Rollback Backup** removes the temporary backups without changing current content.

Historical Simple Download Monitor download counts are not imported by this migration utility.

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

The context includes the tracker ID, file name, file URL, source, and configured ID, filename, and source parameter mappings.

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
3. Create a GitHub Release with a matching version tag such as `v0.1.11`.
4. Publish the release as a normal release, not a draft or prerelease.

Beginning with v0.1.5, no manually uploaded release ZIP is required. The updater uses GitHub's automatically generated release source ZIP and normalizes its extracted repository directory back to `wp-filetrace/` during the WordPress upgrade process.

## Data Storage

WP FileTrace uses dedicated WordPress database tables:

- `{prefix}wft_downloads`
- `{prefix}wft_download_events`

Analytics and Download Page configuration are stored in WordPress options. No visitor IP address or other personally identifying visitor data is intentionally stored by the current download-tracking implementation.

## Uninstall Behavior

Deleting WP FileTrace through the WordPress Plugins screen runs `uninstall.php` and permanently removes WP FileTrace tracker/event tables, analytics configuration, Download Page HTML/CSS settings, temporary SDM migration state/rollback metadata, rewrite-version state, and plugin database-version options. Legacy pre-v0.1.2 ADT tables/options are also cleaned up when present.

## Changelog

See [changelog.md](changelog.md).

## License

GPL-2.0-or-later

### Developer / Testing Tools

The Settings tab can optionally expose the **Generate 200 Test Rows** utility for testing sorting, pagination, and bulk actions. It is disabled by default and should normally remain off on production sites.
