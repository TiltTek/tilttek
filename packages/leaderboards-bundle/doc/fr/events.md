<!-- phil-dev source-sha256=2b6f8bde0c292b435182f872885954cf9ea9a933d3e629719811e7a046411497 -->
# Événements

| Événement | Transmis quand | Contient |
|---|---|---|
| `Tilttek\LeaderboardsBundle\Infrastructure\Event\SnapshotTaken` | Un instantané est pris, une fois par classement et par période | `snapshot`, le `Snapshot` de la bibliothèque : classement, période, moment, nombre de membres classés et les `Placing` des premiers membres |
| `Tilttek\LeaderboardsBundle\Infrastructure\Event\LeagueSettled` | Une semaine d'une ligue est réglée, une fois par classement et par semaine | `settlement`, le `Settlement` de la bibliothèque : classement, semaine, moment, et chaque `Membership` avec son `Movement` |
| `Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached` | Un membre a atteint le sommet, une fois par classement, par période et par membre : premier d'un instantané, juste après celui-ci (plusieurs dans un classement dont les égalités sont partagées), avec `celebrate_on: period_close` ; en tête de la période en cours à mesure que son score est gardé, avec `celebrate_on: score_change` ; ou promu dans le palier le plus haut d'une ligue, juste après le règlement | `board` (la clé du classement, avec l'empreinte de l'objet dans un classement par objet), `period` (le code de la période ou de la semaine), `member`, `summit` (`Summit::FirstPlace`, `Summit::Lead` ou `Summit::HighestTier`), `at`, le moment où l'instantané a été pris, où le score a été atteint ou où la semaine a été réglée, et `appearance`, la façon dont le membre a choisi de paraître |

## Récompenser les premiers

```php
#[AsEventListener]
final readonly class RewardTheFirst
{
    public function __invoke(SnapshotTaken $event): void
    {
        foreach (array_slice($event->snapshot->placings, 0, 3) as $placing) {
            // grant points, a badge or a title to $placing->member for $placing->rank
        }
    }
}
```

L'instantané nomme chaque membre qu'il classe, caché ou non : récompenser ou non un membre caché relève de
l'application.

## Féliciter ceux qui sont montés

```php
#[AsEventListener]
final readonly class CongratulateThePromoted
{
    public function __invoke(LeagueSettled $event): void
    {
        foreach ($event->settlement->moved(Movement::Promoted) as $membership) {
            // tell $membership->member they reached the tier after $membership->tier
        }
    }
}
```

## Célébrer le sommet

```php
#[AsEventListener]
final readonly class AnnounceTheTop
{
    public function __invoke(TopReached $event): void
    {
        // send $event->member a notification whose link opens the board's page, which includes
        // @TilttekLeaderboards/celebration.html.twig — see the templates
    }
}
```

`TopReached` suit l'événement qui le dit : un écouteur de `SnapshotTaken` ou de `LeagueSettled` a donc déjà été exécuté.
Ses règles sont celles de `tilttek_leaderboards_celebration()`, que l'écran lit plus tard : le membre qui l'ouvre voit la
célébration, qu'un écouteur le lui ait dit ou non.

## Quand une première place est dite

`celebration.celebrate_on` est le choix du site ([Configuration](configuration.md)).

- **`period_close`**, la valeur par défaut : une première place est dite une fois la période close, par l'instantané. Le
  membre l'apprend quand la période est terminée, et le classement final la soutient.
- **`score_change`** : une première place est dite au moment où elle arrive. `LeadWatch` décore le stockage de scores
  que reçoit la bibliothèque ; quand un score gardé dans la période en cours place son membre premier (ou premier avec
  d'autres, dans un classement dont les égalités sont partagées) parmi au moins deux membres, et qu'il ne l'était pas
  avant, la première place est réclamée dans `tilttek_leaderboards_lead` et un `TopReached` de `Summit::Lead` est
  transmis. Un membre est informé une seule fois par classement et par période : reprendre la tête après l'avoir perdue
  n'apprend rien de nouveau, pas plus qu'une reconstruction de la projection, qui compte de nouveau les mêmes
  activités. Une valeur rapportée passe par `keep()` : celle que le stockage ne garde pas ne prend pas la tête. Une période terminée n'est jamais surveillée. L'instantané ne dit alors aucune première place ; un écouteur
  de `SnapshotTaken` récompense tout de même le classement final.

Chaque score gardé lit le score que le membre détenait et son rang avant et après : deux ou trois lectures indexées de
plus par score, les participants n'étant comptés que lorsque le membre est premier. L'événement est transmis dans le
processus qui garde le score (la projection qui suit le journal, ou la requête qui rapporte une valeur), dans le même
travail : un écouteur qui a un travail lourd s'envoie un message.

## Le dire aux autres

Chaque membre est caché jusqu'à ce qu'il choisisse de paraître, et les événements respectent ce choix.
`TopReached::$appearance` est l'`Appearance` du membre au moment de l'événement : un écouteur qui le dit aux autres (un
fil, un message dans un canal, un écran dans un lieu) ne nomme le membre que lorsque `appearance->visibility` vaut
`Visibility::Named`, écrit `appearance->pseudonym` pour `Visibility::Pseudonym`, et ne dit rien de son identité pour
`Visibility::Hidden` :

```php
#[AsEventListener]
final readonly class TellTheChannel
{
    public function __construct(
        private MemberNames $members, // the application's own service that names a member
    ) {
    }

    public function __invoke(TopReached $event): void
    {
        $who = match ($event->appearance->visibility) {
            Visibility::Named => $this->members->name($event->member),
            Visibility::Pseudonym => $event->appearance->pseudonym,
            Visibility::Hidden => null, // congratulate the member alone, tell the others nothing
        };
    }
}
```

Le membre est toujours informé lui-même, quel que soit son choix : la célébration ne s'affiche que pour lui.

Les autres événements sont transmis dans le processus qui prend l'instantané ou règle la semaine : le *worker* du
planificateur, une commande, ou la requête d'un membre qui entre dans la semaine suivante. Un écouteur qui a un travail
lourd s'envoie un message qui porte le classement et la période, et lit le reste quand le message est traité.
