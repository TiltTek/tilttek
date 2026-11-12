# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Weather` | Forecasts the next days from a state, in one language. |
| `WeatherState` | The zone, the season, and the condition and temperature the last day left; read from and written to a map of texts. |
| `Forecast` | The days of a forecast and the state the last one leaves. |
| `Day` | The condition, label, temperature, zone and season of one day; the temperature in Fahrenheit. |
| `Climates` | The declared languages, conditions, seasons and zones, every reference checked; the shipped climates. |
| `ClimateReader` | Reads climates from YAML, a file or a decoded document, naming the place of each problem. |
| `Condition` | A weather condition: its labels, its temperature shift and its neighbours. |
| `Season` | A season and its labels. |
| `Zone` | A climate zone: its labels, its seasons, its persistence and its daily roll. |
| `SeasonalClimate` | What a season brings to a zone: its range of temperatures and the weights of its conditions. |
| `WeatherTable` | Writes climates as a table with a memory of `tilttek/generator`: a catalog, a document or a YAML file; the shipped files. |
| `Exception\WeatherException` | The interface of every exception of the library. |
| `Exception\InvalidClimate` | Climates that cannot be used. |
| `Exception\UnknownClimate` | A name the climates do not declare. |
| `Exception\InvalidState` | A state that cannot be read. |
