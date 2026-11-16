# État du projet et suite

À lire en premier pour reprendre le travail. Dates en absolu. Les valeurs
réelles (nom de la gazette, prénoms, domaine d'expédition, nom du bucket,
adresses) ne sont **jamais** dans ce dépôt public : elles sont dans le wiki du
homelab (page « Gazette familiale (spécification v1) »), dans `.env.local` en
dev et dans le vault Ansible en prod.

## Fait

| Quoi | Quand | Preuve |
|---|---|---|
| Spécification v1 (périmètre, flux, Listmonk + B2, pas d'IA par API) | 2026-11-07 | page wiki |
| Rédaction : numéros, rubriques, photos Immich (période, album, personne), prompt à coller, aperçu | 2026-11-07 | `make test`, testé sur le vrai Immich |
| Envoi : photos recompressées déposées dans Listmonk, campagne, test, envoi réel, panneau « Envoi » | 2026-11-07 | 54 tests PHP, 24 tests front, Listmonk fictif |
| Côté Listmonk : utilisateur d'API et son rôle, liste, template (`gazette:listmonk-setup`), médiathèque sur un bucket S3 public (Backblaze B2) | 2026-11-08 | mail de test reçu avec les photos dans Gmail, Mail macOS, Thunderbird |
| Expéditeur sur un domaine authentifié dans Brevo (DKIM + DMARC) | 2026-11-08 | expéditeur non réécrit dans le mail reçu |
| Carnet du mois (notes datées par numéro, seul endroit des notes : plus de notes par rubrique), repris dans le prompt des rubriques et dans un prompt « premier jet » de tout le numéro | 2026-11-16 | 61 tests PHP, 33 tests front |

Chaîne complète validée le 2026-11-08 depuis l'environnement de dev :
rédaction → préparation (photos sur B2) → test → réception. L'envoi réel à la
liste n'a pas encore été cliqué ; la liste ne contient que l'adresse de test.

## Reste à faire, dans l'ordre

1. **Dépôt GitHub** `Heyfara/homelab-gazette` (public) : à créer par
   l'utilisateur (`gh repo create … --source . --push`), puis vérifier que la
   CI (`.github/workflows`) publie l'image sur un tag.
2. **Clé d'API Immich dédiée** « Gazette » avec `person.read` (en dev, la
   clé du dashboard sert et le filtre par personne répond 502).
3. **Rôle Ansible `service-gazette`** dans `homelab-ansible`, sur le modèle de
   `service-dashboard` : conteneur sur helium derrière le nginx partagé,
   route Caddy `gazette.home`, entrée DNS Pi-hole (krypton), certificat mkcert
   régénéré, sauvegarde nocturne de `GAZETTE_DATA_DIR` vers argon (Dagu
   `backup-nuit`), garde-fou CD seulement après le premier déploiement.
   Variables à fournir (voir « Configuration » dans `docs/architecture.md`) ;
   secrets dans le vault : jeton Listmonk, clé Immich, `GAZETTE_FAMILY`.
4. **Premier vrai numéro** : abonner les destinataires à la liste (prénom ou
   surnom dans le nom), envoyer, puis créer la page Services « Gazette » du
   wiki (partie « Utiliser » pas à pas pour les rédacteurs) et rayer la ligne
   des Chantiers ouverts.

## Améliorations possibles (non demandées)

- Repasser un numéro en `brouillon` quand on le modifie après un test, pour
  forcer un nouveau test avant l'envoi réel.
- Dupliquer un numéro envoyé pour en commencer un nouveau avec les mêmes
  rubriques.

## Idées écartées ou remises à plus tard (ne pas rouvrir sans l'utilisateur)

- PDF, impression, vidéos, site public : hors v1.
- IA par API (payante) ou Ollama : refusé. Le prompt à coller suffit.
- Comptes et mots de passe : non, LAN/VPN seulement, un espace partagé.
- Templates de mail dans l'appli : non, c'est le template Listmonk qui porte
  la mise en page.
