<?php

/**
 * CarBulkController.php
 * ============================================================
 * 役割:
 * - 車両管理（cars）の一括編集
 * - 対象項目:
 *   - car_insurance_premium（自動車保険料）
 *   - total_expenses（経費総額）
 *   - mileage_amount（走行距離）
 *
 * 方針（K-Core / CarController 同期）:
 * - 権限ガードは Policies::guardView / guardEdit
 * - 未ログイン時は、画面は / にリダイレクト、APIは 401 JSON
 * - 論理削除済みは更新対象外
 * - 差分がある行だけ UPDATE
 * - 差分がある行だけ Audit::log
 * - 一括保存APIは JSON を返す
 *
 * 改善点（今回）:
 * - 更新前データを id IN (...) で一括取得
 * - 更新後データも更新対象IDだけ一括取得
 * - UPDATE は1件ずつ維持（差分判定・監査ログ整合を優先）
 * - DB往復回数を削減して、大量件数時の負荷を軽減
 */

class CarBulkController
{
    /**
     * 監査対象フィールド
     * - 一括編集対象だけに絞る
     */
    private array $fields = [
        'car_insurance_premium',
        'total_expenses',
        'mileage_amount',
    ];

    // ============================================================
    // Guards
    // ============================================================
    private function guardView(): array
    {
        $u = Auth::user();
        if (!$u) {
            if (Response::isApi()) {
                $this->jsonOut([
                    'ok'      => false,
                    'message' => 'Unauthorized',
                    'updated' => 0,
                    'skipped' => 0,
                    'errors'  => [],
                ], 401);
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
    // Page
    // ============================================================
    public function index(): void
    {
        $me = $this->guardEdit();
        $cfg = require __DIR__ . '/../config.php';

        $pdo = Db::pdo();
        $sql = "
            SELECT
                id,
                maker,
                car_model,
                vehicle_number,
                model_year,
                status_code,
                car_insurance_premium,
                total_expenses,
                mileage_amount
            FROM cars
            WHERE deleted_at IS NULL
            ORDER BY id DESC
        ";
        $rows = $pdo->query($sql)->fetchAll() ?: [];

        Response::view('cars/bulk', [
            'title' => '車両一括編集',
            'cfg'   => $cfg,
            'rows'  => $rows,
            'me'    => $me,
        ]);
    }

    // ============================================================
    // API: bulk update
    // ============================================================
    public function bulkUpdate(): void
    {
        // JSON APIなので、ここでは絶対にHTMLを返さない
        $pdo = null;

        try {
            $me = $this->guardEdit();

            // ------------------------------------------------------------
            // JSON受信
            // - FormData ではなく application/json を受ける
            // - max_input_vars / multipart body parts limit 回避
            // ------------------------------------------------------------
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);

            if (!is_array($input)) {
                $this->jsonOut([
                    'ok'      => false,
                    'message' => 'JSONの解析に失敗しました。',
                    'updated' => 0,
                    'skipped' => 0,
                    'errors'  => [],
                ], 422);
            }

            try {
                Csrf::check($input['_token'] ?? null);
            } catch (Throwable $e) {
                $this->jsonOut([
                    'ok'      => false,
                    'message' => 'CSRFトークンが不正です。',
                    'updated' => 0,
                    'skipped' => 0,
                    'errors'  => [],
                ], 419);
            }

            $payload = $input['rows'] ?? null;

            if (!is_array($payload)) {
                $this->jsonOut([
                    'ok'      => false,
                    'message' => '更新データが不正です。',
                    'updated' => 0,
                    'skipped' => 0,
                    'errors'  => [],
                ], 422);
            }

            $pdo = Db::pdo();

            $updatedCount = 0;
            $skippedCount = 0;
            $errors = [];

            // ------------------------------------------------------------
            // 1) 更新対象IDを先に収集
            // - ここでは「形式上有効なID」だけを集める
            // - 実在チェック / deleted_at チェックは後段の一括取得結果で判断する
            // ------------------------------------------------------------
            $targetIds = [];
            foreach ($payload as $idx => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $targetIds[$id] = $id;
                }
            }

            // IDが1件も無い場合でも、後続ループで個別エラーを返せるように空配列で進める
            $oldMap = [];

            // ------------------------------------------------------------
            // 2) 旧データを一括取得
            // - 論理削除済みは除外
            // - ここで取得できなかったIDは「見つからない or 削除済み」と判断する
            // ------------------------------------------------------------
            if (!empty($targetIds)) {
                $placeholders = implode(',', array_fill(0, count($targetIds), '?'));

                $stAll = $pdo->prepare("
                    SELECT *
                    FROM cars
                    WHERE deleted_at IS NULL
                      AND id IN ($placeholders)
                ");

                $bindIndex = 1;
                foreach (array_values($targetIds) as $targetId) {
                    $stAll->bindValue($bindIndex++, $targetId, PDO::PARAM_INT);
                }

                $stAll->execute();
                $oldRows = $stAll->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($oldRows as $r) {
                    $rid = (int)($r['id'] ?? 0);
                    if ($rid > 0) {
                        $oldMap[$rid] = $r;
                    }
                }
            }

            // ------------------------------------------------------------
            // 3) 各行を差分判定し、差分があるものだけ1件ずつ更新
            // - UPDATE 自体は1件ずつ維持
            //   （既存実装の流儀 / エラー時の切り分け / 監査整合を優先）
            // ------------------------------------------------------------
            $updatedIds = [];
            $oldAuditMap = [];

            foreach ($payload as $idx => $row) {
                if (!is_array($row)) {
                    $skippedCount++;
                    $errors[] = '行データの形式が不正です。index=' . $idx;
                    continue;
                }

                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) {
                    $skippedCount++;
                    $errors[] = 'IDが不正です。index=' . $idx;
                    continue;
                }

                $old = $oldMap[$id] ?? null;
                if (!$old || !is_array($old)) {
                    $skippedCount++;
                    $errors[] = '対象車両が見つからないか、削除済みです。id=' . $id;
                    continue;
                }

                $data = [
                    'car_insurance_premium' => $this->toIntOrNull($row['car_insurance_premium'] ?? null),
                    'total_expenses'        => $this->toIntOrNull($row['total_expenses'] ?? null),
                    'mileage_amount'        => $this->toIntOrNull($row['mileage_amount'] ?? null),
                ];

                $newLike = $old;
                foreach ($data as $k => $v) {
                    $newLike[$k] = $v;
                }

                $diff = Audit::diff($old, $newLike, $this->fields);
                if (empty($diff)) {
                    $skippedCount++;
                    continue;
                }

                // --------------------------------------------------------
                // 既存 CarController@update と同じ流儀
                // - 更新だけをトランザクションに入れる
                // - 監査ログは commit 後に行う
                // --------------------------------------------------------
                $pdo->beginTransaction();

                try {
                    $stUp = $pdo->prepare("
                        UPDATE cars
                        SET
                            car_insurance_premium = :car_insurance_premium,
                            total_expenses        = :total_expenses,
                            mileage_amount        = :mileage_amount
                        WHERE id = :id
                    ");

                    foreach ($data as $k => $v) {
                        $type = match (true) {
                            is_int($v)  => PDO::PARAM_INT,
                            is_null($v) => PDO::PARAM_NULL,
                            default     => PDO::PARAM_STR,
                        };
                        $stUp->bindValue(':' . $k, $v, $type);
                    }

                    $stUp->bindValue(':id', $id, PDO::PARAM_INT);
                    $stUp->execute();

                    $pdo->commit();

                    $updatedCount++;
                    $updatedIds[$id] = $id;
                    $oldAuditMap[$id] = $old;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $skippedCount++;
                    $errors[] = '更新に失敗しました。id=' . $id . ' message=' . $e->getMessage();
                    continue;
                }
            }

            // ------------------------------------------------------------
            // 4) 更新後データを一括取得
            // - 監査ログ用
            // - 更新成功IDだけを対象にする
            // ------------------------------------------------------------
            $newMap = [];

            if (!empty($updatedIds)) {
                $placeholders = implode(',', array_fill(0, count($updatedIds), '?'));

                $stNewAll = $pdo->prepare("
                    SELECT *
                    FROM cars
                    WHERE id IN ($placeholders)
                ");

                $bindIndex = 1;
                foreach (array_values($updatedIds) as $updatedId) {
                    $stNewAll->bindValue($bindIndex++, $updatedId, PDO::PARAM_INT);
                }

                $stNewAll->execute();
                $newRows = $stNewAll->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($newRows as $r) {
                    $rid = (int)($r['id'] ?? 0);
                    if ($rid > 0) {
                        $newMap[$rid] = $r;
                    }
                }
            }

            // ------------------------------------------------------------
            // 5) 監査ログ
            // - commit後に実行
            // - 差分があるものだけ記録
            // ------------------------------------------------------------
            foreach ($updatedIds as $id) {
                $old = $oldAuditMap[$id] ?? null;
                $new = $newMap[$id] ?? null;

                if (!$old || !is_array($old) || !$new || !is_array($new)) {
                    continue;
                }

                $diff = Audit::diff($old, $new, $this->fields);
                if (!empty($diff)) {
                    Audit::log('cars', 'Car', $id, 'update', (int)$me['id'], $diff);
                }
            }

            $this->jsonOut([
                'ok'      => true,
                'message' => '一括更新が完了しました。',
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors'  => $errors,
            ], 200);
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('CarBulkController::bulkUpdate error: ' . $e->getMessage());
            error_log($e->getTraceAsString());

            $this->jsonOut([
                'ok'      => false,
                'message' => '一括更新に失敗しました: ' . $e->getMessage(),
                'updated' => 0,
                'skipped' => 0,
                'errors'  => [],
            ], 500);
        }
    }

    // ============================================================
    // Helpers
    // ============================================================
    private function toIntOrNull($x): ?int
    {
        $s = trim((string)$x);
        if ($s === '') return null;

        $s = mb_convert_kana($s, 'n', 'UTF-8');
        $s = str_replace(',', '', $s);
        $s = preg_replace('/[^0-9]/', '', $s);

        if ($s === '') return null;

        return (int)$s;
    }

    private function jsonOut(array $data, int $status = 200): void
    {
        if (function_exists('ob_get_length') && ob_get_length()) {
            ob_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}