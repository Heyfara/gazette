import type { AlbumView, CampaignState, ConfigView, IssueSummary, IssueView, PersonView, PhotoInput, SearchPage } from './types'

/** Erreur renvoyée par l'API ({ error, details? }) ou réponse inattendue. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly details: string[] = [],
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

async function getJson<T>(url: string, signal?: AbortSignal, init: RequestInit = {}): Promise<T> {
  const response = await fetch(url, { headers: { Accept: 'application/json' }, signal, ...init })
  const body: unknown = response.status === 204 ? undefined : await response.json().catch(() => null)

  if (!response.ok) {
    const error = body as { error?: string; details?: string[] } | null
    throw new ApiError(response.status, error?.error ?? `Le serveur a répondu ${response.status}`, error?.details ?? [])
  }

  return body as T
}

const sendJson = (method: string, body: unknown): RequestInit => ({
  method,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify(body),
})

const issueUrl = (id: string) => `/api/issues/${encodeURIComponent(id)}`
const sectionUrl = (id: string, sectionId: string) => `${issueUrl(id)}/sections/${encodeURIComponent(sectionId)}`

export const fetchConfig = (signal?: AbortSignal) => getJson<ConfigView>('/api/config', signal)
export const fetchIssues = (signal?: AbortSignal) => getJson<IssueSummary[]>('/api/issues', signal)
export const fetchIssue = (id: string, signal?: AbortSignal) => getJson<IssueView>(issueUrl(id), signal)

export const createIssue = (fields: { month?: string; name?: string; title?: string }) => getJson<IssueView>('/api/issues', undefined, sendJson('POST', fields))
export const patchIssue = (id: string, fields: { name?: string; title?: string; month?: string }) => getJson<IssueView>(issueUrl(id), undefined, sendJson('PATCH', fields))
export const deleteIssue = (id: string) => getJson<void>(issueUrl(id), undefined, { method: 'DELETE' })

export interface SectionPatch {
  title?: string
  notes?: string
  text?: string
  photos?: PhotoInput[]
}

export const addSection = (id: string, title = '') => getJson<IssueView>(`${issueUrl(id)}/sections`, undefined, sendJson('POST', { title }))
export const patchSection = (id: string, sectionId: string, fields: SectionPatch) => getJson<IssueView>(sectionUrl(id, sectionId), undefined, sendJson('PATCH', fields))
export const deleteSection = (id: string, sectionId: string) => getJson<IssueView>(sectionUrl(id, sectionId), undefined, { method: 'DELETE' })
export const reorderSections = (id: string, order: string[]) => getJson<IssueView>(`${issueUrl(id)}/sections-order`, undefined, sendJson('PUT', { order }))

export const fetchPrompt = (id: string, sectionId: string) => getJson<{ prompt: string }>(`${sectionUrl(id, sectionId)}/prompt`).then((r) => r.prompt)

/** Adresse de l'aperçu du numéro (page HTML complète), à mettre dans une iframe. */
export const previewUrl = (id: string, version: number) => `${issueUrl(id)}/preview?v=${version}`
/** Le mail rendu par Listmonk (template compris) ; prépare le numéro au passage. */
export const mailPreviewUrl = (id: string, version: number) => `${issueUrl(id)}/mail-preview?v=${version}`

const post = (url: string) => getJson<IssueView>(url, undefined, { method: 'POST', headers: { Accept: 'application/json' } })
/** Dépose les photos et crée ou met à jour la campagne Listmonk. */
export const prepareIssue = (id: string) => post(`${issueUrl(id)}/prepare`)
/** Envoi de test aux adresses configurées ; le numéro passe en « testé ». */
export const testIssue = (id: string) => post(`${issueUrl(id)}/test`)
/** Envoi à la famille ; le numéro passe en « envoyé » et se fige. */
export const sendIssue = (id: string) => post(`${issueUrl(id)}/send`)
export const fetchCampaignState = (id: string, signal?: AbortSignal) => getJson<CampaignState | null>(`${issueUrl(id)}/campaign`, signal)

export const fetchAlbums = (signal?: AbortSignal) => getJson<AlbumView[]>('/api/immich/albums', signal)
export const fetchPeople = (signal?: AbortSignal) => getJson<PersonView[]>('/api/immich/people', signal)

export interface PhotoSearch {
  album?: string
  from?: string
  to?: string
  person?: string
  cursor?: string
}

export const searchPhotos = (search: PhotoSearch, signal?: AbortSignal) => {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(search)) if (value !== undefined && value !== '') params.set(key, String(value))
  return getJson<SearchPage>(`/api/immich/search?${params}`, signal)
}

/** Miniature d'une photo, relayée par le serveur. */
export const thumbnailUrl = (assetId: string, size: 'thumbnail' | 'preview' = 'thumbnail') => `/api/immich/assets/${encodeURIComponent(assetId)}/${size}`
export const personThumbnailUrl = (personId: string) => `/api/immich/people/${encodeURIComponent(personId)}/thumbnail`
