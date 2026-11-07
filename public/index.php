<?php

declare(strict_types=1);

// PHP ビルトインサーバーのルータースクリプトとして動かすので、public 配下の静的ファイルはそのまま配信する
if (PHP_SAPI === 'cli-server') {
    $file = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($file !== false && is_file($file) && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

(require __DIR__ . '/../bootstrap/app.php')->run();
