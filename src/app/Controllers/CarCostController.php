<?php

/**
 * CarCostController.php
 * ============================================================
 * 車両 年度別コスト管理
 *
 * 役割:
 * - 車両別 年度コスト一覧
 * - 過去年度の税/保険/経費 編集
 * - 年度切替時の前年度スナップショット自動生成（不足分のみ）
 *
 * 方針:
 * - 権限: Policies::guardView / guardEdit（module=car_fy_costs）
 * - 当年度の支出は cars（現在値）を参照
 * - 過去年度のみ car_fy_costs を編集対象とする
 * - 監査ログは car_fy_costs 単位で diff 記録
 */

class CarCostController
{
    /**
     * car_fy_costs 監査対象フィールド（固定）
     * - array_keys() 依存を排除し、テーブル定義に合わせて固定化する
     */
    private array $auditFields = [
        'car_id',
        'fy',
        'tax_amount',
        'insurance_amount',
        'expense_amount',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

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
        Policies::guardView($u, 'car_fy_costs');
        return $u;
    }

    private function guardEdit(): array
    {
        $u = $this->guardView();
        Policies::guardEdit($u, 'car_fy_costs');
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

    // ============================================================
    // 自動生成（前年度スナップショット）
    // ============================================================
    /**
     * 指定車両について「前年度」のスナップショットが無ければ作成する
     * - 値は cars の現在値をコピー
     *
     * 監査:
     * - create の diff を car_fy_costs の固定フィールドで記録する
     */
    private function ensurePreviousFySnapshot(int $carId, int $actorUserId): void
    {
        $pdo = Db::pdo();

        $currentFy = $this->currentFiscalYear();
        $prevFy = $currentFy - 1;

        if ($prevFy <= 0) return;

        // 既に存在するか
        $stChk = $pdo->prepare("
            SELECT id FROM car_fy_costs
            WHERE car_id = :cid AND fy = :fy AND deleted_at IS NULL
            LIMIT 1
        ");
        $stChk->execute([':cid' => $carId, ':fy' => $prevFy]);
        if ($stChk->fetch()) return;

        // cars から現在値を取得
        $stCar = $pdo->prepare("
            SELECT car_tax, car_insurance_premium, total_expenses
            FROM cars
            WHERE id = :cid
            LIMIT 1
        ");
        $stCar->execute([':cid' => $carId]);
        $car = $stCar->fetch();
        if (!$car) return;

        // INSERT
        $stIns = $pdo->prepare("
            INSERT INTO car_fy_costs
              (car_id, fy, tax_amount, insurance_amount, expense_amount)
            VALUES
              (:cid, :fy, :tax, :ins, :exp)
        ");
        $stIns->execute([
            ':cid' => $carId,
            ':fy'  => $prevFy,
            ':tax' => $car['car_tax'],
            ':ins' => $car['car_insurance_premium'],
            ':exp' => $car['total_expenses'],
        ]);

        // 監査（固定フィールドでdiff）
        $newId = (int)$pdo->lastInsertId();
        $stNew = $pdo->prepare("SELECT * FROM car_fy_costs WHERE id = :i");
        $stNew->execute([':i' => $newId]);
        $new = $stNew->fetch() ?: [];

        $diff = Audit::diff([], $new, $this->auditFields);
        Audit::log('car_fy_costs', 'CarFyCost', $newId, 'create', $actorUserId, $diff);
    }

    // ============================================================
    // Pages
    // ============================================================
    /**
     * 車両別 年度コスト一覧
     */
    public function index($carId): void
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

        // 前年度スナップショット自動生成（不足分のみ）
        $actorId = (int)($me['id'] ?? 0);
        $this->ensurePreviousFySnapshot($carId, $actorId);

        $currentFy = $this->currentFiscalYear();

        // 過去年度コスト一覧
        $st = $pdo->prepare("
            SELECT *
            FROM car_fy_costs
            WHERE car_id = :cid
              AND deleted_at IS NULL
            ORDER BY fy DESC
        ");
        $st->execute([':cid' => $carId]);
        $rows = $st->fetchAll() ?: [];

        $cfg = require __DIR__ . '/../config.php';

        Response::view('car_costs/index', [
            'title'      => '年度別コスト',
            'me'         => $me,
            'cfg'        => $cfg,
            'car'        => $car,
            'currentFy'  => $currentFy,
            'rows'       => $rows,
        ]);
    }

    // ============================================================
    // Edit (過去年度のみ)
    // ============================================================
    public function edit($carId, $fy): void
    {
        $me = $this->guardEdit();
        $carId = (int)$carId;
        $fy = (int)$fy;

        $currentFy = $this->currentFiscalYear();
        if ($fy >= $currentFy) {
            Response::forbidden();
        }

        $pdo = Db::pdo();

        // レコード
        $st = $pdo->prepare("
            SELECT *
            FROM car_fy_costs
            WHERE car_id = :cid AND fy = :fy AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([':cid' => $carId, ':fy' => $fy]);
        $item = $st->fetch();
        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';

        Response::view('car_costs/form', [
            'title' => '年度別コスト編集',
            'me'    => $me,
            'cfg'   => $cfg,
            'carId' => $carId,
            'fy'    => $fy,
            'item'  => $item,
            'errors'=> [],
            'old'   => [],
        ]);
    }

    public function update($carId, $fy): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $carId = (int)$carId;
        $fy = (int)$fy;

        $currentFy = $this->currentFiscalYear();
        if ($fy >= $currentFy) {
            Response::forbidden();
        }

        $pdo = Db::pdo();

        $stOld = $pdo->prepare("
            SELECT *
            FROM car_fy_costs
            WHERE car_id = :cid AND fy = :fy AND deleted_at IS NULL
            LIMIT 1
        ");
        $stOld->execute([':cid' => $carId, ':fy' => $fy]);
        $old = $stOld->fetch();
        if (!$old) {
            Response::notFound();
        }

        $tax = (int)preg_replace('/[^0-9]/', '', (string)($_POST['tax_amount'] ?? ''));
        $ins = (int)preg_replace('/[^0-9]/', '', (string)($_POST['insurance_amount'] ?? ''));
        $exp = (int)preg_replace('/[^0-9]/', '', (string)($_POST['expense_amount'] ?? ''));
        $notes = $_POST['notes'] ?? null;

        $pdo->beginTransaction();

        try {
            $pdo->prepare("
                UPDATE car_fy_costs
                SET tax_amount = :tax,
                    insurance_amount = :ins,
                    expense_amount = :exp,
                    notes = :notes
                WHERE id = :id
            ")->execute([
                ':tax'   => $tax ?: null,
                ':ins'   => $ins ?: null,
                ':exp'   => $exp ?: null,
                ':notes' => $notes,
                ':id'    => (int)$old['id'],
            ]);

            $pdo->commit();

            $stNew = $pdo->prepare("SELECT * FROM car_fy_costs WHERE id = :i");
            $stNew->execute([':i' => (int)$old['id']]);
            $new = $stNew->fetch() ?: [];

            $diff = Audit::diff($old, $new, $this->auditFields);
            if (!empty($diff)) {
                Audit::log(
                    'car_fy_costs',
                    'CarFyCost',
                    (int)$old['id'],
                    'update',
                    (int)$me['id'],
                    $diff
                );
            }

            Response::redirect('/cars/' . $carId . '/fy_costs');
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('car_costs/form', [
                'title'  => '年度別コスト編集',
                'me'     => $me,
                'cfg'    => require __DIR__ . '/../config.php',
                'carId'  => $carId,
                'fy'     => $fy,
                'item'   => $old,
                'errors' => ['__global' => ['更新に失敗しました。']],
                'old'    => $_POST,
            ]);
        }
    }
}
