# tilttek/dice-tower

**A table's shared dice tower: every roll seen by everyone at the table, in order, as it falls.**

tilttek/dice-tower is a Symfony bundle on `tilttek/dice-bundle`. A roll of the journal of the dice is dropped in a
table's tower; the tower keeps where it fell, in a log of its own, and pushes the new entry to a private Mercure topic
of the table, which only the viewers the application allows subscribe to. A viewer who was away reads what was missed
from the log, by position: nothing is kept in memory between two requests. The entries carry identifiers and the
result of the roll, never a person's name. A Twig and Stimulus overlay, drawn with the PHIL UX kit, shows the tower and
adds each new roll; relays carry each entry to other channels, through adapters of their own packages.

## Status

| Part | State |
|---|---|
| The log of the towers, in Doctrine or in memory, its positions given by the database | Implemented and tested |
| `DiceTower`: rolls dropped, entries read in order, missed entries read again, erasure of a roller | Implemented and tested |
| The private topic of each table on a Mercure hub, and the authorization of its viewers | Implemented and tested |
| The route of the missed entries, for allowed viewers only | Implemented and tested |
| `dice_tower()`, the overlay drawn with the PHIL UX kit and its Stimulus controller, in English and French | Implemented and tested |
| The relays, at once or through a message bus | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the route, the hub and the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every key, its default and what it sets. |
| [The tower in PHP](doc/tower.md) | Rolling and dropping, reading the entries, the payload, privacy and long-running processes. |
| [The overlay](doc/overlay.md) | `dice_tower()`, the overlay, its Stimulus controller and its events. |
| [Extension points](doc/extension-points.md) | Who may watch a table, relays to other channels, publishers, templates and translations. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that uses a tower. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```sh
composer require tilttek/dice-tower symfony/mercure-bundle
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceTower\TilttekDiceTowerBundle::class => ['all' => true],
];
```

```yaml
# config/packages/tilttek_dice_tower.yaml
tilttek_dice_tower:
    table_access: App\Dice\TableMembers

# config/routes/tilttek_dice_tower.yaml
tilttek_dice_tower:
    resource: '@TilttekDiceTowerBundle/config/routes.php'
```

```php
$tower->roll(ResourceReference::of('table', '7'), '1d20+5', ResourceReference::of('member', '42'));
```

```twig
{{ dice_tower('table:7', {'member:42': 'Aelis'}) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, `tilttek/dice-bundle`, Doctrine ORM for the log and a Mercure hub for the live entries;
Twig, Symfony UX and the recipes of the PHIL UX kit for the overlay.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
