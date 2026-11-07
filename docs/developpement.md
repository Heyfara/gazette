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
IMMICH_URL=https://immich.example
IMMICH_API_KEY=…
TLS_CA_FILE=/certs/mkcert-rootCA.pem
```

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

Le prompt se règle dans `config/prompt.txt` (ou le fichier donné par
`GAZETTE_PROMPT_FILE`), relu à chaque clic : pas besoin de redémarrer.
