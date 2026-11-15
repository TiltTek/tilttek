<!-- phil-dev source-sha256=f5a4d508b1da9a761d0780796abec9247e7c6b1913fbf945ba7f4c0f092c0678 -->
# Célébrations

Un défi gagné est un moment à célébrer. La bibliothèque dit quand ; la page le joue, avec le composant de célébration
du kit PHIL UX ou autre chose. La bibliothèque ne dépend d'aucun kit.

## L'événement

`Event\ChallengeWon` est réparti une seule fois, quand une preuve est acceptée, après le paiement du lot : il porte le
défi, le gagnant, la preuve, le lot et son unité. Un écouteur avise le gagnant et les participants, puis la page montre
la célébration :

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\FriendChallenges\Event\ChallengeWon;

#[AsEventListener]
final readonly class CelebrateChallengeWon
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function __invoke(ChallengeWon $won): void
    {
        $this->notifier->tell($won->winner, 'challenge.won', ['challenge' => $won->challenge, 'prize' => $won->prize]);
    }
}
```

L'événement ne porte que des identifiants : un écouteur qui envoie un message de Messenger les envoie, et le *handler*
lit le reste dans le magasin.

## La page

La recette du kit se joue aussitôt sur la page qui montre le défi gagné :

```twig
{% if challenge.state.value == 'won' %}
    <twig:Celebration message="{{ 'challenge.won'|trans({'%prize%': challenge.prize.amount}) }}" effect="confetti" :autoplay="true" />
{% endif %}
```

ou sur un événement du DOM que la page répartit quand elle apprend la victoire, par Mercure par exemple :

```twig
<twig:Celebration message="{{ 'challenge.won'|trans }}" data-action="challenge:won@window->celebration#play" />
```

## Accessibilité

La célébration du kit dit son message dans une région vivante polie, ne dessine rien là où le mouvement est réduit
(`prefers-reduced-motion`), ne joue aucun son, dure quelques secondes au plus et ne clignote jamais plus de trois fois
par seconde. Le message nomme ce qui a été gagné — le défi et son lot — pour que le sens soit le même pour un lecteur
d'écran.
