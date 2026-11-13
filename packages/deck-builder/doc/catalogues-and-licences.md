# Catalogues and licences

## No catalogue is bundled

The library imports no publisher's catalogue and ships none: no card list, no format and no taboo list of a published
game, and no importer of one. The cards of a game, their names and texts, and the lists of a game's formats are data
their publisher owns; copying them needs the publisher's licence, and a catalog published without a licence grants
none.

The examples, the tests and the fuzz corpora of the library use invented cards and formats only.

## How an application provides its catalog

An application provides its cards and formats through two ports:

- `Contract\CardCatalog::find($id)` gives a `Card` — its identifier, its name and, when it differs from the format's,
  its copies — or null;
- `Contract\FormatCatalog::find($code)` gives a `Format`, with its taboo lists, or null.

The data behind them is the application's to hold:

- cards it wrote itself, for its own game or a game of its members;
- invented cards, for examples, tutorials and tests;
- cards under a licence it holds that allows this use — a licence it checked, for the cards and the texts it shows.

A `Format` is read from the application's configuration or files with `Format::fromArray()` or `fromJson()`; the
cards come from its own database or files. The library needs only identifiers, names and numbers of copies: a card's
text, art and rules stay with the application, which shows what its licence allows.
