<!-- phil-dev source-sha256=a502536e826cc753f7d26862d2af1ad722b3253ca9eb99806f9f24d3ff9ae736 -->
# Ce que les joueurs ont vu

## Marquer ce qu'un joueur a ouvert

```php
$sighting = $handouts->markSeen($letter, $ada, $ada);
```

L'application appelle `markSeen()` quand un joueur ouvre un document. Elle enregistre une `Sighting` : la révision
courante du document, le moment où le joueur l'a ouvert pour la première fois et celui où il l'a ouvert pour la
dernière. Un joueur n'ouvre que ce qu'il voit (`Refusal::NotRevealed`).

L'événement `Change::Seen` est communiqué une fois par révision : rouvrir le document ne déplace que le dernier moment,
tandis que l'ouvrir après une modification le communique de nouveau.

## La vue du joueur

```php
foreach ($handouts->visibleTo($table, $ada) as $visible) {
    $visible->handout;        // the current revision
    $visible->revealedAt;     // the earliest moment it was revealed to Ada, to everyone or by name
    $visible->freshness;      // Freshness::Unseen, Outdated or Seen
    $visible->sighting;       // what Ada opened, or null
}
```

`visibleTo()` liste les documents de la table que le joueur voit, le plus anciennement révélé d'abord, les documents
révélés au même moment dans l'ordre des octets de leurs références. Leur `Freshness` dit ce que le joueur a vu :

| Fraîcheur | Le joueur |
|---|---|
| `Unseen` | N'a pas ouvert le document |
| `Outdated` | A ouvert une révision antérieure |
| `Seen` | A ouvert la révision courante |

`unseen()` garde les documents qui ne sont pas `Seen` : le badge sur l'écran du joueur, la liste de ce qui a changé
depuis son dernier regard.

## La vue du meneur de jeu

`sightings($handout)` liste ce que chaque joueur a ouvert du document, le premier à l'avoir vu d'abord : qui a lu la
lettre, quelle révision, et quand. `audience($handout)` et `staged($handout)` disent à qui il est révélé, et ce qui
attend.

## Effacer un joueur

```php
$handouts->forget($ada, $gm);
```

`forget()` supprime les divulgations nominatives, les révélations planifiées et les vues du joueur dans toutes les
tables, et communique `Change::Forgotten` une fois par document qui en contenait. Les documents révélés à toute la
table le restent ; les documents eux-mêmes, que le meneur de jeu a écrits, restent.
