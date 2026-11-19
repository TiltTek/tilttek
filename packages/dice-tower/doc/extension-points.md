# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Who may watch a table

Implement `Tilttek\DiceTower\Application\Contract\TableAccess` and name the service in `table_access`. It answers for
the current viewer, from the application's own members and tables:

```php
use Phil\Resource\ResourceReference;
use Symfony\Bundle\SecurityBundle\Security;
use Tilttek\DiceTower\Application\Contract\TableAccess;

final readonly class TableMembers implements TableAccess
{
    public function __construct(private Security $security, private Seats $seats)
    {
    }

    public function canWatch(ResourceReference $table): bool
    {
        $user = $this->security->getUser();

        return null !== $user && $this->seats->isSeated($user->getUserIdentifier(), $table->id);
    }
}
```

The overlay, the subscription and the route of the missed entries all ask it; a viewer it refuses sees nothing and
reads nothing.

## Relays to other channels

Implement `Tilttek\DiceTower\Application\Contract\TowerRelay`: each service that implements it is tagged
`tilttek_dice_tower.relay` and receives every new entry. A relay to a chat channel — a Discord channel of the table,
for instance — is an adapter of its own package, which brings the client of that service; this package needs none.

```php
use Tilttek\DiceTower\Application\Contract\TowerRelay;
use Tilttek\DiceTower\Application\TowerEntry;

final readonly class ChannelRelay implements TowerRelay
{
    public function relay(TowerEntry $entry): void
    {
        // Find the channel of $entry->table, and post the totals of $entry->results.
    }
}
```

With `message_bus`, the relays are reached through that bus: the message, `RelayDrop`, carries the table and the
position only, and its handler reads the entry from the log. Route it to an asynchronous transport so that a slow
channel never delays a roll. A relay that fails is logged and the others still receive the entry.

## Publishers

`TowerPublisher` pushes each new entry to the viewers. The bundle's publisher pushes to a Mercure hub; alias the
interface to a service of yours to push elsewhere:

```yaml
services:
    Tilttek\DiceTower\Application\Contract\TowerPublisher: '@App\Dice\SocketPublisher'
```

## Templates

Override the templates as for any bundle, under `templates/bundles/TilttekDiceTowerBundle/`:

| Template | What it draws |
|---|---|
| `overlay.html.twig` | The card, its list, the empty tower and the status line, with the values of the controller |
| `_entry.html.twig` | One entry |

An override keeps the words of each die and the values of the controller. It names the components of the kit through
`dice_tower_kit()`, so that it follows the application's subdirectory. The controller writes a new entry as
`_entry.html.twig` does: an override of the entry changes the controller's `item()` too.

## Translations

The words are in the `tilttek_dice_tower` domain, in English and French; add a file
`translations/tilttek_dice_tower.<locale>.yaml` for another language, with the keys of the bundle's files. The labels
of the faces and the texts of the rolls go through the translator of `TilttekDiceBundle`.
