<!-- phil-dev source-sha256=9b10b79e2ad91d2ab7f68aee58d3ac7f0d02cfb689035f09b24be3bfb6cff567 -->
# Tournois et étapes

## Un tournoi

Un `Tournament` est déclaré une seule fois, au démarrage :

| Champ | Ce qu'il contient |
|---|---|
| `id` | L'identifiant du tournoi. |
| `seed` | Une graine publiée, de 1 à 128 caractères ASCII imprimables sans espace. Elle fixe le premier ordre des participants sans classement initial, et départage les égalités qui restent à la fin du classement. Quiconque détient la graine et les résultats recalcule les mêmes appariements et le même classement. |
| `participants` | De 2 à 512 identifiants, chacun une seule fois. |
| `stages` | De 1 à 4 étapes, dans l'ordre. |
| `scoring` | Les points d'un match ; `Scoring` donne 2 pour une victoire, 1 pour un nul, 0 pour une défaite. |
| `tiebreakers` | Jusqu'à 8 codes de bris d'égalité, dans l'ordre où ils s'appliquent. |

Chaque identifiant — d'un tournoi, d'une étape, d'un participant, ou de qui saisit un résultat — compte de 1 à 128
lettres, chiffres, `_`, `.`, `:`, `@` ou `-`, et commence par une lettre. Il est stable et opaque : une application
garde les noms, la bibliothèque ne les voit jamais, et aucun appariement, classement ou événement ne porte ce qui
identifie une personne.

## Étapes

| Étape | Créée par | Apparie |
|---|---|---|
| Suisse | `Stage::swiss($id, $rounds, $qualifiers)` | De 1 à 64 rondes, chacune appariée par groupes de points ; les `$qualifiers` premiers du classement continuent. |
| Toutes rondes | `Stage::roundRobin($id, $pools, $legs, $qualifiers)` | De 1 à 26 poules, remplies en serpentin par tête de série, chacun rencontrant chacun de sa poule une fois par tour, en un ou deux tours ; les `$qualifiers` premiers de chaque poule continuent. |
| Élimination simple | `Stage::singleElimination($id)` | Un tableau par têtes de série ; un match perdu est la fin. C'est la dernière étape. |

Une étape commence quand celle d'avant est terminée. Ses participants sont les qualifiés de l'étape précédente, par
rang, les poules prises à tour de rôle à chaque rang : le premier de la poule A, le premier de la poule B, puis le
deuxième de la poule A, et ainsi de suite. Un participant qui s'est retiré ne se qualifie pas ; le suivant, oui. Les
tournois à deux étapes — des poules ou des rondes suisses, puis un tableau par têtes de série — se déclarent en deux
étapes.

## Pointage

`Scoring` contient des nombres entiers, de 0 à 1 000 : une victoire, un nul, une défaite et, quand ils diffèrent d'une
victoire et d'une défaite, une exemption, une victoire par forfait et une défaite par forfait. Les échecs comptent 1, ½
et 0 ; la valeur par défaut, 2, 1 et 0, garde le même ordre en nombres entiers. `Scoring::threeForAWin()` donne 3, 1 et
0. Un nul ne rapporte pas plus qu'une victoire, ni moins qu'une défaite.

## Mener un tournoi

`Tournaments` est le service qu'une application appelle ; il ne garde rien entre les appels, et lit et enregistre le
tournoi par un `Contract\TournamentStore`.

```php
$tournaments->start($tournament);                 // the first order is fixed now
$round = $tournaments->prepareRound('cup');       // the next round, with its reasons, not published
$tournaments->publishRound('cup', $round);        // or a Round seating the same participants otherwise
$tournaments->reschedule('cup', 'swiss', 1, new DateTimeImmutable('2027-04-03 13:00'));
$tournaments->record('cup', $round->key(1), Result::draw(), 'm-104');
$tournaments->standings('cup');                   // the stage under way, or a stage and a pool given
$tournaments->bracket('cup');                     // the elimination, filled with what is known
$tournaments->schedule('cup', 'm-104');           // every round of a participant, rests included
$tournaments->explain('cup', 'swiss', 2, 'm-104', 'm-221');
```

La ronde suivante n'est préparée que si chaque table de la ronde précédente a un résultat et qu'aucun conflit n'attend.
Une ronde publiée place une fois chaque participant encore en lice dans l'étape ; un organisateur peut les placer
autrement, et la bibliothèque garde cette ronde telle que publiée. Chaque table a une clé, `stage#round#table`, celle
sous laquelle on saisit les résultats.

Une ronde a un numéro, qui l'ordonne, et une date de début, qu'on peut fixer, déplacer ou effacer après sa publication
sans changer son numéro ni ses tables.

### Premier ordre

Les participants sont ordonnés une seule fois, au démarrage du tournoi : par les forces que donne un
`Contract\SeedingSource`, la plus forte d'abord, puis ceux de même force ou sans force dans l'ordre de la graine publiée.
Sans source de classement initial, la graine les ordonne tous. Cet ordre classe les participants avant la première
ronde d'une étape suisse, les numérote dans les tables de Berger et sert de tête de série au tableau.

### Calendrier

`schedule()` donne à un participant chaque ronde publiée, puis, pour les poules, les rondes que la table de Berger fixe
déjà : l'adversaire, le côté, la poule, la date quand elle est fixée, le repos d'une ronde là où la poule est impaire,
et si la ronde est publiée ou seulement prévue.

### Explications

`explain()` dit pourquoi deux participants se rencontrent dans une ronde publiée, ou pourquoi ils ne se rencontrent pas,
sous la forme d'un `ExplanationKind` qu'une application traduit : ils se rencontrent ; ils se sont déjà rencontrés, à la
ronde donnée ; l'un d'eux a l'exemption ou se repose ; l'un d'eux est absent — retiré, ou hors de l'étape ; ils n'ont pas
les mêmes points ; ils sont dans des poules différentes ; le calendrier de la poule fixe leur rencontre à une autre
ronde ; ou, avec les mêmes points et sans rencontre passée, un autre appariement de leur groupe coûtait moins.
