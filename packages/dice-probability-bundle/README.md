# tilttek/dice-probability-bundle

**The probabilities of dice expressions on a Symfony page: a table that is always there, a chart, and a component that
computes as you type.**

tilttek/dice-probability-bundle integrates `tilttek/dice-probability` with Symfony. It reads the expressions with the
`DiceRoller` of `tilttek/dice-bundle` — its notations, limits and custom dice —, takes the depth of explosions, the work
limit, the estimates and the rounding from the configuration, and gives `DiceStatistics` for expressions written as
text. In templates, Twig functions give a distribution or a comparison, and draw them as a summary, a bar chart and an
accessible table; the Live Component `TilttekDiceProbability:Distribution` does the same as an expression, and a second
one to compare it with, are typed. The screens are drawn with the recipes of the PHIL UX kit, which the application
installs with the Symfony UX Toolkit.

Anyone may type into the component, so the bundle bounds the work of every request: the limits of the library, a
weight of the groups an estimate searches checked before any die is rolled, a cap on the rows of a table, fractions
reduced within a bounded work, and a rate limit on the component per IPv4 address or IPv6 /64, on by default.

## Status

| Part | State |
|---|---|
| Configuration: explosion depth, work limit, samples, seed, rounding, bounds of a request | Implemented and tested |
| `DiceStatistics`: distributions, comparisons and events of expressions written as text | Implemented and tested |
| Groups too long to search refused before the estimate, `GroupSearch` | Implemented and tested |
| Twig functions, filter with a bounded reduction, table and chart, in English and French | Implemented and tested |
| Live Component, with the refusals of the bundle translated | Implemented and tested |
| Rate limit of the Live Component per IPv4 address or IPv6 /64, on by default | Implemented and tested |
| Screens drawn with the recipes of the PHIL UX kit, in the Toolkit's subdirectory | Implemented and tested |
| Events of `chance()` in Twig and in the component | Not started |
| Languages other than English and French | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, optional packages, registering the bundle and installing the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every key, its default and what it sets; the search of groups and the rate limit. |
| [Statistics in PHP](doc/statistics.md) | `DiceStatistics` and `Rounding`. |
| [Templates](doc/templates.md) | The Twig functions, the table and the chart, their accessibility, and the component. |
| [Extension points](doc/extension-points.md) | Overriding the templates, adding a language, events of the application. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test pages that show probabilities. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```sh
composer require tilttek/dice-probability-bundle
```

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle::class => ['all' => true],
];
```

```sh
php bin/console ux:install alert card chart field input radio-group table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```twig
{{ tilttek_dice_probabilities('4d6kh3') }}
<twig:TilttekDiceProbability:Distribution expression="1d20+5" versus="2d10+4" />
```

## Requirements

PHP 8.5 or later, Symfony 8.2, `tilttek/dice-probability` and `tilttek/dice-bundle`; Twig, the translator, Symfony UX
and the recipes of the PHIL UX kit for the templates and the component, with `symfony/rate-limiter` for its rate limit;
`ext-gmp` for the long fractions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
