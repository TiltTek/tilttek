# Exceptions

Every exception of the library implements `Tilttek\Weather\Exception\WeatherException`. A forecast also lets the
exceptions of `tilttek/generator` through, under its own `GeneratorException`: a limit broken (`LimitExceeded`), or a
forecast of fewer than one day (`InvalidDefinition`).

| Exception | Thrown when | Message |
|---|---|---|
| `InvalidClimate` | Climates cannot be used: a document that does not parse or is longer than 1 MiB, a value of the wrong kind or out of its range, an identifier, a label missing for a language, a neighbour, season or condition not declared, a daily roll that `tilttek/dice` does not read. | `zones.arid.seasons.hot.high: 20 is not a whole number from 25 to 80` |
| `UnknownClimate` | A state, a lookup or a `Weather` names a zone, a season of a zone, a condition or a language the climates do not declare. | `The zone "polar" is not declared; the climates know "temperate", "boreal", "mediterranean", "arid", "tropical".` |
| `InvalidState` | A state cannot be read: no zone or season, a condition that is not a text, a temperature that is not a whole number of degrees from -200 to 200. | `The state of the weather holds its temperature as a whole number of degrees.` |
