<?php

declare(strict_types=1);

namespace App;

use Extism\Manifest;
use Extism\Manifest\PathWasmSource;
use Extism\Plugin;

/**
 * Enhance SSR（wasm/enhance-ssr.wasm）を Extism 経由で実行し、
 * ブラウザと同じ elements/*.mjs の Web Components をサーバーサイドで描画する。
 */
final class EnhanceRenderer
{
    public function __construct(
        private readonly string $wasmPath,
        private readonly string $elementsDir,
    ) {
    }

    /**
     * @param array<string, mixed> $initialState
     */
    public function render(string $markup, array $initialState): string
    {
        $plugin = new Plugin(new Manifest(new PathWasmSource($this->wasmPath)), true);

        $output = $plugin->call('ssr', json_encode([
            'markup' => $markup,
            'elements' => $this->loadElements(),
            'initialState' => $initialState,
        ], JSON_THROW_ON_ERROR));

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR)['document'];
    }

    /**
     * Enhance SSR Wasm は要素を「タグ名 => 関数のソース文字列」で受け取る。
     * elements/*.ts を esbuild で変換した build/elements/*.js は
     * 「function TaskList(...) { ... }」と末尾の「export { TaskList as default };」だけになるので、
     * export 文を取り除いた残りを関数のソースとして渡す。
     *
     * @return array<string, string>
     */
    private function loadElements(): array
    {
        $elements = [];
        foreach (glob($this->elementsDir . '/*.js') as $file) {
            $source = trim(preg_replace('/^export\s*\{[^}]*\};?\s*$/m', '', file_get_contents($file)));
            // 関数の外に処理があると SSR では参照できないので、関数宣言1つだけで構成されているか確認する
            if (!str_starts_with($source, 'function ') || !str_ends_with($source, '}')) {
                throw new \RuntimeException("要素は関数宣言1つだけで書いてください（import や関数外の処理は使えません）: {$file}");
            }
            $elements[basename($file, '.js')] = $source;
        }

        return $elements;
    }
}
