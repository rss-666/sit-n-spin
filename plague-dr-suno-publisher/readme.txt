=== Plague Dr Suno Publisher ===
Contributors: plaguedr
Tags: suno, music, audio, embed, soundtracks
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Suno, local-audio, YouTube, and Vimeo releases as native Plague Dr Universe music content.

== Description ==

Plague Dr Suno Publisher provides four publishing modes:

1. **Theme Soundtrack only** — creates and synchronizes native `pdu_track` content for Now Streaming and `/soundtracks/`.
2. **Theme Music Video only** — creates and synchronizes native `pdu_video` content for the homepage video section and `/videos/` without requiring Suno.
3. **Theme Soundtrack + Music Video** — creates one linked entry of each type from one managed release.
4. **Page/post destination** — dynamically places a hosted Suno player before or after another WordPress page/post.

Every managed song also receives a shortcode:

`[plague_dr_song id="123"]`

=== Hybrid playback ===

- Choose a direct MP3/OGG/WAV/M4A/AAC file from the WordPress Media Library to use the theme's native shared audio player everywhere.
- Leave Local audio empty to use Suno's official hosted player. On the homepage and media track lists, the play button opens an accessible modal. The individual Soundtrack page displays the hosted player.
- A Suno webpage is never incorrectly passed to the HTML audio element as an audio file.

=== Theme metadata and shared lyrics ===

Soundtrack mode synchronizes title, description, excerpt, status, album, duration, genre, buy/stream URL, and cover artwork. Video mode synchronizes title, description, status, duration, runtime note, YouTube/Vimeo/direct-video URL, and artwork. Lyrics are stored once and rendered on both linked pages without entering archive excerpts. Updating a release updates the same linked native records instead of creating duplicates.

== Installation ==

1. Upload `plague-dr-suno-publisher-1.3.2.zip` through Plugins -> Add New Plugin -> Upload Plugin.
2. Activate Plague Dr Suno Publisher.
3. Open Plague Dr Music -> Add from Suno.
4. Choose Soundtrack, Music Video, Both, or page placement.
5. Provide Suno/local audio for a Soundtrack and/or YouTube/Vimeo for a Video Release.
6. Add shared lyrics, description, duration, artwork, and the applicable track/video metadata.
7. Save as Draft or Active.

== Frequently Asked Questions ==

= Does this modify The Plague Dr Universe theme? =

No. The integration is an adapter inside the plugin. Theme files remain untouched and updateable.

= Should I put the Suno URL in Audio File URL? =

No. The theme's Audio File URL expects direct audio bytes such as an MP3. The plugin stores the Suno ID separately and renders the hosted player when no direct file is selected.

= How do I use the native theme player? =

Download a song you own through your Suno account, upload the audio file to WordPress Media Library, and choose it under Local audio. Only use media you have the right to publish.

= Will syncing create duplicate Soundtrack or Video posts? =

No. The plugin stores relationships in both directions and updates the same linked `pdu_track` and `pdu_video` entries. Trashing the managed release also trashes both linked records.

= Where are lyrics stored when I publish both? =

Lyrics are stored once on the managed release. The plugin renders that same reviewed text on both linked pages, while keeping it out of homepage rows and archive excerpts.

= Can I still place a player on another page? =

Yes. Choose Page/post destination, or use the shortcode in a Shortcode block.

= Why did a Suno short share link fail? =

Suno or its anti-bot layer may prevent server-side short-link resolution. Open it in your browser and paste the resulting full `https://suno.com/song/…` URL.

== Privacy ==

A metadata request is sent to Suno only when a song is added and public title, description, or artwork is needed. The hosted iframe is loaded on an individual track page or after a visitor presses a hosted track's play button. No Suno account credential or API key is collected.

== Changelog ==

= 1.3.2 =
* Centered and reduced the weight/size of hero titles on plugin-managed native pages.
* Tightened hero spacing so the player and lyrics are easier to discover below the fold.
* Added contextual Listen/Watch/Lyrics scroll cues without changing unrelated theme content.

= 1.3.1 =
* Added a clear post-submit confirmation panel with direct View/Edit links for every synchronized native entry.
* Clarified when no shortcode or second URL entry is required.
* Fixed hosted player and lyrics visibility when a linked native post is public but the private managed record status differs.

= 1.3.0 =
* Added YouTube, Vimeo, and direct-video source validation.
* Added Soundtrack only, Music Video only, Soundtrack + Music Video, and page-placement publishing modes.
* Added native `pdu_video` synchronization with duration, runtime note, artwork, status, and duplicate prevention.
* Shared one lyrics record across linked Soundtrack and Music Video pages.
* Added video-only releases that do not require a Suno URL.

= 1.2.0 =
* Added a dedicated lyrics editor separate from descriptions and credits.
* Added collapsible lyrics to generic song cards.
* Added a theme-styled Lyrics section to native Soundtrack pages with either local or hosted playback.
* Kept lyrics out of homepage cards and archive excerpts.

= 1.1.0 =
* Added native The Plague Dr Universe `pdu_track` synchronization.
* Added album, duration, genre, buy/stream URL, local audio, and cover-art fields.
* Added Media Library audio selection and native-player precedence.
* Added hosted Suno fallback player for individual Soundtrack pages.
* Added accessible hosted-player modal fallback for homepage and media track lists.
* Added bidirectional relationships, status synchronization, and duplicate prevention.

= 1.0.0 =
* Initial release with secure Suno URL validation, page destinations, hosted players, metadata lookup, shortcodes, and draft visibility.
