import { render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { App } from './App'
import { issue, mockFetch } from './test-fixtures'

const summary = { ...issue, sections: 2 }
const config = { defaultName: 'x', maxPhotos: 12, send: { configured: true, list: 'Famille', template: 'Gazette', testEmails: ['alice@test'] } }

// Le panneau d'envoi lit l'état de la campagne.
const campaign = { '/api/issues/ab12cd34/campaign': null, '/api/issues/ff00ff00/campaign': null }

describe('App', () => {
  beforeEach(() => window.history.replaceState(null, '', '/'))
  afterEach(() => vi.unstubAllGlobals())

  it('liste les numéros et ouvre celui qu’on choisit', async () => {
    vi.stubGlobal('fetch', mockFetch({ '/api/issues': [summary], '/api/issues/ab12cd34': issue, '/api/config': config, ...campaign }).fetchMock)
    render(<App />)

    const row = await screen.findByRole('button', { name: /Spécial mer/ })
    expect(row.textContent).toContain('novembre 2026')
    expect(row.textContent).toContain('2 rubriques, 1 photo')
    expect(row.textContent).toContain('Brouillon')

    row.click()
    await screen.findByLabelText('Rubrique 1')
    expect(window.location.pathname).toBe('/numeros/ab12cd34')
    expect((screen.getAllByLabelText('Titre de la rubrique')[0] as HTMLInputElement).value).toBe('À la mer')
  })

  it('crée un numéro du mois courant et l’ouvre', async () => {
    const { fetchMock, calls } = mockFetch({ '/api/issues': [], 'POST /api/issues': { ...issue, id: 'ff00ff00' }, '/api/issues/ff00ff00': issue, '/api/config': config, ...campaign })
    vi.stubGlobal('fetch', fetchMock)
    render(<App />)

    await screen.findByText(/Aucun numéro/)
    screen.getByRole('button', { name: 'Nouveau numéro' }).click()

    await screen.findByLabelText('En-tête du numéro')
    expect(window.location.pathname).toBe('/numeros/ff00ff00')
    const post = calls.find((c) => c.init?.method === 'POST')
    expect(JSON.parse(String(post?.init?.body))).toEqual({ month: expect.stringMatching(/^\d{4}-\d{2}$/) })
  })

  it('affiche l’erreur de l’API', async () => {
    vi.stubGlobal('fetch', async () => new Response(JSON.stringify({ error: 'Immich injoignable' }), { status: 502 }))
    render(<App />)

    expect((await screen.findByRole('alert')).textContent).toContain('Immich injoignable')
  })
})
