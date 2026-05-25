<?php
/**
 * car_config.php
 * ============================================================
 * 車両管理（cars）専用 設定
 * - 画面選択肢（options）
 * - 監査ログ値変換（audit_value_labels）
 * - 監査ログフィールド表示名（audit_fields）
 *
 * 依存:
 * - app/config/car_models.php
 * - app/config/car_audit_fields.php
 */

// ------------------------------------------------------------
// 日付select（年/月/日）用 選択肢生成
// - 年の範囲は運用で調整（例：1980〜(今年+5)）
// ------------------------------------------------------------
$date_year_from = 1980;
$date_year_to   = (int)date('Y') + 5;

$date_years = [0 => '-- Please select --'];
for ($y = $date_year_from; $y <= $date_year_to; $y++) {
    $date_years[$y] = (string)$y;
}

$date_months = [0 => '-- Please select --'];
for ($m = 1; $m <= 12; $m++) {
    $date_months[$m] = (string)$m;
}

$date_days = [0 => '-- Please select --'];
for ($d = 1; $d <= 31; $d++) {
    $date_days[$d] = (string)$d;
}

// ------------------------------------------------------------
// 車両状態（cars.status_code）
// ------------------------------------------------------------
$status_code = [
    1 => '在庫',
    2 => 'リース中',
    3 => 'お客様所有（販売済）',
    4 => '代車',
    5 => '廃車',
    6 => 'リース予定',
];

// ------------------------------------------------------------
// 車両管理（cars）用 選択肢辞書（共通）
// ------------------------------------------------------------
$car_options_common = [
    'please_select' => [0 => '-- Please select --'],

    'date_years'  => $date_years,
    'date_months' => $date_months,
    'date_days'   => $date_days,

    'yes_no' => [
        0 => '-- Please select --',
        1 => 'あり',
        2 => 'なし',
    ],

    'applicable' => [
        0 => '-- Please select --',
        1 => '該当する',
        2 => '該当しない',
    ],

    'oem_aftermarket_unknown' => [
        0 => '-- Please select --',
        1 => '純正',
        2 => '社外',
        3 => '不明',
    ],

    'exist_none' => [
        0 => '-- Please select --',
        1 => 'あり',
        2 => 'なし',
    ],
];

// ------------------------------------------------------------
// メーカー別 車種辞書（maker連動）
// ------------------------------------------------------------
$car_models_by_maker = require __DIR__ . '/car_models.php';

// フラット辞書
$car_models_flat = [0 => '-- Please select --'];
foreach ($car_models_by_maker as $makerKey => $models) {
    foreach ($models as $code => $label) {
        if ((string)$code === '0') {
            $car_models_flat[0] = $label;
            continue;
        }
        $car_models_flat[$code] = $label;
    }
}

// ------------------------------------------------------------
// 車両管理（cars）用 選択肢辞書
// ------------------------------------------------------------
$car_options = [
    'maker' => [
        0 => '-- Please select --',
        'lexus'            => 'レクサス',
        'toyota'           => 'トヨタ',
        'nissan'           => '日産',
        'honda'            => 'ホンダ',
        'matsuda'          => 'マツダ',
        'subaru'           => 'スバル',
        'suzuki'           => 'スズキ',
        'mitsubishi'       => '三菱',
        'daihatsu'         => 'ダイハツ',
        'isuzu'            => 'いすゞ',
        'hinojidousha'     => '日野自動車',
        'ud'               => 'ＵＤトラックス',
        'mitsubishihusou'  => '三菱ふそう',
        'mitsuoka'         => '光岡',
        'other_jp'         => '国産車その他',
        'mercedesbenz'     => 'メルセデス・ベンツ',
        'mercedesbenz_amg' => 'メルセデスＡＭＧ',
        'smart'            => 'スマート',
        'bmw'              => 'BMW',
        'audi'             => 'アウディ',
        'volkswagen'       => 'フォルクスワーゲン',
        'opel'             => 'オペル',
        'porsche'          => 'ポルシェ',
        'mini'             => 'ミニ',
        'cadillac'         => 'キャデラック',
        'chevrolet'        => 'シボレー',
        'hummer'           => 'ハマー',
        'gmc'              => 'GMC',
        'ford'             => 'フォード',
        'chrysler'         => 'クライスラー',
        'jeep'             => 'ジープ',
        'tesla'            => 'テスラ',
        'bentley'          => 'ベントレー',
        'jaguar'           => 'ジャガー',
        'daimler'          => 'デイムラー',
        'landrover'        => 'ランドローバー',
        'lotus'            => 'ロータス',
        'rover'            => 'ローバー',
        'volvo'            => 'ボルボ',
        'peugeot'          => 'プジョー',
        'renault'          => 'ルノー',
        'citroen'          => 'シトロエン',
        'fiat'             => 'フィアット',
        'alfaromeo'        => 'アルファ ロメオ',
        'maserati'         => 'マセラティ',
        'byd'              => 'BYD',
        'hyundai'          => 'ヒョンデ',
        'others'           => '輸入車その他',
    ],

    'car_model' => [
        0 => '-- Please select --',
    ],

    'status_code' => $status_code,

    'car_models_by_maker' => $car_models_by_maker,
    'car_models_flat'     => $car_models_flat,

    'type_of_car' => [
        0 => '-- Please select --',
        1 => '軽自動車',
        2 => '小型',
        3 => '普通',
        4 => '大型特殊',
    ],

    'car_purpose' => [
        0 => '-- Please select --',
        1 => '乗用',
        2 => '貨物',
        3 => '乗合',
        4 => '特種',
    ],

    'how_to_use' => [
        0 => '-- Please select --',
        1 => '自家用',
        2 => '事業用',
    ],

    'body_shape' => [
        0 => '-- Please select --',
        1 => '箱型',
        2 => 'ステーションワゴン',
        3 => '幌型',
    ],

    'new_used' => [
        0 => '-- Please select --',
        1 => '新車',
        2 => '中古車',
    ],

    'car_tax_simple_table' => [
        'enabled' => true,
        'kei' => [
            'tax' => 10800,
        ],
        'displacement_cc_brackets' => [
            ['upper_cc' => 1000, 'tax' => 29500],
            ['upper_cc' => 1500, 'tax' => 34500],
            ['upper_cc' => 2000, 'tax' => 39500],
            ['upper_cc' => 2500, 'tax' => 45000],
            ['upper_cc' => 3000, 'tax' => 51000],
            ['upper_cc' => 3500, 'tax' => 58000],
            ['upper_cc' => 4000, 'tax' => 66500],
            ['upper_cc' => 4500, 'tax' => 76500],
            ['upper_cc' => 6000, 'tax' => 88000],
            ['upper_cc' => 999999, 'tax' => 111000],
        ],
    ],

    'model_year' => [
        0    => '-- Please select --',
        1980 => '1980(S55)',
        1981 => '1981(S56)',
        1982 => '1982(S57)',
        1983 => '1983(S58)',
        1984 => '1984(S59)',
        1985 => '1985(S60)',
        1986 => '1986(S61)',
        1987 => '1987(S62)',
        1988 => '1988(S63)',
        1989 => '1989(H01)',
        1990 => '1990(H02)',
        1991 => '1991(H03)',
        1992 => '1992(H04)',
        1993 => '1993(H05)',
        1994 => '1994(H06)',
        1995 => '1995(H07)',
        1996 => '1996(H08)',
        1997 => '1997(H09)',
        1998 => '1998(H10)',
        1999 => '1999(H11)',
        2000 => '2000(H12)',
        2001 => '2001(H13)',
        2002 => '2002(H14)',
        2003 => '2003(H15)',
        2004 => '2004(H16)',
        2005 => '2005(H17)',
        2006 => '2006(H18)',
        2007 => '2007(H19)',
        2008 => '2008(H20)',
        2009 => '2009(H21)',
        2010 => '2010(H22)',
        2011 => '2011(H23)',
        2012 => '2012(H24)',
        2013 => '2013(H25)',
        2014 => '2014(H26)',
        2015 => '2015(H27)',
        2016 => '2016(H28)',
        2017 => '2017(H29)',
        2018 => '2018(H30)',
        2019 => '2019(R01)',
        2020 => '2020(R02)',
        2021 => '2021(R03)',
        2022 => '2022(R04)',
        2023 => '2023(R05)',
        2024 => '2024(R06)',
    ],

    'one_owner'               => $car_options_common['applicable'],
    'camper'                  => $car_options_common['applicable'],
    'repair_history'          => $car_options_common['exist_none'],
    'vehicle_inspection'      => $car_options_common['exist_none'],
    'record_book'             => $car_options_common['exist_none'],
    'new_car_property'        => $car_options_common['applicable'],
    'non_smoking'             => $car_options_common['applicable'],
    'officially_imported_car' => $car_options_common['applicable'],
    'is_recycling_fee'        => $car_options_common['exist_none'],
    'registered_unused_car'   => $car_options_common['applicable'],
    'test_car'                => $car_options_common['applicable'],
    'rental_up'               => $car_options_common['applicable'],

    'body_type' => [
        0  => '-- Please select --',
        1  => 'コンパクトカー',
        2  => 'ミニバン',
        3  => 'ステーションワゴン',
        4  => 'SUV・クロカン',
        5  => 'セダン',
        6  => 'クーペ',
        7  => 'ハッチバック',
        8  => 'オープンカー',
        9  => 'ピックアップトラック',
        10 => 'トラック',
        11 => 'その他',
    ],

    'body_color' => [
        0  => '-- Please select --',
        1  => 'ホワイト',
        2  => 'パール',
        3  => '黒',
        4  => '青',
        5  => 'シルバー',
        6  => '赤',
        7  => 'グレイ',
        8  => 'ブラウン',
        9  => 'グリーン',
        10 => 'パープル',
        11 => 'イエロー',
        12 => 'オレンジ',
        13 => 'ピンク',
        14 => 'ゴールド',
        15 => 'その他',
    ],

    'doors' => [
        0 => '-- Please select --',
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
        5 => '5',
        6 => 'その他',
    ],

    'passengers' => [
        0  => '-- Please select --',
        1  => '1',
        2  => '2',
        3  => '3',
        4  => '4',
        5  => '5',
        6  => '6',
        7  => '7',
        8  => '8',
        9  => '9',
        10 => '10',
    ],

    'handle' => [
        0 => '-- Please select --',
        1 => '右ハンドル',
        2 => '左ハンドル',
    ],

    'engine_type' => [
        0 => '-- Please select --',
        1 => 'ガソリン',
        2 => 'ハイブリッド',
        3 => 'ディーゼル',
        4 => '電気',
        5 => 'その他',
    ],

    'supercharger_settings' => [
        0 => '-- Please select --',
        1 => 'ターボ',
        2 => 'スーパーチャージャー',
        3 => 'その他',
    ],

    'drive_system' => [
        0 => '-- Please select --',
        1 => '2WD',
        2 => '4WD',
        3 => 'その他',
    ],

    'welfare_vehicles' => $car_options_common['applicable'],
    'eco_car'          => $car_options_common['applicable'],

    'safety_yes_no_fields' => [
        'power_steering'                     => 'パワステ',
        'abs'                                => 'ABS',
        'support_car'                        => 'サポカー',
        'collision_damage'                   => '衝突被害軽減ブレーキ',
        // ★誤字修正
        'adaptive_cruise_control'            => 'アダプティブクルーズコントロール',
        'lane_keep_assist'                   => 'レーンキープアシスト',
        'parking_assist'                     => 'パーキングアシスト',
        'accidental_start_prevention_device' => 'アクセル踏み間違い（誤発進）防止装置',
        'obstacle_sensor'                    => '障害物センサー',
        'airbag_driver_seat'                 => 'エアバッグ運転席',
        'airbag_passenger_seat'              => 'エアバッグ助手席',
        'airbag_side'                        => 'エアバッグサイド',
        'airbag'                             => 'エアバッグ',
        'neck_shock_mitigation_headrest'     => '頸部衝撃緩和ヘッドレスト',
        'all_around_camera'                  => '全周囲カメラ',
        'camera_back'                        => 'カメラ：バック',
        'monitor_blind_spot'                 => 'モニター：ブラインドスポット',
        'monitor'                            => 'モニター：－',
        'anti_skid_device'                   => '横滑り防止装置',
        'hill_descent_control'               => 'ヒルディセントコントロール',
        'idling_stop'                        => 'アイドリングストップ',
        'anti_theft_device'                  => '盗難防止装置',
        'automatic_high_beam'                => 'オートマチックハイビーム',
    ],

    'comfort_applicable_fields' => [
        'air_conditioner_cooler' => 'エアコン・クーラー',
        'seat_air_conditioner'   => 'シートエアコン',
        'seat_heater'            => 'シートヒーター',
        'w_air_conditioner'      => 'Wエアコン',
        'hdd_car_navigation'     => 'カーナビ：HDD',
        'dvd_car_navigation'     => 'カーナビ：DVD',
        'tv'                     => 'TV',
        'picture'                => '映像',
        'music_server'           => 'オーディオ：ミュージックサーバー',
        'is_music_player'        => 'ミュージックプレイヤー接続可',
        'etc'                    => 'ETC',
        'power_supply'           => '1500W給電',
        'drive_recorder'         => 'ドライブレコーダー',
        'display_audio'          => 'ディスプレイオーディオ',
        'rear_seat_monitor'      => '後席モニター',
        'ottoman'                => 'オットマン',
    ],

    'keyless'              => $car_options_common['oem_aftermarket_unknown'],
    'smart_key'            => $car_options_common['applicable'],
    'power_window'         => $car_options_common['applicable'],
    'sunroof_glassroof'    => $car_options_common['oem_aftermarket_unknown'],
    'bench_seat'           => $car_options_common['applicable'],
    'walk_through'         => $car_options_common['applicable'],
    'three_row_seats'      => $car_options_common['applicable'],
    'electric_seat'        => $car_options_common['applicable'],
    'full_flat_seat'       => $car_options_common['applicable'],
    'genuine_leather_seat' => $car_options_common['oem_aftermarket_unknown'],

    'slide_door' => [
        0 => '-- Please select --',
        1 => '両側電動スライドドア',
        2 => '両側スライドドア',
        3 => '片側電動スライドドア',
        4 => '片側スライドドア',
        5 => '両側スライド片側電動ドア',
        6 => 'その他',
    ],
    'electric_rear_gate' => $car_options_common['applicable'],
    'hid_led' => [
        0 => '-- Please select --',
        1 => 'ディスチャージドランプ',
        2 => 'LEDヘッドライト',
        3 => 'その他',
    ],
    'front_fog_lamp' => $car_options_common['applicable'],
    'full_aero'      => $car_options_common['oem_aftermarket_unknown'],
    'low_down'       => $car_options_common['applicable'],
    'lift_up'        => $car_options_common['oem_aftermarket_unknown'],
    'air_suspension' => $car_options_common['applicable'],
    'aluminum_wheel' => $car_options_common['oem_aftermarket_unknown'],
    'roof_rail'      => $car_options_common['applicable'],
    'cold_region_specification' => $car_options_common['applicable'],
    'all_painted'              => $car_options_common['applicable'],
];

$car_audit_value_labels = [
    'maker'       => $car_options['maker'],
    'car_model'   => $car_models_flat,
    'status_code' => $car_options['status_code'],

    'type_of_car' => $car_options['type_of_car'],
    'car_purpose' => $car_options['car_purpose'],
    'how_to_use'  => $car_options['how_to_use'],
    'body_shape'  => $car_options['body_shape'],
    'new_used'    => $car_options['new_used'],
    'model_year'  => $car_options['model_year'],

    'one_owner'               => $car_options['one_owner'],
    'camper'                  => $car_options['camper'],
    'repair_history'          => $car_options['repair_history'],
    'vehicle_inspection'      => $car_options['vehicle_inspection'],
    'record_book'             => $car_options['record_book'],
    'new_car_property'        => $car_options['new_car_property'],
    'non_smoking'             => $car_options['non_smoking'],
    'officially_imported_car' => $car_options['officially_imported_car'],
    'is_recycling_fee'        => $car_options['is_recycling_fee'],
    'registered_unused_car'   => $car_options['registered_unused_car'],
    'test_car'                => $car_options['test_car'],
    'rental_up'               => $car_options['rental_up'],
    'body_type'               => $car_options['body_type'],
    'body_color'              => $car_options['body_color'],
    'doors'                   => $car_options['doors'],
    'passengers'              => $car_options['passengers'],
    'handle'                  => $car_options['handle'],
    'engine_type'             => $car_options['engine_type'],
    'supercharger_settings'   => $car_options['supercharger_settings'],
    'drive_system'            => $car_options['drive_system'],
    'welfare_vehicles'        => $car_options['welfare_vehicles'],
    'eco_car'                 => $car_options['eco_car'],

    'power_steering'                     => $car_options_common['yes_no'],
    'abs'                                => $car_options_common['yes_no'],
    'support_car'                        => $car_options_common['yes_no'],
    'collision_damage'                   => $car_options_common['yes_no'],
    'adaptive_cruise_control'            => $car_options_common['yes_no'],
    'lane_keep_assist'                   => $car_options_common['yes_no'],
    'parking_assist'                     => $car_options_common['yes_no'],
    'accidental_start_prevention_device' => $car_options_common['yes_no'],
    'obstacle_sensor'                    => $car_options_common['yes_no'],
    'airbag_driver_seat'                 => $car_options_common['yes_no'],
    'airbag_passenger_seat'              => $car_options_common['yes_no'],
    'airbag_side'                        => $car_options_common['yes_no'],
    'airbag'                             => $car_options_common['yes_no'],
    'neck_shock_mitigation_headrest'     => $car_options_common['yes_no'],
    'all_around_camera'                  => $car_options_common['yes_no'],
    'camera_back'                        => $car_options_common['yes_no'],
    'monitor_blind_spot'                 => $car_options_common['yes_no'],
    'monitor'                            => $car_options_common['yes_no'],
    'anti_skid_device'                   => $car_options_common['yes_no'],
    'hill_descent_control'               => $car_options_common['yes_no'],
    'idling_stop'                        => $car_options_common['yes_no'],
    'anti_theft_device'                  => $car_options_common['yes_no'],
    'automatic_high_beam'                => $car_options_common['yes_no'],

    'air_conditioner_cooler' => $car_options_common['applicable'],
    'seat_air_conditioner'   => $car_options_common['applicable'],
    'seat_heater'            => $car_options_common['applicable'],
    'w_air_conditioner'      => $car_options_common['applicable'],
    'hdd_car_navigation'     => $car_options_common['applicable'],
    'dvd_car_navigation'     => $car_options_common['applicable'],
    'tv'                     => $car_options_common['applicable'],
    'picture'                => $car_options_common['applicable'],
    'music_server'           => $car_options_common['applicable'],
    'is_music_player'        => $car_options_common['applicable'],
    'etc'                    => $car_options_common['applicable'],
    'power_supply'           => $car_options_common['applicable'],
    'drive_recorder'         => $car_options_common['applicable'],
    'display_audio'          => $car_options_common['applicable'],
    'rear_seat_monitor'      => $car_options_common['applicable'],
    'ottoman'                => $car_options_common['applicable'],

    'keyless'              => $car_options['keyless'],
    'smart_key'            => $car_options['smart_key'],
    'power_window'         => $car_options['power_window'],
    'sunroof_glassroof'    => $car_options['sunroof_glassroof'],
    'bench_seat'           => $car_options['bench_seat'],
    'walk_through'         => $car_options['walk_through'],
    'three_row_seats'      => $car_options['three_row_seats'],
    'electric_seat'        => $car_options['electric_seat'],
    'full_flat_seat'       => $car_options['full_flat_seat'],
    'genuine_leather_seat' => $car_options['genuine_leather_seat'],

    'slide_door'                => $car_options['slide_door'],
    'electric_rear_gate'        => $car_options['electric_rear_gate'],
    'hid_led'                   => $car_options['hid_led'],
    'front_fog_lamp'            => $car_options['front_fog_lamp'],
    'full_aero'                 => $car_options['full_aero'],
    'low_down'                  => $car_options['low_down'],
    'lift_up'                   => $car_options['lift_up'],
    'air_suspension'            => $car_options['air_suspension'],
    'aluminum_wheel'            => $car_options['aluminum_wheel'],
    'roof_rail'                 => $car_options['roof_rail'],
    'cold_region_specification' => $car_options['cold_region_specification'],
    'all_painted'               => $car_options['all_painted'],
];

$car_audit_fields = require __DIR__ . '/car_audit_fields.php';

return [
    'options_common' => $car_options_common,
    'options'        => $car_options,
    'audit_values'   => $car_audit_value_labels,
    'audit_fields'   => $car_audit_fields,
];
