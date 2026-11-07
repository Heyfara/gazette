import { mailPreviewUrl, previewUrl } from '../http/client'

export type PreviewMode = 'local' | 'listmonk'

/**
 * Le numéro tel qu'il se lira dans le mail ; rechargé à chaque modification
 * (`version`). En mode « listmonk », c'est Listmonk qui rend le mail, template
 * compris, ce qui prépare le numéro (dépôt des photos, campagne).
 */
export function PreviewPane({ issueId, version, mode = 'local' }: { issueId: string; version: number; mode?: PreviewMode }) {
  return (
    <div className="sticky top-6 flex h-[calc(100dvh-3rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200 dark:bg-slate-900 dark:ring-slate-800">
      <p className="border-b border-stone-200 px-4 py-2 text-xs font-semibold tracking-widest text-stone-500 uppercase dark:border-slate-800 dark:text-slate-400">
        {mode === 'listmonk' ? 'Aperçu du mail, rendu par Listmonk' : 'Aperçu du mail'}
      </p>
      <iframe title="Aperçu du numéro" src={mode === 'listmonk' ? mailPreviewUrl(issueId, version) : previewUrl(issueId, version)} className="h-full w-full flex-1 bg-[#f3f1ec]" />
    </div>
  )
}
