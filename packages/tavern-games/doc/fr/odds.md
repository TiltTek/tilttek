<!-- phil-dev source-sha256=b2133964bfb9e57264bbab10bce44791cac407b7fa61ff23daefe2d8a347d0d8 -->
# Chances

## L'estimateur

`Odds\Estimator::estimate($simulation, $samples, $randomizer)` joue une position jusqu'à sa fin `$samples` fois — de 1 à
100 000 — et compte comment chaque fin s'est passée pour le siège qu'on lui désigne.

```php
$estimate = Estimator::estimate($game->chase($state), 10_000, new Randomizer(new Secure()));

$estimate->toArray(); // ['wins' => 6120, 'ties' => 830, 'losses' => 3050, 'samples' => 10000]
```

Un `Odds\Estimate` garde les victoires, les égalités et les défaites à part, avec `samples()` le nombre de fins sur
lesquelles elles ont été comptées, et leurs parts de 0 à 1 : `winRate()`, `tieRate()`, `lossRate()`. Une égalité est une
victoire partagée, que ni une victoire ni une défaite ne décrit : montrez les trois parts et le nombre de fins avant
toute moyenne qu'on en tire.

Une position implémente `Contract\Simulation` : `trial($randomizer)` la joue une fois jusqu'à sa fin et rend un `Trial`
— `Win`, `Tie` ou `Loss`. Une estimation relève du hasard, pas du jeu : elle ne change jamais une table, et elle tire du
générateur qu'on lui donne, pas de la graine de la table.

## La poursuite d'une cargaison

`ShipCaptainCrew::chase($state)` donne le tour du siège qui joue sous forme d'un `Odds\CargoChase` : combien de navire,
de capitaine et d'équipage sont à bord, les dés lancés en dernier, les lancers restants, et la cible — la meilleure
cargaison des sièges qui le précèdent, 0 quand aucun n'en porte. Son essai lance tant qu'il reste des lancers et que la
cargaison ne bat pas la cible, et s'arrête dès qu'elle la bat : une cargaison au-dessus de la cible gagne, la même
cargaison est une égalité, moins perd ; sans cargaison avant lui, ne rien porter est une égalité. Ses dés sont tirés
directement du générateur, pour la vitesse : ce sont ceux de la simulation, jamais ceux de la table.
