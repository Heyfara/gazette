import { useState } from 'react'
import { fetchPrompt } from '../http/client'
import { button, field } from '../ui/styles'

interface Props {
  issueId: string
  sectionId: string
  /** Des notes pas encore enregistrées : on attend avant de bâtir le prompt */
  pendingSave: boolean
}

/**
 * « Copier le prompt » : le serveur assemble le prompt de la rubrique, on le
 * met dans le presse-papiers. Si le presse-papiers est refusé (http, iframe),
 * on l'affiche à copier à la main.
 */
export function PromptButton({ issueId, sectionId, pendingSave }: Props) {
  const [state, setState] = useState<{ status: 'idle' | 'loading' | 'copied' | 'shown' | 'error'; prompt?: string; message?: string }>({ status: 'idle' })

  const copy = async () => {
    setState({ status: 'loading' })
    try {
      const prompt = await fetchPrompt(issueId, sectionId)
      try {
        await navigator.clipboard.writeText(prompt)
        setState({ status: 'copied', prompt })
        setTimeout(() => setState((s) => (s.status === 'copied' ? { status: 'idle' } : s)), 2500)
      } catch {
        setState({ status: 'shown', prompt })
      }
    } catch (e) {
      setState({ status: 'error', message: e instanceof Error ? e.message : String(e) })
    }
  }

  return (
    <div className="flex flex-col items-end gap-2">
      <button type="button" className={button.secondary} onClick={copy} disabled={state.status === 'loading' || pendingSave} title={pendingSave ? 'Enregistrement des notes en cours…' : 'Prompt à coller dans ChatGPT ou Claude'}>
        {state.status === 'loading' ? 'Préparation…' : state.status === 'copied' ? 'Prompt copié ✓' : 'Copier le prompt'}
      </button>
      {state.status === 'error' && (
        <p role="alert" className="text-xs text-rose-700 dark:text-rose-300">
          {state.message}
        </p>
      )}
      {state.status === 'shown' && (
        <div className="w-full max-w-xl">
          <p className="mb-1 text-xs text-stone-500">Le presse-papiers n'est pas accessible ici : copiez le texte ci-dessous.</p>
          <textarea className={`${field} min-h-32 text-xs`} readOnly value={state.prompt} onFocus={(e) => e.target.select()} aria-label="Prompt à copier" />
          <button type="button" className={`${button.ghost} mt-1`} onClick={() => setState({ status: 'idle' })}>
            Fermer
          </button>
        </div>
      )}
    </div>
  )
}
