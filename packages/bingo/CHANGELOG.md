# Changelog

All notable changes to `tilttek/bingo` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-25

### Changed

- The development requirements ask for the latest minor of `twig/twig`, 3.31.

### Security

- `twig/twig` older than 3.31.1 refused, for the security fixes of that version: its sandbox checks the policy in more
  places, and a template can no longer replace the content of a `Markup`.

## 0.2.0-alpha - 2026-11-14

### Changed

- An event of the game can no longer be lost when a listener fails. A tick, a call or a win is written with its event,
  in the same version of the card or the game, and the event stays there until it was dispatched without an
  exception: `Card::undelivered()` and `Card::markDelivered()`, `BingoGame::undelivered()` and
  `BingoGame::markDelivered()`. A listener that throws leaves its event undelivered, the other events are dispatched
  all the same, and the first exception is thrown again at the end; the same call repeated — the gesture recorded
  again, the square called again, the claim made again — dispatches it again. Each delivery is one more change of the
  card or the game.
- A card's win takes its place among the game's winners() right before its event is dispatched, so that a win whose step
  failed is resumed with its event.
- `SquareTicked` carries the idempotency key of the gesture that ticked the square, `gesture`, a new argument of its
  constructor.
- `Activity\BingoActivityObserver` implements `ResumableObserver` of `tilttek/activity`: the same gesture recorded
  again, or the same cancellation given again, is handed to it once more.
- The package requires `tilttek/activity` `^0.1.1@dev`, whose `ResumableObserver` `Activity\BingoActivityObserver`
  implements.

### Fixed

- A cell ticked by more gestures than `Card::MOST_KEYS` could be unticked when the gestures it remembered were
  cancelled, while a gesture it could not remember still stood. Such a cell is now pinned: it stays ticked for good,
  and `Card::pinned()` lists it. A card stored before reads with no cell pinned; nothing is migrated.

### Added

- `Bingo::deliver()`: the game's undelivered calls and, given a serial, the card's undelivered ticks and win
  dispatched again, to resume after a failure.
- `key()` on `BingoWon`, `SquareTicked` and `SquareCalled`: a stable key, the same each time the event is dispatched
  again, under which an idempotent listener acts once.
- The delivery of the events documented: at-least-once delivery, idempotent listeners keyed by `key()`, resuming, and
  the cards and games stored before.

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
