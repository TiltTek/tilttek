<!-- phil-dev source-sha256=5530ea318c9433154bf4ad00851fb6f77f24940d1cb5eb6f9f6e24976c97522f -->
# Lire et compter

## Positions

Chaque entrée a une position, à partir de 1, qui ne fait que croître : une entrée écrite plus tard a une position plus
grande, et une position n'est jamais donnée deux fois, même quand un effacement a supprimé les dernières entrées.
`ActivityLog::lastPosition()` donne la position de la dernière entrée gardée, 0 pour un journal vide.

## Filtres

Un `Filter` choisit des activités ; chaque méthode renvoie un nouveau filtre.

```php
use Tilttek\Activity\Filter;

$filter = Filter::all()
    ->member($member)                       // one member
    ->verbs('game.*', 'news.commented')     // any of these verbs
    ->object($game)                         // one object
    ->since($monday)                        // from this moment, included
    ->until($nextMonday);                   // to this moment, excluded
```

La période se lit sur le moment de l'activité, `occurredAt`, et non sur le moment de son enregistrement. Un motif de
verbe nomme un verbe (`game.played`), tous les verbes sous un préfixe (`game.*` correspond à `game.played` et à
`game.won.flawless`, mais ni à `game` ni à `gamebook.read`), ou tous les verbes (`*`).

## Lire dans l'ordre

```php
$entries = $journal->read(after: 0, limit: 500, filter: $filter);
```

`read()` renvoie les entrées situées après une position dont l'activité correspond au filtre, dans l'ordre de leurs
positions, au plus `limit` d'entre elles. Lisez la page suivante après la dernière position renvoyée. Une annulation
correspond quand l'activité qu'elle annule correspond. La position vaut 0 ou plus et la limite au moins 1 ; sinon,
`InvalidActivity` est levée.

## Totaux

```php
$totals = $journal->totals(Filter::all()->member($member)->verbs('game.played')->since($monday));
$totals->count;     // how many activities
$totals->quantity;  // the sum of their quantities
```

Les totaux excluent les activités annulées. Ils lisent tout le journal à travers le filtre : un paquet qui compte
souvent tient plutôt ses propres totaux dans une projection.
