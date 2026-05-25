<?php

/**
 * Db.php
 * ============================================================
 * 役割:
 * - PDO接続を1箇所に集約（Singleton）
 *
 * 設計意図:
 * - 全コードが Db::pdo() を使うことで、接続設定の散乱を防ぐ
 * - ATTR_EMULATE_PREPARES=false でネイティブprepare（安全寄り）
 * - STRINGIFY_FETCHES=false で型の揺れを減らす
 *
 * 注意:
 * - トランザクション管理は呼び出し側責務
 */

class Db
{
    private static ?PDO $pdo = null;

    /**
     * PDOを取得（初回のみ生成）
     */
    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            $cfg = require __DIR__ . '/config.php';

            // 将来拡張：port があれば DSN に含める（未設定なら従来通り）
            $host = (string)($cfg['db']['host'] ?? '127.0.0.1');
            $name = (string)($cfg['db']['name'] ?? 'nsystem');
            $charset = (string)($cfg['db']['charset'] ?? 'utf8mb4');
            $port = isset($cfg['db']['port']) ? (string)$cfg['db']['port'] : '';

            $dsn = $port !== ''
                ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset)
                : sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

            try {
                self::$pdo = new PDO($dsn, (string)$cfg['db']['user'], (string)$cfg['db']['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 例外で統一
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 連想配列
                    PDO::ATTR_EMULATE_PREPARES   => false,                  // ネイティブprepare
                    PDO::ATTR_STRINGIFY_FETCHES  => false,                  // 数値が文字列化される揺れを減らす
                ]);
            } catch (Throwable $e) {
                // DB接続失敗は原因調査が多いので、ログに落とす余地を残す
                // error_log('[DB CONNECT ERROR] ' . $e->getMessage());
                throw $e; // 上位（Response::handleException）に委譲
            }
        }

        return self::$pdo;
    }
}
