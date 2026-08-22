# Troubleshooting The Runbook Briefings

## A source will not save

- Use a complete public `http://` or `https://` RSS/Atom URL.
- Localhost, private/reserved addresses, malformed URLs, and non-HTTP schemes are rejected.
- Ensure the feed is not already configured; normalized feed URLs are unique.
- If a public hostname resolves to a private address inside your network, WordPress safe URL validation will reject it by design.

## A feed shows an error

Open **Content Briefings → Logs** and check the source health message.

- Load the feed URL without authentication and verify it returns RSS or Atom XML.
- Confirm outbound HTTPS is allowed from the WordPress server.
- Check DNS and TLS certificate validity.
- Increase the request timeout under **Settings**, up to 60 seconds.
- The importer follows at most three redirects; configure the final feed URL when possible.
- A malformed feed may need correction by its publisher. The plugin uses WordPress/SimplePie parsing and does not attempt to repair arbitrary HTML.

Errors do not erase existing queue items. A later scheduled or manual run retries the source.

## A successful feed imports no items

A source can be healthy while all entries are filtered. Check:

- Keywords use “match any” against title and feed description.
- Categories must match labels actually supplied by the feed.
- The global maximum item age may exclude older posts.
- The URL may already exist after tracking parameters and URL variants are normalized.
- A similar recent title may appear as a dismissed probable duplicate.
- The per-run new-item limit may have been reached; run another import.

## WP-Cron does not run

Check the event:

```bash
wp cron event list --fields=hook,next_run,next_run_relative | grep trb_import_feeds
```

If it is missing, deactivate and reactivate the plugin or save the plugin settings to reschedule it. If it is late, confirm loopback requests work under **Tools → Site Health**. Low-traffic sites should use a system cron job as documented in the root README.

The importer sets a ten-minute overlap lock. A run started while another is active is skipped and logged. A lock is removed in a `finally` block and on deactivation; if a PHP process was forcibly killed, it expires automatically.

## “No AI API key is configured”

This is not an import failure. Either:

- continue writing all briefing fields manually, or
- ask an administrator to configure **Content Briefings → Settings → API key**.

The saved key is intentionally never redisplayed.

## The API key cannot be saved

Authenticated encryption requires PHP Sodium or OpenSSL. Enable one extension and retry. The plugin refuses to fall back to plaintext storage.

Changing WordPress authentication salts invalidates an existing encrypted key. This is a secure failure: enter and save the provider key again after rotating salts.

## The AI provider rejects a request

- **401/403:** replace the key and verify account/model access.
- **429:** wait for the provider limit to reset; the plugin also permits at most ten generation actions per WordPress user per minute.
- **5xx/transport:** check provider status and outbound connectivity. The configured retry limit applies only to transport, 429, and 5xx failures.
- **Invalid structured JSON:** use a chat-completions model that supports JSON response format, or adapt a trusted endpoint through the documented filter.

Raw API responses, authorization headers, and keys are not logged. Errors intentionally provide bounded diagnostic detail.

## An internal-link suggestion disappeared

AI suggestions are retained only when their URL exactly matches one of the internal candidates sent with the prompt. This prevents invented links. Manually entered suggestions must use the same hostname as the site and the format:

```text
Title | https://example.com/local-post/ | Relevance note
```

At most three suggestions are retained.

## A title was marked as a duplicate incorrectly

Open the dismissed item and inspect the linked item number and confidence. Choose **Return to queue** if it is a distinct story. A developer can raise the `trb_duplicate_title_threshold` filter; see the root README.

## Draft creation fails

- Add a non-empty factual summary.
- Confirm your role has `review_runbook_briefings` and can create/edit posts.
- If updating a linked draft, confirm you still have permission to edit that post.
- Check that another plugin is not rejecting `wp_insert_post`/`wp_update_post`.

The plugin only accepts `draft` or `pending` as configured statuses. It never offers automatic publication.

## An editor cannot see the menu

Activation grants `review_runbook_briefings` to the Administrator and Editor roles. If roles were recreated after activation, grant the capability with a role editor or reactivate the plugin. Source, settings, and log screens additionally require `manage_options`.

## Resetting or removing data

Deactivation retains all data. To remove it on uninstall:

1. Enable **Delete plugin tables and settings when the plugin is uninstalled**.
2. Save settings.
3. Delete the plugin from WordPress Plugins.

This drops only the plugin’s custom tables and option. WordPress posts already created by editors and their attribution meta remain standard site content.
