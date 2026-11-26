<!-- phil-dev source-sha256=1104dca65c18e27cbd4f8145de6f5bbb6b00cbba5a9ed404e44dfdbc1345dda6 -->
# Conditions et effets

## Le catalogue

La bibliothèque ne connaît aucun système de jeu. Le site déclare les conditions de son système sous forme de
`ConditionDefinition`, réunies dans un `ConditionCatalogue` construit une fois — depuis sa propre configuration, ou
depuis les termes d'un système qu'il lit ailleurs. Une condition n'est connue que par son identifiant : ses noms et ses
descriptions restent au site, dans ses traductions.

```php
new ConditionDefinition(
    'frightened',                     // the identifier
    Stacking::Highest,                // what a new application does
    maxValue: 4,                      // a value of 1 to 4; null for a condition without a value
    modifiers: [Modifier::add('checks', -1, perValue: true, type: 'status')],
    implies: [],                      // the conditions it brings with it
    duration: null,                   // its own duration; until removed when null
    decay: new Decay(1, Boundary::End),
);
```

| Borne | Valeur |
|---|---|
| Conditions dans un catalogue | 1 000 |
| Valeur la plus haute d'une condition | 100 |
| Modificateurs d'une condition | 32 |
| Conditions qu'une condition entraîne | 16 |
| Instances d'une condition qui se cumule à part | 2 à 16 |

Le catalogue refuse un identifiant en double, une condition qui en entraîne une qu'il n'a pas ou une dotée d'une valeur,
et une condition qui s'entraîne elle-même par d'autres. Une définition refuse les règles qui se contredisent : une
somme, un déclin ou un modificateur selon la valeur sur une condition sans valeur ; des instances séparées avec un
maximum de 1, ou plusieurs instances pour tout autre cumul.

## Le cumul

`Conditions::apply()` applique une condition à un personnage selon son `Stacking` :

| Cumul | Quand le personnage l'a déjà |
|---|---|
| `Replace` | Les applications en place se terminent (`EndReason::Replaced`), et la nouvelle commence. |
| `Highest` | Quand la nouvelle valeur est égale ou supérieure, celle en place la prend, avec la nouvelle durée, la nouvelle source et le nouveau moment ; sinon rien ne change. Une condition sans valeur est rafraîchie. |
| `Sum` | Les valeurs s'additionnent jusqu'à la valeur la plus haute ; celle en place prend la nouvelle durée et la nouvelle source, et garde le moment de sa première application. |
| `Separate` | Chaque application est distincte, jusqu'au maximum d'instances ; une de plus est refusée. |
| `Ignore` | Rien ne change. |

Une nouvelle application reçoit un identifiant d'instance, `i1`, `i2` et ainsi de suite pour le personnage, jamais
donné deux fois. Un personnage a 64 applications au plus. Avant d'appliquer, le service termine les conditions dont la
fin en temps réel est déjà passée.

## Valeurs et déclin

Une condition avec valeur s'applique avec une valeur de 1 à sa plus haute ; une condition sans valeur s'applique sans.
`setValue()` change la valeur d'une application, et 0 la retire.

Un `Decay` abaisse la valeur de son montant au début ou à la fin des tours propres du personnage touché ; à zéro, la
condition se termine avec `EndReason::Decayed`. Effrayé 2 avec un déclin de 1 à la fin du tour est effrayé 1 après son
prochain tour, et disparu après le suivant.

## Durées

| Durée | S'écoule |
|---|---|
| `Duration::rounds($n, $boundary, $anchor)` | Après `$n` frontières — le début ou la fin — des tours de l'ancre : ceux de la cible, de la source ou des rounds. |
| `Duration::gameTime($seconds)` | Quand la table a fait avancer la fiction d'autant de secondes. |
| `Duration::realTime($milliseconds)` | Au moment de l'application plus les millisecondes, lu sur l'horloge. |
| `Duration::untilRemoved()` | Jamais d'elle-même : une mutation, un membre perdu. |

Les rounds comptent les frontières après l'application de la condition : une condition de 1 round jusqu'à la fin du
tour de la cible, appliquée pendant ce tour, se termine à sa fin ; appliquée pendant le tour d'un autre personnage, elle
se termine à la fin du prochain tour de la cible. Une durée sur les tours de la source exige la source, et compte les
tours du personnage nommé comme source. Les durées vont de 1 à 10 000 rounds, jusqu'à cent ans de temps de jeu, et de
une seconde à 366 jours de temps réel.

## Tours, rounds et temps

La bibliothèque n'a pas d'ordre de tour à elle : l'application lui annonce chaque frontière, depuis son suivi
d'initiative ou son moteur de jeu, avec les personnages dont les conditions comptent.

| Appel | Compte |
|---|---|
| `startTurn($tick, $character, $participants)` | Le début du tour d'un personnage : les durées ancrées sur la cible ou la source au début, les déclins au début. |
| `endTurn($tick, $character, $participants)` | La fin du tour d'un personnage. |
| `startRound($tick, $participants)` | Le début d'un round : les durées ancrées sur le round au début. |
| `endRound($tick, $participants)` | La fin d'un round. |
| `advanceTime($tick, $seconds, $participants)` | Des secondes de la fiction : les durées en temps de jeu. |

Une frontière compte pour 200 participants au plus, chacun traité à part. Chacune porte un tic, un identifiant de la
frontière — `enc-4:r3:hero-1:end` — qu'un participant enregistre quand la frontière change ses conditions : annoncée de
nouveau juste après, comme le ferait un message rejoué, elle ne compte qu'une fois. Un participant dont la frontière ne
change pas les conditions n'est pas écrit. Quand les conditions d'un participant ont changé entre-temps,
`ConditionConflict` est levée, les participants précédents sont gardés, et le même appel refait ne compte que pour les
autres.

Les conditions en temps réel se terminent à leur propre moment : `view()` donne la prochaine, `ConditionsView::$nextExpiry`,
et l'application appelle alors `expire()`. D'ici là, `view()`, `has()`, `modifiers()` et `effective()` les écartent
déjà, et la prochaine écriture les termine.

## Ce que les conditions entraînent

Une condition en entraîne d'autres tant qu'elle dure : mourant entraîne inconscient, qui entraîne aveuglé et à terre.
Les conditions entraînées n'ont ni valeur, ni instance, ni durée à elles ; elles se terminent avec celles qui les
entraînent. `has()` les voit, la vue les liste après les applications avec les conditions qui les entraînent, et leurs
modificateurs ne comptent qu'une fois, peu importe combien de conditions les entraînent. Une condition appliquée pour
elle-même n'est pas listée de nouveau comme entraînée.

## Modificateurs

Un `Modifier` change une statistique du personnage, nommée par un identifiant que le site choisit :

| Opération | Effet |
|---|---|
| `Operation::Multiply` | Multiplie par 0 à 1 000 pour cent : 50 divise par deux. |
| `Operation::Add` | Ajoute le montant, sous zéro pour retirer. |
| `Operation::Upgrade` | Relève au moins au montant. |
| `Operation::Downgrade` | Abaisse au plus au montant. |
| `Operation::Override` | Fixe au montant. |

Pour chaque statistique, les opérations s'exécutent dans cet ordre : toutes les multiplications, l'une après l'autre
dans l'ordre d'application des conditions, chacune arrondie par le `Rounding` du système ; les additions sommées ; le
plus haut plancher ; le plus bas plafond ; le dernier remplacement. Un modificateur `perValue` est multiplié par la
valeur de la condition : effrayé 3 prend 3. Les additions d'un même `type` ne se cumulent pas : parmi elles, seuls le
meilleur bonus et la pire pénalité comptent ; les additions sans type se cumulent toujours.

`effective()` reçoit les statistiques de base — jusqu'à 1 000, chacune à un milliard près dans les deux sens — et donne
pour chacune son `Effect\StatValue` : sa base, sa valeur, et les modificateurs qui ont fait la différence, pour montrer
au joueur pourquoi. Une statistique reste à un million de milliards près dans les deux sens ; un modificateur d'une
statistique absente de la base ne change rien. Une condition que le catalogue n'a plus garde sa place sur le personnage
et ne modifie rien.

## La vue

`view()` donne une `View\ConditionsView` : les applications dans l'ordre d'application, chacune avec son instance, sa
valeur, sa source, ce qui reste de sa durée et sa fin en temps réel ; puis les conditions qu'elles entraînent ; la
prochaine fin en temps réel ; et la version lue. `toArray()` la donne en données simples, les moments en RFC 3339 avec
millisecondes, à pousser vers la table.
