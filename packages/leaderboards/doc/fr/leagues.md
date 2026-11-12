<!-- phil-dev source-sha256=b84df83ec991ef51911d6977d773e3e57baad454b33ce125bed5db803a7db237 -->
# Ligues

Une ligue répartit les membres d'un classement hebdomadaire en divisions d'une trentaine, classées entre elles, en
paliers du plus bas au plus haut. Chaque membre ne voit que sa division, et rien n'en est public : un membre qui n'a pas
choisi de paraître est une ligne sans nom, dans sa division comme dans tout classement. À la fin de la semaine, dans le
fuseau horaire du classement, les premiers de chaque division montent d'un palier, les derniers descendent, et les
divisions de la semaine suivante se reforment, selon l'ordre d'arrivée des membres ou selon le volume de leur activité
de la semaine précédente.

## Déclarer une ligue

Une ligue appartient à un classement par semaine ISO ; un classement d'une autre période doté d'une ligue est refusé.

```php
$boards = Boards::fromArray([
    'weekly-xp' => [
        'measure' => 'total',
        'verbs' => ['lesson.done'],
        'period' => 'week',
        'league' => ['tiers' => ['bronze', 'silver', 'gold', 'sapphire', 'diamond'], 'division_size' => 30, 'promoted' => 7, 'relegated' => 5],
    ],
], 'America/Toronto');
```

`League` contient de 1 à 50 paliers, chacun nommé une seule fois comme un classement ; une division de 2 à 100 membres ;
le nombre des premiers qui montent (`promoted`, 7 par défaut) et des derniers qui descendent (`relegated`, 5 par
défaut), jamais plus au total que ce que contient une division ; et comment ses divisions sont formées (`formed_by`) :
par `arrival` (la valeur par défaut) ou par `volume`. La mesure peut être n'importe laquelle : un total de points, un
nombre de parties, une valeur que rapporte l'application. Un classement par objet n'a pas de ligue.

## Entrer dans une division

`Leagues` place un membre la première fois qu'il marque des points dans une semaine : la projection le fait pour un
classement lu à partir du journal, `Leaderboards::report()` pour un classement rapporté. Le membre entre dans une
division de son palier (le plus bas pour un nouveau membre, ou pour un palier que la ligue n'a plus), dans l'ordre
d'arrivée : la division de plus petit numéro qui compte moins de membres que la taille d'une division, ou une nouvelle.
Un membre qui ne participe pas à une semaine n'a pas de division cette semaine-là ; il n'est ni déplacé ni compté.

```php
$leagues = new Leagues($boards, $scores, new InMemoryLeagueStore(), $clock, $observer);
$projection = new LeaderboardProjection($boards, $scores, leagues: $leagues);
$leaderboards = new Leaderboards($boards, $scores, $snapshots, $appearances, $clock, $observer, $leagues);
```

Une activité enregistrée en retard pour une semaine déjà réglée compte encore dans les scores de la semaine, mais
n'entre dans aucune division.

## Divisions formées par volume

Avec `formed_by: volume`, un membre entre dans une division de son palier parmi les membres de sa cohorte : son score
dans le classement la semaine précédente, en tranches qui doublent (aucun score : cohorte 0, 1 : cohorte 1, de 2 à 3 :
cohorte 2, de 4 à 7 : cohorte 3, et ainsi de suite, `League::cohortOf()`). Une division contient les membres d'une
seule cohorte : un nouveau venu y rencontre des membres qui ont fait à peu près autant que lui, non un membre qui marque
cent fois plus. La cohorte est gardée sur la `Membership`, et la division de plus petit numéro de la cohorte qui a de la
place prend le membre, ou le numéro qui suit le plus haut du palier.

## La division d'un membre

```php
$division = $leaderboards->division('weekly-xp', $member);   // the week under way
$division = $leaderboards->division('weekly-xp', $member, '2026-w45');
```

`division()` retourne null quand le membre ne participe pas à cette semaine. Une `Division` contient la `Membership`
(palier, numéro de division, semaine, moment d'arrivée, cohorte et, une fois réglée, son `Movement`), le `Standing` de
la division classée entre ses seuls membres, le nombre de membres qu'elle compte, et combien montent et descendent. Chaque
ligne paraît selon le choix de son membre, comme dans tout classement ([Paraître dans un classement](appearance.md)) :
un membre qui n'a pas choisi de paraître est une ligne sans nom, dans la division comme ailleurs. `movementAt($rank)`
dit où irait une ligne de ce rang si la semaine finissait maintenant, pour les zones qu'affiche un gabarit.

## La fin de la semaine

`settleDue()` règle chaque semaine terminée qu'un classement doté d'une ligue n'a pas réglée, la plus ancienne d'abord ;
un planificateur l'exécute peu après la fin de la semaine. `settle($board, $week)` règle une semaine, refuse une semaine
non terminée avec `PeriodNotOver` et retourne null pour une semaine déjà réglée. Un membre qui entre dans une division
règle d'abord les semaines encore ouvertes avant la sienne : il entre ainsi au palier qu'il a atteint, même quand le
planificateur est en retard.

Dans chaque division, classée selon le score dans l'ordre du classement (un membre resté sans score à cause d'une
annulation vient en dernier, dans son ordre d'arrivée) :

- les `promoted` premiers montent d'un palier, aucun à partir du palier le plus haut ;
- les derniers descendent d'un palier, aucun à partir du palier le plus bas : autant qu'il y en a sous les places
  qu'une division pleine garde. Une division de 30 en fait descendre 5, une de 26 en fait descendre 1, et une de 25 ou
  moins aucun ;
- les autres restent.

Une semaine se règle une seule fois : le stockage écrit ensemble chaque déplacement, chaque nouveau palier et la marque
de la semaine, et un second règlement n'écrit rien. Le `LeagueObserver` reçoit `leagueSettled()` une seule fois, avec
le `Settlement` (chaque appartenance de la semaine avec son déplacement), pour que l'application félicite ou récompense
les membres qui sont montés.

```php
final class Congratulate implements LeagueObserver
{
    public function leagueSettled(Settlement $settlement): void
    {
        foreach ($settlement->moved(Movement::Promoted) as $membership) {
            // tell $membership->member they reached the next tier
        }
    }
}
```

`Leagues::tier()` lit le palier qu'un membre a atteint (le plus bas avant sa première semaine réglée) ; `membership()`
sa place dans une semaine.

## Exportation et effacement

`Leaderboards::export()` contient les paliers et les appartenances du membre sous `leagues` ; `erase()` les retire, et
les autres gardent leurs divisions ([Effacement](erasure.md)).
