<?php
/**
 * views/layout.php
 * ============================================================
 * 役割:
 * - 全画面共通レイアウト（header / sidebar / main）
 * - $tpl（Response::viewから渡される）を require して画面を描画
 *
 * 改修ポイント（Policies寄せ）
 * - メニュー表示の role_code 直書きを極力廃止し、Policies::menuVisible() に集約
 * - 将来モジュール（customers等）は role_sets に追加するだけでメニュー制御できる
 *
 * 追加（今回）:
 * - common.js を先に読み込めるように（defer）head側へ移動
 *   → view内で usersForm.js 等を読み込む場合も、defer を付ければ KCore.FormHelpers が先に用意される
 *
 * 追加（今回）:
 * - 車両管理（cars）
 * - 法人顧客管理（office_customers）
 * - 個人顧客管理（personal_customers）
 *
 * 追加（今回）:
 * - 車両リース管理（car_leases） ← ★今回追加
 *
 * 注意:
 * - サイドメニューの項目自体はサンプル運用前提（開発の都度変わる）
 * - stores/financials依存は別システム土台のため削除済み
 */

$me = Auth::user();
$title = $title ?? 'k-cloud-sc';

/**
 * 参考：メニューを出したいモジュールは config.php に role_sets を追加
 *
 * 例）
 * 'role_sets' => [
 *   'users' => ['view'=>[...], 'edit'=>[...] ],
 *   'audit' => ['view'=>[...], 'edit'=>[...] ],
 *   'customers' => ['view'=>[1,2,3,4], 'edit'=>[1,2,3,4]],
 *   'grave_contracts' => ['view'=>[1,2,3,4], 'edit'=>[1,2,3,4]],
 *   'construction_contracts' => ['view'=>[1,2,3,4], 'edit'=>[1,2,3,4]],
 * ]
 */
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSRF: JS(Ajax)から参照する用 -->
  <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

  <!-- default Header Object -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- DataTables (Bootstrap5) -->
  <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <!-- web font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">

  <!-- content Header Object -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.2/css/all.min.css">
  <link href="/css/content/custom.css" rel="stylesheet" type="text/css" media="all">
  <link href="/css/app.css" rel="stylesheet">

  <!-- 共通フォームヘルパ（先読み）
       view側で usersForm.js 等を読み込む場合は <script defer> を付ける想定 -->
  <script src="/js/common.js" defer></script>
</head>

<body>
  <!-- header -->
  <header>
      <h1><img src="/images/logo.svg" alt="K-cloud"></h1>
      <div class="d-flex align-items-center gap-5">
          <!-- <div class="notification_area">
              <a href="#" class="text-decoration-none position-relative">
                  <i class="fa-solid fa-bell"></i>
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">5</span>
              </a>
          </div> -->

          <!-- サイドバー（モバイル） -->
          <button class="hamburger d-lg-none" type="button"
                  data-bs-toggle="offcanvas" data-bs-target="#sidebar"
                  aria-controls="sidebar" aria-label="メニューを開く">
              <span></span><span></span><span></span>
          </button>
      </div>
  </header>
  <!-- /header -->

  <!-- content_area -->
  <div class="content_area">
    <!-- 左カラム（ナビゲーション） -->
    <?php if ($me): ?>
    <div id="sidebar" class="offcanvas offcanvas-start offcanvas-lg sidebar" tabindex="-1" aria-labelledby="sidebarLabel">
        <div class="offcanvas-header d-lg-none">
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
        </div>

        <div class="offcanvas-body p-0">
            <nav class="sidebar-inner">
                <!-- ダッシュボード（サンプル） -->
                <!-- <p class="dashboard">
                    <a href="">
                        <i class="fa-solid fa-folder"></i>
                        ダッシュボード
                    </a>
                </p> -->

                <!-- ===================================================== -->
                <!-- 追加：業務メニュー（現行モジュール） -->
                <!-- ===================================================== -->

                <!-- 車両管理（cars） -->
                <?php if (Policies::menuVisible($me, 'cars')): ?>
                <p class="menu_link">
                    <a href="/cars">
                        <i class="fa-solid fa-car"></i>
                        車両管理
                    </a>
                </p>
                <?php endif; ?>

                <!-- 個人顧客管理（personal_customers） -->
                <?php if (Policies::menuVisible($me, 'personal_customers')): ?>
                <p class="menu_link">
                    <a href="/personal_customers">
                        <i class="fa-solid fa-user"></i>
                        個人顧客管理
                    </a>
                </p>
                <?php endif; ?>

                <!-- 法人顧客管理（office_customers） -->
                <?php if (Policies::menuVisible($me, 'office_customers')): ?>
                <p class="menu_link">
                    <a href="/office_customers">
                        <i class="fa-solid fa-building"></i>
                        法人顧客管理
                    </a>
                </p>
                <?php endif; ?>

                <!-- ★車両リース管理（car_leases） -->
                <?php if (Policies::menuVisible($me, 'car_leases')): ?>
                <p class="menu_link">
                    <a href="/car_leases/active">
                        <i class="fa-solid fa-file-contract"></i>
                        リース中一覧
                    </a>
                </p>
                <?php endif; ?>

                <!-- 監査ログ（audit） -->
                <!-- <?php if (Policies::menuVisible($me, 'audit')): ?>
                <p class="menu_link">
                    <a href="/audit">監査ログ</a>
                </p>
                <?php endif; ?> -->

                <!-- ログアウト（POST + CSRF） -->
                <form method="post" action="/logout" class="mt-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

                    <button type="submit" class="btn btn-logout w-100">
                        <i class="fa-solid fa-power-off me-2"></i>
                        ログアウト
                    </button>
                </form>

            </nav>
        </div>
    </div>
    <?php endif; ?>
    <!-- /左カラム -->

    <!-- メイン要素 -->
    <main>
      <?php if (!$me): ?>
        <?php
          // 未ログイン時：loginテンプレ（または $tpl 指定があればそれ）
          require __DIR__ . '/' . ($tpl ?? 'login') . '.php';
        ?>
      <?php else: ?>
        <?php
          // ログイン時：指定テンプレ
          require __DIR__ . '/' . $tpl . '.php';
        ?>
      <?php endif; ?>

      <p class="copyright">Copyright © </p>
    </main>
    <!-- /メイン要素 -->
  </div>
  <!-- /content_area -->

  <!-- JS（順序が重要） -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- DataTables v2 + Bootstrap5 -->
  <script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>

  <script>
    // DataTables のロード状況をログ出し（デバッグ用）
    console.log('[DT check] window.DataTable =', typeof window.DataTable);
    console.log('[DT check] jQuery.fn.DataTable =', (window.jQuery && typeof jQuery.fn.DataTable));
  </script>

  <?php /* stores依存コンポーネントの常時読み込みは削除 */ ?>
  <script src="/js/content/custom.js" defer></script>
</body>
</html>
