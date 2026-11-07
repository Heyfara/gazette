import { useState } from 'react'
import { useAutosave } from '../hooks/useAutosave'
import { patchSection } from '../http/client'
import type { IssueView, PhotoInput, SectionView } from '../http/types'
import { button, card, field } from '../ui/styles'
import { PhotoPicker } from './PhotoPicker'
import { PhotoStrip } from './PhotoStrip'
import { PromptButton } from './PromptButton'
import { SaveIndicator } from './SaveIndicator'

interface Props {
  issue: IssueView
  section: SectionView
  index: number
  onIssue: (issue: IssueView) => void
  onDelete: () => void
  onMoveUp?: () => void
  onMoveDown?: () => void
}

/**
 * Une rubrique : titre, photos, notes brutes, prompt à coller, texte. Les
 * champs texte s'enregistrent tout seuls ; les photos à chaque changement.
 */
export function SectionEditor({ issue, section, index, onIssue, onDelete, onMoveUp, onMoveDown }: Props) {
  const [fields, setFields] = useState({ title: section.title, notes: section.notes, text: section.text })
  const [picking, setPicking] = useState(false)
  const [photoError, setPhotoError] = useState<Error | null>(null)
  const save = useAutosave(fields, async (value) => onIssue(await patchSection(issue.id, section.id, value)))
  const disabled = !issue.editable

  const savePhotos = async (photos: PhotoInput[]) => {
    setPhotoError(null)
    try {
      onIssue(await patchSection(issue.id, section.id, { photos }))
    } catch (e) {
      setPhotoError(e instanceof Error ? e : new Error(String(e)))
    }
  }

  return (
    <section className={card} aria-label={`Rubrique ${index + 1}`}>
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <p className="text-xs font-semibold tracking-widest text-brique uppercase">Rubrique {index + 1}</p>
        <SaveIndicator state={save} />
        {!disabled && (
          <div className="ml-auto flex gap-1">
            <button type="button" className={button.ghost} onClick={onMoveUp} disabled={!onMoveUp} aria-label="Monter la rubrique">
              ↑
            </button>
            <button type="button" className={button.ghost} onClick={onMoveDown} disabled={!onMoveDown} aria-label="Descendre la rubrique">
              ↓
            </button>
            <button
              type="button"
              className={button.danger}
              onClick={() => {
                if (window.confirm('Supprimer cette rubrique ?')) onDelete()
              }}
            >
              Supprimer
            </button>
          </div>
        )}
      </div>

      <div className="flex flex-col gap-4">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-stone-600 dark:text-slate-400">Titre de la rubrique</span>
          <input className={field} value={fields.title} maxLength={120} placeholder="À la mer" disabled={disabled} onChange={(e) => setFields({ ...fields, title: e.target.value })} />
        </label>

        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <span className="text-sm text-stone-600 dark:text-slate-400">
              Photos{section.photos.length > 0 && ` (${section.photos.length})`}
            </span>
            {!disabled && (
              <button type="button" className={button.secondary} onClick={() => setPicking(true)}>
                + Choisir des photos
              </button>
            )}
          </div>
          <PhotoStrip photos={section.photos} editable={!disabled} onChange={savePhotos} />
          {photoError && (
            <p role="alert" className="text-sm text-rose-700 dark:text-rose-300">
              {photoError.message}
            </p>
          )}
        </div>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-stone-600 dark:text-slate-400">Notes en vrac (pour le prompt, pas dans le mail)</span>
          <textarea
            className={`${field} min-h-20`}
            value={fields.notes}
            maxLength={5000}
            placeholder="première fois à la mer, peur des vagues puis n'a plus voulu sortir de l'eau…"
            disabled={disabled}
            onChange={(e) => setFields({ ...fields, notes: e.target.value })}
          />
        </label>

        <div className="flex flex-col gap-1 text-sm">
          <div className="flex items-center justify-between">
            <span className="text-stone-600 dark:text-slate-400">Texte de la rubrique</span>
            {!disabled && <PromptButton issueId={issue.id} sectionId={section.id} pendingSave={save.status === 'pending' || save.status === 'saving'} />}
          </div>
          <textarea
            className={`${field} min-h-40 font-serif text-base`}
            value={fields.text}
            maxLength={10000}
            placeholder="Collez ici le texte généré, ou écrivez-le directement."
            disabled={disabled}
            onChange={(e) => setFields({ ...fields, text: e.target.value })}
          />
        </div>
      </div>

      {picking && (
        <PhotoPicker
          month={issue.month}
          already={section.photos.map((p) => p.assetId)}
          onClose={() => setPicking(false)}
          onAdd={(ids) => {
            setPicking(false)
            void savePhotos([...section.photos.map((p) => ({ assetId: p.assetId, caption: p.caption })), ...ids.map((assetId) => ({ assetId, caption: '' }))])
          }}
        />
      )}
    </section>
  )
}
