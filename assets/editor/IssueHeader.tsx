import { useState } from 'react'
import { useAutosave } from '../hooks/useAutosave'
import { patchIssue } from '../http/client'
import type { IssueView } from '../http/types'
import { SaveIndicator } from './SaveIndicator'
import { card, field } from '../ui/styles'

/** En-tête du numéro : nom de la gazette, titre, mois. Enregistré tout seul. */
export function IssueHeader({ issue, onIssue }: { issue: IssueView; onIssue: (issue: IssueView) => void }) {
  const [fields, setFields] = useState({ name: issue.name, title: issue.title, month: issue.month })
  const save = useAutosave(fields, async (value) => onIssue(await patchIssue(issue.id, value)))
  const disabled = !issue.editable

  return (
    <section className={card} aria-label="En-tête du numéro">
      <div className="mb-4 flex items-center justify-between">
        <p className="text-xs font-semibold tracking-widest text-brique uppercase">Numéro {issue.number}</p>
        <SaveIndicator state={save} />
      </div>
      <div className="grid gap-4 sm:grid-cols-[1fr_auto]">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-stone-600 dark:text-slate-400">Nom de la gazette</span>
          <input className={field} value={fields.name} maxLength={80} disabled={disabled} onChange={(e) => setFields({ ...fields, name: e.target.value })} />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-stone-600 dark:text-slate-400">Mois</span>
          <input type="month" className={field} value={fields.month} disabled={disabled} onChange={(e) => setFields({ ...fields, month: e.target.value })} />
        </label>
        <label className="flex flex-col gap-1 text-sm sm:col-span-2">
          <span className="text-stone-600 dark:text-slate-400">Titre du numéro (facultatif)</span>
          <input
            className={field}
            value={fields.title}
            maxLength={120}
            placeholder="Spécial vacances à la mer"
            disabled={disabled}
            onChange={(e) => setFields({ ...fields, title: e.target.value })}
          />
        </label>
      </div>
    </section>
  )
}
