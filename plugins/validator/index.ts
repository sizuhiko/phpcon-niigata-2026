// Extism プラグインのエントリーポイント
// shared/task-schema.ts（フロントエンドと同じ Zod のルール）を Wasm として公開する
import { validateTask, type ValidationResult } from '../../shared/task-schema'

export function validate(): number {
  let input: unknown
  try {
    input = JSON.parse(Host.inputString())
  } catch {
    const result: ValidationResult = { success: false, errors: { _form: ['JSON の形式が正しくありません'] } }
    Host.outputString(JSON.stringify(result))
    return 0
  }

  Host.outputString(JSON.stringify(validateTask(input)))
  return 0
}
