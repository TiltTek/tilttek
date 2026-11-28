<!-- phil-dev source-sha256=89e6669f570094c9c4a1f670bd0e74e18cc6b4856049cc742cb23ff9edf2516c -->
<!-- phil-dev source-sha256=0 -->
# Célébrer

## La prochaine célébration

`next($member, $deviceReducesMotion)` renvoie ce que la fenêtre montre ensuite — une `Celebration` — ou rien quand
rien n'attend ou que le membre a désactivé les célébrations :

| Champ | Ce qu'il contient |
|---|---|
| `group` | Le premier `Group` en attente : sa source, son genre, ses premières entrées et son compte |
| `motion` | Le `Motion` que joue la fenêtre : `Full`, `Reduced` ou `None` |
| `sound` | Si elle joue un son |
| `remaining` | Combien de groupes attendent après celui-ci |

Une fois la fenêtre montrée, la page marque le groupe comme vu — `markSeen($member, $celebration->group->sequences())`,
ou `markGroupSeen()` pour inclure les entrées qu'elle n'a pas montrées — et redemande `next()` tant que `remaining` est
supérieur à zéro.

## Préférences

`choose($member, $preferences)` garde les `Preferences` d'un membre ; `preferences($member)` les lit, avec les défauts
quand le membre n'en a pas choisi :

| Champ | Défaut | Ce qu'il décide |
|---|---|---|
| `celebrate` | `true` | Si une fenêtre célèbre ; désactivé, `next()` ne renvoie rien alors que `pending()`, `count()` et l'historique lisent la boîte comme avant |
| `motion` | `Motion::Full` | Combien la fenêtre bouge |
| `sound` | `false` | Si la fenêtre joue un son : jamais par défaut |

La page dit à `next()` si l'appareil du membre demande de réduire les mouvements — la fonctionnalité média
`prefers-reduced-motion`, lue dans le navigateur et envoyée avec la requête, ou un témoin que la page pose — : cela
réduit `Full` à `Reduced`, et n'ajoute jamais de mouvement à un membre qui a choisi `Reduced` ou `None`.
`Preferences::motionFor()` donne la même réponse au code qui construit sa propre fenêtre.

| Le `motion` du membre | L'appareil demande de réduire les mouvements | La fenêtre joue |
|---|---|---|
| `Full` | Non | `Full` |
| `Full` | Oui | `Reduced` |
| `Reduced` | L'un ou l'autre | `Reduced` |
| `None` | L'un ou l'autre | `None` |

La fenêtre elle-même — CSS et SVG légers, un fondu pour `Reduced`, une carte immobile pour `None`, le focus déplacé vers
elle puis rendu, annoncée aux lecteurs d'écran — appartient à l'application ou à un bundle.

## Écouteurs pour les paquets de récompenses

La boîte ne dépend d'aucun paquet de récompenses : l'application donne à chaque paquet qu'elle utilise un écouteur qui
transforme ses événements en réussites. Un écouteur pour les badges de `tilttek/badges` :

```php
use Tilttek\Badges\Award;
use Tilttek\Badges\AwardObserver;
use Tilttek\Badges\Badge;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\Celebrations;

final readonly class CelebrateBadges implements AwardObserver
{
    public function __construct(private Celebrations $celebrations)
    {
    }

    public function awarded(Award $award, Badge $badge): void
    {
        $this->celebrations->receive(new Achievement(
            $award->member,
            'badges',
            'badge',
            'badge.'.$award->badge,
            $award->badge.'#'.$award->number,
            $award->awardedAt,
        ));
    }

    public function revoked(Award $award, Badge $badge): void
    {
        $this->celebrations->withdraw($award->member, 'badges', $award->badge.'#'.$award->number);
    }
}
```

L'occurrence porte le numéro de l'attribution : un badge répétable obtenu deux fois est donc célébré deux fois, et un
avis signalé de nouveau est gardé une seule fois. Un niveau de `tilttek/progression` donne `progression`, `level`, la
clé de la piste et une occurrence faite de la piste, de la saison et du niveau atteint ; une quête de `tilttek/quests`,
`quests`, `quest`, la clé de la quête et son accomplissement. Chaque écouteur écrit une fois sa source et ses genres :
les traductions de l'application donnent leurs messages.
