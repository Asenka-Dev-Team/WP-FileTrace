=== Asenka Download Tracker ===
Contributors: Asenka Interactive, Brian McLendon
Tags: downloads, tracking, media, shortcode, csv
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.1.1
License: GPLv2 or later

Track WordPress media and external file downloads through shortcodes and shareable tracked links.

== Description ==

Asenka Download Tracker provides:

* [adt media="123"] and [adt url="https://example.com/file.pdf"] shortcodes.
* Optional shortcode button text: [adt media="123" text="Download Report"].
* A WordPress Media Library-powered tracking-link creator.
* External/email tracked links.
* A Tracked Files management table with Copy Shortcode, Copy Link, Edit, and Delete actions.
* Total, shortcode, and external download counts.
* Latest-download and date-created timestamps.
* Server-side sorting across file title, counts, and date columns.
* 20-row pagination.
* A temporary admin test-data tool that creates 200 synthetic rows for pagination/sorting testing.
* Anonymous event records.
* CSV summary export.
* Confirmed deletion of a tracked item together with all of its stored download history.
* A GA-ready adt_download_tracked action hook for future analytics integration.

Primary Developer: Brian McLendon
https://asenka.com/

== Installation ==

1. Upload and activate Asenka Download Tracker.
2. Open Download Tracker in the WordPress admin menu.
3. Select a Media Library file or enter a direct URL.
4. Generate the tracking link record.
5. Copy the shortcode or external tracked link from the new row under Tracked Files.

== Changelog ==

= 0.1.1 =
* Reworked the creator so generated files are managed directly in Tracked Files instead of a separate generated-links panel.
* Renamed the reporting table to Tracked Files.
* Added Date Created to the reporting table.
* Added sortable table headers for file title, download counts, last download, and creation date.
* Added 20-row pagination.
* Added temporary Generate 200 Test Rows tooling for sorting/pagination testing.
* Persisted shortcode button text with each tracker so row-level Copy Shortcode preserves custom labels.
* Added Button Text to tracker editing and CSV export.
* Styled the Edit action in yellow for faster visual identification.

= 0.1.0 =
* Initial release.
* Added nonce-protected tracked-item deletion with confirmation and full event-history cleanup.
