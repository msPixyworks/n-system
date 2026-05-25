<?php

/**
 * Validation.php
 * ============================================================
 * 役割:
 * - 最小限のバリデーション／正規化ユーティリティ
 *
 * 設計意図:
 * - フレームワーク的に縛りすぎず、各Controllerで必要なルールを積み上げる
 * - errors は field => [messages...] 形式（画面表示に便利）
 */

class Validation
{
    /** @var array<string, string[]> */
    public array $errors = [];

    /**
     * エラー追加
     */
    public function add(string $field, string $msg): void
    {
        $this->errors[$field][] = $msg;
    }

    /**
     * エラーが無いか
     */
    public function ok(): bool
    {
        return empty($this->errors);
    }

    /**
     * メール形式
     */
    public static function email(?string $v): bool
    {
        return (bool)filter_var($v, FILTER_VALIDATE_EMAIL);
    }

    /**
     * カナ正規化（業務用）
     * - 半角カナ → 全角カナ
     * - 濁点/長音の揺れを正規化
     * - 英数の全角化はしない（DB/検索の揺れ防止）
     */
    public static function kana(string $v): string
    {
        return mb_convert_kana($v, 'KV', 'UTF-8');
    }
}
