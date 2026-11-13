<!-- phil-dev source-sha256=2532952127ed0d219d9931f9e4672f81afc39fb9177896a3f7ff1676b4b7211d -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\GameEngine\Exception\GameEngineException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidGame` | Une définition, une phase, une transition, une option, une préférence, une statistique, les options ou les sièges d'une table, les préférences d'un siège, le type ou la charge utile d'un coup, un germe, un journal ou un état relu est refusé, un journal ne peut pas être rejoué ou son germe n'est pas celui de l'engagement, ou une issue rapporte une statistique que le jeu ne déclare pas | `The move 4 of the table "table:42" draws differently when it is replayed: the game is not pure, or its rules changed.` |
| `IllegalMove` | Un coup est refusé dans l'état courant de la table : la partie est terminée, le siège n'est pas à la table ou n'a pas le tour, le jeu le refuse, une transition ne quitte pas la phase, ou une reprise n'est pas permise | `It is not the turn of the seat "seat:bob".` |
| `GameOver` | Un coup est repris à une table dont la partie est terminée, puisque son germe a pu être montré | `The game at the table "table:42" is over: its seed may be shown, so no move is taken back.` |
| `GameInProgress` | Le journal d'une table, germe compris, est demandé à être montré alors que sa partie continue | `The game at the table "table:42" is not over: its log, which holds the seed, stays secret until it is.` |
| `ConcurrentMove` | Un coup ou une reprise est fait sur un journal qui a changé depuis sa lecture — un autre nombre de coups, ou le même nombre à une autre révision —, un journal a changé pendant chacune de ses lectures, ou le journal lu d'une table terminée n'est pas celui de son état terminé | `The log of the table "table:42" holds 5 moves, not the 4 expected: another move came first.` |
| `UnknownTable` | Une table ou un jeu est demandé par un identifiant ou un nom qu'aucun n'a | `No table has the identifier "table:9".` |
