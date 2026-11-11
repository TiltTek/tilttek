<!-- phil-dev source-sha256=d0ac123c350df218025fe32aa10330fa18dc20d5fdf17346067b19edaa7cd4da -->
# Écrire un journal

Une application qui garde son journal hors du bundle implémente `Tilttek\Activity\Log\ActivityLog` sur son stockage.
Le contrat :

| Méthode | Doit |
|---|---|
| `append($activity, $at)` | Écrire l'activité une seule fois par clé d'idempotence, même quand deux processus l'écrivent au même moment ; renvoyer l'entrée gardée par le stockage et indiquer si cet appel l'a écrite ; lever `IdempotencyConflict` quand la clé porte un autre geste (`Activity::sameGestureAs()`) |
| `cancel($key, $at)` | Écrire l'annulation une seule fois par clé, avec l'activité enregistrée ; la renvoyer et indiquer si cet appel l'a écrite ; renvoyer null quand aucune activité n'est enregistrée sous la clé |
| `find($key)`, `isCancelled($key)` | Trouver l'entrée enregistrée d'une clé ; indiquer si elle est annulée |
| `read($after, $limit, $filter)` | Renvoyer les entrées correspondantes situées après une position, dans l'ordre, au plus `limit` ; vérifier les bornes avec `Reading::check()` |
| `lastPosition()` | Renvoyer la position de la dernière entrée gardée, 0 s'il n'y en a aucune |
| `totals($filter)` | Compter les activités enregistrées correspondantes qui ne sont pas annulées, et additionner leurs quantités |
| `eraseMember($member)` | Supprimer les entrées du membre, l'oublier comme objet de celles des autres, et indiquer le nombre de chaque sorte |

Les positions ne font que croître et ne sont jamais données deux fois. Le cas de test de la bibliothèque,
`ActivityLogTestCase` dans son dossier `tests/`, énonce le contrat sous forme de tests : copiez-le à côté de votre
implémentation et exécutez-le contre elle.
