<!-- phil-dev source-sha256=c9a0fb6e1d6e7bbda6141f5d557129115ba62df3a52972117590e859f4f95a7b -->
# Instantanés

## Figer une période

```php
$snapshot = $leaderboards->snapshot('games-month', '2026-10');
```

`snapshot()` fige un classement à la fin d'une période : ses premiers membres (autant que le classement en garde), leurs
rangs, leurs scores et les moments où ils les ont atteints, le nombre de membres classés et le moment où il a été pris.
Une période qui n'est pas terminée lève `PeriodNotOver`, tout comme `ever`, qui ne finit jamais. Un instantané se prend
une seule fois par classement et par période : redemandé, celui qui a été pris est retourné, et l'observateur n'est pas
informé de nouveau.

`snapshotDue()` prend, pour chaque classement, l'instantané de la période qui s'est terminée en dernier (hier, la
semaine dernière, le mois dernier, la dernière saison terminée) quand il n'en a pas encore, et retourne ceux qu'elle a
pris. Un planificateur l'exécute peu après minuit ; une activité enregistrée en retard pour une période déjà figée
change ses scores, pas son instantané.

## Récompenser les premiers

Le `LeaderboardObserver` reçoit `snapshotTaken()` une fois par instantané. Un écouteur récompense les premiers membres
par les paquets dont dispose l'application (des points, un badge, un titre) :

```php
final class RewardTheFirst implements LeaderboardObserver
{
    public function snapshotTaken(Snapshot $snapshot): void
    {
        foreach (array_slice($snapshot->placings, 0, 3) as $placing) {
            // grant something to $placing->member for $placing->rank
        }
    }
}
```

Récompenser un membre caché relève de l'application : le choix du membre porte sur le fait d'être montré, pas sur celui
d'être classé.

## Montrer le classement final

`frozen()` retourne l'instantané sous forme de `Standing` vu par un spectateur, selon le choix actuel de chaque membre :
ses `limit` premières lignes (de 1 à 1000, 10 par défaut), la ligne du spectateur quand il y figure, le nombre de
membres classés et le moment où il a été figé (`Standing::frozenAt`). Elle retourne null quand aucun instantané n'a été
pris.

Effacer un membre retire ses lignes de chaque instantané ; les autres gardent leurs rangs.
