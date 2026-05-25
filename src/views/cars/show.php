<?php
/**
 * views/cars/show.php
 * ============================================================
 * 役割:
 * - 車両詳細表示
 *
 * 追加（今回）:
 * - current_lease_id がある場合、car_leases を参照して
 *   - 開始日が未来 → 「リース予定」
 *   - 開始日が今日以前 → 「リース中」
 *   を表示する
 *
 * 変更（今回）:
 * - manual_status_code / status_code を参照して
 *   在庫 / 代車 / 廃車 / 販売済 の通常状態も明示表示する
 * - 「ステータス変更」カードを追加
 *   - 在庫: 代車へ変更 / 廃車へ変更 / 販売済へ変更
 *   - 代車: 在庫へ戻す
 * - 販売済の場合は最新の販売情報を表示
 * - 直近の状態変更履歴を表示
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me = $me ?? Auth::user();

$canEdit = $me ? Policies::canEditCars($me) : false;

$visible = function (string $field) use ($me) {
    if (!$me) return true;
    return Policies::fieldVisible($me, 'cars', $field);
};

$optsCars = $cfg['options']['cars'] ?? [];
$optsCommon = $cfg['options']['common'] ?? [];

$label = function (string $field, $value) use ($optsCars, $optsCommon) {
    if ($field === 'car_model') {
        $flat = $optsCars['car_models_flat'] ?? [];
        return (string)($flat[(string)$value] ?? (string)$value);
    }
    if ($field === 'maker') {
        $mk = $optsCars['maker'] ?? [];
        return (string)($mk[(string)$value] ?? (string)$value);
    }
    if (isset($optsCars[$field]) && is_array($optsCars[$field])) {
        return (string)($optsCars[$field][(int)$value] ?? (string)$value);
    }
    if (isset($optsCommon[$field]) && is_array($optsCommon[$field])) {
        return (string)($optsCommon[$field][(int)$value] ?? (string)$value);
    }
    return (string)$value;
};

$id = (int)$item['id'];
$isDeleted = !empty($item['deleted_at']);

$leaseBadge = null;
$leaseText = null;

// ------------------------------------------------------------
// リース予定 / 中 判定
// ------------------------------------------------------------
$currentLeaseId = isset($item['current_lease_id']) ? (int)$item['current_lease_id'] : 0;
if (!$isDeleted && $currentLeaseId > 0) {
    try {
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT lease_start_date, status FROM car_leases WHERE id = :i LIMIT 1");
        $st->execute([':i' => $currentLeaseId]);
        $lease = $st->fetch();

        if ($lease && (string)($lease['status'] ?? '') === 'active') {
            $today = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
            $start = (string)($lease['lease_start_date'] ?? '');

            if ($start !== '' && $start > $today) {
                $leaseBadge = 'warning';
                $leaseText  = 'リース予定';
            } else {
                $leaseBadge = 'info';
                $leaseText  = 'リース中';
            }
        }
    } catch (Throwable $e) {
        // 失敗は握る（表示を止めない）
    }
}

// ------------------------------------------------------------
// 通常状態表示
// - リースがある場合はリース状態を優先
// - それ以外は status_code / manual_status_code から表示
// ------------------------------------------------------------
$statusCode = (int)($item['status_code'] ?? 1);
$manualStatusCode = (int)($item['manual_status_code'] ?? 1);

$normalStatusText = '';
$normalStatusBadge = 'secondary';

if (!$leaseText) {
    switch ($statusCode) {
        case 3:
            $normalStatusText = 'お客様所有（販売済）';
            $normalStatusBadge = 'success';
            break;
        case 4:
            $normalStatusText = '代車';
            $normalStatusBadge = 'primary';
            break;
        case 5:
            $normalStatusText = '廃車';
            $normalStatusBadge = 'dark';
            break;
        case 6:
            $normalStatusText = 'リース予定';
            $normalStatusBadge = 'warning';
            break;
        case 2:
            $normalStatusText = 'リース中';
            $normalStatusBadge = 'info';
            break;
        case 1:
        default:
            $normalStatusText = '在庫';
            $normalStatusBadge = 'secondary';
            break;
    }
}

// ------------------------------------------------------------
// 最新販売情報
// ------------------------------------------------------------
$latestSale = null;
if (!$isDeleted) {
    try {
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT *
            FROM car_sales
            WHERE car_id = :cid
              AND deleted_at IS NULL
            ORDER BY sold_at DESC, id DESC
            LIMIT 1
        ");
        $st->execute([':cid' => $id]);
        $latestSale = $st->fetch() ?: null;
    } catch (Throwable $e) {
        $latestSale = null;
    }
}

// ------------------------------------------------------------
// 直近の状態変更履歴
// ------------------------------------------------------------
$statusHistories = [];
if (!$isDeleted) {
    try {
        $pdo = Db::pdo();
        $st = $pdo->prepare("
            SELECT *
            FROM car_status_histories
            WHERE car_id = :cid
              AND deleted_at IS NULL
            ORDER BY changed_at DESC, id DESC
            LIMIT 10
        ");
        $st->execute([':cid' => $id]);
        $statusHistories = $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        $statusHistories = [];
    }
}

// 表示用値
$v_id = (string)$id;
$v_management_number = (string)($item['management_number'] ?? '');
$v_vehicle_number = (string)($item['vehicle_number'] ?? '');
$v_chassis_number = (string)($item['chassis_number'] ?? '');
$v_maker = (string)$label('maker', $item['maker'] ?? '');
$v_car_model = (string)$label('car_model', $item['car_model'] ?? '');
$v_deleted_at = (string)($item['deleted_at'] ?? '');
$v_legal_maintenance = (string)($item['legal_maintenance'] ?? '');
$v_guarantee = (string)($item['guarantee'] ?? '');

$statusLabelMap = $optsCars['status_code'] ?? [];
?>
<div class="k-page cars-show">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">車両詳細</h1>
      <!-- <div class="k-page__sub">車両情報の詳細を表示します。</div> -->
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars">一覧へ戻る</a>

      <?php if ($canEdit): ?>
        <a class="btn btn-outline-primary" href="/cars/<?= $id ?>/edit">編集</a>

        <?php if (!$isDeleted): ?>
          <form method="post"
                action="/cars/<?= $id ?>/delete"
                class="d-inline"
                onsubmit="return confirm('削除（論理削除）を実行します。よろしいですか？');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-outline-danger">削除（論理削除）</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="k-card">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">基本情報</div>
        <div class="k-section-sub text-muted">ID / 管理番号 / 状態など</div>
      </div>
    </div>

    <div class="k-card__body">

      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>

            <tr>
              <th>ID</th>
              <td><?= htmlspecialchars($v_id, ENT_QUOTES, 'UTF-8') ?></td>
              <th>管理番号</th>
              <td><?= htmlspecialchars($v_management_number, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>車両番号</th>
              <td><?= htmlspecialchars($v_vehicle_number, ENT_QUOTES, 'UTF-8') ?></td>
              <th>車台番号</th>
              <td><?= htmlspecialchars($v_chassis_number, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>メーカー</th>
              <td><?= htmlspecialchars($v_maker, ENT_QUOTES, 'UTF-8') ?></td>
              <th>車種</th>
              <td><?= htmlspecialchars($v_car_model, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>

            <tr>
              <th>状態</th>
              <td>
                <?php if ($isDeleted): ?>
                  <span class="badge text-bg-secondary">削除済み</span>
                <?php elseif ($leaseBadge): ?>
                  <span class="badge text-bg-<?= htmlspecialchars($leaseBadge, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($leaseText, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <a class="btn btn-sm btn-outline-primary ms-2" href="/cars/<?= $id ?>/leases">リース詳細</a>
                <?php else: ?>
                  <span class="badge text-bg-<?= htmlspecialchars($normalStatusBadge, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($normalStatusText, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endif; ?>
              </td>
              <th>通常状態</th>
              <td>
                <span class="badge text-bg-light border">
                  <?= htmlspecialchars((string)($statusLabelMap[$manualStatusCode] ?? $manualStatusCode), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
            </tr>

            <tr>
              <th>削除日時</th>
              <td colspan="3">
                <?= htmlspecialchars($v_deleted_at, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($isDeleted): ?>
                  <span class="badge text-bg-secondary ms-2">削除済み</span>
                <?php endif; ?>
              </td>
            </tr>

            <tr>
              <th>法定整備</th>
              <td colspan="3">
                <pre class="mb-0"><?= htmlspecialchars($v_legal_maintenance, ENT_QUOTES, 'UTF-8') ?></pre>
              </td>
            </tr>

            <tr>
              <th>保証</th>
              <td colspan="3">
                <pre class="mb-0"><?= htmlspecialchars($v_guarantee, ENT_QUOTES, 'UTF-8') ?></pre>
              </td>
            </tr>

          </tbody>
        </table>
      </div>

    </div>
  </div>

  <?php if ($canEdit && !$isDeleted): ?>
    <div class="k-card mt-3">
      <div class="k-card__header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="k-section-title">ステータス変更</div>
          <div class="k-section-sub text-muted">通常状態の変更操作</div>
        </div>
      </div>

      <div class="k-card__body">
        <div class="d-flex flex-wrap gap-2">
          <?php if ($manualStatusCode === 1): ?>
            <a class="btn btn-outline-primary" href="/cars/<?= $id ?>/status/loaner">代車へ変更</a>
            <a class="btn btn-outline-dark" href="/cars/<?= $id ?>/status/scrap">廃車へ変更</a>
            <a class="btn btn-outline-success" href="/cars/<?= $id ?>/status/sell">販売済へ変更</a>
          <?php elseif ($manualStatusCode === 4): ?>
            <a class="btn btn-outline-secondary" href="/cars/<?= $id ?>/status/back_to_stock">在庫へ戻す</a>
          <?php else: ?>
            <span class="text-muted">この状態では変更操作はありません。</span>
          <?php endif; ?>
        </div>

        <div class="small text-muted mt-2">
          ※ リース中・リース予定がある場合は変更できない操作があります。
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($latestSale): ?>
    <div class="k-card mt-3">
      <div class="k-card__header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="k-section-title">最新販売情報</div>
          <div class="k-section-sub text-muted">販売済時の金額内訳</div>
        </div>
      </div>

      <div class="k-card__body">
        <div class="k-show-table-wrap">
          <table class="k-show-table">
            <tbody>
              <tr>
                <th>販売日</th>
                <td><?= htmlspecialchars((string)($latestSale['sold_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <th>販売先</th>
                <td><?= htmlspecialchars((string)($latestSale['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <tr>
                <th>販売額</th>
                <td><?= number_format((int)($latestSale['sale_price'] ?? 0)) ?> 円</td>
                <th>消費税</th>
                <td><?= number_format((int)($latestSale['tax_amount'] ?? 0)) ?> 円</td>
              </tr>
              <tr>
                <th>リサイクル料</th>
                <td><?= number_format((int)($latestSale['recycle_fee'] ?? 0)) ?> 円</td>
                <th>その他諸費用</th>
                <td><?= number_format((int)($latestSale['other_fee'] ?? 0)) ?> 円</td>
              </tr>
              <tr>
                <th>合計金額</th>
                <td colspan="3"><?= number_format((int)($latestSale['total_amount'] ?? 0)) ?> 円</td>
              </tr>
              <tr>
                <th>備考</th>
                <td colspan="3">
                  <pre class="mb-0"><?= htmlspecialchars((string)($latestSale['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="k-card mt-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">状態変更履歴</div>
        <div class="k-section-sub text-muted">直近10件</div>
      </div>
    </div>

    <div class="k-card__body">
      <?php if (empty($statusHistories)): ?>
        <div class="text-muted">履歴はありません。</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>変更日時</th>
                <th>変更前</th>
                <th>変更後</th>
                <th>相手先</th>
                <th>備考</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($statusHistories as $h): ?>
                <tr>
                  <td><?= htmlspecialchars((string)($h['changed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($statusLabelMap[(int)($h['from_status_code'] ?? 0)] ?? ($h['from_status_code'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($statusLabelMap[(int)($h['to_status_code'] ?? 0)] ?? ($h['to_status_code'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($h['partner_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($h['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>