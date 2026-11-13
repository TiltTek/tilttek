# Live tables

A player's page shows the table, subscribes to its Mercure topic, and reads the view of its seat again at each update.
The update carries identifiers and counters only; the view is read from the server, which checks who reads it.

## Publishing

With `mercure.enabled`, every change of a table — opened, a move, a move taken back — is published on its topic,
`urn:tilttek-game-engine:table:{table}` unless configured, privately unless `mercure.private` is off:

```json
{"table":"table:42","sequence":12,"phase":"race","round":4,"to_act":["seat:2"],"over":false}
```

The move has landed in the log when the update is published: a hub that fails is logged as a warning, not thrown, and
the players read the table again on their own. A private update reaches only the subscribers whose token the hub
signed for the topic; the application authorizes each player for the topics of their tables, with the `mercure()` Twig
function of MercureBundle or its `Authorization` service.

## The viewer

The endpoints ask the application who reads a table, through `Application\Contract\ViewerResolver`:

```php
final readonly class SeatOfTheMember implements ViewerResolver
{
    public function __construct(private Security $security, private Seating $seating)
    {
    }

    public function resolve(string $gameId): Viewer
    {
        $member = $this->security->getUser();
        $seat = null === $member ? null : $this->seating->seatOf($gameId, $member);

        return null === $seat ? Viewer::denied() : Viewer::seat($seat, $this->seating->preferences($member));
    }
}
```

`Viewer::seat()` names a seat and the preferences its player chose, `Viewer::audience()` the audience, which sees what
the game shows a null seat, and `Viewer::denied()` no one. Without a resolver, `view.audience` opens the audience view
of every table to anyone; otherwise no one reads a table.

## The endpoints

| Route | Path | What it answers |
|---|---|---|
| `tilttek_game_engine_view` | `GET /{table}` | The view of the reader's seat and what anyone at the table may know |
| `tilttek_game_engine_log` | `GET /{table}/log` | The log of a table whose game is over, seed included |

The view answers the table, its game, the number of moves, the phase, the round, the seats that may act, the outcome
and its statistics once the game is over, the reader's seat, the commitment to the seed, the Mercure topic and the
view itself:

```json
{
    "table": "table:42", "game": "roll-race", "sequence": 12, "phase": "race", "round": 4,
    "to_act": ["seat:2"], "over": false, "outcome": null, "seat": "seat:1",
    "commitment": "9f2c…", "topic": "urn:tilttek-game-engine:table:table:42",
    "view": {"totals": {"seat:1": 9, "seat:2": 7}, "secret": 54}
}
```

The log is refused with 409 while the game goes on: its seed would tell the draws to come. It is refused with 409 as
well when the log read is not the one of the finished state — a table opened again under its identifier between the two
reads —, so that the seed of a table that goes on is never shown. Once the game is over, it
answers the log as plain values with its commitment, which anyone replays to check that every draw came from the seed
the players were shown at the opening — `tilttek:game-engine:verify` does it on the server.

Both answer 403 when the resolver refuses the reader or names a seat not at the table, 404 for a table that does not
exist, and 500 for a table whose log cannot be replayed; every answer is JSON, `Cache-Control: no-store, private`, and
an error is `application/problem+json`.

Making moves is the application's own controller's work: it checks the player, then calls `GameTables::play()` with
the seat it knows and the number of moves the player saw. `GameTables::undo()` takes the last move back the same way,
and throws `GameOver` once the game is over: by then the log endpoint may have shown the seed, which tells every draw
to come.

## The template and the Stimulus controller

`@TilttekGameEngine/table.html.twig` shows a table as anyone at it may see it — its number of moves, phase, round, the
seats that may act, its outcome once over, the commitment to its seed — built of the recipes Card and Badge of the kit,
and the place where the view goes:

```twig
{% set table = tilttek_game_table('table:42') %}
{{ include('@TilttekGameEngine/table.html.twig', {
    table: table,
    view_url: path('tilttek_game_engine_view', {table: 'table:42'}),
    hub_url: mercure(table.topic, {subscribe: table.topic}),
}) }}
```

The Stimulus controller `game-table` reads the view from `view_url`, subscribes to `hub_url` when it is given, and reads
the view again at each update whose number of moves differs from the page's — a later move, or one taken back. It shows
the number of moves, the phase, the round and the seats that may act in their targets as text, and dispatches the
answer as the `game-table:view` event; the application renders the view of its game by listening to it:

```js
element.addEventListener('tilttek--game-engine-bundle--game-table:view', (event) => render(event.detail.view));
```

| Twig function | What it gives |
|---|---|
| `tilttek_game_table(table)` | What anyone at the table may know, never the game's data |
| `tilttek_game_engine_kit(recipe)` | The name a recipe of the kit is installed under |
| `tilttek_game_engine_domain()` | The translation domain of the template |

The template's texts are in the `tilttek_game_engine` domain, in English and French; a game's name is
`games.<name>`.
