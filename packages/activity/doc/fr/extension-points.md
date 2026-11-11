<!-- phil-dev source-sha256=e3dc18ef8d714cfe5200f50cf8d867d7ec6fcd88b4918f7243e8c2400a06e439 -->
# Points d'extension

| Point | Ce que fournit une application | Où |
|---|---|---|
| `Log\ActivityLog` | Le stockage du journal, à la place de `InMemoryActivityLog` | [Écrire un journal](writing-a-journal.md) |
| `Projection\Projection` | Un état dérivé qui lui est propre, tenu à jour à partir du journal et reconstruit quand une règle change | [Projections](projections.md#en-écrire-une) |
| `Projection\Checkpoints` | L'endroit où chaque projection s'est arrêtée, à la place de `InMemoryCheckpoints` | [Projections](projections.md#le-projecteur) |
| `JournalObserver` | Ce qui se fait juste après l'écriture d'une entrée, dans le même processus | [Enregistrer l'activité](recording.md#observateurs) |
| `Psr\Clock\ClockInterface` | L'horloge qui date chaque entrée : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise. Les services ne gardent aucun état propre entre deux
appels.
