# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

`Weather` takes its collaborators in its constructor; each has a default and can be replaced.

| Collaborator | Default | Use |
|---|---|---|
| `Tilttek\Weather\Climates` | `Climates::default()` | The world's climates: read from the application's YAML by `ClimateReader`, or built in PHP. |
| the language | `en` | The language of the labels, one of the climates' `locales`. |
| `Random\Randomizer` | a secure engine | The randomness of a forecast without a seed: a seeded engine in tests. |
| `Tilttek\Generator\Limits` | the default limits | The bounds of a draw of `tilttek/generator`; `results` bounds the days of one forecast. |

`WeatherTable` gives the weather to an application that draws with `tilttek/generator` itself: `catalog()` as a
`Catalog`, under a name of its choice, and `yaml()` as a generator file.
