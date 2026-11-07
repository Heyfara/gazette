import { useState } from 'react'
import { thumbnailUrl } from '../http/client'
import type { PhotoInput, PhotoView } from '../http/types'
import { dateLabel, placeLabel } from '../ui/format'
import { button } from '../ui/styles'

interface Props {
  photos: PhotoView[]
  editable: boolean
  onChange: (photos: PhotoInput[]) => void
}

/** Les photos d'une rubrique : vignette, infos Immich, légende, ordre, retrait. */
export function PhotoStrip({ photos, editable, onChange }: Props) {
  const [captions, setCaptions] = useState<Record<string, string>>({})
  const inputs = (): PhotoInput[] => photos.map((p) => ({ assetId: p.assetId, caption: captions[p.assetId] ?? p.caption }))

  if (photos.length === 0) {
    return <p className="rounded-xl border border-dashed border-stone-300 p-4 text-center text-sm text-stone-400 dark:border-slate-700 dark:text-slate-500">Aucune photo pour l'instant.</p>
  }

  const move = (i: number, direction: -1 | 1) => {
    const next = inputs()
    const j = i + direction
    if (j < 0 || j >= next.length) return
    ;[next[i], next[j]] = [next[j]!, next[i]!]
    onChange(next)
  }

  const commitCaption = (assetId: string) => {
    const caption = captions[assetId]
    const photo = photos.find((p) => p.assetId === assetId)
    if (caption === undefined || caption === photo?.caption) return
    onChange(inputs())
  }

  return (
    <ul className="flex gap-3 overflow-x-auto pb-2">
      {photos.map((photo, i) => (
        <li key={photo.assetId} className="flex w-44 shrink-0 flex-col gap-1 rounded-xl bg-stone-50 p-2 ring-1 ring-stone-200 dark:bg-slate-800 dark:ring-slate-700">
          <img src={thumbnailUrl(photo.assetId)} alt={photo.caption || `Photo ${i + 1}`} className="aspect-square w-full rounded-lg object-cover" loading="lazy" />
          <p className="truncate text-xs text-stone-500 dark:text-slate-400" title={[dateLabel(photo.takenAt), placeLabel(photo.city, photo.country), photo.people.join(', ')].filter(Boolean).join(' · ')}>
            {dateLabel(photo.takenAt)}
            {photo.city && ` · ${photo.city}`}
          </p>
          {photo.people.length > 0 && <p className="truncate text-xs text-stone-500 dark:text-slate-400">{photo.people.join(', ')}</p>}
          <input
            className="w-full rounded-md border-0 bg-white px-2 py-1 text-xs ring-1 ring-stone-200 focus:ring-2 focus:ring-brique focus:outline-none dark:bg-slate-900 dark:ring-slate-700"
            placeholder="Légende"
            aria-label={`Légende de la photo ${i + 1}`}
            value={captions[photo.assetId] ?? photo.caption}
            maxLength={200}
            disabled={!editable}
            onChange={(e) => setCaptions({ ...captions, [photo.assetId]: e.target.value })}
            onBlur={() => commitCaption(photo.assetId)}
          />
          {editable && (
            <div className="flex justify-between">
              <button type="button" className={button.ghost} onClick={() => move(i, -1)} disabled={i === 0} aria-label={`Avancer la photo ${i + 1}`}>
                ←
              </button>
              <button type="button" className={button.danger} onClick={() => onChange(inputs().filter((p) => p.assetId !== photo.assetId))} aria-label={`Retirer la photo ${i + 1}`}>
                ✕
              </button>
              <button type="button" className={button.ghost} onClick={() => move(i, 1)} disabled={i === photos.length - 1} aria-label={`Reculer la photo ${i + 1}`}>
                →
              </button>
            </div>
          )}
        </li>
      ))}
    </ul>
  )
}
