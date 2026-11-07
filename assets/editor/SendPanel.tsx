import { useState } from 'react'
import { useJson } from '../hooks/useJson'
import { fetchCampaignState, prepareIssue, sendIssue, testIssue } from '../http/client'
import type { IssueView, SendConfigView } from '../http/types'
import { ErrorMessage } from '../ui/ErrorMessage'
import { button, card } from '../ui/styles'

interface Props {
  issue: IssueView
  config: SendConfigView | null
  onIssue: (issue: IssueView) => void
  /** Afficher l'aperçu rendu par Listmonk (prépare le numéro au passage) */
  onPreview: () => void
}

/**
 * L'envoi du numéro, en trois temps : préparer (photos déposées, campagne
 * Listmonk créée), envoyer un test aux adresses configurées, envoyer à la
 * famille. Le vrai envoi exige un test avant.
 */
export function SendPanel({ issue, config, onIssue, onPreview }: Props) {
  const [busy, setBusy] = useState<'prepare' | 'test' | 'send' | null>(null)
  const [error, setError] = useState<Error | null>(null)
  const [done, setDone] = useState<string | null>(null)
  const state = useJson((signal) => fetchCampaignState(issue.id, signal), `campaign:${issue.id}:${issue.status}:${issue.campaignId ?? ''}`)

  const run = async (what: 'prepare' | 'test' | 'send', action: () => Promise<IssueView>, message: string) => {
    setBusy(what)
    setError(null)
    setDone(null)
    try {
      onIssue(await action())
      setDone(message)
    } catch (e) {
      setError(e instanceof Error ? e : new Error(String(e)))
    } finally {
      setBusy(null)
    }
  }

  if (config && !config.configured) {
    return (
      <section className={card} aria-label="Envoi">
        <h2 className="mb-2 text-xs font-semibold tracking-widest text-brique uppercase">Envoi</h2>
        <p className="text-sm text-stone-500 dark:text-slate-400">L'envoi n'est pas configuré sur ce serveur (Listmonk, liste et template). Le numéro peut être rédigé en attendant.</p>
      </section>
    )
  }

  const sent = issue.status === 'envoye'
  const emails = config?.testEmails ?? []

  return (
    <section className={card} aria-label="Envoi">
      <div className="mb-3 flex flex-wrap items-center gap-3">
        <h2 className="text-xs font-semibold tracking-widest text-brique uppercase">Envoi</h2>
        {issue.campaignId && (
          <span className="text-xs text-stone-500 dark:text-slate-400">
            Campagne Listmonk n° {issue.campaignId}
            {state.data && ` · ${state.data.status}${state.data.sent > 0 || state.data.toSend > 0 ? `, ${state.data.sent}/${state.data.toSend} envoyés` : ''}`}
          </span>
        )}
      </div>

      {sent ? (
        <p className="text-sm text-stone-700 dark:text-slate-300">Numéro envoyé à la famille le {issue.sentAt ? new Date(issue.sentAt).toLocaleString('fr-FR') : '—'}.</p>
      ) : (
        <ol className="flex flex-col gap-3 text-sm">
          <li className="flex flex-wrap items-center gap-3">
            <span className="w-6 shrink-0 font-serif text-lg text-brique">1</span>
            <button type="button" className={button.secondary} disabled={busy !== null} onClick={() => void run('prepare', () => prepareIssue(issue.id), 'Photos déposées et campagne à jour.')}>
              {busy === 'prepare' ? 'Préparation…' : issue.campaignId ? 'Mettre à jour la campagne' : 'Préparer l’envoi'}
            </button>
            <button type="button" className={button.ghost} onClick={onPreview}>
              Voir le mail rendu par Listmonk
            </button>
            <span className="text-stone-500 dark:text-slate-400">photos recompressées et déposées, campagne créée dans Listmonk</span>
          </li>
          <li className="flex flex-wrap items-center gap-3">
            <span className="w-6 shrink-0 font-serif text-lg text-brique">2</span>
            <button type="button" className={button.secondary} disabled={busy !== null || emails.length === 0} onClick={() => void run('test', () => testIssue(issue.id), `Test envoyé à ${emails.join(', ')}. Vérifiez-le sur le téléphone avant l’envoi.`)}>
              {busy === 'test' ? 'Envoi du test…' : 'Envoyer un test'}
            </button>
            <span className="text-stone-500 dark:text-slate-400">{emails.length > 0 ? `à ${emails.join(', ')}` : 'aucune adresse de test configurée'}</span>
            {issue.status === 'teste' && <span className="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-200">test envoyé</span>}
          </li>
          <li className="flex flex-wrap items-center gap-3">
            <span className="w-6 shrink-0 font-serif text-lg text-brique">3</span>
            <button
              type="button"
              className={button.primary}
              disabled={busy !== null || issue.status !== 'teste'}
              title={issue.status !== 'teste' ? 'Envoyer d’abord un test' : undefined}
              onClick={() => {
                if (window.confirm(`Envoyer le numéro ${issue.number} à toute la liste « ${config?.list ?? ''} » ? Il ne pourra plus être modifié.`)) {
                  void run('send', () => sendIssue(issue.id), 'Numéro envoyé à la famille.')
                }
              }}
            >
              {busy === 'send' ? 'Envoi…' : 'Envoyer à la famille'}
            </button>
            <span className="text-stone-500 dark:text-slate-400">liste « {config?.list ?? '…'} », après relecture du test</span>
          </li>
        </ol>
      )}

      {done && (
        <p role="status" className="mt-3 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-900">
          {done}
        </p>
      )}
      {error && (
        <div className="mt-3">
          <ErrorMessage error={error} />
        </div>
      )}
    </section>
  )
}
