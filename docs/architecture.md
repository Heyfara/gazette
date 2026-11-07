# Architecture

Une API Symfony sans état et un front React. L'appli n'a ni compte ni mot de
passe : elle n'est joignable que sur le LAN (ou en VPN), et deux personnes
rédigent dans le même espace.

## Découpage

```
src/
  Gazette/            le métier
    Model/            Issue (numéro), Section (rubrique), Photo, IssueStatus — final readonly, copies par withX()
    IssueRepository   un fichier JSON par numéro, modifications sous verrou (change())
    SectionPhotos     la liste de photos envoyée par le front → Photo[] (métadonnées lues dans Immich une fois)
    Payload           lecture et vérification d'un corps JSON → 422 avec la liste des refus
    Prompt/           PromptBuilder : config/prompt.txt rempli avec le numéro, la famille (GAZETTE_FAMILY), la rubrique, les photos, les notes
    Html/             IssueHtmlRenderer : corps HTML du numéro (tableaux, styles en ligne) et page d'aperçu
  Source/Immich/      ImmichClient (API 3.x) et ses objets : albums, recherche (curseur), personnes, miniatures
  Controller/         un contrôleur par ressource, IssueView = ce que lit le front
  EventListener/      ApiExceptionListener : sous /api, toute erreur est du JSON { error, details? }
  Storage/JsonFiles   fichiers JSON privés réécrits par renommage atomique sous verrou (repris du dashboard)
assets/
  App.tsx             deux écrans : / (numéros) et /numeros/:id (rédaction)
  pages/              IssueListPage, IssueEditorPage (état du numéro, aperçu)
  editor/             IssueHeader, SectionEditor, PhotoStrip, PhotoPicker, PromptButton, PreviewPane, SaveIndicator
  hooks/              usePath (routage minimal), useJson (une requête), useAutosave (enregistrement différé)
  http/               client.ts (les appels) et types.ts (ce que renvoie l'API)
  ui/                 styles partagés, formats (mois, dates), ErrorMessage
```

## Données

Pas de base de données : un numéro est un document (en-tête + rubriques +
photos) toujours lu et écrit en entier, quelques-uns par an. Chaque numéro est
un fichier `GAZETTE_DATA_DIR/<id>.json` (id : 8 caractères hexadécimaux),
plus `compteur.json` pour la numérotation. Sauvegarder = copier le dossier.

Toute modification passe par `IssueRepository::change(id, fn)` : lecture,
modification, réécriture sous verrou (`flock`), pour que deux rédacteurs qui
enregistrent au même moment ne s'écrasent pas. Entre deux enregistrements, le
dernier écrit gagne (pas de détection de conflit, voulu en v1).

Une photo copie à l'ajout ce qu'Immich sait d'elle (date, ville, pays,
personnes nommées) : le prompt n'a plus besoin d'Immich ensuite, et un
numéro reste lisible si une photo est supprimée d'Immich.

## API

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/config` | nom par défaut, nombre de photos conseillé |
| GET, POST | `/api/issues` | les numéros ; création (`month`, `name`, `title`) |
| GET, PATCH, DELETE | `/api/issues/{id}` | un numéro ; en-tête ; suppression (brouillon seulement) |
| POST | `/api/issues/{id}/sections` | nouvelle rubrique |
| PATCH, DELETE | `/api/issues/{id}/sections/{sid}` | titre, notes, texte, `photos: [{assetId, caption}]` |
| PUT | `/api/issues/{id}/sections-order` | `{ order: [sid…] }` |
| GET | `/api/issues/{id}/sections/{sid}/prompt` | le prompt à coller |
| GET | `/api/issues/{id}/preview` | page HTML du numéro, photos relayées |
| GET | `/api/immich/albums`, `/api/immich/people` | listes Immich |
| GET | `/api/immich/search?album=…` ou `?from=&to=`, `&person=`, `&cursor=` | photos (60 par page, `nextCursor`) |
| GET | `/api/immich/assets/{id}/{thumbnail\|preview}`, `/api/immich/people/{id}/thumbnail` | images relayées, cache 24 h |

Les écritures sont refusées quand elles viennent d'un autre site
(`SameSiteWrites` : `Sec-Fetch-Site`, `Origin`), et exigent un corps JSON.
Un numéro envoyé (`status: envoye`) répond 409 à toute modification.

## Immich

API 3.x. La recherche utilise le filtre récent (`filter.takenAt`,
`filter.albumIds.any`, `filter.personIds.all`, `orderBy`) et sa pagination par
**curseur** (`nextCursor` → `cursor`) : le champ `page` est refusé avec
`filter`. `localDateTime` est l'heure de l'appareil marquée « Z » à tort : on
la garde telle quelle, sans fuseau. Les visages sans nom sont ignorés.

Les miniatures exigent la clé : l'appli les relaie (`ImmichController`) et
les garde 24 h dans le pool `cache.images` (disque). En prod, l'appli joint
Immich comme le dashboard : par son nom `.home`, avec la CA mkcert
(`TLS_CA_FILE`) et le DNS du homelab.

## Dépendances

- `symfony/http-client` : Immich (et Listmonk à venir).
- `symfony/yaml` : lecture de la configuration du framework.
- `ext-gd` : recompression des photos avant l'envoi (à venir).
- Front : React, Vite, Tailwind, Testing Library, vitest. Rien d'autre.

## À venir (spec v1)

Envoi par Listmonk : dépôt des photos recompressées dans sa médiathèque
(stockée sur un bucket B2 public), création de la campagne avec le corps HTML
(`IssueHtmlRenderer::body()`, adresse des images = URL B2), envoi test puis
réel ; passage du numéro en `teste` puis `envoye`.
