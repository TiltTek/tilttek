<!-- phil-dev source-sha256=9417d8ffaa8a7f2bcd99b8a3054848280533950caa551155a8ea2cd00ae271c6 -->
# Enregistrer l'activité

Injectez `Tilttek\Activity\Journal` là où un geste se produit — un écouteur d'événements, un *handler* de Messenger, un
contrôleur — et enregistrez le geste avec une clé construite à partir de ce qui le rend unique :

```php
$this->journal->record(Activity::of(
    'game:'.$game->id.':player:'.$player->id,
    'member:'.$player->id,
    'game.played',
    $game->endedAt,
    'game:'.$game->id,
    context: ['system' => $game->system],
));
```

Un écouteur qui s'exécute deux fois, un message livré de nouveau ou une commande relancée n'enregistre rien de plus : la
clé compte une seule fois. Quand le geste est défait, annulez-le par la même clé :

```php
$this->journal->cancel('order:'.$order->number.':paid');
```

Les verbes de `tilttek_activity.verbs` sont vérifiés avant toute écriture. Consultez la documentation de la
bibliothèque pour l'activité, la clé, le contexte et les observateurs.
