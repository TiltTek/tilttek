# Configuration

```yaml
tilttek_escape_room:
    store: doctrine                 # or memory, for tests
    connection: default             # the Doctrine DBAL connection
    rooms:
        crypt:
            title: The crypt
            duration: 3600          # seconds, 60 to 86 400
            hint_penalty: 120       # seconds a hint adds to the final time, unless its own penalty says otherwise
            skip_penalty: 300       # seconds a puzzle skipped by the host adds
            min_players: 2
            max_players: 6          # the seats of a session
            scenario:
                id: crypt
                finale: tomb
                puzzles:
                    - { id: candle, title: The candle, answers: [wick], hints: [{ text: Look at the top }, { text: It burns, penalty: 30 }] }
                    - { id: crest, title: The crest, answers: [lion], unlock: { requires: [candle] } }
                    - { id: tomb, title: The tomb, answers: ['1347'], unlock: { requires: [crest] } }
    room_catalog: ~                 # a RoomCatalog service, instead of the rooms above
    access: App\EscapeRoom\Access   # an EscapeRoomAccess service; every screen but the leaderboard is refused without it
    team_names: ~                   # a TeamNames service; a team is named by its place on the leaderboard without it
    reservations: ~                 # a SessionReservations service of the application
    room_bookables: ~               # a RoomBookables service: the opening hours and rules of each room on phil/reservation
    guesses: { limit: 10, seconds: 60 }
    guess_limiter: ~                # a GuessLimiter service, instead of the limit above
    schedule:
        enabled: false
        frequency: 15 seconds
        batch: 100                  # games ended in one run, 1 to 1 000
    mercure:
        enabled: true
        hub: ~                      # the default hub when Mercure is installed
        hub_name: ~                 # the hub of MercureBundle whose cookie the viewers are given
        topic_prefix: tilttek-escape-room
        poll: 5                     # seconds between two readings of a screen without a hub
    layout: base.html.twig          # the application's template the screens extend, with a block "body"
    leaderboard_size: 10
    kit_subdirectory: ~             # the components subdirectory of the kit; that of ux_toolkit when null
    celebration:
        enabled: true
        effect: confetti            # or fireworks
    translation_domain: tilttek_escape_room
    privacy:
        member_kind: member
```

| Option | Default | What it does |
|---|---|---|
| `store` | `doctrine` | Where the games, the results, the screens and the bookings are kept: Doctrine DBAL, or `memory` for tests. |
| `connection` | `default` | The Doctrine DBAL connection. |
| `rooms` | none | The rooms by identifier; the scenario as `tilttek/puzzle-builder` reads it with `Scenario::fromArray()`. |
| `room_catalog` | null | A service of `RoomCatalog` that holds the rooms instead: from a database, for instance. |
| `access` | null | A service of `EscapeRoomAccess`. |
| `team_names` | null | A service of `TeamNames`, the names the leaderboard shows. |
| `reservations` | null | A service of `Tilttek\EscapeRoom\Contract\SessionReservations`; otherwise those on `phil/reservation` when it is installed, else the memory store. |
| `room_bookables` | null | A service of `Tilttek\EscapeRoom\Reservation\Contract\RoomBookables`. |
| `guesses` | 10 in 60 seconds | The guesses a team makes on a puzzle in a window, on Symfony Rate Limiter over `cache.app` when it is installed, in memory otherwise. |
| `guess_limiter` | null | A service of `Tilttek\PuzzleBuilder\Contract\GuessLimiter`. |
| `schedule` | disabled | The schedule `tilttek_escape_room`, its frequency and batch. |
| `mercure` | enabled | The hub the screens are told of the moves through. |
| `layout` | `base.html.twig` | The template the screens extend. |
| `leaderboard_size` | 10 | The places of the public leaderboard, 1 to 1 000. |
| `kit_subdirectory` | null | Where the recipes of the kit are installed. |
| `celebration` | enabled, `confetti` | The celebration of an escape on the hint screen. |
| `translation_domain` | `tilttek_escape_room` | The domain of the texts of the screens. |
| `privacy.member_kind` | `member` | The kind of the reference a data subject of `phil/privacy-bundle` stands for. |

The rooms are checked when the catalog is first built: a room or a scenario the library refuses throws `InvalidRoom` or
`InvalidScenario`.
