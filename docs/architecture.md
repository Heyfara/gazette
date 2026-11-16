# Architecture

Une API Symfony sans état et un front React. L'appli n'a ni compte ni mot de
passe : elle n'est joignable que sur le LAN (ou en VPN), et deux personnes
rédigent dans le même espace.

## Découpage

```
src/
  Gazette/            le métier
    Model/            Issue (numéro), Section (rubrique), Note (carnet du mois), Photo, IssueStatus — final readonly, copies par withX()
    IssueRepository   un fichier JSON par numéro, modifications sous verrou (change())
    SectionPhotos     la liste de photos envoyée par le front → Photo[] (métadonnées lues dans Immich une fois)
    Payload           lecture et vérification d'un corps JSON → 422 avec la liste des refus
    Prompt/           PromptBuilder : prompt d'une rubrique (config/prompt.txt) et premier jet du numéro (config/prompt-numero.txt), remplis avec le numéro, la famille (GAZETTE_FAMILY), le carnet, les rubriques, les photos
    Html/             IssueHtmlRenderer : corps HTML du numéro (tableaux, styles en ligne) et page d'aperçu
  Source/Immich/      ImmichClient (API 3.x) et ses objets : albums, recherche (curseur), personnes, miniatures
  Controller/         un contrôleur par ressource, IssueView = ce que lit le front
  EventListener/      ApiExceptionListener : sous /api, toute erreur est du JSON { error, details? }
  Storage/JsonFiles   fichiers JSON privés réécrits par renommage atomique sous verrou (repris du dashboard)
assets/
  App.tsx             deux écrans : / (numéros) et /numeros/:id (rédaction)
  pages/              IssueListPage, IssueEditorPage (état du numéro, aperçu)
  editor/             IssueHeader, NotebookPanel (carnet du mois), SectionEditor, PhotoStrip, PhotoPicker, PromptButton, PreviewPane, SaveIndicator
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

Le **carnet du mois** (`notes` du numéro) est le seul endroit des notes : des
notes datées (AAAA-MM-JJ), prises au fil du mois (« on a accueilli les
nouvelles poules »), rangées par date (même jour : ordre d'ajout). Elles ne
vont jamais dans le mail : le prompt de chaque rubrique les reprend toutes
(l'IA garde ce qui la concerne), et le prompt du premier jet s'en sert pour
proposer tout le numéro. Les rubriques n'ont plus de notes à elles (retirées
le 2026-11-16) : celles des anciens fichiers passent dans le carnet à la
lecture, au 1er du mois, préfixées du titre de la rubrique.

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
| PATCH, DELETE | `/api/issues/{id}/sections/{sid}` | titre, texte, `photos: [{assetId, caption}]` |
| PUT | `/api/issues/{id}/sections-order` | `{ order: [sid…] }` |
| GET | `/api/issues/{id}/sections/{sid}/prompt` | le prompt à coller |
| POST | `/api/issues/{id}/notes` | note du carnet : `text`, `date` (défaut : aujourd'hui, heure de Paris) ; 200 notes au plus |
| PATCH, DELETE | `/api/issues/{id}/notes/{nid}` | texte, date ; suppression |
| GET | `/api/issues/{id}/prompt` | le prompt du premier jet de tout le numéro (carnet + rubriques commencées) |
| GET | `/api/issues/{id}/preview` | page HTML du numéro, photos relayées |
| POST | `/api/issues/{id}/prepare` | photos recompressées et déposées dans Listmonk, campagne créée ou mise à jour (idempotent) |
| GET | `/api/issues/{id}/mail-preview` | le mail rendu par Listmonk, template compris (prépare au passage) |
| POST | `/api/issues/{id}/test` | envoi de test (`GAZETTE_TEST_EMAILS`) ; statut `teste` |
| POST | `/api/issues/{id}/send` | envoi à la liste, exige un test avant ; statut `envoye`, numéro figé |
| GET | `/api/issues/{id}/campaign` | état de la campagne Listmonk (`null` sans campagne) |
| GET | `/api/immich/albums`, `/api/immich/people` | listes Immich |
| GET | `/api/immich/search?album=…` ou `?from=&to=`, `&person=`, `&cursor=` | photos (60 par page, `nextCursor`) |
| GET | `/api/immich/assets/{id}/{thumbnail\|preview}`, `/api/immich/people/{id}/thumbnail` | images relayées, cache 24 h |

Les écritures sont refusées quand elles viennent d'un autre site
(`SameSiteWrites` : `Sec-Fetch-Site`, `Origin`), et exigent un corps JSON.
Un numéro envoyé (`status: envoye`) répond 409 à toute modification, comme un
numéro qui ne peut pas partir (`NotSendableException` : vide, pas de test,
liste ou template Listmonk absents).

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

## Envoi (Listmonk)

`IssueSender` : `prepare()` lit la preview Immich de chaque photo sans
`mediaUrl`, la recompresse (`PhotoCompressor`, GD, 1200 px, ~250 Ko), la
dépose dans la médiathèque Listmonk sous un nom non devinable et garde
l'adresse publique dans la photo ; puis crée la campagne (liste
`LISTMONK_LIST`, template `LISTMONK_TEMPLATE`, sujet, `Reply-To`) ou la met à
jour avec le corps HTML (`IssueHtmlRenderer::body()`, images = adresses
Listmonk). `test()` et `send()` commencent par `prepare()` : le mail part
toujours à jour. Après l'envoi réel, le numéro est figé ; les photos restent
dans la médiathèque (archive).

Le template de campagne (`listmonk/template-gazette.html`) porte toute la mise
en page du mail et se retouche dans Listmonk ; `gazette:listmonk-setup` ne le
crée que s'il manque.

Parcours dans le panneau « Envoi » (`assets/editor/SendPanel.tsx`) et
statuts (`IssueStatus`) :

1. **Préparer l'envoi** / **Mettre à jour la campagne** → `prepare` ; à refaire
   après toute modification (le test et l'envoi le refont d'eux-mêmes).
2. **Envoyer un test** → `test` ; statut `brouillon` → `teste`. Une
   modification ultérieure ne repasse **pas** le numéro en `brouillon`
   (choix v1 : l'envoi refait la préparation, le mail part donc à jour, mais
   sans nouveau test).
3. **Envoyer à la famille** (grisé sans test, confirmation) → `send` ;
   statut `envoye`, `sentAt`, numéro figé (409 sur toute écriture).

Seules les photos sans `mediaUrl` sont déposées : changer de médiathèque
(disque → S3) ne redéplace pas celles déjà déposées (voir `docs/depannage.md`).

## Configuration

| Variable | Rôle | Défaut |
|---|---|---|
| `GAZETTE_NAME` | nom par défaut des numéros | — |
| `GAZETTE_FAMILY` | phrase présentant la famille, injectée dans le prompt | — |
| `GAZETTE_DATA_DIR` | dossier des numéros | `var/data` |
| `GAZETTE_PROMPT_FILE` | gabarit du prompt d'une rubrique | `config/prompt.txt` |
| `GAZETTE_DRAFT_PROMPT_FILE` | gabarit du prompt du premier jet | `config/prompt-numero.txt` |
| `IMMICH_URL`, `IMMICH_API_KEY`, `TLS_CA_FILE` | Immich (lecture seule) | — |
| `LISTMONK_URL`, `LISTMONK_API_USER`, `LISTMONK_API_TOKEN` | API Listmonk ; vides = envoi désactivé, le reste marche | — |
| `LISTMONK_LIST`, `LISTMONK_TEMPLATE` | noms de la liste et du template | `Famille`, `Gazette` |
| `GAZETTE_FROM` | expéditeur `Nom <adresse>` sur un domaine authentifié dans Brevo ; vide = celui de Listmonk | — |
| `GAZETTE_REPLY_TO` | adresse qui reçoit les réponses | — |
| `GAZETTE_TEST_EMAILS` | adresses de test (abonnés Listmonk), séparées par des virgules | — |

## Dépendances

- `symfony/http-client` : Immich et Listmonk ; `symfony/mime` : le dépôt multipart des photos dans la médiathèque Listmonk.
- `symfony/yaml` : lecture de la configuration du framework.
- `ext-gd` : recompression des photos avant l'envoi (`PhotoCompressor`).
- Front : React, Vite, Tailwind, Testing Library, vitest. Rien d'autre.

