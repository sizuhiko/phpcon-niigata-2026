// @enhance/element と @enhance/store は型定義を同梱していないので、このアプリで使う分だけ宣言する

declare module '@enhance/store' {
  type Store<S extends object> = S & {
    subscribe(fn: (state: S) => void, keys?: (keyof S)[]): void
    unsubscribe(fn: (state: S) => void): void
  }
  export default function Store<S extends object>(initialState?: S): Store<S>
}

declare module '@enhance/element' {
  import type { EnhanceElemFn } from '@enhance/types'

  type EnhanceOptions<S extends object> = {
    render: EnhanceElemFn
    store?: S
    keys?: (keyof S)[]
    attrs?: string[]
    shadow?: 'open' | 'closed'
    connected?: (this: HTMLElement, element: HTMLElement) => void
    disconnected?: (this: HTMLElement, element: HTMLElement) => void
  }
  export default function enhance<S extends object>(tagName: string, options: EnhanceOptions<S>): void
}
