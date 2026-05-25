<?php

class CarLeaseController
{
    private array $auditFields = [
        'car_id','lessee_type','lessee_id','lease_start_date','lease_end_date','monthly_fee',
        'status','ended_at','canceled_at','notes','created_at','updated_at','deleted_at',
    ];

    private function guardView(): array
    {
        $u = Auth::user();
        if (!$u) {
            if (Response::isApi()) Response::fail('UNAUTHORIZED', 'Unauthorized', 401);
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

    private function findCarById(int $carId): ?array
    {
        if ($carId <= 0) return null;
        $st = Db::pdo()->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $st->execute([':i' => $carId]);
        $car = $st->fetch();
        return $car ? (array)$car : null;
    }

    private function isYmd(string $s): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
    }

    private function toDigitsInt($x): int
    {
        $s = preg_replace('/[^0-9]/', '', (string)$x);
        return ($s === '' ? 0 : (int)$s);
    }

    private function todayJstYmd(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
    }

    /**
     * 期間重複チェック（被りだけNG / 延長も考慮）
     */
    private function hasOverlappingLease(int $carId, string $newStart, string $newEnd, ?int $selfId = null): bool
    {
        $pdo = Db::pdo();

        $params = [':cid'=>$carId, ':ns'=>$newStart, ':ne'=>$newEnd];
        $selfCond = '';
        if ($selfId !== null) {
            $selfCond = ' AND id <> :self_id ';
            $params[':self_id'] = $selfId;
        }

        $sql = "
            SELECT id
            FROM car_leases
            WHERE car_id = :cid
              AND deleted_at IS NULL
              {$selfCond}
              AND (
                :ns <= GREATEST(
                        lease_end_date,
                        COALESCE(DATE(ended_at), DATE(canceled_at), lease_end_date)
                      )
                AND :ne >= lease_start_date
              )
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (bool)$st->fetch();
    }

    /**
     * 開始日到来済み scheduled を active に昇格する
     * - ただし同一車両に別 active があれば昇格しない
     * - 昇格できなかったものは alerts として返す
     *
     * @return array<int, array<string, mixed>>
     */
    public function promoteDueScheduledLeases(?int $onlyCarId = null): array
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

                    // 既存activeがあるので車両状態は active 側を優先して整合だけ取る
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

    /**
     * cars 側の状態を再計算して整合を取る
     *
     * 変更（今回）:
     * - active があれば status_code=2 / current_lease_id=そのID
     * - active は無く scheduled があれば status_code=6 / current_lease_id=NULL
     * - どちらも無い場合は manual_status_code を優先
     *   - manual_status_code が 3/4/5 ならその状態を維持
     *   - 未設定や不正なら在庫(1)
     *
     * 重要:
     * - 以前は「リースが無ければ在庫(1)」固定だったが、
     *   今回の機能追加により「代車 / 販売済 / 廃車」を維持する必要があるため、
     *   通常状態は cars.manual_status_code を正として扱う
     */
    private function syncCarLeaseState(int $carId): void
    {
        if ($carId <= 0) return;

        $pdo = Db::pdo();

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

        if ($activeLeaseId > 0) {
            $pdo->prepare("
                UPDATE cars
                SET current_lease_id = :lid,
                    status_code = 2
                WHERE id = :cid
            ")->execute([
                ':lid' => $activeLeaseId,
                ':cid' => $carId,
            ]);
            return;
        }

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
        $scheduledLeaseId = (int)($stScheduled->fetchColumn() ?: 0);

        if ($scheduledLeaseId > 0) {
            $pdo->prepare("
                UPDATE cars
                SET current_lease_id = NULL,
                    status_code = 6
                WHERE id = :cid
            ")->execute([':cid' => $carId]);
            return;
        }

        // --------------------------------------------------------
        // 変更（今回）:
        // - リースが無いときは manual_status_code を採用する
        // --------------------------------------------------------
        $stManual = $pdo->prepare("
            SELECT manual_status_code
            FROM cars
            WHERE id = :cid
            LIMIT 1
        ");
        $stManual->execute([':cid' => $carId]);
        $manualStatus = (int)($stManual->fetchColumn() ?: 1);

        // 許可する通常状態:
        // 1=在庫 / 3=販売済 / 4=代車 / 5=廃車
        if (!in_array($manualStatus, [1, 3, 4, 5], true)) {
            $manualStatus = 1;
        }

        $pdo->prepare("
            UPDATE cars
            SET current_lease_id = NULL,
                status_code = :status
            WHERE id = :cid
        ")->execute([
            ':status' => $manualStatus,
            ':cid'    => $carId,
        ]);
    }

    private function validateLeaseInput(array $in, bool $isEdit, ?array $existing = null): array
    {
        $v = new Validation();
        $pdo = Db::pdo();

        $carId = (int)($in['car_id'] ?? 0);
        if ($carId <= 0) $v->add('car_id', '車両が指定されていません。');

        $lesseeType = trim((string)($in['lessee_type'] ?? ''));
        if (!in_array($lesseeType, ['office','personal'], true)) $v->add('lessee_type', 'リース先種別を正しく選択してください。');

        $lesseeId = $this->toDigitsInt($in['lessee_id'] ?? '');
        if ($lesseeId <= 0) $v->add('lessee_id', 'リース先IDを正しく入力してください。');

        $start = trim((string)($in['lease_start_date'] ?? ''));
        $end   = trim((string)($in['lease_end_date'] ?? ''));

        if ($start === '' || !$this->isYmd($start)) $v->add('lease_start_date', '開始日を正しく入力してください。');
        if ($end === '' || !$this->isYmd($end))     $v->add('lease_end_date', '終了予定日を正しく入力してください。');
        if ($this->isYmd($start) && $this->isYmd($end) && $start > $end) $v->add('lease_end_date', '終了予定日は開始日以降にしてください。');

        $monthlyFee = $this->toDigitsInt($in['monthly_fee'] ?? '');
        if ($monthlyFee <= 0) $v->add('monthly_fee', '月額リース料は1円以上で入力してください。');

        $notes = isset($in['notes']) ? (string)$in['notes'] : null;

        if ($isEdit && $existing) {
            if ((int)$existing['car_id'] !== $carId) $v->add('car_id', '車両の変更はできません。');
            if (!in_array((string)($existing['status'] ?? ''), ['active','scheduled'], true)) {
                $v->add('__global', '終了済みのリースは編集できません。');
            }
        }

        // リース先存在チェック（deleted除外）
        if ($lesseeType === 'office') {
            $st = $pdo->prepare("SELECT id FROM office_customers WHERE id = :i AND deleted_at IS NULL LIMIT 1");
            $st->execute([':i'=>$lesseeId]);
            if (!$st->fetch()) $v->add('lessee_id', '指定された法人リース先が見つかりません。');
        } else {
            $st = $pdo->prepare("SELECT id FROM personal_customers WHERE id = :i AND deleted_at IS NULL LIMIT 1");
            $st->execute([':i'=>$lesseeId]);
            if (!$st->fetch()) $v->add('lessee_id', '指定された個人リース先が見つかりません。');
        }

        // 期間重複チェック
        if ($carId > 0 && $this->isYmd($start) && $this->isYmd($end)) {
            $selfId = null;
            if ($isEdit && $existing) $selfId = (int)($existing['id'] ?? 0);
            if ($selfId !== null && $selfId <= 0) $selfId = null;

            if ($this->hasOverlappingLease($carId, $start, $end, $selfId)) {
                $v->add('__global', 'この車両は指定期間に他のリースが存在します（期間が重複しています）。');
            }
        }

        if (!$v->ok()) return ['ok'=>false,'errors'=>$v->errors,'in'=>$in];

        return [
            'ok'=>true,
            'data'=>[
                'car_id'=>$carId,
                'lessee_type'=>$lesseeType,
                'lessee_id'=>$lesseeId,
                'lease_start_date'=>$start,
                'lease_end_date'=>$end,
                'monthly_fee'=>$monthlyFee,
                'notes'=>$notes,
            ],
            'in'=>$in
        ];
    }

    // ============================================================
    // Pages
    // ============================================================
    public function showByCar($carId): void
    {
        $me = $this->guardView();
        $carId = (int)$carId;

        $this->promoteDueScheduledLeases($carId);

        $pdo = Db::pdo();

        $stCar = $pdo->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $stCar->execute([':i'=>$carId]);
        $car = $stCar->fetch();
        if (!$car) Response::notFound();

        $st = $pdo->prepare("
            SELECT
              cl.*,
              COALESCE(oc.name, pc.name, '') AS lessee_name,
              COALESCE(oc.company_name_phonetic, pc.letter, '') AS lessee_kana
            FROM car_leases cl
            LEFT JOIN office_customers oc
              ON (cl.lessee_type = 'office' AND oc.id = cl.lessee_id)
            LEFT JOIN personal_customers pc
              ON (cl.lessee_type = 'personal' AND pc.id = cl.lessee_id)
            WHERE cl.car_id = :cid
              AND cl.deleted_at IS NULL
            ORDER BY cl.lease_start_date DESC, cl.id DESC
        ");
        $st->execute([':cid'=>$carId]);
        $leases = $st->fetchAll() ?: [];

        $cfg = require __DIR__ . '/../config.php';
        Response::view('car_leases/show_by_car', [
            'title'=>'リース詳細',
            'me'=>$me,
            'cfg'=>$cfg,
            'car'=>$car,
            'leases'=>$leases
        ]);
    }

    public function create(): void
    {
        $me = $this->guardEdit();
        $cfg = require __DIR__ . '/../config.php';
        $carId = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 0;
        $car = $this->findCarById($carId);

        Response::view('car_leases/form', [
            'title'=>'リース登録',
            'me'=>$me,
            'cfg'=>$cfg,
            'car'=>$car,
            'item'=>null,
            'errors'=>[],
            'old'=>[]
        ]);
    }

    public function store(): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $res = $this->validateLeaseInput($_POST, false, null);
        $cfg = require __DIR__ . '/../config.php';

        $carId = (int)($_POST['car_id'] ?? 0);
        $car = $this->findCarById($carId);

        if (!$res['ok']) {
            Response::view('car_leases/form', [
                'title'=>'リース登録',
                'me'=>$me,
                'cfg'=>$cfg,
                'car'=>$car,
                'item'=>null,
                'errors'=>$res['errors'],
                'old'=>$res['in']
            ]);
            return;
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $carId = (int)$res['data']['car_id'];

            $stCar = $pdo->prepare("SELECT id, deleted_at FROM cars WHERE id = :i LIMIT 1 FOR UPDATE");
            $stCar->execute([':i'=>$carId]);
            $carRow = $stCar->fetch();
            if (!$carRow) throw new RuntimeException('車両が存在しません。');
            if (!empty($carRow['deleted_at'])) throw new RuntimeException('削除済みの車両はリース登録できません。');

            if ($this->hasOverlappingLease($carId, $res['data']['lease_start_date'], $res['data']['lease_end_date'], null)) {
                throw new RuntimeException('この車両は指定期間に他のリースが存在します（期間が重複しています）。');
            }

            $today = $this->todayJstYmd();
            $start = (string)$res['data']['lease_start_date'];
            $status = ($start > $today) ? 'scheduled' : 'active';

            $st = $pdo->prepare("
                INSERT INTO car_leases
                  (car_id, lessee_type, lessee_id, lease_start_date, lease_end_date, monthly_fee, notes, status)
                VALUES
                  (:car_id, :lessee_type, :lessee_id, :start, :end, :fee, :notes, :status)
            ");
            $st->execute([
                ':car_id'=>$res['data']['car_id'],
                ':lessee_type'=>$res['data']['lessee_type'],
                ':lessee_id'=>$res['data']['lessee_id'],
                ':start'=>$res['data']['lease_start_date'],
                ':end'=>$res['data']['lease_end_date'],
                ':fee'=>$res['data']['monthly_fee'],
                ':notes'=>$res['data']['notes'],
                ':status'=>$status,
            ]);

            $leaseId = (int)$pdo->lastInsertId();

            if ($status === 'active') {
                $pdo->prepare("UPDATE cars SET current_lease_id=:lid, status_code=2 WHERE id=:cid")
                    ->execute([':lid'=>$leaseId, ':cid'=>$carId]);
            } else {
                // future 開始は車両を「リース予定」にする
                // ただし既に active がある場合は active 優先
                $stActive = $pdo->prepare("
                    SELECT id
                    FROM car_leases
                    WHERE car_id = :cid
                      AND deleted_at IS NULL
                      AND status = 'active'
                    LIMIT 1
                ");
                $stActive->execute([':cid' => $carId]);
                $activeLeaseId = (int)($stActive->fetchColumn() ?: 0);

                if ($activeLeaseId > 0) {
                    $pdo->prepare("
                        UPDATE cars
                        SET current_lease_id = :lid,
                            status_code = 2
                        WHERE id = :cid
                    ")->execute([
                        ':lid' => $activeLeaseId,
                        ':cid' => $carId,
                    ]);
                } else {
                    $pdo->prepare("
                        UPDATE cars
                        SET current_lease_id = NULL,
                            status_code = 6
                        WHERE id = :cid
                    ")->execute([':cid' => $carId]);
                }
            }

            $pdo->commit();

            $stNew = $pdo->prepare("SELECT * FROM car_leases WHERE id = :i");
            $stNew->execute([':i'=>$leaseId]);
            $new = $stNew->fetch() ?: [];
            $diff = Audit::diff([], $new, $this->auditFields);
            Audit::log('car_leases', 'CarLease', $leaseId, 'create', (int)$me['id'], $diff);

            Response::redirect('/cars/'.$carId.'/leases');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::view('car_leases/form', [
                'title'=>'リース登録',
                'me'=>$me,
                'cfg'=>$cfg,
                'car'=>$car,
                'item'=>null,
                'errors'=>['__global'=>[$e->getMessage()]],
                'old'=>$_POST
            ]);
        }
    }

    public function edit($id): void
    {
        $me = $this->guardEdit();
        $id = (int)$id;

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM car_leases WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $st->execute([':i'=>$id]);
        $item = $st->fetch();
        if (!$item) Response::notFound();

        $car = $this->findCarById((int)$item['car_id']);
        $cfg = require __DIR__ . '/../config.php';

        Response::view('car_leases/form', [
            'title'=>'リース編集',
            'me'=>$me,
            'cfg'=>$cfg,
            'car'=>$car,
            'item'=>$item,
            'errors'=>[],
            'old'=>[]
        ]);
    }

    public function update($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();
        $cfg = require __DIR__ . '/../config.php';

        $stOld = $pdo->prepare("SELECT * FROM car_leases WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $stOld->execute([':i'=>$id]);
        $old = $stOld->fetch();
        if (!$old) Response::notFound();

        $car = $this->findCarById((int)$old['car_id']);

        $res = $this->validateLeaseInput($_POST, true, $old);
        if (!$res['ok']) {
            Response::view('car_leases/form', [
                'title'=>'リース編集',
                'me'=>$me,
                'cfg'=>$cfg,
                'car'=>$car,
                'item'=>$old,
                'errors'=>$res['errors'],
                'old'=>$res['in']
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $stLock = $pdo->prepare("SELECT status FROM car_leases WHERE id = :i LIMIT 1 FOR UPDATE");
            $stLock->execute([':i'=>$id]);
            $stt = (string)($stLock->fetchColumn() ?: '');
            if (!in_array($stt, ['active','scheduled'], true)) throw new RuntimeException('終了済みのリースは編集できません。');

            if ($this->hasOverlappingLease((int)$old['car_id'], $res['data']['lease_start_date'], $res['data']['lease_end_date'], (int)$old['id'])) {
                throw new RuntimeException('この車両は指定期間に他のリースが存在します（期間が重複しています）。');
            }

            $today = $this->todayJstYmd();
            $start = (string)$res['data']['lease_start_date'];
            $newStatus = ($start > $today) ? 'scheduled' : 'active';

            $pdo->prepare("
                UPDATE car_leases
                SET lessee_type=:lessee_type,
                    lessee_id=:lessee_id,
                    lease_start_date=:start,
                    lease_end_date=:end,
                    monthly_fee=:fee,
                    notes=:notes,
                    status=:status
                WHERE id=:id
            ")->execute([
                ':lessee_type'=>$res['data']['lessee_type'],
                ':lessee_id'=>$res['data']['lessee_id'],
                ':start'=>$res['data']['lease_start_date'],
                ':end'=>$res['data']['lease_end_date'],
                ':fee'=>$res['data']['monthly_fee'],
                ':notes'=>$res['data']['notes'],
                ':status'=>$newStatus,
                ':id'=>$id
            ]);

            // cars整合を再計算
            $this->syncCarLeaseState((int)$old['car_id']);

            $pdo->commit();

            // 開始日が当日以前に変更された場合は昇格判定も実行
            $this->promoteDueScheduledLeases((int)$old['car_id']);

            $stNew = $pdo->prepare("SELECT * FROM car_leases WHERE id=:i");
            $stNew->execute([':i'=>$id]);
            $new = $stNew->fetch() ?: [];

            $diff = Audit::diff($old, $new, $this->auditFields);
            if (!empty($diff)) Audit::log('car_leases','CarLease',$id,'update',(int)$me['id'],$diff);

            Response::redirect('/cars/'.(int)$old['car_id'].'/leases');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::view('car_leases/form', [
                'title'=>'リース編集',
                'me'=>$me,
                'cfg'=>$cfg,
                'car'=>$car,
                'item'=>$old,
                'errors'=>['__global'=>[$e->getMessage()]],
                'old'=>$_POST
            ]);
        }
    }

    public function forceEndForm($id): void
    {
        $me = $this->guardEdit();
        $id = (int)$id;

        $pdo = Db::pdo();
        $stOld = $pdo->prepare("SELECT * FROM car_leases WHERE id=:i AND deleted_at IS NULL LIMIT 1");
        $stOld->execute([':i'=>$id]);
        $item = $stOld->fetch();
        if (!$item) Response::notFound();

        Response::view('car_leases/force_end', ['title'=>'リース強制終了','me'=>$me,'item'=>$item,'errors'=>[]]);
    }

    public function forceEnd($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();

        $stOld = $pdo->prepare("SELECT * FROM car_leases WHERE id=:i AND deleted_at IS NULL LIMIT 1");
        $stOld->execute([':i'=>$id]);
        $old = $stOld->fetch();
        if (!$old) Response::notFound();

        if (!in_array((string)($old['status'] ?? ''), ['active','scheduled'], true)) {
            Response::view('car_leases/force_end', ['title'=>'リース強制終了','me'=>$me,'item'=>$old,'errors'=>['__global'=>['このリースはすでに終了しています。']]]);
            return;
        }

        $endDate = trim((string)($_POST['end_date'] ?? ''));
        if ($endDate === '') $endDate = (string)($old['lease_end_date'] ?? '');
        if (!$this->isYmd($endDate)) {
            Response::view('car_leases/force_end', ['title'=>'リース強制終了','me'=>$me,'item'=>$old,'errors'=>['__global'=>['実終了日を正しく入力してください。']]]);
            return;
        }

        $startDate = (string)($old['lease_start_date'] ?? '');
        if ($this->isYmd($startDate) && $endDate < $startDate) {
            Response::view('car_leases/force_end', ['title'=>'リース強制終了','me'=>$me,'item'=>$old,'errors'=>['__global'=>['実終了日は開始日以降にしてください。']]]);
            return;
        }

        $carId = (int)($old['car_id'] ?? 0);

        $pdo->beginTransaction();
        try {
            $kind = (string)($_POST['end_kind'] ?? 'ended');
            if (!in_array($kind, ['ended','canceled'], true)) $kind = 'ended';

            $endAt = $endDate . ' 00:00:00';
            $endedAt = null;
            $canceledAt = null;
            if ($kind === 'ended') $endedAt = $endAt;
            else $canceledAt = $endAt;

            $stLockLease = $pdo->prepare("SELECT * FROM car_leases WHERE id=:i AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
            $stLockLease->execute([':i'=>$id]);
            $leaseRow = $stLockLease->fetch();
            if (!$leaseRow) throw new RuntimeException('リースが存在しません。');
            if (!in_array((string)($leaseRow['status'] ?? ''), ['active','scheduled'], true)) throw new RuntimeException('このリースはすでに終了しています。');

            $pdo->prepare("
                UPDATE car_leases
                SET status=:status,
                    ended_at=:ended_at,
                    canceled_at=:canceled_at
                WHERE id=:id
            ")->execute([
                ':status'=>$kind,
                ':ended_at'=>$endedAt,
                ':canceled_at'=>$canceledAt,
                ':id'=>$id
            ]);

            // いったん車両状態を再計算
            $this->syncCarLeaseState($carId);

            $pdo->commit();

            // 終了後、開始日到来済みの次回 scheduled があれば昇格
            $this->promoteDueScheduledLeases($carId);

            // 念のため再度整合
            $this->syncCarLeaseState($carId);

            $stNew = $pdo->prepare("SELECT * FROM car_leases WHERE id=:i");
            $stNew->execute([':i'=>$id]);
            $new = $stNew->fetch() ?: [];
            $diff = Audit::diff($old, $new, $this->auditFields);
            if (!empty($diff)) Audit::log('car_leases','CarLease',$id,'update',(int)$me['id'],$diff);

            Response::redirect('/cars/'.$carId.'/leases');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::view('car_leases/force_end', ['title'=>'リース強制終了','me'=>$me,'item'=>$old,'errors'=>['__global'=>['リース終了に失敗しました。']]]);
        }
    }
}