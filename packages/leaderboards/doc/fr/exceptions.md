<!-- phil-dev source-sha256=eccfec73c7b9579dc8134b5c67d15e3104ebefd8570d37b18556f7b76c54f3bf -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Leaderboards\Exception\LeaderboardsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidLeaderboard` | Un nom, un classement, une période, une saison, un calendrier, un score, un pseudonyme, un cercle, une limite, un fuseau horaire, une date, une ligue ou un numéro de division est refusé, une valeur est rapportée à un classement qui lit le journal, ou une division est demandée à un classement sans ligue | `A snapshot keeps 1 to 1000 members; the board "games" asks for 0.` |
| `UnknownBoard` | Un classement est demandé par un nom qu'aucun ne porte | `No board is named "games".` |
| `UnknownPeriod` | Une période est demandée par un code que le calendrier du classement ne connaît pas, ou un classement entre deux saisons n'a aucune période en cours | `The board "games" has no period coded "2026-13".` |
| `PeriodNotOver` | Un instantané est demandé pour une période qui n'est pas terminée, ou pour `ever`, ou une semaine d'une ligue est réglée avant sa fin | `The period "2026-11" of the board "games" is not over: it cannot be frozen yet.` |

Les motifs de verbes et les conditions de contexte d'un classement sont vérifiés par `tilttek/activity`, qui lève sa
propre `InvalidActivity`.
