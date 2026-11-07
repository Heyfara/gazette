import { useEffect, useRef, useState } from 'react'

export type SaveState = { status: 'idle' | 'pending' | 'saving' | 'saved' | 'error'; error: Error | null }

/**
 * Enregistre `value` un peu après sa dernière modification (`delay` ms), sans
 * rien faire au montage. Une modification pendant l'enregistrement relance
 * celui-ci à la fin ; dernier écrit gagne (voir la spec : pas de verrou).
 */
export function useAutosave<T>(value: T, save: (value: T) => Promise<void>, delay = 800): SaveState {
  const [state, setState] = useState<SaveState>({ status: 'idle', error: null })
  const lastSaved = useRef(JSON.stringify(value))
  const saveRef = useRef(save)
  saveRef.current = save

  const serialized = JSON.stringify(value)

  useEffect(() => {
    if (serialized === lastSaved.current) return
    setState({ status: 'pending', error: null })
    let cancelled = false
    const timer = setTimeout(() => {
      setState({ status: 'saving', error: null })
      saveRef.current(value).then(
        () => {
          if (cancelled) return
          lastSaved.current = serialized
          setState({ status: 'saved', error: null })
        },
        (error: unknown) => {
          if (!cancelled) setState({ status: 'error', error: error instanceof Error ? error : new Error(String(error)) })
        },
      )
    }, delay)
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- `serialized` représente `value`
  }, [serialized, delay])

  return state
}
