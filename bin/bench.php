<?php

declare(strict_types=1);

/**
 * Extism プラグインの実行コストを計測する（登壇スライド用）
 *
 *   docker compose run --rm app php bin/bench.php
 *
 * wasmtime のコンパイルキャッシュがない状態（初回）を測るときは、空のキャッシュディレクトリを指定する
 *
 *   docker compose run --rm -e XDG_CACHE_HOME=/tmp/empty-cache app php bin/bench.php
 */

use App\EnhanceRenderer;
use Extism\Manifest;
use Extism\Manifest\PathWasmSource;
use Extism\Plugin;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$validatorWasm = $root . '/wasm/validator.wasm';
$iterations = (int) ($argv[1] ?? 200);

$valid = ['title' => 'スライドを作る', 'description' => '', 'startAt' => '2026-11-03T10:00', 'endAt' => '2026-11-03T12:00', 'status' => 'TODO'];
$invalid = ['title' => '', 'startAt' => '2026-11-03T12:00', 'endAt' => '2026-11-03T10:00', 'status' => 'WIP'];

/**
 * @param callable(): mixed $fn
 * @return array{first: float, median: float, p95: float}
 */
function measure(callable $fn, int $iterations): array
{
    $start = hrtime(true);
    $fn();
    $first = (hrtime(true) - $start) / 1e6;

    $samples = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $fn();
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    sort($samples);

    return [
        'first' => $first,
        'median' => $samples[intdiv(count($samples), 2)],
        'p95' => $samples[(int) floor(count($samples) * 0.95)],
    ];
}

$newPlugin = static fn (): Plugin => new Plugin(new Manifest(new PathWasmSource($validatorWasm)), true);

// 1. プラグインの生成だけ（Wasm の読み込み・コンパイル or キャッシュ読み込み・インスタンス化）
$results['プラグイン生成のみ'] = measure($newPlugin, $iterations);

// 2. リクエストごとに生成して呼ぶ（いまの TaskValidator がビルトインサーバーで動くときと同じ）
$results['生成 + validate（毎回作り直す）'] = measure(
    static fn () => $newPlugin()->call('validate', json_encode($valid)),
    $iterations,
);

// 3. 生成済みのプラグインを使い回す（FrankenPHP / RoadRunner などの常駐型を想定）
$plugin = $newPlugin();
$results['validate のみ（使い回し・正常値）'] = measure(
    static fn () => $plugin->call('validate', json_encode($valid)),
    $iterations,
);
$results['validate のみ（使い回し・エラー値）'] = measure(
    static fn () => $plugin->call('validate', json_encode($invalid)),
    $iterations,
);

// 4. 比較用: 同じルールを PHP で手書きした場合（＝「2回書く」場合）
$phpValidate = static function (array $t): array {
    $errors = [];
    $title = trim((string) ($t['title'] ?? ''));
    if ($title === '') {
        $errors['title'][] = 'タイトルは必須です';
    } elseif (mb_strlen($title) > 100) {
        $errors['title'][] = 'タイトルは100文字以内で入力してください';
    }
    if (mb_strlen(trim((string) ($t['description'] ?? ''))) > 1000) {
        $errors['description'][] = '詳細は1000文字以内で入力してください';
    }
    foreach (['startAt' => '開始日時', 'endAt' => '終了日時'] as $key => $label) {
        $v = trim((string) ($t[$key] ?? ''));
        if ($v === '') {
            $errors[$key][] = "{$label}は必須です";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $v) || strtotime($v) === false) {
            $errors[$key][] = "{$label}の形式が正しくありません";
        }
    }
    if (!in_array($t['status'] ?? null, ['TODO', 'DOING', 'DONE'], true)) {
        $errors['status'][] = 'ステータスは TODO / DOING / DONE のいずれかを指定してください';
    }
    if (!isset($errors['startAt'], $errors['endAt']) && strtotime($t['startAt']) > strtotime($t['endAt'])) {
        $errors['endAt'][] = '終了日時は開始日時以降にしてください';
    }

    return $errors === [] ? ['success' => true] : ['success' => false, 'errors' => $errors];
};
$results['比較: PHP で手書きしたバリデーション'] = measure(static fn () => $phpValidate($valid), $iterations);

// 5. Enhance SSR（タスク3件の一覧を描画）
$renderer = new EnhanceRenderer($root . '/wasm/enhance-ssr.wasm', $root . '/build/elements');
$tasks = [
    ['id' => 1, 'title' => 'スライドを作る', 'description' => '', 'startAt' => '2026-11-01T10:00', 'endAt' => '2026-11-01T12:00', 'status' => 'DONE'],
    ['id' => 2, 'title' => 'デモを仕上げる', 'description' => '', 'startAt' => '2026-11-02T10:00', 'endAt' => '2026-11-02T18:00', 'status' => 'DOING'],
    ['id' => 3, 'title' => '新潟へ移動', 'description' => '', 'startAt' => '2026-11-07T08:00', 'endAt' => '2026-11-07T11:00', 'status' => 'TODO'],
];
$state = [
    'tasks' => $tasks,
    'form' => ['mode' => 'create', 'id' => null, 'values' => ['title' => '', 'description' => '', 'startAt' => '', 'endAt' => '', 'status' => 'TODO'], 'errors' => new stdClass(), 'notice' => null, 'skipClientValidation' => false],
];
$markup = file_get_contents($root . '/templates/index.html');
$results['Enhance SSR（生成 + 描画）'] = measure(static fn () => $renderer->render($markup, $state), (int) max(20, $iterations / 10));

printf("PHP %s / %d 回計測 / XDG_CACHE_HOME=%s\n\n", PHP_VERSION, $iterations, getenv('XDG_CACHE_HOME') ?: '(未設定)');
printf("| %s | 1回目 | 中央値 | p95 |\n|---|---:|---:|---:|\n", '処理');
foreach ($results as $label => $r) {
    printf("| %s | %.3f ms | %.3f ms | %.3f ms |\n", $label, $r['first'], $r['median'], $r['p95']);
}
