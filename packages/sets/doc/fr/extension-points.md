<!-- phil-dev source-sha256=cf7e7b9bcde2274b23f6ad6e3e1fd50ec671425fbea82058ab56c229b39d2894 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Où |
|---|---|---|
| `Contract\DefinitionStore` | Les versions des ensembles, jamais réécrites ; `Store\InMemoryDefinitionStore` les garde en mémoire | [Définir des ensembles](defining-sets.md#versions) |
| `Contract\CompletionStore` | Les complétions, une par ensemble, palier et détenteur ; `Store\InMemoryCompletionStore` les garde en mémoire | [Progression et complétions](progress-and-completions.md#concurrence) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui est informé de chaque complétion, par `Event\SetCompleted` et `Event\TierReached`, dans des gestionnaires idempotents | [Progression et complétions](progress-and-completions.md#remise) |
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
- `Completion::toArray()` donne les valeurs d'une ligne, avec le moment en UTC à la microseconde.
