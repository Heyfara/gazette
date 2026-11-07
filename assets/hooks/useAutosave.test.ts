import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useAutosave } from './useAutosave'

describe('useAutosave', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('n’enregistre rien au montage, puis un peu après la dernière modification', async () => {
    const save = vi.fn(async () => {})
    const { result, rerender } = renderHook(({ value }) => useAutosave(value, save, 500), { initialProps: { value: { text: 'a' } } })

    await act(() => vi.advanceTimersByTimeAsync(1000))
    expect(save).not.toHaveBeenCalled()
    expect(result.current.status).toBe('idle')

    rerender({ value: { text: 'ab' } })
    expect(result.current.status).toBe('pending')
    await act(() => vi.advanceTimersByTimeAsync(300))
    rerender({ value: { text: 'abc' } })
    await act(() => vi.advanceTimersByTimeAsync(300))
    expect(save).not.toHaveBeenCalled()

    await act(() => vi.advanceTimersByTimeAsync(300))
    expect(save).toHaveBeenCalledTimes(1)
    expect(save).toHaveBeenCalledWith({ text: 'abc' })
    expect(result.current.status).toBe('saved')
  })

  it('signale l’erreur d’enregistrement', async () => {
    const save = vi.fn(async () => {
      throw new Error('Données refusées')
    })
    const { result, rerender } = renderHook(({ value }) => useAutosave(value, save, 100), { initialProps: { value: 'a' } })

    rerender({ value: 'b' })
    await act(() => vi.advanceTimersByTimeAsync(200))

    expect(result.current).toEqual({ status: 'error', error: new Error('Données refusées') })
  })
})
