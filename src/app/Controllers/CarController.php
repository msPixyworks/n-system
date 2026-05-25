<?php

/**
 * CarController.php
 * ============================================================
 * 役割:
 * - 車両管理（cars）の CRUD + DataTables API
 *
 * 方針（K-Core / users 完全同期）:
 * - 権限ガードは Policies::guardView/guardEdit
 * - 未ログイン時は、画面は / にリダイレクト、APIは 401 JSON（Response::fail）
 * - DataTables の操作列は編集権限がある人だけ表示
 * - 監査は全カラム（create/update/delete）
 * - destroy は論理削除（deleted_at セット）
 * - update は diff が空なら監査しない（ノイズ抑制）
 *
 * 重要（管理番号）:
 * - DBトリガで cars を UPDATE すると MySQL #1442 が出るため、
 *   採番はアプリ側で「INSERT後に lastInsertId → UPDATE」で行う。
 *
 * 変更（今回）:
 * - 車両状態変更機能の追加に備え、
 *   status_code / manual_status_code / current_lease_id を監査対象へ追加
 * - 通常の車両編集画面では manual_status_code は編集させない
 *   （状態変更は専用の CarStatusController で行う）
 *
 * 追加修正（今回）:
 * - 一覧サマリーに「在庫」を追加
 * - 一覧絞り込みの「ID」を廃止
 */

class CarController
{
    /**
     * 監査対象フィールド（全カラム）
     * - SQL定義（cars）と同期させる
     *
     * 変更（今回）:
     * - status_code
     * - manual_status_code
     * - current_lease_id
     * を監査対象に追加
     */
    private array $fields = [
        // 車検証情報
        'management_number',
        'vehicle_number',
        'chassis_number',

        // 登録年月日（年/月/日）
        'registration_year',
        'registration_month',
        'registration_day',

        // 初年度登録年月（年/月）
        'first_registration_year',
        'first_registration_month',

        'maker',
        'car_model',

        'model_designation_number',
        'classification_division_number',

        'type_of_car',
        'car_purpose',
        'how_to_use',

        // --------------------------------------------------------
        // 状態関連
        // --------------------------------------------------------
        'status_code',
        'manual_status_code',
        'current_lease_id',

        'body_shape',

        'vehicle_weight',
        'displacement',

        // 取引情報
        'new_used',

        // 購入時期（年/月/日）
        'purchase_year',
        'purchase_month',
        'purchase_day',

        'purchase_price',
        'consumption_tax',
        'recycling_cost',
        'purchase_costs',
        'car_tax',
        'car_insurance_premium',
        'total_expenses',

        // 車両情報
        'model_year',
        'mileage_amount',
        'base_price',
        'total_to_pay',

        'one_owner',
        'camper',
        'repair_history',
        'vehicle_inspection',
        'record_book',

        'new_car_property',
        'non_smoking',
        'officially_imported_car',
        'is_recycling_fee',
        'registered_unused_car',
        'test_car',
        'rental_up',

        'body_type',
        'body_color',

        'color_code',
        'key_number',
        'doors',
        'passengers',
        'handle',

        'engine_type',
        'supercharger_settings',
        'drive_system',

        'welfare_vehicles',
        'eco_car',

        'chassis_number_suffix',
        'legal_maintenance',
        'guarantee',

        // 安全装備（0未選択/1あり/2なし）
        'power_steering',
        'abs',
        'support_car',
        'collision_damage',
        'adaptive_cruise_control',
        'lane_keep_assist',
        'parking_assist',
        'accidental_start_prevention_device',
        'obstacle_sensor',
        'airbag_driver_seat',
        'airbag_passenger_seat',
        'airbag_side',
        'airbag',
        'neck_shock_mitigation_headrest',
        'all_around_camera',
        'camera_back',
        'monitor_blind_spot',
        'monitor',
        'anti_skid_device',
        'hill_descent_control',
        'idling_stop',
        'anti_theft_device',
        'automatic_high_beam',

        // 快適装備（0未選択/1該当/2非該当）
        'air_conditioner_cooler',
        'seat_air_conditioner',
        'seat_heater',
        'w_air_conditioner',
        'hdd_car_navigation',
        'dvd_car_navigation',
        'tv',
        'picture',
        'music_server',
        'is_music_player',
        'etc',
        'power_supply',
        'drive_recorder',
        'display_audio',
        'rear_seat_monitor',
        'ottoman',

        // インテリア
        'keyless',
        'smart_key',
        'power_window',
        'sunroof_glassroof',
        'bench_seat',
        'walk_through',
        'three_row_seats',
        'electric_seat',
        'full_flat_seat',
        'genuine_leather_seat',

        // エクステリア
        'slide_door',
        'electric_rear_gate',
        'hid_led',
        'front_fog_lamp',
        'full_aero',
        'low_down',
        'lift_up',
        'air_suspension',
        'aluminum_wheel',
        'roof_rail',
        'cold_region_specification',
        'all_painted',

        // 運用
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
    // Pages
    // ============================================================
    public function index(): void
    {
        $me = $this->guardView();
        Response::view('cars/index', ['title' => '車両一覧', 'me' => $me]);
    }

    public function create(): void
    {
        $me = $this->guardEdit();
        $cfg = require __DIR__ . '/../config.php';

        Response::view('cars/form', [
            'title'  => '車両登録',
            'cfg'    => $cfg,
            'item'   => null,
            'errors' => [],
            'old'    => [],
            'me'     => $me,
        ]);
    }

    public function show($id): void
    {
        $me = $this->guardView();

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('cars/show', [
            'title' => '車両詳細',
            'cfg'   => $cfg,
            'item'  => $item,
            'me'    => $me,
        ]);
    }

    public function edit($id): void
    {
        $me = $this->guardEdit();

        // ★変更：論理削除済みは編集不可
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $st->execute([':i' => (int)$id]);
        $item = $st->fetch();

        if (!$item) {
            Response::notFound();
        }

        $cfg = require __DIR__ . '/../config.php';
        Response::view('cars/form', [
            'title'  => '車両編集',
            'cfg'    => $cfg,
            'item'   => $item,
            'errors' => [],
            'old'    => [],
            'me'     => $me,
        ]);
    }

    // ============================================================
    // DataTables API
    // ============================================================
    public function datatable(): void
    {
        $me = $this->guardView();

        try {
            $pdo = Db::pdo();
            $cfg = require __DIR__ . '/../config.php';

            $draw  = (int)($_GET['draw'] ?? 0);
            $start = max(0, (int)($_GET['start'] ?? 0));

            $len = isset($_GET['length']) ? (int)$_GET['length'] : 200;
            $len = max(1, min(200, $len));

            // フィルタ（グローバル検索なし）
            $f_vehicle_number  = trim((string)($_GET['f_vehicle_number'] ?? ''));
            $f_chassis_number  = trim((string)($_GET['f_chassis_number'] ?? ''));
            $f_maker           = trim((string)($_GET['f_maker'] ?? ''));
            $f_car_model       = trim((string)($_GET['f_car_model'] ?? ''));
            $f_status          = trim((string)($_GET['f_status'] ?? ''));
            $f_include_deleted = trim((string)($_GET['f_include_deleted'] ?? ''));

            $where  = [];
            $params = [];

            if ($f_vehicle_number !== '') {
                $where[] = "vehicle_number LIKE :fvn";
                $params[':fvn'] = "%{$f_vehicle_number}%";
            }
            if ($f_chassis_number !== '') {
                $where[] = "chassis_number LIKE :fcn";
                $params[':fcn'] = "%{$f_chassis_number}%";
            }
            if ($f_maker !== '') {
                $where[] = "maker = :fmk";
                $params[':fmk'] = $f_maker;
            }
            if ($f_car_model !== '') {
                $where[] = "car_model = :fcm";
                $params[':fcm'] = $f_car_model;
            }
            if ($f_status !== '' && ctype_digit($f_status)) {
                $statusCode = (int)$f_status;
                if (in_array($statusCode, [1, 2, 3, 4, 5, 6], true)) {
                    $where[] = "status_code = :fst";
                    $params[':fst'] = $statusCode;
                }
            }

            // 論理削除除外（デフォルト）
            $includeDeleted = ($f_include_deleted === '1');
            if (!$includeDeleted) {
                $where[] = "deleted_at IS NULL";
            }

            $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

            // columns (JS):
            //   0: No（連番・ソート対象外）
            //   1: vehicle_number
            //   2: chassis_number
            //   3: maker
            //   4: car_model
            //   5: 操作（canEdit時のみ、ソート対象外）
            $colMap = [
                1 => 'vehicle_number',
                2 => 'chassis_number',
                3 => 'maker',
                4 => 'car_model',
            ];

            $orderByParts = [];
            if (isset($_GET['order']) && is_array($_GET['order'])) {
                $count = 0;
                foreach ($_GET['order'] as $ord) {
                    if ($count >= 3) {
                        break;
                    }
                    $colIdx = isset($ord['column']) ? (int)$ord['column'] : null;
                    $dir    = strtolower((string)($ord['dir'] ?? ''));
                    if (!isset($colMap[$colIdx])) {
                        continue;
                    }
                    $dir = ($dir === 'asc' ? 'ASC' : ($dir === 'desc' ? 'DESC' : 'ASC'));
                    $orderByParts[] = $colMap[$colIdx] . ' ' . $dir;
                    $count++;
                }
            }
            if (!$orderByParts) {
                $orderByParts[] = 'id DESC';
            }
            $orderBySql = ' ORDER BY ' . implode(', ', $orderByParts);

            // recordsTotal は一覧の基準に合わせる
            if (!$includeDeleted) {
                $stt = $pdo->prepare("SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL");
                $stt->execute();
                $total = (int)$stt->fetchColumn();
            } else {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
            }

            $stc = $pdo->prepare("SELECT COUNT(*) FROM cars{$wsql}");
            foreach ($params as $k => $v) {
                $stc->bindValue($k, $v);
            }
            $stc->execute();
            $filtered = (int)$stc->fetchColumn();

            $limitClause = " LIMIT {$start}, {$len}";

            $sql = "SELECT
                        id,
                        vehicle_number,
                        chassis_number,
                        maker,
                        car_model,
                        deleted_at,
                        status_code,
                        manual_status_code,
                        current_lease_id
                    FROM cars{$wsql}{$orderBySql}{$limitClause}";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $rows = $st->fetchAll() ?: [];

            // --------------------------------------------------------
            // サマリー
            // - 一覧上部表示用
            // - 未削除のみを対象
            // --------------------------------------------------------
            $summarySql = "
                SELECT
                    SUM(CASE WHEN status_code = 1 THEN 1 ELSE 0 END) AS stock,
                    SUM(CASE WHEN status_code = 2 THEN 1 ELSE 0 END) AS leasing,
                    SUM(CASE WHEN status_code = 6 THEN 1 ELSE 0 END) AS scheduled,
                    SUM(CASE WHEN status_code = 4 THEN 1 ELSE 0 END) AS loaner,
                    SUM(CASE WHEN status_code = 3 THEN 1 ELSE 0 END) AS sold,
                    SUM(CASE WHEN status_code = 5 THEN 1 ELSE 0 END) AS scrap
                FROM cars
                WHERE deleted_at IS NULL
            ";
            $stSum = $pdo->query($summarySql);
            $summary = $stSum->fetch() ?: [
                'stock'     => 0,
                'leasing'   => 0,
                'scheduled' => 0,
                'loaner'    => 0,
                'sold'      => 0,
                'scrap'     => 0,
            ];

            $canEdit = Policies::canEditCars($me);
            $canViewLeases = Policies::canViewCarLeases($me);
            $canEditLeases = Policies::canEditCarLeases($me);
            $canViewCosts  = Policies::canViewCarFyCosts($me);
            $canViewProfit = Policies::canViewCarProfits($me);

            $optsCars = $cfg['options']['cars'] ?? [];
            $makerLabels = $optsCars['maker'] ?? [];
            $modelFlat   = $optsCars['car_models_flat'] ?? [];

            $data = [];
            $idx = 0;

            foreach ($rows as $r) {
                $idx++;
                $id = (int)$r['id'];

                $makerKey  = (string)($r['maker'] ?? '');
                $makerText = $makerKey !== '' ? (string)($makerLabels[$makerKey] ?? $makerKey) : '';

                $modelKey  = (string)($r['car_model'] ?? '');
                $modelText = $modelKey !== '' ? (string)($modelFlat[$modelKey] ?? $modelKey) : '';

                $isLeasing = false;
                if (!empty($r['current_lease_id'])) {
                    $isLeasing = true;
                } else {
                    $isLeasing = in_array((int)($r['status_code'] ?? 0), [2, 6], true);
                }

                $buttons = '';
                if ($canEdit) {
                    $menuItems = [];

                    $menuItems[] = '<li><a class="dropdown-item" href="/cars/' . $id . '/edit">編集</a></li>';

                    if ($canViewLeases) {
                        $menuItems[] = '<li><a class="dropdown-item" href="/cars/' . $id . '/leases">リース</a></li>';
                    }
                    if ($canViewCosts) {
                        $menuItems[] = '<li><a class="dropdown-item" href="/cars/' . $id . '/fy_costs">年度コスト</a></li>';
                    }
                    if ($canViewProfit) {
                        $menuItems[] = '<li><a class="dropdown-item" href="/cars/' . $id . '/profit">収支</a></li>';
                    }
                    if ($canEditLeases && !$isLeasing && empty($r['deleted_at'])) {
                        $menuItems[] = '<li><a class="dropdown-item text-success" href="/car_leases/create?car_id=' . $id . '">リース登録</a></li>';
                    }

                    $buttons = '<div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap text-nowrap">';
                    $buttons .= '<a class="btn btn-sm btn-outline-primary text-nowrap" href="/cars/' . $id . '">詳細</a>';

                    if (!empty($menuItems)) {
                        $buttons .= '
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    その他
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">'
                                    . implode('', $menuItems) .
                                '</ul>
                            </div>';
                    }

                    $buttons .= '</div>';
                }

                $no = $start + $idx;

                $row = [
                    (int)$no,
                    htmlspecialchars((string)($r['vehicle_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string)($r['chassis_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($makerText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($modelText, ENT_QUOTES, 'UTF-8'),
                ];

                if ($canEdit) {
                    $row[] = $buttons;
                }

                $data[] = $row;
            }

            Response::json([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $filtered,
                'data'            => $data,
                'summary'         => [
                    'stock'     => (int)($summary['stock'] ?? 0),
                    'leasing'   => (int)($summary['leasing'] ?? 0),
                    'scheduled' => (int)($summary['scheduled'] ?? 0),
                    'loaner'    => (int)($summary['loaner'] ?? 0),
                    'sold'      => (int)($summary['sold'] ?? 0),
                    'scrap'     => (int)($summary['scrap'] ?? 0),
                ],
            ]);
        } catch (Throwable $e) {
            Response::json([
                'error'   => 'SERVER_ERROR',
                'message' => 'Unexpected error in datatable endpoint.',
            ], 500);
        }
    }

    // ============================================================
    // Validation
    // ============================================================
    private function validate(array $in, ?int $id = null): array
    {
        $v = new Validation();
        $cfg = require __DIR__ . '/../config.php';

        $optsCars = $cfg['options']['cars'] ?? [];
        $optsCommon = $cfg['options']['common'] ?? [];

        $in['management_number'] = trim((string)($in['management_number'] ?? ''));
        $in['vehicle_number'] = trim((string)($in['vehicle_number'] ?? ''));

        $in['chassis_number'] = trim((string)($in['chassis_number'] ?? ''));
        if ($in['chassis_number'] === '') {
            $v->add('chassis_number', '車台番号は必須です。');
        }

        $toInt = fn($x) => (int)preg_replace('/[^0-9]/', '', (string)$x);

        $in['registration_year']  = $toInt($in['registration_year'] ?? 0);
        $in['registration_month'] = $toInt($in['registration_month'] ?? 0);
        $in['registration_day']   = $toInt($in['registration_day'] ?? 0);

        $in['purchase_year']  = $toInt($in['purchase_year'] ?? 0);
        $in['purchase_month'] = $toInt($in['purchase_month'] ?? 0);
        $in['purchase_day']   = $toInt($in['purchase_day'] ?? 0);

        $in['first_registration_year']  = $toInt($in['first_registration_year'] ?? 0);
        $in['first_registration_month'] = $toInt($in['first_registration_month'] ?? 0);

        $checkYmd = function (string $prefix) use (&$in, $v) {
            $y = (int)$in["{$prefix}_year"];
            $m = (int)$in["{$prefix}_month"];
            $d = (int)$in["{$prefix}_day"];

            $all0 = ($y === 0 && $m === 0 && $d === 0);
            if ($all0) {
                return;
            }

            if ($y === 0 || $m === 0 || $d === 0) {
                $v->add("{$prefix}_year", '年月日は年/月/日をすべて選択してください。');
                return;
            }

            if (!checkdate($m, $d, $y)) {
                $v->add("{$prefix}_year", '存在しない日付です。');
            }
        };

        $checkYmd('registration');
        $checkYmd('purchase');

        $fy = (int)$in['first_registration_year'];
        $fm = (int)$in['first_registration_month'];
        $all0 = ($fy === 0 && $fm === 0);
        if (!$all0 && ($fy === 0 || $fm === 0)) {
            $v->add('first_registration_year', '初年度登録年月は年/月をすべて選択してください。');
        }
        if ($fy !== 0) {
            if ($fm < 1 || $fm > 12) {
                $v->add('first_registration_month', '初年度登録月が不正です。');
            }
        }

        $in['maker'] = trim((string)($in['maker'] ?? ''));
        if ($in['maker'] !== '' && $in['maker'] !== '0') {
            if (!isset(($optsCars['maker'] ?? [])[$in['maker']])) {
                $v->add('maker', 'メーカーを正しく選択してください。');
            }
        } else {
            $in['maker'] = null;
        }

        $in['car_model'] = trim((string)($in['car_model'] ?? ''));
        if ($in['car_model'] !== '' && $in['car_model'] !== '0') {
            $byMaker = $optsCars['car_models_by_maker'] ?? [];
            if ($in['maker'] !== null && isset($byMaker[$in['maker']])) {
                if (!isset($byMaker[$in['maker']][$in['car_model']])) {
                    $v->add('car_model', '車種を正しく選択してください。');
                }
            } else {
                $flat = $optsCars['car_models_flat'] ?? [];
                if (!isset($flat[$in['car_model']])) {
                    $v->add('car_model', '車種を正しく選択してください。');
                }
            }
        } else {
            $in['car_model'] = null;
        }

        $toIntOrNull = function ($x): ?int {
            $s = trim((string)$x);
            if ($s === '') {
                return null;
            }
            $s = str_replace(',', '', $s);
            $s = preg_replace('/[^0-9]/', '', $s);
            if ($s === '') {
                return null;
            }
            return (int)$s;
        };

        $toTiny = fn($x) => max(0, min(255, (int)$x));

        $in['model_designation_number'] = trim((string)($in['model_designation_number'] ?? ''));
        $in['classification_division_number'] = trim((string)($in['classification_division_number'] ?? ''));

        $in['type_of_car'] = $toTiny($in['type_of_car'] ?? 0);
        $in['car_purpose'] = $toTiny($in['car_purpose'] ?? 0);
        $in['how_to_use']  = $toTiny($in['how_to_use'] ?? 0);
        $in['body_shape']  = $toTiny($in['body_shape'] ?? 0);

        $in['vehicle_weight'] = $toIntOrNull($in['vehicle_weight'] ?? null);
        $in['displacement']   = $toIntOrNull($in['displacement'] ?? null);

        $in['new_used'] = $toTiny($in['new_used'] ?? 0);

        $in['purchase_price']        = $toIntOrNull($in['purchase_price'] ?? null);
        $in['consumption_tax']       = $toIntOrNull($in['consumption_tax'] ?? null);
        $in['recycling_cost']        = $toIntOrNull($in['recycling_cost'] ?? null);
        $in['purchase_costs']        = $toIntOrNull($in['purchase_costs'] ?? null);
        $in['car_tax']               = $toIntOrNull($in['car_tax'] ?? null);
        $in['car_insurance_premium'] = $toIntOrNull($in['car_insurance_premium'] ?? null);
        $in['total_expenses']        = $toIntOrNull($in['total_expenses'] ?? null);

        $in['model_year']     = $toInt($in['model_year'] ?? 0);
        $in['mileage_amount'] = $toIntOrNull($in['mileage_amount'] ?? null);
        $in['base_price']     = $toIntOrNull($in['base_price'] ?? null);
        $in['total_to_pay']   = $toIntOrNull($in['total_to_pay'] ?? null);

        $in['one_owner']          = $toTiny($in['one_owner'] ?? 0);
        $in['camper']             = $toTiny($in['camper'] ?? 0);
        $in['repair_history']     = $toTiny($in['repair_history'] ?? 0);
        $in['vehicle_inspection'] = $toTiny($in['vehicle_inspection'] ?? 0);
        $in['record_book']        = $toTiny($in['record_book'] ?? 0);

        $in['new_car_property']        = $toTiny($in['new_car_property'] ?? 0);
        $in['non_smoking']             = $toTiny($in['non_smoking'] ?? 0);
        $in['officially_imported_car'] = $toTiny($in['officially_imported_car'] ?? 0);
        $in['is_recycling_fee']        = $toTiny($in['is_recycling_fee'] ?? 0);
        $in['registered_unused_car']   = $toTiny($in['registered_unused_car'] ?? 0);
        $in['test_car']                = $toTiny($in['test_car'] ?? 0);
        $in['rental_up']               = $toTiny($in['rental_up'] ?? 0);

        $in['body_type']  = $toTiny($in['body_type'] ?? 0);
        $in['body_color'] = $toTiny($in['body_color'] ?? 0);

        $in['color_code'] = trim((string)($in['color_code'] ?? ''));
        $in['key_number'] = trim((string)($in['key_number'] ?? ''));

        $in['doors']      = $toTiny($in['doors'] ?? 0);
        $in['passengers'] = $toTiny($in['passengers'] ?? 0);
        $in['handle']     = $toTiny($in['handle'] ?? 0);

        $in['engine_type']           = $toTiny($in['engine_type'] ?? 0);
        $in['supercharger_settings'] = $toTiny($in['supercharger_settings'] ?? 0);
        $in['drive_system']          = $toTiny($in['drive_system'] ?? 0);

        $in['welfare_vehicles'] = $toTiny($in['welfare_vehicles'] ?? 0);
        $in['eco_car']          = $toTiny($in['eco_car'] ?? 0);

        $in['chassis_number_suffix'] = trim((string)($in['chassis_number_suffix'] ?? ''));
        $in['legal_maintenance']     = (string)($in['legal_maintenance'] ?? '');
        $in['guarantee']             = (string)($in['guarantee'] ?? '');

        $yesNoFields = array_keys($optsCars['safety_yes_no_fields'] ?? []);
        foreach ($yesNoFields as $f) {
            $in[$f] = $toTiny($in[$f] ?? 0);
        }

        $comfortFields = array_keys($optsCars['comfort_applicable_fields'] ?? []);
        foreach ($comfortFields as $f) {
            $in[$f] = $toTiny($in[$f] ?? 0);
        }

        $moreTiny = [
            'keyless', 'smart_key', 'power_window', 'sunroof_glassroof', 'bench_seat', 'walk_through', 'three_row_seats',
            'electric_seat', 'full_flat_seat', 'genuine_leather_seat',
            'slide_door', 'electric_rear_gate', 'hid_led', 'front_fog_lamp', 'full_aero', 'low_down', 'lift_up',
            'air_suspension', 'aluminum_wheel', 'roof_rail', 'cold_region_specification', 'all_painted',
        ];
        foreach ($moreTiny as $f) {
            $in[$f] = $toTiny($in[$f] ?? 0);
        }

        if (!$v->ok()) {
            return ['ok' => false, 'errors' => $v->errors, 'in' => $in];
        }

        $save = [
            'management_number' => ($in['management_number'] !== '') ? $in['management_number'] : null,
            'vehicle_number'    => ($in['vehicle_number'] !== '') ? $in['vehicle_number'] : null,
            'chassis_number'    => $in['chassis_number'],

            'registration_year'  => (int)$in['registration_year'],
            'registration_month' => (int)$in['registration_month'],
            'registration_day'   => (int)$in['registration_day'],

            'first_registration_year'  => (int)$in['first_registration_year'],
            'first_registration_month' => (int)$in['first_registration_month'],

            'maker'     => $in['maker'],
            'car_model' => $in['car_model'],

            'model_designation_number'       => ($in['model_designation_number'] !== '') ? $in['model_designation_number'] : null,
            'classification_division_number' => ($in['classification_division_number'] !== '') ? $in['classification_division_number'] : null,

            'type_of_car' => (int)$in['type_of_car'],
            'car_purpose' => (int)$in['car_purpose'],
            'how_to_use'  => (int)$in['how_to_use'],

            // ----------------------------------------------------
            // 状態関連は通常編集フォームでは更新しない
            // - status_code / manual_status_code / current_lease_id は
            //   専用機能（リース処理 / ステータス変更処理）で更新する
            // ----------------------------------------------------

            'body_shape'  => (int)$in['body_shape'],

            'vehicle_weight' => $in['vehicle_weight'],
            'displacement'   => $in['displacement'],

            'new_used' => (int)$in['new_used'],

            'purchase_year'  => (int)$in['purchase_year'],
            'purchase_month' => (int)$in['purchase_month'],
            'purchase_day'   => (int)$in['purchase_day'],

            'purchase_price'        => $in['purchase_price'],
            'consumption_tax'       => $in['consumption_tax'],
            'recycling_cost'        => $in['recycling_cost'],
            'purchase_costs'        => $in['purchase_costs'],
            'car_tax'               => $in['car_tax'],
            'car_insurance_premium' => $in['car_insurance_premium'],
            'total_expenses'        => $in['total_expenses'],

            'model_year'     => (int)$in['model_year'],
            'mileage_amount' => $in['mileage_amount'],
            'base_price'     => $in['base_price'],
            'total_to_pay'   => $in['total_to_pay'],

            'one_owner'          => (int)$in['one_owner'],
            'camper'             => (int)$in['camper'],
            'repair_history'     => (int)$in['repair_history'],
            'vehicle_inspection' => (int)$in['vehicle_inspection'],
            'record_book'        => (int)$in['record_book'],

            'new_car_property'        => (int)$in['new_car_property'],
            'non_smoking'             => (int)$in['non_smoking'],
            'officially_imported_car' => (int)$in['officially_imported_car'],
            'is_recycling_fee'        => (int)$in['is_recycling_fee'],
            'registered_unused_car'   => (int)$in['registered_unused_car'],
            'test_car'                => (int)$in['test_car'],
            'rental_up'               => (int)$in['rental_up'],

            'body_type'  => (int)$in['body_type'],
            'body_color' => (int)$in['body_color'],

            'color_code' => ($in['color_code'] !== '') ? $in['color_code'] : null,
            'key_number' => ($in['key_number'] !== '') ? $in['key_number'] : null,

            'doors'      => (int)$in['doors'],
            'passengers' => (int)$in['passengers'],
            'handle'     => (int)$in['handle'],

            'engine_type'           => (int)$in['engine_type'],
            'supercharger_settings' => (int)$in['supercharger_settings'],
            'drive_system'          => (int)$in['drive_system'],

            'welfare_vehicles' => (int)$in['welfare_vehicles'],
            'eco_car'          => (int)$in['eco_car'],

            'chassis_number_suffix' => ($in['chassis_number_suffix'] !== '') ? $in['chassis_number_suffix'] : null,
            'legal_maintenance'     => ($in['legal_maintenance'] !== '') ? $in['legal_maintenance'] : null,
            'guarantee'             => ($in['guarantee'] !== '') ? $in['guarantee'] : null,
        ];

        foreach (array_merge($yesNoFields, $comfortFields, $moreTiny) as $f) {
            $save[$f] = (int)$in[$f];
        }

        return ['ok' => true, 'data' => $save, 'in' => $in];
    }

    // ============================================================
    // Create
    // ============================================================
    public function store(): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $res = $this->validate($_POST, null);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('cars/form', [
                'title'  => '車両登録',
                'cfg'    => $cfg,
                'item'   => null,
                'errors' => $res['errors'],
                'old'    => $res['in'],
                'me'     => $me,
            ]);
            return;
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $data = $res['data'];

            // --------------------------------------------------------
            // 新規登録時の初期状態
            // - 通常状態は在庫(1)
            // - 最終状態 status_code も在庫(1)
            // - current_lease_id は NULL
            // --------------------------------------------------------
            $data['status_code'] = 1;
            $data['manual_status_code'] = 1;
            $data['current_lease_id'] = null;

            $cols = array_keys($data);
            $sql = "INSERT INTO cars (" . implode(',', $cols) . ") VALUES (" . implode(',', array_map(fn($c) => ":$c", $cols)) . ")";
            $st = $pdo->prepare($sql);

            $bind = [];
            foreach ($cols as $c) {
                $bind[":$c"] = $data[$c];
            }
            $st->execute($bind);

            $id = (int)$pdo->lastInsertId();

            $stUp = $pdo->prepare("
                UPDATE cars
                SET management_number = CONCAT('CAR-', LPAD(id, 9, '0'))
                WHERE id = :i AND (management_number IS NULL OR management_number = '')
            ");
            $stUp->execute([':i' => $id]);

            $pdo->commit();

            $st2 = $pdo->prepare("SELECT * FROM cars WHERE id = :i LIMIT 1");
            $st2->execute([':i' => $id]);
            $new = $st2->fetch() ?: [];

            $diff = Audit::diff([], $new, $this->fields);
            Audit::log('cars', 'Car', $id, 'create', (int)$me['id'], $diff);

            Response::redirect('/cars/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('cars/form', [
                'title'  => '車両登録',
                'cfg'    => $cfg,
                'item'   => null,
                'errors' => ['__global' => ['保存に失敗しました。']],
                'old'    => $res['in'] ?? $_POST,
                'me'     => $me,
            ]);
        }
    }

    // ============================================================
    // Update
    // ============================================================
    public function update($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();

        // ★変更：論理削除済みは更新不可
        $st = $pdo->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        $res = $this->validate($_POST, $id);
        $cfg = require __DIR__ . '/../config.php';

        if (!$res['ok']) {
            Response::view('cars/form', [
                'title'  => '車両編集',
                'cfg'    => $cfg,
                'item'   => $old,
                'errors' => $res['errors'],
                'old'    => $res['in'],
                'me'     => $me,
            ]);
            return;
        }

        $data = $res['data'];
        $cols = array_keys($data);
        $sets = implode(',', array_map(fn($c) => "$c=:$c", $cols));
        $data['id'] = $id;

        $pdo->beginTransaction();

        try {
            $st2 = $pdo->prepare("UPDATE cars SET {$sets} WHERE id = :id");

            foreach ($data as $k => $v) {
                $type = match (true) {
                    is_int($v)  => PDO::PARAM_INT,
                    is_null($v) => PDO::PARAM_NULL,
                    default     => PDO::PARAM_STR,
                };
                $st2->bindValue(':' . $k, $v, $type);
            }

            $st2->execute();
            $pdo->commit();

            $st3 = $pdo->prepare("SELECT * FROM cars WHERE id = :i LIMIT 1");
            $st3->execute([':i' => $id]);
            $new = $st3->fetch() ?: [];

            $diff = Audit::diff($old, $new, $this->fields);
            if (!empty($diff)) {
                Audit::log('cars', 'Car', $id, 'update', (int)$me['id'], $diff);
            }

            Response::redirect('/cars/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();

            Response::view('cars/form', [
                'title'  => '車両編集',
                'cfg'    => $cfg,
                'item'   => $old,
                'errors' => ['__global' => ['更新に失敗しました。']],
                'old'    => $_POST,
                'me'     => $me,
            ]);
        }
    }

    // ============================================================
    // Logical delete
    // ============================================================
    public function destroy($id): void
    {
        $me = $this->guardEdit();
        Csrf::check($_POST['_token'] ?? null);

        $id = (int)$id;
        $pdo = Db::pdo();

        // ★変更：論理削除済みは削除処理対象外
        $st = $pdo->prepare("SELECT * FROM cars WHERE id = :i AND deleted_at IS NULL LIMIT 1");
        $st->execute([':i' => $id]);
        $old = $st->fetch();
        if (!$old) {
            Response::notFound();
        }

        $pdo->beginTransaction();
        try {
            $now = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');

            $st2 = $pdo->prepare("UPDATE cars SET deleted_at = :d WHERE id = :i");
            $st2->execute([':d' => $now, ':i' => $id]);

            $pdo->commit();

            $st3 = $pdo->prepare("SELECT * FROM cars WHERE id = :i LIMIT 1");
            $st3->execute([':i' => $id]);
            $new = $st3->fetch() ?: array_merge($old, ['deleted_at' => $now]);

            $diff = Audit::diff($old, $new, $this->fields);
            Audit::log('cars', 'Car', $id, 'delete', (int)$me['id'], $diff);

            Response::redirect('/cars');
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::fail('SERVER_ERROR', '削除に失敗しました。', 500);
        }
    }
}