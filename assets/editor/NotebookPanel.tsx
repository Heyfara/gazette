import { useState, type FormEvent } from 'react'
import { addNote, deleteNote, fetchDraftPrompt, patchNote } from '../http/client'
import type { IssueView, NoteView } from '../http/types'
import { ErrorMessage } from '../ui/ErrorMessage'
import { dayLabel, today } from '../ui/format'
import { button, card, field } from '../ui/styles'
import { PromptButton } from './PromptButton'

interface Props {
  issue: IssueView
  onIssue: (issue: IssueView) => void
}

/**
 * Le carnet du mois : on y note au fil des jours ce qui s'est passé, pour s'en
 * souvenir à la rédaction. Les notes vont dans les prompts, jamais dans le mail.
 */
export function NotebookPanel({ issue, onIssue }: Props) {
  const [draft, setDraft] = useState({ text: '', date: today() })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<Error | null>(null)
  const editable = issue.editable

  const run = async (action: () => Promise<IssueView>): Promise<boolean> => {
    setError(null)
    setBusy(true)
    try {
      onIssue(await action())
      return true
    } catch (e) {
      setError(e instanceof Error ? e : new Error(String(e)))
      return false
    } finally {
      setBusy(false)
    }
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const text = draft.text.trim()
    if (text === '') return
    if (await run(() => addNote(issue.id, { text, date: draft.date }))) setDraft({ ...draft, text: '' })
  }

  return (
    <section className={card} aria-label="Carnet du mois">
      <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-xs font-semibold tracking-widest text-brique uppercase">Carnet du mois</p>
          <p className="mt-1 text-sm text-stone-500 dark:text-slate-400">Ce qui s'est passé, noté au fil des jours. Repris dans les prompts, jamais dans le mail.</p>
        </div>
        {editable && <PromptButton load={() => fetchDraftPrompt(issue.id)} pendingSave={busy} label="Prompt du premier jet" title="Prompt de tout le numéro (carnet et rubriques), à coller dans ChatGPT ou Claude" />}
      </div>

      {issue.notes.length > 0 ? (
        <ul className="mb-3 flex flex-col divide-y divide-stone-100 dark:divide-slate-800">
          {issue.notes.map((note) => (
            <NoteItem
              key={note.id}
              note={note}
              editable={editable}
              onSave={(fields) => run(() => patchNote(issue.id, note.id, fields))}
              onDelete={() => {
                if (window.confirm('Supprimer cette note ?')) void run(() => deleteNote(issue.id, note.id))
              }}
            />
          ))}
        </ul>
      ) : (
        <p className="mb-3 text-sm text-stone-500 italic dark:text-slate-400">Rien de noté pour l'instant.</p>
      )}

      {editable && (
        <form className="flex flex-wrap gap-2" onSubmit={submit}>
          <div className="w-44 shrink-0">
            <input type="date" className={field} value={draft.date} required aria-label="Date de la note" onChange={(e) => setDraft({ ...draft, date: e.target.value })} />
          </div>
          <input
            className={`${field} min-w-48 flex-1`}
            value={draft.text}
            maxLength={1000}
            placeholder="On a accueilli les nouvelles poules"
            aria-label="Nouvelle note"
            onChange={(e) => setDraft({ ...draft, text: e.target.value })}
          />
          <button type="submit" className={button.primary} disabled={busy || draft.text.trim() === ''}>
            Noter
          </button>
        </form>
      )}

      {error && (
        <div className="mt-3">
          <ErrorMessage error={error} />
        </div>
      )}
    </section>
  )
}

function NoteItem({ note, editable, onSave, onDelete }: { note: NoteView; editable: boolean; onSave: (fields: { text: string; date: string }) => Promise<boolean>; onDelete: () => void }) {
  const [editing, setEditing] = useState<{ text: string; date: string } | null>(null)

  if (editing) {
    return (
      <li className="py-2">
        <form
          className="flex flex-wrap gap-2"
          onSubmit={async (e) => {
            e.preventDefault()
            if (editing.text.trim() === '') return
            if (await onSave({ text: editing.text.trim(), date: editing.date })) setEditing(null)
          }}
        >
          <div className="w-44 shrink-0">
            <input type="date" className={field} value={editing.date} required aria-label="Date de la note" onChange={(e) => setEditing({ ...editing, date: e.target.value })} />
          </div>
          <input className={`${field} min-w-48 flex-1`} value={editing.text} maxLength={1000} aria-label="Texte de la note" autoFocus onChange={(e) => setEditing({ ...editing, text: e.target.value })} />
          <button type="submit" className={button.secondary}>
            Enregistrer
          </button>
          <button type="button" className={button.ghost} onClick={() => setEditing(null)}>
            Annuler
          </button>
        </form>
      </li>
    )
  }

  return (
    <li className="flex items-baseline gap-3 py-2 text-sm">
      <span className="w-12 shrink-0 font-medium text-stone-500 tabular-nums dark:text-slate-400">{dayLabel(note.date)}</span>
      <span className="flex-1 whitespace-pre-line text-stone-800 dark:text-slate-200">{note.text}</span>
      {editable && (
        <span className="flex shrink-0 gap-1">
          <button type="button" className={button.ghost} onClick={() => setEditing({ text: note.text, date: note.date })}>
            Modifier
          </button>
          <button type="button" className={button.danger} onClick={onDelete}>
            Supprimer
          </button>
        </span>
      )}
    </li>
  )
}
