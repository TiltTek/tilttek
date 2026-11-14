# Changelog

All notable changes to `tilttek/weather` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- The declared climates: `Condition` with its labels, its temperature shift from -20 to 20 and its neighbours;
  `Season`; `SeasonalClimate` with its range from -80 to 80 °C and the weights of its conditions from 1 to 1000; `Zone`
  with its seasons in the order of the year, its persistence from 1 to 20 and its daily roll of `tilttek/dice`, read and
  rolled once to check it gives one whole total; `Climates` with its 1 to 8 languages and 1 to 64 conditions, seasons
  and zones, a label in every language, every neighbour, season and condition declared, the neighbours both ways, and at most
  20 000 moves between conditions in their weather tables (`Climates::TRANSITIONS`).
- `ClimateReader`: climates from YAML, a file or a decoded document of at most 1 MiB, unknown keys refused, and each
  problem with its place.
- The shipped climates in `data/climates.yaml`, `Climates::default()`: sixteen conditions, eight seasons, and five zones
  — temperate, boreal, mediterranean, arid and tropical — in English and in French.
- `WeatherTable`: the weather as a table with a memory of `tilttek/generator`, as a catalog, a document or a YAML file
  under a name of the caller's choice; a condition that stays by its weight times the zone's persistence or turns into
  a neighbour the season brings, and one the season does not bring that turns into a neighbour it brings or into any of
  its conditions; a temperature that moves by the zone's roll and one degree toward the middle of the range moved by
  the condition, within the range, and comes back by two degrees a day when it is out of it; the zone and the season
  left to the application. The generator files written from the shipped climates in `generators/en` and
  `generators/fr`.
- `Weather`: forecasts of one or more days from a `WeatherState`, in a language of the climates, the same days for the
  same state and seed, within the limits of `tilttek/generator`; `Forecast` with its days and the state the last one
  leaves; `Day` with its condition, label, temperature, zone and season, and its temperature in Fahrenheit;
  `WeatherState` read from and written to a map of texts, with a new season or zone.
- Exceptions under `WeatherException`: `InvalidClimate`, `UnknownClimate` and `InvalidState`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
