# Installation

## Requirements

- PHP 8.5 or later, with `sodium`.
- `tilttek/escape-room` 0.1 and `tilttek/puzzle-builder` 0.1.
- Doctrine DBAL 4.5, Twig 3.21, and Symfony 8.2's container, HTTP kernel, routing and CSRF protection.
- Optional: the Doctrine ORM, for the tables in the generated schema; Symfony Console, Scheduler, Mercure and Rate
  Limiter; Twig components and the PHIL UX kit, for the screens; `phil/reservation`, for the bookings;
  `phil/privacy-bundle`, for the export and erasure of a member.

## Installing

```sh
composer require tilttek/escape-room-bundle
```

With Symfony Flex, the recipe registers the bundle and copies `config/packages/tilttek_escape_room.yaml` and
`config/routes/tilttek_escape_room.yaml`. Without it, register the bundle:

```php
// config/bundles.php
return [
    // …
    Tilttek\EscapeRoomBundle\TilttekEscapeRoomBundle::class => ['all' => true],
];
```

and import the routes:

```yaml
# config/routes/tilttek_escape_room.yaml
tilttek_escape_room:
    resource: '@TilttekEscapeRoomBundle/config/routes.php'
```

## The tables

With the ORM, the five tables are added to the schema it generates for the connection of the configuration:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Without the ORM, create them from `EscapeRoomTables::configure()`, as [the database](database.md) describes.

## The access

Every screen but the leaderboard asks the application who the viewer is. Implement
`Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess` from your own members and staff, and name it:

```php
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\StoredGame;

final readonly class Access implements EscapeRoomAccess
{
    public function __construct(private Security $security, private Staff $staff, private Teams $teams)
    {
    }

    public function host(StoredGame $game): ?ResourceReference
    {
        $user = $this->security->getUser();

        return $user instanceof Member && $this->staff->runs($user, $game->room->id) ? ResourceReference::of('member', (string) $user->id) : null;
    }

    public function watches(StoredGame $game): bool
    {
        return $this->security->isGranted('ROLE_ROOM_SCREEN') || null !== $this->host($game) || $this->speaksForTeam($game);
    }

    public function speaksForTeam(StoredGame $game): bool
    {
        $user = $this->security->getUser();

        return $user instanceof Member && $this->teams->isMember($user, $game->game->team);
    }
}
```

```yaml
tilttek_escape_room:
    access: App\EscapeRoom\Access
```

Without it, `NoEscapeRoomAccess` refuses every console, screen and consent.

## The screens

The templates are built of the recipes `Card`, `Badge`, `Button`, `Alert` and `Celebration` of the PHIL UX kit, which
the application installs through the Symfony UX Toolkit:

```sh
php bin/console ux:install card badge button alert celebration --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The Stimulus controllers are in `assets/` of the bundle, as `@tilttek/escape-room`; with StimulusBundle and
AssetMapper, they are enabled in `assets/controllers.json` like those of any Symfony UX package.

## The schedule

To end the games whose countdown reached zero, enable the schedule and run a worker, as
[scheduling](scheduling.md) describes:

```yaml
tilttek_escape_room:
    schedule: { enabled: true }
```
