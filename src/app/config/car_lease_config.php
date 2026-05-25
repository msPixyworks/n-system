<?php
/**
 * config/car_lease_config.php
 * ============================================================
 * 車両リース（car_leases / car_costs / car_profits）専用 設定
 *
 * 役割:
 * - 年度（FY）定義（開始月日）
 * - リース状態・表示ラベル
 * - 監査ログ用 表示名（audit_fields）
 * - 監査ログ用 値変換（audit_values）
 *
 * 方針:
 * - cars と同様に「専用configに分離」して可読性を保つ
 * - config.php 側では、このファイルを merge して集約するだけ
 */

// ------------------------------------------------------------
// 年度（Fiscal Year）定義
// ------------------------------------------------------------
// 例）4/1〜翌3/31
// ※ 後から変更可能だが、変更すると「どの年度に属するか」の見え方は変わる
$fiscal_year = [
    'start_month' => 8,
    'start_day'   => 21,
];

// ------------------------------------------------------------
// リース状態（car_leases.status）
// ------------------------------------------------------------
$lease_status = [
    'scheduled' => 'リース予定',
    'active'   => 'リース中',
    'ended'    => '満了',
    'canceled' => '解約',
];

// ------------------------------------------------------------
// リース先種別（car_leases.lessee_type）
// ------------------------------------------------------------
$lessee_types = [
    'office'   => '法人',
    'personal' => '個人',
];

// ------------------------------------------------------------
// リース整合性チェック：異常種別
// ------------------------------------------------------------
$health_issue_types = [
    'active_car_mismatch'       => 'active と車両状態の不一致',
    'car_active_missing'        => '車両はリース中だが active 不在',
    'scheduled_due_not_started' => '開始日超過の予定リース',
    'multi_active_same_car'     => '同一車両に active 複数',
    'scheduled_status_missing'  => 'リース予定状態の欠落',
    'scheduled_car_status_bad'  => 'scheduled と車両状態の不一致',
    'broken_current_lease'      => 'current_lease_id 不整合',
    'multi_due_scheduled'       => '開始日超過 scheduled 複数',
];

// ------------------------------------------------------------
// リース整合性チェック：重要度
// ------------------------------------------------------------
$health_severity = [
    'high'   => '危険',
    'medium' => '注意',
    'low'    => '確認',
];

// ------------------------------------------------------------
// 監査フィールド表示名
// ------------------------------------------------------------
$audit_fields = [
    // car_leases
    'car_leases' => [
        'car_id'            => '車両ID',
        'lessee_type'       => 'リース先種別',
        'lessee_id'         => 'リース先',
        'lease_start_date'  => 'リース開始日',
        'lease_end_date'    => 'リース終了予定日',
        'monthly_fee'       => '月額リース料',
        'status'            => '状態',
        'ended_at'           => '満了確定日時',
        'canceled_at'        => '解約確定日時',
        'notes'              => '備考',
        'created_at'         => '作成日時',
        'updated_at'         => '更新日時',
        'deleted_at'         => '削除日時',
    ],

    // car_fy_costs
    'car_fy_costs' => [
        'car_id'           => '車両ID',
        'fy'               => '年度',
        'tax_amount'       => '自動車税（年額）',
        'insurance_amount' => '自動車保険料（年額）',
        'expense_amount'   => '経費総額（年額）',
        'notes'            => '備考',
        'created_at'       => '作成日時',
        'updated_at'       => '更新日時',
        'deleted_at'       => '削除日時',
    ],
];

// ------------------------------------------------------------
// 監査ログ 値変換辞書
// ------------------------------------------------------------
$audit_values = [
    'car_leases' => [
        'status'      => $lease_status,
        'lessee_type' => $lessee_types,
    ],
];

// ------------------------------------------------------------
// 画面表示用オプション（必要最小限）
// ------------------------------------------------------------
$options = [
    'lease_status' => $lease_status,
    'lessee_types' => $lessee_types,

    //異常値チェック関連
    'health_issue_types' => $health_issue_types,
    'health_severity'    => $health_severity,
];

return [
    'fiscal_year'  => $fiscal_year,
    'options'      => $options,
    'audit_fields' => $audit_fields,
    'audit_values' => $audit_values,
];
