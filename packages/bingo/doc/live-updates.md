# Live updates

The players' screens follow a game as it is played: the squares the host calls, the cards that win, and, on a
convention's card, the squares a gesture ticks. `Mercure\BingoPublisher` pushes them through Mercure, as private
updates that name the game, the card's serial, the square and the pattern, never a player: each screen that hears an
update reads what it may show from the application, with its own rights. It is optional, and needs `symfony/mercure`:

```sh
composer require symfony/mercure
```

## Wiring the publisher

```php
use Tilttek\Bingo\Mercure\BingoPublisher;

$publisher = new BingoPublisher(
    $hub,                                   // Symfony\Component\Mercure\HubInterface
    'https://example.com/bingo/games/',     // the topic prefix; the game's identifier follows it
);
```

| Method | Event | Topic | Data of the update |
|---|---|---|---|
| `squareCalled()` | `SquareCalled` | The game's | `{"type": "square-called", "game": …, "square": …}` |
| `bingoWon()` | `BingoWon` | The game's | `{"type": "bingo-won", "game": …, "serial": …, "pattern": …}` |
| `squareTicked()` | `SquareTicked` | The card's | `{"type": "square-ticked", "game": …, "serial": …, "square": …}` |

In a Symfony application, register them on the event dispatcher:

```yaml
# config/services.yaml
Tilttek\Bingo\Mercure\BingoPublisher:
    arguments: ['@mercure.hub.default', '%env(BINGO_TOPIC_PREFIX)%']
    tags:
        - { name: kernel.event_listener, event: Tilttek\Bingo\Event\SquareCalled, method: squareCalled }
        - { name: kernel.event_listener, event: Tilttek\Bingo\Event\BingoWon, method: bingoWon }
        - { name: kernel.event_listener, event: Tilttek\Bingo\Event\SquareTicked, method: squareTicked }
```

## Subscribing

`topic($game)` gives the topic of a game, the prefix followed by its identifier encoded for a URL, and
`cardTopic($game, $serial)` the topic of one card, the game's topic followed by `/` and the serial. The updates are
private: the application gives each player a subscriber token for the game's topic and for their own card's topic only,
once it checked that the card is theirs. A screen that hears an update reads the card again from the application; it
never ticks a cell by itself.

The publisher keeps nothing between two calls: one instance serves every request of a worker.
