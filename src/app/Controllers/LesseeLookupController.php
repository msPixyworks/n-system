<?php

/**
 * LesseeLookupController.php
 * ============================================================
 * リース先（法人/個人）横断検索 API（DataTables + Resolve）
 *
 * 修正（重要）:
 * - PDOで同じ名前のプレースホルダ(:q)を複数回使うと HY093 になる環境がある
 *   → office/personalで別名（:q_office / :q_personal）に分離
 *   → さらに各サブクエリ内は CONCAT_WS(...) LIKE :q_xxx の “1回だけ” に統一
 */

class LesseeLookupController
{
    // ============================================================
    // Guards
    // ============================================================
    private function guardView(): array
    {
        $u = Auth::user();
        if (!$u) {
            Response::fail('UNAUTHORIZED', 'Unauthorized', 401);
        }
        Policies::guardView($u, 'car_leases');
        return $u;
    }

    private function isLocalEnv(): bool
    {
        $cfg = require __DIR__ . '/../config.php';
        return ((string)($cfg['env'] ?? '')) === 'local';
    }

    // ============================================================
    // Resolve API（手入力補助）
    // ============================================================
    public function resolve(): void
    {
        $this->guardView();

        $type = trim((string)($_GET['type'] ?? ''));
        $id   = (int)preg_replace('/[^0-9]/', '', (string)($_GET['id'] ?? ''));

        if (!in_array($type, ['office', 'personal'], true) || $id <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid params.'], 400);
        }

        try {
            $pdo = Db::pdo();

            if ($type === 'office') {
                $st = $pdo->prepare("
                    SELECT id, name, company_name_phonetic AS kana
                    FROM office_customers
                    WHERE id = :i AND deleted_at IS NULL
                    LIMIT 1
                ");
            } else {
                $st = $pdo->prepare("
                    SELECT id, name, letter AS kana
                    FROM personal_customers
                    WHERE id = :i AND deleted_at IS NULL
                    LIMIT 1
                ");
            }

            $st->execute([':i' => $id]);
            $row = $st->fetch();

            if (!$row) {
                Response::json(['ok' => false, 'message' => 'Not found.'], 200);
            }

            Response::json([
                'ok' => true,
                'data' => [
                    'lessee_type' => $type,
                    'lessee_id'   => (int)$row['id'],
                    'name'        => (string)($row['name'] ?? ''),
                    'kana'        => (string)($row['kana'] ?? ''),
                ],
            ]);
        } catch (Throwable $e) {
            $msg = $this->isLocalEnv() ? $e->getMessage() : 'SERVER_ERROR';
            Response::json(['ok' => false, 'message' => $msg], 500);
        }
    }

    // ============================================================
    // DataTables API
    // ============================================================
    public function datatable(): void
    {
        $this->guardView();

        try {
            $pdo = Db::pdo();
            $cfg = require __DIR__ . '/../config.php';

            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));

            $len = isset($_GET['length']) ? (int)($_GET['length']) : 50;
            $len = max(1, min(200, $len));

            $f_type = trim((string)($_GET['f_type'] ?? ''));
            $f_q    = trim((string)($_GET['f_q'] ?? ''));
            if ($f_type !== '' && !in_array($f_type, ['office', 'personal'], true)) {
                $f_type = '';
            }

            $qLike = ($f_q !== '') ? ('%' . $f_q . '%') : '';

            // 共通: 論理削除除外
            $whereOffice = ["oc.deleted_at IS NULL"];
            $wherePersonal = ["pc.deleted_at IS NULL"];

            $params = [];

            if ($qLike !== '') {
                // ★重要: サブクエリごとに別パラメータ名にする（HY093回避）
                $params[':q_office'] = $qLike;
                $params[':q_personal'] = $qLike;

                // ★列を CONCAT_WS でまとめ、LIKE は1回だけ
                $whereOffice[] = "CONCAT_WS(' ',
                    oc.name,
                    oc.company_name_phonetic,
                    oc.representative,
                    oc.representative_letter,
                    oc.manager,
                    oc.manager_letter,
                    oc.department_in_charge,
                    oc.person_in_charge,
                    oc.driver,
                    oc.driver_letter,
                    oc.tel,
                    oc.fax,
                    oc.zip,
                    oc.addr01,
                    oc.addr02,
                    oc.mail01,
                    oc.mail02
                ) LIKE :q_office";

                $wherePersonal[] = "CONCAT_WS(' ',
                    pc.name,
                    pc.letter,
                    pc.tel01,
                    pc.mobile01,
                    pc.zip,
                    pc.addr01,
                    pc.addr02,
                    pc.mail01,
                    pc.mail02,
                    pc.emergency_contact,
                    pc.emergency_tel,
                    pc.office,
                    pc.office_letter
                ) LIKE :q_personal";
            }

            $officeSql = "
                SELECT
                  'office' AS lessee_type,
                  oc.id    AS lessee_id,
                  oc.name  AS name,
                  oc.company_name_phonetic AS kana,
                  oc.tel   AS tel,
                  oc.pref_code AS pref_code,
                  CONCAT(
                    COALESCE(oc.addr01,''),
                    CASE WHEN oc.addr02 IS NULL OR oc.addr02='' THEN '' ELSE CONCAT(' ', oc.addr02) END
                  ) AS addr
                FROM office_customers oc
                WHERE " . implode(' AND ', $whereOffice) . "
            ";

            $personalSql = "
                SELECT
                  'personal' AS lessee_type,
                  pc.id      AS lessee_id,
                  pc.name    AS name,
                  pc.letter  AS kana,
                  pc.tel01   AS tel,
                  pc.pref_code AS pref_code,
                  CONCAT(
                    COALESCE(pc.addr01,''),
                    CASE WHEN pc.addr02 IS NULL OR pc.addr02='' THEN '' ELSE CONCAT(' ', pc.addr02) END
                  ) AS addr
                FROM personal_customers pc
                WHERE " . implode(' AND ', $wherePersonal) . "
            ";

            if ($f_type === 'office') {
                $unionSql = $officeSql;
            } elseif ($f_type === 'personal') {
                $unionSql = $personalSql;
            } else {
                $unionSql = $officeSql . " UNION ALL " . $personalSql;
            }

            // filtered count
            $countSql = "SELECT COUNT(*) FROM (" . $unionSql . ") x";
            $stc = $pdo->prepare($countSql);
            foreach ($params as $k => $v) $stc->bindValue($k, $v);
            $stc->execute();
            $filtered = (int)$stc->fetchColumn();

            // total（キーワードなし・type指定に合わせる）
            if ($f_type === 'office') {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM office_customers WHERE deleted_at IS NULL")->fetchColumn();
            } elseif ($f_type === 'personal') {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM personal_customers WHERE deleted_at IS NULL")->fetchColumn();
            } else {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM office_customers WHERE deleted_at IS NULL")->fetchColumn()
                       + (int)$pdo->query("SELECT COUNT(*) FROM personal_customers WHERE deleted_at IS NULL")->fetchColumn();
            }

            // data
            $dataSql = "
                SELECT *
                FROM (" . $unionSql . ") x
                ORDER BY name ASC, lessee_id ASC
                LIMIT {$start}, {$len}
            ";
            $st = $pdo->prepare($dataSql);
            foreach ($params as $k => $v) $st->bindValue($k, $v);
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            $prefLabels = $cfg['prefectures'] ?? [];
            $typeLabels = ($cfg['options']['car_leases']['lessee_types'] ?? ['office' => '法人', 'personal' => '個人']);

            $data = array_map(function ($r) use ($prefLabels, $typeLabels) {
                $type = (string)($r['lessee_type'] ?? '');
                $id   = (int)($r['lessee_id'] ?? 0);

                $typeText = (string)($typeLabels[$type] ?? $type);
                $name = (string)($r['name'] ?? '');
                $kana = (string)($r['kana'] ?? '');
                $tel  = (string)($r['tel'] ?? '');

                $prefCode = (int)($r['pref_code'] ?? 0);
                $prefText = ($prefCode >= 1 && $prefCode <= 47) ? (string)($prefLabels[$prefCode] ?? '') : '';
                $addrText = trim($prefText . ' ' . (string)($r['addr'] ?? ''));

                $btn = '<button type="button" class="btn btn-sm btn-primary js-lessee-pick"'
                     . ' data-lessee-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
                     . ' data-lessee-id="' . $id . '"'
                     . ' data-lessee-name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                     . ' data-lessee-kana="' . htmlspecialchars($kana, ENT_QUOTES, 'UTF-8') . '"'
                     . '>選択</button>';

                return [
                    htmlspecialchars($typeText, ENT_QUOTES, 'UTF-8'),
                    $id,
                    htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($kana, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($tel, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($addrText, ENT_QUOTES, 'UTF-8'),
                    $btn,
                ];
            }, $rows);

            Response::json([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $data,
            ]);
        } catch (Throwable $e) {
            $msg = $this->isLocalEnv() ? $e->getMessage() : 'Unexpected error in lessee lookup endpoint.';
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => $msg,
            ], 500);
        }
    }
}
