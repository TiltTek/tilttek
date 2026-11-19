<!-- phil-dev source-sha256=f6b9c22c0d5f7c566bc3793faf9d71c4181ac0e239081270d221f2ded7e0e228 -->
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

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `store` | `doctrine` | Où sont gardés les parties, les résultats, les écrans et les réservations : Doctrine DBAL, ou `memory` pour les tests. |
| `connection` | `default` | La connexion Doctrine DBAL. |
| `rooms` | aucune | Les salles par identifiant ; le scénario tel que `tilttek/puzzle-builder` le lit avec `Scenario::fromArray()`. |
| `room_catalog` | null | Un service de `RoomCatalog` qui détient les salles à la place : depuis une base de données, par exemple. |
| `access` | null | Un service de `EscapeRoomAccess`. |
| `team_names` | null | Un service de `TeamNames`, les noms que montre le classement. |
| `reservations` | null | Un service de `Tilttek\EscapeRoom\Contract\SessionReservations` ; sinon ceux sur `phil/reservation` quand il est installé, sinon le magasin en mémoire. |
| `room_bookables` | null | Un service de `Tilttek\EscapeRoom\Reservation\Contract\RoomBookables`. |
| `guesses` | 10 en 60 secondes | Les propositions qu'une équipe fait sur une énigme dans une fenêtre, sur Symfony Rate Limiter au-dessus de `cache.app` quand il est installé, en mémoire sinon. |
| `guess_limiter` | null | Un service de `Tilttek\PuzzleBuilder\Contract\GuessLimiter`. |
| `schedule` | désactivé | L'horaire `tilttek_escape_room`, sa fréquence et son lot. |
| `mercure` | activé | Le hub par lequel les écrans apprennent les coups. |
| `layout` | `base.html.twig` | Le gabarit que les écrans étendent. |
| `leaderboard_size` | 10 | Les places du classement public, de 1 à 1 000. |
| `kit_subdirectory` | null | Où sont installées les recettes du kit. |
| `celebration` | activée, `confetti` | La célébration d'une évasion sur l'écran d'indices. |
| `translation_domain` | `tilttek_escape_room` | Le domaine des textes des écrans. |
| `privacy.member_kind` | `member` | Le genre de la référence que représente une personne concernée de `phil/privacy-bundle`. |

Les salles sont vérifiées quand le catalogue est construit pour la première fois : une salle ou un scénario que la
bibliothèque refuse lance `InvalidRoom` ou `InvalidScenario`.
