<?php
/**
 * views/car_leases/force_end.php
 * ============================================================
 * リース強制終了画面
 *
 * 変更（今回）:
 * - 実終了日（end_date）を指定して確定できるようにする
 *   → ended_at / canceled_at は「実終了日」を保存する（処理日ではない）
 */

$me = $me ?? Auth::user();
$item = $item ?? [];
$e = (isset($errors) && is_array($errors)) ? $errors : [];

$id = (int)($item['id'] ?? 0);
$carId = (int)($item['car_id'] ?? 0);

$defaultKind = 'ended'; // 既定: 満了
$defaultEndDate = (string)($item['lease_end_date'] ?? ''); // 実終了日デフォルト＝予定終了日

$leaseStart = (string)($item['lease_start_date'] ?? '');
$leaseEnd   = (string)($item['lease_end_date'] ?? '');
$monthlyFee = (int)($item['monthly_fee'] ?? 0);
$statusText = (string)($item['status'] ?? '');
?>
<div class="k-page car-leases-force-end">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">リース強制終了</h1>
      <div class="k-page__sub">実終了日を指定して、リース契約を終了確定します。</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>/leases">戻る</a>
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <!-- 警告 -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">確認</div>
        <div class="k-section-sub text-muted">この操作は取り消しに注意</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="alert alert-warning mb-0">
        この操作は、リース契約を<strong>終了確定</strong>します。よろしいですか？
        <div class="small text-muted mt-1">
          ※ 終了確定後、車両は「在庫」に戻ります。
        </div>
      </div>
    </div>
  </div>

  <!-- 対象情報（showと同じテーブル） -->
  <div class="k-card mb-3">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">対象情報</div>
        <div class="k-section-sub text-muted">リースID / 車両ID / 契約内容</div>
      </div>
    </div>
    <div class="k-card__body">
      <div class="k-show-table-wrap">
        <table class="k-show-table">
          <tbody>
            <tr>
              <th>リースID</th>
              <td><?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?></td>
              <th>車両ID</th>
              <td><?= htmlspecialchars((string)$carId, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
              <th>契約期間（予定）</th>
              <td colspan="3">
                <?= htmlspecialchars($leaseStart, ENT_QUOTES, 'UTF-8') ?>
                ～ 
                <?= htmlspecialchars($leaseEnd, ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
            <tr>
              <th>月額</th>
              <td><?= number_format($monthlyFee) ?> 円</td>
              <th>状態</th>
              <td><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 終了確定フォーム -->
  <div class="k-card">
    <div class="k-card__header">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="k-section-title">終了確定</div>
        <div class="k-section-sub text-muted">終了区分 / 実終了日（指定）</div>
      </div>
    </div>
    <div class="k-card__body">
      <form method="post"
            action="/car_leases/<?= $id ?>/force_end"
            onsubmit="return confirm('リースを終了確定します。よろしいですか？');">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">終了区分</label>
            <select class="form-select" name="end_kind">
              <option value="ended" <?= $defaultKind === 'ended' ? 'selected' : '' ?>>満了</option>
              <option value="canceled" <?= $defaultKind === 'canceled' ? 'selected' : '' ?>>解約</option>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">実終了日（指定）</label>
            <input type="date" name="end_date" class="form-control"
                   value="<?= htmlspecialchars($defaultEndDate, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-text">
              ※ デフォルトは「予定終了日」です。必要に応じて実終了日に変更してください。
            </div>
          </div>

          <div class="col-12">
            <div class="d-flex flex-wrap gap-2 mt-2">
              <!-- ★危険操作：btn-outline-danger にして「塗り→hover枠」挙動を使う -->
              <button type="submit" class="btn btn-outline-danger">終了確定する</button>
              <a class="btn btn-outline-secondary" href="/cars/<?= $carId ?>/leases">キャンセル</a>
            </div>
          </div>
        </div>

      </form>
    </div>
  </div>

</div>
