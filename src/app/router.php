<?php

/**
 * router.php
 * ============================================================
 * 役割:
 * - ルーティング（メソッド + パス）→ Controller@method にディスパッチ
 *
 * 改良点:
 * - PHP8以降の予約語「match」と衝突しないよう、関数名を matchRoute にする
 * - 404は Response に寄せて返却形式を統一する
 *
 * 安全側（追加）:
 * - /login, /logout 等の “状態変更POST” は原則 POST のまま（CSRFはController側）
 * - ルートパターンに正規表現メタが混ざっても暴れにくいよう、ルート文字列を preg_quote してから
 *   プレースホルダだけ置換する（将来の拡張事故防止）
 * - handler の explode 結果チェックを入れて、壊れた定義で fatal にならないようにする
 * - call_user_func_array の前に method_exists を確認（安全側）
 *
 * 注意:
 * - Controllerは明示 require_once（多重読み込み防止）
 *
 * 配置前提（K-Core正式）:
 * - このファイルは /app/router.php
 * - public/index.php から require される
 *
 * 変更（今回）:
 * - CarStatusController を追加
 * - 車両の通常状態変更ルートを追加
 *   - 代車へ変更
 *   - 在庫へ戻す
 *   - 廃車へ変更
 *   - 販売済へ変更
 */

require_once __DIR__ . '/bootstrap.php';

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ------------------------------------------------------------
// Controllers（基本モジュール）
// ------------------------------------------------------------
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/UserController.php';
require_once __DIR__ . '/Controllers/AuditController.php';

// ------------------------------------------------------------
// Controllers（追加モジュール）
// ------------------------------------------------------------
require_once __DIR__ . '/Controllers/CarController.php';
require_once __DIR__ . '/Controllers/CarBulkController.php';
require_once __DIR__ . '/Controllers/CarLeaseController.php';
require_once __DIR__ . '/Controllers/CarLeaseIndexController.php';
require_once __DIR__ . '/Controllers/CarCostController.php';
require_once __DIR__ . '/Controllers/CarProfitController.php';
require_once __DIR__ . '/Controllers/CarLeaseProfitController.php';
require_once __DIR__ . '/Controllers/CarLeaseHealthController.php';
require_once __DIR__ . '/Controllers/CarStatusController.php'; // ★今回追加
require_once __DIR__ . '/Controllers/OfficeCustomerController.php';
require_once __DIR__ . '/Controllers/PersonalCustomerController.php';

// ------------------------------------------------------------
// Controllers（Lookup系）
// ------------------------------------------------------------
require_once __DIR__ . '/Controllers/LesseeLookupController.php';

// ------------------------------------------------------------
// Route定義: [METHOD, PATH, 'Class@method']
// ------------------------------------------------------------
$routes = [
    // Auth
    ['GET',  '/',               'AuthController@loginForm'],
    ['POST', '/login',          'AuthController@login'],
    ['POST', '/logout',         'AuthController@logout'],

    // Users
    ['GET',  '/users',              'UserController@index'],
    ['GET',  '/users/create',       'UserController@create'],
    ['POST', '/users',              'UserController@store'],
    ['GET',  '/users/{id}',         'UserController@show'],
    ['GET',  '/users/{id}/edit',    'UserController@edit'],
    ['POST', '/users/{id}',         'UserController@update'],
    ['POST', '/users/{id}/delete',  'UserController@destroy'],
    ['GET',  '/api/users',          'UserController@datatable'],

    // Cars
    ['GET',  '/cars',                  'CarController@index'],
    ['GET',  '/cars/create',           'CarController@create'],
    ['POST', '/cars',                  'CarController@store'],
    ['GET',  '/cars/bulk',             'CarBulkController@index'],
    ['POST', '/api/cars/bulk-update',  'CarBulkController@bulkUpdate'],
    ['GET',  '/cars/{id}',             'CarController@show'],
    ['GET',  '/cars/{id}/edit',        'CarController@edit'],
    ['POST', '/cars/{id}',             'CarController@update'],
    ['POST', '/cars/{id}/delete',      'CarController@destroy'],
    ['GET',  '/api/cars',              'CarController@datatable'],

    // --------------------------------------------------------
    // ★Car Statuses（今回追加）
    // --------------------------------------------------------
    // 在庫 → 代車
    ['GET',  '/cars/{id}/status/loaner',        'CarStatusController@loanerForm'],
    ['POST', '/cars/{id}/status/loaner',        'CarStatusController@markLoaner'],

    // 代車 → 在庫
    ['GET',  '/cars/{id}/status/back_to_stock', 'CarStatusController@backToStockForm'],
    ['POST', '/cars/{id}/status/back_to_stock', 'CarStatusController@backToStock'],

    // 在庫 → 廃車
    ['GET',  '/cars/{id}/status/scrap',         'CarStatusController@scrapForm'],
    ['POST', '/cars/{id}/status/scrap',         'CarStatusController@scrap'],

    // 在庫 → 販売済
    ['GET',  '/cars/{id}/status/sell',          'CarStatusController@sellForm'],
    ['POST', '/cars/{id}/status/sell',          'CarStatusController@sell'],

    // --------------------------------------------------------
    // ★Car Leases
    // --------------------------------------------------------
    // リース中車両一覧（期間表示）
    ['GET',  '/car_leases/active',                   'CarLeaseIndexController@indexActive'],
    ['GET',  '/car_leases/export',                   'CarLeaseIndexController@exportCsv'],
    ['GET',  '/api/car_leases/active',               'CarLeaseIndexController@datatableActive'],

    // 個人／法人ごとのリース中車両一覧（現在＋年度ごとの過去）
    // - lessee_type: office | personal
    ['GET',  '/car_leases/lessees/{lessee_type}/{lessee_id}',     'CarLeaseIndexController@byLessee'],
    ['GET',  '/api/car_leases/lessees/{lessee_type}/{lessee_id}', 'CarLeaseIndexController@datatableByLessee'],

    // 車両ごとのリース詳細（現在＋年度ごとの過去）
    ['GET',  '/cars/{id}/leases',                    'CarLeaseController@showByCar'],

    // リース登録（導線：車両詳細／車両一覧→ /car_leases/create?car_id=xx）
    ['GET',  '/car_leases/create',                   'CarLeaseController@create'],
    ['POST', '/car_leases',                          'CarLeaseController@store'],

    // リース編集
    ['GET',  '/car_leases/{id}/edit',                'CarLeaseController@edit'],
    ['POST', '/car_leases/{id}',                     'CarLeaseController@update'],

    // リース強制終了
    ['GET',  '/car_leases/{id}/force_end',           'CarLeaseController@forceEndForm'],
    ['POST', '/car_leases/{id}/force_end',           'CarLeaseController@forceEnd'],

    // 以上チェック
    ['GET', '/car_leases/health', 'CarLeaseHealthController@index'],
    ['GET', '/api/car_leases/health', 'CarLeaseHealthController@datatable'],

    // --------------------------------------------------------
    // ★Car FY Costs ※過去年度の税/保険/経費編集
    // --------------------------------------------------------
    // 車両別 年度コスト一覧
    ['GET',  '/cars/{id}/fy_costs',                  'CarCostController@index'],

    // 年度別コスト編集（過去年度のみ）
    ['GET',  '/cars/{id}/fy_costs/{fy}/edit',        'CarCostController@edit'],
    ['POST', '/cars/{id}/fy_costs/{fy}',             'CarCostController@update'],

    // --------------------------------------------------------
    // ★Car Profits ※車両収支画面
    // --------------------------------------------------------
    // 全車両横断のリース収支集計
    ['GET',  '/car_leases/profit',                   'CarLeaseProfitController@index'],
    ['GET',  '/api/car_leases/profit/summary',       'CarLeaseProfitController@summary'],
    ['GET',  '/api/car_leases/profit/datatable',     'CarLeaseProfitController@datatable'],

    // 車両収支（導線：車両詳細→ /cars/{id}/profit?fy=2025 等）
    ['GET',  '/cars/{id}/profit',                    'CarProfitController@show'],

    // --------------------------------------------------------
    // ★Lessee lookup API
    // --------------------------------------------------------
    ['GET',  '/api/lessees',                         'LesseeLookupController@datatable'],
    ['GET',  '/api/lessees/resolve',                 'LesseeLookupController@resolve'],

    // Office Customers
    ['GET',  '/office_customers',              'OfficeCustomerController@index'],
    ['GET',  '/office_customers/create',       'OfficeCustomerController@create'],
    ['POST', '/office_customers',              'OfficeCustomerController@store'],
    ['GET',  '/office_customers/{id}',         'OfficeCustomerController@show'],
    ['GET',  '/office_customers/{id}/edit',    'OfficeCustomerController@edit'],
    ['POST', '/office_customers/{id}',         'OfficeCustomerController@update'],
    ['POST', '/office_customers/{id}/delete',  'OfficeCustomerController@destroy'],
    ['GET',  '/api/office_customers',          'OfficeCustomerController@datatable'],

    // Personal Customers
    ['GET',  '/personal_customers',              'PersonalCustomerController@index'],
    ['GET',  '/personal_customers/create',       'PersonalCustomerController@create'],
    ['POST', '/personal_customers',              'PersonalCustomerController@store'],
    ['GET',  '/personal_customers/{id}',         'PersonalCustomerController@show'],
    ['GET',  '/personal_customers/{id}/edit',    'PersonalCustomerController@edit'],
    ['POST', '/personal_customers/{id}',         'PersonalCustomerController@update'],
    ['POST', '/personal_customers/{id}/delete',  'PersonalCustomerController@destroy'],
    ['GET',  '/api/personal_customers',          'PersonalCustomerController@datatable'],

    // Audit
    ['GET',  '/audit',                  'AuditController@index'],
    ['GET',  '/api/audit',              'AuditController@datatable'],
    ['GET',  '/api/audit/{id}/details', 'AuditController@details'],
];

/**
 * ルートマッチング
 * - {id} 等のプレースホルダは「/を含まない1セグメント」として扱う
 * - 戻り値: false または [handler, params[]]
 *
 * 安全側:
 * - ルート文字列を preg_quote してから {xxx} をキャプチャに置換する
 *   （将来、ルートに '.' などが入った場合の意図しない正規表現化を防ぐ）
 */
function matchRoute(array $r, string $m, string $p)
{
    if (($r[0] ?? '') !== $m) return false;

    $routePath = (string)($r[1] ?? '');
    if ($routePath === '') return false;

    $quoted = preg_quote($routePath, '#');
    $pat = preg_replace('#\\\\\{[^/]+\\\\\}#', '([^/]+)', $quoted);
    if ($pat === null) return false;

    if (preg_match('#^' . $pat . '$#', $p, $ms)) {
        array_shift($ms);
        return [$r[2] ?? '', $ms];
    }

    return false;
}

// ------------------------------------------------------------
// ディスパッチ
// ------------------------------------------------------------
foreach ($routes as $r) {
    $hit = matchRoute($r, $method, $path);
    if ($hit !== false) {
        [$handler, $params] = $hit;

        $handler = (string)$handler;
        if ($handler === '' || strpos($handler, '@') === false) {
            Response::fail('SERVER_ERROR', 'Invalid route handler.', 500);
            exit;
        }

        [$class, $func] = explode('@', $handler, 2);

        if ($class === '' || $func === '' || !class_exists($class) || !method_exists($class, $func)) {
            Response::fail('SERVER_ERROR', 'Route target not found.', 500);
            exit;
        }

        call_user_func_array([new $class, $func], $params);
        exit;
    }
}

Response::notFound();