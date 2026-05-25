<?php

/**
 * CarLeaseIndexController.php
 * ============================================================
 * 一覧系（リース中一覧 / リース先別一覧）
 *
 * 対応:
 * - scheduled + active を「予定/中」として扱う
 * - 開始日到来済み scheduled は一覧表示前に active 昇格を試みる
 * - 同一車両に未終了 active がある場合は昇格せず、警告情報として返す
 * - 期間が年度をまたぐ場合、該当する年度すべてに表示（重複表示）
 * - DataTables / CSV / リース先別一覧 で同じ状態を参照する
 */

class CarLeaseIndexController
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

    // ============================================================
    // Lease auto promotion helpers
    // ============================================================
    private function todayJstYmd(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
    }

    /**
     * 開始日到来済み scheduled を active に昇格
     * - ただし同一車両に別 active があれば昇格しない
     * - 昇格できなかったものは alerts として返す
     *
     * @return array<int, array<string, mixed>>
     */
    private function promoteDueScheduledLeases(?int $onlyCarId = null): array
    {
        $pdo = Db::pdo();
        $today = $this->todayJstYmd();

        $params = [':today' => $today];
        $carCond = '';
        if ($onlyCarId !== null && $onlyCarId > 0) {
            $carCond = ' AND cl.car_id = :car_id ';
            $params[':car_id'] = $onlyCarId;
        }

        $sql = "
            SELECT
                cl.id,
                cl.car_id,
                cl.lease_start_date,
                c.management_number,
                c.vehicle_number
            FROM car_leases cl
            INNER JOIN cars c
                ON c.id = cl.car_id
            WHERE cl.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND cl.status = 'scheduled'
              AND cl.lease_start_date <= :today
              {$carCond}
            ORDER BY cl.lease_start_date ASC, cl.id ASC
        ";

        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $targets = $st->fetchAll() ?: [];

        $alerts = [];

        foreach ($targets as $row) {
            $leaseId = (int)($row['id'] ?? 0);
            $carId   = (int)($row['car_id'] ?? 0);
            if ($leaseId <= 0 || $carId <= 0) {
                continue;
            }

            $pdo->beginTransaction();
            try {
                $stLease = $pdo->prepare("
                    SELECT *
                    FROM car_leases
                    WHERE id = :id
                      AND deleted_at IS NULL
                    LIMIT 1
                    FOR UPDATE
                ");
                $stLease->execute([':id' => $leaseId]);
                $lease = $stLease->fetch();
                if (!$lease) {
                    $pdo->commit();
                    continue;
                }

                if ((string)($lease['status'] ?? '') !== 'scheduled') {
                    $pdo->commit();
                    continue;
                }

                if ((string)($lease['lease_start_date'] ?? '') > $today) {
                    $pdo->commit();
                    continue;
                }

                $stCar = $pdo->prepare("
                    SELECT id
                    FROM cars
                    WHERE id = :cid
                      AND deleted_at IS NULL
                    LIMIT 1
                    FOR UPDATE
                ");
                $stCar->execute([':cid' => $carId]);
                $car = $stCar->fetch();
                if (!$car) {
                    $pdo->commit();
                    continue;
                }

                $stConflict = $pdo->prepare("
                    SELECT id
                    FROM car_leases
                    WHERE car_id = :cid
                      AND deleted_at IS NULL
                      AND status = 'active'
                      AND id <> :id
                    LIMIT 1
                    FOR UPDATE
                ");
                $stConflict->execute([
                    ':cid' => $carId,
                    ':id'  => $leaseId,
                ]);
                $activeLeaseId = (int)($stConflict->fetchColumn() ?: 0);

                if ($activeLeaseId > 0) {
                    $alerts[] = [
                        'lease_id'          => $leaseId,
                        'car_id'            => $carId,
                        'management_number' => (string)($row['management_number'] ?? ''),
                        'vehicle_number'    => (string)($row['vehicle_number'] ?? ''),
                        'active_lease_id'   => $activeLeaseId,
                        'message'           => '前回のリースが未終了のため、リース開始処理ができません。先にリース終了処理を行ってください。',
                    ];

                    $pdo->prepare("
                        UPDATE cars
                        SET current_lease_id = :lid,
                            status_code = 2
                        WHERE id = :cid
                    ")->execute([
                        ':lid' => $activeLeaseId,
                        ':cid' => $carId,
                    ]);

                    $pdo->commit();
                    continue;
                }

                $pdo->prepare("
                    UPDATE car_leases
                    SET status = 'active'
                    WHERE id = :id
                ")->execute([':id' => $leaseId]);

                $pdo->prepare("
                    UPDATE cars
                    SET current_lease_id = :lid,
                        status_code = 2
                    WHERE id = :cid
                ")->execute([
                    ':lid' => $leaseId,
                    ':cid' => $carId,
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        return $alerts;
    }

    // ============================================================
    // Fiscal Year helpers
    // ============================================================
    private function fyConfig(): array
    {
        $cfg = require __DIR__ . '/../config.php';
        $fy = $cfg['fiscal_year'] ?? ['start_month' => 4, 'start_day' => 1];

        $m = (int)($fy['start_month'] ?? 4);
        $d = (int)($fy['start_day'] ?? 1);
        if ($m < 1 || $m > 12) {
            $m = 4;
        }
        if ($d < 1 || $d > 31) {
            $d = 1;
        }

        return ['start_month' => $m, 'start_day' => $d];
    }

    private function fiscalYearOf(string $ymd): int
    {
        $cfg = $this->fyConfig();
        $startM = (int)$cfg['start_month'];
        $startD = (int)$cfg['start_day'];

        $dt = DateTime::createFromFormat('Y-m-d', $ymd, new DateTimeZone('Asia/Tokyo'));
        if (!$dt) {
            return (int)date('Y');
        }

        $y = (int)$dt->format('Y');
        $m = (int)$dt->format('n');
        $d = (int)$dt->format('j');

        $beforeStart = ($m < $startM) || ($m === $startM && $d < $startD);
        return $beforeStart ? ($y - 1) : $y;
    }

    private function fiscalYearRange(int $fy): array
    {
        $cfg = $this->fyConfig();
        $startM = (int)$cfg['start_month'];
        $startD = (int)$cfg['start_day'];

        $start = sprintf('%04d-%02d-%02d', $fy, $startM, $startD);

        $nextStart = DateTime::createFromFormat(
            'Y-m-d',
            sprintf('%04d-%02d-%02d', $fy + 1, $startM, $startD),
            new DateTimeZone('Asia/Tokyo')
        );
        if (!$nextStart) {
            return ['start' => $start, 'end' => sprintf('%04d-03-31', $fy + 1)];
        }
        $nextStart->modify('-1 day');
        $end = $nextStart->format('Y-m-d');

        return ['start' => $start, 'end' => $end];
    }

    private function dateOnly($dt): string
    {
        $s = (string)$dt;
        if ($s === '') {
            return '';
        }
        return substr($s, 0, 10);
    }

    /**
     * 期間がまたぐFYをすべて返す（inclusive）
     */
    private function fiscalYearsCovered(string $startYmd, string $endYmd): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startYmd)) {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endYmd)) {
            return [];
        }

        $fromFy = $this->fiscalYearOf($startYmd);
        $toFy   = $this->fiscalYearOf($endYmd);

        if ($toFy < $fromFy) {
            [$fromFy, $toFy] = [$toFy, $fromFy];
        }

        $out = [];
        for ($fy = $fromFy; $fy <= $toFy; $fy++) {
            $out[] = $fy;
        }
        return $out;
    }

    private function overlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return ($aStart <= $bEnd) && ($aEnd >= $bStart);
    }

    // ============================================================
    // Common helpers for active/scheduled list
    // ============================================================
    private function activeListFilters(): array
    {
        $f_status = trim((string)($_GET['f_status'] ?? 'both'));
        if (!in_array($f_status, ['both', 'active', 'scheduled'], true)) {
            $f_status = 'both';
        }

        $f_from = trim((string)($_GET['f_from'] ?? ''));
        $f_to   = trim((string)($_GET['f_to'] ?? ''));

        $whereBase = [];
        $params = [];

        $whereBase[] = "cl.deleted_at IS NULL";
        $whereBase[] = "c.deleted_at IS NULL";

        if ($f_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) {
            $whereBase[] = "cl.lease_end_date >= :from";
            $params[':from'] = $f_from;
        }
        if ($f_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to)) {
            $whereBase[] = "cl.lease_start_date <= :to";
            $params[':to'] = $f_to;
        }

        $whereStatus = [];
        if ($f_status === 'active') {
            $whereStatus[] = "cl.status = 'active'";
        } elseif ($f_status === 'scheduled') {
            $whereStatus[] = "cl.status = 'scheduled'";
        } else {
            $whereStatus[] = "cl.status IN ('scheduled','active')";
        }

        return [
            'f_status'    => $f_status,
            'f_from'      => $f_from,
            'f_to'        => $f_to,
            'whereBase'   => $whereBase,
            'whereStatus' => $whereStatus,
            'params'      => $params,
            'wBase'       => ' WHERE ' . implode(' AND ', $whereBase),
            'wAll'        => ' WHERE ' . implode(' AND ', array_merge($whereBase, $whereStatus)),
        ];
    }

    private function csvStatusLabel(string $status, array $statusLabels): string
    {
        if ($status === 'scheduled') {
            return (string)($statusLabels['scheduled'] ?? 'リース予定');
        }
        if ($status === 'active') {
            return (string)($statusLabels['active'] ?? 'リース中');
        }
        return (string)($statusLabels[$status] ?? $status);
    }

    private function csvDate(string $ymd): string
    {
        $s = trim($ymd);
        if ($s === '') {
            return '';
        }
        return str_replace('-', '/', substr($s, 0, 10));
    }

    // ============================================================
    // Pages
    // ============================================================
    public function indexActive(): void
    {
        $me = $this->guardView();
        $cfg = require __DIR__ . '/../config.php';

        $alerts = $this->promoteDueScheduledLeases();

        Response::view('car_leases/index_active', [
            'title'  => 'リース中車両一覧（予定/中）',
            'me'     => $me,
            'cfg'    => $cfg,
            'alerts' => $alerts,
        ]);
    }

    public function byLessee($lesseeType, $lesseeId): void
    {
        $me = $this->guardView();

        $lesseeType = (string)$lesseeType;
        $lesseeId = (int)$lesseeId;
        if (!in_array($lesseeType, ['office', 'personal'], true) || $lesseeId <= 0) {
            Response::notFound();
        }

        $alerts = $this->promoteDueScheduledLeases();

        $pdo = Db::pdo();

        $lesseeName = '';
        if ($lesseeType === 'office') {
            $st = $pdo->prepare("SELECT name FROM office_customers WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        } else {
            $st = $pdo->prepare("SELECT name FROM personal_customers WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        }
        $st->execute([':i' => $lesseeId]);
        $lesseeName = (string)($st->fetchColumn() ?: '');

        $st2 = $pdo->prepare("
            SELECT cl.*, c.management_number, c.vehicle_number, c.chassis_number
            FROM car_leases cl
            INNER JOIN cars c ON c.id = cl.car_id
            WHERE cl.lessee_type = :t
              AND cl.lessee_id   = :i
              AND cl.deleted_at IS NULL
            ORDER BY cl.lease_start_date DESC, cl.id DESC
        ");
        $st2->execute([':t' => $lesseeType, ':i' => $lesseeId]);
        $leases = $st2->fetchAll() ?: [];

        $currentScheduled = [];
        $currentActive = [];
        $pastByFy = [];

        foreach ($leases as $r) {
            $status = (string)($r['status'] ?? '');

            if ($status === 'scheduled') {
                $currentScheduled[] = $r;
                continue;
            }
            if ($status === 'active') {
                $currentActive[] = $r;
                continue;
            }

            $startYmd = (string)($r['lease_start_date'] ?? '');
            $planEnd  = (string)($r['lease_end_date'] ?? '');
            $endEff   = $planEnd;

            $ended = $this->dateOnly($r['ended_at'] ?? '');
            $canc  = $this->dateOnly($r['canceled_at'] ?? '');
            if ($ended !== '' && $ended > $endEff) {
                $endEff = $ended;
            }
            if ($canc !== '' && $canc > $endEff) {
                $endEff = $canc;
            }

            if ($startYmd === '' || $endEff === '') {
                continue;
            }

            $fys = $this->fiscalYearsCovered($startYmd, $endEff);
            foreach ($fys as $fy) {
                $rng = $this->fiscalYearRange((int)$fy);
                if ($this->overlap($startYmd, $endEff, $rng['start'], $rng['end'])) {
                    if (!isset($pastByFy[$fy])) {
                        $pastByFy[$fy] = [];
                    }
                    $pastByFy[$fy][] = $r;
                }
            }
        }

        krsort($pastByFy);

        $cfg = require __DIR__ . '/../config.php';

        Response::view('car_leases/by_lessee', [
            'title'            => 'リース先別 リース一覧',
            'me'               => $me,
            'cfg'              => $cfg,
            'lesseeType'       => $lesseeType,
            'lesseeId'         => $lesseeId,
            'lesseeName'       => $lesseeName,
            'currentScheduled' => $currentScheduled,
            'currentActive'    => $currentActive,
            'pastByFy'         => $pastByFy,
            'alerts'           => $alerts,
        ]);
    }

    // ============================================================
    // CSV Export: scheduled + active
    // ============================================================
    public function exportCsv(): void
    {
        $this->guardView();

        try {
            $this->promoteDueScheduledLeases();

            $pdo = Db::pdo();
            $cfg = require __DIR__ . '/../config.php';

            $filters = $this->activeListFilters();
            $params = $filters['params'];
            $wAll   = $filters['wAll'];

            $sql = "
                SELECT
                  cl.id AS lease_id,
                  cl.car_id,
                  c.maker,
                  c.car_model,
                  c.vehicle_number,
                  c.chassis_number,
                  c.model_year,
                  cl.status,
                  cl.lease_start_date,
                  cl.lease_end_date,
                  COALESCE(oc.name, pc.name, '') AS lessee_name
                FROM car_leases cl
                INNER JOIN cars c ON c.id = cl.car_id
                LEFT JOIN office_customers oc ON (cl.lessee_type='office' AND oc.id=cl.lessee_id)
                LEFT JOIN personal_customers pc ON (cl.lessee_type='personal' AND pc.id=cl.lessee_id)
                {$wAll}
                ORDER BY
                  CASE cl.status
                    WHEN 'active' THEN 0
                    WHEN 'scheduled' THEN 1
                    ELSE 9
                  END ASC,
                  c.maker ASC,
                  c.car_model ASC,
                  c.vehicle_number ASC,
                  cl.id ASC
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            $optsCars = $cfg['options']['cars'] ?? [];
            $makerLabels = $optsCars['maker'] ?? [];
            $modelFlat   = $optsCars['car_models_flat'] ?? [];

            $leaseOpts = $cfg['options']['car_leases'] ?? [];
            $statusLabels = $leaseOpts['lease_status'] ?? [];

            $filename = 'car_leases_' . date('Ymd_His') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new RuntimeException('Failed to open output stream.');
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No', 'メーカー', '車種', '車両番号', '車台番号', '年式', '状態', 'リース先', 'リース期間']);

            $no = 0;
            foreach ($rows as $r) {
                $no++;

                $makerKey = (string)($r['maker'] ?? '');
                $makerText = $makerKey !== '' ? (string)($makerLabels[$makerKey] ?? $makerKey) : '';

                $modelKey = (string)($r['car_model'] ?? '');
                $modelText = $modelKey !== '' ? (string)($modelFlat[$modelKey] ?? $modelKey) : '';

                $vehicleNumber = (string)($r['vehicle_number'] ?? '');
                $chassisNumber = (string)($r['chassis_number'] ?? '');

                $modelYearRaw = (string)($r['model_year'] ?? '');
                $modelYear = ($modelYearRaw !== '' && $modelYearRaw !== '0') ? $modelYearRaw : '';

                $status = (string)($r['status'] ?? '');
                $leaseStartDate = (string)($r['lease_start_date'] ?? '');
                $leaseEndDate   = (string)($r['lease_end_date'] ?? '');
                $statusText = $this->csvStatusLabel($status, $statusLabels);

                $lesseeName = (string)($r['lessee_name'] ?? '');
                $periodText = $this->csvDate($leaseStartDate) . '～' . $this->csvDate($leaseEndDate);

                fputcsv($out, [
                    $no,
                    $makerText,
                    $modelText,
                    $vehicleNumber,
                    $chassisNumber,
                    $modelYear,
                    $statusText,
                    $lesseeName,
                    $periodText,
                ]);
            }

            fclose($out);
            exit;
        } catch (Throwable $e) {
            if (!headers_sent()) {
                Response::fail('SERVER_ERROR', 'CSV export failed.', 500);
            }
            exit;
        }
    }

    // ============================================================
    // DataTables API: scheduled + active
    // ============================================================
    public function datatableActive(): void
    {
        $me = $this->guardView();

        try {
            $alerts = $this->promoteDueScheduledLeases();

            $pdo = Db::pdo();
            $cfg = require __DIR__ . '/../config.php';

            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));

            $len = isset($_GET['length']) ? (int)($_GET['length']) : 200;
            $len = max(1, min(200, $len));

            $filters = $this->activeListFilters();
            $params = $filters['params'];
            $wBase  = $filters['wBase'];
            $wAll   = $filters['wAll'];

            $countSql = "
                SELECT
                  COALESCE(SUM(CASE WHEN cl.status = 'active' THEN 1 ELSE 0 END), 0) AS active_count,
                  COALESCE(SUM(CASE WHEN cl.status = 'scheduled' THEN 1 ELSE 0 END), 0) AS scheduled_count
                FROM car_leases cl
                INNER JOIN cars c ON c.id = cl.car_id
                {$wBase}
            ";
            $stCounts = $pdo->prepare($countSql);
            foreach ($params as $k => $v) {
                $stCounts->bindValue($k, $v);
            }
            $stCounts->execute();
            $countRow = $stCounts->fetch(PDO::FETCH_ASSOC) ?: [];

            $activeCount    = (int)($countRow['active_count'] ?? 0);
            $scheduledCount = (int)($countRow['scheduled_count'] ?? 0);

            $stFiltered = $pdo->prepare("
                SELECT COUNT(*)
                FROM car_leases cl
                INNER JOIN cars c ON c.id = cl.car_id
                {$wAll}
            ");
            foreach ($params as $k => $v) {
                $stFiltered->bindValue($k, $v);
            }
            $stFiltered->execute();
            $filtered = (int)$stFiltered->fetchColumn();

            $total = (int)$pdo->query("
                SELECT COUNT(*)
                FROM car_leases cl
                INNER JOIN cars c ON c.id = cl.car_id
                WHERE cl.status IN ('scheduled','active')
                  AND cl.deleted_at IS NULL
                  AND c.deleted_at IS NULL
            ")->fetchColumn();

            $colMap = [
                1 => ['c.maker', 'c.car_model'],
                2 => ['c.vehicle_number'],
                3 => ['lessee_name'],
                4 => ['cl.lease_start_date', 'cl.lease_end_date'],
                5 => ['cl.status'],
                6 => ['cl.monthly_fee'],
            ];

            $orderParts = [];
            if (isset($_GET['order']) && is_array($_GET['order'])) {
                $cnt = 0;
                foreach ($_GET['order'] as $ord) {
                    if ($cnt >= 3) {
                        break;
                    }
                    $colIdx = isset($ord['column']) ? (int)$ord['column'] : -1;
                    if (!isset($colMap[$colIdx])) {
                        continue;
                    }

                    $dir = strtolower((string)($ord['dir'] ?? 'asc'));
                    $dir = ($dir === 'desc') ? 'DESC' : 'ASC';

                    foreach ($colMap[$colIdx] as $cname) {
                        $orderParts[] = $cname . ' ' . $dir;
                    }
                    $cnt++;
                }
            }
            if (!$orderParts) {
                $orderParts[] = "cl.lease_start_date ASC";
                $orderParts[] = "cl.id ASC";
            }
            $orderBySql = " ORDER BY " . implode(', ', $orderParts);

            $sql = "
                SELECT
                  cl.id AS lease_id,
                  cl.car_id,
                  c.maker,
                  c.car_model,
                  c.vehicle_number,
                  cl.status,
                  COALESCE(oc.name, pc.name, '') AS lessee_name,
                  cl.lease_start_date,
                  cl.lease_end_date,
                  cl.monthly_fee
                FROM car_leases cl
                INNER JOIN cars c ON c.id = cl.car_id
                LEFT JOIN office_customers oc ON (cl.lessee_type='office' AND oc.id=cl.lessee_id)
                LEFT JOIN personal_customers pc ON (cl.lessee_type='personal' AND pc.id=cl.lessee_id)
                {$wAll}
                {$orderBySql}
                LIMIT {$start}, {$len}
            ";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            $optsCars = $cfg['options']['cars'] ?? [];
            $makerLabels = $optsCars['maker'] ?? [];
            $modelFlat   = $optsCars['car_models_flat'] ?? [];

            $leaseOpts = $cfg['options']['car_leases'] ?? [];
            $statusLabels = $leaseOpts['lease_status'] ?? [];

            $canEdit = Policies::canEditCarLeases($me);

            $data = [];
            $i = 0;

            foreach ($rows as $r) {
                $i++;
                $no = $start + $i;

                $makerKey = (string)($r['maker'] ?? '');
                $makerText = $makerKey !== '' ? (string)($makerLabels[$makerKey] ?? $makerKey) : '';

                $modelKey = (string)($r['car_model'] ?? '');
                $modelText = $modelKey !== '' ? (string)($modelFlat[$modelKey] ?? $modelKey) : '';

                $carModelText = trim($makerText . ' ' . $modelText);

                $vehicleNumber = (string)($r['vehicle_number'] ?? '');
                $lesseeName = (string)($r['lessee_name'] ?? '');

                $period = str_replace('-', '/', (string)$r['lease_start_date']) . ' ～ ' . str_replace('-', '/', (string)$r['lease_end_date']);

                $status = (string)($r['status'] ?? '');
                $statusText = (string)($statusLabels[$status] ?? $status);

                $feeText = number_format((int)($r['monthly_fee'] ?? 0));

                $leaseId = (int)$r['lease_id'];
                $carId   = (int)$r['car_id'];

                $menuItems = [];
                $menuItems[] = '<li><a class="dropdown-item" href="/cars/' . $carId . '">車両詳細</a></li>';
                if ($canEdit) {
                    $menuItems[] = '<li><a class="dropdown-item text-danger" href="/car_leases/' . $leaseId . '/force_end">終了</a></li>';
                }

                $buttons  = '<div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap text-nowrap">';
                $buttons .= '<a class="btn btn-lease btn-sm btn-outline-primary text-nowrap" href="/cars/' . $carId . '/leases">リース詳細</a>';

                if (!empty($menuItems)) {
                    $buttons .= '
                        <div class="dropdown">
                            <button class="btn btn-lease btn-sm btn-outline-secondary dropdown-toggle text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                その他
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">'
                                . implode('', $menuItems) .
                            '</ul>
                        </div>';
                }

                $buttons .= '</div>';

                $data[] = [
                    $no,
                    htmlspecialchars($carModelText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($vehicleNumber, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($lesseeName, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($period, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($feeText, ENT_QUOTES, 'UTF-8'),
                    $buttons,
                ];
            }

            Response::json([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
                'meta' => [
                    'active_count'    => $activeCount,
                    'scheduled_count' => $scheduledCount,
                    'alerts'          => $alerts,
                ],
            ]);
        } catch (Throwable $e) {
            Response::json([
                'error' => 'SERVER_ERROR',
                'message' => 'Unexpected error in datatable endpoint.',
            ], 500);
        }
    }
}