# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2, `tilttek/dice-probability` and `tilttek/dice-bundle`, whose `DiceRoller` reads the
expressions with the application's notations, limits and custom dice. Twig, the translator and Symfony UX are optional:

| Package | For |
|---|---|
| `symfony/twig-bundle`, `symfony/ux-twig-component` | The Twig functions, the filter, the table and the chart |
| `symfony/ux-live-component` | The component that computes the probabilities as they are typed |
| `symfony/rate-limiter` | The rate limit of the component, on by default: needed with `symfony/ux-live-component` unless it is turned off |
| `symfony/translation` | The words of the table, the chart and the component, in English and French |
| `ext-gmp` | The fractions whose terms are longer than 18 digits, written in their lowest terms |

Symfony UX 3.8.1 or later (TwigComponent 3.8) and Twig 3.31.1 or later, for their security fixes: older versions are
refused.

## Installing

```sh
composer require tilttek/dice-probability-bundle
```

Register both bundles, `TilttekDiceBundle` first:

```php
// config/bundles.php
return [
    // …
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle::class => ['all' => true],
];
```

Without `TilttekDiceBundle`, the container refuses to compile and says so. For the templates, register
`TwigComponentBundle`; for the component, `LiveComponentBundle` and `StimulusBundle` too, and the routes of Live
Components, as their documentation explains.

## The recipes of the UX kit

The table, the chart and the component are drawn with recipes of the PHIL UX kit, which the Symfony UX Toolkit
installs in the application, nothing being rendered from `vendor/`:

```sh
php bin/console ux:install alert card chart field input radio-group table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipes become the application's own components, styled by phil-ux, the stylesheet of `phil/ux-css`; an
application may change them. The bundle finds them under the components subdirectory of the `ux_toolkit`
configuration, or of `kit_subdirectory`, as the [configuration](configuration.md) explains.
