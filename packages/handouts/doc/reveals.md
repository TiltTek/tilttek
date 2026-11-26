# Reveals

A handout is concealed from every player until it is revealed. An `Audience` says to whom:

| Audience | Who |
|---|---|
| `Audience::everyone()` | Every player at the table: the application decides who sits there |
| `Audience::players($ada, $bo)` | The players named, 1 to 500, each kept once in the order given |

An audience given to an act names at most `Limits::$audiencePerHandout` players — 50 by default
(`Refusal::AudienceTooLarge`).

## Revealing now

```php
$handouts->reveal($letter, Audience::players($ada), $gm);
$handouts->reveal($map, Audience::everyone(), $gm);
```

`reveal()` gives one `Disclosure` for everyone, or one per player: the revision shown, the moment and the acting
reference. Players always see the current revision; the disclosure keeps which one was shown when.

Revealing again to the same audience shows the handout again, at the new moment and revision — the application pushes
it to the players' screens once more. A reveal staged for the same audience is done with and removed.

A handout is revealed to at most `Limits::$audiencePerHandout` audiences — everyone counting as one —
(`Refusal::TooManyRevealed`).

`canSee($handout, $player)` is true when the handout is revealed to everyone, or to the player by name. The application
asks it before it shows a handout or serves one of its files. `audience($handout)` reads the disclosures, the earliest
first.

## Concealing again

```php
$handouts->conceal($letter, Audience::players($ada), $gm);    // Ada no longer sees it
$handouts->conceal($letter, Audience::everyone(), $gm);       // no one sees it
```

Concealing from everyone removes every disclosure, to everyone and to players by name. Concealing from players removes
their disclosures by name: a handout also revealed to everyone stays visible to them. Staged reveals stay; `unstage()`
withdraws them. What players saw stays in their sightings.

## Staging a reveal

A reveal can be prepared ahead, on a `Trigger`:

| Trigger | When it happens |
|---|---|
| `Trigger::at($moment)` | When the application calls `releaseDue()` at that moment or later |
| `Trigger::cue('crypt.opened')` | When the game master gives the cue with `cue($table, 'crypt.opened')` |

A cue is a key: 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them.

```php
$handouts->stage($map, Audience::everyone(), Trigger::cue('crypt.opened'), $gm);
$handouts->stage($letter, Audience::players($ada), Trigger::at(new DateTimeImmutable('2026-12-04 20:00')), $gm);
```

`stage()` gives one `StagedReveal` for everyone, or one per player. Staging again for the same audience replaces the
earlier trigger. A handout holds at most `Limits::$audiencePerHandout` staged reveals (`Refusal::TooManyStaged`).
`staged($handout)` reads them, the earliest staged first; `unstage($handout, $audience)` withdraws them — for everyone,
every staged reveal of the handout.

### Giving a cue

```php
$handouts->cue($table, 'crypt.opened', $gm);
```

`cue()` reveals what was staged on the cue for the table's handouts, the earliest staged first, and gives the
disclosures. It reveals at most `Limits::$releaseBatch` staged reveals — 100 by default —; what remains stays staged
for the next call.

### Releasing what is due

```php
$handouts->releaseDue();
```

`releaseDue()` reveals the staged reveals of every table whose moment has come, the earliest moment first, at most
`Limits::$releaseBatch` per call. The application's scheduler calls it, and calls again while a call reveals as many.

A released reveal is acted by the reference that calls `cue()` or `releaseDue()` with one, or else by the one that
staged it.
