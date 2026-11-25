<!-- phil-dev source-sha256=d577c18f693f0f94fb21d8b8c149c17c5284a10c9c3f31bb1778cbf770d9418a -->
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

| Stockage | Export | Effacement | Pourquoi |
|---|---|---|---|
| `tilttek_table_safety_participation` | les réponses à chaque table | supprimées | Les réponses disent ce que le participant ne jouera pas et jusqu'où il consent : les données les plus sensibles que détient le bundle. L'accord de la table est recalculé à partir des réponses restantes. |
| `tilttek_table_safety_feedback` | les étoiles et souhaits de chaque séance | supprimés | Le sommaire d'une séance est recalculé à partir des étoiles et souhaits restants, par groupes entiers de `feedback_threshold`. |
| `tilttek_table_safety_signal` | les signaux nominatifs qu'il a levés | caviardés : gardés sans le nom | Un signal sert au meneur de jeu pendant la partie — un signal pas encore vu, ou les réponses au dernier point de contrôle, seraient perdus pour la table s'il disparaissait — et la purge le supprime après `retention`. Son auteur est effacé, si bien qu'il devient un signal anonyme ; le rapport dit `redacted` plutôt que `anonymised`, puisque sa table, son genre et son moment peuvent encore désigner le participant à une petite table jusqu'à la purge. |

Les réglages d'une table, `tilttek_table_safety_table`, ne nomment personne et ne sont pas dans le rapport.
L'effacement peut s'exécuter de nouveau sur un participant effacé et rapporte la même chose.

## La purge des signaux

Un signal sert pendant la partie. `tilttek:table-safety:purge-signals` supprime ceux qui sont plus vieux que `retention`
(30 jours par défaut), ou que la conservation qu'on lui donne :

```sh
php bin/console tilttek:table-safety:purge-signals
php bin/console tilttek:table-safety:purge-signals --retention="7 days"
```

Lancez-la depuis un cron ou Symfony Scheduler.
