<!-- phil-dev source-sha256=9f7c8ce4be776e86d428585651f8722e8030b87e7360b6669b63c492aa3b4af2 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les conditions | Implémentez `Contract\ConditionStore`, et passez-le à `Conditions`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\ConditionObserver`, et passez-le à `Conditions`. |
| Déclarer les conditions du système | Construisez un `ConditionCatalogue` de `ConditionDefinition`, et passez-le à `Conditions`. |
| Arrondir comme le système | Passez à `Conditions` un `Effect\EffectCalculator` avec son `Rounding`. |
| Donner l'heure | Passez une `ClockInterface` PSR-20. |
| Terminer les conditions en temps réel | Planifiez `Conditions::expire()` à `ConditionsView::$nextExpiry`. |
| Compter les tours | Appelez `startTurn()`, `endTurn()`, `startRound()` et `endRound()` depuis l'ordre des tours. |
| Dire qui peut appliquer ou retirer une condition | Vérifiez-le dans l'application avant d'appeler `Conditions`. |

## Le magasin

`Contract\ConditionStore` garde les conditions de chaque personnage, à une version :

- `conditions()` donne celles d'un personnage, ou null quand aucune n'a jamais été gardée ;
- `save()` les garde à la version suivante et les retourne. Des conditions à la version 0 sont neuves, et le magasin n'en
  a aucune pour le personnage ; toutes les autres ne sont gardées que si le magasin les détient à cette version, et
  `ConditionConflict` est levée sinon, rien n'étant gardé.

Deux meneurs de jeu qui appliquent une condition en même temps lisent tous deux la version 7 : la première sauvegarde
garde la version 8, et la seconde est refusée avec `ConditionConflict` ; l'application relit et applique de nouveau.

`Store\InMemoryConditionStore` les garde en mémoire, tant que l'objet dure : il sert aux tests. Une application les garde
dans sa base de données. `CharacterConditions` contient des chaînes et des entiers — le personnage, le numéro de la
dernière instance donnée, le dernier tic, la version — et jusqu'à 64 `ActiveCondition`, chacune faite de chaînes, d'une
valeur entière, d'un moment, d'un moment de fin facultatif, et d'une `Duration` composée d'un entier et de trois
énumérations dont les valeurs sont leurs cas en chaîne.

## L'observateur et ses événements

`Contract\ConditionObserver::notify()` reçoit chaque fait une fois les conditions gardées, par identifiants seulement :

| Événement | Quand | Contient |
|---|---|---|
| `Event\ConditionApplied` | Une condition a été appliquée comme nouvelle instance. | |
| `Event\ConditionChanged` | Une instance a changé : sa valeur, ou une nouvelle application l'a rafraîchie. | |
| `Event\ConditionEnded` | Une instance s'est terminée. | l'`EndReason` : retirée, expirée, déclinée ou remplacée |

Chaque événement implémente `Event\ConditionEvent` : le personnage, l'instance et la condition, tous des identifiants.
Dans un même appel, les conditions écoulées en temps réel viennent d'abord, puis une instance remplacée, puis la
nouvelle. Une application en tire un message — par Messenger, avec les identifiants seulement — et lit le reste dans le
magasin : un envoi de la vue à la table, un avis au joueur dont le personnage n'est plus étourdi. Une durée en rounds qui
s'écoule n'est pas un événement ; la vue montre ce qui reste.

## Le catalogue et les données du système

Le catalogue est une donnée simple que l'application construit une fois, depuis sa configuration ou depuis les termes de
son système de jeu, et partage entre les requêtes. Les comportements forment une liste fermée — cumul, valeurs, déclin,
durées, conditions entraînées, cinq opérations — et la bibliothèque n'exécute aucun code du site ; une règle hors de la
liste reste dans l'application, qui lit la vue et les événements.

## L'horloge et les appels

`Conditions` lit l'heure sur la `ClockInterface` PSR-20 qu'on lui donne, et nulle part ailleurs. Entre deux appels, rien
ne se passe de soi-même : le moment où se termine la prochaine condition en temps réel est `ConditionsView::$nextExpiry`,
et l'application appelle alors `expire()` — un message Messenger différé portant l'identifiant du personnage, ou une
tâche planifiée. Une expiration vérifiée trop tôt, ou deux fois, ne change rien. Les tours et le temps de la fiction sont
annoncés par l'application, chaque frontière avec son tic, pour qu'un message rejoué ne compte qu'une fois.
