# AGENTS.md

Gazette familiale par e-mail : API Symfony 8 (PHP 8.5, PHP-FPM) + front
React/Vite/Tailwind v4 dans `assets/`. Commencer par `docs/etat.md` (où on en
est, ce qui reste, ce qui a été écarté), puis `docs/architecture.md` avant
toute modification de structure. Conception et valeurs réelles (noms,
domaine, bucket) : page « Gazette familiale (spécification v1) » du wiki du
homelab, jamais ici.

## Règles du projet

- **TDD** : écrire d'abord le test qui échoue, puis le code. Un correctif de bug
  commence par un test qui reproduit le bug. Pas de PR sans test.
- **Dépendances au strict nécessaire** : pas de bundle tiers, pas de lib qu'on
  remplacerait par quelques dizaines de lignes. Composants Symfony de base OK.
  Toute nouvelle dépendance se justifie dans la PR et dans `docs/architecture.md`.
- **Aucune donnée personnelle dans le dépôt** : l'image est publique. Le nom de
  la gazette, l'adresse d'Immich et les clés viennent de l'environnement
  (`.env.local` en dev, rôle Ansible `service-gazette` en prod). Les tests
  utilisent `tests/Http/FakeImmich.php`, fictif.
- **Pas d'IA dans l'appli** : le bouton « Copier le prompt » produit un texte à
  coller dans ChatGPT ou Claude, c'est tout. Pas d'appel d'API payante.
- **Versions épinglées** (`.npmrc` save-exact, tags d'images exacts) : Renovate
  les met à jour.
- **Textes en français** (messages d'erreur, interface, commentaires, docs).
- **Tenir la doc à jour** dans la même PR : `docs/architecture.md` pour tout
  changement de structure ou d'API, `docs/depannage.md` pour toute panne dont
  la cause n'était pas évidente, `docs/etat.md` à chaque étape franchie
  (avec la date). La page wiki de spécification suit aussi, via le skill
  `bookstack-doc` quand il est disponible.

## Commandes

Mise en route du dev (`.env.local`, clé Immich) : `docs/developpement.md`. Le
dev lit le vrai Immich **en lecture seule** ; ne jamais y écrire.

Tout passe par Docker (`make help`). Exemples :

```bash
make test-php ARGS="--filter Prompt"
make test-front ARGS="editor/PhotoPicker"
make lint
docker compose run --rm gazette bin/console debug:router
```

## Tester l'API à la main

Les écritures exigent un en-tête de même origine, sinon 403 :

```bash
curl -s -X POST -H 'Sec-Fetch-Site: same-origin' http://localhost:8090/api/issues/<id>/prepare
```

Une réponse HTML au lieu de JSON = erreur fatale PHP avant Symfony (souvent
`.env.local` mal formé, voir `docs/depannage.md`). Pour interroger Listmonk
directement, lire le jeton depuis `.env.local` dans une variable sans jamais
l'afficher (`Authorization: token <user>:<jeton>`). En zsh, une variable non
quotée n'est pas découpée en mots : passer chaque argument de `curl`
explicitement, pas via une chaîne.

## Conventions

- Attributs PHP pour le framework (`#[Route]`, `#[AsEventListener]`,
  `#[Autowire]`), autowiring partout, pas de YAML de service sauf
  `config/services_test.yaml`.
- Contrôleurs minces : la logique dans `src/Gazette` (modèle, stockage, prompt,
  rendu) et `src/Source/Immich` (client). Modèle en `final readonly`,
  modifications par copie (`withX()`), persistance par `IssueRepository::change()`
  sous verrou.
- Les écritures passent par le trait `SameSiteWrites` (pas de mot de passe,
  mais pas de CSRF non plus).
- Les types TypeScript de `assets/http/types.ts` reflètent les tableaux
  renvoyés par les contrôleurs (`IssueView`) : modifier les deux ensemble.
- Aucun test ne sort sur le réseau : `tests/Http/FakeImmich.php` (PHP) et
  `fetch` simulé (vitest).
- Style PHP : `@Symfony` (php-cs-fixer non installé : suivre le style existant).

## Découvrir plutôt que deviner

`bin/console about`, `debug:router`, `debug:container`, `debug:autowiring`, et
les sources dans `vendor/`. Les API de Symfony 8, React 19, Vite 8, Vitest 5 et
TypeScript 7 ont pu changer depuis tes connaissances.
