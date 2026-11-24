# Installation

```sh
composer require tilttek/advent-calendar-bundle
```

```php
// config/bundles.php
Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle::class => ['all' => true],
```

The recipe in `recipe/0.1` registers the bundle and copies its configuration and routes; until it is published on a
recipes repository, write them by hand:

```yaml
# config/routes/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    resource: '@TilttekAdventCalendarBundle/config/routes.php'
```

## Who opens a box

The bundle does not know the application's members. Implement `MemberResolver` and name the service in the
configuration; without it, every viewer is a visitor, who sees the boxes and opens none.

```php
use Phil\Resource\ResourceReference;
use Symfony\Bundle\SecurityBundle\Security;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;

final readonly class CurrentMember implements MemberResolver
{
    public function __construct(private Security $security)
    {
    }

    public function current(): ?ResourceReference
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? ResourceReference::of('member', (string) $user->id) : null;
    }
}
```

```yaml
tilttek_advent_calendar:
    member_resolver: App\Advent\CurrentMember
```

The reference is a stable identifier — never an e-mail address, which a member may change and which says who they are.
The bundle refuses a reference whose identifier holds `@`.

## Granting the surprises

The library dispatches `Tilttek\AdventCalendar\Event\BoxOpened` once, when a member first opens a box. The application
listens to it and grants the surprise: credits the points, awards the badge, reveals the content, hands out a code.
[Calendars](calendars.md#granting-the-surprises) shows a listener.

## The tables

With the ORM, `doctrine:migrations:diff` writes the migration of the two tables of [Database](database.md); without
it, create the table of the openings from `AdventCalendarTables::configure()` and declare the calendars in the
configuration.

## The screen

The screen extends the application's layout — `base.html.twig` unless the configuration names another — and fills its
block `body`. It is built of the recipes `Card`, `Badge`, `Button` and `Alert` of a UX Toolkit kit, such as the PHIL UX
kit, and of its `Celebration` when the celebration is enabled:

```sh
php bin/console ux:install card badge button alert celebration --kit=phil
```

The Stimulus controller ships in `assets/`; with Symfony UX's StimulusBundle and the AssetMapper or Webpack Encore, it
is enabled as `tilttek--advent-calendar--boxes`.

The opening form carries a CSRF token under the identifier `tilttek_advent_calendar_open`. With the stateless CSRF
protection of FrameworkBundle, add it to `framework.csrf_protection.stateless_token_ids`.

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31, with FrameworkBundle's CSRF protection and
forms. The ORM, the console, Twig components and `phil/privacy-bundle` are optional.
