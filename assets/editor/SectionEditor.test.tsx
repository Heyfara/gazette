import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { issue, mockFetch, P1, P2 } from '../test-fixtures'
import { SectionEditor } from './SectionEditor'

describe('SectionEditor', () => {
  afterEach(() => vi.unstubAllGlobals())

  function setup(routes: Parameters<typeof mockFetch>[0] = {}) {
    const mock = mockFetch({ 'PATCH /api/issues/ab12cd34/sections/s1': (init?: RequestInit) => ({ ...issue, sections: [{ ...issue.sections[0]!, ...JSON.parse(String(init?.body)) }, issue.sections[1]] }), ...routes })
    vi.stubGlobal('fetch', mock.fetchMock)
    const onIssue = vi.fn()
    render(<SectionEditor issue={issue} section={issue.sections[0]!} index={0} onIssue={onIssue} onDelete={() => {}} />)
    return { ...mock, onIssue }
  }

  it('enregistre le texte un peu après la frappe et le dit', async () => {
    const { calls, onIssue } = setup()

    expect(screen.queryByLabelText(/Notes/)).toBeNull()
    fireEvent.change(screen.getByLabelText('Texte de la rubrique'), { target: { value: 'Léo a adoré la mer.' } })
    expect(screen.getByRole('status').textContent).toBe('Modifié')

    await waitFor(() => expect(onIssue).toHaveBeenCalled(), { timeout: 2000 })
    expect(JSON.parse(String(calls[0]?.init?.body))).toEqual({ title: 'À la mer', text: 'Léo a adoré la mer.' })
    expect(screen.getByRole('status').textContent).toBe('Enregistré')
  })

  it('copie le prompt dans le presse-papiers', async () => {
    setup({ '/api/issues/ab12cd34/sections/s1/prompt': { prompt: 'Tu rédiges…' } })
    const writeText = vi.fn(async () => {})
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })

    screen.getByRole('button', { name: 'Copier le prompt' }).click()

    await screen.findByRole('button', { name: 'Prompt copié ✓' })
    expect(writeText).toHaveBeenCalledWith('Tu rédiges…')
  })

  it('montre le prompt à copier à la main si le presse-papiers est refusé', async () => {
    setup({ '/api/issues/ab12cd34/sections/s1/prompt': { prompt: 'Tu rédiges…' } })
    vi.stubGlobal('navigator', { ...navigator, clipboard: undefined })

    screen.getByRole('button', { name: 'Copier le prompt' }).click()

    expect((await screen.findByLabelText('Prompt à copier')).textContent).toBe('Tu rédiges…')
  })

  it('retire une photo tout de suite', async () => {
    const { calls } = setup()

    screen.getByRole('button', { name: 'Retirer la photo 1' }).click()

    await waitFor(() => expect(calls).toHaveLength(1))
    expect(JSON.parse(String(calls[0]?.init?.body))).toEqual({ photos: [] })
  })

  it('ajoute les photos choisies à la suite', async () => {
    const { calls } = setup({
      '/api/immich/albums': [],
      '/api/immich/people': [],
      '/api/immich/search?from=2026-11-01&to=2026-11-30': { assets: [{ id: P2, takenAt: '2026-11-02T10:00:00', city: 'Cancale', country: 'France', people: [] }], count: 1, nextCursor: null },
    })

    screen.getByRole('button', { name: '+ Choisir des photos' }).click()
    ;(await screen.findByRole('checkbox', { name: /Cancale/ })).click()
    screen.getByRole('button', { name: 'Ajouter' }).click()

    await waitFor(() => expect(calls.some((c) => c.init?.method === 'PATCH')).toBe(true))
    const patch = calls.find((c) => c.init?.method === 'PATCH')
    expect(JSON.parse(String(patch?.init?.body))).toEqual({ photos: [{ assetId: P1, caption: '' }, { assetId: P2, caption: '' }] })
    expect(screen.queryByRole('dialog')).toBeNull()
  })

  it('ne modifie rien sur un numéro envoyé', () => {
    vi.stubGlobal('fetch', mockFetch({}).fetchMock)
    render(<SectionEditor issue={{ ...issue, editable: false }} section={issue.sections[0]!} index={0} onIssue={() => {}} onDelete={() => {}} />)

    expect((screen.getByLabelText('Titre de la rubrique') as HTMLInputElement).disabled).toBe(true)
    expect(screen.queryByRole('button', { name: '+ Choisir des photos' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Copier le prompt' })).toBeNull()
  })
})
