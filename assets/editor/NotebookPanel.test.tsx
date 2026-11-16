import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { issue, mockFetch } from '../test-fixtures'
import { NotebookPanel } from './NotebookPanel'

describe('NotebookPanel', () => {
  afterEach(() => vi.unstubAllGlobals())

  function setup(routes: Parameters<typeof mockFetch>[0] = {}, view = issue) {
    const mock = mockFetch(routes)
    vi.stubGlobal('fetch', mock.fetchMock)
    const onIssue = vi.fn()
    render(<NotebookPanel issue={view} onIssue={onIssue} />)
    return { ...mock, onIssue }
  }

  it('liste les notes par date', () => {
    setup()

    const items = screen.getAllByRole('listitem')
    expect(items.map((li) => li.textContent)).toEqual([expect.stringContaining('03/11On a accueilli les nouvelles poules'), expect.stringContaining('12/11Emma a perdu sa première dent')])
  })

  it('ajoute une note datée du jour par défaut et vide le champ', async () => {
    const { calls, onIssue } = setup({ 'POST /api/issues/ab12cd34/notes': issue })

    const date = screen.getByLabelText('Date de la note') as HTMLInputElement
    expect(date.value).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    fireEvent.change(date, { target: { value: '2026-11-15' } })
    const text = screen.getByLabelText('Nouvelle note')
    fireEvent.change(text, { target: { value: 'Premier match de foot de Léo' } })
    fireEvent.submit(text.closest('form')!)

    await waitFor(() => expect(onIssue).toHaveBeenCalledWith(issue))
    expect(JSON.parse(String(calls[0]?.init?.body))).toEqual({ text: 'Premier match de foot de Léo', date: '2026-11-15' })
    expect((text as HTMLInputElement).value).toBe('')
  })

  it("n'envoie pas une note vide", () => {
    const { calls } = setup()

    fireEvent.submit(screen.getByLabelText('Nouvelle note').closest('form')!)

    expect(calls).toHaveLength(0)
  })

  it('modifie une note', async () => {
    const { calls, onIssue } = setup({ 'PATCH /api/issues/ab12cd34/notes/n2': issue })

    const item = screen.getAllByRole('listitem')[1]!
    within(item).getByRole('button', { name: 'Modifier' }).click()
    fireEvent.change(await within(item).findByLabelText('Texte de la note'), { target: { value: 'Emma a perdu deux dents' } })
    within(item).getByRole('button', { name: 'Enregistrer' }).click()

    await waitFor(() => expect(onIssue).toHaveBeenCalled())
    expect(JSON.parse(String(calls[0]?.init?.body))).toEqual({ text: 'Emma a perdu deux dents', date: '2026-11-12' })
  })

  it('supprime une note après confirmation', async () => {
    const { calls } = setup({ 'DELETE /api/issues/ab12cd34/notes/n1': issue })
    vi.stubGlobal('confirm', () => true)

    within(screen.getAllByRole('listitem')[0]!).getByRole('button', { name: 'Supprimer' }).click()

    await waitFor(() => expect(calls).toHaveLength(1))
  })

  it('copie le prompt du premier jet', async () => {
    setup({ '/api/issues/ab12cd34/prompt': { prompt: 'Premier jet…' } })
    const writeText = vi.fn(async () => {})
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })

    screen.getByRole('button', { name: 'Prompt du premier jet' }).click()

    await screen.findByRole('button', { name: 'Prompt copié ✓' })
    expect(writeText).toHaveBeenCalledWith('Premier jet…')
  })

  it('montre l’erreur de l’API', async () => {
    setup({})

    fireEvent.change(screen.getByLabelText('Nouvelle note'), { target: { value: 'x' } })
    fireEvent.submit(screen.getByLabelText('Nouvelle note').closest('form')!)

    expect((await screen.findByRole('alert')).textContent).toContain('Inconnu')
  })

  it('reste en lecture seule sur un numéro envoyé', () => {
    setup({}, { ...issue, editable: false })

    expect(screen.queryByLabelText('Nouvelle note')).toBeNull()
    expect(screen.queryByRole('button', { name: 'Modifier' })).toBeNull()
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
  })
})
