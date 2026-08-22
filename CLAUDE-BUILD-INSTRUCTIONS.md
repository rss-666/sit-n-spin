# Claude Build Instructions

## Mission

Build a complete standalone WordPress plugin called **The Runbook Briefings**.

This plugin will monitor approved RSS feeds, identify useful hosting and WordPress security stories, create original factual briefings, and save them as WordPress drafts for editorial review.

This is a new standalone repository. Do not assume that any existing theme or plugin files are available. Create the complete plugin structure from scratch.

Do not build a plagiarism tool or a low-quality article spinner. Never copy or republish full source articles. The product should provide trustworthy, source-aware transformation with editorial control.

## Product Positioning

Use the name **The Runbook Briefings**, not "spinner."

Core value proposition:

> Turn hosting news and security alerts into original, engineer-reviewed Runbook briefings.

The plugin should help publishers produce useful analysis, not mass-produce rewritten articles.

## Required Workflow

1. An administrator can add, edit, enable, disable, and delete RSS feed sources.
2. Use WordPress built-in SimplePie and RSS functions.
3. Fetch feeds through a scheduled WP-Cron task.
4. Filter items by keywords, categories, source, date, and duplicate URL.
5. Store discovered items in a custom database table or a suitable custom post type.
6. Provide a WordPress admin review queue.
7. Provide an access-controlled affiliate review area where authorized affiliates can view proposed articles and approve them or request changes.
8. For each imported item, let an editor:
   - Generate a factual summary.
   - Generate a suggested headline.
   - Generate a "Why this matters" section.
   - Generate practical implications for site owners.
   - Generate related internal-link suggestions.
   - Edit generated content.
   - Save the result as a WordPress draft.
   - Dismiss the item.
9. Never publish automatically by default.
10. Include source attribution in every briefing.
11. Detect duplicates using normalized source URLs and title similarity.

## Required Attribution

Every generated briefing must include:

- Original source name.
- Original article title.
- Publication date.
- Source URL.
- Attribution text.
- Optional canonical link.

Never reproduce more than a short permitted excerpt. Keep source facts separate from original commentary.

## Content Rules

- Preserve factual accuracy.
- Do not invent prices, dates, statistics, quotes, features, or claims.
- Do not reproduce full source articles.
- Do not paraphrase every source sentence mechanically.
- Clearly distinguish source facts from Runbook analysis.
- Generate original practical insight for hosting and WordPress readers.
- Support technical, beginner-friendly, concise, and editorial tones.
- Include a visible attribution block.

## Settings

Create an admin settings page with:

- AI provider.
- API key stored securely in WordPress options.
- Model name.
- Default tone.
- Maximum source excerpt length.
- Cron frequency.
- Default post status.

The plugin must still import feeds and support manual editing when no AI API key is configured.

## AI Provider Design

Create a provider abstraction so the first provider can use an OpenAI-compatible API while allowing additional providers later.

The provider interface should handle:

- Prompt construction.
- Request timeouts.
- Authentication.
- Response parsing.
- API errors.
- Rate limits.
- Retry limits.

Never expose API keys in HTML, JavaScript, REST responses, logs, or error messages.

## Admin Interface

Add a top-level **Content Briefings** admin menu with:

- Dashboard.
- Sources.
- Review Queue.
- Settings.
- Logs.

Use normal WordPress admin UI conventions. Display these statuses:

- New.
- Processing.
- Drafted.
- Published.
- Dismissed.
- Error.

Include useful empty states and actionable error messages. Use WordPress APIs and vanilla JavaScript unless another approach is genuinely required.

## Affiliate Review Area

Create a dedicated, access-controlled review area for affiliate reviewers. This may be a protected WordPress page or another WordPress-native interface, but it must not expose proposed articles publicly or require affiliates to have unrestricted administrator access.

The affiliate review area must:

- Display the proposed headline, briefing, Runbook analysis, practical implications, and complete source attribution.
- Provide a link to the original source.
- Show the article's current review status and relevant review history.
- Allow an authorized affiliate to approve the article.
- Allow an authorized affiliate to request changes with a required or strongly encouraged review note.
- Restrict each affiliate to articles assigned or explicitly shared with them.
- Verify capabilities, nonces, and ownership or assignment on every review action.
- Record reviewer, decision, note, and timestamp without exposing API credentials or private logs.

Affiliate approval must advance the editorial workflow only. It must never publish content automatically unless a future, separately configured workflow explicitly permits that action.

## Data Model

Define a clean schema for:

- RSS sources.
- Imported items.
- Generated briefing data.
- Processing status.
- Error messages.
- Timestamps.
- Source attribution.
- Linked WordPress draft ID.

## Security Requirements

Protect every admin, REST, and AJAX action with:

- `current_user_can()` checks.
- Nonce verification.
- Strict input validation.
- Sanitization on input.
- Escaping on output.
- Safe database queries using WordPress APIs.
- Secure handling of API credentials.

Add feed-fetch timeouts, safe URL validation, retry limits, and useful logs without recording secrets.

Support PHP 8.1+ and modern WordPress.

## Testing Requirements

Create focused tests or testable helper functions for:

- URL normalization.
- Duplicate detection.
- Feed parsing.
- Keyword filtering.
- Excerpt length enforcement.
- Prompt construction.
- API error handling.
- Permission checks.
- Draft creation.
- Attribution output.

## Documentation Requirements

Create documentation covering:

- Installation.
- Configuration.
- Adding feeds.
- Configuring an AI provider.
- The editorial workflow.
- Privacy and API-key handling.
- Copyright and attribution safeguards.
- WP-Cron setup.
- Troubleshooting.
- Testing.
- Packaging the plugin for installation.

## Required Development Process

Follow this process and keep working in the same repository until all milestones are complete.

### Milestone 1: Scaffold

- Create the plugin structure.
- Add the plugin bootstrap file.
- Add activation and deactivation hooks.
- Verify the plugin activates without fatal errors.
- Run PHP lint.

### Milestone 2: Storage and Sources

- Implement the data model.
- Add source management.
- Add validation and capability checks.
- Add tests for storage and source validation.
- Run lint and tests before continuing.

### Milestone 3: RSS Importer

- Implement scheduled feed importing.
- Add filtering and duplicate detection.
- Add timeout and error handling.
- Add importer tests.
- Run lint and tests before continuing.

### Milestone 4: Review Queue

- Build the admin review queue.
- Add status handling.
- Add dismiss and retry actions.
- Add permission and nonce checks.
- Run lint and tests before continuing.

### Milestone 5: AI Integration

- Implement the provider interface.
- Add settings and secure credential handling.
- Add prompt construction.
- Add graceful behavior when no API key is configured.
- Add API error tests.
- Run lint and tests before continuing.

### Milestone 6: Draft Creation

- Generate the briefing fields.
- Add source attribution.
- Add internal-link suggestions.
- Create WordPress drafts only after editor action.
- Run lint and tests before continuing.

### Milestone 7: Hardening

- Review all permissions, nonces, validation, escaping, database queries, and external requests.
- Check that secrets never appear in logs or responses.
- Test duplicate and failure scenarios.
- Run the complete available test suite.

### Milestone 8: Documentation and Packaging

- Complete the README and troubleshooting guide.
- Add installation instructions.
- Confirm the plugin can be installed on a clean WordPress site.
- Run PHP lint on every PHP file.
- Run all available tests.
- Summarize completed work and remaining limitations.

## Rules For Claude

- Inspect the repository before editing.
- At the beginning of each milestone, state the files you plan to create or modify.
- Do not stop at a proposal or pseudocode.
- Implement the current milestone before discussing the next one.
- Do not move to the next milestone until the current milestone passes its checks.
- If a check fails, repair the current milestone and rerun the same check.
- Do not use fake API responses as the final implementation.
- Do not modify unrelated projects or files.
- Do not add a theme redesign.
- Do not auto-publish imported or generated content.
- Do not copy complete source articles.
- Keep dependencies minimal and explain every external dependency.
- Report changed files, commands run, test results, and known limitations at the end of each milestone.

## Definition Of Done

The project is complete only when:

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
- Tests pass or documented reasons explain unavailable tests.
- Installation and configuration documentation is complete.
