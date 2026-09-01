# WP FileTrace v0.1.14 — burst/scanner hardening patch

This bundle contains **three replacement files** for a current v0.1.13 working tree:

- `wp-filetrace.php`
- `includes/class-wft-router.php`
- `includes/class-wft-download-page.php`

Copy them over the matching v0.1.13 files in your local WP FileTrace repository.

## What this patch changes

- Initial `/wft-download/{key}/` GETs are **read-only** and no longer increment download counters.
- Recognized preview/prefetch scanners and `HEAD` probes are answered without tracking.
- The tracked-link handoff is short-circuited at `plugins_loaded` before normal theme/frontend setup.
- A real browser waits briefly, then sends a same-origin confirmation `POST` before the download is counted.
- Browser confirmation uses a stable HMAC token plus an `X-WP-FileTrace: browser` header.
- The handoff no longer calls `wp_head()` or `wp_footer()`, preventing unrelated theme/plugin asset storms.
- Only WP FileTrace's explicitly configured global analytics snippet is output on the handoff page.
- Normal automatic continuation goes **directly to the destination file** instead of through `/retry/`.
- The old `/retry/` route remains supported for cached/legacy/custom handoff markup.
- Handoff responses advertise short public/shared-cache lifetimes.
- Tracking failure never blocks the actual file download.
- No database/schema changes are required.

## Intentionally not included in this first patch

To keep the production fix narrow, this bundle does not yet change admin explanatory copy, README wording, or remove the existing `?via=external` query parameter from generated external links. Those are cleanup/documentation changes and do not materially affect the burst-load fix.

## Smoke test checklist

1. Update the three files in a v0.1.13 local working tree.
2. Confirm WP FileTrace reports `v0.1.14` in wp-admin.
3. Open an external tracked URL in a normal browser.
   - Handoff page should look the same.
   - Download should begin automatically.
   - Counter should increment **once**.
4. Open the same tracked URL with DevTools Network open.
   - Initial tracked URL: `200`.
   - One browser-confirmation `POST` should occur using `?wft_download_key=...&wft_download_track=1`.
   - Automatic continuation should go directly to the final file URL.
   - No automatic `/retry/` request should occur.
5. View source/network for the handoff page.
   - No Unicon/theme, Slider Revolution, Quform, Gutenberg/React, or other normal frontend assets should be requested by the handoff itself.
   - The configured WP FileTrace global analytics snippet may still load its own analytics script.
6. Issue a `HEAD` request to a tracked URL.
   - It should not increment counters.
7. Request the tracked URL without executing JavaScript (for example `curl`).
   - It should not increment counters.
8. Click the manual fallback after letting it appear.
   - It should continue to the file without creating a second count.
9. Test a `[wft]` shortcode from a WordPress page.
   - It should still record `shortcode` source and retain the Back to previous page behavior.
10. Test Download Page Preview.
   - It should not download, track, or fire the configured download event.

## Production verification after deployment

During the next Haver email send, compare server logs for:

- initial `/wft-download/` GET count,
- confirmation POST count,
- requests for unrelated theme/plugin assets with a FileTrace referrer,
- peak requests per second,
- server CPU/resource alerts.

The confirmation POST count should be substantially lower than the initial GET count when email-security scanners are involved.
