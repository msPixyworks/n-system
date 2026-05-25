<?php

/**
 * config.php
 * ============================================================
 * 役割:
 * - 環境（env/DB）
 * - ロール辞書 / 権限セット（role_sets）
 * - 監査ログ表示辞書（modules/audit_actions/audit_fields/audit_value_labels）
 * - 画面選択肢辞書（options）
 *
 * 分割:
 * - cars:
 *   - config/car_config.php（options / audit_values / audit_fields）
 *   - config/car_models.php
 *   - config/car_audit_fields.php（今回追加）
 * - car_leases（今回追加）:
 *   - config/car_lease_config.php（options / audit_values / audit_fields / fiscal_year）
 * - office_customers:
 *   - config/office_customer_config.php（今回追加）
 * - personal_customers:
 *   - config/personal_customer_config.php（追加済み）
 *
 * 今回追加:
 * - car_status_histories（車両状態変更履歴）用の modules / audit_fields / audit_value_labels
 * - car_sales（車両販売情報）用の modules / audit_fields / audit_value_labels
 *
 * 方針:
 * - 車両状態コードは cars.options.status_code を唯一の正として流用する
 * - partner_type / customer_type は既存の lessee_types（office / personal）を流用する
 */

$carConfig = require __DIR__ . '/config/car_config.php';
$carLeaseConfig = require __DIR__ . '/config/car_lease_config.php'; // ★追加
$officeCustomerConfig = require __DIR__ . '/config/office_customer_config.php';
$personalCustomerConfig = require __DIR__ . '/config/personal_customer_config.php';

/**
 * 都道府県（1-47）
 * - JS側（zipcloud）でも window.KCore.PREFS 注入に使える想定
 */
$prefectures = [
    1 => '北海道', 2 => '青森県', 3 => '岩手県', 4 => '宮城県', 5 => '秋田県', 6 => '山形県', 7 => '福島県',
    8 => '茨城県', 9 => '栃木県', 10 => '群馬県', 11 => '埼玉県', 12 => '千葉県', 13 => '東京都', 14 => '神奈川県',
    15 => '新潟県', 16 => '富山県', 17 => '石川県', 18 => '福井県', 19 => '山梨県', 20 => '長野県',
    21 => '岐阜県', 22 => '静岡県', 23 => '愛知県', 24 => '三重県',
    25 => '滋賀県', 26 => '京都府', 27 => '大阪府', 28 => '兵庫県', 29 => '奈良県', 30 => '和歌山県',
    31 => '鳥取県', 32 => '島根県', 33 => '岡山県', 34 => '広島県', 35 => '山口県',
    36 => '徳島県', 37 => '香川県', 38 => '愛媛県', 39 => '高知県',
    40 => '福岡県', 41 => '佐賀県', 42 => '長崎県', 43 => '熊本県', 44 => '大分県', 45 => '宮崎県', 46 => '鹿児島県',
    47 => '沖縄県',
];

/**
 * prefectures を audit_value_labels に自動注入する（office/personal）
 */
$officePrefFields = $officeCustomerConfig['pref_field_names'] ?? [];
if (!is_array($officePrefFields)) $officePrefFields = [];

$personalPrefFields = $personalCustomerConfig['pref_field_names'] ?? [];
if (!is_array($personalPrefFields)) $personalPrefFields = [];

/**
 * office/personal の audit_value_labels を作業用に展開
 */
$officeAuditValueLabels = $officeCustomerConfig['audit_value_labels'] ?? [];
if (!isset($officeAuditValueLabels['office_customers'])) {
    $officeAuditValueLabels['office_customers'] = [];
}
foreach ($officePrefFields as $fieldName) {
    $officeAuditValueLabels['office_customers'][(string)$fieldName] = $prefectures;
}

$personalAuditValueLabels = $personalCustomerConfig['audit_value_labels'] ?? [];
if (!isset($personalAuditValueLabels['personal_customers'])) {
    $personalAuditValueLabels['personal_customers'] = [];
}
foreach ($personalPrefFields as $fieldName) {
    $personalAuditValueLabels['personal_customers'][(string)$fieldName] = $prefectures;
}

/**
 * 今回追加:
 * - 車両状態コード / リース先種別を別モジュールでも流用しやすいように事前展開
 */
$carStatusLabels = $carConfig['options']['status_code'] ?? [];
$lesseeTypeLabels = $carLeaseConfig['options']['lessee_types'] ?? [
    'office'   => '法人',
    'personal' => '個人',
];

return [
    // ------------------------------------------------------------
    // env
    // ------------------------------------------------------------
    'env' => getenv('APP_ENV') ?: 'local',

    // ------------------------------------------------------------
    // DB
    // ------------------------------------------------------------
    // 'db'  => [
    //     'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    //     'name'    => getenv('DB_DATABASE') ?: 'nsystem',
    //     'user'    => getenv('DB_USERNAME') ?: 'nsystem',
    //     'pass'    => getenv('DB_PASSWORD') ?: 'nsystempass',
    //     'charset' => 'utf8mb4',
    // ],

    //本番用
    'db'  => [
        'host'    => getenv('DB_HOST') ?: 'mysql107.xbiz.ne.jp',
        'name'    => getenv('DB_DATABASE') ?: 'xb136563_system',
        'user'    => getenv('DB_USERNAME') ?: 'xb136563_nsadmin',
        'pass'    => getenv('DB_PASSWORD') ?: '<1QYeSkU-Z0v',
        'charset' => 'utf8mb4',
    ],

    // ------------------------------------------------------------
    // 監査ログの保存ポリシー（任意）
    // ------------------------------------------------------------
    'audit_policy' => [
        'skip_create_without_details' => true,
        'skip_update_without_details' => true,
    ],

    // ------------------------------------------------------------
    // 認証レート制限（IP×email）
    // ------------------------------------------------------------
    'auth_rate_limit' => [
        'enabled'        => true,
        'max_attempts'   => 5,      // 失敗許容回数
        'window_seconds' => 600,    // 計測ウィンドウ（秒）
        'lock_seconds'   => 900,    // ブロック時間（秒）
    ],

    // ------------------------------------------------------------
    // ロール辞書（role_code -> 表示名）
    // ------------------------------------------------------------
    'roles' => [
        1 => '総務部',
        2 => '総務部事務',
        3 => '経営企画室',
        4 => 'マネージャー',
        5 => '店長',
        6 => '営業社員',
        7 => '支店事務',
    ],

    // ------------------------------------------------------------
    // 都道府県（1-47）
    // ------------------------------------------------------------
    'prefectures' => $prefectures,

    // ------------------------------------------------------------
    // パスワードポリシー（usersで利用）
    // ------------------------------------------------------------
    'password' => [
        'min'   => 8,
        'max'   => 16,
        'regex' => '/^[\x20-\x7E]+$/', // 半角英数記号（ASCII可視文字）
    ],

    // ------------------------------------------------------------
    // 法人顧客：来社経緯（画面/監査で共通利用）
    //   ※互換維持：既存キーのまま
    // ------------------------------------------------------------
    'office_customer_backgrounds' => $officeCustomerConfig['office_customer_backgrounds'] ?? [
        0 => '不明',
        1 => 'HP',
        2 => 'チラシ',
        3 => '営業',
    ],

    // ------------------------------------------------------------
    // ★年度（Fiscal Year）定義（車両リースで利用）
    // ------------------------------------------------------------
    'fiscal_year' => $carLeaseConfig['fiscal_year'] ?? [
        'start_month' => 4,
        'start_day'   => 1,
    ],

    // ------------------------------------------------------------
    // 選択肢辞書（画面・監査で共通利用）
    // ------------------------------------------------------------
    'options' => [
        'common' => $carConfig['options_common'],
        'cars'   => $carConfig['options'],

        // ★car_leases（今回追加）
        'car_leases' => $carLeaseConfig['options'] ?? [],

        'personal_customers' => $personalCustomerConfig['options']['personal_customers'] ?? [],
    ],

    // ------------------------------------------------------------
    // モジュール表示名（監査一覧の表示に利用）
    // ------------------------------------------------------------
    'modules' => array_merge([
        'users' => 'ユーザー管理',
        'audit' => '監査ログ',
        'cars'  => '車両管理',
        'car_leases'  => '車両リース管理',
        'car_fy_costs'=> '車両 年度別コスト',
        'car_profits' => '車両 収支',
        'car_lease_health' => 'リース整合性チェック',

        // ★今回追加
        'car_status_histories' => '車両状態変更履歴',
        'car_sales'            => '車両販売情報',
    ], $officeCustomerConfig['modules'] ?? [], $personalCustomerConfig['modules'] ?? []),

    // ------------------------------------------------------------
    // 監査アクション表示名
    // ------------------------------------------------------------
    'audit_actions' => [
        'create' => '作成',
        'update' => '更新',
        'delete' => '削除',
        'login'  => 'ログイン',
        'logout' => 'ログアウト',
        'login_failed'  => 'ログイン失敗',
        'login_blocked' => 'ログイン拒否（制限）',
    ],

    // 監査アクションのスタイル（一覧バッジ用）
    'audit_action_styles' => [
        'login_blocked' => 'danger',
        'login_failed'  => 'warning',
        'login'         => 'success',
        'logout'        => 'light',
        'create'        => 'primary',
        'update'        => 'info',
        'delete'        => 'secondary',
    ],

    // ------------------------------------------------------------
    // 監査フィールド表示名（module別）
    // ------------------------------------------------------------
    'audit_fields' => array_replace_recursive([
        'users' => [
            'employee_code'               => '社員コード',
            'role_code'                   => '権限',
            'name'                        => '社員名',
            'name_kana'                   => '社員名フリガナ',
            'email'                       => 'ユーザーID（メール）',
            'contract_input_permission'   => '契約入力権限',
            'uncontract_input_permission' => '未契約入力権限',
            'joined_on'                   => '入社日',
            'resigned_on'                 => '退職日',
            'notes'                       => '備考',
            'password_hash'               => 'パスワード',
            // ログイン監査用（detailsに出る想定）
            'email_masked'  => 'ユーザーID（マスク）',
            'reason'        => '理由',
            'blocked_until' => 'ブロック期限',
            'fail_count'    => '失敗回数',
        ],

        // ★cars は分離：car_config.php の audit_fields を参照
        'cars' => $carConfig['audit_fields'] ?? [],

        // ★car_leases / car_fy_costs（今回追加）
        'car_leases'   => ($carLeaseConfig['audit_fields']['car_leases'] ?? []),
        'car_fy_costs' => ($carLeaseConfig['audit_fields']['car_fy_costs'] ?? []),

        // ★今回追加: 車両状態変更履歴
        'car_status_histories' => [
            'car_id'            => '車両ID',
            'from_status_code'  => '変更前状態',
            'to_status_code'    => '変更後状態',
            'changed_at'        => '変更日時',
            'partner_type'      => '相手先種別',
            'partner_id'        => '相手先ID',
            'partner_name'      => '相手先名',
            'note'              => '備考',
            'created_by'        => '実行ユーザーID',
            'created_at'        => '作成日時',
            'updated_at'        => '更新日時',
            'deleted_at'        => '削除日時',
        ],

        // ★今回追加: 車両販売情報
        'car_sales' => [
            'car_id'         => '車両ID',
            'sold_at'        => '販売日',
            'customer_type'  => '販売先種別',
            'customer_id'    => '販売先ID',
            'customer_name'  => '販売先名',
            'sale_price'     => '販売額',
            'tax_amount'     => '消費税',
            'recycle_fee'    => 'リサイクル料',
            'other_fee'      => 'その他諸費用',
            'total_amount'   => '合計金額',
            'notes'          => '備考',
            'created_by'     => '実行ユーザーID',
            'created_at'     => '作成日時',
            'updated_at'     => '更新日時',
            'deleted_at'     => '削除日時',
        ],

        // car_profits は画面表示のみでDBテーブルが無いため、監査辞書は不要
    ], $officeCustomerConfig['audit_fields'] ?? [], $personalCustomerConfig['audit_fields'] ?? []),

    // ------------------------------------------------------------
    // 値変換辞書（module/fieldごと）
    // ------------------------------------------------------------
    'audit_value_labels' => array_replace_recursive([
        'users' => [
            'role_code' => [
                1 => '総務部',
                2 => '総務部事務',
                3 => '経営企画室',
                4 => 'マネージャー',
                5 => '店長',
                6 => '営業社員',
                7 => '支店事務',
            ],
            'contract_input_permission'   => [0 => 'なし', 1 => 'あり'],
            'uncontract_input_permission' => [0 => 'なし', 1 => 'あり'],
        ],

        // ★cars は分離：car_config.php の audit_values を参照
        'cars' => $carConfig['audit_values'] ?? [],

        // ★car_leases（今回追加）
        'car_leases' => ($carLeaseConfig['audit_values']['car_leases'] ?? []),

        // ★今回追加: 車両状態変更履歴
        'car_status_histories' => [
            'from_status_code' => $carStatusLabels,
            'to_status_code'   => $carStatusLabels,
            'partner_type'     => $lesseeTypeLabels,
        ],

        // ★今回追加: 車両販売情報
        'car_sales' => [
            'customer_type' => $lesseeTypeLabels,
        ],

        // login_failed/login_blocked の reason 表示
        'reason' => [
            'no_user'          => 'ユーザーなし',
            'no_password_hash' => 'パスワード未設定',
            'resigned'         => '退職済み',
            'bad_password'     => 'パスワード不一致',
            'rate_limited'     => 'レート制限',
        ],
    ], $officeAuditValueLabels, $personalAuditValueLabels),

    // ------------------------------------------------------------
    // 権限セット（Policies が参照）
    // ------------------------------------------------------------
    'role_sets' => array_replace_recursive([
        'users' => [
            'view' => [1,2,3,4,5,7],
            'edit' => [1,2,3,4],
        ],
        'cars' => [
            // users と同等
            'view' => [1,2,3,4,5,7],
            'edit' => [1,2,3,4],
        ],
        'audit' => [
            'view' => [1,2,3,4],
            'edit' => [1,2,3,4],
        ],

        'car_leases' => [
            'view' => [1,2,3,4,5,7],
            'edit' => [1,2,3,4],
        ],
        'car_fy_costs' => [
            'view' => [1,2,3,4,5,7],
            'edit' => [1,2,3,4],
        ],
        'car_profits' => [
            'view' => [1,2,3,4,5,7],
            'edit' => [1,2,3,4],
        ],

        'car_lease_health' => [
            'view' => [1,2,3,4,5,7],
            'edit' => [1,2,3,4],
        ],
    ], $officeCustomerConfig['role_sets'] ?? [], $personalCustomerConfig['role_sets'] ?? []),
];