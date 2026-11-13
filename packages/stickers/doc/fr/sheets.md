<!-- phil-dev source-sha256=cf999ebb8d841ece17ffcf410643906ed6825daf2465f3c4100957a8f720dbc1 -->
# Planches et albums

Une planche d'autocollants, ou un album, est un ensemble de `tilttek/sets` dont les membres sont des codes
d'autocollants. Définissez-le avec les ensembles de l'application, paliers et récompenses compris :

```php
use Tilttek\Sets\{Reward, SetDefinition, Tier};

$sets->define(new SetDefinition('dragons-sheet', 1, ['red-dragon', 'blue-dragon', 'green-dragon', 'gold-dragon'],
    tiers: [new Tier('first-two', required: 2)],
    rewards: [new Reward('sticker', 'dragon-queen')],
));
```

`StickerSheets` mesure une planche par rapport aux autocollants qu'un détenteur possède :

- `progress($holder, $sheet)` donne le `Tilttek\Sets\Progress` de la planche, sans rien enregistrer ;
- `record($holder, $sheet)` enregistre la complétion de la planche et de ses paliers la première fois qu'ils sont
  atteints, et `tilttek/sets` répartit `SetCompleted` et `TierReached` avec les récompenses déclarées.

Appelez `record()` pour les planches auxquelles un autocollant appartient quand vous entendez `Event\StickerGranted`.
Une complétion n'est jamais reprise : un détenteur qui perd un autocollant garde la planche complétée. Une récompense
du genre `sticker`, comme ci-dessus, revient à l'application, qui l'accorde par `Stickers::grant()`.
