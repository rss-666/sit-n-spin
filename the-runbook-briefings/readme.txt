=== The Runbook Briefings ===
Contributors: therunbook
Tags: rss, editorial workflow, security, hosting, content curation
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn approved hosting and WordPress security RSS stories into original, source-attributed drafts for editorial review.

== Description ==

The Runbook Briefings provides a human-controlled workflow:

RSS source -> filtering -> review queue -> original briefing -> editor approval -> WordPress draft

Manage approved feeds, import short excerpts with WordPress SimplePie, filter and detect duplicates, write manually or request structured suggestions from an OpenAI-compatible provider, and create an attributed WordPress draft only after explicit editor action.

The plugin does not automatically publish and does not store or reproduce complete source articles.

== Installation ==

1. Upload the `the-runbook-briefings` directory to `/wp-content/plugins/`, or upload the release ZIP through Plugins -> Add New Plugin.
2. Activate The Runbook Briefings.
3. Review Content Briefings -> Settings.
4. Add an approved public feed under Content Briefings -> Sources.
5. Choose Fetch Now beside one source, or Run import now from the dashboard, then review items in Content Briefings -> Review Queue.

== Recommended Test Feeds ==

For the first test, leave Keywords and Categories blank so filters cannot hide valid items.

* WordPress.org Security: `https://wordpress.org/news/category/security/feed/`
* Wordfence Security: `https://www.wordfence.com/blog/feed/`
* Sucuri Security Blog: `https://blog.sucuri.net/feed`
* Official WordPress News: `https://wordpress.org/news/feed/`
* WP Tavern: `https://wptavern.com/feed`

Start with WordPress.org Security, run an import, and then run the same feed again to exercise duplicate detection. After the first successful import, useful keyword filters include WordPress, vulnerability, security, exploit, malware, plugin, patch, and update.

Review each source's reliability and usage terms before using it in an editorial workflow. These URLs are testing suggestions, not an endorsement of every article.

== Frequently Asked Questions ==

= Is an AI provider required? =

No. Feed importing, editing, attribution, and draft creation all work in manual mode.

= Does the plugin publish automatically? =

No. It can only create draft or pending posts after an editor clicks the draft action. Publishing remains a normal WordPress editorial action.

= How is the API key protected? =

It is encrypted with authenticated encryption derived from WordPress salts and is never redisplayed, sent to browser JavaScript, or logged. Sodium or OpenSSL is required to save a key.

= Does it copy source articles? =

No. The importer stores source metadata and a configurable plain-text excerpt of 100 to 5,000 characters. The default is 1,200 characters. Every draft includes visible source attribution.

== Changelog ==

= 1.0.3 =
* Fixed the Generate suggestions button becoming stuck before form submission in some browsers.
* Clarified that denied Apache server-status probes are unrelated to plugin generation.

= 1.0.2 =
* Fixed a critical error when opening a newly imported item whose editable briefing fields are still empty.
* Added regression coverage for untouched queue items with null generated fields.

= 1.0.1 =
* Added a per-source Fetch Now action with immediate source health and import counts.
* Added recommended RSS test feeds to the bundled documentation.

= 1.0.0 =
* Initial production-quality MVP.
* Added feed source management, WP-Cron importing, filtering, normalization, and duplicate detection.
* Added review queue, manual editing, OpenAI-compatible generation, processing history, and logs.
* Added editor-triggered draft creation with source attribution.
* Added encrypted credential storage and security hardening.
