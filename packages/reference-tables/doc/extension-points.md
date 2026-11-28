# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Declare the system's tables | Write documents of the [format](format.md) and read them with `Format\TableReader`, or build `ReferenceTable`s in code. |
| Keep the tables | Keep the documents, or the rows of your database, and build the `TableCatalogue` from them; cache it between requests. |
| Act on a roll | Implement `Contract\TableObserver`, and pass it to `Rolling\TableRoller`. |
| Give the randomness | Pass a `Random\Randomizer` to each roll: a secure engine in production, a seed in tests. |
| Bound or extend the dice | Pass a `DiceRoller` of `tilttek/dice` to `Rolling\TableRoller`, with its limits and custom dice. |
| Show where a table comes from | Give `TableCatalogue::provenances()` to the `AttributionRenderer` of `tilttek/rpg-systems`. |
| Search a larger corpus | Index the names and text cells in a search engine, keeping the table's key and the row's position. |
| Say who may see a table | Filter the catalogue in the application before showing or searching it. |

## The observer and its event

`Contract\TableObserver::notify()` is told each roll once its row is found, as an `Event\TableRolled`: the table's key,
the row's position, the total and whether the die was entered. An application sends a message from it, with those
identifiers alone, and reads the rest from its catalogue. [Rolling](rolling.md#the-observer) gives the details.

## The system data

The tables are plain data: the library runs no code of the site's. What a table may hold is a closed list — texts,
integers, dice — and what a lookup does too — a key, a range, a die, a modifier, clamping at the ends. A rule outside
the list stays in the application, which reads the row and acts: a result that sends to another table names its key in
a text cell, and the application rolls that one next.

## The line with the generator

`tilttek/generator` draws content from weighted lists and Twig templates — names, inns, rumours — and shapes its text.
A reference table gives back the row a value or a key names, as the source prints it, with its source and page; it
renders nothing. A random table meant to make content belongs to the generator; a table a game master consults or rolls
to read a rule belongs here.

## The services

`TableReader`, `TableRoller` and `TableSearch` are read-only and keep nothing between two calls: one instance serves
every request of a worker. A `TableCatalogue` and its tables are immutable values that requests share.
