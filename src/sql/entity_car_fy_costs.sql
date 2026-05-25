-- src/sql/entity_car_fy_costs.sql
-- ============================================================
-- 車両 年度別コスト台帳（car_fy_costs）
--
-- 背景:
-- - cars テーブルには税金/保険/経費が「年額の現在値」として1つずつ存在する。
-- - しかし、年度ごとに過去値を固定しないと、過去年度の収支が cars の変更で変わってしまう。
--
-- 方針:
-- - 当年度（現在年度）の支出は cars の現在値（car_tax / car_insurance_premium / total_expenses）を参照する。
-- - 過去年度（前年度以前）の支出は、本テーブル car_fy_costs を参照する。
-- - 本テーブルは「過去年度の確定値」を保持するが、業務要件により編集を許可する。
--   （編集履歴は監査ログで担保する）
--
-- 年度（fy）:
-- - 例：2025 は「2025/04/01〜2026/03/31」を意味する（年度開始は config で変更可能）
-- - DBは fy（年度キー）だけを保持し、開始/終了日の算出はアプリ側で行う。
--
-- 制約:
-- - 同一車両×同一年度は1行のみ（UNIQUE(car_id, fy)）
-- ============================================================

CREATE TABLE IF NOT EXISTS car_fy_costs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'PK',

  car_id BIGINT UNSIGNED NOT NULL COMMENT '車両ID（cars.id）',
  fy     SMALLINT UNSIGNED NOT NULL COMMENT '年度（例: 2025=2025年度）',

  -- 年度別（年額）
  tax_amount       BIGINT UNSIGNED NULL COMMENT '自動車税（年額・円）',
  insurance_amount BIGINT UNSIGNED NULL COMMENT '自動車保険料（年額・円）',
  expense_amount   BIGINT UNSIGNED NULL COMMENT '経費総額（年額・円）',

  notes TEXT NULL COMMENT '備考',

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  deleted_at DATETIME NULL COMMENT '削除日時（論理削除）',

  KEY idx_car_fy_costs_car_id (car_id),
  KEY idx_car_fy_costs_fy (fy),
  KEY idx_car_fy_costs_deleted (deleted_at),

  UNIQUE KEY uq_car_fy_costs_car_fy (car_id, fy)

  -- 外部キー（運用方針により有効化）
  -- ,CONSTRAINT fk_car_fy_costs_car_id FOREIGN KEY (car_id) REFERENCES cars(id)
  --   ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='車両 年度別コスト台帳（税/保険/経費）';
