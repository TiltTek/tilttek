# Installation

```sh
composer require tilttek/dice-tower
```

The bundle needs PHP 8.5 or later, Symfony 8.2 and `tilttek/dice-bundle`, whose journal holds the rolls. Register
both:

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceTower\TilttekDiceTowerBundle::class => ['all' => true],
];
```

## The log

With DoctrineBundle, the table `tilttek_dice_tower_drop` is mapped: create it with a migration.

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Who may watch a table

Nobody may until the application says who: name a service that implements `TableAccess` — see the
[extension points](extension-points.md).

```yaml
# config/packages/tilttek_dice_tower.yaml
tilttek_dice_tower:
    table_access: App\Dice\TableMembers
```

## The hub

`symfony/mercure-bundle` gives the hub and the authorization cookie of the viewers:

```sh
composer require symfony/mercure-bundle
```

Its hub service, `Symfony\Component\Mercure\HubInterface`, is the one the bundle pushes to. Without it, nothing is
pushed: the overlay shows the entries of the page and reads the missed ones when it is drawn again.

## The route of the missed entries

```yaml
# config/routes/tilttek_dice_tower.yaml
tilttek_dice_tower:
    resource: '@TilttekDiceTowerBundle/config/routes.php'
```

## The overlay

The overlay is drawn with the `Card` recipe of the PHIL UX kit, which the Symfony UX Toolkit installs, and its
controller is registered by StimulusBundle from the package's `assets/package.json`:

```sh
composer require symfony/stimulus-bundle symfony/ux-twig-component symfony/ux-toolkit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
```

## Optional packages

| Package | What it adds |
|---|---|
| `symfony/mercure-bundle` | The hub each new entry is pushed to, and the authorization cookie of the viewers |
| `symfony/twig-bundle`, `symfony/ux-twig-component` | `dice_tower()` and the overlay |
| `symfony/stimulus-bundle` | The controller that adds the new entries on the page |
| `symfony/routing` | The route of the missed entries |
| `symfony/messenger` | The relays reached through a message bus |
