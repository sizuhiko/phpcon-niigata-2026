import enhance from '@enhance/element'
import Store from '@enhance/store'
import TaskList from '../elements/task-list'
import TaskForm from '../elements/task-form'
import { validateTask, type Task, type TaskInput, type TaskStatus, type ValidationErrors } from '../shared/task-schema'
import type { AppState, FormState } from '../shared/types'

// SSR 時に PHP が埋め込んだ初期状態をそのままストアに読み込む
const store = Store<AppState>(JSON.parse(document.getElementById('initial-state')!.textContent!))

const emptyForm = (): FormState => ({
  mode: 'create',
  id: null,
  values: { title: '', description: '', startAt: '', endAt: '', status: 'TODO' },
  errors: {},
  notice: null,
  // フォームをリセットしても「検証スキップ」のチェック状態は画面のまま引き継ぐ
  skipClientValidation: document.querySelector<HTMLInputElement>('task-form [name="skipClientValidation"]')?.checked ?? false,
})

type ApiResponse = {
  tasks?: Task[]
  task?: Task
  message?: string
  errors?: ValidationErrors
}

async function api(method: string, path: string, body?: unknown): Promise<{ response: Response; data: ApiResponse | null }> {
  const response = await fetch(path, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const data = response.status === 204 ? null : ((await response.json()) as ApiResponse)
  return { response, data }
}

async function reloadTasks(): Promise<void> {
  const { data } = await api('GET', '/api/tasks')
  store.tasks = data?.tasks ?? []
}

enhance('task-form', {
  store,
  keys: ['form'],
  render: TaskForm,
  connected() {
    this.addEventListener('submit', async (event) => {
      event.preventDefault()
      const formData = new FormData(event.target as HTMLFormElement)
      const skipClientValidation = formData.has('skipClientValidation')
      const values: TaskInput = {
        title: String(formData.get('title') ?? ''),
        description: String(formData.get('description') ?? ''),
        startAt: String(formData.get('startAt') ?? ''),
        endAt: String(formData.get('endAt') ?? ''),
        status: store.form.mode === 'edit' ? (formData.get('status') as TaskStatus) : 'TODO',
      }
      const current: FormState = { ...store.form, values, skipClientValidation }

      // 1. フロントエンドで Zod による検証
      if (!skipClientValidation) {
        const result = validateTask(values)
        if (!result.success) {
          store.form = { ...current, errors: result.errors, notice: { type: 'error', text: 'フロントエンド（Zod）の検証でエラーになりました' } }
          return
        }
      }

      // 2. サーバーへ送信（PHP 側でも Extism 経由で同じ Zod のルールで検証される）
      const { response, data } = current.mode === 'edit'
        ? await api('PUT', `/api/tasks/${current.id}`, values)
        : await api('POST', '/api/tasks', values)

      if (response.status === 400) {
        store.form = { ...current, errors: data?.errors ?? {}, notice: { type: 'error', text: `サーバー（PHP + Extism）の検証でエラーになりました（${response.status}）` } }
        return
      }
      if (!response.ok) {
        store.form = { ...current, errors: {}, notice: { type: 'error', text: data?.message ?? `エラーが発生しました（${response.status}）` } }
        return
      }

      await reloadTasks()
      store.form = { ...emptyForm(), notice: { type: 'success', text: current.mode === 'edit' ? 'タスクを更新しました' : 'タスクを追加しました' } }
    })

    this.addEventListener('click', (event) => {
      if ((event.target as Element).closest('[data-action="cancel"]')) {
        store.form = emptyForm()
      }
    })
  },
})

enhance('task-list', {
  store,
  keys: ['tasks', 'form'],
  render: TaskList,
  connected() {
    this.addEventListener('click', async (event) => {
      const button = (event.target as Element).closest<HTMLButtonElement>('button[data-action]')
      if (!button) return
      const id = Number(button.dataset.id)
      const task = store.tasks.find((t) => t.id === id)
      if (!task) return

      if (button.dataset.action === 'edit') {
        const { id: _, ...values } = task
        store.form = { ...emptyForm(), mode: 'edit', id, values }
        document.querySelector('task-form')?.scrollIntoView({ behavior: 'smooth' })
      }

      if (button.dataset.action === 'delete') {
        if (!confirm(`「${task.title}」を削除しますか？`)) return
        await api('DELETE', `/api/tasks/${id}`)
        if (store.form.mode === 'edit' && store.form.id === id) {
          store.form = emptyForm()
        }
        await reloadTasks()
      }
    })

    this.addEventListener('change', async (event) => {
      const select = (event.target as Element).closest<HTMLSelectElement>('select[data-action="status"]')
      if (!select) return
      const { response, data } = await api('PATCH', `/api/tasks/${select.dataset.id}/status`, { status: select.value })
      if (!response.ok) {
        alert(Object.values(data?.errors ?? {}).flat().join('\n') || data?.message)
      }
      await reloadTasks()
    })
  },
})
