# Changelog

All notable changes to `tilttek/bingo` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Bingo`: games created and closed, one card per player dealt on the first call, squares called for every card,
  gestures of the activity journal recorded and cancelled — the squares one gesture ticks on a card in one change —,
  claims on one's own card and checks by serial, every change made again on a conflict, up to eight times.
- Printed batches: `Bingo::print()` deals 1 to 1 000 cards held by no player in a game whose squares are called, their
  serials reserved at once; `SerialLabel`, the serial and a check code of eight characters of Crockford's base 32 drawn
  from the secret, read leniently from at most 32 bytes; `Bingo::label()` and `Bingo::verifyLabel()`, which refuses a
  code that is not the serial's before the check of `verify()`; `View\PrintedCardView` and `templates/print.html.twig`,
  one section per card with its label, the words given by `labels`, the block `footer`.
- `BingoGame` and `TickMode`: a pool, a grid, win patterns and a mode — ticked by activity, won on its own, or called
  by the host, won by a claim —, up to 100 000 cards, the squares called and the winners in order.
- `Card`: a serial from 1, a player or none for a printed card, a layout, the idempotency keys of the gestures that
  ticked each cell, `tickAll()` for the squares of one gesture in one version, a cell unticked when all its gestures
  are cancelled unless the card won, and a win kept once with its pattern and moment.
- `CardDealer`: layouts and labels drawn from a server secret of at least 32 bytes, seeded by the HMAC-SHA256 of the
  game and the serial, picked and shuffled by a seeded `Xoshiro256StarStar`; fingerprints that keep the cards of a game
  unique, serials whose layout is taken passed over up to 32 times.
- `Claim`: the cells ticked, the free centre included, and the pattern won with; a card that does not match its seed
  refused, a claim on another player's card refused, a card of a closed game checked and never won.
- `Square`, `SquarePool` of 1 to 500 squares, `Trigger` by verb pattern and optional object, and `Grid` of 3 to 9
  cells with an optional free centre.
- `WinPattern` — row, column, diagonal, full card, four corners and custom sets of cells — and `WinPatterns`, 1 to 16
  checked in order, with `WinPatterns::line()`.
- `Activity\BingoActivityObserver`: the cards ticked and unticked from the entries of the activity journal.
- Events carrying identifiers only: `BingoWon`, with a null player for a printed card, `SquareTicked` and
  `SquareCalled`.
- `Mercure\BingoPublisher`: the squares called and the cards won pushed as private Mercure updates on the game's topic,
  the squares ticked on the card's, naming no player; `symfony/mercure` suggested.
- `View\CardView`, `View\CellView` and `templates/card.html.twig`: a table of cells with their ticks said to screen
  readers, the words of the page given by `labels`, the block `won`; the Stimulus controller `card` dispatching
  `bingo:won` on the window once per card in a browser tab, for the celebration of the PHIL UX kit.
- `Contract\BingoStore` with versions and fingerprints, and `Store\InMemoryBingoStore`.
- Exceptions under `Exception\BingoException`: `InvalidBingo`, `BingoRefused` and `BingoConflict`.
- Benchmarks of a layout, of the check of every pattern and of a gesture recorded.
- A fuzz target of the label of a printed card and its check.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
