<!-- phil-dev source-sha256=12527b1184b7a4a36ba51a69d09fd3842e431e2057f4af6dc1d0e35a34cea484 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les tournois | Implémentez `Contract\TournamentStore`, et passez-le à `Tournaments`. |
| Ordonner les participants par force | Implémentez `Contract\SeedingSource`, ou utilisez `Seeding\FixedSeeding` ou `Seeding\RatingSeeding`. |
| Départager les égalités à votre façon | Implémentez `Contract\Tiebreaker`, et passez-le à `Tiebreak\Tiebreakers`. |
| Apparier exactement de plus grands groupes, ou chercher plus longtemps | Passez à `Tournaments` un `Swiss\SwissPairer` avec ses `exactLimit` et `passes`. |
| Célébrer le podium | Répartissez l'`Event\TournamentFinished` que porte un `Recording`. |

## Le dépôt

`Contract\TournamentStore` garde chaque tournoi sous forme de `TournamentState`, à une version : `get()` le lit, `save()`
le garde à la version suivante. Un état à la version 0 est nouveau, et le dépôt n'a aucun tournoi de son identifiant ;
tout autre n'est gardé que si le dépôt le détient à cette version, et `TournamentConflict` est levée sinon, sans rien
garder. Deux changements faits en même temps ne s'écrasent jamais : celui qui est refusé est refait sur le tournoi lu de
nouveau.

`Store\InMemoryTournamentStore` garde les tournois en mémoire, tant que l'objet dure : il sert aux tests. Une application
les garde dans sa base de données, l'état sérialisé comme elle l'entend ; l'état ne contient que des identifiants, des
nombres et des dates.

## La source de classement initial

`Contract\SeedingSource::strengths()` donne une force à chaque participant qu'elle connaît, la plus forte étant la plus
haute ; un participant sans force est placé après les autres, dans l'ordre de la graine. `Seeding\FixedSeeding` prend
les forces données, ou une liste en ordre avec `FixedSeeding::inOrder()`. `Seeding\RatingSeeding` lit les scores des
cotes d'un jeu dans `tilttek/rating`, qu'une application installe à côté de cette bibliothèque :

```php
$tournaments = new Tournaments($store, seeding: new RatingSeeding($ratings, 'chess'));
```

Le classement initial montre un ordre, jamais un score.

## L'événement

`Tournaments` renvoie les événements qu'il lève dans le `Recording` de l'appel qui les a levés, et ne répartit rien :
une application les répartit sur son propre bus. Un écouteur indexe ce qu'il fait par `TournamentFinished::key()`, de
sorte qu'un podium annoncé de nouveau ne fait rien de plus.

## Compatible avec les workers

`Tournaments` et tout ce qu'il utilise ne gardent rien entre les appels : ils peuvent vivre aussi longtemps qu'un
worker, et servir plusieurs tournois à la fois.
