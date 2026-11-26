# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\HandoutStore` | Where handouts, disclosures, staged reveals and sightings are kept | `Store\InMemoryHandoutStore` in tests |
| `Contract\HandoutObserver` | What to do with a change of a handout | None |
| `Limits` | Its bounds on handouts per table, attachments per handout, audiences per handout, reveals per release | 200, 10, 50 and 100 |
| `MediaTypes` | The media types attachments may have | Common images, PDF, sounds, videos and plain text; no SVG |
| `Psr\Clock\ClockInterface` | The moment of each act, and what is due | — |

## Writing a handout store

A `HandoutStore` names handouts, tables and players by their references written `kind:identifier`, and an audience by
its key: `*` for everyone, or a player's reference. It keeps:

- the handouts, by reference — `handout()`, `saveHandout()`, which replaces the earlier one, `removeHandout()`, which
  removes the handout's disclosures, staged reveals and sightings with it, `countHandouts()`, and `handouts($table)`,
  the earliest created first;
- the disclosures, one per handout and key — `disclosure()`, `saveDisclosure()`, `removeDisclosure()`,
  `disclosures($handout)`, the earliest revealed first, and `disclosuresFor($table, $player)`, the disclosures of the
  table's handouts under `*` or the player's key, the earliest revealed first;
- the staged reveals, one per handout and key — `staged()`, `saveStaged()`, `removeStaged()`, `stagedOf($handout)`, the
  earliest staged first, `due($until, $limit)`, the reveals of every table staged at a moment no later than `$until`,
  the earliest moment first, and `onCue($table, $cue, $limit)`, the earliest staged first;
- the sightings, one per handout and player — `sighting()`, `saveSighting()`, and `sightings($handout)`, the earliest
  first seen first;
- `forgetPlayer($player)`, which removes the player's entries in every table and gives the references of the handouts
  that held any, in byte order.

Moments that tie are read in the byte order of the handout's reference, then of the key or the player. A store keeps
`due()` fast with an index on the moment of staged reveals, and `disclosuresFor()` with one on the table and the key.

The library checks the limits before it writes: an application that serves concurrent requests runs each act of
`Handouts` in a transaction, or under a lock on the handout, so that two reveals never pass the limit together, and
runs `releaseDue()` from one scheduler, or under a lock, so that a staged reveal is released once.

## Observers

A `HandoutObserver` is told a `HandoutChanged` after each act, once the store wrote it. The event holds identifiers
only — the handout, its table, the player concerned or none for everyone, the acting reference — with the `Change` and
the moment; titles, texts and files are never in it. An observer relays the event — a push to the players' screens, a
message on a bus — and keeps no state between two calls. A screen that receives it reads the handout through the
application, which checks `canSee()`.

| `Change` | Player |
|---|---|
| `Created`, `Edited`, `Removed` | — |
| `Revealed`, `Concealed`, `Staged`, `Unstaged` | The player, or none for everyone |
| `Seen`, `Forgotten` | The player |

`Revealed`, `Concealed`, `Staged` and `Unstaged` are told once per player of the audience, or once for everyone.
