<!-- phil-dev source-sha256=816ae0873224a35c73b27d37794c5720a15baf76644b5835b05cc13d0deec0d4 -->
# Vie privée

## Ce qui reste anonyme

Les écrans montrent ce que la table a convenu, son pacte, son décompte de points d'étape et le sommaire des étoiles et
souhaits d'une séance, dont aucun ne dit qui a donné quelle réponse. Les étoiles et les souhaits sont montrés par
groupes entiers de `feedback_threshold` participants, 3 par défaut : en dessous, le meneur lit seulement qu'il n'y a pas
encore assez de réponses. Les joueurs voient ce que la table a convenu et le pacte par groupes entiers du seuil de
réponses de la bibliothèque, 3 par défaut ; le meneur voit les lignes, les voiles et la liste dès la première réponse,
toujours sans leurs auteurs, pour les garder hors de la partie. Un signal anonyme n'a pas d'auteur dans la base de données, sur les écrans ni sur le hub. Les
réponses d'un participant ne sont montrées qu'à lui. Les cartes imprimables sont vierges : elles listent les thèmes de
la table et n'impriment les réponses de personne.

## Export et effacement

Avec `phil/privacy-bundle` installé, `TableSafetyPrivacy` est étiqueté comme exportateur et comme effaceur, sur les
contrats de `Phil\PrivacyBundle\Application\Contract`. La personne concernée est un participant, référencé sous
`privacy.participant_kind` (`member` par défaut).

| Stockage | Export | Effacement |
|---|---|---|
| `tilttek_table_safety_participation` | les réponses à chaque table | supprimées |
| `tilttek_table_safety_feedback` | les étoiles et souhaits de chaque séance | supprimés |
| `tilttek_table_safety_signal` | les signaux nominatifs qu'il a levés | gardés sans le nom, comme signaux anonymes |

L'effacement peut s'exécuter de nouveau sur un participant effacé et rapporte la même chose.

## La purge des signaux

Un signal sert pendant la partie. `tilttek:table-safety:purge-signals` supprime ceux qui sont plus vieux que `retention`
(30 jours par défaut), ou que la conservation qu'on lui donne :

```sh
php bin/console tilttek:table-safety:purge-signals
php bin/console tilttek:table-safety:purge-signals --retention="7 days"
```

Lancez-la depuis un cron ou Symfony Scheduler.
