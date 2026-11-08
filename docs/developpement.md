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
GAZETTE_FROM="La gazette <gazette@exemple.fr>"   # expéditeur sur un domaine authentifié dans Brevo (DKIM) ; une adresse gmail.com serait réécrite en @…brevosend.com ; vide = celui de Listmonk
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
2. **Médiathèque sur un stockage public** (*Settings → Media uploads*) :
   sinon les photos des mails pointent vers un Listmonk que les destinataires
   ne joignent pas. Avec un bucket Backblaze B2 **public** dédié (clé
   d'application limitée à ce bucket, lecture et écriture ; le chiffrement
   côté serveur imposé par B2 ne gêne pas l'accès public) :

   | Champ Listmonk | Valeur |
   |---|---|
   | Provider | S3 |
   | AWS access key / secret | keyID / applicationKey de la clé B2 |
   | Region | la région de l'endpoint, ex. `eu-central-003` |
   | Bucket | le nom du bucket (unique au monde chez B2) |
   | Bucket path | `/` |
   | Bucket type | Public |
   | Upload expiry | vide |
   | Custom public URL | `https://f<nnn>.backblazeb2.com/file/<bucket>` (`nnn` = numéro de la région) |
   | S3 backend URL | `https://s3.<région>.backblazeb2.com` |

   Vérifier en déposant une image dans *Media* : son lien doit s'ouvrir
   depuis un téléphone hors du LAN.
3. **Liste des destinataires** : la créer à la main (*Lists → New*, type
   private, opt-in single) et mettre son nom dans `LISTMONK_LIST` (défaut
   `Famille`). Y abonner les destinataires avec un prénom ou un surnom dans le
   nom (le template dit « Bonjour {prénom} »), dont les adresses de
   `GAZETTE_TEST_EMAILS`.
4. `docker compose run --rm gazette bin/console gazette:listmonk-setup` crée
   le template `LISTMONK_TEMPLATE` (`listmonk/template-gazette.html`) s'il
   manque, et la liste si elle manque et que le rôle a `lists:manage_all`.

### 2 ter. Expéditeur (Brevo)

Brevo réécrit l'expéditeur en `<nom>@<id>.brevosend.com` quand le domaine
n'est pas authentifié chez lui (toute adresse gmail.com, par exemple). Pour
un expéditeur propre : *Senders & domains → Domains*, ajouter un domaine
qu'on possède, poser chez le registrar le TXT `brevo-code` à la racine, les
deux CNAME `brevo1._domainkey` et `brevo2._domainkey`, et le TXT `_dmarc`
(`p=none` suffit), puis « Authenticate ». Ajouter ensuite un expéditeur sur
ce domaine (pas besoin de boîte mail : les réponses vont au `Reply-To`) et
le mettre dans `GAZETTE_FROM` sous la forme `Nom <adresse>`.

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
