<?php
/**
 * Plugin Name: WP Audio Card
 * Description: シンプルなカスタム音声プレイヤー（タイトル・ジャンル・シーク・5段階ボリューム）。
 * Version:     1.0.0
 * Author:      TsugumiTamura
 * License:     MIT
 * Text Domain: wp-audio-card
 */

if (!defined('ABSPATH')) exit;

final class WP_Audio_Card {
  const VER = '1.0.0';
  const HANDLE_JS  = 'wp-audio-card-js';
  const HANDLE_CSS = 'wp-audio-card-css';

  public function __construct() {
    add_action('init', [$this,'register_assets']);
    add_shortcode('audio_card', [$this,'shortcode']);
  }

  public function register_assets() {
    $base = plugin_dir_url(__FILE__);
    wp_register_script(
      self::HANDLE_JS,
      $base . 'assets/js/custom-audio-player.js',
      [],
      self::VER,
      true
    );
    wp_register_style(
      self::HANDLE_CSS,
      $base . 'assets/css/custom-audio-player.css',
      [],
      self::VER
    );
  }

  public function shortcode($atts = []) {
    $a = shortcode_atts([
      'src'     => '',
      'title'   => 'Untitled',
      'genre'   => '',
      'preload' => 'metadata', // none|metadata|auto
    ], $atts, 'audio_card');

    if (empty($a['src'])) return '';

    // 複数配置OK
    $id = 'ap-' . wp_generate_uuid4();

    // アセット読み込み（このショートコードが使われた時のみ）
    wp_enqueue_script(self::HANDLE_JS);
    wp_enqueue_style(self::HANDLE_CSS);

    ob_start(); ?>
    <div class="cap card js-audio-player" id="<?php echo esc_attr($id); ?>" role="application" aria-label="Audio player">
      <div class="cap__meta">
        <?php if ($a['genre']): ?>
          <span class="cap__genre"><?php echo esc_html($a['genre']); ?></span>
          <span class="cap__sep"> - </span>
        <?php endif; ?>
        <span class="cap__title"><?php echo esc_html($a['title']); ?></span>
      </div>

      <div class="cap__controls">
        <button class="cap__play" aria-label="Play" aria-pressed="false" type="button"></button>

        <div class="cap__progress" role="group" aria-label="Seek">
          <input class="cap__seek" type="range" min="0" max="100" step="0.1" value="0" aria-label="Playback position">
          <div class="cap__time">
            <span class="cap__current">00:00</span><span class="cap__slash"> / </span><span class="cap__duration">00:00</span>
          </div>
        </div>

        <div class="cap__volume" role="group" aria-label="Volume">
          <button class="cap__mute" aria-label="Mute" aria-pressed="false" type="button"></button>
          <ul class="cap__volbars" tabindex="0" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="80" aria-valuetext="80% volume">
            <?php for($i=0;$i<5;$i++): ?>
              <li class="cap__volbar" data-index="<?php echo esc_attr($i); ?>"></li>
            <?php endfor; ?>
          </ul>
        </div>
      </div>

      <audio class="cap__audio" preload="<?php echo esc_attr($a['preload']); ?>">
        <source src="<?php echo esc_url($a['src']); ?>" type="audio/mpeg">
      </audio>
    </div>
    <?php
    return ob_get_clean();
  }
}

new WP_Audio_Card();
