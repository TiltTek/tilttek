<!-- phil-dev source-sha256=3300b08d1004c09aec450c8d63ffcb3090fa43aca38f9e93bce82221320803f8 -->
# Genres

Le `QuestKind` d'une quête dit quelle activité la fait avancer et qui l'accomplit.

## Personnelle

L'activité propre du membre, pour lui-même. Le membre est le sujet ; les récompenses lui reviennent.

## Communauté

Un défi de toute la communauté : un objectif collectif (*mille parties ce mois-ci*) que fait avancer l'activité de
chaque membre. Il est accompli une fois par occurrence, par le sujet `community:<quest>`, et ses récompenses vont à
**chaque membre dont l'activité a compté** pour lui. Avec `OnAcceptance`, seuls les membres qui l'ont accepté comptent.

## Équipe

Les membres la rejoignent sous une équipe que nomme l'application (`team:red`, `guild:44`), et leur activité
s'additionne vers un objectif qu'ils partagent. L'équipe est le sujet ; les récompenses vont **aux membres de l'équipe
qui la suivent encore** quand elle est accomplie. Un membre fait partie d'une seule équipe par quête à la fois, et son
activité compte de sa propre acceptation jusqu'à son propre abandon.

## Duel

Un défi qu'un membre lance à un autre sur une quête de duel, sous une clé que donne l'application :

```php
$duels->challenge('duel:812', 'ten-games', $member, $friend);
$duels->accept('duel:812', $friend);    // or decline(); the challenger may withdraw() until then
$duels->standing('duel:812');            // how far each side is
$duels->expire();                        // the offers and the duels whose time is up
```

- Une offre attend sa réponse pendant une durée de vie (`P7D` par défaut) ; acceptée en retard, elle expire et
  l'acceptation est refusée.
- Une fois accepté, le duel dure la `duelLength` de la quête (`P7D`, `PT2H`) ou jusqu'à la fermeture de la quête, selon
  ce qui arrive en premier ; sans l'une ni l'autre, il dure jusqu'à ce qu'il soit gagné.
- L'activité de chaque côté compte de l'acceptation jusqu'à la fin ; **le premier côté qui accomplit la quête gagne**,
  ce que décide la projection : le duel est `Won`, et son gagnant reçoit un accomplissement sous la clé du duel ainsi
  que les récompenses.
- Un duel dont le temps s'écoule sans gagnant expire quand `expire()` balaie les duels ; l'observateur en est averti.
- Chaque changement d'état ne s'écrit que si le duel est encore dans l'état où il a été lu : d'une acceptation et d'un
  refus donnés au même moment, un seul est gardé, et l'autre est refusé parce que le duel n'attend plus de réponse — ou
  renvoie le duel, quand il a donné la même réponse. Un duel répondu, expiré ou gagné depuis que le balayage ou la
  projection l'a lu reste tel quel.

Un membre peut avoir plusieurs duels à la fois, sur la même quête ou non ; chacun ne compte que son propre temps.
