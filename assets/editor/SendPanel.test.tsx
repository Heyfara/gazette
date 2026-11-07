import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { issue, mockFetch } from '../test-fixtures'
import { SendPanel } from './SendPanel'

const config = { configured: true, list: 'Famille', template: 'Gazette', testEmails: ['alice@test', 'bob@test'] }

describe('SendPanel', () => {
  afterEach(() => vi.unstubAllGlobals())

  function setup(status: 'brouillon' | 'teste' | 'envoye' = 'brouillon', routes: Parameters<typeof mockFetch>[0] = {}) {
    const current = { ...issue, status, campaignId: status === 'brouillon' ? null : 40, sentAt: status === 'envoye' ? '2026-11-08T10:00:00+01:00' : null }
    const mock = mockFetch({
      '/api/issues/ab12cd34/campaign': status === 'brouillon' ? null : { id: 40, status: status === 'envoye' ? 'running' : 'draft', toSend: 5, sent: 2 },
      'POST /api/issues/ab12cd34/prepare': { ...current, campaignId: 40 },
      'POST /api/issues/ab12cd34/test': { ...current, campaignId: 40, status: 'teste' },
      'POST /api/issues/ab12cd34/send': { ...current, status: 'envoye', editable: false, sentAt: '2026-11-08T10:00:00+01:00' },
      ...routes,
    })
    vi.stubGlobal('fetch', mock.fetchMock)
    const onIssue = vi.fn()
    const onPreview = vi.fn()
    render(<SendPanel issue={current} config={config} onIssue={onIssue} onPreview={onPreview} />)
    return { ...mock, onIssue, onPreview }
  }

  it('prépare, puis propose le test ; l’envoi attend un test', async () => {
    const { onIssue, onPreview, calls } = setup()

    expect((screen.getByRole('button', { name: 'Envoyer à la famille' }) as HTMLButtonElement).disabled).toBe(true)
    screen.getByRole('button', { name: 'Préparer l’envoi' }).click()
    await screen.findByText('Photos déposées et campagne à jour.')
    expect(onIssue).toHaveBeenCalledWith(expect.objectContaining({ campaignId: 40 }))
    expect(calls.some((c) => c.url.endsWith('/prepare') && c.init?.method === 'POST')).toBe(true)

    screen.getByRole('button', { name: 'Voir le mail rendu par Listmonk' }).click()
    expect(onPreview).toHaveBeenCalled()

    screen.getByRole('button', { name: 'Envoyer un test' }).click()
    await screen.findByText(/Test envoyé à alice@test, bob@test/)
    expect(onIssue).toHaveBeenLastCalledWith(expect.objectContaining({ status: 'teste' }))
  })

  it('envoie à la famille après confirmation, une fois le test fait', async () => {
    const { onIssue } = setup('teste')
    vi.stubGlobal('confirm', vi.fn(() => true))

    await screen.findByText(/Campagne Listmonk n° 40/)
    screen.getByText('test envoyé')
    screen.getByRole('button', { name: 'Envoyer à la famille' }).click()

    await screen.findByText('Numéro envoyé à la famille.')
    expect(onIssue).toHaveBeenCalledWith(expect.objectContaining({ status: 'envoye' }))
  })

  it('n’envoie rien si on annule la confirmation', async () => {
    const { calls } = setup('teste')
    vi.stubGlobal('confirm', vi.fn(() => false))

    screen.getByRole('button', { name: 'Envoyer à la famille' }).click()

    await waitFor(() => expect(calls.filter((c) => c.init?.method === 'POST')).toHaveLength(0))
  })

  it('affiche l’état d’un numéro envoyé', async () => {
    setup('envoye')

    await screen.findByText(/running, 2\/5 envoyés/)
    screen.getByText(/Numéro envoyé à la famille le/)
    expect(screen.queryByRole('button', { name: 'Envoyer à la famille' })).toBeNull()
  })

  it('montre la raison d’un refus', async () => {
    setup('brouillon', { 'POST /api/issues/ab12cd34/prepare': undefined })
    vi.stubGlobal(
      'fetch',
      vi.fn(async (_url: string, init?: RequestInit) =>
        init?.method === 'POST' ? new Response(JSON.stringify({ error: 'Le numéro n’a aucune rubrique.' }), { status: 409 }) : new Response('null', { status: 200 }),
      ),
    )

    screen.getByRole('button', { name: 'Préparer l’envoi' }).click()

    expect((await screen.findByRole('alert')).textContent).toContain('Le numéro n’a aucune rubrique.')
  })

  it('explique quand l’envoi n’est pas configuré', () => {
    vi.stubGlobal('fetch', mockFetch({ '/api/issues/ab12cd34/campaign': null }).fetchMock)
    render(<SendPanel issue={issue} config={{ ...config, configured: false }} onIssue={() => {}} onPreview={() => {}} />)

    screen.getByText(/L'envoi n'est pas configuré/)
  })
})
