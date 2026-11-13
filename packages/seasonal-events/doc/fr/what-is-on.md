<!-- phil-dev source-sha256=19cf966f07f96cb8aa0baa8884ec203283797c69774754be2c79980ece91a65a -->
# Ce qui est en cours

`SeasonalEvents` répond pour un moment — maintenant, selon l'horloge reçue, sauf si l'appel en nomme un. Il lit le
catalogue à chaque appel, de sorte qu'une saison modifiée dans une base de données est lue telle quelle.

## Un instantané

```php
$now = $seasons->at();

$now->isQuiet();                 // no season is on
$now->isOn('winter-festival');
$now->quests();                  // the references of the quests on, each once
$now->items();
$now->decorations();
$now->multiplier('points');      // the highest multiplier of the events running, or null
$now->seasons;                   // list<ActiveSeason>: each season on, with its Period
$now->events;                    // list<ActiveEvent>: each event running, with when it runs
$now->toArray();                 // for a JSON answer or a cache
```

Les contenus sont listés une fois chacun, ceux des saisons d'abord, puis ceux des événements, dans l'ordre du
catalogue.

## Ce qui s'en vient

| Appel | Répond |
|---|---|
| `announced($moment, $limit)` | Les événements annoncés qui n'ont pas encore commencé, le plus proche d'abord, au plus `$limit`, 50 au maximum : ceux des saisons en cours et de la prochaine venue de chaque saison. |
| `next($season, $after)` | La prochaine venue d'une saison, ou null quand elle est inconnue ou ne revient plus. |

Un `ActiveEvent` dit quand il se déroule, `from` et `until`, `announcedFrom()`, `isAnnouncedAt($moment)`, et sa `key()`,
la saison, le code de sa venue et l'événement, unique à ce déroulement.

## Le port que lisent les autres paquets

`Contract\ActiveContent` est ce que lit un paquet de quêtes, une boutique, un thème ou un registre ; `SeasonalEvents`
l'implémente :

```php
$content->active(ContentKind::Item);                                   // list<ResourceReference>
$content->isActive(ContentKind::Decoration, ResourceReference::fromString('decoration:snowflakes'));
$content->multiplier('points');
```

Un paquet qui dépend du port ne dépend d'aucune saison, et une application qui n'a aucune saison lui donne un port qui
ne répond rien.
