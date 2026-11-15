# Exceptions

| Exception | Raised when |
|---|---|
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | An option is of the wrong kind or out of range |
| `Tilttek\Leaderboards\Exception\InvalidLeaderboard` | A board, a season or a timezone does not hold together, when the container is compiled; a limit or a circle a template gives is refused |
| `Tilttek\Activity\Exception\InvalidActivity` | A verb pattern or a context condition of a board is refused, when the container is compiled |
| `Tilttek\Leaderboards\Exception\UnknownBoard`, `UnknownPeriod` | A template, a command or a service asks for a board or a period none has |
| `Tilttek\Leaderboards\Exception\PeriodNotOver` | A snapshot is asked for a period that has not ended |
| `UnexpectedValueException` | A row of a table holds a value of another type than its column — a database changed by hand |
