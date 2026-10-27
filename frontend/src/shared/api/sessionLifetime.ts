import { ref } from 'vue'

/** Each generation is a separate authenticated cache lifetime, even within one tenant. */
export const sessionGeneration = ref(0)
export const sessionTransition = ref(false)
let controller = new AbortController()

export function assertSession(generation: number): void {
  if (generation !== sessionGeneration.value) throw new DOMException('Session changed', 'AbortError')
}
export function advanceSession(): void {
  sessionGeneration.value++
  controller.abort()
  controller = new AbortController()
}
/** Guard body consumption as well as headers, including fetch implementations ignoring abort. */
export async function sessionFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const generation = sessionGeneration.value
  const response = await fetch(input, { ...init, signal: init?.signal ? AbortSignal.any([controller.signal, init.signal]) : controller.signal })
  assertSession(generation)
  return new Proxy(response, {
    get(target, property) {
      if (property === 'json' || property === 'blob') return async () => {
        const result = await target[property]()
        assertSession(generation)
        return result
      }
      const value = Reflect.get(target, property, target)
      return typeof value === 'function' ? value.bind(target) : value
    },
  })
}
