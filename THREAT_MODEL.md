# Threat model

## What this project does and where untrusted input enters

Tilttek is a monorepo of PHP 8.5 libraries and Symfony bundles for games and gamification (`packages/<name>/`, each
published as `tilttek/<name>`): dice, probabilities, random generators, loot tables, points, badges, quests,
leaderboards. The libraries are plain PHP; each bundle wires its library into a Symfony application. Several of them
evaluate small languages typed by players in a chat, a form or a virtual table: that input is untrusted, and an
attacker controls every byte of it.

The packages that receive untrusted input, and what each one promises:

| Package | Untrusted input | Promise |
|---|---|---|
| `dice` | Dice expressions (notation, instructions, macros, dialects) | Parsing and rolling stay within `Limits` (input length 256, dice 100, sides 1000, explosions 100, rerolls 100, repetitions 100, instructions 10, text 4096 characters by default): past a limit the expression is refused with `LimitExceeded`, never truncated, and never runs unbounded. Integer overflow is refused, not wrapped. Seed-committed rolls cannot be predicted before the reveal nor changed after it. |
| `dice-probability` | Dice expressions | Exact computation is bounded in work and memory; past its bound it switches to a seeded estimate or refuses, never runs unbounded. |
| `generator` | Draw requests (generator name, count, seed, state passed back by the caller); generator lists and templates when an application lets its users write them | Templates run in a Twig sandbox: no PHP code, no file, no network, only the allowed tags, filters, functions and methods. Every draw stays within `Limits` (depth, picks, time per result and per draw, results, cells). A state passed back by a caller cannot make a draw run outside those limits. |
| `loot-tables` | Draw requests, dice entered by hand, shop codes | A committed draw cannot be changed after it is committed, nor its outcome learnt before the reveal. |
| `points`, `badges`, `quests`, `leaderboards`, `progression`, `collections`, `exchange` | Actions reported by users | A user cannot award themselves points, badges or progress outside the rules the application configured, nor act on another user's records. |

The `*-bundle` packages carry the same promises at the application boundary: form fields and constraints, Twig filters,
and the rate-limited draw API of `generator-bundle` (off by default).

The `phil/*` packages these packages require are scanned in their own monorepo; a defect there is out of scope here
unless a Tilttek package passes them input they do not promise to handle.

## Components that matter most / least

- Most: `dice`, `dice-probability`, `generator`, then `loot-tables` and the bundles that expose them.
- In scope but less exposed: the gamification packages, whose input is mostly the application's own events.
- Out of scope: `accessible-dice` front-end assets beyond the HTML they emit; development tooling (`vendor-bin/`,
  `bin/`, `phil-dev.php`); test fixtures and test applications under `packages/*/tests/`; the random tables shipped as
  data in `packages/generator/tables/`.

## Assumptions

- The packages run inside a Symfony application. Its configuration (`config/`, bundle options, limits, custom dice,
  site macros, generator catalogs written by its developers) is trusted.
- The application authenticates its users and decides who may write macros or generators; the libraries still enforce
  their limits and sandbox on whatever they receive.
- The PHP runtime, its extensions and the operating system are up to date; their own defects are out of scope.

## How to exercise it

- `composer test` runs every suite; `vendor/bin/phpunit packages/<name>/tests` runs one package.
- `packages/*/tests/Fuzz/` holds fuzz targets for the fuzzer installed in `vendor-bin/fuzzer`.
- Each package documents its limits in `packages/<name>/doc/` (`dice/doc/limits.md`, `generator/doc/drawing.md`).

## How we rate severity

- Critical: code execution or file or network access from a generator template or a dice expression; escaping the
  Twig sandbox.
- High: predicting or changing a committed roll or draw; acting on another user's points, badges or progress; stored
  XSS through a Twig filter or form field of a bundle.
- Medium: denial of service from one short expression or request: time or memory well past the documented limits.
- Low: information leaks in error messages; defects that need trusted configuration to be hostile.

## Anything to leave alone

- Statistical quality of non-cryptographic seeded draws, which are meant to be replayable.
- Behaviour that only trusted configuration can trigger, as stated in the assumptions.

## Security contact

See [`SECURITY.md`](SECURITY.md) at the root of the monorepo.
