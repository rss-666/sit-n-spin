# Plague Dr Suno Publisher

## Purpose

Plague Dr Suno Publisher accepts a public Suno song URL and either:

- creates a native Soundtrack entry for **The Plague Dr Universe** theme; or
- dynamically places Suno's hosted player on a selected WordPress page/post.

The plugin is an adapter. It does not modify theme files.

## Recommended Theme Workflow

1. Install and activate The Plague Dr Universe theme and Plague Dr Suno Publisher 1.1.0+.
2. Open **Plague Dr Music → Add from Suno**.
3. Paste a full public `https://suno.com/song/{uuid}` URL.
4. Keep **Publishing mode** set to **Plague Dr Universe Soundtrack**.
5. Review or enter the title, description/credits/lyrics, artwork, album, duration, genre, and buy/stream URL.
6. Optionally choose a direct audio file from Media Library.
7. Save as Draft, review the linked Soundtrack, and publish when ready.

The linked `pdu_track` then participates naturally in:

- the homepage **Now Streaming** query;
- the `/soundtracks/` archive cards;
- the media template's album grouping;
- individual themed Soundtrack pages;
- the theme's MusicRecording structured data.

## Hybrid Playback

### Native local audio

When a direct `.mp3`, `.ogg`, `.oga`, `.wav`, `.m4a`, or `.aac` URL is selected, the plugin writes it to `pdu_audio_url`. The theme's shared accessible HTML audio player handles playback on all theme surfaces.

### Hosted Suno fallback

A normal Suno song page is HTML—not an audio file—and must not be stored in `pdu_audio_url`.

When Local audio is empty, the plugin:

- stores the validated Suno ID separately;
- displays Suno's `/embed/{song-id}` player on the individual Soundtrack page;
- intercepts empty-source theme play buttons for managed songs and opens an accessible hosted-player dialog;
- sends older browsers to the individual track page if `<dialog>` is unavailable.

The iframe is generated only from a UUID-shaped Suno ID. Homepage modal iframes are not loaded until a visitor presses play.

## Synchronized Theme Data

Each managed song owns one linked `pdu_track`. The plugin synchronizes:

| Managed song | Theme Soundtrack |
|---|---|
| Title | `post_title` |
| Description/credits/lyrics | `post_content` and bounded `post_excerpt` |
| Draft/Active status | Native track status |
| Album/release | `pdu_album` |
| Duration | `pdu_duration` |
| Genre | `pdu_genre` taxonomy |
| Buy/stream URL | `pdu_buy_url` |
| Direct local audio | `pdu_audio_url` |
| Imported artwork | Featured image |

Bidirectional protected metadata prevents duplicate Soundtrack records. Updating the managed song updates its existing track. Switching back to page placement moves the linked Soundtrack to Draft rather than deleting editorial work.

## Generic Page Placement

Choose **Page/post destination** to keep the original reversible behavior. An Active song can appear before or after any editable public page/post without rewriting that destination's `post_content`.

For exact block-level placement, use:

```text
[plague_dr_song id="123"]
```

## Artwork

When **Import this artwork into the Media Library as the theme cover image** is checked, the plugin safely sideloads the validated HTTPS artwork and sets it as the linked Soundtrack's featured image. Failed artwork imports do not block track synchronization; set a cover manually if needed.

Only import artwork you have the right to publish.

## Security

- Adding songs requires `edit_pages`; immediate activation requires `publish_pages`.
- Editing placement requires permission to edit both the managed song and selected destination.
- Add and edit actions use WordPress nonces.
- Only exact Suno hosts and UUID-shaped song IDs are accepted.
- Short-link redirects remain restricted to Suno hosts and safe WordPress HTTP requests.
- External requests have strict timeouts and response-size limits.
- Direct audio accepts only safe public HTTP(S) URLs with an approved audio extension.
- Suno embed URLs are generated from validated IDs rather than arbitrary iframe HTML.
- Song text is sanitized and output is escaped.
- The theme is never edited by the plugin.

## Troubleshooting

### Track appears but the theme play button says preview unavailable

Edit the managed Plague Dr Song and verify it is using **Theme Soundtrack** mode. If Local audio is empty, confirm the plugin's `pdu-integration.js` is not blocked by optimization or security software and clear page/CDN caches. The play button should open the hosted-player dialog.

### Native playback fails

Confirm Local audio points directly to a supported audio file—not a Suno page, attachment page, or HTML download page. Open the URL in a private browser window; it should return playable audio.

### Soundtrack does not appear

- Confirm both the managed song and linked Soundtrack are Published.
- Open the linked Soundtrack from the Suno Placement meta box.
- Clear page/CDN caches.
- Confirm The Plague Dr Universe theme still registers `pdu_track`.

### Metadata or artwork is blank

Metadata lookup is optional and may be challenged by Suno. Enter fields manually. Cover-image import can fail when a remote host blocks server requests; use WordPress's normal Featured Image control on the linked Soundtrack.

### Short link will not resolve

Open it in a browser and copy the resulting full `/song/{uuid}` URL.

## Uninstall Behavior

Managed song and linked Soundtrack records are retained to prevent accidental loss. Native tracks with local audio continue working after plugin removal. Hosted-only tracks need the plugin for the Suno fallback player.
