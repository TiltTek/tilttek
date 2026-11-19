<!-- phil-dev source-sha256=99c3d68efcd3d12ea0425f194357ae040f7786dabe3faf41daf5507f8a9bb05e -->
# Console

Les commandes sont enregistrées quand `symfony/console` est installé.

## `tilttek:advent-calendar:list`

Liste les calendriers publiés, ceux de la configuration et ceux de la base de données : leur identifiant, leur titre,
leur fuseau horaire, leur nombre de cases, leurs dates de début et de fermeture, et leur politique d'ouverture.

## `tilttek:advent-calendar:show <calendar>`

Montre les cases d'un calendrier publié : la date de chaque case, le premier et le dernier jour où elle peut être
ouverte, son état maintenant pour un membre qui ne l'a pas ouverte, et sa surprise. Elle ne lit les ouvertures d'aucun
membre.

```text
 Box   Date         First day    Last day     Now        Surprise
 3     2026-12-03   2026-12-03   2026-12-24   openable   points points × 10
```

## `tilttek:advent-calendar:check`

Vérifie chaque calendrier de la base de données, publié ou non, tel que la bibliothèque le lit, et nomme ceux dont les
champs ne tiennent plus ensemble, avec la raison. Elle échoue quand l'un d'eux est dans ce cas, afin qu'un déploiement
ou une tâche planifiée voie un calendrier cassé avant un membre. Elle est enregistrée avec l'ORM et le magasin
`doctrine`.
