const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']

/** « novembre 2026 » depuis « 2026-11 ». */
export function monthLabel(month: string): string {
  const [year, m] = month.split('-')
  const name = MONTHS[Number(m) - 1]
  return name && year ? `${name} ${year}` : month
}

/** Mois courant au format AAAA-MM (heure de Paris). */
export function currentMonth(now = new Date()): string {
  const parts = new Intl.DateTimeFormat('fr-FR', { timeZone: 'Europe/Paris', year: 'numeric', month: '2-digit' }).formatToParts(now)
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? ''
  return `${get('year')}-${get('month')}`
}

/** Premier et dernier jour d'un mois AAAA-MM, en AAAA-MM-JJ. */
export function monthRange(month: string): { from: string; to: string } {
  const [year, m] = month.split('-').map(Number)
  const last = new Date(Date.UTC(year ?? 1970, m ?? 1, 0)).getUTCDate()
  return { from: `${month}-01`, to: `${month}-${String(last).padStart(2, '0')}` }
}

/** « 15/10/2026 » depuis « 2026-10-15T14:30:00 ». */
export function dateLabel(iso: string | null): string {
  if (!iso) return ''
  const [date] = iso.split('T')
  const [y, m, d] = (date ?? '').split('-')
  return y && m && d ? `${d}/${m}/${y}` : iso
}

/** Lieu d'une photo : « Saint-Malo (France) ». */
export function placeLabel(city: string | null, country: string | null): string {
  if (city && country) return `${city} (${country})`
  return city ?? country ?? ''
}

export const STATUS_LABEL = { brouillon: 'Brouillon', teste: 'Test envoyé', envoye: 'Envoyée' } as const
