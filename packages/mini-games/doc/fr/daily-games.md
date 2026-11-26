<!-- phil-dev source-sha256=7576678bfaa542278d539d5c51e342c46d0bd2a5977c11e640653639f93095d9 -->
# Jeux quotidiens

`DailyGames` donne à chaque joueur d'une journée le même jeu de chaque mini-jeu, à une table qui lui est propre.

## La graine du jour

`DailySeed::for($game, $day)` est le HMAC-SHA256 du nom du jeu et de la journée, `bulls-and-cows` et `2026-11-24`, écrit
en 64 chiffres hexadécimaux : la graine avec laquelle s'ouvre une table de `tilttek/game-engine`. Sa clé est dérivée du
secret du site par HKDF-SHA256 sous l'étiquette `DailySeed::USAGE`, `tilttek_mini_games.daily_seed`, de sorte que le
même secret employé pour autre chose ne donne jamais les mêmes hachages.
Chaque joueur de la journée reçoit la même graine, donc la même mise en place et le même hasard ;
un autre jour, un autre jeu ou le secret d'un autre site donne une autre graine, et personne ne peut calculer celle de
demain sans le secret.

Un `Day` est un jour du calendrier tel que le site les compte : `Day::of($now, $timezone)` donne le jour où tombe un
instant dans le fuseau horaire du site, et `Day::fromString('2026-11-24')` en lit un, à partir de `2000-01-01`. Les
jours se comparent comme le fait le calendrier, par leur texte, et `numberFrom($first)` les numérote à partir d'un
premier jour, qui est le 1 : le numéro du jeu quotidien, montré dans son partage.

## La table du joueur

| Appel | Ce qu'il fait |
|---|---|
| `start($game, $day, $player)` | Ouvre la table du joueur pour ce jeu et ce jour, ou la rend telle quelle |
| `play($game, $day, $player, $type, $payload, $expected)` | Joue un coup du jeu pour le joueur ; refusé par `ConcurrentMove` quand `$expected` n'est pas le nombre de coups |
| `view($game, $day, $player, $preferences)` | Ce que le joueur voit de sa table |
| `share($game, $day, $player)` | Le résultat du joueur à partager |
| `ending($game, $day, $player, $best)` | Comment la partie s'est terminée pour le joueur, ou nul tant qu'elle se poursuit |
| `finishedLog($game, $day, $player, $today)` | Le journal de la table terminée, graine comprise, une fois la journée terminée |

L'identifiant de la table est `daily:<game>:<day>:<player>`, de `DailyGames::tableId()`. L'identifiant du joueur est une
valeur opaque de l'application — une lettre ASCII, puis des lettres, des chiffres, `:`, `.`, `_` ou `-` —, jamais un nom
ni une adresse.

Un joueur joue chaque jeu une fois par jour : `start()` à la visite suivante rend la même table, terminée ou non. Une
table d'un jour passé reste telle qu'elle était ; un jour manqué n'ouvre aucune table et ne coûte rien.

## Le puits de scores

Quand le coup qui termine une partie est joué, `DailyGames` envoie un `DailyScore` au puits de scores, une seule fois :
le jeu, le jour, l'identifiant du joueur, s'il a gagné, le score que rapporte le jeu — nul quand il n'en rapporte
aucun — et le sens des scores. Un adaptateur de l'application le transforme en ce que lisent ses classements : une
activité de `tilttek/activity` dont un tableau de `tilttek/leaderboards` garde le meilleur de la quantité, par exemple.

```php
final readonly class LeaderboardScoreSink implements ScoreSink
{
    public function record(DailyScore $score): void
    {
        if ($score->won && null !== $score->score) {
            // Record an activity of the player for the game, with the score as its quantity.
        }
    }
}
```

Le puits ne porte que des identifiants et des nombres ; un puits qui diffère son travail par Symfony Messenger les
envoie tels quels.

## Vérifier le jeu d'une journée

Le moteur montre à chaque joueur l'engagement envers la graine de sa table dès l'ouverture — `Tables::commitment()` —,
et la graine elle-même dans le journal d'une table terminée. Comme tous les joueurs d'une journée partagent la graine,
`finishedLog()` la refuse par `SeedNotRevealable` tant que la journée n'est pas terminée : étant donné le jour courant,
il ne montre que le journal d'un jour antérieur. N'importe qui peut alors rejouer le journal et vérifier chaque tirage
contre l'engagement, comme le décrit `tilttek/game-engine`.
