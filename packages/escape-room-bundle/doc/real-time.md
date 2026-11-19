# Real time

## The hub

With Symfony Mercure installed, the screens are told of each move through the application's default hub, the service
`Symfony\Component\Mercure\HubInterface` that MercureBundle registers. Another hub is named by its service:

```yaml
tilttek_escape_room:
    mercure:
        hub: mercure.hub.rooms
        hub_name: rooms
```

`mercure: false` tells nothing, and the screens read the game at intervals.

## The updates

Each move publishes a private update on the topic of its game — `tilttek-escape-room/games/{game}`, the prefix set by
`mercure.topic_prefix` — whose data names the move only:

```json
{"move": "hint"}
```

The moves are those of `GameDesk::MOVES`: `start`, `hint`, `skip`, `pause`, `resume`, `message`, `clear`, `consent`
and `finish`. No hint, message, team or host is ever sent: the screens read them from the server, under the access of
the application. A hub that cannot be reached is logged and fails nothing.

## The subscription

When it renders the console or the hint screen, the bundle gives the viewer the authorization cookie of the game's topic
— through `Symfony\Component\Mercure\Authorization` — and the URL to subscribe to, which the Stimulus controllers open
with `EventSource`. The hub and the application share the same second-level domain, as Mercure's cookies require.

## Without a hub

Without a hub, the controllers read the screen again every `mercure.poll` seconds — 5 by default —, and the hint
screen stops once the game is over.
