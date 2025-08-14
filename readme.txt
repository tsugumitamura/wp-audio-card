=== WP Audio Card ===
Contributors: TsugumiTamura
Tags: audio, player, html5, shortcode
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

WordPress用の軽量カスタム音声プレイヤー。ジャンル・タイトル表示、シークバー、経過/総時間、5段階ボリュームコントロール搭載。

== Description ==
WP Audio Card は、WordPress サイトに軽量でカスタマイズ可能な音声プレイヤーを追加するプラグインです。

**主な機能**
- ジャンルとタイトル表示
- 再生 / 一時停止ボタン
- シークバー（ドラッグ可能）
- 経過時間 / 総時間表示
- 5段階ボリューム + ミュートボタン
- 複数プレイヤー設置対応
- ARIA 対応でアクセシビリティ向上
- CSS/JSの完全カスタマイズ可能

== Installation ==
1. このリポジトリを ZIP ダウンロードするか、`wp-content/plugins/wp-audio-card/` に直接アップロードします。
2. WordPress 管理画面の「プラグイン」ページから **WP Audio Card** を有効化します。

== Usage ==
ショートコードで挿入:
`[audio_card src="https://example.com/sample.mp3" title="番組CMナレーション" genre="ハイテンション・バラエティ"]`

テーマの PHP テンプレート内で呼び出し:
`<?php echo do_shortcode('[audio_card src="https://example.com/sample.mp3" title="番組CMナレーション" genre="ハイテンション・バラエティ"]'); ?>`

== Shortcode Attributes ==
* `src` (必須) - MP3ファイルのURL
* `title` (任意) - トラックタイトル（デフォルト: Untitled）
* `genre` (任意) - ジャンル名（デフォルト: 空）
* `preload` (任意) - `none` / `metadata` / `auto` （デフォルト: metadata）

== Frequently Asked Questions ==

= 複数の音声プレイヤーを同じページに設置できますか？ =
はい。ショートコードを複数回呼び出すことで、複数設置可能です。

= 音声の総時間がSafariで表示されない場合があります =
Safariは総時間の取得が遅れる場合がありますが、本プラグインではイベント監視・ポーリング・ダミーシークによる対策を実装しています。

== Screenshots ==
1. プレイヤー表示例（ジャンル・タイトル・シークバー・ボリュームコントロール）

== Changelog ==
= 1.0.0 =
* 初回リリース

== Upgrade Notice ==
= 1.0.0 =
初回リリース
