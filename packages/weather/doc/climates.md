# Climates

## The document

```yaml
locales: [en, fr]

conditions:
  clear:
    labels: { en: clear skies, fr: ciel dégagé }
    temperature: 2
    next_to: [fair, frost]
  fair:
    labels: { en: fair, fr: beau temps }
  frost:
    labels: { en: hard frost, fr: gelée blanche }
    temperature: -4

seasons:
  summer: { labels: { en: summer, fr: été } }
  winter: { labels: { en: winter, fr: hiver } }

zones:
  highlands:
    labels: { en: highlands, fr: hautes terres }
    persistence: 3
    change: 1d5-3
    seasons:
      summer: { low: 8, high: 22, conditions: { clear: 4, fair: 5 } }
      winter: { low: -12, high: 3, conditions: { clear: 2, fair: 2, frost: 5 } }
```

| Key | What it holds |
|---|---|
| `locales` | The languages every label is written in: 1 to 8 codes such as `en` or `fr_CA`. |
| `conditions.<id>.labels` | The condition's label in each language, 1 to 100 characters. |
| `conditions.<id>.temperature` | How many degrees the condition moves the temperature from the middle of the season's range, from -20 to 20; 0 by default. |
| `conditions.<id>.next_to` | The conditions it may turn into from one day to the next; a neighbour goes both ways, so each pair is written once. |
| `seasons.<id>.labels` | The season's label in each language. |
| `zones.<id>.labels` | The zone's label in each language. |
| `zones.<id>.persistence` | How many times more weight a condition has to stay than to come, from 1 to 20; 3 by default. |
| `zones.<id>.change` | The roll of `tilttek/dice` added to the temperature each day, `1d5-3` (from -2 to +2) by default; one whole total. |
| `zones.<id>.seasons.<id>.low`, `high` | The range of the season's temperatures, in degrees Celsius, from -80 to 80. |
| `zones.<id>.seasons.<id>.conditions` | How often each condition comes in the season, as whole weights from 1 to 1000. |

An identifier is a lowercase letter, then up to 31 lowercase letters, digits, `_` or `-`. The climates declare 1 to 64
conditions, seasons and zones; a zone has 1 to 16 seasons, in the order of the year, the first being the one a state
without a season starts in. The weather tables of all the zones and seasons together hold at most 20 000 moves between
conditions (`Climates::TRANSITIONS`), counted before anything is built: for each condition of a season, one move to
each condition it may turn into. Every condition, season and zone has a label in every language; every neighbour, season
and condition named is declared. `ClimateReader` reads a document of at most 1 MiB and names the place of each problem:
`zones.highlands.seasons.winter.high: -20 is not a whole number from -12 to 80`. The same checks run on climates built
in PHP from `Condition`, `Season`, `SeasonalClimate` and `Zone`.

## How a day follows from the day before

Each day:

1. the condition stays, with the weight the season gives it times the zone's persistence, or turns into one of its
   neighbours that the season brings, with the weight the season gives that neighbour. A condition the season does not
   bring — after the season changed — turns into one of its neighbours the season brings, or, when it has none, into any
   condition of the season by its weight;
2. the temperature moves by the zone's roll, plus one degree toward its target — the middle of the season's range
   moved by the new condition — and stays within the range. A temperature out of the range, after the season or the
   zone changed, comes back toward it by two degrees a day instead.

The neighbours are what keeps the weather continuous: write them as the sky would move — fair to overcast, overcast to
drizzle, drizzle to rain, rain to a downpour — and a season's weights say how often each comes. A zone with a high
persistence keeps its weather for days; a low one changes it often.

## The shipped climates

`Climates::default()` reads `data/climates.yaml`, which `Climates::file()` names, to copy and adapt. Its sixteen
conditions — clear skies, fair, hot and muggy, overcast, fog, drizzle, steady rain, downpour, thunderstorm, strong
wind, sleet, snowfall, blizzard, hard frost, heatwave and sandstorm — are labelled in English and in French, and its
zones are:

| Zone | Seasons | Persistence | Daily roll |
|---|---|---|---|
| `temperate` | spring 3 to 16 °C, summer 14 to 30, autumn 4 to 17, winter -8 to 6 | 3 | `1d5-3` |
| `boreal` | spring -5 to 10 °C, summer 8 to 24, autumn -6 to 10, winter -35 to -5 | 4 | `1d7-4` |
| `mediterranean` | spring 10 to 22 °C, summer 20 to 36, autumn 12 to 25, winter 2 to 15 | 4 | `1d5-3` |
| `arid` | hot season 25 to 45 °C, cool season 5 to 25 | 5 | `1d5-3` |
| `tropical` | wet season 23 to 32 °C, dry season 20 to 31 | 3 | `1d3-2` |

They describe climates of a game world, not of a real place: a world of its own declares its own.
