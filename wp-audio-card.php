<?php
/**
 * Plugin Name: WP Audio Card
 * Description: シンプルなカスタム音声プレイヤー（タイトル・ジャンル・シーク・5段階ボリューム）。
 * Version:     1.1.0
 * Author:      TsugumiTamura
 * License:     MIT
 * Text Domain: wp-audio-card
 */

if (!defined('ABSPATH')) exit;

final class WP_Audio_Card {
  const VER = '1.1.0';
  const HANDLE_JS  = 'wp-audio-card-js';
  const HANDLE_CSS = 'wp-audio-card-css';
  // 追加: REST ルートとトークン有効期限
  const ROUTE_NS   = 'wp-audio-card/v1';
  const TOKEN_TTL  = 600; // 10分（秒）

  public function __construct() {
    add_action('init', [$this,'register_assets']);
    add_shortcode('audio_card', [$this,'shortcode']);
    // 追加: REST エンドポイント登録
    add_action('rest_api_init', [$this,'register_rest']);
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
      // 追加: プロキシ有効化フラグ（'1'|'0' / true|false / yes|no / on|off）
      'secure'  => '1',
    ], $atts, 'audio_card');

    if (empty($a['src'])) return '';

    // secure=オンならトークン付きのプロキシURLを生成（失敗時は元URLへフォールバック）
    $audio_src = $a['src'];
    if ($this->boolish($a['secure'])) {
      $stream_url = $this->create_stream_url($a['src']);
      if (!empty($stream_url)) {
        $audio_src = $stream_url;
      }
    }

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
        <source src="<?php echo esc_url($audio_src); ?>" type="audio/mpeg">
      </audio>
    </div>
    <?php
    return ob_get_clean();
  }

  // ===== ここから追加: RESTでのストリーミング & ユーティリティ =====

  public function register_rest() {
    register_rest_route(self::ROUTE_NS, '/stream/(?P<token>[A-Za-z0-9]+)', [
      'methods'  => 'GET',
      'callback' => [$this, 'rest_stream'],
      'permission_callback' => '__return_true',
    ]);
  }

  private function boolish($v) {
    if (is_bool($v)) return $v;
    $v = strtolower((string)$v);
    return in_array($v, ['1','true','yes','on'], true);
  }

  private function create_stream_url($url) {
    $path = $this->url_to_local_uploads_path($url);
    if (!$path || !is_readable($path)) return '';

    // トークン生成のフォールバック
    try {
      if (function_exists('random_bytes')) {
        $token = bin2hex(random_bytes(16));
      } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
      } else {
        $token = substr(hash('sha256', microtime(true) . wp_generate_password(64, true)), 0, 32);
      }
    } catch (\Throwable $e) {
      $token = substr(hash('sha256', microtime(true) . wp_generate_password(64, true)), 0, 32);
    }

    $data = [
      'path' => $path,
      'ip'   => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
      'ua'   => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'],0,255) : '',
      'ct'   => time(),
    ];
    set_transient('wac_token_' . $token, $data, self::TOKEN_TTL);

    return rest_url(self::ROUTE_NS . '/stream/' . $token);
  }

  private function url_to_local_uploads_path($url) {
    // 同一サイトの uploads 配下のみ許可（ホスト/ポート差異は無視してパスで判定）
    $uploads = wp_get_upload_dir();
    if (empty($uploads['baseurl']) || empty($uploads['basedir'])) return '';

    $url_norm = esc_url_raw($url);

    // パス抽出（URLエンコードも考慮）
    $uploads_parts = wp_parse_url($uploads['baseurl']);
    $uploads_path  = isset($uploads_parts['path']) ? rtrim($uploads_parts['path'], '/') : '';
    $given_parts   = wp_parse_url($url_norm);
    $given_path    = isset($given_parts['path']) ? rawurldecode($given_parts['path']) : '';

    if ($uploads_path === '' || $given_path === '') return '';

    // 指定URLのパス内に uploads のパスが含まれているか
    $pos = strpos($given_path, $uploads_path);
    if ($pos === false) {
      // 外部URLや uploads 以外は拒否
      return '';
    }

    // uploads_path 以降を相対パスとして取得
    $rel  = substr($given_path, $pos + strlen($uploads_path));
    $path = wp_normalize_path($uploads['basedir'] . $rel);

    // ディレクトリトラバーサル対策
    $realBase = realpath($uploads['basedir']);
    $realPath = realpath($path);
    if (!$realBase || !$realPath || strpos($realPath, $realBase) !== 0) return '';

    // mp3のみ（必要なら拡張）
    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3','mpeg','mpga'], true)) return '';

    return $realPath;
  }

  public function rest_stream(\WP_REST_Request $req) {
    $token = sanitize_text_field($req['token']);
    if (!$token || !preg_match('/^[A-Za-z0-9]+$/', $token)) {
      return new \WP_Error('wac_bad_token', 'Invalid token', ['status'=>400]);
    }

    $key  = 'wac_token_' . $token;
    $data = get_transient($key);
    if (!$data || empty($data['path']) || !is_readable($data['path'])) {
      return new \WP_Error('wac_expired', 'Token expired or file not found', ['status'=>403]);
    }

    // IP固定（外したい場合はこのチェックを削除）
    $reqIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if ($data['ip'] && $reqIp && $data['ip'] !== $reqIp) {
      return new \WP_Error('wac_ip_mismatch', 'IP mismatch', ['status'=>403]);
    }

    $file = $data['path'];
    $filesize = filesize($file);
    if ($filesize === false) {
      return new \WP_Error('wac_filesize', 'File error', ['status'=>404]);
    }

    while (ob_get_level()) { @ob_end_clean(); }

    $range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
    $start = 0;
    $end   = $filesize - 1;
    $code  = 200;

    if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
      if ($m[1] !== '') $start = (int)$m[1];
      if ($m[2] !== '') $end   = (int)$m[2];
      if ($start > $end || $start >= $filesize) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$filesize");
        exit;
      }
      $code = 206;
    }

    $length = $end - $start + 1;

    if ($code === 206) {
      header('HTTP/1.1 206 Partial Content');
      header("Content-Range: bytes $start-$end/$filesize");
    } else {
      header('HTTP/1.1 200 OK');
    }

    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');

    $fp = @fopen($file, 'rb');
    if (!$fp) {
      header('HTTP/1.1 404 Not Found');
      exit;
    }

    if ($start > 0) fseek($fp, $start);

    set_time_limit(0);
    $chunk = 8192;
    $sent  = 0;

    while (!feof($fp) && $sent < $length) {
      $bufSize = min($chunk, $length - $sent);
      $buffer  = fread($fp, $bufSize);
      if ($buffer === false) break;
      echo $buffer;
      $sent += strlen($buffer);
      flush();
      if (connection_aborted()) break;
    }
    fclose($fp);
    exit;
  }
}

new WP_Audio_Card();
