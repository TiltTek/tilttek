<!-- phil-dev source-sha256=48f681b07c605380dee2b8e864d7f21e9778f7775b4c67aaaa0cec0d4562ddcc -->
# Exceptions

Toutes les exceptions de la bibliothèque implémentent `Exception\GuildsException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Lancée quand | Message |
|---|---|---|
| `GuildRefused` | Les règles de la guilde refusent l'acte ; `refusal` dit pourquoi | Le message du refus, ci-dessous |
| `UnknownGuild` | Aucune guilde ne porte la référence, ou aucun objectif la clé | `No guild is known as "…".`, `No goal is known as "…".` |
| `InvalidGuild` | Le nom, la capacité ou les métadonnées d'une guilde, la clé, le nom, la cible ou l'échéance d'un objectif, les points ou la clé d'une contribution, ou une limite hors bornes | Ce qui est hors bornes, et la borne |

`GuildRefused` étend `DomainException`, `UnknownGuild` `OutOfBoundsException`, `InvalidGuild`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `AlreadyFounded` | A guild already holds this reference. |
| `AlreadyMember` | This member already belongs to the guild. |
| `AlreadyRequested` | This member already asked to join the guild. |
| `NotMember` | This member does not belong to the guild. |
| `NoRequest` | This member has no request to join the guild. |
| `Full` | The guild holds as many members as its capacity. |
| `TooManyGuilds` | This member belongs to, or asked to join, as many guilds as allowed. |
| `TooManyRequests` | The guild holds as many waiting requests as allowed. |
| `NotAllowed` | The role of this member does not allow this act. |
| `FounderStays` | The founder hands the guild to another member before leaving it. |
| `BelowMembers` | The capacity cannot go below the members the guild holds. |
| `GoalExists` | A goal already holds this key. |
| `TooManyGoals` | The guild holds as many open goals as allowed. |
| `GoalOver` | The goal is reached, closed or past its deadline. |

Les exceptions de `phil/resource` parviennent à l'appelant telles quelles quand une référence est mal formée.
