<?php
/**
 * views/cars/status_back_to_stock.php
 * ============================================================
 * 役割:
 * - 車両を「在庫へ戻す」画面
 *
 * 対象:
 * - 代車 → 在庫
 *
 * 入力:
 * - 変更日（必須）
 * - 備考（任意）
 *
 * 注意:
 * - 代車のみこの画面へ遷移可能
 */

$cfg = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me  = $me  ?? Auth::user();
$car = $car ?? [];

$e   = (isset($errors) && is_array($errors)) ? $errors : [];
$old = (isset($old) && is_array($old)) ? $old : [];

$val = function (string $k, $default = '') use ($old) {
    if (array_key_exists($k, $old)) return $old[$k];
    return $default;
};

$err = function (string $k) use ($e) {
    if (!empty($e[$k])) {
        echo '<div class="form-error">' .
            htmlspecialchars(implode(' / ', (array)$e[$k]), ENT_QUOTES, 'UTF-8') .
            '</div>';
    }
};

$backUrl = '/cars/' . (int)($car['id'] ?? 0);
?>
<div class="k-page cars-status-back-to-stock">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title">在庫へ戻す</h1>
      <div class="k-page__sub">代車状態の車両を在庫へ戻します。</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">戻る</a>
    </div>
  </div>

  <?php if (!empty($e['__global'])): ?>
    <div class="alert alert-danger mb-3">
      <?= htmlspecialchars(implode(' / ', (array)$e['__global']), ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post"
        action="/cars/<?= (int)($car['id'] ?? 0) ?>/status/back_to_stock"
        autocomplete="off">

    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="d-flex flex-column gap-3">

      <!-- 車両情報 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">対象車両</div>
          <div class="k-section-sub text-muted">管理番号 / 車両番号 / 車台番号</div>
        </div>

        <div class="k-card__body">
          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label">管理番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['management_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">車両番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">車台番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['chassis_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

          </div>
        </div>
      </div>

      <!-- 変更内容 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">変更内容</div>
          <div class="k-section-sub text-muted">変更日 / 備考</div>
        </div>

        <div class="k-card__body">

          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">変更日 <span class="text-danger">*</span></label>
              <input type="date"
                     name="changed_date"
                     class="form-control"
                     value="<?= htmlspecialchars((string)$val('changed_date'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('changed_date'); ?>
            </div>

            <div class="col-12">
              <label class="form-label">備考</label>
              <textarea name="note"
                        class="form-control"
                        rows="4"><?= htmlspecialchars((string)$val('note'), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

          </div>

        </div>
      </div>

      <!-- 操作 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="k-section-title">操作</div>
          <div class="k-section-sub text-muted">保存 / 戻る</div>
        </div>

        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">在庫へ戻す</button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">戻る</a>
          </div>
        </div>
      </div>

    </div>

  </form>

</div>