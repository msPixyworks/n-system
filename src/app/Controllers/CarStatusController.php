<?php

/**
 * CarStatusController.php
 * ============================================================
 * 役割:
 * - 車両の通常状態変更
 *
 * 対応状態:
 * - 在庫 → 代車
 * - 代車 → 在庫
 * - 在庫 → 廃車
 * - 在庫 → お客様所有（販売済）
 *
 * 設計:
 * ------------------------------------------------------------
 * cars.status_code
 *   - 画面表示上の「現在状態」
 *   - 1=在庫
 *   - 2=リース中
 *   - 3=お客様所有（販売済）
 *   - 4=代車
 *   - 5=廃車
 *   - 6=リース予定
 *
 * cars.manual_status_code
 *   - リース状態ではない通常状態の基底値
 *   - 1=在庫
 *   - 3=お客様所有（販売済）
 *   - 4=代車
 *   - 5=廃車
 *
 * 最終状態決定ルール:
 * ------------------------------------------------------------
 * 1. active リースがある      → status_code = 2
 * 2. active が無く scheduled → status_code = 6
 * 3. それ以外                → status_code = manual_status_code
 *
 * 制御ルール:
 * ------------------------------------------------------------
 * - active がある車両は、代車 / 廃車 / 販売済 へ変更不可
 * - scheduled がある車両は、廃車 / 販売済 へ変更不可
 * - scheduled がある車両でも、開始日前だけ代車化は可
 * - 代車 → 在庫 は、manual_status_code = 4 のときのみ可
 * - 在庫 → 代車/廃車/販売済 は、manual_status_code = 1 のときのみ可
 *
 * 保存先:
 * ------------------------------------------------------------
 * - car_status_histories:
 *   状態変更履歴
 * - car_sales:
 *   販売済にしたときの販売情報
 *
 * 監査:
 * ------------------------------------------------------------
 * - car_status_histories 作成時に Audit::log
 * - car_sales 作成時に Audit::log
 *
 * 改善点（今回）:
 * ------------------------------------------------------------
 * - 日付妥当性を checkdate() で厳密化
 * - 代車先 / 販売先の実在チェックを追加
 * - 例外メッセージの生表示を避ける
 * - getLeaseStateSummary / rebuildCarStatus を同一PDOで処理
 * - car_sales 重複登録の競合対策として car_sales 側もロック
 * - 監査ログも業務更新と同一トランザクション内で実行
 *
 * 方針:
 * ------------------------------------------------------------
 * - K-Core 準拠
 * - 権限は cars モジュールに合わせる
 * - モーダル選択は既存の lessee picker を流用
 * - partner/customer は必須にしない
 *
 * 注意:
 * ------------------------------------------------------------
 * - DB側でも car_sales の重複防止制約を別途推奨
 * - View側では old/errors を必ず htmlspecialchars して描画すること
 */

class CarStatusController
{
    /**
     * 車両状態変更履歴の監査対象
     */
    private array $historyAuditFields = [
        'car_id',
        'from_status_code',
        'to_status_code',
        'changed_at',
        'partner_type',
        'partner_id',
        'partner_name',
        'note',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 販売情報の監査対象
     */
    private array $saleAuditFields = [
        'car_id',
        'sold_at',
        'customer_type',
        'customer_id',
        'customer_name',
        'sale_price',
        'tax_amount',
        'recycle_fee',
        'other_fee',
        'total_amount',
        'notes',
        'created_by',
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

        Policies::guardView($u, 'cars');
        return $u;
    }

    private function guardEdit(): array
    {
        $u = $this->guardView();
        Policies::guardEdit($u, 'cars');
        return $u;
    }

    // ============================================================
    // Common helpers
    // ============================================================
    private function nowJst(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');
    }

    private function todayJstYmd(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
    }

    /**
     * YYYY-MM-DD 形式 + 実在日付チェック
     */
    private function isYmd(string $s): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return false;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $s));
        return checkdate($m, $d, $y);
    }

    /**
     * ID専用の安全な数値化
     * - 数字のみ許可
     * - 空は 0
     * - 不正文字混在は -1 を返す
     */
    private function parsePositiveIntOrInvalid($x): int
    {
        $raw = trim((string)$x);
        if ($raw === '') {
            return 0;
        }
        if (!preg_match('/^\d+$/', $raw)) {
            return -1;
        }
        return (int)$raw;
    }

    /**
     * 金額専用パーサ
     * - 空は 0
     * - カンマ除去のみ許可
     * - 負数 / 小数 / 文字混在は validation error
     */
    private function parseMoney($x, Validation $v, string $field, string $label): int
    {
        $raw = trim((string)$x);
        if ($raw === '') {
            return 0;
        }

        $normalized = str_replace(',', '', $raw);
        if (!preg_match('/^\d+$/', $normalized)) {
            $v->add($field, $label . 'は0以上の整数で入力してください。');
            return 0;
        }

        return (int)$normalized;
    }

    /**
     * 想定外エラーの画面表示用メッセージ
     */
    private function genericFailureMessage(): string
    {
        return '処理に失敗しました。入力内容をご確認のうえ、再度お試しください。';
    }

    /**
     * 想定外例外ログ
     */
    private function logUnexpected(Throwable $e): void
    {
        error_log(sprintf(
            '[CarStatusController] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        error_log($e->getTraceAsString());
    }

    /**
     * 対象車両取得
     * - 論理削除済みは対象外
     */
    private function findCar(int $id): array
    {
        $pdo = Db::pdo();

        $st = $pdo->prepare("
            SELECT *
            FROM cars
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        $car = $st->fetch();
        if (!$car) {
            Response::notFound();
        }

        return (array)$car;
    }

    /**
     * 対象車両を FOR UPDATE で取得
     */
    private function lockCar(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("
            SELECT *
            FROM cars
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        $car = $st->fetch();
        if (!$car) {
            throw new RuntimeException('車両が見つかりません。');
        }

        return (array)$car;
    }

    /**
     * car_sales を車両単位でロック確認
     * - 販売情報重複登録の競合を少しでも抑える
     * - 既存があればその id を返す
     */
    private function lockExistingSaleId(PDO $pdo, int $carId): int
    {
        $st = $pdo->prepare("
            SELECT id
            FROM car_sales
            WHERE car_id = :cid
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $st->bindValue(':cid', $carId, PDO::PARAM_INT);
        $st->execute();

        return (int)($st->fetchColumn() ?: 0);
    }

    /**
     * office/personal の名称解決
     */
    private function resolvePartnerName(PDO $pdo, ?string $type, ?int $id): ?string
    {
        $type = trim((string)$type);
        $id   = (int)$id;

        if ($type === '' || $id <= 0) {
            return null;
        }

        if ($type === 'office') {
            $st = $pdo->prepare("
                SELECT name
                FROM office_customers
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
        } elseif ($type === 'personal') {
            $st = $pdo->prepare("
                SELECT name
                FROM personal_customers
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
        } else {
            return null;
        }

        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        $name = $st->fetchColumn();
        return ($name !== false) ? (string)$name : null;
    }

    /**
     * type/id が指定された参照先の存在確認
     */
    private function validatePartnerReference(
        Validation $v,
        ?string $type,
        ?int $id,
        string $typeField,
        string $idField,
        string $labelPrefix
    ): ?string {
        $type = trim((string)$type);
        $id   = (int)$id;

        if ($type === '' && $id <= 0) {
            return null;
        }

        if ($type === '' && $id > 0) {
            $v->add($typeField, $labelPrefix . '種別を正しく指定してください。');
            return null;
        }

        if (!in_array($type, ['office', 'personal'], true)) {
            $v->add($typeField, $labelPrefix . '種別が不正です。');
            return null;
        }

        if ($id <= 0) {
            $v->add($idField, $labelPrefix . 'IDを正しく指定してください。');
            return null;
        }

        $pdo  = Db::pdo();
        $name = $this->resolvePartnerName($pdo, $type, $id);
        if ($name === null) {
            $v->add($idField, '指定した' . $labelPrefix . 'が見つかりません。');
        }

        return $name;
    }

    /**
     * リース状態サマリ
     * - 同一トランザクション / 同一接続で読むため PDO を受け取る
     */
    private function getLeaseStateSummary(PDO $pdo, int $carId): array
    {
        $today = $this->todayJstYmd();

        $st = $pdo->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_count,
              COALESCE(SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END), 0) AS scheduled_count,
              COALESCE(SUM(CASE WHEN status = 'scheduled' AND lease_start_date <= :today_due THEN 1 ELSE 0 END), 0) AS due_scheduled_count,
              COALESCE(SUM(CASE WHEN status = 'scheduled' AND lease_start_date > :today_future THEN 1 ELSE 0 END), 0) AS future_scheduled_count
            FROM car_leases
            WHERE car_id = :cid
              AND deleted_at IS NULL
        ");
        $st->execute([
            ':today_due'    => $today,
            ':today_future' => $today,
            ':cid'          => $carId,
        ]);
        $row = $st->fetch() ?: [];

        $stActive = $pdo->prepare("
            SELECT id
            FROM car_leases
            WHERE car_id = :cid
              AND deleted_at IS NULL
              AND status = 'active'
            ORDER BY lease_start_date ASC, id ASC
            LIMIT 1
        ");
        $stActive->execute([':cid' => $carId]);
        $activeLeaseId = (int)($stActive->fetchColumn() ?: 0);

        $stScheduled = $pdo->prepare("
            SELECT id
            FROM car_leases
            WHERE car_id = :cid
              AND deleted_at IS NULL
              AND status = 'scheduled'
            ORDER BY lease_start_date ASC, id ASC
            LIMIT 1
        ");
        $stScheduled->execute([':cid' => $carId]);
        $earliestScheduledId = (int)($stScheduled->fetchColumn() ?: 0);

        return [
            'has_active'            => ((int)($row['active_count'] ?? 0) > 0),
            'has_scheduled'         => ((int)($row['scheduled_count'] ?? 0) > 0),
            'has_due_scheduled'     => ((int)($row['due_scheduled_count'] ?? 0) > 0),
            'has_future_scheduled'  => ((int)($row['future_scheduled_count'] ?? 0) > 0),
            'active_lease_id'       => $activeLeaseId,
            'earliest_scheduled_id' => $earliestScheduledId,
        ];
    }

    /**
     * cars.status_code を再構築
     * - 同一PDO / 同一トランザクションで実行
     */
    private function rebuildCarStatus(PDO $pdo, int $carId): void
    {
        $leaseSummary = $this->getLeaseStateSummary($pdo, $carId);

        if ($leaseSummary['active_lease_id'] > 0) {
            $st = $pdo->prepare("
                UPDATE cars
                SET current_lease_id = :lease_id,
                    status_code = 2
                WHERE id = :car_id
            ");
            $st->bindValue(':lease_id', (int)$leaseSummary['active_lease_id'], PDO::PARAM_INT);
            $st->bindValue(':car_id', $carId, PDO::PARAM_INT);
            $st->execute();
            return;
        }

        if ($leaseSummary['earliest_scheduled_id'] > 0) {
            $st = $pdo->prepare("
                UPDATE cars
                SET current_lease_id = NULL,
                    status_code = 6
                WHERE id = :car_id
            ");
            $st->bindValue(':car_id', $carId, PDO::PARAM_INT);
            $st->execute();
            return;
        }

        $stManual = $pdo->prepare("
            SELECT manual_status_code
            FROM cars
            WHERE id = :car_id
            LIMIT 1
        ");
        $stManual->bindValue(':car_id', $carId, PDO::PARAM_INT);
        $stManual->execute();
        $manualStatus = (int)($stManual->fetchColumn() ?: 1);

        if (!in_array($manualStatus, [1, 3, 4, 5], true)) {
            $manualStatus = 1;
        }

        $st = $pdo->prepare("
            UPDATE cars
            SET current_lease_id = NULL,
                status_code = :status_code
            WHERE id = :car_id
        ");
        $st->bindValue(':status_code', $manualStatus, PDO::PARAM_INT);
        $st->bindValue(':car_id', $carId, PDO::PARAM_INT);
        $st->execute();
    }

    /**
     * 変更履歴追加
     */
    private function insertStatusHistory(
        PDO $pdo,
        int $carId,
        ?int $fromStatusCode,
        int $toStatusCode,
        string $changedAt,
        ?string $partnerType,
        ?int $partnerId,
        ?string $partnerName,
        ?string $note,
        ?int $createdBy
    ): int {
        $st = $pdo->prepare("
            INSERT INTO car_status_histories
              (car_id, from_status_code, to_status_code, changed_at, partner_type, partner_id, partner_name, note, created_by)
            VALUES
              (:car_id, :from_status_code, :to_status_code, :changed_at, :partner_type, :partner_id, :partner_name, :note, :created_by)
        ");
        $st->bindValue(':car_id', $carId, PDO::PARAM_INT);
        if ($fromStatusCode === null) {
            $st->bindValue(':from_status_code', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':from_status_code', $fromStatusCode, PDO::PARAM_INT);
        }
        $st->bindValue(':to_status_code', $toStatusCode, PDO::PARAM_INT);
        $st->bindValue(':changed_at', $changedAt, PDO::PARAM_STR);
        if ($partnerType === null) {
            $st->bindValue(':partner_type', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':partner_type', $partnerType, PDO::PARAM_STR);
        }
        if ($partnerId === null) {
            $st->bindValue(':partner_id', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':partner_id', $partnerId, PDO::PARAM_INT);
        }
        if ($partnerName === null) {
            $st->bindValue(':partner_name', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':partner_name', $partnerName, PDO::PARAM_STR);
        }
        if ($note === null) {
            $st->bindValue(':note', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':note', $note, PDO::PARAM_STR);
        }
        if ($createdBy === null) {
            $st->bindValue(':created_by', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        }
        $st->execute();

        return (int)$pdo->lastInsertId();
    }

    /**
     * 販売情報追加
     */
    private function insertSale(
        PDO $pdo,
        int $carId,
        string $soldAt,
        ?string $customerType,
        ?int $customerId,
        ?string $customerName,
        int $salePrice,
        int $taxAmount,
        int $recycleFee,
        int $otherFee,
        int $totalAmount,
        ?string $notes,
        ?int $createdBy
    ): int {
        $st = $pdo->prepare("
            INSERT INTO car_sales
              (car_id, sold_at, customer_type, customer_id, customer_name, sale_price, tax_amount, recycle_fee, other_fee, total_amount, notes, created_by)
            VALUES
              (:car_id, :sold_at, :customer_type, :customer_id, :customer_name, :sale_price, :tax_amount, :recycle_fee, :other_fee, :total_amount, :notes, :created_by)
        ");
        $st->bindValue(':car_id', $carId, PDO::PARAM_INT);
        $st->bindValue(':sold_at', $soldAt, PDO::PARAM_STR);
        if ($customerType === null) {
            $st->bindValue(':customer_type', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':customer_type', $customerType, PDO::PARAM_STR);
        }
        if ($customerId === null) {
            $st->bindValue(':customer_id', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        }
        if ($customerName === null) {
            $st->bindValue(':customer_name', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':customer_name', $customerName, PDO::PARAM_STR);
        }
        $st->bindValue(':sale_price', $salePrice, PDO::PARAM_INT);
        $st->bindValue(':tax_amount', $taxAmount, PDO::PARAM_INT);
        $st->bindValue(':recycle_fee', $recycleFee, PDO::PARAM_INT);
        $st->bindValue(':other_fee', $otherFee, PDO::PARAM_INT);
        $st->bindValue(':total_amount', $totalAmount, PDO::PARAM_INT);
        if ($notes === null) {
            $st->bindValue(':notes', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':notes', $notes, PDO::PARAM_STR);
        }
        if ($createdBy === null) {
            $st->bindValue(':created_by', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        }
        $st->execute();

        return (int)$pdo->lastInsertId();
    }

    private function canMoveToLoaner(array $car, array $leaseSummary): ?string
    {
        $manual = (int)($car['manual_status_code'] ?? 1);
        if ($manual !== 1) {
            return '在庫状態の車両だけ代車へ変更できます。';
        }
        if ($leaseSummary['has_active']) {
            return 'リース中の車両は代車へ変更できません。';
        }
        if ($leaseSummary['has_due_scheduled']) {
            return '開始日到来済みのリース予定があるため、代車へ変更できません。';
        }
        return null;
    }

    /**
     * 代車 → 在庫
     * - 現仕様どおり manual_status_code=4 のみ許可
     * - scheduled / active があっても manual base を戻すこと自体は許可
     *   （最終 status_code は rebuildCarStatus() で再決定）
     */
    private function canMoveToStock(array $car): ?string
    {
        $manual = (int)($car['manual_status_code'] ?? 1);
        if ($manual !== 4) {
            return '代車状態の車両だけ在庫へ戻せます。';
        }
        return null;
    }

    private function canMoveToScrap(array $car, array $leaseSummary): ?string
    {
        $manual = (int)($car['manual_status_code'] ?? 1);
        if ($manual !== 1) {
            return '在庫状態の車両だけ廃車へ変更できます。';
        }
        if ($leaseSummary['has_active']) {
            return 'リース中の車両は廃車へ変更できません。';
        }
        if ($leaseSummary['has_scheduled']) {
            return 'リース予定の車両は廃車へ変更できません。';
        }
        return null;
    }

    private function canMoveToSold(array $car, array $leaseSummary): ?string
    {
        $manual = (int)($car['manual_status_code'] ?? 1);
        if ($manual !== 1) {
            return '在庫状態の車両だけ販売済へ変更できます。';
        }
        if ($leaseSummary['has_active']) {
            return 'リース中の車両は販売済へ変更できません。';
        }
        if ($leaseSummary['has_scheduled']) {
            return 'リース予定の車両は販売済へ変更できません。';
        }
        return null;
    }

    private function validateLoanerInput(array $in): array
    {
        $v = new Validation();

        $changedDate = trim((string)($in['changed_date'] ?? ''));
        $changedAt   = $changedDate !== '' ? ($changedDate . ' 00:00:00') : '';

        if ($changedDate === '' || !$this->isYmd($changedDate)) {
            $v->add('changed_date', '変更日を正しく入力してください。');
        }

        $partnerType = trim((string)($in['partner_type'] ?? ''));
        $partnerId   = $this->parsePositiveIntOrInvalid($in['partner_id'] ?? '');

        if ($partnerId < 0) {
            $v->add('partner_id', '代車先IDを正しく指定してください。');
            $partnerId = 0;
        }

        $partnerName = $this->validatePartnerReference(
            $v,
            $partnerType !== '' ? $partnerType : null,
            $partnerId > 0 ? $partnerId : null,
            'partner_type',
            'partner_id',
            '代車先'
        );

        $note = trim((string)($in['note'] ?? ''));

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        return [
            'ok' => true,
            'data' => [
                'changed_at'   => $changedAt,
                'partner_type' => ($partnerType !== '') ? $partnerType : null,
                'partner_id'   => ($partnerId > 0) ? $partnerId : null,
                'partner_name' => $partnerName,
                'note'         => ($note !== '') ? $note : null,
            ],
            'in' => $in,
        ];
    }

    private function validateBackToStockInput(array $in): array
    {
        $v = new Validation();

        $changedDate = trim((string)($in['changed_date'] ?? ''));
        $changedAt   = $changedDate !== '' ? ($changedDate . ' 00:00:00') : '';

        if ($changedDate === '' || !$this->isYmd($changedDate)) {
            $v->add('changed_date', '変更日を正しく入力してください。');
        }

        $note = trim((string)($in['note'] ?? ''));

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        return [
            'ok' => true,
            'data' => [
                'changed_at' => $changedAt,
                'note'       => ($note !== '') ? $note : null,
            ],
            'in' => $in,
        ];
    }

    private function validateScrapInput(array $in): array
    {
        $v = new Validation();

        $changedDate = trim((string)($in['changed_date'] ?? ''));
        $changedAt   = $changedDate !== '' ? ($changedDate . ' 00:00:00') : '';

        if ($changedDate === '' || !$this->isYmd($changedDate)) {
            $v->add('changed_date', '廃車日を正しく入力してください。');
        }

        $note = trim((string)($in['note'] ?? ''));

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        return [
            'ok' => true,
            'data' => [
                'changed_at' => $changedAt,
                'note'       => ($note !== '') ? $note : null,
            ],
            'in' => $in,
        ];
    }

    private function validateSellInput(array $in): array
    {
        $v = new Validation();

        $soldAt = trim((string)($in['sold_at'] ?? ''));
        if ($soldAt === '' || !$this->isYmd($soldAt)) {
            $v->add('sold_at', '販売日を正しく入力してください。');
        }

        $customerType = trim((string)($in['customer_type'] ?? ''));
        $customerId   = $this->parsePositiveIntOrInvalid($in['customer_id'] ?? '');

        if ($customerId < 0) {
            $v->add('customer_id', '販売先IDを正しく指定してください。');
            $customerId = 0;
        }

        $customerName = $this->validatePartnerReference(
            $v,
            $customerType !== '' ? $customerType : null,
            $customerId > 0 ? $customerId : null,
            'customer_type',
            'customer_id',
            '販売先'
        );

        $salePrice  = $this->parseMoney($in['sale_price'] ?? '', $v, 'sale_price', '販売金額');
        $taxAmount  = $this->parseMoney($in['tax_amount'] ?? '', $v, 'tax_amount', '税額');
        $recycleFee = $this->parseMoney($in['recycle_fee'] ?? '', $v, 'recycle_fee', 'リサイクル料');
        $otherFee   = $this->parseMoney($in['other_fee'] ?? '', $v, 'other_fee', 'その他費用');
        $notes      = trim((string)($in['notes'] ?? ''));

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        return [
            'ok' => true,
            'data' => [
                'sold_at'       => $soldAt,
                'customer_type' => ($customerType !== '') ? $customerType : null,
                'customer_id'   => ($customerId > 0) ? $customerId : null,
                'customer_name' => $customerName,
                'sale_price'    => $salePrice,
                'tax_amount'    => $taxAmount,
                'recycle_fee'   => $recycleFee,
                'other_fee'     => $otherFee,
                'total_amount'  => ($salePrice + $taxAmount + $recycleFee + $otherFee),
                'notes'         => ($notes !== '') ? $notes : null,
            ],
            'in' => $in,
        ];
    }

    /**
     * 監査ログ用に登録直後のレコードを取得
     */
    private function fetchOneById(PDO $pdo, string $table, int $id): array
    {
        $allow = [
            'car_status_histories',
            'car_sales',
        ];
        if (!in_array($table, $allow, true)) {
            throw new RuntimeException('取得対象テーブルが不正です。');
        }

        $sql = "SELECT * FROM {$table} WHERE id = :id LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        return (array)($st->fetch() ?: []);
    }

    // ============================================================
    // Forms
    // ============================================================
    public function loanerForm($id): void
    {
        $me  = $this->guardEdit();
        $car = $this->findCar((int)$id);

        $pdo          = Db::pdo();
        $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$car['id']);

        $error = $this->canMoveToLoaner($car, $leaseSummary);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('cars/status_loaner', [
            'title'  => '代車へ変更',
            'cfg'    => $cfg,
            'car'    => $car,
            'me'     => $me,
            'errors' => [],
            'old'    => [
                'changed_date' => $this->todayJstYmd(),
            ],
        ]);
    }

    public function backToStockForm($id): void
    {
        $me  = $this->guardEdit();
        $car = $this->findCar((int)$id);

        $error = $this->canMoveToStock($car);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('cars/status_back_to_stock', [
            'title'  => '在庫へ戻す',
            'cfg'    => $cfg,
            'car'    => $car,
            'me'     => $me,
            'errors' => [],
            'old'    => [
                'changed_date' => $this->todayJstYmd(),
            ],
        ]);
    }

    public function scrapForm($id): void
    {
        $me  = $this->guardEdit();
        $car = $this->findCar((int)$id);

        $pdo          = Db::pdo();
        $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$car['id']);

        $error = $this->canMoveToScrap($car, $leaseSummary);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('cars/status_scrap', [
            'title'  => '廃車へ変更',
            'cfg'    => $cfg,
            'car'    => $car,
            'me'     => $me,
            'errors' => [],
            'old'    => [
                'changed_date' => $this->todayJstYmd(),
            ],
        ]);
    }

    public function sellForm($id): void
    {
        $me  = $this->guardEdit();
        $car = $this->findCar((int)$id);

        $pdo          = Db::pdo();
        $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$car['id']);

        $error = $this->canMoveToSold($car, $leaseSummary);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('cars/status_sell', [
            'title'  => '販売済へ変更',
            'cfg'    => $cfg,
            'car'    => $car,
            'me'     => $me,
            'errors' => [],
            'old'    => [
                'sold_at'      => $this->todayJstYmd(),
                'sale_price'   => '',
                'tax_amount'   => '',
                'recycle_fee'  => '',
                'other_fee'    => '',
            ],
        ]);
    }

    // ============================================================
    // Actions
    // ============================================================
    public function markLoaner($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $car = $this->findCar((int)$id);

        $pdo          = Db::pdo();
        $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$car['id']);

        $error = $this->canMoveToLoaner($car, $leaseSummary);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $res = $this->validateLoanerInput($_POST);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('cars/status_loaner', [
                'title'  => '代車へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => $res['errors'],
                'old'    => $res['in'],
            ]);
            return;
        }

        $pdo->beginTransaction();

        try {
            $lockedCar     = $this->lockCar($pdo, (int)$car['id']);
            $leaseSummary  = $this->getLeaseStateSummary($pdo, (int)$lockedCar['id']);
            $error         = $this->canMoveToLoaner($lockedCar, $leaseSummary);

            if ($error !== null) {
                throw new RuntimeException($error);
            }

            // 再解決してトランザクション中の整合を取る
            $partnerType = $res['data']['partner_type'];
            $partnerId   = $res['data']['partner_id'];
            $partnerName = $this->resolvePartnerName($pdo, $partnerType, $partnerId);

            if ($partnerType !== null && $partnerId !== null && $partnerName === null) {
                throw new RuntimeException('指定した代車先が見つかりません。');
            }

            $fromStatus = (int)($lockedCar['manual_status_code'] ?? 1);

            $st = $pdo->prepare("
                UPDATE cars
                SET manual_status_code = 4,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $st->bindValue(':updated_at', $this->nowJst(), PDO::PARAM_STR);
            $st->bindValue(':id', (int)$lockedCar['id'], PDO::PARAM_INT);
            $st->execute();

            $this->rebuildCarStatus($pdo, (int)$lockedCar['id']);

            $historyId = $this->insertStatusHistory(
                $pdo,
                (int)$lockedCar['id'],
                $fromStatus,
                4,
                $res['data']['changed_at'],
                $partnerType,
                $partnerId,
                $partnerName,
                $res['data']['note'],
                (int)$me['id']
            );

            $newHistory = $this->fetchOneById($pdo, 'car_status_histories', $historyId);
            $historyDiff = Audit::diff([], $newHistory, $this->historyAuditFields);
            Audit::log('car_status_histories', 'CarStatusHistory', $historyId, 'create', (int)$me['id'], $historyDiff);

            $pdo->commit();

            Response::redirect('/cars/' . (int)$lockedCar['id']);
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Response::view('cars/status_loaner', [
                'title'  => '代車へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$e->getMessage()]],
                'old'    => $_POST,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->logUnexpected($e);

            Response::view('cars/status_loaner', [
                'title'  => '代車へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$this->genericFailureMessage()]],
                'old'    => $_POST,
            ]);
        }
    }

    public function backToStock($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $car = $this->findCar((int)$id);
        $error = $this->canMoveToStock($car);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $res = $this->validateBackToStockInput($_POST);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('cars/status_back_to_stock', [
                'title'  => '在庫へ戻す',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => $res['errors'],
                'old'    => $res['in'],
            ]);
            return;
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $lockedCar = $this->lockCar($pdo, (int)$car['id']);

            $error = $this->canMoveToStock($lockedCar);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            $fromStatus = (int)($lockedCar['manual_status_code'] ?? 1);

            $st = $pdo->prepare("
                UPDATE cars
                SET manual_status_code = 1,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $st->bindValue(':updated_at', $this->nowJst(), PDO::PARAM_STR);
            $st->bindValue(':id', (int)$lockedCar['id'], PDO::PARAM_INT);
            $st->execute();

            $this->rebuildCarStatus($pdo, (int)$lockedCar['id']);

            $historyId = $this->insertStatusHistory(
                $pdo,
                (int)$lockedCar['id'],
                $fromStatus,
                1,
                $res['data']['changed_at'],
                null,
                null,
                null,
                $res['data']['note'],
                (int)$me['id']
            );

            $newHistory = $this->fetchOneById($pdo, 'car_status_histories', $historyId);
            $historyDiff = Audit::diff([], $newHistory, $this->historyAuditFields);
            Audit::log('car_status_histories', 'CarStatusHistory', $historyId, 'create', (int)$me['id'], $historyDiff);

            $pdo->commit();

            Response::redirect('/cars/' . (int)$lockedCar['id']);
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Response::view('cars/status_back_to_stock', [
                'title'  => '在庫へ戻す',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$e->getMessage()]],
                'old'    => $_POST,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->logUnexpected($e);

            Response::view('cars/status_back_to_stock', [
                'title'  => '在庫へ戻す',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$this->genericFailureMessage()]],
                'old'    => $_POST,
            ]);
        }
    }

    public function scrap($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $car = $this->findCar((int)$id);

        $pdo          = Db::pdo();
        $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$car['id']);

        $error = $this->canMoveToScrap($car, $leaseSummary);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $res = $this->validateScrapInput($_POST);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('cars/status_scrap', [
                'title'  => '廃車へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => $res['errors'],
                'old'    => $res['in'],
            ]);
            return;
        }

        $pdo->beginTransaction();

        try {
            $lockedCar    = $this->lockCar($pdo, (int)$car['id']);
            $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$lockedCar['id']);

            $error = $this->canMoveToScrap($lockedCar, $leaseSummary);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            $fromStatus = (int)($lockedCar['manual_status_code'] ?? 1);

            $st = $pdo->prepare("
                UPDATE cars
                SET manual_status_code = 5,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $st->bindValue(':updated_at', $this->nowJst(), PDO::PARAM_STR);
            $st->bindValue(':id', (int)$lockedCar['id'], PDO::PARAM_INT);
            $st->execute();

            $this->rebuildCarStatus($pdo, (int)$lockedCar['id']);

            $historyId = $this->insertStatusHistory(
                $pdo,
                (int)$lockedCar['id'],
                $fromStatus,
                5,
                $res['data']['changed_at'],
                null,
                null,
                null,
                $res['data']['note'],
                (int)$me['id']
            );

            $newHistory = $this->fetchOneById($pdo, 'car_status_histories', $historyId);
            $historyDiff = Audit::diff([], $newHistory, $this->historyAuditFields);
            Audit::log('car_status_histories', 'CarStatusHistory', $historyId, 'create', (int)$me['id'], $historyDiff);

            $pdo->commit();

            Response::redirect('/cars/' . (int)$lockedCar['id']);
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Response::view('cars/status_scrap', [
                'title'  => '廃車へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$e->getMessage()]],
                'old'    => $_POST,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->logUnexpected($e);

            Response::view('cars/status_scrap', [
                'title'  => '廃車へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$this->genericFailureMessage()]],
                'old'    => $_POST,
            ]);
        }
    }

    public function sell($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $car = $this->findCar((int)$id);

        $pdo          = Db::pdo();
        $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$car['id']);

        $error = $this->canMoveToSold($car, $leaseSummary);
        if ($error !== null) {
            Response::fail('FORBIDDEN', $error, 403);
        }

        $res = $this->validateSellInput($_POST);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('cars/status_sell', [
                'title'  => '販売済へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => $res['errors'],
                'old'    => $res['in'],
            ]);
            return;
        }

        $pdo->beginTransaction();

        try {
            $lockedCar    = $this->lockCar($pdo, (int)$car['id']);
            $leaseSummary = $this->getLeaseStateSummary($pdo, (int)$lockedCar['id']);

            $error = $this->canMoveToSold($lockedCar, $leaseSummary);
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            // 先に既存販売情報をロック付きで確認
            $existingSaleId = $this->lockExistingSaleId($pdo, (int)$lockedCar['id']);
            if ($existingSaleId > 0) {
                throw new RuntimeException('この車両には既に販売情報が登録されています。');
            }

            $customerType = $res['data']['customer_type'];
            $customerId   = $res['data']['customer_id'];
            $customerName = $this->resolvePartnerName($pdo, $customerType, $customerId);

            if ($customerType !== null && $customerId !== null && $customerName === null) {
                throw new RuntimeException('指定した販売先が見つかりません。');
            }

            $fromStatus = (int)($lockedCar['manual_status_code'] ?? 1);

            $st = $pdo->prepare("
                UPDATE cars
                SET manual_status_code = 3,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $st->bindValue(':updated_at', $this->nowJst(), PDO::PARAM_STR);
            $st->bindValue(':id', (int)$lockedCar['id'], PDO::PARAM_INT);
            $st->execute();

            $this->rebuildCarStatus($pdo, (int)$lockedCar['id']);

            $saleId = $this->insertSale(
                $pdo,
                (int)$lockedCar['id'],
                $res['data']['sold_at'],
                $customerType,
                $customerId,
                $customerName,
                $res['data']['sale_price'],
                $res['data']['tax_amount'],
                $res['data']['recycle_fee'],
                $res['data']['other_fee'],
                $res['data']['total_amount'],
                $res['data']['notes'],
                (int)$me['id']
            );

            $historyId = $this->insertStatusHistory(
                $pdo,
                (int)$lockedCar['id'],
                $fromStatus,
                3,
                $res['data']['sold_at'] . ' 00:00:00',
                $customerType,
                $customerId,
                $customerName,
                $res['data']['notes'],
                (int)$me['id']
            );

            $newSale     = $this->fetchOneById($pdo, 'car_sales', $saleId);
            $newHistory  = $this->fetchOneById($pdo, 'car_status_histories', $historyId);
            $saleDiff    = Audit::diff([], $newSale, $this->saleAuditFields);
            $historyDiff = Audit::diff([], $newHistory, $this->historyAuditFields);

            Audit::log('car_sales', 'CarSale', $saleId, 'create', (int)$me['id'], $saleDiff);
            Audit::log('car_status_histories', 'CarStatusHistory', $historyId, 'create', (int)$me['id'], $historyDiff);

            $pdo->commit();

            Response::redirect('/cars/' . (int)$lockedCar['id']);
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Response::view('cars/status_sell', [
                'title'  => '販売済へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$e->getMessage()]],
                'old'    => $_POST,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->logUnexpected($e);

            Response::view('cars/status_sell', [
                'title'  => '販売済へ変更',
                'cfg'    => $cfg,
                'car'    => $car,
                'me'     => $me,
                'errors' => ['__global' => [$this->genericFailureMessage()]],
                'old'    => $_POST,
            ]);
        }
    }
}