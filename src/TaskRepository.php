<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

/**
 * タスクを SQLite に保存する。
 * PHP のビルトインサーバーはリクエストごとに状態が消えるため、ファイルに永続化している。
 */
final class TaskRepository
{
    private const string COLUMNS = 'id, title, description, start_at, end_at, status';

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        $this->migrate();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT ' . self::COLUMNS . ' FROM tasks ORDER BY start_at ASC, id ASC')->fetchAll();

        return array_map(self::toTask(...), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : self::toTask($row);
    }

    /**
     * @param array{title: string, description: string, startAt: string, endAt: string, status: string} $task
     * @return array<string, mixed>
     */
    public function create(array $task): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (title, description, start_at, end_at, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$task['title'], $task['description'], $task['startAt'], $task['endAt'], $task['status']]);

        return $this->find((int) $this->pdo->lastInsertId());
    }

    /**
     * @param array{title: string, description: string, startAt: string, endAt: string, status: string} $task
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $task): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tasks SET title = ?, description = ?, start_at = ?, end_at = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$task['title'], $task['description'], $task['startAt'], $task['endAt'], $task['status'], $id]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function toTask(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'startAt' => $row['start_at'],
            'endAt' => $row['end_at'],
            'status' => $row['status'],
        ];
    }

    private function migrate(): void
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $exists = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'tasks'")->fetchColumn();
            if ($exists === false) {
                $this->pdo->exec(<<<'SQL'
                    CREATE TABLE tasks (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        description TEXT NOT NULL DEFAULT '',
                        start_at TEXT NOT NULL,
                        end_at TEXT NOT NULL,
                        status TEXT NOT NULL DEFAULT 'TODO'
                    )
                    SQL);
                $this->seed();
            }
            $this->pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /**
     * 起動直後から一覧に表示されるサンプルタスク
     */
    private function seed(): void
    {
        $today = new DateTimeImmutable('today');
        $at = static fn (string $modifier): string => $today->modify($modifier)->format('Y-m-d\TH:i');

        $tasks = [
            ['CFP に応募する', 'PHPカンファレンス新潟2026 のプロポーザルを fortee から提出する', $at('-60 days 10:00'), $at('-60 days 12:00'), 'DONE'],
            ['デモアプリを作る', "Extism で Zod のバリデーションを PHP と共通化する\nEnhance で SSR する", $at('today 09:00'), $at('+1 day 18:00'), 'DOING'],
            ['登壇リハーサル', '持ち時間に収まるか通しで確認する', $at('+2 days 20:00'), $at('+2 days 21:00'), 'TODO'],
        ];

        $stmt = $this->pdo->prepare('INSERT INTO tasks (title, description, start_at, end_at, status) VALUES (?, ?, ?, ?, ?)');
        foreach ($tasks as $task) {
            $stmt->execute($task);
        }
    }
}
