<!-- phil-dev source-sha256=1db5ca0bfc51e940ecd75fa34cc442c249e2208a268a74187a77640724b46cd0 -->
# Célébrations

Une prime attribuée est un moment à célébrer. La bibliothèque dit quand ; la page le joue, avec le composant de
célébration du kit PHIL UX ou autre chose. La bibliothèque ne dépend d'aucun kit.

## L'événement

`Event\BountyAwarded` est réparti une fois pour chaque gagnant, quand la prime est réglée, après le paiement des
points : il porte la prime, le gagnant, les points qui lui sont payés et leur unité. `Event\BountyClosed` suit, avec
l'état et les points rendus. Un écouteur avise chaque gagnant, puis la page montre la célébration :

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\Bounties\Event\BountyAwarded;

#[AsEventListener]
final readonly class CelebrateBountyAwarded
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function __invoke(BountyAwarded $awarded): void
    {
        $this->notifier->tell($awarded->winner, 'bounty.awarded', ['bounty' => $awarded->bounty, 'points' => $awarded->amount]);
    }
}
```

L'événement ne porte que des identifiants : un écouteur qui envoie un message de Messenger les envoie, et le *handler*
lit le reste dans le magasin.

## La page

La recette du kit se joue aussitôt sur la page qui montre au gagnant sa prime :

```twig
<twig:Celebration message="{{ 'bounty.awarded'|trans({'%points%': payout.amount}) }}" effect="fireworks" :autoplay="true" />
```

ou sur un événement du DOM que la page répartit quand elle apprend l'attribution, par Mercure par exemple :

```twig
<twig:Celebration message="{{ 'bounty.awarded'|trans }}" data-action="bounty:awarded@window->celebration#play" />
```

## Accessibilité

La célébration du kit dit son message dans une région vivante polie, ne dessine rien là où le mouvement est réduit
(`prefers-reduced-motion`), ne joue aucun son, dure quelques secondes au plus et ne clignote jamais plus de trois fois
par seconde. Le message nomme ce qui a été gagné — la prime et ses points — pour que le sens soit le même pour un lecteur
d'écran.
