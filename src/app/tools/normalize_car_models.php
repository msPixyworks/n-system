<?php
/**
 * normalize_car_models.php
 * ============================================================
 * 目的:
 * - src/app/config/car_models.php を「仕様どおり」に正規化して上書きする
 *
 * 正規化仕様:
 * 1) 各メーカー配列の先頭は必ず '0' => '-- Please select --'
 * 2) 「その他」は必ず 'z999' => 'その他' を末尾に統一
 * 3) '-- Please select --' が別キーに入っている場合は削除し、0に統一
 * 4) ラベルに含まれる HTMLエンティティ（&amp; 等）はデコードする
 *
 * 入力ファイル対応:
 * - return 配列（return [...];）
 * - どんな変数名でもOK（$cars, $models, $car_models_by_maker など）
 *   → include前後の get_defined_vars() で「新たに生えた配列」を自動検出する
 *
 * 実行:
 *   php src/app/tools/normalize_car_models.php
 */

declare(strict_types=1);

// ------------------------------------------------------------
// 対象ファイル
// ------------------------------------------------------------
$root = realpath(__DIR__ . '/../config');
if (!$root) {
    fwrite(STDERR, "ERROR: cannot resolve app/config root.\n");
    exit(1);
}

$path = $root . '/car_models.php';
if (!is_file($path)) {
    fwrite(STDERR, "ERROR: car_models.php not found: {$path}\n");
    exit(1);
}

// ------------------------------------------------------------
// load (return配列 or 変数代入を自動検出)
// ------------------------------------------------------------
$loadModels = function (string $file): array {
    // 1) return配列ならそのまま
    $ret = require $file;
    if (is_array($ret)) return $ret;

    // 2) returnなしの場合：include前後の変数差分から配列を探す
    $extract = (function (string $file) {
        $before = get_defined_vars();

        // include実行（ここで配列が変数に代入される想定）
        include $file;

        $after = get_defined_vars();

        // 増えた変数を候補に
        $candidates = [];
        foreach ($after as $name => $val) {
            if (array_key_exists($name, $before)) continue;
            if (!is_array($val)) continue;

            // 「メーカー配列っぽい」特徴でスコアリング
            // - キーが string
            // - 値が array（maker => [code=>label]）が多い
            $score = 0;

            $keys = array_keys($val);
            $stringKeyCount = 0;
            foreach ($keys as $k) {
                if (is_string($k)) $stringKeyCount++;
            }
            if ($stringKeyCount > 0) $score += 2;

            $nestedArrayCount = 0;
            foreach ($val as $vv) {
                if (is_array($vv)) $nestedArrayCount++;
            }
            if ($nestedArrayCount >= 2) $score += 5;      // makerが複数あると強い
            if ($nestedArrayCount >= 5) $score += 5;

            // makerキーによくあるものが含まれるとさらに加点
            $probeKeys = ['toyota','lexus','nissan','honda','bmw','audi','volkswagen'];
            foreach ($probeKeys as $pk) {
                if (isset($val[$pk]) && is_array($val[$pk])) {
                    $score += 5;
                    break;
                }
            }

            // 2次元配列の中が「code=>label」っぽいか
            // 文字列ラベルがそこそこあると加点
            $labelCount = 0;
            foreach ($val as $vv) {
                if (!is_array($vv)) continue;
                foreach ($vv as $lbl) {
                    if (is_string($lbl) && $lbl !== '') $labelCount++;
                    if ($labelCount >= 10) break 2;
                }
            }
            if ($labelCount >= 10) $score += 3;

            $candidates[] = ['name' => $name, 'score' => $score, 'value' => $val];
        }

        // 最高スコアを採用
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        return $candidates[0]['value'] ?? null;
    });

    $val = $extract($file);
    if (is_array($val)) return $val;

    throw new RuntimeException('car_models.php did not return array and no suitable array variable was detected.');
};

try {
    $models = $loadModels($path);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

// ------------------------------------------------------------
// helpers
// ------------------------------------------------------------
$decode = function ($s): string {
    return html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

$isPleaseSelect = function (string $label): bool {
    return trim($label) === '-- Please select --';
};

$isOther = function (string $label): bool {
    return trim($label) === 'その他';
};

$normalizeMaker = function (array $makerModels) use ($decode, $isPleaseSelect, $isOther): array {
    $body = [];
    $please = '-- Please select --';
    $other  = 'その他';

    foreach ($makerModels as $code => $label) {
        $lab = $decode((string)$label);
        $key = (string)$code;

        // please select は0に統一するのでスキップ
        if ($isPleaseSelect($lab)) continue;

        // その他は z999 に統一するのでスキップ
        if ($isOther($lab)) continue;

        // 重複コードは先勝ち（揺れ吸収）
        if (!isset($body[$key])) {
            $body[$key] = $lab;
        }
    }

    // 先頭に 0 を必ず入れる
    $out = ['0' => $please];

    foreach ($body as $k => $v) {
        if ($k === '0' || $k === 'z999') continue;
        $out[$k] = $v;
    }

    // 末尾に z999 を必ず入れる
    $out['z999'] = $other;

    return $out;
};

// ------------------------------------------------------------
// normalize all makers
// ------------------------------------------------------------
$normalized = [];
foreach ($models as $maker => $list) {
    if (!is_array($list)) continue;
    $normalized[(string)$maker] = $normalizeMaker($list);
}

// ------------------------------------------------------------
// backup & write
// ------------------------------------------------------------
$bak = $path . '.bak';
if (!copy($path, $bak)) {
    fwrite(STDERR, "ERROR: failed to create backup: {$bak}\n");
    exit(1);
}

$php = "<?php\n";
$php .= "/**\n";
$php .= " * car_models.php\n";
$php .= " * ============================================================\n";
$php .= " * 車種マスタ（正規化済み）\n";
$php .= " * - 先頭: '0' => '-- Please select --'\n";
$php .= " * - 末尾: 'z999' => 'その他'\n";
$php .= " * - HTMLエンティティはデコード済み（&amp; → &）\n";
$php .= " */\n";
$php .= "return " . var_export($normalized, true) . ";\n";

if (file_put_contents($path, $php) === false) {
    fwrite(STDERR, "ERROR: failed to write normalized car_models.php\n");
    exit(1);
}

fwrite(STDOUT, "OK: normalized car_models.php written.\n");
fwrite(STDOUT, "Backup created: {$bak}\n");
