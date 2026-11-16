import { describe, expect, it } from 'vitest'
import { currentMonth, dateLabel, dayLabel, monthLabel, monthRange, placeLabel, today } from './format'

describe('format', () => {
  it('mois en français et bornes du mois', () => {
    expect(monthLabel('2026-11')).toBe('novembre 2026')
    expect(monthLabel('2027-02')).toBe('février 2027')
    expect(monthRange('2026-02')).toEqual({ from: '2026-02-01', to: '2026-02-28' })
    expect(monthRange('2028-02')).toEqual({ from: '2028-02-01', to: '2028-02-29' })
    // 23 h 30 UTC le 30 novembre = minuit passé à Paris
    expect(currentMonth(new Date('2026-11-30T23:30:00Z'))).toBe('2026-12')
  })

  it('jour du carnet', () => {
    expect(today(new Date('2026-11-30T23:30:00Z'))).toBe('2026-12-01')
    expect(dayLabel('2026-11-03')).toBe('03/11')
  })

  it('date et lieu d’une photo', () => {
    expect(dateLabel('2026-10-15T14:30:00')).toBe('15/10/2026')
    expect(dateLabel(null)).toBe('')
    expect(placeLabel('Saint-Malo', 'France')).toBe('Saint-Malo (France)')
    expect(placeLabel(null, 'France')).toBe('France')
    expect(placeLabel(null, null)).toBe('')
  })
})
