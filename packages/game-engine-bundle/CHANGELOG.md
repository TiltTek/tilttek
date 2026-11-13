# Changelog

All notable changes to `tilttek/game-engine-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `TilttekGameEngineBundle` and its configuration `tilttek_game_engine`: the store, its connection, the state cache,
  the lock, Mercure, the viewer, the role-playing scene, the translation domain and the subdirectory of the kit.
- The games: every service implementing `Contract\Game` of `tilttek/game-engine`, tagged by autoconfiguration and
  gathered by name, and the library's role-playing scene on demand.
- `DbalLogStore` and `GameEngineTables`: the logs on Doctrine DBAL, on MySQL, PostgreSQL or SQLite, in two tables, the
  seats, options and payloads as JSON text in the order they were written; the header of a log read from one row, with
  its number of moves and a revision that never goes down; the moves read window by window; a move appended or taken
  back in one statement that compares the number of moves and the revision, a concurrent move refused, the primary key
  against two moves of one number, a whole log read back up to the number of moves of its table, and a table deleted
  with its moves.
- `GameEngineSchemaListener`: the tables of the logs in the schema the ORM generates for their connection.
- `CacheStateCache`: the states in a PSR-6 pool as the JSON text of `Snapshot::toArray()`, with the revision of their
  log and the commitment to their seed, a state JSON would not give back left uncached, what the pool gives back
  checked and forgotten when refused, a later revision of a table never written over — under the lock of its key when
  a lock factory is configured —, and an optional lifetime.
- `GameTables`, `Contract\TableLock` and `SymfonyTableLock`: the library's tables on the configured services, tagged
  `kernel.reset`, each move and move taken back made under the lock of its table on Symfony's lock factory, with its
  time to live, and no move taken back once the game is over.
- `SeatIdentifiers` and `IdentifyingSeat`: a table opened only with opaque seat identifiers, a seat that looks like an
  email address or a reference to a member refused.
- `MercureTablePublisher` and `TableTopic`: every change published privately on the topic of its table, with
  identifiers and counters only, a hub that fails logged rather than thrown.
- `TableController`, `Viewer`, `Contract\ViewerResolver` and `AudienceViewerResolver`: the endpoints of the view of a
  seat, with its preferences, and of the log of a table whose game is over, checked to be the log of its finished
  state, through the application's resolver, the audience opened only on demand, never cached.
- `tilttek:game-engine:setup`, which creates the tables of the logs or shows their SQL, and
  `tilttek:game-engine:verify`, which replays a log against the commitment to its seed.
- The template of a table built of the recipes Card and Badge of the PHIL UX kit, the Twig functions
  `tilttek_game_table()`, `tilttek_game_engine_kit()` and `tilttek_game_engine_domain()`, and its texts in English and
  French.
- The Stimulus controller `game-table`, which reads the view of the reader's seat again at each update the Mercure hub
  pushes and dispatches it as an event.
- The Flex recipe, in `recipe/0.1/`.
- Benchmarks of the storage of a table and of a table of 8 000 moves.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
