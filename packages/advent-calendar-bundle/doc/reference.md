# Reference

Every class of the bundle, with its role. The services are registered under their class name.

## Bundle

| Class | Role |
|---|---|
| `TilttekAdventCalendarBundle` | The bundle: its configuration tree, the ORM mapping of `CalendarRecord`, and its services. |

## Application

| Class | Role |
|---|---|
| `Application\Contract\MemberResolver` | Who views the calendar: the current member's reference, or null for a visitor. |
| `Application\Contract\CalendarCatalogue` | Where the calendars come from; a calendar that is not published is not found. |
| `Application\Contract\MemberOpenings` | Every opening of a member, for their export. |
| `Application\NoMember` | The resolver of an application that names none: every viewer is a visitor. |
| `Application\Members` | The current member, refused when the reference looks like an e-mail address. |
| `Application\CalendarEntry` | A calendar of the library and its title. |
| `Application\ConfiguredCalendars` | The calendars of the configuration, checked when the container is built. |
| `Application\Calendars` | The calendars of several catalogues, asked in order; the service behind `CalendarCatalogue`. |
| `Application\Celebrations` | The first openings of the current request, for the screen to celebrate; emptied after each request. |
| `Application\VisitorBoxes` | The boxes as a visitor sees them, without any surprise. |

## Domain

| Class | Role |
|---|---|
| `Domain\Model\CalendarRecord` | A calendar the site edits, kept by the ORM; `toCalendar()` checks it as the library does. |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\AdventCalendarTables` | The table of the openings, added to a schema. |
| `Infrastructure\Doctrine\DbalOpeningStore` | The openings on Doctrine DBAL; the service behind `OpeningStore` and `MemberOpenings` with the `doctrine` store. |
| `Infrastructure\Doctrine\DoctrineCalendarCatalogue` | The calendars of the database, through the entity manager. |
| `Infrastructure\Doctrine\OpeningsSchemaListener` | Adds the table of the openings to the schema the ORM generates. |
| `Infrastructure\Memory\InMemoryOpenings` | The openings in memory, for tests; emptied after each request. |
| `Infrastructure\EventListener\CelebrationListener` | Keeps each first opening `BoxOpened` tells for the screen. |
| `Infrastructure\Form\CalendarType` | The form of a calendar, on a `CalendarRecord`. |
| `Infrastructure\Form\SurpriseType` | The form of one box's surprise. |
| `Infrastructure\Http\CalendarController` | The screen of a calendar and the opening of a box. |
| `Infrastructure\Console\ListCalendarsCommand` | `tilttek:advent-calendar:list`. |
| `Infrastructure\Console\ShowCalendarCommand` | `tilttek:advent-calendar:show`. |
| `Infrastructure\Console\CheckCalendarsCommand` | `tilttek:advent-calendar:check`. |
| `Infrastructure\Console\Policies` | An opening policy in the words of the commands. |
| `Infrastructure\Privacy\AdventCalendarPrivacy` | A member's export and erasure for `phil/privacy-bundle`. |
| `Infrastructure\Twig\AdventCalendarExtension` | The Twig function `advent_calendar_kit()`. |
| `Infrastructure\Twig\KitComponents` | The names of the kit's components, after their subdirectory. |
| `Infrastructure\DependencyInjection\AdventCalendarServices` | Registers the services the configuration asks for. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Takes the kit's subdirectory from the Symfony UX Toolkit. |

## Library services

| Service | Role |
|---|---|
| `Tilttek\AdventCalendar\AdventCalendar` | Opens a box for a member, with the application's clock and event dispatcher. |
| `Tilttek\AdventCalendar\Contract\OpeningStore` | The store of the openings, an alias. |

## Assets

| File | Role |
|---|---|
| `assets/src/boxes_controller.js` | The Stimulus controller `tilttek--advent-calendar--boxes`. |
| `assets/src/lib/boxes.js` | The reading of the server's answers, tested on its own. |
