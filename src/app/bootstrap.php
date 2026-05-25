<?php
declare(strict_types=1);

/**
 * bootstrap.php
 * ============================================================
 * 役割:
 * - 全リクエストの共通初期化（文字コード/セッション/出力バッファ）
 * - K-Core 基本クラス読み込み
 * - 例外/エラー/致命エラーを統一ハンドリングして「画面/JSON」を壊さない
 *
 * 設計意図:
 * - JSON汚染を防ぐため、最初に出力バッファを開始する
 * - エラー/例外は Response::handleException() に集約する
 * - display_errors は env によって切替（本番では画面に出さない）
 */

$cfg = require __DIR__ . '/config.php';
$env = (string)($cfg['env'] ?? 'local');

// 開発環境のみデバッグ表示。運用時は表示しない（ログに任せる）
$debug = in_array($env, ['local', 'dev', 'development'], true);

ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// 文字コードは必ずUTF-8
if (!headers_sent()) {
    ini_set('default_charset', 'UTF-8');
}
mb_internal_encoding('UTF-8');

// JSON汚染防止: 出力はまずバッファへ。API返却時は Response 側で全消去する
if (ob_get_level() === 0) {
    ob_start();
}

// ------------------------------------------------------------
// セッション
// ------------------------------------------------------------
// HTTPS判定（https なら secure cookie）
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_name('KCSCSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// ------------------------------------------------------------
// K-Core 基本クラス読み込み（明示ロード）
// ------------------------------------------------------------
require __DIR__ . '/Db.php';
require __DIR__ . '/Csrf.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Validation.php';
require __DIR__ . '/Policies.php';
require __DIR__ . '/Response.php';
require __DIR__ . '/Audit.php';

// ------------------------------------------------------------
// エラー/例外の統一ハンドリング
// ------------------------------------------------------------

/**
 * PHP Warning/Notice などを例外化して、例外ハンドラに一本化する
 * ※ @ で抑制されているものは無視する
 */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return true;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * 例外は Response に委譲して「APIならJSON」「画面なら簡易HTML」で返す
 */
set_exception_handler(function (Throwable $e) use ($debug): void {
    Response::handleException($e, $debug);
});

/**
 * 致命エラー（E_ERROR / parse error等）捕捉
 * - PHPは致命エラーを例外として投げないので shutdown function で拾う
 */
register_shutdown_function(function () use ($debug): void {
    $err = error_get_last();
    if (!$err) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array((int)$err['type'], $fatalTypes, true)) return;

    $e = new ErrorException(
        (string)($err['message'] ?? 'Fatal error'),
        0,
        (int)$err['type'],
        (string)($err['file'] ?? ''),
        (int)($err['line'] ?? 0)
    );

    Response::handleException($e, $debug);
});
