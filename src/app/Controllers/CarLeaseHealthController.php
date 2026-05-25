<?php

/**
 * CarLeaseHealthController.php
 * ============================================================
 * 役割:
 * - 車両リース整合性チェック一覧
 * - 異常値/不整合の検索・検出・一覧表示
 *
 * 方針:
 * - K-Core準拠
 * - 一覧画面 + DataTables API
 * - 権限は car_leases の view/edit に準拠
 * - 異常の自動修正はしない（検出・表示のみ）
 *
 * 検出対象:
 * 1) active があるのに cars.status_code != 2 または current_lease_id 不一致
 * 2) cars.status_code = 2 なのに対応する active が無い
 * 3) 開始日到来済みなのに scheduled のまま
 * 4) 同一車両に active が2件以上ある
 * 5) status_code = 6（リース予定）なのに scheduled が無い
 * 6) scheduled はあるのに cars.status_code が 2/6 以外
 * 7) current_lease_id が実在しない、または active ではない
 * 8) 開始日到来済み scheduled が同一車両に複数ある
 */

class CarLeaseHealthController
{
    // ============================================================
    // Guards
    // ============================================================
    private function guardView(): array
    {
        $u = Auth::user();
        if (!$u) {
            if (Response::isApi()) {
                Response::fail('UNAUTHORIZED', 'Unauthorized', 401);
            }
            Response::redirect('/');
        }

        Policies::guardView($u, 'car_leases');
        return $u;
    }

    private function guardEdit(): array
    {
        $u = $this->guardView();
        Policies::guardEdit($u, 'car_leases');
        return $u;
    }

    // ============================================================
    // Common helpers
    // ============================================================
    private function todayJstYmd(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
    }

    private function issueLabels(): array
    {
        return [
            'active_car_mismatch'        => 'active と車両状態の不一致',
            'car_active_missing'         => '車両はリース中だが active 不在',
            'scheduled_due_not_started'  => '開始日超過の予定リース',
            'multi_active_same_car'      => '同一車両に active 複数',
            'scheduled_status_missing'   => 'リース予定状態の欠落',
            'scheduled_car_status_bad'   => 'scheduled と車両状態の不一致',
            'broken_current_lease'       => 'current_lease_id 不整合',
            'multi_due_scheduled'        => '開始日超過 scheduled 複数',
        ];
    }

    private function severityLabels(): array
    {
        return [
            'high'   => '危険',
            'medium' => '注意',
            'low'    => '確認',
        ];
    }

    private function severityOrder(string $severity): int
    {
        return match ($severity) {
            'high'   => 0,
            'medium' => 1,
            default  => 2,
        };
    }

    private function normalizeText(?string $s): string
    {
        return trim((string)$s);
    }

    private function carDisplay(array $row): string
    {
        $mgmt = $this->normalizeText($row['management_number'] ?? '');
        $veh  = $this->normalizeText($row['vehicle_number'] ?? '');

        if ($mgmt !== '' && $veh !== '') return $mgmt . ' / ' . $veh;
        if ($mgmt !== '') return $mgmt;
        if ($veh !== '') return $veh;

        $carId = (int)($row['car_id'] ?? 0);
        return $carId > 0 ? ('CAR#' . $carId) : '—';
    }

    private function makeActionButtons(array $row, bool $canEdit): string
    {
        $carId   = (int)($row['car_id'] ?? 0);
        $leaseId = (int)($row['lease_id'] ?? 0);

        $buttons = [];

        if ($carId > 0) {
            $buttons[] = '<a class="btn btn-sm btn-outline-secondary" href="/cars/' . $carId . '">車両詳細</a>';
            $buttons[] = '<a class="btn btn-sm btn-outline-primary" href="/cars/' . $carId . '/leases">リース詳細</a>';
        }

        if ($canEdit && $leaseId > 0) {
            $buttons[] = '<a class="btn btn-sm btn-outline-danger" href="/car_leases/' . $leaseId . '/force_end">終了</a>';
        }

        return implode(' ', $buttons);
    }

    /**
     * 異常一覧を作る
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectIssues(): array
    {
        $pdo = Db::pdo();
        $today = $this->todayJstYmd();

        $issues = [];
        $seen = [];

        $push = function (array $row) use (&$issues, &$seen): void {
            $issueType = (string)($row['issue_type'] ?? '');
            $carId     = (int)($row['car_id'] ?? 0);
            $leaseId   = (int)($row['lease_id'] ?? 0);
            $extraKey  = (string)($row['extra_key'] ?? '');

            $dedupeKey = implode(':', [$issueType, $carId, $leaseId, $extraKey]);
            if (isset($seen[$dedupeKey])) {
                return;
            }
            $seen[$dedupeKey] = true;
            unset($row['extra_key']);

            $issues[] = $row;
        };

        // ------------------------------------------------------------
        // 1) active があるのに cars.status_code != 2 または current_lease_id 不一致
        // ------------------------------------------------------------
        $st1 = $pdo->query("
            SELECT
                c.id AS car_id,
                c.management_number,
                c.vehicle_number,
                c.status_code,
                c.current_lease_id,
                cl.id AS lease_id,
                cl.lease_start_date,
                cl.lease_end_date
            FROM cars c
            INNER JOIN car_leases cl
                ON cl.car_id = c.id
               AND cl.deleted_at IS NULL
               AND cl.status = 'active'
            WHERE c.deleted_at IS NULL
              AND (
                   c.status_code <> 2
                   OR c.current_lease_id IS NULL
                   OR c.current_lease_id <> cl.id
              )
            ORDER BY c.id, cl.id
        ");
        foreach (($st1->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'active_car_mismatch',
                'severity'          => 'high',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => (int)$r['lease_id'],
                'message'           => 'active リースは存在しますが、cars.status_code または current_lease_id が一致していません。',
            ]);
        }

        // ------------------------------------------------------------
        // 2) cars.status_code = 2 なのに対応する active が無い
        // ------------------------------------------------------------
        $st2 = $pdo->query("
            SELECT
                c.id AS car_id,
                c.management_number,
                c.vehicle_number,
                c.status_code,
                c.current_lease_id
            FROM cars c
            LEFT JOIN car_leases cl
                ON cl.id = c.current_lease_id
               AND cl.deleted_at IS NULL
               AND cl.status = 'active'
            WHERE c.deleted_at IS NULL
              AND c.status_code = 2
              AND cl.id IS NULL
            ORDER BY c.id
        ");
        foreach (($st2->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'car_active_missing',
                'severity'          => 'high',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => (int)($r['current_lease_id'] ?? 0),
                'message'           => '車両は「リース中」ですが、対応する active リースが存在しません。',
            ]);
        }

        // ------------------------------------------------------------
        // 3) 開始日到来済みなのに scheduled のまま
        // ------------------------------------------------------------
        $st3 = $pdo->prepare("
            SELECT
                cl.id AS lease_id,
                cl.car_id,
                c.management_number,
                c.vehicle_number,
                cl.lease_start_date,
                cl.lease_end_date
            FROM car_leases cl
            INNER JOIN cars c
                ON c.id = cl.car_id
            WHERE cl.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND cl.status = 'scheduled'
              AND cl.lease_start_date <= :today
            ORDER BY cl.lease_start_date, cl.id
        ");
        $st3->execute([':today' => $today]);
        foreach (($st3->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'scheduled_due_not_started',
                'severity'          => 'medium',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => (int)$r['lease_id'],
                'message'           => '開始日 ' . (string)$r['lease_start_date'] . ' を過ぎていますが、scheduled のままです。',
            ]);
        }

        // ------------------------------------------------------------
        // 4) 同一車両に active が2件以上ある
        // ------------------------------------------------------------
        $st4 = $pdo->query("
            SELECT
                cl.car_id,
                c.management_number,
                c.vehicle_number,
                COUNT(*) AS active_count
            FROM car_leases cl
            INNER JOIN cars c
                ON c.id = cl.car_id
            WHERE cl.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND cl.status = 'active'
            GROUP BY cl.car_id, c.management_number, c.vehicle_number
            HAVING COUNT(*) >= 2
            ORDER BY cl.car_id
        ");
        foreach (($st4->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'multi_active_same_car',
                'severity'          => 'high',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => 0,
                'message'           => '同一車両に active リースが ' . (int)$r['active_count'] . ' 件あります。',
            ]);
        }

        // ------------------------------------------------------------
        // 5) status_code = 6（リース予定）なのに scheduled が無い
        // ------------------------------------------------------------
        $st5 = $pdo->query("
            SELECT
                c.id AS car_id,
                c.management_number,
                c.vehicle_number,
                c.status_code
            FROM cars c
            LEFT JOIN car_leases cl
                ON cl.car_id = c.id
               AND cl.deleted_at IS NULL
               AND cl.status = 'scheduled'
            WHERE c.deleted_at IS NULL
              AND c.status_code = 6
            GROUP BY c.id, c.management_number, c.vehicle_number, c.status_code
            HAVING COUNT(cl.id) = 0
            ORDER BY c.id
        ");
        foreach (($st5->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'scheduled_status_missing',
                'severity'          => 'medium',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => 0,
                'message'           => '車両は「リース予定」ですが、対応する scheduled リースが存在しません。',
            ]);
        }

        // ------------------------------------------------------------
        // 6) scheduled はあるのに cars.status_code が 2/6 以外
        // ------------------------------------------------------------
        $st6 = $pdo->query("
            SELECT DISTINCT
                c.id AS car_id,
                c.management_number,
                c.vehicle_number,
                c.status_code,
                cl.id AS lease_id
            FROM cars c
            INNER JOIN car_leases cl
                ON cl.car_id = c.id
               AND cl.deleted_at IS NULL
               AND cl.status = 'scheduled'
            WHERE c.deleted_at IS NULL
              AND c.status_code NOT IN (2, 6)
            ORDER BY c.id, cl.id
        ");
        foreach (($st6->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'scheduled_car_status_bad',
                'severity'          => 'medium',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => (int)$r['lease_id'],
                'message'           => 'scheduled リースはありますが、cars.status_code が 2 または 6 ではありません。',
            ]);
        }

        // ------------------------------------------------------------
        // 7) current_lease_id が実在しない、または active ではない
        // ------------------------------------------------------------
        $st7 = $pdo->query("
            SELECT
                c.id AS car_id,
                c.management_number,
                c.vehicle_number,
                c.current_lease_id
            FROM cars c
            LEFT JOIN car_leases cl
                ON cl.id = c.current_lease_id
               AND cl.deleted_at IS NULL
               AND cl.status = 'active'
            WHERE c.deleted_at IS NULL
              AND c.current_lease_id IS NOT NULL
              AND cl.id IS NULL
            ORDER BY c.id
        ");
        foreach (($st7->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'broken_current_lease',
                'severity'          => 'high',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => (int)($r['current_lease_id'] ?? 0),
                'message'           => 'cars.current_lease_id が未存在、削除済み、または active ではありません。',
            ]);
        }

        // ------------------------------------------------------------
        // 8) 開始日到来済み scheduled が同一車両に複数ある
        // ------------------------------------------------------------
        $st8 = $pdo->prepare("
            SELECT
                cl.car_id,
                c.management_number,
                c.vehicle_number,
                COUNT(*) AS due_scheduled_count
            FROM car_leases cl
            INNER JOIN cars c
                ON c.id = cl.car_id
            WHERE cl.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND cl.status = 'scheduled'
              AND cl.lease_start_date <= :today
            GROUP BY cl.car_id, c.management_number, c.vehicle_number
            HAVING COUNT(*) >= 2
            ORDER BY cl.car_id
        ");
        $st8->execute([':today' => $today]);
        foreach (($st8->fetchAll() ?: []) as $r) {
            $push([
                'issue_type'        => 'multi_due_scheduled',
                'severity'          => 'medium',
                'car_id'            => (int)$r['car_id'],
                'management_number' => (string)($r['management_number'] ?? ''),
                'vehicle_number'    => (string)($r['vehicle_number'] ?? ''),
                'lease_id'          => 0,
                'message'           => '開始日到来済みの scheduled リースが ' . (int)$r['due_scheduled_count'] . ' 件あります。',
            ]);
        }

        return $issues;
    }

    private function filteredIssues(array $rows): array
    {
        $fIssueType        = trim((string)($_GET['f_issue_type'] ?? ''));
        $fCarId            = trim((string)($_GET['f_car_id'] ?? ''));
        $fManagementNumber = trim((string)($_GET['f_management_number'] ?? ''));
        $fLeaseId          = trim((string)($_GET['f_lease_id'] ?? ''));

        return array_values(array_filter($rows, function (array $row) use ($fIssueType, $fCarId, $fManagementNumber, $fLeaseId): bool {
            if ($fIssueType !== '' && (string)$row['issue_type'] !== $fIssueType) {
                return false;
            }

            if ($fCarId !== '' && (int)$row['car_id'] !== (int)$fCarId) {
                return false;
            }

            if ($fManagementNumber !== '') {
                $needle = mb_strtolower($fManagementNumber);
                $hay = mb_strtolower((string)($row['management_number'] ?? ''));
                if (mb_strpos($hay, $needle) === false) {
                    return false;
                }
            }

            if ($fLeaseId !== '' && (int)$row['lease_id'] !== (int)$fLeaseId) {
                return false;
            }

            return true;
        }));
    }

    private function sortIssues(array $rows): array
    {
        $order = $_GET['order'] ?? [];
        $colIdx = -1;
        $dir = 'asc';

        if (is_array($order) && isset($order[0]) && is_array($order[0])) {
            $colIdx = isset($order[0]['column']) ? (int)$order[0]['column'] : -1;
            $dir = strtolower((string)($order[0]['dir'] ?? 'asc'));
            $dir = ($dir === 'desc') ? 'desc' : 'asc';
        }

        usort($rows, function (array $a, array $b) use ($colIdx, $dir): int {
            $cmp = 0;

            switch ($colIdx) {
                case 1: // 異常種別
                    $cmp = strcmp((string)$a['issue_label'], (string)$b['issue_label']);
                    break;
                case 2: // 重要度
                    $cmp = $this->severityOrder((string)$a['severity']) <=> $this->severityOrder((string)$b['severity']);
                    break;
                case 3: // 車両
                    $cmp = strcmp($this->carDisplay($a), $this->carDisplay($b));
                    break;
                case 4: // リースID
                    $cmp = ((int)$a['lease_id']) <=> ((int)$b['lease_id']);
                    break;
                default:
                    // 既定: 重要度 > 異常種別 > 車両ID > リースID
                    $cmp = $this->severityOrder((string)$a['severity']) <=> $this->severityOrder((string)$b['severity']);
                    if ($cmp === 0) $cmp = strcmp((string)$a['issue_label'], (string)$b['issue_label']);
                    if ($cmp === 0) $cmp = ((int)$a['car_id']) <=> ((int)$b['car_id']);
                    if ($cmp === 0) $cmp = ((int)$a['lease_id']) <=> ((int)$b['lease_id']);
                    break;
            }

            if ($dir === 'desc') {
                $cmp *= -1;
            }
            return $cmp;
        });

        return $rows;
    }

    // ============================================================
    // Pages
    // ============================================================
    public function index(): void
    {
        $me = $this->guardView();
        $cfg = require __DIR__ . '/../config.php';

        Response::view('car_leases/health', [
            'title'          => 'リース整合性チェック',
            'me'             => $me,
            'cfg'            => $cfg,
            'issueLabels'    => $this->issueLabels(),
            'severityLabels' => $this->severityLabels(),
        ]);
    }

    // ============================================================
    // DataTables API
    // ============================================================
    public function datatable(): void
    {
        $me = $this->guardView();

        try {
            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));
            $len   = isset($_GET['length']) ? (int)$_GET['length'] : 200;
            $len   = max(1, min(500, $len));

            $issueLabels = $this->issueLabels();
            $severityLabels = $this->severityLabels();

            $rows = $this->collectIssues();

            foreach ($rows as &$row) {
                $row['issue_label']    = (string)($issueLabels[$row['issue_type']] ?? $row['issue_type']);
                $row['severity_label'] = (string)($severityLabels[$row['severity']] ?? $row['severity']);
            }
            unset($row);

            $total = count($rows);

            $rows = $this->filteredIssues($rows);
            $filtered = count($rows);

            $rows = $this->sortIssues($rows);
            $rows = array_slice($rows, $start, $len);

            $canEdit = Policies::canEditCarLeases($me);

            $data = [];
            $idx = 0;

            foreach ($rows as $row) {
                $idx++;
                $no = $start + $idx;

                $severityText = (string)$row['severity_label'];
                $severityClass = match ((string)$row['severity']) {
                    'high'   => 'text-bg-danger',
                    'medium' => 'text-bg-warning',
                    default  => 'text-bg-secondary',
                };

                $leaseIdText = ((int)$row['lease_id'] > 0) ? (string)((int)$row['lease_id']) : '—';

                $data[] = [
                    $no,
                    htmlspecialchars((string)$row['issue_label'], ENT_QUOTES, 'UTF-8'),
                    '<span class="badge ' . $severityClass . '">' . htmlspecialchars($severityText, ENT_QUOTES, 'UTF-8') . '</span>',
                    htmlspecialchars($this->carDisplay($row), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($leaseIdText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)$row['message'], ENT_QUOTES, 'UTF-8'),
                    $this->makeActionButtons($row, $canEdit),
                ];
            }

            Response::json([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $data,
                'meta'            => [
                    'total_issues' => $total,
                ],
            ]);
        } catch (Throwable $e) {
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => 'Unexpected error in health datatable endpoint.',
            ], 500);
        }
    }
}