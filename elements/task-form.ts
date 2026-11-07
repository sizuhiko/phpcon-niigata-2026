// <task-form> タスクの追加・編集フォーム
//
// この関数はブラウザ（@enhance/element）と PHP（Enhance SSR Wasm）の両方で実行される。
// SSR 側では JS に変換したあと関数のソース文字列として渡されるため、import や外部の関数を参照せず自己完結させること。
// （import type はビルド時に消えるので使ってよい）
import type { TaskInput, TaskStatus, ValidationErrors } from '../shared/task-schema'
import type { ElementArgs, FormState } from '../shared/types'

export default function TaskForm({ html, state }: ElementArgs): string {
  const form: Partial<FormState> = state.store.form || {}
  const values: Partial<TaskInput> = form.values || {}
  const errors: ValidationErrors = form.errors || {}
  const isEdit = form.mode === 'edit'

  const entities: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }
  const escape = (value: unknown): string => String(value ?? '').replace(/[&<>"']/g, (c) => entities[c])
  const inputClasses = (name: keyof ValidationErrors): string =>
    errors[name]
      ? 'mt-1 w-full rounded-md border border-rose-400 bg-rose-50 px-3 py-2 text-sm'
      : 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm'
  const errorMessages = (name: keyof ValidationErrors): string =>
    (errors[name] || []).map((message) => `<p class="mt-1 text-xs text-rose-600">${escape(message)}</p>`).join('')

  const noticeClasses: Record<NonNullable<FormState['notice']>['type'], string> = {
    success: 'border-emerald-300 bg-emerald-50 text-emerald-800',
    error: 'border-rose-300 bg-rose-50 text-rose-800',
  }
  const notice = form.notice
    ? `<p class="mb-4 rounded-md border px-3 py-2 text-sm ${noticeClasses[form.notice.type]}">${escape(form.notice.text)}</p>`
    : ''

  const statuses: TaskStatus[] = ['TODO', 'DOING', 'DONE']
  const statusField = isEdit
    ? `
      <label class="block text-sm font-medium text-slate-700">
        ステータス
        <select name="status" class="${inputClasses('status')}">
          ${statuses.map((s) => `<option value="${s}"${s === values.status ? ' selected' : ''}>${s}</option>`).join('')}
        </select>
        ${errorMessages('status')}
      </label>`
    : ''

  return html`
    <form novalidate class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-4 text-lg font-bold text-slate-800">${isEdit ? 'タスクを編集' : 'タスクを追加'}</h2>
      ${notice}
      ${errorMessages('_form')}
      <div class="space-y-4">
        <label class="block text-sm font-medium text-slate-700">
          タイトル <span class="text-rose-600">*</span>
          <input type="text" name="title" value="${escape(values.title)}" class="${inputClasses('title')}">
          ${errorMessages('title')}
        </label>
        <label class="block text-sm font-medium text-slate-700">
          詳細
          <textarea name="description" rows="3" class="${inputClasses('description')}">${escape(values.description)}</textarea>
          ${errorMessages('description')}
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            開始日時 <span class="text-rose-600">*</span>
            <input type="datetime-local" name="startAt" value="${escape(values.startAt)}" class="${inputClasses('startAt')}">
            ${errorMessages('startAt')}
          </label>
          <label class="block text-sm font-medium text-slate-700">
            終了日時 <span class="text-rose-600">*</span>
            <input type="datetime-local" name="endAt" value="${escape(values.endAt)}" class="${inputClasses('endAt')}">
            ${errorMessages('endAt')}
          </label>
        </div>
        ${statusField}
        <label class="flex items-center gap-2 text-xs text-slate-500">
          <input type="checkbox" name="skipClientValidation"${form.skipClientValidation ? ' checked' : ''}>
          フロントエンドの検証をスキップしてサーバーに送信する（デモ用）
        </label>
      </div>
      <div class="mt-5 flex gap-2">
        <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">${isEdit ? '更新する' : '追加する'}</button>
        ${isEdit ? '<button type="button" data-action="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">キャンセル</button>' : ''}
      </div>
    </form>
  `
}
