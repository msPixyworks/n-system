<?php

/**
 * CarLeaseProfitController.php
 * ============================================================
 * 全車両横断のリース収支集計
 *
 * 役割:
 * - 指定期間で全車両の売上 / 経費 / 利益を集計する
 * - 画面表示
 * - summary API
 * - datatable API
 *
 * 方針:
 * - 権限は car_profits.view
 * - 売上は car_leases を期間重なり月数で集計
 * - ended は ended_at、canceled は canceled_at を実終了日として扱う
 * - 経費は cars の購入系 + 税 + 保険を原資にする
 * - 購入価格 / リサイクル料 / 購入諸費用 は 60ヶ月按分
 * - 自動車税 / 保険料 は 12ヶ月按分
 * - 月数カウントは CarProfitController と同じ確定仕様に合わせる
 * - 収支はDB保存しない（表示時集計）
 */

class CarLeaseProfitController
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

        Policies::guardView($u, 'car_profits');
        return $u;
    }

    // ============================================================
    // Basic helpers
    // ============================================================
    private function isYmd(string $s): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
        $dt = DateTime::createFromFormat('Y-m-d', $s, new DateTimeZone('Asia/Tokyo'));
        return $dt && $dt->format('Y-m-d') === $s;
    }

    private function todayJstYmd(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
    }

    private function toDateOnly(?string $s): string
    {
        $v = trim((string)$s);
        if ($v === '') return '';
        return substr($v, 0, 10);
    }

    private function toIntOrZero($v): int
    {
        if ($v === null || $v === '') return 0;
        return (int)$v;
    }

    private function fyConfig(): array
    {
        $cfg = require __DIR__ . '/../config.php';
        $fy = $cfg['fiscal_year'] ?? ['start_month' => 4, 'start_day' => 1];

        $m = (int)($fy['start_month'] ?? 4);
        $d = (int)($fy['start_day'] ?? 1);

        if ($m < 1 || $m > 12) $m = 4;
        if ($d < 1 || $d > 31) $d = 1;

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
            return ['start' => $start, 'end' => sprintf('%04d-12-31', $fy)];
        }

        $nextStart->modify('-1 day');
        return [
            'start' => $start,
            'end'   => $nextStart->format('Y-m-d'),
        ];
    }

    private function defaultPeriod(): array
    {
        $today = $this->todayJstYmd();
        $fy = $this->fiscalYearOf($today);
        $range = $this->fiscalYearRange($fy);

        return [
            'from' => $range['start'],
            'to'   => $today,
        ];
    }

    /**
     * @return array{ok:bool,from?:string,to?:string,mode?:string,error?:string}
     */
    private function normalizePeriod(array $src): array
    {
        $defaults = $this->defaultPeriod();

        $from = trim((string)($src['f_from'] ?? $defaults['from']));
        $to   = trim((string)($src['f_to'] ?? $defaults['to']));
        $mode = trim((string)($src['f_mode'] ?? 'all'));

        if (!in_array($mode, ['all', 'revenue', 'deficit'], true)) {
            $mode = 'all';
        }

        if (!$this->isYmd($from)) {
            return ['ok' => false, 'error' => '集計開始日が不正です。'];
        }
        if (!$this->isYmd($to)) {
            return ['ok' => false, 'error' => '集計終了日が不正です。'];
        }
        if ($from > $to) {
            return ['ok' => false, 'error' => '集計開始日は集計終了日以前にしてください。'];
        }

        return [
            'ok'   => true,
            'from' => $from,
            'to'   => $to,
            'mode' => $mode,
        ];
    }

    // ============================================================
    // Month count（CarProfitController と同じ確定仕様）
    // ============================================================
    /**
     * 月数カウント（確定仕様）
     * - 「日」は基本見ない
     * - baseMonths = (endY*12+endM) - (startY*12+startM)
     * - baseMonths==0: end>startなら1, 同日なら0
     * - baseMonths>=1: months=baseMonths
     */
    private function countMonths(string $startYmd, string $endYmd): int
    {
        $s = DateTime::createFromFormat('Y-m-d', $startYmd, new DateTimeZone('Asia/Tokyo'));
        $e = DateTime::createFromFormat('Y-m-d', $endYmd, new DateTimeZone('Asia/Tokyo'));
        if (!$s || !$e) return 0;

        $sy = (int)$s->format('Y');
        $sm = (int)$s->format('n');
        $sd = (int)$s->format('j');

        $ey = (int)$e->format('Y');
        $em = (int)$e->format('n');
        $ed = (int)$e->format('j');

        $base = ($ey * 12 + $em) - ($sy * 12 + $sm);

        if ($base === 0) {
            if ($ey === $sy && $em === $sm) {
                if ($ed > $sd) return 1;
                if ($ed === $sd) return 0;
                return 0;
            }
            return 0;
        }

        if ($base >= 1) return $base;
        return 0;
    }

    private function calcOverlapMonths(string $aStart, string $aEnd, string $bStart, string $bEnd): int
    {
        $start = max($aStart, $bStart);
        $end   = min($aEnd, $bEnd);

        if ($start > $end) return 0;
        return $this->countMonths($start, $end);
    }

    private function buildPurchaseDate(array $car): ?string
    {
        $y = (int)($car['purchase_year'] ?? 0);
        $m = (int)($car['purchase_month'] ?? 0);
        $d = (int)($car['purchase_day'] ?? 0);

        if ($y <= 0 && $m <= 0 && $d <= 0) {
            return null;
        }

        if ($y <= 0 || $m <= 0 || $d <= 0) {
            return null;
        }

        $ymd = sprintf('%04d-%02d-%02d', $y, $m, $d);
        return $this->isYmd($ymd) ? $ymd : null;
    }

    private function resolveLeaseEffectiveEndDate(array $lease): ?string
    {
        $status = trim((string)($lease['status'] ?? ''));
        $leaseEnd = trim((string)($lease['lease_end_date'] ?? ''));

        if (!in_array($status, ['scheduled', 'active', 'ended', 'canceled'], true)) {
            return null;
        }

        if ($status === 'ended') {
            $ended = $this->toDateOnly($lease['ended_at'] ?? '');
            return $this->isYmd($ended) ? $ended : null;
        }

        if ($status === 'canceled') {
            $canceled = $this->toDateOnly($lease['canceled_at'] ?? '');
            return $this->isYmd($canceled) ? $canceled : null;
        }

        return $this->isYmd($leaseEnd) ? $leaseEnd : null;
    }

    // ============================================================
    // Core aggregation
    // ============================================================
    /**
     * @return array<int, array<string,mixed>>
     */
    private function collectRows(string $from, string $to, string $mode): array
    {
        $pdo = Db::pdo();
        $cfg = require __DIR__ . '/../config.php';

        $optsCars = $cfg['options']['cars'] ?? [];
        $makerLabels = $optsCars['maker'] ?? [];
        $modelFlat   = $optsCars['car_models_flat'] ?? [];
        $statusLabels = $optsCars['status_code'] ?? [];

        // cars
        $stCars = $pdo->prepare("
            SELECT
                id,
                vehicle_number,
                maker,
                car_model,
                status_code,
                purchase_year,
                purchase_month,
                purchase_day,
                purchase_price,
                recycling_cost,
                purchase_costs,
                car_tax,
                car_insurance_premium
            FROM cars
            WHERE deleted_at IS NULL
            ORDER BY id DESC
        ");
        $stCars->execute();
        $cars = $stCars->fetchAll() ?: [];

        if (empty($cars)) return [];

        $carIds = array_map(static fn($r) => (int)$r['id'], $cars);
        $placeholders = implode(',', array_fill(0, count($carIds), '?'));

        // leases
        $sqlLeases = "
            SELECT
                id,
                car_id,
                lease_start_date,
                lease_end_date,
                monthly_fee,
                status,
                ended_at,
                canceled_at
            FROM car_leases
            WHERE deleted_at IS NULL
              AND status IN ('scheduled','active','ended','canceled')
              AND car_id IN ($placeholders)
            ORDER BY car_id ASC, id ASC
        ";
        $stLeases = $pdo->prepare($sqlLeases);
        foreach ($carIds as $i => $cid) {
            $stLeases->bindValue($i + 1, $cid, PDO::PARAM_INT);
        }
        $stLeases->execute();
        $leases = $stLeases->fetchAll() ?: [];

        $leasesByCar = [];
        foreach ($leases as $lease) {
            $cid = (int)($lease['car_id'] ?? 0);
            if ($cid <= 0) continue;
            if (!isset($leasesByCar[$cid])) $leasesByCar[$cid] = [];
            $leasesByCar[$cid][] = $lease;
        }

        $rows = [];

        foreach ($cars as $car) {
            $carId = (int)($car['id'] ?? 0);
            $carLeases = $leasesByCar[$carId] ?? [];

            $vehicleNumber = (string)($car['vehicle_number'] ?? '');

            $makerKey = (string)($car['maker'] ?? '');
            $makerText = $makerKey !== '' ? (string)($makerLabels[$makerKey] ?? $makerKey) : '';

            $modelKey = (string)($car['car_model'] ?? '');
            $modelText = $modelKey !== '' ? (string)($modelFlat[$modelKey] ?? $modelKey) : '';

            $statusCode = (int)($car['status_code'] ?? 0);
            $statusText = (string)($statusLabels[$statusCode] ?? (string)$statusCode);

            $purchaseDate = $this->buildPurchaseDate($car);
            $warnings = [];

            // ----------------------------------------------------
            // Revenue
            // ----------------------------------------------------
            $revenue = 0.0;
            $leaseCount = 0;

            foreach ($carLeases as $lease) {
                $leaseStart = trim((string)($lease['lease_start_date'] ?? ''));
                $leaseEndEff = $this->resolveLeaseEffectiveEndDate($lease);

                if (!$this->isYmd($leaseStart) || !$leaseEndEff || !$this->isYmd($leaseEndEff)) {
                    continue;
                }
                if ($leaseStart > $leaseEndEff) {
                    continue;
                }

                $months = $this->calcOverlapMonths($leaseStart, $leaseEndEff, $from, $to);
                if ($months <= 0) continue;

                $fee = (int)($lease['monthly_fee'] ?? 0);
                $revenue += ($fee * $months);
                $leaseCount++;
            }

            if (empty($carLeases)) {
                $warnings[] = '契約なし';
            }

            // ----------------------------------------------------
            // Expense
            // ----------------------------------------------------
            $purchasePrice   = $this->toIntOrZero($car['purchase_price'] ?? 0);
            $recyclingCost   = $this->toIntOrZero($car['recycling_cost'] ?? 0);
            $purchaseCosts   = $this->toIntOrZero($car['purchase_costs'] ?? 0);
            $carTax          = $this->toIntOrZero($car['car_tax'] ?? 0);
            $insurance       = $this->toIntOrZero($car['car_insurance_premium'] ?? 0);

            $costBasis = $purchasePrice + $recyclingCost + $purchaseCosts + $carTax + $insurance;

            $elapsedMonths = 0;
            $periodCostMonths = 0;

            $purchasePart = 0.0;
            $recyclingPart = 0.0;
            $purchaseCostsPart = 0.0;
            $taxPart = 0.0;
            $insurancePart = 0.0;
            $expense = 0.0;

            if ($purchaseDate === null) {
                $warnings[] = '購入日未設定';
            } else {
                if (strtotime($to) >= strtotime($purchaseDate)) {
                    $elapsedMonths = $this->countMonths($purchaseDate, $to);
                    if ($elapsedMonths <= 0) $elapsedMonths = 1;

                    // 集計対象月数（購入日以降のみ）
                    $periodCostMonths = $this->calcOverlapMonths($purchaseDate, $to, $from, $to);

                    if ($periodCostMonths > 0) {
                        // 購入系は 60ヶ月按分
                        $purchasePart      = ($purchasePrice   / 60) * $periodCostMonths;
                        $recyclingPart     = ($recyclingCost   / 60) * $periodCostMonths;
                        $purchaseCostsPart = ($purchaseCosts   / 60) * $periodCostMonths;

                        // 税・保険は 12ヶ月按分
                        $taxPart           = ($carTax          / 12) * $periodCostMonths;
                        $insurancePart     = ($insurance       / 12) * $periodCostMonths;

                        $expense = $purchasePart + $recyclingPart + $purchaseCostsPart + $taxPart + $insurancePart;
                    }
                } else {
                    $warnings[] = '購入日が集計期間後';
                }
            }

            if ($costBasis <= 0) {
                $warnings[] = '経費原資0';
            }

            $profit = $revenue - $expense;

            $row = [
                'car_id'                => $carId,
                'vehicle_number'        => $vehicleNumber,
                'maker'                 => $makerText,
                'car_model'             => $modelText,
                'status_text'           => $statusText,
                'purchase_date'         => $purchaseDate,
                'elapsed_months'        => (int)$elapsedMonths,
                'period_cost_months'    => (int)$periodCostMonths,
                'lease_count'           => (int)$leaseCount,

                'revenue'               => (int)round($revenue),
                'expense'               => (int)round($expense),
                'profit'                => (int)round($profit),

                'purchase_part'         => (int)round($purchasePart),
                'recycling_part'        => (int)round($recyclingPart),
                'purchase_costs_part'   => (int)round($purchaseCostsPart),
                'tax_part'              => (int)round($taxPart),
                'insurance_part'        => (int)round($insurancePart),

                'warnings'              => $warnings,
            ];

            if ($mode === 'revenue' && $row['revenue'] <= 0) {
                continue;
            }
            if ($mode === 'deficit' && $row['profit'] >= 0) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array<string,int>
     */
    private function buildSummary(array $rows): array
    {
        $summary = [
            'revenue_total'          => 0,
            'expense_total'          => 0,
            'profit_total'           => 0,
            'car_count'              => 0,
            'revenue_car_count'      => 0,
            'deficit_car_count'      => 0,

            'purchase_total'         => 0,
            'recycling_total'        => 0,
            'purchase_costs_total'   => 0,
            'tax_total'              => 0,
            'insurance_total'        => 0,
        ];

        foreach ($rows as $row) {
            $summary['car_count']            += 1;
            $summary['revenue_total']        += (int)($row['revenue'] ?? 0);
            $summary['expense_total']        += (int)($row['expense'] ?? 0);
            $summary['profit_total']         += (int)($row['profit'] ?? 0);

            $summary['purchase_total']       += (int)($row['purchase_part'] ?? 0);
            $summary['recycling_total']      += (int)($row['recycling_part'] ?? 0);
            $summary['purchase_costs_total'] += (int)($row['purchase_costs_part'] ?? 0);
            $summary['tax_total']            += (int)($row['tax_part'] ?? 0);
            $summary['insurance_total']      += (int)($row['insurance_part'] ?? 0);

            if ((int)($row['revenue'] ?? 0) > 0) {
                $summary['revenue_car_count'] += 1;
            }
            if ((int)($row['profit'] ?? 0) < 0) {
                $summary['deficit_car_count'] += 1;
            }
        }

        return $summary;
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array<int, array<string,mixed>>
     */
    private function applyOrdering(array $rows): array
    {
        $order = $_GET['order'] ?? null;
        if (!is_array($order) || empty($order)) {
            usort($rows, function (array $a, array $b): int {
                return ($b['car_id'] ?? 0) <=> ($a['car_id'] ?? 0);
            });
            return $rows;
        }

        // columns:
        // 0 No
        // 1 車両番号
        // 2 メーカー
        // 3 車種
        // 4 状態
        // 5 購入日
        // 6 経過月数
        // 7 集計対象月数
        // 8 売上
        // 9 経費
        // 10 利益
        // 11 警告
        $colMap = [
            1  => 'vehicle_number',
            2  => 'maker',
            3  => 'car_model',
            4  => 'status_text',
            5  => 'purchase_date',
            6  => 'elapsed_months',
            7  => 'period_cost_months',
            8  => 'revenue',
            9  => 'expense',
            10 => 'profit',
            11 => 'warnings_text',
        ];

        foreach ($rows as &$r) {
            $r['warnings_text'] = implode(' / ', (array)($r['warnings'] ?? []));
        }
        unset($r);

        $orderSpec = [];
        $limit = 0;
        foreach ($order as $ord) {
            if ($limit >= 3) break;

            $colIdx = isset($ord['column']) ? (int)$ord['column'] : -1;
            if (!isset($colMap[$colIdx])) continue;

            $dir = strtolower((string)($ord['dir'] ?? 'asc'));
            $dir = ($dir === 'desc') ? 'desc' : 'asc';

            $orderSpec[] = ['key' => $colMap[$colIdx], 'dir' => $dir];
            $limit++;
        }

        if (empty($orderSpec)) {
            usort($rows, function (array $a, array $b): int {
                return ($b['car_id'] ?? 0) <=> ($a['car_id'] ?? 0);
            });
            return $rows;
        }

        usort($rows, function (array $a, array $b) use ($orderSpec): int {
            foreach ($orderSpec as $spec) {
                $key = $spec['key'];
                $dir = $spec['dir'];

                $av = $a[$key] ?? null;
                $bv = $b[$key] ?? null;

                if (is_int($av) || is_float($av) || is_int($bv) || is_float($bv)) {
                    $cmp = ((float)$av <=> (float)$bv);
                } else {
                    $cmp = strcmp((string)$av, (string)$bv);
                }

                if ($cmp !== 0) {
                    return $dir === 'desc' ? -$cmp : $cmp;
                }
            }
            return (($b['car_id'] ?? 0) <=> ($a['car_id'] ?? 0));
        });

        return $rows;
    }

    // ============================================================
    // Page
    // ============================================================
    public function index(): void
    {
        $me = $this->guardView();
        $cfg = require __DIR__ . '/../config.php';
        $defaults = $this->defaultPeriod();

        Response::view('car_leases/profit', [
            'title'        => '車両リース収支集計',
            'me'           => $me,
            'cfg'          => $cfg,
            'defaultFrom'  => $defaults['from'],
            'defaultTo'    => $defaults['to'],
            'defaultMode'  => 'all',
        ]);
    }

    // ============================================================
    // API: summary
    // ============================================================
    public function summary(): void
    {
        $this->guardView();

        try {
            $norm = $this->normalizePeriod($_GET);
            if (!$norm['ok']) {
                Response::json([
                    'error'   => 'VALIDATION_ERROR',
                    'message' => (string)$norm['error'],
                ], 422);
                return;
            }

            $rows = $this->collectRows($norm['from'], $norm['to'], $norm['mode']);
            $summary = $this->buildSummary($rows);

            Response::json([
                'from'    => $norm['from'],
                'to'      => $norm['to'],
                'mode'    => $norm['mode'],
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => 'Unexpected error in summary endpoint.',
            ], 500);
        }
    }

    // ============================================================
    // API: datatable
    // ============================================================
    public function datatable(): void
    {
        $this->guardView();

        try {
            $norm = $this->normalizePeriod($_GET);
            if (!$norm['ok']) {
                Response::json([
                    'error'   => 'VALIDATION_ERROR',
                    'message' => (string)$norm['error'],
                ], 422);
                return;
            }

            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));

            $len = isset($_GET['length']) ? (int)$_GET['length'] : 200;
            $len = max(1, min(200, $len));

            // total = 全車両件数（mode 無視）
            $pdo = Db::pdo();
            $total = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL")->fetchColumn();

            $rows = $this->collectRows($norm['from'], $norm['to'], $norm['mode']);
            $filtered = count($rows);

            $rows = $this->applyOrdering($rows);
            $slice = array_slice($rows, $start, $len);

            $data = [];
            $i = 0;

            foreach ($slice as $row) {
                $i++;
                $no = $start + $i;

                $warningsText = implode(' / ', (array)($row['warnings'] ?? []));
                $purchaseDate = (string)($row['purchase_date'] ?? '');

                $data[] = [
                    (int)$no,
                    htmlspecialchars((string)($row['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($row['maker'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($row['car_model'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($row['status_text'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($purchaseDate, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)((int)($row['elapsed_months'] ?? 0)), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)((int)($row['period_cost_months'] ?? 0)), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars(number_format((int)($row['revenue'] ?? 0)) . ' 円', ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars(number_format((int)($row['expense'] ?? 0)) . ' 円', ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars(number_format((int)($row['profit'] ?? 0)) . ' 円', ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($warningsText, ENT_QUOTES, 'UTF-8'),
                ];
            }

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
}