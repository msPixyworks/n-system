<?php

/**
 * Response.php
 * ============================================================
 * 役割:
 * - 出口（view/json/redirect）を一元化し、JSON汚染を防ぐ
 * - API/画面のエラー返しを統一する
 *
 * 設計意図:
 * - json()/redirect()/fail() の前に出力バッファを必ず全消去する
 * - isApi() で API判定し、fail() で統一返却する
 *
 * 安全側（追加）:
 * - isApi() の判定は「Accept: application/json」を強く見るが、
 *   ブラウザ通常遷移（text/html を含む）で誤判定しにくくする
 * - json_encode 失敗時も確実に JSON を返す
 * - redirect は絶対URL/ヘッダインジェクション事故を避けるため CRLF を除去
 * - 可能なら nosniff を付与（ブラウザのMIME推測抑止）
 */

class Response
{
    /**
     * 画面表示（layout.php 経由）
     * $tpl は拡張子なしのパス（例: 'users/index' -> views/users/index.php）
     */
    public static function view(string $tpl, array $data = []): void
    {
        extract($data);

        // layout.php が require する変数名として $tpl を渡す
        $viewPath = $tpl;
        $tpl = $viewPath;

        require __DIR__ . '/../views/layout.php';
        // viewは exit しない（画面の組み立ては呼び出し側に任せる）
    }

    /**
     * JSON返却（API用）
     * - JSON汚染防止のため、出力バッファを全消去してから返す
     */
    public static function json($data, int $status = 200): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            http_response_code(500);
            $fallback = [
                'error'   => 'JSON_ENCODE_FAILED',
                'message' => 'Failed to encode JSON response.',
            ];
            $json = json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                // 最終手段（これでも文字列は出す）
                $json = '{"error":"JSON_ENCODE_FAILED","message":"Failed to encode JSON response."}';
            }
        }

        echo $json;
        exit;
    }

    /**
     * リダイレクト（画面用）
     *
     * 改修ポイント:
     * - 何らかの出力が先に走って headers_sent() になっていると Location が送れない
     * - その場合は最終手段としてHTMLリンクを出す（デバッグ/事故防止）
     *
     * 安全側:
     * - ヘッダインジェクション対策として CR/LF を除去
     */
    public static function redirect(string $path): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }

        // Header Injection 対策（念のため）
        $path = str_replace(["\r", "\n"], '', $path);

        if (!headers_sent()) {
            header('Location: ' . $path);
            exit;
        }

        // headers_sent() の場合（最終手段）
        http_response_code(302);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        $safe = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        echo "<!doctype html><meta charset='utf-8'><title>Redirect</title>";
        echo "<p>Redirect: <a href=\"{$safe}\">{$safe}</a></p>";
        exit;
    }

    /**
     * API判定
     * - /api/ で始まる
     * - Accept: application/json（ただし text/html を含む場合は画面優先にしがち）
     * - X-Requested-With: XMLHttpRequest
     *
     * 安全側:
     * - ブラウザ通常遷移の Accept は広い（application/json を含むケースもある）ため、
     *   text/html が含まれる場合は「画面」とみなす（/api は例外で常にAPI）
     */
    public static function isApi(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (str_starts_with($path, '/api/')) return true;

        $xhr = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        if (strcasecmp($xhr, 'XMLHttpRequest') === 0) return true;

        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $acceptLower = strtolower($accept);

        // text/html を明示的に欲しがっているなら画面扱い
        if (strpos($acceptLower, 'text/html') !== false) return false;

        if (strpos($acceptLower, 'application/json') !== false) return true;

        return false;
    }

    /**
     * エラー返却の統一口
     * - APIなら JSON
     * - 画面なら簡易HTML（layoutを呼ばない：二次エラー回避）
     */
    public static function fail(string $code, string $message, int $status, array $extra = []): void
    {
        if (self::isApi()) {
            self::json(array_merge(['error' => $code, 'message' => $message], $extra), $status);
        }

        while (ob_get_level() > 0) { @ob_end_clean(); }

        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        // 最低限の安全なエラーページ（ここで例外が起きるのを避ける）
        $codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $msgEsc  = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        echo "<!doctype html><meta charset='utf-8'><title>Error</title>";
        echo "<h1>{$codeEsc}</h1>";
        echo "<pre style=\"white-space:pre-wrap;word-break:break-word;\">{$msgEsc}</pre>";
        exit;
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::fail('FORBIDDEN', $message, 403);
    }

    public static function notFound(string $message = 'Not Found'): void
    {
        self::fail('NOT_FOUND', $message, 404);
    }

    public static function csrfMismatch(): void
    {
        self::fail('CSRF_MISMATCH', 'CSRF token mismatch', 419);
    }

    /**
     * 例外の統一ハンドラ（bootstrap.phpから呼ばれる）
     */
    public static function handleException(Throwable $e, bool $debug = false): void
    {
        // 必要ならここで error_log() や監査等を追加可能
        // error_log((string)$e);

        $message = $debug ? ($e::class . ': ' . $e->getMessage()) : 'Unexpected server error.';
        $extra = $debug ? ['file' => $e->getFile(), 'line' => $e->getLine()] : [];

        self::fail('SERVER_ERROR', $message, 500, $extra);
    }
}
