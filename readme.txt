=== Asenka Download Tracker ===
Contributors: Asenka Interactive, Brian McLendon
Tags: downloads, tracking, media, shortcode, csv
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later

Track WordPress media and external file downloads through shortcodes and shareable tracked links.

== Description ==

Asenka Download Tracker provides:

* [adt media="123"] and [adt url="https://example.com/file.pdf"] shortcodes.
* Optional shortcode button text: [adt media="123" text="Download Report"].
* A WordPress Media Library-powered tracking link creator.
* External/email tracked links.
* Total, shortcode, and external download counts.
* Latest-download timestamps.
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
4. Generate and copy the shortcode or external tracked link.

== Changelog ==

= 0.1.0 =
* Initial release.
* Added nonce-protected tracked-item deletion with confirmation and full event-history cleanup.
