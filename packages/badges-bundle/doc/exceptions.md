# Exceptions

The bundle raises the library's exceptions — see `tilttek/badges`'s `doc/exceptions.md` — and:

| Exception | When |
|---|---|
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | A configuration value out of its bounds, or Open Badges enabled without `PhilOpenBadgesBundle` registered or without the achievement URL |
| `Tilttek\Badges\Exception\InvalidBadge` | When the container is compiled, a badge or a timezone of the configuration that does not hold |
| `UnexpectedValueException` | A row whose column does not hold the type the stores write |
