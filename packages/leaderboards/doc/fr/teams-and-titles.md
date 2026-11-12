<!-- phil-dev source-sha256=8a0db5e7d1da83feb36e8cd7d1033f189a5e4261e444c1dea5c9de85c4eac755 -->
# Équipes et titres

## Titres

Un classement peut donner un `Title` : un nom (comme celui d'un classement) et un seuil de 1 à 10^15. Un membre le
détient tant que son score dans le classement, pour la période en cours, atteint le seuil :

```php
'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7,
    'title' => ['name' => 'regular', 'threshold' => 5]],
```

Un titre avec `leader` va au seul meneur parmi ceux qui atteignent le seuil : personne ne se classe avant lui. Dans un
classement par objet sur une fenêtre, il nomme la *légende locale* de chaque objet, le membre qui a le plus joué à un
jeu en quatre-vingt-dix jours :

```php
'legend' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'window', 'window' => 90, 'per_object' => true,
    'title' => ['name' => 'local-legend', 'threshold' => 3, 'leader' => true]],
```

`Leaderboards::holds($board, $member, $object)` dit si le membre détient maintenant le titre d'un classement (pour
l'objet dans un classement par objet) ; `titles($member)` retourne les titres des classements non classés par objet que
le membre détient, chacun une fois, triés : `['regular']` pour un membre actif cinq jours sur les sept derniers. Deux
classements peuvent donner le même titre. Un titre est lu, non gardé : il disparaît avec le score qui le mérite, et
l'application l'affiche là où elle montre le membre.

## Équipes

Tilttek ne garde aucune équipe : l'application donne chaque `Team` (un nom comme celui d'un classement et un `Circle` de
ses membres) quand elle demande leur classement. Le score d'une équipe est la somme des scores de ses membres dans le
classement pour la période :

```php
$rows = $leaderboards->teams('house-cup', [
    new Team('red', Circle::of('member:1', 'member:2')),
    new Team('blue', Circle::of('member:3')),
]);
```

`teams()` prend de 1 à 100 équipes, chacune nommée une fois, la période (celle qui est en cours par défaut) et l'objet
dans un classement par objet. Chaque `TeamRow` contient le rang (les totaux égaux le partagent, listés par nom), le nom
de l'équipe, combien de ses membres ont un score, la somme de leurs scores, le jet de chance et le total.

## La chance dans le score d'une équipe

Un classement active la chance avec une `Chance` : de 1 à 10 dés de 2 à 100 faces, lancés une fois pour chaque équipe et
chaque période et ajoutés à son score.

```php
'house-cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'chance' => ['dice' => 2, 'faces' => 6]],
```

Le jet se tire du secret donné à `Leaderboards`, du classement, de la période et de l'équipe, par un HMAC qui amorce le
moteur aléatoire : la même équipe obtient le même jet pour une période, quel que soit celui qui regarde et le nombre de
fois, et personne sans le secret ne peut le prévoir. Un classement sans chance n'en ajoute aucune ; un classement qui en
a une, sans secret, lève `InvalidLeaderboard`.

```php
$leaderboards = new Leaderboards($boards, $scores, $snapshots, $appearances, $clock, secret: $applicationSecret);
```
