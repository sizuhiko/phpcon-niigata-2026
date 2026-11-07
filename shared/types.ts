// ブラウザのストアと SSR の initialState で共有する状態の型
import type { EnhanceElemArg } from '@enhance/types'
import type { Task, TaskInput, ValidationErrors } from './task-schema'

export type FormState = {
  mode: 'create' | 'edit'
  id: number | null
  values: TaskInput
  errors: ValidationErrors
  notice: { type: 'success' | 'error'; text: string } | null
  skipClientValidation: boolean
}

export type AppState = {
  tasks: Task[]
  form: FormState
}

/**
 * Enhance の要素関数の引数。
 * @enhance/types では store が Record<string, any> なので、このアプリの状態で置き換える。
 * ブラウザでテンプレートを作るときは store が空で呼ばれるため Partial にしている。
 */
export type ElementArgs = Omit<EnhanceElemArg, 'state'> & {
  state: Omit<EnhanceElemArg['state'], 'store'> & { store: Partial<AppState> }
}
