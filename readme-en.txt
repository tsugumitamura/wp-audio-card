=== WP Audio Card ===
Contributors: yourname
Tags: audio, player, html5, shortcode
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

A lightweight custom audio player for WordPress. Displays genre & title, seek bar, elapsed/total time, and a 5-step volume control.

== Description ==
WP Audio Card is a lightweight, customizable audio player plugin for WordPress.

**Features**
- Display genre and track title
- Play / Pause button
- Seek bar (draggable)
- Elapsed / Total time display
- 5-step volume control + mute button
- Supports multiple players on the same page
- Accessible with ARIA support
- Fully customizable CSS/JS

== Installation ==
1. Download the ZIP of this repository or upload it directly to `wp-content/plugins/wp-audio-card/`.
2. Activate **WP Audio Card** from the WordPress admin panel.

== Usage ==
Insert via shortcode:  
`[audio_card src="https://example.com/sample.mp3" title="Sample Audio" genre="High Energy Variety"]`

Call directly in a PHP template:  
`<?php echo do_shortcode('[audio_card src="https://example.com/sample.mp3" title="Sample Audio" genre="High Energy Variety"]'); ?>`

== Shortcode Attributes ==
* `src` (required) - URL of the MP3 file
* `title` (optional) - Track title (default: Untitled)
* `genre` (optional) - Genre name (default: empty)
* `preload` (optional) - `none` / `metadata` / `auto` (default: metadata)

== Frequently Asked Questions ==

= Can I add multiple audio players to the same page? =
Yes. Just call the shortcode multiple times.

= Why does the total duration sometimes not show in Safari? =
Safari may delay fetching total duration, but this plugin includes fallbacks via event monitoring, short polling, and a dummy seek to ensure it appears.

== Screenshots ==
1. Example player showing genre, title, seek bar, and volume controls

== Changelog ==
= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.0.0 =
Initial release
