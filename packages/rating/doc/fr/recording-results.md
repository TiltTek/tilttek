<!-- phil-dev source-sha256=26d32e38c5a9a6d07a77c496339e0c907a2f45bfe1509d4e68e51b7741835a8f -->
# Enregistrer des résultats

## Un résultat

`GameResult` est une partie jouée : un identifiant stable, le jeu, le moment où elle a été jouée, ses camps et le rang
de chaque camp. Un camp est un joueur ou une équipe ; le rang 1 est le premier, et les camps du même rang ont fait nulle.

```php
GameResult::duel('play-81', 'chess', $at, 'member-1042', 'member-1077');              // the first won
GameResult::duel('play-82', 'chess', $at, 'member-1042', 'member-1077', draw: true);  // a draw
GameResult::placings('play-83', 'catan', $at, [['member-7'], ['member-3', 'member-9'], ['member-5']]);
new GameResult('play-84', 'pandemic-legacy', $at, [['m-1', 'm-2'], ['m-3', 'm-4']], [2, 1]);   // team 2 won
```

`placings()` liste les membres à partir de la première place, chaque place contenant ceux qui ont terminé ensemble. Un
résultat a de 2 à 64 camps de 1 à 32 membres, 256 membres au plus, chacun sur un seul camp. Les identifiants du
résultat, du jeu et des membres sont de 1 à 128 lettres, chiffres, `_`, `.`, `:`, `@` ou `-`, commençant par une lettre
ou un chiffre : une référence opaque, jamais un nom.

`toArray()` donne le résultat en valeurs simples et `fromArray()` les relit, le moment en ISO 8601 avec son décalage —
ce que garde une source de résultats.

## Enregistrer

```php
$changes = $ratings->record($result);
foreach ($changes as $change) {
    $change->member; $change->before; $change->after; $change->delta(); $change->peak;
}
```

`record()` refuse un résultat que son jeu compte déjà, lit la cote de chaque membre — la cote initiale pour la première partie cotée d'un membre à ce jeu —, cote le
résultat avec le système, et garde d'un coup les nouvelles cotes de tous les membres. Un changement est en centièmes :
la cote Elo, ou l'estimation prudente d'une habileté. `peak` est vrai quand le membre a dépassé le score le plus haut
qu'il avait atteint, une fois qu'il a joué les parties cotées que demande `celebrateAfter`.

Un résultat s'enregistre une fois. Le dépôt de stockage compte l'identifiant de chaque résultat qu'il a coté, jeu par
jeu, dans la même écriture que les cotes : enregistrer de nouveau le même résultat — un double clic, un message livré
deux fois — lance `Exception\ResultAlreadyRecorded`, ne garde rien et n'envoie rien. Deux enregistrements du même
résultat en même temps ne comptent jamais tous les deux : la première écriture compte le résultat, et la seconde est
refusée, par le résultat ou par les versions des cotes. Une relecture compte de nouveau les résultats qu'elle a cotés,
à partir de sa source ; un résultat amical n'est jamais compté, donc l'enregistrer deux fois ne change rien deux fois.

## Résultats amicaux

Un résultat construit avec `friendly: true` est joué hors des cotes : `record()` ne change rien, ne garde rien et
n'envoie rien. Une relecture le lit à sa place dans l'ordre, et le saute.

## Les événements

Une fois les cotes gardées, `record()` envoie, membre par membre dans l'ordre du résultat, `Event\RatingChanged`, puis
`Event\PeakReached` pour un membre dont le changement est un nouveau score record. Voir [Célébrations](celebrations.md).
