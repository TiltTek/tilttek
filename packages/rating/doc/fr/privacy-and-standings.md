<!-- phil-dev source-sha256=b4980bbe2bb052a05ff0af2b9be37cb13b64430a2031c0753a30b674caac03a2 -->
# Vie privée et classement

Une cote compare un membre à d'autres, et une comparaison publique met de la pression sur lui. La bibliothèque garde donc
chaque cote **privée par défaut** : le membre voit la sienne ; personne d'autre ne la voit tant que le membre ne choisit
pas autrement.

## Publier

```php
$ratings->publish('chess', 'member-1042');   // the member's rating of chess appears in the standings
$ratings->hide('chess', 'member-1042');      // and leaves them
```

La visibilité se choisit jeu par jeu, et par le membre seul : l'application appelle `publish()` à partir de l'action du
membre lui-même, jamais en son nom. Un membre peut publier avant sa première partie cotée ; sa cote apparaît alors quand
il en joue une.

## Le classement

```php
$ratings->standings('chess', limit: 100);    // list<MemberRating>, 1 to 1 000
```

Le classement liste les cotes publiques des membres qui ont au moins une partie cotée, le score le plus haut d'abord — la
cote Elo, ou l'estimation prudente d'une habileté —, puis par membre. Une cote privée n'y figure jamais, pas plus que le
nombre des membres qui gardent la leur privée.

## Les cotes provisoires

Une cote après quelques parties en dit peu : une victoire chanceuse placerait un membre en tête. `isProvisional($rating)`
est vrai tant que le membre a joué moins de parties cotées du jeu que n'en demande `provisionalGames`, 10 par défaut
(`Ratings::PROVISIONAL_GAMES`), ou tant que le système juge la cote incertaine : une habileté OpenSkill dont l'écart est
au-dessus de `provisionalSigma`, quand il est réglé. Elo n'a pas de mesure propre : ses cotes ne sont provisoires que par
leurs parties ; `provisionalGames: 0` laisse les parties de côté.

```php
$chess = new Ratings($store, new EloSystem(), provisionalGames: 20);
$chess->isProvisional($chess->rating('chess', 'member-1042'));   // shown as "1 512?"
$chess->standings('chess', 100, provisional: false);             // the provisional ratings left out
```

Une page marque une cote provisoire à côté de son score, par un point d'interrogation. `standings()` les liste par
défaut ; avec `provisional: false`, il les omet et donne tout de même jusqu'à `$limit` membres, en lisant plus bas dans
le classement quand le système en omet, jusqu'à `Ratings::MAX_STANDINGS` cotes lues.

## Montrer une cote

`MemberRating::value()` est le score à deux décimales ; `$games`, `$peak` et `$lastResult` disent combien de parties
cotées, le score le plus haut atteint et le dernier résultat compté. Montrez une cote privée à son membre seul, et un
changement — le `RatingChange` de `record()` — sur la page du membre lui-même. Une partie amicale, jouée hors des cotes,
est là pour les membres qui ne veulent pas être cotés du tout.

## Export et effacement

```php
$ratings->export('member-1042');   // every rating of the member, whatever the game
$ratings->forget('member-1042');   // erased
```

`forget()` efface les cotes du membre ; les résultats restent dans leur source, dont l'effacement du membre appartient à
la source elle-même. Une relecture après coup cote de nouveau le membre seulement si la source le nomme encore.
