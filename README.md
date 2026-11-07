# Extism TODO — PHPカンファレンス新潟2026 デモ

「Wasm は PHP と JS の壁を壊せるか？ Extism プラグインシステムがもたらす越境開発」のデモプログラムです。

## 起動

```sh
docker compose up --build
```

http://localhost:8080 を開きます。データは SQLite（Docker volume `todo-data`）に保存されます。初期データに戻すときは `docker compose down -v` を実行してください。

## 越境しているもの

| 共有するもの | ブラウザ | PHP |
|---|---|---|
| バリデーション `shared/task-schema.ts`（Zod） | そのまま import | `plugins/validator` で Extism プラグイン（`wasm/validator.wasm`）にビルドし、`src/TaskValidator.php` から呼び出す |
| Web Components `elements/*.ts`（Enhance） | `@enhance/element` で描画 | JS に変換したものを Enhance SSR の Extism プラグイン（`wasm/enhance-ssr.wasm`）で SSR（`src/EnhanceRenderer.php`） |

フォームの「フロントエンドの検証をスキップしてサーバーに送信する」にチェックを入れると、同じルールで PHP 側が 400 を返す様子を確認できます。

## API

| メソッド | パス | 説明 |
|---|---|---|
| GET | `/api/tasks` | 一覧（開始日時の古い順） |
| POST | `/api/tasks` | 追加（ステータスは常に TODO） |
| PUT | `/api/tasks/{id}` | 編集 |
| PATCH | `/api/tasks/{id}/status` | ステータス変更 |
| DELETE | `/api/tasks/{id}` | 削除 |

バリデーションエラーは `400` で `{ "message": "...", "errors": { "フィールド": ["メッセージ"] } }` を返します。

## 開発

フロントエンドは TypeScript で書いています。型チェックは `npm run typecheck` で行います（Docker のビルドでも実行されます）。

## 構成

```
bin/bench.php          Extism プラグインの実行コストの計測
bootstrap/app.php      Slim のルーティング
src/                   TaskRepository (SQLite) / TaskValidator (Extism) / EnhanceRenderer (Extism)
shared/                フロント・バックエンド共通の Zod スキーマと状態の型
plugins/validator/     Zod スキーマを Extism プラグインにするエントリーポイント
elements/              Enhance の Web Components（ブラウザ・SSR 共通）
frontend/              ブラウザ用エントリーポイントと Tailwind CSS
types/                 型定義を同梱していないパッケージの型宣言
templates/index.html   ページのテンプレート
```

Extism プラグインの実行コストは `docker compose run --rm app php bin/bench.php` で計測できます。

主なバージョン: PHP 8.5 / Extism runtime v1.41.0 / extism-js v1.7.0 / enhance-ssr-wasm v0.0.4 / Zod 4.6.7 / Tailwind CSS 4.3.3 / TypeScript 7.0.2
