import { existsSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

// L'icône est un fichier à nom fixe (assets/public/ → /build/favicon.svg) : le
// dashboard du homelab l'affiche par son adresse, elle ne doit pas changer.
describe('favicon', () => {
  const dir = import.meta.dirname

  it('la page la déclare par son chemin public', () => {
    const html = readFileSync(join(dir, 'index.html'), 'utf8')
    expect(html).toContain('<link rel="icon" type="image/svg+xml" href="/favicon.svg" />')
  })

  it('le fichier existe et est un SVG carré', () => {
    const file = join(dir, 'public', 'favicon.svg')
    expect(existsSync(file)).toBe(true)
    expect(readFileSync(file, 'utf8')).toMatch(/^<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg" viewBox="0 0 64 64"/)
  })
})
