# Linked calendars

A world often counts time in several ways: the empire, the elves, a guild. `Calendars` holds them and the links between
them. It is immutable: each addition returns a new registry.

## Links

A `Link` is a day of the campaign two calendars share: the coronation is day 1 000 of the empire and day 10 of the
city. A day lasts as long in both, so every day converts:

```php
$world = Calendars::empty()
    ->with($empire)
    ->with($elves)
    ->linked(new Link(new Day('elves', 500), new Day('empire', 0)));

$world->convert(new Day('empire', 42), 'elves');      // Day(elves, 542)
```

A conversion follows the shortest chain of links, at most 16 of them; calendars no chain joins are refused with
`UnknownCalendar`. Two calendars are linked once.

## Parents and children

A child calendar is linked to its parent, the first day of the link in the child and the second in the parent:

```php
$world = $world
    ->with($city)
    ->with($guild)
    ->withParent(new Link(new Day('city', 0), new Day('empire', 1000)))
    ->withParent(new Link(new Day('guild', 10), new Day('city', 0)));

$world->ancestors('guild');                            // ['city', 'empire']
```

The reminders of a parent, and of its own parents, appear in the agenda of the child: see [Reminders](reminders.md). A
calendar has one parent at most, and a calendar cannot become the parent of one of its ancestors.

## Bounds

A registry holds at most 256 calendars; `get()` refuses an identifier it does not hold.
