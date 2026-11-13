# History and copies

## Versions

`Decks::save($deck, $at, $note = null)` saves the deck as its next version, from 1: a `DeckVersion` with the deck as
it was, the moment in UTC and an optional note of up to 500 characters — "after the regional". A version is never
rewritten. A deck that has not changed since its latest version keeps that version, so saving on each change of a form
is safe.

Two requests that save the same deck at once ask the store to add the same version: the store keeps the first, and the
second throws `DeckConflict`, for the application to read the deck again.

| Method | Gives |
|---|---|
| `latest($deck)` | The latest version |
| `version($deck, $version)` | A given version |
| `history($deck)` | Every version, from the first to the latest |

A deck with no saved version, or without the version asked, throws `UnknownDeck`.

## Differences

`Decks::diff($deck, $from, $to)` gives the `DeckDiff` between two versions, in either order; `DeckDiff::between()`
compares any two decks. It lists the cards added, removed and changed in quantity, and whether the name or the format
changed; `lines()` writes them for people to read:

```text
+2 ember-fox
-1 fox-den
storm-call: 2 → 1
Name: "Foxes" → "Foxes II"
```

## Copies

`Decks::copy($deck, $as, $name, $at, $version = null)` copies a version of a deck — its latest by default — as the
first version of a new deck of identifier `$as`, whose `copiedFrom` is a `DeckReference` to the original deck and
version. The copy keeps the original's format and cards; the original is never changed. An identifier already used
throws `DeckConflict`.

The link stays with the copy and its later versions, so an application can show where a deck comes from and count the
copies of a published deck.
