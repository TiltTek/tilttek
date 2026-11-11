# Templates

## Twig functions

| Function | Returns |
|---|---|
| `tilttek_progression_standing(subject, track)` | The `Standing` of a subject on a track |
| `tilttek_progression_standings(subject)` | One `Standing` per track |
| `tilttek_progression_streak(member, streak)` | The `StreakStatus` of a member's streak |
| `tilttek_progression_domain()` | The configured translation domain |

A profile is a member's: show their progression to others only when they chose to.

## The progress bar

```twig
{{ include('@TilttekProgression/bar.html.twig', {standing: tilttek_progression_standing('member:44', 'player')}) }}
```

It renders the track's name (`tracks.<name>` in the translation domain), the level, a `<progress>` element from the
level's start to the next — full at the last level — with its percent as text, the experience still missing, and the
season under way. Classes start with `tilttek-progression-bar`; the bundle ships no CSS.

## The streak

```twig
{{ include('@TilttekProgression/streak.html.twig', {streak: tilttek_progression_streak('member:44', 'daily-play')}) }}
```

It renders the streak's name (`streaks.<name>`), the current and best runs and the grace left; the class `is-alive`
marks a run under way.

## Translations

The bundle ships `tilttek_progression.en.yaml` and `tilttek_progression.fr.yaml` with `level`, `last_level`,
`missing`, `season` and `streak.current`, `streak.best`, `streak.grace`. The application adds `tracks.<name>` and
`streaks.<name>`, and overrides any key in its own translations.
