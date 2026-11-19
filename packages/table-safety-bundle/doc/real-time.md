# Real time

The signals reach the other screens of a table through a Mercure hub and the Stimulus controller of the signals.
Mercure suits them: a signal goes one way, from the server to the screens that read its topic; the hub keeps the
connections, so the application's workers hold none; and the topics are private, read only with the authorization
cookie the screen gives. The controller reads the hub with the browser's `EventSource`, without a client library.

With `mercure.hub` configured, `MercureSignalObserver` pushes each signal to the hub as it is raised and as it is marked
as seen. Without a hub, the screens show the signals when they load, and the forms still work.

## The hub

`mercure.hub` names a `HubInterface` service, such as `mercure.hub.default` of MercureBundle. Either kind of hub works:

- a hub reached by its URL, such as the Mercure server or a hub of a hosting service, to which the bundle publishes
  over HTTP with a token;
- the hub built into the FrankenPHP server, which MercureBundle chooses when the hub's `url` is empty, and to which the
  bundle publishes within the server's process.

The built-in hub publishes only from a request served by FrankenPHP: its publishing function does not exist on the
command line. The bundle raises and acknowledges signals from requests only, so the built-in hub serves it. An
application that raises signals from a command or a Messenger consumer names a hub reached by its URL for them;
MercureBundle declares several hubs.

## The topics

Each table has two private topics:

| Topic | Signals | Read by |
|---|---|---|
| `urn:tilttek:table-safety:<table>` | those the whole table sees | every participant and the game master |
| `urn:tilttek:table-safety:<table>/game-master` | the discreet ones: the open door, the answers to a check-in, and the X-card and Script Change at a table that chose so | the game master |

The table's reference is encoded so that it stays one segment. A screen subscribes to its topics by their exact names,
with `match` — or `topic` for a hub of the legacy protocol —, never by a pattern.

## What is pushed

The JSON of `Signal::toArray()`: the signal's identifier, the table's reference, the kind, the audience and the
moments — the author's reference only on a named signal. Nothing else reaches the hub. An X-card is anonymous unless
the table chose named signals.

## The subscriptions

When a screen loads, `SignalSubscriptions` gives the viewer's authorization cookie the topics they read — the table's,
and the game master's for the game master — and the screen hands the hub's URL to the Stimulus controller.

## When the hub fails

A hub that cannot be reached — any `\RuntimeException`, that of a hub by URL as that of the built-in hub — is logged as
a warning, with the signal's identifier and the table's reference, and does not fail the signal: it is stored, and the
screen that raised it shows it. When the connection comes back, the controller reads the signals it missed from the
feed of the table.
