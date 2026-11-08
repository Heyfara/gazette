# Développement

L'environnement de dev tourne dans Docker (`compose.yaml`) et lit le **vrai
Immich en lecture seule**. Rien n'est écrit dans Immich : l'appli ne fait que
lire les albums, les photos et les personnes, et relayer les miniatures.

## Une fois

### 1. Clé d'API Immich

Dans Immich, *Paramètres du compte → Clés d'API → Nouvelle clé*, nommée
« Gazette », avec les seules permissions :

- `album.read` (liste des albums),
- `asset.read` et `asset.view` (recherche, métadonnées, miniatures),
- `person.read` (personnes nommées, pour le filtre et le prompt).

La clé voit les photos de son propriétaire et les albums partagés avec lui.
Sans `person.read`, tout marche sauf le filtre par personne (message d'erreur
dans le sélecteur) et les prénoms dans le prompt.

### 2. `.env.local` (non versionné)

```dotenv
GAZETTE_NAME="La gazette de la famille Exemple"
GAZETTE_FAMILY="Alice (la maman), Bob (le papa), Léo et Emma (les enfants)"
IMMICH_URL=https://immich.example
IMMICH_API_KEY=…
TLS_CA_FILE=/certs/mkcert-rootCA.pem

# Envoi (facultatif en dev : sans ces lignes, le panneau « Envoi » le dit et le reste marche)
LISTMONK_URL=http://10.0.0.2:9000      # l'API Listmonk, publiée sur le LAN
LISTMONK_API_USER=gazette
LISTMONK_API_TOKEN=…
GAZETTE_FROM="La gazette <gazette@exemple.fr>"   # expéditeur validé côté SMTP ; vide = celui de Listmonk
GAZETTE_REPLY_TO=famille@exemple.fr
GAZETTE_TEST_EMAILS=moi@exemple.fr               # abonnés connus de Listmonk
```

### 2 bis. Côté Listmonk

1. **Utilisateur d'API** `gazette` (*Users → User roles* puis *Users → New*,
   type *API user*), avec un rôle limité à : `lists:get_all`, `templates:get`,
   `templates:manage`, `media:get`, `media:manage`, `campaigns:get_all`,
   `campaigns:manage_all`, `campaigns:send`. Ajouter `lists:manage_all`
   seulement si la commande de l'étape 3 doit créer la liste. Le jeton va dans
   `LISTMONK_API_TOKEN` (il n'est affiché qu'une fois).
2. **Médiathèque sur un stockage public** (*Settings → Media uploads*,
   fournisseur S3, bucket public) : sinon les photos des mails pointent vers
   un Listmonk que les destinataires ne joignent pas.
3. `docker compose run --rm gazette bin/console gazette:listmonk-setup` crée la
   liste `LISTMONK_LIST` et le template `LISTMONK_TEMPLATE`
   (`listmonk/template-gazette.html`) s'ils manquent. Ajouter ensuite les
   destinataires à la liste, prénom compris, dont les adresses de test.

`compose.yaml` monte la CA mkcert du poste (`~/.local/share/mkcert/rootCA.pem`,
ou `$MKCERT_CAROOT/rootCA.pem`) sur `/certs/mkcert-rootCA.pem`. Sans ce
fichier, le container refuse de démarrer.

### 3. `compose.override.yaml` (non versionné) : DNS du homelab

Sous Docker Desktop, le relais DNS de la VM résout mal les noms locaux. Donner
le résolveur du LAN en direct au container :

```yaml
services:
  gazette:
    dns: [10.0.0.35]
```

## Au quotidien

```bash
make up          # http://localhost:5174 (front, rechargement à chaud)
make test        # tests PHP + front
make lint        # conteneur Symfony, YAML
make down
```

Les numéros créés en dev vont dans `var/data/` (ignoré par git). Les tests
utilisent `var/test-data/`, vidé à chaque test, et l'Immich fictif de
`tests/Http/FakeImmich.php` : ils ne sortent jamais sur le réseau.

Le prompt se règle dans `config/prompt.txt` (les prénoms viennent de `GAZETTE_FAMILY`, jamais du gabarit) (ou le fichier donné par
`GAZETTE_PROMPT_FILE`), relu à chaque clic : pas besoin de redémarrer.
