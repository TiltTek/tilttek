<!-- phil-dev source-sha256=ffeeda70380b23f1f6aeee663ef0f60056c5554688548e1a87041c4b80e84cb8 -->
# Elo

`Elo\EloSystem` cote selon la formule d'Elo. Son identifiant, gardé avec chaque cote, est `elo`.

## La formule

Chaque camp rencontre tous les autres camps du résultat. Contre le camp `b`, le camp `a` marque 1 quand il est classé
au-dessus de lui, ½ au même rang et 0 quand il est classé en dessous, et s'attend à

```text
E = 1 / (1 + 10^((Rb − Ra) / s))
```

où `Ra` et `Rb` sont les cotes des camps — la moyenne de celles de leurs membres — et `s` l'échelle. Chaque membre du
camp `a` bouge de

```text
K × Σ (score − E) / (sides − 1)
```

avec le K des parties et de la cote de ce membre, arrondi une fois au centième. Un duel est l'Elo classique ; une partie
de plusieurs joueurs est une ronde de duels entre chaque paire, pesée comme une seule partie ; une partie par équipes
est la même chose entre les moyennes des équipes, chaque membre bougeant selon son propre K.

## Réglages

```php
new EloSystem(
    k: KFactor::chess(),   // or new KFactor(32), the default
    initial: 1500,         // the rating of a new member, 0 to 10 000
    scale: 400,            // 1 to 10 000
    floor: null,           // the lowest rating, 0 to the initial rating
);
```

## Le facteur K

`KFactor` donne un K fixe, ou un K qui décroît. Ses paliers sont lus dans l'ordre donné ; le premier qui s'applique
donne le K, et le K donné en premier sert quand aucun ne s'applique. Un palier s'applique à un membre qui a moins de
`beforeGames` parties cotées, ou dont la cote est sous `belowRating`, ou aux deux quand les deux sont donnés. Le K va de
1 à 1 000.

```php
new KFactor(32);                                                       // always 32
new KFactor(10, new KFactorStep(40, beforeGames: 30), new KFactorStep(20, belowRating: 2400));
KFactor::chess();                                                      // the same: the chess federation's K for adults
```

Un K élevé pour les premières parties laisse un nouveau venu atteindre vite son niveau ; un K bas au sommet garde les
plus fortes cotes stables.

## Valeurs

Une cote est gardée en centièmes de point, `EloRating::$hundredths`, et chaque changement est arrondi une fois : les
cotes ne portent aucune fraction d'une partie à l'autre, si bien qu'une relecture donne les mêmes valeurs. Le
classement montre `MemberRating::value()`, la cote à deux décimales.
