import type { IssueView } from './http/types'

export const P1 = '0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d71'
export const P2 = '0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d72'
export const P3 = '0a3c1d6e-5b2f-4c8a-9d1e-2f3a4b5c6d73'

export const issue: IssueView = {
  id: 'ab12cd34',
  number: 4,
  name: 'La gazette de test',
  title: 'Spécial mer',
  month: '2026-11',
  status: 'brouillon',
  editable: true,
  photos: 1,
  createdAt: '2026-11-07T20:00:00+01:00',
  sentAt: null,
  campaignId: null,
  notes: [
    { id: 'n1', date: '2026-11-03', text: 'On a accueilli les nouvelles poules' },
    { id: 'n2', date: '2026-11-12', text: 'Emma a perdu sa première dent' },
  ],
  sections: [
    {
      id: 's1',
      title: 'À la mer',
      text: 'Léo a adoré.',
      photos: [{ assetId: P1, takenAt: '2026-10-15T14:30:00', city: 'Saint-Malo', country: 'France', people: ['Léo', 'Emma'], caption: '' }],
    },
    { id: 's2', title: '', text: '', photos: [] },
  ],
}

/** Un `fetch` simulé : chaque route répond avec le corps donné (ou 404). */
export function mockFetch(routes: Record<string, unknown | ((init?: RequestInit) => unknown)>) {
  const calls: { url: string; init?: RequestInit }[] = []
  const fetchMock = async (url: string, init?: RequestInit) => {
    calls.push({ url, init })
    const key = `${init?.method ?? 'GET'} ${url}`
    const route = routes[key] ?? routes[url]
    if (route === undefined) return new Response(JSON.stringify({ error: `Inconnu : ${key}` }), { status: 404 })
    const body = typeof route === 'function' ? route(init) : route
    return new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } })
  }
  return { fetchMock, calls }
}
