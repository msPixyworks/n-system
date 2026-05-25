-- entity_personal_customers_entries.sql
-- ============================================================
-- 個人顧客（personal_customers）
--
-- 方針（office_customers と同期）
-- - 監査：create/update/delete は全カラム差分
-- - 論理削除：deleted_at をセット（NULL=有効）
-- - 一覧はデフォルト deleted_at IS NULL（「削除も含める」チェックで解除）
-- ============================================================

CREATE TABLE IF NOT EXISTS personal_customers (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

  -- ----------------------------------------------------------
  -- 1) 本人情報
  -- ----------------------------------------------------------
  name VARCHAR(100) NOT NULL COMMENT '氏名',
  letter VARCHAR(100) NOT NULL COMMENT '氏名フリガナ（全角カタカナ）',

  tel01 VARCHAR(15) NULL COMMENT '電話番号1（ハイフン除去・数字のみ）',

  zip VARCHAR(7) NULL COMMENT '郵便番号（ハイフン除去7桁）',
  pref_code TINYINT UNSIGNED NULL COMMENT '県名コード（1-47）',
  addr01 VARCHAR(255) NULL COMMENT '住所1（市町村以下）',
  addr02 VARCHAR(255) NULL COMMENT '住所2（地番以降）',

  mail01 VARCHAR(191) NULL COMMENT 'メールアドレス1',
  mail02 VARCHAR(191) NULL COMMENT 'メールアドレス2（mail01と同一禁止）',

  birthday_year SMALLINT UNSIGNED NULL COMMENT '誕生日（年）',
  birthday_month TINYINT UNSIGNED NULL COMMENT '誕生日（月）',
  birthday_day TINYINT UNSIGNED NULL COMMENT '誕生日（日）',

  license_color TINYINT UNSIGNED NULL COMMENT '免許証の色（1:ブルー/2:ゴールド/3:グリーン）',

  mobile01 VARCHAR(15) NULL COMMENT '携帯（ハイフン除去・数字のみ）',

  emergency_contact VARCHAR(100) NULL COMMENT '緊急連絡先名',
  emergency_relationship VARCHAR(50) NULL COMMENT '緊急連絡先の続柄',
  emergency_tel VARCHAR(15) NULL COMMENT '緊急連絡先の電話番号（ハイフン除去・数字のみ）',

  -- ----------------------------------------------------------
  -- 2) お勤め先情報
  -- ----------------------------------------------------------
  office VARCHAR(200) NULL COMMENT '会社名',
  office_letter VARCHAR(200) NULL COMMENT '会社名フリガナ（全角カタカナ推奨）',

  office_zip VARCHAR(7) NULL COMMENT '勤務先 郵便番号（ハイフン除去7桁）',
  office_pref_code TINYINT UNSIGNED NULL COMMENT '勤務先 県名コード（1-47）',
  office_addr01 VARCHAR(255) NULL COMMENT '勤務先 住所1（市町村以下）',
  office_addr02 VARCHAR(255) NULL COMMENT '勤務先 住所2（地番以降）',

  office_tel01 VARCHAR(15) NULL COMMENT '勤務先 電話番号1（ハイフン除去・数字のみ）',
  office_tel02 VARCHAR(15) NULL COMMENT '勤務先 電話番号2（ハイフン除去・数字のみ）',

  years_of_service TINYINT UNSIGNED NULL COMMENT '勤続年数（0-99 年）',

  -- ----------------------------------------------------------
  -- 3) ご来社経緯
  -- ----------------------------------------------------------
  background TINYINT UNSIGNED NULL COMMENT 'ご来社経緯（1:HP/2:チラシ/3:営業）',
  introducer VARCHAR(100) NULL COMMENT 'ご紹介者',
  others VARCHAR(200) NULL COMMENT 'その他',

  -- ----------------------------------------------------------
  -- 4) 備考
  -- ----------------------------------------------------------
  remarks TEXT NULL COMMENT '備考（〜10000文字）',

  -- ----------------------------------------------------------
  -- 5) ご家族情報（1〜5）
  --    ※ブロック入力開始で relationship/name は必須（アプリ側）
  -- ----------------------------------------------------------

  -- 家族1
  first_relationship VARCHAR(50) NULL COMMENT 'ご家族1 続柄',
  first_name VARCHAR(100) NULL COMMENT 'ご家族1 氏名',
  first_letter VARCHAR(100) NULL COMMENT 'ご家族1 氏名フリガナ',
  first_tel01 VARCHAR(15) NULL COMMENT 'ご家族1 電話番号1',
  first_tel02 VARCHAR(15) NULL COMMENT 'ご家族1 電話番号2',
  first_zip VARCHAR(7) NULL COMMENT 'ご家族1 郵便番号',
  first_pref_code TINYINT UNSIGNED NULL COMMENT 'ご家族1 県名コード（1-47）',
  first_addr01 VARCHAR(255) NULL COMMENT 'ご家族1 住所1',
  first_addr02 VARCHAR(255) NULL COMMENT 'ご家族1 住所2',
  first_mail01 VARCHAR(191) NULL COMMENT 'ご家族1 メールアドレス1',
  first_mail02 VARCHAR(191) NULL COMMENT 'ご家族1 メールアドレス2',
  first_remarks TEXT NULL COMMENT 'ご家族1 備考',

  -- 家族2
  second_relationship VARCHAR(50) NULL COMMENT 'ご家族2 続柄',
  second_name VARCHAR(100) NULL COMMENT 'ご家族2 氏名',
  second_letter VARCHAR(100) NULL COMMENT 'ご家族2 氏名フリガナ',
  second_tel01 VARCHAR(15) NULL COMMENT 'ご家族2 電話番号1',
  second_tel02 VARCHAR(15) NULL COMMENT 'ご家族2 電話番号2',
  second_zip VARCHAR(7) NULL COMMENT 'ご家族2 郵便番号',
  second_pref_code TINYINT UNSIGNED NULL COMMENT 'ご家族2 県名コード（1-47）',
  second_addr01 VARCHAR(255) NULL COMMENT 'ご家族2 住所1',
  second_addr02 VARCHAR(255) NULL COMMENT 'ご家族2 住所2',
  second_mail01 VARCHAR(191) NULL COMMENT 'ご家族2 メールアドレス1',
  second_mail02 VARCHAR(191) NULL COMMENT 'ご家族2 メールアドレス2',
  second_remarks TEXT NULL COMMENT 'ご家族2 備考',

  -- 家族3
  third_relationship VARCHAR(50) NULL COMMENT 'ご家族3 続柄',
  third_name VARCHAR(100) NULL COMMENT 'ご家族3 氏名',
  third_letter VARCHAR(100) NULL COMMENT 'ご家族3 氏名フリガナ',
  third_tel01 VARCHAR(15) NULL COMMENT 'ご家族3 電話番号1',
  third_tel02 VARCHAR(15) NULL COMMENT 'ご家族3 電話番号2',
  third_zip VARCHAR(7) NULL COMMENT 'ご家族3 郵便番号',
  third_pref_code TINYINT UNSIGNED NULL COMMENT 'ご家族3 県名コード（1-47）',
  third_addr01 VARCHAR(255) NULL COMMENT 'ご家族3 住所1',
  third_addr02 VARCHAR(255) NULL COMMENT 'ご家族3 住所2',
  third_mail01 VARCHAR(191) NULL COMMENT 'ご家族3 メールアドレス1',
  third_mail02 VARCHAR(191) NULL COMMENT 'ご家族3 メールアドレス2',
  third_remarks TEXT NULL COMMENT 'ご家族3 備考',

  -- 家族4
  fourth_relationship VARCHAR(50) NULL COMMENT 'ご家族4 続柄',
  fourth_name VARCHAR(100) NULL COMMENT 'ご家族4 氏名',
  fourth_letter VARCHAR(100) NULL COMMENT 'ご家族4 氏名フリガナ',
  fourth_tel01 VARCHAR(15) NULL COMMENT 'ご家族4 電話番号1',
  fourth_tel02 VARCHAR(15) NULL COMMENT 'ご家族4 電話番号2',
  fourth_zip VARCHAR(7) NULL COMMENT 'ご家族4 郵便番号',
  fourth_pref_code TINYINT UNSIGNED NULL COMMENT 'ご家族4 県名コード（1-47）',
  fourth_addr01 VARCHAR(255) NULL COMMENT 'ご家族4 住所1',
  fourth_addr02 VARCHAR(255) NULL COMMENT 'ご家族4 住所2',
  fourth_mail01 VARCHAR(191) NULL COMMENT 'ご家族4 メールアドレス1',
  fourth_mail02 VARCHAR(191) NULL COMMENT 'ご家族4 メールアドレス2',
  fourth_remarks TEXT NULL COMMENT 'ご家族4 備考',

  -- 家族5
  fifth_relationship VARCHAR(50) NULL COMMENT 'ご家族5 続柄',
  fifth_name VARCHAR(100) NULL COMMENT 'ご家族5 氏名',
  fifth_letter VARCHAR(100) NULL COMMENT 'ご家族5 氏名フリガナ',
  fifth_tel01 VARCHAR(15) NULL COMMENT 'ご家族5 電話番号1',
  fifth_tel02 VARCHAR(15) NULL COMMENT 'ご家族5 電話番号2',
  fifth_zip VARCHAR(7) NULL COMMENT 'ご家族5 郵便番号',
  fifth_pref_code TINYINT UNSIGNED NULL COMMENT 'ご家族5 県名コード（1-47）',
  fifth_addr01 VARCHAR(255) NULL COMMENT 'ご家族5 住所1',
  fifth_addr02 VARCHAR(255) NULL COMMENT 'ご家族5 住所2',
  fifth_mail01 VARCHAR(191) NULL COMMENT 'ご家族5 メールアドレス1',
  fifth_mail02 VARCHAR(191) NULL COMMENT 'ご家族5 メールアドレス2',
  fifth_remarks TEXT NULL COMMENT 'ご家族5 備考',

  -- ----------------------------------------------------------
  -- timestamps / logical delete
  -- ----------------------------------------------------------
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL COMMENT '削除日時（論理削除）',

  -- ----------------------------------------------------------
  -- indexes（最小限：一覧/検索用）
  -- ----------------------------------------------------------
  INDEX idx_personal_customers_deleted (deleted_at),
  INDEX idx_personal_customers_name (name),
  INDEX idx_personal_customers_tel01 (tel01),
  INDEX idx_personal_customers_zip (zip),
  INDEX idx_personal_customers_pref (pref_code),
  INDEX idx_personal_customers_background (background),
  INDEX idx_personal_customers_mail01 (mail01)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
