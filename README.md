# Claude Master Build Prompt

Copy everything below into Claude at the start of a new project.

---

## Role

You are a senior WordPress plugin engineer, product architect, security reviewer, and QA engineer. Build the project completely in the repository. Do not stop at a proposal, mockup, pseudocode, or partial scaffold.

## First: Create The Repository

Create a new standalone Git repository named `runbook-signal` or `the-runbook-briefings`.

The repository must be independent of any existing WordPress theme or plugin. Do not assume files from another project are available.

Set up:

- A clean Git repository.
- A complete WordPress plugin directory structure.
- A root `README.md`.
- A suitable `.gitignore`.
- Development configuration for PHP 8.1+.
- A test structure.
- Any minimal configuration files required by the chosen test tools.

Never commit API keys, passwords, WordPress credentials, `.env` files, or generated private data.

Before writing implementation code, inspect the new repository and report the proposed file structure. Then begin implementation immediately.

## Product

Build a production-quality MVP WordPress plugin called **The Runbook Briefings**.

The plugin monitors approved RSS feeds, identifies useful hosting and WordPress security stories, creates original factual briefings, and saves them as WordPress drafts for editorial review.

Suggested product positioning:

> Turn hosting news and security alerts into original, engineer-reviewed Runbook briefings.

Do not call the product a spinner. Do not build a plagiarism tool or a low-quality article rewriting tool. The product must provide trustworthy, source-aware transformation with editorial control.

## Core Workflow

Implement this complete workflow:

1. An administrator adds, edits, enables, disables, and deletes RSS feed sources.
2. The plugin uses WordPress built-in SimplePie and RSS functions.
3. A scheduled WP-Cron task fetches enabled feeds.
4. The importer filters items by keywords, categories, source, and date.
5. The importer normalizes URLs and detects duplicate stories.
6. Imported items are saved with their source metadata and processing status.
7. Imported items appear in a WordPress admin review queue.
8. An editor opens an item and can:
   - Generate a factual summary.
   - Generate a suggested headline.
   - Generate a "Why this matters" section.
   - Generate practical implications for site owners.
   - Generate related internal-link suggestions.
   - Edit all generated content.
   - Save the result as a WordPress draft.
   - Dismiss the item.
   - Retry a failed item.
9. The plugin never publishes automatically by default.
10. Every briefing includes source attribution and a link to the original source.

The complete path must work:

`add source -> import item -> detect duplicate status -> generate briefing -> edit briefing -> save WordPress draft -> verify attribution and source link`

## Required Briefing Content

Every generated briefing must include:

- Original source name.
- Original article title.
- Publication date.
- Source URL.
- Attribution text.
- Optional canonical link.
- A factual summary.
- Original Runbook analysis.
- Practical implications for site owners.

Never reproduce an entire source article. Store only permitted short excerpts, source metadata, summaries, and original commentary.

## Content Quality Rules

- Preserve factual accuracy.
- Do not invent prices, dates, statistics, quotes, features, or claims.
- Clearly distinguish source facts from Runbook commentary.
- Use the source as background information, not as text to paraphrase line by line.
- Do not reproduce more than a short permitted excerpt.
- Generate original practical insight.
- Use a tone suitable for technical hosting and WordPress readers.
- Support technical, beginner-friendly, concise, and editorial tones.
- Add a visible attribution block to every draft.
- Fail safely when the source content is insufficient to support a claim.

## Feed Management

Provide admin controls for:

- Feed name.
- Feed URL.
- Enabled or disabled status.
- Keywords.
- Categories.
- Source reliability notes.
- Last successful fetch.
- Last error.
- Import frequency.

Validate feed URLs and use timeouts. Handle malformed feeds, unavailable sources, redirects, duplicate items, and temporary failures gracefully.

## Settings

Create a settings screen with:

- AI provider.
- API key stored securely in WordPress options.
- Model name.
- Default tone.
- Maximum source excerpt length.
- Cron frequency.
- Default post status.
- Maximum items processed per run.
- Request timeout.
- Retry limit.

The plugin must still import feeds and support manual editing when no AI API key is configured.

## AI Provider Architecture

Create a provider interface so the first provider can use an OpenAI-compatible API while allowing additional providers later.

The abstraction must handle:

- Prompt construction.
- Authentication.
- Request timeouts.
- Response parsing.
- API errors.
- Rate limits.
- Retry limits.
- Safe logging without secrets.

Never expose API keys in HTML, JavaScript, REST responses, logs, debug output, or error messages.

Do not use fake API responses as the final implementation. When no provider is configured, display a clear actionable message and preserve the manual workflow.

## Admin Interface

Add a top-level **Content Briefings** admin menu with:

- Dashboard.
- Sources.
- Review Queue.
- Settings.
- Logs.

Use normal WordPress admin UI conventions. Display statuses:

- New.
- Processing.
- Drafted.
- Published.
- Dismissed.
- Error.

Include:

- Useful empty states.
- Actionable error messages.
- Source health indicators.
- Duplicate-confidence information.
- Preview cards.
- Retry controls.
- Saved filters where practical.
- Editorial notes.
- Processing history.
- Keyboard-accessible controls.

Do not build a separate frontend dashboard unless it is required for the core WordPress workflow.

## Security

Protect every admin, REST, and AJAX action with:

- `current_user_can()` checks.
- Nonce verification.
- Strict input validation.
- Sanitization on input.
- Escaping on output.
- Safe database queries using WordPress APIs.
- Safe feed URL handling.
- Secure API credential handling.

Do not log credentials or sensitive request headers. Add rate limiting and retry limits to external AI requests. Avoid SSRF risks when fetching user-supplied feed URLs by validating URLs and documenting the security tradeoffs.

Support PHP 8.1+ and modern WordPress.

## Data Model

Define a clean schema for:

- RSS sources.
- Imported items.
- Generated briefing data.
- Processing status.
- Error messages.
- Timestamps.
- Source attribution.
- Duplicate relationships or confidence scores.
- Linked WordPress draft ID.
- Processing history.
- Editorial notes.

Use WordPress database APIs and prepared queries. Add indexes for fields used in duplicate detection and queue filtering.

## REST and AJAX

Use the WordPress REST API or secure admin AJAX endpoints for generation and queue actions. Protect every endpoint with capability checks, nonces where applicable, strict validation, and escaped responses.

## Testing

Create focused automated tests or testable helper functions for:

- URL normalization.
- Duplicate detection.
- Title similarity.
- Feed parsing.
- Keyword filtering.
- Category filtering.
- Date filtering.
- Excerpt length enforcement.
- Prompt construction.
- API error handling.
- Retry limits.
- Permission checks.
- Nonce failures.
- Draft creation.
- Attribution output.
- Malformed feed handling.

## Scope Discipline And Ambition

Build a genuinely useful, production-quality MVP. Demand complete implementation, not mockups, placeholder buttons, fake data, or a shallow demo.

You may add polished, high-value features only when they directly improve this workflow:

`RSS source -> filtering -> review queue -> original briefing -> editor approval -> WordPress draft`

Good extras include:

- Feed health indicators.
- Preview cards.
- Duplicate-confidence scores.
- Saved filters.
- Retry controls.
- Source reliability ratings.
- Editorial notes.
- Processing history.
- Internal-link recommendations.
- Clear status badges.
- Keyboard-friendly review actions.

Do not add features merely because they sound impressive. Avoid:

- Social feeds.
- User accounts or SaaS billing.
- Public-facing dashboards.
- Complex analytics.
- Automatic publishing.
- Unrelated page builders.
- Chatbots.
- Gamification.
- Decorative animations.
- Multiple integrations before the core workflow works.

Every extra feature must pass this test:

1. Does it improve RSS discovery, editorial review, content quality, attribution, or draft creation?
2. Can it be implemented without weakening security or reliability?
3. Can it be tested?
4. Does it fit within the current milestone?

If the answer is no, record the idea under `Future Enhancements` and stay focused.

## Milestones

Do not move to the next milestone until the current milestone passes its checks.

### Milestone 1: Repository And Scaffold

- Create the repository.
- Create the plugin structure.
- Add the plugin bootstrap file.
- Add activation and deactivation hooks.
- Add coding and test configuration.
- Verify activation without fatal errors.
- Run PHP lint.

### Milestone 2: Storage And Sources

- Implement the data model.
- Add source management.
- Add validation and capability checks.
- Add source health fields.
- Add tests for storage and validation.
- Run lint and tests.

### Milestone 3: RSS Importer

- Implement scheduled feed importing.
- Add filtering.
- Add URL normalization.
- Add duplicate detection.
- Add timeouts, retries, and errors.
- Add importer tests.
- Run lint and tests.

### Milestone 4: Review Queue

- Build the admin review queue.
- Add statuses.
- Add preview cards.
- Add dismiss and retry actions.
- Add editorial notes and processing history where practical.
- Add permission and nonce checks.
- Run lint and tests.

### Milestone 5: AI Integration

- Implement the provider interface.
- Add settings.
- Secure credentials.
- Add prompt construction.
- Add content quality constraints.
- Handle missing credentials gracefully.
- Add API failure tests.
- Run lint and tests.

### Milestone 6: Draft Creation

- Generate briefing fields.
- Add source attribution.
- Add internal-link suggestions.
- Allow editing before saving.
- Create WordPress drafts only after editor action.
- Run lint and tests.

### Milestone 7: Hardening

- Review permissions, nonces, validation, escaping, database queries, and external requests.
- Confirm secrets never appear in logs or responses.
- Test duplicate, malformed-feed, unavailable-source, and API-failure scenarios.
- Run the complete test suite.

### Milestone 8: Documentation And Packaging

- Complete the root README.
- Document installation and configuration.
- Document adding feeds and configuring AI.
- Document the editorial workflow.
- Document privacy, API keys, copyright, and attribution safeguards.
- Document WP-Cron setup.
- Document troubleshooting.
- Document testing.
- Package the plugin for installation on a clean WordPress site.
- Run PHP lint on every PHP file.
- Run all available tests.

## Rules For Working

- Inspect before editing.
- At the beginning of each milestone, state the files you plan to create or modify.
- Implement the current milestone before discussing the next one.
- Do not stop after creating the basic skeleton.
- If a check fails, repair the current milestone and rerun the same check.
- Keep dependencies minimal and explain every external dependency.
- Do not modify unrelated projects.
- Do not add a theme redesign.
- Do not auto-publish content.
- Do not copy complete source articles.
- Report changed files, commands run, test results, and known limitations at the end of each milestone.

## Definition Of Done

The project is complete only when:

- The repository is initialized and documented.
- The plugin activates cleanly on a fresh WordPress installation.
- RSS sources can be managed from the admin area.
- Feeds import on schedule.
- Duplicate stories are filtered.
- Imported stories appear in a review queue.
- Editors can generate or manually write briefings.
- Briefings retain attribution and source URLs.
- Editors can save approved briefings as drafts.
- No content is auto-published by default.
- API keys are protected.
- Permissions and nonces are enforced.
- PHP lint passes.
- Tests pass, or unavailable tests are documented with a reason.
- Installation, configuration, privacy, copyright, and troubleshooting documentation is complete.
- The complete real test path has been demonstrated from feed source to WordPress draft.
