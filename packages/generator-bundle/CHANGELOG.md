# Changelog

All notable changes to `tilttek/generator-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Added

- The limits `steps`, `bytes` and `megabytes` of `tilttek/generator` in `tilttek_generator.limits`.
- The attributions of the results in the template `@TilttekGenerator/draw.html.twig`, in a paragraph of class
  `tilttek-generator-attribution`, and in the Stimulus controller's `attribution` target, refreshed with each draw;
  the API answers them under `attributions`.
- A test that every template of the generators is loaded from a compiled file of the cache directory, never by
  `eval()`.

### Changed

- `tilttek_generator.cache` is the directory of Twig's compiled templates, `%kernel.cache_dir%/tilttek_generator/twig`
  by default, `true` naming the default; `false` and an empty text are refused, so that templates are never loaded by
  `eval()`.
- `tilttek/generator: ^0.1.1@dev`, for its new limits and attributions.

## 0.1.0-alpha - 2026-11-12

### Added

- `CachedCatalog`: the lists and generators of the files and directories, or of `config/generators` of the project, kept
  in a PHP file of the cache and written again in debug when a file changes, even in the second the cache was written,
  `FileChanges` asking the files each time; `GeneratorFactory`, which builds the public `Generator` with the configured
  limits, language and grammars, and the dice of `tilttek/dice-bundle` when it is installed.
- The configuration `tilttek_generator`: paths, default language, aspirated words of the French grammar, words with a
  stressed a of the Spanish grammar, limits, Twig's cache, the library's random tables (`tables`) and their language
  (`tables_locale`, English or French), the store of the states, its pool and its lock (`state`), and the draw API with
  its public generators, clients, tokens and rate limiters; the public draws limited by address by default, sixty a
  minute (`api.public_limit`), on the rate limiter builder of `symfony/rate-limiter`, and the container refused by
  `PublicRateLimiterPass` when that builder is missing.
- The Twig function `tilttek_draw`, the template `@TilttekGenerator/draw.html.twig`, and the Stimulus controller that
  draws again in place, writing results as text, with its status and `aria-busy`, mapped for AssetMapper.
- The commands `tilttek:generator:validate`, which lists every problem, `tilttek:generator:create`, which writes a text,
  structured, map or memory generator, or copies a random table of the library under a new name (`--from`), in English
  or in French (`--locale`), and `tilttek:generator:draw`, which draws from the state kept under `--key`, forgotten
  first by `--reset`, and writes the state it leaves.
- The tables with a memory: `MemoryTables`, public, which draws from the state kept by generator and key and keeps the
  new one, and reads, sets and forgets a state, refusing an empty key or one of more than 200 characters
  (`InvalidStateKey`); the `StateStore` port, public, on a PSR-6 cache pool without expiry under hashed keys
  (`CacheStateStore`, keys hashed by SHA-256), in memory reset with the kernel (`InMemoryStateStore`), or the
  application's own service; the `StateLock` port, off by default, on Symfony's lock factory that `state.lock` names
  (`SymfonyStateLock`, a lock by a SHA-256 hash of the generator and the key, waited for and kept 30 seconds at most), which holds a draw from the load of its
  state to the save of the new one, and a setting or a forgetting, so that two draws on the same state do not lose an
  update; `symfony/lock` suggested.
- The draw API: `ApiAccess`, `Client` and `Grant`, off by default, public generators counted by address and clients by
  token compared in constant time; `DrawRequest`, which reads the state a table with a memory sent back
  (`state[name]=value`) and answers the new one, and refuses with a 400 a name, a seed or a state that is not UTF-8;
  `DrawController` and its route, answering JSON with `no-store`, and problem details for 400, 401, 403, 404, 422 and
  429 with `Retry-After`, the cause of a failed draw told to a client and not to the public; the `DrawRateLimiter` port
  on Symfony's rate limiters.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
