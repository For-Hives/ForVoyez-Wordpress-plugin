# Changelog

All notable changes to this project will be documented in this file.

## 1.1.41

### Fixed

- The generated alt text was saved empty: the API returns `alternativeText` but the plugin read `alt_text`, so every analysis stored an empty alt text (replacing any existing one) while a credit was charged. The plugin now reads `alternativeText` (falling back to `alt_text`) and never replaces an existing alt text, title or caption with an empty value.
- Each describe request now sends an explicit `schema` (`title`, `alternativeText`, `caption`) instead of relying on the server defaults.
- No request is sent to the ForVoyez API while no API key is configured (manual, bulk, upload and scheduled analysis). An admin notice on the plugin and media screens explains how to add the key.
- API key verification calls `GET /api/tokens` instead of the non-existent `/api/describe/verify`.
- API errors (`{"error": "..."}`) are shown to the user; non-JSON responses fall back to the raw body and HTTP status instead of `json_decode_error`. The bulk analysis shows the server message (e.g. the missing API key) instead of "Batch processing failed".
- Analysis requests only accept images the current user can edit: a user with `upload_files` only (e.g. an Author) can no longer overwrite the metadata of other users' images, and non-image attachments are skipped.
- The generated alt text, title and caption are saved as plain text (no HTML, shortcodes or square brackets, so an escaped `[[shortcode]]` cannot become a live one) and capped at 250, 200 and 500 characters.
- Turning automatic analysis off now stops it: the toggle stored the string `"false"`, which was read as enabled, so every upload was still analyzed and charged a credit (and the dashboard showed "Enabled"). Analyses scheduled before it was turned off are skipped too.
- Alt texts, titles and captions edited by hand keep their backslashes.
- Requests carrying the API key no longer follow HTTP redirects, so the key is never sent to another host.
- The admin page no longer loads the missing `api-settings.js` (404) nor `credits-manager.js` (JavaScript error, file removed); the credits widget is handled by `admin-script.js`.
- The sign-up and dashboard links point to https://forvoyez.com/sign-up and https://forvoyez.com/app; the translated readmes link to https://forvoyez.com/contact and the For-Hives/ForVoyez-Wordpress-plugin repository.
- The media-library bulk action ("Analyze with ForVoyez") now starts when a single image is selected: "Start Analysis" threw `$button.data(...).split is not a function` and sent nothing.
- Deleting the plugin removes all its data: `uninstall.php` now also deletes the auto-analyze, context and language settings, every other `forvoyez_*` option and transient and the scheduled analyses, on every site of a multisite network. Alt texts, titles and captions are kept.
- The media-library bulk notices and the low-credits warning escape their output (Plugin Check reported 27 `EscapeOutput` errors); a translation containing an apostrophe can no longer break their script.
- Notifications decode the HTML entities of the server messages before showing them as text, so apostrophes and accents display correctly (French showed `&#039;`).
- A JavaScript error on the Media Library screens (`media-script.js` chained two jQuery wrappers without a separator).
- French, German and Spanish translate every string again, including the 1.1.41 messages, and the compiled `.mo` files match the `.po` files (the new messages showed in English). The three `.po` files declare their plural forms.

### Changed

- The admin page no longer loads the Tailwind Play CDN: its styles are built locally into `assets/css/admin-tailwind.css` (`npm run build:css`), as required by the WordPress.org plugin guidelines.
- Requires PHP 8.0 and WordPress 5.6 or later. Tested up to WordPress 7.1.

### Development

- The PHPUnit suite runs on GitHub Actions (PHP 8.0, 8.2 and 8.4, MariaDB) on every push and pull request, with HTTP calls to the ForVoyez API mocked through `pre_http_request`. A second job fails when `assets/css/admin-tailwind.css` is missing or out of date, and the deploy workflow rebuilds it.
- `npm run lint` passes. PHPCS no longer checks the layout rules that contradict Prettier, which formats the PHP files; php-cs-fixer is no longer part of the lint (it reformats against Prettier). `npm ci` no longer runs `husky install`, which rewrote `core.hooksPath` although the repository has no hooks.
- The deploy workflow uses `softprops/action-gh-release@v3` (v1 needed Node 16) and Node 22, and reads the commit message through an environment variable.
- CI also runs the uninstall test on a multisite install. New tests cover the media-library bulk notice, output escaping and the translation files (every POT string translated, `.mo` in sync with `.po`).
