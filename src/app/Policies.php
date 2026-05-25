<?php

/**
 * Policies.php
 * ============================================================
 * 役割:
 * - 画面アクセス権限（表示/編集）を一元化する
 * - メニュー表示可否を一元化する（View側で呼ぶ）
 * - 項目単位の表示可否を一元化する（fieldVisible）
 *
 * 使い方（実例）
 * ------------------------------------------------------------
 * 1) 画面アクセス制御（Controller）
 *   $u = Auth::user();
 *   if (!$u) Response::redirect('/');
 *
 *   // 表示権限（閲覧）ガード
 *   Policies::guardView($u, 'users');   // 権限なければ403（API/画面はResponseが調整）
 *
 *   // 編集権限ガード（登録/更新/削除/編集画面のとき）
 *   Policies::guardEdit($u, 'users');
 *
 * 2) メニュー表示（layout.php / view）
 *   <?php if (Policies::menuVisible($me, 'users')): ?>
 *     <a href="/users">担当者マスター</a>
 *   <?php endif; ?>
 *
 *   ※menuVisible は基本的に canViewXxx のラッパーです
 *   ※「監査ログメニューは管理者だけ」等もここに集約できます
 *
 * 3) ボタン表示（編集ボタン等）
 *   <?php if (Policies::canEditUsers($me)): ?>
 *     <a href="/users/<?= $id ?>/edit">編集</a>
 *   <?php endif; ?>
 *
 * 4) 項目ごとの表示/非表示（フォームや詳細画面）
 *   <?php if (Policies::fieldVisible($me, 'users', 'contract_input_permission')): ?>
 *     ...契約入力権限の項目...
 *   <?php endif; ?>
 *
 *   fieldVisible の設計意図:
 *   - 「同じ画面を共有しつつ、ロールや権限フラグで一部項目を隠す」
 *   - 例：契約入力権限（contract_input_permission）/未契約入力権限（uncontract_input_permission）
 *   - 例：総務だけ見える内部項目を隠す
 *
 * 重要（保存側も守る）:
 * ------------------------------------------------------------
 * - fieldVisible は “表示制御” だが、セキュリティ的には “保存制御” も必要
 *   （画面で隠れてもPOSTで送信できるため）
 * - Controllerのstore/updateでは、fieldVisible と同条件で
 *   - 無視する（unsetする）
 *   - または拒否する（403/422）
 *   のどちらかを必ず実装する
 *
 * 設計方針（重要）
 * ------------------------------------------------------------
 * - 権限の定義（どのロールが view/edit 可能か）は config.php の role_sets が唯一の正
 * - ControllerやViewで in_array(role_code, ...) を直書きしない（ズレ・穴を防ぐ）
 * - ただし「暫定」や「サンプルUI」は例外になりがちなので、最終的には Policies に寄せる
 *
 * moduleキーについて
 * ------------------------------------------------------------
 * - module は config.php の role_sets / modules と同じキーを使う
 *   例: 'users', 'audit', 'cars', 'car_leases', 'car_fy_costs' など
 *
 * financials / stores は別システム土台のため削除済み
 */

class Policies
{
    /**
     * role_sets 取得（config.phpが唯一の正）
     *
     * @return int[]
     */
    private static function roleSet(string $module, string $capability): array
    {
        $cfg = require __DIR__ . '/config.php';
        $sets = $cfg['role_sets'] ?? [];

        $arr = $sets[$module][$capability] ?? [];
        if (!is_array($arr)) return [];

        // 厳密比較 in_array(..., true) を使うため、整数に統一
        return array_map('intval', $arr);
    }

    /**
     * 共通判定（true/false）
     *
     * capability:
     * - 'view' : 一覧/詳細/検索など「閲覧」全般
     * - 'edit' : 登録/更新/削除/編集画面/保存ボタンなど「変更」全般
     */
    private static function allowed(array $u, string $module, string $capability): bool
    {
        $role = (int)($u['role_code'] ?? 0);
        return in_array($role, self::roleSet($module, $capability), true);
    }

    // ============================================================
    // menuVisible（メニュー表示用）
    // ============================================================
    public static function menuVisible(array $u, string $module): bool
    {
        return self::allowed($u, $module, 'view');
    }

    // ============================================================
    // guard（Controllerでのアクセス制御）
    // ============================================================
    public static function guardView(array $u, string $module): void
    {
        if (!self::allowed($u, $module, 'view')) {
            Response::forbidden();
        }
    }

    public static function guardEdit(array $u, string $module): void
    {
        if (!self::allowed($u, $module, 'edit')) {
            Response::forbidden();
        }
    }

    // ============================================================
    // Module helpers（view側で読みやすくするための薄いラッパー）
    // ============================================================
    public static function canViewUsers(array $u): bool
    {
        return self::allowed($u, 'users', 'view');
    }

    public static function canEditUsers(array $u): bool
    {
        return self::allowed($u, 'users', 'edit');
    }

    public static function canViewCars(array $u): bool
    {
        return self::allowed($u, 'cars', 'view');
    }

    public static function canEditCars(array $u): bool
    {
        return self::allowed($u, 'cars', 'edit');
    }

    // ============================================================
    // 車両リース
    // ============================================================
    public static function canViewCarLeases(array $u): bool
    {
        return self::allowed($u, 'car_leases', 'view');
    }

    public static function canEditCarLeases(array $u): bool
    {
        return self::allowed($u, 'car_leases', 'edit');
    }

    // ============================================================
    // 車両 年度別コスト
    // ============================================================
    public static function canViewCarFyCosts(array $u): bool
    {
        return self::allowed($u, 'car_fy_costs', 'view');
    }

    public static function canEditCarFyCosts(array $u): bool
    {
        return self::allowed($u, 'car_fy_costs', 'edit');
    }

    // ============================================================
    // 車両 収支
    // ============================================================
    public static function canViewCarProfits(array $u): bool
    {
        return self::allowed($u, 'car_profits', 'view');
    }

    public static function canEditCarProfits(array $u): bool
    {
        return self::allowed($u, 'car_profits', 'edit');
    }

    // ============================================================
    // ★リース整合性チェック（追加）
    // ============================================================
    public static function canViewCarLeaseHealth(array $u): bool
    {
        return self::allowed($u, 'car_lease_health', 'view');
    }

    public static function canEditCarLeaseHealth(array $u): bool
    {
        return self::allowed($u, 'car_lease_health', 'edit');
    }

    // ============================================================
    // 既存モジュール
    // ============================================================
    public static function canViewOfficeCustomers(array $u): bool
    {
        return self::allowed($u, 'office_customers', 'view');
    }

    public static function canEditOfficeCustomers(array $u): bool
    {
        return self::allowed($u, 'office_customers', 'edit');
    }

    /**
     * 個人顧客（personal_customers）
     */
    public static function canViewPersonalCustomers(array $u): bool
    {
        return self::allowed($u, 'personal_customers', 'view');
    }

    public static function canEditPersonalCustomers(array $u): bool
    {
        return self::allowed($u, 'personal_customers', 'edit');
    }

    public static function canViewAudit(array $u): bool
    {
        return self::allowed($u, 'audit', 'view');
    }

    public static function canEditAudit(array $u): bool
    {
        return self::allowed($u, 'audit', 'edit');
    }

    // ============================================================
    // fieldVisible（項目単位の表示制御）
    // ============================================================
    public static function fieldVisible(array $u, string $module, string $field): bool
    {
        if ($module === 'users') {
            switch ($field) {
                default:
                    return true;
            }
        }

        // 他モジュールは現時点で制限なし
        return true;
    }

    // ============================================================
    // 保存側の補助（任意：事故防止）
    // ============================================================
    public static function sanitizeInputByFieldVisibility(array $u, string $module, array $input, array $fields): array
    {
        foreach ($fields as $f) {
            if (!self::fieldVisible($u, $module, (string)$f)) {
                unset($input[$f]);
            }
        }
        return $input;
    }
}