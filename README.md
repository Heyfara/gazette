# homelab-gazette

Gazette familiale par e-mail, dans l'esprit de Famileo : on choisit des photos
dans Immich, on écrit un petit texte par rubrique (à la main, ou en collant un
prompt dans ChatGPT ou Claude), et le numéro part par e-mail aux grands-parents
via Listmonk.

- **Back** : Symfony 8 en API JSON seule, PHP-FPM. Les numéros sont des
  fichiers JSON (`GAZETTE_DATA_DIR`), pas de base de données.
- **Front** : React + Vite + Tailwind v4, dans `assets/`.
- **Sources** : Immich (photos, albums, personnes), Listmonk (envoi, à venir).
- **Prod** : image `ghcr.io/heyfara/homelab-gazette`, derrière le nginx
  partagé du serveur, lui-même derrière Caddy (rôle Ansible `service-gazette`).

## Démarrer

```bash
make install   # composer install + npm ci (dans Docker)
make up        # front sur http://localhost:5174, API sur http://localhost:8090/api/issues
make test      # tous les tests
make help      # toutes les commandes
```

Le dev lit le vrai Immich en lecture seule : `.env.local` à préparer une fois,
voir [docs/developpement.md](docs/developpement.md).

## Documentation

| Document | Pour |
|---|---|
| [docs/developpement.md](docs/developpement.md) | mettre en route l'environnement de dev |
| [docs/architecture.md](docs/architecture.md) | comprendre le découpage, l'API, les choix |
| [docs/depannage.md](docs/depannage.md) | une photo qui ne s'affiche pas, Immich qui refuse la clé |
| [AGENTS.md](AGENTS.md) | règles du projet pour les agents IA (et les humains) |

La conception (périmètre, flux d'envoi, Listmonk et Backblaze B2) est dans le
wiki du homelab, page « Gazette familiale (spécification v1) ».

## Livrer une version

1. Tests verts sur `main` (CI).
2. `git tag v0.1.0 && git push --tags` : GitHub Actions publie l'image.
3. Renovate ouvre la PR de montée de version dans `homelab-ansible` ; la merger déploie.
