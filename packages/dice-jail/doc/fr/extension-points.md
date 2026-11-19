<!-- phil-dev source-sha256=cde84028c1c590d0cce87cc0e1ce48a86a9912dd75bc8e483220e0e3b40bbb99 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\JailStore` | Le stockage de l'historique de chaque dé, avec ses positions | [La prison](jail.md#concurrent-writers) |
| `Contract\RollSource` | Les lancers de chaque dé : les faces notées par les joueurs, ou le journal des lancers de l'application | [Les statistiques](statistics.md#reading-them-honestly) |
| `Psr\Clock\ClockInterface` | L'heure de chaque événement et de la fin des peines | [Installation](installation.md#wiring-the-library) |
| `Statistics\ExpectedFaces` | Le dé équitable auquel les lancers sont comparés | [Les statistiques](statistics.md#the-test) |

Chaque point est passé à un constructeur ; aucun service ne garde d'état entre deux appels.
