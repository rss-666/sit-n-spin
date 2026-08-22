# The Runbook Briefings

The Runbook Briefings is a standalone WordPress plugin that turns approved hosting and WordPress security RSS stories into original, source-attributed briefings for editorial review.

> Turn hosting news and security alerts into original, engineer-reviewed Runbook briefings.

## What It Does

```text
RSS source -> filtering -> review queue -> original briefing -> editor approval -> WordPress draft
```

The plugin deliberately does **not** auto-publish or copy complete source articles. It stores source metadata and a bounded plain-text excerpt, keeps facts separate from analysis, and adds a visible attribution block to every draft.

### Included features

- Add, edit, enable, disable, and delete RSS feed sources in WordPress admin.
- Filter each source by keywords and feed categories.
- Apply a global item-age window and per-run import limit.
- Fetch enabled feeds with WordPress `fetch_feed()`/SimplePie on WP-Cron or on demand.
- Validate public HTTP(S) feed URLs, limit redirects and timeouts, and reject unsafe requests.
- Normalize article URLs and detect exact URL and probable title duplicates.
- Review imported items with status, source health, duplicate confidence, editorial notes, and processing history.
- Generate a structured headline, factual summary, “Why this matters,” practical implications, and internal-link suggestions through an OpenAI-compatible provider.
- Continue the full manual editing workflow when AI is disabled or no key is configured.
- Create or update a WordPress draft/pending post only after an editor explicitly requests it.
- Preserve source name, original title, publication date, source URL, canonical metadata, and visible attribution.
- Encrypt API keys at rest using authenticated encryption derived from WordPress salts.
- Record bounded, credential-redacted operational logs.

## Requirements

- WordPress 6.4 or newer.
- PHP 8.1 or newer.
- HTTPS is strongly recommended for WordPress admin.
- PHP Sodium or OpenSSL when an AI key will be stored. Manual mode does not require either extension.
- A working WP-Cron setup, or a system scheduler that calls `wp-cron.php`.

No production PHP packages or JavaScript frameworks are required.

## Installation

### From the installable ZIP

1. Build or download `the-runbook-briefings-1.0.0.zip`.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install it, and activate **The Runbook Briefings**.
4. Open **Content Briefings → Settings** and review the defaults.
5. Open **Content Briefings → Sources** and add an approved public RSS feed.

### From this repository

Copy the `the-runbook-briefings` directory into `wp-content/plugins/`:

```bash
cp -R the-runbook-briefings /path/to/wordpress/wp-content/plugins/
```

Then activate it in WordPress admin or with WP-CLI:

```bash
wp plugin activate the-runbook-briefings
```

Activation creates four prefixed tables for sources, imported items, processing history, and logs; adds the `review_runbook_briefings` capability to administrators and editors; and schedules the importer. Deactivation clears the schedule but retains editorial data. Uninstall retains data by default unless **Delete plugin tables and settings when the plugin is uninstalled** was enabled first.

## Configuration

Open **Content Briefings → Settings**.

### AI provider

- **Provider:** OpenAI-compatible or disabled/manual-only.
- **API key:** enter a new key to save or replace it. The key is never redisplayed.
- **Model:** model identifier accepted by the provider; default `gpt-4o-mini`.
- **Default tone:** technical, beginner-friendly, concise, or editorial.
- **Retry limit / timeout:** bounds external requests.

The built-in provider uses `https://api.openai.com/v1/chat/completions`. A trusted developer can point the same provider abstraction at another compatible HTTPS endpoint:

```php
add_filter(
    'trb_openai_endpoint',
    static fn (): string => 'https://trusted-provider.example/v1/chat/completions'
);
```

Do not accept an endpoint from an untrusted user. WordPress safe HTTP APIs and HTTPS validation are still applied.

### Import and draft safeguards

- **Maximum source excerpt characters:** 100–5,000; default 1,200.
- **Cron frequency:** every 15 minutes, hourly, twice daily, or daily.
- **Default post status:** draft or pending review. `publish` is not accepted.
- **Maximum new items per run:** 1–100.
- **Maximum item age:** 1–365 days.
- **Request timeout:** 5–60 seconds.

## Adding Feeds

1. Open **Content Briefings → Sources**.
2. Enter a descriptive source name and public HTTP(S) RSS/Atom URL.
3. Optionally enter comma- or newline-delimited keywords. An item passes when any keyword appears in its title or short description.
4. Optionally enter feed category labels. An item passes when any configured category matches.
5. Add reliability/editorial notes and select a frequency.
6. Save the source, then use **Run import now** on the dashboard.

Private, loopback, local, malformed, and non-HTTP feed addresses are rejected through WordPress URL validation. Redirects are limited to three. A successful fetch updates source health even when every item is filtered.

## Editorial Workflow

1. Open **Content Briefings → Review Queue**.
2. Filter by status or source and open an item.
3. Verify the original title, source URL, publication time, and bounded excerpt.
4. Either write fields manually or choose **Generate suggestions**.
5. Verify every generated statement against the source. AI output is a suggestion, not an approval.
6. Edit the headline, factual summary, Runbook analysis, implications, local link suggestions, and private notes.
7. Choose **Save changes** to remain in the queue, or **Save as WordPress draft**.
8. Continue normal WordPress editing and publishing outside the plugin.

The generated post contains separate “What happened,” “Why this matters,” and practical-implications sections, optional validated local links, and a visible source-attribution block. The source metadata is also stored as protected post meta.

### Statuses

- **New:** ready for editorial work (including generated suggestions awaiting review).
- **Processing:** an AI request is active.
- **Drafted:** linked to a draft or pending WordPress post.
- **Published:** the linked post was later published through normal WordPress controls.
- **Dismissed:** removed from the active workflow or marked as a probable duplicate.
- **Error:** a provider or processing action failed and can be retried.

## Duplicate Detection

Exact duplicate detection hashes a normalized URL after standardizing scheme/host/path/query order and removing common tracking parameters. Probable duplicates compare normalized meaningful title tokens against recent imported items. Probable title duplicates are retained for auditability, linked to the earlier item with a confidence score, and initially dismissed. An editor can return one to the queue.

The similarity threshold can be adjusted cautiously:

```php
add_filter( 'trb_duplicate_title_threshold', static fn (): float => 0.90 );
```

## Security and Privacy

- Source/settings actions require `manage_options`; queue actions require the plugin review capability.
- Every write action verifies a user-specific WordPress nonce.
- Input is validated and sanitized; output is escaped; database access uses WordPress APIs and prepared SQL.
- Feed and AI requests use safe WordPress HTTP functions, strict timeouts, limited redirects/retries, and safe-URL validation.
- API keys are encrypted with Sodium `secretbox` or OpenSSL AES-256-GCM. The encryption key is derived from WordPress authentication salts and is not stored in the plugin tables.
- Keys are never placed in generated HTML, browser JavaScript, REST output, notices, or logs.
- Log context redacts credential-like fields and bearer/key patterns; records are pruned after 90 days.
- AI requests contain source metadata, the configured short excerpt, tone, and a small list of existing internal-link candidates. They do not contain complete source articles.

Your AI provider processes submitted prompt data under its own terms and privacy policy. Site operators are responsible for obtaining appropriate agreements and informing editors. The plugin does not send data to an AI provider until an editor explicitly clicks **Generate suggestions**.

## Copyright and Attribution Safeguards

The plugin is designed for source-aware editorial transformation, not article spinning:

- It does not fetch or store complete article bodies.
- Imported excerpts are converted to plain text and hard-limited.
- Prompts prohibit unsupported facts, line-by-line rewriting, and source reconstruction.
- Source facts and original analysis have separate fields and headings.
- Attribution is application-generated from stored source metadata, not entrusted to AI output.
- Draft creation requires editor action and leaves publication to normal WordPress controls.

Feed availability does not itself grant republication rights. Configure excerpt limits and source usage according to applicable licenses, permissions, and law.

## WP-Cron

WordPress Cron is traffic-driven. On low-traffic or production sites, disable the built-in runner only after adding a real scheduler:

```php
define( 'DISABLE_WP_CRON', true );
```

Example system cron (every five minutes):

```cron
*/5 * * * * curl -fsS https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

Or with WP-CLI:

```cron
*/5 * * * * cd /var/www/html && wp cron event run --due-now --quiet
```

The plugin uses an overlap lock and each source’s due time. A per-source frequency cannot run more often than the global cron event itself.

## Development and Testing

Install development dependencies:

```bash
composer install
```

Run syntax checks and unit tests:

```bash
composer lint
composer test
```

The dependency-free focused suite covers URL normalization, title similarity, keyword/category/date filtering, excerpt limits, prompt and response handling, credential encryption, capability/nonce enforcement, internal-link validation, attribution, and draft construction. Equivalent PHPUnit test cases are included for teams that use PHPUnit in their development workflow (`composer test:phpunit`).

For a clean-site smoke test:

```bash
wp plugin activate the-runbook-briefings
wp cron event list --fields=hook,next_run_relative | grep trb_import_feeds
wp option get trb_db_version
```

Then exercise the real path in admin:

```text
add source -> import -> inspect duplicate status -> edit/generate -> save draft -> verify attribution
```

No real provider call is made by automated tests. Provider response parsing and failure handling are tested locally; a live call requires the operator’s own account and incurs provider charges.

## Packaging

Run:

```bash
./bin/package.sh
```

The script lints plugin PHP when `php` is available and writes `the-runbook-briefings-1.0.0.zip` with one top-level plugin directory. Development files, credentials, tests, and repository metadata are excluded.

## Troubleshooting

See [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) for feed, cron, provider, permissions, encryption, duplicate, and draft issues.

## Known Limitations

- The MVP supports one OpenAI-compatible chat-completions provider implementation; the interface is ready for additional providers.
- Source filtering is allow-list based (“match any”) rather than a Boolean query language.
- Title similarity is heuristic and intentionally leaves probable duplicates recoverable.
- Internal-link candidates are drawn from the 20 most recent published posts; editors can enter other local links manually.
- WP-Cron timing depends on site traffic unless a system scheduler is configured.
- Factual quality still requires a human editor; a short feed excerpt may be insufficient for some stories.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
