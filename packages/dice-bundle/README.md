# tilttek/dice-bundle

**Dice for a Symfony application: configured, rolled, kept to be replayed, checked in forms and shown in templates.**

tilttek/dice-bundle integrates `tilttek/dice` with Symfony. It builds the library from the configuration — its limits,
the custom dice of the application, the notations of more languages —, rolls from a secure source, keeps replayable
rolls in a Doctrine journal — by member and by table, each one announced by an event — and replays them, keeps the
macros of a site, a table and a member, makes verifiable rolls, and gives a form field with its constraint, Twig
filters and functions, and an accessible detail of a roll. An extension point dresses a member's dice with the
appearance of a set they own — colour, ink, material, texture —, and the numbers always read on it. It does not animate
dice, draw the roller's page, decide who may roll, nor own any dice set: the application does.

It is for Symfony applications that roll dice for their members: a dice roller page, a play-by-post forum, a character
sheet, a game table. An application without Symfony takes `tilttek/dice` alone.

It is an `AbstractBundle` on Symfony 8.2 with Doctrine ORM 3. The form type, the constraint and the Twig extension are
registered only when Symfony Form, Validator and Twig are installed. A member is an opaque `ResourceReference` of
`phil/resource`: the bundle reads no name, address or account.

The design is small: the bundle class reads the configuration, `DiceServices` registers the services, `DiceRolls` is the
service the application calls, `DiceMacros` keeps and resolves macros, `RecordedRoll` is a row of the journal,
`RollRecorded` announces it, and `DiceAppearance` is the port that dresses a member's dice.

## Status

| Part | State |
|---|---|
| Configuration: limits, custom dice, notations, appearance, translation domain, store, entity manager | Implemented and tested |
| `DiceRolls`: parse, read DiceParser, write, translate, roll, replayable rolls, replay, verifiable rolls and verification, a member's rolls and their erasure | Implemented and tested |
| Journal of replayable rolls in Doctrine, or in memory: context of a roll, rolls by owner, by context and many at once | Implemented and tested |
| `RollRecorded` event | Implemented and tested |
| `DiceMacros`: macros of a site, a table and a member, named by token, in Doctrine or in memory | Implemented and tested |
| `DiceAppearance` extension point: colour, ink, material, image, texture; an ink that reads at 4.5:1 when none is given | Implemented and tested |
| `DiceExpression` constraint and `DiceExpressionType` field | Implemented and tested |
| Twig: `dice_notation`, `dice_dialect`, `dice_roll`, `dice_roll_detail`, `dice_text` | Implemented and tested |
| Dialects added by tag | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the journal's table. |
| [Configuration](doc/configuration.md) | Every option of `tilttek_dice`, with its default. |
| [Rolling](doc/rolling.md) | `DiceRolls`: plain, replayable and verifiable rolls, the journal and its contexts, erasing a member's rolls. |
| [Macros](doc/macros.md) | `DiceMacros`: the shelves of a site, a table and a member, defining, resolving and erasing macros. |
| [Forms and validation](doc/forms.md) | `DiceExpressionType` and the `DiceExpression` constraint. |
| [Templates](doc/templates.md) | The Twig filters and functions, the detail of a roll, its classes and its words. |
| [Extension points](doc/extension-points.md) | `DiceAppearance` and the readable ink, dialects by tag, the journal's port, `RollRecorded`. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that rolls dice. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |
| [Upgrading](doc/upgrading.md) | From 0.1 to 0.2: the context column, the journal's port, the appearance. |

## Quick start

```sh
composer require tilttek/dice-bundle
```

```yaml
# config/packages/tilttek_dice.yaml
tilttek_dice:
    custom_dice:
        coin:
            faces:
                - { id: heads, label: 'Heads', value: 1, shape: circle }
                - { id: tails, label: 'Tails', value: 0, shape: square }
```

```php
use Tilttek\DiceBundle\Application\DiceRolls;

public function __invoke(DiceRolls $dice): Response
{
    $recorded = $dice->rollReplayable('4d6kh3 + 1d{coin}');

    return $this->render('dice/roll.html.twig', ['replay' => $dice->replay($recorded->id())]);
}
```

```twig
{{ dice_roll_detail(replay.result) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine ORM 3.7, `tilttek/dice` and `phil/resource` 0.1. Symfony Form, Validator, Twig
and Translation are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
