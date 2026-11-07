import type { SaveState } from '../hooks/useAutosave'

/** « Enregistré », « Enregistrement… » ou l'erreur, à côté de ce qui s'enregistre tout seul. */
export function SaveIndicator({ state }: { state: SaveState }) {
  const label = {
    idle: '',
    pending: 'Modifié',
    saving: 'Enregistrement…',
    saved: 'Enregistré',
    error: `Non enregistré : ${state.error?.message ?? 'erreur'}`,
  }[state.status]

  return (
    <span role="status" aria-live="polite" className={`text-xs ${state.status === 'error' ? 'font-medium text-rose-700 dark:text-rose-300' : 'text-stone-400 dark:text-slate-500'}`}>
      {label}
    </span>
  )
}
