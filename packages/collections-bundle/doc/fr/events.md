<!-- phil-dev source-sha256=00df2de1c51019a1e64336f20072eee116f0cb5cc223d666a6c76837c1ee55c8 -->
# Événements

Le bundle répartit deux événements par le répartiteur de l'application :

| Événement | Quand | Porte |
|---|---|---|
| `Tilttek\CollectionsBundle\Infrastructure\Event\CopyGranted` | Un membre a reçu un exemplaire — l'objet du jour, un paquet, une récompense, un achat | `copy` |
| `Tilttek\Collections\SetCompleted` | Un membre a complété un ensemble pour la première fois, juste après l'exemplaire qui l'a complété | `set`, avec ses `rewards`, et `completion` |

```php
#[AsEventListener]
public function __invoke(SetCompleted $event): void
{
    foreach ($event->set->rewards as $reward) {
        // give $reward->amount of $reward->code to $event->completion->member
    }
}
```

Un déplacement entre membres ne répartit aucun `CopyGranted` ; il répartit `SetCompleted` quand il complète un
ensemble.
