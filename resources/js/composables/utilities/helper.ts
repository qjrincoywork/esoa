export type DebouncedFn<T extends (...args: any[]) => any> = {
  (...args: Parameters<T>): ReturnType<T> | undefined
  cancel: () => void
  flush: () => ReturnType<T> | undefined
}

export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait = 600,
  options?: { leading?: boolean },
): DebouncedFn<T> {
  let timeout: ReturnType<typeof setTimeout> | null = null
  let lastArgs: any[] | null = null
  let lastThis: any
  let result: ReturnType<T> | undefined
  const leading = !!options?.leading

  const invoke = () => {
    if (!lastArgs) return undefined
    const res = func.apply(lastThis, lastArgs)
    result = res
    lastArgs = null
    lastThis = undefined
    return res
  }

  const debounced = function (this: any, ...args: any[]) {
    lastArgs = args
    lastThis = this

    const callNow = leading && !timeout

    if (timeout) clearTimeout(timeout)

    timeout = setTimeout(() => {
      timeout = null
      if (!leading) invoke()
    }, wait)

    if (callNow) {
      return invoke()
    }

    return result
  } as DebouncedFn<T>

  debounced.cancel = () => {
    if (timeout) {
      clearTimeout(timeout)
      timeout = null
    }
    lastArgs = null
    lastThis = undefined
  }

  debounced.flush = () => {
    if (timeout) {
      clearTimeout(timeout)
      timeout = null
      return invoke()
    }
    return result
  }

  return debounced
}
