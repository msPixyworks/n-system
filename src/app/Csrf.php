<?php

/**
 * Csrf.php
 * ============================================================
 * 役割:
 * - CSRFトークン生成・検証
 *
 * 設計意図:
 * - トークンはセッションに1つ保持
 * - POST hidden(_token) と、Ajax用ヘッダ(X-CSRF-Token)の両方に対応
 * - 失敗時は Response に委譲して「API/画面」の返しを統一
 *
 * 任意運用:
 * - ログイン成功時などに token を再発行したい場合は rotate() を呼ぶ
 *   （例: Auth::attempt 成功直後）
 */

class Csrf
{
    /**
     * トークン生成（未生成なら作る）
     */
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            // 32 bytes = 256bit
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    /**
     * トークン再発行（任意）
     * - ログイン成功時などに呼ぶと、古いフォームがCSRFミスマッチになる代わりに
     *   セッション固定・再利用リスクを下げられる
     * - 使う/使わないは運用方針次第
     */
    public static function rotate(): string
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return (string)$_SESSION['csrf'];
    }

    /**
     * リクエストからCSRFトークンを取得
     * - hidden: _token
     * - header: X-CSRF-Token
     */
    public static function readFromRequest(?string $postToken = null): ?string
    {
        if ($postToken) return $postToken;

        $hdr = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($hdr !== '') return $hdr;

        return null;
    }

    /**
     * CSRF検証（失敗時は 419 で終了）
     */
    public static function check(?string $token): void
    {
        $token = self::readFromRequest($token);

        if (!$token || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
            // API/画面で返しを揃える
            Response::csrfMismatch();
        }
    }
}
