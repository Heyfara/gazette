import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockFetch, P1, P2, P3 } from '../test-fixtures'
import { PhotoPicker } from './PhotoPicker'

const asset = (id: string, city: string, people: string[] = []) => ({ id, takenAt: '2026-10-15T14:30:00', city, country: 'France', people })

describe('PhotoPicker', () => {
  afterEach(() => vi.unstubAllGlobals())

  function setup(already: string[] = []) {
    const mock = mockFetch({
      '/api/immich/albums': [{ id: '7b1e2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', name: 'Vacances', count: 2, start: null, end: null, coverAssetId: null }],
      '/api/immich/people': [{ id: '9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3b', name: 'Léo' }],
      '/api/immich/search?from=2026-10-01&to=2026-10-31': { assets: [asset(P1, 'Saint-Malo', ['Léo']), asset(P2, 'Cancale')], count: 2, nextCursor: null },
      '/api/immich/search?from=2026-10-01&to=2026-10-31&person=9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3b': { assets: [asset(P1, 'Saint-Malo', ['Léo'])], count: 1, nextCursor: null },
      '/api/immich/search?album=7b1e2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d': { assets: [asset(P3, 'Dinard')], count: 1, nextCursor: null },
      // Le filtre par personne reste quand on change d'onglet.
      '/api/immich/search?album=7b1e2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d&person=9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3b': { assets: [asset(P3, 'Dinard', ['Léo'])], count: 1, nextCursor: null },
    })
    vi.stubGlobal('fetch', mock.fetchMock)
    const onAdd = vi.fn()
    const onClose = vi.fn()
    render(<PhotoPicker month="2026-10" already={already} onAdd={onAdd} onClose={onClose} />)
    return { ...mock, onAdd, onClose }
  }

  it('propose le mois du numéro, coche et ajoute', async () => {
    const { onAdd } = setup()

    expect((screen.getByLabelText('Du') as HTMLInputElement).value).toBe('2026-10-01')
    expect((screen.getByLabelText('Au') as HTMLInputElement).value).toBe('2026-10-31')
    const ajouter = screen.getByRole('button', { name: 'Ajouter' })
    expect((ajouter as HTMLButtonElement).disabled).toBe(true)

    ;(await screen.findByRole('checkbox', { name: /Cancale/ })).click()
    screen.getByRole('checkbox', { name: /Saint-Malo/ }).click()
    expect(screen.getByText('2 photos choisies')).toBeTruthy()
    ajouter.click()

    expect(onAdd).toHaveBeenCalledWith([P2, P1])
  })

  it('verrouille les photos déjà dans la rubrique', async () => {
    setup([P1])

    const locked = await screen.findByRole('checkbox', { name: /Saint-Malo/ })
    expect((locked as HTMLInputElement).disabled).toBe(true)
    expect((locked as HTMLInputElement).checked).toBe(true)
  })

  it('filtre par personne et par album', async () => {
    const { calls } = setup()
    await screen.findByRole('checkbox', { name: /Cancale/ })

    fireEvent.change(screen.getByLabelText('Personne'), { target: { value: '9c8b7a6f-5e4d-4c3b-a291-807f6e5d4c3b' } })
    await waitFor(() => expect(screen.queryByRole('checkbox', { name: /Cancale/ })).toBeNull())
    expect(calls.some((c) => c.url.includes('person=9c8b7a6f'))).toBe(true)

    screen.getByRole('tab', { name: 'Par album' }).click()
    await screen.findByText('Choisissez un album.')
    await screen.findByRole('option', { name: 'Vacances (2)' })
    fireEvent.change(screen.getByLabelText('Album'), { target: { value: '7b1e2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d' } })
    await screen.findByRole('checkbox', { name: /Dinard/ })
  })

  it('se ferme avec Échap', () => {
    const { onClose } = setup()

    fireEvent.keyDown(window, { key: 'Escape' })

    expect(onClose).toHaveBeenCalled()
  })
})
