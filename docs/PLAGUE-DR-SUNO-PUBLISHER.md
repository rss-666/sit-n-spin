# Plague Dr Suno Publisher

## Purpose

Plague Dr Suno Publisher manages Suno, local-audio, YouTube, Vimeo, and direct-video music releases. One managed release can:

- create a native Soundtrack (`pdu_track`);
- create a native Music Video (`pdu_video`);
- create and synchronize both; or
- dynamically place Suno's hosted player on another WordPress page/post.

The plugin is an adapter. It does not modify theme files.

## Recommended Theme Workflow

1. Install and activate The Plague Dr Universe theme and Plague Dr Suno Publisher 1.4.1+.
2. Open **Plague Dr Music → Add from Suno**.
3. Choose **Theme Soundtrack**, **Theme Music Video**, **Theme Soundtrack + Music Video**, or page placement.
4. Provide a Suno URL and/or local Media Library audio for Soundtrack playback.
5. Provide a YouTube, Vimeo, or direct `.mp4`/`.webm`/`.mov` URL for Music Video publishing.
6. Enter the shared title, description/credits, dedicated lyrics, duration, and artwork plus applicable track/video metadata.
7. Submit once. The confirmation panel shows exactly what was created, its status, and direct View/Edit buttons for the managed release, Soundtrack, Video Release, or selected destination.

Theme publishing is automatic after that submission. A shortcode or second URL entry is not required. Shortcodes remain available only for optional manual placement inside another layout.

The linked `pdu_track` then participates naturally in:

- the homepage **Now Streaming** query;
- the `/soundtracks/` archive cards;
- the media template's album grouping;
- individual themed Soundtrack pages;
- the theme's MusicRecording structured data.

A linked `pdu_video` participates in the homepage video query, `/videos/` archive, individual themed Video Release page, and theme VideoObject structured data. The theme handles YouTube/Vimeo through WordPress oEmbed and direct video files through its native video element.

## YouTube and Video Releases

YouTube and Vimeo URLs are video/webpage sources, never direct audio. The plugin validates them separately and writes them only to `pdu_video_url`. Video-only mode does not require Suno. Both mode creates one Soundtrack and one Video Release, relates each back to the same managed release, and updates rather than duplicates them.

Lyrics remain a single editor-controlled record. They render beneath both linked native pages while staying out of homepage rows and archive excerpts.

## Hybrid Playback

### Native local audio

When a direct `.mp3`, `.ogg`, `.oga`, `.wav`, `.m4a`, or `.aac` URL is selected, the plugin writes it to `pdu_audio_url`. The theme's shared accessible HTML audio player handles playback on all theme surfaces.

### Hosted Suno fallback

A normal Suno song page is HTML—not an audio file—and must not be stored in `pdu_audio_url`.

When Local audio is empty, the plugin:

- stores the validated Suno ID separately;
- displays Suno's `/embed/{song-id}` player on the individual Soundtrack page;
- intercepts empty-source theme play buttons for managed songs and opens an accessible hosted-player dialog;
- keeps the player visible above an independently scrollable lyrics panel with preserved line breaks;
- includes the highlighted artist credit and a direct full-Soundtrack link;
- sends older browsers to the individual track page if `<dialog>` is unavailable.

The iframe is generated only from a UUID-shaped Suno ID. Homepage modal iframes are not loaded until a visitor presses play.

## Synchronized Theme Data

Each managed release can own one linked `pdu_track`, one linked `pdu_video`, or both. Soundtrack synchronization includes:

| Managed song | Theme Soundtrack |
|---|---|
| Title | `post_title` |
| Artist / featured artist | Plugin-rendered highlighted artist credit |
| Description/credits | `post_content` and bounded `post_excerpt` |
| Draft/Active status | Native track status |
| Album/release | `pdu_album` |
| Duration | `pdu_duration` |
| Genre | `pdu_genre` taxonomy |
| Buy/stream URL | `pdu_buy_url` |
| Direct local audio | `pdu_audio_url` |
| Imported artwork | Featured image |
| Dedicated lyrics | Plugin-rendered Lyrics section on the linked track page |

Video synchronization includes:

| Managed release | Theme Video Release |
|---|---|
| Title | `post_title` |
| Artist / featured artist | Plugin-rendered highlighted artist credit |
| Description/credits | `post_content` and bounded `post_excerpt` |
| Draft/Active status | Native video status |
| YouTube/Vimeo/direct video | `pdu_video_url` |
| Duration | `pdu_duration` |
| Runtime/release note | `pdu_runtime_note` |
| Imported artwork | Featured image |
| Dedicated lyrics | Plugin-rendered Lyrics section |

Artist names are stored separately from descriptions and rendered in the theme's amber accent so featured-artist credits remain visually distinct. Lyrics are stored separately from descriptions and credits, excluded from homepage rows and archive excerpts, and displayed in centered theme-styled reading columns on both native pages (plus a centered collapsible section on generic cards). Line breaks and safe basic formatting are preserved.

Bidirectional protected metadata prevents duplicate Soundtrack and Video records. Switching modes moves unused linked records to Draft rather than deleting editorial work.

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
- Video accepts only validated YouTube/Vimeo hosts or safe direct video extensions.
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

Managed releases and linked Soundtrack/Video records are retained to prevent accidental loss. Native tracks with local audio and theme video embeds continue working after plugin removal. Hosted-only Suno tracks need the plugin for fallback playback and shared lyrics rendering.
