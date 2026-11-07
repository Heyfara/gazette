import { useState } from 'react'
import { useJson } from '../hooks/useJson'
import { createIssue, fetchIssues } from '../http/client'
import { ErrorMessage } from '../ui/ErrorMessage'
import { button, card } from '../ui/styles'
import { currentMonth, monthLabel, STATUS_LABEL } from '../ui/format'

/** Les numéros, le plus récent d'abord, et le bouton « Nouveau numéro ». */
export function IssueListPage({ onOpen }: { onOpen: (id: string) => void }) {
  const { data: issues, error, loading } = useJson(fetchIssues, 'issues')
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState<Error | null>(null)

  const create = async () => {
    setCreating(true)
    setCreateError(null)
    try {
      const issue = await createIssue({ month: currentMonth() })
      onOpen(issue.id)
    } catch (e) {
      setCreateError(e instanceof Error ? e : new Error(String(e)))
      setCreating(false)
    }
  }

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-10">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold tracking-widest text-brique uppercase">Gazette familiale</p>
          <h1 className="font-serif text-3xl text-stone-900 dark:text-slate-50">Les numéros</h1>
        </div>
        <button type="button" className={button.primary} onClick={create} disabled={creating}>
          {creating ? 'Création…' : 'Nouveau numéro'}
        </button>
      </header>

      {error && <ErrorMessage error={error} />}
      {createError && <ErrorMessage error={createError} />}
      {loading && <p className="text-stone-500">Chargement…</p>}

      {issues && issues.length === 0 && (
        <p className={`${card} text-center text-stone-500`}>Aucun numéro pour l'instant. Le premier n'attend que vous.</p>
      )}

      <ul className="flex flex-col gap-3">
        {issues?.map((issue) => (
          <li key={issue.id}>
            <button type="button" onClick={() => onOpen(issue.id)} className={`${card} flex w-full items-center gap-5 text-left hover:ring-brique`}>
              <span className="grid size-14 shrink-0 place-items-center rounded-xl bg-brique font-serif text-2xl text-white" aria-hidden="true">
                {issue.number}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-lg font-medium text-stone-900 dark:text-slate-50">
                  {issue.title || `Numéro ${issue.number}`}
                </span>
                <span className="block text-sm text-stone-500 dark:text-slate-400">
                  {issue.name} · {monthLabel(issue.month)} · {issue.sections} rubrique{issue.sections > 1 ? 's' : ''}, {issue.photos} photo{issue.photos > 1 ? 's' : ''}
                </span>
              </span>
              <span
                className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${
                  issue.status === 'envoye'
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                    : issue.status === 'teste'
                      ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'
                      : 'bg-stone-200 text-stone-700 dark:bg-slate-800 dark:text-slate-300'
                }`}
              >
                {STATUS_LABEL[issue.status]}
              </span>
            </button>
          </li>
        ))}
      </ul>
    </main>
  )
}
