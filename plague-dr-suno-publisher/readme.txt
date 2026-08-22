=== Plague Dr Suno Publisher ===
Contributors: plaguedr
Tags: suno, music, audio, embed, pages
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Place your public Suno songs on any WordPress page or post and change the destination whenever you want.

== Description ==

Plague Dr Suno Publisher gives Plague Dr Music an easy, reversible workflow:

1. Paste your public Suno song URL.
2. Optionally edit the title, description or lyrics, and artwork.
3. Choose any editable WordPress page or post.
4. Place the player before or after that destination's content.
5. Move it later by selecting another destination.

The plugin does not permanently inject markup into the destination's saved content. Active song placements are added when WordPress renders the selected destination, so moving a song does not leave stale players behind.

Every song also receives a shortcode:

`[plague_dr_song id="123"]`

Audio stays hosted by Suno. The plugin does not download or rehost music.

== Installation ==

1. Upload `plague-dr-suno-publisher.zip` through Plugins -> Add New Plugin -> Upload Plugin.
2. Activate Plague Dr Suno Publisher.
3. Open Plague Dr Music -> Add from Suno.
4. Paste a public full Suno song URL, choose a destination, and save it as Draft or Active.

== Frequently Asked Questions ==

= Can I change the destination? =

Yes. Open Plague Dr Music -> All Songs, edit the song, and select another page or post in Suno Placement. Update the song and its automatic player moves.

= Can I put one song in a precise location? =

Yes. Choose No automatic destination and place its shortcode in any WordPress Shortcode block. You can also use the shortcode in addition to an automatic destination.

= Does activating a song publish or change the destination page? =

It does not change the page's saved content or status. An Active song appears when its selected destination is rendered. A Draft song remains hidden from automatic destinations.

= Does the plugin download my song? =

No. It embeds Suno's hosted `/embed/` player and links back to the public Suno song.

= Why did a Suno short share link fail? =

Suno or its anti-bot layer may prevent server-side short-link resolution. Open the short link in your browser and paste the resulting full `https://suno.com/song/…` URL.

= Which URL formats are supported? =

Full `suno.com/song/{song-id}` and `suno.com/embed/{song-id}` links are supported. Legacy `suno.ai` equivalents are accepted. The plugin attempts to resolve `suno.com/s/…` links.

== Privacy ==

A metadata request is sent to Suno only when a song is added and its public title, description, or artwork is needed. Visitors who view an embedded player connect to Suno under Suno's privacy terms. No Suno account credential or API key is collected.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added secure Suno URL validation and hosted player rendering.
* Added changeable page/post destinations with before/after placement.
* Added Draft and Active visibility.
* Added public metadata lookup with manual fallback.
* Added shortcodes and duplicate-song prevention.
