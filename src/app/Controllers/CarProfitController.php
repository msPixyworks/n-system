<?php

/**
 * CarProfitController.php
 * ============================================================
 * 車両 収支画面
 *
 * 役割:
 * - 車両ごとの収支（年度収支 / 累積収支）を表示する
 *
 * 収入:
 * - リース料金合計（car_leases）
 * - 月額固定、日割りなし
 * - 月数カウントは確定仕様を適用（「日」は基本見ない）
 *
 * 支出:
 * - 車両購入代金（cars.purchase_price）
 * - 通算の税金・保険料・経費
 *   - 当年度: cars の現在値
 *   - 過去年度: car_fy_costs の年度別値
 *
 * 方針:
 * - 収支はDBに保存しない（表示時集計）
 * - 年度の区切りは config.php の fiscal_year に従う
 */

class CarProfitController
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
    // Fiscal Year helpers
    // ============================================================
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

    private function currentFiscalYear(): int
    {
        $today = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
        return $this->fiscalYearOf($today);
    }

    /**
     * 年度の開始日/終了日を算出
     * @return array{start:string,end:string}
     */
    private function fiscalYearRange(int $fy): array
    {
        $cfg = $this->fyConfig();
        $startM = (int)$cfg['start_month'];
        $startD = (int)$cfg['start_day'];

        $start = sprintf('%04d-%02d-%02d', $fy, $startM, $startD);

        $nextStart = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $fy + 1, $startM, $startD), new DateTimeZone('Asia/Tokyo'));
        if (!$nextStart) {
            return ['start' => $start, 'end' => sprintf('%04d-03-31', $fy + 1)];
        }
        $nextStart->modify('-1 day');
        $end = $nextStart->format('Y-m-d');

        return ['start' => $start, 'end' => $end];
    }

    // ============================================================
    // 月数カウント（確定仕様）
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
            // 同月
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

    // ============================================================
    // Page
    // ============================================================
    public function show($carId): void
    {
        $me = $this->guardView();
        $carId = (int)$carId;

        $pdo = Db::pdo();

        // 車両
        $stCar = $pdo->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $stCar->execute([':i' => $carId]);
        $car = $stCar->fetch();
        if (!$car) {
            Response::notFound();
        }

        $currentFy = $this->currentFiscalYear();

        $fy = isset($_GET['fy']) ? (int)$_GET['fy'] : $currentFy;
        if ($fy <= 0) $fy = $currentFy;

        $range = $this->fiscalYearRange($fy);
        $fyStart = $range['start'];
        $fyEnd   = $range['end'];

        // ------------------------------------------------------------
        // 収入（年度内）
        // - その年度期間と重なるリースを対象に、重なり期間で月数カウント
        // ------------------------------------------------------------
        $stLeases = $pdo->prepare("
            SELECT id, car_id, lease_start_date, lease_end_date, monthly_fee, status, ended_at, canceled_at
            FROM car_leases
            WHERE car_id = :cid
              AND deleted_at IS NULL
        ");
        $stLeases->execute([':cid' => $carId]);
        $leases = $stLeases->fetchAll() ?: [];

        $incomeDetails = [];
        $incomeTotal = 0;

        foreach ($leases as $l) {
            $ls = (string)($l['lease_start_date'] ?? '');
            $le = (string)($l['lease_end_date'] ?? '');
            if ($ls === '' || $le === '') continue;

            // 実終了日がある場合は、表示上はそちらを優先するが、
            // 収支計算は「リース期間の実体」に合わせたいので、
            // ended_at/canceled_at があるならその日付（DATE）を effective_end として使う
            $effectiveEnd = $le;
            if (!empty($l['ended_at'])) {
                $effectiveEnd = substr((string)$l['ended_at'], 0, 10);
            } elseif (!empty($l['canceled_at'])) {
                $effectiveEnd = substr((string)$l['canceled_at'], 0, 10);
            }

            // 年度期間との重なり（最大で [fyStart, fyEnd] に切る）
            $overlapStart = max($ls, $fyStart);
            $overlapEnd   = min($effectiveEnd, $fyEnd);

            if ($overlapStart > $overlapEnd) continue;

            $months = $this->countMonths($overlapStart, $overlapEnd);
            if ($months <= 0) continue;

            $fee = (int)($l['monthly_fee'] ?? 0);
            $amount = $fee * $months;

            $incomeTotal += $amount;

            $incomeDetails[] = [
                'lease_id'       => (int)$l['id'],
                'lease_start'    => $ls,
                'lease_end'      => $le,
                'effective_end'  => $effectiveEnd,
                'overlap_start'  => $overlapStart,
                'overlap_end'    => $overlapEnd,
                'months'         => $months,
                'monthly_fee'    => $fee,
                'amount'         => $amount,
                'status'         => (string)($l['status'] ?? ''),
            ];
        }

        // ------------------------------------------------------------
        // 支出（年度内）
        // ------------------------------------------------------------
        // 購入代金は「累積の投資」として別枠
        $purchasePrice = (int)($car['purchase_price'] ?? 0);

        $costTax = 0;
        $costIns = 0;
        $costExp = 0;
        $costSource = 'cars';
        $costNote = '';

        if ($fy === $currentFy) {
            // 当年度：cars の現在値
            $costTax = (int)($car['car_tax'] ?? 0);
            $costIns = (int)($car['car_insurance_premium'] ?? 0);
            $costExp = (int)($car['total_expenses'] ?? 0);
            $costSource = 'cars';
        } else {
            // 過去年度：car_fy_costs
            $stCost = $pdo->prepare("
                SELECT *
                FROM car_fy_costs
                WHERE car_id = :cid AND fy = :fy AND deleted_at IS NULL
                LIMIT 1
            ");
            $stCost->execute([':cid' => $carId, ':fy' => $fy]);
            $row = $stCost->fetch();

            if ($row) {
                $costTax = (int)($row['tax_amount'] ?? 0);
                $costIns = (int)($row['insurance_amount'] ?? 0);
                $costExp = (int)($row['expense_amount'] ?? 0);
                $costSource = 'car_fy_costs';
            } else {
                // 無い場合は0扱い（表示側で注意を出せるように）
                $costSource = 'missing';
                $costNote = 'この年度の年度別コストが未登録です。';
            }
        }

        $costTotal = $costTax + $costIns + $costExp;

        // 年度収支（購入代金は含めないのが一般的）
        $profitFy = $incomeTotal - $costTotal;

        // ------------------------------------------------------------
        // 累積収支（簡易：全期間の収入 -（購入代金 + 過去年度コスト + 当年度コスト）
        // ※厳密には「年度別コスト未登録」年度をどう扱うかでブレるため、
        //    ここでは “見える範囲での累積” として計算する。
        // ------------------------------------------------------------
        $incomeAll = 0;
        foreach ($leases as $l) {
            $ls = (string)($l['lease_start_date'] ?? '');
            $le = (string)($l['lease_end_date'] ?? '');
            if ($ls === '' || $le === '') continue;

            $effectiveEnd = $le;
            if (!empty($l['ended_at'])) {
                $effectiveEnd = substr((string)$l['ended_at'], 0, 10);
            } elseif (!empty($l['canceled_at'])) {
                $effectiveEnd = substr((string)$l['canceled_at'], 0, 10);
            }

            $months = $this->countMonths($ls, $effectiveEnd);
            if ($months <= 0) continue;

            $fee = (int)($l['monthly_fee'] ?? 0);
            $incomeAll += ($fee * $months);
        }

        // 累積コスト（過去年度は car_fy_costs を合計、当年度は cars）
        $stSum = $pdo->prepare("
            SELECT
              COALESCE(SUM(COALESCE(tax_amount,0)),0) AS tax_sum,
              COALESCE(SUM(COALESCE(insurance_amount,0)),0) AS ins_sum,
              COALESCE(SUM(COALESCE(expense_amount,0)),0) AS exp_sum
            FROM car_fy_costs
            WHERE car_id = :cid
              AND deleted_at IS NULL
        ");
        $stSum->execute([':cid' => $carId]);
        $sumRow = $stSum->fetch() ?: ['tax_sum'=>0,'ins_sum'=>0,'exp_sum'=>0];

        $pastTax = (int)($sumRow['tax_sum'] ?? 0);
        $pastIns = (int)($sumRow['ins_sum'] ?? 0);
        $pastExp = (int)($sumRow['exp_sum'] ?? 0);

        $currTax = (int)($car['car_tax'] ?? 0);
        $currIns = (int)($car['car_insurance_premium'] ?? 0);
        $currExp = (int)($car['total_expenses'] ?? 0);

        $costAll = $purchasePrice + $pastTax + $pastIns + $pastExp + $currTax + $currIns + $currExp;
        $profitAll = $incomeAll - $costAll;

        $cfg = require __DIR__ . '/../config.php';

        Response::view('car_profits/show', [
            'title'         => '車両収支',
            'me'            => $me,
            'cfg'           => $cfg,
            'car'           => $car,

            'fy'            => $fy,
            'fyStart'       => $fyStart,
            'fyEnd'         => $fyEnd,

            'incomeTotal'   => $incomeTotal,
            'incomeDetails' => $incomeDetails,

            'costSource'    => $costSource,
            'costNote'      => $costNote,
            'costTax'       => $costTax,
            'costIns'       => $costIns,
            'costExp'       => $costExp,
            'costTotal'     => $costTotal,

            'purchasePrice' => $purchasePrice,

            'profitFy'      => $profitFy,

            'incomeAll'     => $incomeAll,
            'costAll'       => $costAll,
            'profitAll'     => $profitAll,
        ]);
    }
}
