<!-- phil-dev source-sha256=08fc51211cad7b2f8bf2a334c198c7aa17114ac4f8d5e1d939cc9f9e30b01972 -->
# Projections

Une projection est un lecteur du journal qui tient son propre état — les points d'un membre, la progression d'un badge,
les parties jouées cette saison — pour que le journal ne soit lu en entier que lorsqu'une règle change.

## En écrire une

```php
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;

final class GamesPlayed implements Projection
{
    public function name(): string { return 'games_played'; }

    public function filter(): Filter { return Filter::all()->verbs('game.played'); }

    public function apply(Entry $entry): void
    {
        $this->store->add($entry->activity->member, $entry->isCancellation() ? -1 : 1, $entry->position);
    }

    public function reset(?DateTimeImmutable $since): void
    {
        $this->store->forgetFrom($since); // everything when $since is null
    }
}
```

- **`name()`** est le nom sous lequel son point de reprise est gardé : de 1 à 64 lettres minuscules, chiffres, `_`,
  `.` ou `-`, en commençant par une lettre. Deux projections ne peuvent pas partager un nom.
- **`filter()`** indique quelles activités elle lit ; les autres ne lui sont pas remises.
- **`apply()`** reçoit une activité enregistrée, ou l'annulation d'une activité, à défaire. Un plantage entre
  l'application et l'enregistrement du point de reprise remet les mêmes entrées : gardez les positions appliquées, ou
  faites en sorte que `apply()` tolère une répétition.
- **`reset()`** oublie ce qui a été dérivé des activités à partir de `since`, avant qu'elles soient remises de nouveau.

## Le projecteur

```php
use Tilttek\Activity\Projection\Projector;

$projector = new Projector($log, $checkpoints, batchSize: 500);
$projector->catchUp($gamesPlayed);              // the entries it has not seen
$projector->rebuild($gamesPlayed, $seasonStart); // a rule changed: replay the season
$projector->lag($gamesPlayed);                   // how many entries were written after its checkpoint
```

- **Rattraper** remet à la projection les entrées situées après son point de reprise, par lots. Le point de reprise
  avance après chaque lot : un échec perd au plus un lot, que l'exécution suivante remet. Les entrées que le filtre
  exclut font aussi avancer le point de reprise, jusqu'à la fin du journal tel qu'il était au début de l'exécution.
- **Reconstruire** remet la projection à zéro à partir de `since` — en entier sans lui — et lui remet de nouveau les
  activités de cette période, depuis le début du journal. Quand le filtre de la projection commence plus tard, le début
  le plus tardif l'emporte.
- **Une exécution à la fois.** Deux exécutions de la même projection au même moment appliqueraient deux fois les mêmes
  entrées : lancez les rattrapages depuis un seul *worker*, ou sous un verrou.

`Checkpoints` garde la position de chaque projection sous son nom ; `InMemoryCheckpoints` est celui en mémoire.

## Projections en direct

```php
$journal = new Journal($log, $clock, observers: [new LiveProjections($projector, new Projections([$gamesPlayed]))]);
```

`LiveProjections` rattrape, après chaque nouvelle entrée, chaque projection dont le filtre la lit. Si une projection
échoue, l'entrée reste enregistrée et le point de reprise de la projection reste avant elle : le rattrapage suivant la
remet. Une application qui a beaucoup de projections, ou des projections lentes, les garde hors de la requête et les
rattrape plutôt depuis une tâche planifiée.
