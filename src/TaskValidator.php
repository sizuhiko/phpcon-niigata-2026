<?php

declare(strict_types=1);

namespace App;

use Extism\Manifest;
use Extism\Manifest\PathWasmSource;
use Extism\Plugin;

/**
 * フロントエンドと同じ Zod のルール（shared/task-schema.mjs）を
 * Extism プラグイン（wasm/validator.wasm）経由で実行する。
 */
final class TaskValidator
{
    private ?Plugin $plugin = null;

    public function __construct(private readonly string $wasmPath)
    {
    }

    /**
     * @return array{success: true, data: array<string, mixed>}|array{success: false, errors: array<string, list<string>>}
     */
    public function validate(mixed $task): array
    {
        $this->plugin ??= new Plugin(new Manifest(new PathWasmSource($this->wasmPath)), true);

        $output = $this->plugin->call('validate', json_encode($task, JSON_THROW_ON_ERROR));

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }
}
