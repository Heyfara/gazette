# Dépannage

## « Clé Immich refusée (403) » sur le filtre par personne

La clé n'a pas la permission `person.read`. Le reste (albums, recherche,
miniatures) marche sans. Créer une nouvelle clé avec les quatre permissions
(voir `docs/developpement.md`) : Immich ne permet pas d'en ajouter à une clé
existante.

## « Immich injoignable : HTTP/2 400 » sur une recherche

Immich 3 refuse le corps de la requête. Cause connue : le champ `page`
combiné au `filter` (« Deprecated field page cannot be combined with
filter ») ; la pagination se fait par curseur. Reproduire avec `curl` sur
`/api/search/metadata` pour lire le message de validation, que l'appli ne
remonte pas.

## Les miniatures ne s'affichent pas en dev

- `TLS_CA_FILE` absent ou CA mkcert non montée : le container refuse le
  certificat d'Immich (`Immich injoignable : SSL…`).
- Noms `.home` non résolus : voir `compose.override.yaml` dans
  `docs/developpement.md`.

## Une modification n'est pas enregistrée

L'indicateur « Non enregistré : … » à côté de la rubrique donne la raison
(422 : champ trop long, mois mal formé ; 409 : numéro déjà envoyé ; 502 :
Immich injoignable à l'ajout d'une photo). Les champs texte s'enregistrent
0,8 s après la dernière frappe ; fermer l'onglet avant perd la frappe en cours.

## « Listmonk a refusé (400) : no subscribers found » à l'envoi de test

Les adresses de `GAZETTE_TEST_EMAILS` doivent être des abonnés de Listmonk
(n'importe quelle liste). Les ajouter dans *Subscribers*.

## Les photos du mail ne s'affichent pas chez les destinataires

Regarder l'adresse d'une image dans le mail reçu : si elle commence par celle
de Listmonk (`…/uploads/…`), la médiathèque est encore sur le disque du
serveur, injoignable d'Internet. Passer *Settings → Media uploads* sur le
stockage S3 public, puis re-préparer le numéro : seules les photos sans
adresse sont déposées, donc retirer et remettre celles déjà déposées, ou
repartir d'un numéro dupliqué.

## Le prompt n'inclut pas le dernier titre de rubrique

Le bouton attend la fin de l'enregistrement (grisé pendant « Modifié » et
« Enregistrement… »). Si le titre vient d'être tapé, patienter une seconde.
Les notes du carnet, elles, sont enregistrées dès « Noter ».

## L'API répond une page HTML au lieu de JSON (« Expecting value » côté client)

Erreur fatale avant Symfony. Cas vu le 2026-11-08 : des lignes sans `=`
collées dans `.env.local` (`FormatException: Missing = in the environment
variable declaration`). `docker compose logs gazette` donne la ligne. Ce
fichier n'accepte que `CLE=valeur` et des commentaires `#`.

## « Jeton Listmonk refusé (403) » alors que les lectures marchent

Le jeton est bon mais le rôle n'a pas le droit demandé par cet appel :
`lists:manage_all` pour créer la liste (volontairement absent du rôle
conseillé : créer la liste à la main), `campaigns:send` pour démarrer
l'envoi, `media:manage` pour déposer une photo. Les droits attendus sont
dans `docs/developpement.md`. Vérifier le rôle effectif avec
`GET /api/profile` de Listmonk.

## L'expéditeur reçu est `…@<id>.brevosend.com`

Brevo réécrit tout expéditeur dont le domaine n'est pas authentifié chez
lui, sans erreur. Voir « Expéditeur (Brevo) » dans `docs/developpement.md`.

## Changer de médiathèque Listmonk après un premier dépôt

Les photos gardent leur ancienne adresse (`mediaUrl`) et ne sont pas
redéposées. Supprimer les médias dans Listmonk (`DELETE /api/media/{id}`),
vider `mediaUrl` et `mediaId` dans le JSON du numéro (`GAZETTE_DATA_DIR`),
puis re-préparer. En dev sous Docker Desktop, réécrire le fichier en place
(pas de renommage) ou faire un `ls` du dossier dans le container, sinon il
voit l'ancien fichier.
