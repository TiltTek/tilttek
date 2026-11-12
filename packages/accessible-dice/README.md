# tilttek/accessible-dice

**Dice anyone can roll: large targets, faces spoken and described, results announced to screen readers.**

tilttek/accessible-dice is a Symfony bundle on `tilttek/dice-bundle`. It draws a dice form whose every die is a large
choice, whose custom dice have each face described by its label, its shape and its value — never by its colour alone
—, and whose result is announced in a polite live region, in a sentence that says every die. The form sends the choice
itself, and the server builds the expression from it, so that it rolls the same with or without its script. When a
dice tower of `tilttek/dice-tower` is on the same page, each of its rolls is announced too. Nothing moves for a viewer
who asked for reduced motion, and nothing is read aloud unless the viewer asks for it.

## Status

| Part | State |
|---|---|
| The dice offered, standard and custom, and the expression of a choice, checked | Implemented and tested |
| The faces of the custom dice described by label, shape and value, in English and French | Implemented and tested |
| The sentence a roll is announced with | Implemented and tested |
| `accessible_dice()` and its form: large targets, a polite live region, reduced motion, no sound by default | Implemented and tested |
| The Stimulus controller: the expression shown, the rolls of a dice tower announced, reading aloud on request | Implemented and tested, in a browser |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the controller and its stylesheet. |
| [Configuration](doc/configuration.md) | Every key, its default and what it sets. |
| [The form](doc/form.md) | `accessible_dice()`, the choice sent, rolling it on the server, and announcing the result. |
| [Accessibility](doc/accessibility.md) | What the form does for screen readers, keyboards, motion and sound. |
| [Extension points](doc/extension-points.md) | Templates, styles, translations and listening to other dice. |
| [Testing](doc/testing.md) | The bundle's tests and its browser suite. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```sh
composer require tilttek/accessible-dice
```

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\AccessibleDice\TilttekAccessibleDiceBundle::class => ['all' => true],
];
```

```twig
{{ accessible_dice(path('roll'), result: result) }}
```

```php
$expression = $accessibleDice->expression($request->request->all('dice'));
$result = $diceRolls->roll($expression);
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `tilttek/dice-bundle`; Twig, the translator and StimulusBundle for the form.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
