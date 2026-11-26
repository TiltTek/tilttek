<!-- phil-dev source-sha256=aa6b5c90e3da86c7034402bbb6cbfb6df6dbf7c0e4983e52f5c5f166c4551ded -->
# Révélations

Un document est caché de tous les joueurs jusqu'à ce qu'il soit révélé. Une `Audience` dit à qui :

| Audience | Qui |
|---|---|
| `Audience::everyone()` | Tous les joueurs de la table : l'application décide qui y est assis |
| `Audience::players($ada, $bo)` | Les joueurs nommés, de 1 à 500, chacun gardé une fois dans l'ordre donné |

Une audience donnée à un acte nomme au plus `Limits::$audiencePerHandout` joueurs — 50 par défaut
(`Refusal::AudienceTooLarge`).

## Révéler tout de suite

```php
$handouts->reveal($letter, Audience::players($ada), $gm);
$handouts->reveal($map, Audience::everyone(), $gm);
```

`reveal()` donne une `Disclosure` pour toute la table, ou une par joueur : la révision montrée, le moment et la
référence qui agit. Les joueurs voient toujours la révision courante ; la divulgation garde laquelle a été montrée, et
quand.

Révéler de nouveau à la même audience montre le document encore une fois, au nouveau moment et à la nouvelle révision —
l'application le pousse de nouveau vers les écrans des joueurs. Une révélation planifiée pour la même audience est
accomplie et supprimée.

Un document est révélé à au plus `Limits::$audiencePerHandout` audiences — toute la table comptant pour une —
(`Refusal::TooManyRevealed`).

`canSee($handout, $player)` est vrai quand le document est révélé à toute la table, ou au joueur nommément.
L'application le demande avant de montrer un document ou de servir un de ses fichiers. `audience($handout)` lit les
divulgations, la plus ancienne d'abord.

## Cacher de nouveau

```php
$handouts->conceal($letter, Audience::players($ada), $gm);    // Ada no longer sees it
$handouts->conceal($letter, Audience::everyone(), $gm);       // no one sees it
```

Cacher à toute la table supprime toutes les divulgations, à toute la table et aux joueurs nommés. Cacher à des joueurs
supprime leurs divulgations nominatives : un document aussi révélé à toute la table leur reste visible. Les
révélations planifiées restent ; `unstage()` les retire. Ce que les joueurs ont vu reste dans leurs vues.

## Planifier une révélation

Une révélation peut être préparée d'avance, sur un `Trigger` :

| Déclencheur | Quand elle a lieu |
|---|---|
| `Trigger::at($moment)` | Quand l'application appelle `releaseDue()` à ce moment ou après |
| `Trigger::cue('crypt.opened')` | Quand le meneur de jeu donne le signal avec `cue($table, 'crypt.opened')` |

Un signal est une clé : 1 à 96 lettres minuscules, chiffres, `_`, `-`, ou points isolés entre eux.

```php
$handouts->stage($map, Audience::everyone(), Trigger::cue('crypt.opened'), $gm);
$handouts->stage($letter, Audience::players($ada), Trigger::at(new DateTimeImmutable('2026-12-04 20:00')), $gm);
```

`stage()` donne une `StagedReveal` pour toute la table, ou une par joueur. Planifier de nouveau pour la même audience
remplace le déclencheur précédent. Un document contient au plus `Limits::$audiencePerHandout` révélations planifiées
(`Refusal::TooManyStaged`). `staged($handout)` les lit, la plus anciennement planifiée d'abord ;
`unstage($handout, $audience)` les retire — pour toute la table, toutes les révélations planifiées du document.

### Donner un signal

```php
$handouts->cue($table, 'crypt.opened', $gm);
```

`cue()` révèle ce qui attendait ce signal pour les documents de la table, la plus anciennement planifiée d'abord, et
donne les divulgations. Il révèle au plus `Limits::$releaseBatch` révélations planifiées — 100 par défaut ; ce qui
reste demeure planifié pour le prochain appel.

### Déclencher ce qui est dû

```php
$handouts->releaseDue();
```

`releaseDue()` révèle les révélations planifiées de toutes les tables dont le moment est venu, le plus ancien moment
d'abord, au plus `Limits::$releaseBatch` par appel. Le planificateur de l'application l'appelle, et rappelle tant qu'un
appel en révèle autant.

Une révélation déclenchée est attribuée à la référence qui appelle `cue()` ou `releaseDue()` avec une, sinon à celle
qui l'a planifiée.
