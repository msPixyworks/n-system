<?php

/**
 * config/car_audit_fields.php
 * ============================================================
 * 役割:
 * - cars（車両管理）の audit_fields（監査表示名辞書）だけを切り出す
 *
 * 方針:
 * - config.php 側の巨大配列を分離して見通しを良くする
 * - ここは「表示名」だけ（値変換は car_config.php の audit_values）
 */

return [
    'management_number'              => '管理番号',
    'vehicle_number'                 => '車両番号',
    'chassis_number'                 => '車台番号',

    'registration_year'              => '登録年',
    'registration_month'             => '登録月',
    'registration_day'               => '登録日',
    'first_registration_year'        => '初年度登録年',
    'first_registration_month'       => '初年度登録月',

    'maker'                          => 'メーカー',
    'car_model'                      => '車種',
    'model_designation_number'       => '型式指定番号',
    'classification_division_number' => '類別区分番号',
    'type_of_car'                    => '自動車の種別',
    'car_purpose'                    => '用途',
    'how_to_use'                     => '自家用・事業用の別',
    'body_shape'                     => '車体の形状',
    'vehicle_weight'                 => '車両重量',
    'displacement'                   => '排気量',

    'new_used'                => '新車／中古車（購入時）',

    'purchase_year'           => '購入年',
    'purchase_month'          => '購入月',
    'purchase_day'            => '購入日',

    'purchase_price'          => '車両購入価格',
    'consumption_tax'         => '消費税',
    'recycling_cost'          => 'リサイクル費用',
    'purchase_costs'          => '購入時諸費用',
    'car_tax'                 => '自動車税（年額）',
    'car_insurance_premium'   => '自動車保険料（年額）',
    'total_expenses'          => '経費総額',

    'model_year'              => '年式',
    'mileage_amount'          => '走行距離',
    'base_price'              => '販売本体価格',
    'total_to_pay'            => '支払総額',
    'one_owner'               => 'ワンオーナー',
    'camper'                  => 'キャンピングカー',
    'repair_history'          => '修復歴',
    'vehicle_inspection'      => '車検',
    'record_book'             => '定期点検記録簿',
    'new_car_property'        => '新車物件',
    'non_smoking'             => '禁煙車',
    'officially_imported_car' => '正規輸入車',
    'is_recycling_fee'        => 'リサイクル料',
    'registered_unused_car'   => '登録（届出）済未使用車',
    'test_car'                => '展示・試乗車',
    'rental_up'               => 'レンタカーアップ',
    'body_type'               => 'ボディタイプ',
    'body_color'              => 'ボディ色',
    'color_code'              => 'カラーコード',
    'key_number'              => 'キー番号',
    'doors'                   => 'ドア数',
    'passengers'              => '乗車定員',
    'handle'                  => 'ハンドル',
    'engine_type'             => 'エンジン種別',
    'supercharger_settings'   => '過給機設定',
    'drive_system'            => '駆動方式',
    'welfare_vehicles'        => '福祉車両',
    'eco_car'                 => 'エコカー減税対象車',
    'chassis_number_suffix'   => '車台番号末尾',
    'legal_maintenance'       => '法定整備',
    'guarantee'               => '保証',

    'power_steering'                     => 'パワステ',
    'abs'                                => 'ABS',
    'support_car'                        => 'サポカー',
    'collision_damage'                   => '衝突被害軽減ブレーキ',
    'adaptive_cruise_control'            => 'アダプティブクルーズコントロロール',
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

    // ★運用カラム（全カラム監査の表示辞書）
    'created_at' => '作成日時',
    'updated_at' => '更新日時',
    'deleted_at' => '削除日時',
];
