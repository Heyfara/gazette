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

## Le prompt n'inclut pas les dernières notes

Le bouton attend la fin de l'enregistrement (grisé pendant « Modifié » et
« Enregistrement… »). Si les notes viennent d'être tapées, patienter une
seconde.
