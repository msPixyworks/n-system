<?php

/**
 * config/personal_customer_config.php
 * ============================================================
 * 役割:
 * - 個人顧客（personal_customers）専用の辞書を提供する
 *   - modules（表示名）
 *   - role_sets（閲覧/編集ロール）
 *   - options（画面選択肢）
 *   - audit_fields（監査表示名）
 *   - audit_value_labels（値変換ラベル）
 *
 * 方針:
 * - K-Core: config.php が唯一の正（ここは分割パーツ）
 * - prefectures（都道府県）は config.php 側の $prefectures を利用するため、
 *   ここでは「どのフィールドがprefか」を pref_field_names に列挙しておく。
 *   （config.php 側でこの配列を見て audit_value_labels に $prefectures を注入する）
 */

return [
    // ------------------------------------------------------------
    // モジュール表示名（監査一覧の表示に利用）
    // ------------------------------------------------------------
    'modules' => [
        'personal_customers' => '個人顧客管理',
    ],

    // ------------------------------------------------------------
    // 権限セット（Policies が参照）
    //  - office_customers と同等（暫定）
    // ------------------------------------------------------------
    'role_sets' => [
        'personal_customers' => [
            'view' => [1, 2, 3, 4, 5, 7],
            'edit' => [1, 2, 3, 4],
        ],
    ],

    // ------------------------------------------------------------
    // 選択肢（画面で利用）
    // ------------------------------------------------------------
    'options' => [
        'personal_customers' => [
            // ご来社経緯（0=未選択を許容）
            'backgrounds' => [
                0 => '（未選択）',
                1 => 'HP',
                2 => 'チラシ',
                3 => '営業',
            ],

            // 免許証の色（0=未選択）
            'license_colors' => [
                0 => '（未選択）',
                1 => 'ブルー',
                2 => 'ゴールド',
                3 => 'グリーン',
            ],
        ],
    ],

    // ------------------------------------------------------------
    // 監査フィールド表示名（module別）
    // ------------------------------------------------------------
    'audit_fields' => [
        'personal_customers' => [
            // 本人情報
            'name' => '氏名',
            'letter' => '氏名フリガナ',
            'tel01' => '電話番号1',
            'zip' => '郵便番号',
            'pref_code' => '県名',
            'addr01' => '住所1',
            'addr02' => '住所2',
            'mail01' => 'メールアドレス1',
            'mail02' => 'メールアドレス2',
            'birthday_year' => '誕生日（年）',
            'birthday_month' => '誕生日（月）',
            'birthday_day' => '誕生日（日）',
            'license_color' => '免許証の色',
            'mobile01' => '携帯',
            'emergency_contact' => '緊急連絡先名',
            'emergency_relationship' => '緊急連絡先の続柄',
            'emergency_tel' => '緊急連絡先の電話番号',

            // お勤め先情報
            'office' => '会社名',
            'office_letter' => '会社名フリガナ',
            'office_zip' => '勤務先 郵便番号',
            'office_pref_code' => '勤務先 県名',
            'office_addr01' => '勤務先 住所1',
            'office_addr02' => '勤務先 住所2',
            'office_tel01' => '勤務先 電話番号1',
            'office_tel02' => '勤務先 電話番号2',
            'years_of_service' => '勤続年数',

            // ご来社経緯
            'background' => 'ご来社経緯',
            'introducer' => 'ご紹介者',
            'others' => 'その他',

            // 備考
            'remarks' => '備考',

            // ご家族情報（1）
            'first_relationship' => 'ご家族1 続柄',
            'first_name' => 'ご家族1 氏名',
            'first_letter' => 'ご家族1 氏名フリガナ',
            'first_tel01' => 'ご家族1 電話番号1',
            'first_tel02' => 'ご家族1 電話番号2',
            'first_zip' => 'ご家族1 郵便番号',
            'first_pref_code' => 'ご家族1 県名',
            'first_addr01' => 'ご家族1 住所1',
            'first_addr02' => 'ご家族1 住所2',
            'first_mail01' => 'ご家族1 メールアドレス1',
            'first_mail02' => 'ご家族1 メールアドレス2',
            'first_remarks' => 'ご家族1 備考',

            // ご家族情報（2）
            'second_relationship' => 'ご家族2 続柄',
            'second_name' => 'ご家族2 氏名',
            'second_letter' => 'ご家族2 氏名フリガナ',
            'second_tel01' => 'ご家族2 電話番号1',
            'second_tel02' => 'ご家族2 電話番号2',
            'second_zip' => 'ご家族2 郵便番号',
            'second_pref_code' => 'ご家族2 県名',
            'second_addr01' => 'ご家族2 住所1',
            'second_addr02' => 'ご家族2 住所2',
            'second_mail01' => 'ご家族2 メールアドレス1',
            'second_mail02' => 'ご家族2 メールアドレス2',
            'second_remarks' => 'ご家族2 備考',

            // ご家族情報（3）
            'third_relationship' => 'ご家族3 続柄',
            'third_name' => 'ご家族3 氏名',
            'third_letter' => 'ご家族3 氏名フリガナ',
            'third_tel01' => 'ご家族3 電話番号1',
            'third_tel02' => 'ご家族3 電話番号2',
            'third_zip' => 'ご家族3 郵便番号',
            'third_pref_code' => 'ご家族3 県名',
            'third_addr01' => 'ご家族3 住所1',
            'third_addr02' => 'ご家族3 住所2',
            'third_mail01' => 'ご家族3 メールアドレス1',
            'third_mail02' => 'ご家族3 メールアドレス2',
            'third_remarks' => 'ご家族3 備考',

            // ご家族情報（4）
            'fourth_relationship' => 'ご家族4 続柄',
            'fourth_name' => 'ご家族4 氏名',
            'fourth_letter' => 'ご家族4 氏名フリガナ',
            'fourth_tel01' => 'ご家族4 電話番号1',
            'fourth_tel02' => 'ご家族4 電話番号2',
            'fourth_zip' => 'ご家族4 郵便番号',
            'fourth_pref_code' => 'ご家族4 県名',
            'fourth_addr01' => 'ご家族4 住所1',
            'fourth_addr02' => 'ご家族4 住所2',
            'fourth_mail01' => 'ご家族4 メールアドレス1',
            'fourth_mail02' => 'ご家族4 メールアドレス2',
            'fourth_remarks' => 'ご家族4 備考',

            // ご家族情報（5）
            'fifth_relationship' => 'ご家族5 続柄',
            'fifth_name' => 'ご家族5 氏名',
            'fifth_letter' => 'ご家族5 氏名フリガナ',
            'fifth_tel01' => 'ご家族5 電話番号1',
            'fifth_tel02' => 'ご家族5 電話番号2',
            'fifth_zip' => 'ご家族5 郵便番号',
            'fifth_pref_code' => 'ご家族5 県名',
            'fifth_addr01' => 'ご家族5 住所1',
            'fifth_addr02' => 'ご家族5 住所2',
            'fifth_mail01' => 'ご家族5 メールアドレス1',
            'fifth_mail02' => 'ご家族5 メールアドレス2',
            'fifth_remarks' => 'ご家族5 備考',

            // 運用カラム（監査の表示辞書）
            'created_at' => '作成日時',
            'updated_at' => '更新日時',
            'deleted_at' => '削除日時',
        ],
    ],

    // ------------------------------------------------------------
    // 値変換辞書（module/fieldごと）
    // ------------------------------------------------------------
    'audit_value_labels' => [
        'personal_customers' => [
            'background' => [
                0 => '（未選択）',
                1 => 'HP',
                2 => 'チラシ',
                3 => '営業',
            ],
            'license_color' => [
                0 => '（未選択）',
                1 => 'ブルー',
                2 => 'ゴールド',
                3 => 'グリーン',
            ],
            // pref_code 系は config.php 側で $prefectures を注入する（下の pref_field_names を参照）
        ],
    ],

    // ------------------------------------------------------------
    // config.php 側で prefectures を注入したいフィールド一覧
    // ------------------------------------------------------------
    'pref_field_names' => [
        'pref_code',
        'office_pref_code',
        'first_pref_code',
        'second_pref_code',
        'third_pref_code',
        'fourth_pref_code',
        'fifth_pref_code',
    ],
];
