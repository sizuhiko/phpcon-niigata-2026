// タスクのバリデーションルール（フロントエンドとバックエンドで共通）
//
// - ブラウザ: frontend/app.ts から import して Zod をそのまま実行
// - PHP:      plugins/validator で Extism プラグイン（Wasm）にビルドして実行
import { z } from 'zod'

export const STATUSES = ['TODO', 'DOING', 'DONE'] as const

// <input type="datetime-local"> の値（例: 2026-11-03T10:00）
const DATETIME_PATTERN = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/

const dateTime = (label: string) =>
  z
    .string({ error: `${label}は必須です` })
    .trim()
    .min(1, `${label}は必須です`)
    .regex(DATETIME_PATTERN, `${label}の形式が正しくありません`)
    .refine((value) => !Number.isNaN(Date.parse(value)), `${label}が正しい日時ではありません`)

export const taskSchema = z
  .object({
    title: z
      .string({ error: 'タイトルは必須です' })
      .trim()
      .min(1, 'タイトルは必須です')
      .max(100, 'タイトルは100文字以内で入力してください'),
    description: z
      .string({ error: '詳細は文字列で入力してください' })
      .trim()
      .max(1000, '詳細は1000文字以内で入力してください')
      .default(''),
    startAt: dateTime('開始日時'),
    endAt: dateTime('終了日時'),
    status: z.enum(STATUSES, { error: 'ステータスは TODO / DOING / DONE のいずれかを指定してください' }),
  }, { error: 'タスクはオブジェクトで指定してください' })
  .refine((task) => Date.parse(task.startAt) <= Date.parse(task.endAt), {
    message: '終了日時は開始日時以降にしてください',
    path: ['endAt'],
  })

export type TaskStatus = (typeof STATUSES)[number]
/** 検証前の入力（フォームの値や API のリクエストボディ） */
export type TaskInput = z.input<typeof taskSchema>
/** 検証済みのタスク（id は保存時に PHP 側で採番される） */
export type TaskData = z.output<typeof taskSchema>
export type Task = TaskData & { id: number }

/** フィールド名 => エラーメッセージ（`_form` はオブジェクト全体のエラー） */
export type ValidationErrors = Partial<Record<keyof TaskInput | '_form', string[]>>

export type ValidationResult =
  | { success: true; data: TaskData }
  | { success: false; errors: ValidationErrors }

export function validateTask(input: unknown): ValidationResult {
  const result = taskSchema.safeParse(input)
  if (result.success) {
    return { success: true, data: result.data }
  }
  const { formErrors, fieldErrors } = z.flattenError(result.error)
  const errors: ValidationErrors = { ...fieldErrors }
  if (formErrors.length > 0) {
    errors._form = formErrors
  }
  return { success: false, errors }
}
