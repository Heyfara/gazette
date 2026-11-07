import { useEffect, useState } from 'react'
import { useJson } from '../hooks/useJson'
import { fetchAlbums, fetchPeople, searchPhotos, thumbnailUrl, type PhotoSearch } from '../http/client'
import type { AssetView } from '../http/types'
import { ErrorMessage } from '../ui/ErrorMessage'
import { dateLabel, monthLabel, monthRange, placeLabel } from '../ui/format'
import { button, field } from '../ui/styles'

interface Props {
  /** Mois du numéro : période proposée par défaut */
  month: string
  /** Photos déjà dans la rubrique : cochées et verrouillées */
  already: string[]
  onAdd: (assetIds: string[]) => void
  onClose: () => void
}

type Tab = 'period' | 'album'

/**
 * Choix des photos dans Immich : par période (le mois du numéro par défaut)
 * ou par album, avec un filtre par personne. Les photos cochées sont ajoutées
 * à la rubrique en une fois.
 */
export function PhotoPicker({ month, already, onAdd, onClose }: Props) {
  const range = monthRange(month)
  const [tab, setTab] = useState<Tab>('period')
  const [from, setFrom] = useState(range.from)
  const [to, setTo] = useState(range.to)
  const [album, setAlbum] = useState('')
  const [person, setPerson] = useState('')
  const [selected, setSelected] = useState<string[]>([])
  const albums = useJson(fetchAlbums, 'albums')
  const people = useJson(fetchPeople, 'people')

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const search: PhotoSearch | null = tab === 'period' ? { from, to, person } : album ? { album, person } : null

  const toggle = (id: string) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]))

  return (
    <div className="fixed inset-0 z-20 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-6" onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Choisir des photos"
        className="flex max-h-[95dvh] w-full max-w-4xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl dark:bg-slate-900"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex flex-wrap items-center gap-3 border-b border-stone-200 px-5 py-3 dark:border-slate-800">
          <h2 className="text-lg font-medium text-stone-900 dark:text-slate-50">Choisir des photos</h2>
          <div className="flex rounded-lg bg-stone-100 p-1 text-sm dark:bg-slate-800" role="tablist">
            {(['period', 'album'] as const).map((t) => (
              <button
                key={t}
                type="button"
                role="tab"
                aria-selected={tab === t}
                className={`rounded-md px-3 py-1 ${tab === t ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-stone-500'}`}
                onClick={() => setTab(t)}
              >
                {t === 'period' ? 'Par période' : 'Par album'}
              </button>
            ))}
          </div>
          <button type="button" className={`${button.ghost} ml-auto`} onClick={onClose} aria-label="Fermer">
            ✕
          </button>
        </header>

        <div className="flex flex-wrap items-end gap-3 border-b border-stone-200 px-5 py-3 text-sm dark:border-slate-800">
          {tab === 'period' ? (
            <>
              <label className="flex flex-col gap-1">
                <span className="text-stone-500">Du</span>
                <input type="date" className={field} value={from} onChange={(e) => setFrom(e.target.value)} />
              </label>
              <label className="flex flex-col gap-1">
                <span className="text-stone-500">Au</span>
                <input type="date" className={field} value={to} onChange={(e) => setTo(e.target.value)} />
              </label>
              <button
                type="button"
                className={button.ghost}
                onClick={() => {
                  setFrom(range.from)
                  setTo(range.to)
                }}
              >
                {monthLabel(month)}
              </button>
            </>
          ) : (
            <label className="flex min-w-60 flex-col gap-1">
              <span className="text-stone-500">Album</span>
              <select className={field} value={album} onChange={(e) => setAlbum(e.target.value)}>
                <option value="">Choisir un album…</option>
                {albums.data?.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name} ({a.count})
                  </option>
                ))}
              </select>
            </label>
          )}
          <label className="flex min-w-40 flex-col gap-1">
            <span className="text-stone-500">Personne</span>
            <select className={field} value={person} onChange={(e) => setPerson(e.target.value)}>
              <option value="">Tout le monde</option>
              {people.data?.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="min-h-60 flex-1 overflow-y-auto px-5 py-4">
          {albums.error && <ErrorMessage error={albums.error} />}
          {people.error && tab === 'period' && <p className="mb-2 text-xs text-stone-500">Personnes indisponibles : {people.error.message}</p>}
          {search ? <PhotoGrid search={search} already={already} selected={selected} onToggle={toggle} /> : <p className="text-center text-stone-500">Choisissez un album.</p>}
        </div>

        <footer className="flex items-center justify-between gap-3 border-t border-stone-200 px-5 py-3 dark:border-slate-800">
          <span className="text-sm text-stone-500">{selected.length > 0 ? `${selected.length} photo${selected.length > 1 ? 's' : ''} choisie${selected.length > 1 ? 's' : ''}` : 'Cochez des photos'}</span>
          <div className="flex gap-2">
            <button type="button" className={button.secondary} onClick={onClose}>
              Annuler
            </button>
            <button type="button" className={button.primary} disabled={selected.length === 0} onClick={() => onAdd(selected)}>
              Ajouter
            </button>
          </div>
        </footer>
      </div>
    </div>
  )
}

function PhotoGrid({ search, already, selected, onToggle }: { search: PhotoSearch; already: string[]; selected: string[]; onToggle: (id: string) => void }) {
  // Les pages déjà reçues pour cette recherche ; `cursor` = celle qu'on charge (undefined : la première).
  const [pages, setPages] = useState<AssetView[]>([])
  const [cursor, setCursor] = useState<string | undefined>(undefined)
  const key = JSON.stringify(search)
  const current = useJson((signal) => searchPhotos({ ...search, cursor }, signal), `${key}:${cursor ?? ''}`)

  useEffect(() => {
    setPages([])
    setCursor(undefined)
  }, [key])

  useEffect(() => {
    if (current.data) setPages((p) => (cursor ? [...p, ...current.data!.assets] : current.data!.assets))
  }, [current.data, cursor])

  const assets = pages

  if (current.error) return <ErrorMessage error={current.error} />
  if (!current.loading && assets.length === 0) return <p className="text-center text-stone-500">Aucune photo sur cette sélection.</p>

  return (
    <>
      <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
        {assets.map((asset) => {
          const locked = already.includes(asset.id)
          const checked = locked || selected.includes(asset.id)
          const label = [dateLabel(asset.takenAt), placeLabel(asset.city, asset.country), asset.people.join(', ')].filter(Boolean).join(' · ')
          return (
            <li key={asset.id}>
              <label className={`relative block cursor-pointer overflow-hidden rounded-lg ring-2 ${checked ? 'ring-brique' : 'ring-transparent'} ${locked ? 'opacity-60' : ''}`} title={label}>
                <input type="checkbox" className="absolute top-2 left-2 z-10 size-5 accent-brique" checked={checked} disabled={locked} onChange={() => onToggle(asset.id)} aria-label={label || 'Photo'} />
                <img src={thumbnailUrl(asset.id)} alt="" className="aspect-square w-full object-cover" loading="lazy" />
                {asset.people.length > 0 && <span className="absolute inset-x-0 bottom-0 truncate bg-black/50 px-2 py-0.5 text-xs text-white">{asset.people.join(', ')}</span>}
              </label>
            </li>
          )
        })}
      </ul>
      {current.loading && <p className="mt-3 text-center text-sm text-stone-500">Chargement…</p>}
      {current.data?.nextCursor && (
        <div className="mt-3 text-center">
          <button type="button" className={button.secondary} onClick={() => setCursor(current.data!.nextCursor!)}>
            Plus de photos
          </button>
        </div>
      )}
    </>
  )
}
