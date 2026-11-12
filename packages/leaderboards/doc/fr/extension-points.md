<!-- phil-dev source-sha256=9840d5cdd8385fabe430911370b6c88560a5ee8adb93af27c67e84f4ef10ab58 -->
# Points d'extension

| Point | Ce que donne une application | Où |
|---|---|---|
| `Store\ScoreStore`, `Store\SnapshotStore`, `Store\AppearanceStore`, `Store\LeagueStore` | Le stockage des scores, des instantanés, des choix d'apparence et des ligues, à la place des stockages en mémoire | [Écrire un stockage](writing-a-store.md) |
| `Contract\ValkeyConnection` | La connexion à Valkey de `ValkeyScoreStore`, quand le client n'est aucun de ceux qu'enveloppe `RawCommandConnection` | [Écrire un stockage](writing-a-store.md#scores-in-valkey) |
| `LeaderboardObserver` | Ce qui est fait quand un instantané est pris : récompenser les premiers membres | [Instantanés](snapshots.md) |
| `League\LeagueObserver` | Ce qui est fait quand une semaine est réglée : féliciter les membres qui sont montés | [Ligues](leagues.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services, la projection aussi, pour écarter un geste plus ancien qu'une fenêtre : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |
| Le secret de `Leaderboards` | Le secret d'où se tire la chance des équipes | [Équipes et titres](teams-and-titles.md) |

Les classements eux-mêmes sont des valeurs : `Boards::fromArray()` les lit à partir d'une configuration, ou
l'application les construit dans le code. Chaque point est passé au constructeur du service qui l'utilise ; les
services ne gardent aucun état propre entre deux appels.
