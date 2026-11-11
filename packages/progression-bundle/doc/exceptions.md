# Exceptions

| Exception | Raised when |
|---|---|
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | An option is of the wrong kind or out of range |
| `Tilttek\Progression\Exception\InvalidProgression` | A track, a curve, a milestone, a streak or a timezone does not hold together, when the container is compiled |
| `Tilttek\Progression\Exception\UnknownTrack`, `UnknownStreak` | A template or a service asks for a track or a streak none has |
| `UnexpectedValueException` | A row of a table holds a value of another type than its column — a database changed by hand |
