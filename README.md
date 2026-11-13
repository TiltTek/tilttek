# Tilttek

**Open PHP libraries and Symfony bundles for games and gamification: dice, loot, random generators, weather, points,
levels, badges, quests, collections and exchanges.**

Tilttek is a set of small `tilttek/*` packages, each doing one thing: a library in plain PHP, usually paired with a
Symfony bundle that wires it into configuration, Doctrine, Twig, the console and Messenger. They follow the rules of
the [`phil/*`](https://github.com/parler-haut-interagir-librement/phil) packages of PHIL: typed for PHPStan at its
maximum level and tested down to the mutation of every line of their domain.

This repository is the **monorepo** where every package is developed, tested and documented together. Each package is
then split into its own **read-only** repository, from which Composer installs it through Packagist.

## Packages

### Dice and loot

| Package | Description |
|---|---|
| [`tilttek/dice`](https://github.com/TiltTek/dice) | Dice notation parsed, checked and rolled: pools, keep and drop, explosions, rerolls, successes, verifiable and replayable rolls. |
| [`tilttek/dice-bundle`](https://github.com/TiltTek/dice-bundle) | Dice in a Symfony application: limits and custom dice in configuration, replayable rolls in Doctrine, macros, a form field, Twig and an accessible detail of a roll. |
| [`tilttek/dice-probability`](https://github.com/TiltTek/dice-probability) | The exact odds of a dice expression, as fractions: distributions, at least and at most, mean, comparisons. |
| [`tilttek/loot-tables`](https://github.com/TiltTek/loot-tables) | Loot tables rolled with any die, drawn in a way anyone can verify, physical dice included. |
| [`tilttek/accessible-dice`](https://github.com/TiltTek/accessible-dice) | An accessible dice form for Symfony, with or without JavaScript: large targets, faces spoken by label, shape and value, results announced to screen readers. |
| [`tilttek/secret-rolls`](https://github.com/TiltTek/secret-rolls) | The game master's hidden rolls, committed before they are made and verifiable by the players after the reveal. |

### Generators

| Package | Description |
|---|---|
| [`tilttek/generator`](https://github.com/TiltTek/generator) | Random generators written as data: weighted lists, Markov names, Twig templates in a sandbox, grammar in six languages, maps, tables with a memory, replayable draws. |
| [`tilttek/generator-bundle`](https://github.com/TiltTek/generator-bundle) | Random generators in a Symfony application: lists from YAML and JSON, ready-made random tables, commands, Twig, a Stimulus controller and a rate-limited draw API. |
| [`tilttek/weather`](https://github.com/TiltTek/weather) | Weather in the game world: seasons and climate zones, conditions that follow the day before, temperatures rolled within the season, reproducible from a seed. |
| [`tilttek/heraldry`](https://github.com/TiltTek/heraldry) | Coats of arms drawn from a seed, blazoned in English and French, and written as safe SVG. |

### Gamification

| Package | Description |
|---|---|
| [`tilttek/activity`](https://github.com/TiltTek/activity) | A journal of what members do, recorded once per gesture, with the projections every other module reads. |
| [`tilttek/activity-bundle`](https://github.com/TiltTek/activity-bundle) | The activity journal in a Symfony application: Doctrine DBAL tables, projections kept up to date live or by Messenger. |
| [`tilttek/points`](https://github.com/TiltTek/points) | Points kept in a double-entry ledger: currencies, earning rules, caps, expiry, spending and transfers. |
| [`tilttek/points-bundle`](https://github.com/TiltTek/points-bundle) | Points in a Symfony application: rules in configuration, the ledger in Doctrine DBAL, balances and histories. |
| [`tilttek/progression`](https://github.com/TiltTek/progression) | Experience, levels on a curve, seasons, milestones and streaks counted in the member's timezone. |
| [`tilttek/progression-bundle`](https://github.com/TiltTek/progression-bundle) | Levels and streaks in a Symfony application: tracks in configuration, a progress bar and a streak in Twig. |
| [`tilttek/badges`](https://github.com/TiltTek/badges) | Badges earned on members' activity: criteria, tiers, progress, hidden badges and showcases the member chooses. |
| [`tilttek/badges-bundle`](https://github.com/TiltTek/badges-bundle) | Badges in a Symfony application: criteria in configuration, awards stored, a showcase in Twig, Open Badges credentials. |
| [`tilttek/quests`](https://github.com/TiltTek/quests) | Quests and challenges: steps read on activity, dates that come back, chains, team quests and duels. |
| [`tilttek/quests-bundle`](https://github.com/TiltTek/quests-bundle) | Quests in a Symfony application: in configuration or the database, with their progress, events and templates. |
| [`tilttek/collections`](https://github.com/TiltTek/collections) | Collectible items and sets: copies and doubles, the item of the day, packs drawn with published odds. |
| [`tilttek/collections-bundle`](https://github.com/TiltTek/collections-bundle) | Collections in a Symfony application: the catalog in configuration or the database, copies in Doctrine DBAL, an album and the item of the day in Twig. |
| [`tilttek/exchange`](https://github.com/TiltTek/exchange) | Rewards bought with points in two steps, points given between members, and items traded by offers and counter-offers in one transaction. |
| [`tilttek/leaderboards`](https://github.com/TiltTek/leaderboards) | Leaderboards kept up to date from the activity journal: periods and sliding windows, tie-breaks, snapshots, weekly leagues, titles and teams. |
| [`tilttek/advent-calendar`](https://github.com/TiltTek/advent-calendar) | An advent calendar: one box per day in the calendar's timezone, an opening policy, each box opened once and told by an event. |
| [`tilttek/writing-goals`](https://github.com/TiltTek/writing-goals) | An author's writing goals: a target in words or pages, a plan on a curve, a daily quota recomputed from what remains, no streak counted. |
| [`tilttek/seasonal-events`](https://github.com/TiltTek/seasonal-events) | Yearly seasons by RFC 5545 rule, with their contents and the multipliers they announce. |
| [`tilttek/sets`](https://github.com/TiltTek/sets) | Versioned sets with tiers and rewards: a neutral building block that collections and badges build on. |
| [`tilttek/stickers`](https://github.com/TiltTek/stickers) | Stickers placed on surfaces, with limits per surface and per holder, and moderation. |
| [`tilttek/bingo`](https://github.com/TiltTek/bingo) | Bingo with cards drawn from a server secret, printed cards that can be verified, and wins checked on the server. |
| [`tilttek/game-engine`](https://github.com/TiltTek/game-engine) | Turn-based games as a log of moves: seeded randomness committed in advance, undo, snapshots and replay. |
| [`tilttek/game-engine-bundle`](https://github.com/TiltTek/game-engine-bundle) | Game tables in Symfony: opaque seat identifiers, the log in DBAL, a lock, and private Mercure updates. |
| [`tilttek/table-safety`](https://github.com/TiltTek/table-safety) | Safety tools for tabletop games: lines and veils, anonymous signals, and the credits of each tool. |

### Game building

| Package | Description |
|---|---|
| [`tilttek/deck-builder`](https://github.com/TiltTek/deck-builder) | Card decks built to a format's declared rules: sizes, copies, banned cards and taboo lists, every violation readable, no publisher's catalogue bundled. |
| [`tilttek/wargame-army`](https://github.com/TiltTek/wargame-army) | Army lists for miniatures games on a generic engine: units, options, points and detachment slots checked against a catalogue the application provides. |
| [`tilttek/puzzle-builder`](https://github.com/TiltTek/puzzle-builder) | Puzzles for a scenario, checked on the server: normalized and partial answers, bounded guesses, tiered hints and a validated unlock graph. |

Every package is at `0.x`: its API may still change between minor versions, as its CHANGELOG records.

## Installing a package

Require the package you need; a bundle brings its library along:

```sh
composer require tilttek/points-bundle
```

Each package's README and `doc/` say what it requires, how to configure it and what it leaves to the application.

## Layout

- `packages/<name>/`: one package per folder, installable on its own, with its `composer.json`, README in English and
  in French, CHANGELOG, licence, documentation in `doc/` (French mirror in `doc/fr/`) and tests.
- The root ties them together through a Composer path repository and holds the shared quality tooling, which comes
  from [`phil/dev-tools`](https://github.com/parler-haut-interagir-librement/dev-tools).

## Contributing

Issues and pull requests go to **this repository**, never to the split repositories, which are overwritten at each
split. Read the [contribution guide](CONTRIBUTING.md) and the [code of conduct](CODE_OF_CONDUCT.md). Report a
vulnerability privately, as the [security policy](SECURITY.md) explains, never in a public issue.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). Each
package carries its own licence file.
