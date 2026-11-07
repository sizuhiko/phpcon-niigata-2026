// <task-list> タスク一覧
//
// この関数はブラウザ（@enhance/element）と PHP（Enhance SSR Wasm）の両方で実行される。
// SSR 側では JS に変換したあと関数のソース文字列として渡されるため、import や外部の関数を参照せず自己完結させること。
// （import type はビルド時に消えるので使ってよい）
import type { TaskStatus } from '../shared/task-schema'
import type { ElementArgs } from '../shared/types'

export default function TaskList({ html, state }: ElementArgs): string {
  const tasks = state.store.tasks || []
  const editingId = state.store.form && state.store.form.mode === 'edit' ? state.store.form.id : null

  const entities: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }
  const escape = (value: unknown): string => String(value ?? '').replace(/[&<>"']/g, (c) => entities[c])
  const formatDateTime = (value: string): string => value.replace('T', ' ').replace(/-/g, '/').slice(0, 16)

  const badgeClasses: Record<TaskStatus, string> = {
    TODO: 'bg-slate-100 text-slate-700 ring-slate-300',
    DOING: 'bg-amber-100 text-amber-800 ring-amber-300',
    DONE: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
  }
  const statuses: TaskStatus[] = ['TODO', 'DOING', 'DONE']

  const items = tasks
    .map((task) => {
      const options = statuses
        .map((s) => `<option value="${s}"${s === task.status ? ' selected' : ''}>${s}</option>`)
        .join('')
      const cardClasses = task.id === editingId ? 'border-sky-400 ring-2 ring-sky-200' : 'border-slate-200'
      const titleClasses = task.status === 'DONE' ? 'text-slate-400 line-through' : 'text-slate-900'

      return `
        <li class="rounded-xl border ${cardClasses} bg-white p-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <h3 class="text-base font-semibold ${titleClasses}">${escape(task.title)}</h3>
            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ${badgeClasses[task.status]}">${escape(task.status)}</span>
          </div>
          <p class="mt-1 text-sm text-slate-500">${formatDateTime(task.startAt)} 〜 ${formatDateTime(task.endAt)}</p>
          ${task.description ? `<p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">${escape(task.description)}</p>` : ''}
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-1 text-sm text-slate-600">
              ステータス
              <select data-action="status" data-id="${task.id}" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm">${options}</select>
            </label>
            <span class="flex-1"></span>
            <button type="button" data-action="edit" data-id="${task.id}" class="rounded-md border border-slate-300 px-3 py-1 text-sm text-slate-700 hover:bg-slate-50">編集</button>
            <button type="button" data-action="delete" data-id="${task.id}" class="rounded-md border border-rose-300 px-3 py-1 text-sm text-rose-700 hover:bg-rose-50">削除</button>
          </div>
        </li>`
    })
    .join('')

  return html`
    <section>
      <h2 class="mb-3 text-lg font-bold text-slate-800">タスク一覧 <span class="text-sm font-normal text-slate-500">（${String(tasks.length)}件・開始日時の古い順）</span></h2>
      ${tasks.length === 0 ? '<p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-slate-500">タスクはありません</p>' : `<ul class="space-y-3">${items}</ul>`}
    </section>
  `
}
