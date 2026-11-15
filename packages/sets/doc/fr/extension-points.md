<!-- phil-dev source-sha256=742ced31dddadc61aa65af2d82e4c9e6094cb5e8337f9615188cf1128ec1985b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Où |
|---|---|---|
| `Contract\DefinitionStore` | Les versions des ensembles, jamais réécrites ; `Store\InMemoryDefinitionStore` les garde en mémoire | [Définir des ensembles](defining-sets.md#versions) |
| `Contract\CompletionStore` | Les complétions, une par ensemble, palier et détenteur ; `Store\InMemoryCompletionStore` les garde en mémoire | [Progression et complétions](progress-and-completions.md#concurrence) |
| `Contract\PendingDeliveries` | Facultatif, à côté du stockage de complétions : les détenteurs qui ont des complétions non remises et les échecs de leur remise, pour `Sets::deliverPending()` | [Progression et complétions](progress-and-completions.md#remise) |
| `DeliveryBackoff` | Combien de temps attend un détenteur dont la remise a échoué, et à partir de combien d'échecs de suite chacun est signalé | [Progression et complétions](progress-and-completions.md#remise) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui est informé de chaque complétion, par `Event\SetCompleted` et `Event\TierReached`, dans des gestionnaires idempotents, et des échecs répétés, par `Event\DeliveryKeepsFailing` | [Progression et complétions](progress-and-completions.md#remise) |
| `Psr\Clock\ClockInterface` | Le moment des complétions, gardé en UTC | [Installation](installation.md#câbler-la-bibliothèque) |

Chaque point est passé à un constructeur ; `Sets` ne garde aucun état entre deux appels.

## Implémenter un stockage

- `DefinitionStore::add()` retourne faux, sans rien écrire, quand le stockage contient cette version de cet ensemble ;
  `latest()` donne la version la plus élevée.
- `CompletionStore::add()` retourne faux, sans rien écrire, quand le stockage contient une complétion de même
  `Completion::key()` : l'ensemble, le palier ou aucun, et le détenteur. `of()` et `ofHolder()` listent dans l'ordre
  d'ajout.
- Une complétion est ajoutée non remise. `CompletionStore::undelivered()` liste les complétions non remises d'un
  détenteur dans tous les ensembles, dans l'ordre d'ajout ; `markDelivered()` en marque une remise, et ne fait rien pour
  une complétion que le stockage ne contient pas. Un stockage en base de données garde une colonne à cette fin, comme un
  moment de remise qui peut être nul.
- Un stockage de complétions qui implémente aussi `Contract\PendingDeliveries` permet à `Sets::deliverPending()` de
  trouver les détenteurs qui attendent. `holdersWithUndelivered($limit, $now)` donne une seule fois chaque détenteur
  qui a une complétion non remise et aucune tentative repoussée après `$now`, au plus `$limit` d'entre eux, dans
  l'ordre où sa plus ancienne complétion non remise a été ajoutée, et aucun quand `$limit` est inférieur à 1.
  `failedDeliveries()` donne les échecs de suite d'un détenteur, 0 pour aucun ; `postpone()` les garde avec le moment
  avant lequel le détenteur n'est pas listé ; `forgetFailures()` oublie les deux, et effacer un détenteur les oublie
  aussi. Un stockage en base de données les garde dans une table à lui, par détenteur. Un stockage en base de données y
  répond par une seule requête, comme les détenteurs des lignes non remises groupées par détenteur et triées selon leur
  plus petit numéro de ligne, avec un index sur la colonne de remise. Un stockage qui ne l'implémente pas continue de
  fonctionner pour tout le reste.
- `Completion::toArray()` donne les valeurs d'une ligne, avec le moment en UTC à la microseconde.
