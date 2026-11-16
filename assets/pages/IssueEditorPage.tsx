import { useCallback, useEffect, useState } from 'react'
import { SectionEditor } from '../editor/SectionEditor'
import { IssueHeader } from '../editor/IssueHeader'
import { NotebookPanel } from '../editor/NotebookPanel'
import { PreviewPane, type PreviewMode } from '../editor/PreviewPane'
import { SendPanel } from '../editor/SendPanel'
import { useJson } from '../hooks/useJson'
import { addSection, deleteIssue, deleteSection, fetchConfig, fetchIssue, reorderSections } from '../http/client'
import type { IssueView } from '../http/types'
import { ErrorMessage } from '../ui/ErrorMessage'
import { button } from '../ui/styles'

/**
 * Rédaction d'un numéro : en-tête, carnet du mois, rubriques, aperçu. Chaque modification
 * est enregistrée par l'API, qui renvoie le numéro à jour (`setIssue`).
 */
export function IssueEditorPage({ id, onBack }: { id: string; onBack: () => void }) {
  const loaded = useJson((signal) => fetchIssue(id, signal), `issue:${id}`)
  const config = useJson(fetchConfig, 'config')
  const [issue, setIssue] = useState<IssueView | null>(null)
  const [actionError, setActionError] = useState<Error | null>(null)
  const [version, setVersion] = useState(0)
  const [preview, setPreview] = useState<PreviewMode | null>(null)

  useEffect(() => {
    if (loaded.data) setIssue(loaded.data)
  }, [loaded.data])

  const update = useCallback((next: IssueView) => {
    setIssue(next)
    setVersion((v) => v + 1)
  }, [])

  const run = async (action: () => Promise<IssueView>) => {
    setActionError(null)
    try {
      update(await action())
    } catch (e) {
      setActionError(e instanceof Error ? e : new Error(String(e)))
    }
  }

  const remove = async () => {
    if (!window.confirm('Supprimer ce numéro et tout son contenu ?')) return
    try {
      await deleteIssue(id)
      onBack()
    } catch (e) {
      setActionError(e instanceof Error ? e : new Error(String(e)))
    }
  }

  const move = (sectionId: string, direction: -1 | 1) => {
    if (!issue) return
    const ids = issue.sections.map((s) => s.id)
    const i = ids.indexOf(sectionId)
    const j = i + direction
    if (i < 0 || j < 0 || j >= ids.length) return
    ;[ids[i], ids[j]] = [ids[j]!, ids[i]!]
    void run(() => reorderSections(id, ids))
  }

  if (loaded.error) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-10">
        <button type="button" className={button.ghost} onClick={onBack}>
          ← Les numéros
        </button>
        <div className="mt-4">
          <ErrorMessage error={loaded.error} />
        </div>
      </main>
    )
  }
  if (!issue) return <p className="p-10 text-center text-stone-500">Chargement…</p>

  const photoCount = issue.sections.reduce((n, s) => n + s.photos.length, 0)
  const maxPhotos = config.data?.maxPhotos ?? 12

  return (
    <div className={`mx-auto flex gap-6 px-4 py-6 ${preview ? 'max-w-7xl' : 'max-w-3xl'}`}>
      <main className="flex min-w-0 flex-1 flex-col gap-6">
        <nav className="flex flex-wrap items-center justify-between gap-2">
          <button type="button" className={button.ghost} onClick={onBack}>
            ← Les numéros
          </button>
          <div className="flex gap-2">
            <button type="button" className={button.secondary} onClick={() => setPreview((v) => (v ? null : 'local'))} aria-pressed={preview !== null}>
              {preview ? 'Masquer l’aperçu' : 'Aperçu'}
            </button>
            {issue.editable && (
              <button type="button" className={button.danger} onClick={remove}>
                Supprimer le numéro
              </button>
            )}
          </div>
        </nav>

        {!issue.editable && (
          <p className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-900">
            Ce numéro a été envoyé : il ne se modifie plus.
          </p>
        )}
        {actionError && <ErrorMessage error={actionError} />}

        <IssueHeader issue={issue} onIssue={update} />

        <NotebookPanel issue={issue} onIssue={update} />

        {photoCount > maxPhotos && (
          <p role="status" className="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-100 dark:ring-amber-900">
            {photoCount} photos dans ce numéro : au-delà de {maxPhotos}, le mail devient lourd à lire. Pensez à en garder les meilleures.
          </p>
        )}

        {issue.sections.map((section, i) => (
          <SectionEditor
            key={section.id}
            issue={issue}
            section={section}
            index={i}
            onIssue={update}
            onDelete={() => void run(() => deleteSection(id, section.id))}
            onMoveUp={i > 0 ? () => move(section.id, -1) : undefined}
            onMoveDown={i < issue.sections.length - 1 ? () => move(section.id, 1) : undefined}
          />
        ))}

        {issue.editable && (
          <button type="button" className={`${button.secondary} self-start`} onClick={() => void run(() => addSection(id))}>
            + Ajouter une rubrique
          </button>
        )}

        <SendPanel
          issue={issue}
          config={config.data?.send ?? null}
          onIssue={update}
          onPreview={() => {
            setVersion((v) => v + 1)
            setPreview('listmonk')
          }}
        />
      </main>

      {preview && (
        <aside className="hidden w-[640px] shrink-0 lg:block">
          <PreviewPane issueId={id} version={version} mode={preview} />
        </aside>
      )}
    </div>
  )
}
