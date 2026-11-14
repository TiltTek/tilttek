<!-- phil-dev source-sha256=e630f74f93b99f2bcca98452315cceef71091dfdd75734d87456d408e422eee5 -->
# Événements

La projection et le balayage avertissent le répartiteur d'événements de l'application :

| Événement | Transmis quand | Porte |
|---|---|---|
| `Tilttek\ProgressionBundle\Infrastructure\Event\LevelUp` | Un sujet atteint un niveau plus élevé d'une piste | `change` : un `LevelChange` (piste, sujet, saison, de, vers, moment, activité) |
| `Tilttek\ProgressionBundle\Infrastructure\Event\LevelDown` | Une annulation ramène un sujet à un niveau inférieur | `change` |
| `Tilttek\Progression\ReachedMilestone` | Un sujet dépasse un jalon, une fois par saison | piste, jalon, sujet, membre, saison, moment, activité |
| `Tilttek\Progression\Streak\StreakBroken` | La série d'un membre se brise, une fois | série, membre, longueur, dernière unité active, moment |

## Récompenser un jalon

```php
#[AsEventListener]
final readonly class RewardMilestones
{
    public function __construct(private Points $points) {}

    public function __invoke(ReachedMilestone $reached): void
    {
        $this->points->grant($reached->member, 100, 'milestone:'.$reached->track.':'.$reached->milestone.':'.$reached->subject->toString().':'.$reached->season);
    }
}
```

La clé fait que l'attribution n'est écrite qu'une fois, même si l'écouteur s'exécute deux fois — et cela peut
arriver : `LevelUp`, `ReachedMilestone` et `StreakBroken` sont répartis au moins une fois. Un écouteur qui échoue laisse
son annonce due, gardée dans la colonne `delivered` des gains et des jalons, et l'événement est réparti de nouveau
quand l'entrée est remise de nouveau, avec la prochaine activité du sujet, ou par `ProgressionProjection::deliver()`. Pendant une
reconstruction, seuls les niveaux au-dessus de ceux atteints avant sont annoncés ; ni un niveau perdu ni une série
brisée ne le sont, et les jalons ne sont jamais annoncés de nouveau.
