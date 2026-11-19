# Screens

The routes of `config/routes.php`:

| Route | Path | Who |
|---|---|---|
| `tilttek_escape_room_console` | `GET /escape-room/console/{game}` | The host |
| `tilttek_escape_room_console_act` | `POST /escape-room/console/{game}/{action}` | The host |
| `tilttek_escape_room_screen` | `GET /escape-room/screen/{game}` | Who watches the game |
| `tilttek_escape_room_screen_state` | `GET /escape-room/screen/{game}/state` | Who watches the game |
| `tilttek_escape_room_consent` | `POST /escape-room/games/{game}/consent` | The team, or the host |
| `tilttek_escape_room_leaderboard` | `GET /escape-room/leaderboard/{room}` | Everyone |

`EscapeRoomAccess` answers who is who; a viewer it refuses gets a 403, an unknown game a 404. Every answer but the
leaderboard is private and never stored by a cache; the leaderboard is public for a minute. Each form carries a CSRF
token — `tilttek_escape_room_console` or `tilttek_escape_room_consent` — and is refused without it.

## The console

The host's page shows the countdown and what the hint screen shows, the totals — time played, penalties, hints and
skips —, and each puzzle with its status and hints revealed. For an open puzzle, a button pushes its next hint and says
how many are left, another skips it; a field sends a message of up to 280 characters to the hint screen; buttons clear
the screen, pause or resume the countdown, and end the game.

The actions are `hint` and `skip`, with the field `puzzle`; `message`, with the field `message`; `pause`, `resume`,
`clear` and `abandon`. Without a script, a form is answered by a redirection to the console, with the reason of a
refusal shown in an alert. With `Accept: application/json`, the answer is `{"action": "hint", "game": {…}}`, or
`{"error": "…"}` with the status 422.

The Stimulus controller `tilttek--escape-room--console` sends the forms marked
`data-action="tilttek--escape-room--console#send"` in the background, says what was done or why it was refused in a
polite live region, and reads the console again in place; it runs the countdown between two readings, and reads the
console again when the hub tells it the game moved — a hint the players asked — or every few seconds without a hub.

## The hint screen

The page shown in the room: the room's title, the countdown, the hint pushed — its puzzle, its text, the penalty it
added — or the message of the host, the outcome once the game is over, and the celebration of an escape. A viewer who
speaks for the team also sees whether its result is on the public leaderboard, and a button to change it.

The Stimulus controller `tilttek--escape-room--screen` reads the state — `GET …/state`, the same without the layout —
when the hub tells it the game moved, or every few seconds without a hub, and replaces the page's copy only when its
version changed. It says each hint or message as a whole sentence and the time left at chosen marks in a polite live
region, and dispatches on its element:

| Event | When | `detail` |
|---|---|---|
| `tilttek-escape-room:moved` | A new state is shown | none |
| `tilttek-escape-room:finished` | The game ended | `outcome`: `escaped`, `time-up` or `abandoned` |

The celebration is the `Celebration` recipe of the PHIL UX kit, with `autoplay`: it plays once when the new state of
an escape is shown, its message said in a polite live region, confetti or fireworks drawn only where motion is not
reduced. A page of the application may play its own on `tilttek-escape-room:finished`, or dispatch the kit's
`celebrate` event to a `Celebration` of its own:

```js
document.addEventListener('tilttek-escape-room:finished', (event) => {
    if ('escaped' === event.detail.outcome) {
        document.querySelector('#exit-celebration')?.dispatchEvent(new CustomEvent('celebrate'));
    }
});
```

## The leaderboard

A table with its caption and headers — place, team, final time, hints —, readable without a script. A team is named by
the name `TeamNames` gives, or by its place; never by a member.

## The templates

| Template | What it draws |
|---|---|
| `@TilttekEscapeRoom/console.html.twig` | The console |
| `@TilttekEscapeRoom/screen.html.twig` | The hint screen |
| `@TilttekEscapeRoom/_screen.html.twig` | The state of a game: the countdown, the hint or the message, the outcome, the celebration; `celebrate: false` draws no celebration |
| `@TilttekEscapeRoom/leaderboard.html.twig` | The public leaderboard |

Each extends the `layout` of the configuration. The Twig functions are `tilttek_escape_room_kit('Card:Title')`, the
name of a recipe as the application installed it; `tilttek_escape_room_domain()`, the translation domain; and
`tilttek_escape_room_clock(seconds)`, a duration as `42:07` or `1:02:07`. The texts are in English and French, under the
translation domain of the configuration.
