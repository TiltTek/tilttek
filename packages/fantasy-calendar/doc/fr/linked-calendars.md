<!-- phil-dev source-sha256=d03ae0b47d78bebf8d3f9aad5996b95ac7340d38030a5b062b7c35bdd60730af -->
# Calendriers liés

Un monde compte souvent le temps de plusieurs façons : l'empire, les elfes, une guilde. `Calendars` les contient, ainsi
que les liens entre eux. Il est immuable : chaque ajout renvoie un nouveau registre.

## Liens

Un `Link` est un jour de la campagne que deux calendriers partagent : le couronnement est le jour 1 000 de l'empire et
le jour 10 de la ville. Un jour dure aussi longtemps dans les deux, de sorte que chaque jour se convertit :

```php
$world = Calendars::empty()
    ->with($empire)
    ->with($elves)
    ->linked(new Link(new Day('elves', 500), new Day('empire', 0)));

$world->convert(new Day('empire', 42), 'elves');      // Day(elves, 542)
```

Une conversion suit la plus courte chaîne de liens, de 16 au plus ; des calendriers qu'aucune chaîne ne joint sont
refusés avec `UnknownCalendar`. Deux calendriers ne sont liés qu'une fois.

## Parents et enfants

Un calendrier enfant est lié à son parent, le premier jour du lien dans l'enfant et le second dans le parent :

```php
$world = $world
    ->with($city)
    ->with($guild)
    ->withParent(new Link(new Day('city', 0), new Day('empire', 1000)))
    ->withParent(new Link(new Day('guild', 10), new Day('city', 0)));

$world->ancestors('guild');                            // ['city', 'empire']
```

Les rappels d'un parent, et de ses propres parents, apparaissent dans l'agenda de l'enfant : voyez
[Rappels](reminders.md). Un calendrier a un parent au plus, et un calendrier ne peut devenir le parent de l'un de ses
ancêtres.

## Bornes

Un registre contient au plus 256 calendriers ; `get()` refuse un identifiant qu'il ne contient pas.
