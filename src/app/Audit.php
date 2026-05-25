<?php

/**
 * Audit.php
 * ============================================================
 * 役割:
 * - 監査ログ（ヘッダ + 差分）を記録する
 *
 * 設計意図:
 * - 監査の書き込みはトランザクションで原子化（ヘッダと詳細を整合）
 * - 監査失敗で本処理を止めない（運用方針による）
 *
 * 注意:
 * - 子/孫のツリー監査（V2）を将来的に入れるなら、ここに logTree を追加するのが自然
 *
 * 追加（運用要件）:
 * - 「変更がない update」や「作成(create)の詳細なしログ」を保存したくない場合がある。
 * - その場合、Audit.php 側で“保存しない判断”を行うと、各Controllerに漏れなく適用できる。
 * - スキップ条件は config.php の audit_policy で調整できるようにする。
 *
 * 追加（login_failed 想定）:
 * - ログイン失敗は「対象ユーザーが不明」の場合があるため、
 *   entity_id=0 / actor_user_id=NULL で記録する運用を許容する。
 * - 監査詳細（audit_log_details）には email（マスク）や reason を入れる。
 */

class Audit
{
    /**
     * 監査ポリシー取得（config.php）
     */
    private static function policy(): array
    {
        $cfg = require __DIR__ . '/config.php';
        $p = $cfg['audit_policy'] ?? [];
        return is_array($p) ? $p : [];
    }

    /**
     * 「保存しない」判定
     */
    private static function shouldSkip(string $action, array $diff): bool
    {
        $p = self::policy();

        $skipCreate = (bool)($p['skip_create_without_details'] ?? false);
        $skipUpdate = (bool)($p['skip_update_without_details'] ?? false);

        if ($skipCreate && $action === 'create' && empty($diff)) return true;
        if ($skipUpdate && $action === 'update' && empty($diff)) return true;

        return false;
    }

    /**
     * クライアントIP取得（安全側の最小実装）
     *
     * 今は REMOTE_ADDR を優先。
     * リバプロ配下で X-Forwarded-For を使う場合は、
     * config.php に trusted_proxies 等を入れてここで拡張する想定。
     */
    private static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ip = is_string($ip) ? trim($ip) : null;
        return ($ip !== '') ? $ip : null;
    }

    /**
     * 監査ログ記録（V1: 単一エンティティ + 差分）
     *
     * @param string   $module  モジュールキー（例: users）
     * @param string   $etype   表示用エンティティタイプ（例: User）
     * @param int      $eid     対象ID（login_failed等で不明なら 0 を許容）
     * @param string   $action  create/update/delete/login/logout/login_failed など
     * @param ?int     $actorId 実行者（users.id / 不明ならnull）
     * @param array    $diff    ['field'=>['old'=>..,'new'=>..], ...]
     */
    public static function log(string $module, string $etype, int $eid, string $action, ?int $actorId, array $diff = []): void
    {
        if (self::shouldSkip($action, $diff)) {
            return;
        }

        $pdo = Db::pdo();
        $startedHere = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedHere = true;
            }

            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ua = substr($ua, 0, 255);

            // ヘッダ
            $st = $pdo->prepare("
                INSERT INTO audit_logs (module, entity_type, entity_id, action, actor_user_id, ip, user_agent)
                VALUES (:m, :t, :i, :a, :u, :ip, :ua)
            ");
            $st->execute([
                ':m'  => $module,
                ':t'  => $etype,
                ':i'  => $eid,
                ':a'  => $action,
                ':u'  => $actorId,
                ':ip' => self::clientIp(),
                ':ua' => $ua,
            ]);

            $auditId = (int)$pdo->lastInsertId();

            // 差分
            if (!empty($diff)) {
                $st2 = $pdo->prepare("
                    INSERT INTO audit_log_details (audit_log_id, field_name, old_value, new_value)
                    VALUES (:aid, :f, :o, :n)
                ");

                foreach ($diff as $field => $pair) {
                    $st2->execute([
                        ':aid' => $auditId,
                        ':f'   => (string)$field,
                        ':o'   => $pair['old'] ?? null,
                        ':n'   => $pair['new'] ?? null,
                    ]);
                }
            }

            if ($startedHere) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedHere && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 監査失敗でアプリを止めない（運用ポリシーにより変更可）
            // error_log((string)$e);
        }
    }

    /**
     * 差分生成ヘルパ
     */
    public static function diff(array $old, array $new, array $fields): array
    {
        $out = [];

        foreach ($fields as $f) {
            $ov = $old[$f] ?? null;
            $nv = $new[$f] ?? null;

            if ($ov !== $nv) {
                $out[$f] = ['old' => $ov, 'new' => $nv];
            }
        }

        return $out;
    }
}
