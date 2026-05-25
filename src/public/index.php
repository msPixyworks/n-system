<?php
/**
 * public/index.php
 * ============================================================
 * 役割:
 * - Apache の DocumentRoot（/public）配下でのエントリポイント
 * - .htaccess（mod_rewrite）で飛んできたリクエストを受け、
 *   K-Core の router に処理を委譲する
 *
 * 想定ディレクトリ構成:
 * - /var/www/html/public  -> DocumentRoot
 * - /var/www/html/app     -> K-Core本体（router.php / bootstrap.php 等）
 *
 * 注意:
 * - パスを変えた場合は require の相対パスを合わせる
 * - ここで出力を行うと JSON 汚染の原因になるので、基本は何もしない
 */

// router.php を読み込んで処理開始
$router = __DIR__ . '/../app/router.php';

// 念のため存在確認（本番では不要だが移植時の詰まり防止）
if (!is_file($router)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Router not found: {$router}";
    exit;
}

require $router;
