<?php
/**
 * views/car_leases/form.php
 * ============================================================
 * 車両リース 登録/編集フォーム（共通）
 *
 * 前提:
 * - 登録時: car は固定（URL ?car_id=xx から）
 * - 編集時: car_id / lessee は変更不可
 *
 * JS:
 * - 数値入力補助: /public/js/carLeasesForm.js
 * - リース先検索ピッカー: /public/js/components/lesseePicker.js
 *
 * Components:
 * - リース先入力（インライン）: views/components/lessee_select_inline.php
 * - ピッカーモーダル: views/components/lessee_picker_modal.php
 */

$cfg  = $cfg ?? (require __DIR__ . '/../../app/config.php');
$me   = $me  ?? Auth::user();

$e    = (isset($errors) && is_array($errors)) ? $errors : [];
$old  = (isset($old)    && is_array($old))    ? $old    : [];
$item = (isset($item)   && is_array($item))   ? $item   : null;
$car  = (isset($car)    && is_array($car))    ? $car    : null;

$isEdit = (bool)$item;

$val = function (string $k, $default = '') use ($old, $item) {
    if (is_array($old) && array_key_exists($k, $old))  return $old[$k];
    if ($item && array_key_exists($k, $item)) return $item[$k];
    return $default;
};

$err = function (string $k) use ($e) {
    if (!empty($e[$k])) {
        echo '<div class="form-error">' .
            htmlspecialchars(implode(' / ', (array)$e[$k]), ENT_QUOTES, 'UTF-8') .
            '</div>';
    }
};

$titleText = $isEdit ? 'リース編集' : 'リース登録';
$actionUrl = $isEdit
    ? '/car_leases/' . (int)$item['id']
    : '/car_leases';

$backUrl = $car ? '/cars/'.(int)$car['id'].'/leases' : ($item ? '/cars/'.(int)$item['car_id'].'/leases' : '/cars');
?>
<div class="k-page car-leases-form">

  <div class="k-page__header">
    <div>
      <h1 class="k-page__title"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="k-page__sub">車両リースの登録／編集を行います。</div>
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

  <form id="car-lease-form" method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <?php if ($car): ?>
      <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
    <?php elseif ($item): ?>
      <input type="hidden" name="car_id" value="<?= (int)$item['car_id'] ?>">
    <?php endif; ?>

    <!-- ===================================================== -->
    <!-- タイル（カード）縦積み：1段に1個（フル幅） -->
    <!-- ===================================================== -->
    <div class="d-flex flex-column gap-3">

      <!-- 対象車両 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">対象車両</div>
            <div class="k-section-sub text-muted">管理番号 / 車両番号 / 車台番号</div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">管理番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['management_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">車両番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">車台番号</label>
              <input type="text" class="form-control" readonly
                value="<?= htmlspecialchars((string)($car['chassis_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- リース先 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">リース先</div>
            <div class="k-section-sub text-muted">法人／個人の横断検索ピッカーで選択</div>
          </div>
        </div>
        <div class="k-card__body">

          <?php
            // ------------------------------------------------------------
            // ★リース先表示名をサーバ側で補完（編集/バリデーションエラーでも消えない）
            // - old['lessee_name'] が空で、lessee_type+lessee_id があればDBから引く
            // ------------------------------------------------------------
            if (is_array($old)) {
              $t = (string)($old['lessee_type'] ?? '');
              $i = (int)($old['lessee_id'] ?? 0);
              $name = (string)($old['lessee_name'] ?? '');
              if ($name === '' && ($t === 'office' || $t === 'personal') && $i > 0) {
                try {
                  $pdo = Db::pdo();
                  if ($t === 'office') {
                    $st = $pdo->prepare("SELECT name FROM office_customers WHERE id=:i AND deleted_at IS NULL LIMIT 1");
                  } else {
                    $st = $pdo->prepare("SELECT name FROM personal_customers WHERE id=:i AND deleted_at IS NULL LIMIT 1");
                  }
                  $st->execute([':i'=>$i]);
                  $old['lessee_name'] = (string)($st->fetchColumn() ?: '');
                } catch (Throwable $e) {
                  // 握る
                }
              }
            }

            // edit初期表示（oldが空でitemがある時）
            if (empty($old) && is_array($item)) {
              $t = (string)($item['lessee_type'] ?? '');
              $i = (int)($item['lessee_id'] ?? 0);
              $old = $old ?: [];
              $old['lessee_type'] = $t;
              $old['lessee_id']   = $i;

              if ($t === 'office' || $t === 'personal') {
                try {
                  $pdo = Db::pdo();
                  if ($t === 'office') {
                    $st = $pdo->prepare("SELECT name FROM office_customers WHERE id=:i AND deleted_at IS NULL LIMIT 1");
                  } else {
                    $st = $pdo->prepare("SELECT name FROM personal_customers WHERE id=:i AND deleted_at IS NULL LIMIT 1");
                  }
                  $st->execute([':i'=>$i]);
                  $old['lessee_name'] = (string)($st->fetchColumn() ?: '');
                } catch (Throwable $e) {}
              }
            }
          ?>

          <?php
            // インライン部品（lessee_type / lessee_id + 検索ボタン）
            // - $cfg, $item, $old を渡している前提
            require __DIR__ . '/../components/lessee_select_inline.php';
          ?>

          <?php $err('lessee_type'); ?>
          <?php $err('lessee_id'); ?>

        </div>
      </div>

      <!-- 契約内容 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">契約内容</div>
            <div class="k-section-sub text-muted">期間 / 月額</div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">開始日</label>
              <input type="date" name="lease_start_date" class="form-control"
                value="<?= htmlspecialchars((string)$val('lease_start_date'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('lease_start_date'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">終了予定日</label>
              <input type="date" name="lease_end_date" class="form-control"
                value="<?= htmlspecialchars((string)$val('lease_end_date'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('lease_end_date'); ?>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">月額リース料（円）</label>
              <input type="text" name="monthly_fee" class="form-control js-num-comma"
                value="<?= htmlspecialchars((string)$val('monthly_fee'), ENT_QUOTES, 'UTF-8') ?>">
              <?php $err('monthly_fee'); ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 備考 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">備考</div>
            <div class="k-section-sub text-muted">メモ</div>
          </div>
        </div>
        <div class="k-card__body">
          <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars((string)$val('notes'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>

      <!-- 操作 -->
      <div class="k-card">
        <div class="k-card__header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="k-section-title">操作</div>
            <div class="k-section-sub text-muted"><?= $isEdit ? '更新 / キャンセル' : '登録 / キャンセル' ?></div>
          </div>
        </div>
        <div class="k-card__body">
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新する' : '登録する' ?></button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">キャンセル</a>
          </div>
        </div>
      </div>

    </div><!-- /tiles stack -->

  </form>

  <?php
    // ピッカーモーダル
    require __DIR__ . '/../components/lessee_picker_modal.php';
  ?>

</div>

<script src="/js/carLeasesForm.js"></script>
<script src="/js/components/lesseePicker.js" defer></script>
