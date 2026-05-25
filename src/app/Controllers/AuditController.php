<?php

/**
 * AuditController.php
 * ============================================================
 * 役割:
 * - 監査ログ画面（index）と DataTables API（datatable）
 * - 差分詳細 API（details）
 *
 * 改修ポイント:
 * - 権限判定を Policies に集約（ズレ防止）
 * - 「差分が無いログ」「create を出さない」等の運用要件に対応（オプションフィルタ）
 *
 * オプション（GETパラメータ）:
 * - only_changed=1   : audit_log_details が存在するログのみ表示
 * - exclude_create=1 : action=create を一覧から除外
 *
 * 追加（login_failed/login_blocked 対応）:
 * - actor_user_id NULL の行は actor_email を '-' 表示
 * - entity_id=0 の行はそのまま表示（details の email_masked/reason 等で確認）
 *
 * 追加（要望反映）:
 * - 一覧表示から「UA（user_agent）」「種別（entity_type）」を除外
 *
 * 追加（要望反映）:
 * - IPでの絞り込みを追加（クリック→同IP絞り込み用）
 *   - GET: ip=xxx を受け取り、al.ip = :ip でフィルタ
 *
 * 追加（JS装飾用）:
 * - action_key（生action）を返す
 *   - action_label（表示名）と両方返すことで、JSが login_blocked を安全に判定できる
 *
 * DataTables 一覧表示列（順番）:
 *  0: ID
 *  1: モジュール
 *  2: エンティティID
 *  3: アクション（HTML: バッジ含む想定、JSで装飾）
 *  4: ユーザーID（実行者）
 *  5: IP（クリックで絞り込み想定）
 *  6: 日時
 *  7: 詳細（差分ボタン）
 */

class AuditController
{
    /**
     * 監査画面の共通ガード
     */
    private function guard(): array
    {
        $u = Auth::user();
        if (!$u) {
            Response::redirect('/');
            return [];
        }

        Policies::guardView($u, 'audit');
        return $u;
    }

    /**
     * 監査ログ画面
     */
    public function index(): void
    {
        $me = $this->guard();
        if (!$me) return;

        Response::view('audit/index', ['title' => '監査ログ', 'me' => $me]);
    }

    /**
     * DataTables API
     */
    public function datatable(): void
    {
        $me = $this->guard();
        if (!$me) return;

        try {
            $pdo = Db::pdo();
            $cfg = require __DIR__ . '/../config.php';

            // ------------------------------------------------------------
            // DataTables 基本
            // ------------------------------------------------------------
            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));
            $len   = isset($_GET['length']) ? (int)$_GET['length'] : 10;
            $len   = max(1, min(500, $len));

            // ------------------------------------------------------------
            // フィルタ
            // ------------------------------------------------------------
            $module    = trim((string)($_GET['module'] ?? ''));
            $action    = trim((string)($_GET['action'] ?? ''));
            $dateFrom  = trim((string)($_GET['date_from'] ?? ''));
            $dateTo    = trim((string)($_GET['date_to'] ?? ''));
            $newValueQ = trim((string)($_GET['f_new_value'] ?? ''));

            // 追加: IPフィルタ（クリック→絞り込み用）
            $ipFilter  = trim((string)($_GET['ip'] ?? ''));

            // 表示オプション
            $onlyChanged   = (int)($_GET['only_changed'] ?? 0) === 1;
            $excludeCreate = (int)($_GET['exclude_create'] ?? 0) === 1;

            $where  = [];
            $params = [];

            if ($module !== '')   { $where[] = "al.module = :m";       $params[':m'] = $module; }
            if ($action !== '')   { $where[] = "al.action = :a";       $params[':a'] = $action; }
            if ($dateFrom !== '') { $where[] = "al.created_at >= :df"; $params[':df'] = $dateFrom.' 00:00:00'; }
            if ($dateTo   !== '') { $where[] = "al.created_at <= :dt"; $params[':dt'] = $dateTo.' 23:59:59'; }

            if ($ipFilter !== '') {
                $where[] = "al.ip = :ip";
                $params[':ip'] = $ipFilter;
            }

            if ($excludeCreate) {
                $where[] = "al.action <> 'create'";
            }

            if ($onlyChanged) {
                $where[] = "EXISTS (SELECT 1 FROM audit_log_details d0 WHERE d0.audit_log_id = al.id)";
            }

            // ------------------------------------------------------------
            // 新値（差分）での絞り込み（ラベル→コード逆引き対応）
            // ------------------------------------------------------------
            if ($newValueQ !== '') {
                $nvOrs = [];
                $nvParams = [];

                // 1) 部分一致（そのまま）
                $nvOrs[] = "(d.new_value LIKE :nv_like)";
                $nvParams[':nv_like'] = '%' . $newValueQ . '%';

                // 2) ラベル→コード逆引き
                $valueMapsByModule = $cfg['audit_value_labels'] ?? [];
                $modulesToScan = [];

                if ($module !== '' && isset($valueMapsByModule[$module])) {
                    $modulesToScan[$module] = $valueMapsByModule[$module];
                } else {
                    $modulesToScan = $valueMapsByModule;
                }

                $idxField = 0;
                foreach ($modulesToScan as $modKey => $fieldMaps) {
                    if (!is_array($fieldMaps)) continue;

                    foreach ($fieldMaps as $fieldName => $codeMap) {
                        if (!is_array($codeMap)) continue;

                        $codes = [];
                        foreach ($codeMap as $code => $label) {
                            $labelStr = (string)$label;
                            if ($labelStr === $newValueQ || mb_strpos($labelStr, $newValueQ) !== false) {
                                $codes[] = (string)$code;
                            }
                        }

                        if (!empty($codes)) {
                            $placeField = ":f" . $idxField;
                            $placeCodes = [];

                            foreach ($codes as $j => $codeVal) {
                                $ph = ":v{$idxField}_{$j}";
                                $placeCodes[] = $ph;
                                $nvParams[$ph] = $codeVal;
                            }

                            $nvOrs[] = "(d.field_name = {$placeField} AND d.new_value IN (" . implode(',', $placeCodes) . "))";
                            $nvParams[$placeField] = $fieldName;

                            $idxField++;
                        }
                    }
                }

                $where[] = "EXISTS (
                    SELECT 1
                    FROM audit_log_details d
                    WHERE d.audit_log_id = al.id
                      AND (" . implode(' OR ', $nvOrs) . ")
                )";

                foreach ($nvParams as $k => $v) {
                    $params[$k] = $v;
                }
            }

            $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

            // ------------------------------------------------------------
            // 並び替え（DataTables列→SQLカラムのホワイトリスト）
            // ------------------------------------------------------------
            $colMap = [
                0 => 'al.id',
                1 => 'al.module',
                2 => 'al.entity_id',
                3 => 'al.action',     // action_key
                4 => 'u.email',       // actor_email
                5 => 'al.ip',
                6 => 'al.created_at',
            ];

            $orderByParts = [];
            if (isset($_GET['order']) && is_array($_GET['order'])) {
                $count = 0;
                foreach ($_GET['order'] as $ord) {
                    if ($count >= 3) break;

                    $colIdx = isset($ord['column']) ? (int)$ord['column'] : null;
                    $dir    = strtolower((string)($ord['dir'] ?? ''));

                    if (!isset($colMap[$colIdx])) continue;
                    $dir = ($dir === 'desc') ? 'DESC' : 'ASC';

                    $orderByParts[] = $colMap[$colIdx] . ' ' . $dir;
                    $count++;
                }
            }
            if (!$orderByParts) $orderByParts[] = 'al.id DESC';
            $orderBySql = ' ORDER BY ' . implode(', ', $orderByParts);

            // ------------------------------------------------------------
            // 件数
            // ------------------------------------------------------------
            $total = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

            $stc = $pdo->prepare("
                SELECT COUNT(*)
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.actor_user_id
                {$wsql}
            ");
            foreach ($params as $k => $v) { $stc->bindValue($k, $v); }
            $stc->execute();
            $filtered = (int)$stc->fetchColumn();

            // ------------------------------------------------------------
            // データ（JOINで実行者メールを取得）
            // ------------------------------------------------------------
            $sql = "
                SELECT
                    al.id,
                    al.module,
                    al.entity_id,
                    al.action AS action_key,
                    al.ip,
                    al.created_at,
                    u.email AS actor_email
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.actor_user_id
                {$wsql}{$orderBySql}
                LIMIT :start, :len
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) { $st->bindValue($k, $v); }
            $st->bindValue(':start', $start, PDO::PARAM_INT);
            $st->bindValue(':len', $len, PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            // 表示名に変換（config辞書）
            $actionLabel = $cfg['audit_actions'] ?? [];
            $moduleLabel = $cfg['modules'] ?? [];

            $data = array_map(function ($r) use ($actionLabel, $moduleLabel) {
                $modDisp = $moduleLabel[$r['module']] ?? $r['module'];

                $actionKey = (string)($r['action_key'] ?? '');
                $actDisp = $actionLabel[$actionKey] ?? $actionKey;

                $actorEmail = (string)($r['actor_email'] ?? '');
                $actorDisp  = ($actorEmail !== '') ? $actorEmail : '-';

                // JS側で装飾できるよう action_key を data-action に埋めて返す
                // （表示テキストはラベル）
                $actionHtml = '<span class="audit-action" data-action="' . htmlspecialchars($actionKey, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars((string)$actDisp, ENT_QUOTES, 'UTF-8')
                            . '</span>';

                // JS側でクリック可能にするため、IPも data-ip で包む（見た目は通常テキスト）
                $ip = (string)($r['ip'] ?? '');
                $ipHtml = '<span class="audit-ip" data-ip="' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8')
                        . '</span>';

                return [
                    (int)$r['id'],
                    htmlspecialchars((string)$modDisp, ENT_QUOTES, 'UTF-8'),
                    (int)$r['entity_id'],
                    $actionHtml,
                    htmlspecialchars($actorDisp, ENT_QUOTES, 'UTF-8'),
                    $ipHtml,
                    htmlspecialchars((string)($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-diff" data-id="' . ((int)$r['id']) . '">差分</button>',
                ];
            }, $rows);

            Response::json([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $data,
            ]);
        } catch (Throwable $e) {
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => 'Unexpected error in datatable endpoint.',
            ], 500);
        }
    }

    /**
     * 差分API（フィールド名・値を辞書で変換）
     */
    public function details($id): void
    {
        $me = $this->guard();
        if (!$me) return;

        try {
            $pdo = Db::pdo();

            // Controllers 配下（app/Controllers）から ../config.php → app/config.php を指す
            $cfg = require __DIR__ . '/../config.php';

            $aid = (int)$id;

            // ログ情報（モジュール名取得）
            $log = $pdo->prepare("SELECT module FROM audit_logs WHERE id = :id LIMIT 1");
            $log->execute([':id' => $aid]);
            $logRow = $log->fetch();

            if (!$logRow) {
                Response::json(['error' => 'NOT_FOUND', 'message' => 'Audit log not found.'], 404);
                return;
            }

            $module = (string)$logRow['module'];

            // 差分取得
            $st = $pdo->prepare("
                SELECT field_name, old_value, new_value
                FROM audit_log_details
                WHERE audit_log_id = :id
                ORDER BY id ASC
            ");
            $st->execute([':id' => $aid]);
            $rows = $st->fetchAll() ?: [];

            // 辞書
            $fieldMap  = $cfg['audit_fields'][$module] ?? [];
            $valueMaps = $cfg['audit_value_labels'][$module] ?? [];

            $out = [];
            foreach ($rows as $r) {
                $fieldKey   = (string)$r['field_name'];
                $fieldLabel = $fieldMap[$fieldKey] ?? $fieldKey;

                $oldRaw = $r['old_value'] ?? null;
                $newRaw = $r['new_value'] ?? null;

                $old = ($oldRaw === null) ? '' : (string)$oldRaw;
                $new = ($newRaw === null) ? '' : (string)$newRaw;

                // 値変換（コード→ラベル）
                if (isset($valueMaps[$fieldKey]) && is_array($valueMaps[$fieldKey])) {
                    $map = $valueMaps[$fieldKey];

                    if ($old !== '' && array_key_exists($old, $map)) $old = (string)$map[$old];
                    if ($new !== '' && array_key_exists($new, $map)) $new = (string)$map[$new];
                }

                $out[] = [
                    'field' => (string)$fieldLabel,
                    'old'   => $old,
                    'new'   => $new,
                ];
            }

            Response::json(['data' => $out]);
        } catch (Throwable $e) {
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => 'Failed to load details.',
            ], 500);
        }
    }
}
