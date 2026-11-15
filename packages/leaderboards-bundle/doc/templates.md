# Templates

## Twig functions

| Function | Returns |
|---|---|
| `tilttek_leaderboards_standing(board, viewer = null, period = null, limit = 10, among = [], object = null)` | The `Standing` of a board in a period — the one under way by default — as the viewer sees it, among the circle's members when `among` lists them, for the `object` on a board ranked by object |
| `tilttek_leaderboards_frozen(board, period, viewer = null, limit = 10)` | The snapshot of a period as the viewer sees it, or null |
| `tilttek_leaderboards_division(board, viewer, period = null)` | The viewer's `Division` on a board with a league, in a week — the one under way by default —, or null when they take no part in it |
| `tilttek_leaderboards_league(board, viewer)` | A `LeagueView`: everything the member's league screen shows |
| `tilttek_leaderboards_teams(board, teams, period = null, object = null)` | The teams ranked, each team's members by its name — `{red: ['member:1'], blue: ['member:2']}` —, as `TeamRow`s |
| `tilttek_leaderboards_titles(member)` | The titles the member holds now |
| `tilttek_leaderboards_holds(board, member, object = null)` | Whether the member holds the board's title now, for the object on a board ranked by object |
| `tilttek_leaderboards_appearance(member)` | The member's choice, for the form that changes it |
| `tilttek_leaderboards_celebration(board, viewer, period = null)` | A `CelebrationView` when the viewer reached the top as the period closed — the last one over by default —: promoted into the highest tier, on a board with a league, or first of its snapshot; null otherwise, on a board with no period behind it (`ever`, a window), or when `celebration` is disabled |
| `tilttek_leaderboards_kit(recipe)` | The name a recipe of the kit is installed under: `ui:Badge` with the Toolkit's subdirectory `ui` |
| `tilttek_leaderboards_domain()` | The translation domain of the template |

Members are written `kind:identifier`, such as `member:44`.

## The standing template

```twig
{{ include('@TilttekLeaderboards/standing.html.twig', {
    standing: tilttek_leaderboards_standing('games-month', 'member:44'),
    names: {'member:3': 'Alice'},
}) }}
```

It renders the board's name — the translation key `boards.<name>` —, the period, how many are ranked, "Final standing"
for a snapshot, the lines in an ordered list, and the viewer's own line below them when it is not among them. A line
reads "You" for the viewer, the name `names` gives for a member who chose to appear under their name, the pseudonym for
one who chose one, and "A member" for everyone else — a named member missing from `names` included. The application
passes only the names of the members it shows.

The template ships no CSS: its classes start with `tilttek-leaderboards`, and the viewer's line carries
`tilttek-leaderboards__row--self`.

## The division template

```twig
{% set division = tilttek_leaderboards_division('weekly-xp', 'member:44') %}
{% if division %}
    {{ include('@TilttekLeaderboards/division.html.twig', {division: division, names: {'member:3': 'Alice'}}) }}
{% endif %}
```

It renders the board's name, the tier — the translation key `tiers.<name>` — and the division's number, the week, how
many go up and down, and the division's lines, each read as in a standing and marked by where it goes if the week ended
now: its class `tilttek-leaderboards__row--promoted`, `--stayed` or `--relegated`, its `data-movement`, and the
translated `movement.*` text. Only the division's members are shown, each by their choice of appearance.

## The league screen

```twig
{{ include('@TilttekLeaderboards/league.html.twig', {
    league: tilttek_leaderboards_league('weekly-xp', 'member:44'),
    names: {'member:3': 'Alice'},
    appearance_url: path('profile_leaderboards'),
}) }}
```

The member's own screen on a weekly board with a league: the board's name, their tier and when the week ends, the
ladder of tiers from the highest with theirs marked `aria-current="step"`, where they went last week, their division —
the division template above — or, before they score this week, what makes them join one, and how the members of their
division see them, with a link to the form that changes it when `appearance_url` is given. Nothing of it is public.

`LeagueView` holds the board, its tiers from the lowest — `ladder()` from the highest —, the member's tier, their
`Division` or null, the end of the week, their `Membership` of last week or null, and their `Appearance`.

## The ranking of teams

```twig
{{ include('@TilttekLeaderboards/teams.html.twig', {
    board: 'house-cup',
    rows: tilttek_leaderboards_teams('house-cup', {red: ['member:1', 'member:2'], blue: ['member:3']}),
}) }}
```

A table of the teams: rank, name — the translation key `team_names.<name>` —, how many members scored, their scores
added up, the roll of luck when the board enables it, and the total.

## The celebration

```twig
{{ include('@TilttekLeaderboards/celebration.html.twig', {
    celebration: tilttek_leaderboards_celebration('weekly-xp', 'member:44'),
}) }}
```

On the screen a member opens after reaching the top — the board's page, the page a notification of `TopReached` links
to —, it plays the `Celebration` recipe of the PHIL UX kit: "You finished first on …!", "You took the lead on …!" or
"You reached …, the highest tier of …!", and the configured effect. With `celebrate_on: period_close`, a first place is
celebrated during the period that follows the snapshot; with `celebrate_on: score_change`, a lead is celebrated while
the member holds it, in the period under way, once they took it among at least two members. A promotion into the
highest tier is celebrated during the week that follows either way. The celebration is the member's own: it says
"You", and shows nothing of them to anyone else. It renders nothing when `celebration` is null. With `autoplay: false`, it waits
for a `celebrate` DOM event or a button with `data-action="celebration#play"`, as the recipe describes. A template of
the application draws it where it likes with the block alone:

```twig
{% use '@TilttekLeaderboards/celebration.html.twig' %}
{% set celebration = tilttek_leaderboards_celebration('games-month', app.user.reference) %}
{{ block('tilttek_leaderboards_celebration') }}
```

The recipe makes it accessible: the message is said in a polite live region (`role="status"`, `aria-live="polite"`)
and stays shown; the confetti or fireworks are drawn only where motion is not reduced — `prefers-reduced-motion`, or
`data-motion="reduce"` on an ancestor —; their canvas is hidden from screen readers and lets every click through; there
is no sound. The celebration plays each time the screen is shown while it holds: an application that wants it once
keeps what the member saw — the board, the period and the summit — and passes `celebration: null` afterwards. `CelebrationView` holds the
`Board`, the period's code, the `Summit`, the effect and, for a league, the tier reached. Its root carries
`data-board`, `data-period` and `data-summit`.

## The recipes of the kit

The league screen and the ranking of teams are built of the recipes `Card`, `Badge` and `Empty`, and the celebration of
the recipe `Celebration`, of a kit of the Symfony UX Toolkit — the PHIL UX kit, styled by phil-ux, or the Shadcn kit, whose recipes bear the same names and props —, which
the application installs, then renders through `symfony/ux-twig-component`:

```sh
bin/console ux:install card badge empty celebration --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The templates name the recipes after the subdirectory of the application's `ux_toolkit` configuration —
`tilttek_leaderboards_kit('Card')` is `ui:Card` with `ui` —, or after `kit_subdirectory` when it is set. The
`Celebration` recipe is the PHIL UX kit's own, from its version 0.5.0-alpha; with another kit, override `celebration.html.twig` or disable
`celebration`. The standing,
snapshot and division templates need no recipe.

## Translations

`translations/tilttek_leaderboards.en.yaml` and `.fr.yaml` hold the texts: `you`, `a_member`, `period`,
`participants`, `frozen`, `empty`, `division`, `promotion`, `relegation`, `movement.promoted`, `.stayed` and
`.relegated`, the league screen's `league.*`, the ranking's `teams.*` and the celebration's `celebration.first_place`,
`celebration.lead` and `celebration.highest_tier`. The boards' names are the application's, under
`boards.<name>`, and so are the tiers', under `tiers.<name>`, and the teams', under `team_names.<name>`.
