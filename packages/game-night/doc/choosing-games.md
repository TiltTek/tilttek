# Choosing games

`Choice\GameChooser` suggests games for a table, among the games the members can bring and those the club or the
store keeps.

## The criteria

`Choice\Criteria` holds what a game is chosen for:

| Value | What it is | Bounds |
|---|---|---|
| `players` | The players, guests included | 1 to 1500 |
| `minutes` | The minutes there are | 1 to 1440 |
| `owners` | The members whose games can be brought | at most 1500, each once |
| `library` | The games of the catalogue the club or the store keeps | at most 500, each once |
| `limit` | The most suggestions to give | 1 to 50, 10 by default |

`Criteria::forTable()` takes the places taken at a table, or its seats while nobody sits there, the minutes of the
night, and the confirmed members seated at it. `Criteria::forNight()` takes the confirmed places of the whole night,
its minutes and its confirmed members.

## The shelves

The games of the members are read through `Contract\ShelfReader`, at most 1000 of them: the library never reads a
member's collection otherwise. A game a member not named in the criteria owns is left out.

## Which games are suggested

A game is suggested when the catalogue has it and does not hide it, its publisher states numbers of players that hold
the players, and it ends within the minutes: the longest playing time the publisher states is at most the minutes
there are. A game whose playing time the catalogue does not know is suggested after those whose time is known.

## The order

The suggestions come in this order:

1. the games the members of the catalogue voted best for this number of players, then recommended, then without a
   vote, then not recommended;
2. among them, the games whose playing time is known;
3. then the games most can bring: their owners, and the club or the store when it keeps them;
4. then by name, then by identifier.

## The votes a suggestion rests on

A choice from one vote ranks with a choice from fifty. Each `Choice\Suggestion` carries what it rests on, to be shown
beside it:

| Value | What it is |
|---|---|
| `choice` | The choice most members of the catalogue gave for this number of players, or `null` |
| `choiceVotes` | How many members gave one of the three choices for it |
| `averageRating` | The average rating of this number of players, in hundredths, or `null` |
| `ratingVotes` | How many members rated it |

## BoardGameGeek

A suggestion keeps the identifier of the game on BoardGameGeek and builds the link to its page, through
`boardGameGeekLink()`; nothing else of BoardGameGeek is read or kept.
