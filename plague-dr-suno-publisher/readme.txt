=== Plague Dr Suno Publisher ===
Contributors: plaguedr
Tags: suno, music, audio, embed, soundtracks
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish public Suno songs as native Plague Dr Universe Soundtracks or place them on any WordPress page.

== Description ==

Plague Dr Suno Publisher provides two publishing modes:

1. **Plague Dr Universe Soundtrack** — creates and synchronizes the theme's native `pdu_track` entry so the song appears in the homepage Now Streaming list, `/soundtracks/` archive, and themed individual track layout.
2. **Page/post destination** — dynamically places the hosted Suno player before or after another WordPress page or post without rewriting its stored content.

Every managed song also receives a shortcode:

`[plague_dr_song id="123"]`

=== Hybrid playback ===

- Choose a direct MP3/OGG/WAV/M4A/AAC file from the WordPress Media Library to use the theme's native shared audio player everywhere.
- Leave Local audio empty to use Suno's official hosted player. On the homepage and media track lists, the play button opens an accessible modal. The individual Soundtrack page displays the hosted player.
- A Suno webpage is never incorrectly passed to the HTML audio element as an audio file.

=== Theme metadata ===

Soundtrack mode synchronizes title, description, excerpt, status, album, duration, genre, buy/stream URL, and optional cover artwork. Updating the managed song updates the same linked Soundtrack instead of creating duplicates. New songs default to Draft.

== Installation ==

1. Upload `plague-dr-suno-publisher-1.1.0.zip` through Plugins -> Add New Plugin -> Upload Plugin.
2. Activate Plague Dr Suno Publisher.
3. Open Plague Dr Music -> Add from Suno.
4. Paste a public full Suno song URL.
5. Leave Publishing mode on Plague Dr Universe Soundtrack when that theme is active.
6. Add album, duration, genre, purchase URL, and optional local audio.
7. Save as Draft or Active.

== Frequently Asked Questions ==

= Does this modify The Plague Dr Universe theme? =

No. The integration is an adapter inside the plugin. Theme files remain untouched and updateable.

= Should I put the Suno URL in Audio File URL? =

No. The theme's Audio File URL expects direct audio bytes such as an MP3. The plugin stores the Suno ID separately and renders the hosted player when no direct file is selected.

= How do I use the native theme player? =

Download a song you own through your Suno account, upload the audio file to WordPress Media Library, and choose it under Local audio. Only use media you have the right to publish.

= Will syncing create duplicate Soundtrack posts? =

No. The plugin stores relationships in both directions and updates the same `pdu_track` entry. Trashing the managed song also trashes its linked Soundtrack.

= Can I still place a player on another page? =

Yes. Choose Page/post destination, or use the shortcode in a Shortcode block.

= Why did a Suno short share link fail? =

Suno or its anti-bot layer may prevent server-side short-link resolution. Open it in your browser and paste the resulting full `https://suno.com/song/…` URL.

== Privacy ==

A metadata request is sent to Suno only when a song is added and public title, description, or artwork is needed. The hosted iframe is loaded on an individual track page or after a visitor presses a hosted track's play button. No Suno account credential or API key is collected.

== Changelog ==

= 1.1.0 =
* Added native The Plague Dr Universe `pdu_track` synchronization.
* Added album, duration, genre, buy/stream URL, local audio, and cover-art fields.
* Added Media Library audio selection and native-player precedence.
* Added hosted Suno fallback player for individual Soundtrack pages.
* Added accessible hosted-player modal fallback for homepage and media track lists.
* Added bidirectional relationships, status synchronization, and duplicate prevention.

= 1.0.0 =
* Initial release with secure Suno URL validation, page destinations, hosted players, metadata lookup, shortcodes, and draft visibility.
