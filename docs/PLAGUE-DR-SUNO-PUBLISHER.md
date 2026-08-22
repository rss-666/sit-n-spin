# Plague Dr Suno Publisher

## Purpose

Plague Dr Suno Publisher accepts a public Suno song URL and displays Suno's hosted player on a selected WordPress page or post. The destination is stored on the managed song record rather than inserted into the destination's `post_content`, making placement reversible.

## Workflow

1. Install and activate the plugin.
2. Open **Plague Dr Music → Add from Suno**.
3. Paste a full public `https://suno.com/song/{uuid}` link. `/embed/` links are also accepted; `/s/` links are resolved when Suno permits it.
4. Optionally enter a title, description/credits/lyrics, and HTTPS artwork URL. Missing public metadata is requested from the Suno embed page.
5. Select any WordPress page or post the current user can edit.
6. Select before/after placement and Draft/Active visibility.
7. Edit the managed song later to move it to a different destination.

## Automatic vs manual placement

An **Active** song with a destination is dynamically added when WordPress renders that destination. Its database content is not rewritten. Setting the song to Draft hides the automatic placement.

For exact placement inside a block layout, use:

```text
[plague_dr_song id="123"]
```

Choose **No automatic destination** to use only the shortcode. A published song can be used on multiple pages by placing the shortcode more than once.

## Security

- Adding songs requires `edit_pages`; immediate activation requires `publish_pages`.
- Editing placement requires permission to edit both the song and destination.
- Add and edit actions use WordPress nonces.
- Only exact Suno hosts, HTTPS artwork, and UUID-shaped song identifiers are accepted.
- Short-link redirects remain restricted to Suno hosts and safe WordPress HTTP requests.
- External requests have strict timeouts, response-size limits, and no credentials.
- The embed URL is generated from a validated song ID rather than rendered from arbitrary HTML.
- Song text is sanitized with WordPress content APIs and output is escaped.

## Media and privacy

The plugin does not download, copy, or rehost audio. Suno hosts the iframe player and public artwork. Loading a player causes the visitor's browser to connect to Suno. Site owners should disclose this third-party media behavior in their privacy policy when appropriate.

Only use songs and artwork you have the right to publish. Suno availability and embed behavior remain controlled by Suno.

## Troubleshooting

### A short link will not resolve

Open it in a browser and copy the resulting full `/song/{uuid}` URL. Suno may challenge automated requests even when a browser can follow the link.

### The player does not appear

- Confirm the managed song status is **Active**, not Draft.
- Confirm the destination is selected and the destination itself is viewable.
- Check whether a security plugin or Content Security Policy blocks frames from `https://suno.com`.
- Open the original Suno URL and confirm the song is still public.
- Clear page/CDN caches after changing placement.

### Metadata is blank

Metadata is optional. Enter the title, text, and HTTPS artwork manually. Anti-bot responses can prevent server-side metadata lookup without affecting the public hosted player.

### A page builder does not show automatic placement in its preview

View the actual page. Some builders bypass WordPress's normal main `the_content` render in editor previews. Use the shortcode block for exact placement in custom builder layouts.

## Uninstall behavior

Uninstall removes the plugin version option but deliberately retains managed song posts and metadata to prevent accidental loss of titles, lyrics, and placement choices. Delete song records before uninstalling when permanent removal is desired.
