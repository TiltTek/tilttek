<!-- phil-dev source-sha256=0ba52189f5f373f42e9e7cc661911f6fb76c9839ffaa7cb76be0a063a0ddf20f -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Où |
|---|---|---|
| `Contract\DefinitionStore` | Les versions des ensembles, jamais réécrites ; `Store\InMemoryDefinitionStore` les garde en mémoire | [Définir des ensembles](defining-sets.md#versions) |
| `Contract\CompletionStore` | Les complétions, une par ensemble, palier et détenteur ; `Store\InMemoryCompletionStore` les garde en mémoire | [Progression et complétions](progress-and-completions.md#concurrence) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui est informé de chaque complétion, par `Event\SetCompleted` et `Event\TierReached` | [Progression et complétions](progress-and-completions.md#enregistrement) |
| `Psr\Clock\ClockInterface` | Le moment des complétions, gardé en UTC | [Installation](installation.md#câbler-la-bibliothèque) |

Chaque point est passé à un constructeur ; `Sets` ne garde aucun état entre deux appels.

## Implémenter un stockage

- `DefinitionStore::add()` retourne faux, sans rien écrire, quand le stockage contient cette version de cet ensemble ;
  `latest()` donne la version la plus élevée.
- `CompletionStore::add()` retourne faux, sans rien écrire, quand le stockage contient une complétion de même
  `Completion::key()` : l'ensemble, le palier ou aucun, et le détenteur. `of()` et `ofHolder()` listent dans l'ordre
  d'ajout.
- `Completion::toArray()` donne les valeurs d'une ligne, avec le moment en UTC à la microseconde.
